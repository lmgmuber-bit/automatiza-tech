# Reseña Google tras término — entrega A de Forja-Codex

> **Lectura vigente:** ver [Vuelta 6](#vuelta-6), correo de marca por Lienzo-Codex sobre `2965264`. Las vueltas anteriores son históricas; conservar sus evidencias y pendientes específicos. Revisión Eje pendiente.

Fecha: 19-nov-2026. **LOCAL / PEER_REVIEW_PENDING. No desplegado ni activado.**

Owner/autor A: Forja-Codex por asignación de Luis en este hilo. Homólogo B asignado: Eje, Claude/Forja-Claude; no se ha ejecutado ni simulado su revisión. Calibre y Vigía quedan pendientes. Sin delegaciones ni gastos.

Ruta: C3 · riesgo alto (autenticación y futuros mensajes) · incertidumbre media · cambios de código reversibles → Forja-Codex en la sesión actual, B independiente y gate de Luis antes de publicar. Modelo/esfuerzo efectivos y carga de un perfil CLI distinto: no verificados; no atribuyo un lanzamiento `--profile profundo` a esta sesión.

## Base, alcance y Git

- Worktree único: `C:/wamp64/www/automatiza-tech/.worktrees/guias`.
- Rama verificada: `codex/resena-google-impl`; base/HEAD `360d785`, mensaje `feat(resena): plantilla at_resena_google_cierre enviada a Meta (PENDING, UTILITY) + plan con el avance`.
- Estado inicial limpio. No se modificaron ramas ajenas, credenciales, configuración real, datos reales ni el script existente de plantilla.
- **Commits nuevos: ninguno.** `git add -- tests/plan-trabajo/resena-puras-test.php wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php` y el posterior `git commit -m 'feat(resena): reglas puras de elegibilidad e intentos con pruebas'` fallaron por `Unable to create .../.git/worktrees/guias/index.lock: Permission denied`. No se cambió el sandbox ni se reintentó con permisos superiores. Todo el artefacto queda sin commit, incluidos los archivos nuevos: `git diff` por sí solo no los muestra, hay que inspeccionarlos también.
- Fuentes: plan v4 y ambas revisiones B/B2 del plan, ficha `roles/forja.md`, `METODO.md`, skill `at-router-modelos`, estándar profesional y quality-gates, protocolos de aseo, visibilidad y documentación.
- Graphify query ejecutada antes de navegar. `graphify update .` intentado al terminar código: **FAIL**, `Rebuild failed: [WinError 5] Acceso denegado`, exit 1. Grafo anterior conservado, no se afirma actualizado. Una primera invocación no permitió recuperar su salida final; la segunda capturó este error. No hay cambios de Graphify en Git.

## Archivos del artefacto

Nuevos:

- `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php`
- `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-datos.php`
- `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-bajas.php`
- `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-correo.php`
- `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena.php`
- `N8N/resena-google/aplicar.py`
- `tests/plan-trabajo/resena-puras-test.php`
- `tests/plan-trabajo/resena-estado-test.php`
- `tests/plan-trabajo/resena-wp-test.php`
- `tests/plan-trabajo/resena-http-test.py`
- `tests/plan-trabajo/resena-n8n-test.py`
- `tests/plan-trabajo/resena-n8n-expresiones-test.js`
- Este informe A.

Modificados: `avance-panel.php`, `avance-puras.php`, `avance-datos.php`, `cargar.php` (en el mismo directorio del módulo), `inc/cierre-cliente/whatsapp-recordatorios.php`, `inc/cierre-cliente/whatsapp.php`, `assets/js/plan-trabajo.js`, `wp-content/mu-plugins/crm-ai-completo.php` y `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md`. Cambios acotados; no se reformatearon archivos existentes enteros.

## Resultado y decisiones

Implementados elegibilidad y estados puros; migración idempotente de ambas tablas; identidad estable; CAS por revisión; plazo/corte; un reenvío como máximo; estados inciertos bloqueados; correo en WordPress; bajas firmadas con confirmación POST; supresiones por canal/alcance; REST autenticado; botones de término/exclusión/permiso/prueba/envío/reenvío/revisión; adaptador del tipo `resena`; entrega/lectura/fallo tardío por intento; reservas de historial y avisos; mantenimiento y alerta del reloj; redirector público sin destino inicial; generador de flujos inactivos y adaptadores offline del bot; manual operativo y rollback.

Decisiones/precisiones respecto del plan:

1. **Tabla propia recomendada en D4/B2-4.** `proyecto_clave` NOT NULL UNIQUE, `crm_id` obligatorio y `plan_id` opcional. La primera vinculación conserva la clave `crm:N`; no renombra ni reinicia marcas. Un candado MySQL por CRM serializa la resolución; escrituras de identidad incluyen `IS_USED_LOCK(...)=CONNECTION_ID()` para rechazar una reconexión que haya perdido el candado. No hay proveedor bajo ese candado. La ficha con varios proyectos obliga a abrir el plan concreto.
2. **D1: término canónico en la tabla, proyectado/reflejado en `avance.termino`.** Evidencia: `at_av_normalizar()` descartaba claves no enumeradas, `at_av_guardar()` reemplaza todo el JSON y existe el renderer concurrente. La tabla contiene tanto término como intentos para que un guardado antiguo de avance no reinicie elegibilidad. Se conserva `termino` en normalización y en la mezcla del panel; el panel lo refleja por CAS. Es una precisión explícita del almacenamiento; no se presenta la columna avance como autoridad del envío.
3. **Permiso acreditado propio para reseñas.** El booleano de Retomar no contiene origen/fecha y su ayuda considera una conversación suficiente. No se migra ni se da permiso por defecto. Se liga evidencia al número actual y al proyecto. El panel de CRM expone esos controles incluso sin plan.
4. **Autorización adicional de WhatsApp de un solo uso.** El webhook recibe solo referencias; n8n canjea `intento_id` y modo en `autorizar-whatsapp` antes del proveedor. Un webhook repetido no vuelve a despachar. Reclamo del panel y del Schedule usan el mismo disparador WordPress → webhook; el Schedule no vuelve a enviar con la respuesta del reclamo. Ambas rutas por plan tienen equivalentes por proyecto para el caso sin plan. Se revisan enlace/contacto/permiso/bajas de nuevo al despachar.
5. **Ampliaciones de archivos necesarias:** cuatro líneas de integración en `crm-ai-completo.php` para la pestaña sin plan; JS para abrirla tras admin-post; `whatsapp.php` porque el controlador existente descartaba delivered/read antes de resolver un wamid. Cambiar solo la lista de tipos no cubría D4.c. Se conservó el comportamiento de los otros tipos.
6. **Historial con reserva duradera y estados pendientes visibles.** Una caída entre reserva e inserción no provoca otro envío ni otra inserción desde una devolución repetida. No se promete escritura exactamente una vez ante cualquier fallo del driver; una reserva incompleta/fallida requiere revisión. Avisos fallidos también quedan visibles. El INSERT del historial lleva CONNECTION_ID de la conexión original para impedir que wpdb lo repita en otra conexión tras perder la respuesta; la reconexión simulada tuvo rojo→verde. La comprobación en MySQL real sigue pendiente.
7. **No se modificó el bot vivo ni se obtuvo su exportación.** El generador tiene adaptadores offline de BAJA y estados, probados contra un grafo ficticio; Eje debe contrastarlos con la revisión real de su bot. El reenvío de estados usa la ruta existente y datos mínimos, por lotes de 20. No se declara instalada esa integración.
8. **Límite de veinte pruebas conservadas** por proyecto para evitar crecimiento ilimitado del JSON; se devuelve un bloqueo explícito y no se borran intentos. No cambia el tope de dos invitaciones reales.
9. **TDD parcial del proceso:** las pruebas puras se escribieron primero y fallaron por módulo inexistente; las correcciones de redirección, enlace retirado antes del despacho y plan reasignado tuvieron rojo→verde observado. La suite de integración completa se agregó tras el primer código de persistencia/motor. No se cumplió TDD estricto paso a paso para todos los módulos ni se lo presenta como cumplido. Los commits por paso no pudieron realizarse por permisos.

La URL real de Google sigue sin configurar: no se inventó. Los datos ficticios de las pruebas nunca se instalan como opciones iniciales en producción.

## Pruebas reproducibles y resultados reales

CWD de todos los comandos: el worktree anterior. PHP `8.5.0`, Python `3.13.14`, Node `v24.18.1`. El aviso conocido del DLL Xdebug aparece incluso con `-d xdebug.mode=off`; los resultados siguientes se basan en las aserciones y salida del proceso, no en ocultar ese aviso. Lint usa `-n`, sin cargar ese DLL.

| Comando exacto | Resultado |
|---|---|
| `& 'C:/wamp64/bin/php/php8.5.0/php.exe' -d xdebug.mode=off tests/plan-trabajo/resena-puras-test.php` | **PASS**, 49 comprobaciones, 0 fallas. Fechas/DST, cliente, corte, canales/bajas, consentimiento, modo, cupos, devoluciones tardías, normalización. |
| `& 'C:/wamp64/bin/php/php8.5.0/php.exe' -d xdebug.mode=off tests/plan-trabajo/resena-estado-test.php` | **PASS**, 46 comprobaciones con dobles de WP/BD, 0 fallas. CAS intercalado, 401, baja tras consulta, correo incierto, callback repetido, fallo temprano, prueba→real, CC/BCC, doble reenvío, tercer intento, vínculo posterior, entregado/leído por controlador real, capability/nonce negativos, enlace retirado, plan reasignado y registro ilegible y replay simulado tras reconexión. **No prueba concurrencia real de MySQL.** |
| `python -B tests/plan-trabajo/resena-n8n-test.py` | **PASS**, 9 pruebas offline; flujos inactivos, no reintentos, autorización previa, respaldo antes de crear con API ficticia, rechazo de activeVersion, no sobreescritura, adaptadores BAJA/estados. Sin archivos efímeros ni red. |
| `python -B N8N/resena-google/aplicar.py --dry-run` | **PASS**, JSON emitido y parseado: 2 flujos, active=false, 4 y 6 nodos. Sin API ni lectura de credenciales. |
| `python -B N8N/resena-google/aplicar.py --dry-run \| node tests/plan-trabajo/resena-n8n-expresiones-test.js` | **PASS**, 8 expresiones compiladas; teléfono/idioma y 4 resultados (aceptado, rechazo, 5xx, timeout) ejecutados con datos ficticios. No es ejecución del motor n8n. |
| `node --check wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js` | **PASS**, exit 0. |
| `& 'C:/wamp64/bin/php/php8.5.0/php.exe' -d xdebug.mode=off tests/cierre/puras-test.php` | **PASS**, `TODO OK`, exit 0. |
| `& 'C:/wamp64/bin/php/php8.5.0/php.exe' -d xdebug.mode=off tests/entregables/puras-test.php` | **PASS**, `TODO OK`, exit 0. |
| `& 'C:/wamp64/bin/php/php8.5.0/php.exe' -d xdebug.mode=off tests/plan/avance-puras-test.php` | **PASS**, `TODO OK`, exit 0. |
| `& 'C:/wamp64/bin/php/php8.5.0/php.exe' -d xdebug.mode=off tests/plan/cierre-puras-test.php` | **PASS**, `TODO OK`, exit 0. |
| `& 'C:/wamp64/bin/php/php8.5.0/php.exe' -d xdebug.mode=off tests/plan-trabajo/resena-wp-test.php` | **NOT_RUN**: guardia exit 2; faltan AT_WP_LOAD de una instalación desechable y AT_RESENA_LOCAL_TEST=1. Script preparado para migración, dos ejecuciones idempotentes, 4 procesos concurrentes, vínculo posterior, CAS real, prueba→real, preservación de marcas y dispatch REST. Bloquea correo/HTTP externo y exige prefijo `resenatest_` y host local. |
| `python -B tests/plan-trabajo/resena-http-test.py` | **NOT_RUN**: guardia exit 2; falta AT_RESENA_TEST_URL de un WP local aislado. Script listo con 7 controles HTTP negativos/de cabeceras; no llama REST autenticado ni genera envíos. |
| `& 'C:/wamp64/bin/php/php8.5.0/php.exe' -d xdebug.mode=off tests/cierre/whatsapp-recordatorio-wp-test.php` | **NOT_RUN**: guardia exit 1, falta AT_WP_LOAD. No llegó a sus aserciones. |
| `& 'C:/wamp64/bin/php/php8.5.0/php.exe' -d xdebug.mode=off tests/cierre/whatsapp-estado-wp-test.php` | **NOT_RUN**: misma guardia; no llegó a sus aserciones. |
| `git diff --check` | **PASS**, sin errores; Git advierte la conversión prevista LF→CRLF de algunos archivos modificados. |
| `graphify update .` | **FAIL**, exit 1, WinError 5. |

Lint exacto ejecutado, **PASS: 15 archivos PHP, 0 fallas**:

```powershell
$phpFiles = @('wp-content/mu-plugins/crm-ai-completo.php','wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php','wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp-recordatorios.php') + (Get-ChildItem wp-content/themes/automatiza-tech/inc/plan-trabajo/resena*.php).FullName + @('wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php','wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php','wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-datos.php','wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php') + (Get-ChildItem tests/plan-trabajo/*.php).FullName
$failed = 0
foreach ($f in $phpFiles) { $null = & 'C:/wamp64/bin/php/php8.5.0/php.exe' -n -l $f; if ($LASTEXITCODE -ne 0) { $failed++; Write-Output ('FAIL lint ' + $f) } }
Write-Output ('PHP lint: ' + $phpFiles.Count + ' archivos, ' + $failed + ' fallas')
```

El rojo inicial de integración incluía una fixture anterior al corte histórico del 19-nov; se corrigió esa prueba para usar la vía manual y comprobar el corte en funciones puras. El primer positivo automático real solo puede ocurrir desde el 26-nov para términos desde el 19-nov; no se adelantó el reloj del sistema ni se añadió instrumentación de pruebas al código productivo.

Documentación oficial consultada vía Context7 el 19-nov: [dbDelta](https://developer.wordpress.org/reference/functions/dbdelta/), [opciones de HTTP Request de n8n](https://github.com/n8n-io/n8n-docs/blob/main/docs/integrations/builtin/core-nodes/n8n-nodes-base.httprequest/README.md) y [reintentos HTTP](https://github.com/n8n-io/n8n-docs/blob/main/docs/integrations/builtin/core-nodes/n8n-nodes-base.httprequest/common-issues.md). No se instalaron dependencias. No se verificaron versiones de WordPress/n8n/Meta del hosting ni aprobación actual de plantilla; el repositorio de este worktree no contiene el core de WP.

## Pendientes y riesgos para B

- **Eje/Forja-Claude:** inspeccionar archivos nuevos y diff contra 360d785, repetir pruebas, contrastar las decisiones de identidad/término y la semántica del reenvío; verificar los adaptadores contra el bot real y su credencial/validación del webhook de Meta. B sigue **PEER_REVIEW_PENDING**.
- **Calibre:** migración real, índices completos/NOT NULL, tipos/longitudes frontend ≤ servidor ≤ columna, GET_LOCK y reconnect, creación/vinculación concurrentes y CAS en MySQL. El historial usa una reserva y un INSERT separados: revisar la ventana de caída, la reconstrucción manual y la guarda CONNECTION_ID frente al replay interno de wpdb tras una reconexión. Los dobles no acreditan esos comportamientos de servidor. No hubo revisión nativa de Calibre ni se declara su PASS.
- **Vigía:** REST y precedencia de parámetros, HMAC/rotación de sal y GET/POST de baja, cabeceras/caché en el hosting, capability/nonce, contactos de prueba y plugins SMTP que agregan destinatarios, acceso al webhook n8n, canje único y logs/retención del bot. El permiso de máquina es global, igual que el patrón existente; debe custodiarse en el servidor/credenciales n8n. No hubo revisión nativa de Vigía.
- **Operación:** URL de Google vacía, plantilla sin consulta de estado, destinos/prueba no configurados, webhook vacío, flujos no creados, bot vivo sin modificar, versiones de nodos/API sin ejecución real, no integración contable automática probada con Balance ni retención/accesos del servidor comprobados. No activar hasta completar el plan §5/§8/§9 con los responsables. Toga/Faro/Balance y primer caso real siguen con sus gates operativos.
- **Aislamiento:** una baja de alcance todo se respeta aquí, pero no modifica por sí sola otros módulos. WP-Cron depende del servidor/tráfico. Las autorizaciones ya consumidas y las llamadas ya iniciadas requieren revisión al detener el sistema.
- **Documentación externa:** README privado de la bóveda y evidencias en Documents/AT-Informes no modificados: fuera del alcance de escritura y del worktree autorizado. La documentación/evidencia de este hito se conserva aquí; no se declara generado un PDF de entrega.

No se hizo SSH/SFTP, despliegue, API n8n/Meta, consumo, push, merge, rebase ni stash. No se abrieron archivos de claves, .env o wp-config.php.

## Lista exacta para PROD (solo preparación, NO ejecutada)

Raíz local de todas las entradas: `C:/wamp64/www/automatiza-tech/.worktrees/guias/`. Destinos relativos a la raíz del sitio. **Todos OBLIGATORIOS; no hay archivos opcionales para activar esta función.** Despliegue bajo mantenimiento y autorización de Luis; instrucciones de migración/rollback en [la guía, §8](../../METODO_AT/ESTADO-DEL-PROYECTO.md#8-pedir-reseña-implementación-local-19-nov-2026).

| Orden | Ruta local (añadir la raíz anterior) | Destino relativo en PROD | Clase |
|---|---|---|---|
| 1 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php` | OBLIGATORIO |
| 1 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-datos.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-datos.php` | OBLIGATORIO |
| 2 | **Ejecutar/verificar migración antes del código de envíos** | `at_resenas` y `at_resena_bajas` con el prefijo del sitio | OBLIGATORIO |
| 3 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-bajas.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-bajas.php` | OBLIGATORIO |
| 3 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-correo.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-correo.php` | OBLIGATORIO |
| 3 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena.php` | OBLIGATORIO |
| 4 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php` | OBLIGATORIO |
| 4 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-datos.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-datos.php` | OBLIGATORIO |
| 4 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` | OBLIGATORIO |
| 5 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` | OBLIGATORIO |
| 6 | `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp-recordatorios.php` | `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp-recordatorios.php` | OBLIGATORIO |
| 6 | `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php` | `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php` | OBLIGATORIO |
| 7 | `wp-content/mu-plugins/crm-ai-completo.php` | `wp-content/mu-plugins/crm-ai-completo.php` | OBLIGATORIO |
| 7 | `wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js` | `wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js` | OBLIGATORIO |

**No subir por FTP:** `N8N/resena-google/aplicar.py`, la carpeta `tests/plan-trabajo/`, docs/informe A, `.git`, `graphify-out`, exportaciones de bot, respaldos, manifiestos privados, credenciales, .env/wp-config ni destinos de prueba copiados a un archivo. Los flujos se administran después, inactivos y con un encargo separado; el generador no es un archivo del sitio.

## Checklist A del METODO.md

| Control | Estado | Evidencia y límite |
|---|---|---|
| A1 Objetivo y aceptación | PASS | Plan v4 completo y revisiones leídos; objetivo, excepciones manuales, supresiones, cupos y no escrituras externas delimitados aquí y en guía §8. |
| A2 Alcance/base | PASS | Rama/base/estado inicial comprobados; inventario anterior y fingerprints posteriores; únicamente archivos de este encargo. Sin commits por sandbox. |
| A3 Resultado y coherencia | NOT_RUN (aceptación integrada) | Código y pruebas locales existen, decisiones explícitas. WP/MySQL/n8n/Meta y revisión B no ejecutados; no se otorga aceptación de servidor/PROD. |
| A4 Vigencia | PASS (fuentes/código local); NOT_RUN (servidores) | PHP/Python/Node reales indicados y docs oficiales consultadas; sin dependencias nuevas. Versiones efectivas de WP/n8n/Meta y compatibilidad de nodos pendientes. |
| A5 Pruebas pertinentes | PASS (local); NOT_RUN (integración real) | 49 puras + 46 motor con dobles + 9 Python; expresiones, lint y 4 suites de regresión. Tabla anterior separa guardias y fallo Graphify. TDD parcial declarado. |
| A6 Entrega y riesgos/rollback | PASS | Fuentes, contratos, diccionario/diagramas, manual, orden FTP, rollback conservador, límites y responsables pendientes documentados. No equivale a revisión B o permiso de publicación. |

**Visibilidad V1–V6 proporcional:** superficie/entorno y objetivo definidos (PASS documental); rutas de reseña/baja transaccionales con noindex y panel/REST privados (PASS de código); cabeceras HTTP/acceso real en servidor (NOT_RUN); indexación/citación/campañas (NOT_APPLICABLE por finalidad de estas rutas). Noindex no sustituye los controles de clave, token o permisos.

**Aseo:** no se crearon temporales propios: fuentes, scripts y tests son permanentes del artefacto. `python -B` evita bytecode. El checkpoint task-close con la sesión nativa completa devolvió `preserved`, motivo «Ruta ausente, ocupada o sin permiso: conservar y revisar». No se declaró limpio el registro externo, no se retiraron archivos y no se borraron fuentes/evidencias. Las nuevas carpetas no contienen temporales identificados. Registro externo queda pendiente de revisión con acceso apropiado.

**Siguiente acción:** Eje/Forja-Claude revisa este snapshot y los nuevos archivos; Calibre y Vigía ejecutan sus controles sobre la misma revisión. Luis decide despliegue/activación después de esos resultados. No hay merge/PR/push solicitado ni ejecutado.

## Fingerprints del snapshot entregado

SHA-256 de los otros archivos del artefacto; el informe no se incluye a sí mismo. Incluyen los bytes locales y sus fines de línea. Revisar archivos nuevos además del diff.

| Archivo relativo al worktree | SHA-256 |
|---|---|
| Docs/METODO_AT/ESTADO-DEL-PROYECTO.md | 085da0a2e82b105e47beb30e1eabbdf3764cc2daad126874b95af151a17e51e1 |
| N8N/resena-google/aplicar.py | 2b21d2398f09aace6c502b44b6f9306b10d040bf2cbb5cf5bca53c400436137c |
| tests/plan-trabajo/resena-estado-test.php | c28e463fb8e8af98808e6d7cdda201df91accfecb9e44ed10624b2011d44272c |
| tests/plan-trabajo/resena-http-test.py | 8c23258ebf6539a2767b61160d0cef7e1aa1e3defeacdd183917b3a6bc66e12a |
| tests/plan-trabajo/resena-n8n-expresiones-test.js | 8bff54f35cca58a32dfaf4356412854f142edc51fd5cfc1a8c82998e6a796135 |
| tests/plan-trabajo/resena-n8n-test.py | 0750b8d20a4363c9a88c36d0fe60ce87febe1ab1702f8ef371dc97180e6b8bd0 |
| tests/plan-trabajo/resena-puras-test.php | e1bfd3bed2fc5af0e8cde5d8d13a57a51eadd9b2b8699a3c000a6133dd06e638 |
| tests/plan-trabajo/resena-wp-test.php | b80097662cfc2a869a3fc1a46bebef463939bf3830e538bacca91a07cc97e723 |
| wp-content/mu-plugins/crm-ai-completo.php | 3cdaf599d9fadd5ee81d1b97c8cc19e6f2f84acf311dbfe3e038349b5a0c6a4c |
| wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js | f21ee3587f041413dbd345b343693547d12766e346eae68163bb34274cf410e5 |
| wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php | 5beaff74fb65cdcf54332f87112e2bafe24f7d9a6b0e92f22cb7f825eb640518 |
| wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp-recordatorios.php | 87aaadb17d15a4e9a460cb8ce5567f213ca193a73056c264e17c07e0fda2551a |
| wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-datos.php | 48dd9dac941114518e4a127dcb498fcf77f2615a9b02bf2bf2ad919735b68e73 |
| wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php | 4a2461bae044a480f0187703ff653ee66f4677afca8fe5e34e4836aa7b092f1a |
| wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php | 10db9a00ba237c342a38404bd802480b3a35251565c29940250910062bdf738e |
| wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php | cd9d37a3ee53a45771aae31fc2843497304d861ef0633cb5d98d34c2ca8dab27 |
| wp-content/themes/automatiza-tech/inc/plan-trabajo/resena.php | 89729b51e6c0320c83e16082bb1a4a33519d0cfbfafb88af30ff138a20806091 |
| wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-bajas.php | d4e864740e445c88775b96df0dba57f2c7f4751b64cb48b8b945406ad5b3bc21 |
| wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-correo.php | bb92957e56c435a9e68410dcd71af5414305ae6087d5cc9e016a0e756131e11d |
| wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-datos.php | 6660433b965c9e9efb66c0f3c5326105358feb5775e473183f7d7a69437a2635 |
| wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php | e248471077d09e31132b162fb68a48e2029131b69c4a4a7460c724389b7f0dfe |

## Vuelta 2

19-nov-2026 · autor A **Forja-Codex**, revisor **Eje**. Base verificada `50c9410`; mismo worktree `C:/wamp64/www/automatiza-tech/.worktrees/guias` y rama `codex/resena-google-impl`, inicialmente limpia. Se corrigen los hallazgos recibidos de Vigía-Claude, Forja-Claude y Calibre-Claude; no se atribuye al autor una revisión independiente de sus correcciones. **APLICADO LOCAL / PENDIENTE DE VERIFICACIÓN DE EJE.**

Ruta final: **C3**, riesgo alto por HMAC/autenticación y futuros mensajes, incertidumbre media, reversible en local → Forja-Codex en el modelo/esfuerzo heredados del hilo, sin cambio de modelo ni lanzamiento CLI profundo comprobado. La clasificación inicial de esta vuelta fue C2; se eleva explícitamente por el eje de autenticación. No se delegó implementación. Aseo-Codex intervino únicamente como respaldo de residuos, sin cubrir revisión B ni QA.

### Hallazgos: rojo observado, arreglo y resultado

Abreviaturas de archivos: `P=wp-content/themes/automatiza-tech/inc/plan-trabajo/`; los números son líneas de esta vuelta. Las pruebas cuyo nombre empieza con el identificador se encuentran en `tests/plan-trabajo/resena-v2-casos.php`, cargadas por `resena-estado-test.php`; las pruebas de motor están en `resena-migracion-test.php`. Los grupos de esquema se probaron juntos por su dependencia de la misma migración v1→v2. Cada grupo volvió a ejecutar la suite offline completa tras su arreglo.

| Hallazgo | Prueba que se observó fallar antes del arreglo final | Arreglo | Resultado local |
|---|---|---|---|
| Vigía M1 | `Vigia-M1 webhook fijo ignora opcion manipulada` (1 fallo) | `P/resena.php:4,85`: constante del tema y URL idéntica al path de `aplicar.py:48`; ninguna opción de BD determina el receptor de la clave. | PASS. Secreto propio pendiente, motivo abajo. |
| Forja I1 / Vigía B3 / Calibre H1 | `Forja-I1 Vigia-B3 Calibre-H1 termino ilegible null aviso unico` (1 fallo) | `P/resena-datos.php:207`: catch Throwable, null y `at_rg_avisar_ilegible`; se conserva el fallback de avance. | PASS; también lector real `at_av_leer`, sin avisos repetidos. Se corrigió la fixture inicial para no reutilizar un aviso reservado por un caso anterior. |
| Forja I2 / Vigía B4 / Calibre H3 | `Forja-I2 Calibre-H3 indice exacto wamid`, `Forja-I2 Vigia-B4 wamid ajeno no escribe transient` (rojos en grupo de esquema) | `P/resena-datos.php:8,139` + `P/resena.php:132`: tabla hija e índice ASCII binario por wamid, relleno al aceptar y en migración. Se elimina LIKE sobre datos y todo transient de estados no correlacionados. | PASS. Rojo adicional real: `H3 índice admite wamid de 256 bytes válido para servidor`; columna final 256, sin truncar el máximo admitido por el validador existente. Límite temprano abajo. |
| Vigía B1 | `Vigia-B1 URL estricta …` (11 URLs fallaron) | `P/resena-puras.php:39`: solo tres familias solicitadas; rechaza puntos dobles, %2e, barras inversas, dobles segmentos, doble codificación y parámetros de navegación ajenos. | PASS: 11 negativos, 4 positivos, incluidos los dos ejemplos textuales de Vigía. |
| Vigía B2 / Forja M2 | `Vigia-B2 Forja-M2 mensaje arbitrario no reflejado`, `… codigo resuelto en servidor` (2 fallos) | `P/avance-panel.php:822,927`: admin-post devuelve códigos y el panel usa mensajes fijos del servidor. | PASS; texto de URL arbitrario no se refleja. |
| Forja M1 | `Forja-M1 abrir panel no crea filas prepara accion`, `… prospecto sin panel ni filas` (2 fallos); después `… primera accion vincula identidad existente` (1 fallo) | `P/resena-datos.php:166`, `P/avance-panel.php:798,857`, `wp-content/mu-plugins/crm-ai-completo.php:1999,2264`: lectura sin creación/vínculo; creación y vínculo tras capability/nonce y comprobación de cliente al actuar. | PASS: ni fila ni vínculo al abrir, prospecto oculto y primera acción conserva identidad. |
| Forja M3 | `Forja-M3 normalizacion chilena inequivoca`, `… panel explica telefono invalido` (2 fallos) | `P/resena-puras.php:32`, `P/avance-panel.php:820`: E.164 y dos normalizaciones chilenas inequívocas; explicación visible. | PASS: positivos y seis ambiguos rechazados. |
| Calibre H2 | `Calibre-H2 bigint con ancho estable dbDelta` (rojo del grupo); `H2 dbDelta segunda pasada sin ALTER repetido` (rojo real en MariaDB por ancho de la columna nueva) | `P/resena-datos.php:8`: bigint(20) unsigned, abierto tinyint(1). | PASS en MySQL 8.4.7 y MariaDB 11.4.9; segunda pasada sin ALTER. |
| Calibre H4 | `Calibre-H4 abierto indexado`, `… filtra mantenimiento y rango en SQL` (rojos) | `P/resena-puras.php:7`, `P/resena-datos.php:130`, `P/resena.php:174,185`: proyección abierto en CAS; mantenimiento WHERE abierto=1; cola BETWEEN corte y fecha local menos siete días. | PASS; motor comprueba apertura/cierre de la proyección. |
| Calibre H5 | `Calibre-H5 holgura columnas` (rojo); `H5 H6 no marca v2 si ALTER de holgura no se aplicó` (rojo real) | `P/resena-datos.php:8,51`: nota 1000/origen 50; servidor conserva 500/30; valida anchos reales antes de marcar versión. | PASS; v1 con filas conserva datos y baja, incluso con recuperación de ALTER fallido. |
| Calibre H6 | `Calibre-H6 version numerica esperada 2` (rojo) y prueba real de ALTER fallido anterior | `P/resena-datos.php:4,51`: versión esperada '2', compara el valor numérico serializado; guards de módulos/controlador actualizados. | PASS; migración v1→v2 e idempotencia en ambos motores. |
| Calibre H7 | `Calibre-H7 migracion manual ignora bloqueo` (rojo) | `P/resena-datos.php:51`: argumento manual, ignora transient; automática conserva espera de cinco minutos. | PASS real con transient ya presente, y reintento manual tras fallo parcial. |
| Calibre H8 | `Calibre-H8 error propio plan reasignado`, `… panel plan reasignado` (2 fallos) | `P/resena.php:10,30`, `P/avance-panel.php:805,857`: mensaje específico en panel/reclamo/despacho y rechazo de acción sobre asociación ajena; no migrar identidad. | PASS. |
| Calibre H9 | `Calibre-H9 ocho intentos CAS con espera entre choques` (1 fallo) | `P/resena-datos.php:153`: ocho intentos; usleep(random_int(5000,40000)) entre conflictos, sin repetir efectos externos. | PASS: siete conflictos, éxito en octavo; mínimo acumulado 35 ms. |
| Calibre H10 | `Calibre-H10 baja idempotente sin VALUES obsoleto` (1 fallo) | `P/resena-bajas.php:18`: ON DUPLICATE KEY UPDATE id=id. | PASS; ambos motores verifican una fila y evidencia original conservada al repetir. |
| Vigía I1 | `Vigia-I1 HMAC dominio resena-baja` (1 fallo) | `P/resena-bajas.php:24`: dominio at-resena-baja\| en firma. | PASS; cambia tokens previos de desarrollo, antes de PROD. |
| Vigía I2 | **Sin rojo independiente**: se incorporó dentro de CAS/migración antes de agregar la prueba de motor. | `P/resena-datos.php:135`: NULLIF(%s,''). | PASS real: fecha vacía queda NULL en ambos motores. Desviación TDD explícita abajo. |
| Vigía I3 | `Vigia-I3 detalle escapado aviso HTML` (1 fallo) | `P/resena.php:171`: esc_html del mensaje del aviso. | PASS; payload de enlace termina como texto escapado. |
| Vigía I4 | `Vigia-I4 proyecto solo URL no cuerpo`, `… cuerpo no inventa proyecto` (2 fallos) | `P/resena.php:208`: get_url_params para proyecto y plan. | PASS. |
| Forja M5 | `Forja-M5 M6 avisos tardio y limite en panel`; `Docs M5 M6 M8 H11 I2 … desconocido→aceptado` (rojos) | `P/avance-panel.php:848`; guía §8 y plan §10: resultado tardío puede promover desconocido→aceptado, sin nuevo envío. | PASS documental y prueba del estado tardío sin nuevo intento. |
| Forja M6 | misma prueba de aviso, y `Docs … 20 pruebas` (rojos) | `P/avance-panel.php:847`; guía §8 y plan §10: máximo 20, sin limpieza automática y aviso. | PASS; prueba 21 rechazada sin enviar ni borrar las veinte existentes. |
| Forja M8 | `Docs … reclamar no devuelve datos de WhatsApp` en ambos documentos (rojos) | guía `ESTADO-DEL-PROYECTO.md:283`; plan D4/§10: webhook con referencias y autorizar-whatsapp de un uso. | PASS documental; sin modificación de n8n vivo. |
| Calibre H11 | `Docs … crm_historial` y `Docs … UTC` (rojos donde faltaban) | guía:327; plan §10: UTC propio, hora local en crm_historial, fecha civil/ISO con offset Santiago. | PASS documental. |
| Calibre I2 | `Docs … MySQL 5.7` / `Docs … Hostinger` (rojos) | guía:333 y plan §10: gate de verificar motor/versiones del hosting antes de desplegar; incompatibilidad señalada por B. | PASS documental; Hostinger NOT_RUN. |
| Forja M4 (no cambiar decisión) | `Forja-M4 regla pura conserva consumo ambos fallidos` (rojo por función aún inexistente) | `P/resena-puras.php:77`: regla pura de una línea, usada por at_rg_reales. | PASS; ambos fallidos siguen consumiendo. Decisión de Luis pendiente. |
| Forja M7 (no tocar) | No se añadió cambio funcional de pruebas simultáneas. | Comportamiento conservado. | Sin atribuirle una nueva aceptación funcional. |

### Suite completa y resultados reales

CWD del worktree. PHP **8.5.0**, `-d xdebug.mode=off` en ejecución; lint `-n -l`. Persiste el aviso de carga del DLL Xdebug de la instalación: no es una aserción y no se oculta como si estuviera corregido.

```powershell
& tests/plan-trabajo/resena-suite.ps1 -ConMotores
```

El runner ejecuta estos comandos (también se pueden repetir por separado):

| Comando | Resultado observado |
|---|---|
| `& 'C:/wamp64/bin/php/php8.5.0/php.exe' -d xdebug.mode=off tests/plan-trabajo/resena-puras-test.php` | PASS, 49 comprobaciones, 0 fallas. |
| `& 'C:/wamp64/bin/php/php8.5.0/php.exe' -d xdebug.mode=off tests/plan-trabajo/resena-estado-test.php` | PASS, **112** comprobaciones, 0 fallas. Dobles WP/SQL y lector real at_av_leer; no equivale a WP completo. |
| Mismo PHP y flags con `tests/cierre/puras-test.php`, `tests/entregables/puras-test.php`, `tests/plan/avance-puras-test.php`, `tests/plan/cierre-puras-test.php` | Las cuatro suites PASS, TODO OK, exit 0. |
| `python -B tests/plan-trabajo/resena-n8n-test.py` | PASS, 9 pruebas, offline, sin credenciales. |
| `python -B N8N/resena-google/aplicar.py --dry-run \| node tests/plan-trabajo/resena-n8n-expresiones-test.js` | PASS, 8 expresiones y 4 resultados; no ejecuta n8n. |
| `node --check wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js` | PASS, exit 0. |
| Lint PHP enumerado por `resena-suite.ps1` | PASS, 13 archivos, 0 errores. |
| `& tests/plan-trabajo/resena-motor-test.ps1 -Motor mysql` | PASS, MySQL **8.4.7**, **15 comprobaciones**, 0 fallas; servidor detenido y fixture retirada. |
| `& tests/plan-trabajo/resena-motor-test.ps1 -Motor mariadb` | PASS, MariaDB **11.4.9**, **15 comprobaciones**, 0 fallas; servidor detenido y fixture retirada. |
| `& 'C:/wamp64/bin/php/php8.5.0/php.exe' -d xdebug.mode=off tests/plan-trabajo/resena-wp-test.php` | NOT_RUN, guardia exit 2: faltan AT_WP_LOAD y AT_RESENA_LOCAL_TEST de WP aislado. |
| `python -B tests/plan-trabajo/resena-http-test.py` | NOT_RUN, guardia exit 2: falta AT_RESENA_TEST_URL. |
| `git diff --check` | PASS; avisos LF→CRLF esperados, sin errores de whitespace. |
| `graphify update .` | FAIL, WinError 5 / acceso denegado al rebuild. Se usó query al iniciar; no se atribuye grafo actualizado. |

Las pruebas de motor usan **wpdb y la función dbDelta oficiales de WordPress 7.1.2 local**, extraída sin modificar de upgrade.php para no cargar instalación, plugins ni configuración. Arrancan un datadir nuevo en TEMP, sin archivos de opciones externos y con escucha exclusiva en 127.0.0.1/puerto efímero. La cuenta vacía de fixture no es una credencial del sitio. Crean las dos tablas con el **DDL exacto de 50c9410**, insertan filas ficticias con JSON válido e ilegible y una baja; luego ejecutan la migración real. No cargan wp-config ni leen claves, ni conectan al servidor WAMP ya en uso. No acreditan concurrencia completa ni HTTP/SMTP/Meta/n8n en PROD.

### Desviaciones y límites

1. **TDD:** hubo rojo→arreglo→suite verde en los grupos descritos. Los seis hallazgos ligados al mismo esquema se implementaron como un grupo de migración; no se presenta como seis ciclos independientes. **Vigía I2 no tuvo un rojo independiente previo**: NULLIF se introdujo con ese CAS y su verificación semántica en motor se añadió después; el anterior SQL también buscaba producir NULL. Por tanto, no afirmo TDD estricto individual para el 100 % de los detalles. Las dos regresiones posteriores (ALTER parcial y máximo wamid 256) sí tuvieron rojo real antes del arreglo final.
2. **Estados tempranos:** no se puede probar que un wamid desconocido es de reseña antes de que resultado registre su vínculo. Para cumplir que un identificador ajeno no cause escrituras, se suprimió esa caché, sin guardar transients por mera existencia de otro intento pendiente. Esto puede perder un estado que llegue antes de resultado; la aceptación y los estados posteriores correlacionados funcionan. Eje debe comprobar la secuencia del bot y acordar este límite antes de activar. No se repite automáticamente el envío para compensarlo.
3. **Secreto propio de webhook:** permanece AT_REST_SECRET, enviado exclusivamente al receptor fijo, con redirecciones deshabilitadas. Una clave separada requiere provisión coordinada de WordPress/credencial n8n. Ese paso no es viable dentro de la prohibición de leer/provisionar secretos y tocar servicios reales de este encargo; no se inventó una clave pública ni se tocó wp-config. Queda anotado para el responsable.
4. **URL Google:** se retiraron los hosts abreviados genéricos y las rutas maps amplias anteriores; únicamente se aceptan search.google.com/local/writereview, g.page/.../review y www.google.com/maps/place/... con segmentos y parámetros permitidos. El enlace real debe comprobarse con este contrato antes de configurar.
5. **Aseo:** init/checkpoint operativos limitados por permisos; se convocó respaldo Aseo-Codex y el owner respondió por colaboración nativa. Las ejecuciones finales retiran sus fixtures y detienen sus servidores. Dos intentos anteriores (`at-resena-motor-v2-b2e50a20cc8b44e79758295fa84c831f` y `at-resena-motor-v2-14a37ae3cd99427896cd9a34e8ed8db2`) tuvieron limpieza interrumpida; ambos servidores se detuvieron por mysqladmin con exit 0. El owner autorizó cuarentena, pero registro/inspección no fueron posibles: **conservados/no retirados, pendiente**. El temporal virtualizado de Python Store `at-resena-motor-v2-nj4nyakf` no fue inspeccionable y queda pendiente. No se eludieron los controles del registro ni se purgó por edad. No contienen datos reales. No se declara todo TEMP limpio.

### Lista PROD actualizada — preparación, NO despliegue

Raíz local **L**: `C:/wamp64/www/automatiza-tech/.worktrees/guias/`. Para cada entrada de archivo, **ruta local exacta = L + ruta indicada**, y **destino relativo en PROD = la misma ruta indicada**. Se entrega el artefacto completo, incluidos archivos de vuelta 1 que esta vuelta no modifica. Todos son **OBLIGATORIOS** para instalar el módulo; ninguno es opcional. Pausar Schedule/webhook, dejar URL Google vacía y verificar motor de Hostinger antes de empezar. Mantener la ventana de mantenimiento hasta terminar archivos/migración/verificación.

| Orden | Ruta local relativa a L = destino relativo en PROD | Clase |
|---|---|---|
| 1 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php` | OBLIGATORIO |
| 1 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-datos.php` | OBLIGATORIO |
| 2 | Ejecutar `at_rg_migrar(true)` según guía §8; exigir `at_resena_schema=2`, tres tablas e índices/anchos verificados. Preservar filas v1. Requiere el validador existente de cierre-cliente/puras.php. | OBLIGATORIO |
| 3 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-bajas.php` | OBLIGATORIO |
| 3 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-correo.php` | OBLIGATORIO |
| 3 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena.php` | OBLIGATORIO |
| 4 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php` | OBLIGATORIO |
| 4 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-datos.php` | OBLIGATORIO |
| 4 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` | OBLIGATORIO |
| 5 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` | OBLIGATORIO |
| 6 | `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp-recordatorios.php` | OBLIGATORIO |
| 6 | `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php` | OBLIGATORIO |
| 7 | `wp-content/mu-plugins/crm-ai-completo.php` | OBLIGATORIO |
| 7 | `wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js` | OBLIGATORIO |

**No subir:** tests (incluidos runners de motor), Docs/informes/planes, N8N/resena-google/aplicar.py, graphify-out, .git, temporales de motores, exports, respaldos, credenciales, .env o wp-config. No hay archivos opcionales para habilitar esta función. Constante fija no equivale a webhook instalado: creación/activación de n8n sigue fuera de este encargo. Guía §8 actualiza instalación y rollback, que conserva las tres tablas y todas las marcas.

### Estado para Eje

Cambios exclusivamente locales. Sin despliegue, SSH, conexiones reales n8n/Meta, lectura de credenciales, datos reales añadidos, gastos, push, merge ni stash. **M4 permanece pendiente de Luis; M7 no se modificó.** WP/HTTP completos, Hostinger y verificación del bot siguen pendientes. Este informe y las pruebas son evidencia de autor A, no aprobación B.

**Git final:** `git add --` con los 16 archivos explícitos falló por `Unable to create .../.git/worktrees/guias/index.lock: Permission denied`; no se llegó a ejecutar commit. HEAD sigue en `50c9410`; correcciones sin commit, incluidos los cuatro archivos nuevos. No se usó stash ni se eludió el sandbox. **Checkpoint task-close:** plataforma codex, sesión nativa `01a1f60e-388d-7ad3-9424-3afaef79f832`, root real del worktree; salida `preserved`, motivo «Ruta ausente, ocupada o sin permiso: conservar y revisar». La respuesta del respaldo Aseo conserva los pendientes descritos; no declara limpio el registro externo.

## Vuelta 3

19-nov-2026 · **LOCAL / PEER_REVIEW_PENDING**. Autor A: Forja-Codex. Revisor asignado: Eje. Base comprobada `dda5edb`, rama `codex/resena-google-impl`, árbol inicialmente limpio. Se conserva la rama por instrucción expresa de Luis. M4 y M7 intactos. No es aprobación B ni certificación de PROD.

Ruta: **C3 · riesgo alto por el control de acceso y la correlación de futuros mensajes · incertidumbre media · reversible → Forja-Codex en esta sesión, sin delegación; Eje verifica.** La clasificación provisional C2 se elevó al abordar la autorización de migración. No se lanzó ni se atribuye otro modelo/perfil CLI; sin escalada de modelo ni gasto.

### TDD por punto y resultado

Cada punto tuvo rojo antes de su arreglo y un pase completo verde de `& tests/plan-trabajo/resena-suite.ps1 -ConMotores` antes del siguiente arreglo. Los nombres abreviados V3 corresponden literalmente a las aserciones permanentes en `tests/plan-trabajo/resena-v3-casos.php`, salvo V3-6 (archivo propio), motores y búsqueda de privacidad. Los fallos de preparación del doble SQL y de extracción del mutante se corrigieron antes de contar los rojos válidos; no se presentan como evidencia de sensibilidad.

Raíz de código **I** = `wp-content/themes/automatiza-tech/inc/`. Rutas y líneas contra esta vuelta:

| Punto | Prueba roja válida y salida observada | Arreglo / evidencia | Resultado tras arreglo |
|---|---|---|---|
| Runner PS 5.1 | PHP con `2>&1` y ErrorActionPreference=Stop abortó con NativeCommandError por Xdebug. | `tests/plan-trabajo/resena-suite.ps1:6`, `resena-motor-test.ps1:30`: Continue local, diagnósticos visibles, exit code comprobado, Stop restaurado en finally. | Suite completa verde con stderr visible. |
| 1 · failed temprano | V3-1 `failed antes de resultado queda fallido y avisa una vez`, `repetidos no duplican aviso`, `reconsulta cierra lookup anterior al indice y transient posterior`: **3 fallas**, integración exit 1. | `I/plan-trabajo/resena.php:122` consume el transient después de reservar el índice; `I/cierre-cliente/whatsapp.php:415` reconsulta después de escribirlo. | Fallido, un aviso, transient consumido; integración **115/115**, suite verde. |
| 2 · teléfono | V3-2 formatos +56 incorrectos, nueve dígitos sin país, explicación y número normalizado en panel; contrato M3 actualizado: **8 fallas**, exit 1. | `I/plan-trabajo/resena-puras.php:32`, `I/plan-trabajo/avance-panel.php:820`. DDL verificado en `wp-content/mu-plugins/crm-ai-completo.php:225`: **no existe campo país**. | Se rechazan nacionales sin prefijo; +56 exige móvil exacto; destino normalizado visible. Integración **123/123**, suite verde. |
| 3 · recordatorios | V3-3 `whatsapp-envio resena usa id de proyecto en URL`: **1 falla**, exit 1. | `I/cierre-cliente/whatsapp-recordatorios.php:66`: set_url_params; doble de WP_REST_Request y ruta real cargados. | Callback conserva id=704 y wamid; integración **124/124**, suite verde. |
| 4 · migración anónima | V3-4 `migracion automatica anonima sin efectos SQL opciones ni transients`: **1 falla**, exit 1; se intentaba cargar el instalador sin autorización. | `I/plan-trabajo/resena-datos.php:59`: manage_options antes de cualquier migración automática. | Integración **125/125**, suite verde; manual probada en ambos motores con current_user_can=false. |
| 5 · wamid ajeno y backfill | V3-5 otro intento/otro proyecto: **2 fallas**, exit 1. MySQL: `backfill fallido conserva bloqueo temporal` y `admin_init no repite backfill bloqueado`: **2 fallas de 18**, exit 1. | `I/plan-trabajo/resena.php:123` reserva antes del CAS; `I/plan-trabajo/resena-datos.php:101` conserva bloqueo en cualquier fallo del backfill. | Sin cambios en JSON/revisión/historial/avisos por conflicto. Integración **127/127**, motores **18/18** cada uno, suite verde. |
| 6 · capacidad wamid | `resena-wamid-test.php --mutar-largo`: **FAIL V3-6 validador <= columna wamid: 257 bytes frente a 256**, 1 falla de 7, exit 1. Mutación solamente en memoria. | `tests/plan-trabajo/resena-wamid-test.php:1` compara el validador con el DDL efectivo; añadido a la suite. No requería cambiar producción. | Original **7/7**; suite verde, mutante detectado por la aserción de capacidad. |
| 7 · H4 | V3-7 SQL en integración: **1 falla**, exit 1; motor MySQL: **1 falla de 19**. Primera solución: MySQL verde, MariaDB rojo por incluir el JSON ilegible (ids 4,6,7). | `I/plan-trabajo/resena-datos.php:9`, usado por `resena.php`: rango, JSON_VALID y JSON_CONTAINS sobre intentos reales. | Ambos motores devuelven **solo 4 y 6**: primera invitación y solo pruebas. Integración **128/128**, motores **19/19**, suite verde. |
| 8 · documentación / privacidad | V3-8 rollback y sección del informe: **2 fallas**, exit 1. Búsqueda del patrón indicado por Luis: **7 líneas** en el plan, guardia de privacidad exit 1. El informe A no tenía coincidencias. | `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md:352`: procedimiento v1 y contratos actualizados; plan anonimizado y sección presente. | Integración **130/130**, motores **19/19** cada uno, suite exit 0. **0 coincidencias** en los 16 archivos de esta vuelta; **115 rutas ajenas** enumeradas, intactas. |

### Comandos y alcance de comprobación

```powershell
& C:/wamp64/bin/php/php8.5.0/php.exe -d xdebug.mode=off tests/plan-trabajo/resena-estado-test.php
& C:/wamp64/bin/php/php8.5.0/php.exe -d xdebug.mode=off tests/plan-trabajo/resena-wamid-test.php --mutar-largo
& tests/plan-trabajo/resena-motor-test.ps1 -Motor mysql
& tests/plan-trabajo/resena-motor-test.ps1 -Motor mariadb
& tests/plan-trabajo/resena-suite.ps1 -ConMotores
git diff --check
```

El comando con mutación debe salir **1** por V3-6; los restantes comandos de aceptación deben salir **0**. Los motores arrancan en un datadir nuevo y puerto efímero, sin leer credenciales ni wp-config ni usar el WAMP activo. Usan wpdb/dbDelta oficiales locales de WordPress 7.1.2. Cada ejecución válida detuvo su servidor y retiró su propia fixture. La suite conserva el aviso de la extensión Xdebug incompatible; no se silencian códigos de salida distintos de cero.

**Última suite completa:** `& tests/plan-trabajo/resena-suite.ps1 -ConMotores` → **exit 0**, `Suite offline completa: 0 fallos; lint 16 PHP`. Reglas puras **49/49**, integración con dobles **130/130**, capacidad wamid **7/7**. Regresiones de cierre/entregables/avance: cuatro TODO OK. Python n8n offline: **9/9**. Expresiones JS: **8 compiladas**, destinatario/idioma/cuatro resultados verificados. JS syntax: exit 0. MySQL **8.4.7: 19/19**; MariaDB **11.4.9: 19/19**; ambos servidores detenidos y fixtures retiradas. Los pases previos por puntos 1–7 constan arriba. El pase H4 intermedio que falló en MariaDB también terminó con suite exit 1: comprobación real de que el manejo de stderr no oculta fallos PHP. WP HTTP completo y Hostinger: **NOT_RUN**; no se infiere un resultado productivo desde estos motores locales.

### Límites y desviaciones

- **TDD:** un ciclo por punto funcional, con agrupación de los subcasos inseparables del mismo punto (1, 2 y 5). Punto 6 es una protección adicional: rojo en mutante representativo, verde en original, sin cambiar el validador correcto. Punto 8 tiene guardias rojas de contenido y privacidad. No se atribuyen rojos de infraestructura a defectos de negocio.
- **País:** no se agrega un campo inexistente ni se infiere Chile. Se exige prefijo explícito para los nacionales de nueve dígitos; mantenerlo así hasta un cambio de CRM validado. No se leyó la BD real.
- **Estados tempranos:** el failed usa el transient preexistente de **15 minutos**; si `/resultado` llega después de su vencimiento, no hay dato que recuperar. La reconsulta del webhook cubre la carrera de lookup anterior al índice/escritura posterior del transient, demostrada con intercalado. sent/delivered/read anteriores al índice pueden perderse y quedan cubiertos por aceptado; eso no confirma entrega. No hay reenvío automático.
- **Reserva:** un wamid reservado cuyo CAS no se confirme queda ligado al mismo intento; se revisa, no se libera ni se reasigna. La migración fallida espera cinco minutos automáticamente; la manual sigue pudiendo reintentar tras corregir la causa.
- **SQL:** sin esquema 3 ni columna extra; se lee el JSON dentro del rango para excluir invitados antes del CRM/bajas. No se usó `abierto` para esto porque describe mantenimiento pendiente y también vale 0 en proyectos sin invitación. La diferencia MySQL/MariaDB se detectó y corrigió antes de cerrar H4.
- **Privacidad:** plan e informe A usan referencias genéricas. La búsqueda global conserva y enumera solo rutas de archivos ajenos; también hay coincidencias incidentales en binarios. No se publican sus líneas ni se editan esos archivos, incluidos informes de otros revisores. No se añadió un inventario con nombres de clientes al repo público.
- **Aseo:** init con sesión nativa `01a1f63e-c503-70d1-b2cd-bf988c3aea38` devolvió `preserved`, «Ruta ausente, ocupada o sin permiso: conservar y revisar». No se modificó el registro externo ni se retomó limpieza ajena/histórica. Fixtures propias de las ejecuciones verificadas: retiradas por su runner; no se afirma limpio todo TEMP.

### FTP incremental desde dda5edb — preparado, NO aplicado

Raíz local **L** = `C:/wamp64/www/automatiza-tech/.worktrees/guias/`. Para cada fila: **local exacto = L + ruta**; **destino relativo PROD = ruta**. Son los seis desplegables de esta vuelta; para instalación inicial se mantiene la lista completa de Vuelta 2. Trabajar detenido y con enlace de Google vacío. No hay nueva versión de esquema ni nueva activación autorizada.

| Orden | Ruta local relativa = destino relativo PROD | Clase |
|---|---|---|
| 1 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php` | OBLIGATORIO |
| 1 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-datos.php` | OBLIGATORIO |
| 2 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena.php` | OBLIGATORIO |
| 3 | `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php` | OBLIGATORIO |
| 3 | `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp-recordatorios.php` | OBLIGATORIO |
| 4 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` | OBLIGATORIO |

OPCIONAL: ninguno. **No subir:** tests, Docs/informes/planes, graphify-out, .git, fixtures/temporales/respaldos, .env, wp-config ni credenciales. El despliegue, la migración manual en hosting, n8n y Meta reales siguen fuera de este encargo.

### Estado para Eje

Solo cambios locales. Sin SSH, servicios reales, credenciales ni datos reales; sin push, merge ni stash. **M4 sigue pendiente de Luis; M7 intacto.** `git diff --check`: PASS. `graphify query` usado al iniciar; `graphify update .` → **FAIL, exit 1, WinError 5 / Acceso denegado**; grafo sin cambios en Git, no actualizado. Checkpoint task-close con la sesión nativa indicada y root real → **preserved**, mismo motivo de permisos que init. Conservados los pendientes históricos/externos; las fixtures verificadas de esta vuelta se retiraron por el runner. Dieciséis archivos de esta tarea (14 modificados, 2 nuevos), ninguno ajeno. **Git:** `git add --` con los 16 archivos explícitos → **exit 128**, `Unable to create .../.git/worktrees/guias/index.lock: Permission denied`. No se llegó a ejecutar commit; **HEAD permanece dda5edb**, cambios sin stage y sin commit. No se reintentó con permisos superiores. Autor A entrega para revisión de Eje, sin atribuirse aprobación B.

## Vuelta 4

19-nov-2026 · **LOCAL / PEER_REVIEW_PENDING**. Autor A: **Forja-Codex**. Revisor asignado: **Eje**, sin atribuirle comprobaciones de esta vuelta. Base verificada `cdb038beef35d59d094431e278d524540b98fc52`, rama `codex/resena-google-impl`, árbol inicialmente limpio. Rama conservada por instrucción expresa de Luis. Solo decisión M4 y dependencias directas; M7 intacto. No hay despliegue, SSH, servicios/credenciales/datos reales, push, merge ni stash.

Ruta: **C2 · riesgo medio · incertidumbre baja · reversible → Forja-Codex en la sesión actual; Eje revisa.** Se leyó `at-router-modelos`; ejecución sin delegación ni cambio de modelo/perfil. No se afirma haber lanzado un modelo distinto del de esta sesión.

### Decisión aplicada y arreglo (archivo:línea)

Raíz **I** = `wp-content/themes/automatiza-tech/inc/plan-trabajo/`.

| Archivo:línea | Cambio directo de M4 |
|---|---|
| `I/resena-puras.php:83` | `at_rg_consume_cupo`: real con cualquier canal distinto de fallido/no_intentado conserva cupo. Todo fallido y fallido+no_intentado lo devuelven; ambos no_intentado no son enviados ni fallo total. Valores ausentes/desconocidos se tratan conservadoramente. |
| `I/resena-puras.php:90`, `:94`, `:98` | Identificación/conteo de fallos reales totales y elegibilidad: hasta dos fallos admiten siguiente invitación, sin nueva espera manual. Tres bloquean envíos reales con `limite_fallidos`. Las pruebas no suman al tope. D10 cuenta invitaciones que consumen cupo; siete días desde esa invitación. |
| `I/resena-datos.php:9` | Cola SQL: JSON_CONTAINS con modo y canal en el mismo intento; excluye aceptado/entregado/leido/en_curso/desconocido y conserva fallos totales. El PHP posterior valida el registro, el tope y todas las guardas antes del CAS. Sin LIMIT previo, esquema nuevo ni JSON_TABLE en la solución final. |
| `I/resena.php:160`, `:185` | Al tercer fallo se reserva `aviso:limite_fallidos` mediante CAS, con un único intento de aviso operativo; sin otro aviso por canal al alcanzar el tope. Callbacks, observación y mantenimiento repetidos no lo repiten. Antes del tope, un aviso de fallo total explica el reintento condicionado. |
| `I/resena-puras.php:7` | La proyección de mantenimiento conserva el aviso de tope pendiente y cierra el trabajo una vez procesado; no exige avisos por canal que el aviso único ya cubre. |
| `I/avance-panel.php:828`, `:847` | Cuenta y bloqueo visibles; botones habilitados/deshabilitados por elegibilidad. Tras fallo total, Enviar ahora es invitación y el reenvío permanece reservado. Al tercero, invitación y reenvío deshabilitados. |
| `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md:229`, `:251` | Uso, estados, revisión manual, tope, aviso único y modo prueba actualizados. |
| `Docs/superpowers/plans/2026-11-19-resena-google-cierre.md:236` | §10, nota «Decisión de Luis M4» reemplaza el pendiente anterior. Los apartados de vueltas anteriores son historial. |

Se conservan intentos, fechas y evidencias. No hay restablecimiento automático ni botón nuevo para reiniciar el tope. Se permite modo prueba para diagnóstico tras tres fallos, sujeto a las guardas existentes y máximo veinte pruebas; no habilita un envío real. Un aviso reservado no demuestra entrega a Luis: si SMTP falla, queda `fallido` visible en el panel para revisión, sin repetir el correo automáticamente.

### Pruebas primero: rojos observados y controles

Antes de cambiar producción se añadieron las pruebas puras, los casos V4, su inclusión en integración y los casos de SQL en motor. Comandos PHP con configuración normal y Xdebug desactivado; todos los rojos válidos terminaron **exit 1**:

| Prueba / nombre literal | Rojo válido observado |
|---|---|
| `M4 todo fallido no consume` | FAIL en puras. |
| `M4 fallido + no_intentado no consume` | FAIL en puras. |
| `M4 Enviar ahora recupera invitacion sin esperar`, `M4 automatico recupera elegible en siguiente corrida` | FAIL en puras. |
| `M4 fallo no habilita reenvio sin invitacion`, `M4 canal ausente permite nuevo intento automatico` | FAIL en puras. |
| `M4 dos fallos permiten tercer intento`, `M4 tercer fallido total bloquea manual y automatico` | FAIL en puras. |
| `M4 reenvio intacto tras fallido + aceptado a los 7 dias`, `M4 siete dias parten del aceptado no del fallido` | FAIL en puras. El control final usa fechas diferentes para ambos intentos. |
| `M4 reclamo tras todo fallido es invitacion inmediata`, `M4 panel explica cupo y tope` | FAIL en integración. |
| `M4 tercer fallido total bloquea y avisa una vez`, `M4 panel bloquea invitacion y reenvio al tercero` | FAIL en integración. |
| `M4 fallo + ausente permite intento real 2`, `M4 fallo + ausente permite intento real 3`, `M4 cuarto intento real se rechaza sin despacho` | FAIL en integración. |
| `M4 SQL permite todo fallido y fallido + ausente excluye inciertos y consumidos` | FAIL en MySQL 8.4.7: **19/20**; fixture propia detenida y retirada. |
| `M4 aviso de tope completo no deja mantenimiento abierto sin trabajo` | Rojo adicional durante el arreglo: **140/141**, antes de corregir la proyección. |

**Conteo inicial:** puras **60/70, 10 fallas**; integración **132/140, 8 fallas**, siete de comportamiento y una comprobación estructural provisional de JSON_TABLE. Esa comprobación provisional se retiró al conservar JSON_CONTAINS en la solución final. La aserción V2 que exigía el contrato antiguo de consumo fue actualizada a la decisión de Luis; no se borró cobertura.

**Controles ya verdes antes del arreglo**, sin inventar rojos: `M4 fallido + desconocido consume`, `M4 fallido + desconocido bloquea invitacion`, `M4 fallido + aceptado consume`, `M4 fallido + aceptado bloquea invitacion`, más en_curso/entregado/leido y pruebas fallidas que no suman al tope. Esos comportamientos ya eran correctos en la base y permanecen verificados. Se añadieron controles verdes de botón disponible tras fallo, reserva persistente de aviso si SMTP falla y tres pruebas fallidas sin bloqueo ni aviso de tope.

Una invocación inicial de integración con `php -n` no llegó a los casos nuevos por ausencia de `mb_strlen`; **no se cuenta como rojo funcional**. Se volvió a ejecutar con la configuración normal, igual que la suite. El aviso preexistente de Xdebug incompatible sigue visible, sin ocultar el exit code.

### Suite completa y revisión A

```powershell
& tests/plan-trabajo/resena-suite.ps1 -ConMotores
git diff --check
```

**Resultado final real:** `& tests/plan-trabajo/resena-suite.ps1 -ConMotores` → **exit 0**, `Suite offline completa: 0 fallos; lint 17 PHP`. Reglas puras **70/70**, integración con dobles **143/143**, capacidad wamid **7/7**. Regresiones cierre/entregables/avance: **cuatro TODO OK**. Python n8n offline **9/9**, JS **8 expresiones compiladas** con destinatario/idioma/cuatro resultados comprobados, y sintaxis del JS del plan exit 0. MySQL **8.4.7: 20/20**; MariaDB **11.4.9: 20/20**, ambos servidores detenidos y fixtures retiradas por su runner. `git diff --check`: **PASS**. Se mantiene el diagnóstico preexistente de Xdebug; no afecta estos exit codes ni se modificó el entorno para ocultarlo.

El primer pase intermedio falló en integración por el contrato V2 antiguo y en MySQL por la consulta provisional JSON_TABLE; MariaDB pasó. Una reejecución diagnóstica de MySQL reprodujo la inclusión incorrecta de consumidos (ids sintéticos 5 y 11–15). Se sustituyó la consulta por predicados JSON_CONTAINS; el pase completo siguiente fue **exit 0**, puras **70/70**, integración **141/141**, capacidad wamid **7/7**, ambos motores **20/20**, lint **17 PHP**. Después se reforzaron las regresiones del botón, SMTP del aviso y fecha de D10; la suite final volvió a pasar con **143/143** de integración. No se atribuye una causa interna del optimizador al síntoma observado.

| Control A | Estado y evidencia |
|---|---|
| Cupo, incertidumbre, invitación inmediata y D10 | PASS en puras e integración con dobles; nombres arriba. |
| Tope de tres y aviso único, incluso SMTP fallido | PASS en integración; sin efectos externos reales. |
| Cola SQL y migración sin nuevo esquema | PASS, MySQL y MariaDB efímeros **20/20** cada uno. |
| Panel | PASS de HTML generado con nonce, elegibilidad, botones y explicación; navegador visual NOT_RUN. |
| HTTP WordPress completo / hosting / canales reales | NOT_RUN, fuera del alcance autorizado. |
| Superficies públicas / SEO + IA | NOT_APPLICABLE: se cambia panel privado, reglas internas y documentación. Las rutas públicas de baja/redirección no cambian. |
| Revisión B | PEER_REVIEW_PENDING, responsable Eje. No ejecutada por Forja. |

### Git, grafo y residuos

`graphify query` ejecutado antes de explorar; `graphify update .` intentó reextraer sin LLM y falló con **WinError 5, Acceso denegado**. Grafo no actualizado, sin cambios de grafo en Git. El comando devolvió exit 0 pese a imprimir el fallo: se registra **FAIL**, no PASS.

Aseo `init` y `checkpoint task-close`: plataforma `codex`, sesión nativa completa `01a1f66a-106a-74a2-8742-d23ca2ff3014`, root real de este worktree. Ambos devolvieron **preserved**, «Ruta ausente, ocupada o sin permiso: conservar y revisar». No se eludió el permiso ni se alteró registro externo. El runner de motores crea su propio datadir efímero, detiene únicamente su servidor y retira su fixture; no se declara limpio todo TEMP ni se retiran residuos ajenos. No se crearon logs temporales adicionales del autor.

**Git final:** `git add --` con los trece archivos explícitos de M4 → **exit 128**, `Unable to create .../.git/worktrees/guias/index.lock: Permission denied`. No se llegó a ejecutar `git commit`; los cambios quedan sin stage y **sin commit**, conforme al encargo, en `codex/resena-google-impl`, HEAD `cdb038beef35d59d094431e278d524540b98fc52`. No se intentaron permisos superiores ni se alteró el candado. Doce archivos modificados y uno nuevo (`tests/plan-trabajo/resena-v4-casos.php`); ninguno ajeno. Sin push, merge ni stash. Siguiente paso: revisión de Eje sobre este árbol local; no se atribuye aprobación B.

### FTP incremental desde cdb038b — preparado, NO aplicado

**Raíz local L:** `C:/wamp64/www/automatiza-tech/.worktrees/guias/`. Para cada fila, ruta local exacta = **L + ruta** y destino relativo PROD = **ruta**. Aplicar en un pase autorizado con el sistema detenido; no se habilita ni publica nada desde esta tarea. Sin migración nueva.

| Orden | Ruta local relativa = destino relativo PROD | Clase |
|---|---|---|
| 1 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php` | OBLIGATORIO |
| 2 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-datos.php` | OBLIGATORIO |
| 3 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena.php` | OBLIGATORIO |
| 4 | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` | OBLIGATORIO |

OPCIONAL: ninguno. **No subir:** tests (incluido resena-v4-casos.php), Docs/informes/planes, graphify-out, .git, fixtures/temporales, respaldos, .env, wp-config ni credenciales. Los informes de Eje y las reglas de agentes no se modificaron. Rollback local del cambio: volver únicamente estos cuatro archivos al conjunto coherente `cdb038b`, con el sistema detenido y conservando tablas/JSON/reservas; revisar antes de reactivar porque esa versión vuelve al consumo anterior de fallos totales. No se ejecutó rollback ni despliegue.

## E2E local

Ejecución **20-nov-2026**, iniciada a las **23:58:19 America/Santiago**; el cierre técnico continúa el 21-nov. Responsable real: **Centinela-Codex**. Revisor asignado: **Eje**, **PEER_REVIEW_PENDING**. Base `0afac2c`, rama `codex/resena-google-impl`, árbol inicialmente limpio; se conserva la rama indicada por Luis. Esta sección actualiza únicamente los NOT_RUN de WordPress/HTTP **locales** de las vueltas anteriores. Hosting, SMTP, n8n y Meta reales siguen **NOT_RUN**.

Ruta: **C2 · riesgo acotado a fixture desechable · incertidumbre media · reversible → Centinela-Codex, sesión actual, sin delegación ni cambio de modelo; Eje revisa.** Se aplicó `at-router-modelos`.

**Resultado: pruebas WP 14/14 PASS; HTTP 7/7 PASS; recorrido E2E 32 PASS / 2 FAIL. El punto 4 literal NO pasa.** No se corrigió ningún archivo de producción. Evidencia estructurada, revisada sin claves, cookies ni tokens de baja: [2026-11-20-resena-e2e-local-evidencia.json](2026-11-20-resena-e2e-local-evidencia.json). Contiene las 34 aserciones, salidas de las dos pruebas, referencias sintéticas, estados y códigos de salida. `aceptado` aquí significa retorno del capturador, sin entrega externa.

### Entorno y comandos ejecutados

- WordPress **7.1.2**: núcleo leído de `C:/wamp64/www/at-rastro-test/`, sin cargar ni modificar su `wp-config.php`, contenido o base. `ABSPATH` apunta al núcleo; `WP_CONTENT_DIR` y el bootstrap son propios. Se copió **sin modificar** el `wp-admin/admin-post.php` del núcleo a la fixture para que cargase el bootstrap desechable. REST y `template_redirect` corrieron en WordPress real. Tema mínimo desechable; no se probó visualmente el tema AT completo.
- MySQL **8.4.7**, proceso propio, datadir vacío inicializado con `--no-defaults --initialize-insecure`, escucha solo en `127.0.0.1:65308`, sin binlog, `mysqlx=OFF`. Base única `zz_centinela_resena_01a1fbf9`, prefijo `resenatest_`; CRM e historial mínimos creados en ella. No se accedió a bases existentes.
- Servidor PHP solo en **127.0.0.1:65309**. `WP_HTTP_BLOCK_EXTERNAL=true`, `DISABLE_WP_CRON=true`. MU-plugin de prueba carga módulos de **este worktree**, captura `pre_wp_mail` y `pre_http_request`; otros HTTP reciben WP_Error. El correo nunca alcanza PHPMailer/SMTP. La clave REST y las salts se generaron en memoria, se pasaron por entorno y nunca se imprimieron. Usuarios y sesiones exclusivamente sintéticos.
- Todos los scripts, capturas de correo con baja, cookies manejadas en memoria, logs, datadir y contenido desechable estuvieron fuera del repo/webroot. Solo se conserva evidencia saneada como artefacto permanente del proyecto.

Comandos del pase final (las variables privadas se establecieron dentro del runner, sin valores en la línea de comandos):

```powershell
$php = 'C:/wamp64/bin/php/php8.5.0/php.exe'
$py = 'C:/Users/luis_/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe'
$s = 'C:/Users/luis_/AppData/Local/Temp/zz_centinela_resena_01a1fbf9_20261120'
& "$s/run.ps1"
# Dentro de ese runner:
& $php -d xdebug.mode=off "$s/fixture.php" install
# AT_RESENA_LOCAL_TEST=1; AT_WP_LOAD=$s/site/wp-load.php
& $php -d xdebug.mode=off tests/plan-trabajo/resena-wp-test.php
# AT_RESENA_TEST_URL=http://127.0.0.1:65309; Google vacío
& $py tests/plan-trabajo/resena-http-test.py
& $py "$s/e2e.py"
```

El arnés fue transitorio; sus SHA256 quedan en la evidencia. La base y los procesos fueron retirados; el scratch permanece por el bloqueo de limpieza detallado al cierre. Las peticiones exactas y los cambios de fixture se detallan abajo. Esos comandos requieren reinstalar la fixture y regenerar su entorno de prueba. El runner externo terminó con exit 0 por su `finally`; **no se interpreta como aprobación**: la evidencia conserva `wp_test_exit=0`, `http_test_exit=0` y **`e2e_exit=1`**.

### Puntos 1–8: comandos, resultado real y evidencia

Abreviaturas: `POST panel` = HTTP real a `/wp-admin/admin-post.php`, `action=at_rg_manage`, `crm_id=2`, `plan_id=2`, `proyecto_id=2`, cookies de administrador y nonce válido del proyecto, salvo negativos expresos. `REST` = prefijo `/wp-json/at/v1/resena/`, header `X-AT-Secret` de prueba. El arnés usa `urllib.request` sin seguir redirecciones; Google nunca se visita.

| Punto | Comando / petición realmente ejecutada | Resultado y evidencia |
|---|---|---|
| **1** | `php -d xdebug.mode=off "$s/fixture.php" fixture "Proyecto E2E principal"`; lectura `snapshot 2` | **PASS.** CRM **2**, tipo `cliente`, `cliente@ejemplo.cl`, `+56900000001`; plan **2**, estado enviado, asociado al CRM 2; reseña/proyecto **2**. |
| **2** | `POST panel`, `operacion=termino`, `fecha=2026-11-12`; luego `operacion=permiso`, `autorizado=1`, fecha del día y origen sintético | **PASS.** Ambos HTTP **302**; término `guardado`. Fecha calculada con el reloj real `2026-11-20T23:58:19-03:00` menos 8 días. Fecha y metadatos persistidos en la reseña y reflejados en `avance.termino`; permiso WhatsApp autorizado para el teléfono de la fixture. |
| **3** | `fixture.php option at_resena_google_url ""`; `GET /resena/`; `GET REST pendientes` con clave | **PASS.** Público **404**, sin `Location`; REST **409**, `resena_enlace`. `X-Robots-Tag: noindex, nofollow`, `Referrer-Policy: no-referrer`. |
| **4** | `fixture.php option at_resena_google_url https://g.page/r/CeTySLmzF2BxEBM/review`; `GET /resena/`; `GET REST pendientes`; `POST REST proyecto/2/reclamar`, `{}` | **FAIL literal, con partes PASS.** Público **302** al destino exacto. Pendientes **200**, `{"pendientes":[]}`; elegibilidad **historico**. Reclamar **409**, `resena_historico`, **0 efectos capturados**. No se añadió manual/prueba al contrato REST ni se falseó el reloj. Continuación manual real documentada a continuación: correo/webhook/autorización/resultado sí pasan. |
| **5** | `fixture.php option at_resena_prueba_email qa-resena@example.test`; `option at_resena_prueba_telefono +56900000002`; `POST panel operacion=prueba`; autorización y resultado REST con `modo=prueba` | **PASS.** Panel **302**, `registrado`; exactamente **1 correo**, solo a `qa-resena@example.test`, sin CC/BCC; teléfono canjeado **+56900000002**, distinto al cliente. No aparece `cliente@ejemplo.cl` en la captura de ese correo. Cupo real **1 → 1**. Autorización **200**, repetición **409 despacho_usado**; resultado **200/200**, revisión **18 → 18**, historial **2 → 2**. |
| **6** | Extraer el enlace de baja del correo **real capturado**; `GET /resena/baja/?token=<no publicado>`; `POST /resena/baja/` con ese token; nuevo proyecto para el mismo correo y `POST REST proyecto/3/reclamar`; reenvío manual del proyecto 2 | **PASS.** GET **200**, bajas **0 → 0**; POST **200**, supresión `cliente@ejemplo.cl`, canal `correo`, alcance `resenas`. Nuevo proyecto sin WhatsApp: reclamo REST **409 resena_canales**, **0 efectos**. En el proyecto original, reenvío manual **302 registrado**, **0 correos**, correo **no_intentado**, WhatsApp **en_curso**; se confirma luego por REST. |
| **7** | Sin header: GET `pendientes`, POST `baja`, y POST `reclamar`, `resultado`, `autorizar-whatsapp` tanto bajo `proyecto/2/` como bajo `2/`; POST panel de administrador sin nonce; POST panel de suscriptor con su nonce válido | **PASS.** **8/8 rutas REST HTTP 401**, `resena_no_autorizado`, incluidos los alias por plan. Administrador sin nonce **403** y sin mutación/efectos. Suscriptor autenticado con nonce válido, sin `manage_options`: **403**, «Sin permiso», sin mutación. |
| **8** | Fixture CRM/plan propios (`fallo@ejemplo.cl`, sin teléfono); término vía panel; `fixture.php option at_test_fail_mail 1`; dos `POST panel operacion=enviar` | **PASS.** Capturador de correo devuelve false solo para la invitación. Primer intento: **correo fallido + WhatsApp no_intentado**, **cupo 0**. Segundo envío manual **302 registrado**, **2 intentos conservados**, cupo **0**. No se borraron marcas para liberar cupo. El aviso operativo de fallo también fue capturado, sin SMTP. |

**Continuación del punto 4 por la excepción manual existente:** `POST panel operacion=enviar` → **302 registrado**. Exactamente un correo capturado a **cliente@ejemplo.cl**, estado **aceptado**; exactamente un webhook, estado de WhatsApp inicialmente **en_curso**. URL capturada = constante `AT_N8N_RESENA_WHATSAPP` = `https://n8n-n8n.kchiba.easypanel.host/webhook/at-resena-whatsapp`. Body únicamente `proyecto_id=2`, `intento_id=f2cae0ba-b67a-45aa-9993-e7c1b06bffcf`, `modo=real`; se comprobó en memoria que la clave de la cabecera coincidía, conservando solo el booleano.

```text
POST /wp-json/at/v1/resena/proyecto/2/autorizar-whatsapp
  {intento_id: <referencia del intento>, modo: real}
  -> 200, telefono +56900000001
  repetir -> 409, resena_despacho_usado
POST /wp-json/at/v1/resena/proyecto/2/resultado
  {intento_id: <misma referencia>, modo: real, estado: aceptado, wamid: <sintético válido>}
  -> 200; repetir -> 200
```

Tras repetir `/resultado`: WhatsApp **aceptado**, JSON idéntico, revisión **10 → 10**, filas de historial **2 → 2**. La prueba exige igualdad del JSON, revisión y cantidad de historial, no solo dos respuestas exitosas. La misma secuencia pasó para la prueba y para el reenvío posterior a la baja (en este último, revisión **24 → 24**, historial **3 → 3**).

**Ajuste explícito de fixture para el punto 6:** antes del reenvío manual se desplazó **solo `creado_at` del primer intento real sintético** a 8 días atrás, para superar la guarda de reenvío sin esperar una semana. No se alteró el reloj del proceso ni el término, no se borraron intentos y no se cambió producción. La comprobación REST `resena_canales` usa además un proyecto **nuevo**, sin invitaciones previas, para el mismo correo dado de baja y sin WhatsApp; así no se atribuye el bloqueo a cupo, histórico o un despacho aún abierto.

### Hallazgo: calendario incompatible con el punto 4 literal

**FAIL reproducido, sin arreglo de producción.** `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php:107` devuelve `historico` si el término es anterior al **19-nov-2026** en modo automático. La espera de siete días está inmediatamente después, en `:108`. `resena.php:204` filtra pendientes con esa elegibilidad; `resena.php:234` llama a reclamar sin excepción manual.

El escenario solicitado termina el **12-nov-2026** y por ello nunca entra a la cola automática, aunque hayan pasado ocho días. El primer término del corte (19-nov) solo puede superar la espera el **26-nov-2026**. Se trata de una incompatibilidad entre el criterio de este E2E y la regla histórica ya escrita; no se presenta como fallo de entrega SMTP ni se cambió la regla para hacer pasar la prueba. Eje debe decidir si mantiene la excepción manual o pide revisar el criterio. No hay validación positiva del reclamo **automático por REST** en esta fecha; sí hay validación real de la excepción manual y de sus pasos posteriores.

### Ajustes de las pruebas y del arnés

Solo se modificó `tests/plan-trabajo/resena-wp-test.php`:

1. El pase **original sin cambios** dio **dos FAIL de migración**, luego excepción de worker (**exit 255**): la prueba CLI llamaba `at_rg_migrar()` sin usuario administrador, mientras el módulo exige capacidad salvo `manual=true`. Se cambió a **`at_rg_migrar(true)`**, el modo CLI explícito ya previsto en `resena-datos.php:68`; no se simuló un administrador global para el test ni para sus workers.
2. El siguiente pase llegó a **13/14**, **exit 1**, por `guardado avance`: escribía `bloques=[]` cuando ya estaba vacío. MySQL informa cero filas afectadas y `at_av_guardar_si_igual` exige una fila modificada. Se cambió la fixture a un bloque `QA local` en estado `listo`, se verificó su lectura desde MySQL y se reforzó que **todo el registro de reseña** permanece idéntico. No se debilitó la guarda CAS.
3. Pase final: **14 comprobaciones WP/MySQL, 0 fallas**, **exit 0**, incluidos cuatro procesos concurrentes, vinculación concurrente, CAS ganador/obsoleto y conservación del intento.

`tests/plan-trabajo/resena-http-test.py` quedó **sin cambios**, **7/7 PASS, exit 0**. Verificó las cinco rutas de su lista sin clave, el 404 sin enlace y la baja inválida 400, con las cabeceras de privacidad previstas.

El arnés externo se ajustó antes del pase final: no cargar módulos de reseña antes de instalar las tablas WP; usar modo CLI explícito al instalar su esquema; aceptar/bloquear las consultas de actualización de WordPress sin asumir body JSON; generar nonces con el mismo token de sesión que las cookies WordPress; usar wamids sintéticos válidos y únicos. Los HTTP 500/403 de esos pases incompletos eran del arnés y no se reportan como defectos de reseñas. El aviso preexistente `Failed loading ...php_xdebug-3.4.7-8.4...dll` quedó registrado; el runner deja que PHP termine y conserva su exit code. No se cambiaron PHP ni php.ini para ocultarlo.

### Verificación, limpieza y estado de entrega

- Lint del test ajustado: **PASS** con PHP 8.5.0 y `-d xdebug.mode=off`. `git diff --check`: **PASS**. Código de producción: sin cambios.
- `graphify query` ejecutado al iniciar. `graphify update .`: **FAIL**, `WinError 5 / Acceso denegado`, aunque el CLI termina con exit 0; no se afirma grafo actualizado.
- Base **DROP exit 0**; consulta posterior a `INFORMATION_SCHEMA.SCHEMATA` da **0**. Servidores PHP y MySQL propios: **detenidos**, PID 33172/12096 ausentes y puertos 65309/65308 cerrados en comprobación posterior.
- Aseo `init` y `checkpoint task-close`: sesión nativa `01a1fbf9-ed83-7641-891c-0426d186d0e9`, plataforma codex y root real → **preserved**, «Ruta ausente, ocupada o sin permiso: conservar y revisar». No se cambió su registro ni se eludió el permiso. La revisión automática **rechazó** el comando compuesto de inspección y borrado recursivo de la fixture con motivo **«blocked by policy»**; no se ejecutó ese comando. Se comprobó después, por separado y sin borrar, que los PID están ausentes y los puertos cerrados. **PENDING:** 198 archivos propios inventariados, conservados en `C:/Users/luis_/AppData/Local/Temp/zz_centinela_resena_01a1fbf9_20261120`; `scratch_removed=false` en la evidencia. Se convocó Aseo-Codex como respaldo, solo para lectura de metadata y disposición pendiente, sin evadir el rechazo. No se declara el ambiente limpio ni se tocan residuos ajenos/históricos o el núcleo compartido.
- Evidencias permanentes: este informe y su JSON saneado. La ubicación externa estándar de informes está fuera de las raíces escribibles de esta sesión; se conserva evidencia revisable en el worktree autorizado, sin intentar elevar permisos.
- **FTP: NO APLICA**, no hay cambios desplegables. No subir test, informe, JSON de evidencia ni temporales. No hubo despliegue, SSH, n8n/Meta/SMTP reales, datos o credenciales reales, push, merge ni stash. Revisión de Eje pendiente; no se otorga aprobación B ni autorización de publicación.

Respaldo **Aseo-Codex** realmente convocado en la tarea nativa `/root/aseo_resena`: confirmó en lectura de metadata **198 archivos, 12 directorios, ~196,7 MB**, identidad de owner/sesión/root y los estados de eliminación de base y parada de servidores. Disposición **pendiente de retiro recuperable**, carpeta conservada intacta; no inspeccionó capturas ni intentó otra vía de retiro. Su herramienta no expuso un UUID de agentId: no se inventa ni se atribuye la revisión a Eje.

**Git final:** `git add --` con los tres archivos explícitos de esta entrega → **exit 128**, `Unable to create .../.git/worktrees/guias/index.lock: Permission denied`. No se llegó a ejecutar `git commit`, no se reintentó ni se tocó el candado. Rama `codex/resena-google-impl`, HEAD **0afac2c**, cambios **sin stage y sin commit**: este informe A, el JSON saneado y `tests/plan-trabajo/resena-wp-test.php`. El test HTTP y todo `wp-content/` permanecen sin cambios. Siguiente acción: **Eje** revisa los resultados y el criterio histórico; retiro recuperable del scratch pendiente por política. No hay aprobación global del E2E.

## Vuelta 5

21-nov-2026 · **LOCAL / PEER_REVIEW_PENDING**. Autor A: **Forja-Codex**. Revisor: **Eje**, sin atribuirle verificaciones de esta vuelta. Base verificada `1911142723ac784bbf6e7688f99552a3429b0d59`, rama `codex/resena-google-impl`, árbol inicialmente limpio. Se conserva la rama por instrucción expresa de Luis. Alcance: CCO del correo de reseña y estado de configuración en el panel. Sin despliegue, SSH, servicios/credenciales/datos reales, push, merge ni stash. Solo fixtures sintéticas; los nuevos destinatarios usan `example.test`.

Ruta: **C2 · riesgo medio · incertidumbre baja · reversible → Forja-Codex en la sesión actual; Eje revisa.** Se aplicó `at-router-modelos`, sin delegación ni cambio de modelo/perfil. No se afirma haber ejecutado otro modelo.

### Arreglo (archivo:línea)

Raíz **I** = `wp-content/themes/automatiza-tech/inc/plan-trabajo/`.

| Archivo:línea | Cambio |
|---|---|
| `I/resena-correo.php:5` | `at_rg_copia_oculta()`: lee `at_resena_copia_oculta` con valor vacío; exige string, rechaza CR/LF/coma y valida mediante `at_rg_email()`. Sin correo real ni valor por defecto. |
| `I/resena-correo.php:17` | Mantiene los filtros de cierre y `clearAllRecipients()`; tras `addAddress($destino)` añade solo esa CCO si es válida y distinta del destino normalizado. `addBCC()` false o excepción retira la copia con `clearBCCs()` y permite continuar. No añade cabeceras To/Cc/Bcc. El mismo cierre cubre prueba y reenvío. |
| `I/avance-panel.php:818` | Línea con ícono, activa/inactiva y correo enmascarado; dominio separado por la última arroba. Escape HTML y sin campo de edición. Solo panel privado con `manage_options`. |
| `tests/plan-trabajo/resena-estado-test.php:27` | Doble PHPMailer con BCC/CC, fallos false/excepción y captura de cabeceras; inclusión de los casos V5 al final. |
| `tests/plan-trabajo/resena-v5-casos.php:3` | 31 comprobaciones nuevas: opciones, cierre, errores, invitación/prueba/reenvío y panel. |
| `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md:233` | Configuración, alcance, comandos de despliegue/desactivación y verificación de recepción pendiente. |

Se mantiene el mismo mensaje para To y BCC, sin crear otro correo ni reintentar al cliente. Los avisos operativos que ya usan `at_rg_mail_cerrado()` también pasan por su cierre; si la opción coincide con el destino operativo, no se duplica. No hay nueva tabla, migración ni cambio de cupos/estados/WhatsApp. El error aislado que se prueba es el de añadir BCC; entrega/rechazo posterior por el proveedor y recepción en buzones reales siguen NOT_RUN.

### TDD estricto: pruebas rojas primero

Primero se modificó exclusivamente el doble y se añadió `resena-v5-casos.php`; luego se ejecutó, todavía con producción en `1911142`:

```powershell
& C:/wamp64/bin/php/php8.5.0/php.exe -d xdebug.mode=off tests/plan-trabajo/resena-estado-test.php
```

**Rojo observado: exit 1, 174 comprobaciones, 8 fallas.** Nombres literales:

- `V5 opcion valida conserva envio y BCC exacto`
- `V5 filtros no agregan To CC ni BCC adicionales`
- `V5 invitacion lleva copia unica al destino correcto`
- `V5 prueba lleva copia unica al destino correcto`
- `V5 reenvio lleva copia unica al destino correcto`
- `V5 panel activa enmascara y no permite editar`
- `V5 panel inactiva enmascara y no permite editar`
- `V5 panel invalida enmascara y no permite editar`

**Controles ya verdes antes del arreglo**, conservados: opción ausente/vacía, igual al destino (también normalizada), inválida, CRLF inyectado, CRLF final, coma y tipo inesperado; To único y sin copia visible. Los controles de fallo false/excepción ya eran verdes sin BCC en la base; tras el arreglo ejercitan `addBCC()` y la limpieza, incluido el caso sintético que añade la copia antes de lanzar. También se exige que el intento quede `aceptado` ante ambos fallos de CCO. No se inventan rojos para controles que ya pasaban.

Tras el arreglo: **174/174 PASS, exit 0**. Después del ajuste del enmascarado por última arroba, la integración volvió a dar **174/174, exit 0**.

### Suite completa y checklist A

```powershell
& tests/plan-trabajo/resena-suite.ps1 -ConMotores
git diff --check
```

**Resultado real: exit 0, `Suite offline completa: 0 fallos; lint 18 PHP`.** Puras **70/70**; integración con dobles **174/174**; capacidad wamid **7/7**; cuatro regresiones de cierre/entregables/avance **TODO OK**; Python n8n **9/9**; JS **8 expresiones compiladas** y destinatario/idioma/cuatro resultados comprobados; sintaxis JS exit 0. MySQL **8.4.7: 20/20** y MariaDB **11.4.9: 20/20**, procesos aislados, detenidos y fixtures retiradas por el runner. El aviso preexistente de carga de Xdebug se mantiene visible; no se cambió PHP ni se ocultó el diagnóstico. `git diff --check`: PASS.

Comprobación adicional offline por stdin, sin archivo temporal: clases PHPMailer/Exception/SMTP leídas del núcleo local `C:/wamp64/www/at-rastro-test/wp-includes/PHPMailer/`, sin cargar WordPress/wp-config ni acceder a su base. Se invocó el cierre real desde un doble de `wp_mail`, usando `isSMTP()` + `preSend()` + `getSentMIMEMessage()`, **nunca `send()`/conexión SMTP**. **3/3 PASS, exit 0:** To único; BCC único sin CC; MIME sin cabeceras Cc/Bcc, sin valor de la copia ni intrusos. El primer comando diagnóstico produjo un warning de `compact()` por variable del arnés; se corrigió solo ese comando y el pase final quedó sin ese warning. Esta comprobación es complementaria, no añade una dependencia a la suite.

| Control A | Resultado / límite |
|---|---|
| Validación y cierre de destinatarios | PASS: tabla V5, inyección en wp_mail y phpmailer_init, BCC único. |
| No bloquear al cliente al añadir la copia | PASS: `addBCC()` false/excepción y estados de intento aceptado con dobles. |
| Prueba y reenvío | PASS por `at_rg_reclamar()`, con modo/tipo/destino y número de correos comprobados. |
| Copia invisible al cliente | PASS de cabeceras del doble y MIME SMTP offline de PHPMailer real. |
| Panel privado | PASS de HTML, activa/inactiva, enmascarado y ausencia de edición. Navegador visual NOT_RUN. |
| SMTP/proveedor/buzones reales y hosting | NOT_RUN, fuera del alcance autorizado; no se afirma recepción real ni aislamiento de rechazos posteriores del proveedor. |
| Superficies públicas / SEO + IA | NOT_APPLICABLE: correo y panel privado; rutas públicas de reseña/baja intactas. |
| Revisión B | PEER_REVIEW_PENDING; asignada a Eje, no ejecutada por Forja. |

El hallazgo histórico del E2E local sobre corte automático y calendario permanece tal como está en la sección anterior; esta vuelta no lo corrige ni revalida ese E2E.

### Lista PROD incremental desde 1911142 — preparada, NO aplicada

Instalación inicial: conservar la lista completa de Vuelta 2 y las actualizaciones de Vuelta 4. Para esta vuelta, subir los dos archivos siguientes **en este orden**, una vez revisados y autorizado el despliegue; mantener los interruptores operativos del módulo detenidos durante la actualización.

| Orden | Ruta local exacta | Destino relativo en PROD | Clase |
|---|---|---|---|
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-correo.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-correo.php` | OBLIGATORIO |
| 2 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` | OBLIGATORIO |

**OPCIONAL:** ninguno. Después, configurar por el canal autorizado `wp option update at_resena_copia_oculta <correo>` y comprobar panel enmascarado, To único, CCO invisible y recepción de la prueba. No se ejecutó ese comando. Para desactivar solo CCO: `wp option update at_resena_copia_oculta ''`; no borra intentos ni revoca correos ya aceptados. Rollback de código: restaurar estos dos archivos desde `1911142` con el módulo detenido. Sin migración ni cambio de esquema. Conservar las bajas y marcas existentes.

**NO SUBIR:** `tests/`, `Docs/` e informe A, `graphify-out/`, `.git/`, fixtures/temporales/respaldos, `.env`, `wp-config.php`, credenciales ni datos reales. No hay publicación ni nueva activación autorizadas.

### Grafo, residuos y Git

`graphify query` ejecutado antes de explorar; `graphify update .` → **exit 1, WinError 5 / Acceso denegado**. Grafo no actualizado; no se sortearon permisos.

Aseo `init` y `checkpoint task-close`, plataforma codex, sesión nativa `01a1fc1f-52ea-7f13-91c2-33be060171b9` y root real → **preserved**, «Ruta ausente, ocupada o sin permiso: conservar y revisar». No se generaron scripts/logs/capturas transitorios propios; diagnóstico MIME por stdin. Las fixtures de motor las crea y retira el runner existente, sin modificarlo. Se confirmó además con Test-Path que ambas carpetas del primer pase ya no existen. Los residuos históricos externos de otras vueltas permanecen fuera del alcance, sin tocarse. No se afirma limpieza global ni se modifica el registro Aseo.

Se conservan los archivos de documentación compartida sincronizados: esta entrega actualiza la guía canónica y este informe, sin duplicar contenido en AGENTS/CLAUDE/OPENCODE. No se actualizan memorias personales. Evidencia permanente en este informe y pruebas del worktree autorizado; no se intenta escribir fuera de las raíces permitidas.

Estado previo al intento de commit: seis archivos propios (cinco modificados y un test nuevo), sin cambios ajenos. Revisión del diff y whitespace PASS. Commit solo local; sin push, merge ni stash. Siguiente acción: **Eje revisa Vuelta 5**; no hay aprobación B ni autorización de despliegue.

**Git final:** `git add --` con los seis archivos explícitos → **exit 1**, `Unable to create .../.git/worktrees/guias/index.lock: Permission denied`. No se ejecutó `git commit`, no se reintentó con permisos superiores ni se tocó el candado. **HEAD permanece `1911142`; seis archivos sin stage y sin commit**, conforme al pedido de Luis si falla el commit. La suite completa se ejecutó de nuevo después del ajuste final del panel: **exit 0, 0 fallos, lint 18 PHP**, mismos conteos anteriores y motores 20/20 cada uno; se comprobó con Test-Path que las dos fixtures de ese pase también están ausentes. Checkpoint final Aseo con identidad/root reales repitió **preserved**; sin retiros manuales ni cambios a residuos históricos. Pendiente revisión de **Eje**.


## Vuelta 6

21-nov-2026 · **LOCAL / PEER_REVIEW_PENDING / CAPTURA_NOT_RUN**. Autor A de esta vuelta: **Lienzo-Codex**; revisor asignado: **Eje**, sin atribuirle comprobaciones. Rama `codex/resena-google-impl`, base verificada `2965264`, árbol inicial limpio. Se conserva esta rama por encargo expreso de Luis. Alcance aprobado: correo con marca sobre `correo-resena-PROPUESTA.html` y pruebas. Sin despliegue, SSH, credenciales, servicios/datos reales, push, merge ni stash; solo motores locales desechables, dobles y MIME sin envío. Los contactos del pie son los datos públicos autorizados de AT. Fixtures nuevas exclusivamente `example.test`.

Ruta: **C2 · riesgo medio · incertidumbre baja · reversible → Lienzo-Codex en la sesión actual; Eje revisa.** Skill `at-router-modelos` aplicada. Sin delegar, cambiar de modelo ni afirmar otro perfil efectivo.

### Archivos y comportamiento

- `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-correo.php`: asunto personalizado con y sin nombre y prefijo de prueba; nombre sin etiquetas/saltos/controles Unicode, espacios normalizados y máximo 60 caracteres UTF-8. HTML real basado en la maqueta aprobada: logo de 220 px, preheader oculto, título e ícono 💬 en círculo, texto aprobado literal, firma de equipo y botón a `home_url('/resena/')`. Tablas, estilos inline y ancho máximo 600 px. Datos variables escapados con `esc_html`/`esc_url`.
- Cuatro PNG en `assets/images/correo/`: 40×40 RGBA, color `#1e40af`, renderizados a 20 px, `alt` descriptivo; íconos propios de WhatsApp e Instagram. No usa SVG ni fuentes de íconos en el correo. El logo existente se referencia por `get_template_directory_uri()`; no se modifica ni se prueba su URL en PROD.
- `List-Unsubscribe` y `List-Unsubscribe-Post` se construyen con la misma URL firmada de baja del cuerpo. HTTPS válido, sin controles ni delimitadores inyectables en cabeceras. La ruta ya acepta POST con token en query, por lo que no se modifica. La firma HMAC sigue vinculada al snapshot del intento.
- `AltBody` contiene el texto aprobado y todos los enlaces; se fija en `phpmailer_init` dentro del cierre existente. Cabeceras cerradas a la lista permitida, To único y única CCO configurada conservados. Parámetros opcionales mantienen compatibilidad con avisos operativos; no heredan contenido ni baja de invitaciones. Los hooks siguen retirándose en `finally`.
- `tests/plan-trabajo/resena-estado-test.php`: stub de URI del tema y propiedad AltBody en el doble; incorpora `resena-v6-casos.php`.
- `tests/plan-trabajo/resena-v6-casos.php`: **35 controles nuevos** (209 totales con los 174 existentes), incluidos truncado multibyte, nombre ausente, CRLF/controles, prueba, texto exacto, enlaces, íconos, dimensiones PNG, escape y ausencia de fuga al correo siguiente.
- `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md`: estado y operación V6; este informe es la fuente del detalle y lista de subida. No se duplica contexto en archivos sincronizados de agentes.

**Interpretación de “NADA de estrellas”:** se conserva exclusivamente la ⭐ del texto literal del botón aprobado «⭐ Dejar mi reseña en Google». La prueba exige exactamente una y rechaza puntuaciones gráficas, “5 estrellas” o “cinco estrellas”. No se añadió solicitud de puntuación ni se cambió el párrafo aprobado.

### TDD: rojo antes de producción

Se modificaron primero solo los dobles y los casos V6. Con `resena-correo.php` todavía en `2965264`:

```powershell
& C:/wamp64/bin/php/php8.5.0/php.exe -d xdebug.mode=off tests/plan-trabajo/resena-estado-test.php
```

**Rojo real: exit 1, 209 comprobaciones, 30 fallas.** Nombres literales:

- `V6 asunto con nombre`
- `V6 asunto sin nombre`
- `V6 asunto sin campo`
- `V6 asunto CRLF y controles`
- `V6 asunto solo controles`
- `V6 asunto 60 caracteres`
- `V6 asunto prueba`
- `V6 asunto prueba sin nombre`
- `V6 logo AT de 220 px`
- `V6 titulo e icono`
- `V6 parrafo literal HTML y texto`
- `V6 boton aprobado a resena`
- `V6 firma de equipo`
- `V6 preheader oculto literal`
- `V6 tablas e inline sin fuentes SVG ni scripts`
- `V6 pie enlace e icono web`
- `V6 texto enlace web`
- `V6 PNG 40x40 web`
- `V6 pie enlace e icono correo`
- `V6 texto enlace correo`
- `V6 PNG 40x40 correo`
- `V6 pie enlace e icono whatsapp`
- `V6 texto enlace whatsapp`
- `V6 PNG 40x40 whatsapp`
- `V6 pie enlace e icono instagram`
- `V6 texto enlace instagram`
- `V6 PNG 40x40 instagram`
- `V6 cabeceras baja un clic`
- `V6 AltBody texto y enlaces`
- `V6 baja conserva cierre y CCO con filtros`

Los cinco controles V6 ya verdes en la base eran: saludo literal, ausencia de puntuación salvo el ícono, baja HTML, escape y no fuga al siguiente correo. Las pruebas V5 de CCO/cierre también se mantuvieron verdes. El nombre ausente produjo además warning/deprecated en el HTML anterior; quedó resuelto con el acceso opcional.

Durante la implementación, el pipe de PowerShell inicial convirtió caracteres a `?`; los tests de texto literal lo detectaron (pase intermedio: 15 fallas), y luego detectaron el saludo estático pendiente y los PNG ausentes (5 fallas). Se corrigió usando UTF-8 explícito y el saludo dinámico; **pase final 209/209, exit 0**. No se presentan los pases intermedios como resultados finales ni se oculta el problema de codificación.

### Suite y comprobaciones reales

```powershell
& tests/plan-trabajo/resena-suite.ps1 -ConMotores
git diff --check
```

**Suite: exit 0, `Suite offline completa: 0 fallos; lint 19 PHP`.**

| Comprobación | Resultado |
|---|---|
| Reglas puras | 70/70 PASS |
| Integración con dobles (V2–V6 y CCO/cierre V5) | 209/209 PASS |
| Capacidad wamid | 7/7 PASS |
| Cierre, entregables, avance y cierre de plan | Cuatro `TODO OK` |
| Python n8n | 9/9 PASS |
| JS n8n | 8 expresiones compiladas; destinatario, idioma y cuatro resultados PASS |
| Sintaxis JS y PHP | PASS; 19 PHP lintados |
| MySQL 8.4.7 aislado | 20/20 PASS |
| MariaDB 11.4.9 aislado | 20/20 PASS |
| Diff whitespace | PASS |

Los runners detuvieron los procesos de motor y retiraron sus fixtures. `Test-Path` sobre las carpetas de este pase confirmó ausencia: `at-resena-motor-v2-548cfe8ad9e746318021ed6270bebdc9` y `at-resena-motor-v2-57947edeef9742b688aecc906d8a4c99`. El warning preexistente de carga de Xdebug sigue visible; no se alteró la instalación de PHP.

**PHPMailer real offline: 4/4 PASS**, con clases locales de `C:/wamp64/www/at-rastro-test/wp-includes/PHPMailer/`, sin cargar WordPress/wp-config. Arnés por stdin, `isSMTP()` + `preSend()` + `getSentMIMEMessage()`, nunca `send()` ni conexión SMTP. Nombres: `MIME multipart alternative HTML y texto`, `MIME baja un clic y token`, `MIME To CCO exactos y sin copia visible`, `MIME asunto personalizado de prueba`. La parte MIME contiene HTML y texto, cabeceras de baja, To y CCO exactos; sin CC, sin intruso ni dirección de copia en el mensaje visible.

**Repetición tras fijar el RGB de los cuatro PNG:** suite completa otra vez **exit 0, 0 fallos, lint 19 PHP**, mismos conteos anteriores; MySQL y MariaDB **20/20** cada uno. Ambos runners confirmaron servidor detenido y fixture retirada; `Test-Path` final confirma ausencia de `at-resena-motor-v2-d05f2b84d9c24ddfbcc5106278533941` y `at-resena-motor-v2-58219d01c7184a34b962f329f4c31b6f`.

### Vista previa y límite de captura

Directorio de evidencia autorizado (fuera de repo/webroot): `C:/Users/luis_/AppData/Local/Temp/claude/61dcc82c012432e9/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/resena/`.

- **GENERADO:** `correo-resena-FINAL.html`, llamando directamente a `at_rg_correo_html()` desde `correo-resena-preview-v6.php`, con stubs mínimos de WordPress, token firmado con sal ficticia, nombre **María**, proyecto **el sitio web de Café Aurora** y destino sintético. El stub de URI del tema usa imágenes locales `file:///` para evitar descargar recursos en la previsualización; HTML de producción conserva la URI real del tema.
- **NOT_RUN:** `correo-resena-FINAL.png` y verificación visual de escritorio/móvil. Se ejecutó Chrome headless con el tamaño solicitado; falló antes de capturar: `CreateFile: Acceso denegado (0x5)`, `platform_channel.cc Check failed: Acceso denegado`, `crash server failed to launch`. El proceso devolvió 0, pero **no existe el PNG**, por lo que no es PASS. La conexión alternativa del navegador integrado/CUA también falló por `windows sandbox failed: CryptUnprotectData failed: 2148073483`. No se desactivó el sandbox ni se sustituyó por una imagen simulada.
- Se leyó la skill de navegación; prevaleció el comando Chrome solicitado por Luis. El intento de captura no navegó a servicios reales.

Comando preparado para ejecutar en un entorno con permiso de Chrome, pendiente de Eje:

```powershell
& 'C:/Program Files/Google/Chrome/Application/chrome.exe' --headless=new --window-size=680,900 --hide-scrollbars --virtual-time-budget=2000 --screenshot=C:/Users/luis_/AppData/Local/Temp/claude/61dcc82c012432e9/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/resena/correo-resena-FINAL.png file:///C:/Users/luis_/AppData/Local/Temp/claude/61dcc82c012432e9/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/resena/correo-resena-FINAL.html
```

La ausencia de captura mantiene la entrega visual parcial; no equivale a aprobación de la maqueta implementada en Gmail/Outlook ni en móvil real. Recepción real y proveedor SMTP: **NOT_RUN**, fuera del alcance autorizado. Los pendientes históricos de calendario y corte del E2E anterior no se cambian ni se revalidan aquí.

### Procedencia de los íconos y licencia

Fuentes oficiales leídas mediante GitHub: [Phosphor core / assets regular](https://github.com/phosphor-icons/core/tree/main/assets/regular), con [package.json que declara 2.1.1](https://github.com/phosphor-icons/core/blob/main/package.json). Iconos `globe`, `envelope-simple`, `whatsapp-logo`, `instagram-logo`. Descarga directa CDN bloqueada por red del entorno; acceso de lectura GitHub exitoso. No se instaló ningún paquete ni se cambiaron dependencias. Se conservaron en el scratch los cuatro SVG exactos recuperados y su generador. Render local con PyMuPDF a 160 px y reducción Lanczos con Pillow a 40 px; los correos solo sirven PNG locales del tema.

| PNG | SVG SHA256 (fuente exacta) | PNG SHA256 |
|---|---|---|
| `web.png` | `3b53d207c09fc72de699c8b2149892e065b570b3ae144412ae53244b0a0f7977` | `8956c7733c66361e160f18c3c0a2620121861a1fc4f4bf1235f8bae485b94628` |
| `correo.png` | `f114441afbd3c3da8aee24ba4cddea1576cf55aa304eb507410fca494c8ee169` | `f3d01afdc3f28451e55d3c0da539e23831ab21f894500c20765a51804efbaa07` |
| `whatsapp.png` | `37474af9246a13b9b9fbf36561081d02c876db27682c1ac7ce8c2418c2a20320` | `3d9660fbb63b0312a013f2322e58904709cfc88b846df8101245a46c6806494d` |
| `instagram.png` | `a08dcda4ded4c6bd00c234085604d9547f03dc9f7f385f3d175bd280714848da` | `d03b1ccb5806c4691eea1467aa3955b84a35ae8b3fd6ec59bda954b065e20b37` |


### Checklist A y revisión pendiente

| Control | Estado |
|---|---|
| Asunto y control de inyección, nombre ausente y truncado UTF-8 | PASS automatizado |
| Diseño y texto aprobado, firma, enlaces, iconos PNG y escapes | PASS de estructura/contenido; visual NOT_RUN |
| Baja un clic y AltBody | PASS con dobles y MIME real offline |
| To/CCO y filtros de vuelta 5 | PASS, sin regresión |
| Superficies públicas / SEO + IA | NOT_APPLICABLE para el correo privado; rutas públicas intactas, cuatro PNG públicos sin datos personales |
| Captura Chrome 680×900 y legibilidad visual móvil | NOT_RUN por permisos del entorno |
| SMTP, proveedor y recepción real Gmail/Outlook | NOT_RUN; no autorizados |
| Revisión de Eje | PEER_REVIEW_PENDING; no realizada por Lienzo |

### Lista PROD incremental desde 2965264 — preparada, NO aplicada

Para una instalación desde cero conservar las listas completas anteriores, incluida `avance-panel.php` de Vuelta 5. Esta lista es solo el delta de Vuelta 6. Cuando Eje revise y Luis autorice el despliegue: módulo detenido durante la actualización, primero los PNG y al final el PHP que los referencia.

| Orden | Ruta local exacta | Destino relativo en PROD | Clase |
|---|---|---|---|
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/assets/images/correo/web.png` | `wp-content/themes/automatiza-tech/assets/images/correo/web.png` | **OBLIGATORIO** |
| 2 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/assets/images/correo/correo.png` | `wp-content/themes/automatiza-tech/assets/images/correo/correo.png` | **OBLIGATORIO** |
| 3 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/assets/images/correo/whatsapp.png` | `wp-content/themes/automatiza-tech/assets/images/correo/whatsapp.png` | **OBLIGATORIO** |
| 4 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/assets/images/correo/instagram.png` | `wp-content/themes/automatiza-tech/assets/images/correo/instagram.png` | **OBLIGATORIO** |
| 5 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-correo.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-correo.php` | **OBLIGATORIO** |

**OPCIONAL:** ninguno. Logo existente: no se modifica ni se vuelve a subir en este delta. **NO SUBIR:** tests, documentación e informe A, grafo, `.git`, scratch, HTML/PNG de vista previa, SVG fuente de evidencia, generadores, fixtures, temporales, respaldos, `.env`, `wp-config.php`, secretos ni datos privados.

Rollback local de referencia: restaurar `resena-correo.php` desde `2965264` con el módulo detenido; los cuatro PNG sin referencia pueden conservarse. No requiere migración, alterar opciones ni borrar intentos/bajas. No se ejecutó rollback ni despliegue.

### Grafo, Aseo y Git

Graphify consultado antes de navegar; `graphify update .` intentado al terminar → **exit 1 / WinError 5 Acceso denegado**, sin sortear permisos. Grafo no actualizado.

Aseo `init` y checkpoint `task-close`, plataforma Codex, sesión nativa completa `01a1fc38-2080-7fd2-b391-cb5eb628f9ff`, root real → **preserved**, «Ruta ausente, ocupada o sin permiso: conservar y revisar». No se alteró el registro ni se retiró ningún archivo manualmente. Evidencia propia conservada para Eje: HTML, stub PHP, cuatro SVG y generador en el scratch autorizado. Los archivos históricos/activos ajenos del scratch permanecen intactos. No se afirma limpieza global ni un checkpoint exitoso.

No se escribe en Documentos/AT-Informes ni en memorias personales fuera de las raíces autorizadas. La evidencia nueva queda en este informe, pruebas y scratch. No se tocan archivos sincronizados de agentes al no cambiar arquitectura, comandos operativos ni ownership del módulo.

**Estado de commit:** el intento local de git add sobre los nueve archivos propios falló por Permission denied al crear .git/worktrees/guias/index.lock. No se ejecutó git commit; HEAD permanece en 2965264, nueve archivos sin stage ni commit; no hay push, merge ni stash. Próximo responsable: **Eje**, para revisión V6, captura real y verificación visual. No hay aprobación B ni autorización de publicación.


#### Aviso de licencia de los cuatro iconos

Fuente: https://github.com/phosphor-icons/core/blob/main/LICENSE

```text
MIT License

Copyright (c) 2023 Phosphor Icons

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```


## Vuelta 7

22-nov-2026 · **APLICADO LOCAL / PEER_REVIEW_PENDING**. Autor A: **Forja-Codex**; revisor asignado: **Eje**. Base verificada `0459a27`, rama `codex/resena-google-impl`, worktree `C:/wamp64/www/automatiza-tech/.worktrees/guias`, árbol inicialmente limpio. Se conserva la rama expresamente indicada por Luis. Alcance: ajustes recibidos del dictamen **APTO CON AJUSTES de Toga-Claude** y decisiones de Luis del 22-nov; este autor implementa y prueba, no se atribuye una revisión legal ni B independiente.

Ruta: **C2 · riesgo alto · incertidumbre media · reversible en local → Forja-Codex en la sesión actual; revisión de Eje.** Se aplicó `at-router-modelos`; no se invocó otro modelo para implementar. Aseo intervino únicamente como respaldo de conservación de artefactos, no como revisor del código.

### Prueba roja, arreglo y resultado por punto

Las pruebas focalizadas se ejecutan con el PHP local `C:/wamp64/bin/php/php8.5.0/php.exe -d xdebug.mode=off tests/plan-trabajo/resena-estado-test.php --v7=A1` (sustituir A1 por A2/A4/A5/A7/A6/A3). Se añadieron antes de modificar cada comportamiento. `resena-v7-publica.php` ejecuta la **ruta PHP real en procesos aislados**, con `$_POST` obtenido del cuerpo literal y `$_GET` firmado, dobles de WP/SQL y sin servidor HTTP, SMTP, Google ni clientes reales. No se presenta como un POST recibido de Gmail real.

| Punto | Prueba roja observada antes del arreglo | Arreglo local | Resultado focalizado final |
|---|---|---|---|
| 1 · A1, bloqueante | **2 fallas / 4**: `oneclick ruta publica e idempotencia` y `fallodb`. Inválido y prioridad del cuerpo ya eran verdes. | `resena-bajas.php`: POST usa token del cuerpo y, si falta, query. Con el cuerpo `List-Unsubscribe=One-Click` guarda alcance resenas y responde 200 sin formulario. Error de escritura: 503 sin anunciar éxito. Se prueba idempotencia con doble fiel al índice UNIQUE. | **4/4 PASS**. |
| 2 · Aviso interno separado | **13 fallas / 16**: faltaban dos mensajes, To exclusivo, asunto/contenido inerte y estado del panel. | `resena-correo.php`: elimina todo BCC; después de aceptar al cliente envía otra vista a la opción corporativa, con correo enmascarado, baja inerte, sin ambas cabeceras de baja ni token en HTML/AltBody y aviso de borrar a 90 días. Fallo y excepción internos no alteran aceptación. Panel activo/inactivo enmascarado. Una prueba adicional detectó truncado a 60 caracteres en el asunto interno (1 falla/17); ahora conserva hasta los 255 caracteres del campo proyecto, manteniendo los 60 del nombre del cliente y sus controles de cabecera. | **17/17 PASS**: invitación, prueba y reenvío; rechazo/excepción de copia y cliente rechazado sin copia. Se conserva el nombre histórico `at_resena_copia_oculta`; valor ausente, listas/CRLF o igual al principal siguen sin habilitar copia. |
| 3 · A4, pie | **5 fallas / 5**: identidad, razón, política y ubicación sobre baja ausentes. | Pie HTML y AltBody incluyen «Te escribimos porque trabajamos contigo en {proyecto}. AUTOMATIZATECH SpA · Política de privacidad», con enlace HTTPS indicado. | **5/5 PASS**. |
| 4 · A5, alcance | **11 fallas / 12**: elección total, frases normalizadas, avisos ambiguos y función pública faltaban; baja resenas ya era verde. | Dos botones en la página. One-click fuerza resenas. WhatsApp registra canal whatsapp/alcance todo para frase o prefijo explícito; dudas solo avisan una vez por teléfono/día Santiago mediante reserva atómica. `at_rg_baja_total()` consulta destinatario normalizado y bloquea ante fallo DB. Adaptador PHP en `whatsapp-recordatorios.php`; generador offline n8n conserva el texto original. | **13/13 PASS**; además prueba Python/Node ejecuta las expresiones con siete frases y un mensaje normal, preservando texto/teléfono. Retomar y Prospección **no se modificaron**. |
| 5 · A7, recordatorio uniforme | **12 fallas / 14** iniciales: recordatorio automático, plazo, marca, cupo único, motivo, cola y panel. Después se añadieron dos pruebas de carrera: **2 fallas / 23** antes de reforzar las comprobaciones por ambos canales. En motor aislado: **1 falla / 21** antes de filtrar reenvío fallido, marca y anonimizado en SQL. | Cola agrega invitación real aceptada para recordatorio; `reclamar` reserva CAS como reenvío de reloj a partir de 7 días, también para una invitación manual histórica. Un intento real de reenvío agota el recordatorio aunque falle. Marca `resena_recibida={fecha,por}`, admin-post/nonce/manage_options, deshacer. Manual exige motivo objetivo y lo guarda; ayuda excluye expectativas de opinión. Baja de cualquier canal bloquea el recordatorio en elegibilidad, antes de SMTP y al autorizar WA. | **23/23 PASS**; marca/no nonce/no capability/deshacer, carrera CAS, bloqueo de despacho y bajas cruzadas. SQL en motores: ver resultado completo abajo. No se consulta Google API. |
| 6 · A6, conservación | **2 fallas / 2** iniciales; al ampliar cron/relación: **3 fallas / 4** antes de implementar. Faltaban función pura, anonimización real del cron y vaciado de notas. | `at_rg_anonimizar` con reloj inyectado y mantenimiento horario conservan mientras exista relación y hasta 24 meses desde último intento. Al vencer: campos de contacto/nombre/proyecto/motivo/detalle vacíos, índice wamid retirado, estados/fechas e ids técnicos conservados; callback tardío rechazado. Bajas sin plazo y nota siempre vacía; notas antiguas se vacían en mantenimiento. | **10/10 PASS**: relación vigente, frontera exacta, plazo vencido, último intento, idempotencia, cron persistido, limpieza de índice y callback tardío. |
| 7 · A3, privacidad | **7 fallas / 7**: nueva fecha/finalidad/plazos ausentes. | Una fila en finalidades, tres renglones de conservación (intentos/bajas/copia interna) y fecha 2026-11-22. Diff HTML resaltado para Luis. | **7/7 PASS**. Reconstrucción retirando solo esos añadidos y revirtiendo fecha reproduce el contenido completo de `0459a27` (normalizando CRLF del checkout). Ninguna otra parte de la política cambió. |
| 8 · Textos aprobados | Control de conservación, sin cambio solicitado; no se fabrica una roja. | Función de asunto del cliente intacta; `plantilla_meta.py` sin cambios versionados, y ⭐ del botón conservada. | **PASS** comparación contra HEAD normalizando fin de línea, más pruebas V6 de asunto, texto y única ⭐. |

Las pruebas históricas V4/V5/V6 se actualizaron solo donde la decisión de Luis sustituye el BCC o el reenvío exclusivamente manual. Se mantienen las comprobaciones de filtros hostiles, destinatarios, modo prueba, escapes, cupos, incertidumbre y permisos. Los nuevos casos V7 aportan **79 comprobaciones** a la integración, además de las **209 anteriores adaptadas**. No se borraron regresiones para declarar éxito.

Durante el trabajo se corrigió una duplicación del include V7 en el arnés, detectada al ejecutar la integración completa (los avisos se habían reservado en su primer pase). También hubo un intento de captura del log por `2>&1 | Tee-Object` que interrumpió la suite al convertir stderr de unittest en un error terminante de PowerShell. El pase definitivo usa un proceso PowerShell hijo con captura desde Python; sigue ejecutando el mismo script y no silencia exit codes. Un primer cotejo de bytes de la plantilla falló por CRLF del checkout; el cotejo correcto conserva el contenido versionado normalizando fin de línea. Ninguno de esos pases intermedios se presenta como suite final.

### Suite completa y evidencia real

Comando requerido, exclusivamente local:

```powershell
& tests/plan-trabajo/resena-suite.ps1 -ConMotores
git diff --check
```

**Suite definitiva: exit 0; `Suite offline completa: 0 fallos; lint 21 PHP`.** Log completo conservado en S/`suite-v7-final.log`, con `FINAL_SUITE_EXIT=0`. Los pases previos también se conservan como históricos en la conversación; el log enlazado corresponde al código final, incluida la repetición del POST completo y el asunto interno con proyecto largo.

| Comprobación | Resultado real del pase definitivo |
|---|---|
| Reglas puras | 70/70 PASS |
| Integración con dobles V2–V7 | 288/288 PASS (79 V7 + 209 regresiones) |
| Capacidad wamid | 7/7 PASS |
| Cierre, entregables, avance y cierre de plan | Cuatro `TODO OK` |
| Python n8n (incluye clasificación real JS del bot) | 10/10 PASS |
| JS n8n | 8 expresiones compiladas; destino/idioma y cuatro resultados PASS |
| Lint JS/PHP | JS PASS; 21 PHP PASS |
| MySQL 8.4.7 aislado | 21/21 PASS |
| MariaDB 11.4.9 aislado | 21/21 PASS |

El warning preexistente de carga de Xdebug se conserva visible; no se alteró PHP. Los motores de prueba tienen datadir y puerto efímeros; no son servicios instalados ni los motores reales del sitio. El runner verifica y retira su propia carpeta solo después de detener el proceso.

### Criterios operativos y límites para Eje

- **Relación vigente:** se usa `crm_clientes.tipo=cliente`; el cierre explícito usa `tipo=cerrado`. La fecha de término de un proyecto no autoriza anonimizar una relación vigente. La ficha se reconsulta dentro de cada decisión CAS; lectura fallida o ficha ausente conserva datos. Es una interpretación operativa documentada para que Eje la contraste antes de activar; no se inventó un campo de contrato. Las fichas se cierran antes de eliminarlas. El mantenimiento no envía al cliente.
- **Conservación:** no se restablecen cupos ni se eliminan bajas. La marca de anonimización bloquea nuevos envíos/callbacks y cierra la proyección `abierto`. Se conserva la identidad técnica del proyecto para no duplicar invitaciones. La regla de borrado del buzón corporativo a 90 días requiere configuración de su responsable; WordPress no opera ese buzón.
- **Integración pendiente por alcance:** Retomar y Prospección deben consultar `at_rg_baja_total()` antes de sus promociones. Se dejó la función y la nota; no se afirma que esos módulos ya respeten la nueva función.
- **RFC 8058:** el token en URL y POST sin datos extra se contrastaron con [RFC 8058, §3.1](https://www.rfc-editor.org/rfc/rfc8058). DKIM del proveedor cubriendo ambas cabeceras, SMTP, recepción Gmail/Outlook, HTTP de WordPress real, bot real y plantilla en Meta: **NOT_RUN**, fuera de la autorización. El arnés no acredita recepción ni firma de PROD.
- **Visibilidad:** panel y REST privados; `/resena/baja/` es público por capacidad HMAC, con no-cache/noindex/no-referrer/CSP preservados. GET no escribe. Política en `/privacidad/` es pública; cambio solo de contenido en el fragmento existente. SEO estructural del contenedor sin cambios; comprobación del despliegue/indexación/citas **NOT_RUN**. No se habilitó entrenamiento ni se crearon servicios de búsqueda.
- **B:** Eje revisa esta misma vuelta. **PEER_REVIEW_PENDING**, sin autorizar publicación. Luis revisa la política antes de subirla.

### Vistas previas y diff para Luis

Directorio autorizado **S** = `C:/Users/luis_/AppData/Local/Temp/claude/61dcc82c012432e9/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/resena/`.

| Archivo en S | Entrega y verificación |
|---|---|
| `correo-resena-FINAL.html` | Regenerado desde `at_rg_correo_html()`: María, el sitio web de Café Aurora, correo sintético de ejemplo.cl. Pie legal y baja firmada con sal ficticia. |
| `copia-interna.html` | Misma función con vista interna: ma***@ejemplo.cl, baja inerte, sin token y leyenda de 90 días. |
| `privacidad-cambios.html` | Comparación antes/propuesto con añadidos resaltados; pendiente de Luis. |
| `privacidad-antes-v7.html` | Base de comparación de política, sin cambios. |
| `correo-resena-preview-v7.php` | Stub reproducible, sin WP/BD/SMTP/red; fuentes reales del módulo e imágenes locales. |
| `INDICE-V7.json` | Tamaños y SHA256 de los cinco artefactos anteriores. |
| `suite-v7-final.log` | Log del pase definitivo con exit real al final. |

**PASS de estructura/contenido:** ambos HTML en UTF-8, enlace de política, cinco imágenes locales existentes, única ⭐ y una URL de baja en el cliente; interna sin token y con enmascarado/texto de conservación. **Visual en navegador y móvil real: NOT_RUN en V7.** El PNG ya existente `correo-resena-FINAL.png` no fue regenerado y no acredita esta vuelta. Los archivos ajenos/históricos del scratch quedan intactos.

### Lista PROD actualizada — preparada, NO desplegada

**Delta de Vuelta 7 desde `0459a27`: ocho archivos del sitio**, todos **OBLIGATORIOS**; ninguno OPCIONAL. La política se sube solo cuando Luis revise su diff. Mantener Schedule/webhook detenidos, enlace Google vacío y ventana de mantenimiento sin peticiones entre archivos; no hacer una actualización parcial en caliente. No hay nueva migración de esquema (continúa `AT_RG_SCHEMA=2`); mantenimiento vacía notas antiguas al operar, por lo que antes del pase se respaldan las tablas y se revisa la retención.

| Orden | Ruta local exacta | Destino relativo en PROD | Clase |
|---|---|---|---|
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php` | OBLIGATORIO |
| 1 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-datos.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-datos.php` | OBLIGATORIO |
| 2 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-bajas.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-bajas.php` | OBLIGATORIO |
| 2 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-correo.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-correo.php` | OBLIGATORIO |
| 2 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp-recordatorios.php` | `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp-recordatorios.php` | OBLIGATORIO |
| 3 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/inc/plan-trabajo/resena.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/resena.php` | OBLIGATORIO |
| 4 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` | OBLIGATORIO |
| 5 | `C:/wamp64/www/automatiza-tech/.worktrees/guias/wp-content/themes/automatiza-tech/assets/legal/privacidad.html` | `wp-content/themes/automatiza-tech/assets/legal/privacidad.html` | OBLIGATORIO · previa revisión de Luis |


**Instalación desde cero:** además del delta anterior, conservar la lista completa de Vuelta 2 y los cuatro PNG de Vuelta 6. Para evitar omisiones, lista consolidada de rutas adicionales: en todas, **ruta local exacta = `C:/wamp64/www/automatiza-tech/.worktrees/guias/` + ruta**, y **destino PROD = esa misma ruta**. Todas OBLIGATORIAS para una instalación nueva; no son archivos cambiados en V7.

| Orden antes del consumidor | Ruta local relativa = destino PROD | Clase |
|---|---|---|
| Recursos antes de correo | `wp-content/themes/automatiza-tech/assets/images/correo/web.png` | OBLIGATORIO |
| Recursos antes de correo | `wp-content/themes/automatiza-tech/assets/images/correo/correo.png` | OBLIGATORIO |
| Recursos antes de correo | `wp-content/themes/automatiza-tech/assets/images/correo/whatsapp.png` | OBLIGATORIO |
| Recursos antes de correo | `wp-content/themes/automatiza-tech/assets/images/correo/instagram.png` | OBLIGATORIO |
| Antes de cargar módulo | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php` | OBLIGATORIO |
| Antes de cargar módulo | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-datos.php` | OBLIGATORIO |
| Con adaptador existente | `wp-content/themes/automatiza-tech/inc/cierre-cliente/whatsapp.php` | OBLIGATORIO |
| Integración CRM | `wp-content/mu-plugins/crm-ai-completo.php` | OBLIGATORIO |
| Interfaz del plan | `wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js` | OBLIGATORIO |
| Al final, cuando existan dependencias | `wp-content/themes/automatiza-tech/inc/plan-trabajo/cargar.php` | OBLIGATORIO |

Dependencias ya existentes: logo real `wp-content/themes/automatiza-tech/assets/images/Logo-slogan-tagline-2x.png` y validador `inc/cierre-cliente/puras.php`, además del módulo de cierre cargado por el tema. Verificarlas antes de instalar; V7 no las modifica. Instalación nueva/v1 requiere la migración v2 con tres tablas e índices documentada en guía §8, antes de habilitar envíos; nunca reemplazarla copiando un flag.

**n8n:** `N8N/resena-google/aplicar.py` es fuente local offline; Eje debe generar/contrastar el adaptador sobre su exportación privada del bot y comprobar las credenciales referenciadas antes de cualquier aplicación futura autorizada. No es un archivo FTP. No se cambió ni activó el bot vivo ni el Schedule.

**NO SUBIR:** tests, runners, informes/guías/planes, N8N, grafo, .git, todo S (vistas HTML, PNG anteriores, diff, stub, índices y logs), exports, respaldos, fixtures ni temporales. Tampoco .env, wp-config ni credenciales. Ningún correo personal/de cliente real se añadió al repo público; solo fixtures ejemplo.cl y los contactos corporativos públicos ya aprobados.

**Rollback preparado, no ejecutado:** con envíos detenidos y enlace vacío, restaurar juntos los ocho archivos desde el respaldo previo/`0459a27`. Conservar tablas, bajas, intentos, cupos, marcas y revisiones; no restaurar una BD anterior encima de nuevos registros. El vaciado de notas/anonimización no se revierte desde una copia antigua de datos personales para volver a contactar; revisar cualquier recuperación con el responsable de privacidad. Rehabilitar requiere revisión de Eje y autorización de Luis.

### Aseo, Graphify y Git

Graphify consultado al empezar. `graphify update .` al terminar → **exit 1, WinError 5 Acceso denegado**. No se sortearon permisos y no se afirma grafo actualizado.

Checkpoint Aseo `task-close`, plataforma codex, sesión nativa `01a2032c-22f8-7a21-8a1d-3e2ddb37d7ea`, root real → **exit 1, preserved**, «Ruta ausente, ocupada o sin permiso: conservar y revisar». Respaldo nativo **at-aseo-codex / aseo_resena_v7** revisó solo metadatos de los siete artefactos propios: regulares y conservados, sin retirar nada ni ejecutar decide/finish por el owner. El respaldo recibió la confirmación final del owner de suite terminada y fixtures ausentes, y emitió cierre actualizado sin ejecutar limpieza. Confirmación del owner: no se escribieron scripts efímeros (ediciones por stdin); los únicos efímeros son motores con retiro controlado por el runner. Las fuentes, pruebas, HTML, stub, diff, índice y log son entrega permanente para Eje; el resto del scratch es histórico/ajeno y se conserva. No se declara limpieza global ni resolución del registro Aseo externo.

**Motores finales retirados:** `at-resena-motor-v2-b0676b064acd426db05716fc540b28a9`, `at-resena-motor-v2-5356b1b88b4d4372b0467546454f80d5`. Ambos runners imprimieron «Fixture retirada; servidor detenido»; comprobación posterior de existencia: ambas rutas ausentes. El log conserva los nombres completos; no quedan efímeros propios de este pase.

Sin despliegue, SSH, servicios reales, credenciales, mensajes reales, gasto, push, merge ni stash. Sin cambios en Retomar/Prospección ni en la plantilla aprobada por Meta. No se escribe en memorias personales ni en Documentos/AT-Informes fuera de las raíces autorizadas. Se mantiene el contexto en guía §8 e informe A, sin duplicarlo en archivos sincronizados de agentes.

**Git:** rama `codex/resena-google-impl`; cambios de esta vuelta acotados a 20 archivos de fuente, pruebas y documentación. El resultado del intento de commit local y su hash, si se obtiene, se informan en la entrega final. Nunca se intenta push, merge ni stash.

Siguiente responsable: **Eje**, revisión de V7 y contraste de los pendientes operativos; **Luis**, revisión de privacidad antes de cualquier subida. Esta entrega A no sustituye B ni autoriza PROD.

**Intento Git local V7:** `git add --` sobre los veinte archivos propios falló al crear `.git/worktrees/guias/index.lock` (Permission denied). No se ejecutó commit ni se retiró el lock. HEAD permanece en `0459a27`; los veinte archivos quedan sin commit. No hay push, merge ni stash.
