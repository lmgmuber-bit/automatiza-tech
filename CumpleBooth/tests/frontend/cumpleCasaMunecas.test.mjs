import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

/**
 * Temática «Casa de Muñecas» (cumpleaños con la Gabby animada, 2026-10-29).
 *
 * Pruebas de contrato sobre los archivos:
 * - intro propio de una fiesta: `themes/<tema>/invitation/intro-fiesta-<slug>.mp4`, el video con el nombre,
 *   toma el lugar del intro genérico, no se repite en la portada y nunca entra al repo público;
 * - `guiaSinRegalos`: la guía hasta confirmar suena aunque la invitación no esté en modo `rsvp`;
 * - la temática existe completa.
 *
 * El comportamiento por HTTP lo cubre `tests/backend/invitacion-intro-personalizado-http.php`.
 */
const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const leer = (ruta) => readFileSync(join(raiz, ruta), 'utf8')
const php = leer('public/invitacion.php')

test('el intro propio de la fiesta reemplaza el intro de la temática', () => {
  assert.match(php, /'\/invitation\/intro-fiesta-' \. \$slugFiesta \. '\.mp4'/)
  // Tiene que quedar DESPUÉS del intro genérico, o el genérico lo pisa.
  assert.ok(php.indexOf('$introPersonalizado = true;') > php.indexOf("$themeIntroRelative = 'invitation/intro-invitacion-wow-v1.mp4'"))
})

test('con intro propio la portada no repite el video', () => {
  const antes = php.slice(0, php.indexOf('data-inv-video-final'))
  const condicion = antes.slice(antes.lastIndexOf('<?php if ('))
  assert.match(condicion, /^<\?php if \(\$hasVideo && !\$introPersonalizado\): \?>/)
})

test('el intro con nombre nunca entra al repo público', () => {
  for (const ruta of ['public/themes/casa-munecas/invitation/intro-fiesta-ana-x1.mp4',
    'public/themes/casa-munecas/invitation/intro-fiesta-ana-x1-poster.jpg']) {
    // `git check-ignore` termina en 0 si la ruta está ignorada y lanza si no.
    execFileSync('git', ['check-ignore', '-q', ruta], { cwd: raiz })
  }
})

test('la guía también carga con guiaSinRegalos', () => {
  assert.match(php, /\['guiaSinRegalos'\]/)
  assert.match(php, /\(\$modoRegalos === 'rsvp' \|\| \$guiaSinRegalos\)[\s\S]{0,300}guia-confirmar/)
})
