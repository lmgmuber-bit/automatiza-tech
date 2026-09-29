"""JavaScript que comparten los flujos n8n del plan de trabajo (1 Borrador, 2 Cambios y 3 Render).

Va embebido en los nodos Code. Necesita, antes en el mismo nodo, JS_LEER_JSON (json_guard.py: leerJsonModelo y
esObjetoPlano) y JS_LIMPIAR_FOTOS (fotos_guard.py: limpiarFotos), que viven en N8N/propuestas-v3.

Reparto de responsabilidades: WordPress (at_pt_validar_plan) es quien decide si un plan es válido (claves de fase,
responsables, días de 1 a 60, largo de los textos) y rechaza con HTTP 422. Aquí solo se revisa la FORMA mínima
para no mandarle basura: que la respuesta sea un objeto JSON del plan, con fases y al menos una actividad; en
«cambios» se une con el plan guardado (lo que el modelo omite se conserva); el bloque «Arranque» del modelo se descarta
(en «cambios» vuelve el guardado; en «borrador» lo pone WordPress); se fija el origen de cada actividad, se
protegen los días que Luis editó a mano y se filtran las descripciones de fotos (solo láminas nuevas del plan, una
por lámina, con las reglas de fotos_guard). Nunca lanza: devuelve {ok, reason, plan, protegidas} para que el flujo
pase el plan a «error» con un motivo legible.
"""

