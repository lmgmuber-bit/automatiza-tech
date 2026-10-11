# Cumpleaños de adulto y temática «Coquetería Pop» (AT-CUMPLECLICK-026)

Estado al 2026-10-11: **LOCAL, sin desplegar**. Rama `claude/cumple-adulto-coqueteria`, base `origin/main` (`188ba45`).
Ticket: `Docs/ORCHESTRATION/AT-CUMPLECLICK-026.yaml`.

El piloto es un cumpleaños privado. Sus datos, fotos, video y dirección **no están ni deben estar** en este repositorio
(es público): viven en el paquete privado de Codex y, al desplegar, en la base y el almacén privado de PROD.

## Qué es

Una tercera modalidad de evento, `adult_birthday`, junto a `child_birthday` y `baby_shower`, y una temática genérica
para ella. La temática no lleva nombre, edad ni fotos: el kiosco escribe el nombre y la edad de la fiesta encima.

| Pieza | Dónde | Qué hace |
|---|---|---|
| Modalidad | `public/lib.php` `cb_party_event_type()` | Reemplaza los nueve `=== 'baby_shower' ? … : 'child_birthday'` que había repartidos. Lo desconocido cae a infantil. |
| Guarda de temática | `cb_theme_is_adult()` + admin + `cb_resolve_party()` | Temática adulta solo con cumpleaños de adulto y al revés. El admin avisa; `api.php` responde 409 si la base trae el cruce. |
| Edad | `cb_party_age_valid()` | Niño 1–17, adulto 18–120. **Antes se recortaba a 17 al guardar y «69» se perdía sin aviso.** |
| Temática | `public/data/themes.json` → `adulto-coqueteria-pop` | `audiencia: adulto`, sin personajes, `frameBox`, `grupal` y el bloque `cabinaAdulto` (frases, lema, recuerdo, cinta, panel, juego). |
| Fondos | `public/themes/adulto-coqueteria-pop/` | `fondo-sala.jpg` (marco «¡Se busca!»), `fondo-banner.jpg` (bienvenida), `fondo-grupal.jpg`. Hechos con código, sin IA: `design/generadores/tematicas/coqueteria_pop.py`. |
| Kiosco | `src/App.jsx`, `src/cumpleAdulto.js` | Bienvenida → «¡Atrapa la coquetería!» (se puede saltar) → elegir frase → cámara → ficha → QR. Sin ruleta, sin diploma, sin música si no hay pista. |
| Invitación | `public/invitacion.php` | Sin la voz de Alice (le habla a un niño), sin capítulos, video del evento con póster y controles, asistencia para adultos. |

## Contrato de `cabinaAdulto` (themes.json)

```json
"cabinaAdulto": {
  "titulo": "gente con ganas de pasarlo bien",
  "lema": "{edad} y todavía doy guerra",
  "recuerdo": "Cómplice oficial de {nombre}",
  "cinta": {"x": 0.0833, "y": 0.6229, "w": 0.8333, "h": 0.0521},
  "panel": {"x": 0.0741, "y": 0.6979, "w": 0.8519, "h": 0.1875},
  "frases": ["…hasta 12, de hasta 48 letras; {nombre} permitido…"],
  "juego": {"label": "¡Atrapa la coquetería!", "emojis": ["🍆", "🍑", "💋", "🍒", "🌶️"]}
}
```

`cb_theme_cabina_adulto()` lo copia campo por campo. Una cinta o un panel fuera del lienzo invalidan el bloque. Solo una
temática adulta lo publica; el kiosco infantil no recibe ni la clave. Sin `{edad}` en la fiesta, el lema cambia entero
(«Y todavía doy guerra»), y sin nombre «Cómplice oficial de la fiesta»: nunca queda un hueco.

`cinta` y `panel` son las cajas que el generador pinta vacías en `fondo-sala.jpg`; si se rehace el fondo, se copian los
valores que imprime el script.

## Juego de atrapar

Es el mismo `JuegoCopos` del baby shower («Atrapa los chupetes»), con 20 segundos y los emojis del bloque. Los de doble
sentido (🍆🍑) son el pedido de Luis («atrapa algo asociado a la temática»). Se eligieron emojis y no dibujos explícitos
porque funcionan en cualquier tablet, no cuestan nada y la foto se puede mostrar en la fiesta. El puntaje queda impreso
en la ficha.

## Portada en pareja (revista)

Botón «📰 Portada en pareja» en la bienvenida: elegir titular → foto de los dos → portada → QR. Fondo
`fondo-revista.jpg` (hueco 4:5 y panel vacío); el kiosco escribe el nombre de la revista («COQUETA», editable en
`cabinaAdulto.revista.titulo`), la edición, un sello con la edad en la esquina de la foto (lejos de las caras) y el
titular. La foto entra en modo «cubrir», recortando más del techo que de los pies, como la foto grupal.

## Asómate adulto

Botón «🎭 Asómate y sé la estrella»: 14 figuras en 7 parejas (dama de rojo y galán de terno, pin-up y galán de
telenovela, diva de cabaret y gánster, salsera y salsero, reina y rey de la fiesta, reina del verano y el forzudo,
Súper Coqueta y Capitán Galán). Parte en «Los dos» y no pasa por la lista de invitados. Las figuras son ilustraciones
genéricas generadas con Recraft v4.1 por la API de Higgsfield el 11-oct-2026 con el visto bueno de Luis: 18 imágenes,
USD 0,63 de lista (cuatro rehechas porque la cara no servía para el hueco). El recorte y el hueco los hace
`design/generadores/tematicas/asomate_adultos.py` sin IA; el óvalo de cada cara está puesto a ojo en `OVALOS` (la
detección de piel falla con piel rosada y camisas claras). Fondo: la bienvenida de la temática.

## Juegos del animador

Botón «🎲 Juegos de la fiesta» en la bienvenida (solo cumpleaños de adulto). Los 15 juegos del catálogo de Codex
(`src/animador/mazos.js`), aprobados por Luis el 11-oct («haz todas»), sobre un motor sin DOM (`src/animador/motor.js`):
nivel como techo (Coqueto / Picante / Sinvergüenza; se filtra antes de barajar y nunca sube solo), mazo sin repetir que
se rebaraja solo a pedido, «Paso esta» sin castigo, un punto por equipo y ronda aunque se toque dos veces, deshacer el
último, cronómetro por juego, respuesta oculta hasta «Revelar», frase de mímica visible solo manteniendo apretado,
marcador que sobrevive a un recargón (sessionStorage; nunca respuestas) y cierre con ganador y foto de los cómplices.
Las preguntas del Tribunal no traen respuesta: la tiene la celebrada o el animador.

## Cómo probarlo en local

- `node --test tests/frontend/cumpleAdulto.test.mjs` (textos y temática genérica).
- `php tests/backend/cumple-adulto-http.php` (36: admin, api del kiosco, invitación, fiesta infantil sin cambios y cruce
  en la base). Tiene dos controles en rojo hechos: con el tope viejo de 17 falla la edad, y sin la guarda de la narración
  falla «sin la narración de Alice».
- El recorrido del kiosco se probó con Chrome sin ventana y una cámara falsa (retrato ficticio), en una SQLite temporal.

## No probado

Tablet real, cámara real con personas (grupos, alturas, gafas), impresión, la invitación en un teléfono real, el video
verdadero del evento (el local usó uno de 2 s hecho con el fondo).
