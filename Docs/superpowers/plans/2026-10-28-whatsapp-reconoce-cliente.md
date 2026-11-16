# Tech WhatsApp reconoce a quien escribe — plan de implementación

> **Para agentes:** SUB-SKILL REQUERIDA: superpowers:subagent-driven-development (recomendada) o superpowers:executing-plans, tarea por tarea. Los pasos usan casillas (`- [ ]`).

**Objetivo:** que el asistente de WhatsApp sepa por el número si le escribe una persona **nueva**, un **prospecto** o un **cliente real**, lo salude por su nombre, le ofrezca el seguimiento de su propuesta o su proyecto (preguntando cuál si hay varios) y deje la reunión ligada a ese contrato o esa propuesta.

**Arquitectura:**
- **WordPress:** un módulo nuevo (`inc/wa-contexto/`) junta las fichas del número desde cuatro tablas y aplica la regla de las tres situaciones con funciones puras. Lo expone en `GET /automatiza-tech/v1/wa-contexto`, que solo usa n8n.
- **Reuniones:** `POST /followup-meetings` guarda el vínculo (`contrato_id`, `propuesta_id`) solo si es del mismo cliente.
- **n8n:**
  - el flujo de WhatsApp arma **una sola** línea de contexto interno y cambia «Verify Client» por la ruta nueva;
  - la elección entre varias opciones se hace con botones o con una lista;
  - la elección se guarda en la misma clave de Redis que ya lee el reagendamiento (`followup_resched_client_<teléfono>`), y desde ahí llega al sub-workflow de agenda;
  - los cambios los aplica un script con respaldo, en el estilo de `N8N/cerebro-at/sincronizar.py`.

**Stack:** PHP de WordPress (tema `automatiza-tech`), MySQL, n8n (JS en nodos Code), Node 20 para las pruebas de JS, Python 3 para el script de n8n.

**Diseño:** `Docs/superpowers/specs/2026-10-28-whatsapp-reconoce-cliente-design.md` (aprobado por Luis el 28-oct-2026). Rama `claude/wa-reconoce-cliente`, worktree `.worktrees/wa-reconoce` (al día con `main`, `0231861`).

## Datos verificados en PROD antes de planificar (28-oct, solo lectura)

- **Columnas reales.**
  - `automatiza_contracts`: `id, client_id, proposal_id, contract_number, type, placeholders, status, signed_at…`
  - `automatiza_propuestas`: `id, client_email, unique_link_id, client_name, company_name, phone, status, created_at…`; no hay columna de título.
  - `automatiza_leads`: `name, email, phone, status, scheduled_date, scheduled_time, attendance_status…`
  - `crm_clientes`: `nombre, email, telefono, empresa`
  - `automatiza_tech_clients`: `crm_cliente_id, name, email, company, phone`
  - `automatiza_planes_trabajo`: `contrato_id, codigo, payload`; el payload trae la clave `proyecto`.
  - `automatiza_followup_meetings`: no tiene `contrato_id` ni `propuesta_id`.
- **Estados que existen hoy.**
  - contratos `soporte/at_pending`, `soporte/signed` y `servicios/signed`;
  - propuestas `aceptada`, `archivada`, `pending` y `sent`;
  - citas `active` y `cancelled`; la inasistencia se anota en `attendance_status = 'no_show'`.
- **Conteo que pedía el diseño (§10).**
  - 4 clientes reales, todos con teléfono y con correo.
  - 4 propuestas abiertas, todas con correo; 1 sin teléfono propio.
  - Ninguna reunión de seguimiento futura activa.
- **Flujo vivo `bBcNlFgBzQ0766Mq` (223 nodos).**
  - `Route Action` (salida 7) → `Verify Client` → `Is Client?` → `Prepare Followup Data` → `Get Appointments Config` → `Generate Days` → calendario.
  - `Generate Days` lee `$('Prepare Followup Data')` **por nombre**: ese nodo se queda.
  - El seguimiento termina en `Prepare Seguimiento Data Direct` / `Custom`, que leen Redis `followup_resched_client_<tel>` (`{name, email, company, subject, notes}`) y llaman a `PejBgr2LZQSAbbBy`.
  - `Button Action` tiene 23 reglas (0–22).
  - Los datos del número están en `Extract Message Data` (`phoneNumber`, `phoneNumberId`, `contactName`).
- **Sub-workflow `PejBgr2LZQSAbbBy`.** `Get Client Data` → `Merge Client Data` usa la respuesta solo si `appointmentType === 'Seguimiento'`. El diagnóstico, que Instagram también usa, no la lee.

## Restricciones globales

- **Repositorio público.** Nada de datos reales de clientes en pruebas, fixtures ni documentos; los nombres de prueba son inventados («Cliente Prueba WA»).
- **`functions.php` no se toca.** El módulo se carga desde `inc/admin-followup-meetings.php`, que `functions.php` ya requiere.
- **La ruta exige la clave de n8n:** `permission_callback` = `at_cc_rest_auth`, con el mismo cierre de respaldo que tiene hoy `verify-client`. Sin clave, 401.
- **Teléfono:** se compara por los últimos 9 dígitos, sin signos, en los dos lados. Con menos de 9 dígitos no se busca.
- **La línea de contexto para el agente** lleva solo el primer nombre y los nombres de propuestas o proyectos (y, como hoy, el aviso de propuesta pendiente). Nunca correo, teléfono ni montos; la propuesta pendiente sigue con su enlace, igual que ahora.
- **Las palabras con que el asistente le habla a la persona** van siempre como «diagnóstico gratis», nunca «demo».
- **Límites de WhatsApp:**
  - como mucho 3 botones de respuesta, con título de hasta 20 caracteres;
  - en una lista, como mucho 10 filas, con título de hasta 24 caracteres y descripción de hasta 72.
