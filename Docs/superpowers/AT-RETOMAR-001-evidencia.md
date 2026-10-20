# AT-RETOMAR-001 — Evidencia local

Fecha: 2026-10-18. Ejecutor A: Forja/Lienzo-Codex. Revisor B: Eje (Claude), pendiente.
Base: `9648a0459257fbb457a603bcd8895dff85d5527a`, rama `codex/retomar-prospectos`.
Worktree: `C:/wamp64/www/automatiza-tech/.worktrees/codex-retomar`.

Clasificación: C2, riesgo alto, incertidumbre media, reversibilidad moderada.
Perfil: strong_reasoner; plataforma Codex desktop; modelo de esta sesión no expuesto de forma verificable.
Autoridad: encargo de Luis AT-RETOMAR-001; implementación local y commits autorizados, revisión B de Eje.
Sin permiso para despliegue, merge, PR ni activación de n8n/Meta.

## Entorno

- Preflight PHP 8.4.15 ejecutado con `HTTP_HOST=localhost:8093`, sin SHORTINIT: `WP_VERSION=7.1.2`, `DB_SELECT=1`, `SITE_WRITABLE=yes`.
- El sandbox devolvió error de conexión a BD; el mismo preflight con permiso de acceso al MySQL local pasó.
- Junctions del sitio `wp-local-plan`: tema y mu-plugins reapuntados del worktree `crm-ficha-orden` a `codex-retomar`, autorizado por el encargo. No se borraron sus destinos.
- Graphify query en sandbox: salida vacía y exit 1. Repetido con permiso local: exit 0, 49 nodos; respuesta amplia de documentación, complementada con lectura acotada de patrones del plan.
- Aseo principal: `f0dc4413be9ea708bbf17999`, sesión `01a14fdd-54a0-7662-bf9a-95ef90f51d65`. Sin temporales iniciales.

## Superficies y visibilidad

- Panel CRM: privado, requiere `manage_options` y nonce para mutaciones.
- Presentación/agenda/baja: acceso por token opaco; contenido personalizado excluido de indexación, sin sitemap ni enlaces de descubrimiento.
- Endpoints de reloj/render/resultado: autenticación con secreto existente, sin valores en fuentes o evidencia.
- Indexación y citas IA: NOT_APPLICABLE; no son superficies comerciales destinadas a buscadores.

## Ejecución

Las Tasks 0–9 se registrarán aquí con resultados reales. No se certifica PROD ni dispositivos reales.

### Task 0 — esquema y carga

- Commit `d7679bf517a3ab98ef0a4836e60f17452a04b21f`.
- Ejecutor interno `/root/task0`: rojo real (exit 1, función `at_rt_migrar_esquema` ausente); verde real (exit 0, 17 comprobaciones, TODO OK).
- Comando: con `AT_WP_LOAD` al sitio de pruebas, PHP 8.4.15 `tests/retomar/esquema-wp-test.php`, acceso autorizado al MySQL local.
- Incluye columnas, defaults, campos NULL, índices de bajas, doble migración y opción string `'1'`.
- Sin temporales propios; checkpoint del ejecutor reportado sin residuos. Revisión B: pendiente de Eje.
- Revisión A interna `/root/review0`: cumplimiento estático PASS y calidad PASS sobre `9648a04..d7679bf`; no ejecutó BD, atribuye rojo/verde al ejecutor.

### Task 1 — funciones puras

- Commit `14b3a35`.
- Ejecutor `/root/task1`: rojo real, 15 interfaces ausentes; verde PHP 8.4.15, 74 pruebas y 0 fallas. Lint y diff-check PASS.
- Incluye ventanas/intervalos/canales/paradas/tokens/asuntos/correo HTML+texto, URLs HTTPS y bloqueo XSS/CRLF/D8 recursivo.
- Sin temporales generados. `init` Aseo rechazó el identificador canónico del hijo (no se expone UUID nativo); checkpoint devolvió `preserved` por ruta/identidad, pendiente de comprobación central, sin retiro.
- Revisión A `/root/review1`: especificación y calidad PASS; reprodujo de forma independiente las 74 pruebas (exit 0).

