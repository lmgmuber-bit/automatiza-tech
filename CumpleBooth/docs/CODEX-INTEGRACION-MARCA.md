# CODEX — Integración de la identidad CumpleClick con el trabajo de temáticas

> **CIERRE 2026-07-19 — Claude retomó y TERMINÓ este ticket** (Codex se quedó
> sin tokens a mitad de camino). Todo lo de abajo quedó implementado y
> verificado; Codex solo debe revisar, no re-ejecutar:
>
> 1. **Portada familia-canina**: título "¡Bienvenidos…" y guirnalda subidos
>    DENTRO del cartel crema del fondo (`.intro--familia-canina` en
>    `src/styles.css`: título 18.5%, guirnalda 34%); la etiqueta del nombre
>    bajó a la torta (68%) — antes tapaba a los cachorros. Verificado con
>    screenshot real (Chrome headless 540×960 contra dist/).
> 2. **Frase de agradecimiento en la foto**: ya existía en `composeImage`
>    ("Muchas gracias {invitado} / por venir a la fiesta de {nombre}") — sin
>    cambios, confirmada en código.
> 3. **azulita-cut.png (Bluey) con AMBOS brazos**: regenerado vía Higgsfield
>    (nano_banana_pro: figura idéntica sobre magenta plano, ref azulita.jpg) +
>    chroma key local con ffmpeg (colorkey FF00FF) + crop a bounding box
>    (646×894). El recorte viejo sin brazo quedó respaldado en
>    `design/renders/azulita-cut-v1-sin-brazo.png`. JPGs/videos intactos.
> 4. **Pase de artista condicional**: nuevo `photoSession.characters`
>    (["Bluey","Bingo"] en `themes.json`), passthrough sanitizado en
>    `lib.php` (solo nombres declarados en la temática), `themeFlow.js` ahora
>    expone `afterSpinner(nombre)`/`afterCharacter(nombre)`; App.jsx pasa el
>    ganador de la ruleta. Bluey/Bingo → alfombra → saludo → cámara; el resto
>    → saludo directo → cámara. Sin lista, el pase aplica a todos (compat).
>    Tests: `tests/frontend/themeFlow.test.mjs` actualizado + caso nuevo (9/9).
> 5. **Marca oficial "El globo dulce" integrada**:
>    `public/brand/cumpleclick-mark.svg` y `cumpleclick-lockup.svg` ahora son
>    el globo oficial (con width/height explícitos para canvas);
>    `BRAND_LOGO_SRC` → cumpleclick-mark.svg (chip de intro EN LA DERECHA por
>    pedido de Luis, watermark de foto y diploma); header del admin con
>    isotipo + "CumpleClick Admin"; footer con isotipo en `galeria.php` y
>    `ver.php` (página del QR). Verificado en intros de Bluey y Cars.
> 6. **Suite completa verde tras todo**: backend 77/77, frontend 9/9, lint 36
>    + 7/7 smoke entrypoints, `check-dist-parity.php` exit 0 (93 archivos,
>    dist reconstruido). Fiesta QA `demo-bluey` (familia-canina, PIN galería
>    1234) creada en la BD local vía `cb_save_parties` para probar el flujo.
>    Sin commit/push/deploy. Créditos Higgsfield usados en esto: ~4.
>
> **Pendiente humano (Luis)**: probar en tablet real el flujo completo de
> demo-bluey (ruleta → pase solo con Bluey/Bingo → foto → diploma) y validar
> las posiciones de portada en la pantalla física.

**De:** Claude (diseño/CM) · **Para:** Codex (planner/ejecutor temáticas) · **Fecha:** 2026-07-18
**Aprobado por Luis:** identidad oficial = **"El globo dulce"** (globo caramelo violeta→fucsia que es lente de cámara, aro amarillo, hilo dorado con corazón).

---

## 1. Qué existe ya (no rehacer)

Todo el material de marca vive en **`design/`** (fuente de verdad: `design/MANUAL-DE-MARCA.md`):

| Asset | Ruta | Estado |
|---|---|---|
| Manual de identidad | `design/MANUAL-DE-MARCA.md` | ✅ completo (logo, paleta, tipografía, voz, aplicaciones, precios) |
| Tokens CSS | `design/tokens.css` | ✅ listos para importar |
| Logo master (render) | `design/logo/cumpleclick-logo-master-render.png` | ✅ elegido por Luis |
| Isotipo SVG | `design/logo/cumpleclick-globo-mark.svg` | ✅ verificado en Chrome |
| Lockup SVG (símbolo+wordmark) | `design/logo/cumpleclick-globo-lockup.svg` | ✅ verificado |
| Alternativas (NO logo) | `design/renders/alt-*.png` | ✅ solo campañas especiales |
| Plan redes sociales | `design/social/ESPECIFICACIONES.md` | ✅ IG prioritario |
| Frames de video 9:16 | `design/video/frame-*.png` | ✅ 3 frames (sala-kiosco, flash-mágico, endcard) |
| Clips promo 5s 9:16 | `design/video/clip-*.mp4` | ⏳ en generación Higgsfield (ver §4) |
| Filosofía de diseño | `docs/BRAND-FILOSOFIA-CUMPLECLICK.md` | ✅ |

