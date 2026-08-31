# Campaña de lanzamiento — invitaciones inmersivas CumpleClick

Fecha: 2026-08-11. Estado: estrategia y preproducción; **sin videos generados,
sin publicación y sin consumo de créditos**.

## Decisión comercial canónica

CumpleClick empieza antes de la fiesta: la invitación es la primera entrega y el
primer momento WOW para la familia y sus invitados.

| Oferta pública | Valor técnico actual | Invitación incluida | Diferencia principal |
|---|---|---|---|
| **Plan Básico** | `service_plan=booth` | **Versión Scroll** | El invitado controla el avance con el dedo y descubre la historia por capítulos. |
| **Plan Full** | `service_plan=full` | **Versión Automática** | La historia se reproduce sola como una secuencia cinematográfica con videos y narración por capítulo cuando esos assets están disponibles. Incluye todo lo del Básico. |

Los materiales antiguos llaman a estas ofertas **Mágico** y **Premium**. Mientras
se actualizan todas las piezas comerciales, usar esta equivalencia:

- Mágico = Plan Básico.
- Premium = Plan Full.

Precios de referencia vigentes: Básico/Mágico **$69.990 CLP** y Full/Premium
**$99.990 CLP**. Temática a medida: **+$25.000 CLP**. Confirmar vigencia antes de
publicar una campaña pagada.

### Funciones comunes que sí se pueden mostrar

- Sobre animado de entrada y diseño coherente con la temática.
- Nombre, fecha, hora y datos de la invitación compuestos de forma dinámica.
- Música temática, narración inicial y despedida cuando los audios existen.
- Guardar, descargar y compartir la invitación.
- Acceso opcional a “Conoce al protagonista” cuando el perfil está publicado.
- Experiencia mobile-first y fallback visual si falta un video o un audio.

### Diferenciadores del Plan Full que sí existen

- Invitación Automática con playlist de videos reales de la temática.
- Narración por capítulo cuando el tema tiene sus MP3 aprobados.
- Galería privada con PIN cuando está habilitada.
- Misión Full, video estrella y atlas del runner únicamente donde el tema tenga
  esos recursos aprobados.
- Operador presente, según la definición comercial vigente.

## Gate por plan — IMPLEMENTADO 2026-08-21

La asignación Básico/Scroll y Full/Automática ya no depende del enlace. El gate
vive en `public/invitacion.php`, justo antes de resolver el hero:

- `cb_invitation_service_plan($invitation['party_id'])` lee `service_plan` de
  `cc_parties` y **falla cerrado a `booth`**: ante cualquier error de base o
  fiesta sin resolver se entrega el plan menor, nunca de más.
- `booth` → `hero=scroll`, `capitulos=1`. `full` → `hero=auto`, `capitulos=auto`.
- `hero` y `capitulos` dejaron de leerse de `$_GET` en los tres puntos donde se
  usaban. Solo se respetan acompañados de la firma `qa`
  (`cb_invitation_preview_mac`, HMAC de `id|hero|capitulos`), que emite el admin.

Probado el 2026-08-21 contra manipulación de URL: una invitación `booth` con
`&hero=auto&capitulos=auto` sigue entregando scroll, con firma inválida también,
y solo cambia con la firma correcta. Una `full` no se puede degradar a scroll sin
firma. Los capítulos siguen la misma regla: la playlist de videos solo aparece en
`full`.

En `public/admin/invitations.php` cada invitación muestra ahora el enlace
reconstruible (`cb_invitation_share_token`, sirve aunque se haya perdido el token
en claro), qué versión entrega según el plan, y dos botones de vista previa
**Ver Básico** y **Ver Full** para comparar. Esos dos llevan la firma `qa`: son
para uso interno, no se le mandan al cliente.

La firma de vista previa no caduca a propósito. Lo que protege no es un secreto
sino que el plan no se suba a mano desde la barra del navegador; quien reciba un
enlace de vista previa ve esa variante y nada más.

Los parámetros siguen sin aparecer en copies, capturas ni tutoriales públicos.

### Qué ve cada plan, por tema (2026-08-23)

El Básico avanza por **capítulos**: imágenes fijas en `themes/<tema>/invitation/chapters/`.
Solo Carreras las tiene (9 archivos: `scene-01..05`, `conn-01..04`). Hielo no, y sin
esa carpeta el recorrido saltaba directo a la lámina de la invitación, sin
personajes.

Luis decidió el 2026-08-23 que el Básico de un tema sin capítulos use la playlist
de videos. Lo que separa los planes en ese caso es el **hero**: en Básico el
invitado lo avanza con el dedo y recién después la historia corre sola; en Full
arranca solo desde el principio. Carreras no cambia: tiene capítulos y su lógica
aprobada se respeta tal cual.

