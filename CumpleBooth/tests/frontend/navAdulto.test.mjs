// Navegación del cumpleaños de adulto: qué pantalla lleva Inicio y a dónde vuelve «← Volver».
import test from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { navAdulto } from '../../src/navAdulto.js'

const RECORRIDO = ['juego', 'frase-adulto', 'capture', 'preview-adulto', 'revista-titular', 'revista-capturar',
  'revista-preview', 'asomate-elegir', 'asomate-capturar', 'asomate-preview', 'grupal-capturar', 'grupal-preview',
  'qr', 'animador']

test('todas las pantallas del recorrido adulto tienen Inicio; la bienvenida y la despedida no', () => {
  for (const p of RECORRIDO) assert.ok(navAdulto(p), p)
  for (const p of ['intro', 'farewell', 'spinner', 'invitados']) assert.equal(navAdulto(p), null, p)
})

test('«← Volver» va al paso anterior que no está ya en la pantalla', () => {
  assert.equal(navAdulto('capture').volver.destino, 'frase-adulto')
  assert.equal(navAdulto('revista-capturar').volver.destino, 'revista-titular')
  assert.equal(navAdulto('asomate-capturar').volver.destino, 'asomate-elegir')
  assert.equal(navAdulto('frase-adulto').volver, null)
  assert.equal(navAdulto('frase-adulto', { hayFoto: true }).volver.destino, 'preview-adulto')
  assert.equal(navAdulto('revista-titular', { hayFoto: true }).volver.destino, 'revista-preview')
  assert.equal(navAdulto('grupal-capturar').volver, null)
  assert.equal(navAdulto('grupal-capturar', { grupalDesde: 'animador' }).volver.destino, 'animador')
  for (const p of RECORRIDO) {
    const v = navAdulto(p, { hayFoto: true, grupalDesde: 'animador' }).volver
    if (v) assert.ok(v.aria.startsWith('Volver a'), p)
  }
})

test('las vistas previas que guardan una vez dibujan su propia barra (CX-24)', () => {
  assert.equal(navAdulto('preview-adulto').propia, true)
  assert.equal(navAdulto('revista-preview').propia, true)
  assert.ok(!navAdulto('capture').propia)
})

test('App: la barra solo existe en el cumpleaños de adulto y las vistas previas la bloquean al guardar', async () => {
  const fuente = await readFile(new URL('../../src/App.jsx', import.meta.url), 'utf8')
  assert.match(fuente, /const nav = isAdulto \? navAdulto\(/)
  assert.equal((fuente.match(/<NavAdulto onInicio=\{libre\(onInicio\)\} bloqueada=\{guardando\} \/>/g) || []).length, 2)
  // La cuenta regresiva de Capture se cancela al desmontar.
  assert.match(fuente, /if \(cuentaRef\.current\) clearInterval\(cuentaRef\.current\)/)
  // El infantil conserva su botón «volver» de la ruleta.
  assert.match(fuente, /const conVolver = !isBabyShower && !isAdulto && trasInvitados === 'spinner'/)
})
