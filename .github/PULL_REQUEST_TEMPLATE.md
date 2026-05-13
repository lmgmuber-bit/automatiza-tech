<!--
PR Template AutomatizaTech.
Marca con [x] cada item que aplique. Si NO aplica, marcalo igual y
explica brevemente "n/a porque ...". No mergear PRs sin esta lista.
-->

## Descripcion

<!-- Que cambia y por que. Tickets/issues relacionados. -->

## Tipo de cambio

- [ ] Bugfix
- [ ] Nueva funcionalidad
- [ ] Refactor / limpieza
- [ ] Cambio de seguridad / hardening
- [ ] Cambio de configuracion / infra
- [ ] Documentacion

## Checklist de seguridad (obligatorio)

### Datos y queries
- [ ] Toda query a `$wpdb` usa `$wpdb->prepare()` con placeholders cuando hay variables.
- [ ] No hay concatenacion de `$_GET`/`$_POST`/`$_REQUEST`/`$_COOKIE` en SQL.
- [ ] Cuando recibo un `id` de input, lo paso por `intval()` o `absint()` ademas de `prepare()`.

### Autenticacion y autorizacion
- [ ] Endpoints admin verifican `current_user_can( 'manage_options' )` (o capability adecuada).
- [ ] Endpoints AJAX (`wp_ajax_*`, `wp_ajax_nopriv_*`) verifican `check_ajax_referer()` o `wp_verify_nonce()`.
- [ ] Para acceso a recursos por id (propuesta, contrato, cliente, mensaje), valido **ownership** antes de devolver datos (no IDOR).
- [ ] Scripts de mantenimiento incluyen `require_once 'at-maintenance-guard.php';` al inicio.

### Input / output
- [ ] Sanitizo input con la funcion adecuada (`sanitize_text_field`, `sanitize_email`, `absint`, `wp_kses_post`, ...).
- [ ] Escapo output segun contexto (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`, `wp_json_encode`).
- [ ] No hago `echo` directo de variables sin escapar.

### Secretos y config
- [ ] No hay API keys, passwords ni tokens hardcodeados. Todo via `at_env()` o `wp-config-secrets.php`.
- [ ] No agrego nuevos `define( '*_SECRET', '...' )` con valor literal.
- [ ] Si introduzco una env var nueva, la documento en `wp-config-secrets.example.php`.

### Uploads
- [ ] Si recibo `$_FILES`, valido extension + MIME real con `at_validate_upload()`.
- [ ] Renombro el archivo a hash; nunca uso el `name` del cliente como nombre final.
- [ ] El directorio destino tiene `.htaccess` que niega ejecucion PHP.

### Webhooks / APIs externas
- [ ] Webhooks aceptan firma HMAC en header (`at_webhook_verify_hmac`).
- [ ] Endpoints publicos sensibles tienen rate limiting (`at_rate_limit_check`).
- [ ] CORS usa `at_cors_apply()`; nunca emito `Access-Control-Allow-Origin: *`.

### Path / shell
- [ ] No interpolo input en `shell_exec`/`exec`/`system`. Si los uso, args por `escapeshellarg()` y rutas hardcodeadas.
- [ ] Cuando construyo paths con input, valido con `at_path_inside()` o `at_safe_basename()`.

### Logging
- [ ] No hago `error_log( print_r( $_POST, true ) )` ni similar (no PII en logs).
- [ ] Logs no contienen tokens, passwords, ni cuerpos de webhooks.

## Pruebas

- [ ] Probado en local.
- [ ] Verificado que endpoints publicos siguen respondiendo.
- [ ] Verificado que el cap-check / nonce rechaza accesos no autorizados.

## Despliegue

- [ ] No requiere migraciones, o estan documentadas.
- [ ] No requiere variables nuevas en Hostinger, o estan documentadas.
- [ ] No requiere cambios al `.htaccess`, o estan documentadas en `Docs/HARDENING_HTACCESS_DEPLOY.md`.
