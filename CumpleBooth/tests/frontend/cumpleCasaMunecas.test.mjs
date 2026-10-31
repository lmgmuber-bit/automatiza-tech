import test from 'node:test'
import assert from 'node:assert/strict'
import { existsSync, readFileSync } from 'node:fs'
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

test('el intro propio suena aunque el teléfono pida reducir movimiento', () => {
  // 30-oct, en PROD: con «reducir movimiento» activado (Windows sin animaciones, iPhone con Reducir movimiento) el
  // intro se cerraba sin reproducirse y el video de Gabby no se veía en ninguna parte. El intro genérico de una
  // temática es decoración y se puede saltar; el de la fiesta es la invitación misma. Se puede omitir con el botón.
  const js = leer('public/assets/invitation.js')
  assert.match(php, /<\?php if \(\$introPersonalizado\): \?> data-inv-theme-intro-personal<\?php endif; \?>/)
  assert.match(js, /const themeIntroPersonal = themeIntro\?\.hasAttribute\('data-inv-theme-intro-personal'\)/)
  assert.match(js, /if \(reducedMotion\.matches && !themeIntroPersonal\) \{\s*finishThemeIntro\(\);/)
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

test('casa-munecas tiene su despedida en el recorrido, con la voz de Gabby', () => {
  assert.match(php, /'casa-munecas' => \[\s*'despedida-casa-munecas\.mp4' => '¡Te esperamos!',\s*\]/)
  // Luis, 30-oct: donde dice «¡Te esperamos!» tiene que escucharse. La página busca la voz por el nombre del clip.
  assert.ok(existsSync(join(raiz, 'public/themes/casa-munecas/narracion-video/despedida-casa-munecas.mp3')))
})

test('la lámina deja los datos en el centro del panel, sobre los cojines', () => {
  // Luis, 30-oct: los datos quedaban muy abajo, encima de los cojines de gatos. El panel de la lámina va de 0,27 a
  // 0,70 del alto; los pompones bajan hasta ~0,31 y los cojines empiezan en ~0,72.
  const area = JSON.parse(leer('public/data/event-profile-presets.json')).themes['casa-munecas'].text_area
  assert.ok(area.y >= 0.32 && area.y <= 0.34, `y=${area.y}`)
  assert.ok(area.y + area.h <= 0.62, `abajo=${area.y + area.h}`)
})

test('el marco de la foto del kiosco es grande y cabe en el lienzo', () => {
  // La lámina de la invitación no usa frameBox (va con su text_area): el marco del kiosco se mide contra
  // fondo-sala.jpg y tiene que dejar la cara grande (lección de baby-rosas: 352 px se veía chico, 552 px bien).
  const caja = JSON.parse(leer('public/data/themes.json')).themes['casa-munecas'].frameBox
  assert.ok(caja.x >= 0 && caja.y >= 0 && caja.x + caja.w <= 1 && caja.y + caja.h <= 1, JSON.stringify(caja))
  const lado = Math.min(caja.w * 1080, caja.h * 1920) * (1 - 2 * 0.085)
  assert.ok(lado >= 550, `cuadrado de la foto: ${Math.round(lado)} px`)
})

test('el agradecimiento de la foto termina antes de la cabeza del personaje', () => {
  // App.jsx compone la foto: el texto parte en (frameBox.y+h)·H + 0,018·H, línea 1 de 0,046·W, línea 2 a 1,35 líneas y
  // de 0,036·W; se dibuja DESPUÉS del personaje, que va en la pista 0,58–0,88·H (frameGeometry.js). Medido el 31-oct
  // sobre los 6 recortes: lo visible del más alto (DJ Catnip) empieza en 1189 px = 0,58·H + 75. La revisión final
  // encontró el texto encima de las caras con el marco grande.
  const [W, H] = [1080, 1920]
  const caja = JSON.parse(leer('public/data/themes.json')).themes['casa-munecas'].frameBox
  const base = (caja.y + caja.h) * H + 0.018 * H
  const fin = base + 0.046 * W * 1.35 + 0.036 * W * 0.6
  assert.ok(fin <= 0.58 * H + 70, `el texto termina en ${Math.round(fin)} px; la cabeza más alta está en ${0.58 * H + 75}`)
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

test('en la bienvenida del kiosco el título y la guirnalda van en el cielo, sobre la casa', () => {
  // Luis, 31-oct (captura): con el top general (título 25,5 %, guirnalda 41 %) quedaban encima del techo y no se leían.
  // En fondo-banner.jpg el cielo libre va del logo (~9 %) a los techos del centro (~29 %).
  const css = leer('src/styles.css')
  const top = (sel) => {
    const m = css.match(new RegExp(sel.replace(/[.]/g, '\\.') + '\\s*\\{[^}]*?top:\\s*([\\d.]+)%'))
    return m ? Number(m[1]) : null
  }
  const titulo = top('.intro--casa-munecas .intro-title')
  const guirnalda = top('.intro--casa-munecas .intro-party-decoration')
  assert.ok(titulo !== null && titulo >= 12 && titulo <= 17, `título en ${titulo}%`)
  assert.ok(guirnalda !== null && guirnalda >= 9.5 && guirnalda < titulo, `guirnalda en ${guirnalda}%`)
})
