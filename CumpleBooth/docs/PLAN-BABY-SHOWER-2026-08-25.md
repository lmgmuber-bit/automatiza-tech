# Plan — Modalidad Baby Shower

Estado: **propuesta, sin aprobar y sin código.** Rama `claude/baby-shower-plan`.
Fecha: 2026-08-25.

> Este documento se reescribió varias veces el mismo día, siguiendo cómo Luis
> fue acotando el encargo. La primera versión asumía dos temáticas infantiles y
> 28 videos. La segunda lo dejó en sólo invitación y álbum. Esta es la buena:
> baby shower lleva **invitación, cabina de fotos con juegos propios, recuerdito
> y álbum**, en **un solo plan**, a diferencia de las infantiles que tienen dos.
>
> Luis además tomó las recomendaciones de la sección 9.

---

## 1. Resumen para decidir en dos minutos

**Qué se agrega.** Una segunda modalidad de evento, `baby_shower`, además de las
fiestas infantiles. Lleva cuatro piezas:

| Pieza | Qué es |
|---|---|
| **La invitación** | Con la lista de regalos, que es lo que la hace vendible |
| **La cabina de fotos** | El recuerdo con la embarazada o los papás, con juegos propios de baby shower |
| **El recuerdito** | El equivalente del diploma de las infantiles |
| **El Álbum Recuerdo** | La revista con todas las fotos de la tarde |

**Un solo plan**, con todo incluido. Las infantiles se venden en dos niveles
(`booth` y `full`); baby shower en uno.

**Lo único realmente nuevo.** La **lista de regalos con reserva**: los futuros
papás la arman desde un enlace propio, cada invitado ve qué sigue disponible,
toma uno y queda a su nombre, y si quiere regalar algo que no está lo escribe y
entra ya tomado. Es backend con concurrencia real. Todo lo demás ya existe en el
producto y se reutiliza.

**Los juegos salen casi gratis.** El kiosco tiene un solo motor de juego básico,
`copos` (atrapar cosas que caen), y cambiarle los copos por chupetes es un
cambio de imágenes, no de código. Detalle en la 4.5.

**Lo que ya está hecho y apagado.** `cc_parties.event_type` está desde la
migración 008 con default `child_birthday`, y `baby_shower` ya figura declarado
en `event-profile-presets.json`. Falta **una** migración, la 010.

**Lo que cuesta.** Cada temática de baby shower necesita bastante menos que una
infantil: sin recortes de personajes, sin videos de saludo, sin narración de
personaje y sin el Show 3D. La cuenta está en la sección 6.

**Lo que propongo.** Empezar por la lista de regalos sobre una temática que ya
existe. Al terminar la Fase 2 la modalidad se puede vender y mostrar, sin haber
generado un solo asset nuevo.

**Lo que NO propongo.** Tocar el Show 3D ni la lógica aprobada de Carreras y
Reino de Hielo.

---

## 2. Lo que verifiqué en el código

Todo lo de esta sección está leído, no supuesto. Lo que no verifiqué está en la
sección 11.

