# Cierre de cliente — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el cliente pueda aceptar, seguir evaluando o rechazar la propuesta desde el sitio de AT (y que Luis registre a mano una aceptación con evidencia), y que al aceptar pase solo a cliente en una ficha única, reciba la bienvenida con sus primeros pasos y quede armado el contrato de servicio para que Luis lo ajuste y firme.

**Architecture:** Módulo nuevo `wp-content/themes/automatiza-tech/inc/cierre-cliente/` que carga siempre `inc/admin-proposals.php` (nunca `functions.php`). Las reglas van en funciones puras (`puras.php`) con pruebas sin WordPress; lo que toca la base, el correo y las pantallas va en archivos por responsabilidad (`clientes.php`, `contrato.php`, `bienvenida.php`, `respuesta.php`, `pagina.php`, `panel.php`, `ajustes.php`, `whatsapp.php`) con pruebas contra un WordPress local de prueba. Los módulos existentes (panel de propuestas, CRM, Contactos, contratos, Seguimiento, `ver-presentacion.php`) reciben cambios chicos y anclados.

**Tech Stack:** PHP (PROD 8.3.33 + WordPress 7.1.2; local 8.4.15 + WordPress 6.9.4), MySQL/MariaDB, FPDF (ya usado por contratos), HTML/CSS/JS sin dependencias.

Spec: `Docs/superpowers/specs/2026-09-25-cierre-de-cliente-design.md` (rama `claude/cierre-cliente`).

## Global Constraints

