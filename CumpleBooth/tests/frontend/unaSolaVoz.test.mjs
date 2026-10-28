import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

/**
 * Una sola voz a la vez en la invitación (Luis, 2026-10-24): al tocar «Toca para seguir» con la
 * narración de inicio todavía sonando, la voz de la sección siguiente se montaba encima.
 */
const js = readFileSync(join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'public/assets/invitation.js'), 'utf8')

test('cada voz que arranca calla a las demás y les suelta la música baja', () => {
  const duck = js.slice(js.indexOf('const duckWhile = (audio) =>'), js.indexOf('const startIntroNarration'))
  assert.match(duck, /callarVoces\(audio\);/, 'duckWhile debe callar las otras voces antes de sonar')
  assert.match(duck, /vocesSonando\.set\(audio, done\)/, 'duckWhile debe registrar su voz')
  assert.match(duck, /vocesSonando\.delete\(audio\)/, 'al terminar, la voz sale del registro')
  const callar = js.slice(js.indexOf('const callarVoces = (excepto) =>'), js.indexOf('const duckWhile = (audio) =>'))
  assert.match(callar, /voz\.pause\(\);\s*liberar\(\);/, 'callar una voz también devuelve la música a su volumen')
})

test('«Toca para seguir» calla la voz de la portada', () => {
  const ir = js.slice(js.indexOf('const irALaHistoria = () =>'), js.indexOf('const armarBotonHistoria'))
  assert.match(ir, /callarVoces\(null\);/)
})
