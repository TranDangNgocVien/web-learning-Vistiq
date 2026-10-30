import puppeteer from 'puppeteer-core';
import path from 'path';

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE_URL = 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = 'C:\\Users\\kieup\\.gemini\\antigravity\\scratch\\Vistiq-v1\\public\\screenshots';

async function testResultAndHistory() {
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    defaultViewport: { width: 1440, height: 900 },
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1440,900']
  });

  const page = await browser.newPage();

  try {
    console.log('Login as learner...');
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
    const emailInput = await page.$('input[name="email"]');
    const passInput = await page.$('input[name="password"]');
    if (emailInput && passInput) {
      await emailInput.type('tienben9123@gmail.com');
      await passInput.type('Password123!');
      const submitBtn = await page.$('button[type="submit"]');
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 10000 }).catch(() => {}),
        submitBtn.click()
      ]);
    }

    console.log('Navigating to result page: /attempts/1/result');
    await page.goto(`${BASE_URL}/attempts/1/result`, { waitUntil: 'domcontentloaded' });
    await new Promise(r => setTimeout(r, 2000));
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '28_exam_result_summary.png') });
    console.log('Saved 28_exam_result_summary.png');

    console.log('Navigating to leaderboard: /exams/1/leaderboard');
    await page.goto(`${BASE_URL}/exams/1/leaderboard`, { waitUntil: 'domcontentloaded' });
    await new Promise(r => setTimeout(r, 2000));
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '29_exam_leaderboard.png') });
    console.log('Saved 29_exam_leaderboard.png');

    console.log('Navigating to history: /attempts/history');
    await page.goto(`${BASE_URL}/attempts/history`, { waitUntil: 'domcontentloaded' });
    await new Promise(r => setTimeout(r, 2000));
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '30_learner_attempt_history.png') });
    console.log('Saved 30_learner_attempt_history.png');

  } catch (err) {
    console.error('Error:', err);
  } finally {
    await browser.close();
  }
}

testResultAndHistory();
