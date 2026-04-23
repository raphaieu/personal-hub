import fs from "node:fs/promises";
import path from "node:path";
import { chromium } from "playwright";

const DEFAULT_SESSION_PATH =
  process.env.THREADS_SESSION_PATH || path.resolve(process.cwd(), "storage/threads-session.json");
const HEADLESS = process.env.PLAYWRIGHT_HEADLESS !== "false";
const USER_AGENT =
  process.env.THREADS_USER_AGENT ||
  "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36";

const RANDOM_DELAY_MIN_MS = Number(process.env.THREADS_RANDOM_DELAY_MIN_MS || 250);
const RANDOM_DELAY_MAX_MS = Number(process.env.THREADS_RANDOM_DELAY_MAX_MS || 700);
const DEFAULT_TIMEOUT_MS = Number(process.env.THREADS_STEP_TIMEOUT_MS || 30000);
const DEBUG_DIR = process.env.THREADS_DEBUG_DIR || path.resolve(process.cwd(), "downloads/threads-debug");

function randomInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

export async function humanDelay() {
  const ms = randomInt(RANDOM_DELAY_MIN_MS, RANDOM_DELAY_MAX_MS);
  await new Promise((resolve) => setTimeout(resolve, ms));
}

async function ensureDir(filePath) {
  await fs.mkdir(path.dirname(filePath), { recursive: true });
}

async function ensureDirectory(dirPath) {
  await fs.mkdir(dirPath, { recursive: true });
}

async function pathExists(filePath) {
  try {
    await fs.access(filePath);
    return true;
  } catch {
    return false;
  }
}

export async function hasSessionFile() {
  return pathExists(DEFAULT_SESSION_PATH);
}

export async function createBrowser() {
  return chromium.launch({
    headless: HEADLESS,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
    executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined,
  });
}

async function fillFirstVisible(page, selectors, value) {
  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if ((await locator.count()) > 0) {
      await locator.fill(value);
      return true;
    }
  }

  return false;
}

async function pressEnterOnFirstVisible(page, selectors) {
  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if ((await locator.count()) > 0) {
      await locator.press("Enter");
      return true;
    }
  }

  return false;
}

async function captureLoginDebugScreenshot(page, reason = "login-error") {
  await ensureDirectory(DEBUG_DIR);
  const screenshotPath = path.join(DEBUG_DIR, `${reason}-${Date.now()}.png`);
  await page.screenshot({ path: screenshotPath, fullPage: true });
  return screenshotPath;
}

export async function performThreadsLogin({ forceRelogin = false } = {}) {
  const username = process.env.THREADS_USERNAME;
  const password = process.env.THREADS_PASSWORD;

  if (!username || !password) {
    throw new Error("THREADS_USERNAME e THREADS_PASSWORD são obrigatórios para login.");
  }

  const sessionExists = await hasSessionFile();
  if (sessionExists && !forceRelogin) {
    return {
      sessionPath: DEFAULT_SESSION_PATH,
      reused: true,
    };
  }

  const browser = await createBrowser();
  const context = await browser.newContext({
    userAgent: USER_AGENT,
    viewport: { width: 1400, height: 900 },
  });

  try {
    const page = await context.newPage();
    page.setDefaultTimeout(DEFAULT_TIMEOUT_MS);

    await page.goto("https://www.threads.net/login", { waitUntil: "domcontentloaded" });
    await humanDelay();

    const usernameFilled = await fillFirstVisible(
      page,
      [
        'input[name="username"]',
        'input[name="email"]',
        'input[autocomplete="email"]',
        'input[autocomplete="username"]',
        'input[aria-label*="username" i]',
        'input[aria-label*="email" i]',
        'input[placeholder*="email" i]',
        'input[placeholder*="username" i]',
        'input[placeholder*="usuário" i]',
      ],
      username,
    );

    const passwordFilled = await fillFirstVisible(
      page,
      [
        'input[name="password"]',
        'input[type="password"]',
        'input[autocomplete="current-password"]',
        'input[aria-label*="password" i]',
        'input[placeholder*="password" i]',
        'input[placeholder*="senha" i]',
      ],
      password,
    );

    if (!usernameFilled || !passwordFilled) {
      const screenshotPath = await captureLoginDebugScreenshot(page, "login-fields-not-found");
      throw new Error(`Não foi possível localizar campos de login do Threads. Screenshot: ${screenshotPath}`);
    }

    await humanDelay();
    const submitButton = page
      .locator(
        [
          'button[type="submit"]',
          'button:has-text("Log in")',
          'button:has-text("Entrar")',
          'button:has-text("Sign in")',
          'div[role="button"]:has-text("Log in")',
          'div[role="button"]:has-text("Entrar")',
        ].join(", "),
      )
      .first();

    let submitAttempted = false;
    if ((await submitButton.count()) > 0) {
      await submitButton.click();
      submitAttempted = true;
    }

    if (!submitAttempted) {
      const enterPressed = await pressEnterOnFirstVisible(page, [
        'input[name="password"]',
        'input[type="password"]',
        'input[autocomplete="current-password"]',
      ]);
      submitAttempted = enterPressed;
    }

    if (!submitAttempted) {
      const screenshotPath = await captureLoginDebugScreenshot(page, "login-submit-not-found");
      throw new Error(`Botão de login não encontrado e fallback Enter falhou. Screenshot: ${screenshotPath}`);
    }

    await Promise.race([
      page.waitForURL((url) => !url.toString().includes("/login"), { timeout: DEFAULT_TIMEOUT_MS }),
      page.waitForSelector('a[href*="/@"]', { timeout: DEFAULT_TIMEOUT_MS }),
    ]);

    await ensureDir(DEFAULT_SESSION_PATH);
    await context.storageState({ path: DEFAULT_SESSION_PATH });

    return {
      sessionPath: DEFAULT_SESSION_PATH,
      reused: false,
    };
  } finally {
    await context.close();
    await browser.close();
  }
}

export async function createAuthenticatedContext(browser) {
  const hasSession = await hasSessionFile();

  if (!hasSession) {
    await performThreadsLogin();
  }

  return browser.newContext({
    storageState: DEFAULT_SESSION_PATH,
    userAgent: USER_AGENT,
    viewport: { width: 1400, height: 900 },
  });
}

export function getSessionPath() {
  return DEFAULT_SESSION_PATH;
}