| Hecho | Dónde |
|---|---|
| `cc_parties.event_type` existe, `VARCHAR(40)`, default `'child_birthday'` | `database/migrations/008_event_profiles.php:50` |
| `baby_shower` ya está declarado como `"status": "architecture_only"` | `public/data/event-profile-presets.json` |
| Su layout declarado es `event-profile-future-v1`, y **no tiene campos definidos**: sólo `label`, `title_suggestions` y `cta_suggestions` | mismo archivo |
| Sus sugerencias de título ya dicen **"Conoce al bebé"** | mismo archivo |
| `cc_invitations` **no** tiene `event_type`. Sólo lo tienen `cc_parties` (008) y `cc_leads` (006) | `database/migrations/` |
| La última migración es la **009**, así que la nueva es la **010** | `database/migrations/` |
| El plan de servicio sale de `cc_parties.service_plan`, whitelist `['booth','full']`, y **cae a `booth`** cuando no hay fiesta | `public/lib.invitations.php:747` |
| El género acepta sólo `['m','f']` y convierte cualquier otro valor a nulo **en silencio** | `public/lib.invitations.php:135` y `public/admin/invitations.php:266` (duplicado) |
| El álbum consume del tema **sólo** los 9 tokens de color más `assets.banner` y `assets.grupo` | `src/album/pages.js:31` y `:76` |
| La invitación cae sola a modo `auto` cuando la temática no trae carpeta `chapters` | `public/invitacion.php` |
| Ya existe el patrón de escritura pública con token, límites y moderación | `public/subir.php`, `cb_album_intake_open()`, `cb_album_limits()`, `cc_rate_limits` |
| Ya existe emisión y revocación de tokens por rol | `cb_album_issue_token($albumId, 'intake'\|'view', ...)`, tabla `cc_event_album_tokens` |
| Ya existe generación de tokens opacos | `cb_opaque_token()` |

**La consecuencia práctica:** la modalidad no necesita arquitectura nueva. Casi
todo lo que hace falta ya está resuelto en otra parte del producto y hay que
reusarlo, no reinventarlo.

---

## 3. Decisión de arquitectura

`event_type` es la modalidad, y **sube de `cc_parties` a `cc_invitations`**.

El motivo es concreto: una invitación puede existir sin fiesta. Hoy
`cb_invitation_service_plan()` lo reconoce y cae a `'booth'` cuando no encuentra
la fiesta. Si la modalidad viviera sólo en `cc_parties`, una invitación de baby
shower sin fiesta se renderizaría como cumpleaños infantil. Por eso la 010
agrega la columna donde de verdad se necesita.

Todo lo demás se deriva de ahí: qué textos usa la invitación, qué preset de
ficha carga, y si muestra o no la lista de regalos.

---

## 4. Plan de backend

### 4.1 Migración 010

Una sola migración, con su `.down.php`, siguiendo la convención de las
anteriores (chequeo de existencia antes de alterar, para que correrla dos veces
no rompa nada).

**Agrega:**

1. `cc_invitations.event_type VARCHAR(40) NOT NULL DEFAULT 'child_birthday'`.
   El default hace que las invitaciones existentes sigan comportándose igual.

2. Tabla `cc_gift_items` — los regalos de la lista.

| Columna | Tipo | Para qué |
|---|---|---|
| `id` | PK | |
| `invitation_id` | FK | La lista cuelga de la invitación, no de la fiesta, por lo de la sección 3 |
| `position` | INT | Orden que eligen los papás |
| `title` | VARCHAR(120) | "Coche", "Pañales talla RN" |
| `notes` | VARCHAR(400) | Condición o detalle: talla, color, dónde comprarlo |
| `added_by` | ENUM `parents` \| `guest` | Los de invitado se ven y se moderan distinto |
| `status` | ENUM `available` \| `taken` \| `hidden` | `hidden` es para moderación, no borrado |
| `claimed_name` | VARCHAR(80) NULL | Quién lo tomó |
| `claimed_token` | CHAR(32) NULL | Le permite deshacer sin tener cuenta |
| `claimed_at` | DATETIME NULL | |
| `moderation_status` | ENUM `approved` \| `pending` \| `rejected` | Sólo aplica a los de invitado |
| `created_at` / `updated_at` | DATETIME | |

3. Un rol nuevo de token para los papás. **Recomendación: reutilizar
   `cc_event_album_tokens` no sirve**, porque esa tabla cuelga de `album_id` y
   acá el dueño es la invitación. Propongo una tabla propia
   `cc_invitation_tokens` con la misma forma y el mismo ciclo de vida (emitir,
   revocar, registrar quién lo emitió), copiando el patrón de
   `cb_album_issue_token()`. Es diez líneas más de esquema y evita forzar una
   relación que no existe.

