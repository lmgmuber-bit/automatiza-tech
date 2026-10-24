import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

/**
 * Baby shower con regalo validado (modo `rsvp`) y temática «Osito de Bienvenida».
 *
 * Son pruebas de contrato sobre los archivos: el formulario del modal trae los
 * tres campos nuevos solo en modo rsvp, la sección de regalos no ofrece
 * reservar, el script manda FormData y muestra el mensaje del 422 sin borrar
 * lo escrito, y la temática existe completa (themes.json, preset, agenda,
 * playlist, kicker, chispas). Los JPG y MP3 los pone el orquestador.
 */
const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const leer = (ruta) => readFileSync(join(raiz, ruta), 'utf8')
const php = leer('public/invitacion.php')
const js = leer('public/assets/invitation.js')
const css = leer('public/assets/invitation.css')

test('el modal en modo rsvp trae nombre, regalo, equipo y foto opcional', () => {
  assert.match(php, /data-rsvp-modo="rsvp"/)
  assert.match(php, /name="gift"[^>]*required/)
  assert.match(php, /name="gift"[^>]*maxlength="120"/)
  assert.match(php, /Toca una idea o escribe el tuyo/)
  assert.match(php, /name="team" value="mama"/)
  assert.match(php, /name="team" value="papa"/)
  assert.match(php, /Team Mamá/)
  assert.match(php, /Team Papá/)
  assert.match(php, /name="baby_photo"[^>]*accept="image\/\*"/)
  assert.doesNotMatch(php, /name="baby_photo"[^>]*required/)
  assert.match(php, /¿Juegas a "¿Quién es este bebé\?"\? Sube una foto tuya de bebé \(opcional\)\. Solo se verá en la fiesta\./)
})

test('los campos nuevos solo salen en modo rsvp', () => {
  const desde = php.indexOf('name="gift"')
  const antes = php.lastIndexOf('<?php if (', desde)
  assert.match(php.slice(antes, desde), /\$modoRegalos === 'rsvp'/)
})

test('la sección de regalos en rsvp es de solo lectura y usa los textos del plan', () => {
  assert.match(php, /'¿Qué le vas a llevar' \. \$aQuienRegalo \. '\?'/)
  assert.match(php, /Nos lo cuentas al confirmar\. Así nadie repite\./)
  assert.match(php, /Coche, cuna y otros regalos grandes: uno por persona\. Pañales: hasta 3 personas\. Ropa, aseo, dinero o gift card: libre\./)
  assert.match(php, /Todavía nadie anota su regalo\./)
  assert.match(php, /Ya lo traen/)
  // Se muestra aunque esté vacía.
  assert.match(php, /\$modoRegalos === 'open' \|\| \$modoRegalos === 'rsvp'\)\): \?>/)
  // «Yo lo regalo» y agregar no se imprimen en rsvp.
  assert.match(php, /\$regalo\['tomado'\] \|\| \$modoRegalos === 'rsvp'/)
  assert.match(php, /<\?php if \(\$modoRegalos !== 'rsvp'\): \?>\s*<button class="inv-gifts-otro"/)
  assert.match(php, /<\?php if \(\$modoRegalos !== 'rsvp'\): \?>\s*<div class="inv-gifts-yo"/)
})

test('el script manda FormData en rsvp, muestra el mensaje del 422 y no borra lo escrito', () => {
  assert.match(js, /body: datos \}\)/)
  assert.match(js, /cuerpo\.mensaje/)
  assert.match(js, /marcarCulpable\(cuerpo\.error\)/)
  assert.match(js, /MAX_FOTO = 10 \* 1024 \* 1024/)
  assert.match(js, /No pudimos usar esa foto\. Prueba con otra o confirma sin foto\./)
  // Ningún reset del formulario: el 422 deja todo en su sitio.
  assert.doesNotMatch(js, /formulario\.reset\(/)
  // La sección de solo lectura no cablea reservas.
  assert.match(js, /hasAttribute\('data-gifts-rsvp'\)/)
})

test('el JSON viejo de los demás modos no cambia', () => {
  assert.match(js, /headers: \{ 'Content-Type': 'application\/json' \}/)
  assert.match(js, /guest_names: datos\.get\('guest_names'\)/)
})

