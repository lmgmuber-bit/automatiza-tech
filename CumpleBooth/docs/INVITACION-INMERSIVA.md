# Invitación inmersiva

Fecha: 2026-08-09. Estado: **construida en local, no desplegada.**

`invitacion.php` dejó de ser una tarjeta centrada y pasó a ser un recorrido
vertical tematizado que termina en el acceso al Perfil del protagonista.

## Recorrido

| Sección | Qué muestra | Condición |
|---|---|---|
| `.inv-entry-gate` | Sobre que obtiene el gesto real del invitado | siempre |
| `.inv-theme-intro` | Video vertical temático, logo real, progreso y `Omitir intro` | si existe `invitation/intro-invitacion-wow-v1.mp4` en el tema |
| `.inv-hero` | Fondo temático + nombre + fecha larga en español | siempre |
| `.inv-art-section` | La invitación aprobada, nítida y enmarcada | siempre |
| `.inv-facts` | Cuándo y Dónde en tarjetas separadas | si hay fecha, hora o dirección |
| `.inv-message` | Mensaje del anfitrión como cita | si hay mensaje |
| `.inv-save` | Guardar y compartir | siempre |
| `.inv-finale` | "¿Quieres conocer a …?" + acceso a la ficha | **solo si hay perfil publicado** |

Sin perfil no se renderiza `.inv-finale` y no se cargan `event-profile.css` ni
`event-profile.js`: la invitación queda exactamente como estaba en costo y en
comportamiento.

El intro temático es parametrizable por convención de archivos, no por nombres
como Carreras o Hielo. Se genera desde cero, pero toma la paleta, atmósfera y
lenguaje visual del fondo vigente. Al terminar, omitir o fallar la reproducción,
el overlay se retira y continúa el mismo recorrido. El video no modifica el hero,
los capítulos, el Show 3D ni la lógica de juegos del tema.

## Decisiones que conviene no revertir sin leer esto

**El fondo del hero es atmósfera, no ilustración.** Va desenfocado, desaturado y
bajo un velo. Tres razones, en orden de importancia:

1. El texto se lee. Con el fondo a plena luz, `hielo` y `carreras` —que son
   fondos claros— dejaban el nombre blanco por debajo de AA.
2. El foco es el nombre del protagonista, no el decorado.
3. Los `fondo-banner.jpg` contienen personajes que se parecen a franquicias
   protegidas. Nítidos y a pantalla completa, ese parecido queda expuesto. El
   desenfoque **escala con el viewport** (7 px en móvil, 15 px desde 720 px,
   24 px desde 1200 px) precisamente porque un blur fijo se vuelve
   insuficiente en pantallas grandes.

**El velo se controla por preset, no por CSS por temática.** En
`themes.<slug>.surface`:

| Clave | Qué controla |
|---|---|
| `hero_dim` | Cuánto se apaga el fondo (0,1 – 0,85). `hielo` 0,62; `kpop` 0,34 |
| `title` | Color del antetítulo. `tropical` usa coral de marca, que sobre su propio fondo coral no llegaba a AA |

El brillo se deriva de `hero_dim`; no hay una segunda clave que mantener en
sincronía. Una temática nueva sin `surface` cae en valores intermedios seguros.

**El título ya no conjuga.** Antes decía "¡Emma y Lucas *está* de cumpleaños!".
El nombre ahora vive separado del verbo (antetítulo fijo + nombre + fecha), así
que funciona con una persona, con dos y con una pareja, sin heurísticas para
adivinar cuántos protagonistas hay.

**La fecha se formatea en PHP con nombres de mes y día en duro.** No se usa
`strftime` ni `IntlDateFormatter`: el locale del servidor no coincide entre
Windows local y Hostinger, y la fecha salía en inglés o en ISO.

**La aparición al hacer scroll es aditiva.** El contenido es visible por
defecto; `invitation.js` añade `inv-js` al `<html>` y solo entonces el CSS
oculta y anima. Si el script no carga o `IntersectionObserver` no existe, la
invitación se lee igual. Hay además un temporizador de 2,5 s como red de
seguridad.

**El loop de fondo se pausa con `prefers-reduced-motion`.** Se hace desde JS:
`animation-play-state` no afecta a la reproducción de un `<video>`.

**La ficha del protagonista fija su propia alineación.** `.ep-shell` declara
`text-align: left` porque el acceso vive dentro de una sección centrada y sin
eso los datos salían centrados y dejaban de escanearse.

## Guardar y compartir

Tres acciones, en este orden de prominencia:

1. **Enviar por WhatsApp** — `https://wa.me/?text=…` con un mensaje ya redactado
   (nombre, fecha larga, hora) y el enlace de la invitación. Comparte el
   **enlace**, porque WhatsApp no admite adjuntar un archivo remoto por URL.
