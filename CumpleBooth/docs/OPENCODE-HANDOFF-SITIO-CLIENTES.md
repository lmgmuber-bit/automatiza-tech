# OPENCODE — Traspaso: sitio web de presentación para clientes (landing CumpleClick)

> **Estado actualizado 2026-08-01:** la landing ya existe en `sitio/` y su
> formulario dejó de ser una simulación/puente a WhatsApp. Persiste solicitudes
> en `cc_leads` mediante `POST sitio/api/contacto.php`, con migración 006,
> validación, consentimiento, honeypot, rate limit persistente y referencia opaca.
> WhatsApp queda como canal complementario pendiente de configurar con el número
> real; no inventar uno ni revertir el formulario a un flujo solo cliente.

**De:** Claude · **Para:** OpenCode, sugerido probar con **Kimi K2.7 Code** (o Kimi K3 si aparece en tu picker) — es la tarea elegida para evaluar ese modelo en frontend real, con bajo riesgo (no toca `lib.php`, no toca el kiosco en producción local).
**Fecha:** 2026-07-21 · **Aprobado por:** Luis

---

## 0. Qué es esto y por qué existe

Todo lo construido hasta ahora (`src/`, `public/`) es el **kiosco** — la pantalla que usan los INVITADOS el día de la fiesta. No existe todavía el **sitio de presentación** — la página que ve un padre/madre que todavía NO contrató, decidiendo si reservar. Ese sitio es lo que se pide en esta tarea.

`design/MANUAL-DE-MARCA.md` §6 ya lo anticipa: *"Sitio/landing: Tokens de `tokens.css`; hero sobre crema"* — o sea, el sitio ya estaba planeado, solo falta construirlo.

**Esta tarea NO toca el kiosco, NO toca el admin, NO toca `dist/`, NO toca deploy/rutas de producción.** Es un entregable nuevo y aislado — ver §6.

---

## 1. Contexto comercial (para escribir copy real, no genérico)

- **Producto:** CumpleClick by AutomatizaTech — cabina de fotos infantil para cumpleaños. Cada invitado se va con SU foto junto a su personaje favorito, al instante, impresa/QR.
- **Decisor:** madres/padres 28–45 años, Chile, deciden 2–4 semanas antes de la fecha del cumpleaños. Compran *tranquilidad + wow*, no tecnología.
- **Dolores que resuelve:** "las fotos de la fiesta siempre quedan malas/desordenadas", "los niños se aburren a mitad de fiesta", "quiero algo distinto al payaso/cama elástica de siempre".
- **Promesa única (USP):** cada invitado se va con su foto junto a su personaje favorito, al instante.
- **Precios oficiales (usar tal cual, en Fucsia Click, formato `$XX.XXX`):**
  - Mágico **$69.990**
  - Premium **$99.990**
  - Temática a medida **+$25.000**
- **Temáticas disponibles hoy** (mostrar como catálogo/vitrina — nombres de marca, no franquicia): Carreras, Reino de Hielo, Patrulla de Cachorros, Súper Héroes, Princesas, Dinosaurios, Bajo el Mar, Historia de Juguetes, Aventura Tropical, Mickey, y **Aventura Perruna** (familia de perritos, la más nueva y mejor terminada — úsala como imagen destacada si necesitas una sola).
- **CTA único, siempre:** "Agenda la fecha por WhatsApp 📲" — link `https://wa.me/<numero>?text=<mensaje precargado>` (el número real lo confirma Luis antes de publicar; usa un placeholder visible tipo `[WHATSAPP_NUMBER]` mientras tanto, NUNCA inventes un número).
- **Voz de marca:** la tía/el tío entusiasta que organiza la mejor parte de la fiesta. Cálido, directo, chileno neutro. Habla de recuerdos, no de tecnología ("el flash que tus hijos no van a olvidar", NO "nuestra IA genera fotos"). Emojis con moderación (🎈📸🎉, máx. 2 por sección). Nada de mayúsculas sostenidas ni promesas de descuento agresivas.

---

## 2. Identidad visual — usar la aprobada, no la alternativa

Hay DOS documentos de marca en `design/`. **Usa `design/MANUAL-DE-MARCA.md` + `design/tokens.css` — es la dirección OFICIAL elegida por Luis ("El globo dulce", v3, 2026-07-18).** `docs/BRAND-FILOSOFIA-CUMPLECLICK.md` ("Confeti Contenido") es una propuesta alternativa que **NO fue elegida como principal** — no la uses salvo que Luis lo pida explícitamente.