| Tema | Básico (`booth`) | Full (`full`) |
|---|---|---|
| Carreras | hero scroll + 9 capítulos | hero auto + playlist de videos |
| Hielo | hero scroll + playlist de videos | hero auto + playlist de videos |
| kpop | hero scroll + 6 saludos | hero auto + los mismos 6 |
| tropical | hero scroll + 6 saludos | hero auto + los mismos 6 |
| familia-canina | hero scroll + 8 saludos | hero auto + los mismos 8 |

Los tres temas nuevos entraron el 2026-08-23. Sus saludos ya existían en la raíz
del tema desde el kiosco; lo que faltaba era el orden en `$playlistOrdersByTheme`,
el intro con el logo corregido y un hero scroll.

El hero scroll de esos tres se derivó del `invitation-motion-v1.mp4` de cada tema
con ffmpeg (540×960, 30 fps, un keyframe por cuadro, sin audio), replicando el
spec del de Carreras. No se generó contenido nuevo ni se gastaron créditos de IA.

Cuidado con un detalle que costó encontrar: cuando un tema no tiene candidatos
`candidate-*-scroll/auto`, quedan activos sus dos heroes por defecto a la vez y
gana el scroll, así que el Full se veía idéntico al Básico. `invitacion.php`
ahora limpia el que no corresponde según el plan.

### Saludos de Hielo promovidos (2026-08-23)

Luis aprobó pasar las tomas de Higgsfield a definitivas. `$playlistOrdersByTheme['hielo']`
apunta ahora a `invitation/candidates/`: `saludo-elsa-v2`, `saludo-anna-v3`,
`saludo-olaf-v2`, `saludo-kristoff-v2`, `saludo-sven-v3`, `saludo-bruni-v3`, más
`despedida-hielo.mp4`.

Los saludos originales siguen en la raíz del tema y `$playlistCandidateOrdersByTheme`
sigue existiendo, así que revertir es cambiar esas seis rutas y nada más.

## Posicionamiento

### Idea central

**La fiesta comienza cuando abren la invitación.**

CumpleClick no envía una tarjeta estática: entrega una experiencia temática que
abre el mundo de la celebración, presenta al protagonista y conduce a la fiesta.

### Audiencia inicial

- Madres, padres y cuidadores de 28–45 años en Chile.
- Familias que organizan un cumpleaños infantil con 2–6 semanas de anticipación.
- Motivaciones: sorprender, ahorrar coordinación, entregar información clara y
  ofrecer algo distinto desde el primer contacto.

### Embudo recomendado

1. **Descubrimiento:** Reels/Stories verticales que muestran el sobre abriéndose.
2. **Consideración:** comparación visual Scroll vs. Automática.
3. **Decisión:** precio, qué incluye cada plan y CTA único a WhatsApp.
4. **Retargeting:** grabación real de “Conoce al protagonista”, compartir y
   guardar la invitación.

## Reel maestro — “La fiesta empieza antes”

Formato: 9:16, 30–40 segundos, subtítulos siempre visibles y CTA a WhatsApp.

| Tiempo | Imagen | Voz / texto principal |
|---|---|---|
| 0–5 s | Clip IA: sobre que abre un portal temático | **“¿Y si la fiesta comenzara antes del cumpleaños?”** |
| 5–10 s | Grabación real del sobre CumpleClick y nombre dinámico | “En CumpleClick, todo empieza con la invitación.” |
| 10–17 s | Grabación Plan Básico: avance táctil por capítulos | **“Plan Básico: ellos descubren la historia con el dedo.”** |
| 17–24 s | Transición IA Scroll → Automática y grabación Full | **“Plan Full: la historia cobra vida y avanza sola.”** |
| 24–30 s | Perfil del protagonista, favoritos y sugerencias de regalo | “Y pueden conocer al protagonista antes de llegar.” |
| 30–35 s | Compartir/guardar + vistazo breve al Photo Booth | “Invitación, experiencia y recuerdos en un solo mundo.” |
| 35–40 s | Endcard de marca | **“Elige tu experiencia. Reserva tu fecha por WhatsApp.”** |

### Locución corrida

> ¿Y si la fiesta comenzara antes del cumpleaños? En CumpleClick, todo empieza
> con una invitación inmersiva creada para su temática. En el Plan Básico, cada
> invitado descubre la historia con el dedo. En el Plan Full, la experiencia
> cobra vida y avanza sola con videos y narración. Además, pueden conocer al
> protagonista, guardar la invitación y compartirla. CumpleClick: la fiesta
> comienza desde el primer toque. Reserva tu fecha por WhatsApp.

