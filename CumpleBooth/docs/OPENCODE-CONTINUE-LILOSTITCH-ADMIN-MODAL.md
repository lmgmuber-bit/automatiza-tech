# OPENCODE: CONTINUAR LILO & STITCH + MODAL PREVIEW ADMIN

> **Actualización 2026-07-21**: TAREA 2 (modal preview) **ya está completa** —
> OpenCode la implementó, Claude la verificó en navegador (desktop+mobile). No
> repetir. Queda pendiente solo la TAREA 1 (assets tropical). Para los prompts
> de los 6 personajes + banner/sala, usa la versión en inglés ya guardada en
> `cc_theme_prompts` (ver `docs/OPENCODE-HANDOFF-STITCH-Y-MODAL.md` §3 y §0.1)
> en vez de la tabla en español de la Tarea 1 más abajo, para mantener el mismo
> estilo de prompt que el resto del catálogo. El resto del pipeline (pasos
> A, C–L) sigue vigente tal cual.

## LINEA DIVISORIA: qué hizo Claude vs qué haces tú

### CLAUDE COMPLETÓ (NO TOCAR, YA ESTÁ EN PRODUCCIÓN LOCAL):
- Video `artist-teaser.mp4` (5s, 432KB) — los 4 perros bailando, reemplaza versión anterior con orejas grandes
- Poster `artist-teaser.jpg` (122KB) — backup si falla el video
- `teaserLabel: "La familia Heeler"` en themes.json
- Integración en App.jsx:885-897 — `<video>` debajo de la ruleta con loop+muted+autoplay
- themeFlow.js:32 — resuelve teaserLabel desde themes.json o fallback a nombres
- Tests themeFlow.test.mjs — 5 tests cubriendo photoSession, teaserLabel, red carpet
- Build + parity OK, 10/10 tests pass

### TÚ DEBES COMPLETAR (2 tareas):

---

## TAREA 1: TEMA LILO & STITCH (tropical)

### OBJETIVO
Crear el tema `tropical` (Aventura Tropical / Lilo & Stitch) siguiendo EXACTAMENTE la misma metodología, diseño y estructura que `familia-canina` (Bluey). El tema referencia es `familia-canina` — estudia sus assets, su themes.json, y replica el patrón.

### REGLAS INNEGOCIABLES
1. **CAMUFLAJE OBLIGATORIO**: NUNCA uses nombres de franquicia ni personajes reales en prompts de generación. Solo describe rasgos físicos. El backend bloquea nombres protegidos.
2. **MISMA LÍNEA DE DISEÑO**: Mismo estilo visual, mismo tipo de encuadre, mismo tono infantil pero premium.
3. **NO REINVENTES**: Copia la estructura de familia-canina. Los 6 personajes con sus .jpg, -cut.png, saludo-*.mp4, etc.
4. **NO TOQUES otros temas ni código existente** salvo lo necesario para tropical.
5. **BudgetPixel (MCP) para generar imágenes**: usa `generate_image` con modelo `seedream-5.0-pro` o `flux-2-klein` para drafts rápidos. Higgsfield para videos.

### PASO A PASO

#### A. Crear directorio
```
mkdir public\themes\tropical\
```

#### B. Generar 6 personajes (BudgetPixel + remover fondo)
El themes.json define 6 personajes camuflados. Genera CADA UNO con BudgetPixel:

| Archivo | Nombre Camuflaje | Rasgos Físicos (para el prompt) |
|---------|-----------------|----------------------------------|
| `hawaiana.jpg` | Niña Hawaiana | Niña pequeña de piel morena, cabello negro largo ondulado, vestido rojo con estampado de hojas tropicales, sandalias, sonrisa alegre, estilo cartoon 3D tipo Pixar |
| `alienazul.jpg` | Alien Azul | Criatura alienígena azul de 4 brazos, ojos grandes negros, orejas puntiagudas, garras retráctiles, pelaje azul corto, postura traviesa, estilo cartoon 3D |
| `alienrosa.jpg` | Alien Rosa | Criatura alienígena rosa de 4 brazos, ojos grandes negros, antenas en la cabeza, pelaje rosa suave, expresión curiosa, estilo cartoon 3D |
| `tortugamar.jpg` | Tortuga Marina | Tortuga marina verde con caparazón marrón, ojos grandes amigables, aletas, expresión sabia y anciana, estilo cartoon 3D |
| `loro.jpg` | Loro Tropical | Loro de plumaje rojo y azul, pico amarillo curvo, alas extendidas, cresta despeinada, expresión caótica y divertida, estilo cartoon 3D |
| `surfista.jpg` | Surfista | Niño grande y musculoso, cabello rubio corto, camisa hawaiana azul, shorts, chancletas, expresión bonachona, estilo cartoon 3D |

