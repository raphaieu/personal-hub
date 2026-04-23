import express from "express";
import { getSessionPath, hasSessionFile, performThreadsLogin } from "./src/threads-auth.js";
import { scrapeByKeyword, scrapeByUrl } from "./src/threads-scraper.js";

const app = express();
app.use(express.json({ limit: "1mb" }));

const PORT = Number(process.env.PORT || 3001);

app.get("/health", async (_req, res) => {
  const session_ready = await hasSessionFile();

  res.status(200).json({
    status: "ok",
    service: "threads-playwright",
    session_ready,
    session_path: getSessionPath(),
    now: new Date().toISOString(),
  });
});

app.post("/threads/auth/login", async (req, res) => {
  try {
    const forceRelogin = Boolean(req.body?.force_relogin);
    const result = await performThreadsLogin({ forceRelogin });
    return res.status(200).json({
      success: true,
      ...result,
      now: new Date().toISOString(),
    });
  } catch (error) {
    return res.status(500).json({
      success: false,
      error: error instanceof Error ? error.message : "Falha no login do Threads.",
      now: new Date().toISOString(),
    });
  }
});

app.post("/threads/scrape-url", async (req, res) => {
  const { url } = req.body || {};

  try {
    const payload = await scrapeByUrl({ url });
    const status = payload.success ? 200 : 502;
    return res.status(status).json(payload);
  } catch (error) {
    return res.status(500).json({
      success: false,
      mode: "url",
      source_value: url || null,
      error: error instanceof Error ? error.message : "Falha inesperada no endpoint scrape-url.",
      scraped_at: new Date().toISOString(),
    });
  }
});

app.post("/threads/scrape-keyword", async (req, res) => {
  const { keyword, max_posts, include_comments, known_post_ids, only_new, known_streak_stop } = req.body || {};

  try {
    const payload = await scrapeByKeyword({
      keyword,
      max_posts,
      include_comments,
      known_post_ids,
      only_new,
      known_streak_stop,
    });
    const status = payload.success ? 200 : 502;
    return res.status(status).json(payload);
  } catch (error) {
    return res.status(500).json({
      success: false,
      mode: "keyword",
      source_value: keyword || null,
      error: error instanceof Error ? error.message : "Falha inesperada no endpoint scrape-keyword.",
      scraped_at: new Date().toISOString(),
    });
  }
});

app.listen(PORT, () => {
  console.log(`[playwright] threads service listening on port ${PORT}`);
});
