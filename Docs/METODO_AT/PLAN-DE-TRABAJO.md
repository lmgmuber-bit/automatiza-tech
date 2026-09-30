# Plan de trabajo con carta Gantt — Etapa 1

Guía del módulo que le arma a cada cliente con contrato de servicios firmado un **plan de trabajo**: qué se hace, en qué
orden, cuánto demora cada parte, qué necesitamos del cliente y cuándo revisa y aprueba. Se presenta dentro del Método AT,
con la misma plantilla y estilo que la presentación de la propuesta.

- Diseño: `Docs/superpowers/specs/2026-09-27-plan-de-trabajo-design.md`.
- Decisiones de la Etapa 1 (D1 a D21, mandan sobre el resto): `Docs/superpowers/plans/2026-09-29-plan-de-trabajo-etapa-1/contrato-interfaces.md`.
- Rama `claude/plan-de-trabajo` (WordPress y n8n) y `claude/plan-renderer` (renderer). Desplegado el 30-sep-2026, cerca de las 00:00 hora de Chile, con autorización de Luis.
- 🔴 **Estado real:** el código y las pruebas locales están hechos y todo está subido, pero **nunca ha corrido con un contrato real**. Los tres flujos de n8n no han tenido una sola ejecución en el n8n de producción. En la primera prueba hay que revisar las ejecuciones (ver «Lo que falta»).
- 🔴 **En la Etapa 1 nada le llega al cliente.** Ni correo ni WhatsApp. Los correos que genera el módulo son solo para Luis.

## Qué es y cuándo se dispara

El plan es el «por escrito» que el contrato de servicios promete en su cláusula 4.1 («el plazo se define con EL CLIENTE en la
reunión de inicio y queda por escrito»). Sus fechas dicen «estimadas» y corren desde que AT recibe el anticipo y los insumos
del cliente (cláusula 4.2). La revisión del cliente es de 5 días hábiles por cada entrega (cláusula 6.1).

**Disparo automático.** Al final de `ContractService::sign_as_client()` (`contracts/contract-service.php`), después de mandar
las copias, se emite `do_action('at_contrato_firmado', $fresh)` dentro de un `try/catch`: un fallo del plan **nunca** afecta
la firma. El módulo escucha ese hook (`at_pt_al_firmar`, `inc/plan-trabajo/disparador.php`):

1. Solo actúa si el contrato es de tipo `servicios`.
2. Si ya existe un plan para ese contrato, no hace nada. Un contrato, un plan (llave única `contrato_id`). Con o sin
   propuesta: un contrato sin propuesta también tiene plan.
3. Crea el plan en estado `generando` y avisa al flujo n8n «1 Borrador». Espera a lo más 5 segundos, porque va dentro de la
   petición en que el cliente firma.
4. Si n8n no recibe el aviso, el plan queda en `error` con el motivo y desde el panel se reintenta.

**Disparo manual.** Para contratos firmados antes de este cambio, o si el automático falló, la pestaña «🗓️ Plan de
trabajo» muestra el botón **«Crear plan de trabajo»** de cada contrato de servicios firmado que aún no tiene plan.

## Estados y transiciones

| Estado | Qué significa |
|---|---|
| `generando` | La IA está armando el primer borrador. |
| `borrador` | Hay plan y vista previa sin fotos; Luis puede editar, pedir cambios o aprobar. |
| `cambios` | La IA está aplicando un pedido de cambios de Luis. |
| `aprobando` | Se están generando las fotos y la versión final. |
| `listo` | Versión final con fotos, presentación y PDF. |
| `enviado` | Reservado para la Etapa 2: en la Etapa 1 no se llega a este estado. |
| `error` | Algo falló; la nota dice qué. |

Transiciones válidas (`at_pt_transiciones()`):

```
generando -> borrador | error
borrador  -> borrador | cambios | aprobando | error
cambios   -> borrador | error
aprobando -> listo | error
listo     -> borrador | cambios | aprobando | enviado
error     -> generando | borrador | cambios | aprobando
enviado   -> (ninguna en la Etapa 1)
```

Cualquier otra transición se rechaza. Guardar desde `listo` o `error` devuelve el plan a `borrador`: la versión final hay que
aprobarla de nuevo.

## Qué hace Luis: pestaña «🗓️ Plan de trabajo»

