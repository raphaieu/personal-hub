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
const EMBASA_HOME_URL = "https://atendimentovirtual.embasa.ba.gov.br/home";
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

/**
 * Modais `.blk-modal` (avisos, propagandas) ficam por cima e interceptam o clique na matrícula.
 */
async function dismissEmbasaBlockingModals(page) {
  for (let attempt = 0; attempt < 4; attempt += 1) {
    const modal = page.locator("section.blk-modal").first();
    if ((await modal.count()) === 0) {
      break;
    }
    const visible = await modal.isVisible().catch(() => false);
    if (!visible) {
      break;
    }

    const ackButtons = modal.locator(
      'button:has-text("OK"), button:has-text("Ok"), button:has-text("Entendi"), button:has-text("Continuar"), button:has-text("Fechar")',
    );
    if ((await ackButtons.count()) > 0) {
      await ackButtons.first().click({ timeout: 3000 }).catch(() => {});
      await page.waitForTimeout(350);
      continue;
    }

    await page.keyboard.press("Escape");
    await page.waitForTimeout(350);

    const stillThere = await modal.isVisible().catch(() => false);
    if (!stillThere) {
      break;
    }

    const closeBtn = modal
      .locator('[aria-label*="Fechar" i], button.close, .btn-close, button[class*="close"]')
      .first();
    if ((await closeBtn.count()) > 0) {
      await closeBtn.click({ timeout: 3000 }).catch(() => {});
      await page.waitForTimeout(350);
    }
  }
}

/**
 * Clica na linha da matrícula no dropdown/modal (evita locator genérico `text=` pegar span coberto por overlay).
 */
