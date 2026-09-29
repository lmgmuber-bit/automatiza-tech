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
test('integración real 020 + build 021: SQLite aislada, tres fondos adultos y fotos numeradas', { skip: !backend, timeout: 420000 }, async (t) => {
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
  writeFileSync(setup, "<?php\n$r=getenv('CC_TEST_BACKEND');\nrequire $r.'/public/lib.php';\nrequire $r.'/public/lib.ferias.php';\nrequire $r.'/tests/backend/_migraciones.php';\ncb_test_migrar_todo(cb_pdo());\n$hoy=(new DateTimeImmutable('now',new DateTimeZone('America/Santiago')))->format('Y-m-d');\ncb_save_parties(['parties'=>['normal-prueba'=>['nombre'=>'Celebración de prueba','tema'=>'hielo','fecha'=>$hoy,'activa'=>true,'invitados'=>[['name'=>'Ana','g'=>'f']],'creada'=>gmdate('Y-m-d H:i:s')]]]);\n[$datos,$errores]=cb_feria_validar(['nombre'=>'Prueba técnica de feria','organizador'=>'Equipo de prueba','organizador_ig'=>'prueba_local','lugar'=>'Entorno local','fecha'=>$hoy,'hora_inicio'=>'10:00','mesa'=>'1','mundos_infantil'=>['hielo','fiestas-patrias'],'mundos_adulto'=>['hielo','adulto-estudio-bn','adulto-glam-dorado','adulto-noche-brujas','fiestas-patrias'],'max_fotos'=>50,'activa'=>'1']);\nif($errores)throw new RuntimeException(implode(' ',$errores));\n$feria=cb_feria_guardar($datos,null,'test');\necho json_encode(['slug'=>$feria['slug']]);\n")
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
  await page.goto(origin+'/feria.html?f='+fixture.slug,{waitUntil:'networkidle0'})
  await button('Toca para empezar');await shot('01-selector')
  await button('Adultos');await shot('02-mundos-adultos')
  for(const theme of ['adulto-estudio-bn','adulto-glam-dorado','adulto-noche-brujas']){
    await page.goto(origin+'/?'+new URLSearchParams({p:fixture.slug,tema:theme,modo:'adulto'}),{waitUntil:'networkidle0'})
    await button('Saltar');await pasarIntro();await page.waitForSelector('[data-step=camera]')
    // Sin Asómate no hay menú: del nombre se pasa a la cámara, con la intro solo si la temática la trae (Noche de Brujas).
    assert.deepEqual(await page.evaluate(()=>window.__pasos),theme==='adulto-noche-brujas'?['name','welcome','camera']:['name','camera'],'sin menú en '+theme)
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
  // La prueba corre con movimiento reducido (la intro muestra un emoji, como en la fiesta): para ver el video se
  // desactiva solo aquí; la ruleta ya leyó la preferencia al cargar y sigue igual.
  await page.emulateMediaFeatures([{name:'prefers-reduced-motion',value:'no-preference'}])
  await page.click('input[type=checkbox]');await button('Saltar')
  await page.waitForSelector('[data-feria-welcome] video');await new Promise((done)=>setTimeout(done,1500))
  const intro=await page.$eval('[data-feria-welcome] video',(v)=>({t:v.currentTime,paused:v.paused,muted:v.muted,src:v.currentSrc}));await shot('08-ninos-intro')
  // Desde el 28-09 la intro se baja de antemano: si alcanzó a bajar, el <video> monta un blob URL en vez del archivo.
  assert.ok(intro.t>0.3&&!intro.paused&&!intro.muted&&(intro.src.includes('welcome-hielo')||intro.src.startsWith('blob:')),'la intro de Hielo suena y avanza: '+JSON.stringify(intro))
  await pasarIntro();await page.emulateMediaFeatures([{name:'prefers-reduced-motion',value:'reduce'}])
  assert.ok(await page.evaluate(()=>window.__pasos.includes('welcome')),'Hielo muestra su intro antes del menú')
  // Hielo trae Asómate: en Niños se elige primero (26-09). Después, ruleta, personaje y su minijuego como en una fiesta.
  await page.waitForSelector('[data-step=menu]');await shot('08a-ninos-menu')
  assert.ok((await page.$eval('.feria-opcion-asomate',(n)=>n.textContent)).length>0,'Hielo ofrece Asómate en la feria')
  await button('Foto con tu personaje')
  await page.waitForSelector('[data-step=roulette]');await shot('08-ninos-ruleta')
  await button('¡Me gusta!');await button('Continuar')
  await page.waitForSelector('[data-step=juego]');await shot('08b-ninos-juego')
  await button('Saltar');await button('Ir a mi foto');await button('Tomar mi foto')
  await page.waitForSelector('.feria-result');await shot('09-ninos-foto')
  await button('Guardar y ver mi QR');await page.waitForSelector('.feria-qr');await shot('10-ninos-qr')
  await button('Ver mi diploma');await page.waitForSelector('.feria-diploma img');await shot('11-ninos-diploma')

  // ── Despedida y respaldo de la intro (28-09: en la feria la intro se saltaba y caía directo a la ruleta) ─────
  // Al terminar, la despedida de la temática antes de volver al selector, como en una fiesta.
  const vuelta=page.waitForNavigation({waitUntil:'domcontentloaded',timeout:45000})
  await button('Terminar');await page.waitForSelector('[data-feria-video=despedida]');await shot('11b-ninos-despedida')
  assert.ok(await page.evaluate(()=>window.__pasos.includes('despedida')),'Hielo despide antes de volver al selector')
  await vuelta;assert.ok(page.url().includes('/feria.html'),'la despedida vuelve al selector: '+page.url())
  // Si el video de la intro no llega (wifi caído), queda la tarjeta con el emoji un mínimo antes de seguir: nunca salta de una.
  await page.setRequestInterception(true)
  page.on('request',(req)=>req.url().includes('welcome-hielo')?req.abort():req.continue())
  await page.goto(origin+'/?'+new URLSearchParams({p:fixture.slug,tema:'hielo',modo:'infantil'}),{waitUntil:'networkidle0'})
  if(await page.$('input[type=checkbox]'))await page.click('input[type=checkbox]')
  await button('Saltar');const t0=Date.now()
  await page.waitForSelector('[data-feria-video=welcome] .welcome-car3d-emoji',{timeout:10000});await shot('08c-ninos-intro-respaldo')
  await page.waitForSelector('[data-step=menu]',{timeout:15000});const duro=Date.now()-t0
  assert.ok(duro>=3500&&duro<=12000,'la intro de respaldo se ve un mínimo antes de seguir: '+duro+' ms')
  page.removeAllListeners('request');await page.setRequestInterception(false)

  // ── Ruta Asómate dentro de la cabina de feria (26-09, primera corrida) ───────────────────────────────
  // Recorre lo mismo que un niño en la feria: nombre → menú → Asómate → elegir personaje → cámara con la
  // guía del hueco → vista previa del kiosco (con ajuste automático de la cara) → Guardar → la foto sale
  // con número F-### y franja de la feria → QR → diploma. Las piezas (AsomateElegir, Capture,
  // AsomatePreview) son las del kiosco normal: los textos de botón son los de src/App.jsx.
  // Cámara con guía → vista previa del kiosco → "💾 Guardar" → vista previa de la feria. Devuelve la escena del kiosco.
  const capturarYGuardar=async(prefijo)=>{
    await page.waitForSelector('[data-step=asomate-capturar] .cam-guia',{timeout:20000})
    await shot(prefijo+'-camara-guia')
    await page.click('button.shutter:not([disabled])')
    await page.waitForSelector('[data-step=asomate-preview] .asomate-preview img.preview-img',{timeout:20000})
    // El detector tiene 4 s de plazo; si ve una cara, los mandos dejan de estar en 1 / 0 / 0.
    const ajuste=await page.waitForFunction(()=>{const v=[...document.querySelectorAll('.asomate-mando input')].map((i)=>Number(i.value));return v.length===3&&(v[0]!==1||v[1]!==0||v[2]!==0)?v:false},{timeout:9000}).then((h)=>h.jsonValue()).catch(()=>null)
    await new Promise((done)=>setTimeout(done,400))
    const kioscoSrc=await page.$eval('.asomate-preview img.preview-img',(img)=>img.src)
    saveDataUrl(prefijo+'-kiosco',kioscoSrc)
    await shot(prefijo+'-vista-previa')
    await button('Guardar')
    await page.waitForSelector('[data-step=preview] .feria-result',{timeout:30000})
    const etiqueta=(await page.$eval('[data-step=preview] h1',(h)=>h.textContent)).match(/F-\d+/)?.[0]
    assert.ok(etiqueta,'la foto de Asómate recibe su número F-###')
    return{ajuste,kioscoSrc,etiqueta}
  }
  const asomate=async({tema,modo,nombre,carta,prefijo,repetir=false})=>{
    await page.goto(origin+'/?'+new URLSearchParams({p:fixture.slug,tema,modo}),{waitUntil:'networkidle0'})
    if(await page.$('.feria-consent input'))await page.click('.feria-consent input')
    if(nombre){await page.type('.feria-name-label input',nombre);await button('Continuar')}else await button('Saltar')
    await pasarIntro();await page.waitForSelector('[data-step=menu]')
    const opciones=await page.$$eval('.feria-opcion',(nodes)=>nodes.map((node)=>node.textContent.trim()))
    await shot(prefijo+'-menu')
    await page.click('.feria-opcion-asomate')
    await page.waitForSelector('[data-step=asomate-elegir] .asomate-card')
    const cartas=await page.$$eval('.asomate-card',(nodes)=>nodes.map((node)=>node.textContent.trim()))
    // "← Volver" de AsomateElegir devuelve al menú de la feria (onCancel) y se puede volver a entrar.
    await button('Volver');await page.waitForSelector('[data-step=menu]')
    await page.click('.feria-opcion-asomate');await page.waitForSelector('[data-step=asomate-elegir] .asomate-card')
    await (await page.$$('.asomate-card'))[carta].click()
    assert.equal(await page.$eval('.asomate-card[aria-pressed=true]',(node)=>node.textContent.trim()),cartas[carta])
    await shot(prefijo+'-elegir')
    await button('A la foto')
    let{ajuste,kioscoSrc,etiqueta}=await capturarYGuardar(prefijo)
    if(repetir){
      // "Tomar otra foto" en la vista previa de la feria vuelve a la cámara de Asómate (no a la de la feria) y
      // conserva la reserva: la segunda foto sale con el MISMO número.
      await shot(prefijo+'-primera')
      await button('Tomar otra foto')
      const segunda=await capturarYGuardar(prefijo+'-repetida')
      assert.equal(segunda.etiqueta,etiqueta,'repetir conserva el número F-###');({ajuste,kioscoSrc}=segunda)
    }
    // La foto final es la escena del kiosco reducida sobre la franja de la feria (prepare(compuesta, true)
    // no vuelve a componer): se compara píxel a píxel con la vista previa y se mide el color de la franja.
    const medida=await page.evaluate(async(src)=>{
      const load=(s)=>new Promise((ok,ko)=>{const i=new Image();i.onload=()=>ok(i);i.onerror=ko;i.src=s})
      const final=await load(document.querySelector('.feria-result').src), kiosco=await load(src)
      const W=final.naturalWidth,H=final.naturalHeight,footer=Math.round(H*0.13),scale=(H-footer)/H
      const c=document.createElement('canvas');c.width=W;c.height=H;const x=c.getContext('2d',{willReadFrequently:true})
      x.drawImage(final,0,0);const a=x.getImageData(0,0,W,H).data
      x.clearRect(0,0,W,H);x.drawImage(kiosco,(W-W*scale)/2,0,W*scale,H-footer);const b=x.getImageData(0,0,W,H-footer).data
      let diff=0,n=0
      for(let yy=2;yy<H-footer-2;yy+=7)for(let xx=Math.ceil((W-W*scale)/2)+2;xx<Math.floor((W+W*scale)/2)-2;xx+=7){const i=(yy*W+xx)*4;diff+=Math.abs(a[i]-b[i])+Math.abs(a[i+1]-b[i+1])+Math.abs(a[i+2]-b[i+2]);n+=3}
      const px=(px,py)=>{const i=(Math.round(py)*W+Math.round(px))*4;return[a[i],a[i+1],a[i+2]]}
      return{final:[W,H],kiosco:[kiosco.naturalWidth,kiosco.naturalHeight],diffMedia:Math.round(diff/n*100)/100,franja:[px(W*0.5,H-footer*0.05),px(W*0.02,H-footer*0.5),px(W*0.98,H-3)]}
    },kioscoSrc)
    assert.deepEqual(medida.final,medida.kiosco,'la franja se agrega sin cambiar el tamaño de la escena')
    assert.ok(medida.diffMedia<8,'la foto final es la escena del kiosco, no una recomposición: diferencia '+medida.diffMedia)
    for(const color of medida.franja)assert.ok(Math.abs(color[0]-0x47)<14&&Math.abs(color[1]-0x20)<14&&Math.abs(color[2]-0x6e)<14,'franja morada de la feria: '+color)
    saveDataUrl(prefijo+'-final',await page.$eval('.feria-result',(img)=>img.src))
    await shot(prefijo+'-foto-feria')
    await button('Guardar y ver mi QR');await page.waitForSelector('.feria-qr');await shot(prefijo+'-qr')
    const diploma=modo==='infantil'
    if(diploma){
      await button('Ver mi diploma');await page.waitForSelector('.feria-diploma img')
      saveDataUrl(prefijo+'-diploma',await page.$eval('.feria-diploma img',(img)=>img.src))
      await shot(prefijo+'-diploma')
    }else assert.equal(await page.evaluate(()=>[...document.querySelectorAll('button')].some((b)=>b.textContent.includes('diploma'))),false,'Adultos no ofrece diploma')
    const pasos=await page.evaluate(()=>window.__pasos), descargas=await page.evaluate(()=>window.__descargas)
    const captura=['asomate-capturar','asomate-preview','preview']
    assert.deepEqual(pasos,['name','welcome','menu','asomate-elegir','menu','asomate-elegir',...captura,...(repetir?captura:[]),'qr',...(diploma?['diploma']:[])])
    return{tema,modo,nombre:nombre||'',opciones,cartas,elegido:cartas[carta],etiqueta,repetida:repetir,ajusteAutomatico:ajuste,medida,pasos,descargas}
  }
  camPortrait=asomatePortrait
  const asomateNinos=await asomate({tema:'hielo',modo:'infantil',nombre:'Tomás',carta:1,prefijo:'12-asomate-ninos-hielo',repetir:true})
  assert.match(asomateNinos.opciones[0],/Foto con tu personaje/);assert.match(asomateNinos.opciones[1],/Asómate/)
  // Adultos con una temática que trae Asómate (fiestas-patrias): el menú aparece y la ruta completa funciona.
  const asomateAdultos=await asomate({tema:'fiestas-patrias',modo:'adulto',nombre:'',carta:3,prefijo:'13-asomate-adultos-fiestas-patrias'})
  // Fiestas Patrias trae además Chile en Volantín (26-09): se buscan por texto, no por posición.
  assert.match(asomateAdultos.opciones[0],/Foto con la temática/);assert.ok(asomateAdultos.opciones.some((o)=>/huas/i.test(o)),'ofrece Asómate de huaso');assert.ok(asomateAdultos.opciones.some((o)=>/Volantín/.test(o)),'ofrece el volantín')
  // Niños con fiestas-patrias (la temática de la feria de hoy): sin ruleta, Asómate de huaso y diploma.
  const asomateNinosFp=await asomate({tema:'fiestas-patrias',modo:'infantil',nombre:'Pía',carta:0,prefijo:'14-asomate-ninos-fiestas-patrias'})
  assert.match(asomateNinosFp.opciones[0],/Foto con la temática/)
  camPortrait=portrait
  evidence.asomate=[asomateNinos,asomateAdultos,asomateNinosFp];evidence.detector=[...new Set(detector)]

  const inspect=join(temp,'inspect.php')
  writeFileSync(inspect,'<?php $pdo=new PDO(getenv("CC_PDO_DSN")); echo json_encode($pdo->query("SELECT ff.numero, ff.modo, ff.theme_slug, ff.nombre, ff.photo_id IS NOT NULL AS ligada, ph.storage_key, ph.width, ph.height, ph.byte_size FROM cc_feria_fotos ff LEFT JOIN cc_photos ph ON ph.id = ff.photo_id ORDER BY ff.numero")->fetchAll(PDO::FETCH_ASSOC));')
  const bound=JSON.parse(execFileSync(php,[inspect],{env,encoding:'utf8'}))
  evidence.feriaFotos=bound
  // Cada foto de Asómate quedó ligada a SU número, con su modo, temática y nombre, y al tamaño de la foto final.
  for(const caso of evidence.asomate){
    const row=bound.find((r)=>Number(r.numero)===Number(caso.etiqueta.slice(2)))
    assert.ok(row,'existe la fila de '+caso.etiqueta)
    assert.equal(Number(row.ligada),1,'foto de Asómate ligada a '+caso.etiqueta)
    assert.equal(row.modo,caso.modo);assert.equal(row.theme_slug,caso.tema);assert.equal(row.nombre||'',caso.nombre)
    assert.deepEqual([Number(row.width),Number(row.height)],caso.medida.final,'la foto guardada es la final con franja')
    const stored=row.storage_key&&findFile(join(temp,'photos'),String(row.storage_key).split('/').pop())
    caso.guardada={storage_key:row.storage_key,encontrada:Boolean(stored),bytes:Number(row.byte_size)}
    if(artifacts&&stored)copyFileSync(stored,join(artifacts,caso.etiqueta+'-guardada'+extname(stored)))
  }
  assert.ok(bound.filter((row)=>Number(row.ligada)===1).length>=4,'foto ligada a número en SQLite real')
  evidence.linkedPhotos=bound.filter((row)=>Number(row.ligada)===1).length
  assert.deepEqual(pageErrors,[])
  if(artifacts)writeFileSync(join(artifacts,'mediciones.json'),JSON.stringify(evidence,null,2))
  console.log(JSON.stringify(evidence))
})
