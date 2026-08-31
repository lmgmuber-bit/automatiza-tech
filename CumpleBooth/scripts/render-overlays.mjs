import puppeteer from 'puppeteer-core'
import { mkdirSync, writeFileSync } from 'fs'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const OVERLAYS = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\explicativo\\overlays'
const LOGO = 'C:/wamp64/www/automatiza-tech/CumpleBooth/design/logo/logo-icon-wordmark.png'
mkdirSync(OVERLAYS, { recursive: true })

// Los subtítulos van sobre una BANDA opaca al pie del cuadro.
//
// Antes eran texto suelto con sombra a 180px del fondo, que es exactamente
// donde el kiosco dibuja sus botones ("Siguiente invitado", "Otra foto",
// "Guardar"): los dos textos se pisaban y no se leía ninguno. La banda resuelve
// las dos cosas a la vez — tapa la fila de botones a propósito y le da al
// subtítulo un fondo con contraste garantizado sobre cualquier temática.
//
// `alto` = altura de la banda. Se agranda en las pantallas donde el kiosco
// tiene DOS filas de botones (preview, QR, diploma).
const overlays = [
  { id:'t01', text:'En cada cumpleaños<br>pasa lo mismo…', size:68, alto:420 },
  { id:'t02', text:'20 niños.<br>Cero fotos de ellos.', size:68, alto:420 },
  // La portada no tapa nada: el título va centrado y sin banda.
  // Logo real (icono + wordmark, fondo recortado a transparente) en vez de
  // texto plano — Luis pidió ver el logo de verdad, no una tipografía suelta.
  { id:'t03', text:`<img src="file:///${LOGO}" style="width:3.6em;display:block;margin:0 auto 0.18em;filter:drop-shadow(0 6px 16px rgba(0,0,0,.4))"><div style="font-size:0.34em;font-weight:600;color:#fff;text-shadow:0 3px 10px rgba(0,0,0,.7);">Tu fiesta, tu personaje o temática, tu foto</div>`, size:88, centrado:true, posicion:'abajo' },
  { id:'t04', text:'Cada invitado<br>toca su nombre', size:52, alto:340 },
  { id:'t05', text:'La ruleta elige<br>su personaje', size:52, alto:340 },
  { id:'t06', text:'Y sale a recibirlo<br>por su nombre', size:52, alto:340 },
  { id:'t07', text:'¡Click!', size:72, alto:300 },
  { id:'t08', text:'Su foto, con el marco<br>de la temática', size:52, alto:430 },
  { id:'t09', text:'Se la lleva al celular.<br>Y su diploma.', size:52, alto:470 },
  { id:'t10', text:'Los papás reciben<br>el álbum completo', size:52, alto:380 },
  // Antes decía "11 temáticas para elegir": Luis pidió no comprometerse a un
  // número (crece con el tiempo) y hablar de la elección del cliente, no de
  // un catálogo cerrado.
  { id:'t11', text:'La temática<br>de tu preferencia', size:60, alto:380 },
  { id:'t12', text:'Agenda tu fecha 📲', size:52, alto:340 },
]

// Dos juegos de subtítulos, uno por formato de salida.
//
// El horizontal NO puede reusar los PNG verticales: escalar 1080x1920 a
// 1920x1080 no conserva la proporción y la tipografía sale estirada a lo ancho
// y aplastada a lo alto. Cada formato se renderiza en su propio tamaño.
const SALIDAS = [
  { sufijo: '', W: 1080, H: 1920, escala: 1 },
  { sufijo: '-16x9', W: 1920, H: 1080, escala: 0.72 },
]

async function main() {
  const browser = await puppeteer.launch({
    executablePath: CHROME, headless:'new',
    args:['--allow-file-access-from-files'],
    defaultViewport:{width:1080,height:1920,deviceScaleFactor:1},
  })
  const page = await browser.newPage()

  for (const salida of SALIDAS) {
  await page.setViewport({ width: salida.W, height: salida.H, deviceScaleFactor: 1 })
  for (const ovBase of overlays) {
    // En horizontal hay menos alto disponible: la banda y la letra se achican
    // proporcionalmente para que no se coman medio cuadro.
    const ov = {
      ...ovBase,
      size: Math.round(ovBase.size * salida.escala),
      alto: ovBase.alto ? Math.round(ovBase.alto * salida.escala) : undefined,
    }
    // Tinta de marca (MANUAL-DE-MARCA.md) para la banda, no negro puro.
    const banda = ov.centrado
      ? `body{align-items:${ov.posicion === 'abajo' ? 'flex-end' : 'center'}}
.t{text-align:center;color:${ov.color || 'white'};font-weight:800;font-size:${ov.size}px;
line-height:1.15;padding:${ov.posicion === 'abajo' ? '0 70px 90px' : '0 70px'};text-shadow:0 4px 18px rgba(0,0,0,.7),0 1px 5px rgba(0,0,0,.5);}`
      : `body{align-items:flex-end}
.t{width:100%;height:${ov.alto}px;box-sizing:border-box;display:flex;align-items:center;
justify-content:center;text-align:center;color:${ov.color || 'white'};font-weight:800;
font-size:${ov.size}px;line-height:1.2;padding:0 70px 34px;
/* La banda arranca transparente y termina opaca: tapa los botones del kiosco
   sin que se note un corte duro contra la imagen. */
background:linear-gradient(to bottom,
  rgba(76,40,130,0) 0%,
  rgba(76,40,130,.72) 34%,
  rgba(76,40,130,.94) 62%,
  rgba(76,40,130,.97) 100%);}`

    const h = `<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><style>
@font-face{font-family:"Baloo 2";font-weight:800;font-display:block;
src:url("file:///C:/wamp64/www/automatiza-tech/CumpleBooth/node_modules/@fontsource/baloo-2/files/baloo-2-latin-800-normal.woff2") format("woff2")}
body{margin:0;width:${salida.W}px;height:${salida.H}px;display:flex;justify-content:center;
background:transparent;font-family:"Baloo 2",system-ui;}
${banda}
</style></head><body><div class="t">${ov.text}</div></body></html>`
    const nombre = `${ov.id}${salida.sufijo}`
    writeFileSync(`${OVERLAYS.replace(/\\/g,'/')}/${nombre}.html`, h)
    await page.goto(`file:///${OVERLAYS.replace(/\\/g,'/')}/${nombre}.html`, {waitUntil:'networkidle0',timeout:10000})
    await new Promise(r=>setTimeout(r,600))
    await page.screenshot({path:`${OVERLAYS}\\${nombre}.png`,type:'png',omitBackground:true})
    console.log(`  ✓ ${nombre}`)
  }
  }
  await browser.close()
  console.log('Done!')
}
main().catch(e=>{console.error('Fatal:',e.message);process.exit(1)})
