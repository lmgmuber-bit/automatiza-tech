import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync, existsSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const presets = JSON.parse(readFileSync(join(root, 'public', 'data', 'event-profile-presets.json'), 'utf8'))
const themeCatalog = JSON.parse(readFileSync(join(root, 'public', 'data', 'themes.json'), 'utf8'))
const activeThemes = ['carreras', 'familia-canina', 'tropical', 'hielo', 'kpop']

test('los cinco temas actuales tienen preset y assets reutilizables', () => {
  for (const slug of activeThemes) {
    const preset = presets.themes[slug]
    assert.equal(preset?.status, 'active', `${slug}: preset activo`)
    assert.ok(preset.layout, `${slug}: layout parametrizado`)
    assert.ok(preset.scene, `${slug}: descriptor visual`)
    assert.ok(existsSync(join(root, 'public', 'themes', slug, preset.background)), `${slug}: background existente`)
    assert.equal(Object.keys(themeCatalog.themes[slug]?.colors || {}).length, 9, `${slug}: paleta completa`)
  }
})

test('un tema futuro usa un fallback sin necesitar código nuevo', () => {
  assert.equal(presets.theme_fallback.status, 'future')
  assert.equal(presets.theme_fallback.background, 'fondo-banner.jpg')
  assert.ok(presets.theme_fallback.layout)
  assert.ok(presets.theme_fallback.scene)
})

test('los tipos futuros existen como arquitectura, no como UI activa', () => {
  assert.equal(presets.event_types.child_birthday.status, 'active')
  for (const key of ['adult_birthday', 'wedding', 'baby_shower', 'baptism', 'pet_party', 'custom']) {
    assert.equal(presets.event_types[key]?.status, 'architecture_only', key)
  }
})

test('campos y secciones infantiles se definen por datos', () => {
  const birthday = presets.event_types.child_birthday
  const sectionKeys = birthday.sections.map((section) => section.key)
  // "Mejor no regalar" es una tarjeta propia, no un renglón dentro de la de
  // regalos: mezclado ahí se leía como una idea más de regalo (pedido de Luis,
  // 2026-09-02, con la ficha real de Samantha).
  assert.deepEqual(sectionKeys, ['introduction', 'favorites', 'sizes', 'gifts', 'avoid_gifts', 'custom'])
  assert.ok(birthday.fields.some((field) => field.key === 'shoe_size' && field.section === 'sizes'))
  assert.ok(birthday.fields.some((field) => field.key === 'gift_ideas' && field.section === 'gifts'))
  assert.ok(birthday.fields.some((field) => field.key === 'avoid_gifts' && field.section === 'avoid_gifts'))
  assert.ok(presets.section_accents.avoid_gifts?.tone, 'acento propio para la tarjeta de evitar')
})

test('descriptores generativos no nombran franquicias ni personajes del catálogo', () => {
  for (const slug of activeThemes) {
    const theme = themeCatalog.themes[slug]
    const scene = presets.themes[slug].scene.toLocaleLowerCase('es')
    const forbidden = [theme.franquicia, ...(theme.personajes || []).map((person) => person.name)]
      .filter(Boolean)
      .map((term) => String(term).toLocaleLowerCase('es'))
    for (const term of forbidden) {
      assert.equal(scene.includes(term), false, `${slug}: descriptor no debe contener ${term}`)
    }
  }
})
