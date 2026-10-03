import test from 'node:test'
import assert from 'node:assert/strict'
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync, rmSync, readdirSync, copyFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { resolve, join, extname, sep } from 'node:path'
import { spawn, execFileSync } from 'node:child_process'
import http from 'node:http'
import puppeteer from 'puppeteer-core'

const root = resolve(import.meta.dirname, '../..')
const backend = process.env.CC_FERIA_BACKEND_ROOT
const php = process.env.PHP_PATH || 'C:/wamp64/bin/php/php8.3.28/php.exe'
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'
const artifacts = process.env.CC_FERIA_ARTIFACTS
const portrait = process.env.CC_FERIA_PORTRAIT
const adb = process.env.CC_FERIA_ADB
// Retrato opcional solo para Asómate (una cara que el detector reconozca); sin él se usa CC_FERIA_PORTRAIT.
const asomatePortrait = process.env.CC_FERIA_ASOMATE_PORTRAIT || portrait
// Busca un archivo por nombre dentro de una carpeta (la foto guardada por upload.php, para mirarla).
const findFile = (dir, name) => {
  for (const entry of existsSync(dir) ? readdirSync(dir, { withFileTypes: true }) : []) {
    const full = join(dir, entry.name)
    if (entry.isDirectory()) { const hit = findFile(full, name); if (hit) return hit }
    else if (entry.name === name || full.endsWith(name.replaceAll('/', sep))) return full
  }
  return null
}
// Optativa en CI sin backend: su ejecución real y comandos quedan en FTP-MANIFEST.
test('Fiestas Patrias: kiosco de fiesta sin ruleta y Chile en Volantín en la feria', { skip: !backend, timeout: 420000 }, async (t) => {
  assert.ok(existsSync(join(backend, 'public/lib.ferias.php')))
  assert.ok(existsSync(join(root, 'dist/feria.html')), 'Ejecutar npm run build primero.')
  assert.ok(existsSync(php)); assert.ok(existsSync(chrome))
  const temp = mkdtempSync(join(tmpdir(), 'cc-feria-021-'))
  mkdirSync(join(temp, 'state')); mkdirSync(join(temp, 'photos'))
  const evidence = { viewport: '1200x2000', device: 'Chrome desktop; no representa hardware Galaxy Tab A7', cases: [] }
  let backendProcess, browser, frontend, page, reversePort, debugPort
  t.after(async () => {
    if (adb && browser) { await page?.close(); browser.disconnect() } else await browser?.close()
    if (adb && reversePort) execFileSync(adb, ['-d', 'reverse', '--remove', 'tcp:' + reversePort])
    if (adb && debugPort) execFileSync(adb, ['-d', 'forward', '--remove', 'tcp:' + debugPort])
    if (frontend) await new Promise((done) => frontend.close(done))
    backendProcess?.kill()
    await new Promise((done) => setTimeout(done, 150))
    const allowed = resolve(tmpdir()) + sep
    if (resolve(temp).startsWith(allowed) && temp.includes('cc-feria-021-')) rmSync(temp, { recursive: true, force: true, maxRetries: 4, retryDelay: 200 })
  })
  const socket = http.createServer()
  await new Promise((done) => socket.listen(0, '127.0.0.1', done))
  const backendPort = socket.address().port
  await new Promise((done) => socket.close(done))
  const mime = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json', '.jpg': 'image/jpeg', '.png': 'image/png', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.wasm': 'application/wasm', '.tflite': 'application/octet-stream', '.woff2': 'font/woff2', '.woff': 'font/woff', '.mp4': 'video/mp4', '.mp3': 'audio/mpeg' }
  // La cámara falsa pide /qa/portrait.jpg en cada getUserMedia: cambiar esta variable cambia la "persona" frente a la tablet.
  let camPortrait = portrait
  frontend = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost')
    if (url.pathname.endsWith('.php')) {
      const proxy = http.request({ hostname: '127.0.0.1', port: backendPort, path: req.url, method: req.method, headers: { ...req.headers, host: '127.0.0.1:' + backendPort } }, (reply) => { res.writeHead(reply.statusCode, reply.headers); reply.pipe(res) })
      proxy.on('error', () => { res.writeHead(502); res.end() }); req.pipe(proxy); return
    }
    let file = url.pathname === '/qa/portrait.jpg' && camPortrait ? camPortrait : join(root, 'dist', decodeURIComponent(url.pathname === '/' ? 'index.html' : url.pathname))
    if (!existsSync(file)) file = join(backend, 'public', decodeURIComponent(url.pathname))
    try { res.setHeader('Content-Type', mime[extname(file)] || 'application/octet-stream'); res.end(readFileSync(file)) }
    catch { res.statusCode = 404; res.end() }
  })
  await new Promise((done) => frontend.listen(0, '127.0.0.1', done))
  const origin = 'http://127.0.0.1:' + frontend.address().port
  const env = { ...process.env, CC_STORAGE_MODE: 'db', CC_PDO_DSN: 'sqlite:' + join(temp, 'test.sqlite'),
    CC_APP_HMAC_KEY: 'f'.repeat(64), CC_PUBLIC_BASE_URL: origin, CC_PHOTO_DIR: join(temp, 'photos'), CC_STATE_DIR: join(temp, 'state'),
    CC_INVITATION_DIR: join(temp, 'invitations'), CUMPLECLICK_CONFIG_FILE: join(temp, 'no-config.php'),
    CC_AJUSTES_PATH: join(temp, 'ajustes.json'), CC_SMTP_HOST: '', CC_TEST_BACKEND: backend }
  const setup = join(temp, 'fixture.php')
  writeFileSync(setup, "<?php\n$r=getenv('CC_TEST_BACKEND');\nrequire $r.'/public/lib.php';\nrequire $r.'/public/lib.ferias.php';\nrequire $r.'/tests/backend/_migraciones.php';\ncb_test_migrar_todo(cb_pdo());\n$hoy=(new DateTimeImmutable('now',new DateTimeZone('America/Santiago')))->format('Y-m-d');\ncb_save_parties(['parties'=>['normal-prueba'=>['nombre'=>'Celebración de prueba','tema'=>'hielo','fecha'=>$hoy,'activa'=>true,'invitados'=>[['name'=>'Ana','g'=>'f']],'creada'=>gmdate('Y-m-d H:i:s')],'normal-fp'=>['nombre'=>'Fiesta dieciochera','tema'=>'fiestas-patrias','fecha'=>$hoy,'activa'=>true,'invitados'=>[['name'=>'Ana','g'=>'f']],'creada'=>gmdate('Y-m-d H:i:s')]]]);\n[$datos,$errores]=cb_feria_validar(['nombre'=>'Prueba técnica de feria','organizador'=>'Equipo de prueba','organizador_ig'=>'prueba_local','lugar'=>'Entorno local','fecha'=>$hoy,'hora_inicio'=>'10:00','mesa'=>'1','mundos_infantil'=>['hielo','spidey','kpop','familia-canina','tropical','carreras','heroes','fiestas-patrias'],'mundos_adulto'=>['hielo','adulto-estudio-bn','adulto-glam-dorado','adulto-noche-brujas','fiestas-patrias'],'max_fotos'=>50,'activa'=>'1']);\nif($errores)throw new RuntimeException(implode(' ',$errores));\n$feria=cb_feria_guardar($datos,null,'test');\necho json_encode(['slug'=>$feria['slug']]);\n")
  const fixture = JSON.parse(execFileSync(php, [setup], { env, encoding: 'utf8' }))
  backendProcess = spawn(php, ['-S', '127.0.0.1:' + backendPort, '-t', join(backend, 'public')], { env, windowsHide: true, stdio: ['ignore','ignore','ignore'] })
  for (let i=0;i<50;i++) { try { const res=await fetch(origin+'/feria-api.php?f='+fixture.slug); if(res.status!==502)break } catch {} await new Promise((done)=>setTimeout(done,100)) }
  const normal = await (await fetch(origin + '/api.php?p=normal-prueba')).text()
  assert.equal(await (await fetch(origin + '/api.php?p=normal-prueba&modo=adulto&tema=adulto-estudio-bn')).text(), normal)
  const selector = await (await fetch(origin + '/feria-api.php?f=' + fixture.slug)).json()
  assert.equal(selector.mundos.adulto.filter((world)=>world.slug.startsWith('adulto-')).length, 3)
  // Asómate llega por api.php solo si la temática lo trae: fiestas-patrias sí (también en Adultos), glam dorado no.
  const apiDe=async(tema,modo)=>(await (await fetch(origin+'/api.php?'+new URLSearchParams({p:fixture.slug,tema,modo}))).json()).theme
  assert.ok((await apiDe('fiestas-patrias','adulto')).asomate?.personajes?.length>0,'fiestas-patrias adulto publica Asómate')
  assert.ok(!(await apiDe('adulto-glam-dorado','adulto')).asomate?.personajes?.length,'glam dorado no publica Asómate')
  assert.ok((await apiDe('hielo','infantil')).asomate?.personajes?.length>0,'hielo infantil publica Asómate')
  if (adb) {
    reversePort = frontend.address().port
    execFileSync(adb, ['-d', 'reverse', 'tcp:' + reversePort, 'tcp:' + reversePort])
    debugPort = Number(execFileSync(adb, ['-d', 'forward', 'tcp:0', 'localabstract:chrome_devtools_remote'], {encoding:'utf8'}).trim())
    browser = await puppeteer.connect({ browserURL: 'http://127.0.0.1:' + debugPort, defaultViewport: null })
    evidence.device = execFileSync(adb, ['-d', 'shell', 'getprop', 'ro.product.model'], {encoding:'utf8'}).trim()
    evidence.android = execFileSync(adb, ['-d', 'shell', 'getprop', 'ro.build.version.release'], {encoding:'utf8'}).trim()
    evidence.input = 'Retrato público de prueba por canvas; cámara física no utilizada'
  } else {
    browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox','--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream','--autoplay-policy=no-user-gesture-required'] })
  }
  page = await browser.newPage()
  await page.setViewport({ width: 1200, height: 2000, deviceScaleFactor: 1 })
  evidence.browser = await browser.version()
  await page.emulateMediaFeatures([{name:'prefers-reduced-motion',value:'reduce'}])
  const pageErrors = []
  page.on('pageerror', (error)=>pageErrors.push(error.message))
  await page.evaluateOnNewDocument((photoUrl) => {
    if (!photoUrl) return
    navigator.mediaDevices.getUserMedia = async () => {
      const image=new Image();image.src=photoUrl;await image.decode()
      const canvas=document.createElement('canvas');canvas.width=600;canvas.height=800
      const ctx=canvas.getContext('2d'), paint=()=>ctx.drawImage(image,0,0,600,800)
      paint();const interval=setInterval(paint,33), stream=canvas.captureStream(30)
      const track=stream.getVideoTracks()[0],stop=track.stop.bind(track)
      track.stop=()=>{clearInterval(interval);stop()}
      return stream
    }
  }, portrait ? origin + '/qa/portrait.jpg' : null)
  // Pasos (data-step) y descargas de cada carga: así se ve si pasó por el menú y cuántas copias pidió guardar en la tablet.
  await page.evaluateOnNewDocument(() => {
    window.__pasos = []; window.__descargas = []
    const click = HTMLAnchorElement.prototype.click
    HTMLAnchorElement.prototype.click = function () { if (this.download) window.__descargas.push({ nombre: this.download, tipo: String(this.href).slice(5, String(this.href).indexOf(';')) }); return click.call(this) }
    const mirar = () => { const paso = document.querySelector('main[data-step]')?.dataset.step; if (paso && window.__pasos[window.__pasos.length - 1] !== paso) window.__pasos.push(paso) }
    new MutationObserver(mirar).observe(document, { subtree: true, childList: true, attributes: true, attributeFilter: ['data-step'] })
  })
  const detector = []
  page.on('response', (res) => { if (res.url().includes('/vendor/mediapipe/')) detector.push(res.url().split('/').pop() + ' ' + res.status()) })
  const saveDataUrl = (name, src) => { if (!artifacts || !src?.startsWith('data:')) return; const ext = src.startsWith('data:image/png') ? '.png' : '.jpg'; writeFileSync(join(artifacts, name + ext), Buffer.from(src.split(',')[1], 'base64')) }
  const shot=async(name)=>{if(artifacts){mkdirSync(artifacts,{recursive:true});await page.screenshot({path:join(artifacts,name+'.png'),fullPage:false})}}
  const button=async(label)=>{const h=await page.waitForFunction((text)=>[...document.querySelectorAll('button')].find((node)=>node.textContent.includes(text)&&!node.disabled),{},label);await h.asElement().click()}
  // Tras el nombre llega la intro de la temática si la trae (26-09): se salta con un toque, como en una fiesta.
  const pasarIntro=async()=>{await page.waitForFunction(()=>document.querySelector('main')?.dataset.step!=='name');if(await page.$('[data-step=welcome]')){await page.waitForSelector('[data-feria-welcome]');await page.click('[data-feria-welcome]')}}
  // 1) Una fiesta NORMAL de Fiestas Patrias no tiene personajes: del niño se pasa a la cámara, sin ruleta (26-09).
  await page.goto(origin+'/?p=normal-fp',{waitUntil:'networkidle0'})
  await button('Toca para entrar')
  await page.waitForFunction(()=>[...document.querySelectorAll('button')].some((b)=>b.textContent.trim().includes('Ana')))
  await page.evaluate(()=>[...document.querySelectorAll('button')].find((b)=>b.textContent.includes('Ana')).click())
  await page.waitForFunction(()=>document.querySelector('.welcome-popup')||[...document.querySelectorAll('button')].some((b)=>b.textContent.includes('Toca para tu foto')))
  if (await page.$('.welcome-popup')) await page.click('.welcome-popup')
  await button('Toca para tu foto')
  await page.waitForFunction(()=>!!document.querySelector('.shutter, .capture, video'),{timeout:20000})
  assert.equal(await page.$('.spinner'),null,'no aparece la ruleta')
  if (artifacts) { mkdirSync(artifacts,{recursive:true}); await page.screenshot({path:join(artifacts,'fp-fiesta-camara.png')}) }
  // 2) En la feria, Fiestas Patrias ofrece el juego en Niños y en Adultos, y lleva a juego/volantin/ sin `p`.
  for (const modo of ['infantil','adulto']) {
    await page.goto(origin+'/?'+new URLSearchParams({p:fixture.slug,tema:'fiestas-patrias',modo}),{waitUntil:'networkidle0'})
    if (await page.$('.feria-consent input')) await page.click('.feria-consent input')
    await page.type('.feria-name-label input','Pía');await button('Continuar');await pasarIntro()
    await page.waitForSelector('[data-step=menu]')
    await page.waitForSelector('.feria-opcion-juego')
    // El juego vive en otro repositorio y no está en este servidor: se intercepta su pedido para leer la dirección.
    let pedido = null
    const alPedir = (r) => { if (r.url().includes('/juego/volantin/')) { pedido = r.url(); r.respond({status:200,contentType:'text/html',body:'<h1>volantin</h1>'}) } else r.continue() }
    await page.setRequestInterception(true); page.on('request', alPedir)
    await page.click('.feria-opcion-juego')
    await page.waitForFunction(()=>document.querySelector('h1')?.textContent==='volantin',{timeout:15000})
    page.off('request', alPedir); await page.setRequestInterception(false)
    const destino = new URL(pedido)
    assert.ok(destino.pathname.endsWith('/juego/volantin/'),'va al juego: '+destino.href)
    assert.equal(destino.searchParams.get('p'),null,'sin p: no anota ni hay tabla de posiciones')
    assert.equal(destino.searchParams.get('kiosco'),'1')
    assert.equal(destino.searchParams.get('jugador'),'Pía')
    assert.ok((destino.searchParams.get('volver')||'').includes('feria.html?f='),'vuelve al selector de la feria')
  }
  // 3) Otras temáticas de la feria no muestran el juego.
  await page.goto(origin+'/?'+new URLSearchParams({p:fixture.slug,tema:'hielo',modo:'infantil'}),{waitUntil:'networkidle0'})
  await page.click('.feria-consent input');await button('Saltar');await pasarIntro();await page.waitForSelector('[data-step=menu]')
  assert.equal(await page.$('.feria-opcion-juego'),null,'Hielo no ofrece el volantín')
  assert.deepEqual(pageErrors,[])
})
