# Colaborador AT por cliente — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** un rol «Colaborador AT» que entra a una página «Mis clientes» y solo ve, anota y responde en el historial y los entregables de los clientes que Luis le asigna.

**Architecture:** módulo nuevo `inc/colaboradores/` cargado por un mu-plugin (no se toca `functions.php`). Una sola puerta de permisos (`at_colab_puede`) que usan la página y las dos acciones `admin-post.php`. Lee `wp_crm_historial`, `wp_automatiza_clients_details` y el módulo de entregables; no abre la ficha del CRM.

**Tech Stack:** WordPress (PHP 8.3/8.4), funciones sueltas con prefijo `at_colab_`, pruebas en PHP plano con `ok()`/`fin()` como `tests/entregables/`.

**Spec:** `Docs/superpowers/specs/2026-10-27-crm-colaborador-por-cliente-design.md`

## Global Constraints

- Rol `at_colaborador` («Colaborador AT») con capacidades `read` y `at_colaborador`; nunca `edit_posts`.
- Meta de usuario `at_clientes_asignados`: arreglo de ids de `wp_crm_clientes`.
- La lista de clientes solo la muestra y la guarda quien tiene `manage_options`.
- Tipos de detalle fuera del historial: `propuesta_enviada`, `cotizacion`, `boleta`, `factura`, `pago`, `contrato`, `contratacion`.
- Nunca se muestra `amount` ni `attachment_url`/`attachment_name`; del cliente solo nombre y empresa (ni correo ni teléfono).
- La página no imprime `crm_nonce` ni enlaza a `automatiza-crm-ficha`.
- `functions.php` y `crm-ai-completo.php` no se tocan. Textos en español de Chile, voz de equipo.
- El repo es público: nada de datos de clientes ni hallazgos de seguridad en código, commits o docs.
- Pruebas con WordPress: necesitan WAMP encendido; `AT_WP_LOAD=C:/wamp64/www/automatiza-tech/wp-load.php` y PHP `C:/wamp64/bin/php/php8.4.15/php.exe`. El bootstrap de la prueba carga el módulo **del worktree** con `require_once`.

## Review Focus

1. Un cliente se quita de la lista con la página abierta: la nota o la respuesta que se envía después se rechaza con «No tienes acceso» (Task 4).
2. A un colaborador le cambian el rol a suscriptor pero conserva la meta: `at_colab_puede` dice que no (Task 1).
3. Doble clic en «Responder»: queda una sola respuesta y un solo correo (`at_en_reclamar_respuesta`) (Task 4).
4. Nota vacía, solo espacios o de más de 6.000 caracteres: rechazada con mensaje, sin fila nueva (Task 4).
5. Cliente asignado sin historial ni entregables, o id asignado que ya no existe en el CRM: la página muestra el estado vacío o lo omite, sin error (Task 3).

---

### Task 1: Rol, permisos y campo del perfil

**Files:**
- Create: `wp-content/mu-plugins/at-colaboradores.php` (solo `require_once` de `themes/automatiza-tech/inc/colaboradores/cargar.php` si existe)
- Create: `wp-content/themes/automatiza-tech/inc/colaboradores/cargar.php`, `puras.php`, `permisos.php`, `perfil.php`
- Test: `tests/colaboradores/puras-test.php`, `tests/colaboradores/wp-bootstrap.php`, `tests/colaboradores/permisos-wp-test.php`, `tests/colaboradores/correr.sh`

**Interfaces:**
- Produces (puras): `at_colab_normalizar_ids($crudo): array` (ints > 0, únicos, ordenados; cualquier otra cosa → `[]`).
- Produces: constantes `AT_COLAB_ROL = 'at_colaborador'`, `AT_COLAB_META = 'at_clientes_asignados'`; `at_colab_registrar_rol(): void` (en `init`, crea el rol si falta y da la capacidad `at_colaborador` al rol `administrator`); `at_colab_ids_asignados(int $user_id): array`; `at_colab_es_colaborador(int $user_id = 0): bool`; `at_colab_puede(int $crm_id, int $user_id = 0): bool` (0 = usuario actual; `manage_options` → true si el cliente existe; colaborador con el rol → true solo si el id está asignado y existe; resto → false).
- Produces (perfil): bloque «Clientes asignados» en `show_user_profile`/`edit_user_profile` y guardado en `personal_options_update`/`edit_user_profile_update`, ambos solo con `manage_options`.

- [ ] **Step 1: Pruebas puras** (`puras-test.php`):
  `at_colab_normalizar_ids(['3','3',0,-1,'x',7]) === [3,7]`; `at_colab_normalizar_ids('3') === []`; `at_colab_normalizar_ids(null) === []`.
