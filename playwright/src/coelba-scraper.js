import path from "node:path";
import fs from "node:fs/promises";
import {
  getUtilitySessionPath,
  hasUtilitySession,
  nowIso,
  saveContextStorageState,
  setDefaultTimeout,
  withBrowserContext,
} from "./utility-auth.js";

const COELBA_LOGIN_URL = "https://agenciavirtual.neoenergia.com/#/login";
const COELBA_DEBITOS_URL = "https://agenciavirtual.neoenergia.com/#/home/servicos/consultar-debitos";
const DOWNLOADS_DIR = process.env.PLAYWRIGHT_DOWNLOADS_DIR || path.resolve(process.cwd(), "playwright/downloads");
const DEBUG_DIR =
  process.env.PLAYWRIGHT_DEBUG_DIR || path.resolve(process.cwd(), "playwright/downloads/debug");

function normalizeText(value) {
  return (value || "").replace(/\s+/g, " ").trim();
}

function normalizeMoney(value) {
  if (typeof value !== "string") {
    return null;
  }

  const cleaned = value.replace(/[^\d,.-]/g, "").replace(/\./g, "").replace(",", ".");
  const parsed = Number(cleaned);
  return Number.isFinite(parsed) ? parsed : null;
}

function normalizeDate(value) {
  if (typeof value !== "string") {
    return null;
  }

  const match = value.trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
  if (!match) {
    return null;
  }

  const [, dd, mm, yyyy] = match;
  return `${yyyy}-${mm}-${dd}`;
}

function mapCoelbaStatus(rawStatus) {
  const status = normalizeText(rawStatus).toLowerCase();
  if (status.includes("a vencer")) {
    return "a_vencer";
  }

  if (status.includes("vencida")) {
    return "vencida";
  }

  return "pago";
}

async function slowStep(page, ms = 800) {
  await page.waitForTimeout(ms);
}

async function fillFirst(page, selectors, value) {
  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if ((await locator.count()) > 0) {
      await locator.fill(value);
      return true;
    }
  }

  return false;
}

async function clickFirst(page, selectors) {
  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if ((await locator.count()) > 0) {
      await locator.click();
      return true;
    }
  }

  return false;
}

async function captureDebugScreenshot(page, prefix) {
  await fs.mkdir(DEBUG_DIR, { recursive: true });
  const filePath = path.join(DEBUG_DIR, `${prefix}-${Date.now()}.png`);
  await page.screenshot({ path: filePath, fullPage: true });
  return filePath;
}

async function isAccessDeniedPage(page) {
  const title = (await page.title()).toLowerCase();
  const body = (await page.textContent("body").catch(() => ""))?.toLowerCase() || "";
  return (
    title.includes("access denied") ||
    body.includes("access denied") ||
    body.includes("you don't have permission") ||
    body.includes("akamai")
  );
}

async function solveRecaptchaV3() {
  const apiKey = process.env.CAPSOLVER_API_KEY;
  if (!apiKey) {
    return null;
  }

  const createResponse = await fetch("https://api.capsolver.com/createTask", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      clientKey: apiKey,
      task: {
        type: "ReCaptchaV3TaskProxyLess",
        websiteURL: "https://agenciavirtual.neoenergia.com",
        websiteKey: process.env.COELBA_RECAPTCHA_SITE_KEY || "6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI",
        pageAction: "login",
        minScore: 0.5,
      },
    }),
  });
  const createJson = await createResponse.json();
  if (createJson.errorId !== 0 || !createJson.taskId) {
    return null;
  }

  const taskId = createJson.taskId;
  for (let attempt = 0; attempt < 24; attempt += 1) {
    await new Promise((resolve) => setTimeout(resolve, 1500));
    const resultResponse = await fetch("https://api.capsolver.com/getTaskResult", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        clientKey: apiKey,
        taskId,
      }),
    });
    const resultJson = await resultResponse.json();
    if (resultJson.status === "ready" && resultJson.solution?.gRecaptchaResponse) {
      return resultJson.solution.gRecaptchaResponse;
    }
  }

  return null;
}

async function injectRecaptchaToken(page, token) {
  if (!token) {
    return;
  }

  await page.evaluate((value) => {
    const textarea = document.querySelector('textarea[name="g-recaptcha-response"]');
    if (textarea) {
      textarea.value = value;
      textarea.dispatchEvent(new Event("change", { bubbles: true }));
    }
  }, token);
}

