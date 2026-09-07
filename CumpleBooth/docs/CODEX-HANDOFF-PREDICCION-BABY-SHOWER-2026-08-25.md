# Codex — La predicción de la cabina de baby shower

Fecha: 2026-08-25 · Encargado por Luis · Escrito por Claude
Rama de referencia: `claude/baby-shower-plan`
Estado: **aprobado por Luis para ejecutar.**

---

## Antes que nada: si tienes una idea mejor, dila

Luis fue explícito en esto, y es la parte más importante del encargo:

> "Si él tiene una mejor opción, una mejor opinión o su punto de vista que
> considera mejor, tiene que explicar por qué, y ahí se la aprobamos o no se la
> aprobamos según sea el caso."

O sea: **no ejecutes en silencio algo que te parece equivocado, y tampoco lo
cambies por tu cuenta.** Si crees que hay un camino mejor:

1. Escribe cuál es.
2. Escribe **por qué** es mejor, en concreto: qué problema evita, qué cuesta de
   más o de menos, qué se rompe si nos equivocamos.
3. Espera que Luis apruebe o rechace. No empieces.

Vale para todo: el diseño de las pantallas, el esquema de la tabla, el orden del
recorrido, las preguntas mismas. Si algo de este documento está mal, decirlo es
parte del trabajo, no una interrupción.

---

## Contexto en tres párrafos

CumpleClick es un servicio de cabina de fotos para fiestas infantiles. El
invitado pasa por un kiosco, sale en una foto con la temática de la fiesta, se
lleva un QR con su foto y un diploma, y todo termina en un Álbum Recuerdo que es
una revista digital con volteo de páginas.

Se está agregando una **modalidad nueva: baby shower**. Lleva invitación con
lista de regalos, cabina con juegos, recuerdito (el equivalente del diploma) y
álbum. Un solo plan, con todo incluido.

**El plan completo está en
`CumpleBooth/docs/PLAN-BABY-SHOWER-2026-08-25.md`, rama
`claude/baby-shower-plan`. Léelo antes de tocar nada**, sobre todo las secciones
2 (lo verificado en el código), 4.1 (migración), 4.5 (la cabina) y 4.8 (esto que
te toca).

---

## Lo que tienes que construir

Una cabina que sólo saca una foto no sorprende a nadie. En las fiestas
infantiles el momento fuerte es la **revelación**: el niño se ve dentro de la
escena con su personaje. Baby shower necesita el suyo y no puede ser ese, porque
no hay personajes.

La respuesta es **la predicción**: antes de la foto, cada invitado apuesta, y esa
apuesta lo acompaña el resto del recorrido.

### Recorrido completo

| # | Pantalla | Qué pasa | Estado |
|---|---|---|---|
| 1 | Intro | Video inmersivo del tema | ya existe |
| 2 | **Tu predicción** | Tres preguntas de un toque, más el nombre | **la construyes tú** |
| 3 | Juego | "¡Atrapa los chupetes!" | motor `copos`, sólo imágenes |
| 4 | Foto | Fondo de sala del tema y marco | ya existe |
| 5 | **La revelación** | La foto dentro de la escenografía, con la predicción escrita encima y el puntaje | **le das contenido**, la pantalla existe |
| 6 | Recuerdito | Honorífico + predicción + puntaje | mecanismo del diploma |
| 7 | QR | Se lleva foto y recuerdito | ya existe |

### Las tres preguntas

Botones grandes, respuesta de un toque. **Nadie escribe nada salvo su nombre**,
porque la gente está de pie con un vaso en la mano.

- **¿A quién se va a parecer?** → a la mamá · al papá · a los dos
- **¿Cuánto va a pesar?** → menos de 3 kilos · entre 3 y 3½ · más de 3½
- **¿Cuándo va a nacer?** → antes de la fecha · justo ese día · después

### El tablero de predicciones

Al final de la fiesta, los papás abren un enlace y ven **todas las apuestas
juntas**: quién dijo qué. Se guarda, se imprime, y cuando el bebé nace se sabe
quién acertó.

Ese tablero es el que ninguna cabina de la competencia entrega, así que trátalo
como parte del entregable y no como un extra.

---

## Lo que ya existe y tienes que reutilizar

Verificado leyendo el código, con archivo y línea. **No reinventes nada de
esto**; si alguno no sirve para tu caso, explica por qué antes de reemplazarlo.