**El `.down.php`** borra la columna y las dos tablas.

### 4.2 La lista de regalos

Es lo único genuinamente nuevo. Cinco problemas, con su solución propuesta.

**Concurrencia.** Dos invitados tocan "Yo lo regalo" sobre el mismo ítem con dos
segundos de diferencia. La solución **no** es leer y después escribir: es una
escritura condicional, del tipo

```sql
UPDATE cc_gift_items
   SET status='taken', claimed_name=?, claimed_token=?, claimed_at=NOW()
 WHERE id=? AND status='available'
```

y mirar cuántas filas cambiaron. Si son cero, alguien se adelantó, y el segundo
invitado recibe un mensaje amable, no un error. Es la diferencia entre que dos
personas lleven el mismo coche y que no.

**Deshacer.** No hay cuentas de usuario. Cuando el invitado toma un regalo se le
emite un `claimed_token` opaco (`cb_opaque_token()`, igual que en el álbum) que
queda en su navegador. Mientras lo tenga puede soltar el regalo. Si borra sus
datos, pierde la capacidad de soltarlo y tiene que pedírselo a los papás. Es un
compromiso aceptable y hay que decirlo en pantalla, no esconderlo.

**Abuso.** El enlace es público: cualquiera que lo tenga puede tomar los diez
regalos o escribir groserías. Contención, reutilizando `cc_rate_limits` que ya
existe:

- Máximo de regalos que una misma persona puede tomar (propongo 3).
- Máximo de ítems agregados por invitado en una ventana de tiempo.
- Largo máximo de `title` y `notes`, y escapado al mostrarlos.
- Los papás pueden ocultar cualquier ítem desde su enlace.

**Moderación.** El álbum ya funciona con curaduría y conviene el mismo modelo,
pero **es decisión de Luis** si lo que agrega un invitado entra directo o espera
aprobación. Ver sección 9.

**Qué ve cada uno.** Depende de la decisión de identificación (sección 9). El
esquema soporta las tres opciones sin cambios: `claimed_name` se guarda siempre,
y lo que cambia es a quién se le muestra.

### 4.3 Los tres enlaces

| Quién | Qué puede hacer | Cómo entra |
|---|---|---|
| AutomatizaTech | crear el evento, emitir y revocar enlaces, moderar | admin con clave, como hoy |
| Futuros papás | armar y editar su lista, escribir condiciones, ocultar ítems | enlace propio con token, **sin clave** |
| Invitados | ver la invitación, tomar un regalo, soltarlo, agregar uno propio | la invitación pública |

El enlace de los papás no es el admin ni la invitación: es una tercera pantalla.
Se emite y se revoca desde el admin, igual que hoy se hace con el QR de carga
del álbum.

### 4.4 Vocabulario

El producto dice "cumpleañero" y "el cumpleaños de X" en muchos lugares. La
solución **no** es duplicar plantillas por modalidad, sino que cada cadena que
cambie salga de un solo diccionario indexado por `event_type`, con
`child_birthday` conservando exactamente los textos de hoy para que nada de lo
que ya funciona se mueva.

La Fase 2 incluye hacer el inventario exacto con `grep` y pasarlo al
diccionario. No lo listo acá porque el inventario hay que hacerlo contra el
código en el momento de ejecutarlo, y pegar una lista de hoy sería darle a
alguien una guía que va a estar vencida.

---

### 4.5 La cabina y sus juegos

El kiosco ya recorre estas pantallas (`src/App.jsx:342`):

`intro → invitados → spinner → photo-session → video-personaje → juego →
transition → capture → revelacion → preview → qr → diploma → farewell`

Baby shower usa **el mismo kiosco**, conservando la pantalla de `juego` y
saltándose las que dependen de personajes de ficción: `spinner`,
`video-personaje` y `revelacion`. La foto es de personas reales — la embarazada,
los papás, los invitados — contra el fondo de sala del tema y su marco. El marco
por fiesta ya existe: `cc_parties.frame_box_json`, normalizado por
`cb_normalize_frame_box()` (`public/lib.php:392` y `:440`).