**IMPORTANTE**: Genera TODOS en el MISMO estilo visual consistente (misma iluminación, mismo fondo neutro, misma calidad).

#### C. Remover fondos (cut PNGs)
Después de generar cada .jpg, usa el script de Python `remove-familia-canina-backgrounds.py` como referencia para crear `rembg` recortes. Nombres: `hawaiana-cut.png`, `alienazul-cut.png`, `alienrosa-cut.png`, `tortugamar-cut.png`, `loro-cut.png`, `surfista-cut.png`.

#### D. Generar 6 saludos individuales (Higgsfield)
Cada personaje necesita `saludo-<base>.mp4`. Usa Higgsfield `generate_video`:
- Modelo: `seedance-2.0` o `kling3_0_turbo`
- 5 segundos, 720p, 9:16
- Prompt por personaje: "[Rasgos físicos] saludando alegremente a la cámara con una ola, sonriendo, fondo tropical difuminado con palmeras y playa al atardecer, iluminación cálida"

#### E. Fondo de sala y banner
- `fondo-sala.jpg`: Playa tropical al atardecer, palmeras, cielo naranja/rosa, sin personajes, 9:16, calidad alta. Es el fondo de la cámara.
- `fondo-banner.jpg`: Mismo estilo pero horizontal/ancho, para banner. Con espacio para fotos.

#### F. Grupo de personajes
- `grupo-personajes.png`: Los 6 personajes juntos, posando, estilo foto familiar, con fondo transparente o neutro.

#### G. Ruleta
- `roulette/roulette-background-v1.png`: Fondo texturizado estilo tropical (palmeras, hojas, flores hawaianas), 1:1, colores vibrantes pero que permitan leer los nombres de los personajes encima.

#### H. Música
- `musica-fondo.mp3`: Música infantil alegre estilo tropical/hawaiano, instrumental, 45-60 segundos en loop.

#### I. Video welcome
- `welcome-familia-canina.mp4` → tú creas `welcome-tropical.mp4`: 5s, los 6 personajes saludando juntos, estilo tropical.

#### J. Invitación (opcional inicial)
Si quieres construir también la invitación como tiene familia-canina:
- `invitation/invitation-base-v1.png`
- `invitation/invitation-base-motion-v1.mp4`
- `invitation/invitation-thumb-v1.webp`

#### K. Actualizar themes.json
El tema tropical YA está definido en themes.json:580-643 con colores, personajes, confetti, frameBox. Solo falta agregar `photoSession` (opcional) si quieres pase de artista. Verifica que los nombres de archivo en `personajes[].img` coincidan con lo que generaste.

#### L. Verificar en admin
Una vez subidos los archivos, el admin debe mostrar el tema como "Listo" (ready: true). Ve a `index.php?view=temas` y verifica que no falten archivos.

---

## TAREA 2: MODAL PREVIEW DE FOTOS EN ADMIN

### OBJETIVO
En el panel de administración (`public/admin/index.php`), cuando haces clic en una imagen del inventario de assets (vista `?view=tema&slug=...`), debe abrirse un modal con la imagen a tamaño completo. Debe ser responsive (funcionar en móvil).

### ESTADO ACTUAL
Actualmente las imágenes en la grilla `asset-detail-grid` se muestran como `<img>` con `object-fit: contain` dentro de un contenedor de 220px de alto (línea 376-377 de index.php y CSS línea 376-377 de _style.css.php). No hay interacción de clic — son solo thumbnails estáticos.

### LO QUE DEBES IMPLEMENTAR

#### A. HTML/CSS del Modal
Agregar al final de `_style.css.php` (antes del `@media` responsive existente):

