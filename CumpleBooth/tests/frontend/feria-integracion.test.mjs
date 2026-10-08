import test from 'node:test'
import assert from 'node:assert/strict'
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync, rmSync } from 'node:fs'
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
// Optativa en CI sin backend: su ejecución real y comandos quedan en FTP-MANIFEST.
test('integración real 020 + build 021: SQLite aislada, tres fondos adultos y fotos numeradas', { skip: !backend, timeout: 180000 }, async (t) => {
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
  const mime = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json', '.jpg': 'image/jpeg', '.png': 'image/png', '.svg': 'image/svg+xml', '.wasm': 'application/wasm', '.tflite': 'application/octet-stream', '.woff2': 'font/woff2', '.woff': 'font/woff', '.mp4': 'video/mp4', '.mp3': 'audio/mpeg' }
  frontend = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost')
    if (url.pathname.endsWith('.php')) {
      const proxy = http.request({ hostname: '127.0.0.1', port: backendPort, path: req.url, method: req.method, headers: { ...req.headers, host: '127.0.0.1:' + backendPort } }, (reply) => { res.writeHead(reply.statusCode, reply.headers); reply.pipe(res) })
      proxy.on('error', () => { res.writeHead(502); res.end() }); req.pipe(proxy); return
    }
    let file = url.pathname === '/qa/portrait.jpg' && portrait ? portrait : join(root, 'dist', decodeURIComponent(url.pathname === '/' ? 'index.html' : url.pathname))
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
  writeFileSync(setup, "<?php\n$r=getenv('CC_TEST_BACKEND');\nrequire $r.'/public/lib.php';\nrequire $r.'/public/lib.ferias.php';\nrequire $r.'/tests/backend/_migraciones.php';\ncb_test_migrar_todo(cb_pdo());\n$hoy=(new DateTimeImmutable('now',new DateTimeZone('America/Santiago')))->format('Y-m-d');\ncb_save_parties(['parties'=>['normal-prueba'=>['nombre'=>'Celebración de prueba','tema'=>'hielo','fecha'=>$hoy,'activa'=>true,'invitados'=>[['name'=>'Ana','g'=>'f']],'creada'=>gmdate('Y-m-d H:i:s')]]]);\n[$datos,$errores]=cb_feria_validar(['nombre'=>'Prueba técnica de feria','organizador'=>'Equipo de prueba','organizador_ig'=>'prueba_local','lugar'=>'Entorno local','fecha'=>$hoy,'hora_inicio'=>'10:00','mesa'=>'1','mundos_infantil'=>['hielo'],'mundos_adulto'=>['hielo','adulto-estudio-bn','adulto-glam-dorado','adulto-noche-brujas'],'max_fotos'=>50,'activa'=>'1']);\nif($errores)throw new RuntimeException(implode(' ',$errores));\n$feria=cb_feria_guardar($datos,null,'test');\necho json_encode(['slug'=>$feria['slug']]);\n")
  const fixture = JSON.parse(execFileSync(php, [setup], { env, encoding: 'utf8' }))
  backendProcess = spawn(php, ['-S', '127.0.0.1:' + backendPort, '-t', join(backend, 'public')], { env, windowsHide: true, stdio: ['ignore','ignore','ignore'] })
  for (let i=0;i<50;i++) { try { const res=await fetch(origin+'/feria-api.php?f='+fixture.slug); if(res.status!==502)break } catch {} await new Promise((done)=>setTimeout(done,100)) }
  const normal = await (await fetch(origin + '/api.php?p=normal-prueba')).text()
  assert.equal(await (await fetch(origin + '/api.php?p=normal-prueba&modo=adulto&tema=adulto-estudio-bn')).text(), normal)
  const selector = await (await fetch(origin + '/feria-api.php?f=' + fixture.slug)).json()
  assert.equal(selector.mundos.adulto.filter((world)=>world.slug.startsWith('adulto-')).length, 3)
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
  const shot=async(name)=>{if(artifacts){mkdirSync(artifacts,{recursive:true});await page.screenshot({path:join(artifacts,name+'.png'),fullPage:false})}}
  const button=async(label)=>{const h=await page.waitForFunction((text)=>[...document.querySelectorAll('button')].find((node)=>node.textContent.includes(text)&&!node.disabled),{},label);await h.asElement().click()}
  await page.goto(origin+'/feria.html?f='+fixture.slug,{waitUntil:'networkidle0'})
  await button('Toca para empezar');await shot('01-selector')
  await button('Adultos');await shot('02-mundos-adultos')
  for(const theme of ['adulto-estudio-bn','adulto-glam-dorado','adulto-noche-brujas']){
    await page.goto(origin+'/?'+new URLSearchParams({p:fixture.slug,tema:theme,modo:'adulto'}),{waitUntil:'networkidle0'})
    await button('Saltar');await page.waitForSelector('[data-step=camera]')
    await page.waitForFunction(()=> {
      const canvas = document.querySelector('.feria-camera-frame canvas')
      const status = document.querySelector('main')?.dataset.segmentation
      return canvas?.dataset.preview || (status && JSON.parse(status).fallback)
    },{timeout:20000})
    const fps=await page.$eval('.feria-camera-frame canvas',(c)=>({fps:Number(c.dataset.fps),mode:c.dataset.preview}))
    await shot(theme+'-camara')
    await button('Tomar mi foto');await page.waitForSelector('.feria-result',{timeout:30000})
    const metrics=await page.$eval('main',(main)=>JSON.parse(main.dataset.segmentation))
    assert.equal(metrics.available,true,'segmentación real disponible en '+theme)
    assert.equal(metrics.fallback,'','foto final con recorte, no marco, en '+theme)
    evidence.cases.push({theme,...fps,...metrics});await shot(theme+'-foto')
    if(artifacts){const image=await page.$eval('.feria-result',(img)=>img.src);writeFileSync(join(artifacts,theme+'-composicion.jpg'),Buffer.from(image.split(',')[1],'base64'))}
    await button('Guardar y ver mi QR');await page.waitForSelector('.feria-qr')
    assert.doesNotMatch(await page.$eval('main',(main)=>main.textContent),/diploma|niño|responsable/i)
    await shot(theme+'-qr')
  }
  await page.setRequestInterception(true)
  page.on('request',(req)=>req.url().endsWith('selfie_segmenter.tflite')?req.abort():req.continue())
  await page.setCacheEnabled(false)
  await page.goto(origin+'/?'+new URLSearchParams({p:fixture.slug,tema:'adulto-glam-dorado',modo:'adulto'}),{waitUntil:'networkidle0'})
  await button('Saltar');await button('Tomar mi foto');await page.waitForSelector('.feria-result',{timeout:30000})
  assert.ok(await page.$eval('main',(main)=>JSON.parse(main.dataset.segmentation).fallback))
  await shot('07-respaldo-marco')
  page.removeAllListeners('request')
  await page.setRequestInterception(false)
  await page.goto(origin+'/?'+new URLSearchParams({p:fixture.slug,tema:'hielo',modo:'infantil'}),{waitUntil:'networkidle0'})
  await page.click('input[type=checkbox]');await button('Saltar')
  await page.waitForSelector('[data-step=roulette]');await shot('08-ninos-ruleta')
  await button('¡Me gusta!');await button('Continuar');await button('Tomar mi foto')
  await page.waitForSelector('.feria-result');await shot('09-ninos-foto')
  await button('Guardar y ver mi QR');await page.waitForSelector('.feria-qr');await shot('10-ninos-qr')
  await button('Ver mi diploma');await page.waitForSelector('.feria-diploma img');await shot('11-ninos-diploma')
  const inspect=join(temp,'inspect.php')
  writeFileSync(inspect,'<?php $pdo=new PDO(getenv("CC_PDO_DSN")); echo json_encode($pdo->query("SELECT numero, photo_id IS NOT NULL AS ligada FROM cc_feria_fotos ORDER BY numero")->fetchAll(PDO::FETCH_ASSOC));')
  const bound=JSON.parse(execFileSync(php,[inspect],{env,encoding:'utf8'}))
  assert.ok(bound.filter((row)=>Number(row.ligada)===1).length>=4,'foto ligada a número en SQLite real')
  evidence.linkedPhotos=bound.filter((row)=>Number(row.ligada)===1).length
  assert.deepEqual(pageErrors,[])
  if(artifacts)writeFileSync(join(artifacts,'mediciones.json'),JSON.stringify(evidence,null,2))
  console.log(JSON.stringify(evidence))
})