- **n8n:**
  - la clave se lee de `~/.claude.json` y nunca se imprime;
  - antes del PUT, respaldo en `~/respaldos/n8n/wa-reconoce/<fecha>/`;
  - PUT con los settings filtrados (lista `SETTINGS_OK` de `sincronizar.py`);
  - se publica solo si el flujo estaba activo y nunca se cambia su estado;
  - después de subir, se relee `activeVersion`.
  - **Dueño:** Eje, en el flujo de WhatsApp y en `PejBgr2LZQSAbbBy`. Antes de subir `PejBgr2LZQSAbbBy` se avisa a la sesión «Asistente de mensajes directos de Instagram», que lo usa para el diagnóstico.
- **PROD, solo con el OK de Luis en el chat:** despliegue de WordPress por SSH (respaldo, `php -l`, md5 contra PROD), los PUT de n8n y `sincronizar.py --subir`.
- **Cómo se corren las pruebas:**
  - puras: `php tests/wa-contexto/<x>-test.php`;
  - WordPress local: `AT_WP_LOAD=<ruta a wp-load.php> php tests/wa-contexto/<x>-wp-test.php` (bootstrap `tests/cierre/wp-bootstrap.php`);
  - JS: `node --test N8N/wa-reconoce/nodos/`.

## Foco de la revisión

1. **Una respuesta caída de `wa-contexto`** (timeout, 500, sin `situacion`): el mensaje pasa al agente sin línea, como hoy. En el seguimiento se sigue con un seguimiento general sin vínculo; nunca se le ofrece el diagnóstico a alguien solo porque la consulta falló. *(Pruebas en la Tarea 6; condición de «Is Client?» en la Tarea 7.)*
2. **Un botón tocado tarde**, cuando esa propuesta o ese contrato ya no es opción (se aceptó, se archivó o se canceló): se vuelven a mostrar las opciones vigentes, sin agendar con un id viejo. *(Tarea 6.)*
3. **Números extranjeros** (+58 Venezuela, +1 EE. UU.) guardados con o sin signos: calzan igual por los últimos 9 dígitos. *(Tarea 1.)*
4. **Dos opciones que quedan con el mismo título** después de recortarlo a 20 o 24 caracteres: se distinguen con un sufijo « 2», « 3». *(Tarea 6.)*
5. **Un número que está en dos fichas distintas:** no saluda por nombre, cada opción lleva la agenda de su propia ficha, y el vínculo solo se acepta para fichas de ese número o de ese correo. *(Tareas 2 y 4.)*

---

### Tarea 1: Regla de las tres situaciones (funciones puras)

**Archivos:**
- Crear: `wp-content/themes/automatiza-tech/inc/wa-contexto/puras.php`
- Prueba: `tests/wa-contexto/puras-test.php`, con el estilo de `tests/meta-capi/puras-test.php` (`ok($cond, $msg)`, `exit(1)` si hay fallas).

**Interfaces que produce:**
- `at_wa_ultimos9(string $tel): string`: los últimos 9 dígitos, o `''` si hay menos de 9.
- `at_wa_primer_nombre(string $nombre): string`: la primera palabra con mayúscula inicial (`mb_convert_case`), o `''`.
- `at_wa_codigo_de_texto(string $texto): string`: el código de 12 letras o números de «(código XXXXXXXXXXXX)», con o sin tilde y sin distinguir mayúsculas en «código»; `''` si no viene.
- `at_wa_nombre_contrato(string $tipo, array $placeholders, string $proyecto_plan, string $numero): string`. Toma lo primero que exista:
  1. `$proyecto_plan`;
  2. si es `servicios`, la primera línea de `servicios_contratados`, sin el «- » inicial;
  3. si es `soporte` o `sla`, «Soporte técnico»;
  4. «Contrato $numero».
- `at_wa_nombre_propuesta(string $servicio, string $empresa, string $creada): string`: el servicio; si no hay, la empresa; si tampoco, «Propuesta del DD-MM» (tomado de `$creada`, en formato `AAAA-MM-DD…`).
- `at_wa_clasificar(array $fichas, string $ahora): array`:
  - recibe una lista de fichas: `['clave'=>string, 'nombre'=>string, 'agenda'=>['nombre_completo','correo','empresa'], 'contratos'=>[['id','tipo','estado','firmado'=>bool,'nombre','plan_codigo']], 'propuestas'=>[['id','estado','nombre']], 'diagnosticos'=>[['cuando'=>'AAAA-MM-DD HH:MM','estado','asistencia']]]`;
  - devuelve `['situacion'=>'nuevo'|'prospecto'|'cliente', 'nombre'=>string, 'opciones'=>[['tipo'=>'proyecto'|'propuesta','id'=>int,'nombre'=>string,'plan_codigo'=>string,'agenda'=>array]], 'diagnostico_proximo'=>'AAAA-MM-DD HH:MM'|'']`.
- `at_wa_elegida(array $opciones, string $codigo): ?array`: la opción `proyecto` cuyo `plan_codigo` es exactamente `$codigo` (`hash_equals`), o `null`.

**Reglas exactas de `at_wa_clasificar`** (constantes en el archivo):

| Constante | Valor |
|---|---|
| `AT_WA_TIPOS_TRABAJO` | `['servicios','soporte','sla']` |
| `AT_WA_PROPUESTA_ABIERTA` | `['sent','evaluando']` |
| `AT_WA_PROPUESTA_NO_CUENTA` | `['borrador','error']` |

- **Cliente:** alguna ficha tiene un contrato con tipo en `TIPOS_TRABAJO`, `firmado` y estado distinto de `cancelled`. Un contrato `expired` firmado cuenta.
- **Prospecto:** no es cliente y tiene alguna propuesta con estado fuera de `NO_CUENTA` (`pending` cuenta), o un diagnóstico realizado: `cuando < $ahora`, estado fuera de `cancelled`/`no_show` y `asistencia !== 'no_show'`.
- **Nuevo:** todo lo demás.
- **La situación de varias fichas** es la más alta (nuevo < prospecto < cliente).
- **Opciones:**
  - para un cliente, sus proyectos (contratos con tipo en `TIPOS_TRABAJO` y estado `signed`, por id descendente) y después sus propuestas abiertas (estado en `PROPUESTA_ABIERTA`, por id descendente);
  - para un prospecto, solo las propuestas abiertas;
  - para un nuevo, `[]`.
  - Cada opción lleva la `agenda` de su ficha.
