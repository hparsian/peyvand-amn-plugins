import path from 'node:path';
import { fileURLToPath } from 'node:url';
import express from 'express';
import { dashboardRouter } from './src/routes/dashboard.js';
import { portalRouter } from './src/routes/portal.js';
import { legacyRouter } from './src/routes/legacy.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = Number(process.env.PORT || 3000);
const HOST = '0.0.0.0';

app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

app.use(express.urlencoded({ extended: true }));
app.use(express.json());
app.use('/assets', express.static(path.join(__dirname, 'public', 'assets')));

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
