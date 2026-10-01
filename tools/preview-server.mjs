/* Preview-сервер: PHP (WASM) + раздача статики. Только для предпросмотра. */
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { PHP, PHPRequestHandler } from '@php-wasm/universal';
import { loadNodeRuntime, createNodeFsMountHandler } from '@php-wasm/node';

const ROOT = process.env.SVR_ROOT || '/home/user/sitevrost';
const PORT = Number(process.env.SVR_PORT || 8080);

const php = new PHP(await loadNodeRuntime('8.2', { emscriptenOptions: { processId: process.pid } }));
await php.mount('/app', createNodeFsMountHandler(ROOT));
const handler = new PHPRequestHandler({
  php,
  documentRoot: '/app',
  absoluteUrl: 'http://localhost:' + PORT,
  cookieStore: false,
  getFileNotFoundAction: () => ({ type: 'internal-redirect', uri: '/index.php' }),
});
console.log('PHP runtime ready, docroot /app ->', ROOT);

const MIME = {
  '.css': 'text/css', '.js': 'application/javascript', '.mjs': 'application/javascript',
  '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.gif': 'image/gif',
  '.webp': 'image/webp', '.avif': 'image/avif', '.svg': 'image/svg+xml', '.ico': 'image/x-icon',
  '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.txt': 'text/plain',
  '.json': 'application/json', '.map': 'application/json',
};
const STATIC_RE = /\.(css|js|mjs|map|jpg|jpeg|png|gif|webp|avif|svg|ico|woff2?|ttf|eot)$/i;

const server = http.createServer((req, res) => {
  const url = new URL(req.url, 'http://localhost');
  const p = decodeURIComponent(url.pathname);
  if (STATIC_RE.test(p) && !p.includes('..')) {
    const fp = path.join(ROOT, p);
    if (fs.existsSync(fp) && fs.statSync(fp).isFile()) {
      res.writeHead(200, { 'content-type': MIME[path.extname(fp).toLowerCase()] || 'application/octet-stream', 'cache-control': 'max-age=3600' });
      fs.createReadStream(fp).pipe(res);
      return;
    }
  }
  const chunks = [];
  req.on('data', (c) => chunks.push(c));
  req.on('end', async () => {
    const body = Buffer.concat(chunks);
    try {
      const headers = {};
      for (const [k, v] of Object.entries(req.headers)) headers[k] = Array.isArray(v) ? v.join(', ') : String(v);
      headers['host'] = 'sitevrost.local';
      const r = await handler.request({
        url: 'http://localhost:' + PORT + req.url,
        method: req.method,
        headers,
        body,
      });
      const outHeaders = {};
      for (const [k, v] of Object.entries(r.headers || {})) {
        const key = k.toLowerCase();
        if (Array.isArray(v)) outHeaders[key] = v.length === 1 ? v[0] : v;
        else outHeaders[key] = v;
      }
      if (!outHeaders['content-type']) outHeaders['content-type'] = 'text/html; charset=utf-8';
      res.writeHead(r.httpStatusCode || 200, outHeaders);
      res.end(Buffer.from(r.bytes || []));
    } catch (e) {
      res.writeHead(500, { 'content-type': 'text/plain; charset=utf-8' });
      res.end('PHP runtime error: ' + (e && e.message ? e.message : e));
    }
  });
});
server.listen(PORT, '0.0.0.0', () => console.log('preview listening on', PORT));