Está en **CRM › Ficha de Cliente**, junto a «🚀 Proyectos» y «📜 Contratos y operación». El plan vive en el módulo de
clientes, no en el de propuestas. Si el cliente tiene más de un contrato firmado, un selector elige el plan. Todas las
acciones exigen ser administrador y llevan un nonce por plan.

La pestaña muestra el estado, las fechas estimadas (inicio, entrega estimada y semanas), la vista previa y el PDF, y avisa si
el cliente no tiene correo (la lámina «Sigue tu proyecto» saldría sin enlace al portal) o si el contrato no tiene propuesta
(la portada y el cierre llevarán fotos nuevas).

**Editar y «Guardar y recalcular».** La tabla trae, por fase, bloques y actividades con responsable (AutomatizaTech, Tú o
Ambos), días hábiles, «en paralelo» y el origen de cada duración. Se pueden editar el nombre del proyecto, la fecha de
inicio, la descripción de cada fase, «qué necesitamos del cliente», reuniones, hitos y servicios mensuales. Los meses de
garantía son de solo lectura: vienen del contrato y se cambian allá. Guardar **no usa IA**: recalcula las fechas en días
hábiles y pide una vista previa nueva (sin fotos). Lo que Luis cambia queda con origen `luis`. Si la fecha de inicio cae en
fin de semana o feriado, el plan parte el día hábil siguiente. Sin fecha, parte el primer lunes hábil después de la firma.

**«Pedir cambios».** Luis escribe qué quiere que cambie la IA (`comentarios`) y el flujo «2 Cambios» los aplica. Respeta los
días que Luis editó a mano. No genera fotos. Llega un correo con la vista previa nueva.

**«Aprobar y generar versión final».** Genera las fotos nuevas y la versión final. El botón muestra el costo antes de
confirmar (ver «Revisión de texto de las fotos»). Queda `listo`, o `error` con el motivo. Llega un correo a Luis.

**«Destrabar».** Aparece mientras el plan está en `generando`, `cambios` o `aprobando`. La pestaña avisa que la IA está
trabajando y que, **si lleva más de 20 minutos, hay que destrabarlo**. El botón pasa el plan a `error` con la nota
«Destrabado por Luis»; no llama a n8n. Ese umbral de 20 minutos es una indicación escrita en el panel, no un temporizador:
el sistema no destraba solo.

**Reintentar.** Con el plan en `error`, el panel ofrece «🔁 Reintentar borrador» (si no hay plan guardado: vuelve a pedir el
borrador a la IA) o «↩️ Volver al borrador» (si ya hay plan: vuelve a `borrador` sin llamar a nadie).

**«📅 Agendar llamada de seguimiento».** Abre `admin.php?page=automatiza-followup&pt_plan=<id>`, el formulario de reuniones de
seguimiento que ya existía, con los datos del cliente precargados y el asunto «Seguimiento del plan de trabajo — {proyecto}».
Usa el flujo normal: evento en Google Calendar con Meet, y aviso al cliente por correo y por WhatsApp **solo si Luis deja
marcadas las casillas de siempre**. Nunca crea una demo ni un lead: lo que se agenda desde el plan es siempre una reunión
de seguimiento.

No existen todavía «Enviar al cliente» ni «Enviar por mi WhatsApp»: son de la Etapa 2.

## Ajustes del plan: tabla de tiempos

**CRM › Ajustes del plan** (`admin.php?page=at-pt-ajustes`). Es la tabla de días hábiles por tipo de servicio y etapa
(diseño, desarrollo, pruebas, implementación). Se guarda en la opción `at_pt_duraciones` (JSON), normalizada: claves
`[a-z0-9_]{2,40}`, nombre de 1 a 60 caracteres y días enteros de 0 a 60. Sin JavaScript: para agregar un tipo se llena una
fila vacía y para quitarlo se marca «Quitar». Si la opción está vacía, se usa la tabla de defecto
(`at_pt_duraciones_defecto()`):

| Servicio | Diseño | Desarrollo | Pruebas | Implementación |
|---|---|---|---|---|
| Sitio de una página | 3 | 5 | 2 | 1 |
| Sitio web o tienda | 5 | 10 | 3 | 2 |
| Asistente básico | 2 | 4 | 2 | 1 |
| Asistente avanzado | 3 | 8 | 3 | 2 |
| Plataforma o sistema a medida | 8 | 20 | 5 | 3 |
| Automatización (flujo n8n) | 2 | 5 | 2 | 1 |
| Google Ads (puesta en marcha) | 2 | 3 | 1 | 1 |

