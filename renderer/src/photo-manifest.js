const crypto = require('node:crypto');
const fs = require('node:fs/promises');
const path = require('node:path');

// A repeated POST /render for the same unique_id (n8n's "3 Final" flow retries
// up to 3 times while anything is missing) must not pay Higgsfield again for
// a photo it already generated. The manifest is the record, next to the
// downloaded photos, of which prompt produced which file, so a retry can tell
// "same brief, reuse the file" apart from "prompt changed, regenerate".

function promptHash(prompt) {
  return crypto.createHash('sha256').update(String(prompt)).digest('hex');
}

function manifestPath(outputDir) {
  return path.join(outputDir, 'img', 'manifest.json');
}

// Never throws: a missing or corrupt manifest is just an empty one, so a
// broken file can never take down a render that would otherwise succeed.
async function readManifest(outputDir) {
  try {
    const raw = await fs.readFile(manifestPath(outputDir), 'utf8');
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return {};
    return parsed;
  } catch (err) {
    return {};
  }
}

async function writeManifest(outputDir, manifest) {
  const imgDir = path.join(outputDir, 'img');
  await fs.mkdir(imgDir, { recursive: true });
  await fs.writeFile(manifestPath(outputDir), JSON.stringify(manifest || {}, null, 2));
}

// A brief is reusable only when both hold: the prompt that would be sent to
// Higgsfield today hashes the same as the one recorded for that slide, AND
// the downloaded file is still there (deleted/never-saved files must fall
// through to regeneration, not resolve to a broken path).
async function reusablePhotos(briefs, manifest, outputDir) {
  const list = Array.isArray(briefs) ? briefs : [];
  const source = manifest && typeof manifest === 'object' ? manifest : {};
  const reused = {};

  for (const brief of list) {
    const slide = brief && brief.slide;
    const prompt = brief && brief.prompt;
    if (!slide || !prompt) continue;
    const entry = source[slide];
    if (!entry || !entry.file || entry.hash !== promptHash(prompt)) continue;
    try {
      await fs.access(path.join(outputDir, 'img', entry.file));
      reused[slide] = `img/${entry.file}`;
    } catch (err) {
      // File missing on disk: not reusable, fall through to regeneration.
    }
  }

  return reused;
}

module.exports = { promptHash, readManifest, writeManifest, reusablePhotos };