```css
/* Modal de vista previa de fotos en el inventario de assets */
.asset-preview { cursor: pointer; }
.asset-preview:hover { opacity: 0.92; }

.modal-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(23, 18, 38, 0.85); /* ink oscuro translúcido */
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
  animation: modalFadeIn 0.2s ease;
}
@keyframes modalFadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal-content {
  position: relative;
  max-width: 90vw; max-height: 90vh;
  background: #171026; /* fondo oscuro para resaltar la imagen */
  border-radius: var(--radius);
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,.5);
}
.modal-content img {
  display: block; max-width: 90vw; max-height: 85vh;
  object-fit: contain; margin: 0 auto;
}

.modal-close {
  position: absolute; top: 12px; right: 12px; z-index: 10;
  width: 40px; height: 40px; border-radius: 50%;
  background: rgba(255,255,255,.15); color: #fff; border: none;
  font-size: 1.5rem; cursor: pointer; display: grid; place-items: center;
  transition: background .2s;
}
.modal-close:hover { background: rgba(255,255,255,.25); }

/* Responsive */
@media (max-width: 480px) {
  .modal-overlay { padding: 10px; }
  .modal-content { max-width: 96vw; max-height: 85vh; }
  .modal-content img { max-width: 96vw; max-height: 80vh; }
  .modal-close { width: 36px; height: 36px; top: 8px; right: 8px; }
}
```

#### B. JavaScript del Modal
Agregar al bloque `<script>` existente al final del `index.php` (línea 978-1059). Insertar ANTES del cierre `</script>`:

```javascript
// --- Modal vista previa de imágenes en inventario de assets ---
(function () {
  var assetPreviews = document.querySelectorAll('.asset-preview img');
  if (!assetPreviews.length) return;

  // Crear overlay una sola vez
  var overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.style.display = 'none';
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-label', 'Vista previa de imagen');

  var content = document.createElement('div');
  content.className = 'modal-content';
  overlay.appendChild(content);

  var closeBtn = document.createElement('button');
  closeBtn.className = 'modal-close';
  closeBtn.innerHTML = '&times;';
  closeBtn.setAttribute('aria-label', 'Cerrar vista previa');
  content.appendChild(closeBtn);

  var modalImg = document.createElement('img');
  modalImg.alt = '';
  content.appendChild(modalImg);

  document.body.appendChild(overlay);

  function openModal(src, alt) {
    modalImg.src = src;
    modalImg.alt = alt || '';
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    overlay.style.display = 'none';
    document.body.style.overflow = '';
    modalImg.src = ''; // liberar memoria
  }

  closeBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closeModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.style.display === 'flex') closeModal();
  });

  // Vincular clic en cada imagen del inventario
  Array.prototype.forEach.call(assetPreviews, function (img) {
    img.closest('.asset-preview').style.cursor = 'pointer';
    img.closest('.asset-preview').addEventListener('click', function (e) {
      // No abrir modal si el clic fue en un video o audio
      if (e.target.tagName === 'VIDEO' || e.target.tagName === 'AUDIO') return;
      var targetImg = this.querySelector('img');
      if (!targetImg) return;
      openModal(targetImg.src, targetImg.alt);
    });
  });
})();
```

### LO QUE NO DEBES HACER
- No agregues librerías externas ni CDNs. Todo es vanilla JS + CSS.
- No modifiques la estructura HTML del admin (solo agrega el modal dinámicamente).
- No rompas la funcionalidad existente de upload, prompts, o formularios.
- No uses emojis en la UI (los iconos son SVG inline con `admin_icon()`).
- No uses `alert()`, `confirm()` para el modal — usa el overlay descrito.

### VERIFICACIÓN
1. Abre `index.php?view=tema&slug=familia-canina`
2. Haz clic en cualquier imagen (ej: `azulita.jpg`)
3. Debe abrirse modal oscuro con la imagen a tamaño completo
4. Clic fuera del modal o tecla Escape debe cerrarlo
5. Probar en viewport móvil (375px) — debe ser usable

---

## FLUJO DE TRABAJO RECOMENDADO

