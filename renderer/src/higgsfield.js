const ENDPOINT = 'https://platform.higgsfield.ai/higgsfield-ai/soul/standard';
const POLL_INTERVAL_MS = 5000;
const POLL_TIMEOUT_MS = 60000;

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
    throw new Error(`higgsfield submit failed: ${response.status}`);
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
      throw new Error(`higgsfield status check failed: ${response.status}`);
    }
    const data = await response.json();
    if (data.status === 'completed') return data;
    if (data.status === 'failed') throw new Error('higgsfield generation failed');
    await new Promise((resolve) => setTimeout(resolve, intervalMs));
  }
  throw new Error('higgsfield polling timed out');
}

async function generateImageUrl(prompt, credentials) {
  const submitted = await submitImageRequest(prompt, credentials);
  const completed = await pollUntilComplete(submitted.status_url, credentials);
  const url = completed.images && completed.images[0] && completed.images[0].url;
  if (!url) throw new Error('higgsfield response had no image url');
  return url;
}

/**
 * Generates one image per brief. Never throws — a failed brief resolves to
 * null so the template can fall back to a brand gradient for that slide.
 */
async function generateProposalImages(imageBriefs, credentials) {
  const briefs = Array.isArray(imageBriefs) ? imageBriefs : [];
  const results = await Promise.all(
    briefs.map(async (brief) => {
      try {
        const url = await generateImageUrl(brief.prompt, credentials);
        return { slide: brief.slide, url };
      } catch (err) {
        return { slide: brief.slide, url: null, error: err.message };
      }
    })
  );
  const bySlide = {};
  for (const result of results) {
    bySlide[result.slide] = result.url;
  }
  return bySlide;
}

module.exports = { submitImageRequest, pollUntilComplete, generateImageUrl, generateProposalImages };
