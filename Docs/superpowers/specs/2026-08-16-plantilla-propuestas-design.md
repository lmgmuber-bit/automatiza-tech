# Renderizador propio de propuestas (reemplazo de Gamma)

**Fecha:** 2026-08-16
**Estado:** Diseño aprobado, pendiente de plan de implementación
**Origen:** Sesión Claude Code — revisión del WF n8n "Propuesta AutomatizaTech (Con Tasa CLP)"

## Contexto

El pipeline de ventas de AutomatizaTech genera propuestas comerciales automáticamente a partir de la transcripción de una reunión (Google Meet/Gemini → n8n → OpenAI → email de aprobación a Luis). Hoy, la parte visual de la propuesta (la presentación tipo "deck") depende de que Luis copie un prompt generado por IA y lo pegue manualmente en Gamma (gamma.app), obtenga el link de embed, y lo pegue a mano en el panel de WordPress. Gamma sí tiene API (plan Pro, US$18/mes) pero Luis quiere control total del diseño y no depender de un tercero de pago.

Este spec cubre **reemplazar Gamma por un motor de presentación propio**: plantilla HTML/CSS diseñada a medida con la marca AutomatizaTech, renderizada automáticamente por el mismo pipeline de n8n, sin intervención manual de Luis salvo su revisión/aprobación habitual.

**Fuera de alcance de este spec** (ver sección "No-goals"): asesor de precios/margen (spec aparte, a diseñar después), automatización del envío final al cliente tras aprobación (nodos `Esperar Aprobación`/`Enviar al Cliente` de n8n, hoy desconectados a propósito — Luis sigue aprobando y enviando manualmente desde el panel WP).

## Decisiones de diseño (confirmadas con Luis)

1. **Formato de salida:** link web (HTML, embebible en iframe) + PDF descargable. **No** se genera `.pptx` editable — prioriza velocidad de construcción y fidelidad visual sobre edición nativa en PowerPoint.
2. **Imágenes:** generadas por IA por slide, contextuales al rubro del cliente, usando `budgetpixel`/`higgsfield` (ya conectados en la cuenta). Si falla la generación de una imagen puntual, esa slide cae a un fondo/gradiente de marca AT genérico — nunca bloquea la propuesta completa.
3. **Hosting del render:** contenedor nuevo en el mismo VPS de Easypanel donde corre n8n (`n8n-n8n.kchiba.easypanel.host`). Cero costo de infraestructura adicional.
4. **Estilo visual:** "Cinematográfico Oscuro" — foto de fondo a página completa con degradado navy, logo AT real (no un placeholder) pequeño y translúcido en la esquina superior izquierda (consistente con la regla de marca AT para todo material audiovisual), texto grande abajo con barra de acento teal. Aprobado con mockups en la sesión de brainstorming (ver `.superpowers/brainstorm/` — no versionado, son solo mockups de la sesión).
5. **"Sin depender de terceros"** se interpreta como *sin depender de Gamma*. OpenAI (ya en uso) y budgetpixel/higgsfield (ya conectados) siguen siendo dependencias externas para texto e imágenes — no es un pipeline 100% self-hosted de IA.

## Arquitectura

```
Webhook (Entrada)
  → Obtener Tasa CLP
  → OpenAI (Cerebro)          [CAMBIA: JSON estructurado en vez de gamma_prompt libre]
  → Limpiar JSON
  → Generar Imágenes Propuesta [NUEVO: budgetpixel/higgsfield, 1 imagen por slide de contenido]
  → OpenAI (Personalidad Bot)  [sin cambios]
  → Guardar en BD              [sin cambios]
  → Renderizar Propuesta       [NUEVO: HTTP → microservicio propuesta-renderer]
  → Guardar Presentación       [NUEVO: HTTP → api-save-presentation.php]
  → Email Aprobación (Admin)   [CAMBIA: incluye link real a la presentación, ya no un prompt para copiar]
  → (resto del workflow sin cambios: ¿Tiene Drive File ID? → Renombrar/Archivar Drive)
```

## Componentes

### 1. `OpenAI (Cerebro)` — esquema de salida (editar node existente)

Reemplaza el campo único `gamma_prompt` (texto libre para que un humano/Gamma lo interprete) por campos estructurados que la plantilla consume directamente:

- `client_name`, `company_name` (sin cambios)
- `challenge_title`, `challenge_text` (slide 2)
- `solution_title`, `solution_text` (slide 3)
- `benefits: [{title, text}]` (slide 4, 4-5 ítems)
- `how_it_works: [{step_title, step_text}]` (slide 5, 3 pasos)
- `pricing_rows: [{service, price_usd, price_clp}]`, `pricing_note` (slide 6)
- `next_steps: [string]` (slide 7)
- `image_briefs: [{slide, prompt}]` — brief corto de foto por slide de contenido (5 items), contextual al rubro del cliente
- `email_subject`, `email_body_summary` (sin cambios, para el correo al cliente)

### 2. Nodo n8n nuevo: `Generar Imágenes Propuesta`

Itera `image_briefs[]` (5 ítems) llamando a `budgetpixel`/`higgsfield`, recolecta las URLs resultantes. Si una falla, esa entrada queda `null` y la plantilla usa el fallback de marca AT para esa slide (no reintenta indefinidamente, no bloquea el resto).

### 3. Microservicio nuevo: `propuesta-renderer`

