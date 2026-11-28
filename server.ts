import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import express from 'express';
import { dashboardRouter } from './src/routes/dashboard.js';
import { portalRouter } from './src/routes/portal.js';
import { legacyRouter } from './src/routes/legacy.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = fs.existsSync(path.join(__dirname, 'views'))
  ? __dirname
  : path.resolve(__dirname, '..');

const app = express();
const PORT = Number(process.env.PORT || 3000);
const HOST = '0.0.0.0';

app.disable('x-powered-by');
app.use((_req, res, next) => {
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
  res.setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
  next();
});

app.set('view engine', 'ejs');
app.set('views', path.join(rootDir, 'views'));

app.use(express.urlencoded({ extended: true }));
app.use(express.json());
app.use('/assets', express.static(path.join(rootDir, 'public', 'assets')));

app.get('/', (_req, res) => {
  res.redirect('/dashboard?module=overview');
});

app.get('/health', (_req, res) => {
  res.json({ ok: true, service: 'peyvand-amn-clinical-suite' });
});

app.use('/dashboard', dashboardRouter);
app.use('/', dashboardRouter);
app.use('/portal', portalRouter);
app.use('/api/legacy', legacyRouter);

app.listen(PORT, HOST, () => {
  console.log(`Peyvand Amn Clinical Suite listening on http://${HOST}:${PORT}`);
});