Paleta corta: violeta `#8B5CF6` · tinta `#4C2882` · fucsia `#D6307F` · amarillo `#FBBF24` · oro `#E8A317` (solo detalles) · crema `#FFF8EC`. Tipografía: Baloo 2 (ya self-hosted en el producto).

---

## 2. Regla clave: marca vs. temática (no mezclar)

- **La identidad CumpleClick** viste TODO lo que no es la fiesta en sí: landing comercial, admin, página del QR (`config.php`), galería de papás (chrome exterior), diplomas (cabecera/pie), redes, materiales de venta.
- **La temática de cada fiesta** (carreras, familia-canina, etc.) manda DENTRO del kiosco (`?p=<slug>`): sus colores por CSS vars de `themes.json` no se tocan.
- Punto de encuentro: el **diploma** y la **galería** pueden llevar el isotipo chico (marca de agua) + filete oro; nada más. El globo NUNCA compite con los personajes de la temática.

## 3. Tareas de integración propuestas para Codex

1. **Galería + página QR**: agregar isotipo (`cumpleclick-globo-mark.svg`) chico en el footer con "CumpleClick" en Baloo 2 (tokens de `design/tokens.css`). Hoy dice solo texto plano/logo AT.
2. **Diploma canvas**: watermark del isotipo (esquina, ~48px, opacidad ~0.85) — respetar zona de respeto (manual §2).
3. **Admin/backoffice**: header con lockup pequeño (cosmético, baja prioridad).
4. **Landing CumpleClick** (pendiente del roadmap): construir sobre `tokens.css` + hero con `frame-endcard.png` o el clip endcard como video de fondo; precios del manual §7.
5. **Temáticas nuevas (familia-canina en curso)**: sin cambios en tu flujo — solo verificar que ningún asset de temática incluya el globo de marca (la marca va en el chrome, no en la escena).
6. Al deployar: `design/` NO se sube (interno). Solo los assets que se integren a `public/` pasan por el build/parity normal.

## 4. Video promo (estado Higgsfield al cierre de este doc)

Guion 3 planos × 5s, 9:16, mudos (música en post — usar la de la temática o stock libre):

| # | Clip | Start frame | Job Higgsfield | Estado |
|---|---|---|---|---|
| 1 | Hook: dolly-in al kiosco (tablet VERTICAL, como el producto) | `frame-sala-kiosco.png` | `85a22671-c8e9-4560-bebf-8f18bd8bc424` | ✅ `design/video/clip-01-kiosco.mp4` |
| 1b | Alternativa tablet horizontal (no usar en el promo principal) | `frame-sala-kiosco-alt-horizontal.png` | `b390d0bb-523d-4270-838b-e7044d19aca2` | ✅ `design/video/clip-01-kiosco-alt-horizontal.mp4` |
| 2 | Magia: globo-lente dispara flash + confeti | `frame-flash-magico.png` | `1d17581f-212b-4466-9644-6c3ab8296b8d` | ✅ `design/video/clip-02-flash.mp4` |
| 3 | Endcard: logo flotando + wordmark quieto | `frame-endcard.png` | `cfa21cf3-46a8-4783-a135-4af10fcb3e91` | ✅ `design/video/clip-03-endcard.mp4` |

Los 3 MP4 verificados con ffprobe: h264, 716×1284 (9:16), 5.04s, mudos.
El clip 3 sirve además como **bumper** standalone de apertura/cierre de reels.

Post-producción sugerida (ffmpeg, mismo flujo del reel AT):
- Concatenar 1→2→3, crossfade 0.3s.
- Texto overlay (NO generado por IA, para nitidez): plano 1 "El photo booth que tus hijos no van a olvidar", plano 2 "Cada invitado se lleva su foto con su personaje favorito", plano 3 CTA "Agenda tu fecha 📲 (WhatsApp)". Baloo 2, tinta violeta sobre crema / blanco sobre foto.
- Los MP4 descargados quedarán en `design/video/clip-01-kiosco.mp4`, `clip-02-flash.mp4`, `clip-03-endcard.mp4` (si aún no están, bajarlos con los job ids de arriba vía MCP Higgsfield).

## 5. Presupuesto Higgsfield usado (informativo)

8 imágenes ≈ 14 cr + 3 videos = 15 cr → **~29 cr total**; saldo restante ≈ 166 cr. Preflight `get_cost:true` siempre antes de generar.

## 6. Restricciones vigentes

- Nada de este trabajo toca `Docs/ORCHESTRATION/*` ni el ticket AT-CUMPLECLICK-007 (Gate A cerrado, en revisión tuya).
- No deploy, no commit/push (decisión de Luis al cierre).
- Caras de niños reales: nunca en materiales sin autorización escrita (manual social).
