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

// Plan de trabajo (document_type 'plan'). Solo revisa la forma que el renderer necesita para dibujar;
// el contenido (fases válidas, días de 1 a 60, responsables) ya lo validó WordPress con at_pt_validar_plan.
const PLAN_REQUIRED_STRING_FIELDS = ['unique_id', 'company_name', 'proyecto'];

function esFechaYmd(valor) {
  if (typeof valor !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(valor)) return false;
  const d = new Date(`${valor}T00:00:00Z`);
  return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === valor;
}

function validatePlanPayload(body) {
  if (!body || typeof body !== 'object' || Array.isArray(body)) {
    return { valid: false, errors: ['el cuerpo debe ser un objeto JSON'] };
  }
  const errors = [];

  for (const field of PLAN_REQUIRED_STRING_FIELDS) {
    if (typeof body[field] !== 'string' || body[field].trim() === '') {
      errors.push(`falta o está vacío el campo obligatorio: ${field}`);
    }
  }

  // La misma regla que server.js aplica después (unique_id pasa a ser una carpeta de publicDir), pero aquí
  // con motivo legible en details: todo 400 del plan trae details y n8n lo copia a la nota sin reintentar.
  if (typeof body.unique_id === 'string' && body.unique_id.trim() !== '' && !/^[A-Za-z0-9_-]{6,64}$/.test(body.unique_id)) {
    errors.push('unique_id debe tener de 6 a 64 caracteres, solo letras, números, guion o guion bajo');
  }

  if (!Array.isArray(body.fases) || body.fases.length === 0) {
    errors.push('falta o está vacío el arreglo obligatorio: fases');
  }

  const c = body.cronograma;
  if (!c || typeof c !== 'object' || Array.isArray(c)) {
    errors.push('falta el objeto obligatorio: cronograma');
  } else {
    if (!esFechaYmd(c.inicio)) errors.push('cronograma.inicio debe ser una fecha AAAA-MM-DD');
    if (!esFechaYmd(c.fin)) errors.push('cronograma.fin debe ser una fecha AAAA-MM-DD');
    if (esFechaYmd(c.inicio) && esFechaYmd(c.fin) && c.fin < c.inicio) {
      errors.push('cronograma.fin no puede ser anterior a cronograma.inicio');
    }
    if (!Array.isArray(c.barras)) errors.push('cronograma.barras debe ser un arreglo');
  }

  if (body.image_briefs !== undefined && !Array.isArray(body.image_briefs)) {
    errors.push('image_briefs debe ser un arreglo si viene');
  }

  return { valid: errors.length === 0, errors };
}

// «Estado del proyecto» (document_type 'estado'). WordPress ya validó el avance (at_av_validar_import); aquí solo la
// forma que el renderer necesita para dibujar, con motivos legibles en details (n8n los copia a la nota).
const ESTADO_REQUIRED_STRING_FIELDS = ['unique_id', 'company_name', 'proyecto'];

function validateEstadoPayload(body) {
  if (!body || typeof body !== 'object' || Array.isArray(body)) {
    return { valid: false, errors: ['el cuerpo debe ser un objeto JSON'] };
  }
  const errors = [];
  for (const field of ESTADO_REQUIRED_STRING_FIELDS) {
    if (typeof body[field] !== 'string' || body[field].trim() === '') {
      errors.push(`falta o está vacío el campo obligatorio: ${field}`);
    }
  }
  if (typeof body.unique_id === 'string' && body.unique_id.trim() !== '' && !/^[A-Za-z0-9_-]{6,64}$/.test(body.unique_id)) {
    errors.push('unique_id debe tener de 6 a 64 caracteres, solo letras, números, guion o guion bajo');
  }
  if (!esFechaYmd(body.actualizado)) errors.push('actualizado debe ser una fecha AAAA-MM-DD');
  if (!Array.isArray(body.bloques)) errors.push('bloques debe ser un arreglo');
  if (!Array.isArray(body.cierres)) {
    errors.push('cierres debe ser un arreglo');
  } else {
    body.cierres.forEach((c, i) => {
      if (!c || typeof c !== 'object' || typeof c.titulo !== 'string' || c.titulo.trim() === '') {
        errors.push(`el cierre ${i + 1} no tiene título`);
      }
    });
  }
  const c = body.cronograma;
  if (c !== undefined && (!c || typeof c !== 'object' || Array.isArray(c))) {
    errors.push('cronograma debe ser un objeto si viene');
  }
  return { valid: errors.length === 0, errors };
}

function validateRetomarPayload(body) {
  const errors = [];
  if (!body || typeof body !== 'object' || Array.isArray(body)) {
    return { ok: false, valid: false, errors: ['el cuerpo debe ser un objeto JSON'] };
  }
  const limites = { company_name: 120, client_name: 120, proyecto: 180, quedamos: 600, proponemos: 600, partir: 600 };
  for (const [field, max] of Object.entries(limites)) {
    if (field !== 'partir' && (typeof body[field] !== 'string' || !body[field].trim())) errors.push(`falta o está vacío el campo obligatorio: ${field}`);
    if (body[field] !== undefined && typeof body[field] !== 'string') errors.push(`${field} debe ser texto`);
    if (typeof body[field] === 'string' && (body[field].length > max || body[field].split('\n').length > 6)) errors.push(`${field} admite hasta ${max} caracteres y 6 líneas`);
  }
  if (body.document_type !== 'retomar') errors.push('document_type debe ser retomar');
  if (typeof body.unique_id !== 'string' || !/^[A-Za-z0-9_-]{6,64}$/.test(body.unique_id)) errors.push('unique_id debe tener de 6 a 64 caracteres, solo letras, números, guion o guion bajo');
  if (!Array.isArray(body.demo_urls) || body.demo_urls.length > 6 || body.demo_urls.some((u) => typeof u !== 'string')) errors.push('demo_urls debe ser un arreglo de hasta 6 enlaces');
  for (const field of ['demo_imagen', 'propuesta_url']) if (body[field] !== undefined && typeof body[field] !== 'string') errors.push(`${field} debe ser texto`);
  for (const field of ['agenda', 'fotos']) if (body[field] !== undefined && (!body[field] || typeof body[field] !== 'object' || Array.isArray(body[field]))) errors.push(`${field} debe ser un objeto`);
  return { ok: errors.length === 0, valid: errors.length === 0, errors };
}

module.exports = { validatePayload, validatePlanPayload, validateEstadoPayload, validateRetomarPayload, REQUIRED_STRING_FIELDS, REQUIRED_ARRAY_FIELDS, PLAN_REQUIRED_STRING_FIELDS, ESTADO_REQUIRED_STRING_FIELDS };
