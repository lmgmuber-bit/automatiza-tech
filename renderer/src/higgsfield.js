const ENDPOINT = 'https://platform.higgsfield.ai/higgsfield-ai/soul/standard';
const POLL_INTERVAL_MS = 5000;
// Higgsfield's queue is the bottleneck, not the generation: measured 71s end-to-end
// (37s still queued) on 2026-08-30 vs ~12s earlier the same day. 60s silently
// dropped every slide to the brand-gradient fallback, so keep a wide margin.
// Ceiling is n8n's own 300s HTTP default on the "Renderizar Propuesta" node: slides poll
// in parallel, so worst case here is ~180s + render, leaving real margin under it.
const POLL_TIMEOUT_MS = 180000;
// Three at a time, one retry each, and a hard ceiling on the whole image
// phase: 6 slides must still land comfortably inside the timeout n8n gives
// the "Renderizar Propuesta" HTTP node (its default, 300s).
const CONCURRENCY = 3;
const MAX_ATTEMPTS = 2;
const PHASE_BUDGET_MS = 210000;

function authHeader(keyId, keySecret) {
  return `Key ${keyId}:${keySecret}`;
}

async function submitImageRequest(prompt, { keyId, keySecret, fetchImpl = fetch } = {}) {
  const response = await fetchImpl(ENDPOINT, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: authHeader(keyId, keySecret),
    },
    body: JSON.stringify({ prompt, aspect_ratio: '16:9', resolution: '720p' }),
  });
  if (!response.ok) {
    const body = await response.text().catch(() => '');
    throw new Error(`higgsfield submit failed: ${response.status} ${body}`.trim());
  }
  return response.json();
}

async function pollUntilComplete(
  statusUrl,
  { keyId, keySecret, fetchImpl = fetch, intervalMs = POLL_INTERVAL_MS, timeoutMs = POLL_TIMEOUT_MS } = {}
) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    const response = await fetchImpl(statusUrl, { headers: { Authorization: authHeader(keyId, keySecret) } });
    if (!response.ok) {
      const body = await response.text().catch(() => '');
      throw new Error(`higgsfield status check failed: ${response.status} ${body}`.trim());
    }
    const data = await response.json();
    if (data.status === 'completed') return data;
    if (data.status === 'failed') throw new Error('higgsfield generation failed');
    await new Promise((resolve) => setTimeout(resolve, intervalMs));
  }
  throw new Error('higgsfield polling timed out');
}

async function generateImageUrl(prompt, credentials, { timeoutMs } = {}) {
  const submitted = await submitImageRequest(prompt, credentials);
  const pollOptions = timeoutMs ? { ...credentials, timeoutMs } : credentials;
  const completed = await pollUntilComplete(submitted.status_url, pollOptions);
  const url = completed.images && completed.images[0] && completed.images[0].url;
  if (!url) throw new Error('higgsfield response had no image url');
  return url;
}

/**
 * Generates one image per brief. Never throws — a failed brief resolves to
 * null so the template can fall back to a brand gradient for that slide.
 *
 * Requests go out a few at a time, never all six at once. Firing the whole
 * batch simultaneously congests Higgsfield's own queue: on 2026-08-31 a real
 * proposal came back with 2 of 6 photos, the other four logging "polling
 * timed out", while a single image requested on its own finished in 71s.
 * Fewer in flight means each one actually gets served inside its window.
 */
async function generateProposalImages(imageBriefs, credentials) {
  const briefs = Array.isArray(imageBriefs) ? imageBriefs : [];
  const bySlide = {};
  const deadline = Date.now() + PHASE_BUDGET_MS;
  let cursor = 0;

  async function worker() {
    while (cursor < briefs.length) {
      const brief = briefs[cursor++];
      const slide = brief && brief.slide;
      const prompt = brief && brief.prompt;
      if (!slide || !prompt) {
        // Malformed entry (null/undefined, or missing slide/prompt): never
        // let it reach generateImageUrl or throw — degrade to a resolved
        // null result when we at least have a slide id, otherwise skip it.
        if (slide) bySlide[slide] = null;
        continue;
      }

      let url = null;
      for (let attempt = 1; attempt <= MAX_ATTEMPTS && url === null; attempt++) {
        const remaining = deadline - Date.now();
        // The whole phase is bounded so the render always answers well
        // inside the timeout n8n gives the "Renderizar Propuesta" node. A
        // slide with no time left keeps its brand-gradient fallback rather
        // than dragging the entire proposal past that ceiling.
        if (remaining <= 0) {
          console.error(
            `higgsfield image generation skipped for slide "${slide}": image phase budget exhausted`
          );
          break;
        }
        try {
          url = await generateImageUrl(prompt, credentials, {
            timeoutMs: Math.min(POLL_TIMEOUT_MS, remaining),
          });
        } catch (err) {
          // Deliberately never rethrown (contract: this function never
          // throws), but the failure must be visible somewhere or a broken
          // Higgsfield credential/quota degrades every proposal to
          // no-images with zero diagnostic trail. Log it.
          console.error(
            `higgsfield image generation failed for slide "${slide}" (intento ${attempt}/${MAX_ATTEMPTS}): ${err.message}`
          );
        }
      }
      bySlide[slide] = url;
    }
  }

  const workers = Math.min(CONCURRENCY, briefs.length);
  await Promise.all(Array.from({ length: workers }, () => worker()));
  return bySlide;
}

module.exports = { submitImageRequest, pollUntilComplete, generateImageUrl, generateProposalImages };
