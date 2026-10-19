# Retomar prospectos — operación y entrega local

AT-RETOMAR-001 · fase 1: 18-oct-2026, rama `codex/retomar-prospectos` (EN PROD desde esa fecha; correcciones de Eje en la rama `claude/retomar-prospectos-final`) · **fase 2: 19-oct-2026, rama `claude/retomar-prospectos-final`, construida y probada en local, no desplegada.**
Root vigente: `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje` (el root `codex-retomar` de la fase 1 es solo historia).

Implementación local para volver a contactar, de forma limitada, a prospectos con los que ya existe una conversación comercial. Prepara una presentación breve, correo con alternativa de texto y, cuando corresponde, WhatsApp con plantilla. Permite ver la propuesta, agendar y darse de baja. No incorpora respuestas automáticas ni interpreta respuestas entrantes.

**Cómo se usa desde la fase 2:** el sistema detecta a los prospectos con propuesta enviada y sin respuesta, les prepara el recordatorio y, una vez por semana, le manda a Luis un correo para que apruebe, pida cambios u omita cada uno. **Nada sale al prospecto sin esa aprobación.** Ver «Fase 2: correo semanal con aprobación».

## Estado y responsables

**Estado actual (19-oct-2026).** Fase 1: EN PROD (WordPress, renderer y flujos de render y de reloj; ver las notas del 18-oct más abajo). Fase 2: construida y probada en local; **sin desplegar y sin autorización de PROD**; resultados y lista de subida en «Fase 2». Lo que sigue en esta sección es el registro de la fase 1 al 18-oct, conservado como historia.

Implementación Tasks 0–8 preparada y revisiones A internas PASS. La carrera P2 entre renders A/B quedó corregida en `1d2ac6b32059a7688055af9c10b0f5f666cfb886` y cerrada por revisión independiente. Evidencia y commits por tarea: [registro local](../superpowers/AT-RETOMAR-001-evidencia.md). La revisión B corresponde a Eje (Claude) y está **PENDIENTE**; las revisiones internas de Codex no la sustituyen.

Verificación local: Retomar PHP **282 controles validados** (datos 28, envío 42, esquema 17, panel 56, pública 32, puras 74, render 33). La suite completa inicial dio 275/275; después de P2 se repitieron los dos archivos afectados con revisión independiente, 89/89 PASS. Suite renderer: 22 archivos, exit 0, sin fallos. Smoke HTTP público **8/8 PASS**, sin entrega real: 404, vista, noindex, no-store, confirmación GET sin baja, POST de baja y One-Click idempotente; fixture eliminado y servidor propio cerrado. Evidencia: `C:/Users/luis_/.codex/visualizations/2026/10/18/01a14fdd-54a0-7662-bf9a-95ef90f51d65/retomar-http-smoke.json`. Panel en navegador a 1280 y 390 px: PASS, sin desborde horizontal, con JSON/capturas en la evidencia del responsable. Estos resultados no acreditan PROD.

Aseo: 17 archivos propios de pruebas registrados y retirados individualmente a cuarentena recuperable; scratch sin archivos, directorios vacíos conservados. Checkpoint `task-close`: `review_required`, `complete:false` por baseline inicial incompleta, con cero tareas/candidatos/no registrados pendientes. Respaldo Aseo consultado; no se declara limpio todo el entorno.

Graphify actualizado localmente, exit 0: 16.086 nodos, 28.801 relaciones y 1.451 comunidades. Conservó 32 nodos de tres archivos fuera del corpus y avisó etiquetas de comunidades pendientes de refresco; no se ejecutó etiquetado con LLM ni se purgó el respaldo curado. Esas advertencias no son resultados de QA del módulo.

Regresiones ejecutadas: Plan **28/36 archivos PASS y 8 con fallos**; Entregables **8/11 archivos PASS y 3 con fallos**. No se declara regresión completa PASS. El padre reprodujo los mismos conteos de los 11 archivos fallidos con el tema base `9648a0459257fbb457a603bcd8895dff85d5527a` cargado en memoria y `at_rt_enviar` ausente en cada proceso: fallos históricos de esa base, incluidos tres ya conocidos y ocho adicionales (cinco Plan y tres Entregables). Limitación: helpers de pruebas de los hijos usaban la junction actual; la comprobación de baseline la hizo el padre. Los nombres y conteos por archivo se conservan en el registro local enlazado.

Luis autoriza el despliegue y el piloto; Eje revisa y coordina la configuración del destino. Importación y ejecución real de n8n, SMTP, aprobación Meta, entrega real, PROD y piloto: **NOT_RUN**. Esta guía no acredita ninguno de esos pasos ni un costo o una entrega garantizados.

## Fase 2: correo semanal con aprobación

**Estado (19-oct-2026): construida y probada en local; no desplegada.** Rama `claude/retomar-prospectos-final`, worktree `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje`. En PROD sigue corriendo la fase 1: ningún archivo de la fase 2 está subido, la constante `AT_N8N_RETOMAR_REDACTAR` no existe en `wp-config.php` y los flujos de n8n tienen su versión de la fase 1. Diseño y decisiones de Luis: `Docs/superpowers/specs/2026-10-18-retomar-aprobacion-semanal-design.md`; plan de construcción: `Docs/superpowers/plans/2026-10-18-retomar-aprobacion-semanal.md`.

En la fase 1 Luis llenaba cada prospecto a mano y, al activarlo, los recordatorios salían solos. La fase 2 lo da vuelta: **el sistema detecta y prepara, y nada sale al prospecto sin que Luis lo apruebe**.

### Cómo funciona la semana (en simple)

1. **Detecta.** Con el reloj activo, cada hora el sistema revisa las propuestas. Una propuesta entra a Retomar cuando se cumplen todas estas condiciones: está `sent` (enviada) o `evaluando`; se envió hace 14 días o más; es la más reciente de ese correo; el correo no pertenece a una ficha de **cliente** del CRM; no está en la lista de bajas; y no existe ya un seguimiento de Retomar con ese correo, en cualquier estado. La fecha de envío es la del último envío anotado en el historial de la propuesta (el envío desde el panel o una nota «propuesta enviada»); si no hay ninguno, la de creación de la propuesta. Las aceptadas, rechazadas, archivadas, pendientes y los borradores no entran solas: para que entren, se desarchivan o se agregan a mano. Cada persona tiene un solo seguimiento. Un prospecto detectado entra con la casilla «WhatsApp autorizado» **apagada**: si esa persona ya conversa con Luis por WhatsApp, hay que marcarla en el panel.
2. **Prepara.** Apenas entra, el sistema le pide a la IA (flujo n8n «Retomar · Redactar», `gpt-4o`) cuatro textos: «Lo que conversamos», «Lo que proponemos», «Para partir» y una recomendación de ajuste (cómo partir por etapas o adaptarse al presupuesto). La IA lee la transcripción de la reunión, un resumen de la propuesta, los títulos del historial del CRM y las notas que escriba Luis, **siempre sin montos**: WordPress le quita las líneas con precios antes de entregarle el contexto. La IA también escribe los encargos de las fotos. Con eso se arma la presentación en el renderer, con fotos hechas por Higgsfield (ver «Fotos y costo»). Mientras no termina, el prospecto figura «En preparación».
3. **Avisa a Luis.** Una vez por semana, por defecto **los lunes a las 09:00 (hora de Chile)**, llega **un correo** con una tarjeta por prospecto. El día, la hora y el correo de destino se configuran en el panel (destino vacío = el correo de avisos del equipo). El reloj corre al minuto 5 de cada hora, así que «09:00» sale hacia las 09:05; si ese envío se pierde, se recupera en la pasada siguiente (por ejemplo, el martes). Una semana, un solo correo, aunque el reloj corra varias veces; si el correo falla, se reintenta en la pasada siguiente; si no hay nada que revisar ni salidas que contar, no se envía.
   Cada tarjeta trae nombre y negocio, días desde la propuesta, «Recordatorio n de 4», canales, los cuatro textos, una miniatura (un bloque con el nombre del negocio, no una imagen), «Ver la presentación completa», «Ver el correo tal como le llega» y los tres botones. Al final, «Salieron de Retomar»: quienes cerraron su ciclo desde el correo anterior (completó sus recordatorios, agendó, ya es cliente, se dio de baja o rechazó). Las tarjetas «En preparación», «Necesita revisión» y «Necesita propuesta nueva» no llevan botones. Orden: primero lo que hay que decidir.