### Task 2 — datos y bajas

- Commit `a234d9d90d6f842c2298e0d4ebd6ed1f5fef22ae`.
- Ejecutor `/root/task2`: rojo real por función ausente; verde PHP/WordPress, 28 controles, exit 0; lint y diff-check PASS.
- Fixtures SQL propios eliminados por shutdown; sin temporales de archivos.
- Contrato de eventos: un registro `{tipo:'envio',n,fecha,at,correo:{estado},whatsapp:null|{estado,...}}` por contacto. Diferidos/detenidos/errores no aumentan envíos realizados; resultado WhatsApp actualiza el mismo contacto.
- Cobertura: normalización, baja por correo O teléfono, reversión con motivo sin reactivación, whitelist, token/JSON y contexto temporal de reuniones/propuestas.
- Revisión A `/root/review2`: especificación y calidad PASS, verde independiente de 28 controles (exit 0) sobre `14b3a35..a234d9d`.

### Task 3 — renderer

- Commit `7ee5ccd71dd574b7f171b2fd21c9cfb7fc340e31`.
- Ejecutor `/root/task3`: rojo real, dos tests con MODULE_NOT_FOUND; verde `node --test test/template-retomar*.test.js`, 5/5 PASS.
- Chromium real 1920×1080 en pantalla e impresión, ejemplo corto y máximo aceptado; red abortada y `page.setContent`, sin archivos HTML temporales.
- Dispatch retomar no llama manifiestos, persistencia ni generación de imágenes, incluso con `image_briefs`; respuesta 200 válida y 400 para UID inválido.
- Límites técnicos explícitos para seis láminas: company/client 120 caracteres, proyecto 180, tres textos 600 y seis líneas cada uno; hasta seis demos. Se rechaza con error, sin truncamiento silencioso. El formulario limitará demos a dos según plan.
- Captura permanente de inspección: `C:/Users/luis_/.codex/visualizations/2026/10/18/01a14fdd-54a0-7662-bf9a-95ef90f51d65/retomar-demo.png`. Vista por el padre: jerarquía y CTA alineados. Imágenes/logo sin cargar por bloqueo deliberado de red; esta captura no certifica medios reales.
- Revisión A `/root/review3`: especificación y calidad PASS, 5/5 tests independientes. Contraste de geometría sin exclusión genérica por altura: cero desbordes en corto/máximo, pantalla e impresión; solo fondos/watermark decorativos excluidos.
- Ajuste proporcional de privacidad `d562a65ee47d22fcb1506452ab6e588cf483bb07`: cabeceras noindex/nofollow y no-referrer también en HTML/PDF servidos directamente, solo UID Retomar. TDD rojo de cabecera ausente → verde 6/6 tests; otras presentaciones sin cambios.
- Revisión posterior detectó P2 en rutas codificadas que omitían cabeceras; corregido en `46c38214dab357a4acea69dbabd0c338a4e02b35` con decodificación y normalización coherentes con static, variantes Windows y fixtures marcados `[PRUEBA]`. Rojo reproducido → verde 6/6; revisión de corrección pendiente.
- Revisión de corrección `/root/review3`: PASS en ambas fases sobre `d562a65..46c38214`, seis pruebas reproducidas, P2 resuelto.

### Task 4 — WordPress y flujo de render

- Commit `6dcf2f7bcd28515e0f49e136c177cc39958d62ae`.
- Ejecutor `/root/task4`: rojo real por `at_rt_armar_render` ausente; verde WordPress de 27 controles; lint y diff-check PASS.
- Callback autenticado con URL y UID del mismo seguimiento, límites explícitos y sin fotos nuevas; ausencia de constante no altera render.
- Flujo inactivo de cuatro nodos: webhook, renderer, normalizador del resultado y callback. El normalizador adicional permite devolver errores al panel.
- Revisión A `/root/review4`: especificación y calidad PASS; 27 controles reproducidos con permiso MySQL. Builder/JSON idénticos y normalizador probado en éxito, HTTP 400 y error de red. Un intento sandbox devolvió Database Error con exit 0 y cero checks: no se cuenta como prueba pasada.
- Checkpoints Aseo con UUID nativo del hijo localizado en `CODEX_THREAD_ID`: cero residuos/candidatos/tareas pendientes, pero `review_required` y `complete=false` por baseline faltante. Revisión manual central pendiente; no se declara entorno completo limpio.

