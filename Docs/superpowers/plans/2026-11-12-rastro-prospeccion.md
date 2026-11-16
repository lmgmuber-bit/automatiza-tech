# Rastro · Prospección: plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pestaña «Prospección» en el wp-admin de AT: lista de prospectos con estados, importación CSV, enlace personal al formulario de agenda, paso automático al CRM como prospecto, avisos por correo y WhatsApp, y baja.

**Architecture:** Módulo nuevo `inc/prospeccion/` con el patrón del tema (`cargar.php` + `puras.php` sin WordPress + archivos con WordPress), cargado por una línea en `functions.php`. El enlace `?r=<token>` deja una cookie propia; el paso a «llenó formulario» se dispara por dos caminos sin tocar el formulario existente: un envoltorio de `window.atLead` (camino principal, que agenda por n8n) y el gancho `save_post_at_demo_request` (camino de respaldo `contact_form`). El servidor confirma que el lead existe antes de marcar.

**Tech Stack:** PHP 8 / WordPress (tema `automatiza-tech`), `$wpdb` + `dbDelta`, `WP_List_Table`, JS sin dependencias, n8n por webhook para el WhatsApp.

**Spec:** `Docs/superpowers/specs/2026-11-12-rastro-prospeccion-design.md`

## Global Constraints

- PHP de pruebas: `PHP="/c/wamp64/bin/php/php8.4.15/php.exe"`. Pruebas puras: `"$PHP" tests/prospeccion/<archivo>.php` → termina en `TODO OK`. Pruebas con WordPress: `AT_WP_LOAD=<ruta de Task 0> "$PHP" tests/prospeccion/<archivo>-wp-test.php`.
- Prefijo de funciones `at_pro_`; opciones `at_pro_*`; tabla `{prefix}at_prospectos`.
- Estados exactos: `por_contactar`, `contactado`, `respondio`, `formulario`, `descartado`, `baja`.
- Fuentes exactas: `lead_scout`, `sellbird`, `manual`, `instagram`.
- Toda consulta con `$wpdb->prepare`; toda acción del panel con nonce y `manage_options`.
- Nada sale a un prospecto desde el sistema: el panel solo copia texto o abre `wa.me`; Luis envía.
- Repositorio público: sin datos de prospectos, sin CSV reales, sin destinatarios de correo ni números de teléfono en el código (van en opciones o constantes del servidor).
- Textos visibles en español de Chile. Íconos del wp-admin: Dashicons (los del propio panel), con `aria-label` en los botones que solo tienen ícono.
- `functions.php` se coteja contra PROD antes de subir (solo se agrega una línea).

## Review Focus

1. Prospecto en `baja` que vuelve en un CSV nuevo (por dominio, correo o teléfono): no debe entrar; lo prueba Task 2.
2. Token inexistente, de un prospecto en `baja` o `descartado`, o reenviado dos veces: no marca nada, no crea dos filas en el CRM ni dos avisos; lo prueba Task 4.
3. Alguien llama al AJAX público con un token válido pero sin haber agendado: no debe marcar (el lead tiene que existir con ese correo en los últimos 30 minutos); lo prueba Task 4.
4. Correo del formulario que ya existe en `crm_clientes` como cliente: no se baja a prospecto ni se pisa su origen; lo prueba Task 5.
5. CSV con BOM, separador `;`, columnas en otro orden o filas sin web ni teléfono: se importa lo válido y se informa lo rechazado; lo prueba Task 2.

---