Solo «Sitio web o tienda» y «Plataforma» tienen un precedente en propuestas antiguas (4 a 6 y 6 a 8 semanas, sin desglose por
fase); las demás filas son **supuestos** de Claude, a la espera de que Luis las corrija. 🔴 La tabla de la Etapa 1 debe
revisarse antes de fiarse de los plazos.

**Cómo se usa.** La IA arma las actividades y asigna a cada una un servicio y una etapa. WordPress reparte el total de la
tabla entre las actividades de cada par (servicio, etapa) en proporción a lo que propuso la IA (mínimo 1 día por actividad) y
las marca `origen: tabla`. Lo que no calza con la tabla queda `origen: ia` y el panel lo marca «IA · revisar». Un grupo con
alguna actividad `origen: luis` no se toca. El **Arranque** es fijo y se suma a los totales: «Reunión de inicio» (ambos,
1 día) y «Entrega de logo, textos y accesos» (cliente, 3 días).

## Días hábiles y feriados

- Día hábil = lunes a viernes que no sea feriado. Las fechas son cadenas `Y-m-d` calculadas en UTC: nada depende de la zona
  horaria del servidor.
- Los **feriados** se leen del ajuste que ya usa la agenda: «Ajustes del chat» → `automatiza_chat_schedule['holidays']`,
  una fecha `AAAA-MM-DD` por línea. El plan no tiene lista propia.
- El 29-sep-2026 se cargaron en ese ajuste los 33 feriados nacionales oficiales de 2026 y 2027. La lista tiene 38 fechas
  porque se conservaron 5 fechas propias de Luis. 🔴 Al terminar 2027 hay que agregar los de 2028; si la lista queda corta,
  el plan cuenta como hábiles días que no lo son.
- El cálculo es en secuencia: fases, bloques y actividades en su orden. Cada actividad parte el hábil siguiente al último
  día del bloque hasta ese momento, salvo las marcadas «en paralelo», que parten el mismo día que la anterior (la primera
  de un bloque nunca es paralela).
- Un bloque con **entrega** agrega una franja «Tu revisión» de 5 días hábiles, y el cronograma sigue después de ella.
- La **entrega estimada** es el fin del último bloque de la fase «Implementación», sin contar la revisión.
- Topes que valida WordPress: 14 bloques (con el Arranque), 10 actividades por bloque, 60 actividades en total, 1 a 60 días
  por actividad y 130 días hábiles en total contando el Arranque (4 días) y las revisiones. Como la IA no manda el Arranque,
  a la IA se le dice 13 bloques, 58 actividades y 126 días. Si el plan los pasa, queda en `error` con una nota legible.

## Rutas REST (WordPress ← n8n)

Namespace `automatiza-tech/v1`. Todas usan `permission_callback => 'automatiza_proposals_rest_auth'`, o sea la misma clave de
las propuestas en la cabecera `X-AT-Secret` (`AT_REST_SECRET`): sin clave 401, clave mala 403. **No se creó ningún secreto
nuevo.** Código en `inc/plan-trabajo/rest.php`.

| Ruta | Para qué |
|---|---|
| `GET /plan/{id}/contexto` | Lo que recibe la IA: servicios contratados, alcance, entregables y fases siguientes del **contrato**; rubro y extracto de la reunión de la propuesta si existe; la tabla de tiempos; el plan actual y los comentarios. |
| `POST /plan/{id}/borrador` | `{plan, origen: 'borrador'\|'cambios'}`. Valida, aplica la tabla, calcula fechas, guarda, deja el plan en `borrador` y pide la vista previa. Si no valida: `error` y HTTP 422. Un borrador tardío (plan que ya no lo espera) recibe 409. |
| `GET /plan/{id}/render&modo=draft\|final` | Cuerpo para el renderer, más `propuesta_uid`, `crm_cliente_id` y `estado`. Se llama con `&modo=` porque la base ya trae `?rest_route=`. |
| `POST /plan/{id}/vista` | `{modo, ok, view_url, pdf_url, faltan, nota}`. Guarda enlaces; en `final` completo pasa `aprobando` a `listo`. Una vista previa tardía no pisa los enlaces si el plan ya está en `aprobando`, `listo` o `enviado`. |
| `POST /plan/{id}/error` | `{nota}`. Pasa a `error` solo desde `generando` o `cambios`. |

## Flujos n8n

