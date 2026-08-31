# Handoff a Claude — CumpleClick: invitaciones inmersivas y campaña promocional

Fecha de corte: 2026-08-12  
Responsable del traspaso: Codex  
Proyecto: `C:\wamp64\www\automatiza-tech\.worktrees\cumplebooth-protagonista\CumpleBooth`  
Rama activa: `codex/frozen-invitation-parity`  
Último commit observado: `c13abea feat(invitations): close hielo candidates and global outro narration`

## 1. Objetivo al retomar

Continuar con Luis la producción de una campaña vertical para promocionar las
invitaciones inmersivas de CumpleClick y sus dos modalidades comerciales:

- **Plan Básico**: invitación **Scroll**.
- **Plan Full**: invitación **Automática**.

La promesa central aprobada es: **«La fiesta comienza cuando abren la
invitación»**.

La siguiente etapa no es generar más escenas de inmediato. Primero hay que:

1. recibir las grabaciones de pantalla que hará Luis;
2. revisar y preparar los cinco intros temáticos;
3. corregir en postproducción el logo defectuoso de tres videos;
4. montar una primera versión del Reel maestro de 30–40 segundos;
5. mostrar el borrador a Luis antes de publicar, desplegar o pautar.

## 2. Restricciones y autorizaciones

- No hacer deploy, commit, push, merge ni publicación sin autorización expresa
  de Luis.
- No ejecutar migraciones en producción ni modificar datos productivos.
- No tocar Show 3D ni la lógica histórica de Carreras/Rayo.
- No generar videos ni consumir créditos sin mostrar previamente modelo, costo y
  prompt, y recibir aprobación expresa.
- Luis genera manualmente con Seedance 2.5 desde la web de Higgsfield usando
  `Unlimited`; no automatizar la interfaz web.
- No incluir tokens de invitación, credenciales, cookies ni datos sensibles en
  Git, documentación, logs o entregables.
- Los datos, MP3 y scripts de prueba bajo `storage/` son locales e ignorados por
  Git. No deben subirse por FTP.
- En prompts generativos no nombrar franquicias ni personajes protegidos;
  describir únicamente una estética original.

## 3. Decisiones de producto y marketing ya aprobadas

| Oferta pública | Valor técnico actual | Experiencia |
|---|---|---|
| Plan Básico | `service_plan=booth` | Scroll; el invitado avanza con el dedo. |
| Plan Full | `service_plan=full` | Automática; la secuencia avanza sola con videos y narración donde existan assets aprobados. |

Equivalencia transitoria con material antiguo:

- Mágico = Plan Básico.
- Premium = Plan Full.

Precios documentados como referencia, que deben reconfirmarse antes de una
campaña pagada:

- Básico/Mágico: `$69.990 CLP`.
- Full/Premium: `$99.990 CLP`.
- Temática a medida: `+$25.000 CLP`.

Pendiente técnico comercial: el código todavía selecciona Scroll/Automática con
parámetros QA (`hero` y `capitulos`). Aún no existe el gate definitivo que
derive la experiencia desde `service_plan`. No mostrar esos parámetros en
capturas, copies ni tutoriales públicos.

## 4. Estado funcional de las invitaciones

### 4.1 Intro temático genérico

La invitación detecta por convención:

- `public/themes/<slug>/invitation/intro-invitacion-wow-v1.mp4`
- `public/themes/<slug>/invitation/intro-invitacion-wow-v1-poster.jpg`

Si existen, al tocar el sobre se reproduce el video vertical con audio,
progreso, logo oficial superpuesto mediante HTML y botón `Omitir intro`. Al
terminar, omitir o fallar, continúa la invitación. Si no existen, conserva el
flujo anterior.

Ya están integrados y pasan QA:

- `public/themes/carreras/invitation/intro-invitacion-wow-v1.mp4`
- `public/themes/carreras/invitation/intro-invitacion-wow-v1-poster.jpg`
- `public/themes/hielo/invitation/intro-invitacion-wow-v1.mp4`
- `public/themes/hielo/invitation/intro-invitacion-wow-v1-poster.jpg`