**Qué motores de juego hay hoy.** Lo verifiqué en `public/data/themes.json` y el
resultado es más simple de lo esperado:

| Campo | Motores que existen | Quién los usa |
|---|---|---|
| `game` (plan básico) | **sólo `copos`** | sólo Reino de Hielo |
| `fullGame` (plan Full) | **sólo `concierto3d`** | carreras, hielo, heroes, tropical, familia-canina, kpop |

O sea que el "juego básico" es un único motor de atrapar cosas que caen,
configurado con `{kind, seconds, label}`. Cambiarle los copos por chupetes es
**un cambio de imágenes, no de código**.

**Los tres juegos que propongo**, en orden de lo que cuestan:

1. **"¡Atrapa los chupetes!"** — el motor `copos` con otras imágenes. Costo:
   sólo los sprites. Es el que debería ir en la Fase 3.
2. **"Arma el ajuar"** — el puzzle, con una imagen del tema en vez de un
   personaje. Los assets `puzzle-*.jpg` existen por temática, así que el motor
   probablemente ya está disponible para todas; **hay que confirmarlo en la
   Fase 0**, porque en `themes.json` el puzzle no aparece declarado como `game`.
3. **"¿A quién se parece?" o "¿Cuánto mide la guatita?"** — los juegos clásicos
   de baby shower son de adivinar, y para eso **no hay motor**. Habría que
   escribir uno de quiz. Es el más vistoso y el único que cuesta código de
   verdad. Lo dejaría fuera del primer entregable.

**Baby shower no lleva `fullGame`.** Con un solo plan y sin el Show 3D, el campo
queda vacío. Eso también evita tocar `SHOW_STYLES` y los escenarios 3D.

### 4.6 El recuerdito

No hay que inventarlo: es el mismo mecanismo del diploma, y ya funciona.

Cómo está hecho hoy, verificado:

- El título honorífico sale de la temática: `theme.diploma`, por ejemplo
  *"Guardián del Reino de Hielo"* (`src/App.jsx:58` y `:180`, valor en
  `themes.json`).
- La imagen se genera en el kiosco y **se sube por el mismo endpoint que la
  foto**; se distingue por el prefijo del nombre de archivo, `diploma-`
  (`public/ver.php:32-34`).
- `ver.php` ya cambia sus textos cuando detecta que es un diploma: *"Tu
  diploma"*, *"Guardar diploma"* (`public/ver.php:77` y `:79`).

Para baby shower hay que hacer dos cosas: darle a cada temática su honorífico
(están en la sección 7) y parametrizar por modalidad los textos de `ver.php`,
que hoy dicen *"diploma"* y *"Fiesta de X"*. Van al diccionario de la 4.4.

Es de las piezas más baratas del plan y de las que más se lucen: la persona se va
con algo suyo en el teléfono.

### 4.7 Un solo plan

Hoy el plan sale de `cc_parties.service_plan`, con whitelist `['booth','full']`
y caída a `'booth'` (`public/lib.invitations.php:747`).

Baby shower tiene un solo nivel, así que hay dos caminos: agregar un tercer valor
a la whitelist, o **que la modalidad mande sobre el plan** — cuando `event_type`
es `baby_shower`, todo va incluido y `service_plan` se ignora.

*Recomiendo el segundo.* Es menos código, no toca la whitelist que ya funciona
para las infantiles, y evita el estado sin sentido de un baby shower "plan booth"
contra uno "plan full". El campo queda en la tabla, simplemente no decide nada en
esta modalidad.

## 5. Plan de frontend

### 5.1 La sección de regalos dentro de la invitación