JS_PLAN = r"""
const CLAVES_PLAN = ['version', 'proyecto', 'fecha_firma', 'fecha_inicio', 'fases', 'hitos', 'necesitamos_de_ti',
  'reuniones', 'soporte', 'image_briefs', 'cronograma'];
// Láminas con foto NUEVA (decisión 7). Portada y cierre solo si el contrato no tiene propuesta: si la tiene,
// se reutilizan sus fotos (portada ← cover, cierre ← next_steps) y no se pagan.
const SLIDES_NUEVAS = ['metodo', 'gantt', 'fase_1', 'fase_2', 'fase_3', 'necesitamos', 'reuniones', 'portal'];
const SLIDES_DE_PROPUESTA = { cover: 'cover', cierre: 'next_steps' };
const normPlan = (s) => String(s == null ? '' : s).trim().toLowerCase().replace(/\s+/g, ' ');

function slidesConFoto(hayPropuesta, nFases) {
  const n = Math.max(0, Math.min(3, Number(nFases) || 0));
  const l = SLIDES_NUEVAS.filter((s) => !/^fase_\d$/.test(s) || Number(s.slice(5)) <= n);
  return hayPropuesta ? l : ['cover', ...l, 'cierre'];
}

function recorrerActividades(plan, fn) {
  const fases = plan && Array.isArray(plan.fases) ? plan.fases : [];
  for (const f of fases) {
    if (!esObjetoPlano(f) || !Array.isArray(f.bloques)) continue;
    for (const b of f.bloques) {
      if (!esObjetoPlano(b) || !Array.isArray(b.actividades)) continue;
      for (const a of b.actividades) if (esObjetoPlano(a)) fn(a, b, f);
    }
  }
}

// El bloque «Arranque» (reunión de inicio y entrega de logo, textos y accesos) lo pone WordPress (at_pt_arranque, con
// los días de la tabla) y Luis lo puede editar en el panel: el modelo nunca lo manda ni lo cambia (decisión D12).
const esArranque = (b) => esObjetoPlano(b) && normPlan(b.nombre) === 'arranque';

// Saca los bloques «Arranque» de todas las fases. Cambia el plan que recibe y lo devuelve.
function quitarArranque(plan) {
  for (const f of (plan && Array.isArray(plan.fases) ? plan.fases : [])) {
    if (esObjetoPlano(f) && Array.isArray(f.bloques)) f.bloques = f.bloques.filter((b) => !esArranque(b));
  }
  return plan;
}

// Cambios: descarta el «Arranque» que haya mandado el modelo y pone, al comienzo de la misma fase donde estaba, el que
// está guardado (con lo que Luis haya editado en él). Si la fase no vino en la respuesta, unirPor ya dejó la guardada.
// Cambia el plan que recibe y lo devuelve.
function restituirArranque(plan, anterior) {
  const guardados = new Map();
  for (const f of (anterior && Array.isArray(anterior.fases) ? anterior.fases : [])) {
    if (!esObjetoPlano(f) || !Array.isArray(f.bloques)) continue;
    const a = f.bloques.filter(esArranque);
    if (a.length && !guardados.has(f.clave)) guardados.set(f.clave, a);
  }
  quitarArranque(plan);
  for (const f of (plan && Array.isArray(plan.fases) ? plan.fases : [])) {
    if (!esObjetoPlano(f) || !Array.isArray(f.bloques) || !guardados.has(f.clave)) continue;
    f.bloques = JSON.parse(JSON.stringify(guardados.get(f.clave))).concat(f.bloques);
    guardados.delete(f.clave);
  }
  return plan;
}

// Solo láminas del plan, una foto por lámina y el filtro de fotos_guard (idempotente: volver a filtrar una
// descripción ya filtrada no la cambia, así el renderer reutiliza la foto por su hash y no la vuelve a cobrar).
function fotosDelPlan(briefs, nFases, hayPropuesta) {
  const validas = new Set(slidesConFoto(hayPropuesta, nFases));
  const vistas = new Set();
  const elegidas = (Array.isArray(briefs) ? briefs : []).filter((b) => esObjetoPlano(b) && typeof b.slide === 'string'
    && typeof b.prompt === 'string' && b.prompt.trim() !== '' && validas.has(b.slide) && !vistas.has(b.slide)
    && vistas.add(b.slide));
  return limpiarFotos(elegidas.map((b) => ({ slide: b.slide, prompt: b.prompt }))).limpias;
}

// Cambios: una actividad se reconoce por su nombre (sin mayúsculas ni espacios de más) y por cuántas veces apareció
// antes ese nombre en el plan: la misma clave que at_pt_recorrer_actividades de WordPress (Task 2). Moverla de bloque
// no la hace nueva; renombrarla, sí (por eso el prompt le prohíbe al modelo renombrar las de Luis). Si Luis le puso
// los días a mano (origen «luis»), vuelven a los guardados aunque el modelo los haya cambiado. Una actividad que ya
// existía conserva su origen si el modelo no le cambió los días; si se los cambió, o si es nueva, queda «ia»
// (revisar), como la marca WordPress (at_pt_marcar_ediciones(…, 'ia')): el modelo nunca declara «luis» ni «tabla».
// Devuelve cuántas actividades de Luis traían días distintos.
function protegerLuis(plan, anterior) {
  const contador = () => {
    const vistas = new Map();
    return (a) => {
      const n = normPlan(a.nombre);
      vistas.set(n, (vistas.get(n) || 0) + 1);
      return n + '#' + vistas.get(n);
    };
  };
  const previas = new Map();
  const claveAnterior = contador();
  recorrerActividades(anterior, (a) => { previas.set(claveAnterior(a), a); });
  const claveNueva = contador();
  let protegidas = 0;
  recorrerActividades(plan, (a) => {
    const p = previas.get(claveNueva(a));
    if (!p) { a.origen = 'ia'; return; }
    const mismosDias = Number(a.dias_habiles) === Number(p.dias_habiles);
    if (p.origen === 'luis') {
      if (!mismosDias) protegidas++;
      a.dias_habiles = p.dias_habiles;
      a.origen = 'luis';
    } else {
      a.origen = p.origen === 'tabla' && mismosDias ? 'tabla' : 'ia';
    }
  });
  return protegidas;
}

// Une dos listas de objetos por un campo (fases por «clave», fotos por «slide»): cada elemento guardado se reemplaza,
// en su lugar, por el que trae el modelo con el mismo campo; los que el modelo agrega van al final. Si alguna de las
// dos no es una lista o el modelo la manda vacía, queda la del modelo tal cual (la validación decide).
function unirPor(antes, nuevos, campo) {
  if (nuevos === undefined) return antes;
  if (!Array.isArray(antes) || !Array.isArray(nuevos) || !nuevos.length) return nuevos;
  const dados = new Map(nuevos.filter(esObjetoPlano).map((x) => [x[campo], x]));
  const previos = antes.filter(esObjetoPlano);
  return previos.map((x) => (dados.has(x[campo]) ? dados.get(x[campo]) : x))
    .concat(nuevos.filter((x) => !esObjetoPlano(x) || !previos.some((p) => p[campo] === x[campo])));
}

// ia = salida del nodo OpenAI ({message: {content}} o {error} con onError=continue).
// op = {modo: 'borrador'|'cambios', hayPropuesta: bool, anterior: plan guardado (solo en cambios)}.
function leerPlanModelo(ia, op) {
  const mal = (reason) => ({ ok: false, reason, plan: null, protegidas: 0 });
  const contenido = ia && ia.message ? ia.message.content : null;
  if (contenido == null || String(contenido).trim() === '') {
    const err = ia && ia.error ? (ia.error.message || String(ia.error)) : '';
    return mal('El modelo no respondió' + (err ? ': ' + err : ''));
  }
  let r;
  try {
    r = leerJsonModelo(contenido);
  } catch (e) {
    return mal('La respuesta del modelo no es un JSON válido (' + (e && e.message ? e.message : String(e)) + ')');
  }
  if (!esObjetoPlano(r)) return mal('La respuesta del modelo no es un objeto JSON');
  // El modelo a veces imita la forma de la entrada y envuelve el plan: {"plan": …} o {"plan_actual": …}.
  for (const k of ['plan', 'plan_actual']) {
    if (esObjetoPlano(r[k]) && Object.keys(r).every((x) => x === k || x === 'comentarios')) { r = r[k]; break; }
  }
  // Claves que no son del plan. En cambios, una nota o un «antes/después» no pasan como cambio aplicado: toda clave
  // debe ser del plan (o una que ya traía el plan guardado). En borrador se descartan: WordPress arma el plan solo con
  // las claves que conoce, y una nota suelta o la plantilla del prompt igual fallan por no traer fases o actividades.
  const cambios = op.modo === 'cambios';
  const previo = cambios && esObjetoPlano(op.anterior) ? op.anterior : {};
  const ajenas = Object.keys(r).filter((k) => !CLAVES_PLAN.includes(k) && !Object.prototype.hasOwnProperty.call(previo, k));
  if (ajenas.length && cambios) return mal('La respuesta del modelo trae claves que no son del plan: ' + ajenas.slice(0, 5).join(', '));
  for (const k of ajenas) delete r[k];
  let plan = r;
  if (cambios) {
    if (!Object.keys(r).length) return mal('La respuesta del modelo no trae ninguna parte del plan');
    // Lo que el modelo omita se conserva del plan guardado: nunca se manda a WordPress un plan a medias. Las fases se
    // unen por su clave y las fotos por su lámina: si el modelo devuelve solo la fase o la foto que cambió, las otras
    // quedan como estaban (con los días de Luis, y cada foto con su mismo texto, que el renderer reconoce por su hash).
    plan = Object.assign({}, previo, r);
    plan.fases = unirPor(previo.fases, r.fases, 'clave');
    plan.image_briefs = unirPor(previo.image_briefs, r.image_briefs, 'slide');
  }
  plan = JSON.parse(JSON.stringify(plan));
  if (!Array.isArray(plan.fases) || !plan.fases.length) return mal('El plan del modelo no trae fases');
  if (plan.fases.some((f) => !esObjetoPlano(f) || !Array.isArray(f.bloques))) {
    return mal('El plan del modelo trae una fase sin su lista de bloques');
  }
  // El «Arranque» del modelo se descarta: en borrador lo pone WordPress; en cambios vuelve el guardado.
  if (cambios) restituirArranque(plan, previo);
  else quitarArranque(plan);
  let n = 0;
  recorrerActividades(plan, () => { n++; });
  if (!n) return mal('El modelo no propuso ninguna actividad');
  let protegidas = 0;
  if (cambios) protegidas = protegerLuis(plan, previo);
  else recorrerActividades(plan, (a) => { a.origen = 'ia'; });
  plan.image_briefs = fotosDelPlan(plan.image_briefs, plan.fases.length, op.hayPropuesta === true);
  return { ok: true, reason: '', plan, protegidas };
}
"""


