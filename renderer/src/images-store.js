const fs = require('node:fs/promises');
const path = require('node:path');

const EXT_BY_TYPE = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };

// Photos are copied next to index.html so a published proposal never depends
// on a CDN that can expire: Higgsfield's did, and by 2026-09-23 one of the
// seven photos of proposal 42 no longer loaded. A photo that fails to
// download keeps its remote URL — a slide with a possibly-stale photo beats a
// slide with none — and the report says which ones, so the caller can flag it.
async function persistImages(images, outputDir, { fetchImpl = fetch, timeoutMs = 30000 } = {}) {
  const imgDir = path.join(outputDir, 'img');
  await fs.mkdir(imgDir, { recursive: true });
  const stored = {};
  const report = { saved: [], kept_remote: [] };

  for (const [slide, url] of Object.entries(images || {})) {
    if (!url) continue;
    if (!/^https?:\/\//i.test(url)) {
      stored[slide] = url;
      continue;
    }
    try {
      const res = await fetchImpl(url, { signal: AbortSignal.timeout(timeoutMs) });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const type = String(res.headers.get('content-type') || '').split(';')[0].trim().toLowerCase();
      const ext = EXT_BY_TYPE[type];
      if (!ext) throw new Error(`not an image: ${type || 'sin content-type'}`);
      const name = `${slide.replace(/[^a-z0-9_-]/gi, '')}.${ext}`;
      await fs.writeFile(path.join(imgDir, name), Buffer.from(await res.arrayBuffer()));
      stored[slide] = `img/${name}`;
      report.saved.push(slide);
    } catch (err) {
      console.error(`persistImages: kept remote url for "${slide}": ${err.message}`);
      stored[slide] = url;
      report.kept_remote.push(slide);
    }
  }
  return { images: stored, report };
}

module.exports = { persistImages };