La invitación ya es una experiencia con scroll y video. La lista tiene que
sentirse parte de eso y no un formulario pegado al final. Concretamente:

- Entra como una sección más del scroll, con el mismo tratamiento de papel y
  color de la temática que ya usa el resto.
- Cada regalo es una tarjeta, no una fila de tabla. Las tarjetas tomadas se
  atenúan y se van al final, para que lo disponible quede arriba.
- Tomar un regalo no recarga la página ni te saca de la invitación.
- El nombre de la sección **está sin decidir** (sección 9). Va en el diccionario
  de la 4.4, en un solo lugar, porque va a cambiar.

Microcopy propuesto, para discutir:

| Elemento | Texto |
|---|---|
| Subtítulo | Elige un regalo y márcalo. Así nadie lleva lo mismo. |
| Botón del regalo | Yo lo regalo |
| Ya tomado | Ya lo lleva Camila / Ya lo lleva alguien |
| Agregar el propio | Voy a regalar otra cosa |
| Alguien se adelantó | Camila lo tomó hace un ratito. Elige otro. |
| Soltar | Ya no puedo llevarlo |

### 5.2 La página de los futuros papás

Una sola pantalla, sin clave, entrando por su token. Tiene que poder hacer
exactamente tres cosas: agregar un regalo, escribir su condición, y ordenar la
lista. Nada más.

Arriba, una línea que explique para qué sirve: *"Agrega lo que necesitan. Los
invitados van a ir eligiendo de acá."*

### 5.3 Estados, que es donde se nota si está bien hecho

Ninguna de las dos pantallas está terminada si sólo contempla el caso feliz.
Cada una debe resolver, en pantalla y con texto escrito de antemano:

- Lista vacía. Los papás todavía no agregaron nada y un invitado abre la
  invitación.
- Guardando, y guardado.
- Alguien se te adelantó con ese regalo.
- Ya no queda ningún regalo disponible.
- Sin conexión, o el guardado falló.
- El enlace de los papás fue revocado.

### 5.4 Qué hereda el álbum sin tocar nada

Prácticamente todo. El álbum consume del tema **sólo** los 9 tokens de color más
`assets.banner` y `assets.grupo` (`src/album/pages.js:31` y `:76`). El rediseño
del 2026-08-25 hizo que el papel, el confeti y los acentos se deriven de esos
tokens, así que una temática de baby shower que declare sus 9 colores tiene el
álbum entero resuelto.

Lo único a revisar: los textos de portada y cierre dicen "cumpleaños" y "Gracias
por venir a la fiesta de X". Van al diccionario de la 4.4.

---

## 6. Tabla B — qué necesita una temática de baby shower

La `TEMATICA-COMPLETA.md` vigente exige estrella con tres juegos, seis recortes
de personajes, videos de saludo y narración por personaje. **Nada de lo que
depende de personajes de ficción aplica acá**, porque la foto es de personas
reales. Propongo agregar esta tabla a ese documento como excepción declarada para
`event_type = baby_shower`, no ignorarlo en silencio.

| Pieza | Cantidad | Para qué |
|---|---|---|
| Tokens de color | 9 | Resuelven el álbum completo y los acentos de la invitación |
| `fondo-sala.jpg` | 1 | El fondo de la foto de cabina |
| `fondo-banner.jpg` | 1 | Banner del tema y respaldo de portada del álbum |
| `intro-inmersiva.mp4` | 1 | La entrada del kiosco |
| `musica-fondo.mp3` | 1 | Ambiente de la cabina |
| Sprites del juego | 1 set | Los "chupetes" que caen, reemplazando los copos |
| `fondo-juego-*.jpg` y `musica-juego.mp3` | 1 c/u | Fondo y música de la pantalla de juego |
| `intro-invitacion-wow-v1.mp4` + póster | 1 | Héroe automático de la invitación |
| `invitation-scroll-v1.mp4` | 1 | Héroe con scroll |
| `invitation-base-v1.jpg` + `invitation-motion-v1.mp4` | 1 c/u | La tarjeta que se comparte por WhatsApp |
| `despedida-<tema>.mp4` | 1 | Cierre de la invitación |
| `assets.grupo` | 1 | Cierre del álbum |
| Honorífico del recuerdito | 1 texto | `theme.diploma` |

