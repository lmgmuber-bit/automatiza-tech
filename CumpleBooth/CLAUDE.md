# CumpleClick shared memory

Follow `AGENTS.md`. The canonical entry point is
`docs/HANDOFF-OTRA-PC-2026-08-02.md` — read it first; if an older handoff
contradicts it, that closure wins. Then `docs/CUMPLECLICK-HANDOFF-CODEX.md`;
current architecture/deploy are in `docs/ARQUITECTURA.md` and `docs/DEPLOY.md`.

> The canonical handoff is **not in the local working tree** on this machine
> (the local branch is one commit behind `origin`). Read it with:
> `git show origin/codex/cumpleclick-site-frontend-fixes:CumpleBooth/docs/HANDOFF-OTRA-PC-2026-08-02.md`

Ticket `AT-CUMPLECLICK-001` introduced the independent DB, secure PHP backend,
persistent frames, local Baloo 2, AT branding, private tokenized photos and safe
rollback/retention.

**PROD status (corrected 2026-08-03).** PROD *is* deployed, since 2026-07-27, at
`https://automatizatech.cl/cumpleclick/`. What is **not** deployed is the
2026-08-02 closure — no merge, no FTP. So PROD runs the 2026-07-27 build, and
nothing from that closure may be described as live. The previous line here said
"PROD has not been deployed", which was wrong and is kept noted so the error is
not reintroduced. Never claim something is in PROD without evidence.

**Invitación: portada/música/narración de Alice + multi-tema (2026-08-11,
local, sin deploy).** Ver `docs/INVITACION-MUSICA-Y-NARRACION-ALICE.md`
(canónico para esto). Portada "sobre que se abre" + música de fondo + pie con
logo/link/favicon: hechos por Claude, genéricos por tema. Multi-tema
(`$playlistOrdersByTheme`, hielo con 7 videos y nombre dinámico): hecho por
Codex, ver `docs/CODEX-HANDOFF-INVITACION-HIELO-2026-08-11.md`. Falta SOLO la
narración de audio real (ElevenLabs, voice_id `Xb7hH8MSUJpSbSDYk0k2`) para
ambos temas — código ya resiliente sin ella. Gotcha de entorno local:
`event_profile_enabled` debe ir en `true` (default `false`) o la ficha del
cumpleañero nunca aparece; usar `config/cumpleclick.local.php` (gitignored)
en vez de env vars para no reiniciar el servidor de pruebas.

**Baby shower (2026-08-26, local, sin deploy).** Modalidad nueva: predicciones
en la cabina + tablero privado de los papás. Backend y decisión de arquitectura
en `docs/DECISION-PREDICCIONES-POR-EVENTO-2026-08-25.md` (las predicciones son
del evento por `party_id`, no de la invitación). El estándar de qué es una
temática de baby shower COMPLETA está al final de `docs/TEMATICA-COMPLETA.md`
— la tabla A original no aplica, porque exige seis personajes y un baby shower
no tiene ninguno.

Temáticas `baby-nube` y `baby-safari`: fondos 9:16 propios, `frameBox`
calibrado contra el marco de cada `fondo-sala.jpg`, y `musica-fondo.mp3` +
`musica-juego.mp3` ya presentes (loopeadas sin costura; el fundido final de
cada pista se cruza sobre el principio, si no se oía un bajón cada 90 s). El
test `tests/frontend/themeFlow.test.mjs` verifica los cuatro archivos contra
disco. La nota anterior de esta sección decía que faltaba la música: quedó
corregida el 2026-08-26 y se deja escrito para no reintroducir el dato viejo.

**La invitación pública de baby shower (2026-08-26, local, sin deploy).**
`public/invitacion.php` estaba escrita entera para cumpleaños. Ahora resuelve
el vocabulario UNA vez, junto a los demás datos (`$esBabyShower`,
`$eventoNombre`, `$eventoDeQuien`, `$eventoTitulo`, `$eventoArchivo`) y lo
consumen el hero, la lámina, el calendario, el `.ics`, la tarjeta de WhatsApp
y el `<title>`. `event_type` viene con `child_birthday` por defecto, así que
las invitaciones infantiles no cambian nada.

