const test = require('node:test');
const assert = require('node:assert/strict');
const { generateProposalImages } = require('../src/higgsfield');

function makeFakeFetch({
  submitOk = true,
  finalStatus = 'completed',
  imageUrl = 'https://cdn.example.com/img.jpg',
  submitStatus = 500,
  submitBody = '',
} = {}) {
  return async (url) => {
    if (String(url).endsWith('/soul/standard')) {
      if (!submitOk) return { ok: false, status: submitStatus, text: async () => submitBody };
      return {
        ok: true,
        json: async () => ({
          status: 'queued',
          request_id: 'req-1',
          status_url: 'https://platform.higgsfield.ai/requests/req-1/status',
        }),
      };
    }
    return {
      ok: true,
      json: async () => ({ status: finalStatus, request_id: 'req-1', images: [{ url: imageUrl }] }),
    };
  };
}

test('resolves an image url per slide on success', async () => {
  const originalFetch = global.fetch;
  global.fetch = makeFakeFetch();
  try {
    const result = await generateProposalImages(
      [{ slide: 'cover', prompt: 'foto de academia de béisbol' }],
      { keyId: 'id', keySecret: 'secret' }
    );
    assert.equal(result.cover, 'https://cdn.example.com/img.jpg');
  } finally {
    global.fetch = originalFetch;
  }
});

test('resolves to null (not a thrown error) when the submit call fails', async () => {
  const originalFetch = global.fetch;
  global.fetch = makeFakeFetch({ submitOk: false });
  try {
    const result = await generateProposalImages(
      [{ slide: 'cover', prompt: 'foto' }],
      { keyId: 'id', keySecret: 'secret' }
    );
    assert.equal(result.cover, null);
  } finally {
    global.fetch = originalFetch;
  }
});

test('surfaces the response body in the captured error when submit is rejected (e.g. 403 permission errors)', async () => {
  const originalFetch = global.fetch;
  global.fetch = makeFakeFetch({
    submitOk: false,
    submitStatus: 403,
    submitBody: '{"message":"plan does not include API access"}',
  });
  const errors = [];
  const originalConsoleError = console.error;
  console.error = (msg) => errors.push(msg);
  try {
    const result = await generateProposalImages(
      [{ slide: 'cover', prompt: 'foto' }],
      { keyId: 'id', keySecret: 'secret' }
    );
    assert.equal(result.cover, null);
    assert.equal(errors.length, 1);
    assert.match(errors[0], /403/);
    assert.match(errors[0], /plan does not include API access/);
  } finally {
    global.fetch = originalFetch;
    console.error = originalConsoleError;
  }
});

test('resolves to null when the job status ends up failed', async () => {
  const originalFetch = global.fetch;
  global.fetch = makeFakeFetch({ finalStatus: 'failed' });
  try {
    const result = await generateProposalImages(
      [{ slide: 'cover', prompt: 'foto' }],
      { keyId: 'id', keySecret: 'secret' }
    );
    assert.equal(result.cover, null);
  } finally {
    global.fetch = originalFetch;
  }
});

test('returns an empty object when there are no briefs', async () => {
  const result = await generateProposalImages(undefined, { keyId: 'id', keySecret: 'secret' });
  assert.deepEqual(result, {});
});

test('resolves multiple briefs independently, keyed by slide', async () => {
  const originalFetch = global.fetch;
  global.fetch = makeFakeFetch();
  try {
    const result = await generateProposalImages(
      [
        { slide: 'cover', prompt: 'a' },
        { slide: 'challenge', prompt: 'b' },
      ],
      { keyId: 'id', keySecret: 'secret' }
    );
    assert.equal(result.cover, 'https://cdn.example.com/img.jpg');
    assert.equal(result.challenge, 'https://cdn.example.com/img.jpg');
  } finally {
    global.fetch = originalFetch;
  }
});

test('never throws when the briefs array contains malformed entries', async () => {
  const originalFetch = global.fetch;
  global.fetch = makeFakeFetch();
  try {
    const result = await generateProposalImages(
      [null, undefined, { slide: 'cover' }, { prompt: 'no slide here' }],
      { keyId: 'id', keySecret: 'secret' }
    );
    // Entries with a slide but no usable prompt resolve to null for that slide.
    assert.equal(result.cover, null);
    // Entries with no slide identifier at all (null/undefined/no .slide) are
    // skipped rather than crashing the whole batch.
    assert.deepEqual(Object.keys(result), ['cover']);
  } finally {
    global.fetch = originalFetch;
  }
});
