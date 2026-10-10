---
name: at-gamma-proposal
description: Arma a mano una propuesta comercial AutomatizaTech con la plantilla única del propuesta-renderer (JSON de láminas + fotos + system prompt del chatbot + prompt de diseño opcional). Nombre histórico; ya no genera prompts de Gamma. Usar también cuando pidan "at-propuesta".
triggers:
  - generar propuesta
  - nueva propuesta cliente
  - armar propuesta AT
  - at propuesta
  - crear propuesta automatizatech
  - crear presentación automatizatech
  - generar propuesta gamma
  - propuesta gamma cliente
  - at gamma proposal
---

# at-gamma-proposal

Copia única compartida por Claude Code, Codex, OpenCode y GitHub Copilot (conciliada el 2026-10-10).
Fuente: `C:\Users\luis_\.agents\skills\at-gamma-proposal`; los hosts la leen por enlace. En el repo
`automatiza-tech` vive una copia idéntica en `.github/skills/at-gamma-proposal/SKILL.md`. No editar
una copia sola: cambiar las dos y comparar su sha256 (ignorando fin de línea).

## Reglas que mandan

- **Generador propio, plantilla única** (regla de Luis, 2026-09-23): toda propuesta se arma con el
  `propuesta-renderer`, la misma estructura y controles que la de Jeffer García (propuesta 42). No se
  generan prompts de Gamma. Canva/Gamma quedan como respaldo **solo si Luis lo pide expresamente**.
- **El nombre `at-gamma-proposal` es histórico** y se conserva por los disparadores. `at-propuesta` es
  un alias que remite aquí.
- **Camino normal = flujo automático v3**: Meet → n8n «1 Borrador» → panel (precios y cambios) →
  «3 Final» → envío desde el panel. Lo describe `Docs/METODO_AT/PROPUESTAS-FLUJO-V3.md`. Esta skill
  sirve para armar o ajustar una propuesta **a mano** cuando ese flujo no aplica.
- Los precios los decide Luis: proponer, nunca darlos por aprobados.
- Gastar en fotos, escribir en PROD (`api-save-proposal.php`, `/render`, panel) o enviar al cliente
  exige el "ok" de Luis para esa acción.
- Las claves (`X-AT-Render-Key` de `/render`, Higgsfield) viven en n8n y Easypanel. Nunca se
  escriben en un prompt, un documento, un commit ni el chat.

Guía de punta a punta del camino manual: `Docs/METODO_AT/PROPUESTAS-PLANTILLA-UNICA.md`. Código del
renderer: carpeta `renderer/` de `origin/main` (`src/schema.js`, `src/template.js`, `DEPLOY.md`).

## Entrada

Pedir al usuario (de la transcripción, notas o brief):

```
CLIENTE: nombre, empresa, rubro, email, teléfono, Instagram/redes
DIAGNÓSTICO: negocio y etapa, procesos manuales que consumen tiempo, objetivo de crecimiento
SOLUCIÓN: servicios incluidos, integraciones (WhatsApp, Instagram, Excel...), beneficio principal
PRECIOS: los que decida Luis (CLP; desde el 27-sep las propuestas nuevas dicen «+ IVA»)
IDENTIDAD: colores, logo (URL o imagen), estilo visual
```

Los datos del desafío salen de la reunión y de una revisión real del sitio del cliente; no inventar cifras.

## Salida 1: JSON del propuesta-renderer

Campos obligatorios (`renderer/src/schema.js`): `unique_id`, `client_name`, `company_name`,
`challenge_title`, `challenge_text`, `solution_title`, `solution_text`, `benefits[]`,
`how_it_works[]`, `pricing_rows[]`, `next_steps[]`. Opcionales: `extra_slides[]` (máx. 2),
`pricing_note`, `image_briefs[]`, `images{}`.

- Precios en CLP con `price_label` y `price_usd: 0`; `emphasis` marca un total y `strike` tacha.
  Inversión en tres bloques (pago único, mensual, anual) según la sección «Precios» de la guía v3.
- Más de 6 filas de precios: el renderer usa la tabla densa sola. Las URL `https://…` de párrafos y
  próximos pasos salen como enlace; el demo va como primer próximo paso.
- Fotos del rubro con personas en acción, sin texto, logos ni rostros reconocibles (reglas en la
  sección «Fotos por rubro» de la guía v3).
- Previsualizar gratis con `renderProposalHtml` + Playwright antes de gastar o de tocar PROD.

