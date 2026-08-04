// Tokens visuales de una temática -> variables CSS.
//
// Vivía dentro de App.jsx. Se extrajo cuando el Álbum Recuerdo necesitó los
// mismos colores: dos implementaciones de la misma paleta se habrían separado
// a la primera temática nueva. El kiosco sigue llamando exactamente a esta
// función con los mismos valores, así que no cambia un solo pixel.
//
// La fuente es siempre `theme.colors` de themes.json. Si un tema no declara un
// token, se deja el default de :root en styles.css en vez de inventar un color.

// El nombre de la variable CSS no coincide con el de la clave a propósito:
// --pink/--yellow vienen de la primera temática (Carreras) y renombrarlas
// tocaría las 2.200 líneas de styles.css sin ganar nada.
export const THEME_COLOR_VARS = {
  accent: '--pink',
  accentSoft: '--pink-soft',
  yellow: '--yellow',
  ink: '--ink',
  bgLight1: '--bg-light1',
  bgLight2: '--bg-light2',
  dark1: '--dark1',
  dark2: '--dark2',
  dark3: '--dark3',
}

/**
 * Vuelca los colores de la temática al elemento raíz.
 * Devuelve true si aplicó algo, false si no había colores que aplicar.
 */
export function applyThemeColors(colors, target) {
  if (!colors) return false
  const root = (target || document.documentElement).style
  let applied = false
  for (const [key, cssVar] of Object.entries(THEME_COLOR_VARS)) {
    const value = colors[key]
    if (typeof value === 'string' && value !== '') {
      root.setProperty(cssVar, value)
      applied = true
    }
  }
  return applied
}

/**
 * Convierte una ruta relativa de asset en una URL absoluta apta para meter
 * dentro de una custom property.
 *
 * Tiene que ser absoluta: una URL relativa dentro de var() se resuelve contra
 * la hoja de estilos donde se usa, que tras el build de Vite vive en
 * dist/assets/, no contra el HTML. Con ruta relativa termina pidiendo
 * dist/assets/themes/... y da 404.
 */
export function cssUrl(relativePath) {
  return `url("${new URL(relativePath, document.baseURI).href}")`
}