Código en `N8N/plan-trabajo/` (builders `build_plan_*.py`, JSON generados, `correos_plan.py`, pruebas `probar_plan.py` y
`probar_revision_plan.py`). Los tres tienen `errorWorkflow` = «0 Avisar error» (`m7TOfKznVSBGz4Nd`). Los webhooks usan
autenticación por cabecera (credencial «AT REST Secret (header)», id `1NI0sJKc0kC430pb`) y responden **403 sin clave**. Los
correos a Luis enlazan solo a `automatizatech.cl`, nunca a `*.easypanel.host` (el SMTP de Hostinger los rechaza como spam).

| Flujo | Id | Nodos | Webhook | Qué hace |
|---|---|---|---|---|
| Plan de trabajo · 1 Borrador | `0ugLzUGG5fXJePEv` | 17 | `plan-v1-borrador` | Lee el contexto, GPT-4o arma el plan, lo entrega a WordPress y avisa a Luis. |
| Plan de trabajo · 2 Cambios | `mqHZt64Em9NBX0pD` | 19 | `plan-v1-cambios` | Igual, pero aplica los comentarios de Luis sobre el plan guardado sin cambiar los días `origen: luis`. |
| Plan de trabajo · 3 Render | `iO4PkBba8PBNWAv9` | 24 | `plan-v1-render` | Pide el render al renderer, reutiliza fotos, revisa el texto de las fotos, reintenta y guarda la vista. |

Detalle del flujo 3:

- En `draft` renderiza sin fotos. Si el plan ya está en `aprobando`, `listo` o `enviado`, **no renderiza** un `draft` (no pisa
  `/p/<codigo>/`).
- En `final` y con propuesta, reutiliza las fotos `cover` y `next_steps` de la propuesta desde su `img/manifest.json`, sin costo.
- Reintenta hasta **3 renders en total** por corrida si faltan fotos o el renderer falla (5xx o red). Un 400 con `details`
  no se reintenta. Una foto ya guardada con el mismo prompt no se vuelve a pagar (manifest del plan).
- Cierra guardando la vista en WordPress y mandando el correo a Luis.

Salida del renderer: `/p/<codigo>/index.html` y `presentation.pdf`, con el código aleatorio de 12 caracteres del plan.

**Renderer.** Tipo de documento `document_type: 'plan'` en `POST /render`, con `validatePlanPayload()` y `template-plan.js`
(`renderPlanHtml()`). Sin `document_type` es una propuesta, como siempre; el HTML de propuestas no cambia (hay una prueba
que lo compara byte a byte). Ocho láminas: portada, Método AT (con «Estás aquí: Propuesta por fases»), carta Gantt por
semanas, una lámina por fase, «Qué necesitamos de ti», reuniones y soporte, «Sigue tu proyecto» (portal del cliente) y cierre
con los enlaces para agendar la llamada de seguimiento por WhatsApp con Tech. El enlace web de agenda queda vacío hasta la
Etapa 2. Desplegado desde `claude/plan-renderer` `1a9a0df` (zip subido por Luis a Easypanel).

## Revisión de texto de las fotos y su costo

Las fotos nuevas del plan (Método AT, carta Gantt, una por fase, «Qué necesitamos», reuniones, portal, y con contrato sin
propuesta también portada y cierre) se generan con Soul 2, con la descripción escrita por la IA según el rubro. La imagen
generativa a veces imprime letras (prendas, envases, paredes), así que el flujo 3 aplica la **misma revisión de texto que las
propuestas** (decisión D18):

1. Solo en `final` y con la versión completa, una consulta a GPT-4o con las fotos nuevas (nunca la portada ni el cierre que
   vienen de la propuesta).
2. Las que traen texto se piden **una vez más** con la descripción más la instrucción `RETOMA`. La retoma cuenta dentro de los
   3 renders de tope y solo se hace si queda uno.
3. Una segunda consulta solo informa. Lo que siga con texto va como aviso en la nota y en el correo a Luis, **nunca** como error.
4. Decisión de Luis del 29-sep (opción A): si la foto rehecha no sale, el plan queda en `error`.

**Costo.** El panel lo muestra en el botón «Aprobar» (`at_pt_costo_fotos`): US$0,0032 por foto de lista más US$0,026 por
consulta de GPT-4o. Con propuesta son 8 fotos: **≈ US$0,0516**, máximo US$0,1032. Sin propuesta son 10 fotos:
≈ US$0,058, máximo US$0,116. El máximo cuenta las fotos dos veces más dos consultas. 🔴 El US$0,026 sale de la documentación de
OpenAI y el US$0,0032 de la cifra de lista de las propuestas: **no están medidos en una factura**. En la primera prueba real
hay que verificar que la revisión corrió y anotar el gasto real.

