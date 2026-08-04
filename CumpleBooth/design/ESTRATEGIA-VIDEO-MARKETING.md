# CumpleClick — Estrategia de video marketing con Higgsfield

**v1.0 — 2026-07-18 · Objetivo: enganchar padres y convertirlos en reservas por WhatsApp, gastando el mínimo de créditos posible.**

Saldo Higgsfield al escribir esto: **~166 cr**. Presupuesto de esta estrategia: **Fase 1 = 34 cr máx.** Nada se genera sin preflight `get_cost:true`.

---

## 1. A quién le vendemos y qué le duele

- **Decisor**: madres/padres 28–45, Chile, organizando un cumpleaños infantil (se decide 2–4 semanas antes de la fecha). Compran *tranquilidad + wow*: quieren que la fiesta se recuerde y no trabajar ellos de fotógrafos.
- **Dolores que atacamos**: "las fotos de la fiesta siempre quedan malas/desordenadas", "los niños se aburren a mitad de fiesta", "quiero algo distinto que no sea el mismo payaso/cama elástica".
- **Promesa única (USP)**: *cada invitado se va con SU foto junto a su personaje favorito, al instante*. Precio claro: Mágico $69.990 / Premium $99.990.
- **Canal**: Instagram Reels (descubrimiento) → WhatsApp (conversión). El link de WhatsApp con mensaje precargado es el único CTA, siempre.

## 2. El embudo en 3 niveles (cada video tiene UN trabajo)

| Nivel | Trabajo del video | Formato | Frecuencia |
|---|---|---|---|
| **TOFU — Enganchar** | Parar el scroll en 1.5s con magia visual | Reel 7–15s, loop-perfecto | 2/semana |
| **MOFU — Convencer** | Mostrar cómo funciona y las temáticas | Reel 15–30s demo | 1/semana |
| **BOFU — Convertir** | Precio + urgencia de fechas + CTA | Story/Reel 10s | 1/semana + stories diarias |

**Regla de oro del hook**: los primeros 1.5 segundos deben tener movimiento + color de marca + una pregunta implícita ("¿qué es eso?"). El flash del globo-lente ES nuestro hook natural.

## 3. Sistema de ahorro de créditos (LEER ANTES DE GENERAR)

1. **Lo real es gratis**: la demo del producto se graba del kiosco REAL (Playwright/OBS sobre `http://localhost/...`, flujo ya probado en el reel AT). Higgsfield solo para lo que no se puede filmar: el mundo mágico de la marca. **Nunca gastar créditos en mostrar la interfaz.**
2. **Assets existentes = start frames gratis**: los fondos de temáticas (`themes/carreras/*.jpg`, familia-canina cuando Codex la termine) y los 4 frames de `design/video/` ya están pagados. Un clip nuevo desde asset existente = solo 5 cr.
3. **Flujo imagen-primero**: reroll y ajustes SIEMPRE en imagen (2 cr) hasta que el frame esté perfecto; recién entonces video (5 cr). Nunca reroll de video por un detalle que se podía ver en el frame.
4. **Parámetros fijos**: `cinematic_studio_video_v2`, mode std, `sound: off`, 5s, 9:16. Música/textos en post con ffmpeg (gratis y más nítido). Preflight `get_cost:true` en cada llamada.
5. **Reutilización agresiva**: el bumper (`clip-03-endcard.mp4`) cierra TODOS los reels → cada video nuevo necesita solo 1 clip nuevo, no 3. Máximo 2 jobs concurrentes (límite del plan).
6. **Regla de corte**: si un video orgánico no supera 1.000 vistas en 7 días, no se le hace secuela; se itera el hook, no la producción.

## 4. Fase 1 — Paquete de lanzamiento (34 cr máx.)

| # | Video | Nivel | Recursos nuevos | Costo |
|---|---|---|---|---|
| V1 | **"La fiesta que se detuvo"** — hook mágico | TOFU | 1 frame + 1 clip | 7 cr |
| V2 | **"Así funciona"** — demo 3 pasos con pantalla real | MOFU | 0 (grabación real + bumper existente) | 0 cr |
| V3 | **"Elige tu mundo"** — carrusel de temáticas | MOFU | 2 clips desde fondos existentes de carreras | 10 cr |
| V4 | **"Quedan pocas fechas"** — urgencia + precio | BOFU | 1 frame + 1 clip | 7 cr |
| V5 | **Reserva** para 1 reroll de imagen + imprevistos | — | — | 10 cr |

Los 3 clips ya generados (kiosco vertical, flash, endcard) se montan como **Reel 0 de presentación** (0 cr, solo post ffmpeg — ya asignado a Codex).

## 5. Prompts listos para ejecutar (copiar/pegar al MCP)

> Todos con `get_cost:true` primero. Imágenes: `nano_banana_pro`, `aspect_ratio: "9:16"`. Videos: `cinematic_studio_video_v2`, `duration: 5`, `sound: "off"`, `aspect_ratio: "9:16"`, `medias: [{role: "start_image", value: "<job_id_del_frame>"}]`. Referencia de estilo cuando se indica: job `19f962cb-9177-4eac-a8a0-1c107e81bc8c` (logo master) con role `image`.

