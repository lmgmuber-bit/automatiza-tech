import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync, statSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

/**
 * El cierre del recorrido de baby shower, en sus tres versiones.
 *
 * Alice le habla al INVITADO sobre el bebé —"sus papás y todos sus seres
 * queridos la esperamos con ansias"— y ahí el pronombre lleva género. Por eso
 * hay tres MP3 y no uno: `nina`, `nino` y `neutro`, este último reordenado
 * para no necesitar ni "la" ni "lo", que es el caso de las familias que hacen
 * la fiesta justamente para revelar el sexo.
 *
 * Un MP3 que falte no rompe ninguna página: el capítulo se queda mudo y nadie
 * se entera hasta que un cliente lo escucha. Por eso se verifica contra disco
 * y no contra una lista escrita a mano.
 */
const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const VERSIONES = ['nina', 'nino', 'neutro']

test('existen las tres versiones del cierre en las dos temáticas', () => {
  for (const tema of ['baby-nube', 'baby-safari']) {
    for (const version of VERSIONES) {
      const ruta = join(raiz, 'public/themes', tema, 'narracion-video', `despedida-${tema}-${version}.mp3`)
      const bytes = statSync(ruta).size
      assert.ok(bytes > 10_000, `${tema}/${version}: ${bytes} bytes es demasiado poco para una locución`)
    }
  }
})

test('existe el respaldo compartido, también en las tres versiones', () => {
  for (const version of VERSIONES) {
    const ruta = join(raiz, 'public/assets/audio', `narracion-playlist-final-baby-shower-${version}.mp3`)
    assert.ok(statSync(ruta).size > 10_000, `falta o está vacío el respaldo ${version}`)
  }
})

test('los tres archivos son MP3 de verdad, no HTML de error guardado con otro nombre', () => {
  // Una API que responde 200 con un JSON de error deja un archivo que parece
  // estar y no suena. Se mira la cabecera: ID3, o el sync word de un frame MPEG.
  for (const version of VERSIONES) {
    const ruta = join(raiz, 'public/assets/audio', `narracion-playlist-final-baby-shower-${version}.mp3`)
    const cabecera = readFileSync(ruta).subarray(0, 3)
    const esId3 = cabecera.toString('latin1') === 'ID3'
    const esFrame = cabecera[0] === 0xff && (cabecera[1] & 0xe0) === 0xe0
    assert.ok(esId3 || esFrame, `${version} no parece MP3 (${cabecera.toString('hex')})`)
  }
})

test('la página elige la versión por el sexo del bebé, resuelto una sola vez', () => {
  const pagina = readFileSync(join(raiz, 'public/invitacion.php'), 'utf8')
  assert.match(
    pagina,
    /\$sufijoSexoBebe = \$birthdayGender === 'f' \? 'nina' : \(\$birthdayGender === 'm' \? 'nino' : 'neutro'\)/,
    'el sufijo debe salir del sexo, con neutro como caída',
  )
  // Una sola definición: si se calculara en cada lugar podrían separarse.
  const definiciones = pagina.match(/\$sufijoSexoBebe\s*=/g) || []
  assert.equal(definiciones.length, 1, 'el sufijo se resuelve una vez, no en cada uso')

  for (const tema of ['nube', 'safari']) {
    assert.ok(
      pagina.includes(`'narracion' => 'despedida-baby-${tema}-' . $sufijoSexoBebe`),
      `el capítulo 6 de baby-${tema} debe elegir la narración por sexo`,
    )
  }
  assert.ok(
    pagina.includes("'narracion-playlist-final-baby-shower-' . $sufijoSexoBebe . '.mp3'"),
    'el respaldo compartido también va por sexo',
  )
})

test('el clip de video es uno solo: cambia la voz, no la imagen', () => {
  const pagina = readFileSync(join(raiz, 'public/invitacion.php'), 'utf8')
  for (const tema of ['nube', 'safari']) {
    // Si el MP4 llevara sufijo de sexo harían falta seis videos en vez de dos.
    assert.ok(
      pagina.includes(`'despedida-baby-${tema}.mp4' => [`),
      `baby-${tema} debe seguir usando un único MP4 de despedida`,
    )
    assert.ok(
      !pagina.includes(`despedida-baby-${tema}-nina.mp4`),
      `baby-${tema} no debe tener video por sexo`,
    )
  }
})
