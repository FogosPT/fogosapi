'use strict';

const Fastify = require('fastify');
const { chromium } = require('playwright');

const PORT = parseInt(process.env.PORT || '3000', 10);
const HOST = process.env.HOST || '0.0.0.0';
const POOL_SIZE = parseInt(process.env.POOL_SIZE || '3', 10);
const CONTEXT_MAX_USES = parseInt(process.env.CONTEXT_MAX_USES || '50', 10);
const RENDER_TIMEOUT_MS = parseInt(process.env.RENDER_TIMEOUT_MS || '15000', 10);
const DEFAULT_WIDTH = parseInt(process.env.DEFAULT_WIDTH || '1000', 10);
const DEFAULT_HEIGHT = parseInt(process.env.DEFAULT_HEIGHT || '1300', 10);
const SETTLE_MS = parseInt(process.env.SETTLE_MS || '1500', 10);

let browser = null;
const pool = [];
const waiters = [];

async function createSlot() {
  const context = await browser.newContext({ acceptDownloads: false });
  await context.addCookies([
    {
      name: 'CookieConsent',
      value: '{stamp:%27-%27%2Cnecessary:true%2Cpreferences:true%2Cstatistics:true%2Cmarketing:true%2Cver:1}',
      domain: '.fogos.pt',
      path: '/',
    },
  ]);
  return { context, uses: 0 };
}

async function recycleSlot(slot) {
  try { await slot.context.close(); } catch (_) {}
  return createSlot();
}

async function acquireSlot() {
  if (pool.length > 0) return pool.pop();
  return new Promise((resolve) => waiters.push(resolve));
}

function releaseSlot(slot) {
  const next = waiters.shift();
  if (next) next(slot); else pool.push(slot);
}

async function init() {
  browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  for (let i = 0; i < POOL_SIZE; i++) {
    pool.push(await createSlot());
  }
}

async function render({ url, width, height, waitFor }) {
  let slot = await acquireSlot();
  let page;
  try {
    page = await slot.context.newPage();
    await page.setViewportSize({
      width: width || DEFAULT_WIDTH,
      height: height || DEFAULT_HEIGHT,
    });
    await page.goto(url, { waitUntil: 'networkidle', timeout: RENDER_TIMEOUT_MS });
    if (waitFor) {
      await page.waitForSelector(waitFor, { timeout: RENDER_TIMEOUT_MS });
    }
    if (SETTLE_MS > 0) await page.waitForTimeout(SETTLE_MS);
    const buffer = await page.screenshot({ type: 'png' });
    return buffer;
  } finally {
    if (page) { try { await page.close(); } catch (_) {} }
    slot.uses += 1;
    if (slot.uses >= CONTEXT_MAX_USES) {
      try { slot = await recycleSlot(slot); } catch (e) { slot = await createSlot(); }
    }
    releaseSlot(slot);
  }
}

const app = Fastify({ logger: { level: process.env.LOG_LEVEL || 'info' } });

app.get('/healthz', async () => ({
  ok: true,
  pool: pool.length,
  waiters: waiters.length,
}));

app.post('/render', async (request, reply) => {
  const { url, width, height, waitFor, minBytes } = request.body || {};
  if (!url || typeof url !== 'string') {
    return reply.code(400).send({ error: 'url is required' });
  }

  const started = Date.now();
  let buffer;
  try {
    buffer = await Promise.race([
      render({ url, width, height, waitFor }),
      new Promise((_, reject) => setTimeout(
        () => reject(new Error('render timeout')), RENDER_TIMEOUT_MS + 5000)),
    ]);
  } catch (err) {
    request.log.error({ err: err.message, url, ms: Date.now() - started }, 'render failed');
    return reply.code(502).send({ error: err.message });
  }

  if (minBytes && buffer.length < minBytes) {
    request.log.warn({ url, bytes: buffer.length, minBytes }, 'render below minBytes');
    return reply.code(422).send({ error: 'below minBytes', bytes: buffer.length });
  }

  request.log.info({ url, bytes: buffer.length, ms: Date.now() - started }, 'render ok');
  reply.header('Content-Type', 'image/png').header('Content-Length', buffer.length).send(buffer);
});

(async () => {
  try {
    await init();
    await app.listen({ host: HOST, port: PORT });
    app.log.info({ pool: POOL_SIZE }, 'fogos-renderer ready');
  } catch (err) {
    console.error(err);
    process.exit(1);
  }
})();

for (const sig of ['SIGINT', 'SIGTERM']) {
  process.on(sig, async () => {
    app.log.info({ sig }, 'shutting down');
    try { await app.close(); } catch (_) {}
    try { if (browser) await browser.close(); } catch (_) {}
    process.exit(0);
  });
}
