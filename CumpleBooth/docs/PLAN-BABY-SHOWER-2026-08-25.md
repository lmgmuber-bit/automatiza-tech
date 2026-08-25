# Plan — Modalidad Baby Shower

Estado: **propuesta, sin aprobar y sin código.** Rama `claude/baby-shower-plan`.
Fecha: 2026-08-25.

> Este documento reemplaza por completo una versión anterior del mismo nombre.
> Aquella asumía que baby shower llevaba cabina de fotos, juegos y dos
> temáticas infantiles, y presupuestaba 28 videos. Luis acotó el alcance
> después: baby shower es **sólo invitación y álbum**, y las cuatro temáticas
> son todas de baby shower. Lo que sigue está escrito sobre el alcance real.

---

## 1. Resumen para decidir en dos minutos

**Qué se agrega.** Una segunda modalidad de evento, `baby_shower`, además de
las fiestas infantiles. No comparte casi nada operativo con ellas: no hay
kiosco, no hay juegos, no hay fotos de cabina. Son dos piezas: **la invitación**
y **el Álbum Recuerdo**.

**Qué la hace vendible.** La invitación trae una **lista de regalos con
reserva**. Los futuros papás la arman desde un enlace propio. Cada invitado ve
qué sigue disponible, toma uno, y queda a su nombre. Si quiere regalar algo que
no está, lo escribe y entra a la lista ya tomado por él. Nadie repite regalo.
Eso es lo que un baby shower necesita y una fiesta infantil no, y es lo único
verdaderamente nuevo que hay que construir.

**Lo que ya está hecho y apagado.** Buena parte de la plomería existe:
`cc_parties.event_type` está desde la migración 008 con default
`child_birthday`, y `baby_shower` ya figura declarado en
`event-profile-presets.json`. Falta **una** migración, la 010.

**Lo que cuesta.** El grueso ya no son los videos. Con cuatro o cinco piezas de
video por temática en vez del set completo de cabina, el presupuesto audiovisual
baja mucho. El trabajo real se reparte entre la lista de regalos (backend nuevo
de verdad) y sacarle al producto los "cumpleañero" que tiene repartidos.

**Lo que propongo.** Empezar por la lista de regalos sobre una temática que ya
existe. Al terminar la Fase 2 la modalidad ya se puede vender y mostrar, sin
haber generado un solo asset nuevo.

**Lo que NO propongo.** Tocar el Show 3D, la cabina, ni la lógica aprobada de
Carreras y Reino de Hielo. Baby shower no las usa.

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

La `TEMATICA-COMPLETA.md` vigente está escrita para una temática de cabina:
exige estrella con tres juegos, recortes de personajes, fondos de sala. **Nada
de eso aplica acá.** Propongo agregar esta tabla a ese documento como excepción
declarada para `event_type = baby_shower`, no ignorarlo en silencio.

| Pieza | Cantidad | Nota |
|---|---|---|
| Tokens de color | 9 | Resuelven el álbum completo y los acentos de la invitación |
| `intro-invitacion-wow-v1.mp4` + su póster | 1 | Héroe automático de la invitación |
| `invitation-scroll-v1.mp4` | 1 | Héroe con scroll, que es lo que ve el plan básico |
| `invitation-base-v1.jpg` + `invitation-motion-v1.mp4` | 1 c/u | La tarjeta que se comparte por WhatsApp |
| `despedida-<tema>.mp4` | 1 | Cierre. **Opcional**: evaluar si baby shower lo necesita |
| `assets.banner` y `assets.grupo` | 1 c/u | Portada de respaldo y cierre del álbum |
| Narración | 0 nuevas | Las de `nino`/`nina` ya existen y son genéricas |
| **Capítulos de personajes** | **0** | No hay personajes |

Sobre los capítulos: hoy cada temática infantil trae varios `saludo-<personaje>.mp4`
que son los capítulos de la invitación. Baby shower no tiene personajes, y no
hace falta inventarlos: `invitacion.php` ya cae solo a modo `auto` cuando la
temática no trae carpeta `chapters`. O sea que una temática de baby shower
simplemente no la trae, y la invitación funciona.