- **`nombre`:** el primer nombre si hay una sola ficha; `''` si hay dos o más.
- **`diagnostico_proximo`:** el diagnóstico futuro más cercano que no esté cancelado.

- [ ] **Paso 1: Escribir la prueba que falla.** Cada caso con fichas armadas a mano y `$ahora = '2026-11-10 12:00'`:
  - `at_wa_ultimos9('+56 9 1234 5678') === '912345678'`; `('56912345678')` y `('912345678')` dan lo mismo; `('+58 414-123-4567') === '141234567'`; `('+1 (804) 298-0236') === '042980236'`; `('1234') === ''`.
  - `at_wa_codigo_de_texto('Hola, quiero agendar (código Ab3dE6gH9jK1)') === 'Ab3dE6gH9jK1'`; con «codigo» sin tilde, igual; sin código, `''`.
  - Las cuatro ramas de `at_wa_nombre_contrato` y las tres de `at_wa_nombre_propuesta`.
  - **Clasificación:**
    - Contrato `servicios/signed` firmado → `cliente`, con 1 opción `proyecto`.
    - Mismo contrato `expired` firmado → `cliente`, con 0 opciones de proyecto.
    - Solo un `nda` firmado → no es cliente.
    - Contrato `cancelled` → no es cliente.
    - Propuesta `pending` sin contrato → `prospecto`, con 0 opciones.
    - Propuesta `sent` → `prospecto`, con 1 opción `propuesta`.
    - Propuesta `aceptada` → `prospecto`, con 0 opciones.
    - Solo diagnóstico pasado `active` → `prospecto`.
    - Diagnóstico pasado con `asistencia = 'no_show'` → `nuevo`.
    - Diagnóstico futuro → `nuevo`, con `diagnostico_proximo = '2026-11-12 10:00'`.
    - Cliente que además tiene una propuesta `sent` → `cliente`, con opciones [proyecto, propuesta] en ese orden.
    - Dos fichas (una cliente, otra prospecto) → `cliente`, `nombre === ''` y las opciones de las dos, cada una con su `agenda`.
    - Lista vacía → `nuevo`, `nombre ''`, `opciones []`.
  - `at_wa_elegida`: con el código exacto, la opción; con otras mayúsculas o un código de otra ficha, `null`.
- [ ] **Paso 2:** `php tests/wa-contexto/puras-test.php` → falla porque el archivo de funciones no existe.
- [ ] **Paso 3:** implementar `puras.php`. Sin `$wpdb` ni funciones de WordPress. Debe empezar con `if (!defined('ABSPATH') && !defined('AT_WA_PURAS_TEST')) { exit; }`, y la prueba define `AT_WA_PURAS_TEST`.
- [ ] **Paso 4:** correr la prueba → `TODO OK`.
- [ ] **Paso 5:** commit `feat(wa): regla nuevo, prospecto o cliente para el asistente de WhatsApp`.

### Tarea 2: Fichas de un número desde la base

**Archivos:**
- Crear: `wp-content/themes/automatiza-tech/inc/wa-contexto/datos.php`
- Prueba: `tests/wa-contexto/datos-wp-test.php`. Crea sus propias filas con datos inventados y un número al azar que no exista (como `vc_numero()` en `tests/followup/verify-client-wp-test.php`), y las borra al final, aunque una aserción falle (`try/finally`).

**Interfaces:**
- **Consume:**
  - de la Tarea 1, `at_wa_ultimos9`, `at_wa_primer_nombre`, `at_wa_nombre_contrato`, `at_wa_nombre_propuesta`, `at_wa_clasificar` y `at_wa_elegida`;
  - del tema, `at_cc_crm_de_email(string): int` y `at_cc_techs_de_crm(int): array` (`cierre-cliente/clientes.php`), `at_cc_filas_de_propuesta(object): array` (servicio en `[0]['service']`) y `at_pt_plan_de_contrato(int): ?object` (`plan-trabajo/datos.php`), cada una con `function_exists`.
- **Produce:**
  - `at_wa_sql_ultimos9(string $columna): string`: la expresión `RIGHT(REPLACE(…), 9)` que hoy usa `automatiza_tech_verify_client_by_phone`.
  - `at_wa_fichas_por_telefono(string $tel): array`: la lista de fichas en la forma de la Tarea 1.
  - `at_wa_contexto(string $tel, string $texto = ''): array`: `at_wa_clasificar(...)` más `'elegida' => at_wa_elegida(opciones, at_wa_codigo_de_texto($texto))` y `'agenda'`.
  - `at_wa_vinculo_valido(int $contrato_id, int $propuesta_id, string $tel, string $correo): array`: devuelve `['contrato_id'=>int, 'propuesta_id'=>int]`, y cada id vale `0` si no es del cliente.

**Cómo se arman las fichas** (decisiones de Eje, por el diseño §4 y §10):
- **Agrupación.** Se juntan las coincidencias por número de `crm_clientes.telefono`, `automatiza_tech_clients.phone` (→ `crm_cliente_id`), `automatiza_propuestas.phone` y `automatiza_leads.phone`. Cada una se lleva a la ficha del CRM: directo, por `crm_cliente_id` o con `at_cc_crm_de_email(correo)`. Si no tiene ficha en el CRM, la clave del grupo es `correo:<correo normalizado>`.
- **Contratos** de una ficha del CRM: los de `client_id` en los ids de `at_cc_techs_de_crm(crm_id)`.
  - `firmado` = `signed_at IS NOT NULL`.
  - `plan_codigo` y `proyecto_plan` salen de `at_pt_plan_de_contrato(id)` y de `payload['proyecto']`.
  - `nombre` = `at_wa_nombre_contrato(...)`, con `placeholders` decodificado.
