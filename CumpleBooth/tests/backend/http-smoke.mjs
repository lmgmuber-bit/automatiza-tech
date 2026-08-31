import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const __dirname = dirname(fileURLToPath(import.meta.url))
const base = (process.env.CC_HTTP_BASE || 'http://localhost/automatiza-tech/CumpleBooth/dist').replace(/\/$/, '')
const phpBin = process.env.CC_PHP_BIN || 'php'
let checks = 0
const check = (condition, message) => { assert.ok(condition, message); checks += 1 }
const post = (payload) => fetch(`${base}/upload.php`, {
  method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify(payload),
})

// Todo el cuerpo va en un try/finally único: cleanup-http-smoke.php corre
// siempre al final (borra fotos y invitaciones SMOKE-TEST), incluso si algún
// check falla o la siembra de invitaciones revienta a mitad de camino.
try {
  const home = await fetch(`${base}/?p=demo`)
  check(home.status === 200, 'home responde')
  check(home.headers.get('permissions-policy') === 'camera=(self), microphone=(), geolocation=()', 'Permissions-Policy efectiva')
  const api = await fetch(`${base}/api.php?p=demo`).then((response) => response.json())
  check(api.ok === true && api.party.slug === 'demo', 'API demo en DB')
  check(!('galeriaPin' in api.party) && !('galeriaPinHash' in api.party), 'API no filtra PIN')

  for (const path of ['/data/parties.json', '/admin/config.php']) {
    const response = await fetch(base + path)
    check(response.status === 403, `${path} bloqueado`)
  }
  let response = await post({})
  check(response.status === 400 && (await response.json()).error === 'no_image', 'rechaza payload sin imagen')
  response = await post({ party: '../demo', image: 'data:image/png;base64,AAAA' })
  check(response.status === 400 && (await response.json()).error === 'bad_party', 'rechaza path traversal')
  response = await post({ party: 'demo', image: 'data:image/png;base64,%%%%' })
  check(response.status === 400 && (await response.json()).error === 'bad_base64', 'rechaza base64 corrupto')
  response = await post({ party: 'demo', image: `data:image/png;base64,${Buffer.from('\xff\xd8\xff\xe0JPEG').toString('base64')}` })
  check(response.status === 415 && (await response.json()).error === 'png_required', 'rechaza JPEG renombrado')

  const png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
  response = await post({ party: 'demo', name: 'smoke-test', image: `data:image/png;base64,${png}` })
  const uploaded = await response.json()
  check(response.status === 200 && uploaded.ok && /^http:\/\/localhost\//.test(uploaded.url), 'upload PNG válido usa base URL configurada')
  const photo = await fetch(uploaded.url + '&download=inline')
  check(photo.status === 200 && photo.headers.get('content-type') === 'image/png', 'token opaco sirve PNG')

  // Invitaciones (Gate A #1/#10): siembra 4 invitaciones reales marcadas
  // admin_label=SMOKE-TEST en la BD que sirve este mismo `base` (published con
  // imagen+video aprobados, draft, revoked, expired), y prueba invitacion.php /
  // descargar-invitacion.php de punta a punta contra el servidor real.
  // Si la siembra falla, el test entero falla (no se saltea silenciosamente).
  let tokens
  try {
    const seedOut = execFileSync(phpBin, [join(__dirname, 'seed-invitation-smoke.php')], { encoding: 'utf8' }).trim()
    tokens = JSON.parse(seedOut)
  } catch (e) {
    throw new Error(`No se pudo sembrar las invitaciones de prueba (¿PHP en PATH? ¿storage_mode=db?): ${e.message}`)
  }

  const invPage = await fetch(`${base}/invitacion.php?t=${tokens.published}`)
  check(invPage.status === 200, 'invitacion.php responde 200 para invitación publicada')
  check(invPage.headers.get('x-robots-tag') === 'noindex, nofollow', 'invitacion.php envía X-Robots-Tag')
  const invBody = await invPage.text()
  check(invBody.includes('Smoke Test') && !invBody.includes('SMOKE-TEST'), 'invitacion.php muestra datos del invitado, no el admin_label interno')

  const dlImage = await fetch(`${base}/descargar-invitacion.php?t=${tokens.published}&type=image`)
  check(dlImage.status === 200 && dlImage.headers.get('content-type') === 'image/png', 'descargar-invitacion.php sirve la imagen aprobada')
  check(dlImage.headers.get('content-disposition')?.includes('invitacion-cumpleclick.png') ?? false, 'nombre de descarga de imagen es neutro, sin ID interno')

  const dlVideo = await fetch(`${base}/descargar-invitacion.php?t=${tokens.published}&type=video`)
  check(dlVideo.status === 200 && dlVideo.headers.get('content-type') === 'video/mp4', 'descargar-invitacion.php sirve el video aprobado')
  check(dlVideo.headers.get('content-disposition')?.includes('invitacion-cumpleclick.mp4') ?? false, 'nombre de descarga de video es neutro, sin ID interno')

  const draftPage = await fetch(`${base}/invitacion.php?t=${tokens.draft}`)
  check(draftPage.status === 404, 'invitacion.php rechaza invitación draft/no publicada')
  const draftDl = await fetch(`${base}/descargar-invitacion.php?t=${tokens.draft}&type=image`)
  check(draftDl.status === 403, 'descargar-invitacion.php rechaza invitación draft/no publicada')

  const revokedPage = await fetch(`${base}/invitacion.php?t=${tokens.revoked}`)
  check(revokedPage.status === 404, 'invitacion.php rechaza invitación revocada')
  const revokedDl = await fetch(`${base}/descargar-invitacion.php?t=${tokens.revoked}&type=image`)
  check(revokedDl.status === 403, 'descargar-invitacion.php rechaza invitación revocada')

  const expiredPage = await fetch(`${base}/invitacion.php?t=${tokens.expired}`)
  check(expiredPage.status === 410, 'invitacion.php rechaza invitación expirada')
  const expiredDl = await fetch(`${base}/descargar-invitacion.php?t=${tokens.expired}&type=image`)
  check(expiredDl.status === 403, 'descargar-invitacion.php rechaza invitación expirada')

  for (const badToken of ['XYZ', '00000000000000000000000000000000']) {
    const bad = await fetch(`${base}/invitacion.php?t=${badToken}`)
    check(bad.status === 400 || bad.status === 404, `invitacion.php rechaza token inválido/inexistente (${badToken})`)
  }
  const noToken = await fetch(`${base}/invitacion.php`)
  check(noToken.status === 400, 'invitacion.php rechaza request sin token')
} finally {
  execFileSync(phpBin, [join(__dirname, 'cleanup-http-smoke.php')], { encoding: 'utf8' })
}

console.log(`OK ${checks} checks HTTP`)
