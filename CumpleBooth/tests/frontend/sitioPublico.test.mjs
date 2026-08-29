import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync, existsSync, statSync } from 'node:fs'

/* La landing pública (`sitio/`), que es la cara del dominio.
 *
 * Las dos cosas que cuida este archivo llegaron juntas a producción y ninguna
 * hacía fallar nada: la página cargaba entera, se veía bien y no tiraba un solo
 * error visible. Así son los dos defectos que más caro salen.
 *
 * 1. Los botones de WhatsApp apuntaban a `wa.me/[WHATSAPP_NUMBER]`, el marcador
 *    literal. Es el CTA principal de una página cuyo único objetivo es que la
 *    familia escriba. El botón se veía perfecto y no llevaba a ninguna parte.
 *
 * 2. `sitio/vendor/` no existía en el repo. No fue un olvido: el `.gitignore`
 *    de la raíz ignora `vendor/` —pensado para Composer— y se tragó gsap,
 *    ScrollTrigger, lenis y three. En el disco de quien las bajó estaban; en el
 *    repo nunca. La página seguía pintando, solo que sin animaciones de scroll
 *    ni globo 3D, y eso solo se nota abriendo la consola.
 */

const RAIZ = new URL('../../sitio/', import.meta.url)
const INDEX = readFileSync(new URL('index.html', RAIZ), 'utf8')

test('la landing no dejó ningún marcador de configuración sin reemplazar', () => {
  // Genérico a propósito: cualquier [ALGO_ASI] delata un valor que quedó por
  // poner, no solo el de WhatsApp.
  const marcadores = INDEX.match(/\[[A-Z][A-Z0-9_]{3,}\]/g) ?? []
  assert.deepEqual(
    [...new Set(marcadores)],
    [],
    'quedaron marcadores sin reemplazar en sitio/index.html'
  )
})

test('todos los enlaces de WhatsApp llevan a un número real', () => {
  const enlaces = [...INDEX.matchAll(/wa\.me\/([^"?]*)/g)].map((m) => m[1])
  assert.ok(enlaces.length > 0, 'la landing no tiene ningún enlace de WhatsApp')

  for (const numero of enlaces) {
    // Chile: 56 + 9 dígitos. Y se descartan los de relleno que suelen quedar
    // pegados de una plantilla (12345678, 00000000...).
    assert.match(numero, /^56\d{9}$/, `número de WhatsApp inválido: "${numero}"`)
    assert.ok(
      !/(\d)\1{5,}/.test(numero) && !numero.includes('123456') && !numero.includes('987654'),
      `"${numero}" parece un número de relleno, no uno real`
    )
  }
  assert.equal(new Set(enlaces).size, 1, 'hay más de un número de WhatsApp en la página')
})

test('las librerías de motion están en el repo y son las que el HTML pide', () => {
  // Se leen del HTML en vez de escribirlas acá: si mañana alguien agrega otra
  // <script src="vendor/...">, este test la exige sin que haya que tocarlo.
  const pedidos = [...INDEX.matchAll(/<script[^>]+src="(vendor\/[^"]+)"/g)].map((m) => m[1])
  const deModulo = [...readFileSync(new URL('js/globo3d.js', RAIZ), 'utf8')
    .matchAll(/from\s+['"]\.\.\/(vendor\/[^'"]+)['"]/g)].map((m) => m[1])

  // Y se sigue la cadena: un archivo puede estar y aun asi no cargar porque le
  // falta algo que EL importa. Paso exactamente eso: `three.module.min.js`
  // estaba, se servia 200 con el content-type correcto, y el `import()` fallaba
  // igual porque adentro pide `./three.core.min.js`, que no se habia copiado.
  // Un test que solo mirara la lista del HTML lo habria dado por bueno.
  const pendientes = [...new Set([...pedidos, ...deModulo])]
  assert.ok(pendientes.length >= 4, `se esperaban al menos 4 librerías, se encontraron ${pendientes.length}`)

  const vistos = new Set()
  while (pendientes.length) {
    const rel = pendientes.shift()
    if (vistos.has(rel)) continue
    vistos.add(rel)

    const ruta = new URL(rel, RAIZ)
    assert.ok(existsSync(ruta), `sitio/${rel} no está en el repo (¿lo comió el .gitignore?)`)
    // Un archivo de 0 bytes o truncado existe igual: el peso mínimo lo descarta.
    assert.ok(statSync(ruta).size > 10_000, `sitio/${rel} pesa sospechosamente poco`)
    const codigo = readFileSync(ruta, 'utf8')
    assert.match(
      codigo.slice(0, 400),
      /[/*!]|function|export|import|var |const /,
      `sitio/${rel} no parece JavaScript`
    )

    const carpeta = rel.slice(0, rel.lastIndexOf('/') + 1)
    for (const [, spec] of codigo.matchAll(/from\s*["'](\.\/[^"']+)["']/g)) {
      pendientes.push(carpeta + spec.slice(2))
    }
  }
})

test('la 404 de marca no depende de ningún archivo externo', () => {
  // Una página de error que a su vez falla al cargar sus assets es peor que la
  // de Apache: aparece justo cuando algo del servidor ya anda mal.
  const html = readFileSync(new URL('404.html', RAIZ), 'utf8')

  assert.doesNotMatch(html, /<script/i, 'la 404 no debe traer JavaScript')
  assert.doesNotMatch(html, /<link[^>]+stylesheet/i, 'la 404 no debe pedir una hoja de estilos')
  assert.doesNotMatch(html, /<img\b/i, 'la 404 no debe pedir imágenes; el isotipo va incrustado')

  // Sólo lo que el navegador REALMENTE va a buscar. Un `https?://` suelto no
  // sirve como señal: el `xmlns` del SVG es uno y no genera ninguna petición.
  const externos = [...html.matchAll(/(?:src|href)\s*=\s*["'](https?:\/\/[^"']+)/gi)].map((m) => m[1])
  assert.deepEqual(externos, [], 'la 404 enlaza recursos de otro servidor')
  const enCss = [...html.matchAll(/url\(\s*["']?(https?:\/\/[^)"']+)/gi)].map((m) => m[1])
  assert.deepEqual(enCss, [], 'el CSS de la 404 pide algo de otro servidor')

  // Lo único externo aceptado es la tipografía, y tiene que degradar sola.
  assert.match(html, /font-display:\s*swap/, 'la fuente debe degradar con font-display: swap')
  assert.match(html, /system-ui/, 'la fuente debe tener respaldo del sistema')

  // El isotipo tiene que ser el de verdad, no un dibujo parecido.
  assert.match(html, /<svg[^>]*viewBox="0 0 400 400"/, 'falta el isotipo incrustado')
  assert.ok(html.includes('#8B5CF6') && html.includes('#D6307F'), 'la 404 no usa los colores de marca')
})
