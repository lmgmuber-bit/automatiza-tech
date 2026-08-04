# Assets de producción — NO son del kiosco

Material intermedio que se usó para **generar** los assets finales, pero que el
kiosco no consume en runtime. Vive fuera de `public/` a propósito: así no entra
en `dist/`, no viaja por FTP y no ocupa cuota en el hosting.

**No borrar sin pensarlo.** Regenerarlo cuesta créditos.
**No mover a `public/`**: no se usa, solo agregaría peso al deploy.

## carreras/ (archivado 2026-08-03)

Auditoría de la temática Carreras: 12,21 MB sin una sola referencia en código,
datos ni plantillas.

| Archivo | Qué es |
|---|---|
| `rayo-mcqueen-pista.jpg` y otros 5 `*-pista.jpg` | Los seis autos sobre asfalto. Fueron el `start_image` de Higgsfield para generar los `saludo-*.mp4` v3. Si algún día hay que rehacer esos videos, se parte de acá. |
| `saludo-rayo-mcqueen-v4.mp4` | Cuarta versión del saludo de Rayo. Nunca se conectó: el kiosco usa `saludo-rayo-mcqueen.mp4` (por convención `saludo-<img>`) y `saludo-rayo-mcqueen-v3.mp4` (welcome alterno, referenciado explícitamente en `App.jsx`). |
| `_NOTA.txt` | Nota obsoleta que avisaba que los assets tenían "Mariano" quemado en los banners. Ya no aplica: `fondo-banner.jpg` fue regenerado sin texto. Se conserva solo como rastro histórico. |

Cómo se detectaron: cruce de cada archivo de `public/themes/carreras/` contra
todas las referencias en `src/`, `public/data/` y `public/lib.php`, contemplando
también las convenciones implícitas (`saludo-<img>`, `puzzle-<img>`,
`<img>-cut`, `<img>-run-atlas`) que el código arma solas y que por eso no
aparecen como texto literal.
