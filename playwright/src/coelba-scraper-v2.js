import path from "node:path";
import fs from "node:fs/promises";
import { nowIso, setDefaultTimeout, withBrowserContext } from "./utility-auth.js";

const COELBA_LOGIN_URL = "https://agenciavirtual.neoenergia.com/#/login";
const DOWNLOADS_DIR = process.env.PLAYWRIGHT_DOWNLOADS_DIR || path.resolve(process.cwd(), "playwright/downloads");
const DEBUG_DIR =
  process.env.PLAYWRIGHT_DEBUG_DIR || path.resolve(process.cwd(), "playwright/downloads/debug");

/** Delays tunáveis (headless/Docker costumam precisar de mais tempo que dev com headful). */
const COELBA_PAGE_SETTLE_MS = Number(process.env.COELBA_PAGE_SETTLE_MS || 2500);
const COELBA_AFTER_LOGIN_CLICK_MS = Number(process.env.COELBA_AFTER_LOGIN_CLICK_MS || 1500);
const COELBA_BEFORE_FILL_MS = Number(process.env.COELBA_BEFORE_FILL_MS || 1200);
const COELBA_NETWORKIDLE_TIMEOUT_MS = Number(process.env.COELBA_NETWORKIDLE_TIMEOUT_MS || 25000);

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

  const match = value.trim().match(/^(\d{2})\/(\d{2})\/(\d{2,4})$/);
  if (!match) {
    return null;
  }

  const [, dd, mm, yy] = match;
  const yyyy = yy.length === 2 ? `20${yy}` : yy;
  return `${yyyy}-${mm}-${dd}`;
}

function mapCoelbaStatus(rawStatus) {
  const status = normalizeText(rawStatus).toLowerCase();
  if (status.includes("a vencer")) return "a_vencer";
  if (status.includes("vencida")) return "vencida";
  return "pago";
}

function referenceFromDueDate(dueDateIso) {
  if (!dueDateIso) {
    return null;
  }

  const match = dueDateIso.match(/^(\d{4})-(\d{2})-\d{2}$/);
  if (!match) {
    return null;
  }

  const [, year, month] = match;
  return `${month}/${year}`;
}

async function slowStep(page, ms = 800) {
  await page.waitForTimeout(ms);
}

async function captureDebugScreenshot(page, prefix) {
  await fs.mkdir(DEBUG_DIR, { recursive: true });
  const screenshotPath = path.join(DEBUG_DIR, `${prefix}-${Date.now()}.png`);
  await page.screenshot({ path: screenshotPath, fullPage: true });
  return screenshotPath;
}

async function clickFirst(page, selectors) {
  for (const selector of selectors) {
    const locator = page.locator(selector);
    const count = await locator.count();

    for (let index = 0; index < count; index += 1) {
      const candidate = locator.nth(index);
      const visible = await candidate.isVisible().catch(() => false);
      if (!visible) {
        continue;
      }

      try {
        await candidate.scrollIntoViewIfNeeded();
        await candidate.click({ timeout: 2500 });
        return true;
      } catch {
        // tenta próximo elemento do seletor
      }
    }
  }

  return false;
}

/** Preenche o primeiro campo visível — evita inputs ocultos do Angular/Material em headless. */
async function fillFirstVisible(page, selectors, value) {
  for (const selector of selectors) {
    const locator = page.locator(selector).filter({ visible: true }).first();
    try {
      await locator.waitFor({ state: "visible", timeout: 8000 });
      await locator.fill(value);
      return true;
    } catch {
      // tenta próximo seletor
    }
  }

  return false;
}

async function isAccessDeniedPage(page) {
  const title = (await page.title()).toLowerCase();
  const body = ((await page.textContent("body").catch(() => "")) || "").toLowerCase();
  return (
    title.includes("access denied") ||
    body.includes("access denied") ||
    body.includes("you don't have permission") ||
    body.includes("akamai")
  );
}

/**
 * Espera shell/página estável antes de clicar em LOGIN (DOM + rede + margem para o Angular hidratar).
 * `networkidle` pode nunca ocorrer em SPA com polling; nesse caso seguimos após timeout.
 */
