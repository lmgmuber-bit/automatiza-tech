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

// --- Método AT y fases -------------------------------------------------------

test('el Método AT marca Diagnóstico y Priorización hechos y «Estás aquí» en Propuesta por fases', () => {
  const html = tp.renderMetodoSlide(planCorto(), 2, '');
  assert.ok(html.includes('02 · El Método AT'));
  assert.equal(contar(html, 'class="metodo-paso '), 6);
  assert.equal(contar(html, 'class="metodo-paso is-hecha"'), 2);
  assert.equal(contar(html, 'Estás aquí'), 1);
  const actual = html.match(/<li class="metodo-paso is-actual">[\s\S]*?<\/li>/)[0];
  assert.ok(actual.includes('<h3>Propuesta por fases</h3>'));
  assert.ok(actual.includes('Cerrada con tu firma del 28 de septiembre de 2026'));
  assert.equal(contar(html, 'class="metodo-paso is-proxima"'), 3);
  assert.ok(html.includes('Lo que viene: Diseño y desarrollo → Implementación → Soporte y mejora continua.'));
});

test('sin fecha de firma ni datos del método, la lámina igual sale bien', () => {
  const html = tp.renderMetodoSlide({}, 2, '');
  assert.equal(contar(html, 'Estás aquí'), 1);
  assert.ok(html.includes('Cerrada con tu firma del contrato'));
});

test('paginarBloques deja una fase larga en dos láminas y una corta en una', () => {
  const [fase1] = planLargo().fases;
  const paginas = tp.paginarBloques(fase1.bloques, fase1.descripcion);
  assert.deepEqual(
    paginas.map((p) => p.map((b) => b.nombre)),
    [['Arranque', 'Diseño', 'Núcleo de la plataforma', 'Agenda y pagos'], ['Integraciones', 'Pruebas y revisión']]
  );
  assert.equal(tp.paginarBloques(planCorto().fases[0].bloques, planCorto().fases[0].descripcion).length, 1);
  assert.deepEqual(tp.paginarBloques(undefined), [[]]);
});

test('paginarBloques deja a lo más 5 entregas por lámina, para que «Qué aprobamos juntos» quepa', () => {
  // El peor caso de la Task 3: Arranque + 13 bloques con entrega de una actividad cada uno. Por alto de la
  // tabla caben 7 en la segunda lámina, pero la tarjeta de entregas se saldría por abajo.
  const bloques = [
    { nombre: 'Arranque', entrega: false, actividades: [{ nombre: 'Reunión de inicio' }, { nombre: 'Entrega de logo, textos y accesos' }] },
  ];
  for (let i = 1; i <= 13; i++) {
    bloques.push({ nombre: `Etapa ${i}`, entregable: `Módulo ${i}`, entrega: true, actividades: [{ nombre: `Trabajo ${i}` }] });
  }
  const paginas = tp.paginarBloques(bloques, 'Descripción corta de la fase.');
  assert.deepEqual(
    paginas.map((p) => p.filter((b) => b.entrega).length),
    [5, 5, 3]
  );
  assert.equal(paginas.flat().length, 14);
});

test('dos bloques con el mismo nombre en una fase muestran cada uno su propia revisión', () => {
  const plan = planCorto();
  // El bloque «Desarrollo» pasa a llamarse igual que el anterior («Diseño»), en la fase y en su barra.
  plan.fases[0].bloques[2].nombre = 'Diseño';
  plan.cronograma.barras[3].etiqueta = 'Diseño';
  const fase = plan.fases[0];
  const [pagina] = tp.paginarBloques(fase.bloques, fase.descripcion);
  for (const bloques of [fase.bloques, pagina]) {
    const html = tp.renderFaseSlide(
      { fase, numeroFase: 1, totalFases: 3, bloques, continuacion: false, cronograma: plan.cronograma },
      4,
      ''
    );
    assert.ok(html.includes('<li><b>Diseño de la página</b><span>Tu revisión: 15 – 21 oct.</span></li>'));
    assert.ok(html.includes('<li><b>Sitio en ambiente de prueba</b><span>Tu revisión: 2 – 6 nov.</span></li>'));
  }
});

test('la lámina de una fase lista actividades con responsable, días y fechas, y qué aprobamos juntos', () => {
  const plan = planCorto();
  const html = tp.renderFaseSlide(
    { fase: plan.fases[0], numeroFase: 1, totalFases: 3, bloques: plan.fases[0].bloques, continuacion: false, cronograma: plan.cronograma },
    4,
    ''
  );
  assert.ok(html.includes('04 · Fase 1 de 3'));
  assert.ok(html.includes('<h2>Diseño y desarrollo</h2>'));
  assert.ok(html.includes('Diseñamos y construimos tu sitio de una página.'));
  assert.ok(html.includes('<td class="plan-act">Reunión de inicio</td><td><span class="quien is-ambos">Ambos</span></td><td>1 día hábil</td><td>5 oct</td>'));
  assert.ok(html.includes('<span class="quien is-cliente">Tú</span></td><td>3 días hábiles</td><td>6 – 8 oct</td>'));
  assert.ok(html.includes('<span class="quien is-at">AutomatizaTech</span>'));
  assert.ok(html.includes('<b>Diseño</b><span>Entrega: Diseño de la página</span>'));
  assert.ok(html.includes('Qué aprobamos juntos'));
  assert.ok(html.includes('<li><b>Diseño de la página</b><span>Tu revisión: 15 – 21 oct.</span></li>'));
  assert.ok(html.includes('<li><b>Sitio en ambiente de prueba</b><span>Tu revisión: 2 – 6 nov.</span></li>'));
  assert.ok(html.includes('cláusula 6.1 del contrato'));
});

test('una fase sin entregas lo dice, y la continuación no repite la descripción', () => {
  const plan = planLargo();
  const fase = plan.fases[2];
  const html = tp.renderFaseSlide(
    { fase, numeroFase: 3, totalFases: 3, bloques: fase.bloques, continuacion: true, cronograma: plan.cronograma },
    10,
    ''
  );
  assert.ok(html.includes('Soporte y mejora continua <span class="plan-cont">(continuación)</span>'));
  assert.ok(!html.includes('No desaparecemos: acompañamos'));
  assert.ok(html.includes('En esta parte no hay entregas que aprobar'));
});

test('las actividades en paralelo se marcan y la tabla se compacta con muchas filas', () => {
  const plan = planLargo();
  const [pagina1] = tp.paginarBloques(plan.fases[0].bloques, plan.fases[0].descripcion);
  const html = tp.renderFaseSlide(
    { fase: plan.fases[0], numeroFase: 1, totalFases: 3, bloques: pagina1, continuacion: false, cronograma: plan.cronograma },
    4,
    ''
  );
  assert.ok(html.includes('Módulo de pagos<em class="plan-paralelo">en paralelo</em>'));
  assert.ok(html.includes('<table class="plan-tabla is-muy-denso">'));
});

test('los textos de las fases se escapan', () => {
  const plan = planCorto();
  plan.fases[0].descripcion = 'Descripción <img src=x onerror=alert(1)>';
  plan.fases[0].bloques[0].actividades[0].nombre = 'Reunión <script>x</script>';
  const html = tp.renderFaseSlide(
    { fase: plan.fases[0], numeroFase: 1, totalFases: 3, bloques: plan.fases[0].bloques, continuacion: false, cronograma: plan.cronograma },
    4,
    ''
  );
  assert.ok(!html.includes('<img src=x'));
  assert.ok(!html.includes('<script>x'));
  assert.ok(html.includes('Reunión &lt;script&gt;x&lt;/script&gt;'));
});