Las dos temáticas de baby shower están registradas en
`public/data/event-profile-presets.json` con `base_image: fondo-sala.jpg` y un
`text_area` que **es** el `frameBox` calibrado: los datos de la fiesta quedan
escritos dentro del marco decorativo que la foto ya trae, el mismo que en el
kiosco encuadra la foto del invitado. Sin ese preset caen a `theme_fallback`,
que no define `base_image`, y la página muestra dos veces el mismo banner —
una degradación silenciosa, por eso hay un test que la bloquea.

Hoy no tienen video, y aunque lo tengan no van a imitar el recorrido de
personajes de las temáticas infantiles: no hay personajes a quienes hacer
saludar. La invitación se sostiene sola con el bloque que cierra
`assets/invitation.css` ("la espera"): la foto respira, `.inv-sparks` —doce `<span>`
que existían en el HTML sin una sola regla de CSS— se convierte en estrellas o
luciérnagas según el tema, y hay **dos** contadores. Todo cuelga de
`[data-theme]` y respeta `prefers-reduced-motion`.

Los dos contadores importan y no son un capricho de diseño: el primero decía
"faltan 39 días para conocer a Valentina", que es falso — en 39 días es el
baby shower, no el nacimiento. Ahora el de la izquierda cuenta los días hasta
la fiesta (lo que sí se sabe) y el de la derecha ocupa el mismo lugar donde
iría un número y muestra `¿?`, porque esa cifra no la sabe nadie. El signo va
del mismo tamaño y con la misma tipografía que el número: ahí no es un adorno,
es el dato, y achicarlo lo convertiría en una nota al pie.

**El nombre y el sexo del bebé son opcionales, las cuatro combinaciones.** Un
baby shower puede no saber el nombre, el sexo, los dos o ninguno — hay familias
que hacen la fiesta justamente para revelar uno. No hizo falta columna nueva:
los dos ya se expresan vacíos. Sí hizo falta sacar `birthday_person_name` de
`cb_invitation_mandatory_missing()` cuando es baby shower, porque era obligatorio
y esas invitaciones no se podían publicar; fecha, hora y dirección siguen
siéndolo, que son datos de la fiesta y no del bebé. El `<h1>` cae a "Nuestra
bebé"/"Nuestro bebé", el segundo contador a "conocer al bebé", y el capítulo 4
del recorrido cambia de "El nombre de X ya se dice en voz alta" a "Todavía no
tiene nombre, y ya tiene quien lo espere" — el video es el mismo, cambia solo la
narración, y para eso `$playlistOrdersByTheme` acepta un array
`['caption','narracion']` además del texto suelto de siempre.

El recorrido con videos: rutas, arco, prompts y verificación en
`docs/VIDEOS-INVITACION-BABY-SHOWER.md`. Los MP4 ya existen — ver más abajo.
Los capítulos NO cuentan la sala preparándose —eso es la decoración— sino la
espera, lo que significa traer un hijo al mundo y el nacimiento que se está
esperando. El arco es el mismo en las dos temáticas porque lo que se cuenta
es del bebé; el decorado es lo único que cambia.
El hero ya lee `invitation/invitation-scroll-v1.mp4` y `-motion-v1.mp4` de
cualquier tema sin lista blanca, y cada capítulo pasa por `is_file()`, así
que los clips entran de a uno sin tocar código. No llevan logo AT —son la
invitación de un cliente, no una pieza de AT— ni superficies escribibles en
cuadro, que es lo que hace que el generativo estampe texto inventado.

**La lista de regalos con reserva (2026-08-27).** "Para cuando llegue" —el
nombre lo decidió Luis— vive en `lib.gifts.php`, `gift-api.php`, la sección de
`invitacion.php` y `regalos-papas.php`. **El invitado ve que un regalo está
tomado, nunca por quién**; el nombre solo lo ven los papás y el admin. Esto
CORRIGE la decisión 1 del plan del 2026-08-25 y el porqué está escrito ahí y en
la cabecera de `lib.gifts.php`: no hay cuentas, así que `claimed_name` es texto
libre — publicarlo lo vuelve un dato imposible de verificar pero socialmente
creído, y el enlace es público. La reserva usa escritura condicional, no
leer-y-después-escribir.

