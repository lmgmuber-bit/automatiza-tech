const test = require('node:test');
const assert = require('node:assert/strict');
const { renderEstadoHtml } = require('../src/template-estado');
const { validateEstadoPayload } = require('../src/schema');
const { estadoCorto, estadoLargo } = require('./fixtures/estado-ejemplo');

const laminas = (html) => (html.match(/<section class="slide/g) || []).length;

test('el estado de ejemplo valida y dibuja sus láminas en orden', () => {
  assert.deepEqual(validateEstadoPayload(estadoCorto()), { valid: true, errors: [] });
  const html = renderEstadoHtml(estadoCorto());
  // portada, dónde vamos, cronograma, 1 cierre, cambios, qué incluye, lo que sigue, cierre = 8
  assert.equal(laminas(html), 8);
  const orden = ['Estado del proyecto — Sitio de la panadería', 'Así va tu proyecto', 'Planificado y real', 'Sitio web',
    'Cambios en el plan', 'Qué incluye', 'Lo que sigue', '¿Revisamos juntos el avance?'];
  let desde = 0;
  for (const t of orden) {
    const i = html.indexOf(t, desde);
    assert.ok(i > desde - 1, `falta o está fuera de orden: ${t}`);
    desde = i;
  }
  assert.ok(html.includes('Actualizado el 14 de octubre de 2026'));
});

test('Dónde vamos: estado de cada bloque, «Estás aquí» en el que está en curso y el plan inicial en la ventana completa', () => {
  const html = renderEstadoHtml(estadoCorto());
  assert.ok(html.includes('<b>2 de 4</b>'));
  assert.match(html, /class="estado-bloque is-en_curso is-aqui"[^>]*>.*Estás aquí.*Puesta en marcha/s);
  assert.ok(html.includes('Planificado 5 – 9 oct · listo el 8 oct')); // rangoCorto junta el mes cuando es el mismo
  assert.ok(html.includes(`<a class="estado-plan" href="https://automatizatech.cl/ver-plan.php?id=AbCdEf123456" target="_top" rel="noopener">`));
  assert.ok(html.includes('Ver el plan de actividades inicial'));
});

test('cierre: antes, durante y después, fechas contra lo planificado, evidencias y la entrega', () => {
  const html = renderEstadoHtml(estadoCorto());
  assert.ok(html.includes('Lo teníamos planificado para el 21 de octubre de 2026 y quedó listo el 10 de octubre de 2026 (11 días antes de lo planificado).'));
  assert.ok(html.includes('<h3>Antes</h3>') && html.includes('<h3>Durante</h3>') && html.includes('<h3>Después</h3>'));
  assert.ok(html.includes('plan 6 oct · real 3 oct'));
  assert.match(html, /class="ev ev-cifra">.*<b>98<\/b><span>puntos<\/span>.*Fuente: medición local · 13 de octubre de 2026/s);
  assert.ok(html.includes('<video controls preload="none" src="https://automatizatech.cl/wp-content/uploads/at-estado/7/bbbbbbbbbbbbbbbbbbbb.mp4"'));
  assert.ok(html.includes('class="ev-video-pdf"'), 'en el PDF el video se cambia por un enlace (R1)');
  assert.ok(html.includes('href="https://automatizatech.cl/ver-entregable.php?id=XyZ123456789" target="_top"'));
  assert.ok(!html.includes('easypanel'), 'ningún enlace al servidor interno');
});

test('lo que no es https no se vuelve enlace ni imagen, y el texto se escapa', () => {
  const e = estadoCorto({ plan_url: 'javascript:alert(1)' });
  e.cierres[0].titulo = '<script>alert(1)</script>';
  e.cierres[0].despues.evidencias = [
    { tipo: 'captura', titulo: 'http', url: 'http://example.com/a.png' },
    { tipo: 'enlace', titulo: 'comillas', url: 'https://example.com/"onmouseover="x' },
  ];
  const html = renderEstadoHtml(e);
  assert.ok(!html.includes('<script>alert(1)</script>') && html.includes('&lt;script&gt;alert(1)&lt;/script&gt;'));
  assert.ok(!html.includes('javascript:') && !html.includes('class="estado-plan"'));
  assert.ok(!html.includes('http://example.com/a.png') && !html.includes('onmouseover'));
});

test('vista previa con borradores: marca BORRADOR y avisa que el cliente no la ve', () => {
  const e = estadoCorto({ draft: true });
  e.cierres[0].borrador = true;
  const html = renderEstadoHtml(e);
  assert.ok(html.includes('· BORRADOR') && html.includes('Vista previa con borradores · el cliente no la ve'));
  assert.ok(!renderEstadoHtml(estadoCorto()).includes('BORRADOR'));
});

test('sin cambios de plan no hay lámina de cambios; sin necesitamos dice que vamos al día', () => {
  const html = renderEstadoHtml(estadoCorto({ cambios_plan: [], necesitamos: [] }));
  assert.equal(laminas(html), 7);
  assert.ok(!html.includes('Cambios en el plan') && html.includes('Por ahora nada: vamos al día.'));
});

test('más de 4 evidencias: siguen en láminas «Evidencias» de hasta 8', () => {
  const html = renderEstadoHtml(estadoLargo());
  const c1 = (html.match(/Evidencias \(\d\/\d\)/g) || []);
  assert.deepEqual(c1, ['Evidencias (1/2)', 'Evidencias (2/2)']); // 13 = 4 en la lámina del cierre + 8 + 1
});

test('una fecha real absurda (2099) no revienta el cronograma: como mucho 104 semanas desde el inicio del plan', () => {
  const e = estadoCorto();
  e.bloques[0].fin_real = '2099-12-31';
  const html = renderEstadoHtml(e);
  const cabeceras = html.match(/<b>S\d+<\/b>/g) || [];
  assert.equal(cabeceras.length, 104);
  assert.ok(html.includes('--semanas:104'));
  assert.ok(html.includes('<b>S1</b><small>')); // y empieza en la semana del inicio del plan, no en la fecha absurda
  assert.ok(html.includes('5/10'), 'la primera semana es la del lunes 5 de octubre de 2026');
  // un plan normal sigue igual (8 semanas el ejemplo, igual que antes del tope)
  assert.equal(((renderEstadoHtml(estadoCorto()).match(/<b>S\d+<\/b>/g)) || []).length, 8);
});

test('validateEstadoPayload explica lo que falta', () => {
  const r = validateEstadoPayload({ document_type: 'estado', unique_id: '../x', company_name: '', proyecto: 'P', actualizado: '2026-13-01', bloques: {}, cierres: [{}] });
  assert.equal(r.valid, false);
  for (const t of ['unique_id debe tener', 'company_name', 'actualizado debe ser una fecha', 'bloques debe ser un arreglo', 'el cierre 1 no tiene título']) {
    assert.ok(r.errors.some((e) => e.includes(t)), `falta el motivo: ${t} (${r.errors.join(' | ')})`);
  }
});

test('una evidencia de tipo enlace es una tarjeta que abre en otra pestaña', () => {
  const e = estadoCorto();
  e.cierres[0].despues.evidencias = [{ tipo: 'enlace', titulo: 'El sitio publicado', url: 'https://example.com/' }];
  const html = renderEstadoHtml(e);
  assert.ok(html.includes('<a class="ev ev-enlace" href="https://example.com/" target="_blank" rel="noopener">'));
  assert.ok(html.includes('El sitio publicado') && html.includes('example.com/'));
});
