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
  // La música de Casa de Muñecas es la canción de la serie (con derechos): también va solo al servidor.
  for (const ruta of ['public/themes/casa-munecas/invitation/intro-fiesta-ana-x1.mp4',
    'public/themes/casa-munecas/invitation/intro-fiesta-ana-x1-poster.jpg',
    'public/themes/casa-munecas/musica-fondo.mp3']) {
    // `git check-ignore` termina en 0 si la ruta está ignorada y lanza si no.
    execFileSync('git', ['check-ignore', '-q', ruta], { cwd: raiz })
  }
})

test('casa-munecas existe en themes.json y en el preset con sus banderas', () => {
  const crudo = leer('public/data/themes.json')
  assert.equal(crudo.match(/(?<!\r)\n/g), null, 'themes.json es CRLF: ningún salto de línea suelto')
  const tema = JSON.parse(crudo).themes['casa-munecas']
  assert.ok(tema, 'falta la entrada casa-munecas')
  assert.equal(tema.nombre, 'Casa de Muñecas')
  assert.equal(tema.vozPropia, true)
  assert.equal(tema.guiaSinRegalos, true)
  assert.match(tema.colors.accent, /^#[0-9A-F]{6}$/i)
  const preset = JSON.parse(leer('public/data/event-profile-presets.json')).themes['casa-munecas']
  assert.ok(preset?.text_area, 'falta el text_area de casa-munecas en el preset')
})

test('casa-munecas tiene su despedida en el recorrido', () => {
  assert.match(php, /'casa-munecas' => \[\s*'despedida-casa-munecas\.mp4' => '¡Te esperamos!',\s*\]/)
})

test('casa-munecas destaca el mensaje como aviso', () => {
  const css = leer('public/assets/invitation.css')
  assert.match(css, /\.inv-body\[data-theme="casa-munecas"\] \.inv-message/)
  // El ícono es un escape CSS (🧦 = \1F9E6). Un heredoc de bash ya se comió la barra una vez y dejó
  // un carácter de control invisible: el navegador mostraba un cuadrado y la prueba de arriba pasaba igual.
  assert.ok(css.includes('content: "\\1F9E6";'), 'falta el escape del calcetín')
  assert.equal(css.match(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/), null, 'invitation.css tiene un carácter de control')
})

test('la lámina de casa-munecas se lee en un teléfono', () => {
  // Medido el 29-oct a 375 px: con las reglas generales la fecha y el lugar quedaban en 8,8 px, turquesa sobre
  // el panel rosado. La temática los agranda y los escribe en blanco sobre la píldora fucsia.
  const css = leer('public/assets/invitation.css')
  const bloque = (sel) => (css.match(new RegExp(sel.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ' \\{([^}]*)\\}')) || [])[1] || ''
  const chips = bloque('.inv-body[data-theme="casa-munecas"] .inv-plate-when,\r\n.inv-body[data-theme="casa-munecas"] .inv-plate-where')
  assert.match(chips, /color: #fff;/)
  assert.match(chips, /font-size: clamp\(\.8rem, 6\.4cqw, 1\.2rem\);/)
  assert.match(bloque('.inv-body[data-theme="casa-munecas"] .inv-plate-kicker'), /font-size: clamp\(\.8rem, 6cqw, 1\.1rem\);/)
})

test('la guía también carga con guiaSinRegalos', () => {
  assert.match(php, /\['guiaSinRegalos'\]/)
  assert.match(php, /\(\$modoRegalos === 'rsvp' \|\| \$guiaSinRegalos\)[\s\S]{0,300}guia-confirmar/)
})