**Dos modos, por invitación** (`gift_mode`, migración 011, default `list`):
`list` es el de siempre —los papás cargan y los invitados eligen— y `open` no
tiene lista: cada invitado anota lo que va a llevar y todos lo ven. Pedir una
lista se le hace incómodo a muchas familias, pero igual quieren que nadie
repita. Los dos modos usan la misma tabla y el mismo mecanismo de reserva; lo
que cambia es quién escribe y cómo se presenta, por eso es una columna y no un
esquema aparte. En `open` la sección aparece **aunque esté vacía** —vacía es el
estado normal al principio— y el badge dice "Ya lo llevan" en vez de "Ya lo
tomaron", porque nadie tomó nada de ninguna lista. Sin la columna todo cae a
`list`, que es el comportamiento anterior. El interruptor está arriba de todo en
`regalos-papas.php`; cambiar de modo no borra nada.

**El cierre del recorrido lleva género (2026-08-28).** Luis pidió que Alice
diga que sus papás y seres queridos esperan al bebé con ansias. Esa frase le
habla al INVITADO sobre el bebé, así que el pronombre lleva género y el
capítulo 6 pasó de un MP3 a tres por temática: `nina` ("la esperamos"), `nino`
("lo esperamos") y `neutro`, que reordena la frase a "esperamos con ansias su
llegada" para no necesitar ninguno de los dos — es el caso de las familias que
hacen la fiesta justamente para revelar el sexo. **El video sigue siendo uno
solo por temática**: cambia la voz, no la imagen. `$sufijoSexoBebe` se resuelve
una vez y lo usan el capítulo 6 y el respaldo compartido.
`tests/frontend/babyShowerVoice.test.mjs` lo bloquea (verificado en rojo).
Los tres MP3 con el texto viejo se borraron; siguen en el historial de git.
`narracion-final-baby-shower.mp3` no se tocó: ese le habla al invitado sobre
guardar el enlace, no sobre el bebé.

**El recorrido y la voz (2026-08-27).** Los 12 capítulos existen (seedance1_5,
8s, 720x1280, sin audio) y los MP3 de Alice también. Ningún clip tiene
personas y ninguna narración pronuncia el nombre del bebé — el nombre es
dinámico y vive en el HTML. Los prompts y el arco, en
`docs/VIDEOS-INVITACION-BABY-SHOWER.md`.

**El hero sigue siendo la foto que respira, a propósito.** Se generaron cuatro
clips de hero y NO se instalaron: son atmósfera genérica y el `fondo-banner.jpg`
es la decoración real que la familia va a tener. Quedan fuera del repo, en el
scratchpad, por si Luis prefiere movimiento.

**Las dos pantallas de los papás, alcanzables (2026-08-27).** El mismo token
`parents` abre el tablero de predicciones y la lista de regalos, pero el admin
entregaba sólo la primera URL y a `regalos-papas.php` no la enlazaba nada: era
accesible únicamente adivinando la dirección, así que la lista de regalos
estaba inservible sin que nada fallara. Ahora el admin entrega las dos URLs al
emitir el token y cada pantalla enlaza a la otra, así que perder un enlace no
cuesta nada y revocar sigue cerrando las dos a la vez.
`tests/frontend/parentsAccess.test.mjs` bloquea la regresión — se verificó que
se pone en rojo al quitar el enlace.

**El solape del último capítulo (2026-08-27).** La pastilla "Ver invitación"
empieza en `bottom:24px` y mide 61px medidos en el navegador; el texto del
capítulo terminaba en 76px, o sea 9px montados, justo en el último clip que es
cuando la pastilla aparece. Ya pasaba así en producción con Hielo y Carreras.
El texto sube **sólo** mientras la pastilla está visible (`:has()`), no
siempre: en los capítulos anteriores no hay nada abajo con qué chocar y moverlo
cambiaría un encuadre ya aprobado. Sin `:has()` la página queda como está hoy
en producción. Las dos medidas salen de `--inv-hint-bottom` /
`--inv-hint-alto`. Medido en las dos variantes: 9px de solape → 13px de aire.