- **Nunca tocar `wp-content/themes/automatiza-tech/functions.php`:** PROD va adelantado. El módulo lo carga `inc/admin-proposals.php`.
- **Fines de línea:** en este worktree los archivos existentes están en CRLF (`grep -c $'\r' <archivo>` igual a `wc -l`). Se editan solo con la herramienta Edit, nunca reescribiéndolos con scripts. Al terminar cada tarea, comprobar que cada archivo existente modificado sigue con CRLF en todas sus líneas. Los archivos nuevos pueden ser LF.
- **Si un archivo CRLF quedó con líneas LF** después de editarlo, normalizarlo así (y volver a contar):
  `"$PHP" -r '$f=$argv[1]; file_put_contents($f, preg_replace("/
?
/", "
", file_get_contents($f)));' <archivo>`
- **Prefijo `at_cc_`** para toda función, opción, acción, nonce y transient nuevos. Textos de interfaz y correos en español de Chile.
- **Seguridad:**
  - Todo SQL con `$wpdb->prepare` (o `insert`/`update`); nombres de tabla con `$wpdb->prefix`.
  - Toda salida escapada (`esc_html`, `esc_attr`, `esc_url`, `esc_textarea`; en funciones puras, `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
  - Acciones de Luis: `manage_options` + nonce. Acción pública: nonce + campo trampa + límite por IP, y nunca por GET.
  - Las imágenes de evidencia nunca quedan públicas.
- **El repositorio es público:** ningún nombre real de cliente, teléfono, correo, RUT personal ni secreto en código, pruebas, comentarios ni commits. En pruebas: `example.com`, RUT `11.111.111-1`, `10.000.013-K`. Excepción decidida por Luis (25-sep): los datos públicos de AutomatizaTech (RUT `78.363.717-0` y WhatsApp `+56 9 2700 2984`) sí pueden ir en código y pruebas, porque ya están publicados en el sitio. El teléfono para las pruebas reales de WhatsApp (Task 12) lo tiene el controlador y no va al repo.
- **Tipos de contrato reales:** `type` = `servicios` (ya existe en el ENUM de `wp_automatiza_contracts`), `template_id` = `servicios_v1`. Sin cambios al ENUM.
- **Estados nuevos de propuesta:** `evaluando`, `rechazada`, `aceptada` (la columna `status` es `varchar(50)`: sin cambio de base). Tipo nuevo de Seguimiento: `respuesta_cliente` (`detail_type` es `varchar(50)`).
- **Único cambio de base:** columna `crm_cliente_id BIGINT UNSIGNED NULL` con índice en `wp_automatiza_tech_clients`, idempotente y con la opción `at_cierre_schema` = `'1'`.
- **PHP de pruebas:** `PHP="/c/wamp64/bin/php/php8.4.15/php.exe"`. Las pruebas puras se corren con `"$PHP" tests/cierre/<archivo>.php` y terminan en `TODO OK`. Las pruebas con WordPress se corren con `AT_WP_LOAD=<ruta que entrega el controlador> "$PHP" tests/cierre/<archivo>.php`.
- **Subagentes:** no despliegan, no se conectan a servidores, no usan `git stash`, `clean` ni `push`, no leen el archivo de claves. Los despliegues y todo lo de Meta y n8n los hace el controlador con autorización de Luis.
- **Commits:** `git commit -m "<asunto>" -m "Co-Authored-By: <modelo que corre> <noreply@anthropic.com>"`. Se agregan archivos por ruta; nunca `git add -A`.

## Mapa de archivos

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `wp-content/themes/automatiza-tech/inc/cierre-cliente/puras.php` | Crear | Reglas sin WordPress: transiciones, montos, anticipo, RUT, teléfonos, filas, fechas, textos, HTML de correos, validación de evidencia, marcadores del contrato |
| `wp-content/themes/automatiza-tech/inc/cierre-cliente/cargar.php` | Crear | Carga el módulo |
| `wp-content/themes/automatiza-tech/inc/cierre-cliente/clientes.php` | Crear | Esquema, ficha única: `at_cc_asegurar_cliente()` y enlaces |
| `wp-content/themes/automatiza-tech/inc/cierre-cliente/contrato.php` | Crear | Contrato de servicios desde una propuesta |
| `wp-content/themes/automatiza-tech/inc/cierre-cliente/ajustes.php` | Crear | Página «Ajustes del cierre» (datos de transferencia) |
| `wp-content/themes/automatiza-tech/inc/cierre-cliente/bienvenida.php` | Crear | Correo de bienvenida con la lista de arranque |
| `wp-content/themes/automatiza-tech/inc/cierre-cliente/respuesta.php` | Crear | Registrar respuestas, cierre automático, avisos, pedir respuesta |
| `wp-content/themes/automatiza-tech/inc/cierre-cliente/pagina.php` | Crear | Barra y formularios de respuesta en la página pública |
| `wp-content/themes/automatiza-tech/inc/cierre-cliente/panel.php` | Crear | Bloque «Respuesta del cliente» del panel, «Pedir respuesta», «Registrar aceptación», evidencia |
| `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php` | Crear | Aviso de WhatsApp tras el envío, envío por plantilla (inactivo) y endpoint REST |
| `Docs/CONTRATO_SERVICIO_DESARROLLO.md` | Crear | Plantilla del contrato de servicios (borrador para abogado) |
| `tests/cierre/puras-test.php`, `tests/cierre/plantilla-test.php` | Crear | Pruebas sin WordPress |
| `tests/cierre/wp-bootstrap.php`, `tests/cierre/clientes-wp-test.php`, `tests/cierre/contrato-wp-test.php`, `tests/cierre/cierre-wp-test.php`, `tests/cierre/rest-wp-test.php` | Crear | Pruebas contra el WordPress local de prueba |
| `wp-content/themes/automatiza-tech/inc/admin-proposals.php` | Modificar | Una línea: cargar el módulo |
| `wp-content/themes/automatiza-tech/inc/propuestas-admin/consultas.php` + `assets/css/propuestas-admin.css` + `tests/propuestas/admin-lista-test.php` | Modificar | Estados nuevos en la lista y la ficha |
| `wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php` | Modificar | Aviso, bloque de respuesta, casilla de WhatsApp, formularios externos |
| `wp-content/themes/automatiza-tech/inc/propuestas-admin/acciones.php` | Modificar | Bloque «Aceptar la propuesta» en el correo y WhatsApp tras el envío |
| `wp-content/mu-plugins/crm-ai-completo.php` | Modificar | Instancia global, enlace al portal, bienvenida nueva, conversión enlazada, pestaña «Contratos y operación» |
| `wp-content/themes/automatiza-tech/inc/contact-form.php` | Modificar | Al pasar un lead a cliente, enlazarlo; enlace «Ficha única» en la lista |
| `wp-content/themes/automatiza-tech/inc/client-details-module.php` | Modificar | Tipo de Seguimiento `respuesta_cliente` |
| `contracts/contract-service.php` | Modificar | Plantilla por id, título por tipo, revisión antes de firmar |
| `contracts/at-sign-contract.php` | Modificar | Formulario de revisión y firma bloqueada hasta guardarla; arreglo del enlace del PDF |
| `ver-presentacion.php` (raíz del sitio) | Modificar | Sin caché y barra de respuesta |

---

### Task 0: Entorno de prueba local (la hace el controlador)

No se delega. Deja listo `AT_WP_LOAD` para las tareas con pruebas de WordPress.

**Files:** ninguno del repositorio. Todo en `<scratchpad>/wp-local-cierre/`.

- [ ] **Step 1:** Confirmar que responde `http://localhost/automatiza-tech/` (WAMP encendido) y que la base es `automatiza_tech_local`.
- [ ] **Step 2:** Armar `<scratchpad>/wp-local-cierre/`:
  - uniones (`mklink /J`) a `wp-admin` y `wp-includes` de `C:\wamp64\www\automatiza-tech`;
  - copias de los `*.php` de la raíz **de este worktree** (incluye `ver-presentacion.php`, `at-path-safe.php`, `wp-*.php`) y de `wp-config.php` desde `C:\wamp64\www\automatiza-tech`;
  - uniones a `contracts` y `Docs` de este worktree;
  - `wp-content/` con uniones: `themes/automatiza-tech` y `mu-plugins` a las de este worktree, `plugins` a la copia principal; `uploads` como carpeta real nueva (no unión), para no ensuciar la copia principal.
- [ ] **Step 2b:** Confirmar que el `wp-config.php` local define `AT_REST_SECRET` (hoy trae un valor por defecto solo para local). La prueba de la Task 10 lo necesita; nunca se imprime su valor.
- [ ] **Step 3:** Crear la tabla `wp_automatiza_contracts` en la base local si no existe, cargando `wp-load.php` del sitio de prueba y ejecutando el `CREATE TABLE` de `contracts/setup-contracts-db.php` con `dbDelta` (desde un script del scratchpad; no se modifica el archivo del repo).
- [ ] **Step 4:** `router.php` para `php -S localhost:8089` (igual al de la tarea 5 del plan `2026-09-24-modulo-propuestas-admin.md`: `WP_HOME`/`WP_SITEURL` = `http://localhost:8089`, sesión del primer administrador local solo para ese servidor) y entrada `wp-local-cierre` en `.claude/launch.json` del worktree (no se commitea).
- [ ] **Step 5:** Anotar en el ledger la ruta `AT_WP_LOAD=<scratchpad>/wp-local-cierre/wp-load.php` y pasarla a cada subagente que corra pruebas `*-wp-test.php`.

---

### Task 1: Reglas puras del cierre (`puras.php`)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/cierre-cliente/puras.php`
- Test: `tests/cierre/puras-test.php`

**Interfaces:**
- Consumes: nada.
- Produces (todas sin WordPress, usadas por las tareas 3 a 10):
  - `at_cc_salidas(): array` → `['acepta' => 'aceptada', 'evalua' => 'evaluando', 'rechaza' => 'rechazada']`
  - `at_cc_transicion_respuesta_valida(string $desde, string $hacia, bool $manual = false): bool`
  - `at_cc_puede_pedir_respuesta(string $status): bool`
  - `at_cc_monto_de_etiqueta(string $etiqueta): ?int`, `at_cc_es_mensual(string $etiqueta): bool`, `at_cc_total_unico(array $filas): ?int`, `at_cc_anticipo(array $filas): ?int`, `at_cc_formato_clp(int $n): string`
  - `at_cc_email_normalizado(string $e): string`
  - `at_cc_rut_limpio(string): string`, `at_cc_rut_valido(string): bool`, `at_cc_rut_formato(string): string`
  - `at_cc_telefono_normalizado(string $t): string`
  - `at_cc_json_de_payload(string $s): ?array`, `at_cc_filas_de_payload(string $s): array`, `at_cc_filas_aceptadas(array $filas, array $indices): array`
  - `at_cc_huella(string $contenido): string`
  - `at_cc_fecha_larga(string $fecha): string`, `at_cc_fecha_declarada(string $ymd, string $hoy): string`
  - `at_cc_canales_manuales(): array`, `at_cc_canal_texto(array $d): string`
  - `at_cc_url_respuesta(string $base, string $codigo, string $responder = ''): string`
  - `at_cc_bloque_aceptar_html(string $url_aceptar, string $url_evaluar): string`
  - `at_cc_texto_whatsapp(string $nombre, string $empresa, string $url): string`, `at_cc_url_wa_me(string $telefono, string $texto): string`
  - `at_cc_servicios_markdown(array $filas): string`, `at_cc_claves_contrato_servicios(): array`, `at_cc_marcadores_servicios(array $d): array`
  - `at_cc_mensaje_respuesta(string $clave): ?array` → `['tipo' => 'ok'|'aviso'|'error', 'texto' => string]`
  - `at_cc_mimes_evidencia(): array`, `at_cc_error_evidencia(array $f, int $max = 5242880): string`, `at_cc_archivos_normalizados(array $campo): array`
  - `at_cc_banco_completo(array $b): bool`, `at_cc_bienvenida_html(array $v): string`, `at_cc_pedido_respuesta_html(string $nombre, string $empresa, string $bloque, string $logo, string $url_ver): string`

- [ ] **Step 1: Escribir la prueba** `tests/cierre/puras-test.php`:

```php
<?php
// Correr: php tests/cierre/puras-test.php   (sin WordPress)
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/cierre-cliente/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

// Salidas y transiciones
ok(at_cc_salidas() === ['acepta' => 'aceptada', 'evalua' => 'evaluando', 'rechaza' => 'rechazada'], 'salidas');
ok(at_cc_transicion_respuesta_valida('sent', 'aceptada'), 'sent -> aceptada');
ok(at_cc_transicion_respuesta_valida('sent', 'evaluando') && at_cc_transicion_respuesta_valida('sent', 'rechazada'), 'sent -> evaluando / rechazada');
ok(at_cc_transicion_respuesta_valida('evaluando', 'aceptada') && at_cc_transicion_respuesta_valida('evaluando', 'rechazada'), 'evaluando -> aceptada / rechazada');
ok(at_cc_transicion_respuesta_valida('rechazada', 'aceptada'), 'rechazada -> aceptada (cambió de opinión)');
ok(!at_cc_transicion_respuesta_valida('rechazada', 'evaluando'), 'rechazada no vuelve a evaluando');
ok(!at_cc_transicion_respuesta_valida('aceptada', 'rechazada') && !at_cc_transicion_respuesta_valida('aceptada', 'aceptada'), 'aceptada es final');
ok(!at_cc_transicion_respuesta_valida('borrador', 'aceptada') && !at_cc_transicion_respuesta_valida('lista', 'aceptada'), 'sin enviar no se acepta desde la página');
ok(at_cc_transicion_respuesta_valida('lista', 'aceptada', true) && at_cc_transicion_respuesta_valida('pending', 'aceptada', true), 'a mano sí desde lista y pending');
ok(!at_cc_transicion_respuesta_valida('borrador', 'aceptada', true), 'a mano no desde borrador');
ok(!at_cc_transicion_respuesta_valida('pending', 'evaluando', true), 'a mano solo se registra aceptación');
ok(at_cc_puede_pedir_respuesta('sent') && at_cc_puede_pedir_respuesta('evaluando') && at_cc_puede_pedir_respuesta('rechazada'), 'se pide respuesta en sent, evaluando y rechazada');
ok(!at_cc_puede_pedir_respuesta('aceptada') && !at_cc_puede_pedir_respuesta('borrador') && !at_cc_puede_pedir_respuesta('lista'), 'no se pide en aceptada, borrador ni lista');

// Montos y anticipo
ok(at_cc_monto_de_etiqueta('$2.000.000 en 2 pagos') === 2000000, 'monto con puntos');
ok(at_cc_monto_de_etiqueta('$250.000 en 2 pagos') === 250000, 'monto $250.000');
ok(at_cc_monto_de_etiqueta('$1.250.000 en 2 pagos (estimado)') === 1250000, 'monto estimado');
ok(at_cc_monto_de_etiqueta('$120.000 al mes') === 120000, 'monto mensual se lee');
ok(at_cc_monto_de_etiqueta('Incluido') === null && at_cc_monto_de_etiqueta('Por confirmar') === null, 'sin monto');
ok(at_cc_es_mensual('$120.000 al mes') && at_cc_es_mensual('$50.000 mensual') && !at_cc_es_mensual('$2.000.000 en 2 pagos'), 'mensual');
$filas = [
	['service' => 'Fase 1', 'price_label' => '$250.000 en 2 pagos'],
	['service' => 'Fase 2', 'price_label' => '$400.000 en 2 pagos'],
	['service' => 'Google Ads', 'price_label' => '$120.000 al mes'],
	['service' => 'Soporte', 'price_label' => 'Incluido'],
];
ok(at_cc_total_unico($filas) === 650000, 'total único sin mensuales ni incluidos');
ok(at_cc_anticipo($filas) === 325000, 'anticipo 50 %');
ok(at_cc_anticipo([['service' => 'X', 'price_label' => '$2.000.000 en 2 pagos']]) === 1000000, 'anticipo de una fase');
ok(at_cc_anticipo([['service' => 'X', 'price_label' => 'Por confirmar']]) === null, 'anticipo sin montos');
ok(at_cc_anticipo([['service' => 'X', 'price_label' => '$333']]) === 167, 'anticipo redondea');
ok(at_cc_formato_clp(1000000) === '$1.000.000' && at_cc_formato_clp(0) === '$0', 'formato CLP');

// Correo, RUT y teléfono
ok(at_cc_email_normalizado('  Ana@Example.COM ') === 'ana@example.com', 'correo normalizado');
ok(at_cc_rut_valido('78.363.717-0'), 'RUT de AutomatizaTech');
ok(at_cc_rut_valido('11.111.111-1') && at_cc_rut_valido('111111111'), 'RUT con y sin formato');
ok(at_cc_rut_valido('10.000.013-K') && at_cc_rut_valido('10000013-k'), 'RUT con K');
ok(!at_cc_rut_valido('11.111.111-2') && !at_cc_rut_valido('') && !at_cc_rut_valido('K') && !at_cc_rut_valido('abc'), 'RUT inválidos');
ok(at_cc_rut_formato('111111111') === '11.111.111-1' && at_cc_rut_formato('10000013k') === '10.000.013-K', 'formato de RUT');
ok(at_cc_telefono_normalizado('+56 9 1234 5678') === '56912345678', 'teléfono +56 9 con espacios');
ok(at_cc_telefono_normalizado('912345678') === '56912345678', 'teléfono de 9 dígitos');
ok(at_cc_telefono_normalizado('56912345678') === '56912345678', 'teléfono ya normalizado');
ok(at_cc_telefono_normalizado('') === '', 'teléfono vacío');

// Payload y filas
$json = '{"pricing_rows":[{"service":"A","price_label":"$1"}]}';
ok(at_cc_json_de_payload($json)['pricing_rows'][0]['service'] === 'A', 'JSON normal');
ok(at_cc_json_de_payload(addslashes($json))['pricing_rows'][0]['service'] === 'A', 'JSON con barras (propuestas viejas)');
ok(at_cc_json_de_payload("Texto antes\n" . $json . "\nDespués")['pricing_rows'][0]['service'] === 'A', 'JSON dentro de texto');
ok(at_cc_json_de_payload('no es json') === null, 'sin JSON');
ok(at_cc_filas_de_payload($json) === [['service' => 'A', 'price_label' => '$1']], 'filas del payload');
ok(at_cc_filas_de_payload('nada') === [], 'sin filas');
ok(at_cc_filas_aceptadas($filas, ['2', 0, 0, 9, 'x']) === [$filas[0], $filas[2]], 'filas aceptadas en orden, sin repetir ni inválidas');
ok(at_cc_filas_aceptadas($filas, []) === [], 'ninguna fila');
ok(at_cc_filas_aceptadas([['service' => '', 'price_label' => '$1']], [0]) === [], 'fila sin servicio no cuenta');
ok(at_cc_huella('abc') === hash('sha256', 'abc'), 'huella sha256');

// Fechas y canales
ok(at_cc_fecha_larga('2026-09-25 14:00:00') === '25 de septiembre de 2026' && at_cc_fecha_larga('2026-01-05') === '5 de enero de 2026', 'fecha larga');
ok(at_cc_fecha_larga('basura') === '' && at_cc_fecha_larga('2026-02-30') === '', 'fecha larga inválida');
ok(at_cc_fecha_declarada('2026-09-20', '2026-09-25') === '2026-09-20 12:00:00', 'fecha declarada anterior');
ok(at_cc_fecha_declarada('2026-09-26', '2026-09-25') === '' && at_cc_fecha_declarada('20-09-2026', '2026-09-25') === '', 'fecha futura o mal escrita');
ok(array_keys(at_cc_canales_manuales()) === ['whatsapp', 'correo', 'llamada', 'reunion', 'otro'], 'canales manuales');
ok(at_cc_canal_texto(['canal' => 'pagina']) === 'en la página de la propuesta', 'canal página');
ok(at_cc_canal_texto(['canal' => 'whatsapp']) === 'con un botón en WhatsApp', 'canal botón de WhatsApp');
ok(at_cc_canal_texto(['canal' => 'manual', 'canal_manual' => 'whatsapp']) === 'por WhatsApp, registrada por AutomatizaTech', 'canal manual');

// URL, bloque del correo y WhatsApp
ok(at_cc_url_respuesta('https://ejemplo.cl/', 'aB3 x', 'aceptar') === 'https://ejemplo.cl/ver-presentacion.php?id=aB3%20x&responder=aceptar', 'URL de respuesta');
ok(at_cc_url_respuesta('https://ejemplo.cl', 'abc') === 'https://ejemplo.cl/ver-presentacion.php?id=abc', 'URL sin responder');
$bl = at_cc_bloque_aceptar_html('https://e.cl/?a=1&b=2', 'https://e.cl/ev');
ok(strpos($bl, 'Aceptar la propuesta') !== false && strpos($bl, 'href="https://e.cl/?a=1&amp;b=2"') !== false && strpos($bl, 'https://e.cl/ev') !== false, 'bloque de aceptar con enlaces escapados');
$t = at_cc_texto_whatsapp('Ana', 'Muebles', 'https://e.cl/x');
ok(strpos($t, 'Hola Ana') === 0 && strpos($t, 'para Muebles') !== false && strpos($t, 'https://e.cl/x') !== false, 'texto de WhatsApp');
ok(at_cc_url_wa_me('+56 9 1234 5678', 'a b') === 'https://wa.me/56912345678?text=a%20b', 'enlace wa.me');
ok(at_cc_url_wa_me('', 'x') === '', 'sin teléfono no hay enlace');

// Marcadores del contrato de servicios
$m = at_cc_marcadores_servicios([
	'empresa' => 'Muebles', 'representante' => 'Ana', 'rut_representante' => '11.111.111-1',
	'email' => 'ana@example.com', 'telefono' => '+56 9 1111 1111', 'codigo_propuesta' => 'abc123',
	'fecha_propuesta' => '24 de septiembre de 2026', 'fecha_aceptacion' => '25 de septiembre de 2026',
	'canal_aceptacion' => 'en la página de la propuesta', 'filas_aceptadas' => [$filas[0], $filas[2]],
	'filas_siguientes' => [$filas[1]], 'alcance' => 'Sitio', 'entregables' => '- Sitio', 'plazo' => 'Ocho semanas',
]);
ok($m['monto_total'] === '$250.000', 'monto total sin mensuales');
ok(strpos($m['forma_pago'], '$125.000') !== false && strpos($m['forma_pago'], 'Google Ads') !== false, 'forma de pago con anticipo y mensual');
ok($m['servicios_contratados'] === "- **Fase 1**: \$250.000 en 2 pagos\n- **Google Ads**: \$120.000 al mes", 'servicios en lista');
ok($m['fases_siguientes'] === '- **Fase 2**: $400.000 en 2 pagos', 'fases siguientes');
ok(!isset($m['rut_cliente']) && !isset($m['domicilio_cliente']), 'vacíos no se incluyen (quedan en blanco para llenar)');
ok(array_diff(array_keys($m), at_cc_claves_contrato_servicios()) === [], 'solo claves conocidas');
$m2 = at_cc_marcadores_servicios(['representante' => 'Ana', 'filas_aceptadas' => [['service' => 'X', 'price_label' => 'Por confirmar']]]);
ok(!isset($m2['monto_total']) && $m2['razon_social_cliente'] === 'Ana' && $m2['fases_siguientes'] === 'La propuesta no tiene fases siguientes.', 'sin montos ni empresa');

// Mensajes de la página
ok(at_cc_mensaje_respuesta('aceptada')['tipo'] === 'ok' && at_cc_mensaje_respuesta('datos')['tipo'] === 'aviso' && at_cc_mensaje_respuesta('error')['tipo'] === 'error', 'mensajes');
ok(at_cc_mensaje_respuesta('inventado') === null, 'mensaje desconocido');

// Evidencia
$tmp = sys_get_temp_dir() . '/at-cc-prueba-' . getmypid();
file_put_contents($tmp . '.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
file_put_contents($tmp . '.pdf.png', "%PDF-1.4\n%falso\n");
ok(at_cc_error_evidencia(['error' => UPLOAD_ERR_OK, 'size' => 70, 'tmp_name' => $tmp . '.png']) === '', 'PNG válido');
ok(at_cc_error_evidencia(['error' => UPLOAD_ERR_OK, 'size' => 20, 'tmp_name' => $tmp . '.pdf.png']) !== '', 'PDF renombrado a .png se rechaza');
ok(at_cc_error_evidencia(['error' => UPLOAD_ERR_OK, 'size' => 6 * 1024 * 1024, 'tmp_name' => $tmp . '.png']) !== '', 'más de 5 MB se rechaza');
ok(at_cc_error_evidencia(['error' => UPLOAD_ERR_INI_SIZE, 'size' => 0, 'tmp_name' => '']) !== '', 'error de subida se rechaza');
@unlink($tmp . '.png');
@unlink($tmp . '.pdf.png');
$multi = ['name' => ['a.png', '', 'b.jpg'], 'type' => ['image/png', '', 'image/jpeg'], 'tmp_name' => ['/t/a', '', '/t/b'], 'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK], 'size' => [10, 0, 20]];
$n = at_cc_archivos_normalizados($multi);
ok(count($n) === 2 && $n[0]['name'] === 'a.png' && $n[1]['tmp_name'] === '/t/b', 'archivos múltiples normalizados sin los vacíos');
ok(at_cc_archivos_normalizados([]) === [], 'sin archivos');
ok(at_cc_mimes_evidencia() === ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 'tipos aceptados');

// Correos
$banco = ['banco' => 'Banco de Prueba', 'tipo' => 'Cuenta corriente', 'numero' => '123', 'titular' => 'AutomatizaTech SpA', 'rut' => '78.363.717-0'];
ok(at_cc_banco_completo($banco) && !at_cc_banco_completo(['banco' => 'X']), 'banco completo');
$b = at_cc_bienvenida_html(['nombre' => 'Ana <b>', 'empresa' => 'Muebles', 'anticipo' => 1000000, 'banco' => $banco, 'correo_pago' => 'pagos@example.com', 'whatsapp' => '+56 9 2700 2984', 'url_portal' => 'https://example.com/portal?a=1&b=2', 'logo' => '', 'con_propuesta' => true]);
ok(strpos($b, '$1.000.000') !== false && strpos($b, 'Banco de Prueba') !== false, 'bienvenida con anticipo y banco');
ok(strpos($b, 'Ana &lt;b&gt;') !== false, 'nombre escapado');
ok(strpos($b, 'https://wa.me/56927002984') !== false, 'enlace al WhatsApp de AT');
ok(strpos($b, 'reunión de inicio') !== false && strpos($b, 'bienvenida oficial') !== false, 'anuncia la reunión de inicio');
ok(strpos($b, 'https://example.com/portal?a=1&amp;b=2') !== false, 'portal escapado');
$b2 = at_cc_bienvenida_html(['nombre' => 'Ana', 'empresa' => '', 'anticipo' => null, 'banco' => [], 'con_propuesta' => true]);
ok(strpos($b2, 'por separado') !== false && strpos($b2, 'va en tu contrato') !== false, 'sin banco ni anticipo');
$b3 = at_cc_bienvenida_html(['nombre' => 'Ana', 'con_propuesta' => false]);
ok(strpos($b3, 'anticipo') === false && strpos($b3, 'Tu contrato') === false, 'sin propuesta no habla de contrato ni anticipo');
$pd = at_cc_pedido_respuesta_html('Ana', 'Muebles', '<div>BLOQUE</div>', '', 'https://e.cl/v');
ok(strpos($pd, 'Hola <strong>Ana</strong>') !== false && strpos($pd, '<div>BLOQUE</div>') !== false && strpos($pd, 'https://e.cl/v') !== false, 'correo para pedir respuesta');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
```

- [ ] **Step 2: Correr la prueba y ver que falla.**
  Run: `"$PHP" tests/cierre/puras-test.php`
  Expected: error fatal porque `puras.php` no existe.

- [ ] **Step 3: Escribir `puras.php`:**

```php
<?php
/**
 * Cierre de cliente: reglas sin WordPress. Pruebas: php tests/cierre/puras-test.php
 * Spec: Docs/superpowers/specs/2026-09-25-cierre-de-cliente-design.md
 */

/** Salida que elige el cliente => estado en que queda la propuesta. */
function at_cc_salidas(): array {
	return ['acepta' => 'aceptada', 'evalua' => 'evaluando', 'rechaza' => 'rechazada'];
}

/** Transiciones de una respuesta. A mano (Luis) también se acepta una propuesta en pending o lista. */
function at_cc_transicion_respuesta_valida(string $desde, string $hacia, bool $manual = false): bool {
	$permitidas = [
		'sent'      => ['evaluando', 'rechazada', 'aceptada'],
		'evaluando' => ['rechazada', 'aceptada'],
		'rechazada' => ['aceptada'],
	];
	if ($manual && $hacia === 'aceptada' && in_array($desde, ['pending', 'lista'], true)) {
		return true;
	}
	return in_array($hacia, $permitidas[$desde] ?? [], true);
}

/** Estados en que tiene sentido pedirle la respuesta al cliente. */
function at_cc_puede_pedir_respuesta(string $status): bool {
	return in_array($status, ['sent', 'evaluando', 'rechazada'], true);
}

/** Primer monto en pesos de una etiqueta ('$2.000.000 en 2 pagos' => 2000000); null si no hay. */
function at_cc_monto_de_etiqueta(string $etiqueta): ?int {
	if (!preg_match('/\$\s*(\d{1,3}(?:\.\d{3})+|\d+)/', $etiqueta, $m)) {
		return null;
	}
	return (int) str_replace('.', '', $m[1]);
}

/** La etiqueta describe un cobro mensual. */
function at_cc_es_mensual(string $etiqueta): bool {
	return (bool) preg_match('/al\s+mes|mensual|por\s+mes|\/\s*mes/iu', $etiqueta);
}

/** Suma de los montos únicos (no mensuales) que se pueden leer; null si ninguno se puede leer. */
function at_cc_total_unico(array $filas): ?int {
	$total = 0;
	$hay = false;
	foreach ($filas as $f) {
		$e = (string) ($f['price_label'] ?? '');
		if (at_cc_es_mensual($e)) {
			continue;
		}
		$n = at_cc_monto_de_etiqueta($e);
		if ($n !== null) {
			$total += $n;
			$hay = true;
		}
	}
	return $hay ? $total : null;
}

/** Anticipo: 50 % de lo único aceptado, en pesos enteros; null si no se puede calcular. */
function at_cc_anticipo(array $filas): ?int {
	$t = at_cc_total_unico($filas);
	return $t === null ? null : (int) round($t / 2);
}

function at_cc_formato_clp(int $n): string {
	return '$' . number_format($n, 0, ',', '.');
}

function at_cc_email_normalizado(string $e): string {
	return strtolower(trim($e));
}

function at_cc_rut_limpio(string $rut): string {
	return strtoupper((string) preg_replace('/[^0-9kK]/', '', $rut));
}

/** RUT chileno con dígito verificador correcto. */
function at_cc_rut_valido(string $rut): bool {
	$r = at_cc_rut_limpio($rut);
	if (strlen($r) < 2) {
		return false;
	}
	$cuerpo = substr($r, 0, -1);
	$dv = substr($r, -1);
	if (!ctype_digit($cuerpo)) {
		return false;
	}
	$suma = 0;
	$mult = 2;
	for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
		$suma += (int) $cuerpo[$i] * $mult;
		$mult = $mult === 7 ? 2 : $mult + 1;
	}
	$resto = 11 - ($suma % 11);
	$esperado = $resto === 11 ? '0' : ($resto === 10 ? 'K' : (string) $resto);
	return $dv === $esperado;
}

function at_cc_rut_formato(string $rut): string {
	$r = at_cc_rut_limpio($rut);
	if (strlen($r) < 2) {
		return '';
	}
	return number_format((int) substr($r, 0, -1), 0, ',', '.') . '-' . substr($r, -1);
}

/** Teléfono chileno en dígitos con 56 adelante ('+56 9 1234 5678' y '912345678' => '56912345678'). */
function at_cc_telefono_normalizado(string $t): string {
	$d = (string) preg_replace('/\D/', '', $t);
	if (strlen($d) === 9 && $d[0] === '9') {
		return '56' . $d;
	}
	return $d;
}

/** El contenido de una propuesta como arreglo: JSON, JSON con barras (propuestas viejas) o JSON dentro de texto. */
function at_cc_json_de_payload(string $s): ?array {
	foreach ([$s, stripslashes($s)] as $intento) {
		$d = json_decode($intento, true);
		if (is_array($d)) {
			return $d;
		}
	}
	if (preg_match('/\{.*\}/s', $s, $m)) {
		foreach ([$m[0], stripslashes($m[0])] as $intento) {
			$d = json_decode($intento, true);
			if (is_array($d)) {
				return $d;
			}
		}
	}
	return null;
}

/** Filas de precio del contenido de una propuesta; [] si no hay. */
function at_cc_filas_de_payload(string $s): array {
	$d = at_cc_json_de_payload($s);
	if (!$d || !is_array($d['pricing_rows'] ?? null)) {
		return [];
	}
	return array_values(array_filter($d['pricing_rows'], 'is_array'));
}

/** Filas elegidas por su índice, en el orden de la propuesta, sin repetir ni aceptar filas sin servicio. */
function at_cc_filas_aceptadas(array $filas, array $indices): array {
	$r = [];
	foreach ($indices as $i) {
		if (!is_numeric($i)) {
			continue;
		}
		$i = (int) $i;
		if (!isset($filas[$i]) || !is_array($filas[$i]) || trim((string) ($filas[$i]['service'] ?? '')) === '') {
			continue;
		}
		$r[$i] = $filas[$i];
	}
	ksort($r);
	return array_values($r);
}

function at_cc_huella(string $contenido): string {
	return hash('sha256', $contenido);
}

/** '2026-09-25 ...' => '25 de septiembre de 2026'; '' si no es una fecha real. */
function at_cc_fecha_larga(string $fecha): string {
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $fecha, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
		return '';
	}
	$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
	return (int) $m[3] . ' de ' . $meses[(int) $m[2] - 1] . ' de ' . $m[1];
}

/** Fecha que declara Luis ('AAAA-MM-DD', no futura) a 'AAAA-MM-DD 12:00:00'; '' si no sirve. */
function at_cc_fecha_declarada(string $ymd, string $hoy): string {
	$ymd = trim($ymd);
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || $ymd > $hoy) {
		return '';
	}
	return $ymd . ' 12:00:00';
}

/** Por dónde aceptó el cliente cuando Luis lo registra a mano. */
function at_cc_canales_manuales(): array {
	return ['whatsapp' => 'WhatsApp', 'correo' => 'Correo', 'llamada' => 'Llamada', 'reunion' => 'Reunión', 'otro' => 'Otro medio'];
}

/** Cómo aceptó, en palabras para el contrato y los avisos. */
function at_cc_canal_texto(array $d): string {
	$canal = (string) ($d['canal'] ?? '');
	if ($canal === 'whatsapp') {
		return 'con un botón en WhatsApp';
	}
	if ($canal === 'manual') {
		$frases = ['whatsapp' => 'por WhatsApp', 'correo' => 'por correo', 'llamada' => 'en una llamada', 'reunion' => 'en una reunión', 'otro' => 'por otro medio'];
		return ($frases[(string) ($d['canal_manual'] ?? '')] ?? 'por otro medio') . ', registrada por AutomatizaTech';
	}
	return 'en la página de la propuesta';
}

function at_cc_url_respuesta(string $base, string $codigo, string $responder = ''): string {
	$u = rtrim($base, '/') . '/ver-presentacion.php?id=' . rawurlencode($codigo);
	return $responder !== '' ? $u . '&responder=' . rawurlencode($responder) : $u;
}

/** Bloque del correo que pide aceptar la propuesta. El botón abre la página: no acepta por sí solo. */
function at_cc_bloque_aceptar_html(string $url_aceptar, string $url_evaluar): string {
	$a = htmlspecialchars($url_aceptar, ENT_QUOTES, 'UTF-8');
	$e = htmlspecialchars($url_evaluar, ENT_QUOTES, 'UTF-8');
	return '<div style="background:#ecfdf5;border:1px solid #10b981;border-radius:8px;padding:20px;margin:24px 0;text-align:center">'
		. '<p style="margin:0 0 14px 0;color:#065f46;font-size:15px">Si estás de acuerdo con la propuesta, acéptala aquí. Con tu aceptación te enviamos el contrato y los primeros pasos para partir.</p>'
		. '<a href="' . $a . '" style="display:inline-block;background:#059669;color:#ffffff;text-decoration:none;font-weight:bold;padding:14px 32px;border-radius:30px;font-size:16px">✅ Aceptar la propuesta</a>'
		. '<p style="margin:14px 0 0 0;font-size:13px"><a href="' . $e . '" style="color:#047857">¿Tienes dudas o necesitas más tiempo? Cuéntanos aquí</a></p>'
		. '</div>';
}

/** Mensaje que Luis manda desde su WhatsApp con el enlace para aceptar. */
function at_cc_texto_whatsapp(string $nombre, string $empresa, string $url): string {
	$saludo = trim($nombre) !== '' ? 'Hola ' . trim($nombre) : 'Hola';
	$para = trim($empresa) !== '' ? ' para ' . trim($empresa) : '';
	return $saludo . ', te escribe Luis de AutomatizaTech. Te enviamos la propuesta' . $para . '. Puedes revisarla y, si estás de acuerdo, aceptarla aquí: ' . $url
		. "\nSi tienes dudas o necesitas más tiempo, respóndeme por aquí.";
}

function at_cc_url_wa_me(string $telefono, string $texto): string {
	$n = at_cc_telefono_normalizado($telefono);
	return $n === '' ? '' : 'https://wa.me/' . $n . '?text=' . rawurlencode($texto);
}

/** Filas de precio como lista del contrato: '- **Servicio**: etiqueta'. */
function at_cc_servicios_markdown(array $filas): string {
	$l = [];
	foreach ($filas as $f) {
		$s = trim((string) ($f['service'] ?? ''));
		if ($s === '') {
			continue;
		}
		$p = trim((string) ($f['price_label'] ?? ''));
		$l[] = '- **' . str_replace('*', '', $s) . '**' . ($p !== '' ? ': ' . $p : '');
	}
	return implode("\n", $l);
}

/** Marcadores que puede llenar el contrato de servicios con los datos de la propuesta. */
function at_cc_claves_contrato_servicios(): array {
	return [
		'razon_social_cliente', 'rut_cliente', 'representante_cliente_nombre', 'representante_cliente_rut',
		'email_cliente', 'telefono_cliente', 'domicilio_cliente', 'propuesta_codigo', 'fecha_propuesta',
		'fecha_aceptacion', 'canal_aceptacion', 'nombre_proyecto', 'servicios_contratados', 'alcance',
		'entregables', 'plazo', 'monto_total', 'forma_pago', 'fases_siguientes', 'garantia_meses_servicio',
	];
}

/**
 * Marcadores del contrato de servicios. Los que quedan vacíos no se incluyen: el PDF los muestra
 * como '_______' para llenarlos al revisar.
 */
function at_cc_marcadores_servicios(array $d): array {
	$filas = (array) ($d['filas_aceptadas'] ?? []);
	$total = at_cc_total_unico($filas);
	$mensuales = [];
	foreach ($filas as $f) {
		if (at_cc_es_mensual((string) ($f['price_label'] ?? ''))) {
			$mensuales[] = trim((string) ($f['service'] ?? '')) . ': ' . trim((string) ($f['price_label'] ?? ''));
		}
	}
	if ($total !== null) {
		$primero = (int) round($total / 2);
		$forma = '50 % al firmar este contrato (' . at_cc_formato_clp($primero) . ') y 50 % a la entrega (' . at_cc_formato_clp($total - $primero) . ').';
	} else {
		$forma = 'Según lo indicado para cada servicio contratado.';
	}
	if ($mensuales) {
		$forma .= ' Los servicios mensuales (' . implode('; ', $mensuales) . ') se pagan mes a mes desde que parten.';
	}
	$empresa = trim((string) ($d['empresa'] ?? ''));
	$repr = trim((string) ($d['representante'] ?? ''));
	$siguientes = at_cc_servicios_markdown((array) ($d['filas_siguientes'] ?? []));
	$ph = [
		'razon_social_cliente'         => $empresa !== '' ? $empresa : $repr,
		'rut_cliente'                  => (string) ($d['rut_cliente'] ?? ''),
		'representante_cliente_nombre' => $repr,
		'representante_cliente_rut'    => (string) ($d['rut_representante'] ?? ''),
		'email_cliente'                => (string) ($d['email'] ?? ''),
		'telefono_cliente'             => (string) ($d['telefono'] ?? ''),
		'domicilio_cliente'            => (string) ($d['domicilio'] ?? ''),
		'propuesta_codigo'             => (string) ($d['codigo_propuesta'] ?? ''),
		'fecha_propuesta'              => (string) ($d['fecha_propuesta'] ?? ''),
		'fecha_aceptacion'             => (string) ($d['fecha_aceptacion'] ?? ''),
		'canal_aceptacion'             => (string) ($d['canal_aceptacion'] ?? ''),
		'nombre_proyecto'              => $empresa !== '' ? $empresa : $repr,
		'servicios_contratados'        => at_cc_servicios_markdown($filas),
		'alcance'                      => (string) ($d['alcance'] ?? ''),
		'entregables'                  => (string) ($d['entregables'] ?? ''),
		'plazo'                        => (string) ($d['plazo'] ?? ''),
		'monto_total'                  => $total !== null ? at_cc_formato_clp($total) : '',
		'forma_pago'                   => $forma,
		'fases_siguientes'             => $siguientes !== '' ? $siguientes : 'La propuesta no tiene fases siguientes.',
		'garantia_meses_servicio'      => '3',
	];
	$r = [];
	foreach ($ph as $k => $v) {
		$v = trim($v);
		if ($v !== '') {
			$r[$k] = $v;
		}
	}
	return $r;
}

/** Mensaje que ve el cliente después de responder en la página. */
function at_cc_mensaje_respuesta(string $clave): ?array {
	$m = [
		'aceptada'  => ['ok', '¡Gracias! Recibimos tu aceptación. Te enviamos por correo los primeros pasos, y el contrato llega en un correo aparte para firmarlo.'],
		'evaluando' => ['ok', 'Gracias por contarnos. Te escribimos pronto para resolver tus dudas.'],
		'rechazada' => ['ok', 'Gracias por tu respuesta. Si algo cambia, aquí estamos.'],
		'recibida'  => ['ok', 'Recibimos tu respuesta. Gracias.'],
		'datos'     => ['aviso', 'Revisa tu nombre y tu RUT, marca lo que aceptas y la casilla «Acepto la propuesta».'],
		'vencida'   => ['aviso', 'La página estuvo abierta mucho rato. Recárgala e inténtalo de nuevo.'],
		'limite'    => ['aviso', 'Recibimos muchos intentos desde tu conexión. Espera un rato y vuelve a intentarlo.'],
		'error'     => ['error', 'No pudimos registrar tu respuesta. Escríbenos por WhatsApp y lo vemos.'],
	];
	return isset($m[$clave]) ? ['tipo' => $m[$clave][0], 'texto' => $m[$clave][1]] : null;
}

function at_cc_mimes_evidencia(): array {
	return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
}

/** '' si el archivo subido es una imagen aceptada de hasta 5 MB; si no, el motivo. El tipo se mira en el contenido. */
function at_cc_error_evidencia(array $f, int $max = 5242880): string {
	if ((int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
		return 'no se pudo subir';
	}
	$tam = (int) ($f['size'] ?? 0);
	if ($tam <= 0 || $tam > $max) {
		return 'pesa más de 5 MB';
	}
	$info = @getimagesize((string) ($f['tmp_name'] ?? ''));
	if (!$info || !isset(at_cc_mimes_evidencia()[$info['mime'] ?? ''])) {
		return 'no es una imagen JPG, PNG o WEBP';
	}
	return '';
}

/** Un campo de $_FILES (simple o múltiple) como lista de archivos, sin los vacíos. */
function at_cc_archivos_normalizados(array $campo): array {
	if (!isset($campo['name'])) {
		return [];
	}
	if (!is_array($campo['name'])) {
		return (int) ($campo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$campo];
	}
	$r = [];
	foreach ($campo['name'] as $i => $nombre) {
		$error = (int) ($campo['error'][$i] ?? UPLOAD_ERR_NO_FILE);
		if ($error === UPLOAD_ERR_NO_FILE) {
			continue;
		}
		$r[] = [
			'name'     => (string) $nombre,
			'type'     => (string) ($campo['type'][$i] ?? ''),
			'tmp_name' => (string) ($campo['tmp_name'][$i] ?? ''),
			'error'    => $error,
			'size'     => (int) ($campo['size'][$i] ?? 0),
		];
	}
	return $r;
}

function at_cc_banco_completo(array $b): bool {
	foreach (['banco', 'tipo', 'numero', 'titular', 'rut'] as $k) {
		if (trim((string) ($b[$k] ?? '')) === '') {
			return false;
		}
	}
	return true;
}

/**
 * Correo de bienvenida con la lista de arranque.
 * $v: nombre, empresa, anticipo (?int), banco (array), correo_pago, whatsapp, url_portal, logo, con_propuesta (bool).
 */
function at_cc_bienvenida_html(array $v): string {
	$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
	$nombre = trim((string) ($v['nombre'] ?? ''));
	$empresa = trim((string) ($v['empresa'] ?? ''));
	$pasos = [];
	if (!empty($v['con_propuesta'])) {
		$pasos[] = ['Tu contrato', 'Te llega en un correo aparte para que lo firmes en línea. Nosotros lo revisamos y lo firmamos primero.'];
		$anticipo = $v['anticipo'] ?? null;
		$pago = $anticipo !== null
			? 'El primer pago es de <strong>' . $h(at_cc_formato_clp((int) $anticipo)) . '</strong>, el 50 % de lo que aceptaste.'
			: 'El monto del primer pago va en tu contrato.';
		$banco = (array) ($v['banco'] ?? []);
		if (at_cc_banco_completo($banco)) {
			$pago .= '<br>Transferencia a <strong>' . $h($banco['banco']) . '</strong>, ' . $h($banco['tipo']) . ' N° <strong>' . $h($banco['numero']) . '</strong>, a nombre de ' . $h($banco['titular']) . ', RUT ' . $h($banco['rut']) . '.';
		} else {
			$pago .= '<br>Te enviamos los datos para la transferencia por separado.';
		}
		$correo_pago = trim((string) ($v['correo_pago'] ?? ''));
		$pago .= $correo_pago !== ''
			? '<br>Cuando transfieras, avísanos a ' . $h($correo_pago) . ' y te enviamos la boleta o factura.'
			: '<br>Cuando transfieras, avísanos respondiendo este correo y te enviamos la boleta o factura.';
		$pasos[] = ['El anticipo', $pago];
	}
	$wa = at_cc_telefono_normalizado((string) ($v['whatsapp'] ?? ''));
	$pasos[] = ['Tu marca y tus accesos', 'Responde este correo con tu logo, tus colores, los textos de tu empresa y los accesos que tengas (dominio, hosting, redes sociales)'
		. ($wa !== '' ? ', o envíanoslos por <a href="https://wa.me/' . $h($wa) . '" style="color:#1e40af">WhatsApp</a>' : '') . '.'];
	$pasos[] = ['La reunión de inicio', 'Te vamos a contactar para coordinar la reunión de inicio, donde te damos la bienvenida oficial y repasamos juntos los pasos a seguir y los ítems de trabajo.'];
	$lista = '';
	foreach ($pasos as $i => $p) {
		$lista .= '<tr><td style="vertical-align:top;padding:10px 12px 10px 0;font-weight:bold;color:#1e40af;font-size:18px">' . ($i + 1) . '</td>'
			. '<td style="padding:10px 0"><strong>' . $h($p[0]) . '</strong><br>' . $p[1] . '</td></tr>';
	}
	$portal = trim((string) ($v['url_portal'] ?? ''));
	$logo = trim((string) ($v['logo'] ?? ''));
	return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f4f6fb;margin:0;padding:0;color:#222">'
		. '<div style="max-width:600px;margin:24px auto;background:#ffffff;border-radius:10px;overflow:hidden">'
		. '<div style="background:#1e40af;color:#ffffff;text-align:center;padding:28px 20px">'
		. ($logo !== '' ? '<img src="' . $h($logo) . '" alt="AutomatizaTech" style="max-height:70px"><br>' : '')
		. '<h1 style="margin:12px 0 0;font-size:22px">¡Te damos la bienvenida a AutomatizaTech!</h1></div>'
		. '<div style="padding:28px 26px;line-height:1.6">'
		. '<p>Hola <strong>' . $h($nombre !== '' ? $nombre : 'cliente') . '</strong>,</p>'
		. '<p>Gracias por confiar en nosotros' . ($empresa !== '' ? ' para <strong>' . $h($empresa) . '</strong>' : '') . '. Estos son tus primeros pasos:</p>'
		. '<table role="presentation" style="width:100%;border-collapse:collapse">' . $lista . '</table>'
		. ($portal !== '' ? '<p style="text-align:center;margin:26px 0"><a href="' . $h($portal) . '" style="background:#059669;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:24px;font-weight:bold">Ver mi portal</a></p>'
			. '<p style="font-size:13px;color:#555">En tu portal vas a ver el avance de tu proyecto y la historia de lo que hemos hecho juntos.</p>' : '')
		. '<p>Cualquier duda, responde este correo.</p><p>Un abrazo,<br><strong>El equipo de AutomatizaTech</strong></p></div>'
		. '<div style="background:#f1f1f1;color:#777;text-align:center;font-size:12px;padding:14px">© ' . date('Y') . ' AutomatizaTech · automatizatech.cl</div>'
		. '</div></body></html>';
}

/** Correo corto que pide la respuesta, con el bloque de aceptar. */
function at_cc_pedido_respuesta_html(string $nombre, string $empresa, string $bloque, string $logo, string $url_ver): string {
	$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
	return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f4f6fb;margin:0;padding:0;color:#222">'
		. '<div style="max-width:600px;margin:24px auto;background:#ffffff;border-radius:10px;overflow:hidden">'
		. '<div style="background:#1e40af;text-align:center;padding:24px 20px">' . ($logo !== '' ? '<img src="' . $h($logo) . '" alt="AutomatizaTech" style="max-height:64px">' : '') . '</div>'
		. '<div style="padding:26px;line-height:1.6">'
		. '<p>Hola <strong>' . $h(trim($nombre) !== '' ? trim($nombre) : 'cliente') . '</strong>,</p>'
		. '<p>Te escribimos por la propuesta que te enviamos' . (trim($empresa) !== '' ? ' para <strong>' . $h(trim($empresa)) . '</strong>' : '')
		. '. Puedes volver a verla <a href="' . $h($url_ver) . '" style="color:#1e40af">aquí</a>.</p>'
		. $bloque
		. '<p>Un abrazo,<br><strong>El equipo de AutomatizaTech</strong></p></div>'
		. '<div style="background:#f1f1f1;color:#777;text-align:center;font-size:12px;padding:14px">© ' . date('Y') . ' AutomatizaTech · automatizatech.cl</div>'
		. '</div></body></html>';
}
```

- [ ] **Step 4: Correr la prueba.**
  Run: `"$PHP" tests/cierre/puras-test.php && "$PHP" -l wp-content/themes/automatiza-tech/inc/cierre-cliente/puras.php`
  Expected: `TODO OK` y `No syntax errors detected`. Si una aserción falla, se corrige el código, no la prueba; solo se corrige la prueba si contradice la spec, y se explica en el reporte.

- [ ] **Step 5: Commit.**

```bash
git add wp-content/themes/automatiza-tech/inc/cierre-cliente/puras.php tests/cierre/puras-test.php
git commit -m "feat(cierre): reglas puras del cierre de cliente con pruebas" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

### Task 2: Estados nuevos en la lista y la ficha de propuestas

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/propuestas-admin/consultas.php` (funciones `at_pa_grupos_estado()` y `at_pa_estado_etiqueta()`)
- Modify: `wp-content/themes/automatiza-tech/assets/css/propuestas-admin.css`
- Test: `tests/propuestas/admin-lista-test.php`

**Interfaces:**
- Produces: grupos `evaluando`, `aceptadas`, `rechazadas` y etiquetas `En evaluación`, `Aceptada`, `Rechazada`, `Contratada` (`contracted`, escrito por Contactos). La tarea 8 usa `at_pa_estado_etiqueta()` para mostrar el estado.

- [ ] **Step 1: Agregar las aserciones** en `tests/propuestas/admin-lista-test.php`, justo después de la línea `ok(at_pa_estado_etiqueta('')['etiqueta'] === 'Sin estado', 'vacío');` (herramienta Edit, el archivo es CRLF):

```php
ok(at_pa_grupo_de_estado('evaluando') === 'evaluando' && at_pa_grupo_de_estado('aceptada') === 'aceptadas' && at_pa_grupo_de_estado('rechazada') === 'rechazadas', 'grupos de respuesta del cliente');
ok(at_pa_grupo_de_estado('contracted') === 'aceptadas', 'contracted (Contactos) cae en Aceptadas');
ok(at_pa_estado_etiqueta('aceptada') === ['etiqueta' => 'Aceptada', 'clase' => 'at-estado--aceptadas'], 'etiqueta de aceptada');
ok(at_pa_estado_etiqueta('evaluando')['etiqueta'] === 'En evaluación' && at_pa_estado_etiqueta('rechazada')['etiqueta'] === 'Rechazada' && at_pa_estado_etiqueta('contracted')['etiqueta'] === 'Contratada', 'etiquetas nuevas');
ok(array_keys(at_pa_grupos_estado()) === ['borrador', 'ajustando', 'generando', 'lista', 'enviadas', 'evaluando', 'aceptadas', 'rechazadas', 'pendiente', 'error'], 'orden de los grupos');
```

- [ ] **Step 2: Correr y ver que fallan las 5 nuevas.**
  Run: `"$PHP" tests/propuestas/admin-lista-test.php`
  Expected: 5 líneas `FALLA` (las nuevas) y ninguna otra.

- [ ] **Step 3: Cambiar `consultas.php`** (Edit). En `at_pa_grupos_estado()`, reemplazar la línea
  `'enviadas'  => ['etiqueta' => 'Enviadas', 'estados' => ['sent']],`
  por:

```php
		'enviadas'  => ['etiqueta' => 'Enviadas', 'estados' => ['sent']],
		'evaluando' => ['etiqueta' => 'En evaluación', 'estados' => ['evaluando']],
		'aceptadas' => ['etiqueta' => 'Aceptadas', 'estados' => ['aceptada', 'contracted']],
		'rechazadas' => ['etiqueta' => 'Rechazadas', 'estados' => ['rechazada']],
```

  En `at_pa_estado_etiqueta()`, reemplazar la línea
  `'lista' => 'Lista para enviar', 'sent' => 'Enviada', 'pending' => 'Pendiente', 'error' => 'Error',`
  por:

```php
		'lista' => 'Lista para enviar', 'sent' => 'Enviada', 'pending' => 'Pendiente', 'error' => 'Error',
		'evaluando' => 'En evaluación', 'aceptada' => 'Aceptada', 'rechazada' => 'Rechazada', 'contracted' => 'Contratada',
```

- [ ] **Step 4: Colores.** En `assets/css/propuestas-admin.css`, después de la línea `.at-estado--error { background: #fee2e2; color: #991b1b; }`, agregar:

```css
.at-estado--evaluando { background: #ede9fe; color: #5b21b6; }
.at-estado--aceptadas { background: #d1fae5; color: #065f46; }
.at-estado--rechazadas { background: #f3f4f6; color: #6b7280; }
```

- [ ] **Step 5: Correr las pruebas y revisar fines de línea.**
  Run:
  ```bash
  "$PHP" tests/propuestas/admin-lista-test.php && "$PHP" tests/propuestas/flow-test.php && "$PHP" -l wp-content/themes/automatiza-tech/inc/propuestas-admin/consultas.php
  for f in wp-content/themes/automatiza-tech/inc/propuestas-admin/consultas.php wp-content/themes/automatiza-tech/assets/css/propuestas-admin.css tests/propuestas/admin-lista-test.php; do echo "$f $(wc -l < $f) $(grep -c $'\r' $f)"; done
  ```
  Expected: `TODO OK` en las dos pruebas, sintaxis OK, y en cada archivo el número de líneas igual al de CR.

- [ ] **Step 6: Commit.**

```bash
git add wp-content/themes/automatiza-tech/inc/propuestas-admin/consultas.php wp-content/themes/automatiza-tech/assets/css/propuestas-admin.css tests/propuestas/admin-lista-test.php
git commit -m "feat(propuestas): estados en evaluación, aceptada y rechazada en la lista y la ficha" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

### Task 3: Módulo, esquema y ficha única (`cargar.php`, `clientes.php`)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/cierre-cliente/cargar.php`
- Create: `wp-content/themes/automatiza-tech/inc/cierre-cliente/clientes.php`
- Modify: `wp-content/themes/automatiza-tech/inc/admin-proposals.php`
- Test: `tests/cierre/wp-bootstrap.php`, `tests/cierre/clientes-wp-test.php`

**Interfaces:**
- Consumes: `at_cc_email_normalizado()` (Task 1).
- Produces:
  - `at_cc_migrar_esquema(): void` (en `admin_init` y al inicio de `at_cc_asegurar_cliente`)
  - `at_cc_asegurar_cliente(array $d)` → `['crm_id' => int, 'tech_id' => int]` o `WP_Error`. `$d`: `nombre`, `email`, `empresa`, `telefono`, `origen`, `valor` (`?int`), `servicios` (texto), `fecha_contrato` (`'Y-m-d H:i:s'`).
  - `at_cc_tech_de_crm(int $crm_id): ?object` (fila de `wp_automatiza_tech_clients`)
  - `at_cc_crm_de_email(string $email): int` (0 si no hay)
  - Acción `admin_post_at_cc_crear_ficha_operativa` (campo `crm_id`, nonce `at_cc_crear_ficha_operativa_<crm_id>`), la usa la tarea 4.

- [ ] **Step 1: Arranque de pruebas con WordPress** `tests/cierre/wp-bootstrap.php`:

```php
<?php
// Carga el WordPress local de prueba que arma el controlador (Task 0).
// Uso: AT_WP_LOAD=<ruta a wp-load.php> php tests/cierre/<prueba>-wp-test.php
$wp = getenv('AT_WP_LOAD');
if (!$wp || !is_file($wp)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba (la entrega el controlador).\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8089';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
define('WP_USE_THEMES', false);
require $wp;
if (!function_exists('at_cc_asegurar_cliente')) {
	fwrite(STDERR, "El módulo cierre-cliente no está cargado en ese sitio.\n");
	exit(2);
}
$GLOBALS['fallas'] = 0;
function ok($cond, $msg) { if ($cond) { echo "ok   $msg\n"; } else { $GLOBALS['fallas']++; echo "FALLA $msg\n"; } }
function fin() { echo $GLOBALS['fallas'] ? "\n{$GLOBALS['fallas']} FALLAS\n" : "\nTODO OK\n"; exit($GLOBALS['fallas'] ? 1 : 0); }
```

- [ ] **Step 2: Prueba** `tests/cierre/clientes-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/clientes-wp-test.php   (WordPress local de prueba)
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
$crm = $wpdb->prefix . 'crm_clientes';
$tech = $wpdb->prefix . 'automatiza_tech_clients';

at_cc_migrar_esquema();
ok(in_array('crm_cliente_id', $wpdb->get_col("SHOW COLUMNS FROM {$tech}"), true), 'columna crm_cliente_id creada');
ok(get_option('at_cierre_schema') === '1', 'esquema marcado');

$marca = 'prueba-cierre-' . strtolower(wp_generate_password(6, false, false));
$email = $marca . '@example.com';

$r = at_cc_asegurar_cliente(['nombre' => 'Prueba Cierre', 'email' => '  ' . strtoupper($email) . ' ', 'empresa' => '[PRUEBA] Empresa', 'telefono' => '+56 9 1111 1111', 'valor' => 1000000, 'servicios' => 'Fase 1']);
ok(is_array($r) && $r['crm_id'] > 0 && $r['tech_id'] > 0, 'cliente nuevo en las dos listas');
$c = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$crm} WHERE id = %d", $r['crm_id']));
ok($c && $c->tipo === 'cliente' && $c->estado === 'contratado' && $c->email === $email, 'CRM: cliente contratado con el correo normalizado');
$t = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tech} WHERE id = %d", $r['tech_id']));
ok($t && (int) $t->crm_cliente_id === $r['crm_id'] && (float) $t->contract_value === 1000000.0, 'ficha operativa enlazada y con su valor');

$r2 = at_cc_asegurar_cliente(['nombre' => 'Otro', 'email' => $email]);
ok($r2 === $r, 'la segunda llamada devuelve los mismos ids');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$crm} WHERE email = %s", $email)) === 1, 'sin duplicado en el CRM');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tech} WHERE email = %s", $email)) === 1, 'sin duplicado en la ficha operativa');

$email3 = $marca . '-3@example.com';
$wpdb->insert($crm, ['nombre' => 'Prospecto', 'email' => $email3, 'tipo' => 'prospecto', 'estado' => 'nuevo']);
$pid = (int) $wpdb->insert_id;
$r3 = at_cc_asegurar_cliente(['nombre' => 'Prospecto', 'email' => $email3, 'fecha_contrato' => '2026-09-20 10:00:00']);
$c3 = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$crm} WHERE id = %d", $pid));
ok($r3['crm_id'] === $pid && $c3->tipo === 'cliente' && $c3->fecha_contrato === '2026-09-20 10:00:00', 'prospecto existente pasa a cliente con la fecha dada');
at_cc_asegurar_cliente(['email' => $email3, 'fecha_contrato' => '2026-09-25 10:00:00']);
ok($wpdb->get_var($wpdb->prepare("SELECT fecha_contrato FROM {$crm} WHERE id = %d", $pid)) === '2026-09-20 10:00:00', 'la fecha de un cliente existente no se pisa');

$email5 = $marca . '-5@example.com';
$wpdb->insert($tech, ['name' => 'Solo ficha', 'email' => $email5, 'contract_status' => 'active']);
$tid5 = (int) $wpdb->insert_id;
$r5 = at_cc_asegurar_cliente(['nombre' => 'Solo ficha', 'email' => $email5]);
ok($r5['tech_id'] === $tid5 && (int) $wpdb->get_var($wpdb->prepare("SELECT crm_cliente_id FROM {$tech} WHERE id = %d", $tid5)) === $r5['crm_id'], 'ficha operativa existente se enlaza sin duplicar');

ok(is_wp_error(at_cc_asegurar_cliente(['email' => 'no-es-correo'])), 'correo inválido devuelve error');
ok((int) at_cc_tech_de_crm($r['crm_id'])->id === $r['tech_id'], 'at_cc_tech_de_crm encuentra la ficha');
ok(at_cc_tech_de_crm(0) === null, 'sin id no hay ficha');
ok(at_cc_crm_de_email(strtoupper($email)) === $r['crm_id'] && at_cc_crm_de_email('nadie@example.com') === 0, 'at_cc_crm_de_email');

$wpdb->query($wpdb->prepare("DELETE FROM {$tech} WHERE email LIKE %s", $wpdb->esc_like($marca) . '%'));
$wpdb->query($wpdb->prepare("DELETE FROM {$crm} WHERE email LIKE %s", $wpdb->esc_like($marca) . '%'));
fin();
```

- [ ] **Step 3: Correr y ver que falla.**
  Run: `AT_WP_LOAD=<ruta> "$PHP" tests/cierre/clientes-wp-test.php`
  Expected: sale con código 2 y el mensaje «El módulo cierre-cliente no está cargado en ese sitio».

- [ ] **Step 4: `cargar.php`:**

```php
<?php
/**
 * Cierre de cliente: respuesta a la propuesta, ficha única, contrato de servicio y bienvenida.
 * Spec: Docs/superpowers/specs/2026-09-25-cierre-de-cliente-design.md
 * Se carga siempre (panel, página pública, admin-post y REST) desde inc/admin-proposals.php.
 */
if (!defined('ABSPATH')) {
	exit;
}
require_once dirname(__DIR__) . '/proposals-flow.php';
require_once __DIR__ . '/puras.php';
require_once __DIR__ . '/clientes.php';
```

- [ ] **Step 5: `clientes.php`:**

```php
<?php
/**
 * Ficha única de cliente (opción A): el CRM (wp_crm_clientes) es la ficha y la ficha operativa
 * (wp_automatiza_tech_clients: contratos, facturas, accesos) queda enlazada por crm_cliente_id.
 */
if (!defined('ABSPATH')) {
	exit;
}

/** Columna de enlace y enlace inicial por correo. Idempotente. */
function at_cc_migrar_esquema(): void {
	if (get_option('at_cierre_schema') === '1') {
		return;
	}
	global $wpdb;
	$tech = $wpdb->prefix . 'automatiza_tech_clients';
	$crm = $wpdb->prefix . 'crm_clientes';
	$cols = $wpdb->get_col("SHOW COLUMNS FROM {$tech}");
	if (!$cols) {
		return;
	}
	if (!in_array('crm_cliente_id', $cols, true)) {
		$wpdb->query("ALTER TABLE {$tech} ADD COLUMN crm_cliente_id BIGINT(20) UNSIGNED NULL DEFAULT NULL, ADD INDEX idx_crm_cliente (crm_cliente_id)");
	}
	$wpdb->query("UPDATE {$tech} t JOIN {$crm} c ON LOWER(TRIM(c.email)) = LOWER(TRIM(t.email)) SET t.crm_cliente_id = c.id WHERE t.crm_cliente_id IS NULL AND t.email <> ''");
	update_option('at_cierre_schema', '1');
}
add_action('admin_init', 'at_cc_migrar_esquema');

/** Id del CRM por correo; 0 si no hay. */
function at_cc_crm_de_email(string $email): int {
	global $wpdb;
	$email = at_cc_email_normalizado($email);
	if ($email === '') {
		return 0;
	}
	return (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}crm_clientes WHERE LOWER(TRIM(email)) = %s ORDER BY id ASC LIMIT 1", $email));
}

/** Ficha operativa enlazada a un cliente del CRM; null si no hay. */
function at_cc_tech_de_crm(int $crm_id): ?object {
	if ($crm_id <= 0) {
		return null;
	}
	global $wpdb;
	at_cc_migrar_esquema();
	$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id = %d ORDER BY id ASC LIMIT 1", $crm_id));
	return $fila ?: null;
}

/**
 * Garantiza que la persona sea cliente en el CRM y tenga su ficha operativa enlazada.
 * Un cliente que ya era cliente conserva su fecha de contrato.
 */
function at_cc_asegurar_cliente(array $d) {
	global $wpdb;
	at_cc_migrar_esquema();
	$crm = $wpdb->prefix . 'crm_clientes';
	$tech = $wpdb->prefix . 'automatiza_tech_clients';
	$email = at_cc_email_normalizado((string) ($d['email'] ?? ''));
	if ($email === '' || !is_email($email)) {
		return new WP_Error('sin_correo', 'El cliente no tiene un correo válido.');
	}
	$nombre = trim((string) ($d['nombre'] ?? ''));
	$empresa = trim((string) ($d['empresa'] ?? ''));
	$telefono = trim((string) ($d['telefono'] ?? ''));
	$fecha = trim((string) ($d['fecha_contrato'] ?? '')) !== '' ? (string) $d['fecha_contrato'] : current_time('mysql');
	$nombre_ficha = $nombre !== '' ? $nombre : ($empresa !== '' ? $empresa : $email);

	$fila = $wpdb->get_row($wpdb->prepare("SELECT id, tipo, fecha_contrato FROM {$crm} WHERE LOWER(TRIM(email)) = %s ORDER BY id ASC LIMIT 1", $email));
	if ($fila) {
		$crm_id = (int) $fila->id;
		$cambios = [];
		if ($fila->tipo !== 'cliente') {
			$cambios = ['tipo' => 'cliente', 'estado' => 'contratado', 'fecha_contrato' => $fecha];
		} elseif (empty($fila->fecha_contrato)) {
			$cambios = ['fecha_contrato' => $fecha];
		}
		if ($cambios) {
			$wpdb->update($crm, $cambios, ['id' => $crm_id]);
		}
	} else {
		$ok = $wpdb->insert($crm, [
			'nombre'         => $nombre_ficha,
			'email'          => $email,
			'empresa'        => $empresa,
			'telefono'       => $telefono,
			'tipo'           => 'cliente',
			'estado'         => 'contratado',
			'fecha_contrato' => $fecha,
			'fecha_contacto' => $fecha,
			'origen'         => (string) ($d['origen'] ?? 'propuesta_aceptada'),
		]);
		$crm_id = $ok ? (int) $wpdb->insert_id : 0;
		if (!$crm_id) {
			return new WP_Error('crm', 'No se pudo crear el cliente en el CRM: ' . $wpdb->last_error);
		}
	}

	$tech_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tech} WHERE crm_cliente_id = %d ORDER BY id ASC LIMIT 1", $crm_id));
	if (!$tech_id) {
		$tech_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tech} WHERE LOWER(TRIM(email)) = %s ORDER BY id ASC LIMIT 1", $email));
		if ($tech_id) {
			$wpdb->update($tech, ['crm_cliente_id' => $crm_id], ['id' => $tech_id]);
		}
	}
	if (!$tech_id) {
		$nueva = [
			'crm_cliente_id'  => $crm_id,
			'name'            => mb_substr($nombre_ficha, 0, 100),
			'email'           => mb_substr($email, 0, 100),
			'company'         => mb_substr($empresa, 0, 100),
			'phone'           => mb_substr($telefono, 0, 20),
			'contracted_at'   => $fecha,
			'contract_status' => 'active',
			'project_type'    => mb_substr(trim((string) ($d['servicios'] ?? '')), 0, 100),
		];
		if (isset($d['valor']) && $d['valor'] !== null) {
			$nueva['contract_value'] = (float) $d['valor'];
		}
		$ok = $wpdb->insert($tech, $nueva);
		$tech_id = $ok ? (int) $wpdb->insert_id : 0;
		if (!$tech_id) {
			return new WP_Error('tech', 'No se pudo crear la ficha operativa: ' . $wpdb->last_error);
		}
	}
	return ['crm_id' => $crm_id, 'tech_id' => $tech_id];
}

/** «Crear ficha operativa» desde la ficha del CRM (solo para clientes). */
function at_cc_accion_crear_ficha_operativa(): void {
	$crm_id = (int) ($_POST['crm_id'] ?? 0);
	check_admin_referer('at_cc_crear_ficha_operativa_' . $crm_id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	global $wpdb;
	$c = $wpdb->get_row($wpdb->prepare("SELECT nombre, email, empresa, telefono, tipo FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm_id));
	if ($c && $c->tipo === 'cliente') {
		at_cc_asegurar_cliente(['nombre' => $c->nombre, 'email' => $c->email, 'empresa' => $c->empresa, 'telefono' => $c->telefono, 'origen' => 'crm_manual']);
	}
	wp_safe_redirect(admin_url('admin.php?page=automatiza-crm-ficha&id=' . $crm_id));
	exit;
}
add_action('admin_post_at_cc_crear_ficha_operativa', 'at_cc_accion_crear_ficha_operativa');
```

- [ ] **Step 6: Cargar el módulo.** En `inc/admin-proposals.php` (CRLF, Edit), justo después del bloque que termina en `    require_once __DIR__ . '/propuestas-admin/ficha.php';` + `}`, agregar una línea en blanco y:

```php
require_once __DIR__ . '/cierre-cliente/cargar.php';
```

- [ ] **Step 7: Correr las pruebas.**
  Run:
  ```bash
  AT_WP_LOAD=<ruta> "$PHP" tests/cierre/clientes-wp-test.php
  "$PHP" tests/cierre/puras-test.php
  for f in cargar clientes; do "$PHP" -l wp-content/themes/automatiza-tech/inc/cierre-cliente/$f.php; done
  "$PHP" -l wp-content/themes/automatiza-tech/inc/admin-proposals.php
  f=wp-content/themes/automatiza-tech/inc/admin-proposals.php; echo "$(wc -l < $f) $(grep -c $'\r' $f)"
  ```
  Expected: `TODO OK` en las dos pruebas, sintaxis OK y `admin-proposals.php` con CR en todas sus líneas (su última línea no termina en salto: el conteo de CR puede ser igual o uno más que `wc -l`, como hoy: 116/117).

- [ ] **Step 8: Commit.**

```bash
git add wp-content/themes/automatiza-tech/inc/cierre-cliente/cargar.php wp-content/themes/automatiza-tech/inc/cierre-cliente/clientes.php wp-content/themes/automatiza-tech/inc/admin-proposals.php tests/cierre/wp-bootstrap.php tests/cierre/clientes-wp-test.php
git commit -m "feat(cierre): ficha única, crm_cliente_id y función puente at_cc_asegurar_cliente" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

### Task 4: Ficha única en el CRM y en Contactos

**Files:**
- Modify: `wp-content/mu-plugins/crm-ai-completo.php`
- Modify: `wp-content/themes/automatiza-tech/inc/contact-form.php`
- Modify: `wp-content/themes/automatiza-tech/inc/client-details-module.php`

**Interfaces:**
- Consumes: `at_cc_asegurar_cliente()`, `at_cc_tech_de_crm()`, acción `at_cc_crear_ficha_operativa` (Task 3); `at_render_client_contracts_widget($fila_tech)` (contratos) y `automatiza_client_full_modal_button($id, $texto)` (operaciones), ya existentes.
- Produces:
  - `at_crm_url_portal(int $cliente_id): string` (función global del mu-plugin) y el método público `AutomatizaTech_CRM_AI::url_portal($cliente_id)`.
  - `$GLOBALS['at_crm_ai']` con la instancia del CRM.
  - `_enviar_correo_bienvenida()` delega en `at_cc_enviar_bienvenida((int) $cliente_id)` cuando existe (Task 6 la define).
  - Tipo de Seguimiento `respuesta_cliente`.

Todos los anclajes de abajo aparecen **una sola vez** en su archivo (verificado el 25-sep). Antes de editar, confirmarlo con `grep -cF '<anclaje>' <archivo>`; si da distinto de 1, parar y reportar.

- [ ] **Step 1: CRM — instancia global y enlace al portal.** Reemplazar la línea `new AutomatizaTech_CRM_AI();` por:

```php
$GLOBALS['at_crm_ai'] = new AutomatizaTech_CRM_AI();

/** Enlace público a la línea de tiempo de un cliente del CRM, con la firma de enlaces del CRM; '' si no hay. */
function at_crm_url_portal(int $cliente_id): string {
    return isset($GLOBALS['at_crm_ai']) ? (string) $GLOBALS['at_crm_ai']->url_portal($cliente_id) : '';
}
```

  Y antes de la línea `    public function render_public_timeline() {`, agregar:

```php
    /** URL pública de la línea de tiempo del cliente; '' si no existe o no tiene correo. */
    public function url_portal($cliente_id) {
        global $wpdb;
        $cliente_id = (int) $cliente_id;
        $email = $wpdb->get_var($wpdb->prepare("SELECT email FROM {$this->tabla_clientes} WHERE id = %d", $cliente_id));
        if (!$email) {
            return '';
        }
        return home_url('/?crm_view=timeline&cid=' . $cliente_id . '&token=' . $this->_generar_token($cliente_id, $email));
    }

```

- [ ] **Step 2: CRM — la bienvenida nueva.** Reemplazar la línea `    private function _enviar_correo_bienvenida($cliente_id) {` por:

```php
    private function _enviar_correo_bienvenida($cliente_id) {
        // Cierre de cliente: bienvenida con la lista de arranque (inc/cierre-cliente/bienvenida.php).
        if (function_exists('at_cc_enviar_bienvenida')) {
            at_cc_enviar_bienvenida((int) $cliente_id);
            return;
        }
```

- [ ] **Step 3: CRM — la conversión enlaza la ficha operativa.**
  - En `ajax_convertir_cliente()`, reemplazar la línea `        if ($res !== false) {` que está **justo antes** de `            if (isset($_POST['enviar_bienvenida']) && $_POST['enviar_bienvenida'] === 'true') {` + `                $this->_enviar_correo_bienvenida($id);` (usar las tres líneas juntas como anclaje) por:

```php
        if ($res !== false) {
            if (function_exists('at_cc_asegurar_cliente')) {
                $conv = $wpdb->get_row($wpdb->prepare("SELECT nombre, email, empresa, telefono FROM {$this->tabla_clientes} WHERE id = %d", $id));
                if ($conv && is_email((string) $conv->email)) {
                    at_cc_asegurar_cliente(['nombre' => $conv->nombre, 'email' => $conv->email, 'empresa' => $conv->empresa, 'telefono' => $conv->telefono, 'origen' => 'crm_manual']);
                }
            }
            if (isset($_POST['enviar_bienvenida']) && $_POST['enviar_bienvenida'] === 'true') {
                $this->_enviar_correo_bienvenida($id);
```

  - En `ajax_convertir_propuesta()`, el anclaje son estas cinco líneas (la última es única en el archivo):

```php
        // Registrar evento en historial
        $wpdb->insert($this->tabla_historial, [
            'cliente_id' => $cliente_id,
            'tipo_evento' => 'conversion',
            'titulo' => 'Conversión desde Propuesta',
```

    Antes de ellas, agregar:

```php
        if (function_exists('at_cc_asegurar_cliente')) {
            at_cc_asegurar_cliente(['nombre' => $propuesta->client_name, 'email' => $propuesta->client_email, 'empresa' => $propuesta->company_name, 'telefono' => $propuesta->phone, 'origen' => 'propuesta_web']);
        }

```

- [ ] **Step 4: CRM — pestaña «Contratos y operación».**
  - Después de la línea `                        <button class="ficha-tab" data-target="tab-proyectos">🚀 Proyectos <span class="ficha-tab-badge"><?php echo count($proyectos); ?></span></button>`, agregar:

```php
                        <button class="ficha-tab" data-target="tab-operacion">📜 Contratos y operación</button>
```

  - Después de la línea `                    </div><!-- /tab-proyectos -->`, agregar:

```php
                    <!-- Tab: Contratos y operación (ficha única, cierre de cliente) -->
                    <div class="ficha-tab-content" id="tab-operacion">
                    <div class="ficha-card">
                        <h3>📜 Contratos y operación</h3>
                        <?php
                        $at_cc_tech = function_exists('at_cc_tech_de_crm') ? at_cc_tech_de_crm((int) ($cliente['id'] ?? 0)) : null;
                        if ($at_cc_tech):
                            if (function_exists('automatiza_client_full_modal_button')) {
                                echo '<p>' . automatiza_client_full_modal_button((int) $at_cc_tech->id, '📋 Ver ficha operativa (facturación, accesos, técnico y redes)') . '</p>';
                            }
                            if (function_exists('at_render_client_contracts_widget')) {
                                at_render_client_contracts_widget($at_cc_tech);
                            }
                        elseif (($cliente['tipo'] ?? '') === 'cliente' && function_exists('at_cc_asegurar_cliente')): ?>
                            <p>Este cliente todavía no tiene ficha operativa (contratos, facturación y accesos).</p>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="at_cc_crear_ficha_operativa">
                                <input type="hidden" name="crm_id" value="<?php echo (int) $cliente['id']; ?>">
                                <?php wp_nonce_field('at_cc_crear_ficha_operativa_' . (int) $cliente['id']); ?>
                                <button type="submit" class="button button-primary">Crear ficha operativa</button>
                            </form>
                        <?php else: ?>
                            <p>La ficha operativa se crea cuando el prospecto pasa a cliente.</p>
                        <?php endif; ?>
                    </div>
                    </div><!-- /tab-operacion -->
```

- [ ] **Step 5: Contactos.**
  - En `move_to_clients()`, después de la línea `            $client_id = $wpdb->insert_id;`, agregar:

```php
            // Ficha única: el lead contratado queda también como cliente en el CRM, enlazado.
            if (function_exists('at_cc_asegurar_cliente')) {
                at_cc_asegurar_cliente(['nombre' => $contact->name, 'email' => $contact->email, 'empresa' => $contact->company, 'telefono' => $contact->phone, 'origen' => 'contactos']);
            }
```

  - En la lista de clientes, el anclaje es la línea `                                       title="Ver ficha completa del cliente">`; dos líneas más abajo está `                                    </a>`. Reemplazar ese `</a>` (usar las tres líneas `title=...`, `📋 Ficha` y `</a>` como anclaje) para que quede:

```php
                                       title="Ver ficha completa del cliente">
                                       📋 Ficha
                                    </a>
                                    <?php if (!empty($client->crm_cliente_id)): ?>
                                    <br><a href="<?php echo esc_url(admin_url('admin.php?page=automatiza-crm-ficha&id=' . (int) $client->crm_cliente_id)); ?>" class="button button-small" style="margin-top:4px">Ficha única</a>
                                    <?php endif; ?>
```

- [ ] **Step 6: Seguimiento — tipo `respuesta_cliente`.** En `client-details-module.php`, en `get_detail_types()`, antes de la línea `            'propuesta_enviada' => array(`, agregar:

```php
            'respuesta_cliente' => array(
                'label' => '✅ Respuesta del cliente',
                'icon' => '✅',
                'color' => '#059669'
            ),
```

- [ ] **Step 7: Verificar.**
  Run:
  ```bash
  for f in wp-content/mu-plugins/crm-ai-completo.php wp-content/themes/automatiza-tech/inc/contact-form.php wp-content/themes/automatiza-tech/inc/client-details-module.php; do "$PHP" -l $f; echo "$f $(wc -l < $f) $(grep -c $'\r' $f)"; done
  AT_WP_LOAD=<ruta> "$PHP" -r 'require "tests/cierre/wp-bootstrap.php"; ok(function_exists("at_crm_url_portal"), "at_crm_url_portal existe"); ok(isset(AutomatizaTech_Client_Details::get_detail_types()["respuesta_cliente"]), "tipo respuesta_cliente"); fin();'
  AT_WP_LOAD=<ruta> "$PHP" tests/cierre/clientes-wp-test.php
  ```
  Expected: sintaxis OK, CR en todas las líneas (`contact-form.php` puede tener un CR más que líneas, como hoy), `TODO OK`. La vista de la pestaña nueva la revisa el controlador en la tarea 11.

- [ ] **Step 8: Commit.**

```bash
git add wp-content/mu-plugins/crm-ai-completo.php wp-content/themes/automatiza-tech/inc/contact-form.php wp-content/themes/automatiza-tech/inc/client-details-module.php
git commit -m "feat(cierre): ficha única en el CRM (Contratos y operación) y enlace desde Contactos" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

### Task 5: Contrato de servicios — plantilla, revisión y firma

**Files:**
- Create: `Docs/CONTRATO_SERVICIO_DESARROLLO.md`
- Modify: `contracts/contract-service.php`
- Modify: `contracts/at-sign-contract.php`
- Test: `tests/cierre/plantilla-test.php`, `tests/cierre/contrato-wp-test.php`

**Interfaces:**
- Consumes: `at_cc_claves_contrato_servicios()` (Task 1).
- Produces (métodos estáticos de `ContractService`):
  - `archivo_plantilla($template_id): string`, `load_template($template_id)` (ahora elige por id), `titulo_por_tipo($type): string`
  - `campos_revision(): array` → `[clave => [etiqueta, 'linea'|'texto']]`
  - `necesita_revision($c): bool` (true para `servicios` sin `revision_at` en sus marcadores)
  - `guardar_revision($contract_id, array $cambios)` → contrato actualizado o `WP_Error`
  - `sign_as_at()` devuelve `WP_Error('sin_revision', ...)` si `necesita_revision`.

- [ ] **Step 1: Prueba sin WordPress** `tests/cierre/plantilla-test.php`:

```php
<?php
// Correr: php tests/cierre/plantilla-test.php   (sin WordPress)
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/cierre-cliente/puras.php';
$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

$md = @file_get_contents(__DIR__ . '/../../Docs/CONTRATO_SERVICIO_DESARROLLO.md');
ok(is_string($md) && $md !== '', 'la plantilla existe');
$md = (string) $md;
ok(strpos($md, "\n## ACEPTACIÓN") !== false, 'tiene la sección ACEPTACIÓN (ahí corta el PDF y dibuja las firmas)');
ok(preg_match('/^##\s+Comparecientes/m', $md) === 1, 'el cuerpo empieza en un título ##');
ok(strpos($md, 'BORRADOR') !== false, 'marcada como borrador para abogado');
$cuerpo = preg_split('/^##\s+ACEPTACI/mu', $md)[0];
preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $cuerpo, $m);
$usadas = array_values(array_unique($m[1]));
$empresa = ['ciudad_firma', 'fecha_firma_larga', 'rut_at', 'representante_at_nombre', 'representante_at_rut', 'domicilio_at', 'email_at', 'ciudad_jurisdiccion'];
$sin_fuente = array_values(array_diff($usadas, at_cc_claves_contrato_servicios(), $empresa));
ok($sin_fuente === [], 'todos los marcadores tienen de dónde salir' . ($sin_fuente ? ': ' . implode(', ', $sin_fuente) : ''));
$obligatorios = ['servicios_contratados', 'monto_total', 'forma_pago', 'alcance', 'entregables', 'plazo', 'razon_social_cliente', 'representante_cliente_nombre', 'representante_cliente_rut', 'fases_siguientes', 'propuesta_codigo', 'fecha_aceptacion', 'canal_aceptacion'];
$faltan = array_values(array_diff($obligatorios, $usadas));
ok($faltan === [], 'usa los datos del acuerdo' . ($faltan ? ': faltan ' . implode(', ', $faltan) : ''));

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
```

- [ ] **Step 2: Correr y ver que falla** (`"$PHP" tests/cierre/plantilla-test.php`: «la plantilla existe» en FALLA).

- [ ] **Step 3: Escribir `Docs/CONTRATO_SERVICIO_DESARROLLO.md`** (el generador del PDF muestra desde el primer `##` hasta `## ACEPTACIÓN`; admite títulos, listas con `- `, negritas `**` y citas `>`):

```markdown
# CONTRATO DE PRESTACIÓN DE SERVICIOS DE DESARROLLO E IMPLEMENTACIÓN

> **Plantilla legal — versión 1.0 (Chile) — BORRADOR**
> Borrador para revisión de un abogado antes de su uso productivo. AutomatizaTech revisa y ajusta cada contrato antes de firmarlo, según el cliente y los servicios.

---

## Comparecientes

En **{{ciudad_firma}}**, a **{{fecha_firma_larga}}**, comparecen:

**POR UNA PARTE:**
**AutomatizaTech SpA** (en adelante "**EL PROVEEDOR**" o "**AT**"), RUT **{{rut_at}}**, representada legalmente por **{{representante_at_nombre}}**, RUT **{{representante_at_rut}}**, con domicilio en {{domicilio_at}}, correo {{email_at}}.

**Y POR LA OTRA:**
**{{razon_social_cliente}}** (en adelante "**EL CLIENTE**"), RUT **{{rut_cliente}}**, representada por **{{representante_cliente_nombre}}**, RUT **{{representante_cliente_rut}}**, correo {{email_cliente}}, teléfono {{telefono_cliente}}, con domicilio en {{domicilio_cliente}}.

Ambas partes, en adelante "**LAS PARTES**", acuerdan el siguiente contrato (en adelante el "**Contrato**"), que se rige por las cláusulas siguientes y, en lo no previsto, por la legislación chilena.

---

## CLÁUSULA PRIMERA — Antecedentes

1.1. EL CLIENTE aceptó la propuesta comercial de AT código **{{propuesta_codigo}}**, de fecha {{fecha_propuesta}}, el día **{{fecha_aceptacion}}**, {{canal_aceptacion}}.

1.2. Este Contrato cubre solo lo que EL CLIENTE decidió partir. Las fases siguientes de la propuesta se contratan, si EL CLIENTE lo decide, con un anexo firmado que fija su precio definitivo.

---

## CLÁUSULA SEGUNDA — Objeto y servicios contratados

2.1. EL PROVEEDOR prestará a EL CLIENTE los siguientes servicios para el proyecto **{{nombre_proyecto}}** (en adelante el "**Proyecto**"):

{{servicios_contratados}}

---

## CLÁUSULA TERCERA — Alcance y entregables

3.1. **Alcance:** {{alcance}}

3.2. **Entregables:**

{{entregables}}

3.3. Lo que no esté descrito en esta cláusula queda fuera del alcance y se trata según la cláusula séptima.

---

## CLÁUSULA CUARTA — Plazo

4.1. {{plazo}}

4.2. El plazo corre desde que EL PROVEEDOR recibe el anticipo y la información que EL CLIENTE debe entregar (logo, textos, accesos y material). Si esa entrega se atrasa, el plazo se corre en los mismos días.

---

## CLÁUSULA QUINTA — Precio y forma de pago

5.1. El precio total de lo contratado es de **{{monto_total}}**, IVA incluido.

5.2. **Forma de pago:** {{forma_pago}}

5.3. Los pagos se hacen por transferencia a la cuenta que EL PROVEEDOR informa por escrito. Por cada pago, EL PROVEEDOR emite la boleta o factura correspondiente.

5.4. Si un pago se atrasa más de 10 días hábiles, EL PROVEEDOR puede pausar el trabajo hasta que se regularice, y el plazo se corre en los mismos días.

---

## CLÁUSULA SEXTA — Aceptación de entregables

6.1. EL PROVEEDOR entrega cada avance en un ambiente de prueba. EL CLIENTE tiene **5 días hábiles** para aprobarlo o enviar sus observaciones por escrito. Si en ese plazo no hay observaciones, el avance se entiende aprobado.

6.2. Las observaciones que correspondan al alcance se corrigen sin costo. Las que pidan algo nuevo se tratan según la cláusula séptima.

---

## CLÁUSULA SÉPTIMA — Cambios de alcance

7.1. Cualquier cambio o agregado al alcance se cotiza por escrito y se ejecuta solo con la aprobación escrita de EL CLIENTE, con su precio y su efecto en el plazo.

---

## CLÁUSULA OCTAVA — Obligaciones de EL CLIENTE

- Entregar a tiempo la información, el material y los accesos que el Proyecto necesita.
- Designar a una persona de contacto que responda las consultas y apruebe los avances.
- Pagar en las fechas acordadas.

---

## CLÁUSULA NOVENA — Propiedad intelectual

9.1. Una vez pagado el precio total, EL CLIENTE es dueño del código, los diseños y los contenidos desarrollados para el Proyecto. Mientras el precio total no esté pagado, la propiedad sigue siendo de EL PROVEEDOR.

9.2. EL PROVEEDOR conserva la propiedad de sus herramientas, librerías y componentes genéricos anteriores al Proyecto, y le concede a EL CLIENTE una licencia de uso indefinida y gratuita sobre ellos, dentro del Proyecto.

9.3. EL PROVEEDOR puede mostrar el Proyecto en su portafolio (nombre, capturas y una descripción general), sin revelar información confidencial de EL CLIENTE.

---

## CLÁUSULA DÉCIMA — Confidencialidad

10.1. LAS PARTES mantienen reservada la información que conozcan de la otra con motivo de este Contrato, durante su vigencia y por dos años después de su término.

---

## CLÁUSULA UNDÉCIMA — Datos personales

11.1. Si EL PROVEEDOR trata datos personales por cuenta de EL CLIENTE, lo hace solo para ejecutar el Proyecto y conforme a la Ley N° 19.628 sobre protección de la vida privada y sus modificaciones.

---

## CLÁUSULA DUODÉCIMA — Garantía

12.1. EL PROVEEDOR corrige sin costo los errores del Proyecto que aparezcan dentro de los **{{garantia_meses_servicio}} meses** siguientes a la entrega final, siempre que no se deban a cambios hechos por terceros o por EL CLIENTE.

---

## CLÁUSULA DECIMOTERCERA — Término anticipado

13.1. Cualquiera de LAS PARTES puede terminar el Contrato con un aviso escrito de 15 días. EL CLIENTE paga el trabajo hecho hasta la fecha del término y EL PROVEEDOR entrega lo avanzado.

---

## CLÁUSULA DECIMOCUARTA — Fases siguientes

14.1. La propuesta incluye además las siguientes fases, con precios referenciales que se confirman al iniciar cada una:

{{fases_siguientes}}

---

## CLÁUSULA DECIMOQUINTA — Firma electrónica

15.1. LAS PARTES aceptan firmar este Contrato con firma electrónica simple, conforme a la Ley N° 19.799, con plena validez entre ellas.

---

## CLÁUSULA DECIMOSEXTA — Domicilio y jurisdicción

16.1. Para todos los efectos de este Contrato, LAS PARTES fijan domicilio en la ciudad de {{ciudad_jurisdiccion}} y se someten a la competencia de sus tribunales ordinarios de justicia.

---

## ACEPTACIÓN

El bloque de firmas lo agrega el sistema.
```

- [ ] **Step 4: Correr** `"$PHP" tests/cierre/plantilla-test.php` → `TODO OK`.

- [ ] **Step 5: Prueba con WordPress** `tests/cierre/contrato-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/contrato-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require_once ABSPATH . 'contracts/contract-service.php';
global $wpdb;
$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);

ok(ContractService::archivo_plantilla('servicios_v1') === 'CONTRATO_SERVICIO_DESARROLLO.md', 'plantilla de servicios por id');
ok(ContractService::archivo_plantilla('soporte_v2') === 'CONTRATO_SOPORTE_POSTPROYECTO.md' && ContractService::archivo_plantilla('otra') === 'CONTRATO_SOPORTE_POSTPROYECTO.md', 'soporte por defecto');
ok(strpos(ContractService::load_template('servicios_v1'), 'DESARROLLO E IMPLEMENTACIÓN') !== false, 'load_template carga la de servicios');
ok(strpos(ContractService::load_template('soporte_v2'), 'POST-PROYECTO') !== false, 'load_template sigue cargando la de soporte');
ok(strpos(ContractService::titulo_por_tipo('servicios'), 'DESARROLLO') !== false && strpos(ContractService::titulo_por_tipo('soporte'), 'POST-PROYECTO') !== false, 'título por tipo');

$c = ContractService::create_contract(['client_id' => 0, 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Muebles', 'monto_total' => '$1.000'], 'created_by' => 0]);
ok(is_object($c) && $c->type === 'servicios' && $c->template_id === 'servicios_v1' && $c->status === 'at_pending', 'contrato de servicios creado en at_pending');
$ph = json_decode($c->placeholders, true);
ok(strpos($ph['contract_title'], 'DESARROLLO') !== false, 'título de servicios guardado');
ok(ContractService::necesita_revision($c), 'necesita revisión antes de firmar');
$firma = ContractService::sign_as_at($c->id, ['signer_name' => 'Prueba', 'signer_rut' => '11.111.111-1', 'signer_email' => 'prueba@example.com', 'method' => 'canvas', 'signature_dataurl' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==']);
ok(is_wp_error($firma) && $firma->get_error_code() === 'sin_revision', 'no se firma sin revisión');
$r = ContractService::guardar_revision($c->id, ['plazo' => 'Ocho semanas', 'rut_cliente' => '11.111.111-1', 'monto_total' => '', 'inventado' => 'x']);
$ph2 = json_decode($r->placeholders, true);
ok(!is_wp_error($r) && $ph2['plazo'] === 'Ocho semanas' && $ph2['rut_cliente'] === '11.111.111-1' && !isset($ph2['monto_total']) && !isset($ph2['inventado']) && !empty($ph2['revision_at']), 'revisión guardada: cambia, borra lo vaciado e ignora claves desconocidas');
ok(!ContractService::necesita_revision($r), 'ya no necesita revisión');
$firma2 = ContractService::sign_as_at($c->id, ['signer_name' => 'Prueba', 'signer_rut' => '11.111.111-1', 'signer_email' => 'prueba@example.com', 'method' => 'canvas', 'signature_dataurl' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==']);
ok(!is_wp_error($firma2) && $firma2->status === 'at_signed', 'con revisión se firma');
ok(is_wp_error(ContractService::guardar_revision($c->id, ['plazo' => 'x'])), 'firmado ya no se edita');
$sop = ContractService::create_contract(['client_id' => 0, 'type' => 'soporte', 'template_id' => 'soporte_v2', 'placeholders' => [], 'created_by' => 0]);
ok(is_object($sop) && !ContractService::necesita_revision($sop), 'el de soporte no pide revisión');

$wpdb->query($wpdb->prepare("DELETE FROM " . ContractService::table() . " WHERE id IN (%d, %d)", $c->id, $sop->id));
fin();
```

- [ ] **Step 6: Correr y ver que falla** (métodos inexistentes).

- [ ] **Step 7: Cambiar `contracts/contract-service.php`** (CRLF, Edit):
  - Reemplazar el método `load_template` completo (desde `    public static function load_template($template_id = 'soporte_v2') {` hasta su `    }` de cierre, 9 líneas) por:

```php
    /** Archivo de plantilla según el id; lo desconocido cae en la de soporte, como antes. */
    public static function archivo_plantilla($template_id) {
        $mapa = array('servicios_v1' => 'CONTRATO_SERVICIO_DESARROLLO.md');
        return $mapa[(string) $template_id] ?? 'CONTRATO_SOPORTE_POSTPROYECTO.md';
    }
    public static function load_template($template_id = 'soporte_v2') {
        $archivo = self::archivo_plantilla($template_id);
        $candidates = array(
            ABSPATH . 'Docs/' . $archivo,
            dirname(ABSPATH) . '/Docs/' . $archivo,
            dirname(__DIR__) . '/Docs/' . $archivo,
        );
        foreach ($candidates as $p) if (file_exists($p)) return file_get_contents($p);
        return '';
    }
    public static function titulo_por_tipo($type) {
        if ($type === 'servicios') {
            return 'CONTRATO DE PRESTACIÓN DE SERVICIOS DE DESARROLLO E IMPLEMENTACIÓN';
        }
        return 'CONTRATO DE PRESTACIÓN DE SERVICIOS, CESIÓN DE PROPIEDAD INTELECTUAL Y SOPORTE TÉCNICO POST-PROYECTO';
    }

    /** Campos que AT ajusta antes de firmar un contrato de servicios: clave => [etiqueta, 'linea'|'texto']. */
    public static function campos_revision() {
        return array(
            'razon_social_cliente'  => array('Cliente (razón social o nombre)', 'linea'),
            'rut_cliente'           => array('RUT del cliente', 'linea'),
            'domicilio_cliente'     => array('Domicilio del cliente', 'linea'),
            'servicios_contratados' => array('Servicios contratados (uno por línea, empezando con «- »)', 'texto'),
            'alcance'               => array('Alcance', 'texto'),
            'entregables'           => array('Entregables (uno por línea, empezando con «- »)', 'texto'),
            'plazo'                 => array('Plazo', 'texto'),
            'monto_total'           => array('Precio total, IVA incluido', 'linea'),
            'forma_pago'            => array('Forma de pago', 'texto'),
        );
    }
    /** Un contrato de servicios no se firma hasta que AT guarda su revisión. */
    public static function necesita_revision($c) {
        if (!$c || $c->type !== 'servicios') return false;
        $ph = json_decode($c->placeholders, true) ?: array();
        return empty($ph['revision_at']);
    }
    /** Guarda la revisión de AT (solo antes de firmar) y regenera el PDF. */
    public static function guardar_revision($contract_id, array $cambios) {
        global $wpdb;
        $c = self::get_by_id($contract_id);
        if (!$c) return new WP_Error('not_found', 'Contrato no encontrado');
        if (!in_array($c->status, array('draft', 'at_pending'), true)) {
            return new WP_Error('bad_status', 'El contrato ya fue firmado: no se puede editar.');
        }
        $ph = json_decode($c->placeholders, true) ?: array();
        foreach (self::campos_revision() as $k => $_) {
            if (array_key_exists($k, $cambios)) {
                $v = trim((string) $cambios[$k]);
                if ($v === '') unset($ph[$k]); else $ph[$k] = $v;
            }
        }
        $ph['revision_at'] = current_time('mysql');
        $wpdb->update(self::table(), array('placeholders' => wp_json_encode($ph, JSON_UNESCAPED_UNICODE)), array('id' => $c->id));
        $pdf = self::render_pdf($c->id);
        if ($pdf) {
            $wpdb->update(self::table(), array('pdf_url' => self::path_to_url($pdf), 'document_hash' => hash_file('sha256', $pdf)), array('id' => $c->id));
        }
        return self::get_by_id($c->id);
    }
```

  - Reemplazar la línea que empieza con `        $ph['contract_title']   = 'CONTRATO DE PRESTACIÓN DE SERVICIOS, CESIÓN` por:

```php
        $ph['contract_title']   = self::titulo_por_tipo($a['type']);
```

  - En `sign_as_at()`, reemplazar las tres líneas
    `        if (!in_array($c->status, array('draft','at_pending'))) {` / `            return new WP_Error('bad_status','El contrato ya fue firmado por AT o por el cliente');` / `        }`
    por esas mismas tres líneas seguidas de:

```php
        if (self::necesita_revision($c)) {
            return new WP_Error('sin_revision', 'Guarda la revisión del contrato antes de firmarlo.');
        }
```

- [ ] **Step 8: Cambiar `contracts/at-sign-contract.php`** (CRLF, Edit):
  - Antes de la línea `    } elseif ($_POST['action'] === 'send') {`, agregar:

```php
    } elseif ($_POST['action'] === 'revisar') {
        $cambios = array();
        foreach (ContractService::campos_revision() as $k => $_) {
            if (isset($_POST['rev'][$k])) {
                $cambios[$k] = sanitize_textarea_field(wp_unslash($_POST['rev'][$k]));
            }
        }
        $r = ContractService::guardar_revision($c->id, $cambios);
        if (is_wp_error($r)) { $flash = $r->get_error_message(); $flash_type = 'error'; }
        else { $flash = 'Revisión guardada y PDF actualizado. Revísalo y firma cuando esté listo.'; $flash_type = 'ok'; $c = $r; }
```

  - Arreglar el enlace del PDF (hoy agrega un segundo `?` a una URL que ya tiene parámetros y rompe el token): reemplazar `<iframe src="<?= esc_url($pdf_url) ?>?v=<?= time() ?>"></iframe>` por `<iframe src="<?= esc_url(add_query_arg('v', time(), $pdf_url)) ?>"></iframe>`.
  - Reemplazar la línea `        <h3 style="margin:0 0 6px 0">🖋️ Firmar como representante AT</h3>` por:

```php
        <?php if ($c->type === 'servicios'): ?>
          <h3 style="margin:0 0 6px 0">✏️ Ajustar el contrato</h3>
          <p class="muted" style="margin:0 0 12px 0">Viene armado con los datos de la propuesta aceptada. Ajusta lo que corresponda a este cliente y guarda: el PDF de la izquierda se regenera. La firma se habilita después de guardar.</p>
          <form method="post">
            <?php wp_nonce_field('at_sign_' . $c->id, '_at_nonce'); ?>
            <input type="hidden" name="action" value="revisar">
            <?php foreach (ContractService::campos_revision() as $k => $campo): ?>
              <label><?= esc_html($campo[0]) ?></label>
              <?php if ($campo[1] === 'texto'): ?>
                <textarea name="rev[<?= esc_attr($k) ?>]" rows="4" style="width:100%"><?= esc_textarea($ph[$k] ?? '') ?></textarea>
              <?php else: ?>
                <input type="text" name="rev[<?= esc_attr($k) ?>]" value="<?= esc_attr($ph[$k] ?? '') ?>">
              <?php endif; ?>
            <?php endforeach; ?>
            <button type="submit">💾 Guardar revisión y regenerar PDF</button>
          </form>
          <hr>
        <?php endif; ?>
        <?php if (ContractService::necesita_revision($c)): ?>
          <p class="muted"><strong>Para firmar, primero guarda la revisión.</strong></p>
        <?php else: ?>
        <h3 style="margin:0 0 6px 0">🖋️ Firmar como representante AT</h3>
```

  - Reemplazar la línea `      <?php elseif ($c->status === 'at_signed'): ?>` por:

```php
        <?php endif; ?>
      <?php elseif ($c->status === 'at_signed'): ?>
```

- [ ] **Step 9: Correr todo.**
  Run:
  ```bash
  "$PHP" tests/cierre/plantilla-test.php && "$PHP" tests/cierre/puras-test.php
  AT_WP_LOAD=<ruta> "$PHP" tests/cierre/contrato-wp-test.php
  for f in contracts/contract-service.php contracts/at-sign-contract.php; do "$PHP" -l $f; echo "$f $(wc -l < $f) $(grep -c $'\r' $f)"; done
  ```
  Expected: `TODO OK` en las tres, sintaxis OK, CR en todas las líneas.

- [ ] **Step 10: Commit.**

```bash
git add Docs/CONTRATO_SERVICIO_DESARROLLO.md contracts/contract-service.php contracts/at-sign-contract.php tests/cierre/plantilla-test.php tests/cierre/contrato-wp-test.php
git commit -m "feat(contratos): plantilla de servicios, revisión obligatoria antes de firmar y título por tipo" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

### Task 5b: Contrato según el tipo de cliente (persona natural o empresa) y correcciones de la revisión de la Task 5

Aprobado por Luis el 26-sep. Corre DESPUÉS de la Task 6. Parte de lo que dejó la Task 5 (`contracts/contract-service.php`, `contracts/at-sign-contract.php`, `Docs/CONTRATO_SERVICIO_DESARROLLO.md`, `tests/cierre/plantilla-test.php`, `tests/cierre/contrato-wp-test.php`) y de `inc/cierre-cliente/puras.php` (Task 1).

**Por qué:** la plantilla asumía que el cliente siempre es una empresa («razón social …, representada por …»). Si el contrato va a nombre de una persona, quedaba «Julio Chirinos, representada por Julio Chirinos» o a nombre de una marca que no es persona jurídica. Además la revisión de la Task 5 encontró tres defectos que Luis aprobó corregir.

**Files:**
- Modify: `contracts/contract-service.php` (CRLF), `contracts/at-sign-contract.php` (CRLF), `Docs/CONTRATO_SERVICIO_DESARROLLO.md`, `wp-content/themes/automatiza-tech/inc/cierre-cliente/puras.php`
- Test: `tests/cierre/plantilla-test.php`, `tests/cierre/contrato-wp-test.php`, `tests/cierre/puras-test.php`

**Interfaces que produce (las usa la Task 7):**
- `ContractService::comparecencia_cliente(array $ph): string` — párrafo del cliente en «Comparecientes», derivado de los marcadores; nunca se guarda: se calcula al generar el PDF.
- `ContractService::faltantes($c): array` — etiquetas de los datos esenciales que faltan (o son inválidos) para firmar un contrato de servicios; `[]` si está completo o si no es de servicios.
- `ContractService::actualizar_datos_cliente($contract_id, array $datos)` — guarda los datos que el cliente da después de aceptar (`tipo_cliente`, `razon_social_cliente`, `rut_cliente`, `domicilio_cliente`); contrato actualizado o `WP_Error`.
- Marcador nuevo `tipo_cliente`: `'persona'` | `'empresa'` | ausente (sin elegir).

---

#### 1. Limpieza del texto de la revisión (defecto: «50%de» salía «50 anticipo», «<24 horas» salía con entidades)

- `at-sign-contract.php` deja de pasar los campos por `sanitize_textarea_field` / `sanitize_text_field`: entrega `wp_unslash($_POST[...])` tal cual a `guardar_revision()`.
- La limpieza vive en `ContractService` (un método privado usado por `guardar_revision()` y `actualizar_datos_cliente()`): `str_replace("\0", '', ...)`, `wp_check_invalid_utf8(...)`, fines de línea `\r\n`/`\r` → `\n`, `trim`. Nada de `strip_tags`, entidades ni quitar `%`. El destino es el PDF; donde se muestre en HTML se escapa al imprimir (la página ya usa `esc_attr`/`esc_textarea`: confírmalo).
- Prueba (contrato-wp-test.php): guardar `plazo` = `Respuesta en <24 horas, "comillas" y 'simples'; 50%de anticipo` y comprobar que el marcador guardado es **idéntico**.

#### 2. Tipo de cliente y párrafo del cliente según el tipo

- En la plantilla, el párrafo que hoy empieza con `**{{razon_social_cliente}}** (en adelante "**EL CLIENTE**"), RUT …` (sección «Comparecientes», después de `**Y POR LA OTRA:**`) se reemplaza por una sola línea: `{{comparecencia_cliente}}`.
- `ContractService::comparecencia_cliente(array $ph)` arma el texto (los valores vacíos se muestran como `_______`, igual que el resto del PDF; negritas con `**` como el resto de la plantilla):
  - `empresa`:
    `**{razon_social_cliente}** (en adelante "**EL CLIENTE**"), RUT **{rut_cliente}**, representada por **{representante_cliente_nombre}**, RUT **{representante_cliente_rut}**, correo {email_cliente}, teléfono {telefono_cliente}, con domicilio en {domicilio_cliente}.`
  - `persona`:
    `**{razon_social_cliente}** (en adelante "**EL CLIENTE**"), RUT **{rut_cliente}**, correo {email_cliente}, teléfono {telefono_cliente}, con domicilio en {domicilio_cliente}, para su proyecto «{nombre_proyecto}».`
    La frase `, para su proyecto «{nombre_proyecto}»` va solo si `nombre_proyecto` no está vacío y es distinto (sin mayúsculas ni espacios de más) de `razon_social_cliente`; si no, el párrafo termina en `…con domicilio en {domicilio_cliente}.`
  - sin elegir (no hay `tipo_cliente`): el formato de `persona` **sin** la frase del proyecto (borrador neutro; la firma igual queda bloqueada hasta elegir).
- Donde se reemplazan los `{{marcadores}}` al generar el PDF, `{{comparecencia_cliente}}` se calcula con este método a partir de los marcadores del contrato. No se guarda en la base.
- **Persona natural = sus datos de la aceptación.** Método privado usado por `guardar_revision()` y `actualizar_datos_cliente()`: si `tipo_cliente` es `persona` y `razon_social_cliente` está vacío, toma `representante_cliente_nombre`; si `rut_cliente` está vacío, toma `representante_cliente_rut` (quien aceptó es el cliente).
- `campos_revision()` agrega, en este orden al principio: `'tipo_cliente' => ['Tipo de cliente', 'tipo']`; y después de `domicilio_cliente`: `'representante_cliente_nombre' => ['Representante (solo si es empresa)', 'linea']`, `'representante_cliente_rut' => ['RUT del representante (solo si es empresa)', 'linea']`. Al final: `'fases_siguientes' => ['Fases siguientes (una por línea, empezando con «- »)', 'texto']`.
- `at-sign-contract.php` dibuja el tipo `'tipo'` como un `<select>` con «— Elegir —» (valor vacío), «Persona natural (a su nombre)» (`persona`) y «Empresa o persona jurídica» (`empresa`). `guardar_revision()` solo acepta `persona`, `empresa` o vacío para `tipo_cliente`.
- `at_cc_marcadores_servicios()` (puras.php) acepta la clave opcional `tipo_cliente` en `$d` y la pasa al marcador si es `persona` o `empresa`; agregar `'tipo_cliente'` a `at_cc_claves_contrato_servicios()`. Sin tipo, todo sigue como hoy.

#### 3. No se firma con datos en blanco

- `ContractService::faltantes($c)`: solo para `type === 'servicios'`. Siempre exige `tipo_cliente` («Tipo de cliente»). Luego:
  - `persona`: `razon_social_cliente` («Nombre completo del cliente»), `rut_cliente` («RUT del cliente»), `domicilio_cliente`, `monto_total`, `forma_pago`.
  - `empresa`: `razon_social_cliente` («Razón social»), `rut_cliente` («RUT de la empresa»), `representante_cliente_nombre`, `representante_cliente_rut`, `domicilio_cliente`, `monto_total`, `forma_pago`.
  - Un RUT presente pero inválido cuenta como faltante con la etiqueta «RUT … (no es válido)»; se valida con `at_cc_rut_valido()` si existe (`function_exists`), si no, basta con que no esté vacío.
  - Las etiquetas salen de `campos_revision()` salvo las nombradas arriba.
- `sign_as_at()`: después del chequeo de revisión existente, si `faltantes($c)` no está vacío devuelve `WP_Error('faltan_datos', 'Antes de firmar completa: ' . implode(', ', $faltantes) . '.')`.
- `at-sign-contract.php`: cuando la revisión ya está guardada pero hay faltantes, muestra ese mismo mensaje en un aviso visible (lista de lo que falta) y **no** muestra el bloque de firma, igual que hoy cuando falta la revisión.

#### 4. Cláusula 14.1 (texto aprobado por Luis)

En la plantilla, reemplazar `14.1. La propuesta incluye además las siguientes fases, con precios referenciales que se confirman al iniciar cada una:` por:
`14.1. Fases siguientes de la propuesta (precios referenciales que se confirman al iniciar cada una):`

#### 5. Nota para el abogado (no sale en el PDF)

Antes de `## Comparecientes` (el PDF se genera desde el primer `##`, así que esto no se imprime), agregar una cita:
`> Nota para la revisión legal: si EL CLIENTE es persona natural, revisar la aplicación de la Ley 19.496 (y de la Ley 20.416 para micro y pequeñas empresas) sobre las cláusulas de responsabilidad, término anticipado, domicilio y jurisdicción.`

#### 6. Datos que da el cliente después de aceptar (lo usa la Task 7)

`ContractService::actualizar_datos_cliente($contract_id, array $datos)`:
- Solo contratos `servicios` en `draft` o `at_pending` **y sin revisión de AT guardada** (si ya hay `revision_at`, devuelve `WP_Error('ya_revisado', ...)`: Luis ya lo ajustó y manda lo suyo).
- Acepta solo `tipo_cliente` (`persona`|`empresa`), `razon_social_cliente`, `rut_cliente`, `domicilio_cliente`; ignora el resto. Limpia como en el punto 1; aplica «persona natural = sus datos de la aceptación».
- No marca `revision_at`. Guarda y regenera el PDF (mismo camino que `guardar_revision`).

#### 7. Línea en la bienvenida

En `at_cc_bienvenida_html()` (puras.php), cuando `con_propuesta` es verdadero, agregar un párrafo antes del cierre del correo con este texto exacto:
«Para preparar tu contrato necesitamos saber a nombre de quién va (tú o tu empresa), el RUT y la dirección. Si ya los completaste en la página de la propuesta, no tienes que hacer nada; si no, respóndenos este correo con esos datos.»
Prueba en `puras-test.php`: aparece con `con_propuesta` y no aparece sin propuesta.

#### 8. Enlaces a PDF del portal del cliente (del cotejo de PROD del 25-sep)

El módulo de contratos que trae esta rama solo entrega los PDF a través de `ContractService::secure_pdf_url()` (descarga con permiso). La línea de tiempo pública del cliente en `wp-content/mu-plugins/crm-ai-completo.php` todavía enlaza directo al archivo (`$c['signed_pdf_url']` y `$c['pdf_url']`, hoy alrededor de las líneas 4806 y 4824, en el bloque de contratos que consulta `signed_pdf_url, pdf_url, sign_token, …`): con el módulo nuevo esos enlaces darán 403.
- Cambiarlos por `ContractService::secure_pdf_url((object) $c, true, $c['sign_token'])` (firmado) y `ContractService::secure_pdf_url((object) $c, false, $c['sign_token'])` (preliminar), dentro de `esc_url()`.
- Si la clase no está cargada en esa petición pública, cargarla con `require_once ABSPATH . 'contracts/contract-service.php'` cuando el archivo exista (confirma cómo se carga hoy el módulo en el front). Si aun así no existe la clase, no mostrar el enlace (nunca el directo).
- `crm-ai-completo.php` es CRLF: mide CR y LF con PHP antes y después y conserva la diferencia; confirma los anclajes con `grep -cF`.
- Prueba (en `contrato-wp-test.php` o una nueva `portal-pdf-wp-test.php`): generar el HTML de la línea de tiempo pública de un cliente de prueba con un contrato (buffer de salida) y comprobar que el enlace contiene `admin-ajax.php?action=at_download_contract` y el token, y que no contiene la URL directa de `uploads/automatiza-tech-contracts`. Si generar esa vista en la prueba no es viable, explica por qué en el reporte y prueba al menos la función que arma el enlace.
- Agregar `wp-content/mu-plugins/crm-ai-completo.php` (y la prueba nueva, si la hay) al commit.

---

#### Pruebas (TDD: primero fallan, después pasan)

- `plantilla-test.php`: `comparecencia_cliente` es un marcador **derivado** permitido (lista `$derivados`, igual que `$empresa`); en los obligatorios, `comparecencia_cliente` reemplaza a `razon_social_cliente`, `representante_cliente_nombre` y `representante_cliente_rut`; la plantilla ya no contiene «representada por **{{»; contiene «14.1. Fases siguientes de la propuesta»; la nota legal está **antes** del primer `##`.
- `contrato-wp-test.php` (ajustar la prueba existente, sin debilitarla):
  - comparecencia `empresa` con todos los datos: contiene «representada por»; `persona` con `nombre_proyecto` distinto: no contiene «representada por» y termina con «para su proyecto «…».»; `persona` con `nombre_proyecto` igual al nombre: sin la frase; sin tipo: sin «representada por» ni proyecto; vacíos como `_______`.
  - `faltantes()`: sin tipo → incluye «Tipo de cliente»; persona completa → `[]`; empresa sin representante → lo lista; RUT inválido → lo lista; contrato `soporte` → `[]`.
  - `sign_as_at()` con revisión guardada pero faltantes → `WP_Error('faltan_datos')`; completando los datos → firma (`at_signed`). La prueba existente «con revisión se firma» ahora completa tipo y datos esenciales antes de firmar; se conserva la aserción de que vaciar un campo lo borra.
  - persona natural toma nombre y RUT del representante si vienen vacíos.
  - limpieza: el texto con `<`, comillas y `%xx` se guarda idéntico.
  - `actualizar_datos_cliente()`: guarda tipo/razón/RUT/domicilio sin marcar revisión; ignora claves ajenas; con revisión ya guardada → `WP_Error('ya_revisado')`; firmado → error.
  - el PDF regenerado contiene el párrafo del tipo elegido (extraer el texto del PDF como ya hace la prueba, o comprobar el texto que se pasa al generador).
- `puras-test.php`: `tipo_cliente` pasa al marcador; la línea nueva de la bienvenida.
- Correr: `plantilla-test.php`, `puras-test.php`, `contrato-wp-test.php`, `clientes-wp-test.php`, `cierre-wp-test.php` (Task 6), todas en `TODO OK`.

#### Commit

```bash
git add contracts/contract-service.php contracts/at-sign-contract.php wp-content/mu-plugins/crm-ai-completo.php Docs/CONTRATO_SERVICIO_DESARROLLO.md wp-content/themes/automatiza-tech/inc/cierre-cliente/puras.php tests/cierre/plantilla-test.php tests/cierre/contrato-wp-test.php tests/cierre/puras-test.php
git commit -m "feat(cierre): contrato a nombre de persona natural o empresa, sin firma con datos en blanco" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

### Task 6: Cierre automático al aceptar (`contrato.php`, `ajustes.php`, `bienvenida.php`, `respuesta.php`)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/cierre-cliente/contrato.php`, `ajustes.php`, `bienvenida.php`, `respuesta.php`
- Modify: `wp-content/themes/automatiza-tech/inc/cierre-cliente/cargar.php`
- Test: `tests/cierre/cierre-wp-test.php`

**Interfaces:**
- Consumes: Task 1 (puras), Task 3 (`at_cc_asegurar_cliente`, `at_cc_crm_de_email`), Task 4 (`at_crm_url_portal`), Task 5 (`ContractService`).
- Produces:
  - `at_cc_propuesta_por_codigo(string $codigo): ?object`, `at_cc_propuesta_por_id(int $id): ?object`, `at_cc_filas_de_propuesta(object $p): array`
  - `at_cc_registrar_respuesta(object $p, string $salida, array $d): array` → `['ok' => bool, 'estado' => string, 'mensaje' => string, 'avisos' => string[], 'crm_id' => ?int, 'contrato_id' => ?int]`. `$d`: `canal` (`pagina`|`whatsapp`|`manual`), `canal_manual` (clave de `at_cc_canales_manuales`), `nombre`, `rut`, `comentario`, `filas` (filas ya elegidas), `fecha`, `evidencias` (`[['url','nombre','tipo','archivo']]`), `bienvenida` (bool), `telefono`, `wamid`, `ip`, `agente`, `usuario_id`.
  - `at_cc_anotar_simple(object $p, string $tipo, string $titulo, string $descripcion = ''): int`
  - `at_cc_ultima_respuesta(int $propuesta_id): ?array` → `['titulo','fecha','salida','filas','evidencias']`
  - `at_cc_contrato_de_propuesta(int $propuesta_id): ?object`
  - `at_cc_enviar_pedido_respuesta(object $p): bool`
  - `at_cc_enviar_bienvenida(int $crm_id, ?object $p = null, array $filas = []): bool`
  - `at_cc_datos_banco(): array`, página de ajustes `at-cc-ajustes`
  - `at_cc_crear_contrato_servicios(object $p, int $tech_id, array $filas, array $aceptante)` → id o `WP_Error`

- [ ] **Step 1: Prueba** `tests/cierre/cierre-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/cierre-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);
$marca = 'prueba-cierre-' . strtolower(wp_generate_password(6, false, false));
$payload = [
	'company_name' => '[PRUEBA] Muebles', 'solution_text' => 'Configurador 3D de módulos.',
	'how_it_works' => [['step_title' => 'Configurador', 'step_text' => 'Arrastrar y apilar módulos']],
	'pricing_rows' => [
		['service' => 'Fase 1: Configurador 3D', 'price_usd' => 0, 'price_label' => '$2.000.000 en 2 pagos'],
		['service' => 'Fase 2: Sitio web', 'price_usd' => 0, 'price_label' => '$2.500.000 en 2 pagos (estimado)'],
	],
];
$creadas = [];
function crear_propuesta(string $marca, string $status, array $payload, array &$creadas): object {
	global $wpdb;
	$ok = $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => $marca . '-' . $status . '@example.com', 'unique_link_id' => substr(md5($marca . $status . microtime(true)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Muebles', 'phone' => '+56 9 2222 2222',
		'status' => $status, 'flujo' => 'v3', 'gamma_prompt_text' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
		'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	if (!$ok) { fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n"); exit(2); }
	$creadas[] = (int) $wpdb->insert_id;
	return at_cc_propuesta_por_id((int) $wpdb->insert_id);
}
$det = $wpdb->prefix . 'automatiza_propuestas_details';

// Lectura
$p = crear_propuesta($marca, 'sent', $payload, $creadas);
ok(at_cc_propuesta_por_codigo($p->unique_link_id)->id == $p->id && at_cc_propuesta_por_codigo('no-existe') === null && at_cc_propuesta_por_codigo('') === null, 'propuesta por código');
ok(count(at_cc_filas_de_propuesta($p)) === 2, 'filas de la propuesta');

// Evalúa y rechaza: sin cliente ni correo al cliente
$correos = [];
$r = at_cc_registrar_respuesta($p, 'evalua', ['canal' => 'pagina', 'comentario' => 'Quiero ver el plazo', 'fecha' => current_time('mysql')]);
ok($r['ok'] && $r['estado'] === 'evaluando' && at_cc_propuesta_por_id($p->id)->status === 'evaluando', 'evalúa: queda en evaluación');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente'", $p->id)) === 1, 'evalúa: queda en Seguimiento');
ok(at_cc_crm_de_email($p->client_email) === 0, 'evalúa: no crea cliente');
ok(count($correos) === 1 && $correos[0]['to'] === get_option('admin_email'), 'evalúa: solo avisa a Luis');
$p = at_cc_propuesta_por_id($p->id);
$r = at_cc_registrar_respuesta($p, 'evalua', ['canal' => 'pagina', 'comentario' => 'Otra duda']);
ok($r['ok'] && $r['mensaje'] === 'anotada' && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente'", $p->id)) === 2, 'evaluar dos veces anota sin cambiar estado');
$r = at_cc_registrar_respuesta($p, 'rechaza', ['canal' => 'pagina', 'comentario' => 'Muy caro']);
ok($r['ok'] && at_cc_propuesta_por_id($p->id)->status === 'rechazada', 'rechaza');

// Acepta (cambió de opinión) desde la página
$correos = [];
$p = at_cc_propuesta_por_id($p->id);
$filas = at_cc_filas_aceptadas(at_cc_filas_de_propuesta($p), [0]);
$r = at_cc_registrar_respuesta($p, 'acepta', ['canal' => 'pagina', 'nombre' => 'Ana Prueba', 'rut' => '11.111.111-1', 'filas' => $filas, 'fecha' => current_time('mysql'), 'bienvenida' => true, 'ip' => '127.0.0.1']);
ok($r['ok'] && $r['estado'] === 'aceptada' && at_cc_propuesta_por_id($p->id)->status === 'aceptada', 'acepta: propuesta aceptada');
ok($r['crm_id'] > 0 && $wpdb->get_var($wpdb->prepare("SELECT tipo FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $r['crm_id'])) === 'cliente', 'acepta: pasa a cliente');
ok(at_cc_tech_de_crm((int) $r['crm_id']) !== null, 'acepta: con ficha operativa enlazada');
$c = at_cc_contrato_de_propuesta((int) $p->id);
$ph = $c ? json_decode($c->placeholders, true) : [];
ok($c && $c->type === 'servicios' && $c->template_id === 'servicios_v1' && $c->status === 'at_pending' && (int) $c->client_id === (int) at_cc_tech_de_crm((int) $r['crm_id'])->id, 'acepta: contrato de servicios en borrador para la ficha operativa');
ok(($ph['monto_total'] ?? '') === '$2.000.000' && ($ph['representante_cliente_rut'] ?? '') === '11.111.111-1' && strpos($ph['fases_siguientes'] ?? '', 'Fase 2') !== false && strpos($ph['entregables'] ?? '', 'Configurador') !== false, 'acepta: contrato con los datos de lo aceptado');
$al_cliente = array_values(array_filter($correos, function ($m) use ($p) { return $m['to'] === $p->client_email; }));
ok(count($al_cliente) === 1 && strpos($al_cliente[0]['subject'], 'bienvenida') !== false && strpos($al_cliente[0]['message'], '$1.000.000') !== false, 'acepta: bienvenida al cliente con el anticipo');
ok($r['avisos'] === [], 'acepta: sin avisos' . ($r['avisos'] ? ': ' . implode(' | ', $r['avisos']) : ''));
$u = at_cc_ultima_respuesta((int) $p->id);
ok($u && $u['salida'] === 'acepta', 'última respuesta es la aceptación');

// Idempotente
$r2 = at_cc_registrar_respuesta(at_cc_propuesta_por_id($p->id), 'acepta', ['canal' => 'pagina', 'filas' => $filas]);
ok($r2['ok'] && $r2['mensaje'] === 'ya_aceptada' && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . ContractService::table() . " WHERE proposal_id = %d", $p->id)) === 1, 'aceptar dos veces no repite nada');

// Borrador: no se acepta desde la página
$b = crear_propuesta($marca, 'borrador', $payload, $creadas);
ok(!at_cc_registrar_respuesta($b, 'acepta', ['canal' => 'pagina'])['ok'] && at_cc_propuesta_por_id($b->id)->status === 'borrador', 'borrador no se acepta');

// A mano desde pending, sin bienvenida
$correos = [];
$m = crear_propuesta($marca, 'pending', $payload, $creadas);
$r = at_cc_registrar_respuesta($m, 'acepta', ['canal' => 'manual', 'canal_manual' => 'whatsapp', 'nombre' => 'Cliente Prueba', 'comentario' => 'Dijo que sí por WhatsApp', 'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($m), [0, 1]), 'fecha' => '2026-09-20 12:00:00', 'bienvenida' => false, 'usuario_id' => 1]);
ok($r['ok'] && at_cc_propuesta_por_id($m->id)->status === 'aceptada', 'a mano desde pending');
ok(count(array_filter($correos, function ($x) use ($m) { return $x['to'] === $m->client_email; })) === 0, 'a mano sin bienvenida: no escribe al cliente');
ok(count(array_filter($correos, function ($x) { return strpos((string) $x['subject'], 'aceptó la propuesta') !== false; })) === 0, 'a mano no manda el aviso «aceptó la propuesta» (lo registró Luis)');
$ph_m = json_decode(at_cc_contrato_de_propuesta((int) $m->id)->placeholders, true);
ok(($ph_m['monto_total'] ?? '') === '$4.500.000' && strpos($ph_m['canal_aceptacion'] ?? '', 'por WhatsApp') === 0, 'a mano: contrato con lo marcado y el canal');

// Pedir respuesta y bienvenida sin banco
$correos = [];
$s = crear_propuesta($marca, 'sent', $payload, $creadas);
ok(at_cc_enviar_pedido_respuesta($s) && strpos($correos[0]['message'], 'Aceptar la propuesta') !== false && strpos($correos[0]['message'], 'responder=aceptar') !== false, 'pedir respuesta manda el bloque de aceptar');
foreach (['at_cc_banco', 'at_cc_tipo_cuenta', 'at_cc_numero_cuenta', 'at_cc_titular', 'at_cc_rut_titular'] as $o) { delete_option($o); }
ok(!at_cc_banco_completo(at_cc_datos_banco()), 'sin datos bancarios');

// Limpieza
$ids = implode(',', array_map('intval', $creadas));
$emails = $wpdb->get_col("SELECT client_email FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
$crm_ids = array_filter(array_map('at_cc_crm_de_email', $emails));
$wpdb->query("DELETE FROM " . ContractService::table() . " WHERE proposal_id IN ({$ids})");
$wpdb->query("DELETE FROM {$det} WHERE propuesta_id IN ({$ids})");
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_clients_details WHERE propuesta_origin_id IN ({$ids})");
if ($crm_ids) {
	$lista = implode(',', array_map('intval', $crm_ids));
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_historial WHERE cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id IN ({$lista})");
}
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
fin();
```

  Si la limpieza de `automatiza_clients_details` falla porque la columna se llama distinto, revisar `migrate_details_to_client()` en `client-details-module.php` y usar la columna real; reportarlo.

- [ ] **Step 2: Correr y ver que falla** (funciones inexistentes).

- [ ] **Step 3: `contrato.php`:**

```php
<?php
/** Contrato de servicios armado desde una propuesta aceptada (Luis lo ajusta y firma después). */
if (!defined('ABSPATH')) {
	exit;
}

/** Datos del contrato desde la propuesta, lo aceptado y quién aceptó. */
function at_cc_datos_contrato(object $p, array $filas_aceptadas, array $aceptante): array {
	$payload = at_cc_json_de_payload((string) $p->gamma_prompt_text) ?? [];
	$todas = at_cc_filas_de_propuesta($p);
	$siguientes = array_values(array_filter($todas, function ($f) use ($filas_aceptadas) { return !in_array($f, $filas_aceptadas, true); }));
	$entregables = [];
	foreach ((array) ($payload['how_it_works'] ?? []) as $s) {
		if (is_array($s)) {
			$t = trim((string) ($s['step_title'] ?? ''));
			$x = trim((string) ($s['step_text'] ?? ''));
			if ($t !== '' || $x !== '') {
				$entregables[] = '- ' . ($t !== '' ? '**' . str_replace('*', '', $t) . '**' . ($x !== '' ? ': ' : '') : '') . $x;
			}
		} elseif (is_string($s) && trim($s) !== '') {
			$entregables[] = '- ' . trim($s);
		}
	}
	return [
		'empresa'           => (string) $p->company_name,
		'representante'     => trim((string) ($aceptante['nombre'] ?? '')) !== '' ? (string) $aceptante['nombre'] : (string) $p->client_name,
		'rut_representante' => (string) ($aceptante['rut'] ?? ''),
		'email'             => (string) $p->client_email,
		'telefono'          => (string) $p->phone,
		'codigo_propuesta'  => (string) $p->unique_link_id,
		'fecha_propuesta'   => at_cc_fecha_larga((string) $p->created_at),
		'fecha_aceptacion'  => at_cc_fecha_larga((string) ($aceptante['fecha'] ?? current_time('mysql'))),
		'canal_aceptacion'  => (string) ($aceptante['canal_texto'] ?? 'en la página de la propuesta'),
		'filas_aceptadas'   => $filas_aceptadas,
		'filas_siguientes'  => $siguientes,
		'alcance'           => trim((string) ($payload['solution_text'] ?? '')),
		'entregables'       => implode("\n", $entregables),
		'plazo'             => 'Se define con EL CLIENTE en la reunión de inicio y queda por escrito.',
	];
}

/** Crea el borrador del contrato de servicios; devuelve su id o WP_Error. */
function at_cc_crear_contrato_servicios(object $p, int $tech_id, array $filas, array $aceptante) {
	if (!class_exists('ContractService')) {
		$f = ABSPATH . 'contracts/contract-service.php';
		if (!file_exists($f)) {
			return new WP_Error('sin_contratos', 'El módulo de contratos no está instalado.');
		}
		require_once $f;
	}
	$ph = at_cc_marcadores_servicios(at_cc_datos_contrato($p, $filas, $aceptante));
	$c = ContractService::create_contract([
		'client_id'       => $tech_id,
		'proposal_id'     => (int) $p->id,
		'type'            => 'servicios',
		'template_id'     => 'servicios_v1',
		'placeholders'    => $ph,
		'expires_in_days' => 30,
	]);
	return is_wp_error($c) ? $c : (int) $c->id;
}

/** Último contrato creado para una propuesta; null si no hay (o no existe la tabla). */
function at_cc_contrato_de_propuesta(int $propuesta_id): ?object {
	global $wpdb;
	$t = $wpdb->prefix . 'automatiza_contracts';
	if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t))) {
		return null;
	}
	$c = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE proposal_id = %d ORDER BY id DESC LIMIT 1", $propuesta_id));
	return $c ?: null;
}
```

- [ ] **Step 4: `ajustes.php`:**

```php
<?php
/** Propuestas › Ajustes del cierre: datos de transferencia para la bienvenida. Los escribe Luis. */
if (!defined('ABSPATH')) {
	exit;
}

function at_cc_opciones_cierre(): array {
	return [
		'at_cc_banco'         => 'Banco',
		'at_cc_tipo_cuenta'   => 'Tipo de cuenta',
		'at_cc_numero_cuenta' => 'Número de cuenta',
		'at_cc_titular'       => 'Titular',
		'at_cc_rut_titular'   => 'RUT del titular',
		'at_cc_correo_pago'   => 'Correo para avisar el pago',
		'at_cc_whatsapp_at'   => 'WhatsApp de AutomatizaTech (con 56 adelante)',
	];
}

add_action('admin_init', function () {
	foreach (at_cc_opciones_cierre() as $k => $_) {
		register_setting('at_cc_ajustes', $k, [
			'type'              => 'string',
			'sanitize_callback' => $k === 'at_cc_correo_pago' ? 'sanitize_email' : 'sanitize_text_field',
			'default'           => '',
		]);
	}
});

add_action('admin_menu', function () {
	add_submenu_page('automatiza-proposals', 'Ajustes del cierre', 'Ajustes del cierre', 'manage_options', 'at-cc-ajustes', 'at_cc_render_ajustes');
}, 20);

function at_cc_render_ajustes(): void {
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.');
	}
	?>
	<div class="wrap">
		<h1>Ajustes del cierre de cliente</h1>
		<p>Estos datos van en el correo de bienvenida, en el paso del anticipo. Si falta alguno, el correo dice que los datos de pago van por separado.</p>
		<form method="post" action="options.php">
			<?php settings_fields('at_cc_ajustes'); ?>
			<table class="form-table" role="presentation">
				<?php foreach (at_cc_opciones_cierre() as $k => $t): ?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr($k); ?>"><?php echo esc_html($t); ?></label></th>
					<td><input type="text" class="regular-text" id="<?php echo esc_attr($k); ?>" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr((string) get_option($k, '')); ?>"></td>
				</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button('Guardar'); ?>
		</form>
	</div>
	<?php
}

function at_cc_datos_banco(): array {
	return [
		'banco'   => (string) get_option('at_cc_banco', ''),
		'tipo'    => (string) get_option('at_cc_tipo_cuenta', ''),
		'numero'  => (string) get_option('at_cc_numero_cuenta', ''),
		'titular' => (string) get_option('at_cc_titular', ''),
		'rut'     => (string) get_option('at_cc_rut_titular', ''),
	];
}

function at_cc_whatsapp_at(): string {
	$n = trim((string) get_option('at_cc_whatsapp_at', ''));
	return $n !== '' ? $n : '56927002984';
}
```

- [ ] **Step 5: `bienvenida.php`:**

```php
<?php
/** Correo de bienvenida con la lista de arranque (reemplaza al del CRM, también al convertir a mano). */
if (!defined('ABSPATH')) {
	exit;
}

define('AT_CC_LOGO', 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png');

/** Deja un evento en el historial del CRM. */
function at_cc_historial_crm(int $crm_id, string $tipo, string $titulo, string $descripcion): void {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'crm_historial', [
		'cliente_id'  => $crm_id,
		'tipo_evento' => $tipo,
		'titulo'      => $titulo,
		'descripcion' => $descripcion,
		'usuario_id'  => get_current_user_id(),
		'created_at'  => current_time('mysql'),
	]);
}

function at_cc_enviar_bienvenida(int $crm_id, ?object $p = null, array $filas = []): bool {
	global $wpdb;
	$c = $wpdb->get_row($wpdb->prepare("SELECT id, nombre, email, empresa FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm_id));
	if (!$c || !is_email((string) $c->email)) {
		return false;
	}
	if ($p === null) {
		$p = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}automatiza_propuestas WHERE LOWER(TRIM(client_email)) = %s AND status = 'aceptada' ORDER BY id DESC LIMIT 1",
			at_cc_email_normalizado((string) $c->email)
		)) ?: null;
		if ($p) {
			$u = at_cc_ultima_respuesta((int) $p->id);
			$filas = ($u && $u['salida'] === 'acepta') ? (array) ($u['filas'] ?? []) : [];
		}
	}
	$html = at_cc_bienvenida_html([
		'nombre'        => (string) $c->nombre,
		'empresa'       => $p ? (string) $p->company_name : (string) $c->empresa,
		'anticipo'      => $filas ? at_cc_anticipo($filas) : null,
		'banco'         => at_cc_datos_banco(),
		'correo_pago'   => (string) get_option('at_cc_correo_pago', ''),
		'whatsapp'      => at_cc_whatsapp_at(),
		'url_portal'    => function_exists('at_crm_url_portal') ? at_crm_url_portal($crm_id) : '',
		'logo'          => AT_CC_LOGO,
		'con_propuesta' => (bool) $p,
	]);
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	$headers = [
		'Content-Type: text/html; charset=UTF-8',
		'From: Automatiza Tech <' . $from . '>',
		'Reply-To: ' . get_option('admin_email'),
		'Bcc: lgonzalez@automatizatech.cl, adriana.perez@automatizatech.cl',
	];
	$enviado = wp_mail((string) $c->email, '¡Te damos la bienvenida a AutomatizaTech! Tus primeros pasos', $html, $headers);
	at_cc_historial_crm($crm_id, 'email_bienvenida', 'Correo de bienvenida enviado', $enviado ? 'Se envió la bienvenida con los primeros pasos.' : 'Falló el envío de la bienvenida.');
	return (bool) $enviado;
}
```

  La prueba busca `'bienvenida'` en el asunto: el asunto de arriba la contiene.

- [ ] **Step 6: `respuesta.php`:**

```php
<?php
/** Registrar la respuesta del cliente y, si acepta, cerrar: cliente, bienvenida y contrato. */
if (!defined('ABSPATH')) {
	exit;
}

function at_cc_propuesta_por_codigo(string $codigo): ?object {
	global $wpdb;
	$codigo = trim($codigo);
	if ($codigo === '' || strlen($codigo) > 50) {
		return null;
	}
	$p = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_propuestas WHERE unique_link_id = %s", $codigo));
	return $p ?: null;
}

function at_cc_propuesta_por_id(int $id): ?object {
	global $wpdb;
	$p = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $id));
	return $p ?: null;
}

function at_cc_filas_de_propuesta(object $p): array {
	return at_cc_filas_de_payload((string) $p->gamma_prompt_text);
}

/** Registro simple en Seguimiento; devuelve su id. */
function at_cc_anotar_simple(object $p, string $tipo, string $titulo, string $descripcion = ''): int {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas_details', [
		'propuesta_id'   => (int) $p->id,
		'detail_type'    => $tipo,
		'title'          => mb_substr($titulo, 0, 250),
		'description'    => $descripcion,
		'status'         => 'completed',
		'completed_date' => current_time('Y-m-d'),
		'created_by'     => get_current_user_id() ?: null,
		'created_at'     => current_time('mysql'),
	]);
	return (int) $wpdb->insert_id;
}

/** Registro de la respuesta en Seguimiento, con todo lo que prueba qué y cómo respondió. */
function at_cc_anotar_respuesta(object $p, string $salida, array $d, bool $cambio_estado): int {
	global $wpdb;
	$titulos = ['acepta' => 'Aceptó la propuesta', 'evalua' => 'La sigue evaluando', 'rechaza' => 'No aceptó la propuesta'];
	$lineas = ['Cómo: ' . at_cc_canal_texto($d)];
	if (!empty($d['nombre'])) {
		$lineas[] = 'Nombre: ' . $d['nombre'];
	}
	if (!empty($d['rut'])) {
		$lineas[] = 'RUT: ' . $d['rut'];
	}
	if (!empty($d['filas'])) {
		$lineas[] = 'Acepta: ' . implode('; ', array_map(function ($f) { return trim(($f['service'] ?? '') . ' · ' . ($f['price_label'] ?? '')); }, $d['filas']));
	}
	if (!empty($d['comentario'])) {
		$lineas[] = 'Comentario: ' . $d['comentario'];
	}
	if (!$cambio_estado) {
		$lineas[] = '(Sin cambio de estado)';
	}
	$meta = [
		'salida' => $salida, 'canal' => (string) ($d['canal'] ?? ''), 'canal_manual' => (string) ($d['canal_manual'] ?? ''),
		'nombre' => (string) ($d['nombre'] ?? ''), 'rut' => (string) ($d['rut'] ?? ''), 'filas' => (array) ($d['filas'] ?? []),
		'fecha_declarada' => (string) ($d['fecha'] ?? ''), 'ip' => (string) ($d['ip'] ?? ''), 'agente' => (string) ($d['agente'] ?? ''),
		'telefono' => (string) ($d['telefono'] ?? ''), 'wamid' => (string) ($d['wamid'] ?? ''),
		'huella' => at_cc_huella((string) $p->gamma_prompt_text), 'evidencias' => (array) ($d['evidencias'] ?? []),
		'cambio_estado' => $cambio_estado, 'registrado_por' => (int) ($d['usuario_id'] ?? 0),
	];
	$ev = $meta['evidencias'][0] ?? null;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas_details', [
		'propuesta_id'    => (int) $p->id,
		'detail_type'     => 'respuesta_cliente',
		'title'           => $titulos[$salida] ?? $salida,
		'description'     => implode("\n", $lineas),
		'status'          => 'completed',
		'completed_date'  => substr((string) ($d['fecha'] ?? current_time('mysql')), 0, 10),
		'attachment_url'  => $ev['url'] ?? null,
		'attachment_name' => $ev['nombre'] ?? null,
		'attachment_type' => $ev['tipo'] ?? null,
		'metadata'        => wp_json_encode($meta, JSON_UNESCAPED_UNICODE),
		'created_by'      => ((int) ($d['usuario_id'] ?? 0)) ?: null,
		'created_at'      => current_time('mysql'),
	]);
	return (int) $wpdb->insert_id;
}

/** Última respuesta del cliente registrada en Seguimiento. */
function at_cc_ultima_respuesta(int $propuesta_id): ?array {
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare(
		"SELECT title, created_at, metadata FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente' ORDER BY id DESC LIMIT 1",
		$propuesta_id
	));
	if (!$f) {
		return null;
	}
	$m = json_decode((string) $f->metadata, true) ?: [];
	return [
		'titulo'     => (string) $f->title,
		'fecha'      => (string) $f->created_at,
		'salida'     => (string) ($m['salida'] ?? ''),
		'filas'      => (array) ($m['filas'] ?? []),
		'evidencias' => (array) ($m['evidencias'] ?? []),
	];
}

/** Registra la respuesta y, si acepta, ejecuta el cierre. Idempotente frente a una segunda aceptación. */
function at_cc_registrar_respuesta(object $p, string $salida, array $d): array {
	global $wpdb;
	$base = ['ok' => false, 'estado' => (string) $p->status, 'mensaje' => '', 'avisos' => [], 'crm_id' => null, 'contrato_id' => null];
	$salidas = at_cc_salidas();
	if (!isset($salidas[$salida])) {
		return array_merge($base, ['mensaje' => 'Respuesta no válida.']);
	}
	$hacia = $salidas[$salida];
	$desde = (string) $p->status;
	if ($desde === 'aceptada') {
		return array_merge($base, ['ok' => true, 'mensaje' => 'ya_aceptada']);
	}
	if ($desde === $hacia) {
		at_cc_anotar_respuesta($p, $salida, $d, false);
		return array_merge($base, ['ok' => true, 'mensaje' => 'anotada']);
	}
	$manual = ($d['canal'] ?? '') === 'manual';
	if (!at_cc_transicion_respuesta_valida($desde, $hacia, $manual)) {
		return array_merge($base, ['mensaje' => 'Esta propuesta no está esperando respuesta.']);
	}
	$n = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}automatiza_propuestas SET status = %s WHERE id = %d AND status = %s", $hacia, (int) $p->id, $desde));
	if ($n !== 1) {
		return array_merge($base, ['mensaje' => 'La propuesta cambió mientras respondías.']);
	}
	$p->status = $hacia;
	at_cc_anotar_respuesta($p, $salida, $d, true);
	$r = array_merge($base, ['ok' => true, 'estado' => $hacia, 'mensaje' => 'registrada']);
	if ($salida === 'acepta') {
		$r = array_merge($r, at_cc_ejecutar_cierre($p, $d));
	}
	at_cc_avisar_luis($p, $salida, $d, $r);
	return $r;
}

/** Cliente oficial, Seguimiento migrado, bienvenida y contrato. Cada paso que falla queda como aviso. */
function at_cc_ejecutar_cierre(object $p, array $d): array {
	$avisos = [];
	$filas = (array) ($d['filas'] ?? []);
	$cli = at_cc_asegurar_cliente([
		'nombre'         => trim((string) ($d['nombre'] ?? '')) !== '' ? (string) $d['nombre'] : (string) $p->client_name,
		'email'          => (string) $p->client_email,
		'empresa'        => (string) $p->company_name,
		'telefono'       => (string) $p->phone,
		'origen'         => 'propuesta_aceptada',
		'valor'          => at_cc_total_unico($filas),
		'servicios'      => implode(' + ', array_map(function ($f) { return trim((string) ($f['service'] ?? '')); }, $filas)),
		'fecha_contrato' => (string) ($d['fecha'] ?? current_time('mysql')),
	]);
	if (is_wp_error($cli)) {
		return ['avisos' => ['No se pudo pasar a cliente: ' . $cli->get_error_message()], 'crm_id' => null, 'contrato_id' => null];
	}
	if (function_exists('automatiza_migrate_prospect_to_client')) {
		automatiza_migrate_prospect_to_client($cli['tech_id'], (int) $p->id);
	}
	at_cc_historial_crm($cli['crm_id'], 'conversion', 'Aceptó la propuesta', 'Propuesta ' . $p->unique_link_id . ' aceptada ' . at_cc_canal_texto($d) . '.');
	if (!empty($d['bienvenida']) && !at_cc_enviar_bienvenida($cli['crm_id'], $p, $filas)) {
		$avisos[] = 'El correo de bienvenida no salió (revisa el SMTP).';
	}
	$contrato = at_cc_crear_contrato_servicios($p, $cli['tech_id'], $filas, [
		'nombre'      => (string) ($d['nombre'] ?? ''),
		'rut'         => (string) ($d['rut'] ?? ''),
		'fecha'       => (string) ($d['fecha'] ?? current_time('mysql')),
		'canal_texto' => at_cc_canal_texto($d),
	]);
	$contrato_id = null;
	if (is_wp_error($contrato)) {
		$avisos[] = 'El contrato no se creó: ' . $contrato->get_error_message();
	} else {
		$contrato_id = $contrato;
	}
	return ['avisos' => $avisos, 'crm_id' => $cli['crm_id'], 'contrato_id' => $contrato_id];
}

/** Aviso a Luis por correo (WhatsApp a Luis no llega: 131047). No se avisa lo que él mismo registró. */
function at_cc_avisar_luis(object $p, string $salida, array $d, array $r): void {
	if (($d['canal'] ?? '') === 'manual') {
		return;
	}
	$titulos = ['acepta' => 'aceptó la propuesta ✅', 'evalua' => 'la sigue evaluando 🤔', 'rechaza' => 'no aceptó la propuesta ❌'];
	$quien = trim((string) $p->company_name) !== '' ? (string) $p->company_name : (string) $p->client_name;
	$lineas = ['Cómo: ' . at_cc_canal_texto($d)];
	foreach (['nombre' => 'Nombre', 'rut' => 'RUT', 'comentario' => 'Comentario'] as $k => $t) {
		if (!empty($d[$k])) {
			$lineas[] = $t . ': ' . $d[$k];
		}
	}
	if (!empty($d['filas'])) {
		$lineas[] = 'Acepta: ' . implode('; ', array_map(function ($f) { return trim(($f['service'] ?? '') . ' · ' . ($f['price_label'] ?? '')); }, $d['filas']));
	}
	foreach ((array) ($r['avisos'] ?? []) as $a) {
		$lineas[] = '⚠️ ' . $a;
	}
	$html = '<p>' . implode('<br>', array_map('esc_html', $lineas)) . '</p>'
		. '<p><a href="' . esc_url(admin_url('admin.php?page=automatiza-proposals&edit_id=' . (int) $p->id . '&tab=envio')) . '">Abrir la propuesta en el panel</a></p>';
	$c = !empty($r['contrato_id']) ? at_cc_contrato_de_propuesta((int) $p->id) : null;
	if ($c && in_array($c->status, ['draft', 'at_pending'], true)) {
		$html .= '<p><a href="' . esc_url(home_url('/contracts/at-sign-contract.php?token=' . $c->at_review_token)) . '">Revisar, ajustar y firmar el contrato</a></p>';
	}
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	wp_mail((string) get_option('admin_email'), $quien . ' ' . ($titulos[$salida] ?? $salida), $html, ['Content-Type: text/html; charset=UTF-8', 'From: Automatiza Tech <' . $from . '>']);
}

/** Correo corto que pide la respuesta, con el bloque de aceptar. */
function at_cc_enviar_pedido_respuesta(object $p): bool {
	if (!is_email((string) $p->client_email)) {
		return false;
	}
	$base = get_site_url();
	$bloque = at_cc_bloque_aceptar_html(at_cc_url_respuesta($base, (string) $p->unique_link_id, 'aceptar'), at_cc_url_respuesta($base, (string) $p->unique_link_id, 'evaluar'));
	$html = at_cc_pedido_respuesta_html((string) $p->client_name, (string) $p->company_name, $bloque, AT_CC_LOGO, at_cc_url_respuesta($base, (string) $p->unique_link_id));
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	$asunto = 'Tu propuesta de AutomatizaTech' . (trim((string) $p->company_name) !== '' ? ' para ' . $p->company_name : '');
	return (bool) wp_mail((string) $p->client_email, $asunto, $html, [
		'Content-Type: text/html; charset=UTF-8',
		'From: Automatiza Tech <' . $from . '>',
		'Reply-To: ' . get_option('admin_email'),
		'Bcc: automatizacionesbotcore@gmail.com',
	]);
}
```

- [ ] **Step 7: Cargar los archivos** en `cargar.php`, después de `require_once __DIR__ . '/clientes.php';`:

```php
require_once __DIR__ . '/contrato.php';
require_once __DIR__ . '/ajustes.php';
require_once __DIR__ . '/bienvenida.php';
require_once __DIR__ . '/respuesta.php';
```

- [ ] **Step 8: Correr todo.**
  Run:
  ```bash
  AT_WP_LOAD=<ruta> "$PHP" tests/cierre/cierre-wp-test.php
  AT_WP_LOAD=<ruta> "$PHP" tests/cierre/clientes-wp-test.php && AT_WP_LOAD=<ruta> "$PHP" tests/cierre/contrato-wp-test.php
  "$PHP" tests/cierre/puras-test.php && "$PHP" tests/cierre/plantilla-test.php
  for f in contrato ajustes bienvenida respuesta cargar; do "$PHP" -l wp-content/themes/automatiza-tech/inc/cierre-cliente/$f.php; done
  ```
  Expected: `TODO OK` en las cinco pruebas y sintaxis OK. Si una prueba deja filas por un fallo a mitad de camino, borrarlas en la base local (marca `prueba-cierre-`) antes de repetir.

- [ ] **Step 9: Commit.**

```bash
git add wp-content/themes/automatiza-tech/inc/cierre-cliente/contrato.php wp-content/themes/automatiza-tech/inc/cierre-cliente/ajustes.php wp-content/themes/automatiza-tech/inc/cierre-cliente/bienvenida.php wp-content/themes/automatiza-tech/inc/cierre-cliente/respuesta.php wp-content/themes/automatiza-tech/inc/cierre-cliente/cargar.php tests/cierre/cierre-wp-test.php
git commit -m "feat(cierre): al aceptar pasa a cliente, recibe la bienvenida y se arma el contrato de servicios" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

#### Ajuste del controlador (hallazgo de la revisión de la Task 4) — forma parte de esta tarea

El CRM llama a `_enviar_correo_bienvenida()` también cuando se crea un **prospecto** con la casilla «Enviar correo de bienvenida» marcada (formulario «Nuevo Cliente / Prospecto» de `crm-ai-completo.php`). Desde la Task 4 esa función delega en `at_cc_enviar_bienvenida()`, así que, sin este ajuste, un prospecto recibiría la bienvenida de cliente (anticipo, datos de transferencia, reunión de inicio). La bienvenida nueva es solo para clientes.

1. En `at_cc_enviar_bienvenida()` (bienvenida.php), la consulta pasa a leer también `tipo`, y la función devuelve `false` sin enviar nada si el registro no es cliente:

```php
	$c = $wpdb->get_row($wpdb->prepare("SELECT id, nombre, email, empresa, tipo FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm_id));
	if (!$c || !is_email((string) $c->email) || (string) $c->tipo !== 'cliente') {
		return false;
	}
```

   (Reemplaza las dos primeras sentencias de la función que trae el código de arriba. En el cierre automático no cambia nada: `at_cc_asegurar_cliente()` deja el registro como `cliente` antes de llamar a la bienvenida.)

2. En `wp-content/mu-plugins/crm-ai-completo.php` (archivo CRLF: mide CR y LF antes y después con PHP), en `_enviar_correo_bienvenida()`, reemplazar el bloque que dejó la Task 4:

```php
        if (function_exists('at_cc_enviar_bienvenida')) {
            at_cc_enviar_bienvenida((int) $cliente_id);
            return;
        }
```

   por:

```php
        // Solo clientes: si es prospecto (o no se pudo enviar), sigue la bienvenida de siempre.
        if (function_exists('at_cc_enviar_bienvenida') && at_cc_enviar_bienvenida((int) $cliente_id)) {
            return;
        }
```

   Confirma con `grep -cF` que `at_cc_enviar_bienvenida((int) $cliente_id);` aparece una sola vez antes de editar.

3. En `tests/cierre/cierre-wp-test.php`, antes del `fin();` final, agregar (usa el `$correos` que ya captura la prueba):

```php
$antes = count($correos);
$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => 'Prospecto Prueba', 'email' => 'prueba-cierre-prospecto@example.com', 'tipo' => 'prospecto']);
$prospecto = (int) $wpdb->insert_id;
ok(at_cc_enviar_bienvenida($prospecto) === false && count($correos) === $antes, 'un prospecto no recibe la bienvenida de cliente');
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $prospecto));
```

4. Agregar `wp-content/mu-plugins/crm-ai-completo.php` al `git add` del commit de esta tarea.

---

### Task 7: Página pública de respuesta (`pagina.php`, `ver-presentacion.php`)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/cierre-cliente/pagina.php`
- Modify: `wp-content/themes/automatiza-tech/inc/cierre-cliente/cargar.php`
- Modify: `ver-presentacion.php` (raíz)

**Interfaces:**
- Consumes: Task 1 y Task 6 (`at_cc_propuesta_por_codigo`, `at_cc_filas_de_propuesta`, `at_cc_registrar_respuesta`).
- Produces: `at_cc_render_barra(object $p): void`, acción pública `admin-post.php?action=at_cc_responder` (POST: `codigo`, `salida`, `nombre`, `rut`, `filas[]`, `acepto`, `comentario`, `_wpnonce` de la acción `at_cc_responder_<codigo>`, trampa `sitio_web`), `at_cc_ip(): string`, `at_cc_limite_ip_ok(string $accion, int $max, int $segundos): bool`. Redirige a `ver-presentacion.php?id=<código>&respuesta=<clave de at_cc_mensaje_respuesta>`.

- [ ] **Step 1: `pagina.php`:**

```php
<?php
/** Barra de respuesta en la página pública de la propuesta y su procesamiento. */
if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_post_nopriv_at_cc_responder', 'at_cc_procesar_respuesta_publica');
add_action('admin_post_at_cc_responder', 'at_cc_procesar_respuesta_publica');

function at_cc_ip(): string {
	foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
		if (empty($_SERVER[$k])) {
			continue;
		}
		$ip = trim(explode(',', (string) $_SERVER[$k])[0]);
		if (filter_var($ip, FILTER_VALIDATE_IP)) {
			return $ip;
		}
	}
	return '';
}

/** Límite suave de intentos por IP y acción. */
function at_cc_limite_ip_ok(string $accion, int $max, int $segundos): bool {
	$clave = 'at_cc_lim_' . md5($accion . '|' . at_cc_ip());
	$n = (int) get_transient($clave);
	if ($n >= $max) {
		return false;
	}
	set_transient($clave, $n + 1, $segundos);
	return true;
}

function at_cc_procesar_respuesta_publica(): void {
	$codigo = sanitize_text_field(wp_unslash($_POST['codigo'] ?? ''));
	$volver = function (string $clave) use ($codigo): void {
		wp_safe_redirect(home_url('/ver-presentacion.php?id=' . rawurlencode($codigo) . '&respuesta=' . rawurlencode($clave)));
		exit;
	};
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		$volver('error');
	}
	if (!empty($_POST['sitio_web'])) {
		$volver('recibida');
	}
	if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'at_cc_responder_' . $codigo)) {
		$volver('vencida');
	}
	if (!at_cc_limite_ip_ok('responder', 10, HOUR_IN_SECONDS)) {
		$volver('limite');
	}
	$p = at_cc_propuesta_por_codigo($codigo);
	if (!$p) {
		$volver('recibida');
	}
	$salida = sanitize_key(wp_unslash($_POST['salida'] ?? ''));
	$d = [
		'canal'      => 'pagina',
		'nombre'     => sanitize_text_field(wp_unslash($_POST['nombre'] ?? '')),
		'comentario' => sanitize_textarea_field(wp_unslash($_POST['comentario'] ?? '')),
		'ip'         => at_cc_ip(),
		'agente'     => mb_substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 300),
		'fecha'      => current_time('mysql'),
		'bienvenida' => true,
		'usuario_id' => get_current_user_id(),
	];
	if ($salida === 'acepta') {
		$rut = sanitize_text_field(wp_unslash($_POST['rut'] ?? ''));
		$todas = at_cc_filas_de_propuesta($p);
		$d['filas'] = at_cc_filas_aceptadas($todas, array_map('sanitize_text_field', (array) wp_unslash($_POST['filas'] ?? [])));
		if (mb_strlen(trim($d['nombre'])) < 3 || !at_cc_rut_valido($rut) || empty($_POST['acepto']) || ($todas && !$d['filas'])) {
			$volver('datos');
		}
		$d['rut'] = at_cc_rut_formato($rut);
	}
	$r = at_cc_registrar_respuesta($p, $salida, $d);
	$volver($r['ok'] ? $r['estado'] : 'error');
}

function at_cc_render_barra(object $p): void {
	$estado = (string) $p->status;
	if (!at_cc_puede_pedir_respuesta($estado) && $estado !== 'aceptada') {
		return;
	}
	$codigo = (string) $p->unique_link_id;
	$msg = at_cc_mensaje_respuesta(sanitize_key(wp_unslash($_GET['respuesta'] ?? '')));
	$abrir = ['aceptar' => 'at-cc-acepta', 'evaluar' => 'at-cc-evalua'][sanitize_key(wp_unslash($_GET['responder'] ?? ''))] ?? '';
	$filas = at_cc_filas_de_propuesta($p);
	$accion = admin_url('admin-post.php');
	$nonce = wp_create_nonce('at_cc_responder_' . $codigo);
	$ocultos = function (string $salida) use ($codigo, $nonce): string {
		return '<input type="hidden" name="action" value="at_cc_responder">'
			. '<input type="hidden" name="salida" value="' . esc_attr($salida) . '">'
			. '<input type="hidden" name="codigo" value="' . esc_attr($codigo) . '">'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">'
			. '<div class="at-cc-trampa" aria-hidden="true"><label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>';
	};
	?>
<style>
html,body{height:100%}
body{display:flex;flex-direction:column}
body>iframe{flex:1 1 auto;height:auto;min-height:0}
.at-cc-barra{flex:0 0 auto;background:#0f172a;color:#f8fafc;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;padding:10px 16px;display:flex;gap:10px;align-items:center;justify-content:center;flex-wrap:wrap}
.at-cc-barra p{margin:0;font-size:15px}
.at-cc-btn{border:0;border-radius:999px;padding:11px 20px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit}
.at-cc-si{background:#10b981;color:#052e16}
.at-cc-sec{background:#1e293b;color:#e2e8f0;border:1px solid #334155}
.at-cc-msg{width:100%;text-align:center;padding:8px 12px;border-radius:8px;font-size:14px}
.at-cc-msg--ok{background:#064e3b;color:#d1fae5}.at-cc-msg--aviso{background:#78350f;color:#fef3c7}.at-cc-msg--error{background:#7f1d1d;color:#fee2e2}
dialog.at-cc-dlg{border:0;border-radius:14px;padding:0;max-width:440px;width:calc(100% - 32px);font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#0f172a}
dialog.at-cc-dlg::backdrop{background:rgba(15,23,42,.7)}
.at-cc-dlg form{padding:22px}
.at-cc-dlg h2{margin:0 0 6px;font-size:19px}
.at-cc-dlg p{margin:6px 0;font-size:14px;color:#334155}
.at-cc-dlg label{display:block;margin:12px 0 4px;font-size:14px;font-weight:600}
.at-cc-dlg input[type=text],.at-cc-dlg textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:15px;font-family:inherit}
.at-cc-dlg label.at-cc-fila{display:flex;gap:8px;align-items:flex-start;font-weight:400;margin:6px 0}
.at-cc-dlg .at-cc-acciones{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;flex-wrap:wrap}
.at-cc-trampa{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
@media (max-width:600px){.at-cc-barra{padding:8px 10px;gap:8px}.at-cc-btn{padding:10px 14px;font-size:14px}}
</style>
<div class="at-cc-barra" role="region" aria-label="Responder la propuesta">
	<?php if ($msg): ?><div class="at-cc-msg at-cc-msg--<?php echo esc_attr($msg['tipo']); ?>" role="status"><?php echo esc_html($msg['texto']); ?></div><?php endif; ?>
	<?php if ($estado === 'aceptada'): ?>
		<p>✅ Esta propuesta ya está aceptada. Te escribimos con los próximos pasos.</p>
	<?php else: ?>
		<button type="button" class="at-cc-btn at-cc-si" data-abrir="at-cc-acepta">Acepto la propuesta</button>
		<button type="button" class="at-cc-btn at-cc-sec" data-abrir="at-cc-evalua">La sigo evaluando</button>
		<button type="button" class="at-cc-btn at-cc-sec" data-abrir="at-cc-rechaza">No, gracias</button>
	<?php endif; ?>
</div>
<?php if ($estado !== 'aceptada'): ?>
<dialog class="at-cc-dlg" id="at-cc-acepta">
	<form method="post" action="<?php echo esc_url($accion); ?>">
		<?php echo $ocultos('acepta'); ?>
		<h2>Aceptar la propuesta</h2>
		<p>Con tu aceptación te enviamos el contrato para firmar y los primeros pasos para partir.</p>
		<?php if ($filas): ?>
			<p><strong>¿Qué aceptas?</strong></p>
			<?php foreach ($filas as $i => $f): ?>
				<label class="at-cc-fila"><input type="checkbox" name="filas[]" value="<?php echo (int) $i; ?>"<?php echo $i === 0 ? ' checked' : ''; ?>> <span><?php echo esc_html(trim(($f['service'] ?? '') . ' · ' . ($f['price_label'] ?? ''))); ?></span></label>
			<?php endforeach; ?>
		<?php endif; ?>
		<label for="at-cc-nombre">Tu nombre completo</label>
		<input type="text" id="at-cc-nombre" name="nombre" required minlength="3" autocomplete="name" value="<?php echo esc_attr((string) $p->client_name); ?>">
		<label for="at-cc-rut">Tu RUT</label>
		<input type="text" id="at-cc-rut" name="rut" required placeholder="12.345.678-9" autocomplete="off">
		<label class="at-cc-fila"><input type="checkbox" name="acepto" value="1" required> <span>Acepto la propuesta y sus condiciones.</span></label>
		<div class="at-cc-acciones">
			<button type="button" class="at-cc-btn at-cc-sec" data-cerrar>Volver</button>
			<button type="submit" class="at-cc-btn at-cc-si">Confirmar aceptación</button>
		</div>
	</form>
</dialog>
<dialog class="at-cc-dlg" id="at-cc-evalua">
	<form method="post" action="<?php echo esc_url($accion); ?>">
		<?php echo $ocultos('evalua'); ?>
		<h2>La sigo evaluando</h2>
		<label for="at-cc-com-ev">¿Qué te falta o qué dudas tienes? (opcional)</label>
		<textarea id="at-cc-com-ev" name="comentario" rows="4"></textarea>
		<div class="at-cc-acciones">
			<button type="button" class="at-cc-btn at-cc-sec" data-cerrar>Volver</button>
			<button type="submit" class="at-cc-btn at-cc-si">Enviar</button>
		</div>
	</form>
</dialog>
<dialog class="at-cc-dlg" id="at-cc-rechaza">
	<form method="post" action="<?php echo esc_url($accion); ?>">
		<?php echo $ocultos('rechaza'); ?>
		<h2>No, gracias</h2>
		<label for="at-cc-com-re">¿Nos cuentas por qué? (opcional)</label>
		<textarea id="at-cc-com-re" name="comentario" rows="4"></textarea>
		<div class="at-cc-acciones">
			<button type="button" class="at-cc-btn at-cc-sec" data-cerrar>Volver</button>
			<button type="submit" class="at-cc-btn at-cc-si">Enviar respuesta</button>
		</div>
	</form>
</dialog>
<script>
(function () {
	function abrir(id) { var d = document.getElementById(id); if (d && d.showModal) { d.showModal(); } }
	document.querySelectorAll('[data-abrir]').forEach(function (b) { b.addEventListener('click', function () { abrir(b.getAttribute('data-abrir')); }); });
	document.querySelectorAll('dialog.at-cc-dlg [data-cerrar]').forEach(function (b) { b.addEventListener('click', function () { b.closest('dialog').close(); }); });
	var inicial = <?php echo wp_json_encode($abrir); ?>;
	if (inicial) { abrir(inicial); }
})();
</script>
<?php endif;
}
```

- [ ] **Step 2: Cargarlo** en `cargar.php`, después de `require_once __DIR__ . '/respuesta.php';`:

```php
require_once __DIR__ . '/pagina.php';
```

- [ ] **Step 3: `ver-presentacion.php`** (CRLF, Edit):
  - Después de la línea `$iframe_url = $proposal->gamma_iframe_url;`, agregar:

```php

// La página lleva formularios con nonce: nunca se guarda en caché.
if (!defined('DONOTCACHEPAGE')) {
    define('DONOTCACHEPAGE', true);
}
nocache_headers();
```

  - Reemplazar la línea `</body>` por:

```php
<?php if (function_exists('at_cc_render_barra')) { at_cc_render_barra($proposal); } ?>
</body>
```

- [ ] **Step 4: Verificar.**
  Run:
  ```bash
  "$PHP" -l wp-content/themes/automatiza-tech/inc/cierre-cliente/pagina.php && "$PHP" -l ver-presentacion.php
  f=ver-presentacion.php; echo "$(wc -l < $f) $(grep -c $'\r' $f)"
  AT_WP_LOAD=<ruta> "$PHP" tests/cierre/cierre-wp-test.php
  ```
  Expected: sintaxis OK, CR en todas las líneas, `TODO OK`. El recorrido real por HTTP (aceptar, evaluar, rechazar, nonce vencido, trampa, GET) lo hace el controlador en la tarea 11.

- [ ] **Step 5: Commit.**

```bash
git add wp-content/themes/automatiza-tech/inc/cierre-cliente/pagina.php wp-content/themes/automatiza-tech/inc/cierre-cliente/cargar.php ver-presentacion.php
git commit -m "feat(cierre): el cliente acepta, evalúa o rechaza desde la página de la propuesta" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

#### Ajuste del controlador (aprobado por Luis el 26-sep) — forma parte de esta tarea: «Datos para tu contrato»

Justo después de aceptar, la página ofrece un formulario **opcional** para saber a nombre de quién va el contrato. Aceptar sigue pidiendo solo nombre, RUT y la casilla. Usa lo que dejó la Task 5b: `ContractService::actualizar_datos_cliente($contract_id, $datos)` (guarda `tipo_cliente`, `razon_social_cliente`, `rut_cliente`, `domicilio_cliente` sin marcar la revisión; `WP_Error('ya_revisado')` si Luis ya revisó; si el tipo es `persona`, toma solos el nombre y el RUT de quien aceptó) y `at_cc_contrato_de_propuesta(int $propuesta_id): ?object` (Task 6). Si la clase `ContractService` no está cargada, `require_once ABSPATH . 'contracts/contract-service.php';`.

1. **Función testeable** en `pagina.php`: `at_cc_guardar_datos_contrato(object $p, array $post): string` devuelve una clave de mensaje:
   - La propuesta debe estar `aceptada` y tener contrato (`at_cc_contrato_de_propuesta`); si no → `'recibida'`.
   - `tipo` = `persona` | `empresa` (otro valor → `'datos_contrato'`). `direccion` obligatoria (recortada, máx. 300). Si `empresa`: `razon_social` obligatoria (máx. 200) y `rut_empresa` válido con `at_cc_rut_valido()` y guardado con `at_cc_rut_formato()`; si falta algo → `'datos_contrato'`.
   - Llama a `ContractService::actualizar_datos_cliente($c->id, ['tipo_cliente' => $tipo, 'domicilio_cliente' => $direccion] + (empresa ? ['razon_social_cliente' => $razon, 'rut_cliente' => $rut] : []))`.
   - Ficha operativa (`wp_automatiza_tech_clients`, fila `id = $c->client_id`): completa **solo los campos vacíos**: `billing_address` = dirección; `tax_id` = RUT de la empresa, o, si es persona, el RUT de quien aceptó (marcador `representante_cliente_rut` del contrato); `company` = razón social solo si es empresa. `$wpdb->update` con formatos.
   - Siempre anota en Seguimiento con `at_cc_anotar_simple($p, 'respuesta_cliente', 'Datos para el contrato', <tipo, razón social, RUT y dirección en líneas>)`, se hayan aplicado o no.
   - Devuelve `'datos_ok'` si se guardó en el contrato, o `'datos_recibidos'` si el contrato ya tenía la revisión de Luis (`ya_revisado`).
2. **Acción pública** `admin-post.php?action=at_cc_datos_contrato` (con `nopriv`), mismo patrón que `at_cc_procesar_respuesta_publica`: solo POST, trampa `sitio_web` → `'recibida'`, nonce de la acción `at_cc_datos_contrato_<codigo>` → `'vencida'`, `at_cc_limite_ip_ok('datos_contrato', 10, HOUR_IN_SECONDS)` → `'limite'`; luego `at_cc_guardar_datos_contrato()` y redirige a `ver-presentacion.php?id=<código>&respuesta=<clave>`.
3. **En la barra**, cuando la propuesta está `aceptada` y su contrato admite datos del cliente (existe, es `servicios`, está en `draft`/`at_pending` y sin `revision_at` en sus marcadores): junto a «✅ Esta propuesta ya está aceptada…», un botón secundario «Datos para tu contrato» que abre el diálogo `at-cc-datos`. El diálogo se abre solo cuando la página llega con `respuesta=aceptada`. Textos exactos:
   - Título: «Datos para tu contrato»
   - Texto: «Opcional: si nos dejas estos datos ahora, tu contrato llega listo para firmar.»
   - Pregunta «¿A nombre de quién va el contrato?» con dos opciones: «A mi nombre (persona natural)» (`persona`, marcada) y «De una empresa» (`empresa`).
   - Solo si es empresa (mostrar/ocultar con el mismo JS de la barra; el servidor valida igual): «Razón social de la empresa» y «RUT de la empresa».
   - Siempre: «Dirección (calle, número, comuna y ciudad)».
   - Botones: «Guardar datos» y «Ahora no».
   - Ocultos: `action=at_cc_datos_contrato`, `codigo`, `_wpnonce`, trampa `sitio_web` (reutilizar el patrón de `$ocultos`).
4. **Mensajes nuevos** en `at_cc_mensaje_respuesta()` (puras.php), con prueba en `puras-test.php`:
   - `'datos_ok'` => `['ok', '¡Listo! Con estos datos preparamos tu contrato.']`
   - `'datos_recibidos'` => `['ok', 'Recibimos tus datos. Luis los revisa junto con tu contrato.']`
   - `'datos_contrato'` => `['aviso', 'Revisa los datos del contrato: la dirección y, si es una empresa, su razón social y un RUT válido.']`
5. **Prueba** `tests/cierre/pagina-datos-wp-test.php` (patrón de las demás `*-wp-test.php`: `wp-bootstrap.php`, `pre_wp_mail` capturado, datos `prueba-cierre-` que se borran al final): crear una propuesta, aceptarla con `at_cc_registrar_respuesta` (canal `pagina`, nombre y RUT `11.111.111-1`) para que existan cliente, ficha operativa y contrato; luego:
   - persona + dirección → `'datos_ok'`; el contrato queda con `tipo_cliente=persona`, `razon_social_cliente` = nombre de quien aceptó, `rut_cliente` = su RUT, `domicilio_cliente` = la dirección, y **sin** `revision_at`; la ficha operativa recibe `billing_address` y `tax_id` si estaban vacíos.
   - empresa sin RUT válido → `'datos_contrato'` y nada cambia.
   - empresa completa → `'datos_ok'`, `company` de la ficha se llena solo si estaba vacío (probar que no pisa un valor existente).
   - con la revisión de Luis ya guardada (`ContractService::guardar_revision`) → `'datos_recibidos'` y el contrato no cambia, pero queda la nota en Seguimiento.
   - propuesta no aceptada → `'recibida'`.
6. Agregar `wp-content/themes/automatiza-tech/inc/cierre-cliente/puras.php`, `tests/cierre/puras-test.php` y `tests/cierre/pagina-datos-wp-test.php` al commit de esta tarea.

---

### Task 8: Panel — respuesta del cliente, «Pedir respuesta» y «Registrar aceptación» con evidencia

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/cierre-cliente/panel.php`
- Modify: `wp-content/themes/automatiza-tech/inc/cierre-cliente/cargar.php`
- Modify: `wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php`

**Interfaces:**
- Consumes: Task 1, Task 6 (`at_cc_registrar_respuesta`, `at_cc_enviar_pedido_respuesta`, `at_cc_anotar_simple`, `at_cc_ultima_respuesta`, `at_cc_contrato_de_propuesta`), `at_pa_estado_etiqueta()` y `at_pa_fecha_corta()` (consultas.php).
- Produces: `at_cc_render_panel_respuesta(object $p): void` (va dentro del formulario de la ficha), `at_cc_render_formularios_respuesta(object $p): void` (después del formulario), `at_cc_aviso_panel(int $propuesta_id): string`, acciones `admin_post_at_cc_pedir_respuesta`, `admin_post_at_cc_registrar_aceptacion`, `wp_ajax_at_cc_ver_evidencia`. La Task 10 agrega el WhatsApp automático en `at_cc_accion_pedir_respuesta` a través de `at_cc_whatsapp_tras_pedido()` si existe.

- [ ] **Step 1: `panel.php`:**

```php
<?php
/** Bloque «Respuesta del cliente» de la ficha de la propuesta y sus acciones. */
if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_post_at_cc_pedir_respuesta', 'at_cc_accion_pedir_respuesta');
add_action('admin_post_at_cc_registrar_aceptacion', 'at_cc_accion_registrar_aceptacion');
add_action('wp_ajax_at_cc_ver_evidencia', 'at_cc_ver_evidencia');

function at_cc_url_ficha_propuesta(int $id): string {
	return add_query_arg(['page' => 'automatiza-proposals', 'edit_id' => $id, 'tab' => 'envio'], admin_url('admin.php'));
}

function at_cc_guardar_aviso(array $a): void {
	set_transient('at_cc_aviso_' . get_current_user_id(), $a, 120);
}

/** Aviso de la última acción del bloque, una sola vez. */
function at_cc_aviso_panel(int $propuesta_id): string {
	$clave = 'at_cc_aviso_' . get_current_user_id();
	$a = get_transient($clave);
	if (!is_array($a) || (int) ($a['propuesta_id'] ?? 0) !== $propuesta_id) {
		return '';
	}
	delete_transient($clave);
	$clase = ['ok' => 'notice-success', 'aviso' => 'notice-warning'][$a['tipo'] ?? ''] ?? 'notice-error';
	$html = '<div class="notice ' . $clase . ' is-dismissible"><p>' . esc_html((string) ($a['texto'] ?? '')) . '</p>';
	foreach ((array) ($a['detalles'] ?? []) as $d) {
		$html .= '<p>• ' . esc_html((string) $d) . '</p>';
	}
	return $html . '</div>';
}

/** Carpeta privada de evidencias (acceso directo bloqueado, como la de contratos). */
function at_cc_dir_evidencias(): string {
	$up = wp_upload_dir();
	$dir = trailingslashit($up['basedir']) . 'automatiza-tech-evidencias';
	if (!file_exists($dir)) {
		wp_mkdir_p($dir);
	}
	if (!file_exists($dir . '/.htaccess')) {
		@file_put_contents($dir . '/.htaccess', "Order deny,allow\nDeny from all\nOptions -Indexes\n");
	}
	if (!file_exists($dir . '/index.php')) {
		@file_put_contents($dir . '/index.php', "<?php\n// Silencio.\n");
	}
	return $dir;
}

function at_cc_url_evidencia(int $propuesta_id, string $archivo): string {
	return add_query_arg(['action' => 'at_cc_ver_evidencia', 'p' => $propuesta_id, 'f' => $archivo], admin_url('admin-ajax.php'));
}

/** Guarda hasta 3 imágenes válidas con nombre aleatorio. */
function at_cc_guardar_evidencias(int $propuesta_id, array $archivos): array {
	$guardadas = [];
	$errores = [];
	if (count($archivos) > 3) {
		$errores[] = 'Solo se guardan 3 imágenes por aceptación; las demás se ignoraron.';
		$archivos = array_slice($archivos, 0, 3);
	}
	if (!$archivos) {
		return ['guardadas' => [], 'errores' => $errores];
	}
	$dir = at_cc_dir_evidencias() . '/' . $propuesta_id;
	wp_mkdir_p($dir);
	foreach ($archivos as $f) {
		$error = at_cc_error_evidencia($f);
		if ($error !== '') {
			$errores[] = sanitize_file_name((string) $f['name']) . ': ' . $error;
			continue;
		}
		$info = getimagesize((string) $f['tmp_name']);
		$nombre = bin2hex(random_bytes(12)) . '.' . at_cc_mimes_evidencia()[$info['mime']];
		if (!move_uploaded_file((string) $f['tmp_name'], $dir . '/' . $nombre)) {
			$errores[] = sanitize_file_name((string) $f['name']) . ': no se pudo guardar';
			continue;
		}
		$guardadas[] = ['url' => at_cc_url_evidencia($propuesta_id, $nombre), 'nombre' => sanitize_file_name((string) $f['name']), 'tipo' => $info['mime'], 'archivo' => $nombre];
	}
	return ['guardadas' => $guardadas, 'errores' => $errores];
}

/** Muestra una evidencia solo a un administrador con sesión. */
function at_cc_ver_evidencia(): void {
	if (!current_user_can('manage_options')) {
		wp_die('Acceso denegado.', '', ['response' => 403]);
	}
	$pid = (int) ($_GET['p'] ?? 0);
	$f = (string) wp_unslash($_GET['f'] ?? '');
	if ($pid <= 0 || !preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $f, $m)) {
		wp_die('No encontrado.', '', ['response' => 404]);
	}
	$ruta = at_cc_dir_evidencias() . '/' . $pid . '/' . $f;
	if (!is_file($ruta)) {
		wp_die('No encontrado.', '', ['response' => 404]);
	}
	nocache_headers();
	header('Content-Type: ' . array_search($m[1], at_cc_mimes_evidencia(), true));
	header('Content-Length: ' . filesize($ruta));
	header('X-Content-Type-Options: nosniff');
	if (ob_get_level()) {
		ob_end_clean();
	}
	readfile($ruta);
	exit;
}

function at_cc_accion_pedir_respuesta(): void {
	$id = (int) ($_POST['proposal_id'] ?? 0);
	check_admin_referer('at_cc_pedir_' . $id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$p = at_cc_propuesta_por_id($id);
	if (!$p || !at_cc_puede_pedir_respuesta((string) $p->status)) {
		at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => 'error', 'texto' => 'Esta propuesta no está esperando respuesta: se pide cuando ya fue enviada.']);
	} elseif (!is_email((string) $p->client_email)) {
		at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => 'error', 'texto' => 'La propuesta no tiene un correo válido del cliente.']);
	} else {
		$ok = at_cc_enviar_pedido_respuesta($p);
		at_cc_anotar_simple($p, 'propuesta_enviada', $ok ? 'Se le pidió la respuesta al cliente por correo' : 'Falló el correo para pedir la respuesta');
		$detalles = function_exists('at_cc_whatsapp_tras_pedido') ? at_cc_whatsapp_tras_pedido($p) : [];
		at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => $ok ? 'ok' : 'error', 'texto' => $ok ? 'Le pedimos la respuesta por correo.' : 'No salió el correo. Revisa el SMTP.', 'detalles' => $detalles]);
	}
	wp_safe_redirect(at_cc_url_ficha_propuesta($id));
	exit;
}

function at_cc_accion_registrar_aceptacion(): void {
	$id = (int) ($_POST['proposal_id'] ?? 0);
	check_admin_referer('at_cc_aceptar_' . $id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$volver = function (array $aviso) use ($id): void {
		at_cc_guardar_aviso(array_merge(['propuesta_id' => $id], $aviso));
		wp_safe_redirect(at_cc_url_ficha_propuesta($id));
		exit;
	};
	$p = at_cc_propuesta_por_id($id);
	if (!$p) {
		$volver(['tipo' => 'error', 'texto' => 'Esa propuesta no existe.']);
	}
	$nota = trim(sanitize_textarea_field(wp_unslash($_POST['nota'] ?? '')));
	$canal = sanitize_key(wp_unslash($_POST['canal'] ?? ''));
	if ($nota === '' || !isset(at_cc_canales_manuales()[$canal])) {
		$volver(['tipo' => 'error', 'texto' => 'Falta decir por dónde aceptó y qué dijo el cliente.']);
	}
	$fecha = at_cc_fecha_declarada(sanitize_text_field(wp_unslash($_POST['fecha'] ?? '')), current_time('Y-m-d'));
	$rut = sanitize_text_field(wp_unslash($_POST['rut'] ?? ''));
	$ev = at_cc_guardar_evidencias($id, at_cc_archivos_normalizados($_FILES['evidencia'] ?? []));
	$r = at_cc_registrar_respuesta($p, 'acepta', [
		'canal'        => 'manual',
		'canal_manual' => $canal,
		'nombre'       => sanitize_text_field(wp_unslash($_POST['nombre'] ?? '')),
		'rut'          => at_cc_rut_valido($rut) ? at_cc_rut_formato($rut) : '',
		'comentario'   => $nota,
		'filas'        => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($p), array_map('sanitize_text_field', (array) wp_unslash($_POST['filas'] ?? []))),
		'fecha'        => $fecha !== '' ? $fecha : current_time('mysql'),
		'evidencias'   => $ev['guardadas'],
		'bienvenida'   => !empty($_POST['bienvenida']),
		'usuario_id'   => get_current_user_id(),
	]);
	$detalles = array_merge($ev['errores'], (array) $r['avisos']);
	if (!$r['ok']) {
		$volver(['tipo' => 'error', 'texto' => (string) $r['mensaje'], 'detalles' => $detalles]);
	}
	$texto = $r['mensaje'] === 'ya_aceptada' ? 'La propuesta ya estaba aceptada: no se repitió nada.' : 'Aceptación registrada: la propuesta quedó aceptada, el cliente pasó a la ficha única y el contrato quedó listo para tu revisión.';
	$volver(['tipo' => $detalles ? 'aviso' : 'ok', 'texto' => $texto, 'detalles' => $detalles]);
}

/** Va dentro del formulario de la ficha: sus campos usan form="…" para enviarse con los formularios de abajo. */
function at_cc_render_panel_respuesta(object $p): void {
	$estado = (string) $p->status;
	$ultima = at_cc_ultima_respuesta((int) $p->id);
	$filas = at_cc_filas_de_propuesta($p);
	$wa = at_cc_url_wa_me((string) $p->phone, at_cc_texto_whatsapp((string) $p->client_name, (string) $p->company_name, at_cc_url_respuesta(get_site_url(), (string) $p->unique_link_id, 'aceptar')));
	echo '<div class="at-cc-panel" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;margin:0 0 16px">';
	echo '<h3 style="margin-top:0">🤝 Respuesta del cliente</h3>';
	echo '<p>Estado: <strong>' . esc_html(at_pa_estado_etiqueta($estado)['etiqueta']) . '</strong>';
	if ($ultima) {
		echo ' · Última respuesta: ' . esc_html($ultima['titulo']) . ' (' . esc_html(at_pa_fecha_corta($ultima['fecha'])) . ')';
	}
	echo '</p>';
	if ($estado === 'aceptada') {
		at_cc_render_resumen_aceptada($p, $ultima);
		echo '</div>';
		return;
	}
	echo '<p>';
	if (at_cc_puede_pedir_respuesta($estado)) {
		echo '<button type="submit" class="button" form="at-cc-f-pedir">📧 Pedir respuesta por correo</button> ';
	}
	if ($wa !== '') {
		echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url($wa) . '">💬 Enviar por mi WhatsApp</a>';
	} else {
		echo '<span class="description">Sin teléfono: agrégalo en «Cliente y enlaces» para mandar el WhatsApp.</span>';
	}
	echo '</p>';
	if (!at_cc_transicion_respuesta_valida($estado, 'aceptada', true)) {
		echo '<p class="description">La aceptación se registra cuando la propuesta está lista o enviada.</p></div>';
		return;
	}
	$hoy = current_time('Y-m-d');
	echo '<details><summary style="cursor:pointer;font-weight:600">✍️ Registrar aceptación a mano</summary>';
	echo '<p class="description">Para cuando el cliente ya aceptó por WhatsApp, correo, llamada o en una reunión. La propuesta pasa a aceptada, el cliente pasa a la ficha única, recibe la bienvenida si la marcas y el contrato queda listo para tu revisión.</p>';
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th>¿Por dónde aceptó?</th><td><select name="canal" form="at-cc-f-aceptar" required><option value="">Elegir…</option>';
	foreach (at_cc_canales_manuales() as $k => $t) {
		echo '<option value="' . esc_attr($k) . '">' . esc_html($t) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th>¿Cuándo aceptó?</th><td><input type="date" name="fecha" form="at-cc-f-aceptar" value="' . esc_attr($hoy) . '" max="' . esc_attr($hoy) . '"></td></tr>';
	if ($filas) {
		echo '<tr><th>¿Qué aceptó?</th><td>';
		foreach ($filas as $i => $f) {
			echo '<label style="display:block"><input type="checkbox" name="filas[]" value="' . (int) $i . '" form="at-cc-f-aceptar"' . ($i === 0 ? ' checked' : '') . '> '
				. esc_html(trim(($f['service'] ?? '') . ' · ' . ($f['price_label'] ?? ''))) . '</label>';
		}
		echo '</td></tr>';
	}
	echo '<tr><th>¿Quién aceptó?</th><td><input type="text" class="regular-text" name="nombre" form="at-cc-f-aceptar" value="' . esc_attr((string) $p->client_name) . '"></td></tr>';
	echo '<tr><th>RUT (si lo tienes)</th><td><input type="text" class="regular-text" name="rut" form="at-cc-f-aceptar" placeholder="12.345.678-9"></td></tr>';
	echo '<tr><th>¿Qué dijo el cliente?</th><td><textarea name="nota" form="at-cc-f-aceptar" rows="3" class="large-text" required placeholder="Ej.: Me escribió por WhatsApp: sí, partamos con la fase 1."></textarea></td></tr>';
	echo '<tr><th>Evidencia</th><td><input type="file" name="evidencia[]" form="at-cc-f-aceptar" accept="image/jpeg,image/png,image/webp" multiple>'
		. '<p class="description">Hasta 3 imágenes (JPG, PNG o WEBP, 5 MB cada una), por ejemplo la captura del WhatsApp. Se guardan en privado: solo se ven desde el panel.</p></td></tr>';
	echo '<tr><th>Bienvenida</th><td><label><input type="checkbox" name="bienvenida" value="1" form="at-cc-f-aceptar" checked> Enviarle el correo de bienvenida</label></td></tr>';
	echo '</table>';
	echo '<p><button type="submit" class="button button-primary" form="at-cc-f-aceptar" onclick="return confirm(\'Esto pasa la propuesta a aceptada, crea al cliente y le envía la bienvenida si está marcada. ¿Seguir?\');">Registrar aceptación</button></p>';
	echo '</details></div>';
}

function at_cc_render_resumen_aceptada(object $p, ?array $ultima): void {
	$crm_id = at_cc_crm_de_email((string) $p->client_email);
	echo '<p>✅ La propuesta está aceptada.</p><p>';
	if ($crm_id) {
		echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=automatiza-crm-ficha&id=' . $crm_id)) . '">Abrir la ficha del cliente</a> ';
	}
	$c = at_cc_contrato_de_propuesta((int) $p->id);
	if ($c && in_array($c->status, ['draft', 'at_pending'], true)) {
		echo '<a class="button button-primary" href="' . esc_url(home_url('/contracts/at-sign-contract.php?token=' . $c->at_review_token)) . '">Revisar, ajustar y firmar el contrato</a>';
	} elseif ($c) {
		echo '<span class="description">Contrato ' . esc_html((string) $c->contract_number) . ': ' . esc_html((string) $c->status) . '</span>';
	}
	echo '</p>';
	foreach ((array) ($ultima['evidencias'] ?? []) as $ev) {
		echo '<a href="' . esc_url((string) $ev['url']) . '" target="_blank" rel="noopener"><img src="' . esc_url((string) $ev['url']) . '" alt="Evidencia" style="max-width:140px;max-height:140px;margin:4px;border:1px solid #ddd;border-radius:6px"></a>';
	}
}

/** Formularios reales del bloque; van después del formulario de la ficha (no se pueden anidar). */
function at_cc_render_formularios_respuesta(object $p): void {
	$url = esc_url(admin_url('admin-post.php'));
	$id = (int) $p->id;
	echo '<form id="at-cc-f-pedir" method="post" action="' . $url . '" hidden>'
		. '<input type="hidden" name="action" value="at_cc_pedir_respuesta"><input type="hidden" name="proposal_id" value="' . $id . '">'
		. wp_nonce_field('at_cc_pedir_' . $id, '_wpnonce', true, false) . '</form>';
	echo '<form id="at-cc-f-aceptar" method="post" action="' . $url . '" enctype="multipart/form-data" hidden>'
		. '<input type="hidden" name="action" value="at_cc_registrar_aceptacion"><input type="hidden" name="proposal_id" value="' . $id . '">'
		. wp_nonce_field('at_cc_aceptar_' . $id, '_wpnonce', true, false) . '</form>';
}
```

- [ ] **Step 2: Cargarlo** en `cargar.php`, después de `require_once __DIR__ . '/pagina.php';`:

```php
require_once __DIR__ . '/panel.php';
```

- [ ] **Step 3: `ficha.php`** (CRLF, Edit):
  - Reemplazar la línea `      <?php echo $message; ?>` por:

```php
      <?php echo $message; ?>
      <?php if (function_exists('at_cc_aviso_panel')) { echo at_cc_aviso_panel((int) $p->id); } ?>
```

  - Reemplazar la línea `        <section class="at-pa-panel" data-panel="envio" role="tabpanel">` por:

```php
        <section class="at-pa-panel" data-panel="envio" role="tabpanel">
          <?php if (function_exists('at_cc_render_panel_respuesta')) { at_cc_render_panel_respuesta($p); } ?>
```

  - Reemplazar la línea `      </form>` por:

```php
      </form>
      <?php if (function_exists('at_cc_render_formularios_respuesta')) { at_cc_render_formularios_respuesta($p); } ?>
```

- [ ] **Step 4: Verificar.**
  Run:
  ```bash
  "$PHP" -l wp-content/themes/automatiza-tech/inc/cierre-cliente/panel.php && "$PHP" -l wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php
  f=wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php; echo "$(wc -l < $f) $(grep -c $'\r' $f)"
  "$PHP" tests/propuestas/admin-lista-test.php && AT_WP_LOAD=<ruta> "$PHP" tests/cierre/cierre-wp-test.php
  ```
  Expected: sintaxis OK, CR en todas las líneas, `TODO OK`. El recorrido con navegador (formularios con `form=`, subida de evidencia, 403 de la URL directa) lo hace el controlador en la tarea 11.

- [ ] **Step 5: Commit.**

```bash
git add wp-content/themes/automatiza-tech/inc/cierre-cliente/panel.php wp-content/themes/automatiza-tech/inc/cierre-cliente/cargar.php wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php
git commit -m "feat(cierre): en la ficha, respuesta del cliente, Pedir respuesta y Registrar aceptación con evidencia privada" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

### Task 9: Correo de la propuesta con «Aceptar» y WhatsApp tras el envío

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php` (primera parte)
- Modify: `wp-content/themes/automatiza-tech/inc/cierre-cliente/cargar.php`
- Modify: `wp-content/themes/automatiza-tech/inc/propuestas-admin/acciones.php`
- Modify: `wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php`

**Interfaces:**
- Consumes: Task 1, Task 6 (`at_cc_anotar_simple`).
- Produces: `at_cc_tras_envio(object $p, bool $pidio_whatsapp): string` (HTML de aviso para el panel), `at_cc_whatsapp_tras_pedido(object $p): array` (detalles para el aviso), `at_cc_whatsapp_plantilla_activa(): bool` (en esta tarea devuelve `false` siempre que no exista la plantilla; la Task 10 agrega el envío real). Campo nuevo del formulario: `at_cc_whatsapp`.

- [ ] **Step 1: `whatsapp.php`:**

```php
<?php
/** WhatsApp del cierre: desde el teléfono de Luis (wa.me) o, cuando Meta apruebe la plantilla, automático vía n8n. */
if (!defined('ABSPATH')) {
	exit;
}

/** La plantilla de Meta está aprobada y conectada (opción que activa el controlador, y la URL del flujo de n8n). */
function at_cc_whatsapp_plantilla_activa(): bool {
	return get_option('at_cc_wa_plantilla_activa') === '1' && defined('AT_N8N_CC_WHATSAPP') && AT_N8N_CC_WHATSAPP !== '' && function_exists('at_cc_enviar_whatsapp_plantilla');
}

/** Botón para mandar el WhatsApp desde el teléfono de Luis; '' si no hay teléfono. */
function at_cc_boton_wa_me(object $p): string {
	$u = at_cc_url_wa_me((string) $p->phone, at_cc_texto_whatsapp((string) $p->client_name, (string) $p->company_name, at_cc_url_respuesta(get_site_url(), (string) $p->unique_link_id, 'aceptar')));
	return $u === '' ? '' : '<a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url($u) . '">💬 Enviar por mi WhatsApp</a>';
}

/** Después de enviar la propuesta por correo: queda en Seguimiento y, si se pidió, sale o se ofrece el WhatsApp. */
function at_cc_tras_envio(object $p, bool $pidio_whatsapp): string {
	at_cc_anotar_simple($p, 'propuesta_enviada', 'Propuesta enviada por correo, con el botón para aceptarla');
	if (!$pidio_whatsapp) {
		return '';
	}
	if (trim((string) $p->phone) === '') {
		return '<div class="notice notice-warning"><p>No se mandó WhatsApp: la propuesta no tiene teléfono.</p></div>';
	}
	if (at_cc_whatsapp_plantilla_activa()) {
		$error = at_cc_enviar_whatsapp_plantilla($p);
		if ($error === '') {
			at_cc_anotar_simple($p, 'propuesta_enviada', 'WhatsApp enviado con la plantilla (Meta confirma la entrega después)');
			return '<div class="notice notice-success"><p>WhatsApp enviado con los botones para responder. Meta confirma la entrega después.</p></div>';
		}
		return '<div class="notice notice-warning"><p>El WhatsApp automático falló (' . esc_html($error) . '). Mándalo desde tu teléfono: ' . at_cc_boton_wa_me($p) . '</p></div>';
	}
	return '<div class="notice notice-info"><p>Falta el WhatsApp: ' . at_cc_boton_wa_me($p) . ' (abre tu WhatsApp con el mensaje y el enlace para aceptar ya escritos).</p></div>';
}

/** Tras «Pedir respuesta»: detalles para el aviso del panel. */
function at_cc_whatsapp_tras_pedido(object $p): array {
	if (trim((string) $p->phone) === '') {
		return ['Sin teléfono: no se mandó WhatsApp.'];
	}
	if (at_cc_whatsapp_plantilla_activa()) {
		$error = at_cc_enviar_whatsapp_plantilla($p);
		if ($error === '') {
			at_cc_anotar_simple($p, 'propuesta_enviada', 'Se le pidió la respuesta por WhatsApp (plantilla)');
			return ['WhatsApp enviado con los botones para responder.'];
		}
		return ['El WhatsApp automático falló (' . $error . '): usa «Enviar por mi WhatsApp».'];
	}
	return ['Para el WhatsApp, usa «Enviar por mi WhatsApp».'];
}
```

- [ ] **Step 2: Cargarlo** en `cargar.php`, después de `require_once __DIR__ . '/panel.php';`:

```php
require_once __DIR__ . '/whatsapp.php';
```

- [ ] **Step 3: `acciones.php`** (CRLF, Edit):
  - Reemplazar las dos líneas
    `                            <a href="' . esc_url($link_demo) . '" class="btn btn-secondary">🤖 Probar Demo Chatbot</a>` / `                        </div>`
    por:

```php
                            <a href="' . esc_url($link_demo) . '" class="btn btn-secondary">🤖 Probar Demo Chatbot</a>
                        </div>
                        ' . (function_exists('at_cc_bloque_aceptar_html') ? at_cc_bloque_aceptar_html(at_cc_url_respuesta(get_site_url(), (string) $proposal->unique_link_id, 'aceptar'), at_cc_url_respuesta(get_site_url(), (string) $proposal->unique_link_id, 'evaluar')) : '') . '
```

  - Reemplazar la línea que empieza con `                $message = '<div class="notice notice-success is-dismissible"><p>Propuesta actualizada y correo enviado a '` (la del envío exitoso) por esa misma línea seguida de:

```php
                if (function_exists('at_cc_tras_envio')) {
                    $message .= at_cc_tras_envio($proposal, !empty($_POST['at_cc_whatsapp']));
                }
```

- [ ] **Step 4: `ficha.php`** (CRLF, Edit). Reemplazar la línea `            <p>Si desmarcas esta opción, solo se guardarán los datos sin enviar el correo.</p>` por:

```php
            <p>Si desmarcas esta opción, solo se guardarán los datos sin enviar el correo.</p>
            <label style="display:block;margin-top:8px">
              <input type="checkbox" name="at_cc_whatsapp" value="1" <?php checked(trim((string) $p->phone) !== ''); ?> <?php disabled(trim((string) $p->phone) === ''); ?>>
              <span>💬 También por WhatsApp (con el enlace para aceptar)</span>
            </label>
            <p class="description">El correo lleva el botón «Aceptar la propuesta». El WhatsApp sale desde tu teléfono hasta que Meta apruebe la plantilla con botones.</p>
```

- [ ] **Step 5: Verificar.**
  Run:
  ```bash
  for f in wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php wp-content/themes/automatiza-tech/inc/propuestas-admin/acciones.php wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php; do "$PHP" -l $f; done
  for f in wp-content/themes/automatiza-tech/inc/propuestas-admin/acciones.php wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php; do echo "$f $(wc -l < $f) $(grep -c $'\r' $f)"; done
  "$PHP" tests/propuestas/admin-lista-test.php && "$PHP" tests/propuestas/flow-test.php && AT_WP_LOAD=<ruta> "$PHP" tests/cierre/cierre-wp-test.php
  ```
  Expected: sintaxis OK, CR en todas las líneas, `TODO OK`. El correo real (capturado, no enviado) lo revisa el controlador en la tarea 11.

- [ ] **Step 6: Commit.**

```bash
git add wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php wp-content/themes/automatiza-tech/inc/cierre-cliente/cargar.php wp-content/themes/automatiza-tech/inc/propuestas-admin/acciones.php wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php
git commit -m "feat(cierre): el correo de la propuesta pide aceptarla y el envío ofrece el WhatsApp con el enlace" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

### Task 10: WhatsApp automático (código listo, inactivo hasta la plantilla de Meta)

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php`
- Test: `tests/cierre/rest-wp-test.php`

**Interfaces:**
- Consumes: Task 6 (`at_cc_registrar_respuesta`, `at_cc_propuesta_por_codigo`, `at_cc_anotar_simple`), constante `AT_REST_SECRET` (ya usada por `rest-proposals.php`).
- Produces:
  - `at_cc_enviar_whatsapp_plantilla(object $p): string` ('' si n8n respondió 2xx; si no, el motivo). POST JSON a `AT_N8N_CC_WHATSAPP` con cabecera `X-AT-Secret`: `{codigo, telefono, nombre, empresa, propuesto, url}`.
  - `POST /wp-json/at/v1/propuesta-respuesta` con cabecera `X-AT-Secret` y JSON `{salida: acepta|evalua|rechaza, codigo, telefono, wamid}` → `{ok, estado?, motivo}`. `motivo` = `no_existe` | `telefono` | el `mensaje` de `at_cc_registrar_respuesta`.

- [ ] **Step 1: Prueba** `tests/cierre/rest-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/rest-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
add_filter('pre_wp_mail', function () { return true; });
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: el controlador lo define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
$marca = 'prueba-cierre-' . strtolower(wp_generate_password(6, false, false));
$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', ['client_email' => $marca . '@example.com', 'unique_link_id' => substr(md5($marca), 0, 12), 'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Muebles', 'phone' => '+56 9 3333 3333', 'status' => 'sent', 'flujo' => 'v3', 'gamma_prompt_text' => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 en 2 pagos']]]), 'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql')]);
$pid = (int) $wpdb->insert_id;
$codigo = substr(md5($marca), 0, 12);
function pedir(array $cuerpo, ?string $clave = null) {
	$r = new WP_REST_Request('POST', '/at/v1/propuesta-respuesta');
	$r->set_header('content-type', 'application/json');
	if ($clave !== null) { $r->set_header('x-at-secret', $clave); }
	$r->set_body(wp_json_encode($cuerpo));
	return rest_do_request($r);
}
ok(pedir(['salida' => 'acepta', 'codigo' => $codigo, 'telefono' => '56933333333'])->get_status() === 401, 'sin clave: 401');
ok(pedir(['salida' => 'acepta', 'codigo' => $codigo, 'telefono' => '56933333333'], 'mala')->get_status() === 401, 'clave mala: 401');
ok(pedir(['salida' => 'acepta', 'codigo' => 'no-existe', 'telefono' => '56933333333'], AT_REST_SECRET)->get_data()['motivo'] === 'no_existe', 'código inexistente');
$r = pedir(['salida' => 'acepta', 'codigo' => $codigo, 'telefono' => '56911112222', 'wamid' => 'w1'], AT_REST_SECRET)->get_data();
ok($r['ok'] === false && $r['motivo'] === 'telefono' && at_cc_propuesta_por_id($pid)->status === 'sent', 'teléfono distinto: no cambia nada');
$r = pedir(['salida' => 'evalua', 'codigo' => $codigo, 'telefono' => '+56 9 3333 3333', 'wamid' => 'w2'], AT_REST_SECRET)->get_data();
ok($r['ok'] === true && $r['estado'] === 'evaluando', 'evalúa por WhatsApp');
$r = pedir(['salida' => 'acepta', 'codigo' => $codigo, 'telefono' => '933333333', 'wamid' => 'w3'], AT_REST_SECRET)->get_data();
ok($r['ok'] === true && $r['estado'] === 'aceptada' && at_cc_contrato_de_propuesta($pid) !== null, 'acepta por WhatsApp: cierre completo');
$r = pedir(['salida' => 'acepta', 'codigo' => $codigo, 'telefono' => '933333333', 'wamid' => 'w4'], AT_REST_SECRET)->get_data();
ok($r['ok'] === true && $r['motivo'] === 'ya_aceptada', 'dos toques no repiten');
ok(pedir(['salida' => 'otra', 'codigo' => $codigo, 'telefono' => '933333333'], AT_REST_SECRET)->get_data()['ok'] === false, 'salida inválida');
ok(!at_cc_whatsapp_plantilla_activa(), 'plantilla inactiva por defecto');
ok(at_cc_enviar_whatsapp_plantilla(at_cc_propuesta_por_id($pid)) !== '', 'sin plantilla activa no se envía');

$crm = at_cc_crm_de_email($marca . '@example.com');
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_contracts WHERE proposal_id = %d", $pid));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d", $pid));
if ($crm) {
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d", $crm));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id = %d", $crm));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm));
}
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $pid));
fin();
```

- [ ] **Step 2: Correr y ver que falla** (ruta inexistente: 404 en las primeras aserciones).

- [ ] **Step 3: Agregar al final de `whatsapp.php`:**

```php
/** Manda la plantilla de Meta a través del flujo de n8n. '' si respondió 2xx; si no, el motivo. */
function at_cc_enviar_whatsapp_plantilla(object $p): string {
	if (get_option('at_cc_wa_plantilla_activa') !== '1' || !defined('AT_N8N_CC_WHATSAPP') || AT_N8N_CC_WHATSAPP === '') {
		return 'La plantilla de WhatsApp no está activa.';
	}
	if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
		return 'AT_REST_SECRET no está configurado.';
	}
	$telefono = at_cc_telefono_normalizado((string) $p->phone);
	if ($telefono === '') {
		return 'La propuesta no tiene teléfono.';
	}
	$filas = at_cc_filas_de_propuesta($p);
	$propuesto = $filas ? trim(($filas[0]['service'] ?? '') . ', ' . ($filas[0]['price_label'] ?? ''), ' ,') : '';
	$r = wp_remote_post(AT_N8N_CC_WHATSAPP, [
		'timeout' => 15,
		'headers' => ['Content-Type' => 'application/json', 'X-AT-Secret' => AT_REST_SECRET],
		'body'    => wp_json_encode([
			'codigo'    => (string) $p->unique_link_id,
			'telefono'  => $telefono,
			'nombre'    => (string) $p->client_name,
			'empresa'   => (string) $p->company_name,
			'propuesto' => $propuesto,
			'url'       => at_cc_url_respuesta(get_site_url(), (string) $p->unique_link_id),
		]),
	]);
	if (is_wp_error($r)) {
		return $r->get_error_message();
	}
	$code = (int) wp_remote_retrieve_response_code($r);
	return ($code >= 200 && $code < 300) ? '' : "n8n respondió HTTP {$code}";
}

add_action('rest_api_init', function () {
	register_rest_route('at/v1', '/propuesta-respuesta', [
		'methods'             => 'POST',
		'callback'            => 'at_cc_rest_respuesta_whatsapp',
		'permission_callback' => 'at_cc_rest_auth',
	]);
});

function at_cc_rest_auth(WP_REST_Request $r) {
	if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
		return new WP_Error('sin_clave', 'AT_REST_SECRET no está configurado.', ['status' => 500]);
	}
	$dada = (string) $r->get_header('x_at_secret');
	if ($dada === '' || !hash_equals((string) AT_REST_SECRET, $dada)) {
		return new WP_Error('no_autorizado', 'Clave inválida.', ['status' => 401]);
	}
	return true;
}

/** El bot principal de WhatsApp llama aquí cuando el cliente toca un botón de la plantilla. */
function at_cc_rest_respuesta_whatsapp(WP_REST_Request $r) {
	$salida = sanitize_key((string) $r->get_param('salida'));
	$tel = sanitize_text_field((string) $r->get_param('telefono'));
	$wamid = sanitize_text_field((string) $r->get_param('wamid'));
	$p = at_cc_propuesta_por_codigo(sanitize_text_field((string) $r->get_param('codigo')));
	if (!$p) {
		return ['ok' => false, 'motivo' => 'no_existe'];
	}
	$esperado = at_cc_telefono_normalizado((string) $p->phone);
	if ($esperado === '' || $esperado !== at_cc_telefono_normalizado($tel)) {
		at_cc_anotar_simple($p, 'respuesta_cliente', 'Respuesta por WhatsApp desde un número distinto (no se aplicó)', 'Salida: ' . $salida . ' · número que respondió: ' . $tel);
		return ['ok' => false, 'motivo' => 'telefono'];
	}
	$res = at_cc_registrar_respuesta($p, $salida, [
		'canal'      => 'whatsapp',
		'telefono'   => $tel,
		'wamid'      => $wamid,
		'nombre'     => (string) $p->client_name,
		'filas'      => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($p), [0]),
		'fecha'      => current_time('mysql'),
		'bienvenida' => true,
	]);
	return ['ok' => (bool) $res['ok'], 'estado' => (string) $res['estado'], 'motivo' => (string) $res['mensaje']];
}
```

- [ ] **Step 4: Correr todo.**
  Run:
  ```bash
  "$PHP" -l wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php
  AT_WP_LOAD=<ruta> "$PHP" tests/cierre/rest-wp-test.php
  AT_WP_LOAD=<ruta> "$PHP" tests/cierre/cierre-wp-test.php && AT_WP_LOAD=<ruta> "$PHP" tests/cierre/clientes-wp-test.php && AT_WP_LOAD=<ruta> "$PHP" tests/cierre/contrato-wp-test.php
  "$PHP" tests/cierre/puras-test.php && "$PHP" tests/cierre/plantilla-test.php && "$PHP" tests/propuestas/admin-lista-test.php && "$PHP" tests/propuestas/flow-test.php
  ```
  Expected: `TODO OK` en las ocho.

- [ ] **Step 5: Commit.**

```bash
git add wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php tests/cierre/rest-wp-test.php
git commit -m "feat(cierre): endpoint para responder la propuesta con un botón de WhatsApp y envío por plantilla (inactivo)" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

#### Ajuste del controlador (aprobado por Luis el 25-sep) — forma parte de esta tarea

La spec (etapa 4) dice: si el teléfono que tocó el botón no coincide con el de la propuesta, no se cambia el estado, **se anota en Seguimiento y se avisa a Luis**. El código de arriba solo anota. Agregar el aviso por correo:

1. En `at_cc_rest_respuesta_whatsapp()`, en la rama del teléfono distinto, justo después de la línea `at_cc_anotar_simple($p, 'respuesta_cliente', 'Respuesta por WhatsApp desde un número distinto (no se aplicó)', ...);` y antes del `return`, agregar:

```php
		at_cc_avisar_numero_distinto($p, $salida, $tel);
```

2. Agregar al final de `whatsapp.php`:

```php
/** Aviso a Luis cuando alguien responde el WhatsApp de la propuesta desde otro número (aprobado por Luis el 25-sep). */
function at_cc_avisar_numero_distinto(object $p, string $salida, string $tel): void {
	$botones = ['acepta' => 'Acepto la propuesta', 'evalua' => 'La sigo evaluando', 'rechaza' => 'No, gracias'];
	$quien = trim((string) $p->company_name) !== '' ? (string) $p->company_name : (string) $p->client_name;
	$html = '<p>Alguien tocó «' . esc_html($botones[$salida] ?? $salida) . '» en el WhatsApp de la propuesta ' . esc_html((string) $p->unique_link_id)
		. ', pero desde un número distinto al de la propuesta (' . esc_html($tel) . '). No se cambió el estado.</p>'
		. '<p>Revisa la ficha y confirma con el cliente. Si la respuesta es válida, regístrala con «Registrar aceptación».</p>'
		. '<p><a href="' . esc_url(admin_url('admin.php?page=automatiza-proposals&edit_id=' . (int) $p->id . '&tab=envio')) . '">Abrir la propuesta en el panel</a></p>';
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	wp_mail((string) get_option('admin_email'), 'Respuesta a la propuesta de ' . $quien . ' desde otro número', $html, ['Content-Type: text/html; charset=UTF-8', 'From: Automatiza Tech <' . $from . '>']);
}
```

3. En `tests/cierre/rest-wp-test.php`, reemplazar la línea `add_filter('pre_wp_mail', function () { return true; });` por:

```php
$correos = [];
add_filter('pre_wp_mail', function ($r, $a) use (&$correos) { $correos[] = $a; return true; }, 10, 2);
```

   y justo después de la aserción `'teléfono distinto: no cambia nada'`, agregar:

```php
ok(count($correos) === 1 && $correos[0]['to'] === get_option('admin_email') && strpos($correos[0]['subject'], 'desde otro número') !== false, 'teléfono distinto: avisa a Luis por correo');
```

   (Hasta ese punto ninguna otra llamada manda correo: las de 401 y `no_existe` cortan antes.)

---

#### Ajuste del controlador 2 (26-sep): la nota del número distinto es interna

La línea de `at_cc_rest_respuesta_whatsapp()` que anota «Respuesta por WhatsApp desde un número distinto (no se aplicó)» usa el tipo `'respuesta_cliente'`, que se muestra en la página pública del prospecto, y su descripción lleva el teléfono de un tercero. Usa el tipo interno `'aviso_operativo'` (está en `at_cc_tipos_internos()` de puras.php y no sale en ninguna vista pública). Agrega a `tests/cierre/rest-wp-test.php` una aserción: tras la respuesta desde otro número, la propuesta tiene una nota `aviso_operativo` y ninguna `respuesta_cliente` con ese teléfono.

---

### Task 10b: Contexto de la propuesta para el bot (`whatsapp.php`) — agregada por Luis el 25-sep

Cuando el cliente **escribe** en vez de tocar un botón («sí, acepto», «tengo una duda del precio»), el agente de IA del bot principal no sabe que esa persona tiene una propuesta esperando respuesta. Esta tarea agrega el endpoint que el bot consulta antes de pasarle el mensaje a la IA (la parte del bot es la Task 12b). Un mensaje de texto **nunca** cambia el estado: solo se anota y se avisa a Luis.

**Files:**
- Modify: `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php`
- Test: `tests/cierre/contexto-wp-test.php`

**Interfaces:**
- Consumes: Task 10 (`at_cc_rest_auth`), Task 6 (`at_cc_anotar_simple`, `at_cc_filas_de_propuesta`, `at_cc_propuesta_por_id`), Task 1 (`at_cc_telefono_normalizado`, `at_cc_url_respuesta`).
- Produces:
  - `at_cc_propuesta_pendiente_por_telefono(string $tel): ?object` — la propuesta más reciente en `sent` o `evaluando` cuyo teléfono normalizado coincide; `null` si no hay.
  - `POST /wp-json/at/v1/propuesta-contexto` con cabecera `X-AT-Secret` y JSON `{telefono, mensaje?}` → `{tiene:false}` o `{tiene:true, codigo, nombre, empresa, estado, propuesto, url}`. Si trae `mensaje`, lo anota en Seguimiento (`respuesta_cliente`) y avisa a Luis por correo, **una vez cada 30 minutos por propuesta** (transient `at_cc_ctx_aviso_<id>`).

- [ ] **Step 1: Prueba** `tests/cierre/contexto-wp-test.php`:

```php
<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/contexto-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
$correos = [];
add_filter('pre_wp_mail', function ($r, $a) use (&$correos) { $correos[] = $a; return true; }, 10, 2);
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba.\n");
	exit(2);
}
$marca = 'prueba-cierre-' . strtolower(wp_generate_password(6, false, false));
$codigo = substr(md5($marca), 0, 12);
$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', ['client_email' => $marca . '@example.com', 'unique_link_id' => $codigo, 'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Muebles', 'phone' => '+56 9 4444 5555', 'status' => 'sent', 'flujo' => 'v3', 'gamma_prompt_text' => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 en 2 pagos']]]), 'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql')]);
$pid = (int) $wpdb->insert_id;
function contexto(array $cuerpo, ?string $clave = null) {
	$r = new WP_REST_Request('POST', '/at/v1/propuesta-contexto');
	$r->set_header('content-type', 'application/json');
	if ($clave !== null) { $r->set_header('x-at-secret', $clave); }
	$r->set_body(wp_json_encode($cuerpo));
	return rest_do_request($r);
}
ok(contexto(['telefono' => '56944445555'])->get_status() === 401, 'sin clave: 401');
ok(contexto(['telefono' => '56900000001'], AT_REST_SECRET)->get_data()['tiene'] === false, 'otro número: sin propuesta');
$r = contexto(['telefono' => '944445555'], AT_REST_SECRET)->get_data();
ok($r['tiene'] === true && $r['codigo'] === $codigo && strpos($r['propuesto'], 'Fase 1') === 0 && strpos($r['url'], $codigo) !== false, 'mismo número en otro formato: encuentra la propuesta');
ok(count($correos) === 0, 'sin mensaje no avisa');
$r = contexto(['telefono' => '+56 9 4444 5555', 'mensaje' => "Sí, acepto\nla propuesta"], AT_REST_SECRET)->get_data();
ok($r['tiene'] === true && count($correos) === 1 && $correos[0]['to'] === get_option('admin_email'), 'con mensaje: avisa a Luis');
ok(strpos((string) $correos[0]['message'], 'acepto') !== false, 'el correo trae el mensaje');
$n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente'", $pid));
ok($n === 1, 'queda anotado en Seguimiento');
contexto(['telefono' => '56944445555', 'mensaje' => 'otra duda'], AT_REST_SECRET);
ok(count($correos) === 1, 'otro mensaje dentro de 30 minutos: no repite el correo');
ok(at_cc_propuesta_por_id($pid)->status === 'sent', 'un mensaje de texto no cambia el estado');
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['status' => 'aceptada'], ['id' => $pid]);
ok(contexto(['telefono' => '56944445555'], AT_REST_SECRET)->get_data()['tiene'] === false, 'aceptada: ya no está pendiente');

delete_transient('at_cc_ctx_aviso_' . $pid);
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d", $pid));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $pid));
fin();
```

- [ ] **Step 2: Correr y ver que falla** (ruta inexistente: 404 en la primera aserción).

- [ ] **Step 3: Agregar al final de `whatsapp.php`:**

```php
add_action('rest_api_init', function () {
	register_rest_route('at/v1', '/propuesta-contexto', [
		'methods'             => 'POST',
		'callback'            => 'at_cc_rest_contexto_whatsapp',
		'permission_callback' => 'at_cc_rest_auth',
	]);
});

/** La propuesta más reciente enviada o en evaluación para ese teléfono; null si no hay. */
function at_cc_propuesta_pendiente_por_telefono(string $tel): ?object {
	global $wpdb;
	$n = at_cc_telefono_normalizado($tel);
	if (strlen($n) < 8) {
		return null;
	}
	$filas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}automatiza_propuestas WHERE status IN ('sent','evaluando') AND phone <> '' ORDER BY id DESC LIMIT 200");
	foreach ((array) $filas as $p) {
		if (at_cc_telefono_normalizado((string) $p->phone) === $n) {
			return $p;
		}
	}
	return null;
}

/** El bot principal consulta aquí antes de pasarle un mensaje de texto a la IA. Un texto nunca cambia el estado. */
function at_cc_rest_contexto_whatsapp(WP_REST_Request $r) {
	$p = at_cc_propuesta_pendiente_por_telefono(sanitize_text_field((string) $r->get_param('telefono')));
	if (!$p) {
		return ['tiene' => false];
	}
	$mensaje = trim(sanitize_textarea_field((string) $r->get_param('mensaje')));
	if ($mensaje !== '') {
		$mensaje = mb_substr($mensaje, 0, 1000);
		$clave = 'at_cc_ctx_aviso_' . (int) $p->id;
		if (!get_transient($clave)) {
			set_transient($clave, 1, 30 * MINUTE_IN_SECONDS);
			at_cc_anotar_simple($p, 'respuesta_cliente', 'Mensaje por WhatsApp sobre la propuesta (no cambia el estado)', $mensaje);
			at_cc_avisar_mensaje_whatsapp($p, $mensaje);
		}
	}
	$filas = at_cc_filas_de_propuesta($p);
	return [
		'tiene'     => true,
		'codigo'    => (string) $p->unique_link_id,
		'nombre'    => (string) $p->client_name,
		'empresa'   => (string) $p->company_name,
		'estado'    => (string) $p->status,
		'propuesto' => $filas ? trim(($filas[0]['service'] ?? '') . ', ' . ($filas[0]['price_label'] ?? ''), ' ,') : '',
		'url'       => at_cc_url_respuesta(get_site_url(), (string) $p->unique_link_id),
	];
}

/** Correo a Luis con lo que el cliente escribió por WhatsApp sobre su propuesta. */
function at_cc_avisar_mensaje_whatsapp(object $p, string $mensaje): void {
	$quien = trim((string) $p->company_name) !== '' ? (string) $p->company_name : (string) $p->client_name;
	$html = '<p>' . esc_html($quien) . ' escribió por WhatsApp sobre su propuesta (' . esc_html((string) $p->unique_link_id) . '):</p>'
		. '<blockquote style="border-left:3px solid #10b981;margin:0 0 12px 0;padding:8px 12px">' . nl2br(esc_html($mensaje)) . '</blockquote>'
		. '<p>El bot le pidió usar el botón «Acepto la propuesta» o el enlace para dejar registrada su respuesta; un mensaje de texto no cambia el estado. Si ya te dijo que sí, regístralo con «Registrar aceptación». Puede haber más mensajes en el chat: este aviso sale como máximo una vez cada 30 minutos.</p>'
		. '<p><a href="' . esc_url(admin_url('admin.php?page=automatiza-proposals&edit_id=' . (int) $p->id . '&tab=envio')) . '">Abrir la propuesta en el panel</a></p>';
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	wp_mail((string) get_option('admin_email'), $quien . ' escribió por WhatsApp sobre su propuesta', $html, ['Content-Type: text/html; charset=UTF-8', 'From: Automatiza Tech <' . $from . '>']);
}
```

- [ ] **Step 4: Correr todo.**
  Run:
  ```bash
  "$PHP" -l wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php
  AT_WP_LOAD=<ruta> "$PHP" tests/cierre/contexto-wp-test.php
  AT_WP_LOAD=<ruta> "$PHP" tests/cierre/rest-wp-test.php
  "$PHP" tests/cierre/puras-test.php
  ```
  Expected: `TODO OK` en las tres.

- [ ] **Step 5: Commit.**

```bash
git add wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php tests/cierre/contexto-wp-test.php
git commit -m "feat(cierre): contexto de la propuesta para el bot de WhatsApp y aviso a Luis cuando el cliente escribe" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

#### Ajuste del controlador (26-sep): el mensaje del cliente es una nota interna

`at_cc_rest_contexto_whatsapp()` anota el mensaje con el tipo `'respuesta_cliente'`, que se muestra en la página pública del prospecto. Usa un tipo interno nuevo, `'mensaje_whatsapp'`:
- agrégalo a `at_cc_tipos_internos()` en puras.php (con su aserción en `puras-test.php`);
- agrégalo a `get_detail_types()` de `inc/client-details-module.php` con la etiqueta `'💬 Mensaje por WhatsApp'`, ícono `'💬'` y color `'#0f766e'`, **después** de `'respuesta_cliente'` (nunca como primera clave: la primera es el tipo por defecto del formulario de Seguimiento); archivo CRLF: mide CR/LF con PHP antes y después;
- en `tests/cierre/contexto-wp-test.php`, la aserción «queda anotado en Seguimiento» cuenta `detail_type = 'mensaje_whatsapp'`.
Suma `puras.php`, `puras-test.php` e `inc/client-details-module.php` al commit.

---

### Task 11: Verificación integrada en el WordPress local (la hace el controlador)

No se delega: usa el navegador y el servidor local de la tarea 0.

- [ ] **Step 1:** Levantar `wp-local-cierre` con `preview_start`. Crear en la base local una propuesta de prueba `[PRUEBA LOCAL]` en `sent`, con teléfono y dos filas de precio, y anotar su id.
- [ ] **Step 2: Página pública**, con capturas:
  - la barra aparece abajo sin tapar los controles de la presentación, en escritorio y en 375×812 sin desborde horizontal;
  - `&responder=aceptar` abre el diálogo de aceptación; RUT inválido → vuelve con el aviso «datos»;
  - «La sigo evaluando» → mensaje y estado `evaluando`; «No, gracias» → `rechazada`; «Acepto» con RUT `11.111.111-1` → `aceptada`, cliente en el CRM, ficha operativa enlazada, contrato en `at_pending`, bienvenida capturada;
  - GET directo a `admin-post.php?action=at_cc_responder` no acepta nada;
  - nonce alterado → «vencida»; campo `sitio_web` lleno → «recibida» sin cambios en la base;
  - la respuesta HTTP de la página trae `Cache-Control` sin caché.
- [ ] **Step 3: Panel**, con capturas:
  - estados nuevos en la lista y sus conteos;
  - bloque «Respuesta del cliente» en Envío; «Pedir respuesta» manda el correo (capturado); «Enviar por mi WhatsApp» abre `wa.me` con el texto;
  - «Registrar aceptación» con una imagen PNG, sin nota (se rechaza) y con nota (se acepta); la miniatura se ve con sesión; la URL directa `uploads/automatiza-tech-evidencias/...` da 403; `admin-ajax.php?action=at_cc_ver_evidencia...` sin sesión no la entrega;
  - enviar la propuesta con la casilla de WhatsApp: el correo capturado lleva «✅ Aceptar la propuesta» y el aviso ofrece el botón.
- [ ] **Step 4: CRM y contratos:** pestaña «📜 Contratos y operación» del cliente creado (widget de contratos y botón de la ficha operativa); «Crear ficha operativa» en un cliente sin ficha; en `at-sign-contract.php` del contrato de servicios: el PDF se ve (enlace arreglado), la firma no aparece hasta guardar la revisión, guardar regenera el PDF; un contrato de soporte sigue firmándose como antes. Contactos muestra «Ficha única».
- [ ] **Step 5: Limpiar** la base local (filas `[PRUEBA LOCAL]` y `prueba-cierre-`), `preview_stop`, quitar las uniones con `rmdir`. Si algo falla, abrir una ronda de corrección con la tarea que corresponda.

---

### Task 12: WhatsApp con botones — Meta, n8n y bot principal (controlador, con el ok de Luis en cada paso)

No se delega: usa credenciales, n8n de PROD y la cuenta de Meta.

- [ ] **Step 1:** Con el ok de Luis, crear en Meta la plantilla `propuesta_respuesta` (`es`, categoría Utility, sin emojis en botones): cuerpo con `{{1}}` nombre, `{{2}}` empresa, `{{3}}` lo propuesto; tres respuestas rápidas «Acepto la propuesta», «La sigo evaluando», «No, gracias» y un botón de enlace «Ver la propuesta» con sufijo dinámico al código. Esperar `APPROVED`; si Meta la reclasifica como Marketing, informar a Luis antes de seguir. Las cargas de las respuestas rápidas se definen al enviar: `btn_propuesta_acepta_<código>`, `btn_propuesta_evalua_<código>`, `btn_propuesta_rechaza_<código>`.
- [ ] **Step 2:** Crear en n8n el flujo «Propuesta · WhatsApp»: webhook con autenticación por cabecera `X-AT-Secret` (credencial con el valor de `AT_REST_SECRET`, que n8n ya usa para llamar a WordPress) → envío de la plantilla con la credencial de WhatsApp de Meta de los recordatorios. Respaldo del JSON en `C:\Users\luis_\respaldos\n8n\`.
- [ ] **Step 3:** Con el ok explícito de Luis, agregar al bot principal `WhatsApp Tech - Principal (PROD)` la ruta de cargas `btn_propuesta_*`: POST a `https://automatizatech.cl/wp-json/at/v1/propuesta-respuesta` con `X-AT-Secret`, y respuesta en el chat según `ok`/`estado`/`motivo` (textos de la spec, etapa 4). Respaldo previo del flujo.
- [ ] **Step 4:** Prueba de punta a punta con el teléfono de prueba que dio Luis (anotado en el ledger, no en el repo) como cliente, sobre una propuesta de prueba en PROD que después se borra con su ok.
- [ ] **Step 5:** Activar: agregar `define('AT_N8N_CC_WHATSAPP', '<url del webhook>');` en `wp-config.php` de PROD (lo pega Luis o el controlador con su ok, nunca en el repo) y la opción `at_cc_wa_plantilla_activa` = `'1'`.

---

### Task 12b: El bot entiende que el cliente tiene una propuesta pendiente (controlador) — agregada por Luis el 25-sep

No se delega: modifica el bot principal de PROD (`WhatsApp Tech - Principal (PROD)`, `bBcNlFgBzQ0766Mq`). Aprobada por Luis el 25-sep junto con la Task 12. Se aplica **después** de la Task 13, cuando `at/v1/propuesta-contexto` (Task 10b) ya responde en PROD.

- [ ] **Step 1: Ubicar el punto único** donde se arma el `chatInput` del agente `Agente IA - Tech WhatsApp` (hoy `Merge Data`, `Merge Audio Data` y `Merge Image Data`; confirmar leyendo las conexiones del bot publicado). Respaldo previo del bot fuera del repo (bóveda).
- [ ] **Step 2: Nodos nuevos entre ese punto y el agente:**
  - HTTP «Contexto Propuesta»: POST `https://automatizatech.cl/wp-json/at/v1/propuesta-contexto` con la credencial «AT REST Secret (header)», cuerpo `{telefono, mensaje: chatInput}`, `timeout` 5000, `neverError` y `onError: continueRegularOutput`. Si WordPress falla o tarda, el bot sigue como hoy.
  - Code «Sumar Contexto»: si `tiene` es `true`, antepone al `chatInput` una línea `[Contexto interno, no la repitas: este número tiene la propuesta de AutomatizaTech para {empresa} esperando respuesta. Lo propuesto para partir: {propuesto}. Enlace para responder: {url}]`; conserva `phoneNumber`, `contactName`, `phoneNumberId` y `rate` tal cual. Si `tiene` es `false` o hubo error, deja el `chatInput` sin tocar.
- [ ] **Step 3: Regla en el `systemMessage` del agente** (agregar al final):

  > PROPUESTAS PENDIENTES: si el mensaje trae «[Contexto interno …]», la persona tiene una propuesta de AutomatizaTech esperando respuesta. Nunca des por aceptada ni por rechazada una propuesta por un mensaje de texto. Si dice que la acepta, agradécele y pídele que toque «Acepto la propuesta» en el mensaje que le enviamos o que use el enlace del contexto, para dejarla registrada. Si tiene dudas, respóndelas con lo que sabes y dile que Luis le escribirá. No inventes precios, plazos ni condiciones distintas a las de la propuesta. No repitas la línea de contexto.

- [ ] **Step 4: Pruebas locales** del código de «Sumar Contexto» con Node (con y sin propuesta, con error de WordPress) y del aplicador (idempotente, no escribe si el bot tiene un borrador sin publicar o si cambió el punto de inserción).
- [ ] **Step 5: Aplicar**, verificar la versión publicada y probar de punta a punta con el teléfono de prueba sobre una propuesta de prueba en PROD: escribir «sí, acepto» → el bot pide tocar el botón o usar el enlace, llega el correo a Luis, el estado no cambia; escribir desde un número sin propuesta → el bot responde como hoy.

---

### Task 13: Despliegue a PROD, documentación y PR (controlador, con autorización de Luis)

**Cotejo de solo lectura del 26-sep (commit `547f08d`):** los 29 destinos están como se esperaba. Los 12 nuevos no existen en PROD; los 12 del tema, el mu-plugin, `lib/contract-pdf-fpdf.php` y `ver-presentacion.php` son idénticos a la base de la rama (`d07b3f9`); los cinco de `contracts/` que se reemplazan siguen en sus versiones del 9-12 de mayo; `contract-mailer.php` y `create-contract.php` ya son iguales a `main` y no se suben. `main` no tocó ninguno de estos archivos desde la base. `php -l` pasa en el PHP de PROD. En la base: las nueve propuestas respondibles son 11, 12, 14, 16, 21, 22, 26, 42 y 43, todas `sent`; no hay correos repetidos en `wp_crm_clientes`; `crm_cliente_id` todavía no existe; el esquema de las tablas que escribe el módulo calza (`detail_type` varchar(50), `metadata` longtext, `type`/`status` de contratos con `servicios`, `at_pending`); La clave de las rutas REST ya está configurada. El `.htaccess` de la carpeta de contratos se escribe a mano en el paso 3.

**Script:** `.superpowers/sdd/2026-09-25-cierre-de-cliente/t13/deploy_cierre.py` (copia del scratchpad; `cotejar` por defecto, `subir`, `rollback <tar>`), con `render_check_cierre.php` (panel dibujado como administrador con wp-cli, solo conteos) y `archivar_siete.php` (`wp eval-file`, `aplicar` para escribir). Sube el contenido commiteado (`git show <commit>:<ruta>`), cada archivo a un temporal con `php -l` antes del `mv`, y compara la huella después.

- [x] **Step 1: Cotejar PROD** (hecho el 26-sep, resultado arriba). Se repite solo al correr `subir`: si algo cambió, aborta sin tocar nada.
- [ ] **Step 2:** Pedir la autorización de Luis con esta lista y este orden:
  1. `Docs/CONTRATO_SERVICIO_DESARROLLO.md` → `domains/automatizatech.cl/Docs/` (**fuera de `public_html`**: `load_template()` también la busca ahí, y una plantilla no necesita ser pública);
  2. los 11 archivos nuevos de `inc/cierre-cliente/` (inertes hasta el paso 6; `cargar.php` al final);
  3. contratos, **juntos**: `lib/contract-pdf-fpdf.php`, `contracts/contract-service.php`, `at-sign-contract.php`, `sign-contract.php`, `admin-contracts.php`, `client-contracts-widget.php`; enseguida el `.htaccess` con `Deny from all` en `uploads/automatiza-tech-contracts/` (el código solo lo reescribe al generar o firmar) y la carpeta `uploads/automatiza-tech-evidencias/` con el mismo `.htaccess`. Las firmas que el admin muestre por URL directa dejan de verse (403): es el efecto buscado;
  4. del tema: `inc/client-details-module.php`, `inc/client-operations-module.php`, `inc/contact-form.php`, `propuestas-admin/consultas.php`, `acciones.php`, `clasico.php`, `ficha.php` y `assets/css/propuestas-admin.css` (las llamadas al módulo están protegidas con `function_exists`);
  5. `wp-content/mu-plugins/crm-ai-completo.php`;
  6. `inc/admin-proposals.php`, que carga el módulo; verificación del panel como administrador;
  7. **archivar 11, 12, 14, 16, 21, 22 y 26** con `archivar_siete.php aplicar` (crea antes la columna `crm_cliente_id` con `at_cc_migrar_esquema()`); la 42 y la 43 quedan `sent`;
  8. `ver-presentacion.php` (la barra pública), recién con las siete archivadas.
- [ ] **Step 3:** Con la autorización, `python deploy_cierre.py subir`: respaldo del tema completo, respaldo rápido de los 17 archivos que se sobrescriben (extraído aparte y comparado), copia del `.htaccess` de contratos y volcado de `wp_automatiza_propuestas`, `_propuestas_details`, `wp_crm_clientes`, `wp_automatiza_tech_clients`, `_contracts` y `_clients_details`; sube por grupos con chequeos desde afuera tras cada uno y rollback automático si algo falla. El rollback conserva el `.htaccess` nuevo de contratos y devuelve las siete a `sent`.
- [ ] **Step 4:** Verificación (la hace el script): portada, `/wp-json/`, admin 302; `POST /wp-json/at/v1/propuesta-respuesta` y `/propuesta-contexto` sin clave → 401; `at_download_contract` sin token → 403; una sonda con nombre nuevo en cada carpeta privada → 403 (luego se borra); `sign-contract.php` con token inválido ≠ 500; `ver-presentacion.php` de la 43 con barra y de la 14 sin barra, ambas con `Cache-Control: no-store`; panel dibujado como administrador (ficha enviada con «Archivar», ficha archivada con «Desarchivar»). Informativo: un contrato real de la carpeta debería dar 403 (si no, purgar la caché del CDN). Después, Luis con su sesión prueba los botones de PDF en el admin de contratos, en la ficha del CRM y en el portal del cliente.
- [ ] **Step 5:** Luis revisa con su sesión: completar «Ajustes del cierre» (datos de transferencia), ver la pestaña del CRM, y usar «Registrar aceptación» en la propuesta 43 como primer caso real.
- [ ] **Step 6:** Documentar: sección «Cierre de cliente» en `Docs/METODO_AT/PROPUESTAS-FLUJO-V3.md`; puntero en `CLAUDE.md`; memoria `project_at_cierre_cliente`; commit, push y PR hacia `main` con la lista de despliegue y lo que queda pendiente de la Task 12.
