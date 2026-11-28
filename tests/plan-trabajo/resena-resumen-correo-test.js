const assert = require('assert');
const fs = require('fs');
const path = require('path');
const archivo = path.join(__dirname, '..', '..', 'N8N', 'resena-google', 'resumen-correo.js');
const { armarCorreo } = require(archivo);

const base = {
  desde: '2026-11-24T10:00:00-03:00',
  aviso_a: 'operacion@ejemplo.cl',
  total: 2,
  ok: 1,
  filas: [
    { proyecto_id: 1, cliente: '<script>x</script> & "Co"', proyecto: 'Empresa Uno', modo: 'real', tipo: 'invitacion',
      correo: { simbolo: '✓', texto: 'Aceptado por el servidor de correo' },
      whatsapp: { simbolo: '⏳', texto: 'Meta lo aceptó; sin confirmación de entrega' } },
    { proyecto_id: 2, cliente: 'Cliente Dos', proyecto: 'Empresa Dos', modo: 'prueba', tipo: 'reenvio',
      correo: { simbolo: '✗', texto: 'Falló' },
      whatsapp: { simbolo: '—', texto: 'No aplica' } }
  ]
};

const d = armarCorreo(base, 'diario');
assert.strictEqual(d.asunto, 'Reseñas enviadas: 1 de 2 con todo OK');
assert.strictEqual(d.aviso_a, 'operacion@ejemplo.cl');
assert.strictEqual(d.enviar, true);
assert.ok(armarCorreo(base, 'manual').asunto.endsWith(' (envío manual)'));
assert.ok(d.html.includes('&lt;script&gt;'));
assert.ok(!d.html.includes('<script>'));
for (const s of ['✓', '✗', '⏳', '—']) assert.ok(d.html.includes(s), 'falta ' + s);
assert.ok(d.html.includes('(prueba)'));
assert.strictEqual(armarCorreo(Object.assign({}, base, { total: 0, ok: 0, filas: [] }), 'diario').enviar, false);
// Cabecera: el fondo azul va en cada <th>, no solo en el <tr>.
assert.ok((d.html.match(/<th style="[^"]*background:#0B2A4A/g) || []).length === 4, 'fondo en cada th');
// Más envíos que filas: aviso debajo de la tabla.
const mas = armarCorreo(Object.assign({}, base, { total: 7 }), 'diario');
assert.ok(mas.html.includes('Y 5 envíos más que no caben en este correo.'), 'falta aviso de envíos restantes');
assert.ok(!d.html.includes('no caben en este correo'), 'aviso sin sobrante');
const src = fs.readFileSync(archivo, 'utf8');
assert.ok(!src.includes('}}'), 'contiene }}');
assert.ok(!src.includes('$env'), 'contiene $env');
console.log('OK');
