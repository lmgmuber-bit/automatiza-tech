# Campaña Fase 1 — piezas Higgsfield (ver ../../ESTRATEGIA-VIDEO-MARKETING.md)

Ejecución 2026-07-19. Todos los clips: 5s, 9:16, mudos (`cinematic_studio_video_v2`,
mode std, sound off). Textos y música SIEMPRE en post (ffmpeg, Baloo 2).

| Pieza | Archivo | Job Higgsfield | Estado |
|---|---|---|---|
| V1 frame — "La fiesta que se detuvo" | `v1-frame-fiesta-detenida.png` | `6b0c1eea` (reroll de `5a9f01f8`, que salió rotado) | ✅ |
| V1 clip — niños miran el flash | `v1-clip-fiesta-detenida.mp4` | `b0553cda-006e-4e6e-8ce2-e06ebd48d189` | ✅ |
| V4 frame — etiquetas de fechas | `v4-frame-fechas.png` | `dc57fd62` | ✅ |
| V4 clip — etiqueta reclamada | `v4-clip-fechas.mp4` | `7f087727-f067-4876-997d-4a46a84b47d3` | ✅ |
| V3a clip — push-in sala Carreras | `v3a-clip-sala-carreras.mp4` | `62f7811f-d2d3-45b7-a362-4ba48b22af42` | ✅ |
| V3b clip — push-in sala Bluey | `v3b-clip-sala-bluey.mp4` | `d6b06564-85a0-4f7e-9484-508e0e4f12a9` | ✅ |

Los 4 MP4 verificados con ffprobe: h264, ~720×1280 (9:16), 5.04s, mudos.
Costo real de la fase: 4 frames (8 cr, incl. 1 reroll por imagen rotada) +
4 clips (20 cr) = **28 cr**.

- V2 (demo real, 0 cr) = grabación de pantalla del kiosco → tarea de post
  (Codex/ffmpeg), no Higgsfield.
- Overlays V1: "¿Qué hace que TODOS los niños corran al mismo lugar?" → bumper.
- Overlays V4: "Agosto: 3 fechas" → "2 fechas" → "Mágico $69.990 · Premium
  $99.990" → CTA WhatsApp. Fechas se actualizan por texto: el clip es eterno.
- Cierre de todos los reels: `../clip-03-endcard.mp4` (bumper oficial).