async function clickEmbasaMatriculaOption(page, matricula) {
  const safeMatricula = matricula.trim();

  const tryClick = async (locator, preferForce = false) => {
    const target = locator.first();
    if ((await target.count()) === 0) {
      return false;
    }
    if (preferForce) {
      try {
        await target.click({ force: true, timeout: 12000 });
        return true;
      } catch {
        try {
          await target.click({ timeout: 8000 });
          return true;
        } catch {
          return false;
        }
      }
    }
    try {
      await target.click({ timeout: 12000 });
      return true;
    } catch {
      try {
        await target.click({ force: true, timeout: 8000 });
        return true;
      } catch {
        return false;
      }
    }
  };

  const inPickerModal = page
    .locator("section.blk-modal")
    .locator(`span.matricula`)
    .filter({ hasText: safeMatricula });

  /* Overlay .blk-modal costuma interceptar pointer — force no span correto costuma bastar */
  if (await tryClick(inPickerModal, true)) {
    return;
  }

  const spanMatricula = page.locator(`span.matricula`).filter({ hasText: safeMatricula });
  if (await tryClick(spanMatricula)) {
    return;
  }

  const rowLike = page.locator(`section.blk-modal li, section.blk-modal tr, .modal-body li`).filter({ hasText: safeMatricula });
  if (await tryClick(rowLike)) {
    return;
  }

  await dismissEmbasaBlockingModals(page);

  if (await tryClick(inPickerModal, true)) {
    return;
  }
  if (await tryClick(spanMatricula, true)) {
    return;
  }

  const fallback = page.getByText(safeMatricula, { exact: true }).first();
  if (await tryClick(fallback)) {
    return;
  }

  throw new Error(
    `Embasa: não foi possível selecionar a matrícula ${safeMatricula} (modal .blk-modal ou overlay bloqueando).`,
  );
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
  await dismissEmbasaBlockingModals(page);

  const clickedDropdown = await clickFirst(page, [
    'button:has-text("Matrícula")',
    'div:has-text("Matrícula: Selecionar")',
  ]);

  if (!clickedDropdown) {
    return;
  }

  await page.waitForTimeout(800);

  await clickEmbasaMatriculaOption(page, matricula);

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

/**
 * Quando a 2ª via responde "não possui débitos", o PDF não existe — mas a home expõe o carrossel MINHAS CONTAS.
 */
async function extractEmbasaInvoicesFromHome(page) {
  await page.waitForSelector(".card-minhas-contas .inner-card", { timeout: 25000 });

  const data = await page.evaluate(() => {
    const PT = {
      janeiro: "01",
      fevereiro: "02",
      março: "03",
      marco: "03",
      abril: "04",
      maio: "05",
      junho: "06",
      julho: "07",
      agosto: "08",
      setembro: "09",
      outubro: "10",
      novembro: "11",
      dezembro: "12",
    };

    const stripDiacritics = (s) => s.normalize("NFD").replace(/[\u0300-\u036f]/g, "");

    const cards = Array.from(document.querySelectorAll(".card-minhas-contas .inner-card"));

    return cards.map((card) => {
      const title =
        card.querySelector("h3.title-card-carousel, h3.h6.title-card-carousel, h3.h6")?.textContent?.trim() || "";

      const sections = Array.from(card.querySelectorAll(".section-data"));
      const pickValue = (labelNeedle) => {
        for (const sec of sections) {
          const lab = sec.querySelector(".data-label-card")?.textContent?.trim() || "";
          if (lab.toLowerCase().includes(labelNeedle.toLowerCase())) {
            return sec.querySelector(".data-value-card")?.textContent?.trim() || null;
          }
        }
        return null;
      };

      const vencimento = pickValue("Vencimento");
      const consumo = pickValue("Consumo");
      const valor = pickValue("Valor");

      const statusBtn = Array.from(card.querySelectorAll("a.btn.stratched-link")).find(
        (a) => !a.classList.contains("btn-a-pagar"),
      );
      const statusText = (statusBtn?.textContent || "").replace(/\s+/g, " ").trim();

      const parts = title.split(/\s+/).filter(Boolean);
      let referencia = null;
      if (parts.length >= 2) {
        const monthToken = stripDiacritics(parts[0]).toLowerCase();
        const yearToken = parts[parts.length - 1];
        const mm = PT[monthToken];
        if (mm && /^\d{4}$/.test(yearToken)) {
          referencia = `${mm}/${yearToken}`;
        }
      }

      return {
        referencia,
        vencimento,
        consumo_m3: consumo,
        valor_total: valor,
        status_raw: statusText || null,
      };
    });
  });

  return data
    .map((invoice) => ({
      referencia: invoice.referencia,
      vencimento: normalizeDate(invoice.vencimento),
      consumo_m3:
        invoice.consumo_m3 != null && String(invoice.consumo_m3).trim() !== ""
          ? Number(String(invoice.consumo_m3).replace(/[^\d]/g, "")) || null
          : null,
      valor_agua: null,
      valor_esgoto: null,
      valor_servico: null,
      valor_total: normalizeMoney(invoice.valor_total),
      status: mapEmbasaStatus(invoice.status_raw || ""),
      status_raw: invoice.status_raw,
    }))
    .filter((row) => row.referencia && row.vencimento);
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

/**
 * Após "PRÓXIMO" na 2ª via: extrai da tabela de débitos ou, se não houver débitos em aberto, vai à home (MINHAS CONTAS).
 */
async function loadEmbasaFaturasAfterSecondViaSearch(page, matricula) {
  await page.waitForTimeout(2000);

  const bodyText = (await page.locator("body").innerText().catch(() => "")) || "";
  const noOpenDebts = /não possui débitos/i.test(bodyText);

  if (!noOpenDebts) {
    await page.waitForTimeout(600);
    const fromDebtsPage = await extractEmbasaInvoices(page);
    if (fromDebtsPage.length > 0) {
      return fromDebtsPage;
    }
  }

  await page.goto(EMBASA_HOME_URL, { waitUntil: "domcontentloaded" });
  await page.waitForTimeout(1200);
  await selectEmbasaMatricula(page, matricula);
  await page.waitForTimeout(800);

  const fromHome = await extractEmbasaInvoicesFromHome(page);
  if (fromHome.length === 0) {
    const screenshotPath = await captureDebugScreenshot(page, "embasa-home-carousel-empty");
    throw new Error(
      `Embasa: sem faturas na 2ª via e carrossel MINHAS CONTAS vazio ou ilegível. Screenshot: ${screenshotPath}`,
    );
  }

  return fromHome;
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
        await dismissEmbasaBlockingModals(page);
        await selectEmbasaMatricula(page, matricula);
        await page.goto(EMBASA_SECOND_VIA_URL, { waitUntil: "domcontentloaded" });

        await submitEmbasaDebtsSearch(page, matricula);

        const invoices = await loadEmbasaFaturasAfterSecondViaSearch(page, matricula);

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