## Salida 2: system prompt del chatbot

Para el demo dinámico (`https://n8n-n8n.kchiba.easypanel.host/webhook/demo-dinamico/chat`, busca el
prompt por `sessionId` = `unique_id`). Con datos reales del cliente y derivación a un humano:

```
Eres un asistente virtual de {{NOMBRE_EMPRESA}}, una {{DESCRIPCION_NEGOCIO}}.
Tu nombre es {{NOMBRE_EMPRESA}} AI Assistant.

SOBRE EL NEGOCIO:
{{DESCRIPCION_COMPLETA_NEGOCIO}}

TUS FUNCIONES PRINCIPALES:
1. Responder preguntas sobre productos/servicios disponibles
2. Informar sobre precios (si se proporcionan)
3. Tomar pedidos o consultas de personalización
4. Derivar a {{NOMBRE_CLIENTE}} para casos que requieran decisión humana

INFORMACIÓN DE CONTACTO:
- WhatsApp/Teléfono: {{TELEFONO}}
- Instagram: {{INSTAGRAM}}
- Horario de atención humana: (el cliente debe informar)

TONO: Amigable, profesional y orientado a {{RUBRO}}.
LÍMITES: Si no tienes información precisa sobre stock o precio, indica que vas a consultar con el equipo y da el contacto directo.
IDIOMA: Responder siempre en español.
```

## Después de las salidas 1 y 2

1. Crear la fila con `POST https://automatizatech.cl/api-save-proposal.php` (devuelve `unique_id`).
   Escritura en PROD: con el "ok" de Luis.
2. Renderizar con `POST https://n8n-propuesta-renderer.kchiba.easypanel.host/render` y ese mismo
   `unique_id`. Exige la cabecera `X-AT-Render-Key` (sin ella responde 401); el valor lo pone n8n,
   no esta skill. El renderer guarda las fotos en `/p/<unique_id>/img/` y reutiliza las ya pagadas.
3. En el panel (`wp-admin/admin.php?page=automatiza-proposals`) completar y **Guardar**: si no,
   `n8n_chat_url` queda vacío y el demo muestra "Demo en Configuración".
4. Verificar desde afuera `ver-presentacion.php?id=…` y `ver-demo.php?id=…` antes de enviar.

## Paso intermedio: validar contra la reunión

Antes de la salida 3, pedir el historial de la conversación y la presentación generada
(`ver-presentacion.php?id=<unique_id>` o su PDF). Revisar:

| Qué verificar | Hay que refinar si… |
|---|---|
| Láminas vs lo hablado | faltan puntos del historial en el JSON |
| Precios y servicios | difieren de lo acordado |
| Tono del chatbot | suena genérico para el rubro |
| Desafío del cliente | la lámina 2 no refleja el dolor real |
| Próximos pasos | plazos o pasos no corresponden |

Si hay brechas, usar la skill `at-proposal-refiner` con el `edit_id` de la propuesta. Esa skill elige
el camino según el flujo: en las v3, comentarios para «Pedir cambios» en el panel (el `POST /prompts`
responde 409); en las antiguas, el endpoint `/prompts` y un re-render con el mismo `unique_id`. No
inventar datos: solo ajustar redacción, estructura o coherencia con lo que confirma el historial. Si aún
no hay historial, seguir y recordar refinar después.

## Salida 3: prompt de diseño (opcional)

Solo si la propuesta incluye un sitio nuevo. Prompt de alta fidelidad para Claude Design u Open Design.

**Antes:** A) pedir el logo (imagen o URL; si no hay, inferir de colores y rubro). B) Revisar
Instagram, Facebook y el sitio actual del cliente con el navegador disponible en el host (preferir
lectura de texto/árbol de accesibilidad: las capturas de Instagram suelen agotar el tiempo). Extraer
paleta real, tono, productos con nombres exactos, historia, ubicación, público y estilo de fotografía.

