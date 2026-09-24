// Música de fondo del Álbum Recuerdo.
//
// La temática puede traer una pista (`themes/<tema>/musica-album.mp3`; la
// publica album-api.php en `theme.assets.musica`). Suena en bucle mientras se
// pasa la revista, con un botón para silenciarla que se recuerda en el
// dispositivo.
//
// Los navegadores no dejan sonar audio sin un gesto del usuario, y iOS es más
// estricto todavía: el `play()` tiene que ocurrir DENTRO del gesto, no después
// de un fetch. Por eso el formulario del PIN destraba la pista en el mismo
// toque de "Entrar" (la temática ya se conoce antes del PIN), y si el álbum
// abre sin pedir PIN la música parte con el primer toque o tecla en la página.
//
// Nada de acá toca el DOM de React: el Audio vive en JS y la revista solo
// se suscribe a "está sonando / no está sonando".

export const CLAVE_MUSICA = 'cc-album-musica'
export const VOLUMEN_MUSICA = 0.6

const GESTOS = ['pointerdown', 'keydown', 'touchstart']

/** Ruta absoluta de la pista de la temática, o null si no trae. */
export function fuenteMusica(theme, base = '') {
  const rel = theme && theme.assets ? theme.assets.musica : null
  return typeof rel === 'string' && rel !== '' ? base + rel : null
}

/** Preferencia guardada en el dispositivo: 'si', 'no' o null si nunca tocaron el botón. */
export function leerPreferencia(storage) {
  try {
    const valor = storage ? storage.getItem(CLAVE_MUSICA) : null
    return valor === 'si' || valor === 'no' ? valor : null
  } catch {
    return null
  }
}

export function guardarPreferencia(storage, encendida) {
  try {
    if (storage) storage.setItem(CLAVE_MUSICA, encendida ? 'si' : 'no')
  } catch {
    // Modo privado o almacenamiento bloqueado: la preferencia dura la visita.
  }
}

/** La música parte sola salvo que en este dispositivo la hayan silenciado. */
export function arrancaSola(preferencia) {
  return preferencia !== 'no'
}

export class Reproductor {
  constructor({ crearAudio, storage = null } = {}) {
    this.crearAudio = crearAudio || ((src) => new Audio(src))
    this.storage = storage
    this.audio = null
    this.src = null
    this.sonando = false
    this.oyentes = new Set()
    this.esperandoGesto = null
    // El estado sigue al elemento y no al botón: si el sistema pausa la pista
    // (llamada entrante, otra app con audio), el botón vuelve a "Poner música".
    this.alSonar = () => this.marcar(true)
    this.alPausar = () => this.marcar(false)
  }

  cargar(src) {
    if (!src || src === this.src) return
    this.soltar()
    const audio = this.crearAudio(src)
    audio.loop = true
    audio.preload = 'auto'
    try {
      audio.volume = VOLUMEN_MUSICA
    } catch {
      // iOS: el volumen lo maneja el botón físico y la propiedad no se puede fijar.
    }
    audio.addEventListener('play', this.alSonar)
    audio.addEventListener('pause', this.alPausar)
    this.audio = audio
    this.src = src
  }

  soltar() {
    this.cancelarEspera()
    if (!this.audio) return
    this.audio.pause()
    this.audio.removeEventListener('play', this.alSonar)
    this.audio.removeEventListener('pause', this.alPausar)
    this.audio = null
    this.src = null
    this.marcar(false)
  }

  /** Intenta sonar. Devuelve true si el navegador lo permitió. */
  tocar() {
    if (!this.audio) return Promise.resolve(false)
    let promesa
    try {
      promesa = Promise.resolve(this.audio.play())
    } catch (e) {
      promesa = Promise.reject(e)
    }
    return promesa.then(
      () => { this.marcar(true); return true },
      () => { this.marcar(false); return false },
    )
  }

  pausar() {
    if (this.audio) this.audio.pause()
    this.marcar(false)
  }

  /** Botón de la revista: alterna y recuerda la elección en el dispositivo. */
  alternar() {
    if (this.sonando) {
      this.pausar()
      guardarPreferencia(this.storage, false)
      return Promise.resolve(false)
    }
    guardarPreferencia(this.storage, true)
    return this.tocar()
  }

  /**
   * Dentro de un gesto del usuario (el toque de "Entrar" del PIN): carga la
   * pista y la hace sonar si en este dispositivo no la silenciaron.
   */
  destrabar(src) {
    this.cargar(src)
    if (!this.audio || this.sonando || !arrancaSola(leerPreferencia(this.storage))) {
      return Promise.resolve(false)
    }
    return this.tocar()
  }

  /**
   * Sin gesto a mano (álbum que abre ya desbloqueado): prueba a sonar y, si el
   * navegador lo niega, espera el primer toque o tecla en el documento.
   */
  arrancar(doc) {
    if (!this.audio || this.sonando || this.esperandoGesto) return
    if (!arrancaSola(leerPreferencia(this.storage))) return
    const esperar = () => {
      if (!doc || this.esperandoGesto) return
      const alGesto = () => {
        quitar()
        // Solo si nadie apagó la música mientras tanto.
        if (arrancaSola(leerPreferencia(this.storage))) this.tocar()
      }
      const quitar = () => {
        this.esperandoGesto = null
        for (const ev of GESTOS) doc.removeEventListener(ev, alGesto, true)
      }
      this.esperandoGesto = quitar
      for (const ev of GESTOS) doc.addEventListener(ev, alGesto, true)
    }
    this.tocar().then((ok) => { if (!ok && !this.sonando) esperar() })
  }

  cancelarEspera() {
    if (this.esperandoGesto) this.esperandoGesto()
  }

  /** Avisa cada cambio de estado; llama de inmediato con el estado actual. */
  suscribir(fn) {
    this.oyentes.add(fn)
    fn(this.sonando)
    return () => this.oyentes.delete(fn)
  }

  marcar(valor) {
    if (this.sonando === valor) return
    this.sonando = valor
    for (const fn of this.oyentes) fn(valor)
  }
}
