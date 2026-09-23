import test from 'node:test'
import assert from 'node:assert/strict'
import {
  CLAVE_MUSICA, Reproductor, arrancaSola, fuenteMusica, guardarPreferencia, leerPreferencia,
} from '../../src/album/musica.js'

// Un Audio de mentira: registra eventos y deja simular que el navegador
// niega el play() por falta de gesto del usuario.
class AudioFalso {
  constructor(src) {
    this.src = src
    this.paused = true
    this.loop = false
    this.volume = 1
    this.eventos = {}
    this.intentos = 0
    AudioFalso.creados.push(this)
  }
  addEventListener(ev, fn) { (this.eventos[ev] = this.eventos[ev] || []).push(fn) }
  removeEventListener(ev, fn) { this.eventos[ev] = (this.eventos[ev] || []).filter((f) => f !== fn) }
  emitir(ev) { for (const fn of this.eventos[ev] || []) fn() }
  play() {
    this.intentos += 1
    if (AudioFalso.bloqueado) return Promise.reject(new Error('NotAllowedError'))
    this.paused = false
    this.emitir('play')
    return Promise.resolve()
  }
  pause() { this.paused = true; this.emitir('pause') }
}
AudioFalso.creados = []
AudioFalso.bloqueado = false

class AlmacenFalso {
  constructor() { this.datos = new Map() }
  getItem(k) { return this.datos.has(k) ? this.datos.get(k) : null }
  setItem(k, v) { this.datos.set(k, String(v)) }
}

class DocumentoFalso {
  constructor() { this.oyentes = [] }
  addEventListener(ev, fn, cap) { this.oyentes.push({ ev, fn, cap }) }
  removeEventListener(ev, fn) { this.oyentes = this.oyentes.filter((o) => !(o.ev === ev && o.fn === fn)) }
  tocar() { for (const o of [...this.oyentes]) if (o.ev === 'pointerdown') o.fn() }
}

const tick = () => new Promise((r) => setTimeout(r, 0))

function reproductor(storage = new AlmacenFalso()) {
  AudioFalso.creados = []
  AudioFalso.bloqueado = false
  return new Reproductor({ crearAudio: (src) => new AudioFalso(src), storage })
}

test('fuenteMusica: arma la ruta con la base o devuelve null si la temática no trae pista', () => {
  assert.equal(fuenteMusica({ assets: { musica: 'themes/hielo/musica-album.mp3' } }, 'https://x.test/app/'),
    'https://x.test/app/themes/hielo/musica-album.mp3')
  assert.equal(fuenteMusica({ assets: { banner: 'themes/hielo/fondo-banner.jpg' } }, 'https://x.test/app/'), null)
  assert.equal(fuenteMusica({ assets: {} }), null)
  assert.equal(fuenteMusica(null), null)
  assert.equal(fuenteMusica({ assets: { musica: '' } }), null)
})

test('preferencia: tolera un almacenamiento que lanza y solo acepta si/no', () => {
  const roto = { getItem() { throw new Error('bloqueado') }, setItem() { throw new Error('bloqueado') } }
  assert.equal(leerPreferencia(roto), null)
  assert.doesNotThrow(() => guardarPreferencia(roto, true))
  assert.equal(leerPreferencia(null), null)
  const a = new AlmacenFalso()
  a.setItem(CLAVE_MUSICA, 'cualquiera')
  assert.equal(leerPreferencia(a), null)
  guardarPreferencia(a, false)
  assert.equal(leerPreferencia(a), 'no')
  assert.equal(arrancaSola(null), true)
  assert.equal(arrancaSola('si'), true)
  assert.equal(arrancaSola('no'), false)
})

test('cargar: prepara la pista en bucle y no la recrea si es la misma', () => {
  const r = reproductor()
  r.cargar('a.mp3')
  r.cargar('a.mp3')
  assert.equal(AudioFalso.creados.length, 1)
  assert.equal(AudioFalso.creados[0].loop, true)
  assert.equal(AudioFalso.creados[0].preload, 'auto')
  assert.equal(AudioFalso.creados[0].volume, 0.6)
  r.cargar('b.mp3')
  assert.equal(AudioFalso.creados.length, 2)
  assert.equal(AudioFalso.creados[0].paused, true, 'la pista anterior queda pausada')
})

test('destrabar: dentro del gesto del PIN la música parte, salvo que la hayan silenciado', async () => {
  const r = reproductor()
  assert.equal(await r.destrabar('a.mp3'), true)
  assert.equal(r.sonando, true)

  const silenciado = new AlmacenFalso()
  guardarPreferencia(silenciado, false)
  const r2 = reproductor(silenciado)
  assert.equal(await r2.destrabar('a.mp3'), false)
  assert.equal(r2.sonando, false)
  assert.equal(AudioFalso.creados[0].intentos, 0, 'ni siquiera intenta sonar')
})

test('arrancar: si el navegador niega el play sin gesto, espera el primer toque', async () => {
  const r = reproductor()
  const doc = new DocumentoFalso()
  r.cargar('a.mp3')
  AudioFalso.bloqueado = true
  r.arrancar(doc)
  await tick()
  assert.equal(r.sonando, false)
  assert.equal(doc.oyentes.length, 3, 'queda escuchando pointerdown, keydown y touchstart')

  AudioFalso.bloqueado = false
  doc.tocar()
  await tick()
  assert.equal(r.sonando, true)
  assert.equal(doc.oyentes.length, 0, 'suelta los oyentes después del primer gesto')
})

test('arrancar: si mientras esperaba el gesto apagaron la música, no la enciende', async () => {
  const almacen = new AlmacenFalso()
  const r = reproductor(almacen)
  const doc = new DocumentoFalso()
  r.cargar('a.mp3')
  AudioFalso.bloqueado = true
  r.arrancar(doc)
  await tick()
  guardarPreferencia(almacen, false)
  AudioFalso.bloqueado = false
  doc.tocar()
  await tick()
  assert.equal(r.sonando, false)
})

test('alternar: el botón enciende y apaga, y la elección se recuerda', async () => {
  const almacen = new AlmacenFalso()
  const r = reproductor(almacen)
  const estados = []
  r.cargar('a.mp3')
  r.suscribir((v) => estados.push(v))
  assert.equal(await r.alternar(), true)
  assert.equal(leerPreferencia(almacen), 'si')
  assert.equal(await r.alternar(), false)
  assert.equal(leerPreferencia(almacen), 'no')
  assert.deepEqual(estados, [false, true, false])
})

test('el estado sigue al elemento: si el sistema pausa la pista, el botón lo refleja', async () => {
  const r = reproductor()
  r.cargar('a.mp3')
  await r.tocar()
  assert.equal(r.sonando, true)
  AudioFalso.creados[0].pause() // p. ej. una llamada entrante
  assert.equal(r.sonando, false)
})