2. **Compartir imagen / video** — Web Share API con el archivo real: el selector
   nativo del teléfono ofrece WhatsApp y la pieza llega al chat. Es la única vía
   para mandar el archivo en sí.
3. **Descargar** — imagen y, si existe, video.

"Según sea el caso": si la invitación tiene video aprobado, se comparte el
video; si no, la imagen. Lo decide `$hasVideo` en PHP y viaja al JS por
`data-share-kind`.

Detalles que importan:

- El botón de compartir archivo **nace `hidden`** y solo lo revela el JS si
  `navigator.canShare({files})` acepta el tipo. Antes de descargar nada se hace
  una sonda con un `File` vacío del tipo correcto: sin ella habría que bajar un
  video de varios MB para descubrir después que el navegador no puede
  compartirlo.
- La URL sale de `cb_public_base_url()`, **nunca de `HTTP_HOST`**, y va envuelta
  en `try/catch`: esa función lanza excepción si falta `CC_PUBLIC_BASE_URL` y sin
  el catch tumbaría la invitación entera. Sin configuración válida no se ofrece
  el botón de WhatsApp; descargar y compartir el archivo siguen funcionando.
- **La dirección no viaja en el mensaje de WhatsApp.** Se lee dentro de la
  invitación, que exige el enlace con token. El mensaje solo lleva nombre, fecha
  y hora.
- Cancelar el selector nativo (`AbortError`) no muestra ningún aviso; un fallo
  real sí explica la alternativa.

## Foto del protagonista — bug corregido

`cb_event_profile_media_url()` exige `$media['access_token']`, pero el shape
público de `cb_event_profile_public_by_party()` expone esa clave como `token`.
La llamada lanzaba `InvalidArgumentException`, el `catch` de `invitacion.php` la
convertía en cadena vacía y la ficha caía al monograma: **la foto de un
protagonista no se mostraba nunca**, y lo mismo habría pasado con el video y el
póster de intro cuando existieran. No se notaba porque los demos no tenían foto
y el error solo iba al log.

Ahora `$profileMediaUrl()` usa la `url` que el shape público ya trae resuelta, y
solo recurre a `cb_event_profile_media_url()` como respaldo. Verificado subiendo
una foto real por el admin: aparece en la ficha, con el texto alternativo que
escribió el administrador, y el protagonista sin foto conserva su monograma.

Para que una foto se publique hacen falta las tres casillas de su tarjeta:
subir el archivo, *Mostrar esta foto públicamente* y *Confirmo autorización de
publicación*. Sin la autorización, el servidor guarda la foto pero no la
publica.

## Video de fondo (opcional)

Si existe `public/themes/<slug>/invitation/invitation-motion-v1.mp4` el hero lo
reproduce en bucle, silenciado, con el `fondo-banner.jpg` como póster. Si no
existe, usa la imagen. Los prompts están en
`PROMPTS-INVITACION-INMERSIVA.md`; el contrato de formato, en
`FASE-INVITACIONES-DINAMICAS.md` §5.1.

**La invitación funciona sin ningún video.** Es mejora progresiva.

## Contraste verificado

Medido sobre el pixel realmente pintado, 390×844, peor muestra alrededor de cada
texto, en las cinco temáticas: mínimo **4,7:1**, todo AA. Repetible con
`storage/event-profile-demo/check-contrast-invitacion.mjs`.

## Plantilla + datos (2026-08-09)

La imagen base **no lleva datos quemados**: se genera una vez por temática con un
panel vacío y el nombre, la fecha y la hora se componen en HTML encima. La misma
imagen sirve para todas las fiestas de esa temática; cambiar de cliente no
cuesta una generación.

- Asset: `public/themes/<slug>/invitation/invitation-base-v1.png`
- Zona del panel: `themes.<slug>.text_area` en el preset, en coordenadas
  normalizadas `{x, y, w, h}` sobre la imagen, más `tone` para el color del
  texto (claro sobre panel oscuro, oscuro sobre panel claro).
- El overlay usa **porcentajes del marco y `cqw`**, no `vw`: el lienzo es la
  invitación, no la ventana, y con `vw` la superposición se desalinea.
- Si la temática no tiene base, se usa la imagen personalizada de siempre. Nada
  se rompe.

Bases generadas y **pendientes de aprobación de Luis**: `hielo` y `carreras`.
Ambas sin texto, sin personajes y sin elementos de franquicia.

`public/data/nombres-demo.json` trae 100 nombres (50 y 50) para previsualizar
plantillas sin datos reales. Es un catálogo de demo, no datos de personas.
