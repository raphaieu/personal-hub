import fs from "node:fs/promises";
import path from "node:path";
import { createAuthenticatedContext, createBrowser, humanDelay } from "./threads-auth.js";

const DEFAULT_TIMEOUT_MS = Number(process.env.THREADS_STEP_TIMEOUT_MS || 30000);
const MAX_POSTS_PER_KEYWORD = Number(process.env.THREADS_MAX_POSTS_PER_KEYWORD || 20);
const MAX_SCROLL_ROUNDS = Number(process.env.THREADS_MAX_SCROLL_ROUNDS || 120);
const SCROLL_IDLE_ROUNDS = Number(process.env.THREADS_SCROLL_IDLE_ROUNDS || 6);
const KNOWN_STREAK_STOP_DEFAULT = Number(process.env.THREADS_KNOWN_STREAK_STOP || 20);
const DEBUG_DIR = process.env.THREADS_DEBUG_DIR || "/app/downloads/threads-debug";
const SHUTDOWN_TIMEOUT_MS = Number(process.env.THREADS_SHUTDOWN_TIMEOUT_MS || 5000);

function nowIso() {
  return new Date().toISOString();
}

function normalizeText(value) {
  return (value || "").replace(/\s+/g, " ").trim();
}

function extractExternalIdFromPostUrl(url) {
  const match = (url || "").match(/\/post\/([^/?#]+)/i);
  return match ? match[1] : null;
}

async function ensureDir(dirPath) {
  await fs.mkdir(dirPath, { recursive: true });
}

async function captureDebugScreenshot(page, prefix) {
  await ensureDir(DEBUG_DIR);
  const fileName = `${prefix}-${Date.now()}.png`;
  const fullPath = path.join(DEBUG_DIR, fileName);
  await page.screenshot({ path: fullPath, fullPage: true });
  return fullPath;
}

async function extractPostAndComments(page, sourceTag) {
  return page.evaluate((tag) => {
    const normalize = (value) => (value || "").replace(/\s+/g, " ").trim();

    const cleanContent = (value) => {
      return normalize(value)
        .replace(/\b(Like|Curtir|Reply|Responder|Repost|Repostar|Share|Compartilhar)\b/gi, "")
        .replace(/\bTranslate\b/gi, "")
        .trim();
    };

    const getHref = (root, selectors) => {
      for (const selector of selectors) {
        const el = root.querySelector(selector);
        const href = el?.getAttribute?.("href");
        if (href) {
          return href;
        }
      }

      return "";
    };

    const toHandle = (href) => {
      if (!href) return null;
      const match = href.match(/\/@([^/?#]+)/);
      return match ? `@${match[1]}` : null;
    };

    const cardNodes = Array.from(document.querySelectorAll('[data-pagelet^="threads_post_page"]'));
    const articleNodes = Array.from(document.querySelectorAll("article"));

    const extractCardPayload = (node, idx, fallbackExternalId) => {
      const handleHref = getHref(node, ["a[href*='/@']"]);
      const handle = toHandle(handleHref);
      const timeEl = node.querySelector("time");
      const datetime = timeEl?.getAttribute?.("datetime") || null;
      const content = extractBestText(node);

      if (!content || content.length < 10) {
        return null;
      }

      return {
        external_id: `${fallbackExternalId}-comment-${idx}-${datetime || "na"}`,
        author_handle: handle,
        author_name: handle,
        content,
        posted_at: datetime,
      };
    };

    const resolveRootItems = () => {
      if (cardNodes.length > 0) {
        return cardNodes;
      }

      return articleNodes;
    };

    const rootItems = resolveRootItems();
    const postRoot = rootItems.at(0) || articleNodes.at(0) || null;
    const postHandleHref = postRoot ? getHref(postRoot, ["a[href*='/@']"]) : "";
    const postHandle = toHandle(postHandleHref);
    const postTimeEl = postRoot?.querySelector?.("time");
    const postExternalId =
      (window.location.pathname || "").split("/").filter(Boolean).pop() ||
      postTimeEl?.getAttribute?.("datetime") ||
      `${Date.now()}`;

    const extractBestText = (node) => {
      const candidateSelectors = [
        "div[dir='auto']",
        "span[dir='auto']",
        "div[data-lexical-text='true']",
      ];

      const snippets = [];
      for (const selector of candidateSelectors) {
        const elements = node.querySelectorAll(selector);
        for (const element of elements) {
          const text = cleanContent(element.textContent || "");
          if (text.length >= 12) {
            snippets.push(text);
          }
        }
      }

      if (snippets.length > 0) {
        return snippets.sort((a, b) => b.length - a.length)[0];
      }

      return cleanContent(node.innerText || "");
    };

    const postContent = postRoot ? extractBestText(postRoot) : "";
    const comments = rootItems
      .slice(1)
      .map((node, idx) => extractCardPayload(node, idx, postExternalId))
      .filter(Boolean);

    return {
      source_tag: tag,
      post: {
        external_id: `${postExternalId}`,
        author_handle: postHandle,
        author_name: postHandle,
        content: postContent,
        post_url: window.location.href,
        posted_at: postTimeEl?.getAttribute?.("datetime") || null,
      },
      comments,
    };
  }, sourceTag);
}

async function waitForThreadReady(page) {
  await Promise.race([
    page.waitForSelector('[data-pagelet^="threads_post_page"]', { timeout: DEFAULT_TIMEOUT_MS }),
    page.waitForSelector("article", { timeout: DEFAULT_TIMEOUT_MS }),
  ]);

  await page.waitForFunction(
    () => {
      const firstCard =
        document.querySelector('[data-pagelet^="threads_post_page"]') || document.querySelector("article");
      if (!firstCard) return false;
      const text = (firstCard.textContent || "").replace(/\s+/g, " ").trim();
      return text.length >= 20;
    },
    { timeout: DEFAULT_TIMEOUT_MS },
  );
}

async function expandRepliesAndMore(page) {
  const selectors = [
    'button:has-text("View replies")',
    'button:has-text("Ver respostas")',
    'button:has-text("Show more")',
    'button:has-text("Ver mais")',
    'div[role="button"]:has-text("View replies")',
    'div[role="button"]:has-text("Ver respostas")',
  ];

  for (const selector of selectors) {
    const nodes = await page.locator(selector).all();
    for (const node of nodes.slice(0, 5)) {
      try {
        await node.click({ timeout: 1500 });
        await humanDelay();
      } catch {
        // ignora nós não interativos
      }
    }
  }
}

async function progressiveScroll(page, onStep = null) {
  let previousCards = 0;
  let previousHeight = 0;
  let idleRounds = 0;

  for (let i = 0; i < MAX_SCROLL_ROUNDS; i += 1) {
    if (typeof onStep === "function") {
      await onStep(i, "before");
    }

    if (i % 2 === 0) {
      await expandRepliesAndMore(page);
    }

    const metrics = await page.evaluate(() => {
      const cards = document.querySelectorAll('[data-pagelet^="threads_post_page"]').length;
      const fallbackArticles = document.querySelectorAll("article").length;
      const height = document.body?.scrollHeight || 0;

      return {
        cards: cards > 0 ? cards : fallbackArticles,
        height,
      };
    });

    const cardsDidNotGrow = metrics.cards <= previousCards;
    const heightDidNotGrow = metrics.height <= previousHeight;
    if (cardsDidNotGrow && heightDidNotGrow) {
      idleRounds += 1;
    } else {
      idleRounds = 0;
      previousCards = Math.max(previousCards, metrics.cards);
      previousHeight = Math.max(previousHeight, metrics.height);
    }

    if (idleRounds >= SCROLL_IDLE_ROUNDS) {
      break;
    }

    await page.mouse.wheel(0, 2200);
    await humanDelay();

    if (typeof onStep === "function") {
      await onStep(i, "after");
    }
  }
}

export async function scrapeByUrl({ url }) {
  if (!url) {
    throw new Error("Campo 'url' é obrigatório.");
  }

  const browser = await createBrowser();
  const context = await createAuthenticatedContext(browser);

  try {
    const page = await context.newPage();
    page.setDefaultTimeout(DEFAULT_TIMEOUT_MS);

    await page.goto(url, { waitUntil: "domcontentloaded" });
    await waitForThreadReady(page);
    await humanDelay();
    await expandRepliesAndMore(page);

    let bestPost = null;
    /** @type {Map<string, {external_id: string, author_handle: string|null, author_name: string|null, content: string, posted_at: string|null}>} */
    const commentsMap = new Map();

    const collectVisibleComments = async () => {
      const partial = await extractPostAndComments(page, "url");

      if (!bestPost || !normalizeText(bestPost.content)) {
        bestPost = partial.post;
      }

      for (const comment of partial.comments) {
        if (!commentsMap.has(comment.external_id)) {
          commentsMap.set(comment.external_id, comment);
        }
      }
    };

    await collectVisibleComments();

    await progressiveScroll(page, async () => {
      await collectVisibleComments();
    });
    await expandRepliesAndMore(page);
    await collectVisibleComments();

    const payload = {
      source_tag: "url",
      post: bestPost,
      comments: Array.from(commentsMap.values()),
    };

    if (!normalizeText(payload.post.content)) {
      const screenshot_path = await captureDebugScreenshot(page, "threads-url-empty-content");
      return {
        success: false,
        scraped_at: nowIso(),
        mode: "url",
        source_value: url,
        error: "Extração retornou post sem conteúdo após renderização.",
        screenshot_path,
      };
    }

    return {
      success: true,
      scraped_at: nowIso(),
      mode: "url",
      source_value: url,
      data: payload,
    };
  } catch (error) {
    const page = context.pages()[0];
    const screenshot_path = page ? await captureDebugScreenshot(page, "threads-url-error") : null;

    return {
      success: false,
      scraped_at: nowIso(),
      mode: "url",
      source_value: url,
      error: error instanceof Error ? error.message : "Erro desconhecido no scrape por URL.",
      screenshot_path,
    };
  } finally {
    await safeCloseContext(context);
    await safeCloseBrowser(browser);
  }
}

export async function scrapeByKeyword({
  keyword,
  max_posts = MAX_POSTS_PER_KEYWORD,
  include_comments = false,
  known_post_ids = [],
  only_new = false,
  known_streak_stop = KNOWN_STREAK_STOP_DEFAULT,
}) {
  if (!keyword) {
    throw new Error("Campo 'keyword' é obrigatório.");
  }

  const browser = await createBrowser();
  const context = await createAuthenticatedContext(browser);
  const output = [];
  const knownSet = new Set((Array.isArray(known_post_ids) ? known_post_ids : []).filter(Boolean));

  try {
    const page = await context.newPage();
    page.setDefaultTimeout(DEFAULT_TIMEOUT_MS);

    const query = encodeURIComponent(keyword);
    await page.goto(`https://www.threads.net/search?q=${query}`, { waitUntil: "domcontentloaded" });
    await humanDelay();
    await progressiveScroll(page);
    await expandRepliesAndMore(page);

    const links = await page.$$eval("a[href*='/post/']", (anchors) => {
      const unique = new Set();
      for (const anchor of anchors) {
        const href = anchor.getAttribute("href");
        if (href) {
          const full = href.startsWith("http") ? href : `https://www.threads.net${href}`;
          unique.add(full);
        }
      }

      return Array.from(unique);
    });

    const selected = links.slice(0, Number(max_posts));
    let knownStreak = 0;
    let earlyStopTriggered = false;
    let processedCount = 0;
    let skippedKnown = 0;
    let knownDetected = 0;
    let newDetected = 0;

    for (const postUrl of selected) {
      const preKnownExternalId = extractExternalIdFromPostUrl(postUrl);
      const isKnownByUrl = preKnownExternalId ? knownSet.has(preKnownExternalId) : false;

      if (isKnownByUrl) {
        knownDetected += 1;
        knownStreak += 1;
        if (only_new) {
          skippedKnown += 1;
          if (knownStreak >= Number(known_streak_stop)) {
            earlyStopTriggered = true;
            break;
          }
          continue;
        }
      } else {
        newDetected += 1;
        knownStreak = 0;
      }

      await page.goto(postUrl, { waitUntil: "domcontentloaded" });
      await humanDelay();
      await waitForThreadReady(page);

      if (!include_comments) {
        const partial = await extractPostAndComments(page, "keyword");
        if (partial?.post?.external_id && normalizeText(partial.post.content)) {
          const isKnownByContent = knownSet.has(partial.post.external_id);
          if (isKnownByContent && only_new) {
            skippedKnown += 1;
            knownStreak += 1;
            if (knownStreak >= Number(known_streak_stop)) {
              earlyStopTriggered = true;
              break;
            }
            continue;
          }

          output.push({
            source_tag: "keyword",
            post: partial.post,
            is_known: isKnownByContent || isKnownByUrl,
          });
          processedCount += 1;
        }

        if ((knownSet.has(partial?.post?.external_id) || isKnownByUrl) && Number(known_streak_stop) > 0) {
          if (knownStreak >= Number(known_streak_stop)) {
            earlyStopTriggered = true;
            break;
          }
        } else {
          knownStreak = 0;
        }
        continue;
      }

      let bestPost = null;
      const commentsMap = new Map();
      const collectVisibleComments = async () => {
        const partial = await extractPostAndComments(page, "keyword");
        if (!bestPost || !normalizeText(bestPost.content)) {
          bestPost = partial.post;
        }
        for (const comment of partial.comments) {
          if (!commentsMap.has(comment.external_id)) {
            commentsMap.set(comment.external_id, comment);
          }
        }
      };

      await collectVisibleComments();
      await progressiveScroll(page, async () => {
        await collectVisibleComments();
      });
      await expandRepliesAndMore(page);
      await collectVisibleComments();

      const payload = {
        source_tag: "keyword",
        post: bestPost,
        comments: Array.from(commentsMap.values()),
        is_known: bestPost?.external_id ? knownSet.has(bestPost.external_id) || isKnownByUrl : isKnownByUrl,
      };

      if (payload.is_known && only_new) {
        skippedKnown += 1;
        knownStreak += 1;
        if (knownStreak >= Number(known_streak_stop)) {
          earlyStopTriggered = true;
          break;
        }
        continue;
      }

      output.push(payload);
      processedCount += 1;
      knownStreak = payload.is_known ? knownStreak : 0;

      if (knownStreak >= Number(known_streak_stop) && Number(known_streak_stop) > 0) {
        earlyStopTriggered = true;
        break;
      }
    }

    const totalComments = include_comments
      ? output.reduce((carry, item) => carry + (Array.isArray(item.comments) ? item.comments.length : 0), 0)
      : 0;

    return {
      success: true,
      scraped_at: nowIso(),
      mode: "keyword",
      source_value: keyword,
      include_comments,
      only_new,
      data: {
        posts: output,
        stats: {
          posts_detected: links.length,
          posts_selected: selected.length,
          posts_processed: processedCount || output.length,
          known_detected: knownDetected,
          new_detected: newDetected,
          skipped_known: skippedKnown,
          early_stop_triggered: earlyStopTriggered,
          known_streak_stop: Number(known_streak_stop),
          comments_total: totalComments,
        },
      },
    };
  } catch (error) {
    const page = context.pages()[0];
    const screenshot_path = page ? await captureDebugScreenshot(page, "threads-keyword-error") : null;

    return {
      success: false,
      scraped_at: nowIso(),
      mode: "keyword",
      source_value: keyword,
      error: error instanceof Error ? error.message : "Erro desconhecido no scrape por keyword.",
      screenshot_path,
    };
  } finally {
    await safeCloseContext(context);
    await safeCloseBrowser(browser);
  }
}

async function safeCloseContext(context) {
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
    // Não propaga erro de shutdown para não travar o endpoint.
  }
}

async function safeCloseBrowser(browser) {
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
      // fallback best-effort
    }
  }
}
