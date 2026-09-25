const REQUIRED_STRING_FIELDS = [
  'unique_id',
  'client_name',
  'company_name',
  'challenge_title',
  'challenge_text',
  'solution_title',
  'solution_text',
];

const REQUIRED_ARRAY_FIELDS = ['benefits', 'how_it_works', 'pricing_rows', 'next_steps'];

function validatePayload(body) {
  const errors = [];

  if (!body || typeof body !== 'object' || Array.isArray(body)) {
    return { valid: false, errors: ['request body must be a JSON object'] };
  }

  for (const field of REQUIRED_STRING_FIELDS) {
    if (typeof body[field] !== 'string' || body[field].trim() === '') {
      errors.push(`missing or empty required field: ${field}`);
    }
  }

  for (const field of REQUIRED_ARRAY_FIELDS) {
    if (!Array.isArray(body[field]) || body[field].length === 0) {
      errors.push(`missing or empty required array field: ${field}`);
    }
  }

  if (body.image_briefs !== undefined && !Array.isArray(body.image_briefs)) {
    errors.push('image_briefs must be an array when present');
  }

  return { valid: errors.length === 0, errors };
}

module.exports = { validatePayload, REQUIRED_STRING_FIELDS, REQUIRED_ARRAY_FIELDS };