4. **Luis decide** con los botones (siguiente sección).
5. **Envía.** Lo aprobado sale lunes a viernes entre las 10:00 y las 13:00 (hora de Santiago); como el reloj pasa al minuto 5, las pasadas que caen en esa ventana son las de las 10:05, 11:05 y 12:05. Sale el **correo** (con baja de un clic) y, solo si el interruptor `at_rt_wa_automatico` está encendido (Meta aprobó la plantilla) y la persona tiene «WhatsApp autorizado» y teléfono, también el **WhatsApp** con la plantilla `retomar_propuesta`. Con el interruptor apagado, la página de confirmación ofrece «💬 Compartir a mano». Siguen de la fase 1 el tope de **4 recordatorios**, al menos **7 días** entre uno y otro y los días hábiles. Justo antes de enviar se vuelven a revisar las paradas (baja, agendó, cliente, propuesta aceptada o rechazada, tope) y que lo aprobado sea exactamente lo que sale: una aprobación de hace días no le escribe a quien se dio de baja o agendó entretanto.

### Qué hace cada botón

Gmail, Outlook y los antivirus abren solos los enlaces de un correo (GET y HEAD). Por eso **ningún enlace del correo ejecuta nada**: cada uno abre una página (`retomar-aprobar.php`) que dice exactamente qué se va a hacer y trae un botón; solo ese botón, enviado por POST con un nonce de WordPress, ejecuta. Cada enlace va firmado (HMAC con la sal `auth` del sitio), sirve **una sola vez** (aprobar, pedir cambios y omitir comparten un único uso), **vence a los 7 días** y está atado a la versión de la presentación: si el contenido cambió después del correo, la página dice «Este recordatorio cambió» y no hace nada. Un doble clic, o aprobar desde dos aparatos, produce un solo envío: el segundo recibe «Este enlace ya se usó» (y, si ya estaba aprobado, cuándo sale). Las páginas responden `noindex`, sin caché y sin referrer.

- **✅ Aprobar y enviar.** La página pregunta «¿Enviar el recordatorio n de 4 a…?» y dice cuándo sale. Al confirmar, el envío queda programado para hoy (si es día hábil y todavía falta para las 13:00) o para el siguiente día hábil, y lo envía el reloj dentro de la ventana. Si el WhatsApp automático está apagado y la persona tiene teléfono y «WhatsApp autorizado», la página ofrece «💬 Compartir a mano».
- **✏️ Pedir cambios.** La página trae un cuadro «¿Qué cambiamos?» (de 1 a 1000 caracteres). El pedido se suma a las notas del seguimiento, la IA rehace los textos y los encargos de foto, se pide una presentación nueva y, cuando queda lista, llega a Luis un **aviso corto** («Recordatorio corregido») con la tarjeta nueva y los mismos tres botones, sin esperar a la semana siguiente. Los botones del correo anterior dejan de servir. Si la redacción con IA no está configurada, el pedido queda en las notas y el recordatorio pasa a «Necesita revisión».
- **⏭️ Esta semana no.** No se envía nada; queda anotado en el historial del CRM («🔁 Esta semana no») y el prospecto vuelve a aparecer en el correo de la semana siguiente, con enlaces nuevos.
- **👀 Ver el correo tal como le llega.** Solo lectura: muestra el correo al prospecto dentro de la página. No gasta el enlace ni envía nada.

Un prospecto sin decisión reaparece en el correo siguiente con botones nuevos; los viejos mueren («cambió»). Para sacar a alguien para siempre: «Pausar» o «Dar de baja» en el panel.

### Estados y la columna «Revisión»

El panel muestra dos columnas: **Estado** (en qué vida está el seguimiento) y **Revisión** (qué falta para que Luis lo apruebe o cuándo sale).

| Estado | Significa |
| --- | --- |
| `borrador` | Guardado pero sin poder recibir recordatorios todavía (sin correo válido o con una parada). |
| `activo` | Entra al resumen semanal y puede recibir lo que Luis apruebe. |
| `espera_propuesta` | Su propuesta es antigua (no usa la plantilla AT): espera una propuesta nueva. La detección lo crea así sola cuando la propuesta no es `v3`. |
| `pausado`, `respondio` | Detenido a mano; solo «▶️ Reanudar» lo devuelve a `activo`. |
| `agendo`, `cliente`, `rechazado`, `baja`, `agotado` | Cerrado. Salen una vez en «Salieron de Retomar». `agotado` = llegó a 4 recordatorios. |

| Revisión | Qué significa | Qué hace Luis |
| --- | --- | --- |
| Redactando | La IA está escribiendo (si pasan 6 horas, el reloj repite el pedido, como máximo una vez cada 24 h). | Esperar; si no avanza, «✨ Volver a redactar» o escribir los textos a mano. |
| Preparando la presentación | Textos listos; el renderer arma la presentación y las fotos. | Esperar y recargar. |
| Lista para aprobar | Textos y presentación listos: sale con botones en el próximo correo semanal. | Decidir desde el correo. |
| Omitido esta semana | Luis eligió «Esta semana no» en la semana en curso. | Nada. |
| Aprobado, sale el AAAA-MM-DD | Aprobado: el reloj lo envía ese día hábil, en la ventana. | Nada. |
| Aprobado, esperando la presentación | Aprobó, pero el contenido cambió y se está rehaciendo la presentación. | Esperar el aviso «Recordatorio corregido». |
| Necesita revisión | La IA devolvió montos en dos intentos, su respuesta no sirvió, la presentación falló o no hay flujo de redacción. | Corregir los textos en el panel, o pedir otra redacción. |
| Necesita propuesta nueva | `espera_propuesta`. | Ver la sección siguiente. |
| — | Nada pendiente (el próximo envío aún no llega, o está detenido). | Nada. |

### Propuestas antiguas (hechas fuera de la plantilla AT)

Se reconocen porque su `flujo` no es `v3` (las hechas con la plantilla AT son `v3`; las de Gamma no). La propuesta antigua **no se reenvía ni se enlaza** (traería montos viejos). Su seguimiento se crea igual, queda en `espera_propuesta` y aparece en el correo semanal como «Necesita propuesta nueva», sin botón de aprobar y con el enlace al panel. Para destrabarlo:

1. Hacer la propuesta nueva por el flujo de propuestas v3 (`Docs/METODO_AT/PROPUESTAS-FLUJO-V3.md`): con lo hablado en las reuniones, **sin montos y con plazos**, diciendo que lo económico se ajusta y mencionando la demo ya publicada. Es un trabajo de contenido aparte, no parte de este software. Debe quedar aprobada (`lista`).
2. En el panel Retomar, abrir el seguimiento, elegir esa propuesta en «Propuesta vinculada» (debe ser del mismo correo) y **Guardar**. El seguimiento sale de la espera y queda `activo` (se reinicia `activado_at`, así que la reunión que dio origen a la propuesta nueva no lo detiene), la propuesta antigua se **archiva sola** con una nota interna «Reemplazada por la propuesta N», y se pide la redacción.
3. La propuesta nueva llega **con el recordatorio**, no por el envío normal de propuestas. Cuando sale el recordatorio aprobado que la enlaza, WordPress la pasa de `lista` a `sent` sin mandar su propio correo (nota interna en su historial). Motivo: desde `lista` el cliente no puede responderla en `ver-presentacion.php`; desde `sent`, sí.

### Agregar a mano, textos propios y botones del panel

- **Agregar a mano:** desde CRM › Retomar prospectos › Nuevo seguimiento, o con el botón **«🔁 Retomar»** de la ficha de un prospecto (solo prospectos, solo administradores), que abre el panel con sus datos. Sirve para quien tiene la propuesta fuera del sistema (en su dominio o en una landing: se pone su URL en «URL externa de la propuesta», que se respeta siempre) o para quien aún no cumple los 14 días. Un seguimiento agregado a mano entra al **siguiente** correo semanal.
- **Textos propios:** los tres textos del formulario son opcionales. Con los tres vacíos, **Guardar** pide la redacción a la IA y las «Notas para la IA» le sirven de instrucciones (se conservan los últimos 4000 caracteres). Con los tres llenos —también si son los que escribió la IA y solo se guarda el formulario— se toman como **texto de Luis**: quedan marcados como manuales, la IA ya no los pisa (un callback tardío recibe 409 y el reloj no reintenta lo que está «lista») y solo se pide la presentación. Se pueden editar directamente cuando una redacción quedó en «Necesita revisión». Todo texto, venga de la IA o de Luis, necesita igual la aprobación semanal. La recomendación de ajuste solo la escribe la IA y se conserva al editar a mano.
- **✨ Volver a redactar** (solo `activo`): pide una redacción nueva a la IA con las notas; **pisa** los textos vigentes, incluidos los manuales.
- **▶️ Reanudar** (solo `pausado` o `respondio`): lo deja `activo`, le reinicia la fecha de activación, **anula la aprobación que tuviera** y lo manda al siguiente correo semanal. No envía nada por sí mismo y no sirve si hay una parada (baja, cliente, agendó, aceptada, rechazada, tope).
- **📬 Enviarme el resumen ahora:** arma y manda el correo de la semana aunque ya haya salido otro, y **cuenta como el de esa semana**: el automático de ese lunes no se repite.
- **Configuración del correo semanal:** día, hora (de Santiago) y correo de destino, en el recuadro «Resumen semanal» del panel. Con un dato inválido no cambia nada.
- Siguen de la fase 1: Vista previa, Enviar prueba al equipo, Pausar, Respondió, Dar de baja, Revertir baja y «💬 Compartir a mano». Desapareció **Activar**.

### Fotos y costo

La IA escribe un encargo por lámina: `portada`, `cierre` y `demo` (la de la demo solo si el seguimiento no tiene una imagen propia). Son escenas del rubro con personas y **sin texto, letreros ni etiquetas** (la lección del 28-sep: Soul 2 imprime etiquetas en botellas y letreros en foco). Las genera el renderer con su integración de Higgsfield (Soul 2, `renderer/src/higgsfield.js`), que antes el camino `retomar` tenía prohibido.

- **Costo:** el propio código anota US$0,0032 por imagen como precio de lista de `open.higgsfield.ai/pricing` al 20-sep-2026. Con tres fotos, **≈ US$0,0096 (≈ US$0,01) por presentación nueva**. Es precio de lista leído del código: no se cotejó con la factura de Higgsfield. El costo de OpenAI de cada redacción no se midió.
- **No se paga dos veces la misma foto:** WordPress guarda la URL de cada foto junto al hash (`sha256`) de su encargo; si un «Pedir cambios» no cambia el encargo de una lámina, se la pasa al renderer ya hecha. Solo se paga de nuevo la foto cuyo encargo cambió.
- **Si Higgsfield falla o tarda:** la lámina usa las fotos de la propuesta y, si no hay, el degradado de la marca; nunca se bloquea el recordatorio por una foto. El flujo de render espera hasta 300 s al renderer.

### Notas de producto para Luis

- Un seguimiento en `baja` **no se puede reanudar, ni siquiera revirtiendo la baja**. «Revertir baja» solo levanta la supresión; el seguimiento sigue detenido y «▶️ Reanudar» no aplica a `baja`. Para volver a escribirle a esa persona hay que revertir la baja y crear un seguimiento nuevo a mano (la detección no lo hace: ya existe uno con ese correo).
- **Las aprobaciones mueren** cuando el seguimiento se detiene (baja, agendó, cliente…), cuando se reanuda, cuando el correo al prospecto falla (queda en pausa) y cuando cambia su contenido (textos, enlaces, demos o encargos de foto; también si cambia la propuesta vinculada). En ese caso vuelve a «Lista para aprobar» con enlaces nuevos, y si hay que rehacer la presentación, Luis recibe «Recordatorio corregido». Pausar a mano no anula la aprobación, pero un seguimiento pausado tampoco envía.
- La detección evalúa solo la propuesta **más reciente** de cada correo: si esa está aceptada, en borrador o es reciente, una más vieja que siga `sent` no dispara nada.
- Los prospectos detectados entran con «WhatsApp autorizado» apagado y canal «correo y WhatsApp»; el WhatsApp además exige el interruptor encendido.
- Detalle a vigilar: la página de aprobación dice «sale el [día] entre las 10:00 y las 13:00» para lo aprobado hoy antes de las 13:00, pero la última pasada del reloj dentro de la ventana es la de las 12:05. Lo aprobado entre las 12:05 y las 13:00 sale, en la práctica, a las 10:05 del siguiente día hábil.
- Al activar el reloj, la primera pasada detecta **todas** las propuestas que cumplan la regla y pide hasta 3 redacciones por pasada (el resto, en la hora siguiente). La medición del 18-oct dio 2 prospectos, los dos del piloto; antes de activar conviene volver a medir.

### Parte técnica

**Archivos.** Todo entra por `wp-content/themes/automatiza-tech/inc/retomar/cargar.php` (que carga `admin-proposals.php`); `functions.php` no se toca. Ningún `foreach` ni variable suelta a nivel de archivo (la lección de la fase 1: pisaba la global `$accion`).

