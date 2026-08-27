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

**El recorrido y la voz (2026-08-27).** Los 12 capítulos existen (seedance1_5,
8s, 720x1280, sin audio) y los 16 MP3 de Alice también. Ningún clip tiene
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

**Héroes no tiene recorrido.** No figura en `$playlistOrdersByTheme` y en disco
sólo tiene `revelacion-heroes.mp4`: ni saludos ni despedida. Su invitación se
muestra sin capítulos. Es anterior a este trabajo y sigue abierto.

Las migraciones `010_baby_shower_predictions.php` y `011_gift_mode.php` **no
están aplicadas en producción**. Sí lo están en local desde el 2026-08-27
(junto con la 008 y la 009, que también faltaban ahí). Son aditivas e
idempotentes, pero producción requiere autorización explícita de Luis.