Ambos MP4 integrados son H.264 + AAC, 720×1280, 15,072 s. Tamaños observados:

- Carreras: 4.282.419 bytes.
- Hielo: 4.585.021 bytes.

El frontend ya superpone el logo oficial durante el intro con:

`public/invitacion.php` → `brand/cumpleclick-lockup.svg`

Por eso hay que evitar que una exportación para la invitación termine mostrando
dos logos.

### 4.2 Música, mute y Alice

Regla transversal aprobada para todas las temáticas actuales y futuras, tanto
en Scroll como en Automática:

- al salir del intro comienza la música ambiental;
- aparece el control 🎵/🔇;
- Alice inicia si esa invitación tiene narración aprobada;
- el control 🎵/🔇 modifica **únicamente la música ambiental**;
- nunca pausa, silencia ni corta la voz de Alice.

Se corrigió una carrera asíncrona de autoplay en
`public/assets/invitation.js`: el permiso silencioso de música/Alice ahora debe
resolverse antes de iniciar la reproducción real. El recurso se sirve como
`assets/invitation.js?v=5` para invalidar caché.

QA verificado:

- Carreras Scroll: música, mute y Alice funcionan.
- Carreras Automática: música, mute, Alice y hero automático funcionan.
- Al silenciar Carreras, la música queda muteada y Alice continúa hablando.
- Hielo Scroll y Automática: música y mute funcionan.
- Hielo local aún no tiene una narración personalizada de inicio asociada; no
  confundir ausencia de MP3 con un fallo del control.

La narración de Vicente/Carreras usa un MP3 aprobado ya existente, asociado
solo en SQLite local ignorado. No se consumieron créditos para reutilizarlo.

### 4.3 Perfil del protagonista

La arquitectura quedó preparada para uno o varios protagonistas y eventos
futuros, con prioridad visual en las cinco temáticas infantiles actuales:

- Carreras.
- Familia Canina.
- Tropical.
- Reino de Hielo.
- K-Pop.

Componentes principales:

- `database/migrations/008_event_profiles.php`
- migración down correspondiente bajo `database/migrations/`
- `public/lib.event-profiles.php`
- `public/admin/event-profile.php`
- `public/event-profile-media.php`
- `public/assets/event-profile.css`
- `public/assets/event-profile.js`
- integración condicional en `public/invitacion.php`
- acceso desde `public/admin/index.php`

El error anterior `Call to undefined function cb_client_ip()` fue corregido: el
admin usa `cb_request_identity()` en el rate limit. `event-profile.php` pasa
lint y ahora muestra `Ver como invitado` cuando encuentra una invitación pública
aprobada.

No ejecutar la migración en producción sin autorización de Luis.

## 5. Servidor y QA local

Al preparar este handoff no había listener activo en el puerto 8092. Para
levantar el entorno local usando la SQLite ignorada:

```powershell
$env:CC_STORAGE_MODE = 'db'
$env:CC_PDO_DSN = 'sqlite:' + (Resolve-Path 'storage\event-profile-demo\cumpleclick-demo.sqlite')
& 'C:\wamp64\bin\php\php8.2.29\php.exe' -S 127.0.0.1:8092 -t public
```

`config/cumpleclick.local.php` ya apunta al directorio privado local de outputs
y activa `event_profile_enabled`. No copiar esta configuración a producción.

No guardar en este documento las URLs completas porque contienen tokens QA. Las
fuentes locales canónicas son:

- Carreras Scroll/Automática: `storage/event-profile-demo/qa-carreras-audio.cjs`
- Hielo Scroll/Automática: `storage/event-profile-demo/qa-hielo-music.cjs`
- Intros Carreras/Hielo y control sin intro:
  `storage/event-profile-demo/qa-theme-intros.cjs`

Los scripts anteriores son ignorados. Para ejecutar Playwright localmente se ha
usado el runtime Node de Codex y Chrome instalado. Si Claude no puede resolver
Playwright, leer primero esos scripts en vez de inventar otros tokens.