- **Propuestas** de una ficha: las de `client_email` igual al correo del CRM o al de alguna de sus fichas operativas. `nombre` = `at_wa_nombre_propuesta(servicio de la primera fila, company_name, created_at)`.
- **Diagnósticos:** las filas de `automatiza_leads` con esos correos. `cuando` = `scheduled_date scheduled_time` (HH:MM), `estado` = `status`, `asistencia` = `attendance_status`.
- **`agenda`** de la ficha: `nombre_completo` = `crm_clientes.nombre` (si no hay, `name` de la ficha operativa); `correo` = `crm_clientes.email`; `empresa` = `crm_clientes.empresa`. Para un grupo sin CRM, los datos de su propuesta o cita más reciente.
- **`at_wa_contexto`:** la `agenda` de primer nivel es la de la única ficha; con 0 o 2+ fichas va vacía (`['nombre_completo'=>'','correo'=>'','empresa'=>'']`). `$ahora` = `current_time('Y-m-d H:i')`.
- **`at_wa_vinculo_valido`:**
  - Un `contrato_id` vale si es de una ficha de `at_wa_fichas_por_telefono($tel)` o de la ficha de `at_cc_crm_de_email($correo)`, en cualquier estado.
  - Un `propuesta_id`, igual.
  - Un id que no calza se devuelve como `0`.

- [ ] **Paso 1: Escribir la prueba que falla.** Con filas propias:
  - Ficha CRM con teléfono `+56 9 …`, contrato `servicios` firmado y plan con `payload.proyecto = 'Proyecto Prueba WA'` → `at_wa_contexto('569…')` da `cliente`, la opción `proyecto` con ese nombre y su `plan_codigo`, y la `agenda` con el correo de la ficha.
  - El mismo número en el formato `9…` da lo mismo.
  - Propuesta `sent` cuyo número solo está en `automatiza_propuestas.phone` → `prospecto`, con la opción `propuesta`.
  - Número que no existe → `nuevo`.
  - Mismo número en dos fichas del CRM → `nombre === ''` y `agenda.correo === ''`.
  - `$texto` con «(código <plan_codigo>)» → `elegida.id` = el contrato.
  - `at_wa_vinculo_valido`: el contrato propio se devuelve tal cual; el contrato de otra ficha da `0`.
  - `$wpdb->last_error === ''` en todas las consultas (`show_errors(true)`).
- [ ] **Paso 2:** correr con `AT_WP_LOAD` → falla porque las funciones no existen.
- [ ] **Paso 3:** implementar `datos.php` con `$wpdb->prepare` en toda consulta con valores. Se limita a 1000 filas por tabla, como en `at_cc_propuesta_pendiente_por_telefono`.
- [ ] **Paso 4:** correr la prueba → `TODO OK`.
- [ ] **Paso 5:** commit `feat(wa): fichas de un número desde CRM, contratos, propuestas y citas`.

### Tarea 3: Ruta `wa-contexto` y carga del módulo

**Archivos:**
- Crear: `wp-content/themes/automatiza-tech/inc/wa-contexto/rest.php` y `inc/wa-contexto/cargar.php` (este último hace `require_once` de `puras.php`, `datos.php` y `rest.php`).
- Modificar: `wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php`, con `require_once __DIR__ . '/wa-contexto/cargar.php';` al inicio, después del guard de `ABSPATH` si existe.
- Prueba: `tests/wa-contexto/rest-wp-test.php`, con el estilo de `vc_pedir()` (`rest_do_request` y la cabecera `X-AT-Secret`; si no está definida, define `AT_REST_SECRET` de prueba).

**Interfaces:**
- **Consume:** `at_wa_contexto` (Tarea 2) y `at_cc_rest_auth`.
- **Produce:** `GET /wp-json/automatiza-tech/v1/wa-contexto?phone=…&texto=…`, que responde `{situacion, nombre, opciones, diagnostico_proximo, elegida, agenda}`. Las opciones se entregan con `{tipo, id, nombre, plan_codigo, agenda}`. El callback es `at_wa_rest_contexto(WP_REST_Request $r): array`. Los argumentos van saneados: `phone` con `sanitize_text_field` y obligatorio; `texto` con `sanitize_textarea_field`, opcional y cortado a 500 caracteres.

- [ ] **Paso 1: Escribir la prueba que falla.**
  - Sin clave → 401.
  - Con una clave equivocada → 401.
  - Con la clave y el número de una ficha de prueba → 200 y `situacion`.
  - **La respuesta no expone datos de más:** recorriendo todo el JSON, ningún valor fuera de las claves `agenda` contiene `@` ni los últimos 9 dígitos del número, y ninguna clave se llama `url`, `monto` ni `telefono`/`phone`.
- [ ] **Paso 2:** correr → falla con 404 (la ruta no existe).
- [ ] **Paso 3:** implementar `rest.php` y la carga.
- [ ] **Paso 4:** correr también `tests/followup/verify-client-wp-test.php` y `tests/followup/verify-client-clave-wp-test.php` (deben seguir pasando: `verify-client` no se toca aquí) → `TODO OK`.
- [ ] **Paso 5:** commit `feat(wa): ruta wa-contexto solo para n8n`.

### Tarea 4: La reunión de seguimiento queda ligada

**Archivos:**
- Modificar: `wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php`. Se toca la migración de `init` (lista `$required_columns`, ~línea 73), el registro de `POST /followup-meetings` (~línea 2267) y `automatiza_tech_create_followup_meeting_api` (~línea 2489).
- Prueba: `tests/wa-contexto/followup-vinculo-wp-test.php`.

**Interfaces:**
- **Consume:** `at_wa_vinculo_valido` (Tarea 2).
- **Produce:**
  - `automatiza_tech_followup_migrar_columnas(): void`. Es la migración de columnas que hoy es un closure anónimo en `init`; pasa a ser una función con nombre enganchada a `init`, con el mismo comportamiento, para que la prueba la llame directo sin disparar todos los hooks de `init`.
  - columnas `contrato_id BIGINT UNSIGNED NULL` y `propuesta_id BIGINT UNSIGNED NULL`, cada una con su índice (`idx_contrato_id`, `idx_propuesta_id`), en un solo `ALTER TABLE … ADD COLUMN …, ADD KEY …` por columna y solo si falta;
  - `POST /followup-meetings` acepta `contrato_id` y `propuesta_id` (opcionales, con `absint`) y guarda lo que devuelve `at_wa_vinculo_valido($contrato_id, $propuesta_id, $phone, $email)`. Un 0 se guarda como `NULL`.

