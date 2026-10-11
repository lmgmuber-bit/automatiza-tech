/**
 * Guardar una sola vez en las vistas previas adultas (revisión B2, CX-24).
 *
 * Al tocar Guardar la foto se descarga en el acto y el paso al QR espera a que termine el
 * confeti. Antes ese temporizador no se cancelaba: si en esa espera se tocaba «Otra foto»,
 * la vista previa se desmontaba y a los 1,6 s el kiosco saltaba igual al QR con la foto
 * anterior. Esta parte no depende de React para poder probarla con node --test.
 */
export function crearGuardadoUnaVez(programar = setTimeout, cancelar = clearTimeout) {
  let usado = false
  let vivo = true
  let timer = null
  return {
    /** true desde el primer guardado: la vista ya no ofrece otra foto ni otro texto. */
    get usado() { return usado },
    /**
     * `ahora` corre en el toque (descarga, sonido, confeti) y `despues` tras `ms`, una sola
     * vez en toda la vida de la vista. Devuelve false si ya se había guardado.
     */
    guardar(ahora, despues, ms) {
      if (usado || !vivo) return false
      usado = true
      ahora()
      timer = programar(() => {
        timer = null
        if (vivo) despues()
      }, ms)
      return true
    },
    /** Al montar la vista. */
    armar() { vivo = true },
    /** Al desmontar: el paso pendiente al QR ya no ocurre. */
    soltar() {
      vivo = false
      if (timer !== null) {
        cancelar(timer)
        timer = null
      }
    },
  }
}