### Task 0: Sitio local de prueba, carga del módulo y esquema

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/prospeccion/cargar.php`, `inc/prospeccion/esquema.php`
- Modify: `wp-content/themes/automatiza-tech/functions.php` (una línea `require_once get_template_directory() . '/inc/prospeccion/cargar.php';` justo después de la de `seo-tecnico/cargar.php`)
- Create: `tests/prospeccion/wp-bootstrap.php` (copia del de `tests/cierre/`, comprobando `function_exists('at_pro_migrar_esquema')`), `tests/prospeccion/esquema-wp-test.php`

**Interfaces:**
- Produces: `at_pro_migrar_esquema(): void` (idempotente; deja `at_pro_db_version` = `'1'`) · `at_pro_tabla(): string`.

- [ ] **Step 0:** Armar el WordPress local de prueba fuera del repo: carpeta `C:\wamp64\www\at-rastro-test\` con el núcleo copiado del espejo local (sin `wp-content`), `wp-content/themes/automatiza-tech` y `wp-content/mu-plugins` como *junction* a las carpetas del worktree, base `at_rastro_test` importada del volcado de la base local, y `wp-config.php` propio (credenciales locales de WAMP; nunca leer el `wp-config.php` de otro sitio). Anotar la ruta `AT_WP_LOAD` en el ledger.
- [ ] **Step 1:** Test `esquema-wp-test.php`: tras `at_pro_migrar_esquema()` dos veces, `SHOW COLUMNS` trae exactamente las columnas del spec (id, negocio, rubro, comuna, web, dominio, telefono, correo, fuente, senales, servicio_sugerido, estado, canal, token, crm_cliente_id, contactado_at, respondio_at, formulario_at, baja_at, notas, created_at, updated_at), `SHOW INDEX` trae `token` único, `dominio` único, índice `estado` e índice `telefono`, y `get_option('at_pro_db_version') === '1'`.
- [ ] **Step 2:** Correr → FALLA (función no definida).
- [ ] **Step 3:** Implementar con `dbDelta`; `dominio` VARCHAR(190) NULL (único con NULL permitido para filas sin web), `estado` default `por_contactar`. Calibre revisa largos e índices antes del commit.
- [ ] **Step 4:** Correr → `TODO OK`.
- [ ] **Step 5:** Commit `feat(prospeccion): carga del módulo y tabla at_prospectos`.

### Task 1: Funciones puras

**Files:** Create `inc/prospeccion/puras.php` · Test `tests/prospeccion/puras-test.php` (sin WordPress)

**Interfaces:**
- Produces:
  - `at_pro_estados(): array` (los seis, en orden) · `at_pro_fuentes(): array`
  - `at_pro_dominio(string $url): string` (minúsculas, sin esquema, sin `www.`, sin ruta ni puerto; `''` si no hay host)
  - `at_pro_telefono(string $t): string` (solo dígitos; 9 dígitos que empiezan con 9 → `56` + número; igual criterio que `at_pt_telefono_wa`)
  - `at_pro_token(): string` (16 caracteres `[A-Za-z0-9]` desde `random_bytes`)
  - `at_pro_token_valido(string $t): bool`
  - `at_pro_transicion_valida(string $de, string $a): bool`
  - `at_pro_columna_csv(string $cabecera): string` (alias → campo: `nombre|name|negocio|empresa|company` → `negocio`; `website|web|sitio|url` → `web`; `telefono|teléfono|phone` → `telefono`; `email|correo|e-mail` → `correo`; `rubro|categoria|category` → `rubro`; `comuna|ciudad|city` → `comuna`; `senales|señales|signals|motivo` → `senales`; lo demás → `''`)
  - `at_pro_mensaje(array $p, string $url_form, string $url_baja): string`

- [ ] **Step 1:** Tests:
  - `at_pro_dominio('https://www.Ejemplo.cl/contacto?x=1')` = `'ejemplo.cl'`; `('ejemplo.cl:8080')` = `'ejemplo.cl'`; `('')` = `''`.
  - `at_pro_telefono('+56 9 1234 5678')` = `'56912345678'`; `('912345678')` = `'56912345678'`.
  - `at_pro_token()` cumple `at_pro_token_valido`; `at_pro_token_valido('abc')` false; `('aaaaaaaaaaaaaaa!')` false.
  - Transiciones: `por_contactar→contactado` true; `contactado→respondio` true; `respondio→formulario` true; `por_contactar→formulario` true (llenó sin contestar); cualquiera → `descartado` true; cualquiera → `baja` true; `baja→contactado` false; `formulario→por_contactar` false; `descartado→contactado` true (se reabre).
  - `at_pro_columna_csv('Teléfono')` = `'telefono'`; `("\xEF\xBB\xBFnombre")` = `'negocio'` (BOM); `('xyz')` = `''`.
  - `at_pro_mensaje(['negocio'=>'Café Sur','senales'=>'no tienen reservas en línea'], 'https://x/?r=T', 'https://x/?baja=T')` contiene `Café Sur`, `no tienen reservas en línea`, la URL del formulario y la de baja, y no contiene `$` ni la palabra `garantiza`.
- [ ] **Step 2:** Correr → FALLA.
- [ ] **Step 3:** Implementar. Texto base del mensaje (el constructor lo puede pulir sin agregar precios ni promesas): saludo con el negocio, una frase con la señal, qué hace AT en una línea, enlace al diagnóstico gratis, línea final «Si no quieres recibir más mensajes: <url_baja>».
- [ ] **Step 4:** Correr → `TODO OK`.
- [ ] **Step 5:** Commit `feat(prospeccion): funciones puras`.

### Task 2: Datos e importación

**Files:** Create `inc/prospeccion/datos.php` · Test `tests/prospeccion/datos-wp-test.php`, fixture `tests/prospeccion/fixtures/ejemplo.csv` (datos inventados con dominios `.test`)

**Interfaces:**
- Consumes: Task 0 tabla; Task 1 puras; `at_rt_en_bajas(string $email, string $telefono_wa): bool` (módulo retomar, lista común de bajas).
- Produces:
  - `at_pro_crear(array $campos): int|WP_Error` (códigos: `repetido`, `en_bajas`, `sin_contacto` si no trae web, teléfono ni correo)
  - `at_pro_obtener(int $id): ?array` · `at_pro_por_token(string $t): ?array`
  - `at_pro_cambiar_estado(int $id, string $estado, string $canal = ''): bool` (respeta `at_pro_transicion_valida`; fija `contactado_at`/`respondio_at`/`formulario_at`/`baja_at` la primera vez)
  - `at_pro_importar_csv(string $ruta, string $fuente): array` → `['entraron'=>int,'repetidos'=>int,'bajas'=>int,'rechazados'=>int,'errores'=>string[]]`
  - `at_pro_conteos(): array` (por estado, por fuente y por rubro)
  - `at_pro_dar_baja(string $token): bool` (estado `baja` + fila en `{prefix}at_retomar_bajas` con `origen`=`prospeccion` si no existía)

- [ ] **Step 1:** Tests:
  - Crear dos con el mismo dominio → el segundo devuelve `WP_Error` `repetido`; mismo teléfono con otro formato → `repetido`.
  - Correo presente en `at_retomar_bajas` → `en_bajas`.
  - Importar `ejemplo.csv` (BOM, separador `;`, columnas desordenadas, 1 fila repetida, 1 fila sin contacto, 1 fila de un dominio dado de baja) → `entraron`=3, `repetidos`=1, `rechazados`=1, `bajas`=1.
  - Separador `,` también funciona (detectar por la primera línea).
  - `at_pro_cambiar_estado` a `contactado` fija `contactado_at` y una segunda llamada no lo cambia; `baja→contactado` devuelve false.
  - `at_pro_dar_baja(token)` deja `baja`, inserta la fila común, y reimportar el mismo negocio cuenta en `bajas`.
- [ ] **Step 2:** Correr → FALLA.
- [ ] **Step 3:** Implementar. El CSV se lee con `fgetcsv` desde el archivo temporal subido; nunca se guarda una copia.
- [ ] **Step 4:** Correr → `TODO OK`.
- [ ] **Step 5:** Commit `feat(prospeccion): datos, importación CSV y bajas`.

### Task 3: Pantalla «Prospección» en el panel

**Files:** Create `inc/prospeccion/admin.php`, `inc/prospeccion/lista.php` (clase `AT_Prospectos_Lista extends WP_List_Table`, patrón `inc/propuestas-admin/lista.php`) · Test `tests/prospeccion/admin-wp-test.php`

**Interfaces:**
- Consumes: Task 2.
- Produces: submenú `add_submenu_page('automatiza-crm', 'Prospección', 'Prospección', 'manage_options', 'at-prospeccion', 'at_pro_pagina')`; acciones por `admin-post.php`: `at_pro_estado` (id, estado), `at_pro_importar` (archivo, fuente), `at_pro_crear_manual`; nonces `at_pro_estado_<id>`, `at_pro_importar`, `at_pro_crear`.

- [ ] **Step 1:** Tests (con un usuario administrador y uno suscriptor):
  - `at_pro_pagina()` con tres prospectos muestra los tres negocios, las vistas con su conteo y la tarjeta de números.
  - Cada fila trae «Copiar mensaje» (atributo `data-mensaje` con el texto de `at_pro_mensaje` y la URL con su token), un enlace `https://wa.me/<telefono>?text=` con el mensaje codificado cuando hay teléfono, y botones de estado.
  - `at_pro_estado` sin nonce o con suscriptor → no cambia el estado.
  - Filtro `?fuente=sellbird` y búsqueda por negocio devuelven solo lo esperado.