Tokens a importar tal cual desde `design/tokens.css` (ya tiene comentario "landing" en su cabecera):
- Fondo por defecto: `--cc-crema` (`#FFF8EC`)
- Primario: `--cc-violeta` (`#8B5CF6`) / Tinta: `--cc-tinta` (`#4C2882`)
- Acento CTA/precio: `--cc-fucsia` (`#D6307F`)
- Highlight: `--cc-amarillo` (`#FBBF24`)
- Oro solo en detalles finos, nunca en masas grandes: `--cc-oro`
- Tipografía: **Baloo 2** (ya self-hosted en el proyecto vía `@fontsource/baloo-2`, ExtraBold 800 para titulares, SemiBold 600 para UI)
- Proporción de color en pantalla: **70% crema / 20% violeta-fucsia / 8% amarillo / 2% oro** — el oro es condimento, no plato.

Logo: `public/brand/cumpleclick-lockup.svg` (lockup con texto) y `cumpleclick-mark.svg` (solo isotipo). Zona de respeto mínima alrededor del logo = el diámetro del aro amarillo del lente.

**Prohibido:** gradiente violeta genérico tipo "AI slop", texto sobre el globo del logo, recuadrar el isotipo en caja blanca sobre fondo oscuro, serif (la marca es 100% Baloo 2 redondeada).

---

## 3. Lectura de diseño y motion (declarar esto fue el criterio, seguirlo)

**Skills obligatorias antes de escribir código** (Luis lo pidió explícitamente): carga
`design-taste-frontend` y `frontend-motion-toolkit`, y declara en el reporte el *Design Read* y el
*Motion read* que te salieron. Si tu lectura difiere de la de abajo, dilo y justifícalo — no la
sigas por inercia.

**Design read:** landing premium de consumo para un servicio infantil (CumpleClick), audiencia = padres decidiendo una compra puntual por WhatsApp, marca ya definida y aprobada (violeta/fucsia/amarillo/crema, Baloo 2), sin necesidad de un design system externo — página autocontenida en línea con el resto del proyecto (el admin ya es 100% vanilla sin CDNs).

**Motion read (ACTUALIZADO 2026-07-27 por decisión de Luis):** landing de conversión con acabado premium. El trabajo del motion sigue siendo *guiar el scroll y celebrar*, no impresionar con complejidad — pero ahora **sí se autorizan GSAP y Lenis** para lograr un scroll más cinematográfico.

**Antes este handoff prohibía toda librería de motion** (CSS puro + IntersectionObserver). Luis cambió el criterio: quiere que el sitio se vea más ambicioso. Esa versión anterior ya no aplica.

Librerías autorizadas para esta tarea:

| Necesidad | Usa | Instalar |
|---|---|---|
| Scroll suave y normalizado | **Lenis** | `npm i lenis` |
| Reveals encadenados, pinned sections, parallax por scroll | **GSAP + ScrollTrigger** | `npm i gsap` |
| Escena 3D y transiciones con profundidad | **Three.js** | ya está en `package.json` ✅ |
| Hover, focus, pulso del CTA | **CSS puro** | nada |

**Siguen prohibidas:** Barba.js (es una sola página, no hay navegación entre rutas) y cualquier
librería de componentes UI (Tailwind, shadcn, etc. — la marca ya está definida en `tokens.css`).

### 3.1 El 3D — autorizado por Luis, con condiciones

Luis pidió explícitamente **3D con transiciones** en el sitio. Three.js ya es dependencia del
proyecto (el kiosco la usa), así que no hay que instalar nada.

Dónde tiene sentido usarlo (elige UNO o DOS, no lo pongas en todas partes):
- **Hero**: el globo-lente de la marca flotando con profundidad y respondiendo suave al mouse o al
  giroscopio. Es el isotipo, ya existe como forma — no inventes otro objeto.
- **Vitrina de temáticas**: las tarjetas con perspectiva real, girando al pasar de una a otra.
- **Transición entre secciones**: profundidad al hacer scroll, no un simple fade.

Condiciones duras, porque esto es una landing de conversión y no un showcase:
1. **El 3D carga DESPUÉS del contenido, nunca antes.** `import()` dinámico. El titular, el precio
   y el CTA tienen que estar visibles y clicables aunque la escena 3D todavía no haya cargado (o
   falle del todo).
2. **Fallback obligatorio**: si el dispositivo es modesto, no hay WebGL, o el usuario pidió
   `prefers-reduced-motion`, se muestra una imagen estática en su lugar. La página debe verse bien
   sin 3D — el 3D es la cereza, no la torta.
3. **Presupuesto de peso**: el bundle de three ya pesa ~734kB. No sumes loaders de modelos pesados
   ni texturas de varios MB. Geometría simple + los assets que ya existen.
4. **Se mide, no se asume**: si el 3D baja de 30fps en un celular de gama media, se simplifica o
   se saca. Pega la medición en el reporte.