## Despliegue, respaldos y rollback

**Orden obligatorio: renderer → n8n → WordPress.** Nada dispara el plan hasta que sube el hook de la firma en WordPress, así
que lo último en subir es lo que enciende el módulo. En n8n va primero «3 Render» (los otros dos, al guardar el borrador, hacen que WordPress lo llame) y después «1» y «2».

1. **Renderer.** Zip de `claude/plan-renderer` `1a9a0df`, subido por Luis a Easypanel. Las propuestas publicadas siguen abriendo
   igual. Rollback: volver a subir el zip anterior.
2. **n8n.** `python N8N/propuestas-v3/deploy.py <ruta absoluta al json>` (crea el flujo si no existe y lo activa). Rollback:
   desactivar el flujo; mientras esté apagado, WordPress recibe un error y deja el plan en `error` con el motivo.
3. **WordPress por SSH** (30-sep, ~00:00). Antes se comparó el md5 de los 4 archivos compartidos contra la base
   `claude/cierre-cliente`. Se subieron los blobs de git, en LF como PROD:
   - la carpeta `inc/plan-trabajo/` (7 archivos: `ajustes`, `cargar`, `datos`, `disparador`, `panel`, `puras`, `rest`);
   - `assets/css/plan-trabajo.css` y `assets/js/plan-trabajo.js`;
   - `inc/admin-proposals.php` (una línea que carga el módulo), `contracts/contract-service.php` (el hook de la firma),
     `wp-content/mu-plugins/crm-ai-completo.php` (la pestaña) e `inc/admin-followup-meetings.php` (la precarga del seguimiento).

   `functions.php` no se toca. Se creó la tabla `wp_automatiza_planes_trabajo` (opción `at_plan_schema` = `1`).

**Respaldo:** `~/respaldos/plan-trabajo-antes-20260929-235935.tar.gz`.

**Rollback de WordPress:**

```
cd ~ && tar xzf respaldos/plan-trabajo-antes-20260929-235935.tar.gz && rm -rf domains/automatizatech.cl/public_html/wp-content/themes/automatiza-tech/inc/plan-trabajo domains/automatizatech.cl/public_html/wp-content/themes/automatiza-tech/assets/css/plan-trabajo.css domains/automatizatech.cl/public_html/wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js
```

La tabla puede quedarse: sin el código no se usa. Como siempre en Hostinger, `wp db export` no sirve; la tabla se respalda con
un script PHP (ver la guía de propuestas).

## Lo que falta

- 🔴 **Prueba real con un contrato.** Nadie ha visto correr los tres flujos en el n8n real ni el panel con un plan de verdad.
  Al probar hay que revisar, en n8n, las ejecuciones de «1 Borrador», «3 Render» y, si se piden cambios, «2 Cambios»; y
  comprobar que la revisión de texto corrió y cuánto costó.
- 🔴 Luis debe corregir la **tabla de tiempos** (mayormente supuestos) y revisar los primeros planes antes de mostrarlos.
- 🔴 Feriados de 2028 en adelante.
- Riesgos conocidos y aceptados en la Etapa 1 (D20): puede haber tres criterios distintos para «hay propuesta» (si la propuesta
  se borró, el costo mostrado puede no calzar); una vista previa que estaba en curso al aprobar puede terminar después de la
  final y pisar `/p/<codigo>/` (`/vista` la descarta; basta aprobar de nuevo); algunas notas se reemplazan entre sí sin perder
  los errores.

## Etapas siguientes

- **Etapa 2 — el cliente recibe:** envío por correo (PDF y enlace), «Enviar por mi WhatsApp», página pública `ver-plan.php` y
  agenda web de la llamada de seguimiento (`POST /wp-json/automatiza-tech/v1/plan-seguimiento`, siempre como reunión de
  seguimiento, sin tocar demos ni leads). Aquí el plan pasa a `enviado`.
- **Etapa 3 — requiere a Meta y el ok de Luis para tocar el bot:** plantilla de WhatsApp de Utilidad `plan_trabajo_listo`, enviada
  automáticamente (con los avisos de no entrega que ya existen), y Tech reconociendo el código del plan para agendar el
  seguimiento.
- Fuera de alcance: reuniones de seguimiento recurrentes, avance real contra el plan y facturación por hitos.