- [ ] **Step 2: Pruebas con WP** (`permisos-wp-test.php`, crea y borra sus propios usuarios y un cliente de prueba en `wp_crm_clientes`):
  administrador → `at_colab_puede($c)` true y `at_colab_puede(999999)` false; colaborador con `[$c]` → true para `$c`, false para otro cliente existente; colaborador con rol cambiado a `subscriber` y meta intacta → false; usuario sin rol → false; `get_role('at_colaborador')->has_cap('edit_posts') === false`; un colaborador que envía su perfil con `at_clientes_asignados[]` no cambia su meta; un administrador sí.
- [ ] **Step 3: Correr y ver que fallan**: `bash tests/colaboradores/correr.sh` → FALLA por funciones faltantes.
- [ ] **Step 4: Implementar** las piezas de Interfaces. El campo del perfil lista los clientes de `wp_crm_clientes` (id, nombre, empresa) con casillas y nonce propio.
- [ ] **Step 5: Correr**: `bash tests/colaboradores/correr.sh` → TODO OK en las dos pruebas.
- [ ] **Step 6: Commit** `feat(crm): rol Colaborador AT y permisos por cliente`

### Task 2: Datos del historial y del cliente

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/colaboradores/datos.php`; modificar `puras.php`
- Test: `tests/colaboradores/puras-test.php`, `tests/colaboradores/datos-wp-test.php`

**Interfaces:**
- Produces (puras): `at_colab_tipos_excluidos(): array` (los siete de Global Constraints); `at_colab_filtrar_eventos(array $eventos): array` — recibe filas ya unidas (`source`, `detail_type`, `tipo_evento`, `title`, `description`, `amount`, `attachment_url`, `attachment_name`, `usuario_id`, `created_by`, `metadata`, `fecha`), quita las de tipo excluido, borra `amount`, `attachment_url` y `attachment_name` de las demás y ordena por `fecha` descendente.
- Produces: `at_colab_cliente(int $crm_id): ?array` → `['id','nombre','empresa']` o null; `at_colab_historial(int $crm_id): array` — une `wp_crm_historial` (`source='system'`) y `get_client_details` del módulo de detalles (`source='client'`), pasa por `at_colab_filtrar_eventos` y agrega `origen_html` con `at_tl_origen_html($h)` si existe y `autor` (`display_name` de `usuario_id`/`created_by`, o '').

- [ ] **Step 1: Pruebas puras**: un evento `pago` con `amount=150000` no sale; un evento `nota` con `amount` y `attachment_url` sale sin esas claves; el orden es por fecha descendente; `at_colab_tipos_excluidos()` tiene exactamente los siete.
- [ ] **Step 2: Prueba con WP** (`datos-wp-test.php`, cliente de prueba con una nota, un detalle `reunion` y un detalle `factura` con monto): `at_colab_historial` trae la nota y la reunión, no la factura; ninguna fila tiene `amount`; `at_colab_cliente` no trae `email` ni `telefono`; `at_colab_cliente(999999) === null`.
- [ ] **Step 3: Correr y ver que fallan.**
- [ ] **Step 4: Implementar.**
- [ ] **Step 5: Correr** → TODO OK.
- [ ] **Step 6: Commit** `feat(crm): historial del colaborador sin montos ni contratos`

### Task 3: Página «Mis clientes»

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/colaboradores/pagina.php`
- Test: `tests/colaboradores/pagina-wp-test.php`

**Interfaces:**
- Consumes: `at_colab_puede`, `at_colab_ids_asignados`, `at_colab_cliente`, `at_colab_historial` (Tasks 1-2); del módulo de entregables `at_en_de_crm`, `at_en_versiones_enviadas`, `at_en_notas`, `at_en_url_imagen`, `at_en_fecha`.
- Produces: menú `add_menu_page('Mis clientes', 'Mis clientes', 'at_colaborador', 'at-mis-clientes', 'at_colab_render_pagina')`; `at_colab_html_pagina(int $user_id, int $crm_id, string $msg = ''): string` (la vista entera; se prueba sin navegador); `login_redirect` que manda al colaborador a `admin.php?page=at-mis-clientes`.
- Formularios que la Task 4 atiende: `action=at_colab_nota` (campos `crm_id`, `nota`, nonce `at_colab_nota_{crm_id}`) y `action=at_colab_responder` (campos `nota_id`, `texto`, `avisar`, nonce `at_colab_resp_{nota_id}`), ambos a `admin_url('admin-post.php')`.