### Task 5 — página, agenda y baja

- Commit `f666180dfc6562791c354e74e67f9f3194e283f2`.
- Ejecutor `/root/task5`: rojo real por función ausente; verde definitivo WordPress, 32 controles; lint de cuatro PHP PASS.
- Baja GET conserva estado activo; POST con nonce o One-Click suprime de forma idempotente. Token ajeno 404; noindex/no-cache.
- Agenda con token diario, campo trampa, límite de intentos, disponibilidad hasta 21:00, máximo dos reuniones activas y candado compartido. Segunda conexión con candado ocupado devuelve 503 sin insertar.
- Aviso de seguimiento distingue ficha prospecto de cliente convertido. Calendar/Meet y correo real NOT_RUN; HTTP smoke pendiente del padre.
- Sin temporales propios; checkpoint Aseo con UUID nativo y cero pendientes/residuos, baseline faltante ya registrado.
- Revisión A `/root/review5`: cumplimiento y calidad PASS; 32/32 tests independientes (exit 0) y lint. Cambio posterior de carga de Task 6 excluido de su dictamen.
- Smoke HTTP del padre: 8/8 PASS en localhost:8093 (404, vista, noindex, no-store, confirmación GET, GET sin baja, POST baja, One-Click idempotente). Fixture SQL eliminado y servidor propio cerrado; ninguna entrega real. Evidencia `C:/Users/luis_/.codex/visualizations/2026/10/18/01a14fdd-54a0-7662-bf9a-95ef90f51d65/retomar-http-smoke.json`.
- `retomar.php` copiado exclusivamente al sitio local de pruebas para ese smoke, sin sobrescribir archivos distintos. Se conserva como entrada de pruebas para Eje, fuera de entregables FTP.

### Task 6 — envío y reloj

- Commit `51e2790daca401458104c4ce3f727a6708931547`.
- Ejecutor `/root/task6`: rojo real por función de envío ausente; verde definitivo 38 controles WordPress. Regresión datos 28 y timeline 42; cuatro PHP lint y diff-check PASS.
- Hook mínimo adicional en datos tras COMMIT de una baja nueva para registrar su timeline; agenda ya tenía su evento. Desvío de archivos mínimo para cubrir trazabilidad exigida por el diseño.
- Reloj Santiago, claim 10 minutos, idempotencia diaria, diferimiento hábil, HTML/AltBody/One-Click, prueba interna que no cuenta, WA primero/último y CAS de callback reproducido con escritura concurrente. Fallo de correo pausa sin reintentar automáticamente.
- Sin SMTP/Meta/n8n reales ni temporales propios. Graphify update intermedio iniciado por el ejecutor, AST 1644/1644, resultado pendiente; actualización final central después de Task 8.
- Graphify intermedio terminó exit 0: 16.051 nodos, 28.705 relaciones, 1.443 comunidades. Avisó 32 nodos conservados de tres archivos fuera del corpus y etiquetas parcialmente desactualizadas; respaldo curado automático `graphify-out/2026-10-18/` conservado, no FTP. Padre verificó grafo modificado solo en worktree; espejo principal intacto.
- Revisión A `/root/review6`: secuencial PASS, reprodujo 38+28+42 controles y lint; calidad CHANGES_REQUIRED por P1 y P2. P1: baja dentro de `pre_wp_mail` terminaba sobrescrita como activo y devolvía WA; P2: interleaving entre lectura y BEGIN duplicaba historial de baja. Fixtures aislados y eliminados; correcciones en curso, Task 8 retenida.
- Corrección `7e18ccd9cb5f8474a956e58e5130833583dd7831`: finalización transaccional con fila bloqueada y condición de claim/estado; conserva baja/agendo/pausado durante SMTP, registra aceptación sin nuevo WA. Baja relee bajo FOR UPDATE y deduplica historial según transición real.
- Rojo de las carreras → verde 42/42 envío y 28/28 datos. Revisión A independiente `/root/review6` cierra P1/P2 y da PASS ambas fases, confirma también timeline 42/42, lint y diff-check. Task 8 habilitada.

