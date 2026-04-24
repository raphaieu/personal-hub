import fs from "node:fs/promises";
import path from "node:path";
import { chromium } from "playwright";

const HEADLESS = process.env.PLAYWRIGHT_HEADLESS !== "false";
const SHUTDOWN_TIMEOUT_MS = Number(process.env.PLAYWRIGHT_SHUTDOWN_TIMEOUT_MS || 5000);
const DEFAULT_TIMEOUT_MS = Number(process.env.PLAYWRIGHT_STEP_TIMEOUT_MS || 30000);
const DEFAULT_USER_AGENT =
  process.env.PLAYWRIGHT_USER_AGENT ||
  "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36";

const UTILITY_SESSION_PATHS = {
  embasa:
    process.env.EMBASA_SESSION_PATH || path.resolve(process.cwd(), "storage/embasa-session.json"),
  coelba:
    process.env.COELBA_SESSION_PATH || path.resolve(process.cwd(), "storage/coelba-session.json"),
};

export function nowIso() {
  return new Date().toISOString();
}

export function getUtilitySessionPath(provider) {
  return UTILITY_SESSION_PATHS[provider];
}

export async function pathExists(filePath) {
  try {
    await fs.access(filePath);
    return true;
  } catch {
    return false;
  }
}

export async function hasUtilitySession(provider) {
  return pathExists(getUtilitySessionPath(provider));
}

export async function getUtilitySessionStates() {
  const embasaPath = getUtilitySessionPath("embasa");
  const coelbaPath = getUtilitySessionPath("coelba");

  return {
    embasa: {
      ready: await pathExists(embasaPath),
      path: embasaPath,
    },
    coelba: {
      ready: await pathExists(coelbaPath),
      path: coelbaPath,
    },
  };
}

export async function ensureFileDir(filePath) {
  await fs.mkdir(path.dirname(filePath), { recursive: true });
}

export async function createBrowser() {
  return chromium.launch({
    headless: HEADLESS,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
    executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined,
  });
}

export async function createContext(browser, { storageStatePath = null } = {}) {
  const options = {
    userAgent: DEFAULT_USER_AGENT,
    viewport: { width: 1400, height: 900 },
    locale: "pt-BR",
    timezoneId: "America/Sao_Paulo",
  };

  if (storageStatePath) {
    options.storageState = storageStatePath;
  }

  const context = await browser.newContext(options);
  await context.addInitScript(() => {
    Object.defineProperty(navigator, "webdriver", {
      get: () => undefined,
    });
  });

  return context;
}

export function setDefaultTimeout(page) {
  page.setDefaultTimeout(DEFAULT_TIMEOUT_MS);
}

export async function saveContextStorageState(context, provider) {
  const sessionPath = getUtilitySessionPath(provider);
  await ensureFileDir(sessionPath);
  await context.storageState({ path: sessionPath });
  return sessionPath;
}

export async function withBrowserContext(action, { storageStatePath = null } = {}) {
  const browser = await createBrowser();
  const context = await createContext(browser, { storageStatePath });

  try {
    return await action({ browser, context });
  } finally {
    await safeCloseContext(context);
    await safeCloseBrowser(browser);
  }
}

export async function safeCloseContext(context) {
  if (!context) {
    return;
  }

  try {
    await Promise.race([
      context.close(),
      new Promise((_, reject) =>
        setTimeout(() => reject(new Error("Context close timeout")), SHUTDOWN_TIMEOUT_MS),
      ),
    ]);
  } catch {
    // best effort
  }
}

export async function safeCloseBrowser(browser) {
  if (!browser) {
    return;
  }

  try {
    await Promise.race([
      browser.close(),
      new Promise((_, reject) =>
        setTimeout(() => reject(new Error("Browser close timeout")), SHUTDOWN_TIMEOUT_MS),
      ),
    ]);
  } catch {
    try {
      browser?.process()?.kill("SIGKILL");
    } catch {
      // best effort
    }
  }
}