### 3.2 Imágenes reales y logo — usa las que YA existen

Sí, el sitio debe apoyarse en imágenes reales, no en ilustraciones genéricas. **Ya están todas
pagadas y generadas** — úsalas, no hagas otras:

| Qué | Dónde |
|---|---|
| Logo (icono + wordmark, fondo transparente) | `design/logo/logo-icon-wordmark.png` |
| Logo vectorial | `public/brand/cumpleclick-lockup.svg`, `cumpleclick-mark.svg` |
| Render con volumen del logo | `design/logo/cumpleclick-logo-master-render.png` |
| Personajes de cada temática | `public/themes/<slug>/*.jpg` y `*-cut.png` (recortados, fondo transparente) |
| Salas/ambientes por temática | `public/themes/<slug>/fondo-sala.jpg`, `fondo-banner.jpg` |
| Kiosco en una fiesta real | `design/video/frame-sala-kiosco.png` |
| Kiosco con foto en pantalla | `design/video/frame-sala-kiosco-con-foto.png` |
| Clips de video para el hero | `design/video/reels/*.mp4`, `design/video/campania-fase1/*.mp4` |
| Video explicativo del servicio | `design/explicativo/video-explicativo.mp4` |

Las temáticas mejor terminadas, si necesitas destacar pocas: **Reino de Hielo (Frozen)**,
**Aventura Perruna (Bluey)**, **Carreras** y **Aventura Tropical (Stitch)**.

**Condiciones que no se negocian aunque ahora haya librerías:**
- **Nada de scroll hijacking.** Lenis suaviza el scroll; no puede robarle al usuario el control de la página. La rueda, el trackpad, el teclado y la barra de scroll siguen funcionando como siempre.
- **`prefers-reduced-motion: reduce` se respeta**: desactiva Lenis y los parallax, deja solo fades cortos.
- **El primer viewport carga rápido o no sirve.** El que decide es un papá con el celular en la fila del supermercado. Si GSAP retrasa el hero o el CTA, se saca.
- Animar solo `transform` y `opacity`.
- El CTA de WhatsApp **nunca** puede quedar tapado, desplazado ni retrasado por una animación.

**Dials:** VARIANCE 7 (asimetría con propósito, no todo centrado), MOTION 5 (celebratorio pero no saturado — el que compra es un adulto ocupado en el celular), DENSITY 3 (mucho aire, secciones respiran).

---

## 4. Estructura de contenido sugerida (una sola página, scroll largo)

1. **Hero** — titular con la promesa única, subtítulo con el dolor que resuelve, CTA WhatsApp arriba del fold. Fondo crema, isotipo chico, considerar loop de video mudo de fondo usando un clip ya existente de `design/video/reels/` (gratis, no gastar créditos Higgsfield en nada nuevo para esto).
2. **Cómo funciona** — 3 pasos simples (elige tu personaje → la ruleta te sorprende → te llevas tu foto), con íconos/ilustraciones simples, no capturas de pantalla técnicas.
3. **Elige tu mundo** — vitrina de temáticas, usando los `grupo-personajes.png` o `*-cut.png` ya generados de cada tema en `public/themes/<slug>/` (assets reales, cero costo). Prioriza mostrar Carreras y Aventura Perruna (Bluey) que son las más pulidas.
4. **Precios** — Mágico / Premium / Temática a medida, con el CTA de WhatsApp repetido.
5. **Por qué CumpleClick** (opcional) — 2-3 diferenciadores cortos, sin jerga técnica.
6. **FAQ corta** (opcional) — 3-4 preguntas típicas (cuánto dura, cuántos invitados, se puede personalizar el nombre del cumpleañero).
7. **CTA final + footer** — WhatsApp de nuevo, redes sociales, marca AutomatizaTech como desarrollador (chico, pie de página).

No es obligatorio implementar las 7 en el primer pase — prioriza 1-2-3-4-7 primero (lo que convierte), 5-6 son plus.

---

## 5. Restricciones técnicas

- **Dependencias nuevas: solo `gsap` y `lenis`** (autorizadas por Luis el 2026-07-27, ver §3). Cualquier otra —Tailwind, Barba, Three.js, librerías de componentes— sigue necesitando permiso explícito. Instálalas como dependencias del proyecto, nunca global.
- Fuera de esas dos, la página es autocontenida: HTML + CSS + JS, mismo criterio que `public/admin/`. Si prefieres construirla como una ruta más del build de Vite que ya existe, también sirve.
- Un solo archivo de fuente de verdad para el CSS de marca: importa/copia `design/tokens.css`, no reinventes los colores a mano.
- **Mobile-first de verdad** — la mayoría de estos padres deciden desde el celular. Probar en 375px antes que en desktop.
- Sin CDNs externos (fuentes, iconos, scripts) — todo self-hosted, igual que el resto del proyecto.
- Imágenes livianas: reusa los assets ya generados (ver §3.2). Optimízalos para web (redimensiona,
  usa WebP donde puedas) — varios son PNG grandes pensados para el kiosco, no para una landing que
  carga en 4G.