- [ ] **Paso 1: Escribir la prueba que falla.** Las reuniones de prueba van con `meet_link` y `google_event_id` de mentira, para que no se llame al webhook del calendario. Usa una fecha lejana, a una hora sin choques, y correos únicos.
  - Llamar dos veces a `automatiza_tech_followup_migrar_columnas()` no deja errores (`$wpdb->last_error === ''`), y las dos columnas y sus índices existen.
  - POST con el `contrato_id` de la ficha del mismo correo → la fila guarda ese id.
  - POST con el `contrato_id` de otra ficha → la reunión se crea (`success`) y `contrato_id` queda `NULL`.
  - POST sin vínculo → igual que hoy.
  - La nota «Plan de trabajo <código>. …» se guarda tal cual, y `at_pt_seguimiento_pendiente(<código>)` encuentra la reunión.
  - Al final, borrar las filas de prueba.
- [ ] **Paso 2:** correr → falla (no existen las columnas).
- [ ] **Paso 3:** implementar.
- [ ] **Paso 4:** correr esta prueba y `tests/followup/*-wp-test.php` → `TODO OK`.
- [ ] **Paso 5:** commit `feat(wa): la reunión de seguimiento guarda su contrato o su propuesta`.

### Tarea 5: Mostrar a qué pertenece cada reunión

**Archivos:**
- Modificar:
  - `wp-content/themes/automatiza-tech/inc/wa-contexto/datos.php`, para agregar `at_wa_vinculo_texto`;
  - `inc/admin-followup-meetings.php`, en la tabla de «Reuniones de seguimiento», dentro de la celda del cliente, bajo el correo (~línea 1136);
  - `wp-content/mu-plugins/crm-ai-completo.php`, en la pestaña de seguimientos de la ficha, junto al asunto de cada reunión (~línea 3026, `$fm['meeting_subject']`).
- Prueba: `tests/wa-contexto/vinculo-texto-wp-test.php`.

**Interfaces:**
- **Produce:** `at_wa_vinculo_texto($reunion): string` (acepta objeto o arreglo). Devuelve «Proyecto: <nombre>» si tiene `contrato_id`, con el nombre de `at_wa_nombre_contrato` y el plan; si no, «Propuesta: <nombre>» si tiene `propuesta_id`; si no tiene vínculo, `''`. En la página se escapa con `esc_html` y va dentro de un `<small>`. Si `at_wa_vinculo_texto` no existe (orden de carga), no se muestra nada.

- [ ] **Paso 1: Escribir la prueba que falla.** Con un contrato y una propuesta de prueba: los tres casos de texto, que también funcionan con una fila como arreglo (`ARRAY_A`, como en el CRM).
- [ ] **Paso 2:** correr → falla.
- [ ] **Paso 3:** implementar la función y las dos salidas.
- [ ] **Paso 4:** correr la prueba → `TODO OK`. Abrir en el navegador local (preview) «Reuniones de seguimiento» y una ficha del CRM con una reunión de prueba ligada, y comprobar que se ve la línea.
- [ ] **Paso 5:** commit `feat(wa): la lista de reuniones y la ficha del CRM dicen a qué proyecto o propuesta pertenecen`.

### Tarea 6: Lógica de los nodos nuevos de n8n (JS con pruebas)

**Archivos:**
- Crear: `N8N/wa-reconoce/nodos/contexto.js`. El núcleo puro va entre `// <<WA RECONOCE: inicio>>` y `// <<WA RECONOCE: fin>>`, y al final lleva `module.exports`, como `N8N/cerebro-at/validacion.js`.
- Prueba: `N8N/wa-reconoce/nodos/contexto.test.js` (`node:test` + `assert`).

**Interfaces que produce** (el script de la Tarea 7 las copia en los nodos):
- `waTituloCorto(texto: string, max: number): string`: recorta por caracteres reales (`Array.from`) y termina en «…» si recortó.
- `waLineaContexto(ctx: object, prop: object): string`. `ctx` es la respuesta de `wa-contexto`; `prop`, la de `at/v1/propuesta-contexto`. Devuelve una sola línea, o `''` si no hay nada que decir.
- `waElegirSeguimiento(ctx: object, entrada: {buttonId, phoneNumber, phoneNumberId, contactName, output}): {modo: 'directo'|'elegir'|'sin_correo', datos: object, guardar: object|null, mensaje: object|null, opcion: object|null}`.

**Texto exacto de `waLineaContexto`.** Un solo corchete: `[Contexto interno, no la repitas: …]`. Las partes van en este orden, y cada una se agrega solo si corresponde:
1. Según la situación, una de estas:
   - `quien escribe es cliente de AutomatizaTech.`
   - `quien escribe es un prospecto: ya tuvo una reunión con nosotros.`
   - `quien escribe es una persona nueva.`
2. `Se llama <nombre>.` Si `nombre` viene vacío y hay opciones, en cambio: `Este número está en más de una ficha: no lo saludes por nombre.`
3. `Sus proyectos: «A», «B».` (opciones `proyecto`).
4. `Sus propuestas abiertas: «X».` (opciones `propuesta`). Si es un prospecto sin propuestas abiertas: `No tiene propuestas abiertas.`
5. `Escribe desde el plan del proyecto «<elegida.nombre>».`
6. `Tiene un diagnóstico gratis agendado para el DD-MM a las HH:MM.`
7. `Además, tiene la propuesta de AutomatizaTech para <empresa o nombre> esperando respuesta. Lo propuesto para partir: <propuesto>. Enlace para responder: <url>`: es el aviso de hoy de «Sumar Contexto», solo si `prop.tiene === true && prop.url`.

Si `ctx` no trae `situacion` (la ruta falló), se omiten las partes 1 a 6. Si al final no queda ninguna parte, devuelve `''`. Para una persona nueva sin diagnóstico ni propuesta pendiente también devuelve `''`, para que el mensaje llegue igual que hoy.