**Revisión local (2026-08-27).** `scripts/seed-revision-local.php` publica una
invitación por temática —las ocho— con lámina compuesta sobre el
`fondo-banner.jpg` real, y para los baby shower siembra además regalos,
predicciones y el token de los papás. Aborta si `public_base_url` no es
localhost. Es idempotente salvo los tokens, que se reemiten. Ojo:
`carreras/fondo-banner.jpg` **es un PNG** con nombre `.jpg` — el navegador lo
olfatea y no se nota, GD no, por eso el script despacha por contenido.

**Héroes: saludos y despedida generados (2026-09-01), sigue sin recorrido de
invitación.** `public/themes/heroes/` ya tiene los 6 videos que faltaban —
`saludo-hierro.mp4`, `saludo-gigante.mp4`, `saludo-capitan.mp4`,
`saludo-trueno.mp4`, `saludo-pantera.mp4` y `despedida-heroes.mp4` — generados
con Higgsfield (kling3_0, imagen-a-video) y desplegados a PROD. `saludo-arana.mp4`
(Spider-Man) sigue SIN generar: la imagen fuente `arana.jpg` rebota por filtro
de contenido nsfw en dos modelos distintos (kling3_0 y seedance_2_5) pese a
prompts family-friendly explícitos; probablemente el traje ajustado + pose de
acción fotorrealista disparan el filtro. Aparece igual celebrando en
`despedida-heroes.mp4` porque esa escena sale de `fondo-banner.jpg`, que ya
trae a los 6 personajes. El `photoSession` de Héroes en `themes.json` sigue
referenciando `comic-cobra-vida.mp4`/`comic-cobra-vida-poster.jpg`/
`comic-teaser.jpg`, que no existen — quedó pendiente, prioridad más baja.
Héroes **sigue sin figurar en `$playlistOrdersByTheme`**: no tiene recorrido de
capítulos en la invitación (eso es un trabajo aparte, más grande, no solo los
saludos del kiosco).

Las migraciones `010_baby_shower_predictions.php` y `011_gift_mode.php` **no
están aplicadas en producción**. Sí lo están en local desde el 2026-08-27
(junto con la 008 y la 009, que también faltaban ahí). Son aditivas e
idempotentes, pero producción requiere autorización explícita de Luis.

**Bebé entre Rosas — tercera temática de baby shower, COMPLETA (2026-08-29).**
Pedido de Luis. Slug `baby-rosas`, rosa polvo y crema — no rosa chicle: las
otras dos están en registro sobrio y una que se salga parece de otro producto.
Registrada en `themes.json` y en presets, con `frameBox` calibrado de verdad.
Sus chispas en la invitación son **pétalos que caen**, no polvo de estrellas ni
luciérnagas. Todo en `docs/TEMATICA-BABY-ROSAS.md`.

**La lección del `frameBox`, que vale para la próxima temática.** El primer
`fondo-sala` se pidió con marco ovalado colgado alto, copiando el montaje de
Safari que evita que la decoración se cruce por delante. Salió precioso y
cumplía las dos reglas, pero un óvalo alto deja poco espacio para un cuadrado:
el `frameBox` daba **352 px** contra los 714 de Safari, y la cara del invitado
habría salido chica. Se pidió de nuevo con marco **grande, rectangular y
centrado** y quedó en **552 px**. Al pedir el fondo hay que decir el tamaño y la
forma del marco, no sólo que esté vacío y despejado. Costo total: 6 créditos de
Higgsfield (3 imágenes), 2 de ellos del descarte.

**Cerrada del todo el 2026-08-29:** música propia (dos pistas `sonilo_music` de
90 s, loopeadas cruzando los últimos 4 s sobre el principio, quedan en 86 s), el
recorrido de 6 videos y la voz de Alice. De las narraciones **solo se grabaron
tres** —los capítulos 2, 3 y 5, que hablan desde el mundo de cada temática—; el
1, los dos del nombre y los tres del cierre son idénticos entre temáticas y se
copiaron de `baby-nube` sin volver a pagarlos. La despedida se revisó cuadro a
cuadro y no estampó ni una letra: el capítulo 6 evita la pared, el mismo remedio
que arregló Safari.

