// La página de carga del Álbum (subir.php) esconde paneles con el atributo `hidden`.
// El navegador lo aplica con `display:none` de prioridad mínima, así que cualquier
// `.panel{display:flex}` lo pisa y el "¡Gracias!" queda visible desde el principio
// (pasó en las fiestas del 13-sep: los invitados creían haber enviado y no habían
// subido nada). Este test exige la regla que lo bloquea y que la página siga
// marcando como ocultos el panel de gracias y el error.
import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'public')
const css = readFileSync(join(raiz, '_album-intake.css.php'), 'utf8')
const pagina = readFileSync(join(raiz, 'subir.php'), 'utf8')

test('el CSS de subir.php hace que [hidden] gane a cualquier display de clase', () => {
  assert.match(css, /\[hidden\]\s*\{[^}]*display\s*:\s*none\s*!important/i)
})

test('las clases que definen display en subir.php siguen existiendo (la regla de arriba es para ellas)', () => {
  assert.match(css, /\.panel\s*\{[^}]*display\s*:\s*flex/)
  assert.match(css, /\.error\s*\{/)
})

test('el panel de gracias y el error nacen ocultos en el HTML', () => {
  assert.match(pagina, /id="panel-done"[^>]*\bhidden\b/)
  assert.match(pagina, /id="form-error"[^>]*\bhidden\b/)
  assert.doesNotMatch(pagina, /id="panel-form"[^>]*\bhidden\b/)
})
