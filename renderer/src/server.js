const path = require('node:path');
const express = require('express');

function createApp({ publicDir, baseUrl, higgsfieldCredentials }) {
  const app = express();
  app.use(express.json({ limit: '2mb' }));

  // Iframe embedding must keep working for ver-presentacion.php — never set
  // X-Frame-Options or a frame-ancestors CSP here.
  app.use('/p', express.static(publicDir));

  app.get('/health', (req, res) => {
    res.json({ status: 'ok' });
  });

  return app;
}

function start() {
  const port = process.env.PORT || 3000;
  const publicDir = process.env.PUBLIC_DIR || path.join(__dirname, '..', 'public');
  const baseUrl = process.env.BASE_URL || `http://localhost:${port}`;
  const app = createApp({
    publicDir,
    baseUrl,
    higgsfieldCredentials: {
      keyId: process.env.HF_API_KEY_ID,
      keySecret: process.env.HF_API_KEY_SECRET,
    },
  });
  app.listen(port, () => {
    console.log(`propuesta-renderer listening on :${port}`);
  });
}

module.exports = { createApp, start };