## Dos reels para explicar cada plan

### Reel A — Plan Básico / Scroll (15–20 s)

- Hook: “Una invitación que se descubre con el dedo.”
- Mostrar: sobre → nombre dinámico → tres capítulos controlados por scroll →
  datos → compartir.
- Beneficio: participación, sorpresa y control del ritmo.
- Cierre: “Plan Básico · Invitación Scroll · Reserva por WhatsApp”.

### Reel B — Plan Full / Automática (15–20 s)

- Hook: “Toca una vez. La historia comienza.”
- Mostrar: sobre → reproducción automática → dos o tres clips con narración →
  perfil del protagonista → compartir.
- Beneficio: experiencia más cinematográfica y manos libres.
- Cierre: “Plan Full · Invitación Automática · Reserva por WhatsApp”.

## Guía de grabación de pantalla para Luis

1. Grabar vertical en 1080×1920; 720×1280 es el mínimo aceptable.
2. Usar una invitación demo, nunca datos reales de clientes ni tokens visibles.
3. Activar “No molestar”; ocultar barra de direcciones, notificaciones y hora si
   distraen.
4. Hacer tomas separadas de 4–8 segundos, no una navegación larga:
   - apertura del sobre;
   - hero con nombre;
   - tres avances Scroll;
   - reproducción Automática;
   - ficha del protagonista;
   - compartir/guardar;
   - cierre hacia el Photo Booth.
5. Dejar un segundo quieto al inicio y al final de cada toma para facilitar el
   montaje.
6. Grabar sin voz. Locución, música y textos se agregan después para mantener
   nitidez y poder reutilizar el material.
7. No mostrar direcciones, teléfonos, colegios, rutinas ni fotografías de niños
   sin autorización específica.

## Seedance 2.5 — generación manual en Unlimited

Luis genera manualmente desde el generador principal de `higgsfield.ai`, con el
toggle **Unlimited** activo. No automatizar la web. No usar MCP, CLI, Canvas ni
Supercomputer si el objetivo es conservar los créditos.

Configuración recomendada para mantener calidad y estabilidad:

- Modelo: Seedance 2.5.
- Formato: 9:16 vertical.
- Resolución: 720p.
- Duración: **15 segundos por clip**. Un montaje final de 30–40 segundos combina
  dos clips IA con grabaciones reales. Generar 30 segundos en una sola toma es
  posible, pero aumenta el riesgo de deriva visual, texto/logo deformado y menor
  control de edición.
- Audio: ambiente y efectos, sin voces ni música con letra; la locución se agrega
  en post.
- Referencia temática segura: `public/themes/kpop/fondo-sala.jpg`.
- Referencia de marca obligatoria:
  `design/logo/logo-icon-wordmark.png` (PNG transparente, recorte ajustado,
  símbolo + nombre). No usar el master render opaco como watermark.

### Bloque obligatorio de logo

Este bloque recupera el patrón que ya funcionó en una generación CumpleClick de
Seedance 2.5 (`87a678ea-426a-4837-b3af-f82983225441`):

```text
The provided reference image is the CumpleClick logo. It must appear as a very
small, subtle, translucent watermark in the top-left corner of the frame, like a
professional TV watermark. Never center it, never enlarge it.

MANDATORY: The provided reference image is the ONLY official CumpleClick logo.
Keep the exact shape, colors and lettering from the reference. The logo must
remain stable in the same top-left position for the entire video. Never animate,
crop, redesign or replace it with a different logo or generic icon.
```

Si el generador no mantiene el logo fiel, no repetir múltiples veces el mismo
prompt: usar el video aprobado sin logo y aplicar
`design/logo/logo-icon-wordmark.png` como overlay en postproducción.

### Prompt 1 — apertura WOW, 15 segundos

Adjuntar el fondo K-Pop como referencia de ambiente y el logo maestro como
referencia de marca.

```text
ONE CONTINUOUS SHOT — 15 seconds — vertical 9:16 — premium children's
celebration commercial.

Use the supplied empty event-stage background only as the environmental visual
reference: cyan and hot-pink balloon arch, silver star garlands, violet and
electric-blue spotlights, glossy floor and colorful confetti. A sealed elegant
invitation envelope floats at the center.

0–4 seconds: the camera glides through floating glitter and soft cyan-magenta
light streaks toward the sealed envelope. Build anticipation with elegant depth
and restrained motion.

4–9 seconds: the envelope opens naturally and releases a luminous portal. The
balloon arch, star garlands and stage lights gain cinematic depth as if the
invitation has opened a complete celebration world.

9–13 seconds: travel gently through the portal into a magical, original party
space with balloons, confetti and volumetric light. Keep the center and lower
third clean for post-production copy.

13–15 seconds: settle into a stable bright hero frame with a soft celebratory
light pulse, suitable for a clean edit into real CumpleClick screen recording.

Joyful, exciting, age-appropriate, high-end cinematic lighting, realistic
particles, smooth camera, no cuts, no people or children, no readable scene
text, no UI, no personal information, no franchise references and no protected
characters.

[PASTE THE MANDATORY CUMPLECLICK LOGO BLOCK HERE]
```

