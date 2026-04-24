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

const EMBASA_LOGIN_URL = "https://atendimentovirtual.embasa.ba.gov.br/login";
const EMBASA_SECOND_VIA_URL = "https://atendimentovirtual.embasa.ba.gov.br/segunda-via?pay=true";
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

function mapEmbasaStatus(rawStatus) {
  const status = normalizeText(rawStatus).toLowerCase();
  if (status.includes("conta paga")) {
    return "pago";
  }

  if (status.includes("processamento banc")) {
    return "processando";
  }

  return "pendente";
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

async function fillEmbasaLoginFields(page, cpf, password) {
  const cpfSelectors = [
    'input[placeholder*="CPF" i]',
    'input[name*="cpf" i]',
    'input[id*="cpf" i]',
    'input[inputmode="numeric"]',
  ];
  const passwordSelectors = ['input[type="password"]', 'input[name*="senha" i]', 'input[name*="password" i]'];

  const cpfFilled = await fillFirst(page, cpfSelectors, cpf);
  const passwordFilled = await fillFirst(page, passwordSelectors, password);

  if (cpfFilled && passwordFilled) {
    return true;
  }

  const visibleInputs = page.locator("input:visible");
  const count = await visibleInputs.count();
  if (count >= 2) {
    await visibleInputs.nth(0).fill(cpf);
    await visibleInputs.nth(1).fill(password);
    return true;
  }

  return false;
}

async function embasaLoginAndSession(context) {
  const cpf = process.env.EMBASA_CPF;
  const password = process.env.EMBASA_PASSWORD;

  if (!cpf || !password) {
    throw new Error("EMBASA_CPF e EMBASA_PASSWORD são obrigatórios para scraping da Embasa.");
  }

  const page = await context.newPage();
  setDefaultTimeout(page);
  await page.goto(EMBASA_LOGIN_URL, { waitUntil: "domcontentloaded" });

  const fieldsFilled = await fillEmbasaLoginFields(page, cpf, password);
  if (!fieldsFilled) {
    const screenshotPath = await captureDebugScreenshot(page, "embasa-login-fields-not-found");
    throw new Error(`Campos de login da Embasa não encontrados. Screenshot: ${screenshotPath}`);
  }

  // A Embasa habilita o botão "Entrar" somente após sair do foco da senha.
  await page.keyboard.press("Tab").catch(() => {});
  await page.evaluate(() => {
    const active = document.activeElement;
    if (active && typeof active.blur === "function") {
      active.blur();
    }
  });
  await page.waitForTimeout(450);

  const clicked = await clickFirst(page, [
    'button:has-text("Entrar")',
    'button[type="submit"]',
    'input[type="submit"]',
  ]);

  if (!clicked) {
    const screenshotPath = await captureDebugScreenshot(page, "embasa-login-submit-not-found");
    throw new Error(`Botão de login da Embasa não encontrado. Screenshot: ${screenshotPath}`);
  }

  try {
    await page.waitForURL(/\/home|\/segunda-via/i, { timeout: 45000 });
  } catch {
    const screenshotPath = await captureDebugScreenshot(page, "embasa-login-timeout");
    throw new Error(`Timeout no login da Embasa. Screenshot: ${screenshotPath}`);
  }
  await saveContextStorageState(context, "embasa");
}

async function selectEmbasaMatricula(page, matricula) {
  const clickedDropdown = await clickFirst(page, [
    'button:has-text("Matrícula")',
    'div:has-text("Matrícula: Selecionar")',
  ]);

  if (!clickedDropdown) {
    return;
  }

  await page.waitForTimeout(800);
  const matriculaOption = page.locator(`text=${matricula}`).first();
  if ((await matriculaOption.count()) > 0) {
    await matriculaOption.click();
  }

  await clickFirst(page, ['button:has-text("SELECIONAR MATRÍCULA")', 'button:has-text("Selecionar")']);
}

async function downloadEmbasaPdf(page, targetReference) {
  try {
    let button = page
      .locator('.download-bills, button:has-text("BAIXAR 2ª VIA"), button:has-text("BAIXAR"), a:has-text("BAIXAR")')
      .first();

    if (targetReference) {
      const card = page.locator(".card").filter({ hasText: targetReference }).first();
      const scoped = card
        .locator('.download-bills, button:has-text("BAIXAR 2ª VIA"), button:has-text("BAIXAR"), a:has-text("BAIXAR")')
        .first();
      if ((await scoped.count()) > 0) {
        button = scoped;
      }
    }

    if ((await button.count()) === 0) {
      return null;
    }

    await fs.mkdir(DOWNLOADS_DIR, { recursive: true });
    const downloadPromise = page.waitForEvent("download", { timeout: 20000 });
    await button.click();
    const download = await downloadPromise;
    const fileName = `embasa_${Date.now()}.pdf`;
    const fullPath = path.join(DOWNLOADS_DIR, fileName);
    await download.saveAs(fullPath);
    return fullPath;
  } catch {
    return null;
  }
}

async function submitEmbasaDebtsSearch(page, matricula) {
  const filled = await fillFirst(
    page,
    ['#input-matricula', 'input[id="input-matricula"]', 'input[placeholder*="010101010"]', 'input[name="matricula"]'],
    matricula,
  );

  if (!filled) {
    const screenshotPath = await captureDebugScreenshot(page, "embasa-matricula-input-not-found");
    throw new Error(`Input de matrícula não encontrado na Embasa. Screenshot: ${screenshotPath}`);
  }

  await page.keyboard.press("Tab").catch(() => {});
  await page.waitForTimeout(250);

  const clicked = await clickFirst(page, [
    "button.btn-pesquisar-conta",
    "button.btn.btn-primary.ml-2.btn-pesquisar-conta",
    'button:has-text("PRÓXIMO")',
    'button:has-text("Próximo")',
  ]);

  if (!clicked) {
    const screenshotPath = await captureDebugScreenshot(page, "embasa-next-button-not-found");
    throw new Error(`Botão PRÓXIMO da Embasa não encontrado. Screenshot: ${screenshotPath}`);
  }
}

async function extractEmbasaInvoices(page) {
  const data = await page.evaluate(() => {
    const normalize = (value) => (value || "").replace(/\s+/g, " ").trim();
    const cards = Array.from(document.querySelectorAll(".content .card.p-4, .content .card"));

    const parseCard = (card) => {
      const text = normalize(card.textContent || "");
      if (!text.includes("Referência") || !text.includes("Vencimento")) {
        return null;
      }

      const referencia = text.match(/Refer[êe]ncia:\s*([0-9]{2}\/[0-9]{4})/i)?.[1] || null;
      const vencimento = text.match(/Vencimento:\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})/i)?.[1] || null;
      const consumo = text.match(/Consumo:\s*([0-9]+)\s*m/i)?.[1] || null;
      const valorAgua = text.match(/Valor [ÁA]gua:\s*R\$\s*([0-9.,]+)/i)?.[1] || null;
      const valorEsgoto = text.match(/Valor Esgoto:\s*R\$\s*([0-9.,]+)/i)?.[1] || null;
      const valorServico = text.match(/Valor Servi[çc]o:\s*R\$\s*([0-9.,]+)/i)?.[1] || null;
      const valorTotal = text.match(/Valor Total:\s*R\$\s*([0-9.,]+)/i)?.[1] || null;

      let status = null;
      if (/Aguardando pagamento/i.test(text)) {
        status = "Aguardando pagamento";
      } else if (/Conta Paga/i.test(text)) {
        status = "Conta Paga ✓";
      } else if (/processamento banc[aá]rio/i.test(text)) {
        status = "Pagamento em processamento bancário";
      }

      return {
        referencia,
        vencimento,
        consumo_m3: consumo,
        valor_agua: valorAgua ? `R$ ${valorAgua}` : null,
        valor_esgoto: valorEsgoto ? `R$ ${valorEsgoto}` : null,
        valor_servico: valorServico ? `R$ ${valorServico}` : null,
        valor_total: valorTotal ? `R$ ${valorTotal}` : null,
        status,
      };
    };

    return cards.map(parseCard).filter(Boolean);
  });

  return data.map((invoice) => ({
    referencia: invoice.referencia,
    vencimento: normalizeDate(invoice.vencimento),
    consumo_m3: invoice.consumo_m3 ? Number(invoice.consumo_m3.replace(/[^\d]/g, "")) || null : null,
    valor_agua: normalizeMoney(invoice.valor_agua),
    valor_esgoto: normalizeMoney(invoice.valor_esgoto),
    valor_servico: normalizeMoney(invoice.valor_servico),
    valor_total: normalizeMoney(invoice.valor_total),
    status: mapEmbasaStatus(invoice.status),
    status_raw: invoice.status,
  }));
}