| Archivo | Qué hace |
| --- | --- |
| `esquema.php` | Esquema v2: columnas `notas`, `redaccion`, `aprobacion`, `fotos`; opción `at_rt_schema` = `2`. `at_rt_migrar_esquema()` es idempotente (`dbDelta`). |
| `datos.php` | Campos nuevos, estado `espera_propuesta`, `at_rt_config()` (día, hora y destino del resumen). |
| `puras.php` | Reglas sin WordPress: montos, candidata, semana ISO, firma HMAC, programación, HTML del resumen y de las tarjetas, canales. |
| `detectar.php` | Detección de propuestas estancadas y alta automática del seguimiento (con candado MySQL). |
| `redaccion.php` | Pedido a n8n, contexto para la IA (sin montos) y callback con textos y encargos de foto. |
| `render.php` | Payload con encargos de foto y fotos reutilizadas; el callback de la presentación guarda `fotos` y dispara el aviso tras «Pedir cambios». |
| `aprobacion.php` y `retomar-aprobar.php` (raíz) | Emisión, validación y resolución de los enlaces; páginas de confirmación. |
| `resumen.php` | Correo semanal y aviso «Recordatorio corregido». |
| `envio.php` | Solo envía lo aprobado, consume la aprobación, marca `sent` la propuesta v3 que estaba `lista` y trae el reloj por hora. |
| `panel.php` | Panel sin «Activar», configuración, «Enviarme el resumen ahora», botón de la ficha. |
| `wp-content/mu-plugins/crm-ai-completo.php` | Una línea: llama a `at_rt_boton_ficha` en la ficha del prospecto. |
| `renderer/src/schema.js`, `server.js`, `template-retomar.js` | Encargos de foto, `images`, `recomendacion`, y `images.urls` en la respuesta. |

**Datos** (JSON en columnas `longtext`): `redaccion` `{estado: pendiente|lista|error|revision, generacion, intentos, origen: ia|manual, nota, image_briefs, pedido, pedida_at, reintento_at}`; `aprobacion` `{estado: por_aprobar|aprobado|cambios|omitido|enviado, n, nonce, huella, emitido_at, vence, usada, programado?, resuelto_at?}`; `fotos` `{portada|cierre|demo: {url, hash}}`; `textos` (los tres de la fase 1 más `recomendacion`).

**Opciones y constantes.** Opciones: `at_rt_resumen_dia` (`1` = lunes, ISO-8601), `at_rt_resumen_hora` (`09:00`), `at_rt_resumen_destino` (vacío = `at_cc_correo_avisos()`), `at_rt_resumen_semana` (última semana enviada, p. ej. `2026-W43`), `at_rt_resumen_desde` (cuándo salió el último), `at_rt_wa_automatico` (fase 1). Constantes: `AT_N8N_RETOMAR_REDACTAR` y `AT_N8N_RETOMAR_RENDER` (URL de webhook, no secretas) y `AT_REST_SECRET` (existente; no se muestra ni se copia).

**Rutas REST** (`/wp-json/automatiza-tech/v1/`, todas autenticadas): `retomar/tick` y `retomar/whatsapp-resultado` (cabecera `X-AT-Secret`), `retomar/{id}/contexto` (GET) y `retomar/{id}/redaccion` (POST) de la fase 2, y `retomar/{id}/vista` (callback del render, de la fase 1).

**El reloj (`at_rt_tick`)**, una vez por hora, en este orden y cada paso aunque falle el anterior: (1) si es día hábil y está dentro de la ventana, entrega lo aprobado (primero, para que la lista de WhatsApp quede armada antes de lo lento); (2) detección; (3) reintento de las redacciones atascadas (vacías, en error o pendientes hace más de 6 h; una vez cada 24 h y solo si siguen activas, ya tocan y no tienen parada); (4) correo semanal si toca. Un candado de MySQL evita dos detecciones o dos resúmenes simultáneos, y el resumen lee su semana desde la base, no desde la memoria. Límites: 3 pedidos de redacción por pasada, 5 s de espera a n8n desde el reloj (15 s desde el panel) y 120 s en el nodo `Tick`.

**Seguridad y reglas que no se negocian.** (1) Ningún correo —al prospecto ni a Luis— lleva enlaces ni imágenes de `easypanel` ni del host del renderer; los enlaces del resumen apuntan a `automatizatech.cl` y las tarjetas no llevan `<img>`. (2) Ningún texto lleva montos (`$`, `CLP`, `USD`, `UF` o cifras con separador de miles): la IA lo tiene prohibido y WordPress lo valida; con montos, pide la redacción otra vez (una vez) y, si insiste, pasa a «Necesita revisión». (3) Textos: 1 a 600 caracteres y hasta 6 líneas (`quedamos`, `proponemos`, `partir`); recomendación hasta 300 y 3 líneas. (4) Los datos del prospecto salen escapados y el correo semanal solo va al destino configurado. (5) `retomar-aprobar.php` es una entrada sin login protegida por el enlace firmado, de un solo uso y de 7 días; SEO y búsqueda con IA: **NOT_APPLICABLE** (superficie privada).

### Verificación local de la fase 2 (19-oct-2026)

Contra el sitio local de pruebas (nunca PROD) y con las dos tablas de Retomar convertidas a **InnoDB**, como en PROD: localmente se habían creado como MyISAM, donde `FOR UPDATE` no hace nada y las pruebas de concurrencia no probaban lo que decían.

- `tests/retomar/*-wp-test.php`: **11 de 11 archivos «TODO OK»** (aprobación 94 controles, datos 61, detectar 44, envío 107, esquema 27, globales 5, panel 151, pública 32, redacción 117, render 112, resumen 88) y `tests/retomar/puras-test.php`: **205 pruebas, 0 fallas**.
- Cubren los cinco puntos de revisión: GET y HEAD de los tres enlaces no cambian nada; doble clic o dos aparatos, un solo envío; baja o reunión entre la aprobación y el envío, no sale nada; el resumen no se repite en la semana y se reintenta si falla `wp_mail`; bordes de semana y de año (`2027-01-01` es `2026-W53`).
- Renderer: `node --test test/*.test.js`, **200 de 200** (exit 0). Flujo de redacción: `node N8N/retomar/probar_retomar_redactar.js`, exit 0, «TODO OK».
- Regresión de otros módulos: `tests/entregables/` **11 de 11 archivos**; `tests/plan/` **33 de 36 archivos**, con los mismos tres que ya fallaban en `main` y que esta rama no toca (`datos-contexto-wp-test.php`, `panel-render-wp-test.php` y `firma-contrato-wp-test.php`; ver «Fallas anteriores en `main`» en `PLAN-DE-TRABAJO.md`).
- **NOT_RUN:** importación y ejecución real de los flujos en n8n, una redacción real con la IA, fotos reales de Higgsfield, un correo semanal real en una bandeja, la aprobación de Meta y todo PROD. Estos resultados no acreditan nada de eso.

### Lista de subida de la fase 2

Son **14 archivos** (los que cambió o creó esta rama desde `9daf369` bajo `wp-content/` y la raíz, calculados con `git diff --name-only 9daf369..HEAD -- wp-content/ retomar-aprobar.php`). `publica.php` y `retomar.php` no cambiaron. Todo con autorización explícita de Luis; esta lista no autoriza nada.