**Lo que NO necesita**, y es donde está el ahorro:

| Pieza de una temática infantil | Por qué no aplica |
|---|---|
| 6 recortes `*-cut.png` y sus retratos | La foto es de personas reales |
| `saludo-*.mp4` (6) y `narracion-video/` | No hay personajes |
| `invitacion-juego-*.mp3` (6) | Son la voz de cada personaje invitando a jugar |
| `revelacion.mp4` | No hay revelación de personaje |
| `fullGame` y sus escenarios 3D | Un solo plan, sin Show 3D |
| Capítulos de la invitación | Sin personajes. `invitacion.php` ya cae solo a modo `auto` cuando la temática no trae carpeta `chapters` |

**Total: entre 8 y 9 piezas por temática**, contra las más de veinte de una
temática infantil completa.

---

## 7. Las cuatro temáticas

Dos de varón y dos de niña, todas de baby shower.

**La regla que se impusieron:** si les quitas el color y las dejas en blanco y
negro, un cliente tiene que seguir distinguiéndolas. Por eso cada una tiene su
propio material y su propia textura, y el lado varón o niña se lee por el
conjunto, no por el tono. Celeste contra rosado, y lo demás igual, serían dos
temáticas disfrazadas de cuatro.

Ninguna usa personajes con derechos de autor.

### 7.1 Pequeño Navegante — `pequeno-navegante` · varón

Un viaje en barquito de papel. Papel kraft doblado, cuerda, rayas horizontales
de marinero, olas recortadas en cartulina, banderines de señales.

Textura que la identifica: **papel recortado y cuerda trenzada.**

| Token | Valor |
|---|---|
| accent | `#1f4e79` |
| accentSoft | `#dce7f1` |
| yellow | `#e8b04b` |
| ink | `#152a3d` |
| bgLight1 | `#f3ece0` |
| bgLight2 | `#e3edf5` |
| dark1 | `#14395c` |
| dark2 | `#0d2437` |
| dark3 | `#e8b04b` |

Honorífico del recuerdito: **Tripulación de Bienvenida**.

### 7.2 Osito Aviador — `osito-aviador` · varón

Un osito de peluche con gorro de aviador y una avioneta de juguete. Cuero
gastado, lona cosida, latón, planos de vuelo dibujados a mano.

Textura que la identifica: **cuadrícula de plano y costura sobre lona.**

| Token | Valor |
|---|---|
| accent | `#8a5a3b` |
| accentSoft | `#f0e4d8` |
| yellow | `#c9a227` |
| ink | `#2e2118` |
| bgLight1 | `#f4ece1` |
| bgLight2 | `#eae0d2` |
| dark1 | `#5c3a24` |
| dark2 | `#33210f` |
| dark3 | `#c9a227` |

Honorífico del recuerdito: **Escuadrón del Primer Vuelo**.

### 7.3 Jardín de Invernadero — `jardin-invernadero` · niña

Un invernadero de vidrio con eucalipto, flores secas y maceteros de greda.
Botánica dibujada a línea, paneles de vidrio, hojas prensadas.

Textura que la identifica: **línea botánica y vidrio con marco.**

| Token | Valor |
|---|---|
| accent | `#6b8f71` |
| accentSoft | `#e6efe6` |
| yellow | `#c96f4a` |
| ink | `#26332a` |
| bgLight1 | `#f1f4ec` |
| bgLight2 | `#f7ece5` |
| dark1 | `#3f5c46` |
| dark2 | `#24352a` |
| dark3 | `#c96f4a` |