### V1 — Frame "La fiesta que se detuvo" (2 cr)
```
Vertical 9:16, high-end collectible vinyl toy-style photograph: a children's birthday
party hall in violet, fuchsia and warm yellow candy colors, cream walls. Six vinyl toy
child figures (diverse, generic, no branded characters) frozen mid-celebration, all
turning their heads toward a warm golden glow coming from off-frame right, faces lit
with wonder. Confetti suspended mid-air. Gift boxes and balloon garland. Dreamy shallow
depth of field, premium toy-studio lighting. IMPORTANT: no text, no letters, no real
children, no branded characters.
```
### V1 — Motion (5 cr)
```
The warm golden glow from off-frame intensifies like a camera flash charging, the toy
children lean toward it with wonder, confetti drifts slowly, subtle camera push-in
toward the glow. Magical anticipation, elegant slow motion. No text.
```
**Post**: texto overlay "¿Qué hace que TODOS los niños corran al mismo lugar?" → corte al bumper. Loop: el flash final enlaza con el inicio.

### V2 — Demo real (0 cr)
Grabación de pantalla del kiosco real (tema carreras): elegir nombre → ruleta de personaje → cuenta regresiva → foto con personaje → diploma. 3 cortes de 4s + bumper. Overlays: "1. Elige tu personaje" / "2. ¡Click!" / "3. Se lleva su recuerdo". *Asignado a Codex en `docs/CODEX-INTEGRACION-MARCA.md`.*

### V3 — Temáticas (5 cr por clip, empezar con 2)
Subir `public/themes/carreras/fondo-sala.jpg` vía `media_upload` como start frame:
```
Gentle cinematic push-in through the themed party hall, balloons swaying softly,
confetti sparkling, warm inviting light shifting. The golden picture frame on the back
wall glows subtly, inviting. Elegant, calm. No text, no people.
```
Repetir con el fondo de familia-canina cuando Codex entregue sus assets. Post: overlay "Temática Carreras 🏁" / "…y 9 mundos más" + bumper.

### V4 — Frame urgencia/precio (2 cr)
```
Vertical 9:16 minimal brand composition: the glossy candy balloon-lens logo (use
reference image) floating top-third over cream background, below it a stack of three
elegant gift-tag style cards in violet, fuchsia and yellow candy enamel, blank surfaces,
one tag slightly lifted as if just taken. Sparse confetti dots. Premium vinyl toy
finish, soft studio light, lots of negative space bottom. No text anywhere.
```
### V4 — Motion (5 cr)
```
The top gift tag gently lifts and floats away as if claimed, the remaining tags settle,
the balloon-lens bobs softly, confetti twinkles. Calm, premium, subtle motion. No text.
```
**Post**: overlays sobre las etiquetas "Agosto: 3 fechas" → "2 fechas" → "Mágico $69.990 · Premium $99.990" → CTA WhatsApp. (Las fechas cambian por texto, el video se reutiliza cada mes → un solo gasto para siempre.)

## 6. Calendario de ejecución (4 semanas)

- **Semana 1**: publicar Reel 0 (presentación) + abrir IG con avatar/bio de `social/ESPECIFICACIONES.md`. Stories: encuesta "¿temática favorita?".
- **Semana 2**: V1 (hook) lunes + V2 (demo) jueves. Story diaria countdown de fechas.
- **Semana 3**: V3 (temáticas) + repost del mejor performer con hook alternativo (solo cambia el texto = 0 cr).
- **Semana 4**: V4 (precio/urgencia) + evaluar métricas. Si hay ≥1 reserva o ≥5 conversaciones WhatsApp: pasar a Fase 2.

**Fase 2 (condicional, ~30 cr)**: 2 clips más de temáticas nuevas + 1 hero shot `cinematic_studio_3_0` (25 cr) SOLO si se decide invertir en pauta Meta Ads; para orgánico no se justifica.

## 7. Métricas que mandan

| Métrica | Meta 30 días | Acción si no llega |
|---|---|---|
| Retención primeros 3s | >60% | Cambiar hook (texto/corte), no regenerar |
| Vistas por reel | >1.000 | Iterar horario y hashtags |
| Clicks al WhatsApp | >30 | Reforzar CTA en overlay y bio |
| Conversaciones iniciadas | >5 | Revisar mensaje precargado |
| Reservas | ≥1 | — (con 1 reserva de $69.990 la campaña entera se paga sola) |

## 8. Reglas duras (heredadas del manual)

- Sin caras de niños reales, jamás. Los "niños" son figuras vinyl toy genéricas.
- Sin personajes de franquicia en videos de marca (las temáticas se nombran genérico: "Carreras", "Héroes").
- Texto SIEMPRE en post-producción, nunca generado en video (se distorsiona).
- Máx. 2 emojis por copy, 3 hashtags visibles, CTA único a WhatsApp.
