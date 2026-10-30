import puppeteer from 'puppeteer-core';
import fs from 'fs';
import path from 'path';

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE_URL = 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = path.join(process.cwd(), 'public', 'screenshots');

if (!fs.existsSync(SCREENSHOT_DIR)) {
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

async function runPhase1And2() {
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    defaultViewport: { width: 1440, height: 900 },
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1440,900']
  });

  const page = await browser.newPage();

  try {
    console.log('--- 1. Test Login & Forgot Password ---');
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_login_page.png') });
    console.log('Saved 01_login_page.png');

    await page.goto(`${BASE_URL}/forgot-password`, { waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_forgot_password_page.png') });
    console.log('Saved 02_forgot_password_page.png');

    const emailInput = await page.$('input[name="email"]');
    if (emailInput) {
      await emailInput.type('tienben9123@gmail.com');
      const submitBtn = await page.$('button[type="submit"]');
      if (submitBtn) {
        await Promise.all([
          page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 10000 }).catch(() => {}),
          submitBtn.click()
        ]);
      }
    }
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_forgot_password_submitted.png') });
    console.log('Saved 03_forgot_password_submitted.png');

    console.log('--- 2. Test Registration ---');
    await page.goto(`${BASE_URL}/register`, { waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_register_page.png') });
    console.log('Saved 04_register_page.png');

    const randomNum = Math.floor(Math.random() * 90000) + 10000;
    const testEmail = `student_${randomNum}@vistiq.local`;
    
    const nameInput = await page.$('input[name="name"]');
    const regEmailInput = await page.$('input[name="email"]');
    const regPassInput = await page.$('input[name="password"]');
    const regPassConfirm = await page.$('input[name="password_confirmation"]');

    if (nameInput && regEmailInput && regPassInput && regPassConfirm) {
      await nameInput.type(`Test Student ${randomNum}`);
      await regEmailInput.type(testEmail);
      await regPassInput.type('Password123!');
      await regPassConfirm.type('Password123!');
      
      const regSubmit = await page.$('button[type="submit"]');
      if (regSubmit) {
        await Promise.all([
          page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 10000 }).catch(() => {}),
          regSubmit.click()
        ]);
      }
    }
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_register_success.png') });
    console.log('Saved 05_register_success.png');

    console.log('--- 3. Test Login with User ---');
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
    const loginEmail = await page.$('input[name="email"]');
    const loginPass = await page.$('input[name="password"]');
    if (loginEmail && loginPass) {
      await loginEmail.type(testEmail);
      await loginPass.type('Password123!');
      const loginBtn = await page.$('button[type="submit"]');
      if (loginBtn) {
        await Promise.all([
          page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 10000 }).catch(() => {}),
          loginBtn.click()
        ]);
      }
    }
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '06_user_logged_in.png') });
    console.log('Saved 06_user_logged_in.png');

    console.log('--- 4. Test Tra cứu & Tìm kiếm từng tab ---');
    await page.goto(`${BASE_URL}/lookup`, { waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '07_lookup_home.png') });
    console.log('Saved 07_lookup_home.png');

    await page.goto(`${BASE_URL}/lookup?q=english`, { waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '08_lookup_search_results.png') });
    console.log('Saved 08_lookup_search_results.png');

    await page.goto(`${BASE_URL}/lookup/english`, { waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '09_lookup_tab_overview.png') });
    console.log('Saved 09_lookup_tab_overview.png');

    await page.goto(`${BASE_URL}/lookup/english?tab=collocations`, { waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '10_lookup_tab_collocations.png') });
    console.log('Saved 10_lookup_tab_collocations.png');

    await page.goto(`${BASE_URL}/lookup/english?tab=examples`, { waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '11_lookup_tab_examples.png') });
    console.log('Saved 11_lookup_tab_examples.png');

    console.log('--- 5. Test Lưu vô kho kiến thức ---');
    const saveResponse = await page.evaluate(async () => {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const res = await fetch('/knowledge/english/save', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json'
        }
      });
      return { status: res.status, data: await res.json().catch(() => null) };
    });
    console.log('Save response:', saveResponse);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '12_saved_to_knowledge.png') });
    console.log('Saved 12_saved_to_knowledge.png');

    console.log('--- 6. Test Xem kho kiến thức ---');
    await page.goto(`${BASE_URL}/knowledge/saved`, { waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '13_knowledge_saved_list.png') });
    console.log('Saved 13_knowledge_saved_list.png');

    console.log('--- 7. Test Remove khỏi kho kiến thức ---');
    const unsaveResponse = await page.evaluate(async () => {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const res = await fetch('/knowledge/english/save', {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json'
        }
      });
      return { status: res.status, data: await res.json().catch(() => null) };
    });
    console.log('Unsave response:', unsaveResponse);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '14_knowledge_unsaved.png') });
    console.log('Saved 14_knowledge_unsaved.png');

  } catch (err) {
    console.error('Error during test:', err);
  } finally {
    await browser.close();
  }
}

runPhase1And2();
