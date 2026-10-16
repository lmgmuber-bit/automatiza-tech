// En un cumpleaños de adulto el kiosco habla de mujeres y hombres, nunca de niños o niñas (Luis, 16-oct-2026).
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const app = readFileSync(new URL('../../src/App.jsx', import.meta.url), 'utf8')

test('la lista y la gestión de invitados agrupan por Mujeres y Hombres en la fiesta adulta', () => {
  assert.match(app, /renderGroup\(CABINA_ADULTO \? 'Mujeres' : 'Niñas', ninas\)/)
  assert.match(app, /renderGroup\(CABINA_ADULTO \? 'Hombres' : 'Varones', varones\)/)
  assert.match(app, /grupo\(CABINA_ADULTO \? 'Mujeres' : 'Niñas', ninas\)/)
  assert.match(app, /CABINA_ADULTO \? '👩 Mujer' : '👧 Niña'/)
  assert.match(app, /CABINA_ADULTO \? '👨 Hombre' : '👦 Varón'/)
  assert.match(app, /CABINA_ADULTO \? '👥 Elegir otro invitado' : '🧒 Elegir otro niño'/)
})

test('ningún texto visible del kiosco dice niño o niña sin preguntar si la fiesta es adulta', () => {
  // Cada texto entre comillas o etiquetas con niño/niña/varón tiene que estar en una rama no adulta.
  const lineas = app.split('\n').filter((l) => !/^\s*(\/\/|\*|\/\*|\{\/\*)/.test(l))
  const sueltas = lineas.filter((l) => /(['">])[^'"<]*\b(Niñ[oa]s?|niñ[oa]s?|Varón|Varones)\b/.test(l) && !/CABINA_ADULTO/.test(l))
  assert.deepEqual(sueltas, [])
})