Dos cosas que conviene no olvidar. **Un clip falló al enviarse y hubo que
reenviarlo**: seis enviados no son seis generados, hay que mirar el estado de
cada uno. Y los dos MP3 de música **pesan exactamente lo mismo, y no es un
error** — misma duración con bitrate constante da el mismo tamaño; se comprobó
por checksum que el contenido difiere, porque el tamaño idéntico es justo lo que
haría pensar que uno sobrescribió al otro.

**El álbum le decía "fiesta" a un baby shower (2026-08-29).** El cierre decía
"¡Gracias por venir a la fiesta de Amanda!" — Amanda no nace todavía, no hubo
ninguna fiesta suya, y la frase suena a error de quien armó el álbum. Eran
**cinco** textos con el mismo defecto (cierre, estado vacío, alt de la imagen,
mensaje de no-publicado y título de la pestaña) y arreglar solo el que se ve
habría dejado el bug vivo en los otros cuatro. Se resuelve como ya lo hacía el
kiosco: `src/album/evento.js` decide el vocabulario UNA vez y lo consumen todas
las pantallas. `album-api.php` publica `event.type` (y `eventType` en la
respuesta con PIN), sin lo cual el álbum no puede saberlo.

**"Invitación de Tomás" también era falso.** Tomás no invita a nadie. Ahora
`$tituloInvitacion` se resuelve una vez y lo usan el `<title>`, el pie de la
lámina, el `alt` y el título que se comparte: "Invitación del baby shower de
Tomás". En cumpleaños sigue diciendo "Invitación de Isidora", que sí es
correcto porque Isidora invita.

**La dirección tocaba la moldura del marco.** "Av. Los Leones 455, Providencia,
Santiago" llenaba el renglón de lado a lado. Se le puso `max-width: 88%` y
`text-wrap: balance`, sin achicar la letra (en un teléfono ya está al límite).
Medido: 69px de aire a cada lado en 1024px, 25px y dos líneas en 375px.

**Los demos de baby shower terminados** (`scripts/seed-demo-baby-shower.php`)
llevan 9 invitados con mensaje cada uno. El número no es decorativo: una foto
CON mensaje se lleva su propia página y sin mensaje se agrupan de a cuatro en un
mosaico, así que 4 fotos daban un álbum de 4 páginas. Y la portada se lleva una
foto que no entra a las páginas de adentro, por eso 8 daban 9 y hacen falta 9
para llegar a 10. Las fotos llevan la marca de agua real de CumpleClick con la
misma geometría del kiosco (`drawBrandWatermark`: W*0.085, abajo a la izquierda,
alfa 0.42); el isotipo es SVG y GD no lee SVG, así que se pasa un PNG
rasterizado del MISMO archivo por `CC_DEMO_LOGO` — no se recrea el logo.

**La fuente real en el compositor del demo (2026-08-29).** El agradecimiento que
sale impreso en la foto se dibujaba con `imagestring()`, la fuente de mapa de
bits que GD trae adentro: tipografía de terminal, un solo tamaño y sin tildes
("Emilia Nunez"). Ahora se dibuja con Baloo 2, la misma del kiosco.

FreeType no lee WOFF y `@fontsource/baloo-2` no distribuye TTF, así que
`scripts/lib-woff.php` reconstruye el sfnt desde el `.woff` (WOFF 1.0 es el mismo
sfnt con cada tabla en zlib; se usa el `.woff` y no el `.woff2` porque WOFF2
además transforma `glyf`/`loca` y deshacer eso es un decodificador entero). El
TTF derivado se cachea en `storage/fuentes/` — no se versiona un binario de
fuente, y así no puede quedar desincronizado del que usa el kiosco.

`tests/backend/fuente-baloo.php` (39 checks) cuida la degradación silenciosa de
verdad: una conversión mal hecha puede dar un archivo que FreeType ACEPTA y que
igual dibuja cuadraditos `.notdef`, sin devolver error. Por eso rasteriza y
compara píxeles, con control positivo incluido (un carácter fuera del subset
`latin` TIENE que salir como `.notdef`, o la comparación ya no prueba nada).