**Total por temática: 4 o 5 piezas de video, no las 7 y tantas de una temática
de cabina.** Ahí está el ahorro grande respecto del plan anterior.

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

**Fase 0 — Confirmar lo heredado.** Sin escribir código. Correr la invitación y
el álbum de una fiesta existente y anotar qué se rompe si `event_type` fuera
`baby_shower`. Sirve para no descubrir sorpresas en la Fase 2.
*Verificable:* una lista de hallazgos.

**Fase 1 — La modalidad existe.** Migración 010, el admin permite elegir
modalidad al crear el evento, y la invitación distingue las dos.
*Verificable:* crear un evento de baby shower en local, abrir su invitación, y
que use una temática existente sin romperse.

**Fase 2 — La lista de regalos funciona.** Las dos pantallas nuevas, la
escritura condicional, el enlace de los papás, los límites de abuso y los
estados de la 5.3.
*Verificable:* dos navegadores distintos peleando por el mismo regalo, y que uno
de los dos reciba "alguien se te adelantó" en vez de un error. **Al terminar
esta fase la modalidad ya se puede vender**, usando una temática que ya existe.

**Fase 3 — Vocabulario.** El diccionario de la 4.4 y el reemplazo de las cadenas
en invitación y álbum.
*Verificable:* recorrer una invitación y un álbum de baby shower completos sin
encontrar la palabra "cumpleaños" en ninguna parte.

**Fase 4 — Una temática de varón y una de niña, completas.** Propongo Pequeño
Navegante y Luna de Encaje, porque son las dos más distintas entre sí y dejan
mostrar el rango.
*Verificable:* invitación y álbum completos en las dos.

**Fase 5 — Las otras dos.** Osito Aviador y Jardín de Invernadero.

Las fases 0 a 3 no gastan un peso en generación de IA. Recién la 4 lo hace, y
para entonces ya vendiste.

---

## 9. Decisiones que necesito de Luis

Ninguna de estas la puedo tomar yo.

**1. ¿Se muestra el nombre de quien toma un regalo?**
Tres caminos, y cambian la pantalla y la tabla:
- Visible para todos: los invitados se coordinan solos. Se pierde la sorpresa.
- Visible sólo para los papás: el resto ve "ya lo lleva alguien".
- Anónimo: nadie sabe quién.
*Mi recomendación:* visible para todos. Es lo que hace que la lista se sienta
viva, y en un baby shower la sorpresa importa menos que no repetir el regalo.

**2. ¿Lo que agrega un invitado entra directo o lo aprueban los papás?**
*Mi recomendación:* directo. Pedirle aprobación a la mamá embarazada por cada
regalo agregado es fricción, y los papás ya pueden ocultar cualquier ítem.

**3. Las condiciones de los papás: ¿arriba de la lista o por regalo?**
*Mi recomendación:* por regalo. "Coche" sin más no le sirve a nadie; "Coche,
liviano, que quepa en el auto" sí. El esquema ya lo contempla en `notes`.

**4. Si los papás borran un regalo que alguien ya tomó, ¿qué pasa?**
*Mi recomendación:* que no se pueda borrar, sólo ocultar los que están
disponibles. Un invitado que ya compró algo y ve que desapareció de la lista es
un problema que no vale la pena crear.

**5. ¿Qué significa un "plan" en baby shower?**
Hoy son `booth` y `full`, y ninguno aplica sin cabina. Hay que decidir si baby
shower tiene sus propios niveles (por ejemplo con o sin álbum), o uno solo.

**6. El nombre de la sección de regalos.**
Sigue sin decidir. Las opciones que te di: **Para cuando llegue** (mi
recomendación, funciona sin nombre ni sexo), Su primera lista, Lo que necesita
[Nombre], o Lista de regalos.
*Dato que apareció al revisar el código:* el preset de `baby_shower` ya trae
`"Conoce al bebé"` como sugerencia de título. No es vinculante, pero está.

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
- **Nada de esto está probado.** Es un plan, no una implementación.