Honorífico del recuerdito: **Jardineros de la Primera Semilla**.

### 7.4 Luna de Encaje — `luna-encaje` · niña

La pieza del bebé de noche: un móvil colgando sobre la cuna, luna y estrellas
bordadas, encaje calado. Es la única oscura de las cuatro.

Textura que la identifica: **encaje calado sobre fondo de noche.**

| Token | Valor |
|---|---|
| accent | `#b86b7a` |
| accentSoft | `#f3e3e6` |
| yellow | `#d8b26a` |
| ink | `#2b2436` |
| bgLight1 | `#f4eef2` |
| bgLight2 | `#ece7f2` |
| dark1 | `#2e2a4d` |
| dark2 | `#1b1830` |
| dark3 | `#d8b26a` |

Honorífico del recuerdito: **Guardianes del Primer Sueño**.

### 7.5 Comprobación de que se distinguen

| | Material | Textura | Temperatura | Claro u oscuro |
|---|---|---|---|---|
| Pequeño Navegante | papel y cuerda | rayas y recorte | fría | claro |
| Osito Aviador | cuero y lona | cuadrícula y costura | cálida | claro |
| Jardín de Invernadero | vidrio y greda | línea botánica | fría vegetal | claro |
| Luna de Encaje | tela y encaje | calado | fría nocturna | **oscuro** |

Ninguna comparte material ni textura con otra, y la cuarta se separa además por
ser la única oscura.

**Una observación honesta:** Pequeño Navegante y Jardín de Invernadero funcionan
para cualquier bebé, no sólo para el lado al que los asigné. Si en algún momento
quieres venderlos sin etiqueta de sexo, se puede hacer sin tocar el diseño.

---

## 8. Fases

Cada fase termina en algo que se puede abrir y mirar.

**Fase 0 — Confirmar lo heredado.** Sin escribir código. Recorrer el kiosco de
una fiesta existente y anotar: qué pasa al saltarse `spinner`,
`video-personaje` y `revelacion`; si el puzzle está disponible para cualquier
temática o depende de los personajes; y qué se rompe si `event_type` fuera
`baby_shower`.
*Verificable:* una lista de hallazgos.

**Fase 1 — La modalidad existe.** Migración 010, el admin permite elegir
modalidad al crear el evento, y la invitación distingue las dos.
*Verificable:* crear un baby shower en local, abrir su invitación, y que use una
temática existente sin romperse.

**Fase 2 — La lista de regalos funciona.** Las dos pantallas nuevas, la escritura
condicional, el enlace de los papás, los límites de abuso y los estados de la
5.3.
*Verificable:* dos navegadores peleando por el mismo regalo, y que uno reciba
"alguien se te adelantó" en vez de un error. **Al terminar esta fase la modalidad
ya se puede vender**, sobre una temática que ya existe.

**Fase 3 — La cabina, el juego y el recuerdito.** El recorrido sin las pantallas
de personaje, "¡Atrapa los chupetes!" como reskin de `copos`, y el honorífico por
temática.
*Verificable:* sacarse una foto en el kiosco, jugar, recibir el QR y abrir el
recuerdito en el teléfono.

**Fase 4 — Vocabulario.** El diccionario de la 4.4 y el reemplazo de las cadenas
en invitación, kiosco, recuerdito y álbum.
*Verificable:* recorrer un baby shower completo sin encontrar la palabra
"cumpleaños" ni "diploma" en ninguna parte.

**Fase 5 — Una temática de varón y una de niña, completas.** Propongo Pequeño
Navegante y Luna de Encaje, que son las dos más distintas entre sí.
*Verificable:* invitación, cabina, juego, recuerdito y álbum en las dos.

**Fase 6 — Las otras dos.** Osito Aviador y Jardín de Invernadero.

**Después, si conviene:** el juego de adivinar, que es el único que necesita un
motor nuevo.

