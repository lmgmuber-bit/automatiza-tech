// Prueba del nodo «Limpiar sesión» del flujo «Agente Tech Web (PROD)». Sin red ni n8n.
// Correr: node N8N/web-sesion/limpiar-sesion.test.js
const fs = require('fs');
const path = require('path');

const js = fs.readFileSync(path.join(__dirname, 'limpiar-sesion.js'), 'utf8');
const correr = items => new Function('$input', js)({ all: () => items.map(json => ({ json })) }).map(x => x.json);

let fallas = 0;
const ok = (c, m, d) => { console.log((c ? 'ok    ' : 'FALLA ') + m + (c ? '' : '  ' + JSON.stringify(d))); if (!c) fallas++; };
// Así decide WordPress el canal (inc/lead-origen.php) cuando no viene uno explícito.
const canal = (sesion, telefono) => sesion.startsWith('ig:') ? 'instagram' : sesion.includes('whatsapp') ? 'whatsapp'
  : (telefono && sesion === '') ? 'whatsapp' : 'web';

const base = { action: 'saveLead', leadData: { name: 'Ana Rojas', email: 'ana@ejemplo.cl', phone: '+56912345678' } };
const casos = [
  ['sesión normal del formulario', 'a1b2c3d4-e5f6'],
  ['empieza con ig:', 'ig:123456789'],
  ['con «whatsapp» adentro', 'whatsapp_56912345678'],
  ['WhatsApp con mayúsculas', 'WhatsApp-x'],
  ['vacía (con teléfono)', ''],
  ['con caracteres raros', 'ig:<script>alert(1)</script>'],
];
for (const [nombre, sesion] of casos) {
  const r = correr([{ ...base, body: { ...base, sessionId: sesion, source: 'instagram', session_id: 'ig:1' }, sessionId: sesion, session_id: 'ig:2', source: 'whatsapp' }])[0];
  const s = r.body.sessionId;
  ok(/^web-[A-Za-z0-9_-]{1,60}$/.test(s), nombre + ': queda como web-…', s);
  ok(canal(s, '+56912345678') === 'web', nombre + ': WordPress lo cuenta como web', s);
  ok(r.sessionId === s && !('session_id' in r) && !('session_id' in r.body), nombre + ': un solo identificador, sin session_id paralelo', r);
  ok(!('source' in r) && !('source' in r.body), nombre + ': no deja pasar un canal elegido desde afuera', r);
  ok(r.body.leadData.email === 'ana@ejemplo.cl' && r.action === 'saveLead', nombre + ': el resto del pedido queda igual', r);
}
const a = correr([{ body: { sessionId: 'abc-123' } }])[0].body.sessionId;
const b = correr([{ body: { sessionId: 'abc-123' } }])[0].body.sessionId;
ok(a === 'web-abc-123' && a === b, 'la misma sesión da siempre el mismo identificador', [a, b]);
const largo = correr([{ body: { sessionId: 'x'.repeat(300) } }])[0].body.sessionId;
ok(largo.length <= 64, 'un identificador larguísimo se recorta', largo.length);

console.log(fallas ? `\n${fallas} FALLA(S)` : '\nTODO OK');
process.exit(fallas ? 1 : 0);
