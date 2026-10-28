// La huella del navegador (assets/js/at-lead-id.js) debe ser igual a la del servidor (inc/meta-capi/puras.php).
// Uso: node tests/meta-capi/paridad-test.mjs
import { readFileSync } from 'node:fs';
import { webcrypto } from 'node:crypto';
import vm from 'node:vm';

const VECTOR = '91cafbb214ddfd5e5c52a1fb6ca25c02974bf648'; // mismo vector de puras-test.php
const src = readFileSync(new URL('../../wp-content/themes/automatiza-tech/assets/js/at-lead-id.js', import.meta.url), 'utf8');
let fallas = 0;
const ok = (c, m) => { if (c) console.log('ok   ' + m); else { fallas++; console.log('FALLA ' + m); } };

function cargar(crypto) {
  const ctx = { window: {}, TextEncoder, Uint8Array, Promise };
  if (crypto) ctx.window.crypto = crypto;
  vm.createContext(ctx);
  vm.runInContext(src, ctx);
  return ctx.window.AtLeadId;
}

const L = cargar(webcrypto);
ok(L && typeof L.eventId === 'function', 'window.AtLeadId.eventId existe');
ok(L.normCorreo(' Ana@Correo.CL ') === 'ana@correo.cl', 'normCorreo');
ok(L.normFecha('2026-11-03') === '2026-11-03' && L.normFecha('03-11-2026') === '' && L.normFecha('2026-02-30') === '', 'normFecha');
ok(L.normHora('10:00:00') === '10:00' && L.normHora('9:05') === '09:05' && L.normHora('25:00') === '', 'normHora');
ok(await L.eventId(' Ana@Correo.CL ', '2026-11-03', '10:00:00') === VECTOR, 'eventId = vector de PHP (mayúsculas, espacios y segundos)');
ok(await L.eventId('ana@correo.cl', '2026-11-03', '10:00') === VECTOR, 'eventId = vector ya normalizado');
ok(await L.eventId('', '2026-11-03', '10:00') === '' && await L.eventId('a@b.cl', '', '10:00') === '', 'eventId vacío si falta un dato');
const sin = cargar(null);
ok(await sin.eventId('ana@correo.cl', '2026-11-03', '10:00') === '', 'sin crypto.subtle: eventId vacío, sin error');

console.log(fallas ? `\n${fallas} FALLAS` : '\nTODO OK');
process.exit(fallas ? 1 : 0);