async function waitForCoelbaLoginPageSettled(page) {
  await page.waitForLoadState("load").catch(() => {});
  await page
    .waitForFunction(() => document.readyState === "complete", null, { timeout: 30000 })
    .catch(() => {});

  try {
    await page.waitForLoadState("networkidle", { timeout: COELBA_NETWORKIDLE_TIMEOUT_MS });
  } catch {
    // Neoenergia/Angular às vezes mantém requisições longas; não bloqueia o fluxo.
  }

  await page
    .evaluate(async () => {
      try {
        if (document.fonts?.ready) {
          await document.fonts.ready;
        }
      } catch {
        // ignore
      }
    })
    .catch(() => {});

  await slowStep(page, COELBA_PAGE_SETTLE_MS);
}

/** Espera o modal/form de login do Angular (headless costuma renderizar mais devagar que com headful). */
async function waitForCoelbaLoginFields(page, timeoutMs = 45000) {
  await page
    .locator('input[type="password"]')
    .filter({ visible: true })
    .first()
    .waitFor({ state: "visible", timeout: timeoutMs });
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

  for (let attempt = 0; attempt < 20; attempt += 1) {
    await new Promise((resolve) => setTimeout(resolve, 1500));
    const resultResponse = await fetch("https://api.capsolver.com/getTaskResult", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        clientKey: apiKey,
        taskId: createJson.taskId,
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
  if (!token) return;

  await page.evaluate((value) => {
    const textarea = document.querySelector('textarea[name="g-recaptcha-response"]');
    if (textarea) {
      textarea.value = value;
      textarea.dispatchEvent(new Event("change", { bubbles: true }));
    }
  }, token);
}

async function selectBahia(page) {
  await page.waitForURL(/#\/home\/selecionar-estado/i, { timeout: 45000 });
  await slowStep(page, 900);

  const clicked = await clickFirst(page, [
    'button:has-text("Bahia")',
    'div.card-wrapper:has-text("Bahia")',
    'div.card:has-text("Bahia")',
    'text=Bahia',
  ]);

  if (!clicked) {
    const screenshotPath = await captureDebugScreenshot(page, "coelba-state-bahia-not-found");
    throw new Error(`Não foi possível selecionar o estado Bahia. Screenshot: ${screenshotPath}`);
  }
}

async function selectCustomerUnit(page, customerCode) {
  await page.waitForURL(/#\/home\/meus-imoveis/i, { timeout: 45000 });
  await slowStep(page, 1200);

  const clicked = await clickFirst(page, [
    `text=${customerCode}`,
    `div:has-text("${customerCode}")`,
    `a:has-text("${customerCode}")`,
  ]);

  if (!clicked) {
    const screenshotPath = await captureDebugScreenshot(page, "coelba-customer-unit-not-found");
    throw new Error(`Não foi possível selecionar a unidade (${customerCode}). Screenshot: ${screenshotPath}`);
  }

  await slowStep(page, 1800);
  await page.waitForURL(/#\/home($|\/)/i, { timeout: 45000 });
}

async function waitHomeReady(page) {
  await page.waitForURL(/#\/home($|\/)/i, { timeout: 45000 });
  await Promise.race([
    page.waitForSelector('div:has-text("Última Fatura")', { timeout: 25000 }),
    page.waitForSelector('div:has-text("Total a pagar")', { timeout: 25000 }),
    page.waitForSelector("app-home", { timeout: 25000 }),
  ]);
  await slowStep(page, 2500);
}

async function extractLatestInvoiceFromHome(page) {
  const payload = await page.evaluate(() => {
    const normalize = (value) => (value || "").replace(/\s+/g, " ").trim();
    const card = document.querySelector("#ultima-fatura");
    const text = normalize((card || document.body).textContent || "");

    const totalMatch = text.match(/Total a pagar\s*R\$\s*([\d.,]+)/i);
    const dueMatch = text.match(/Vencimento:?\s*(\d{2}\/\d{2}\/\d{2,4})/i);
    const statusMatch = text.match(/Situa[çc][ãa]o:?\s*(A Vencer|Vencida|Pago)/i);

    return {
      valor_fatura_raw: totalMatch ? `R$ ${normalize(totalMatch[1])}` : null,
      vencimento_raw: dueMatch ? normalize(dueMatch[1]) : null,
      situacao_raw: statusMatch ? normalize(statusMatch[1]) : null,
    };
  });

  const dueDate = normalizeDate(payload.vencimento_raw);
  const statusRaw = payload.situacao_raw;

  return {
    referencia: referenceFromDueDate(dueDate),
    vencimento: dueDate,
    valor_fatura: normalizeMoney(payload.valor_fatura_raw),
    situacao: mapCoelbaStatus(statusRaw),
    situacao_raw: statusRaw,
    data_pagamento: null,
  };
}

async function extractPixCodeFromHome(page) {
  const pixTriggerClicked = await clickFirst(page, ['#ultima-fatura button:has-text("PIX")', 'button:has-text("PIX")', 'text=PIX']);
  if (!pixTriggerClicked) {
    return null;
  }

  await Promise.race([
    page.waitForSelector('text=Pagamento por Código', { timeout: 12000 }),
    page.waitForSelector('button:has-text("Copiar Código")', { timeout: 12000 }),
  ]);
  await slowStep(page, 700);

  const pixCode = await page.evaluate(() => {
    const normalize = (value) => (value || "").replace(/\s+/g, " ").trim();
    const onlyDigits = (value) => (value || "").replace(/\D/g, "");
    const modal = Array.from(document.querySelectorAll("div, section"))
      .find((node) => normalize(node.textContent || "").includes("Pagamento por Código"));
    if (!modal) {
      return null;
    }

    const text = normalize(modal.textContent || "");

    // 1) tenta bloco textual com separadores
    const normalizedDigits = onlyDigits(text);
    if (normalizedDigits.length >= 30) {
      return normalizedDigits;
    }

    // 2) tenta input/textarea da modal
    const valueField = modal.querySelector("input, textarea");
    if (valueField) {
      const valueDigits = onlyDigits(valueField.value || valueField.textContent || "");
      if (valueDigits.length >= 30) {
        return valueDigits;
      }
    }

    // 3) fallback por regex de grupos numéricos
    const grouped = text.match(/(\d[\d.\s]{28,}\d)/);
    if (grouped?.[1]) {
      const digits = onlyDigits(grouped[1]);
      if (digits.length >= 30) {
        return digits;
      }
    }

    return null;
  });

  await clickFirst(page, ['button:has-text("Cancelar")', 'text=Cancelar', 'button:has-text("Fechar")', 'button:has-text("OK")']);
  await slowStep(page, 900);

  return pixCode || null;
}

async function downloadInvoiceFromHome(page) {
  const menuClicked = await clickFirst(page, [
    'button:has-text("MAIS OPÇÕES")',
    'button:has-text("Mais Opções")',
    'button:has-text("Mais opções")',
  ]);

  if (!menuClicked) {
    return null;
  }

  await slowStep(page, 800);

  const invoiceOptionsOpened = await clickFirst(page, [
    '[role="menuitem"]:has-text("Opções de fatura")',
    '[role="menuitem"]:has-text("Opcoes de fatura")',
    'button:has-text("Opções de fatura")',
    'text=Opções de fatura',
  ]);

  if (!invoiceOptionsOpened) {
    return null;
  }

  await slowStep(page, 900);

  const downloadOptionClicked = await clickFirst(page, [
    '[role="menuitem"]:has-text("Download")',
    'button:has-text("Download")',
    'a:has-text("Download")',
    'text=Download',
  ]);

  if (!downloadOptionClicked) {
    return null;
  }

  await slowStep(page, 1000);

  // Modal de motivo (Angular Material): é obrigatório marcar um radio para habilitar "BAIXAR".
  await page.waitForSelector("mat-radio-group, mat-radio-button", { timeout: 15000 }).catch(() => {});

  const motivoCandidates = [
    page.locator("mat-radio-button").filter({ hasText: /Não\s+Recebi/i }).first(),
    page.locator("mat-radio-button").filter({ hasText: /Nao\s+Recebi/i }).first(),
    page.locator("mat-radio-button").filter({ hasText: /Fatura\s+Danificada/i }).first(),
    page.locator("mat-radio-button").filter({ hasText: /Comprovar\s+Residência/i }).first(),
    page.locator("mat-radio-button").filter({ hasText: /Não\s+Estou\s+Com\s+Fatura/i }).first(),
  ];

  let motivoSelecionado = false;
  for (const radio of motivoCandidates) {
    try {
      if ((await radio.count()) < 1) {
        continue;
      }
      await radio.waitFor({ state: "visible", timeout: 8000 });
      const input = radio.locator("input.mat-radio-input");
      if ((await input.count()) > 0) {
        await input.click({ force: true, timeout: 8000 });
      } else {
        await radio.locator("label.mat-radio-label").click({ force: true, timeout: 8000 });
      }
      motivoSelecionado = true;
      break;
    } catch {
      // tenta próximo motivo
    }
  }

  if (!motivoSelecionado) {
    await clickFirst(page, [
      'mat-radio-button:has-text("Não Recebi")',
      'mat-radio-button:has-text("Nao Recebi")',
      'label.mat-radio-label:has-text("Não Recebi")',
    ]);
  }

  await slowStep(page, 600);

  await page
    .waitForFunction(
      () => {
        const buttons = Array.from(document.querySelectorAll("button"));
        return buttons.some((b) => {
          const text = (b.textContent || "").replace(/\s+/g, " ").trim();
          if (!/^baixar$/i.test(text)) {
            return false;
          }
          if (b.disabled) {
            return false;
          }
          if (b.getAttribute("aria-disabled") === "true") {
            return false;
          }
          if (b.classList.contains("mat-button-disabled")) {
            return false;
          }
          return true;
        });
      },
      { timeout: 20000 },
    )
    .catch(() => {});

  const downloadPromise = page.waitForEvent("download", { timeout: 45000 });

  const downloadButtonClicked = await page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll("button"));
    const btn = buttons.find((b) => {
      const text = (b.textContent || "").replace(/\s+/g, " ").trim();
      if (!/^baixar$/i.test(text)) {
        return false;
      }
      if (b.disabled) {
        return false;
      }
      if (b.getAttribute("aria-disabled") === "true") {
        return false;
      }
      if (b.classList.contains("mat-button-disabled")) {
        return false;
      }
      return true;
    });
    if (btn) {
      btn.click();
      return true;
    }
    return false;
  });

  if (!downloadButtonClicked) {
    const fallback = await clickFirst(page, [
      'button:has-text("BAIXAR")',
      'button:has-text("Baixar")',
      'button.mat-flat-button:has-text("BAIXAR")',
    ]);
    if (!fallback) {
      return null;
    }
  }

  await fs.mkdir(DOWNLOADS_DIR, { recursive: true });
  const download = await downloadPromise;
  const filePath = path.join(DOWNLOADS_DIR, `coelba_home_${Date.now()}.pdf`);
  await download.saveAs(filePath);

  // Modal de sucesso pode aparecer após o download.
  await clickFirst(page, ['button:has-text("OK")', 'button:has-text("Ok")', 'button:has-text("Fechar")']);
  await slowStep(page, 500);

  return filePath;
}

async function openDebitosFromHome(page) {
  await page.waitForURL(/#\/home($|\/)/i, { timeout: 45000 });
  await Promise.race([
    page.waitForSelector("app-home", { timeout: 20000 }),
    page.waitForSelector('div:has-text("Serviços mais utilizados")', { timeout: 20000 }),
    page.waitForSelector("body", { timeout: 20000 }),
  ]);

  // A home da Coelba renderiza blocos em fases; margem extra para evitar nó oculto.
  await slowStep(page, 7000);

  const clicked = await clickFirst(page, [
    'text=Faturas e 2ª via de faturas',
    'text=Faturas e 2a via de faturas',
    'div.item:has-text("Faturas e 2ª via")',
    'div.item:has-text("Faturas e 2a via")',
    'div.item:has-text("faturas")',
    'button:has-text("Faturas")',
    'a:has-text("Faturas")',
  ]);

  if (!clicked) {
    const screenshotPath = await captureDebugScreenshot(page, "coelba-home-faturas-link-not-found");
    throw new Error(`Não foi possível abrir Faturas e 2ª via. Screenshot: ${screenshotPath}`);
  }

  await page.waitForURL(/#\/home\/servicos\/consultar-debitos/i, { timeout: 45000 });
  await slowStep(page, 1200);
}

async function waitInvoices(page) {
  const selectors = [
    "mat-expansion-panel",
    ".mat-expansion-panel",
    'mat-panel-title:has-text("REFERÊNCIA")',
    'button:has-text("BAIXAR")',
  ];

  for (const selector of selectors) {
    try {
      await page.waitForSelector(selector, { timeout: 30000 });
      return true;
    } catch {
      // tenta próximo
    }
  }

  return false;
}

async function scrollDebitsPage(page, maxRounds = 12, idleStopRounds = 3) {
  let previousHeight = 0;
  let idleRounds = 0;

  for (let round = 0; round < maxRounds; round += 1) {
    const metrics = await page.evaluate(() => {
      const container =
        document.querySelector("mat-sidenav-content") ||
        document.querySelector(".mat-drawer-content") ||
        document.querySelector(".mat-sidenav-content") ||
        document.scrollingElement ||
        document.body;

      const beforeTop = container.scrollTop ?? window.scrollY ?? 0;
      const beforeHeight = container.scrollHeight ?? document.body.scrollHeight ?? 0;

      // Scroll híbrido: container + window (fallback).
      if (typeof container.scrollBy === "function") {
        container.scrollBy(0, 900);
      }
      window.scrollBy(0, 900);

      const afterTop = container.scrollTop ?? window.scrollY ?? 0;
      const afterHeight = container.scrollHeight ?? document.body.scrollHeight ?? 0;

      return {
        topIncreased: afterTop > beforeTop,
        height: Math.max(beforeHeight, afterHeight),
      };
    });

    await page.mouse.wheel(0, 900);
    await slowStep(page, 450);

    const heightDidNotGrow = metrics.height <= previousHeight;
    const topDidNotMove = !metrics.topIncreased;
    if (heightDidNotGrow && topDidNotMove) {
      idleRounds += 1;
    } else {
      idleRounds = 0;
      previousHeight = Math.max(previousHeight, metrics.height);
    }

    if (idleRounds >= idleStopRounds) {
      break;
    }
  }

  await page.evaluate(() => {
    const container =
      document.querySelector("mat-sidenav-content") ||
      document.querySelector(".mat-drawer-content") ||
      document.querySelector(".mat-sidenav-content") ||
      document.scrollingElement ||
      document.body;

    if (container && typeof container.scrollTo === "function") {
      container.scrollTo({ top: 0, behavior: "instant" });
    }
    window.scrollTo({ top: 0, behavior: "instant" });
  });
  await slowStep(page, 600);
}

async function expandTopInvoicePanels(page, maxPanels = 3) {
  const panels = page.locator("mat-expansion-panel");
  const count = await panels.count();
  const limit = Math.min(count, maxPanels);

  for (let index = 0; index < limit; index += 1) {
    const panel = panels.nth(index);
    const header = panel.locator(".mat-expansion-panel-header").first();
    if ((await header.count()) > 0) {
      try {
        const expandedAttr = await panel.getAttribute("class");
        const alreadyExpanded = (expandedAttr || "").includes("mat-expanded");
        if (!alreadyExpanded) {
          await header.click({ timeout: 4000 });
          await slowStep(page, 700);
        }
      } catch {
        // segue para o próximo painel
      }
    }
  }
}

function parseInvoiceFromText(text) {
  const refMatch = text.match(/REFER[ÊE]NCIA\s+([A-ZÇ]+\/\d{2,4}|\d{2}\/\d{2,4})/i);
  const dueMatch = text.match(/VENCIMENTO\s+(\d{2}\/\d{2}\/\d{2,4})/i);
  const valueMatch = text.match(/VALOR FATURA\s+R\$\s*([\d.,]+)/i);
  const statusMatch = text.match(/SITUA[ÇC][ÃA]O\s+(A Vencer|Vencida|Pago)/i);
  const payMatch = text.match(/DATA PAGAMENTO\s+(\d{2}\/\d{2}\/\d{2,4})/i);

  if (!refMatch || !dueMatch) {
    return null;
  }

  const referencia = normalizeText(refMatch[1]);
  const vencimento = normalizeDate(normalizeText(dueMatch[1]));
  const valorFatura = valueMatch ? normalizeMoney(`R$ ${normalizeText(valueMatch[1])}`) : null;
  const situacaoRaw = statusMatch ? normalizeText(statusMatch[1]) : null;
  const dataPagamento = payMatch ? normalizeDate(normalizeText(payMatch[1])) : null;

  return {
    referencia,
    vencimento,
    valor_fatura: valorFatura,
    situacao: mapCoelbaStatus(situacaoRaw),
    situacao_raw: situacaoRaw,
    data_pagamento: dataPagamento,
  };
}

async function extractInvoicesByInteraction(page, maxPanels = 3) {
  await scrollDebitsPage(page, 7);

  const panels = page.locator("mat-expansion-panel");
  const count = await panels.count();
  const limit = Math.min(count, maxPanels);
  const invoices = [];

  for (let index = 0; index < limit; index += 1) {
    const panel = panels.nth(index);
    await panel.scrollIntoViewIfNeeded().catch(() => {});
    await slowStep(page, 400);

    await panel.click({ timeout: 4000 }).catch(() => {});
    await slowStep(page, 2000);

    const panelText = normalizeText(await panel.textContent());
    if (!panelText) {
      continue;
    }

    const parsed = parseInvoiceFromText(panelText);
    if (parsed) {
      invoices.push(parsed);
    }
  }

  return invoices;
}

async function downloadPendingInvoicePdf(page) {
  await scrollDebitsPage(page, 7);

  const panels = page.locator("mat-expansion-panel");
  const count = await panels.count();
  const maxPanelsToInspect = Math.min(count, 3);

  const panelPriority = [];
  for (let index = 0; index < maxPanelsToInspect; index += 1) {
    const panel = panels.nth(index);
    const panelText = normalizeText(await panel.textContent());
    if (!panelText) {
      continue;
    }

    const lower = panelText.toLowerCase();
    if (lower.includes("a vencer")) {
      panelPriority.unshift(index);
    } else if (lower.includes("vencida")) {
      panelPriority.push(index);
    }
  }

  const orderedIndexes = panelPriority.length > 0
    ? panelPriority
    : Array.from({ length: maxPanelsToInspect }, (_, index) => index);

  for (const index of orderedIndexes) {
    const panel = panels.nth(index);
    await panel.scrollIntoViewIfNeeded().catch(() => {});
    await slowStep(page, 500);
    const panelText = normalizeText(await panel.textContent());
    if (!panelText || (!panelText.toLowerCase().includes("a vencer") && !panelText.toLowerCase().includes("vencida"))) {
      continue;
    }

    // Interação principal: clique no card inteiro + espera do refresh visual interno.
    await panel.scrollIntoViewIfNeeded().catch(() => {});
    await panel.click({ timeout: 4000 }).catch(() => {});
    await slowStep(page, 2000);

    let button = panel.locator('button:has-text("BAIXAR"), a:has-text("BAIXAR"), span:has-text("BAIXAR")').first();

    if ((await button.count()) === 0) {
      // Segunda tentativa via evento no card para cenários de render assíncrona.
      await panel.evaluate((el) => {
        el.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
      });
      await slowStep(page, 2000);
      button = panel.locator('button:has-text("BAIXAR"), a:has-text("BAIXAR"), span:has-text("BAIXAR")').first();
    }

    if ((await button.count()) === 0) {
      continue;
    }

    await fs.mkdir(DOWNLOADS_DIR, { recursive: true });
    const downloadPromise = page.waitForEvent("download", { timeout: 25000 });
    try {
      await button.click({ timeout: 4000 });
    } catch {
      // Fallback: dispara click no nó via JS quando o botão estiver no DOM, mas não visível.
      await panel.evaluate((el) => {
        const candidate = el.querySelector("button, a, span");
        if (candidate) {
          candidate.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
        }
      });
    }
    const download = await downloadPromise;
    const filePath = path.join(DOWNLOADS_DIR, `coelba_${Date.now()}.pdf`);
    await download.saveAs(filePath);
    return filePath;
  }

  return null;
}

export async function scrapeCoelba() {
  const cpf = process.env.COELBA_CPF;
  const password = process.env.COELBA_PASSWORD;
  const customerCode = process.env.COELBA_CODIGO_CLIENTE;

  if (!cpf || !password || !customerCode) {
    throw new Error("COELBA_CPF, COELBA_PASSWORD e COELBA_CODIGO_CLIENTE são obrigatórios.");
  }

  return withBrowserContext(async ({ context }) => {
    const page = await context.newPage();
    setDefaultTimeout(page);

    await page.goto(COELBA_LOGIN_URL, { waitUntil: "load", timeout: 90000 });
    await waitForCoelbaLoginPageSettled(page);

    if (await isAccessDeniedPage(page)) {
      const screenshotPath = await captureDebugScreenshot(page, "coelba-access-denied-before-login");
      throw new Error(`Coelba bloqueou acesso (anti-bot/WAF) antes do login. Screenshot: ${screenshotPath}`);
    }

    await clickFirst(page, ['button:has-text("LOGIN")', 'a:has-text("LOGIN")']);
    await slowStep(page, COELBA_AFTER_LOGIN_CLICK_MS);

    try {
      await waitForCoelbaLoginFields(page, 45000);
    } catch {
      const screenshotPath = await captureDebugScreenshot(page, "coelba-login-form-timeout");
      throw new Error(`Formulário de login não ficou visível a tempo (Angular). Screenshot: ${screenshotPath}`);
    }

    await slowStep(page, COELBA_BEFORE_FILL_MS);

    const cpfSelectors = [
      'input[placeholder*="CPF" i]',
      'input[name="documento"]',
      'input[formcontrolname="documento"]',
      'input.mat-input-element:not([type="password"])',
      'input[type="text"]',
    ];
    const cpfFilled = await fillFirstVisible(page, cpfSelectors, cpf);
    const passwordFilled = await fillFirstVisible(page, ['input[type="password"]'], password);

    if (!cpfFilled || !passwordFilled) {
      const screenshotPath = await captureDebugScreenshot(page, "coelba-login-fields-not-found");
      throw new Error(`Campos de login não encontrados. Screenshot: ${screenshotPath}`);
    }

    const recaptchaToken = await solveRecaptchaV3();
    if (recaptchaToken) {
      await injectRecaptchaToken(page, recaptchaToken);
      await slowStep(page, 600);
    }

    const loginClicked = await clickFirst(page, ['button:has-text("ENTRAR")', 'button[type="submit"]']);
    if (!loginClicked) {
      const screenshotPath = await captureDebugScreenshot(page, "coelba-login-submit-not-found");
      throw new Error(`Botão ENTRAR não encontrado. Screenshot: ${screenshotPath}`);
    }

    await page.waitForURL(/#\/home\/selecionar-estado/i, { timeout: 45000 });
    await selectBahia(page);
    await selectCustomerUnit(page, customerCode);
    await waitHomeReady(page);

    const homeInvoice = await extractLatestInvoiceFromHome(page);
    const pixCode = await extractPixCodeFromHome(page);
    const pdfPath = await downloadInvoiceFromHome(page);
    const invoices = homeInvoice.referencia || homeInvoice.vencimento || homeInvoice.valor_fatura
      ? [homeInvoice]
      : [];

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
        pix_code: pixCode,
      },
    };
  });
}