def code_motivo(modo):
    """Código del nodo «Motivo del error» de «1 Borrador» (modo 'borrador') y «2 Cambios» (modo 'cambios').

    Arma un motivo legible venga de donde venga el fallo (contexto, modelo o guardado en WordPress). Nunca lanza.
    """
    if modo not in ('borrador', 'cambios'):
        raise ValueError(modo)
    return r"""const MODO = '__MODO__';
const hook = $('Webhook').first().json.body || {};
const id = parseInt(hook.id, 10) || 0;
const lc = $('Leer contexto').first().json || {};
const ctx = lc.statusCode === 200 && esObjetoPlano(lc.body) ? lc.body : {};
const cuerpo = (x) => (esObjetoPlano(x.body) ? x.body : {});
const estadoHttp = (x) => (x.statusCode ? 'HTTP ' + x.statusCode
  : 'sin respuesta' + (x.error && x.error.message ? ': ' + x.error.message : ''));
let reason = '';
if ($('Guardar borrador').isExecuted) {
  const g = $('Guardar borrador').first().json || {};
  const b = cuerpo(g);
  const errores = Array.isArray(b.errores) ? b.errores.map(String).filter(Boolean) : [];
  if (g.statusCode === 422 && errores.length) reason = 'WordPress rechazó el plan: ' + errores.join(' · ');
  else if (g.statusCode === 200) reason = 'WordPress no confirmó el guardado del plan' + (errores.length ? ': ' + errores.join(' · ') : '');
  else reason = 'WordPress no guardó el plan (' + estadoHttp(g) + ')'
    + (errores.length ? ': ' + errores.join(' · ') : (b.message ? ' — ' + b.message : ''));
} else if ($('Leer plan').isExecuted) {
  reason = String($('Leer plan').first().json.reason || '');
} else if (MODO === 'cambios' && lc.statusCode === 200 && ctx.ok === true && !esObjetoPlano(ctx.plan_actual)) {
  reason = 'El plan no tiene un borrador guardado al que aplicarle cambios';
} else {
  const b = cuerpo(lc);
  reason = 'No se pudo leer el contexto del plan en WordPress (' + estadoHttp(lc) + ')' + (b.message ? ' — ' + b.message : '');
}
if (!reason) reason = MODO === 'cambios' ? 'Error desconocido al aplicar los cambios' : 'Error desconocido al generar el borrador';
// WordPress guarda como mucho 1000 caracteres de nota (/error) y el flujo le suma « (ejecución N)».
if (reason.length > 900) reason = reason.slice(0, 900) + '…';
return [{ json: { id, crm: parseInt(ctx.crm_cliente_id, 10) || 0, proyecto: String(ctx.proyecto || ctx.empresa || ('plan ' + id)),
  reason, exec: String($execution.id) } }];""".replace('__MODO__', modo)