- Node.js + Express + Playwright (headless Chrome), contenedor nuevo en Easypanel.
- `POST /render`: recibe el JSON estructurado completo (texto + URLs de imágenes + `unique_id` de la propuesta) → rellena la plantilla HTML/CSS (diseñada en esta sesión, "Cinematográfico Oscuro", logo real AT) → guarda la página renderizada en una ruta pública estable `/p/{unique_id}` → genera el PDF con `page.pdf()` en `/p/{unique_id}.pdf` → responde `{view_url, pdf_url}`.
- **Requisito explícito:** la ruta `/p/{unique_id}` no debe enviar `X-Frame-Options: DENY` ni un CSP que bloquee `frame-ancestors` — debe quedar embebible en el `<iframe>` de `ver-presentacion.php`.
- La plantilla vive versionada en este repo, ej. `renderer/template/` (HTML + CSS + assets), diseñada primero como mockup en Claude Design y luego adaptada a placeholders dinámicos.
- Usa el logo real de AutomatizaTech: `https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech%20+%20slogan.png` (mismo asset que ya usa el prompt de `OpenAI (Cerebro)` hoy), aplicado como watermark pequeño y discreto según la regla de marca AT vigente (máx. ~4% del ancho, opacidad ~35-45%, nunca centrado ni agrandado).

### 4. Endpoint PHP nuevo: `api-save-presentation.php`

- Mismo patrón de seguridad que `api-save-proposal.php` (ya corregido hoy): `require_once at-rate-limit.php` + `at-webhook-verify.php`, verificación HMAC con fallback a whitelist de IP.
- Recibe `{unique_id, view_url, pdf_url}` y ejecuta:
  ```sql
  UPDATE wp_automatiza_propuestas
  SET gamma_iframe_url = %s, pdf_path = %s
  WHERE unique_link_id = %s
  ```
- **No requiere cambios de schema** — `gamma_iframe_url` (TEXT) y `pdf_path` (TEXT) ya existen en la tabla (confirmado leyendo `setup-propuestas-db.php` + `update-db-schema-v2.php`).

### 5. Reuso del panel de WordPress existente (sin cambios de código)

`wp-content/themes/automatiza-tech/inc/admin-proposals.php` (panel real registrado en `wp-admin/admin.php?page=automatiza-proposals`) ya lee `gamma_iframe_url` y `pdf_path` y los precarga en el formulario de edición. `ver-presentacion.php` ya embebe `gamma_iframe_url` en un `<iframe>` y ya muestra un placeholder ("Estamos finalizando los detalles") cuando está vacío. **No se toca ninguno de los dos archivos.** El único cambio de comportamiento es que esos campos llegan pre-llenados en vez de vacíos.

### 6. `Email Aprobación (Admin)` — contenido (editar node existente)

Se actualiza el HTML del correo: en vez de "copia este prompt y pégalo en Gamma", muestra un resumen del contenido generado (desafío/solución/beneficios) y un botón directo "Ver Presentación" apuntando al `view_url` real.

## Manejo de errores

- Falla de imagen individual → fallback de marca AT para esa slide, no bloquea el resto (ver componente 2).
- Falla del microservicio de render (caído, timeout) → el nodo `Renderizar Propuesta` no bloquea el workflow (`onError: continueRegularOutput`); `gamma_iframe_url`/`pdf_path` quedan vacíos como hoy, `ver-presentacion.php` ya maneja ese caso con su placeholder existente, y Luis conserva la opción de pegar un link a mano en el panel como respaldo — nunca queda en un estado roto o sin salida.
- `api-save-presentation.php` sigue el mismo patrón de manejo de errores que `api-save-proposal.php` (respuestas JSON siempre válidas, rate-limit, HMAC).

## Testing

1. Microservicio `propuesta-renderer` probado de forma aislada con el JSON real ya generado en esta sesión (propuesta "Academia de Béisbol X / Jeffer Garcia") — verificación visual en navegador de `view_url` y `pdf_url`.
2. Verificación de que `/p/{unique_id}` es embebible en iframe (sin bloqueo de `X-Frame-Options`/CSP).
3. Ejecución real de punta a punta en n8n (workflow `Propuesta AutomatizaTech (Con Tasa CLP)`, `APuTGmusbjLAJ74w`) con un caso de prueba: confirmar imágenes generadas, render correcto, `UPDATE` en `wp_automatiza_propuestas`, y que `automatiza-proposals&edit_id=X` muestre los campos precargados.

## No-goals (fuera de este spec)

- **Asesor de precios/margen** (comparar precio propuesto vs. costo real de entrega — suscripciones IA, APIs de imágenes, etc. — y sugerir margen): spec independiente, a diseñar después de este.
- **Automatización del envío final al cliente** tras aprobación de Luis (conectar `Esperar Aprobación`/`Enviar al Cliente` en n8n, o automatizar el clic "Guardar y Enviar" del panel WP): decisión pendiente de Luis, no se toca en este spec. Este spec sí reduce el trabajo manual restante (los campos ya llegan pre-llenados), pero el clic final de envío se mantiene humano.
- Exportación a `.pptx` editable nativo.
- Reemplazar OpenAI o budgetpixel/higgsfield por alternativas 100% self-hosted.

## Preguntas abiertas / supuestos a validar en el plan de implementación

- Confirmar credenciales/plan disponible en budgetpixel o higgsfield para 5 generaciones de imagen por propuesta (costo por propuesta, límites de cuenta).
- Confirmar acceso de Luis a Easypanel para desplegar el nuevo contenedor `propuesta-renderer` (o si lo despliega Claude/Codex con las credenciales ya usadas para n8n).
- Definir dominio/ruta pública final para `/p/{unique_id}` (subdominio propio en el VPS vs. proxy bajo `automatizatech.cl`).