- [ ] **Step 1: Prueba con WP** sobre `at_colab_html_pagina`: sin cliente → lista con el asignado y sin el no asignado; con un cliente no asignado → contiene «No tienes acceso a este cliente» y no su nombre; con el asignado → contiene el título de la nota, no contiene `crm_nonce`, `automatiza-crm-ficha`, el correo ni el teléfono del cliente, ni el monto de la factura; un comentario de cliente sin responder trae el formulario `at_colab_responder`; cliente sin historial ni entregables → «Todavía no hay movimientos» y «Este cliente no tiene entregables»; un id asignado que ya no existe se omite de la lista.
- [ ] **Step 2: Correr y ver que falla.**
- [ ] **Step 3: Implementar** la vista con el estilo de las demás páginas del admin (sin librerías nuevas) y los avisos `msg` que devuelve la Task 4 (`nota_ok`, `respuesta_ok`, `sin_acceso`, `nota_vacia`, `nota_larga`, `ya_respondida`).
- [ ] **Step 4: Correr** → TODO OK.
- [ ] **Step 5: Commit** `feat(crm): página Mis clientes del colaborador`

### Task 4: Agregar nota y responder comentarios

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/colaboradores/acciones.php`
- Test: `tests/colaboradores/acciones-wp-test.php` (corre las acciones sin `exit`: las acciones usan `at_colab_volver(string $msg, int $crm_id)`, que en pruebas lanza una excepción con la URL en vez de redirigir, como `tests/entregables/accion-run.php`)

**Interfaces:**
- Consumes: `at_colab_puede`; del módulo de entregables `at_en_nota`, `at_en_por_id`, `at_en_validar_nota`, `at_en_reclamar_respuesta`, `at_en_soltar_respuesta`, `at_en_archivos_subidos`, `at_en_procesar_imagenes`, `at_en_agregar_nota`, `at_en_historial`, `at_en_cliente`, `at_en_extracto`, `at_en_avisar_respuesta`; `at_cc_correo_avisos()`.
- Produces: `at_colab_accion_nota(): void` en `admin_post_at_colab_nota`; `at_colab_accion_responder(): void` en `admin_post_at_colab_responder`; `at_colab_validar_nota(string $t): array` (puras: `['ok'=>bool,'error'=>'nota_vacia'|'nota_larga'|'','texto'=>string]`, máximo 6.000 caracteres).
- Nota: inserta en `wp_crm_historial` `tipo_evento='nota'`, `titulo='Nota agregada'`, `descripcion`, `usuario_id` = colaborador, `created_at` = ahora; manda un correo a `at_cc_correo_avisos()` con asunto «Nota de {colaborador} en {cliente}» y el texto.
- Responder: la misma secuencia que `at_en_accion_responder` en `inc/entregables/panel.php`, con el permiso cambiado por `at_colab_puede($e->crm_id)` y la firma `display_name` del colaborador.

- [ ] **Step 1: Pruebas**: `at_colab_validar_nota('   ')` → `nota_vacia`; 6.001 caracteres → `nota_larga`. Con WP: la nota de un colaborador en su cliente crea una fila con su `usuario_id` y un correo a avisos; en un cliente no asignado → `sin_acceso` y ninguna fila; nonce de otro cliente → rechazado; quitar el cliente de la meta y enviar → `sin_acceso`; responder un comentario de su cliente crea la respuesta con su nombre y el evento `entregable_respuesta`; segundo envío del mismo → `ya_respondida` y un solo correo; responder un comentario de un entregable de otro cliente → `sin_acceso`.
- [ ] **Step 2: Correr y ver que fallan.**
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Correr toda la carpeta**: `bash tests/colaboradores/correr.sh` → TODO OK en todas; y `bash tests/entregables/correr.sh` sin fallas nuevas.
- [ ] **Step 5: Commit** `feat(crm): notas y respuestas del colaborador`

### Task 5: Guía y lista de despliegue

**Files:**
- Create: `Docs/METODO_AT/COLABORADORES.md` (qué es, cómo crear el usuario con `wp user create <usuario> <correo> --role=at_colaborador --display_name="…" --send-email`, cómo asignar clientes, qué ve y qué no, cómo quitar el acceso, cómo correr las pruebas)
- Modify: `Docs/METODO_AT/README.md` (una línea con el enlace)

- [ ] **Step 1: Escribir la guía** con la lista de despliegue: 1) `inc/colaboradores/` completo, 2) `mu-plugins/at-colaboradores.php` al final; `php -l` de cada archivo, md5 contra el commit y `wp litespeed-purge all`; respaldo previo de lo que exista.
- [ ] **Step 2: Correr todas las pruebas** de `tests/colaboradores/` y `tests/entregables/` → sin fallas.
- [ ] **Step 3: Commit** `docs(crm): guía del colaborador AT`
