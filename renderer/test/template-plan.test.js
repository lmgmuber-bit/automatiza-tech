const test = require('node:test');
const assert = require('node:assert/strict');
const tp = require('../src/template-plan');
const { planCorto, planLargo } = require('./fixtures/plan-ejemplo');

function contar(texto, aguja) {
  return texto.split(aguja).length - 1;
}

// --- Fechas y ayudas ---------------------------------------------------------

test('lunesDe devuelve el lunes de la semana, también en domingo y al cambiar de año', () => {
  assert.equal(tp.lunesDe('2026-10-05'), '2026-10-05');
  assert.equal(tp.lunesDe('2026-10-07'), '2026-10-05');
  assert.equal(tp.lunesDe('2026-10-11'), '2026-10-05');
  assert.equal(tp.lunesDe('2027-01-01'), '2026-12-28');
  assert.equal(tp.lunesDe('2026-02-30'), '');
  assert.equal(tp.lunesDe(''), '');
});

test('semanaIndice cuenta semanas de lunes a domingo desde el lunes de inicio', () => {
  assert.equal(tp.semanaIndice('2026-10-05', '2026-10-05'), 0);
  assert.equal(tp.semanaIndice('2026-10-11', '2026-10-05'), 0);
  assert.equal(tp.semanaIndice('2026-10-12', '2026-10-05'), 1);
  assert.equal(tp.semanaIndice('2027-01-08', '2026-10-05'), 13);
  assert.ok(Number.isNaN(tp.semanaIndice('no-es-fecha', '2026-10-05')));
});

test('las fechas se escriben en español y no dependen de la zona horaria', () => {
  assert.equal(tp.fechaLarga('2026-09-28'), '28 de septiembre de 2026');
  assert.equal(tp.fechaLarga('2027-01-01'), '1 de enero de 2027');
  assert.equal(tp.fechaCorta('2026-10-05'), '5 oct');
  assert.equal(tp.rangoCorto('2026-10-09', '2026-10-14'), '9 – 14 oct');
  assert.equal(tp.rangoCorto('2026-10-28', '2026-11-05'), '28 oct – 5 nov');
  assert.equal(tp.rangoCorto('2026-12-23', '2026-12-23'), '23 dic');
  assert.equal(tp.diasEntre('2026-10-05', '2026-10-12'), 7);
  assert.equal(tp.fechaLarga('2026-13-01'), '');
});

test('días hábiles en singular y plural', () => {
  assert.equal(tp.diasTexto(1), '1 día hábil');
  assert.equal(tp.diasTexto(3), '3 días hábiles');
  assert.equal(tp.diasTexto('x'), '0 días hábiles');
});

test('urlSegura solo deja pasar enlaces https sin comillas ni espacios', () => {
  assert.equal(tp.urlSegura(' https://wa.me/56927002984?text=Hola '), 'https://wa.me/56927002984?text=Hola');
  assert.equal(tp.urlSegura('javascript:alert(1)'), '');
  assert.equal(tp.urlSegura('http://automatizatech.cl'), '');
  assert.equal(tp.urlSegura('https://x.cl/"><script>'), '');
  assert.equal(tp.urlSegura(''), '');
  assert.equal(tp.urlSegura(null), '');
});

// --- Carta Gantt -------------------------------------------------------------

test('la revisión del cliente va en la fila del bloque que revisa: 16 barras son 10 filas', () => {
  const grupos = tp.filasGantt(planLargo().cronograma);
  assert.deepEqual(
    grupos.map((g) => [g.titulo, g.filas.length]),
    [
      ['Diseño y desarrollo', 6],
      ['Implementación', 2],
      ['Soporte y mejora continua', 2],
    ]
  );
  const diseno = grupos[0].filas[1];
  assert.equal(diseno.trabajo.etiqueta, 'Diseño');
  assert.equal(diseno.revision.desde, '2026-10-21');
  assert.equal(grupos[0].filas[0].revision, null, 'el arranque no tiene revisión');
});

test('una revisión sin bloque previo queda en su propia fila y las barras rotas se ignoran', () => {
  const grupos = tp.filasGantt({
    barras: [
      { fase: 'soporte', etiqueta: 'Tu revisión', tipo: 'revision', responsable: 'cliente', desde: '2026-10-05', hasta: '2026-10-09' },
      { fase: 'soporte', etiqueta: 'Rota', tipo: 'trabajo', responsable: 'at', desde: '2026-10-09', hasta: '2026-10-05' },
      { fase: 'soporte', etiqueta: 'Sin fecha', tipo: 'trabajo', responsable: 'at', desde: '', hasta: '2026-10-05' },
      null,
    ],
  });
  assert.equal(grupos.length, 1);
  assert.equal(grupos[0].filas.length, 1);
  assert.equal(grupos[0].filas[0].trabajo, null);
  assert.equal(grupos[0].filas[0].revision.desde, '2026-10-05');
});

test('la carta larga (14 semanas, 16 barras) usa filas altas y todo cabe en su alto', () => {
  const { fila, denso } = tp.medidasGantt(10, 3, true);
  assert.equal(fila, 48);
  assert.equal(denso, false);
  // Con cualquier cantidad de filas, filas + títulos de fase + fila de hitos caben en los 660 px.
  for (const n of [1, 10, 16, 25, 40, 80]) {
    const m = tp.medidasGantt(n, 3, true);
    assert.ok(n * m.fila + 3 * 34 + 72 <= 660, `${n} filas se salen`);
  }
  assert.equal(tp.medidasGantt(25, 3, true).denso, true);
});