| Qué | Dónde |
|---|---|
| El arreglo de pantallas del kiosco | `src/App.jsx:342` |
| El honorífico del diploma sale de la temática (`theme.diploma`) | `src/App.jsx:58` y `:180`, valor en `public/data/themes.json` |
| El diploma se sube por el mismo endpoint que la foto y se distingue por el prefijo `diploma-` | `public/ver.php:32-34` |
| `ver.php` ya cambia sus textos al detectar que es un diploma | `public/ver.php:77` y `:79` |
| El juego básico se declara como `game: {kind, seconds, label}`; el único motor que existe es `copos` | `public/data/themes.json` |
| Emisión y revocación de tokens por rol | `cb_album_issue_token()` en `public/lib.album.php`, tabla `cc_event_album_tokens` |
| Tokens opacos | `cb_opaque_token()` |
| Límites contra abuso desde páginas públicas | `cb_album_limits()`, tabla `cc_rate_limits`, ejemplo vivo en `public/subir.php` |
| Convención de migraciones (chequeo de existencia antes de alterar, `.down.php` obligatorio) | `database/migrations/` |

---

## La migración 010

**Te encargas de la migración 010 completa**, aunque implementes sólo la
predicción.

El motivo: la lista de regalos (que se implementa después) necesita sus tablas en
la misma migración. Partir una migración entre dos agentes que trabajan en
paralelo es la forma más rápida de terminar con una 010 y una 010b que se pisan.
Las tablas de regalos quedan creadas y sin usar hasta que alguien las use. Eso es
barato; un conflicto de migraciones no.

La 010 agrega, según la sección 4.1 del plan:

1. `cc_invitations.event_type VARCHAR(40) NOT NULL DEFAULT 'child_birthday'`
2. `cc_gift_items` — la lista de regalos (**crear, no usar todavía**)
3. `cc_invitation_tokens` — tokens por rol colgando de la invitación
4. `cc_predictions` — **esto es lo tuyo**

```
cc_predictions
  id              PK
  invitation_id   FK
  guest_name      VARCHAR(80)
  parecido        ENUM mama | papa | ambos
  peso            ENUM menos3 | entre | mas35
  fecha           ENUM antes | justo | despues
  puntaje_juego   INT NULL
  created_at      DATETIME
```

Su `.down.php` deshace las cuatro cosas.

---

## Cómo se ve "terminado"

No está listo hasta que puedas mostrar esto andando, no describirlo:

1. Recorrer el kiosco de punta a punta en local con un evento de baby shower.
2. Que la revelación muestre tu foto con **tu** predicción escrita encima.
3. Abrir el recuerdito en el teléfono desde el QR y ver ahí tu apuesta y tu
   puntaje.
4. Que **tres personas** distintas pasen por la cabina y las tres aparezcan en el
   tablero de los papás.
5. Correr la 010 dos veces seguidas sin que reviente, y su `.down.php` dejando la
   base como estaba.

Y los estados que la gente sí se va a encontrar, no sólo el caso feliz: sin
conexión, guardado fallido, el invitado aprieta dos veces, el enlace del tablero
revocado, tablero vacío porque todavía no pasó nadie.

---

## Prohibido

- **Deploy, merge, push a `main`.** Trabaja en tu propia rama y en tu propia
  carpeta. Luis sube a producción, nadie más.
- **Tocar el Show 3D** ni la lógica aprobada de Carreras y Reino de Hielo. Baby
  shower no las usa.
- **Cambiar el comportamiento de las fiestas infantiles.** El default
  `child_birthday` de la migración existe justamente para que nada de lo que hoy
  funciona se mueva. Si algo de tu cambio toca el camino de una fiesta infantil,
  para y avisa.
- **Guardar secretos** en cualquier archivo, `.env`, config, log o documentación.
  Las credenciales viven fuera del repo.
- **Generar imágenes o video con IA.** Los assets de las temáticas no son parte
  de este encargo. Si necesitas un marcador, usa un rectángulo de color.

---

## Dos cosas que NO verifiqué, y tienes que confirmar tú

Están anotadas en la sección 11 del plan. No las des por buenas:

1. **Qué pasa al saltarse las pantallas de personaje.** El recorrido de baby
   shower omite `spinner`, `video-personaje` y `revelacion`... salvo que la
   `revelacion` sí se usa, con otro contenido. Leí el arreglo de `SCREENS` pero
   **no probé qué pasa al omitir las otras**. Puede haber estado compartido entre
   pantallas que reviente. Averígualo antes de diseñar la solución.
2. **Si el marco por fiesta (`frame_box_json`) sirve tal cual** para una foto de
   adultos, o necesita otra proporción. En una fiesta infantil enmarca a un niño.

Si cualquiera de las dos resulta distinta de lo que supuse, ese es exactamente el
caso de "tengo una idea mejor": dilo con el porqué y esperamos a Luis.

---

## Entrega

Un documento corto en `CumpleBooth/docs/` con: qué construiste, qué encontraste
distinto de lo que dice este handoff, y la lista de archivos para FTP con ruta
local, destino en PROD y orden de subida cuando importe.

Nada se describe como que está en producción sin evidencia.