Pruebas ya realizadas en el estado combinado:

- `node --check public/assets/invitation.js`: OK.
- PHP 8.2 y 8.4 lint sobre los endpoints afectados: OK.
- `npm test`: 101/101.
- QA Playwright de Carreras Scroll/Automática: OK.
- QA Playwright de Hielo Scroll/Automática: OK.
- QA de intro con `Omitir intro` y control sin intro: OK.
- `git diff --check`: sin errores; solo advertencias LF/CRLF.
- `graphify update .`: ejecutado al cierre de los cambios.

## 6. Videos de Higgsfield: estado exacto

### 6.1 Carreras y Hielo

Ya fueron normalizados e integrados en `public/themes/.../invitation/` con sus
pósteres. No reemplazarlos sin revisión y autorización de Luis.

### 6.2 Familia Canina, Tropical y K-Pop

Luis generó manualmente tres videos Seedance 2.5, Unlimited, 15 s, 9:16, 720p,
con audio. IDs del historial de Higgsfield:

| Tema | ID Higgsfield | Copia local ignorada | Resultado técnico |
|---|---|---|---|
| Familia Canina | `99c23a70-b75b-4b92-a137-e81908e5f653` | `storage/event-profile-demo/logo-review-20260812/v2.mp4` | H.264 + AAC estéreo, 720×1280, 15,072 s, 16.539.009 bytes |
| Tropical | `3e8ea986-d1f6-4f29-b415-66ebfcd5ac85` | `storage/event-profile-demo/logo-review-20260812/v1.mp4` | H.264 + AAC estéreo, 720×1280, 15,072 s, 25.316.335 bytes |
| K-Pop | `cdc222ad-1a75-4ab3-894e-7c54e8f1be50` | `storage/event-profile-demo/logo-review-20260812/v0.mp4` | H.264 + AAC estéreo, 720×1280, 15,072 s, 21.253.810 bytes |

Problema confirmado: el historial de las tres generaciones registra
`medias: []` y `reference_elements: []`. El PNG oficial no llegó al modelo.
Seedance inventó el wordmark:

- K-Pop: gris/morado muy apagado y sin símbolo oficial.
- Tropical: gris/blanco y sin símbolo.
- Familia Canina: texto claro y, en algunos cuadros, una huella no oficial.

La lámina de evidencia local está en:

`storage/event-profile-demo/logo-review-20260812/top-left-contact.png`

No regenerar solo por este defecto. Luis aprobó corregirlo en postproducción.

## 7. Decisión de postproducción del logo

Logo oficial fuente:

`design/logo/logo-icon-wordmark.png`

SHA-256 observado:

`F9BAA519F06D8D7B74836EE45B684A9818B315C4DC540015B1D9147C29B7F8CA`

Contrato de marca:

- posición: esquina superior izquierda;
- pequeño, sutil, translúcido y estable;
- `Cumple` violeta/morado;
- `Click` rosado/magenta;
- conservar símbolo circular oficial;
- nunca gris, blanco, plateado, monocromático ni adaptado a colores del tema;
- nunca sustituir el símbolo por huella, flor o estrella.

Plan recomendado para los tres videos defectuosos:

1. conservar el video si Luis aprueba la escena;
2. cubrir/eliminar el wordmark generado usando una máscara localizada y
   reconstrucción visual suave de la esquina;
3. producir un **máster limpio para invitación**, sin logo quemado, porque la web
   ya superpone `brand/cumpleclick-lockup.svg` en HTML;
4. producir una **copia promocional** con
   `design/logo/logo-icon-wordmark.png` superpuesto de forma determinista;
5. no aplicar el logo oficial sobre el falso sin limpiarlo primero;
6. validar que no haya doble logo al integrar en la invitación.

No comenzar la integración pública hasta que Luis confirme cuáles escenas
aprueba visualmente.

Destinos posteriores esperados para los másteres limpios aprobados:

- `public/themes/familia-canina/invitation/intro-invitacion-wow-v1.mp4`
- `public/themes/tropical/invitation/intro-invitacion-wow-v1.mp4`
- `public/themes/kpop/invitation/intro-invitacion-wow-v1.mp4`

También crear un póster JPG derivado de cada video. Normalizar para web a H.264,
yuv420p, AAC estéreo, 720×1280, faststart, conservando calidad visual. Medir
duración, audio y peso después del procesamiento.

## 8. Lo que Luis todavía debe grabar

Luis indicó expresamente que todavía no ha grabado la pantalla. Esta es la
dependencia principal antes del montaje promocional.

Formato recomendado:

- vertical 9:16;
- ideal 1080×1920; 720×1280 mínimo;
- viewport móvil tipo iPhone 16 Pro Max es válido;
- ocultar barra de direcciones si es posible;
- activar `No molestar`;
- no mostrar tokens, dirección, teléfono, colegio ni datos reales;
- grabar sin hablar: locución y música se agregan en post;
- dejar aproximadamente un segundo quieto al inicio y final de cada toma;
- grabar clips separados de 4–8 s, no una navegación larga.

### Tomas obligatorias

1. **Entrada Carreras**: sobre, tocar para abrir y mostrar el intro con
   `Omitir intro` visible.
2. **Plan Básico / Carreras Scroll**: hero con Vicente y avance manual por tres
   secciones; que se entienda el gesto vertical.
3. **Plan Full / Carreras Automática**: tocar una vez y dejar avanzar sola la
   secuencia; mostrar brevemente que Alice narra.
4. **Control de música**: mostrar el botón 🎵/🔇. Si se pulsa, Alice debe seguir
   hablando; el control es solo de música.
5. **Perfil del protagonista**: abrir “Conoce a Vicente”, mostrar foto,
   presentación, favoritos/tallas o ideas de regalo sin datos sensibles.
6. **Guardar/compartir**: capturar la zona final y una acción clara.
7. **Entrada Hielo**: sobre + intro temático + hero luminoso.
8. **Hielo Scroll**: una toma corta de desplazamiento manual.
9. **Hielo Automática**: una toma corta de avance autónomo.
10. **Photo Booth**: solo un vistazo de 2–3 s si encaja con el Reel maestro; no
    modificar ni regrabar Show 3D como parte de esta tarea.

### Recomendación de archivos al entregar

```text
01-carreras-sobre-intro.mp4
02-carreras-scroll.mp4
03-carreras-automatica-alice.mp4
04-carreras-mute-solo-musica.mp4
05-perfil-protagonista.mp4
06-guardar-compartir.mp4
07-hielo-sobre-intro.mp4
08-hielo-scroll.mp4
09-hielo-automatica.mp4
10-photobooth-vistazo.mp4
```

Luis puede comprimirlos en ZIP si necesita compartirlos juntos. No usar una
grabación con tokens visibles; si aparecen, recortar o repetir la toma.

## 9. Guion del Reel maestro aprobado como base

Duración objetivo: 30–40 s, 9:16, subtítulos nítidos agregados en post y CTA a
WhatsApp.

| Tiempo | Imagen | Locución/texto |
|---|---|---|
| 0–5 s | Intro IA: sobre abre portal temático | «¿Y si la fiesta comenzara antes del cumpleaños?» |
| 5–10 s | Grabación real del sobre CumpleClick y nombre dinámico | «En CumpleClick, todo empieza con la invitación.» |
| 10–17 s | Plan Básico: Scroll real | «Plan Básico: ellos descubren la historia con el dedo.» |
| 17–24 s | Transición + Plan Full real | «Plan Full: la historia cobra vida y avanza sola.» |
| 24–30 s | Perfil del protagonista | «Y pueden conocer al protagonista antes de llegar.» |
| 30–35 s | Guardar/compartir + vistazo Booth | «Invitación, experiencia y recuerdos en un solo mundo.» |
| 35–40 s | Endcard de marca | «Elige tu experiencia. Reserva tu fecha por WhatsApp.» |

Locución corrida:

> ¿Y si la fiesta comenzara antes del cumpleaños? En CumpleClick, todo empieza
> con una invitación inmersiva creada para su temática. En el Plan Básico, cada
> invitado descubre la historia con el dedo. En el Plan Full, la experiencia
> cobra vida y avanza sola con videos y narración. Además, pueden conocer al
> protagonista, guardar la invitación y compartirla. CumpleClick: la fiesta
> comienza desde el primer toque. Reserva tu fecha por WhatsApp.

Antes de cerrar el montaje, confirmar con Luis:

- si el Reel maestro será de 30–40 s o una versión más corta;
- temática principal del hook: Carreras, Hielo o montaje multitemático;
- CTA/WhatsApp definitivo;
- precios vigentes y si deben aparecer en este Reel o en un carrusel posterior;
- uso de voz Alice, otra voz o locución humana;
- música final y derechos de uso;
- si desea versiones adicionales de 15–20 s para Básico y Full.

## 10. Próximo flujo recomendado para Claude

1. Leer este documento completo.
2. Leer las fuentes canónicas:
   - `docs/CAMPANA-INVITACIONES-BASICO-FULL-2026-08-11.md`
   - `docs/HIGGSFIELD-PROMPTS-CUMPLECLICK-MARCA-DE-AGUA-2026-08-11.md`
   - `docs/INVITACION-MUSICA-Y-NARRACION-ALICE.md`
   - `docs/INVITACION-INMERSIVA.md`
3. No editar ni integrar los tres videos nuevos hasta que Luis comparta sus
   grabaciones y confirme que las escenas están aprobadas.
4. Cuando lleguen las grabaciones, inspeccionar orientación, FPS, audio, peso,
   barras del navegador, datos sensibles y continuidad.
5. Proponer una EDL/storyboard con tiempos exactos y pedir aprobación antes del
   render final.
6. Hacer limpieza de logo y dos salidas por intro: máster de invitación y copia
   promocional.
7. Montar un borrador de baja compresión, mostrarlo a Luis y recibir cambios.
8. Solo tras aprobación, generar exportaciones finales y lista FTP exacta.
9. No subir a producción ni publicar en redes por cuenta propia.

## 11. Estado Git y precauciones

El working tree está sucio y contiene cambios de varias tareas, incluidos
documentación, Graphify, invitación, videos y archivos de usuario. No limpiar,
resetear, revertir ni sobrescribir cambios ajenos.

No asumir que todo lo modificado pertenece únicamente a este handoff. Antes de
versionar, revisar el diff por archivo y separar:

- cambios funcionales de invitación/perfil;
- intros temáticos y pósteres;
- estrategia/prompts/handoff;
- salidas de Graphify;
- archivos locales ignorados bajo `storage/`.

No se hizo commit, push, merge ni deploy durante esta etapa.

## 12. Entrega FTP futura

Todavía no hay autorización de subida. Cuando Luis apruebe la integración de
los tres temas pendientes, preparar una lista con ruta local, destino PROD,
clasificación y orden. Como mínimo, por cada tema aprobado:

- `public/themes/<tema>/invitation/intro-invitacion-wow-v1.mp4` — OBLIGATORIO.
- `public/themes/<tema>/invitation/intro-invitacion-wow-v1-poster.jpg` —
  OBLIGATORIO.

Los archivos de `storage/event-profile-demo/`, la SQLite, los tokens QA, los
scripts temporales, capturas de auditoría y fuentes descargadas de Higgsfield no
deben subirse.

## 13. Criterio de cierre

Esta fase estará lista cuando:

- Luis entregue las tomas de pantalla;
- apruebe las escenas de los tres intros nuevos;
- el logo falso quede eliminado y el logo oficial sea consistente;
- los cinco temas tengan intro normalizado y póster si se autoriza integrarlos;
- el Reel maestro tenga revisión visual, audio, subtítulos, CTA y aprobación;
- no haya tokens ni datos sensibles visibles;
- exista una lista exacta de archivos finales y destinos;
- Luis autorice explícitamente cualquier commit, deploy o publicación.
