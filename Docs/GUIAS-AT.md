# Guías de AutomatizaTech (`/guias/`)

Sección de contenido propia del sitio: un listado en `https://automatizatech.cl/guias/` y cada guía en `https://automatizatech.cl/guias/<slug>/`. Rama de origen: `claude/guias-y-titulos` (parte de `claude/turnstile-web`). El texto de cada guía lo redacta Faro; Luis publica.

## Cómo crear una guía

1. wp-admin › **Guías** › **Agregar guía** (editor de bloques).
2. Llenar y dejar en **Borrador** (nunca publicar directo):
   - **Título**: es el H1 y el `<title>` de la página (se le agrega « | AutomatizaTech»).
   - **Extracto**: es la `meta description` y la bajada visible bajo el título. Entre 120 y 160 caracteres, en español de Chile. Si se deja vacío, la página usa un resumen del contenido, pero conviene escribirlo.
   - **Imagen destacada** (opcional): se usa en el listado y en `og:image`. Sin imagen se usa el logo de AT.
   - **Enlace permanente (slug)**: corto y sin tildes, por ejemplo `ley-21719-datos-personales`.
3. Vista previa del borrador, revisar con Faro y Luis.
4. **Luis publica.** Al publicar: la guía aparece en `/guias/`, entra a `wp-sitemap.xml` y `/guias/` deja de ser `noindex`.

Lo que la plantilla agrega sola: migas (Inicio › Guías › título), fechas de publicación y actualización con `<time datetime>`, el autor con su biografía, el botón «Agenda tu diagnóstico gratis» (abre el modal de agenda) y los datos estructurados `Article` + `BreadcrumbList`. La fecha de actualización es la de la última edición guardada.

La biografía del autor vive en `inc/guias/puras.php` (`at_guias_autor()`). Luis la aprobó el 17-nov-2026 con «fundador de AutomatizaTech».

## Estado: EN PROD desde el 17-nov-2026 (11:21)

Subido por Eje con el OK de Luis, con `C:/Users/luis_/respaldos/deploy-scripts/2026-11-17-guias/deploy_guias.py` (commit `c4f3671`, 30 archivos). Antes de subir, se comparó cada archivo con PROD: los 30 eran iguales a la base `87a3b6e` o nuevos. Después del despliegue se hizo `wp rewrite flush` y se purgó LiteSpeed. La verificación HTTP desde afuera dio 39 de 39 comprobaciones en OK:

- **`/guias/`:** responde 200, muestra el estado vacío, va con `noindex` y tiene un solo `<title>` y un solo canonical.
- **Portada y seis landings:** el enlace «Guías» está, no queda ningún marcador sin reemplazar, hay un solo `<title>`, no se carga Phosphor y los cuatro títulos nuevos están.
- **Navegador:** el modal de agenda abre con Turnstile y en 390 px de ancho no hay scroll horizontal.

La landing de apps móviles no tiene página asignada en PROD: su plantilla se subió, pero no se ve.

- **Respaldo:** `~/respaldos/guias-antes-20261117-112123.tar.gz`.
- **Para volver atrás:** `deploy_guias.py rollback 20261117-112123`. Restaura los modificados, quita los nuevos, hace flush y purga.

## Mientras no haya guías

`/guias/` muestra «Pronto publicaremos la primera guía» y va con `noindex` hasta que se publique la primera (automático).

## Archivos para producción

Subir en este orden (los de arriba no dependen de los de abajo; `functions.php` va al final porque es el que activa todo). Ruta base local: `wp-content/themes/automatiza-tech/`, mismo destino relativo en PROD.

| # | Archivo | Clase |
|---|---|---|
| 1 | `assets/vendor/phosphor/2.1.1/Phosphor.woff2`, `phosphor.css`, `LICENSE` | OBLIGATORIO |
| 2 | `assets/guias/guias.css`, `shell-top.html`, `shell-bottom.html` | OBLIGATORIO |
| 3 | `inc/guias/puras.php`, `plantilla.php`, `cargar.php` | OBLIGATORIO |
| 4 | `archive-guia.php`, `single-guia.php` | OBLIGATORIO |
| 5 | `inc/at-servicios.php` (enlace «Guías» en el menú y funciones del pie) | OBLIGATORIO |
| 6 | `assets/home-premium/body.html` y los siete `assets/landing-*/body.html` (marcador `{{GUIAS_PIE}}` del pie) | OBLIGATORIO con el 7 |
| 7 | `template-home-premium.php` y los siete `template-landing-*.php` (reemplazan el marcador; cuatro traen además los títulos nuevos) | OBLIGATORIO con el 6 |
| 8 | `inc/seo-tecnico/cargar.php` (un solo título y canonical en las guías) | OBLIGATORIO |
| 9 | `functions.php` (una línea `require_once` de `inc/guias/cargar.php`) | OBLIGATORIO, al final |
| 10 | `assets/landing-automatizacion/body.html` y `assets/landing-sistemas-medida/body.html` (primer párrafo con las frases nuevas) | OBLIGATORIO para los títulos |
| — | `tests/guias/*`, `tests/seo-tecnico/ganchos-test.php`, `Docs/GUIAS-AT.md` | OPCIONAL, no se suben a PROD |

Los archivos 6 y 7 van juntos: un `body.html` con el marcador sin su plantilla nueva deja `{{GUIAS_PIE}}` visible en el pie. El 17-nov, el `functions.php` de PROD era igual al de la base `87a3b6e`, así que se subió el de la rama. Si vuelve a divergir, no subas el archivo completo: agrega solo la línea
`require_once get_template_directory() . '/inc/guias/cargar.php';` después de la de `inc/seo-tecnico/cargar.php`.

Después de subir: **`wp rewrite flush`** (o wp-admin › Ajustes › Enlaces permanentes › Guardar). Sin eso `/guias/` da 404. El código además lo hace una vez solo (opción `at_guias_rewrite`), pero se deja como paso explícito.

Verificar desde afuera: `/guias/` responde 200, tiene un solo `<title>` y un solo `rel="canonical"`, el enlace «Guías» aparece en el menú (escritorio y celular) y en el pie de la portada y de las siete landings, y el botón de agenda abre el modal.

## Cómo volver atrás

- Quitar la línea `require_once ... inc/guias/cargar.php` de `functions.php`: desaparece el tipo de contenido y `/guias/` da 404. Las guías creadas quedan guardadas en la base (no se borran).
- El enlace «Guías» del menú y el pie sale al devolver `inc/at-servicios.php`; los marcadores `{{GUIAS_PIE}}` sin plantilla solo se ven si se devuelve un `body.html` sin su plantilla.
- Después, `wp rewrite flush` otra vez.
- Los cuatro títulos de landings se revierten devolviendo sus plantillas y `body.html` anteriores.

## Íconos

Familia única del sitio para lo nuevo: Phosphor 2.1.1 (regular), alojada en `assets/vendor/phosphor/2.1.1/`, bajada de `cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1`. `phosphor.css` trae solo los 13 íconos usados; para agregar uno hay que bajar el CSS completo del paquete 2.1.1 y copiar su regla. El `.woff2` pesa 147 KB completo (no se subsetea sin herramientas de fuentes). Las páginas existentes (menú, tarjetas) usan SVG en línea; el enlace «Guías» es la única pieza que las mezcla.

## Pruebas

```
php tests/guias/puras-test.php
php tests/guias/render-test.php
php tests/seo-tecnico/ganchos-test.php
```