Las fases 0 a 4 no gastan un peso en generación de IA. Recién la 5 lo hace, y
para entonces ya vendiste.

---

## 9. Decisiones — resueltas el 2026-08-25

Luis tomó las recomendaciones. Quedan así:

| # | Decisión | Resuelto |
|---|---|---|
| 1 | Nombre de quien toma un regalo | **Visible para todos.** Hace que la lista se sienta viva, y en un baby shower no repetir el regalo importa más que la sorpresa |
| 2 | Lo que agrega un invitado | **Entra directo**, sin aprobación. Los papás igual pueden ocultarlo |
| 3 | Las condiciones de los papás | **Por regalo**, en el campo `notes`. "Coche" no le sirve a nadie; "Coche, liviano, que quepa en el auto" sí |
| 4 | Borrar un regalo ya tomado | **No se puede.** Sólo se pueden ocultar los que siguen disponibles |
| 5 | Qué es un plan en baby shower | **Uno solo**, con todo incluido. Ver la 4.7 |
| 6 | Nombre de la sección de regalos | **"Para cuando llegue"** |

Sobre el punto 6, un dato que apareció revisando el código: el preset de
`baby_shower` ya trae `"Conoce al bebé"` como sugerencia de título. No es
vinculante y el nombre elegido es otro, pero conviene saber que estaba.

### Lo que sigue abierto

- **Cuál de los tres juegos entra primero.** Mi recomendación es el reskin de
  `copos`, porque no cuesta código. El de adivinar es el más vistoso y el único
  que sí lo cuesta.
- **Los honoríficos de la sección 7** son propuestas mías. Si alguno no te suena,
  cámbialo: es un texto por temática.
- **Si el puzzle sirve para cualquier temática** o depende de los personajes. Se
  resuelve en la Fase 0, mirando el kiosco andando.

---

## 10. Pendiente: propuestas visuales de la clienta

Luis va a mandar **dos propuestas visuales de una clienta real**. Entran en la
Fase 4 y no bloquean nada anterior.

Cuando lleguen, hay que sacarles:

- Paleta real, y si choca con alguna de las cuatro de la sección 7.
- Materiales y texturas, que es lo que de verdad define la temática.
- Si corresponden a varón, a niña, o a ninguno de los dos.
- Si reemplazan una de las cuatro propuestas o se suman como quinta y sexta.
- Tipografía, si la traen.

### Propuesta A — (pendiente)

### Propuesta B — (pendiente)

---

## 11. Qué NO verifiqué

Para que nadie lo lea como si estuviera comprobado:

- **Qué renderiza hoy `event-profile-future-v1`.** Verifiqué que el layout está
  declarado en el preset y que `baby_shower` no define campos, pero no leí el
  código que lo dibuja. Puede que haya más hecho de lo que supongo, o menos.
- **El inventario exacto de cadenas** con "cumpleañero" y "cumpleaños". Es la
  primera tarea de la Fase 3 y hay que hacerlo contra el código del momento.
- **Si `cc_rate_limits` sirve tal cual** para limitar tomas de regalo, o
  necesita una columna más.
- **Cuánto pesa realmente el trabajo de frontend de la invitación**, porque no
  leí a fondo cómo está armado el scroll con video.
- **Cómo se comporta el kiosco al saltarse las pantallas de personaje.** Leí el
  arreglo de pantallas (`src/App.jsx:342`) y sé cuáles son, pero no probé qué
  pasa al omitirlas.
- **Si el puzzle y la ruleta están disponibles para cualquier temática.** Sus
  assets existen por tema (`puzzle-*.jpg`, carpeta `roulette`), pero en
  `themes.json` no aparecen declarados como `game`, así que no sé si son
  mecánicas compartidas o dependen de los personajes.
- **Si el marco por fiesta (`frame_box_json`) sirve tal cual** para una foto de
  adultos, o necesita otra proporción.
- **Nada de esto está probado.** Es un plan, no una implementación.