test('baby-osito existe en themes.json, preset y agenda con la paleta aprobada', () => {
  const temas = JSON.parse(leer('public/data/themes.json'))
  const t = (temas.themes || temas)['baby-osito']
  assert.ok(t, 'falta baby-osito en themes.json')
  assert.equal(t.nombre, 'Osito de Bienvenida')
  assert.equal(t.modalidad, 'baby_shower')
  assert.equal(t.franquicia, null)
  assert.equal(t.colors.accent, '#6E9B5A')
  assert.equal(t.colors.yellow, '#F2CB55')
  assert.equal(t.videos.despedida, 'despedida-baby-osito.mp4')
  for (const k of ['x', 'y', 'w', 'h']) assert.equal(typeof t.frameBox[k], 'number')

  const presets = JSON.parse(leer('public/data/event-profile-presets.json'))
  const todos = JSON.stringify(presets)
  assert.ok(todos.includes('"baby-osito"'), 'falta el preset baby-osito')
  assert.match(leer('public/admin/agenda.php'), /'baby-osito'=>'Osito de Bienvenida'/)
})

test('baby-osito tiene kicker, playlist y chispas propias con movimiento reducido', () => {
  assert.match(php, /'baby-osito'\) \{\s*\$heroKicker = 'Con mucho amor, te esperamos para recibir a:'/)
  assert.match(php, /'baby-osito' => \[[\s\S]*?despedida-baby-osito\.mp4/)
  assert.match(css, /\.inv-body\[data-theme="baby-osito"\] \.inv-spark \{/)
  const reducido = css.slice(css.indexOf('/* ---------- 5. Sin movimiento'))
  assert.match(reducido.slice(0, 900), /baby-osito/)
})

test('baby-osito no suena con la voz de Alice: vozPropia apaga los audios compartidos', () => {
  const temas = JSON.parse(leer('public/data/themes.json'))
  assert.equal((temas.themes || temas)['baby-osito'].vozPropia, true, 'baby-osito debe declarar vozPropia')
  // Con vozPropia, el cierre y la despedida del recorrido salen solo de narracion-video/ de la temática.
  const bloque = php.slice(php.indexOf("['vozPropia']"), php.indexOf("['vozPropia']") + 1200)
  assert.match(bloque, /\$narrationOutroUrl = \$vozDeLaTematica\('narracion-final\.mp3'\);/)
  assert.match(bloque, /\$narrationPlaylistEndUrl = \$vozDeLaTematica\(/)
  // La narración de inicio es propia de cada invitación: vozPropia no la apaga.
  assert.doesNotMatch(bloque.slice(0, bloque.indexOf('$narrationPlaylistEndUrl')), /\$narrationIntroUrl = '';/)
})

test('baby-osito trae su voz propia: despedida en tres versiones y cierre, en MP3', () => {
  for (const archivo of ['despedida-baby-osito-nino', 'despedida-baby-osito-nina', 'despedida-baby-osito-neutro', 'narracion-final']) {
    const datos = readFileSync(join(raiz, 'public/themes/baby-osito/narracion-video', archivo + '.mp3'))
    assert.ok(datos.length > 10000, archivo + ' parece vacío')
    assert.ok(datos.subarray(0, 3).toString() === 'ID3' || (datos[0] === 0xff && (datos[1] & 0xe0) === 0xe0), archivo + ' no es MP3')
  }
})

test('regalos sugeridos: botones que escriben el regalo, y los únicos tomados salen desactivados', () => {
  // Solo en el modo de confirmar con regalo, y la disponibilidad la decide el servidor con sus reglas.
  assert.match(php, /\$sugerenciasRegalo = \[\];[\s\S]{0,200}\$modoRegalos === 'rsvp'[\s\S]{0,900}cb_regalo_validar\(/)
  assert.match(php, /data-rsvp-sug="<\?= \$esc\(\$s\['texto'\]\) \?>"/)
  assert.match(php, /\$s\['disponible'\] \? '' : ' disabled/)
  for (const sugerencia of ["'Coche'", "'Cuna'", "'Pañales'", "'Ropa'", "'Gift card'"]) {
    assert.ok(php.includes(sugerencia), 'falta la sugerencia ' + sugerencia)
  }
  // Tocar una sugerencia escribe el regalo en el campo; si el servidor dice que ya está tomada, se apaga.
  assert.match(js, /closest\('\[data-rsvp-sug\]'\)[\s\S]{0,400}campoRegalo\.value = /)
  assert.match(js, /apagarSugerencia\(/)
})

test('al confirmar suena «Gracias por confirmar, te esperamos»', () => {
  assert.match(php, /<audio data-inv-narration-gracias src=/)
  assert.match(php, /'gracias-confirmar\.mp3'/)
  // Se destraba dentro del toque de «Confirmar» (iOS) y suena al volver el 200.
  assert.match(js, /prepararGracias\(\)/)
  assert.match(js, /duckWhile\(vozGracias\)/)
  const datos = readFileSync(join(raiz, 'public/themes/baby-osito/narracion-video/gracias-confirmar.mp3'))
  assert.ok(datos.length > 5000, 'gracias-confirmar.mp3 parece vacío')
})
