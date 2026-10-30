import puppeteer from 'puppeteer-core';
import fs from 'fs';
import path from 'path';

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE_URL = 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = 'C:\\Users\\kieup\\.gemini\\antigravity\\scratch\\Vistiq-v1\\public\\screenshots';

const READING_CSV = 'C:\\Users\\kieup\\.gemini\\antigravity\\scratch\\Vistiq-v1\\resources\\import-templates\\questions\\vistiq-readings-with-questions-template.csv';
const LISTENING_XLSX = 'C:\\Users\\kieup\\.gemini\\antigravity\\scratch\\Vistiq-v1\\outputs\\toeic-listening-36\\vistiq-listening-question-bank-36.xlsx';
const THPT_XLSX = 'C:\\Users\\kieup\\.gemini\\antigravity\\scratch\\Vistiq-v1\\outputs\\vistiq-thpt-english-40-questions\\vistiq-thpt-english-40-questions.xlsx';

async function runAdminAndExamTests() {
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    defaultViewport: { width: 1440, height: 900 },
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1440,900']
  });

  const page = await browser.newPage();
  const consoleLogs = [];
  page.on('console', msg => consoleLogs.push(`[CONSOLE] ${msg.type()}: ${msg.text()}`));
  page.on('pageerror', err => consoleLogs.push(`[PAGE ERROR] ${err.toString()}`));

  try {
    console.log('=== STEP 1: ADMIN LOGIN ===');
    await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle2' });
    const emailInput = await page.$('#admin-email');
    const passInput = await page.$('#admin-password');
    if (emailInput && passInput) {
      await emailInput.type('admin@vistiq.local');
      await passInput.type('Password123!');
      const submitBtn = await page.$('button[type="submit"]');
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }).catch(() => {}),
        submitBtn.click()
      ]);
    }
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '15_admin_dashboard.png') });
    console.log('Saved 15_admin_dashboard.png');

    console.log('=== STEP 2: READING, LISTENING & THPT QUESTION BANK VIEWS ===');
    // 1. Reading bank
    await page.goto(`${BASE_URL}/admin/readings`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '16_admin_readings_list.png') });
    console.log('Saved 16_admin_readings_list.png');

    // 2. Listening bank
    await page.goto(`${BASE_URL}/admin/questions/listening`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '17_admin_listening_list.png') });
    console.log('Saved 17_admin_listening_list.png');

    // 3. THPT bank
    await page.goto(`${BASE_URL}/admin/questions/thpt`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '18_admin_thpt_list.png') });
    console.log('Saved 18_admin_thpt_list.png');

    console.log('=== STEP 3: IMPORT READING CSV QUESTIONS ===');
    await page.goto(`${BASE_URL}/admin/readings`, { waitUntil: 'networkidle2' });
    
    // Open CSV details panel if collapsed
    await page.evaluate(() => {
      const details = document.querySelector('details.csv-import');
      if (details) details.open = true;
    });

    const fileInput = await page.$('input#reading-csv-file');
    if (fileInput) {
      await fileInput.uploadFile(READING_CSV);
      console.log('Selected reading CSV file for upload');
      const importBtn = await page.$('form.csv-import-form button[type="submit"]');
      if (importBtn) {
        await Promise.all([
          page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }).catch(() => {}),
          importBtn.click()
        ]);
      }
    }
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '19_admin_question_imported.png') });
    console.log('Saved 19_admin_question_imported.png');

    console.log('=== STEP 4: CONTENT APPROVAL (DUYỆT CÂU HỎI) ===');
    await page.goto(`${BASE_URL}/admin/content-approval`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '20_admin_content_approval_queue.png') });
    console.log('Saved 20_admin_content_approval_queue.png');

    // Try approving items if available
    const approveResult = await page.evaluate(async () => {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      // Look for approve button or form
      const approveBtn = document.querySelector('[data-review-action="approve"], form[action*="approve"] button, button[data-action="approve"]');
      if (approveBtn) {
        approveBtn.click();
        return 'clicked approve button in UI';
      }
      return 'no direct button, checked queue';
    });
    console.log('Approve action:', approveResult);
    await new Promise(r => setTimeout(r, 2000));
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '21_admin_content_approved.png') });
    console.log('Saved 21_admin_content_approved.png');

    console.log('=== STEP 5: TẠO ĐỀ & CẤU HÌNH CÁC CHẾ ĐỘ THI (EXAM BUILDER) ===');
    await page.goto(`${BASE_URL}/admin/exams/create`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '22_exam_builder_step1_templates.png') });
    console.log('Saved 22_exam_builder_step1_templates.png');

    // Select TOEIC Mini or Custom template and proceed to Step 2 (Configuration)
    await page.evaluate(() => {
      // Click TOEIC Mini Test card
      const miniCard = document.querySelector('[data-template-card="toeic-mini"]') || document.querySelector('.ex-template-card');
      if (miniCard) miniCard.click();
      // Click next step button
      const step2Btn = document.querySelector('[data-step-link="1"]');
      if (step2Btn) step2Btn.click();
    });
    await new Promise(r => setTimeout(r, 1000));
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '23_exam_builder_step2_config.png') });
    console.log('Saved 23_exam_builder_step2_config.png');

    // Go to Step 3: Question selection
    await page.evaluate(() => {
      const step3Btn = document.querySelector('[data-step-link="2"]');
      if (step3Btn) step3Btn.click();
    });
    await new Promise(r => setTimeout(r, 1000));
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '24_exam_builder_step3_questions.png') });
    console.log('Saved 24_exam_builder_step3_questions.png');

    // Go to Step 4: Preview & Publish
    await page.evaluate(() => {
      const step4Btn = document.querySelector('[data-step-link="3"]');
      if (step4Btn) step4Btn.click();
    });
    await new Promise(r => setTimeout(r, 1000));
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '25_exam_builder_preview.png') });
    console.log('Saved 25_exam_builder_preview.png');

  } catch (err) {
    console.error('Error in Admin & Exam tests:', err);
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'error_admin.png') });
  } finally {
    await browser.close();
  }
}

runAdminAndExamTests();