**El demo ahora usa la geometría del kiosco, no una propia.** `demo_componer()`
estiraba la foto sobre todo el `frameBox`; el kiosco inscribe un cuadrado y lo
mete un 8,5% por lado (`FRAME_PHOTO_INSET_RATIO` en `src/frameGeometry.js`) para
no taparle el borde pintado al marco. El demo mostraba la cara donde el kiosco
no la pone, y encima tapaba defectos del fondo que en la fiesta sí se ven.

**`frameBox` de baby-rosas, recalibrado.** Estaba medido contra el compositor del
demo —que no aplicaba el inset—, así que validaba un número equivocado: con la
geometría real la foto quedaba chica y descentrada en el paspartú, y la línea del
agradecimiento caía ENCIMA de la moldura de abajo. Nuevo valor
`{x:0.1789, y:0.0907, w:0.6192, h:0.5265}`, elegido para que las dos cosas que se
ven queden bien: la foto (555px) centrada en la apertura del marco —medida en
240..815 x, 274..1085 y— y la línea del texto en y=1220, justo bajo el marco.

Ojo con lo que `frameBox` NO es: no es el rectángulo del marco ni es lo mismo que
`text_area` de `event-profile-presets.json`. `text_area` ubica el texto de la
invitación sobre `fondo-sala.jpg`; `frameBox` es el ancla desde donde el kiosco
calcula la foto y la línea del agradecimiento. Coincidían por copia, y un test
llegó a exigir que fueran iguales, lo que obligaba a descalibrar uno para
arreglar el otro. Lo que sí es invariante —y lo que el test verifica ahora— es
que los dos queden centrados en el mismo punto.

---

## Sesión 2026-08-30/31 — cumpleclick.com en PROD: rendimiento, menú, leads y marca

Todo lo de abajo está **desplegado y verificado por hash** contra el repositorio
(32 archivos, 0 diferencias). PROD es `https://cumpleclick.com`.

### Cómo se despliega (importante antes de tocar nada)

La cuenta FTP está **enjaulada en `public_html`** y no alcanza `/domains/` ni
las carpetas hermanas. Eso es bueno —no puede dañar los otros dominios de la
cuenta— pero significa dos cosas:

- `database/migrations/` y `scripts/` quedan **fuera de alcance**. La migración
  012 se aplicó con un ejecutor de un solo uso, protegido por token y que se
  autoborra, subido a `public_html` y verificado por FTP que desapareció. El
  archivo de la migración **no está en el servidor**, solo sus efectos: es
  idempotente, así que un `migrate.php` futuro no hará nada, pero conviene
  subirlo por el File Manager.
- La configuración real vive en
  `/home/u402745362/domains/cumpleclick.com/cumpleclick-config.php`, fuera del
  webroot. Dentro de `public_html/config/cumpleclick.local.php` hay un **puente**
  de una línea que apunta ahí. Editar el puente no sirve de nada.

`public_html/app/` es el árbol `public/`; `public_html/` es `sitio/`.

### La landing dejó de ser estática

`sitio/index.html` pasó a `sitio/index.php`. El WhatsApp y las redes salen de
`public/data/marca.json` —la MISMA fuente que el cierre del Álbum Recuerdo y el
cartel QR— y se editan en **Admin → Marca** (`public/admin/marca.php`).

Dos cosas que no se pueden perder:

- El `.htaccess` declara `DirectoryIndex index.php`. Si `index.html` reaparece,
  el servidor puede preferirlo y la página vuelve a servirse estática: todo se
  ve igual y lo que se edite en el admin deja de tener efecto, sin que falle
  nada. Hay un test que lo bloquea.
- El `.gitignore` de la raíz tiene `index.php` **sin barra inicial** —regla del
  bloque de WordPress— y un patrón así hace match en CUALQUIER carpeta. Se
  estaba tragando la portada: existía en disco y en producción, y en el repo no.
  Hay una negación y un test que corre `git check-ignore`.

### Las revelaciones NO usan IntersectionObserver, y es a propósito

Se probaron tres mecanismos:

1. Un ScrollTrigger por elemento (60 en la página). Funcionaba, pero medían
   layout y costaban ~310 ms de hilo bloqueado en el arranque.