test('renderGantt dibuja 14 semanas, 16 barras, los hitos y la leyenda', () => {
  const html = tp.renderGantt(planLargo().cronograma);
  assert.equal(contar(html, '<span class="gantt-semana">'), 14);
  assert.ok(html.includes('<b>S1</b><small>5 oct</small>'));
  assert.ok(html.includes('<b>S14</b><small>4 ene</small>'));
  assert.equal(contar(html, 'class="gantt-barra '), 16);
  assert.equal(contar(html, 'class="gantt-barra is-revision"'), 6);
  assert.equal(contar(html, '<div class="gantt-fila">'), 10);
  assert.equal(contar(html, '<div class="gantt-fase">'), 3);
  assert.ok(html.includes('style="--semanas:14;--fila:48px"'));
  assert.equal(contar(html, 'class="gantt-hito'), 3);
  assert.ok(html.includes('Entrega estimada · 23 dic'));
  // «Plataforma aprobada» (18 dic, fin del día 75 de 98) y «Entrega estimada» (23 dic, día 80) están pegadas:
  // van en carriles distintos, y la entrega se etiqueta a la izquierda porque a la derecha no cabe.
  assert.ok(html.includes('<span class="gantt-hito is-arriba" style="left:76.531%"><i></i><em>Plataforma aprobada · 18 dic</em>'));
  assert.ok(html.includes('<span class="gantt-hito is-entrega is-abajo is-izq" style="left:81.633%">'));
  for (const leyenda of ['AutomatizaTech', 'Tú', 'Ambos', 'Tu revisión (5 días hábiles)', 'Hito']) {
    assert.ok(html.includes(`</i>${leyenda}</span>`), `falta la leyenda ${leyenda}`);
  }
  assert.ok(html.includes('Fechas estimadas, desde que recibimos el anticipo y tus insumos.'));
});

test('tres hitos pegados no se pisan: el tercero se corre a la derecha de los otros dos', () => {
  const c = planCorto().cronograma;
  c.hitos = [
    { nombre: 'Diseño aprobado', fecha: '2026-10-21' },
    { nombre: 'Textos aprobados', fecha: '2026-10-22' },
    { nombre: 'Fotos aprobadas', fecha: '2026-10-23' },
  ];
  const html = tp.renderGantt(c);
  const hitos = html.match(/<span class="gantt-hito[^>]*>/g);
  assert.equal(hitos[0], '<span class="gantt-hito is-arriba" style="left:40.476%">');
  assert.equal(hitos[1], '<span class="gantt-hito is-abajo" style="left:42.857%">');
  assert.ok(/^<span class="gantt-hito is-arriba" style="left:45.238%;--corrido:\d+px">$/.test(hitos[2]), hitos[2]);
});

test('las barras se ubican por día de calendario dentro de la grilla semanal', () => {
  const html = tp.renderGantt(planCorto().cronograma);
  // 6 semanas = 42 días. Arranque: lunes 5 a jueves 8 de octubre = días 0 a 3.
  assert.ok(html.includes('class="gantt-barra is-ambos" style="left:0%;width:9.524%"'));
  // Primera revisión: jueves 15 a miércoles 21 = días 10 a 16.
  assert.ok(html.includes('class="gantt-barra is-revision" style="left:23.81%;width:16.667%"'));
});

test('la carta se achica (is-denso) cuando hay muchas filas y marca las semanas largas', () => {
  const barras = Array.from({ length: 30 }, (_, i) => ({
    fase: 'diseno_desarrollo',
    etiqueta: `Bloque ${i + 1}`,
    tipo: 'trabajo',
    responsable: 'at',
    desde: tp.lunesDe('2026-10-05'),
    hasta: '2026-10-09',
  }));
  const html = tp.renderGantt({ inicio: '2026-10-05', fin: '2027-04-30', barras, hitos: [] });
  assert.ok(html.includes('class="gantt is-denso is-largo"'));
  assert.equal(contar(html, '<span class="gantt-semana">'), 30);
  assert.ok(html.includes('<b>S30</b><small>26/4</small>'));
});

test('sin barras la carta muestra un aviso y no se rompe', () => {
  const html = tp.renderGantt({ inicio: '2026-10-05', fin: '2026-10-09', barras: [], hitos: [] });
  assert.ok(html.includes('Las fechas aparecen aquí cuando el plan tenga actividades.'));
  assert.doesNotThrow(() => tp.renderGantt(undefined));
});

test('los textos de la carta se escapan', () => {
  const c = planCorto().cronograma;
  c.barras[1].etiqueta = 'Diseño <script>alert(1)</script>';
  c.hitos[0].nombre = 'Hito <b>x</b>';
  const html = tp.renderGantt(c);
  assert.ok(!html.includes('<script>alert(1)'));
  assert.ok(html.includes('Diseño &lt;script&gt;alert(1)&lt;/script&gt;'));
  assert.ok(html.includes('Hito &lt;b&gt;x&lt;/b&gt;'));
});

test('la lámina de la carta dice inicio, entrega estimada y duración', () => {
  const html = tp.renderGanttSlide(planLargo(), 3, '');
  assert.ok(html.includes('03 · Carta Gantt'));
  assert.ok(html.includes('<b>5 de octubre de 2026</b>'));
  assert.ok(html.includes('<b>23 de diciembre de 2026</b>'));
  assert.ok(html.includes('<b>14 semanas</b>'));
});
