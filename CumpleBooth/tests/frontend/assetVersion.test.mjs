import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'
import { conVersion } from '../../src/assetVersion.js'

/**
 * El rompe-cache de los assets de temática.
 *
 * Los archivos del tema se reemplazan con el MISMO nombre, y navegador + CDN
 * siguen sirviendo la versión vieja hasta que su cache expira. Se vio en vivo
 * dos veces el mismo día (2026-09-01): la tablet mostrando el fondo-banner
 * anterior de Héroes recién corregido, y el CDN de Hostinger sirviendo el
 * welcome previo con x-hcdn HIT. El backend publica theme.assetsVersion (mtime
 * más nuevo de la carpeta del tema) y buildRuntime lo agrega como ?v= a cada
 * URL de asset del tema.
 */
test('agrega ?v= y respeta una query existente', () => {
  assert.equal(conVersion('themes/heroes/fondo-banner.jpg', 1756789000), 'themes/heroes/fondo-banner.jpg?v=1756789000')
  assert.equal(conVersion('a.mp4?x=1', 5), 'a.mp4?x=1&v=5')
})

test('sin versión (API vieja) la URL sale intacta: nada se rompe hacia atrás', () => {
  assert.equal(conVersion('a.jpg', 0), 'a.jpg')
  assert.equal(conVersion('a.jpg', undefined), 'a.jpg')
  assert.equal(conVersion('a.jpg', 'no-numero'), 'a.jpg')
  assert.equal(conVersion(null, 9), null)
})

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const app = readFileSync(join(raiz, 'src/App.jsx'), 'utf8')

test('buildRuntime versiona TODOS los mapas de assets del tema', () => {
  // La forma, no cada línea: ningún asset del tema puede construirse sin ver().
  for (const patron of [
    /CHAR_IMG\[p\.name\] = ver\(/,
    /CHAR_VIDEO\[p\.name\] = ver\(/,
    /CHAR_JUEGO_AUDIO\[p\.name\] = ver\(/,
    /CHAR_PNG\[p\.name\] = ver\(/,
    /CHAR_RUN_ATLAS\[p\.name\] = ver\(/,
    /fondo: ver\(BASE \+ theme\.images\.sala\)/,
    /bienvenida: ver\(BASE \+ theme\.images\.banner\)/,
    /musica: ver\(BASE \+ theme\.musica\)/,
  ]) {
    assert.match(app, patron)
  }
})

test('las URLs del flujo (photoSession y video estrella) también van versionadas', () => {
  assert.match(app, /\['photoSessionVideo', 'photoSessionPoster', 'photoSessionTeaser', 'photoSessionTeaserVideo', 'starVideo'\]/)
})

test('los genéricos compartidos NO llevan la versión del tema', () => {
  // videos/saludo.mp4 y audio/captura.mp3 no viven en la carpeta del tema:
  // versionarlos con assetsVersion invalidaría su cache sin motivo.
  assert.match(app, /saludo: BASE \+ 'videos\/saludo\.mp4'/)
  assert.match(app, /captura: BASE \+ 'audio\/captura\.mp3'/)
})