2. `ScrollTrigger.batch()`. **No sirve**: `getAll()` seguía devolviendo 60. Sólo
   coordina los callbacks, no reduce los disparadores.
3. `IntersectionObserver`. Barato, pero el navegador entrega la intersección
   cuando quiere: medido en producción con instrumentación, el aviso del
   contenedor de "Elige tu mundo" llegó **11 segundos** después de que la
   sección ya se veía. Cambiar el margen no lo arregló.

Lo que quedó es una cola movida por el scroll, revisada como mucho una vez por
cuadro, que se desengancha sola cuando se vacía. Más una garantía por lote: al
terminar la duración de la animación, lo que siga invisible se muestra.

`.mundo` se revela **por contenedor**, no por elemento: en pantalla angosta el
carrusel corre en horizontal y varias tarjetas quedan fuera por el costado.

### El punto de quiebre del menú depende del ancho del logo

Está en **1120px** y sale de medir: marca 147 + nav 640 + botón 210 = 997, más
24 de separaciones y 56 de relleno. Estuvo en 1024 y se rompió al traer el
lockup nuevo, que pasó de 81px a 147px. **Si el lockup vuelve a cambiar de
tamaño, hay que volver a medir.**

### Leads: correo y pantalla

Los leads se guardaban en `cc_leads` desde la migración 006 pero no había
pantalla ni correo. Ahora:

- `public/admin/leads.php` — Admin → Solicitudes, con contador de nuevas.
- `public/lib.mail.php` — SMTP hablado a mano sobre socket, sin dependencias.
- `public/lib.mail-templates.php` — los dos correos.
- Migración **012**: `confirmation_sent_at`, `notified_at`, `mail_error`.

El envío **falla en silencio a propósito**: el lead ya está guardado y no tiene
sentido mostrarle un error al visitante por un problema nuestro. Por eso existen
esas tres columnas y por eso el admin muestra el estado del correo al lado de
cada solicitud: sin eso, un buzón mal configurado dejaría a todos sin
confirmación y no se enteraría nadie.

Se envía **después** de responderle al visitante (`fastcgi_finish_request`).

Anti-spam, y no son adornos: `multipart/alternative` con texto plano de verdad,
el remitente del sobre igual al `From` (alineación SPF), y **una sola imagen**
—el logo— con `alt`, sobre un encabezado cuyo color es CSS y cuyo nombre es
texto: con las imágenes bloqueadas se ve la marca, no un hueco.

SPF, DKIM y DMARC están publicados. DMARC en `p=none`; subir a `quarantine`
recién cuando DKIM lleve semanas estable.

### Cierre de la sesión: el repositorio y producción por fin coinciden

Durante meses producción iba adelante y git no lo reflejaba. Se cerró con el
**PR #12** (100 commits, 1514 archivos), mergeado a `main` el 2026-08-31, y con
la rama foto **`prod-sync-2026-08-31`**.

**El flujo de trabajo ahora es canónico y vive en `docs/DEPLOY.md`**, en la
primera sección: "Git no despliega. El FTP sí". Léela antes de tocar nada —
resume que mergear a `main` NO publica, que se despliega y se verifica ANTES de
mergear, y qué archivos del servidor están fuera de git a propósito.

Antes de subir se revisó el contenido de las 374.952 líneas del diff buscando
credenciales, porque el repositorio es **público** y el CI de GitHub está caído
por facturación. 20 candidatos, todos benignos. Los tres que parecían riesgosos
se leyeron uno por uno: el `$TOKEN` de `scripts/web/_at-*.php` es el marcador de
fábrica y el script se niega a correr con él; `data-inv-token` es dinámico; el
del tablero es un nombre de clave de `localStorage`.

### Precios: el baby shower ya no es "cuéntanos y te pasamos el valor"

`$59.990` de lista, **`$29.995`** con el 50% de lanzamiento. Aparece en dos
lugares: tarjeta propia en Precios y el precio concreto en la sección de baby
shower.

La tarjeta se marca como **otro producto**, no como un tercer nivel: etiqueta
violeta en vez del fucsia de "Más elegido", y borde suave. Sin esa distinción,
un baby shower más barato que el Mágico se lee como "el plan básico". La grilla
pasó de dos a tres columnas en escritorio (`max-width` 880 → 1120).