**Reglas de `waElegirSeguimiento`:**
- **`datos`** = `{phoneNumber, phoneNumberId, contactName: <nombre de agenda o primer nombre o contactName>, appointmentType: 'Seguimiento', output, clientEmail: <correo>, clientType: ctx.situacion || '', companyName: <empresa>}`. Son las mismas claves que hoy arma «Prepare Followup Data».
- **Opción elegida:**
  - si `buttonId` empieza con `seg_c_<id>` o `seg_p_<id>`, la opción de `ctx.opciones` con ese tipo e id;
  - si no, `ctx.elegida`;
  - si no, la única opción cuando hay exactamente 1.
- **Botón que ya no es opción:** se trata como si no hubiera botón (foco de revisión 2).
- **Modo:**
  - **`elegir`:** sin opción elegida y con 2 o más opciones. `mensaje` es el JSON completo para `POST /messages`, con `messaging_product` `whatsapp`, `to` y `type` `interactive`.
    - Con 2 o 3 opciones van botones `reply`, con id `seg_c_<id>` o `seg_p_<id>` y título `waTituloCorto(nombre, 20)`.
    - Con 4 o más va una lista: botón «Ver opciones», una sección «Elige una» y como mucho 10 filas, con título `waTituloCorto(nombre, 24)` y descripción «Proyecto» o «Propuesta».
    - Si dos títulos salen iguales, desde el segundo se agrega « 2», « 3».
  - **`sin_correo`:** hay opción (o no hay ninguna y la situación es `prospecto`/`cliente`), pero la agenda de esa opción y la de primer nivel no tienen correo.
  - **`directo`:** todo lo demás, incluso sin `situacion` (seguimiento general, foco de revisión 1).
- **Texto del mensaje para elegir** (`body.text`):
  - solo proyectos: `Hola <nombre> 👋 ¿De cuál proyecto quieres agendar el seguimiento?`;
  - solo propuestas: `Hola <nombre> 👋 ¿Sobre cuál propuesta quieres conversar?`;
  - mezcla: `Hola <nombre> 👋 ¿Sobre cuál quieres agendar el seguimiento?`;
  - sin nombre: `Hola 👋 …`.
- **`guardar`** (solo en `directo`): `{name: agenda.nombre_completo || contactName, email: agenda.correo, company: agenda.empresa, subject, notes, contrato_id, propuesta_id}`. La agenda es la de la opción o, si no hay opción, la de primer nivel. Según la opción:
  - proyecto con `plan_codigo`: `subject` = `Seguimiento: <nombre>` y `notes` = `Plan de trabajo <código>. Agendada por el cliente por WhatsApp.`;
  - proyecto sin plan: el mismo `subject` y `notes` = `Seguimiento del proyecto «<nombre>». Agendada por WhatsApp.`;
  - propuesta: `notes` = `Seguimiento de la propuesta «<nombre>». Agendada por WhatsApp.`;
  - sin opción: `subject` = `Reunión de Seguimiento`, `notes` = `Agendada desde whatsapp` e ids en 0.

- [ ] **Paso 1: Escribir la prueba que falla.**
  - **`waLineaContexto`:**
    - un cliente con un proyecto da exactamente `[Contexto interno, no la repitas: quien escribe es cliente de AutomatizaTech. Se llama Julio. Sus proyectos: «Proyecto Prueba».]`;
    - un prospecto con dos propuestas y además propuesta pendiente: aparecen las dos partes, en un solo corchete;
    - un nuevo sin nada → `''`;
    - un nuevo con diagnóstico futuro → la parte 6;
    - `ctx = {}` con propuesta pendiente → solo la parte 7;
    - `ctx = {}` sin propuesta → `''`;
    - en ningún caso la línea contiene `@` ni la palabra «demo».
  - **`waElegirSeguimiento`:**
    - 1 opción → `directo`, con `guardar.contrato_id` y `notes` con «Plan de trabajo»;
    - 2 opciones → `elegir`, con 2 botones de títulos de 20 caracteres o menos;
    - 5 opciones → una lista con títulos de 24 caracteres o menos;
    - 12 opciones → 10 filas;
    - títulos repetidos → sufijo « 2»;
    - botón `seg_p_<id>` válido → `directo` con `propuesta_id`;
    - botón con un id que ya no está → `elegir` otra vez;
    - `ctx.elegida` → `directo` sin preguntar;
    - opción sin correo → `sin_correo`;
    - `ctx = {}` → `directo` general con ids en 0;
    - `datos` trae siempre las 8 claves.
- [ ] **Paso 2:** `node --test N8N/wa-reconoce/nodos/` → falla.
- [ ] **Paso 3:** implementar `contexto.js`.
- [ ] **Paso 4:** correr → todo pasa. Correr también `node N8N/cerebro-at/validacion.test.js` (no se toca).
- [ ] **Paso 5:** commit `feat(wa): línea de contexto y elección de seguimiento para n8n`.

### Tarea 7: Script que aplica los cambios en n8n (con vista previa)

**Archivos:**
- Crear: `N8N/wa-reconoce/aplicar.py` y `N8N/wa-reconoce/README.md` (qué cambia, cómo se corre y cómo se vuelve atrás).

**Interfaces:**
- **Consume:** `contexto.js` (Tarea 6), el bloque entre marcas; el estilo de `N8N/cerebro-at/sincronizar.py` (`api()`, `SETTINGS_OK`, respaldo y republicar).
- **Produce:**
  - `python -I -X utf8 N8N/wa-reconoce/aplicar.py` hace el GET de los dos flujos vivos, aplica los cambios en memoria, valida y muestra un resumen (nodos agregados y cambiados, y las comprobaciones). No sube nada.
  - `--subir` sube primero `PejBgr2LZQSAbbBy` y después `bBcNlFgBzQ0766Mq`.
  - Es idempotente: si un nodo nuevo ya existe con su nombre, lo actualiza en vez de duplicarlo.

