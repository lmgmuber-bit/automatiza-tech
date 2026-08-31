/**
 * Recaptura SOLO la pantalla de la ruleta.
 *
 * Por qué existe: `capture-full.mjs` corrió dos veces el 2026-07-25 y la
 * segunda pasada dejó `screen-03-ruleta.png` en una pantalla azul vacía con el
 * botón "Continuar" — había fotografiado el video de bienvenida antes de que
 * terminara, no la ruleta. Ese archivo alimenta la infografía "Así funciona"
 * (paso 2) y el video explicativo, así que el error se propagaba a las dos.
 *
 * La diferencia con el script grande: acá se ESPERA a que la rueda esté
 * realmente girando (el nodo `.spinner-rotator` con un ángulo distinto de 0)
 * antes de disparar la foto, en vez de confiar en un timeout fijo.
 *
 * No toca parties.json ni el modo de almacenamiento: usa las fiestas demo tal
 * como están.
 *
 * Uso:  node scripts/capture-ruleta.mjs
 */
import puppeteer from 'puppeteer-core'

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
const BASE = 'http://localhost/automatiza-tech/CumpleBooth/dist/'
const SCREENS = 'C:\\wamp64\\www\\automatiza-tech\\CumpleBooth\\design\\screens'

// OJO con el slug: la fiesta `demo` cambió de temática (hoy sirve Carreras).
// El resto de los pantallazos del paquete son de Bluey, así que la ruleta tiene
// que salir de `demo-bluey` o la infografía mezcla dos temáticas.
const OBJETIVOS = [
  { slug: 'demo-bluey', archivo: 'screen-03-ruleta.png' },
  { slug: 'demo-tropical', archivo: 'tropical-screen-03-ruleta.png' },
  { slug: 'demo', archivo: 'carreras-screen-03-ruleta.png' },
]

const esperar = (ms) => new Promise((r) => setTimeout(r, ms))

/** Qué pantalla se está viendo, para poder diagnosticar si algo se traba. */
const estado = (page) => page.evaluate(() => {
  const s = document.querySelector('.screen')
  return {
    clases: s ? s.className : '(sin .screen)',
    ruleta: Boolean(document.querySelector('.spinner-rotator')),
    botones: [...document.querySelectorAll('button')].map((b) => b.textContent.trim().slice(0, 26)).slice(0, 6),
  }
})

async function capturar(page, slug, archivo) {
  await page.goto(`${BASE}?p=${slug}`, { waitUntil: 'networkidle2', timeout: 30000 })
  await esperar(1500)

  // Intro → lista de invitados
  await page.evaluate(() => {
    const b = [...document.querySelectorAll('button')].find((x) => /toca para entrar/i.test(x.textContent))
    if (b) b.click()
  })
  await esperar(2000)

  // Elegir un invitado. Los botones de la grilla no tienen clase propia: son
  // <button> con el nombre adentro, precedido de "○" (p.ej. "○Sofía").
  //
  // Filtrar por longitud NO alcanza: el botón de silenciar música es "🎵",
  // que en UTF-16 mide 2 y pasaba el filtro — el script lo clickeaba y se
  // quedaba para siempre en la lista de invitados. Se exige que el texto
  // tenga al menos 3 LETRAS reales.
  const elegido = await page.evaluate(() => {
    const b = [...document.querySelectorAll('button')]
      .filter((x) => {
        const t = x.textContent.trim()
        const letras = (t.match(/[A-Za-zÁÉÍÓÚÑáéíóúñ]/g) || []).length
        return letras >= 3 && t.length < 22 && !/entrar|admin|silenciar/i.test(t)
      })[0]
    if (b) { b.click(); return b.textContent.trim() }
    return null
  })
  console.log(`    invitado: ${elegido || '(ninguno)'}`)

  // El video de bienvenida dura ~5s. Se lo saltea, pero SIN clickear a ciegas
  // en bucle: hacerlo pasaba de largo la ruleta (que dura 3.6s y auto-avanza)
  // y el script terminaba en la pantalla de cámara.
  //
  // Secuencia determinista: esperar a que exista el botón de girar → clickearlo
  // → recién ahí medir el ángulo de la rueda.
  try {
    await page.waitForFunction(() => {
      const btns = [...document.querySelectorAll('button')]
      const girar = btns.find((x) => /girar|ruleta/i.test(x.textContent))
      if (girar) return true
      // Todavía en el video de bienvenida: saltarlo y seguir esperando.
      const seguir = btns.find((x) => /continuar|saltar/i.test(x.textContent))
      if (seguir) seguir.click()
      return false
    }, { timeout: 30000, polling: 500 })
  } catch (e) {
    console.log(`    no apareció el botón de girar: ${JSON.stringify(await estado(page))}`)
    throw e
  }

  // Se arranca la medición ANTES de girar: la rueda dura 3.6s y después la
  // pantalla avanza sola, así que no hay margen para consultar después.
  const midiendo = page.waitForFunction(() => {
    const r = document.querySelector('.spinner-rotator')
    if (!r) return false
    return parseFloat(getComputedStyle(r).getPropertyValue('--spin') || '0') > 40
  }, { timeout: 20000, polling: 'raf' })

  await page.evaluate(() => {
    const b = [...document.querySelectorAll('button')].find((x) => /girar|ruleta/i.test(x.textContent))
    if (b) b.click()
  })

  try {
    await midiendo
  } catch (e) {
    console.log(`    la rueda no llegó a girar: ${JSON.stringify(await estado(page))}`)
    throw e
  }

  await esperar(350) // ángulo fotogénico, no un frame borroso
  await page.screenshot({ path: `${SCREENS}\\${archivo}`, type: 'png' })
  console.log(`  ✓ ${archivo}`)
}

const browser = await puppeteer.launch({
  executablePath: CHROME,
  headless: false,
  args: ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream',
         '--autoplay-policy=no-user-gesture-required', '--no-first-run'],
  defaultViewport: { width: 768, height: 1024, deviceScaleFactor: 2 },
})
const page = await browser.newPage()
page.setDefaultTimeout(30000)

for (const o of OBJETIVOS) {
  try {
    await capturar(page, o.slug, o.archivo)
  } catch (e) {
    console.log(`  ! ${o.archivo}: ${e.message}`)
  }
}
await browser.close()
console.log('Listo.')