### 5.1 Higgsfield — autorizado, pero el saldo está en rojo

Luis autorizó usar Higgsfield si hace falta. **Pero el saldo al 2026-07-27 es de 11,17 créditos**
(plan basic). Para dimensionar: una imagen `nano_banana_pro` cuesta 2 cr, un video 7,5 cr. O sea,
alcanza para unas 5 imágenes **o** un solo video, y se acabó.

Reglas, sin excepciones:
1. **Primero mira si el asset ya existe** (§3.2). En el 99% de los casos, sí existe.
2. **`get_cost: true` SIEMPRE antes de generar.** Reporta el costo ANTES de disparar.
3. **Pide autorización a Luis por cada generación**, con el costo en la mano. No hay un presupuesto
   abierto que puedas consumir a criterio propio.
4. **Nunca generes música.** Luis genera todos los MP3 él mismo. Cero excepciones.
5. **Nunca generes el logo.** Se compone desde el SVG/PNG real (§3.2). Los modelos lo redibujan y
   nunca sale idéntico.
6. Los rechazos por filtro (`nsfw`/`failed`) **no cobran**, así que reintentar es gratis — pero si
   el mismo prompt falla **3 veces**, el filtro es consistente: detente y reporta.
7. **Si el gasto total llega a 6 créditos, párate y pregunta.** Queda menos de la mitad del saldo.
8. En los prompts de generación **nunca nombres la franquicia** (Frozen, Bluey, Stitch): usa
   descripción física camuflada. En las piezas finales el nombre real SÍ puede aparecer.

**Recomendación honesta:** con 11 créditos, lo sensato es construir el sitio entero con lo que ya
existe y dejar la generación para un asset puntual que de verdad falte. No arranques generando.

---

## 6. Dónde vive esto (aislado, no toca producción)

Crea el sitio en una carpeta nueva, ej. `sitio/` o `public/inicio/` en la raíz del proyecto — **NO dentro de `dist/`, NO reemplazando nada de lo que sirve hoy `?p=<slug>` del kiosco.** La decisión de en qué URL final vive (¿reemplaza la raíz de `automatizatech.cl/cumpleclick/` y el kiosco pasa a un subpath? ¿vive en otra ruta?) **es de Luis, no la decidas tú ni la implementes** — solo entrega el sitio funcionando en local (`npm run dev` o abrir el HTML directo) para que Luis lo revise primero.

No hagas commit, push, deploy ni cambies ninguna configuración de rutas de Apache/`.htaccess` en esta tarea.

---

## 7. Qué NO tocar

- `src/`, `dist/`, `public/admin/`, `public/api.php`, `public/lib.php`, `public/galeria.php` — nada del kiosco/admin/backend.
- `public/data/themes.json` ni ningún asset de `public/themes/` — solo LEER/copiar/referenciar, no modificar ni mover.
- La temática **Aventura Tropical (Lilo & Stitch)** en construcción por otro agente en paralelo (ver `docs/OPENCODE-HANDOFF-STITCH-Y-MODAL.md`) — no interferir.
- Nada de deploy/commit/push/merge sin instrucción explícita de Luis en el momento.

---

## 8. Cómo evaluar el resultado (para que Luis compare el modelo)

Al terminar, responde estas preguntas en el reporte — no solo "listo":
1. ¿Usó la paleta/tipografía de marca aprobada (`tokens.css`) o inventó colores propios?
2. ¿El copy suena a la voz de marca (cálido, chileno, sin jerga técnica) o genérico tipo plantilla SaaS?
3. ¿Es responsive de verdad en mobile (probado en 375px, no asumido)?
4. ¿Reusó assets ya generados o gastó créditos de Higgsfield/BudgetPixel sin autorización?
5. **Motion:** ¿el *Design Read* y el *Motion read* están declarados? ¿Lenis dejó el scroll usable (rueda, teclado, barra) o lo secuestró? ¿Probaste con `prefers-reduced-motion: reduce`?
6. **Rendimiento:** ¿cuánto tarda en verse el hero y el CTA en un celular? Si GSAP/Lenis los retrasan, hay que sacarlos — pégale la medición al reporte, no una impresión.

**Sé honesto.** Una landing sobria que carga en 1 segundo convierte más que una espectacular que
tironea en el celular de una mamá apurada.