### Orden de ejecución:
1. **Primero Tarea 2** (modal admin): es rápida, vanilla, sin dependencias externas. 15-30 min.
2. **Luego Tarea 1** (tema tropical): lleva más tiempo porque requiere generar assets con IA.
   - Paso A (crear dir) — 1 min
   - Paso K (verificar themes.json) — 2 min
   - Pasos B-C (generar 6 personajes) — 15-30 min con BudgetPixel
   - Pasos D (6 saludos Higgsfield) — 30-60 min (videos tardan)
   - Pasos E-H (fondos, música, grupo) — 30-45 min
   - Paso I-J (videos extra, invitación) — opcional, puede ser después
   - Paso L (verificar en admin) — 2 min

### Después de cada cambio:
```powershell
npm run build                           # Build frontend
node --test tests/frontend/*.test.mjs  # Frontend tests
php tests/backend/run.php              # Backend tests
```

---

## REFERENCIAS RÁPIDAS

### Archivos clave que DEBES leer antes de empezar:
| Archivo | Por qué |
|---------|---------|
| `AGENTS.md` | Reglas del proyecto (camuflaje, PHP 8.0+, no secretos) |
| `docs/CUMPLECLICK-HANDOFF-CODEX.md` | Arquitectura completa, flujo del kiosco, temas |
| `public/data/themes.json` | Catálogo de temas, estructura exacta a replicar |
| `public/themes/familia-canina/` | Tema de referencia — todos los assets que necesitas replicar |
| `public/admin/index.php` | Admin panel donde agregar el modal |
| `public/admin/_style.css.php` | CSS del admin donde agregar estilos del modal |
| `src/App.jsx:883-904` | Cómo se renderiza el video debajo de la ruleta |
| `src/themeFlow.js` | Cómo se resuelve el flujo de temas |
| `docs/OPENCODEGO-TEMA-02-FAMILIA-CANINA.md` | Metodología exacta para construir un tema |

### Comandos esenciales:
```powershell
# Dev server (frontend)
npm run dev

# Build producción
npm run build

# Tests frontend
node --test tests/frontend/*.test.mjs

# Tests backend
php tests/backend/run.php

# Lint PHP
php tests/backend/lint.php

# Si necesitas el admin:
# http://localhost/cumpleclick/admin/index.php
```

### Presupuesto de créditos BudgetPixel:
- Imágenes: ~55 créditos c/u con seedream-5.0-pro (~330 créditos para 6 personajes)
- Videos Higgsfield: ~220 créditos/segundo a 720p (~1100 créditos por video de 5s)
- TOTAL estimado: ~7000 créditos para el tema completo
- Verifica saldo: `budgetpixel_get_credit_balance`

---

## CONTEXTO DEL PROYECTO (para orientarte)

**Producto**: CumpleClick by AutomatizaTech — cabina de fotos infantil para cumpleaños.
**Ruta**: `C:\wamp64\www\automatiza-tech\CumpleBooth`
**URL pública**: `https://automatizatech.cl/cumpleclick/`
**URL local**: `http://localhost/cumpleclick/` (requiere WAMP corriendo)

**Stack**:
- Frontend: React 18 + Vite 6 → `src/` → build a `dist/`
- Backend: PHP 8.0+ vanilla, cero dependencias → `public/`
- DB: MySQL o JSON plano (dual mode) → `public/data/`
- Admin: single-file PHP → `public/admin/index.php`

**Flujo del kiosco**:
intro → invitados → spinner (ruleta) → [photo-session video si existe] → video-personaje → capture (foto) → preview → QR → diploma

**El spinner muestra el artist-teaser debajo**: si el tema tiene `photoSession.teaserVideo`, se reproduce en loop mudo mientras gira la ruleta. Es un `<video>` con `autoPlay loop muted playsInline`.

**Cada tema necesita mínimo**:
- `fondo-banner.jpg`, `fondo-sala.jpg`, `musica-fondo.mp3`
- 6 `<personaje>.jpg` + 6 `<personaje>-cut.png` (recortes)
- 6 `saludo-<base>.mp4` (uno por personaje)
- `grupo-personajes.png` (los 6 juntos)
- Opcional: `roulette/roulette-background-v1.png`, `welcome-<tema>.mp4`, `artist-teaser.mp4`, invitaciones

---

**No salgas de esta línea de diseño. No improvises. Sigue el patrón de familia-canina al pie de la letra.**