### Los correos llevan logo, y el encabezado no depende de él

Verificado con un envío real: **llegan a bandeja de entrada**, no a spam.

La primera versión no traía ninguna imagen, por miedo a que los clientes de
correo las bloquearan. Era pasarse de prudente: lo que penalizan los filtros no
es UNA imagen, es un correo que ES una imagen y no tiene texto que leer.

Ahora hay logo, pero el encabezado aguanta sin él: el degradé de marca es
`background-color` sólido MÁS `background-image` (Outlook de escritorio ignora
el segundo y muestra violeta plano, que es correcto), y el nombre "CumpleClick"
va como TEXTO al lado del logo. Con las imágenes bloqueadas se ve la banda de
color y el nombre, no un rectángulo vacío.

El logo se recorta del render maestro (`design/logo/`) a 264px y se muestra a
56. El test cambió de premisa con el código: exigía CERO imágenes y ahora exige
que el correo no DEPENDA de ellas — una como máximo, con `alt`, con más de 300
caracteres de texto alrededor y sin recursos remotos fuera de cumpleclick.com.

### Errores de redacción encontrados leyendo, no escaneando

Los verificadores automáticos de signos y tildes no sirvieron (ver la sección de
errores). Leyendo el texto aparecieron cuatro cosas reales, ya corregidas:

- **`¿Quieres conocer a ?`** — la invitación pegaba `"a " + nombre + "?"`, y en
  baby shower el nombre es OPCIONAL a propósito. Sin nombre quedaba el signo
  colgando. No se arregla concatenando: la frase pide "al bebé"/"a la bebé", y
  esa contracción no sale de pegar "a " con nada. Hay un test que lo bloquea.
- **La nota de los regalos** decía lo contrario de lo que pasa.
- **El correo en texto plano iba sin tildes** — justo la versión que se ve en un
  reloj o con el HTML bloqueado.
- **Las cabeceras no declaraban `charset`.** Los .html estáticos se servían como
  `text/html` a secas; traen su `<meta>`, pero el navegador interpreta bytes
  antes de llegar a él. Se agregó `AddDefaultCharset UTF-8`.

El crédito a AutomatizaTech pasó a ser enlace en el pie de la landing y de los
correos. **En el kiosco NO**, a propósito: se muestra en la tablet durante la
fiesta y un enlace deja que un niño toque y se salga a mitad del evento.

### Errores de esta sesión, para no repetirlos

- **Dije que la pantalla de marca "nunca se construyó"** sin buscar en otras
  ramas. Existía en `claude/invitacion-url-plan-y-3-temas`, sin fusionar. Antes
  de afirmar que algo no existe: `git log --all`, `git branch --contains`.
- **Una verificación que tapaba el bug**: barrí la página entera y la red de
  seguridad fue rescatando sección por sección, así que el observador roto
  parecía funcionar. Cuando hay un mecanismo de respaldo, hay que verificar el
  mecanismo principal directamente, no el resultado final.
- **`Array.prototype.slice.call(map.keys())` devuelve `[]`** —un iterador no
  tiene `length`— sin lanzar ningún error. Usar `Array.from`.
- **Dos verificadores automáticos inservibles**: uno de signos `¿` que leía
  código PHP (`?>` tiene interrogación) y daba 1056 falsos positivos, y uno de
  tildes que marcaba "que", "el", "tu", "si". Probar el verificador contra un
  caso bueno y uno malo conocidos ANTES de creerle.
- **Un test cuya regex castigaba el uso correcto**: contaba definiciones de
  `$sufijoSexoBebe` con `/\$sufijoSexoBebe\s*=/`, que también matchea `===`.
- **Descarga FTP vacía ≠ archivo ausente**: 30 conexiones seguidas hacen que el
  servidor corte, y 10 archivos parecían faltar. Con pausas aparecieron los 10.
- **Decisión de diseño demasiado prudente**: dejé el logo fuera de los correos
  por miedo al bloqueo de imágenes. Lo que penalizan los filtros no es UNA
  imagen, es un correo que ES una imagen.
