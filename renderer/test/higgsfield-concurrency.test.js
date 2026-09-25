const test = require('node:test');
const assert = require('node:assert/strict');
const { generateProposalImages } = require('../src/higgsfield');

const SIX_BRIEFS = [
  { slide: 'cover', prompt: 'a' },
  { slide: 'challenge', prompt: 'b' },
  { slide: 'solution', prompt: 'c' },
  { slide: 'benefits', prompt: 'd' },
  { slide: 'how_it_works', prompt: 'e' },
  { slide: 'pricing', prompt: 'f' },
];

// Counts how many image jobs are in flight at once. A "job" is in flight
// from its submit until its status call resolves as completed.
function makeTrackingFetch(tracker) {
  return async (url) => {
    if (String(url).endsWith('/soul/v2/standard')) {
      tracker.inFlight += 1;
      tracker.max = Math.max(tracker.max, tracker.inFlight);
      tracker.submits += 1;
      await new Promise((r) => setTimeout(r, 5));
      return {
        ok: true,
        json: async () => ({
          status: 'queued',
          status_url: 'https://api.higgsfield.ai/requests/req/status',
        }),
      };
    }
    tracker.inFlight -= 1;
    return {
      ok: true,
      json: async () => ({ status: 'completed', images: [{ url: 'https://cdn.example.com/i.png' }] }),
    };
  };
}

test('never puts more than one image job in flight at once', async () => {
  const tracker = { inFlight: 0, max: 0, submits: 0 };
  const originalFetch = global.fetch;
  global.fetch = makeTrackingFetch(tracker);
  try {
    const result = await generateProposalImages(SIX_BRIEFS, {
      keyId: 'id',
      keySecret: 'secret',
    });
    // The regression this guards: Higgsfield serves this account serially,
    // so anything in flight beyond the first only queues behind it and the
    // tail times out. Six at once left a real client proposal with 2 of 6
    // photos; batches of three left it with none.
    assert.equal(tracker.max, 1, `expected one job at a time, saw ${tracker.max}`);
    assert.equal(tracker.submits, 6, 'every brief must still be requested');
    for (const brief of SIX_BRIEFS) {
      assert.equal(result[brief.slide], 'https://cdn.example.com/i.png');
    }
  } finally {
    global.fetch = originalFetch;
  }
});

test('still resolves every slide when the batch is smaller than the concurrency cap', async () => {
  const tracker = { inFlight: 0, max: 0, submits: 0 };
  const originalFetch = global.fetch;
  global.fetch = makeTrackingFetch(tracker);
  try {
    const result = await generateProposalImages([{ slide: 'cover', prompt: 'a' }], {
      keyId: 'id',
      keySecret: 'secret',
    });
    assert.equal(result.cover, 'https://cdn.example.com/i.png');
    assert.equal(tracker.submits, 1);
  } finally {
    global.fetch = originalFetch;
  }
});

test('a slide that keeps failing resolves to null without holding up the rest', async () => {
  const originalFetch = global.fetch;
  const originalConsoleError = console.error;
  console.error = () => {};
  global.fetch = async (url) => {
    if (String(url).endsWith('/soul/v2/standard')) {
      // Only the cover is rejected; the other briefs go through.
      return { ok: true, json: async () => ({ status: 'queued', status_url: 'https://x/status' }) };
    }
    return { ok: true, json: async () => ({ status: 'failed' }) };
  };
  try {
    const result = await generateProposalImages(
      [{ slide: 'cover', prompt: 'a' }, { slide: 'pricing', prompt: 'b' }],
      { keyId: 'id', keySecret: 'secret' }
    );
    assert.equal(result.cover, null);
    assert.equal(result.pricing, null);
  } finally {
    global.fetch = originalFetch;
    console.error = originalConsoleError;
  }
});