```
Diseña un prototipo de sitio web {{TIPO_SITIO}} de alta fidelidad para {{NOMBRE_EMPRESA}}.
{{DESCRIPCION_NEGOCIO_ENRIQUECIDA_CON_REDES}}

═══ IDENTIDAD VISUAL (extraída de logo + redes) ═══
- Paleta: {{COLOR_1}} + {{COLOR_2}} + {{COLOR_ACENTO}}
- Logo: {{DESCRIPCION_DETALLADA_LOGO}} — URL: {{URL_LOGO_SI_EXISTE}}
- Tipografía: {{TIPOGRAFIA_DERIVADA}} — títulos {{ESTILO_TITULO}}, cuerpo {{ESTILO_CUERPO}}
- Estética: {{ESTETICA_REAL_EXTRAIDA}} (ej: "premium oscuro con destellos dorados")
- Mood: "{{FRASE_MOOD_DEL_NEGOCIO}}"
- Fotografía: {{ESTILO_FOTOGRAFIA}}

═══ FRAMES Y SECCIONES ═══
Frame 1 — HERO: fondo {{COLOR_FONDO_HERO}} con {{TEXTURA_O_PATRON}}; headline "{{PROPUESTA_DE_VALOR}}",
  subheadline "{{DESCRIPCION_CORTA}}"; CTA "{{CTA_PRINCIPAL}}" color {{COLOR_ACENTO}} con hover glow/scale;
  CTA secundario "{{CTA_SECUNDARIO}}"; imagen {{DESCRIPCION_IMAGEN_HERO}} con parallax sutil;
  header sticky con blur. Entrada: fade-in del headline + slide-up del subtítulo (300 ms).
Frame 2 — {{FEATURE_ESTRELLA}}: {{DESCRIPCION_DIFERENCIADOR}}. Animación {{TIPO_ANIMACION}};
  microinteracción {{MICROINTERACCION}}.
Frame 3 — CATÁLOGO/SERVICIOS: grid {{N_COLUMNAS}} columnas (1 en móvil), cards con imagen, nombre,
  precio y "{{CTA_CARD}}"; hover scale(1.03) + sombra + overlay; categorías {{LISTA_CATEGORIAS_REAL}}
  con filtro y layout animation.
Frame 4 — HISTORIA: {{HISTORIA_MARCA_EXTRAIDA_DE_REDES}}; texto línea por línea al scroll; foto {{FOTO_MARCA}}.
Frame 5 — PRUEBA SOCIAL: feed o galería de {{INSTAGRAM}}, testimonio destacado, contador "{{N}}+ clientes" count-up.
Frame 6 — CONTACTO: mapa {{UBICACION}}, formulario mínimo, WhatsApp {{TELEFONO}} + chatbot IA flotantes, horarios.
Frame 7 — FOOTER: logo, navegación, redes {{REDES_SOCIALES}}, medios de pago, tagline "{{TAGLINE}}".

═══ TRANSICIONES Y ANIMACIONES ═══
- Scroll smooth con indicador de progreso arriba
- Entre secciones: fade + translateY(20px)→0, 400 ms ease-out
- Parallax 0.5x en hero y nosotros; cursor de color acento en escritorio
- Carga inicial: el logo se revela y da paso al hero
- Navegación con subrayado que crece desde el centro; botones con ripple y scale(0.97)

═══ REQUISITOS TÉCNICOS ═══
- Mobile-first: el tráfico viene de {{REDES_SOCIALES}} en celular
- WhatsApp flotante + chatbot IA en todas las pantallas
- {{REQUISITO_ESPECIFICO_DEL_NEGOCIO}} (carrito, cotización, reservas...)
- Imágenes optimizadas, skeleton loaders, contraste AA, foco visible

═══ ENTREGABLE ═══
Prototipo navegable: Hero, {{FEATURE_ESTRELLA}}, Catálogo, Contacto. Estados default, hover, activo y móvil, con transiciones entre frames.
```

El prototipo se muestra al cliente en la segunda reunión (paso 4 del flujo AT).

## Flujo AT de 6 pasos

1. Agendamiento y demo (llamada grabada). 2. Análisis y generación: flujo v3 automático o salidas 1 y 2
de esta skill, más la validación. 3. Diseño visual (salida 3, si aplica). 4. Revisión y aprobación en la
segunda reunión. 5. Ejecución del proyecto. 6. Conversión de prospecto a cliente.

## Referencias

- Caso G&N MobileStore (primer caso completo, edit_id 22): `references/caso-gyn-mobilestore.md`
  (solo en la copia de usuario; no está en el repo porque trae datos del cliente).
- Logo AutomatizaTech: `https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech+slogan.png`
- En el panel, el campo histórico *URL Iframe Gamma* recibe
  `https://n8n-propuesta-renderer.kchiba.easypanel.host/p/<unique_id>/index.html`.
- Estilo visual de las fotos: profesional, cinemático, del mundo del cliente (nunca de la solución AT).