- [ ] **Step 2:** Correr → FALLA.
- [ ] **Step 3:** Implementar. URL del formulario: `home_url('/')` + `?r=<token>`; URL de baja: `home_url('/')` + `?baja=<token>`. Botón «Copiar» con `navigator.clipboard` en un JS inline del panel. Dashicons con `aria-label`.
- [ ] **Step 4:** Correr → `TODO OK`. Captura del panel en el sitio de prueba guardada en la carpeta de evidencias.
- [ ] **Step 5:** Commit `feat(prospeccion): pestaña Prospección en el CRM`.

### Task 4: Enlace personal y paso a «llenó formulario»

**Files:** Create `inc/prospeccion/publico.php`, `wp-content/themes/automatiza-tech/assets/js/prospeccion-ref.js` · Test `tests/prospeccion/publico-wp-test.php`

**Interfaces:**
- Consumes: Task 2; Task 5 `at_pro_al_llenar(array $p, array $persona): void`.
- Produces:
  - `template_redirect`: si llega `?r=<token>` válido y el prospecto no está en `baja`, cookie `at_pro_r` (30 días, `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS) y redirección 302 a la misma URL sin `r`.
  - `at_pro_marcar_formulario(string $token, string $email, string $origen): bool` (solo si el token existe, el estado admite `formulario`, y hay en `automatiza_leads` una fila con ese correo creada hace menos de 30 minutos o un `at_demo_request` con ese `_at_email` del mismo plazo; toma nombre y teléfono de ese lead; llama a `at_pro_al_llenar` una sola vez).
  - AJAX `wp_ajax(_nopriv)_at_pro_formulario` (nonce `at_pro_ref`, lee la cookie del lado del servidor, límite 10 por hora por IP con transient).
  - Gancho `save_post_at_demo_request` → `at_pro_marcar_formulario($_COOKIE['at_pro_r'], _at_email, 'contact_form')`.
  - `prospeccion-ref.js` se encola solo si existe la cookie (bandera `window.AtProRef = {ajax, nonce}`); envuelve `window.atLead` conservando el original: al recibir `{email}` hace un POST a `at_pro_formulario` y siempre llama al original.

- [ ] **Step 1:** Tests:
  - `?r=` con token válido deja la cookie; con token de `baja` no la deja.
  - Token válido + lead real con ese correo de hace 5 minutos → estado `formulario`, `formulario_at` fijado y `at_pro_al_llenar` llamado una vez (doble de prueba).
  - Mismo llamado dos veces → una sola llamada a `at_pro_al_llenar`.
  - Sin lead con ese correo, o lead de hace 2 horas → no marca.
  - Token de prospecto `descartado` → no marca.
  - El gancho de `at_demo_request` con cookie marca igual que el AJAX.
- [ ] **Step 2:** Correr → FALLA.
- [ ] **Step 3:** Implementar.
- [ ] **Step 4:** Correr → `TODO OK`. Prueba manual en el sitio local: abrir `/?r=<token>`, agendar desde el modal y ver el estado cambiar.
- [ ] **Step 5:** Commit `feat(prospeccion): enlace personal y paso a llenó formulario`.

### Task 5: Paso al CRM y avisos

**Files:** Create `inc/prospeccion/crm-avisos.php` · Test `tests/prospeccion/crm-avisos-wp-test.php`

**Interfaces:**
- Produces:
  - `at_pro_crm_prospecto(array $persona, array $p): int` (busca en `crm_clientes` por correo normalizado; si existe, devuelve su id sin cambiar `tipo` ni `origen` salvo que `origen` esté vacío; si no existe, inserta `tipo`=`prospecto`, `estado`=`nuevo`, `origen`=`prospeccion`, `empresa`=negocio, `rubro`, nombre, correo y teléfono). No usa `at_cc_asegurar_cliente`, que convierte en cliente.
  - `at_pro_al_llenar(array $p, array $persona): void` (guarda `crm_cliente_id`, manda correo y WhatsApp).
  - Correo: `wp_mail` a `get_option('at_pro_aviso_correo', get_option('admin_email'))`, asunto `Prospección: <negocio> llenó el formulario`, con negocio, quién, fecha de contacto y enlace a la ficha del CRM.
  - WhatsApp: si están definidas las constantes `AT_N8N_RASTRO_AVISO` (URL del webhook) y `AT_RASTRO_AVISO_KEY`, `wp_remote_post` JSON `{negocio, nombre, fecha_contacto, crm_url}` con cabecera `X-AT-Rastro-Key`; si faltan o falla, se registra con `error_log` y el correo sale igual.

- [ ] **Step 1:** Tests:
  - Correo nuevo → fila `prospecto` con `origen`=`prospeccion`.
  - Correo de un cliente existente → mismo id, `tipo` sigue `cliente`, `origen` intacto.
  - `at_pro_al_llenar` llama a `wp_mail` una vez (capturado con el filtro `pre_wp_mail`) con el asunto exacto.
  - Sin las constantes no hay `wp_remote_post` (capturado con `pre_http_request`); con ellas, uno solo con la cabecera.
- [ ] **Step 2:** Correr → FALLA.
- [ ] **Step 3:** Implementar.
- [ ] **Step 4:** Correr → `TODO OK`.
- [ ] **Step 5:** Commit `feat(prospeccion): paso al CRM y avisos`.

### Task 6: Página de baja

**Files:** Modify `inc/prospeccion/publico.php` · Test `tests/prospeccion/baja-wp-test.php`

**Interfaces:**
- Consumes: Task 2 `at_pro_dar_baja`.
- Produces: `?baja=<token>` muestra una página corta con un botón «Confirmar baja» (POST con nonce, para que un enlace abierto por un antivirus no dé de baja solo); al confirmar, `at_pro_dar_baja` y mensaje «Listo, no te volveremos a escribir». Token inválido: mismo mensaje genérico, sin revelar si existe.

- [ ] **Step 1:** Tests: GET no cambia el estado; POST con nonce lo deja en `baja` y crea la fila común; token inválido responde 200 con el mensaje genérico.
- [ ] **Step 2–5:** FALLA, implementar, `TODO OK`, commit `feat(prospeccion): baja del prospecto`.

### Task 7: Documentación y paquete de subida

**Files:** Create `Docs/METODO_AT/PROSPECCION-RASTRO.md` (qué es, cómo importar, cómo contactar, estados, avisos, baja, diccionario de datos de `at_prospectos`, constantes y opciones sin valores, rollback) · Modify `CLAUDE.md` (un puntero de una línea en «Punteros de proyecto»)

- [ ] **Step 1:** Escribir la guía con la lista exacta de subida (`OBLIGATORIO`, orden: `inc/prospeccion/*`, `assets/js/prospeccion-ref.js`, al final `functions.php` cotejado contra PROD) y lo que **no** se sube (tests, fixtures, CSV).
- [ ] **Step 2:** Correr todas las pruebas de `tests/prospeccion/` → todas `TODO OK`.
- [ ] **Step 3:** Commit `docs(prospeccion): guía, diccionario de datos y paquete de subida`.

**Fuera de este plan (los hace Cimiento con OK de Luis):** flujo n8n del aviso por WhatsApp y su plantilla de Meta categoría Utilidad; constantes en el `wp-config.php` de PROD; despliegue.