### Task 7 — reloj y WhatsApp inactivos

- Commit `7ce396e4a5bed1b105b55107649e05d6eac44a34`.
- Ejecutor `/root/task7`: rojo por script ausente; verde 45 checks locales Node más generador Python y `json.load` PASS con permiso local.
- Un horario L-V 10:15 America/Santiago, sin activación/reintentos, plantilla de dos variables y dos botones; conserva id/n en éxito, error 131049 y fallo de red. Credenciales referenciadas por nombre, sin valores.
- Desvío documental del plan/spec: entrega dos flujos según Tasks 4/7, combinando reloj+WhatsApp en uno; la spec menciona tres flujos. Reglas de negocio y permisos de activación intactos.
- Sin n8n/Meta/SMTP reales ni temporales. Aseo: cero residuos/pendientes detectados, baseline incompleta ya registrada.
- Revisión A `/root/review7`: especificación y calidad PASS, 61 comprobaciones Node independientes (incluye varios items, orden distinto, 131049, red y mensajes malformados) más runpy Python sin escribir; importación real en n8n sigue NOT_RUN.

### Task 8 — panel privado

- Commit `1911298d9c63c687cdcb989cf427fc835a09342c`: ocho acciones con permisos y nonce, campos acotados, dos demos, biblioteca de medios, historial y bajas. TDD rojo/verde 55 controles; revisión A `/root/review8` PASS especificación y calidad.
- Padre: formulario real con Playwright local a 1280 y 390 px: visible, dos demos, intervalo mínimo 7, máximo 4 envíos, botón de medios y sin desbordamiento. Capturas `retomar-panel-1280.png` / `retomar-panel-390.png` y JSON de smoke en carpeta de artefactos. Captura móvil inspeccionada; sin datos de clientes. Recursos externos bloqueados, no certifica carga de medios externos ni dispositivo físico.

### Regresión final local y contraste de base

- PHP Retomar previo a corrección de concurrencia final: 275/275 controles (datos 28, envío 42, esquema 17, panel 55, pública 32, puras 74, render 27), siete archivos PASS. Reporte `AT-RETOMAR-001-php-regresiones.json`.
- Renderer completo: 22 archivos `.test.js`, `node --test --test-concurrency=2` enumerando archivos, exit 0 y cero subtests fallidos. La primera invocación con directorio no descubrió pruebas y se descartó; el reporte válido es `AT-RETOMAR-001-renderer-regresiones.json`.
- Plan: 28/36 archivos PASS; fallan ajustes, avance-formulario, avance-panel, panel-acciones, panel-envio, datos-contexto, firma-contrato y panel-render. Entregables: 8/11 PASS; fallan panel, versiones y vista. No se presenta la regresión completa como PASS.
- Contraste secuencial de los 11 fallos con tema base `9648a04` mediante filtros WordPress en memoria, sin editar/reapuntar el worktree de Claude: en cada proceso se verificó directorio base y ausencia de `at_rt_enviar`. Los 11 reprodujeron exactamente los mismos conteos de aciertos/fallas; firma-contrato reproduce exit 255. Los procesos hijos auxiliares conservan el bootstrap compartido, limitación del contraste. No se modificaron pruebas ni código legacy para forzar aprobación. Reporte `AT-RETOMAR-001-baseline-regresiones.json`.
- Esto amplía las tres fallas históricas advertidas: cinco archivos adicionales de Plan y tres de Entregables fallan también sobre la base aprobada en este entorno. Requieren diagnóstico legacy separado; no son un PASS ni se atribuyen al nuevo módulo.
- Smoke HTTP público con fixture ficticio: 8/8 PASS (404 inválido, vista, noindex/no-store, GET de baja sin mutar, POST de baja y One-Click idempotente). Fixture y servidor propios retirados; `retomar.php` del sitio de pruebas se conserva para Eje.
- Todos los JSON/capturas/ZIP citados están en `C:/Users/luis_/.codex/visualizations/2026/10/18/01a14fdd-54a0-7662-bf9a-95ef90f51d65/`; son evidencia permanente, no FTP.
- Revisión de integración A `/root/review9` detectó P2 adicional: callback de render sin generación y archivos compartidos entre solicitudes A/B. Corregido en `1d2ac6b`: generación/UID por solicitud, huella de payload, bloqueo transaccional en callback y reserva, CAS del error de transporte e invalidación completa al guardar incluso contenido idéntico. Generador/JSON propagan generación también al fallar. Renderer sin cambios.
- TDD de corrección: seis fallos → 33/33 render; un fallo → 56/56 panel. Revisión A independiente `/root/review9` reprodujo ambos verdes (89 controles) y 10 asserts Node de callback éxito/400/red/inactivo; P2 CERRADO, PASS sin nuevos hallazgos. Total actual de Retomar 282 controles validados: los otros cinco archivos conservaron sus resultados de la regresión completa y se repitieron los dos afectados tras corregir.

