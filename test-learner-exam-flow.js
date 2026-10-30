import puppeteer from 'puppeteer-core';
import fs from 'fs';
import path from 'path';

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE_URL = 'http://127.0.0.1:8000';
const SCREENSHOT_DIR = 'C:\\Users\\kieup\\.gemini\\antigravity\\scratch\\Vistiq-v1\\public\\screenshots';

async function runLearnerExamFlow() {
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    defaultViewport: { width: 1440, height: 900 },
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1440,900']
  });

  const page = await browser.newPage();
  const consoleLogs = [];
  page.on('console', msg => consoleLogs.push(`[LEARNER LOG] ${msg.type()}: ${msg.text()}`));
  page.on('pageerror', err => consoleLogs.push(`[LEARNER ERROR] ${err.toString()}`));

  try {
    console.log('=== STEP 1: LOGIN AS LEARNER ===');
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
    const emailInput = await page.$('input[name="email"]');
    const passInput = await page.$('input[name="password"]');
    if (emailInput && passInput) {
      await emailInput.type('tienben9123@gmail.com');
      await passInput.type('Password123!');
      const submitBtn = await page.$('button[type="submit"]');
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }).catch(() => {}),
        submitBtn.click()
      ]);
    }
    console.log('Learner logged in successfully');

    console.log('=== STEP 2: VIEW EXAM CATALOG ===');
    await page.goto(`${BASE_URL}/exams`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '26_learner_exams_catalog.png') });
    console.log('Saved 26_learner_exams_catalog.png');

    console.log('=== STEP 3: START EXAM ATTEMPT ===');
    // Start exam 1
    const startUrl = await page.evaluate(async () => {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const res = await fetch('/exams/1/start', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        }
      });
      const data = await res.json();
      return data.url || `/attempts/${data.id}`;
    });
    console.log('Started exam attempt, target url:', startUrl);

    const targetNavigateUrl = startUrl.startsWith('http') ? startUrl : `${BASE_URL}${startUrl}`;
    await page.goto(targetNavigateUrl, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '27_exam_room_in_progress.png') });
    console.log('Saved 27_exam_room_in_progress.png');

    console.log('=== STEP 4: ANSWER QUESTIONS IN EXAM ROOM ===');
    // Interact with questions: choose options, mark review later, navigate
    await page.evaluate(() => {
      // Find options / radio inputs and click one
      const options = document.querySelectorAll('input[type="radio"], .question-option, [data-option-key], label.option-label');
      if (options.length > 0) {
        options[0].click();
      }
      // Click review later if available
      const flagBtn = document.querySelector('[data-mark-review], .flag-button, [data-action="review_later"]');
      if (flagBtn) {
        flagBtn.click();
      }
    });
    await new Promise(r => setTimeout(r, 1000));
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '27b_exam_question_answered.png') });
    console.log('Saved 27b_exam_question_answered.png');

    console.log('=== STEP 5: SUBMIT EXAM ===');
    // Submit the attempt
    const submitResult = await page.evaluate(async () => {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const currentUrl = window.location.pathname;
      const attemptId = currentUrl.match(/\/attempts\/(\d+)/)?.[1];
      if (!attemptId) return { error: 'No attempt ID found' };

      const res = await fetch(`/attempts/${attemptId}/submit`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        }
      });
      return { status: res.status, attemptId, data: await res.json().catch(() => null) };
    });
    console.log('Submit result:', submitResult);

    // Go to result page
    if (submitResult.attemptId) {
      await page.goto(`${BASE_URL}/attempts/${submitResult.attemptId}/result`, { waitUntil: 'networkidle2' });
      await page.screenshot({ path: path.join(SCREENSHOT_DIR, '28_exam_result_summary.png') });
      console.log('Saved 28_exam_result_summary.png');
    }

    console.log('=== STEP 6: VIEW LEADERBOARD ===');
    await page.goto(`${BASE_URL}/exams/1/leaderboard`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '29_exam_leaderboard.png') });
    console.log('Saved 29_exam_leaderboard.png');

    console.log('=== STEP 7: VIEW ATTEMPT HISTORY ===');
    await page.goto(`${BASE_URL}/attempts/history`, { waitUntil: 'networkidle2' });
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '30_learner_attempt_history.png') });
    console.log('Saved 30_learner_attempt_history.png');

  } catch (err) {
    console.error('Error during learner exam flow:', err);
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'error_learner_exam.png') });
  } finally {
    if (consoleLogs.length > 0) {
      console.log('Console logs sample:', consoleLogs.slice(0, 10));
    }
    await browser.close();
  }
}

runLearnerExamFlow();
