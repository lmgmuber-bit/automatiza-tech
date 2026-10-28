// window.atLead del aviso de cookies (mu-plugins/at-consentimiento.php) con la huella de at-lead-id.js.
// Corre el <script> real del mu-plugin en un window simulado: fbq que anota llamadas y localStorage en memoria.
// Uso: node tests/meta-capi/atlead-test.mjs
import { readFileSync } from 'node:fs';
import { webcrypto } from 'node:crypto';
import vm from 'node:vm';

const VECTOR = '91cafbb214ddfd5e5c52a1fb6ca25c02974bf648'; // sha256("ana@correo.cl|2026-11-03|10:00")[:40]
const raiz = new URL('../../', import.meta.url);
const idJs = readFileSync(new URL('wp-content/themes/automatiza-tech/assets/js/at-lead-id.js', raiz), 'utf8');
const php = readFileSync(new URL('wp-content/mu-plugins/at-consentimiento.php', raiz), 'utf8');
const bloques = [...php.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(m => m[1]).filter(b => b.includes('window.atLead'));
let fallas = 0;
const ok = (c, m) => { if (c) console.log('ok   ' + m); else { fallas++; console.log('FALLA ' + m); } };
ok(bloques.length === 1, 'el mu-plugin trae un único <script> con window.atLead');

function mundo({ permiso = 'si', crypto = webcrypto, almacen = 'normal' } = {}) {
  const llamadas = [];
  const datos = new Map();
  const localStorage = almacen === 'roto'
    ? { getItem() { throw new Error('bloqueado'); }, setItem() { throw new Error('bloqueado'); } }
    : { getItem: k => (datos.has(k) ? datos.get(k) : null), setItem: (k, v) => datos.set(k, String(v)) };
  const window = { fbq: (...a) => llamadas.push(a), localStorage };
  if (crypto) window.crypto = crypto;
  const document = { cookie: permiso ? 'at_consentimiento=' + permiso : '', getElementById: () => null, addEventListener() {} };
  const ctx = { window, document, localStorage, location: { protocol: 'https:', reload() {} }, TextEncoder, Uint8Array, Promise, Date, String, Number };
  vm.createContext(ctx);
  vm.runInContext(idJs, ctx);
  vm.runInContext(bloques[0], ctx);
  return { w: window, llamadas, datos };
}
const espera = () => new Promise(r => setTimeout(r, 20));
const DATOS = { email: ' Ana@Correo.CL ', fecha: '2026-11-03', hora: '10:00:00' };

let m = mundo();
ok(m.w.atLead('Agenda de demo', DATOS) === true, 'con permiso y fbq, atLead responde true');
await espera();
ok(m.llamadas.length === 1, 'una llamada a fbq');
const [cmd, evento, params, opciones] = m.llamadas[0] || [];
ok(cmd === 'track' && evento === 'Lead' && params && params.content_name === 'Agenda de demo', "fbq('track','Lead',{content_name})");
ok(opciones && opciones.eventID === VECTOR, 'eventID igual al del servidor');
ok([...m.datos.keys()].every(k => !k.includes('ana') && !k.includes('@')), 'la marca de 30 días no guarda el correo en claro');

m.w.atLead('Agenda de demo', { ...DATOS, hora: '15:00' });
await espera();
ok(m.llamadas.length === 1, 'segunda agenda del mismo correo en 30 días: no vuelve a avisar');
m.w.atLead('Agenda diagnóstico (portada)', { email: 'otra@correo.cl', fecha: '2026-11-03', hora: '11:00' });
await espera();
ok(m.llamadas.length === 2, 'otro correo sí avisa');

m = mundo({ permiso: 'no' });
ok(m.w.atLead('Agenda de demo', DATOS) === false, 'sin permiso: false');
await espera();
ok(m.llamadas.length === 0, 'sin permiso: no llama a fbq');
m = mundo({ permiso: null });
m.w.atLead('Agenda de demo', DATOS); await espera();
ok(m.llamadas.length === 0, 'sin decisión de cookies: no llama');

m = mundo({ crypto: null });
m.w.atLead('Agenda de demo', DATOS); await espera();
ok(m.llamadas.length === 1 && m.llamadas[0].length === 3, 'sin crypto.subtle: avisa igual, sin eventID');

m = mundo({ almacen: 'roto' });
let lanzo = false;
try { m.w.atLead('Agenda de demo', DATOS); } catch (e) { lanzo = true; }
await espera();
ok(!lanzo && m.llamadas.length === 1 && (m.llamadas[0][3] || {}).eventID === VECTOR, 'localStorage bloqueado: no rompe y avisa con eventID');

m = mundo();
m.w.atLead('Contacto'); await espera();
ok(m.llamadas.length === 1 && m.llamadas[0].length === 3, 'sin datos: se comporta como antes (sin eventID)');

m = mundo();
m.w.atLead('Agenda de demo', { email: 'a@b.cl', fecha: 'mañana', hora: '10:00' }); await espera();
ok(m.llamadas.length === 1 && m.llamadas[0].length === 3, 'fecha inválida: avisa sin eventID');

console.log(fallas ? `\n${fallas} FALLAS` : '\nTODO OK');
process.exit(fallas ? 1 : 0);
