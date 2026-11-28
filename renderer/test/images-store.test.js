const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { persistImages } = require('../src/images-store');

function fakeFetch(map) {
  return async (url) => {
    const hit = map[url];
    if (!hit) return { ok: false, status: 404, headers: { get: () => 'text/html' }, arrayBuffer: async () => new ArrayBuffer(0) };
    return { ok: true, status: 200, headers: { get: () => hit.type }, arrayBuffer: async () => Buffer.from(hit.body) };
  };
}

test('downloads remote photos next to index.html and returns relative paths', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-store-'));
  try {
    const fetchImpl = fakeFetch({ 'https://cdn.example.com/a.png': { type: 'image/png', body: 'PNGDATA' } });
    const { images, report } = await persistImages({ cover: 'https://cdn.example.com/a.png' }, dir, { fetchImpl });
    assert.equal(images.cover, 'img/cover.png');
    assert.deepEqual(report.saved, ['cover']);
    assert.equal(await fs.readFile(path.join(dir, 'img', 'cover.png'), 'utf8'), 'PNGDATA');
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('a photo that cannot be downloaded keeps its remote url', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-store-'));
  try {
    const { images, report } = await persistImages({ challenge: 'https://cdn.example.com/gone.png' }, dir, { fetchImpl: fakeFetch({}) });
    assert.equal(images.challenge, 'https://cdn.example.com/gone.png');
    assert.deepEqual(report.kept_remote, ['challenge']);
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('non-image responses are not saved and empty slides are skipped', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-store-'));
  try {
    const fetchImpl = fakeFetch({ 'https://x.cl/page': { type: 'text/html', body: '<html>' } });
    const { images } = await persistImages({ pricing: 'https://x.cl/page', solution: null }, dir, { fetchImpl });
    assert.equal(images.pricing, 'https://x.cl/page');
    assert.ok(!('solution' in images));
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});
