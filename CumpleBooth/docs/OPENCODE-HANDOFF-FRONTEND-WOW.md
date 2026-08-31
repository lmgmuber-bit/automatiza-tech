# HANDOFF OPENCODE — Frontend "WOW": animaciones, 3D y profundidad en el kiosco

**Fecha:** 2026-07-27 · **Autor del brief:** Claude · **Ejecutor:** OpenCode
**Objetivo:** que el kiosco se sienta **premium y mágico** para un niño de 5-10 años, sin romper
nada del flujo que ya funciona en producción.

**Ticket:** `Docs/ORCHESTRATION/AT-CUMPLECLICK-010.yaml` · **Clase:** C1 · **Riesgo:** medio
(es la app que YA está en producción y en manos de clientes)

---

## −1. CONTEXTO OBLIGATORIO ANTES DE ESCRIBIR CÓDIGO

1. `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\10-Projects\CumpleClick.md` — qué es el producto.
2. `Docs/ORCHESTRATION/current-handoff.yaml` — el lease activo.
3. `Docs/ORCHESTRATION/AT-CUMPLECLICK-010.yaml` — tu ticket.
4. `CumpleBooth/design/MANUAL-DE-MARCA.md` — paleta, tipografía, voz. **No inventes colores.**

Rama nueva antes de tocar código. Sin push/merge/deploy sin permiso explícito de Luis.
Al cerrar: actualiza el ticket, la nota del vault y escribe el cierre en `50-Daily-Logs/`.

---

## 0. LAS SKILLS QUE DEBES USAR (Luis lo pidió explícitamente)

Estas dos son **obligatorias** y se usan juntas. Cárgalas antes de decidir nada:

### `frontend-motion-toolkit`
Te dice qué librería de animación corresponde a cada necesidad y cuál NO usar.
Referencias dentro de la skill:
- `references/use-case-guide.md` — casos de uso y ejemplos
- `references/library-notes.md` — caveats de cada paquete
- `references/ui-component-libraries.md` — componentes ya hechos

**Regla de la skill que aplica acá:** antes de implementar, declara en una línea:
`Motion read: <tipo de pantalla>, <intención>, <rol de la animación>, <librería elegida>, <riesgo a evitar>.`

### `design-taste-frontend`
Te da el criterio visual para que no salga genérico. Antes de generar, declara el **Design Read**:
`"Reading this as: <tipo> para <audiencia>, con lenguaje <vibe>, tendiendo a <sistema/estética>."`

**Dials sugeridos para este proyecto** (justificados abajo, ajústalos si tienes mejor argumento):
```
DESIGN_VARIANCE: 8    (es una experiencia de fiesta, no un dashboard)
MOTION_INTENSITY: 8   (el "wow" ES el producto — pero ver §3, hay un techo duro)
VISUAL_DENSITY: 2     (pantalla táctil para niños: pocos elementos, grandes)
```

---

## 1. QUÉ LIBRERÍAS USAR — y cuáles NO

`package.json` actual: React 18, Vite 6, **three ^0.185.1** (ya instalada y en uso), qrcode.

| Necesidad en este proyecto | Usa | ¿Instalar? |
|---|---|---|
| Timelines, secuencias encadenadas, entradas dramáticas | **GSAP** | `npm i gsap` |
| Animación de componentes React, enter/exit, layout | **Motion** (`motion/react`) | `npm i motion` |
| 3D: ruleta con profundidad real, partículas, shaders | **Three.js** | ya está ✅ |
| Micro-interacciones simples (hover, press, glow) | **CSS puro** | nada |

**NO instales:**
- **Lenis** — no hay scroll en un kiosco a pantalla completa. Sería peso muerto.
- **Barba.js** — no hay navegación entre páginas, es una SPA de una sola vista.
- **Anime.js** — se solapa con Motion; elige uno, y en React el que corresponde es Motion.
- Ninguna librería de componentes (shadcn, Skiper, etc.) — este UI es 100% propio y a medida.

Regla dura de la skill: **si CSS alcanza, usa CSS.** No metas GSAP para un fade.

---

## 2. DÓNDE APLICARLO — las pantallas ordenadas por impacto

Todo vive en `src/App.jsx` (~3200 líneas, un solo archivo) y `src/index.css`.
Lee el componente antes de tocarlo; los nombres de pantalla son los `className="screen …"`.

### 2.1 Prioridad ALTA — acá se gana o se pierde el "wow"