**Cambios en el flujo de WhatsApp** (los nombres de nodos son exactos):
1. **«Contexto Cliente»** (nuevo, `httpRequest` GET `https://automatizatech.cl/wp-json/automatiza-tech/v1/wa-contexto`).
   - Query `phone` = `{{ $('Extract Message Data').first().json.phoneNumber }}`.
   - Query `texto` = el `chatInput` de la primera de `Merge Data`, `Merge Audio Data` o `Merge Image Data` que se haya ejecutado (IIFE con `try`).
   - Las mismas credenciales que «Contexto Propuesta» (se copia su objeto `credentials`, sin imprimirlo), `timeout` 5000 y `neverError`.
   - Conexión: «Contexto Propuesta» → «Contexto Cliente» → «Sumar Contexto».
2. **«Sumar Contexto»** (código nuevo). La envoltura toma `base` como hoy, `prop = $('Contexto Propuesta').first().json` y `ctx = $input.first().json`. Antepone `waLineaContexto(ctx, prop) + '\n\n'` solo si no está vacía, y agrega `chatInputOriginal` con el texto sin la línea.
3. **«Verify Client»:** misma credencial; la URL pasa a `wa-contexto`, con `phone` = `$json.phoneNumber` y `texto` = `$('Sumar Contexto').first().json.chatInputOriginal`.
4. **«Is Client?»:** `value1` = `={{ $json.situacion !== 'nuevo' }}`; `value2` = `true`.
5. **«Elegir Seguimiento»** (nuevo, código). La envoltura arma `entrada`:
   - `buttonId` sale de «Process Interactive» si se ejecutó;
   - `phoneNumber`, `phoneNumberId` y `contactName` salen de «Extract Message Data»;
   - `output` sale de «Route Action» si se ejecutó.

   Devuelve `waElegirSeguimiento(ctx, entrada)`. Lo alimentan «Is Client?» (salida verdadera) y «Contexto Elegido».
6. **«Modo Seguimiento»** (nuevo, `switch` por `$json.modo`). Tiene tres salidas:
   - `directo` → **«Guardar Elección Seguimiento»** (`redis` set `followup_resched_client_{{ $('Extract Message Data').first().json.phoneNumber }}`, valor `={{ JSON.stringify($json.guardar) }}`, `expire` y `ttl` 86400; las credenciales se copian de «Save Reschedule Client») → «Prepare Followup Data».
   - `elegir` → **«Send Seguimiento Opciones»** (`httpRequest` POST `=https://graph.facebook.com/v22.0/{{ $json.datos.phoneNumberId }}/messages`, credencial de «Send Not Client Message», `jsonBody` = `={{ JSON.stringify($json.mensaje) }}`).
   - `sin_correo` → **«Send Seguimiento Sin Correo»** (la misma URL y credencial; cuerpo `{messaging_product: 'whatsapp', to: $json.datos.phoneNumber, type: 'text', text: {body: 'Para agendar tu seguimiento necesitamos un correo registrado y no lo encontramos. Ya le avisamos a Luis para que te escriba y lo coordinen.'}}`) y **«Notify Admin Seguimiento Sin Correo»** (`emailSend`, credenciales y remitente copiados de «Notify Admin Followup Action Error»; asunto `WhatsApp: seguimiento sin correo registrado`; cuerpo con el número y el nombre de la opción).
7. **«Prepare Followup Data»:** `jsonOutput` = `={{ $('Elegir Seguimiento').first().json.datos }}`. Ya no lee «Route Action», porque «Generate Days» lo usa por nombre.
8. **«Button Action»:** se agrega una regla al final, «Seguimiento Elegido», con `buttonId` que `startsWith` `seg_`. Su salida nueva va a **«Contexto Elegido»** (nuevo, igual que «Contexto Cliente», pero sin `texto`) → «Elegir Seguimiento».
9. **«Send Not Client Message»:**
   - texto: `No encontré una propuesta ni un proyecto con este número. ¿Te agendo tu diagnóstico gratis para conocer tu caso?`;
   - botones: el primero se llama `Agendar diagnóstico` (sin emoji, 19 caracteres) y conserva su id `btn_agendar_demo`; el segundo, `ℹ️ Más información`, no cambia;
   - ya no usa `$json.message`.
10. **«Prepare Seguimiento Data Direct»** y **«Prepare Seguimiento Custom»**: el IIFE agrega `contrato_id: Number(c.contrato_id) || 0, propuesta_id: Number(c.propuesta_id) || 0`.

**Cambios en `PejBgr2LZQSAbbBy`:**
1. **«Get Client Data»:** la URL pasa a `wa-contexto?phone=…`; la credencial y `neverError` no cambian.
2. **«Merge Client Data»:** en las tres expresiones que hoy leen `$json.client_email`, `$json.client_name` y `$json.company_name`, se pasa a leer `$json.agenda?.correo`, `$json.agenda?.nombre_completo` y `$json.agenda?.empresa`. Lo demás queda igual.
3. **«Prepare Payload»:** el objeto de `Seguimiento` suma `contrato_id: Number($('Execute Workflow Trigger').item.json.contrato_id) || 0, propuesta_id: Number($('Execute Workflow Trigger').item.json.propuesta_id) || 0`. El objeto del diagnóstico no cambia.

**Comprobaciones de la vista previa.** Si una falla, no se sube nada:
- cada nodo nombrado en `connections` existe;
- cada `$('<nombre>')` de los nodos nuevos o cambiados existe en ese flujo;
- «Button Action» tiene tantas salidas conectadas como reglas (24);
- «Is Client?» sale verdadero a «Elegir Seguimiento»;
- «Prepare Followup Data» ya no contiene `Route Action`;
- el bloque de «Sumar Contexto» y el de «Elegir Seguimiento» son idénticos al de `contexto.js`;
- en `PejBgr2LZQSAbbBy`, el objeto del diagnóstico en «Prepare Payload» quedó idéntico al de antes.

**Al subir:**
- respaldo de los dos flujos vivos;
- PUT con los settings filtrados;
- `activate` solo si estaba activo;
- se relee y se comprueba que la `activeVersion` trae «Elegir Seguimiento» y «Contexto Cliente» (y, en el sub-workflow, `agenda?.correo`).

