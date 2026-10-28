// Pruebas de validacion.js. Correr: node N8N/cerebro-at/validacion.test.js
const assert = require('assert');
const { atValidarNombre: N, atValidarCorreo: C, atValidarWhatsApp: W } = require('./validacion.js');

let n = 0;
const ok = (desc, f) => { f(); n++; };

// Nombre y apellido
ok('nombre y apellido', () => assert.deepStrictEqual(N('maría  gonzález'), { ok: true, valor: 'María González' }));
ok('nombre compuesto con guion y tildes', () => assert.strictEqual(N('José Ignacio Pérez-Cotapos').valor, 'José Ignacio Pérez-Cotapos'));
ok('apóstrofo', () => assert.strictEqual(N("Shaquille O'neal").ok, true));
ok('ñ y ü', () => assert.strictEqual(N('Begoña Güemes').ok, true));
ok('solo nombre: falta apellido', () => assert.strictEqual(N('Luis').motivo, 'falta_apellido'));
ok('inicial no cuenta como apellido', () => assert.strictEqual(N('Luis M').motivo, 'falta_apellido'));
ok('saludo no es nombre', () => assert.strictEqual(N('Hola buenas').motivo, 'no_es_nombre'));
ok('"sí, dale" no es nombre', () => assert.strictEqual(N('si dale').ok, false));
ok('con número', () => assert.strictEqual(N('Juan Pérez 2').motivo, 'caracteres'));
ok('con correo', () => assert.strictEqual(N('juan@correo.cl').ok, false));
ok('vacío', () => assert.strictEqual(N('   ').motivo, 'vacio'));
ok('muy largo', () => assert.strictEqual(N('Ana ' + 'Muylargo'.repeat(10)).motivo, 'largo'));
ok('emoji', () => assert.strictEqual(N('Ana Pérez 😀').motivo, 'caracteres'));

// Correo
ok('correo válido en minúsculas', () => assert.deepStrictEqual(C('  Ana.Perez@Empresa.CL '), { ok: true, valor: 'ana.perez@empresa.cl' }));
ok('subdominio', () => assert.strictEqual(C('ana@mail.empresa.com.co').ok, true));
ok('más en el local', () => assert.strictEqual(C('ana+citas@gmail.com').ok, true));
ok('sin arroba', () => assert.strictEqual(C('ana.gmail.com').motivo, 'formato'));
ok('sin dominio de nivel superior', () => assert.strictEqual(C('ana@gmail').motivo, 'formato'));
ok('dominio de una letra', () => assert.strictEqual(C('ana@gmail.c').motivo, 'formato'));
ok('dos puntos seguidos', () => assert.strictEqual(C('ana..perez@gmail.com').motivo, 'formato'));
ok('punto al final del local', () => assert.strictEqual(C('ana.@gmail.com').motivo, 'formato'));
ok('espacio en medio', () => assert.strictEqual(C('ana perez@gmail.com').motivo, 'formato'));
ok('tipeo gmail.con', () => assert.deepStrictEqual(C('ana@gmail.con'), { ok: false, motivo: 'tipeo', sugerencia: 'ana@gmail.com' }));
ok('tipeo gmial.com', () => assert.strictEqual(C('ana@gmial.com').sugerencia, 'ana@gmail.com'));
ok('tipeo hotmial.com', () => assert.strictEqual(C('ana@hotmial.com').sugerencia, 'ana@hotmail.com'));
ok('tipeo .con en cualquier dominio', () => assert.strictEqual(C('ana@empresa.con').sugerencia, 'ana@empresa.com'));
ok('gmail.com pasa', () => assert.strictEqual(C('ana@gmail.com').ok, true));
ok('dominio .cl propio pasa', () => assert.strictEqual(C('contacto@funerariaamordedios.cl').ok, true));

// WhatsApp
ok('chileno con espacios y +', () => assert.deepStrictEqual(W('+56 9 1234 5678'), { ok: true, valor: '+56912345678' }));
ok('chileno sin código', () => assert.strictEqual(W('9 1234 5678').valor, '+56912345678'));
ok('chileno con 56 sin +', () => assert.strictEqual(W('56912345678').valor, '+56912345678'));
ok('con 00 adelante', () => assert.strictEqual(W('0056912345678').valor, '+56912345678'));
ok('venezolano', () => assert.strictEqual(W('+58 414 123 4567').valor, '+584141234567'));
ok('número de WhatsApp sin + (como lo entrega Meta)', () => assert.strictEqual(W('584141234567').valor, '+584141234567'));
ok('chileno incompleto', () => assert.strictEqual(W('+56 9 1234 567').ok, false));
ok('fijo chileno no es WhatsApp', () => assert.strictEqual(W('+56 2 2345 6789').motivo, 'chile'));
ok('muy corto', () => assert.strictEqual(W('12345').motivo, 'formato'));
ok('vacío', () => assert.strictEqual(W('').motivo, 'formato'));
ok('con letras', () => assert.strictEqual(W('mi número es el de siempre').motivo, 'formato'));

console.log(`validacion: ${n} pruebas OK`);