async function selectCoelbaBahiaState(page) {
  await page.waitForURL(/#\/home\/selecionar-estado/i, { timeout: 45000 });
  await slowStep(page, 900);

  const quickClicked = await clickFirst(page, [
    'button:has-text("Bahia")',
    'a:has-text("Bahia")',
    'div.card-wrapper:has-text("Bahia")',
    'div.card:has-text("Bahia")',
    'text=Bahia',
  ]);

  if (quickClicked) {
    await slowStep(page, 900);
    return;
  }

  const clickedByScript = await page.evaluate(() => {
    const normalize = (value) => (value || "").replace(/\s+/g, " ").trim().toLowerCase();
    const nodes = Array.from(
      document.querySelectorAll("div.card-wrapper, div.card, a.link, button, [role='button']"),
    );

    const target = nodes.find((node) => normalize(node.textContent).includes("bahia"));
    if (!target) {
      return false;
    }

    target.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
    return true;
  });

  if (!clickedByScript) {
    const screenshotPath = await captureDebugScreenshot(page, "coelba-state-bahia-not-found");
    throw new Error(`Não foi possível selecionar o estado Bahia na Coelba. Screenshot: ${screenshotPath}`);
  }

  await slowStep(page, 900);
}

async function selectCoelbaCustomerUnit(page, customerCode) {
  if (!customerCode) {
    throw new Error("COELBA_CODIGO_CLIENTE é obrigatório para selecionar unidade consumidora.");
  }

  await page.waitForURL(/#\/home\/meus-imoveis/i, { timeout: 45000 });
  await slowStep(page, 1200);

  const quickClicked = await clickFirst(page, [
    `text=${customerCode}`,
    `div:has-text("${customerCode}")`,
    `button:has-text("${customerCode}")`,
    `a:has-text("${customerCode}")`,
  ]);

  if (quickClicked) {
    await slowStep(page, 1800);
    try {
      await page.waitForURL(/#\/home($|\/)|#\/home\/servicos/i, { timeout: 25000 });
    } catch {
      // Alguns fluxos permanecem em meus-imoveis; seguimos com fallback de clique de card.
    }
    return;
  }

  const clickedByScript = await page.evaluate((code) => {
    const normalize = (value) => (value || "").replace(/\s+/g, "").trim().toLowerCase();
    const targetCode = normalize(code);
    const nodes = Array.from(
      document.querySelectorAll(
        "app-uic-item, .item, .lista-ucs, .lista-ucs li, div, button, a, [role='button']",
      ),
    );

    const target = nodes.find((node) => normalize(node.textContent).includes(targetCode));
    if (!target) {
      return false;
    }

    target.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
    return true;
  }, customerCode);

  if (!clickedByScript) {
    const screenshotPath = await captureDebugScreenshot(page, "coelba-customer-unit-not-found");
    throw new Error(
      `Não foi possível selecionar a unidade consumidora (${customerCode}) na Coelba. Screenshot: ${screenshotPath}`,
    );
  }

  await slowStep(page, 1800);
  try {
    await page.waitForURL(/#\/home($|\/)|#\/home\/servicos/i, { timeout: 25000 });
  } catch {
    // fallback no fluxo chamador.
  }
}

async function coelbaLoginAndSession(context) {
  const cpf = process.env.COELBA_CPF;
  const password = process.env.COELBA_PASSWORD;

  if (!cpf || !password) {
    throw new Error("COELBA_CPF e COELBA_PASSWORD são obrigatórios para scraping da Coelba.");
  }

  const page = await context.newPage();
  setDefaultTimeout(page);
  await page.goto(COELBA_LOGIN_URL, { waitUntil: "domcontentloaded" });
  await slowStep(page, 1200);

  if (await isAccessDeniedPage(page)) {
    const screenshotPath = await captureDebugScreenshot(page, "coelba-access-denied-before-login");
    throw new Error(`Coelba bloqueou acesso (anti-bot/WAF) antes do login. Screenshot: ${screenshotPath}`);
  }

  await clickFirst(page, ['button:has-text("LOGIN")', 'a:has-text("LOGIN")']);
  await slowStep(page, 700);

  const cpfFilled = await fillFirst(
    page,
    ['input[placeholder*="CPF" i]', 'input[name="documento"]', 'input[type="text"]'],
    cpf,
  );
  const passwordFilled = await fillFirst(page, ['input[type="password"]'], password);
  if (!cpfFilled || !passwordFilled) {
    const screenshotPath = await captureDebugScreenshot(page, "coelba-login-fields-not-found");
    throw new Error(`Campos de login da Coelba não encontrados. Screenshot: ${screenshotPath}`);
  }

  const recaptchaToken = await solveRecaptchaV3();
  if (recaptchaToken) {
    await injectRecaptchaToken(page, recaptchaToken);
    await slowStep(page, 600);
  }

  const clicked = await clickFirst(page, ['button:has-text("ENTRAR")', 'button[type="submit"]']);
  if (!clicked) {
    const screenshotPath = await captureDebugScreenshot(page, "coelba-login-submit-not-found");
    throw new Error(`Botão ENTRAR da Coelba não encontrado. Screenshot: ${screenshotPath}`);
  }

  try {
    await page.waitForURL(/#\/home\/selecionar-estado|#\/home/i, { timeout: 45000 });
    await slowStep(page, 1200);
  } catch {
    if (await isAccessDeniedPage(page)) {
      const screenshotPath = await captureDebugScreenshot(page, "coelba-access-denied-after-login");
      throw new Error(`Coelba bloqueou acesso após login (anti-bot/WAF). Screenshot: ${screenshotPath}`);
    }

    const screenshotPath = await captureDebugScreenshot(page, "coelba-login-timeout");
    throw new Error(`Timeout na navegação pós-login da Coelba. Screenshot: ${screenshotPath}`);
  }

  if (!/#\/home\/selecionar-estado/i.test(page.url())) {
    await page.goto("https://agenciavirtual.neoenergia.com/#/home/selecionar-estado", {
      waitUntil: "domcontentloaded",
    });
  }

  await selectCoelbaBahiaState(page);
  const customerCode = process.env.COELBA_CODIGO_CLIENTE;
  await selectCoelbaCustomerUnit(page, customerCode);
  await slowStep(page, 1200);

  await saveContextStorageState(context, "coelba");
}

async function extractCoelbaInvoices(page) {
  const data = await page.evaluate(() => {
    const normalize = (value) => (value || "").replace(/\s+/g, " ").trim();
    const rows = Array.from(document.querySelectorAll("table tbody tr"));

    return rows.map((row) => {
      const cells = Array.from(row.querySelectorAll("td")).map((cell) => normalize(cell.textContent || ""));
      return {
        referencia: cells[0] || null,
        vencimento: cells[1] || null,
        valor_fatura: cells[2] || null,
        situacao: cells[3] || null,
        data_pagamento: cells[4] || null,
      };
    });
  });

  return data.map((invoice) => ({
    referencia: invoice.referencia,
    vencimento: normalizeDate(invoice.vencimento),
    valor_fatura: normalizeMoney(invoice.valor_fatura),
    situacao: mapCoelbaStatus(invoice.situacao),
    situacao_raw: invoice.situacao,
    data_pagamento: normalizeDate(invoice.data_pagamento),
  }));
}

async function waitForCoelbaInvoices(page, timeoutMs = 30000) {
  const selectors = [
    "table tbody tr",
    'table tr td:has-text("/")',
    'button:has-text("BAIXAR")',
  ];

  for (const selector of selectors) {
    try {
      await page.waitForSelector(selector, { timeout: timeoutMs });
      return true;
    } catch {
      // tenta próximo seletor
    }
  }

  return false;
}

async function ensureCoelbaDebitosContext(page, customerCode) {
  await page.goto("https://agenciavirtual.neoenergia.com/#/home/selecionar-estado", {
    waitUntil: "domcontentloaded",
  });

  await selectCoelbaBahiaState(page);
  await selectCoelbaCustomerUnit(page, customerCode);
  await slowStep(page, 2000);

  await navigateToCoelbaDebitosFromHome(page);
}

async function ensureCoelbaFlowUntilDebitos(page, customerCode) {
  const currentUrl = page.url();

  if (/#\/home\/servicos\/consultar-debitos/i.test(currentUrl)) {
    return;
  }

  if (/#\/home\/selecionar-estado/i.test(currentUrl)) {
    await selectCoelbaBahiaState(page);
    await selectCoelbaCustomerUnit(page, customerCode);
    await slowStep(page, 2000);
    await navigateToCoelbaDebitosFromHome(page);
    return;
  }

  if (/#\/home\/meus-imoveis/i.test(currentUrl)) {
    await selectCoelbaCustomerUnit(page, customerCode);
    await slowStep(page, 2000);
    await navigateToCoelbaDebitosFromHome(page);
    return;
  }

  if (/#\/home($|\/)/i.test(currentUrl)) {
    await navigateToCoelbaDebitosFromHome(page);
    return;
  }

  await ensureCoelbaDebitosContext(page, customerCode);
}

async function navigateToCoelbaDebitosFromHome(page) {
  if (!/#\/home($|\/)/i.test(page.url())) {
    await page.goto("https://agenciavirtual.neoenergia.com/#/home", {
      waitUntil: "domcontentloaded",
    });
  }
  await slowStep(page, 1200);

  await Promise.race([
    page.waitForSelector("app-home", { timeout: 15000 }),
    page.waitForSelector("body", { timeout: 15000 }),
  ]);
  await slowStep(page, 700);

  const clicked = await clickFirst(page, [
    'text=Faturas e 2ª via de faturas',
    'text=Faturas e 2a via de faturas',
    'text=Faturas',
    'a:has-text("Faturas")',
    'button:has-text("Faturas")',
    'div:has-text("Faturas")',
  ]);

  if (!clicked) {
    const clickedByScript = await page.evaluate(() => {
      const normalize = (value) => (value || "").replace(/\s+/g, " ").trim().toLowerCase();
      const nodes = Array.from(document.querySelectorAll("a, button, div, [role='button']"));
      const target = nodes.find((node) => {
        const text = normalize(node.textContent);
        return text.includes("faturas") && (text.includes("2ª via") || text.includes("2a via") || text.includes("fatura"));
      });

      if (!target) {
        return false;
      }

      target.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
      return true;
    });

    if (!clickedByScript) {
      const screenshotPath = await captureDebugScreenshot(page, "coelba-home-faturas-link-not-found");
      throw new Error(
        `Não foi possível navegar da home para 'Faturas e 2ª via de faturas'. Screenshot: ${screenshotPath}`,
      );
    }
  }

  await page.waitForURL(/#\/home\/servicos\/consultar-debitos/i, { timeout: 45000 });
  await slowStep(page, 1200);
}

async function downloadCoelbaPdf(page, targetReference) {
  try {
    const accordion = page
      .locator("mat-expansion-panel, .mat-expansion-panel, mat-card")
      .filter({ hasText: targetReference })
      .first();

    if ((await accordion.count()) > 0) {
      const expandHeader = accordion
        .locator(
          '.mat-expansion-panel-header, .mat-expansion-indicator, [role="button"], mat-panel-title, div:has-text("REFERÊNCIA")',
        )
        .first();

      if ((await expandHeader.count()) > 0) {
        await expandHeader.click();
        await page.waitForTimeout(900);
      }
    }

    const button = page
      .locator('button:has-text("BAIXAR"), a:has-text("BAIXAR"), button:has(span:has-text("BAIXAR"))')
      .filter({ hasText: "BAIXAR" })
      .first();
    if ((await button.count()) === 0) {
      return null;
    }

    await fs.mkdir(DOWNLOADS_DIR, { recursive: true });
    const downloadPromise = page.waitForEvent("download", { timeout: 20000 });
    await button.click();
    const download = await downloadPromise;
    const fileName = `coelba_${Date.now()}.pdf`;
    const fullPath = path.join(DOWNLOADS_DIR, fileName);
    await download.saveAs(fullPath);
    return fullPath;
  } catch {
    return null;
  }
}

export async function scrapeCoelba() {
  const customerCode = process.env.COELBA_CODIGO_CLIENTE;
  if (!customerCode) {
    throw new Error("COELBA_CODIGO_CLIENTE é obrigatório para scraping da Coelba.");
  }

  const sessionPath = getUtilitySessionPath("coelba");
  const hasSession = await hasUtilitySession("coelba");

  const runScrape = async (storageStatePath) =>
    withBrowserContext(
      async ({ context }) => {
        const page = await context.newPage();
        setDefaultTimeout(page);
        await page.goto(COELBA_LOGIN_URL, { waitUntil: "domcontentloaded" });
        await slowStep(page, 1200);
        await ensureCoelbaFlowUntilDebitos(page, customerCode);
        if (await isAccessDeniedPage(page)) {
          const screenshotPath = await captureDebugScreenshot(page, "coelba-access-denied-in-debitos");
          throw new Error(`Coelba bloqueou acesso à página de débitos. Screenshot: ${screenshotPath}`);
        }
        let invoiceLoaded = await waitForCoelbaInvoices(page, 30000);

        if (!invoiceLoaded) {
          await ensureCoelbaDebitosContext(page, customerCode);
          invoiceLoaded = await waitForCoelbaInvoices(page, 30000);
        }

        if (!invoiceLoaded) {
          const screenshotPath = await captureDebugScreenshot(page, "coelba-invoices-loading-stuck");
          throw new Error(
            `Página de débitos da Coelba ficou em loading e não exibiu faturas. Screenshot: ${screenshotPath}`,
          );
        }

        const invoices = await extractCoelbaInvoices(page);
        const pending = invoices.find((invoice) => ["a_vencer", "vencida"].includes(invoice.situacao));
        const pdfPath = pending ? await downloadCoelbaPdf(page, pending.referencia || "") : null;

        return {
          success: true,
          mode: "coelba",
          concessionaria: "coelba",
          scraped_at: nowIso(),
          data: {
            concessionaria: "coelba",
            codigo_cliente: customerCode,
            scraped_at: nowIso(),
            faturas: invoices,
            pdf_path: pdfPath,
          },
        };
      },
      { storageStatePath },
    );

  if (hasSession) {
    try {
      return await runScrape(sessionPath);
    } catch {
      // tentativa extra com a mesma sessão antes de relogar
      try {
        return await runScrape(sessionPath);
      } catch {
        // segue para relogin
      }
    }
  }

  await withBrowserContext(async ({ context }) => {
    await coelbaLoginAndSession(context);
    return null;
  });

  return runScrape(sessionPath);
}