### Prompt 2 — Scroll se transforma en Automática, 15 segundos

Adjuntar el logo maestro. La referencia temática es opcional.

```text
ONE CONTINUOUS SHOT — 15 seconds — vertical 9:16 — premium visual metaphor for
two immersive invitation experiences.

0–5 seconds: layered illustrated celebration panels move upward in response to
an invisible finger-like scroll gesture. The movement is tactile, elegant and
easy to understand; each panel reveals balloons, lights, confetti and a new
chapter. No phone and no simulated interface.

5–10 seconds: the panels accelerate and fold into luminous cinematic frames.
Their motion transforms seamlessly from user-controlled vertical movement into
a self-playing sequence of living lights, particles and dimensional party
scenes.

10–13 seconds: the automatic sequence advances smoothly by itself through two
original celebration environments, communicating effortless cinematic motion.

13–15 seconds: both visual languages resolve into one polished violet, cyan and
warm-gold celebration space with clear room for the post-production labels
“Plan Básico · Scroll” and “Plan Full · Automática”. Do not render those words
inside the generated video.

Premium mobile-first advertising aesthetic, joyful and age-appropriate, smooth
transformation, no abrupt cuts, no people, no readable scene text, no phone UI,
no personal information, no franchise references and no protected characters.

[PASTE THE MANDATORY CUMPLECLICK LOGO BLOCK HERE]
```

## Copies iniciales para Meta/Instagram

### Ángulo 1 — la fiesta comienza antes

- Texto principal: “La fiesta no empieza cuando llegan los invitados. Empieza
  cuando abren una invitación creada para sorprenderlos.”
- Titular: “Invitaciones que cobran vida”
- CTA: “Reserva por WhatsApp”

### Ángulo 2 — elige la experiencia

- Texto principal: “¿Prefieres que descubran la historia con el dedo o que la
  magia avance sola? Conoce nuestras invitaciones Scroll y Automática.”
- Titular: “Básico Scroll o Full Automática”
- CTA: “Elige tu plan”

### Ángulo 3 — información útil sin perder el WOW

- Texto principal: “Nombre, fecha, ubicación, favoritos y sugerencias de regalo,
  dentro de una experiencia que sí dan ganas de abrir.”
- Titular: “Todo lo importante, con magia”
- CTA: “Cotiza tu fecha”

## Plan de publicación y aprendizaje

### Semana 1 — validación orgánica

- Publicar Reel maestro.
- Stories con encuesta: “¿Scroll o Automática?”.
- Medir retención a 3 segundos, reproducciones completas y respuestas.

### Semana 2 — educación

- Publicar Reel Básico y Reel Full en días distintos.
- Guardar una Story destacada “Invitaciones”.
- Responder objeciones con una comparación simple de planes.

### Semana 3 — conversión

- Carrusel con planes, precio y diferencias.
- Retargeting orgánico a quienes vieron 50 % o más del Reel maestro.
- CTA único a WhatsApp con mensaje precargado según plan.

### Pauta, solo después de validar

No invertir antes de comprobar que el Reel maestro retiene y provoca consultas.
Primera prueba sugerida en Meta:

- Audiencia: padres/cuidadores 28–45, radio geográfico realmente atendible.
- Creativos: Scroll vs. Automática; cambiar solo el hook, no cinco variables.
- Objetivo: conversaciones de WhatsApp, no reproducciones.
- Métricas: retención 3 s, CTR, costo por conversación y reservas confirmadas.
- No declarar “el mejor”, “único” ni otras afirmaciones sin evidencia.

## Gate de publicación

- Confirmar nombres finales de planes y precios.
- Implementar o aceptar expresamente el pendiente de autorización por plan.
- Probar ambas variantes en móvil real y con movimiento reducido.
- Verificar que ningún token, dirección o dato de prueba aparezca en pantalla.
- Luis genera manualmente y comparte los videos; Codex no consume créditos.
- Añadir textos en postproducción, nunca dentro del video generativo.
- No desplegar, publicar ni pautar sin autorización de Luis.