export async function scrapeEmbasa() {
  const matricula = process.env.EMBASA_MATRICULA;
  if (!matricula) {
    throw new Error("EMBASA_MATRICULA é obrigatório para scraping da Embasa.");
  }

  const sessionPath = getUtilitySessionPath("embasa");
  const hasSession = await hasUtilitySession("embasa");

  const runScrape = async (storageStatePath) =>
    withBrowserContext(
      async ({ context }) => {
        const page = await context.newPage();
        setDefaultTimeout(page);

        await page.goto(EMBASA_SECOND_VIA_URL, { waitUntil: "domcontentloaded" });
        await page.waitForTimeout(1200);
        await selectEmbasaMatricula(page, matricula);
        await page.goto(EMBASA_SECOND_VIA_URL, { waitUntil: "domcontentloaded" });

        await submitEmbasaDebtsSearch(page, matricula);

        try {
          await page.waitForSelector('h5:has-text("Débitos da Matrícula"), .content-header h5', { timeout: 35000 });
          await page.waitForTimeout(1500);
        } catch {
          const screenshotPath = await captureDebugScreenshot(page, "embasa-invoices-not-found");
          throw new Error(`Dados de débitos da Embasa não carregaram após PRÓXIMO. Screenshot: ${screenshotPath}`);
        }
        const invoices = await extractEmbasaInvoices(page);

        const latestPending = invoices.find((invoice) => invoice.status === "pendente");
        const pdfPath = latestPending ? await downloadEmbasaPdf(page, latestPending.referencia || "") : null;

        return {
          success: true,
          mode: "embasa",
          concessionaria: "embasa",
          scraped_at: nowIso(),
          data: {
            concessionaria: "embasa",
            matricula,
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
      // tenta renovar sessão automaticamente
    }
  }

  await withBrowserContext(async ({ context }) => {
    await embasaLoginAndSession(context);
    return null;
  });

  return runScrape(sessionPath);
}