### Task 9 — entrega y límites

- Guía `Docs/METODO_AT/RETOMAR-PROSPECTOS.md`, índice y punteros en CLAUDE/AGENTS/Docs-AGENTS/Copilot/OpenCode. Tabla FTP exacta de 13 OBLIGATORIOS, orden de carga, migración separada, exclusiones, gates, piloto y rollback que preserva bajas. No se modificó `functions.php`.
- ZIP renderer permanente de 15 archivos, 56.350 bytes, testzip PASS, SHA-256 `595decd2c663edc9486428656cc92cc98c4f0c85f8460d6c01651915b8a2065d`. Fuente renderer sin cambios desde `46c38214`; ZIP archivado desde HEAD `1911298`. Docker build NOT_RUN.
- Resumen consolidado `AT-RETOMAR-001-final-verificacion.json` conserva método y límites. Revisión B Eje, n8n real, SMTP/Meta, Docker, dispositivo físico, piloto y PROD pendientes/NOT_RUN. Sin push, PR, merge, SSH, credenciales ni activaciones.
- Aseo respaldo nativo `/root/aseo_final` auditó sólo metadatos y consultó al responsable. El padre registró los 17 archivos propios de pruebas, confirmó procesos/consumidores finalizados, decidió por archivo y ejecutó finish/retire a cuarentena recuperable con hashes. Scratch `f0dc4413be9ea708bbf17999`: cero archivos tras retiro; directorios vacíos conservados. Fuentes, evidencia, ZIP, testentry y respaldo de Graphify conservados.
- Checkpoint task-close con sesión nativa `01a14fdd-54a0-7662-bf9a-95ef90f51d65`: `review_required`, `complete:false` por baseline inicial incompleta, `pendingTasks:[]`, candidatos 0 y no registrados 0. Informe canónico `C:/Users/luis_/AppData/Local/AutomatizaTech/Aseo/sessions/0a76bfeefee4ee5cd7d5c540.json`. No se declara limpio el entorno completo.
- Graphify final `graphify update .`, exit 0: extracción AST 492 archivos no cacheados, 16.086 nodos, 28.801 relaciones y 1.451 comunidades. Avisó 95 fuentes sin nodos, 32 nodos retenidos de tres archivos fuera del corpus y etiquetas comunitarias sin refrescar; se preserva respaldo curado `graphify-out/2026-10-18/`, excluido de FTP. Sin llamar LLM ni purgar nodos. Se actualizan seis artefactos del grafo en el worktree propio.
- Estado de entrega: revisión A completa con P1/P2 corregidos y PASS; B queda a Eje en Rondas. Worktree y rama propios se conservan para revisión, sin publicar. Plataforma real Codex, familia GPT-6; ID exacto del modelo principal no expuesto por este runtime. Ejecución hija configurada en esfuerzo medio conforme al encargo; cargos A de esta entrega Forja/Lienzo-Codex, B Eje-Claude pendiente.
