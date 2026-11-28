const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { promptHash, readManifest, writeManifest, reusablePhotos } = require('../src/photo-manifest');

test('promptHash is stable for the same prompt and differs for a different one', () => {
  const a1 = promptHash('una foto de un perro');
  const a2 = promptHash('una foto de un perro');
  const b = promptHash('una foto de un gato');
  assert.equal(a1, a2);
  assert.notEqual(a1, b);
  assert.match(a1, /^[0-9a-f]{64}$/);
});

test('readManifest returns {} when the manifest file is missing', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-manifest-'));
  try {
    const manifest = await readManifest(dir);
    assert.deepEqual(manifest, {});
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('readManifest returns {} for a corrupt manifest file instead of throwing', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-manifest-'));
  try {
    const imgDir = path.join(dir, 'img');
    await fs.mkdir(imgDir, { recursive: true });
    await fs.writeFile(path.join(imgDir, 'manifest.json'), '{ esto no es json valido');
    const manifest = await readManifest(dir);
    assert.deepEqual(manifest, {});
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('readManifest reads back what writeManifest wrote', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-manifest-'));
  try {
    const manifest = { cover: { file: 'cover.png', hash: promptHash('foto de portada') } };
    await writeManifest(dir, manifest);
    const read = await readManifest(dir);
    assert.deepEqual(read, manifest);
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('reusablePhotos returns only slides whose prompt hash matches and whose file still exists', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-manifest-'));
  try {
    const imgDir = path.join(dir, 'img');
    await fs.mkdir(imgDir, { recursive: true });
    await fs.writeFile(path.join(imgDir, 'cover.png'), 'PNGDATA');
    // pricing.png is recorded in the manifest but the file was deleted/never saved.
    const manifest = {
      cover: { file: 'cover.png', hash: promptHash('foto de portada') },
      pricing: { file: 'pricing.png', hash: promptHash('foto de precios') },
    };
    const briefs = [
      { slide: 'cover', prompt: 'foto de portada' }, // same prompt, file exists -> reusable
      { slide: 'pricing', prompt: 'foto de precios' }, // same prompt, file missing -> not reusable
      { slide: 'solution', prompt: 'foto de solucion' }, // not in manifest at all
    ];
    const reused = await reusablePhotos(briefs, manifest, dir);
    assert.deepEqual(reused, { cover: 'img/cover.png' });
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});

test('reusablePhotos does not reuse a slide whose prompt changed', async () => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'at-manifest-'));
  try {
    const imgDir = path.join(dir, 'img');
    await fs.mkdir(imgDir, { recursive: true });
    await fs.writeFile(path.join(imgDir, 'cover.png'), 'PNGDATA');
    const manifest = { cover: { file: 'cover.png', hash: promptHash('prompt viejo') } };
    const briefs = [{ slide: 'cover', prompt: 'prompt nuevo' }];
    const reused = await reusablePhotos(briefs, manifest, dir);
    assert.deepEqual(reused, {});
  } finally {
    await fs.rm(dir, { recursive: true, force: true });
  }
});