| Pantalla | Componente | Qué hacer |
|---|---|---|
| **Ruleta** | `Spinner` (busca `.spinner-rotator`) | Es EL momento de tensión. Hoy es un giro CSS 2D plano. Dale profundidad: inclinación 3D en perspectiva, desaceleración con física real (no `ease-out` genérico), destello + partículas al detenerse, y que el sector ganador "salte" hacia la cámara. **Es la pantalla que más se mira en toda la fiesta.** |
| **Intro / portada** | `Intro` (`.gate-screen`) | Primera impresión. Entrada escalonada de los elementos, el logo con vida propia (respiración sutil), fondo con parallax de profundidad según la temática. |
| **Preview de la foto** | `Preview` (`.screen.preview`) | El momento de orgullo del niño. Que la foto "aterrice" con peso (escala + sombra que cae), confeti temático, y el marco apareciendo después de la foto, no junto con ella. |
| **Revelación** | `Revelacion` (`.screen.revelacion`) | Ya existe un video de suspenso. Súmale una transición de salida a la altura, no un corte seco. |

### 2.2 Prioridad MEDIA

| Pantalla | Componente | Qué hacer |
|---|---|---|
| **Lista de invitados** | `ListaInvitados` (`.invitados-list`) | Entrada en cascada de los nombres; al tocar uno, que el resto se aparte en vez de solo marcarse. |
| **Juegos** | `JuegoCopos`, `JuegoMuneco`, `JuegoFichas` | Feedback táctil real: cada acierto debe **sentirse**. Escala, rebote, partícula. Hoy es funcional pero seco. |
| **Diploma** | `DiplomaScreen` | Que se "imprima"/revele, no que aparezca de golpe. |

### 2.3 NO TOCAR
- La **lógica** del flujo (`themeFlow.js`, orden de pantallas, `gamesFor`) — solo capa visual.
- `public/lib.php` ni nada de backend.
- La **captura de cámara** (`.screen.capture`): ahí un frame perdido es una foto perdida.

---

## 3. EL TECHO DURO — restricciones que NO son negociables

Esto es un kiosco real corriendo en **una tablet modesta**, todo el día, con niños tocando.

1. **60fps o no va.** Si una animación baja de 60fps en una tablet de gama media, se simplifica.
   Anima `transform` y `opacity`. **Nunca** animes `width`, `height`, `top`, `left`, `margin`.
2. **`prefers-reduced-motion` se respeta siempre.** El proyecto ya tiene la constante `REDUCE_MOTION`
   en `App.jsx` — úsala, no inventes otra.
3. **Nada puede bloquear el avance del flujo.** Si una animación falla, el kiosco sigue. Los
   componentes ya tienen watchdogs (`setTimeout` de seguridad) — no los quites ni los alargues.
4. **La fila de niños no espera.** Ninguna animación nueva puede sumar más de ~400ms al tiempo
   total de un invitado. El "wow" es en paralelo al flujo, no en serie.
5. **Three.js: cuidado con el peso.** El bundle ya pesa 734kB por three. Si agregas escenas,
   usa `import()` dinámico para no cargarlas en pantallas que no las usan.
6. **Táctil, no hover.** Los estados `:hover` no existen en una tablet. Diseña para `:active` y
   para el toque.

---

## 4. CÓMO VERIFICAR (esto es parte del entregable, no opcional)

El proyecto corre en local: `http://localhost/automatiza-tech/CumpleBooth/dist/?p=<slug>`
Fiestas demo locales con invitados: `demo-hielo` (Frozen), `demo` (Bluey), `demo-tropical`, `demo-carreras`.

```bash
npm test                             # 36 tests, deben seguir en verde
npm run build
php scripts/check-dist-parity.php    # debe decir OK paridad
php tests/backend/run.php            # 83 checks
```

Además, **obligatorio antes de entregar**:
- Recorre el flujo completo de UNA temática de punta a punta en el navegador, con DevTools abierto
  en la pestaña Performance, y confirma que no hay caídas bajo 60fps.
- Prueba con `prefers-reduced-motion: reduce` activado (DevTools → Rendering → Emulate CSS media).
- Prueba en viewport de tablet real (768×1024), no en desktop.

**No entregues diciendo "debería verse bien".** Si no lo miraste corriendo, no está listo.

---

## 5. ORDEN DE TRABAJO SUGERIDO

1. Carga las 2 skills, declara el *Design Read* y el *Motion read*.
2. **Empieza SOLO por la ruleta** (§2.1) y muéstrasela a Luis antes de seguir. Si el estilo no le
   gusta, mejor descubrirlo en una pantalla que en ocho.
3. Con el visto bueno, sigue con intro → preview → revelación.
4. Prioridad media al final.
5. Verificación completa de §4 + reporte.

---

## 6. TU REPORTE FINAL DEBE DECIR

- El *Design Read* y el *Motion read* que declaraste.
- Qué librerías instalaste y **por qué cada una** (si instalaste algo de la lista prohibida de §1,
  justifícalo o revierte).
- Medición de fps por pantalla tocada.
- Qué pantallas quedaron sin tocar y por qué.
- Screenshots o video del antes/después de la ruleta como mínimo.

**Sé honesto.** Si una animación no te quedó bien, dilo y déjala fuera. Una pantalla sobria que
corre a 60fps es infinitamente mejor que una espectacular que tironea en la tablet del cliente.