- [ ] **Paso 1:** escribir `aplicar.py` con las comprobaciones.
- [ ] **Paso 2:** correr la vista previa contra los flujos vivos → todas las comprobaciones `OK` y la lista de cambios igual a los puntos de arriba.
- [ ] **Paso 3:** correr la vista previa dos veces seguidas: la segunda no debe agregar nodos (idempotente).
- [ ] **Paso 4:** escribir `N8N/wa-reconoce/README.md`.
- [ ] **Paso 5:** commit `feat(wa): script con vista previa para aplicar el reconocimiento en n8n`.

### Tarea 8: Lo que el asistente sabe hacer con la línea (Cerebro AT)

**Archivos:**
- Modificar: `N8N/cerebro-at/personalidad-whatsapp.md`. Al final se agrega la sección «QUIÉN TE ESCRIBE», que queda dentro de la región `### 🎭 PERSONALIDAD` que sincroniza `sincronizar.py`.

**Texto que se agrega** (exacto):
```
QUIÉN TE ESCRIBE (manda sobre las reglas de diagnóstico y seguimiento de más arriba):
- Si el mensaje trae «[Contexto interno …]», úsalo y nunca lo repitas ni lo cites.
- Cliente o prospecto: salúdalo por su nombre si viene y nombra su proyecto o su propuesta («¿Agendamos el seguimiento del proyecto Configurador?»). Si quiere agendar, es SEGUIMIENTO: <<ACTION:FOLLOWUP_MEETING>>, sin pedir nombre ni correo. Si tiene varios, no preguntes cuál: el sistema le muestra los botones.
- Solo si pide explícitamente un diagnóstico nuevo (por ejemplo, para otro negocio), agéndale el diagnóstico gratis con <<ACTION:SHOW_CALENDAR>>.
- Si el contexto dice que tiene un diagnóstico gratis agendado, recuérdaselo y ofrécele reprogramarlo (<<ACTION:RESCHEDULE_APPOINTMENT>>) en vez de agendar otro.
- Si dice que el número está en más de una ficha, no lo saludes por nombre.
- Nunca nombres proyectos, propuestas ni datos que no estén en el contexto de este número.
```

- [ ] **Paso 1:** agregar el texto.
- [ ] **Paso 2:** `python -I -X utf8 N8N/cerebro-at/sincronizar.py` (vista previa) → solo cambia la región de personalidad de WhatsApp, el corte de Instagram sigue válido y el prompt web no cambia.
- [ ] **Paso 3:** commit `feat(cerebro): Tech WhatsApp usa la línea de quién escribe`.

### Tarea 9: Despliegue y prueba real (solo Eje, con el OK de Luis)

No se delega: toca PROD y secretos.

- [ ] **Paso 1: cotejar.** El md5 sin CR de PROD contra `main`, para `inc/admin-followup-meetings.php` y `mu-plugins/crm-ai-completo.php` (iguales al 28-oct). Si alguno cambió, no subir y rehacer el cambio sobre la versión de PROD. Los archivos de `inc/wa-contexto/` no deben existir todavía.
- [ ] **Paso 2: WordPress por SSH.**
  - Respaldo de los dos archivos que se reemplazan.
  - Se sube en orden: `inc/wa-contexto/` (puras, datos, rest, cargar) → `crm-ai-completo.php` → `admin-followup-meetings.php` (al final, porque carga el módulo).
  - En cada PHP, `php -l` del archivo subido, md5 y purga de caché.
  - Una sonda por `wp eval-file` confirma que existen las dos columnas y la ruta.
  - Desde afuera, `GET /wp-json/automatiza-tech/v1/wa-contexto?phone=1` sin clave → 401.
- [ ] **Paso 3: n8n.**
  - Avisar a la sesión de Instagram que se sube `PejBgr2LZQSAbbBy`.
  - `aplicar.py` (vista previa) y después `--subir`.
  - `sincronizar.py --subir` para la personalidad.
  - Releer las dos `activeVersion`.
- [ ] **Paso 4: prueba real con Luis.** Su número se carga en una ficha de prueba como prospecto con dos propuestas abiertas, y después como cliente con dos contratos. En cada caso:
  - escribe «quiero agendar»;
  - recibe el saludo y los botones;
  - elige, agenda y cancela.

  Se revisa en n8n la ejecución de cada paso y en la base que la reunión quedó con su `contrato_id` o `propuesta_id`. Al final se borran las fichas y reuniones de prueba.
- [ ] **Paso 5: documentación.** `Docs/METODO_AT/WHATSAPP-RECONOCE-CLIENTE.md` (qué hace, la regla, cómo se apaga: restaurar los respaldos de n8n; las columnas pueden quedar) y el puntero en `CLAUDE.md`. Commit, push y PR.

### Tarea 10: Retirar `verify-client`

- [ ] **Paso 1:** listar todos los flujos de n8n por la API y buscar `verify-client` en sus nodos (solo nombres de flujo y de nodo en la salida). Si alguno lo usa, no se retira y se anota quién.
- [ ] **Paso 2:** si no lo usa nadie, quitar la ruta y `automatiza_tech_verify_client_by_phone` de `admin-followup-meetings.php`. Cambiar `tests/followup/verify-client-*-wp-test.php` para que comprueben que la ruta responde 404, con commit propio.
- [ ] **Paso 3:** subirlo a PROD con el mismo procedimiento de la Tarea 9, paso 2 (con el OK de Luis).

### Tarea 11 (la decide Luis): pedir el correo por el chat cuando la ficha no lo tiene

El diseño (§4) pide que el asistente pida el correo y lo valide con `validacion.js`. Hoy hay **0 casos**: los 4 clientes y las 4 propuestas abiertas tienen correo. Mientras tanto, el modo `sin_correo` de la Tarea 6 avisa a la persona y a Luis. Si Luis la quiere ahora, se hace con su propio ciclo:
- el estado `WAITING_EMAIL_SEG` en Redis;
- una rama en «Check Email Booking» con `atValidarCorreo`;
- la creación del seguimiento con el correo validado y su prueba.
