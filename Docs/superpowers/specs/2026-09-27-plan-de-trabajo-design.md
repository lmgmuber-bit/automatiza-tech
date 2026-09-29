# Plan de trabajo del proyecto (cronograma con carta Gantt) — diseño

Fecha: 2026-09-27; ajustado el 2026-09-29 con las decisiones 6 a 9 de Luis. Rama: `claude/plan-de-trabajo` (sale de `claude/cierre-cliente`, PR #50; usa además el renderer y los
flujos n8n de `claude/propuestas-json-robusto`, PR #51). Estado: diseño aprobado por Luis en conversación; falta su revisión
de este documento.

## Objetivo

Cuando el cliente firma el contrato de servicios, AutomatizaTech le entrega un **plan de trabajo**: qué se hace, en qué
orden, cuánto demora cada parte, qué necesitamos del cliente y cuándo revisa y aprueba, presentado dentro del **Método AT**
con la misma plantilla y estilo que la presentación de la propuesta. Luis recibe un borrador, lo edita y lo aprueba; desde
el panel lo envía al cliente por correo y WhatsApp, con la invitación a agendar una **llamada de seguimiento** para
revisarlo y aclarar dudas, por la web o por WhatsApp con Tech.

## Contexto verificado (27-sep)

- **Método AT publicado** (sección «Método» de la portada, `assets/home-premium/body.html:410-449`): seis fases —
  Diagnóstico, Priorización, Propuesta por fases, Diseño y desarrollo, Implementación, Soporte y mejora continua. **La web no
  promete plazos**: los tiempos del plan no pueden salir de ahí.
- **Contrato de servicios** (`Docs/CONTRATO_SERVICIO_DESARROLLO.md` v1.1): 4.1 el plazo hoy dice «Se define con EL CLIENTE
  en la reunión de inicio y queda por escrito» (lo pone `at_cc_datos_contrato()`); 4.2 el plazo corre desde que AT recibe el
  anticipo y los insumos (logo, textos, accesos); 6.1 el cliente tiene **5 días hábiles** para aprobar cada avance. El plan
  de trabajo es ese «por escrito».
- **Plazos ya prometidos**: las propuestas antiguas (14, 15, 16, 21, 22) decían solo «Implementación (N semanas)», entre 4 y 8
  semanas para un proyecto completo, sin desglose por fase. Es el único precedente.
- **Renderer** (`renderer/`): `server.js`, `render.js` (Playwright → PDF), `higgsfield.js`, `photo-manifest.js` e
  `images-store.js` son genéricos; `template.js` y `schema.js` están atados a la propuesta. Se extiende con un tipo de
  documento nuevo sin tocar el de propuestas.
- **Firma del cliente**: `ContractService::sign_as_client()` (`contracts/contract-service.php:588-666`) guarda la firma,
  regenera el PDF y manda las copias; hoy no dispara ningún hook.
- **Reuniones**: el formulario de la portada y Tech en la web crean **demos** (`wp_automatiza_leads`, flujo n8n `saveLead`).
  Las de **seguimiento** van por otro carril: `wp_automatiza_followup_meetings` y
  `automatiza_tech_create_followup_calendar_event()` → webhook n8n `followup-meeting` (evento en Google Calendar con Meet).
  Tech en WhatsApp (bot `bBcNlFgBzQ0766Mq`, +56 9 2700 2984) ya agenda con Google Calendar.

## Decisiones de Luis

1. Diseño general aprobado (propuesto el 27-sep).
2. **Lo que se agenda desde el plan es siempre una reunión de seguimiento**, fija: el cliente ya es cliente, nunca se crea
   una demo ni un lead.
3. **Tiempos de referencia = opción A**: una tabla por tipo de servicio que Claude propone, Luis corrige una vez y queda
   editable en el panel.
4. El documento **presenta el Método AT y dice en qué etapa estamos**: Diagnóstico ✓ y Priorización ✓ hechas; «Estás aquí:
   Propuesta por fases», que se cierra con la firma; lo que viene: Diseño y desarrollo → Implementación → Soporte y mejora
   continua.
5. Nada le llega al cliente sin el clic de Luis.
6. **(29-sep) El plan vive en el módulo de clientes, no en el de propuestas.** Mismo estilo y botones que el panel de
   propuestas, pero en la ficha del cliente oficial (CRM › Ficha de Cliente): ahí Luis edita semanas, días y
   actividades del cronograma.
7. **(29-sep) Imágenes:** las láminas que ya existían en la propuesta reutilizan sus fotos; las **láminas nuevas** (Método
   AT, carta Gantt, fases, qué necesitamos, reuniones) llevan **fotos nuevas**, con su cantidad y costo a la vista antes
   de «Aprobar».
8. **(29-sep) El cierre del documento trae enlaces que se tocan** para agendar la llamada de seguimiento: por WhatsApp con
   Tech o por el sitio web.
9. **(29-sep) Luis también agenda desde el panel** la llamada de seguimiento con los datos del cliente, por el flujo normal
   de reuniones de seguimiento: evento en Google Calendar con Meet y aviso al cliente por correo y por WhatsApp.

## Etapas

- **Etapa 1 — solo Luis ve:** disparador al firmar y botón manual, tabla de tiempos, borrador con IA, documento con carta
  Gantt, edición en el panel, «Pedir cambios», «Aprobar».
- **Etapa 2 — el cliente recibe:** envío por correo (PDF y enlace), «Enviar por mi WhatsApp», `ver-plan.php` y agenda web de
  la reunión de seguimiento.
- **Etapa 3 — requiere a Meta y el ok de Luis para tocar el bot:** plantilla de WhatsApp de Utilidad `plan_trabajo_listo`
  enviada automáticamente (con los avisos de no entrega de la Task 19) y Tech reconociendo el código del plan para agendar
  el seguimiento.

## Componentes

### 1. Disparador

- Al final de `ContractService::sign_as_client()`, después de las copias por correo: `do_action('at_contrato_firmado',
  $fresh)` dentro de `try/catch`; un fallo del plan nunca afecta la firma.
- El módulo nuevo escucha ese hook: si el contrato es `servicios`, tiene `proposal_id` y no existe un plan para ese contrato,
  crea el plan en estado `generando` y llama al flujo n8n «Plan 1 Borrador». Idempotente: un contrato, un plan.
- Botón **«Crear plan de trabajo»** en la pestaña «Plan de trabajo» de la ficha del cliente, para contratos ya firmados antes de este cambio (p. ej. el
  contrato 12) o si el disparo automático falló.

### 2. Datos

Tabla nueva `wp_automatiza_planes_trabajo` (creada con `dbDelta`, como las demás del cierre):
`id`, `propuesta_id`, `contrato_id`, `tech_id`, `codigo` (12 caracteres aleatorios, único), `estado` (`generando`,
`borrador`, `cambios`, `aprobando`, `listo`, `enviado`, `error`), `fecha_inicio` (DATE), `payload` (JSON del plan),
`comentarios` (último pedido de cambios), `view_url`, `pdf_url`, `created_at`, `updated_at`, `enviado_at`.
Las notas del historial van al Seguimiento de la propuesta, con los tipos internos del cierre.

JSON del plan (`payload`; ejemplo abreviado: «…» son más elementos del mismo tipo):

```json
{
  "fecha_inicio": "2026-10-05",
  "fases": [
    {"clave": "diseno_desarrollo", "titulo": "Diseño y desarrollo",
     "bloques": [
       {"nombre": "Arranque", "actividades": [
         {"nombre": "Reunión de inicio", "detalle": "…", "responsable": "ambos", "dias_habiles": 1,
          "servicio": "sitio_una_pagina", "origen": "tabla"},
         {"nombre": "Entrega de logo, textos y accesos", "responsable": "cliente", "dias_habiles": 3, "origen": "tabla"}]},
       {"nombre": "Diseño", "actividades": ["…"]},
       {"nombre": "Desarrollo", "actividades": ["…"]},
       {"nombre": "Pruebas y revisión", "actividades": ["…"]}]},
    {"clave": "implementacion", "titulo": "Implementación", "bloques": ["…"]},
    {"clave": "soporte", "titulo": "Soporte y mejora continua", "bloques": ["…"]}
  ],
  "hitos": [{"nombre": "Diseño aprobado", "despues_de": "Diseño"}],
  "necesitamos_de_ti": ["Logo y colores", "Textos", "Accesos al dominio y hosting"],
  "reuniones": [{"nombre": "Reunión de inicio"}, {"nombre": "Llamada de seguimiento del plan"}, {"nombre": "Entrega y capacitación"}]
}
```

`responsable`: `at`, `cliente` o `ambos`. `origen`: `tabla` (duración de la tabla de referencia), `ia` (estimada por la IA;
se marca «revisar» en el panel) o `luis` (editada a mano).

### 3. Tabla de tiempos de referencia

Opción `at_pt_duraciones` (JSON), editable en «Propuestas › Ajustes del plan». Días hábiles por fase y tipo de servicio. La
revisión del cliente (5 días hábiles, cláusula 6.1) se suma aparte, una vez por entrega. Propuesta inicial, **para que Luis
la corrija antes de usarla**:

| Tipo de servicio | Diseño | Desarrollo | Pruebas | Implementación | Total con revisión | Fuente |
|---|---|---|---|---|---|---|
| Sitio de una página | 3 | 5 | 2 | 1 | 16 d.h. (~3 sem.) | supuesto |
| Sitio web / tienda | 5 | 10 | 3 | 2 | 25 d.h. (~5 sem.) | precedente 4-6 sem. (16, 21, 22) |
| Asistente básico | 2 | 4 | 2 | 1 | 14 d.h. | supuesto |
| Asistente avanzado | 3 | 8 | 3 | 2 | 21 d.h. | supuesto |
| Plataforma o sistema a medida | 8 | 20 | 5 | 3 | 41 d.h. (~8 sem.) | precedente 6-8 sem. (14, 15) |
| Automatización (flujo n8n) | 2 | 5 | 2 | 1 | 15 d.h. | supuesto |
| Google Ads (puesta en marcha) | 2 | 3 | 1 | 1 | 12 d.h. + mensual | supuesto |

«Arranque» fijo, que se suma a los totales de la tabla: reunión de inicio 1 día y entrega de insumos por el cliente 3 días
hábiles (cláusula 4.2). Si el proyecto combina servicios, cada fase suma las actividades de todos, en secuencia salvo las
marcadas «en paralelo».

### 4. Borrador con IA (n8n «Plan 1 Borrador»)

- Webhook `plan-v1-borrador` con clave propia (`X-AT-Plan-Key`, credencial de n8n, como `X-AT-Borrador-Key`).
- Entrada: id y código del plan, lo aceptado (filas de la aceptación), alcance y entregables de la propuesta
  (`solution_text`, `how_it_works`), extracto de la transcripción de la reunión si existe, y la tabla de tiempos.
- GPT-4o (mismo nodo que el borrador de propuestas) devuelve el JSON del plan: arma actividades concretas para lo contratado y
  asigna a cada una un `servicio` de la tabla; no inventa duraciones si la actividad calza con la tabla.
- WordPress valida el JSON (tipos, responsables, días enteros de 1 a 60, fases conocidas), **pone los días de la tabla** donde
  calza y marca `origen: ia` donde no, y calcula las **fechas** en días hábiles desde `fecha_inicio` (por defecto, el lunes
  hábil siguiente a la firma; Luis la cambia en el panel), saltando los feriados que ya usa la agenda (`appointments-config`).
  El primer bloque es el arranque.
  Fases en secuencia; actividades de una fase en secuencia salvo las que Luis marque «en paralelo».
- Vista previa sin fotos nuevas (renderer, `document_type: "plan"`) y correo a Luis «Borrador del plan de trabajo listo».
- «Plan 2 Cambios»: webhook `plan-v1-cambios`; la IA aplica los comentarios de Luis sin tocar los días `origen: luis`.

### 5. Documento (renderer)

- `document_type: "plan"` en `POST /render`; `schema.js` suma `validatePlanPayload()`; archivo nuevo `template-plan.js` con
  `renderPlanHtml()`. `template.js` y las propuestas no cambian.
- Mismos estilos, tipografía, logo y modo presentación que la propuesta. Láminas:
  1. **Portada**: «Plan de trabajo — {proyecto}».
  2. **El Método AT**: las seis fases; Diagnóstico ✓ y Priorización ✓; **«Estás aquí: Propuesta por fases»**, cerrada con tu
     firma del {fecha}; próximas: Diseño y desarrollo → Implementación → Soporte y mejora continua.
  3. **Carta Gantt** por semanas: barras por bloque, rombos de hitos, franjas de «tu revisión (5 días hábiles)» y la fecha
     estimada de entrega. HTML y CSS en grilla, sin librerías; en el PDF va apaisada y legible en el celular.
  4. **Una lámina por fase**: actividades, responsable, duración, entregable y qué aprobamos juntos.
  5. **Qué necesitamos de ti**: insumos y la regla de la cláusula 4.2 («el plazo corre desde que los recibimos»).
  6. **Reuniones y soporte**: inicio, seguimiento, entrega y capacitación; garantía y servicios mensuales si los hay.
  7. **Cierre**: «Agenda tu llamada de seguimiento», con **dos enlaces que se tocan** (decisión 8): «Por WhatsApp con Tech»
     (`wa.me/56927002984` con el mensaje y el código del plan) y «En el sitio web» (`ver-plan.php?id=<codigo>&agendar=1`,
     que abre el selector de horarios). Los enlaces funcionan en la presentación y en el PDF.
- Fotos (decisión 7):
  - **Reutilizadas, sin costo:** las láminas que ya existían en la propuesta usan sus fotos del mismo almacén de
    imágenes: portada (foto de portada de la propuesta) y cierre (foto de próximos pasos).
  - **Nuevas, con costo:** Método AT, carta Gantt, una por fase, «Qué necesitamos de ti» y «Reuniones y soporte». La IA
    del borrador escribe sus descripciones por rubro con las mismas reglas de la propuesta (`fotos_guard.py`: personas del
    rubro, sin texto ni pantallas). El panel muestra cuántas son y su costo antes de «Aprobar» (mismo cálculo que
    `at_propuesta_costo_fotos()`), y el renderer reutiliza las ya guardadas con el mismo prompt (`img/manifest.json`).
  - Lección del 28-sep: Soul 2 imprime letras en prendas, envases y paredes; para escenas con personas conviene
    `alibaba/qwen-image-3/text-to-image` o `recraft/v4.1/text-to-image` (12 de 12 limpias). Se decide al implementar.
- Salida: `/p/<codigo_plan>/index.html` y `presentation.pdf`.
- Las fechas del documento dicen «estimadas» y «desde que recibimos el anticipo y tus insumos», para no prometer una fecha
  fija que dependa del cliente.

### 6. Panel (WordPress)

Pestaña **«🗓️ Plan de trabajo»** en la **ficha del cliente** (CRM › Ficha de Cliente, `crm-ai-completo.php`, junto a
«🚀 Proyectos» y «📜 Contratos y operación»; decisión 6). Mismo estilo y botones que el panel de propuestas. Si el cliente
tiene más de un contrato firmado, un selector elige el plan. Contiene:

- estado y vista previa;
- fecha de inicio;
- tabla editable: bloque, actividad, responsable, días, «en paralelo», con el origen de cada duración (tabla, IA o Luis);
- botones:
  - «Guardar y recalcular»: sin IA; recalcula fechas y vuelve a renderizar;
  - «Pedir cambios»: con comentarios a la IA;
  - «Aprobar»: versión final; fotos nuevas solo si se pidieron;
  - «Destrabar»: si queda en `generando` o `error`;
  - «Enviar al cliente» y «Enviar por mi WhatsApp»: solo desde `listo`, con confirmación;
  - **«Agendar llamada de seguimiento»** (decisión 9): abre el formulario de reuniones de seguimiento que ya existe
    (`inc/admin-followup-meetings.php`) con los datos del cliente precargados y el tipo fijo «Seguimiento del plan de
    trabajo». Usa el flujo normal: evento en Google Calendar con Meet (`automatiza_tech_create_followup_calendar_event()`)
    y aviso al cliente por correo (`automatiza_tech_send_followup_email()`) y por WhatsApp
    (`automatiza_tech_send_followup_whatsapp()`), con las casillas de siempre.

«Propuestas › Ajustes del plan»: tabla de tiempos.

### 7. Envío y vista del cliente (Etapa 2)

- **Correo al cliente**: plantilla de marca del cierre, asunto «Tu plan de trabajo — {proyecto}», PDF adjunto (hasta 15 MB,
  como la propuesta), enlace a `ver-plan.php?id=<codigo>` y dos botones: «Agendar mi llamada de seguimiento» (web) y
  «Agendar por WhatsApp con Tech». Reply-To al correo principal del cierre y copia oculta de «Ajustes del cierre».
- **`ver-plan.php`**: iframe del renderer, `nocache`, barra inferior con los dos botones de agenda, sin aceptar ni rechazar.
  Logo y colores de AT, como la barra del cierre.
- **Agenda web de seguimiento**: diálogo en `ver-plan.php` con el mismo selector de horarios (`at-agenda.js`, disponibilidad
  de `check-availability`). Envía a una ruta nueva `POST /wp-json/automatiza-tech/v1/plan-seguimiento` (nonce, código del
  plan en estado `enviado`, límite por IP). El **tipo es fijo: «Seguimiento del plan de trabajo»**. Crea la fila en
  `wp_automatiza_followup_meetings` enlazada al cliente y llama `automatiza_tech_create_followup_calendar_event()` (evento en
  Google Calendar con Meet). Nunca toca `wp_automatiza_leads` ni el flujo `saveLead`. El cliente no reescribe sus datos: salen
  del plan. Confirmación en pantalla y por correo al cliente; aviso a Luis.
- **WhatsApp (Etapa 2)**: «Enviar por mi WhatsApp» abre `wa.me` con el mensaje y el enlace. El botón del cliente abre
  `wa.me/56927002984` con «Hola Tech, quiero agendar la llamada de seguimiento de mi plan de trabajo (código XXXX)».
- **Etapa 3**: plantilla Meta de Utilidad `plan_trabajo_listo` con botones «Ver mi plan» y «Agendar seguimiento»; Tech
  reconoce el código y agenda por el carril de seguimiento. Requiere aprobación de Meta y el ok de Luis para tocar el bot.

## Errores y seguridad

- El flujo n8n usa el flujo común «0 Avisar error»; el plan queda en `error` con «Destrabar». El render se reintenta hasta 3
  veces, como en la versión final de la propuesta.
- El disparador nunca rompe la firma (`try/catch` más nota interna si falla).
- Rutas REST de n8n con `X-AT-Secret`; la ruta pública de agenda exige nonce, código válido en estado `enviado` y límite de
  intentos. El código del plan es aleatorio de 12 caracteres; la URL no lleva datos personales.
- El repositorio es público: sin nombres ni datos de clientes en código, pruebas ni commits.

## Pruebas

- **WordPress** (`tests/plan/`):
  - cálculo de fechas en días hábiles con feriados, secuencia y paralelos;
  - tabla → días y marcas de origen;
  - validación del JSON;
  - disparador idempotente que no rompe la firma;
  - acciones del panel;
  - la ruta de agenda crea una reunión de seguimiento y **ninguna** demo ni lead;
  - correos.
- **Renderer**: prueba de `template-plan.js` (láminas, «Estás aquí» en Propuesta por fases, Gantt con semanas y hitos) y de
  que el HTML de propuestas no cambia.
- **n8n**: `probar_plan.py`, como `probar_revision_fotos.py`.
- **Vista**: celular (390 px) y escritorio, con capturas.

## Fuera de alcance

Reuniones de seguimiento recurrentes, avance real del proyecto contra el plan (tareas y porcentajes), facturación por hitos y
el portal del cliente mostrando el plan (posible fase siguiente: enlazar el plan desde la pestaña «Proyectos» del CRM).

## Pendientes de Luis

1. Corregir la tabla de tiempos de referencia (sección 3) antes de la primera generación.
2. Revisar este documento (con los ajustes del 29-sep) y dar el ok para empezar la etapa 1.
3. Decidir si el PR #50 (cierre de cliente) se mergea antes de empezar: esta rama parte de él.
4. Etapa 3: plantilla de Meta y ok para tocar el bot.