Antes de subir: respaldar los 14 destinos y las dos tablas (`at_retomar`, `at_retomar_bajas`) en `~/respaldos/`; cotejar el md5 de cada destino que ya existe con el del commit `9daf369` y, si alguno difiere, parar y revisar quién lo tocó (los cuatro archivos nuevos no tienen destino previo). Después de subir: `php -l` de cada archivo, md5 cotejado contra el repositorio, vaciar la caché y verificar desde afuera. **El `crm-ai-completo.php` es un archivo grande y compartido, y su copia de PROD pudo cambiar desde `9daf369`:** si no es idéntica al de `9daf369`, no subir el del repositorio; agregar a mano su única línea nueva (la llamada a `at_rt_boton_ficha`).

| Orden | Ruta local absoluta | Destino relativo en PROD | Clasificación |
| --- | --- | --- | --- |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/esquema.php` | `wp-content/themes/automatiza-tech/inc/retomar/esquema.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/puras.php` | `wp-content/themes/automatiza-tech/inc/retomar/puras.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/datos.php` | `wp-content/themes/automatiza-tech/inc/retomar/datos.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/render.php` | `wp-content/themes/automatiza-tech/inc/retomar/render.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/redaccion.php` (nuevo) | `wp-content/themes/automatiza-tech/inc/retomar/redaccion.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/detectar.php` (nuevo) | `wp-content/themes/automatiza-tech/inc/retomar/detectar.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/aprobacion.php` (nuevo) | `wp-content/themes/automatiza-tech/inc/retomar/aprobacion.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/resumen.php` (nuevo) | `wp-content/themes/automatiza-tech/inc/retomar/resumen.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/envio.php` | `wp-content/themes/automatiza-tech/inc/retomar/envio.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/panel.php` | `wp-content/themes/automatiza-tech/inc/retomar/panel.php` | OBLIGATORIO |
| 2 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/assets/css/retomar.css` | `wp-content/themes/automatiza-tech/assets/css/retomar.css` | OBLIGATORIO |
| 2 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/retomar-aprobar.php` (nuevo) | `retomar-aprobar.php` (raíz del sitio, junto a `retomar.php`) | OBLIGATORIO |
| 2 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/mu-plugins/crm-ai-completo.php` | `wp-content/mu-plugins/crm-ai-completo.php` | OPCIONAL (sin él falta el botón «🔁 Retomar» de la ficha; lo demás funciona) |
| 3, último | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/cargar.php` | `wp-content/themes/automatiza-tech/inc/retomar/cargar.php` | OBLIGATORIO |

Los módulos van antes de `cargar.php`, que es el que habilita los cuatro nuevos; dentro de un mismo grupo el orden no cambia nada. **Ojo con la ventana intermedia:** el `cargar.php` viejo ya carga los `esquema.php`, `puras.php`, `datos.php`, `render.php`, `envio.php` y `panel.php` nuevos, pero no los cuatro módulos nuevos, así que entre la primera subida y `cargar.php` el panel Retomar de wp-admin puede fallar (solo ese panel, y solo para quien lo abra; la página pública de la baja no depende del esquema nuevo). Por eso se sube todo seguido, fuera de horario y con el reloj inactivo. Hasta que suba `cargar.php`, `retomar-aprobar.php` responde 503 «Aprobación no disponible por ahora» (a propósito). **La migración del esquema va entre el grupo 2 y `cargar.php`.**

**No subir:** `tests/`, `Docs/`, `N8N/`, `renderer/` (va por ZIP), `.superpowers/`, `graphify-out/`, `.git/`, `node_modules/`, `wp-config.php` ni configuraciones con credenciales, `.env`, scratch, respaldos, capturas ni evidencia.

### Después de subir: esquema, constante, renderer y n8n

1. **Esquema** (desde la raíz de WordPress de PROD, con respaldo confirmado; solo agrega cuatro columnas y no toca filas): `wp eval 'at_rt_migrar_esquema(); echo get_option("at_rt_schema");'` → debe imprimir `2`. Después sube `cargar.php`.
2. **Constante** `AT_N8N_RETOMAR_REDACTAR` en `wp-config.php`, con WP-CLI como en la fase 1 (una línea, antes del `require_once` de `wp-settings.php`; respaldo previo del archivo). Es la URL del webhook de «Retomar · Redactar», no un secreto: `wp config set AT_N8N_RETOMAR_REDACTAR '<URL del webhook>' --anchor="require_once ABSPATH . 'wp-settings.php';" --placement=before`. Comprobar sin imprimirla: `wp eval 'echo defined("AT_N8N_RETOMAR_REDACTAR") ? "definida" : "falta";'`. Sin la constante, WordPress responde `sin_flujo`, no pide redacciones y los textos se escriben a mano.
3. **Renderer** (cambió en esta rama): ZIP con `git archive` desde el commit final de la rama, que sube Luis a Easypanel. Mismo comando de la fase 1: `git archive --format=zip --output="C:/Users/luis_/respaldos/deploy-scripts/2026-10-19-retomar-fase2/propuesta-renderer-RETOMAR-F2-<sha>.zip" <sha>:renderer Dockerfile .dockerignore package.json src`. Anotar el `<sha>` y el hash del ZIP. Rollback: `propuesta-renderer-RETOMAR-9daf369.zip` (el que corre hoy) en `C:/Users/luis_/respaldos/deploy-scripts/2026-10-18-retomar/`. Verificar `/health` 200, las cabeceras `noindex` de `/p/*-retomar/` y un `/render` con datos ficticios.
4. **n8n** (credenciales por nombre en `N8N/retomar/README.md`; nunca valores). Antes de importar uno, desactivar y archivar el anterior del mismo nombre. En este orden:
   - **«Retomar · Redactar»** (nuevo, `N8N/retomar/retomar-redactar.json`): importar, vincular credenciales y **activar**; después agregar la constante.
   - **«Retomar · Render»** (actualizar con `retomar-render.json`): espera de 300 s y callback con `fotos`. Queda **activo**.
   - **«Retomar · Reloj y WhatsApp»** (actualizar con `retomar-reloj.json`): cron cada hora al minuto 5 (`0 5 * * * *`) y `Tick` de 120 s. Queda **INACTIVO hasta el OK de Luis**: activarlo es lo que pone en marcha la detección, el correo semanal y los envíos aprobados.
5. Antes de activar el reloj: definir con Luis el día, la hora y el correo de destino del resumen, y volver a medir qué propuestas entrarían.
6. Después de activarlo: `graphify update .` se corre en `main` tras el merge, no en esta rama (`graphify-out` está versionado e inundaría el PR).

### Piloto (todo NOT_RUN)

La lista privada de los dos prospectos del piloto vive fuera del repositorio, en `C:/Users/luis_/Documents/Codex/Shared-Tools/Retomar-Prospectos-Piloto.md`; este repositorio es público y no lleva sus nombres, correos, teléfonos ni códigos.

1. Con el OK de Luis, activar el reloj. La primera pasada detecta y pide la redacción; la presentación se arma con las fotos.
2. **El primer prospecto sale sin esperar al segundo:** revisar su tarjeta en el correo semanal (o con «📬 Enviarme el resumen ahora»), aprobar o pedir cambios, y confirmar que sale en la ventana siguiente. Marcar «WhatsApp autorizado» si ya conversa con Luis por WhatsApp; el WhatsApp automático sigue dependiendo de que Meta apruebe la plantilla (en revisión el 18-oct; no se verificó después).
3. **El segundo necesita primero su propuesta nueva v3** (ver «Propuestas antiguas»). Mientras tanto queda «Necesita propuesta nueva» y no sale nada.
4. Registrar versión desplegada, resultados y aprobación del piloto. Cualquier paso sin acceso queda **NOT_RUN**.

### QA posterior a la subida (todavía no ejecutada)

Con datos ficticios y autorización para cada envío real: GET, HEAD y POST de los cuatro enlaces desde un correo recibido (el GET y el HEAD no cambian nada; el POST sí, una sola vez); enlace vencido, alterado y reutilizado; el correo semanal en una bandeja real (HTML, enlaces, sin imágenes rotas); «Pedir cambios» de punta a punta con el aviso «Recordatorio corregido»; una foto reutilizada frente a una foto con encargo cambiado; la ventana 10:00–13:00 con el reloj real; y que una baja entre la aprobación y el envío detenga el envío.

### Rollback de la fase 2

Desactivar «Retomar · Reloj y WhatsApp» detiene de una vez la detección, el correo semanal y los envíos, sin tocar código. Para volver al código de la fase 1: restaurar los 14 archivos respaldados y el ZIP anterior del renderer, y correr `wp eval 'at_rt_migrar_esquema(); echo get_option("at_rt_schema");'` (debe imprimir `1`: con la opción en `2` el código viejo no da el esquema por listo). El esquema 2 solo **agrega** columnas; no se borran (el código viejo las ignora) y **nunca se hace DROP ni se borran bajas**. Sin verificar: si el MySQL de PROD usa modo estricto, el código de la fase 1 podría no poder crear seguimientos nuevos porque las cuatro columnas nuevas no tienen valor por defecto; los seguimientos existentes no se ven afectados.

## Alta a mano y revisión por Luis (fase 1, ajustada a la fase 2)

1. Abrir **CRM → Retomar prospectos** con un usuario administrador (o pulsar «🔁 Retomar» en la ficha del prospecto, que abre el panel con sus datos). Buscar y seleccionar una ficha de tipo prospecto o marcar su creación si aún no existe; no se convierten fichas de clientes. Correo de ficha y propuesta vinculada deben coincidir. Puede vincularse una propuesta existente o agregarse su URL pública HTTPS.
2. Completar nombre, negocio, correo y teléfono con código de país; elegir una imagen existente de la biblioteca, hasta dos demos públicas y, si se quiere, los tres textos: «En qué quedamos», «Qué proponemos» y «Por dónde podemos partir». **Los tres textos son opcionales:** vacíos, los redacta la IA con las «Notas para la IA»; llenos, se toman como texto de Luis (ver «Agregar a mano, textos propios y botones del panel»). El proyecto toma el nombre del negocio. No pegar enlaces internos ni datos ajenos al contacto.
3. Configurar máximo de 1 a 4 envíos e intervalo mínimo de 7 días (predeterminado). El máximo almacenado para el intervalo es 255 días. Se cuentan días de calendario y se difiere al siguiente lunes a viernes si corresponde; los feriados están fuera de alcance.
4. Mantener **Canales = Correo** hasta que la plantilla Marketing `retomar_propuesta`, idioma `es`, tenga aprobación de Meta. La casilla **WhatsApp autorizado** registra consentimiento real de esa persona (te escribió o conversó contigo por WhatsApp); no acredita aprobación de Meta. En una persona nueva sin ese consentimiento debe quedar desmarcada. Aunque exista consentimiento registrado, conservar el canal solo Correo hasta superar el gate Meta. Además, el WhatsApp automático solo sale con el interruptor `at_rt_wa_automatico` encendido (hoy apagado); mientras tanto se comparte a mano.
5. Pulsar **Guardar**. Con los tres textos llenos, el guardado invalida la versión anterior y solicita la presentación; con los tres vacíos, pide primero la redacción a la IA y la presentación se pide sola cuando esta termina. Si falta el flujo o falla la solicitud, la presentación no queda lista y el prospecto no entra al resumen semanal para aprobarse. **Vista previa** vuelve a pedir el render; recargar para ver el resultado y abrir la presentación cuando figure lista. Cada solicitud tiene una generación y un directorio propios; una respuesta anterior se rechaza con `409`, incluso después de guardar contenido idéntico.
6. Revisar texto, imagen, demos, enlaces, destinatario y datos del seguimiento. **Enviar prueba al equipo** usa el correo de avisos del equipo, no suma contactos realizados y no envía WhatsApp. Un resultado local de `wp_mail` no prueba recepción real en la bandeja.
7. Ya no existe el botón **Activar** (se quitó en la fase 2). Guardar deja el seguimiento `activo` si el prospecto puede recibir recordatorios (correo válido y ninguna condición de parada); aun así, **nada sale al prospecto sin la aprobación semanal de Luis**: el seguimiento entra al siguiente correo semanal, sin esperar los 14 días de la detección. Lo que pone en marcha el sistema completo es el flujo «Retomar · Reloj y WhatsApp» de n8n, y eso solo se activa con el OK de Luis.

Límites del formulario: nombre y negocio, 120 caracteres cada uno; correo, 190; teléfono, 50; cada uno de los tres textos, 1–600 caracteres y hasta 6 líneas (la recomendación de ajuste, que solo escribe la IA, hasta 300 y 3 líneas); notas para la IA, los últimos 4000 caracteres; dos demos como máximo; URLs de demo/propuesta/imagen, hasta 500 caracteres y HTTPS público. Los contenidos que exceden los límites se rechazan; no se recortan silenciosamente.

## Reloj, paradas y bajas

La ventana de envío es lunes a viernes, de 10:00 a antes de 13:00, zona `America/Santiago`. Desde la fase 2 el flujo consulta **cada hora, al minuto 5, todos los días** (`0 5 * * * *`; en la fase 1 era una sola consulta a las 10:15 de lunes a viernes, `0 15 10 * * 1-5`). En cada pasada WordPress decide qué toca: los envíos aprobados solo dentro de la ventana (las pasadas de las 10:05, 11:05 y 12:05), y en toda pasada la detección, el reintento de redacciones atascadas y el correo semanal. El horario se interpreta en Chile con esa zona, no con un desplazamiento UTC fijo. No hay calendario de feriados.

El correo es el canal base. Desde la fase 2 el WhatsApp acompaña a **cada** recordatorio aprobado (en la fase 1 era solo el primero y el último), solo con el interruptor `at_rt_wa_automatico` encendido, canal habilitado, consentimiento y teléfono. El reloj aplica claim y control diario para evitar doble correo; eso no autoriza reejecutar manualmente ejecuciones n8n fallidas, porque podrían repetir WhatsApp.

Se detiene por baja, propuesta rechazada o aceptada, conversión a cliente, reunión agendada o máximo de envíos alcanzado. **Pausar** detiene el siguiente contacto. **Respondió** se marca manualmente cuando el equipo recibe respuesta y también detiene el seguimiento; no hay detección ni contestación automática. Un fallo del correo pausa; no hay reintentos automáticos. Cuando un seguimiento se detiene, su aprobación vigente queda sin efecto. Un render pendiente difiere la fecha hábil sin sumar un contacto. Los fallos de WhatsApp, incluido `131049`, se registran sin reintento automático. Si falla el callback se concilia el registro con Eje, sin reenviar el mensaje.

El enlace de baja con token muestra confirmación mediante GET y **no da de baja al abrirlo**. El POST confirmado y el POST One-Click del correo aplican la baja de forma idempotente. La supresión es global dentro de Retomar por coincidencia de **correo O teléfono normalizados**; no depende solo de la ficha actual ni modifica otros productos. Luis también puede usar **Dar de baja** en el panel. **Revertir baja** exige motivo de 1–255 caracteres y conserva el seguimiento detenido: nunca lo reactiva, y un seguimiento en `baja` no se puede reanudar (el botón «▶️ Reanudar» solo sirve para pausados o con respuesta). Para volver a escribirle a esa persona se revierte la baja y se crea un seguimiento nuevo a mano, que vuelve a comprobar las paradas.

## Superficies y configuración de destino

Panel CRM privado: permiso `manage_options` y nonce para mutaciones. Presentación, agenda y baja: acceso mediante token opaco, sin indexación ni caché de contenido personalizado; no publicar enlaces con tokens en documentación ni buscadores. El token se trata como enlace de acceso y no se comparte fuera del destinatario/equipo autorizado. Endpoints de render/reloj/resultado: autenticados. Renderer, generadores y exports son internos. `retomar-aprobar.php` (fase 2) no pide login, pero exige el enlace firmado de un solo uso que solo se envía al correo de Luis, y responde `noindex`, sin caché y sin referrer. SEO y búsqueda IA: **NOT_APPLICABLE**, no son superficies comerciales para indexación.

Luis configura `AT_N8N_RETOMAR_RENDER` con la URL del webhook de render en el destino. Es una URL de configuración no secreta; su ausencia deja la presentación pendiente. La fase 2 suma `AT_N8N_RETOMAR_REDACTAR` (URL del webhook de «Retomar · Redactar», tampoco secreta): sin ella WordPress responde `sin_flujo` y los textos se escriben a mano. El flujo utiliza la credencial existente `AT REST Secret (header)` con cabecera `X-AT-Secret`; **no pegar `AT_REST_SECRET`, claves ni sus valores** en esta guía, exports, comandos, ZIP o repositorio. Eje vincula las referencias a credenciales existentes en n8n y valida el routing/version Graph y expresiones en ese entorno.

## Fase 1: entrega FTP, exactamente 13 archivos (EN PROD)

> La lista de la fase 2 (14 archivos) está en «Fase 2 → Lista de subida de la fase 2». Esta tabla es la de la fase 1, ya subida el 18-oct.

> **EN PROD desde el 2026-10-18 (WordPress):** los 13 archivos de `35bc894`, subidos por Eje por SSH con autorización de Luis: respaldo `~/respaldos/retomar-antes-20261018.tar.gz`, `php -l`, md5 cotejado, tablas `at_retomar` y `at_retomar_bajas` creadas (`at_rt_schema`=1), caché vaciada. Verificado desde afuera: sitio 200, panel 302, `retomar.php` con token inválido 404 + noindex, `retomar/tick` sin secreto 401, y la ficha del cliente piloto sigue dibujando plan y entregables. **Falta y no está autorizado aún:** ZIP del renderer (Luis), los 2 flujos n8n, la constante `AT_N8N_RETOMAR_RENDER` y la plantilla de Meta. Sin eso no sale ningún recordatorio.

> **18-oct, con autorización de Luis (n8n y Meta):** flujo «Retomar · Render» creado y **activo** (`/webhook/retomar-render`, Header Auth; sin clave responde 403); flujo «Retomar · Reloj y WhatsApp» creado e **inactivo** hasta que Luis active el piloto; constante `AT_N8N_RETOMAR_RENDER` agregada a `wp-config.php` de PROD con WP-CLI (una línea, antes del `require_once` de `wp-settings.php`; respaldo `~/respaldos/wp-config.antes-retomar-20261018.php`); plantilla `retomar_propuesta` enviada a Meta como MARKETING, estado PENDING. **Falta:** el ZIP del renderer en Easypanel (lo sube Luis), la aprobación de Meta y activar el reloj.

> **18-oct, tarde (EN PROD, `9daf369`):** «💬 Compartir a mano» en cada prospecto (copiar el mensaje o abrir el WhatsApp de Luis; queda en el historial como evento `manual` y no cuenta como recordatorio) e **interruptor del WhatsApp automático** `at_rt_wa_automatico` (apagado por defecto: el reloj manda solo correos). El panel muestra «⏸️ WhatsApp automático: en pausa (apagado)». Encenderlo cuando Meta apruebe la plantilla: `wp option update at_rt_wa_automatico 1`. Respaldo `~/respaldos/retomar-manual-antes-20261018.tar.gz`. ZIP del renderer para Easypanel: `C:/Users/luis_/respaldos/deploy-scripts/2026-10-18-retomar/propuesta-renderer-RETOMAR-9daf369.zip` (rollback: `propuesta-renderer-ROLLBACK-CELULAR-a7ab0a7.zip`, misma carpeta).

> **18-oct, noche (renderer EN PROD):** Luis subió el ZIP `propuesta-renderer-RETOMAR-9daf369.zip` a Easypanel. Verificado desde afuera: `/health` 200; las rutas `/p/*-retomar/` responden con `X-Robots-Tag: noindex, nofollow` y `Referrer-Policy: no-referrer` (la versión anterior no los ponía); y un `/render` de prueba con datos ficticios, pedido con la credencial de n8n desde un flujo temporal que se borró, devolvió 200, HTML de 6 láminas sin errores de consola y PDF. La lámina de prueba (`pruebaeje…-retomar`) quedó en el disco del renderer: no tiene datos de clientes y no se indexa.

> **18-oct, Eje:** los archivos salen de la rama `claude/retomar-prospectos-final` (worktree `.worktrees/retomar-eje`), que corrige `inc/retomar/panel.php` (la global `$accion`) y deja `inc/admin-proposals.php` con un solo cambio. El ZIP del renderer de Codex sigue valiendo: la corrección no toca `renderer/`.

Registro de la fase 1, ya subida el 18-oct. La regla sigue igual para cualquier subida: con autorización de Luis, respaldar los archivos de destino que se van a reemplazar y la BD de forma protegida. Subir los grupos en orden; dentro de un mismo grupo el orden no cambia la carga. Los módulos van antes del archivo que los habilita. La migración se ejecuta por separado después del grupo 4 y antes del grupo 5.

| Orden | Ruta local absoluta | Destino relativo en PROD | Clasificación |
| --- | --- | --- | --- |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/esquema.php` | `wp-content/themes/automatiza-tech/inc/retomar/esquema.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/puras.php` | `wp-content/themes/automatiza-tech/inc/retomar/puras.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/datos.php` | `wp-content/themes/automatiza-tech/inc/retomar/datos.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/render.php` | `wp-content/themes/automatiza-tech/inc/retomar/render.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/publica.php` | `wp-content/themes/automatiza-tech/inc/retomar/publica.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/envio.php` | `wp-content/themes/automatiza-tech/inc/retomar/envio.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/panel.php` | `wp-content/themes/automatiza-tech/inc/retomar/panel.php` | OBLIGATORIO |
| 2 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/assets/css/retomar.css` | `wp-content/themes/automatiza-tech/assets/css/retomar.css` | OBLIGATORIO |
| 2 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/mu-plugins/at-timeline-origen.php` | `wp-content/mu-plugins/at-timeline-origen.php` | OBLIGATORIO |
| 2 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php` | `wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php` | OBLIGATORIO |
| 3 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/retomar/cargar.php` | `wp-content/themes/automatiza-tech/inc/retomar/cargar.php` | OBLIGATORIO |
| 4 | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/wp-content/themes/automatiza-tech/inc/admin-proposals.php` | `wp-content/themes/automatiza-tech/inc/admin-proposals.php` | OBLIGATORIO |
| 5, último | `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje/retomar.php` | `retomar.php` | OBLIGATORIO |

Migración idempotente prevista desde el root WordPress de PROD, con WP-CLI y respaldo confirmado (fase 1: ejecutado el 18-oct, `at_rt_schema`=1; la fase 2 usa el mismo comando para pasar a `2`):

```sh
wp eval 'at_rt_migrar_esquema();'
```

No subir al webroot por FTP: `tests/`, `Docs/`, `N8N/`, fuentes/ZIP del renderer, `.git/`, configuraciones con credenciales, `.env`, `wp-config.php` local, `node_modules/`, scratch, respaldos, capturas o evidencia. Esta tabla no incluye configuración privada ni secretos.

## Renderer y n8n de la fase 1: entregas separadas del FTP

ZIP de fuentes del renderer generado y verificado por el responsable principal: `C:/Users/luis_/.codex/visualizations/2026/10/18/01a14fdd-54a0-7662-bf9a-95ef90f51d65/AT-RETOMAR-001-renderer.zip`. Contiene 15 archivos, 56.350 bytes; comprobación `testzip` PASS; SHA-256 `595decd2c663edc9486428656cc92cc98c4f0c85f8460d6c01651915b8a2065d`. Layout en la raíz: `Dockerfile`, `.dockerignore`, `package.json`, `src/` (sin prefijo `renderer/`). No existe `package-lock.json` en la base usada. La construcción Docker real está **NOT_RUN**. Se construye una imagen en la infraestructura del renderer siguiendo su procedimiento, no se extrae en el webroot WordPress.

Comando reproducible desde el worktree, usando solo fuentes versionadas necesarias para construir la imagen (sin dependencias instaladas, `.env`, credenciales ni archivos públicos generados):

```powershell
git archive --format=zip --output="C:/Users/luis_/.codex/visualizations/2026/10/18/01a14fdd-54a0-7662-bf9a-95ef90f51d65/AT-RETOMAR-001-renderer.zip" HEAD:renderer Dockerfile .dockerignore package.json src
```

Fuente del ZIP entregado: HEAD `1911298d9c63c687cdcb989cf427fc835a09342c`; último cambio del renderer `46c38214dab357a4acea69dbabd0c338a4e02b35`. El comando representa el HEAD que se use al ejecutarlo; para reproducir ese contenido, usar `1911298d9c63c687cdcb989cf427fc835a09342c:renderer` en lugar de `HEAD:renderer`. Registrar SHA y hash del ZIP si se vuelve a empaquetar; el hash del contenedor ZIP puede variar con sus metadatos aunque conserve el contenido. No instala dependencias ni despliega. En la fase 1 esta función reutilizaba medios existentes y no pedía fotos nuevas; la fase 2 sí pide fotos a Higgsfield (ver «Fotos y costo»).

En la fase 1 se entregaron **dos** flujos conforme a Tasks 4/7: [render](../../N8N/retomar/retomar-render.json) y [reloj + WhatsApp](../../N8N/retomar/retomar-reloj.json); desde la fase 2 son **tres**, porque se suma [redactar](../../N8N/retomar/retomar-redactar.json) (generador `build_retomar_redactar.py`) y los otros dos cambian (ver «Después de subir»), con sus generadores [build_retomar_render.py](../../N8N/retomar/build_retomar_render.py) y [build_retomar_reloj.py](../../N8N/retomar/build_retomar_reloj.py). La especificación menciona tres flujos separados: la diferencia es de empaque, reloj y WhatsApp comparten uno; no cambia reglas ni permisos. Ver [preparación n8n](../../N8N/retomar/README.md) y [plantilla Marketing](../../N8N/retomar/PLANTILLA-retomar_propuesta.md). Importar, vincular credenciales y comprobar runtime corresponde al destino con Eje. Estado al 19-oct: render activo desde el 18-oct; reloj y WhatsApp inactivo hasta la autorización de Luis. La aprobación de la plantilla Meta es un gate separado del consentimiento individual.

Contrato de render versionado: WordPress envía `{id,generacion,render}`; n8n devuelve `{ok,generacion,view_url,pdf_url,nota}` también en fallos (desde la fase 2 suma `fotos`, las URL de las fotos hechas por lámina). UID interno: token, generación hexadecimal de 16 caracteres y sufijo `-retomar` (57 caracteres). El enlace entregado al prospecto sigue siendo el wrapper con token estable. No mezclar este PHP con un flujo anterior que no devuelva `generacion`.

## QA posterior al FTP, todavía no ejecutada

Con datos ficticios/controlados y autorización para cada entrega real:

- Verificar acceso privado al panel y nonce; 404 con tokens ausentes/ajenos, agenda y vista válidas, cabeceras noindex/no-cache y no-referrer del renderer, también con rutas codificadas; no publicar enlaces de prueba.
- Comprobar autenticación de tick, render y callback sin imprimir secretos; render completo y vista previa, datos máximos y demos HTTPS en pantalla e impresión.
- Comprobar horario Santiago, mínimo de intervalo, máximo de contactos y tick duplicado del mismo día; confirmar paradas por respuesta manual, reunión, propuesta y cliente. Registrar el resultado sin disparar mensajes adicionales por repetir una ejecución.
- Comprobar GET de confirmación sin baja, POST confirmado/One-Click idempotentes, supresión cruzada por correo O teléfono y reversión con motivo sin reactivar. Preservar las bajas durante cualquier corrección.
- Hacer prueba SMTP al equipo y verificar recepción, HTML/texto, enlaces y One-Click en el correo recibido; guardar evidencia sanitizada. `wp_mail=true` o aceptación del proveedor no acreditan entrega. Verificar Meta y callback solo tras aprobación y autorización, sin reintentos automáticos.
- Registrar versión desplegada, resultados y aprobación del piloto; cualquier paso sin acceso permanece **NOT_RUN**. No convertir la aceptación local en certificación de PROD.

## Rollback operativo y de código

Ante incidente, desactivar los flujos n8n y pausar los seguimientos activos. Conservar la página de baja operativa, sus tokens y los datos de supresión; preservar BD, registros de envíos e historial para conciliación. **Nunca ejecutar DROP de tablas ni borrar bajas para volver a empezar.**

Si se autoriza rollback de código, restaurar los archivos respaldados y la imagen anterior del renderer, reteniendo los datos de Retomar. Antes de restaurar una versión sin soporte de baja, resolver con Eje cómo mantener el endpoint de opt-out compatible y disponible; no retirar `retomar.php` dejando enlaces ya enviados inoperantes. No restaurar una BD antigua sobre bajas o envíos recientes. Conciliar fallos y callbacks sin reenviar automáticamente. Estas son instrucciones de contingencia; no se ejecutó rollback ni despliegue. Para la fase 2, ver además «Rollback de la fase 2».
