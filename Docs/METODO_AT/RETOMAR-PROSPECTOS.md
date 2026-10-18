# Retomar prospectos — operación y entrega local

AT-RETOMAR-001 · 18-oct-2026 · rama `codex/retomar-prospectos`.
Root de esta entrega: `C:/wamp64/www/automatiza-tech/.worktrees/codex-retomar`.

Implementación local para volver a contactar, de forma limitada, a prospectos con los que ya existe una conversación comercial. Prepara una presentación breve, correo con alternativa de texto y, cuando corresponde, WhatsApp con plantilla. Permite ver la propuesta, agendar y darse de baja. No incorpora respuestas automáticas ni interpreta respuestas entrantes.

## Estado y responsables

Implementación Tasks 0–8 preparada y revisiones A internas PASS. La carrera P2 entre renders A/B quedó corregida en `1d2ac6b32059a7688055af9c10b0f5f666cfb886` y cerrada por revisión independiente. Evidencia y commits por tarea: [registro local](../superpowers/AT-RETOMAR-001-evidencia.md). La revisión B corresponde a Eje (Claude) y está **PENDIENTE**; las revisiones internas de Codex no la sustituyen.

Verificación local: Retomar PHP **282 controles validados** (datos 28, envío 42, esquema 17, panel 56, pública 32, puras 74, render 33). La suite completa inicial dio 275/275; después de P2 se repitieron los dos archivos afectados con revisión independiente, 89/89 PASS. Suite renderer: 22 archivos, exit 0, sin fallos. Smoke HTTP público **8/8 PASS**, sin entrega real: 404, vista, noindex, no-store, confirmación GET sin baja, POST de baja y One-Click idempotente; fixture eliminado y servidor propio cerrado. Evidencia: `C:/Users/luis_/.codex/visualizations/2026/10/18/01a14fdd-54a0-7662-bf9a-95ef90f51d65/retomar-http-smoke.json`. Panel en navegador a 1280 y 390 px: PASS, sin desborde horizontal, con JSON/capturas en la evidencia del responsable. Estos resultados no acreditan PROD.

Aseo: 17 archivos propios de pruebas registrados y retirados individualmente a cuarentena recuperable; scratch sin archivos, directorios vacíos conservados. Checkpoint `task-close`: `review_required`, `complete:false` por baseline inicial incompleta, con cero tareas/candidatos/no registrados pendientes. Respaldo Aseo consultado; no se declara limpio todo el entorno.

Graphify actualizado localmente, exit 0: 16.086 nodos, 28.801 relaciones y 1.451 comunidades. Conservó 32 nodos de tres archivos fuera del corpus y avisó etiquetas de comunidades pendientes de refresco; no se ejecutó etiquetado con LLM ni se purgó el respaldo curado. Esas advertencias no son resultados de QA del módulo.

Regresiones ejecutadas: Plan **28/36 archivos PASS y 8 con fallos**; Entregables **8/11 archivos PASS y 3 con fallos**. No se declara regresión completa PASS. El padre reprodujo los mismos conteos de los 11 archivos fallidos con el tema base `9648a0459257fbb457a603bcd8895dff85d5527a` cargado en memoria y `at_rt_enviar` ausente en cada proceso: fallos históricos de esa base, incluidos tres ya conocidos y ocho adicionales (cinco Plan y tres Entregables). Limitación: helpers de pruebas de los hijos usaban la junction actual; la comprobación de baseline la hizo el padre. Los nombres y conteos por archivo se conservan en el registro local enlazado.

Luis autoriza el despliegue y el piloto; Eje revisa y coordina la configuración del destino. Importación y ejecución real de n8n, SMTP, aprobación Meta, entrega real, PROD y piloto: **NOT_RUN**. Esta guía no acredita ninguno de esos pasos ni un costo o una entrega garantizados.

## Alta y revisión por Luis

1. Abrir **CRM → Retomar prospectos** con un usuario administrador. Buscar y seleccionar una ficha de tipo prospecto o marcar su creación si aún no existe; no se convierten fichas de clientes. Correo de ficha y propuesta vinculada deben coincidir. Puede vincularse una propuesta existente o agregarse su URL pública HTTPS.
2. Completar nombre, negocio, correo y teléfono con código de país; elegir una imagen existente de la biblioteca, hasta dos demos públicas y los tres textos: «En qué quedamos», «Qué proponemos» y «Por dónde podemos partir». El proyecto toma el nombre del negocio. No pegar enlaces internos ni datos ajenos al contacto.
3. Configurar máximo de 1 a 4 envíos e intervalo mínimo de 7 días (predeterminado). El máximo almacenado para el intervalo es 255 días. Se cuentan días de calendario y se difiere al siguiente lunes a viernes si corresponde; los feriados están fuera de alcance.
4. Mantener **Canales = Correo** hasta que la plantilla Marketing `retomar_propuesta`, idioma `es`, tenga aprobación de Meta. La casilla **WhatsApp autorizado** registra consentimiento real de esa persona (te escribió o conversó contigo por WhatsApp); no acredita aprobación de Meta. En una persona nueva sin ese consentimiento debe quedar desmarcada. Aunque exista consentimiento registrado, conservar el canal solo Correo hasta superar el gate Meta.
5. Pulsar **Guardar y pedir presentación**. El guardado invalida la versión anterior y solicita un render nuevo. Si falta el flujo o falla la solicitud, no queda lista y no puede activarse. **Vista previa** vuelve a pedir el render; recargar para ver el resultado y abrir la presentación cuando figure lista. Cada solicitud tiene una generación y un directorio propios; una respuesta anterior se rechaza con `409`, incluso después de guardar contenido idéntico.
6. Revisar texto, imagen, demos, enlaces, destinatario y datos del seguimiento. **Enviar prueba al equipo** usa el correo de avisos del equipo, no suma contactos realizados y no envía WhatsApp. Un resultado local de `wp_mail` no prueba recepción real en la bandeja.
7. Tras aprobación de la revisión B, configuración comprobada y autorización de Luis para el piloto, Luis pulsa **Activar** en los seguimientos aprobados. Tener una presentación lista o guardar datos no activa el seguimiento. No activar los flujos ni prospectos durante esta preparación local.

Límites del formulario: nombre y negocio, 120 caracteres cada uno; correo, 190; teléfono, 50; cada uno de los tres textos, 1–600 caracteres y hasta 6 líneas; dos demos como máximo; URLs de demo/propuesta/imagen, hasta 500 caracteres y HTTPS público. Los contenidos que exceden los límites se rechazan; no se recortan silenciosamente.

## Reloj, paradas y bajas

La ventana de envío es lunes a viernes, de 10:00 a antes de 13:00, zona `America/Santiago`. El flujo preparado consulta una vez a las 10:15 (`0 15 10 * * 1-5`). El horario se interpreta en Chile con esa zona, no con un desplazamiento UTC fijo. No hay calendario de feriados.

El correo es el canal base. WhatsApp se considera en el primer y último contacto según las reglas del seguimiento, solo con canal habilitado, consentimiento y gates previos cumplidos. El reloj aplica claim y control diario para evitar doble correo; eso no autoriza reejecutar manualmente ejecuciones n8n fallidas, porque podrían repetir WhatsApp.

Se detiene por baja, propuesta rechazada o aceptada, conversión a cliente, reunión agendada o máximo de envíos alcanzado. **Pausar** detiene el siguiente contacto. **Respondió** se marca manualmente cuando el equipo recibe respuesta y también detiene el seguimiento; no hay detección ni contestación automática. Un fallo del correo pausa; no hay reintentos automáticos. Un render pendiente difiere la fecha hábil sin sumar un contacto. Los fallos de WhatsApp, incluido `131049`, se registran sin reintento automático. Si falla el callback se concilia el registro con Eje, sin reenviar el mensaje.

El enlace de baja con token muestra confirmación mediante GET y **no da de baja al abrirlo**. El POST confirmado y el POST One-Click del correo aplican la baja de forma idempotente. La supresión es global dentro de Retomar por coincidencia de **correo O teléfono normalizados**; no depende solo de la ficha actual ni modifica otros productos. Luis también puede usar **Dar de baja** en el panel. **Revertir baja** exige motivo de 1–255 caracteres y conserva el seguimiento detenido: nunca lo reactiva por sí solo. Una activación posterior requiere decisión explícita y vuelve a comprobar las demás paradas.

## Superficies y configuración de destino

Panel CRM privado: permiso `manage_options` y nonce para mutaciones. Presentación, agenda y baja: acceso mediante token opaco, sin indexación ni caché de contenido personalizado; no publicar enlaces con tokens en documentación ni buscadores. El token se trata como enlace de acceso y no se comparte fuera del destinatario/equipo autorizado. Endpoints de render/reloj/resultado: autenticados. Renderer, generadores y exports son internos. SEO y búsqueda IA: **NOT_APPLICABLE**, no son superficies comerciales para indexación.

Luis configura `AT_N8N_RETOMAR_RENDER` con la URL del webhook de render en el destino. Es una URL de configuración no secreta; su ausencia deja la presentación pendiente. El flujo utiliza la credencial existente `AT REST Secret (header)` con cabecera `X-AT-Secret`; **no pegar `AT_REST_SECRET`, claves ni sus valores** en esta guía, exports, comandos, ZIP o repositorio. Eje vincula las referencias a credenciales existentes en n8n y valida el routing/version Graph y expresiones en ese entorno.

## Entrega FTP: exactamente 13 archivos

> **18-oct, Eje:** los archivos salen de la rama `claude/retomar-prospectos-final` (worktree `.worktrees/retomar-eje`), que corrige `inc/retomar/panel.php` (la global `$accion`) y deja `inc/admin-proposals.php` con un solo cambio. El ZIP del renderer de Codex sigue valiendo: la corrección no toca `renderer/`.

Preparación local; **no desplegada**. Tras revisión B y autorización de Luis, respaldar los archivos de destino que se van a reemplazar y la BD de forma protegida. Subir los grupos en orden; dentro de un mismo grupo el orden no cambia la carga. Los módulos van antes del archivo que los habilita. La migración se ejecuta por separado después del grupo 4 y antes del grupo 5.

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

Migración idempotente prevista desde el root WordPress de PROD, con WP-CLI y respaldo confirmado (comando documentado, **no ejecutado en PROD**):

```sh
wp eval 'at_rt_migrar_esquema();'
```

No subir al webroot por FTP: `tests/`, `Docs/`, `N8N/`, fuentes/ZIP del renderer, `.git/`, configuraciones con credenciales, `.env`, `wp-config.php` local, `node_modules/`, scratch, respaldos, capturas o evidencia. Esta tabla no incluye configuración privada ni secretos.

## Renderer y n8n: entregas separadas del FTP

ZIP de fuentes del renderer generado y verificado por el responsable principal: `C:/Users/luis_/.codex/visualizations/2026/10/18/01a14fdd-54a0-7662-bf9a-95ef90f51d65/AT-RETOMAR-001-renderer.zip`. Contiene 15 archivos, 56.350 bytes; comprobación `testzip` PASS; SHA-256 `595decd2c663edc9486428656cc92cc98c4f0c85f8460d6c01651915b8a2065d`. Layout en la raíz: `Dockerfile`, `.dockerignore`, `package.json`, `src/` (sin prefijo `renderer/`). No existe `package-lock.json` en la base usada. La construcción Docker real está **NOT_RUN**. Se construye una imagen en la infraestructura del renderer siguiendo su procedimiento, no se extrae en el webroot WordPress.

Comando reproducible desde el worktree, usando solo fuentes versionadas necesarias para construir la imagen (sin dependencias instaladas, `.env`, credenciales ni archivos públicos generados):

```powershell
git archive --format=zip --output="C:/Users/luis_/.codex/visualizations/2026/10/18/01a14fdd-54a0-7662-bf9a-95ef90f51d65/AT-RETOMAR-001-renderer.zip" HEAD:renderer Dockerfile .dockerignore package.json src
```

Fuente del ZIP entregado: HEAD `1911298d9c63c687cdcb989cf427fc835a09342c`; último cambio del renderer `46c38214dab357a4acea69dbabd0c338a4e02b35`. El comando representa el HEAD que se use al ejecutarlo; para reproducir ese contenido, usar `1911298d9c63c687cdcb989cf427fc835a09342c:renderer` en lugar de `HEAD:renderer`. Registrar SHA y hash del ZIP si se vuelve a empaquetar; el hash del contenedor ZIP puede variar con sus metadatos aunque conserve el contenido. No instala dependencias ni despliega. Esta función reutiliza medios existentes y no solicita fotos nuevas.

Se entregan **dos** flujos inactivos conforme a Tasks 4/7: [render](../../N8N/retomar/retomar-render.json) y [reloj + WhatsApp](../../N8N/retomar/retomar-reloj.json), con sus generadores [build_retomar_render.py](../../N8N/retomar/build_retomar_render.py) y [build_retomar_reloj.py](../../N8N/retomar/build_retomar_reloj.py). La especificación menciona tres flujos separados: la diferencia es de empaque, reloj y WhatsApp comparten uno; no cambia reglas ni permisos. Ver [preparación n8n](../../N8N/retomar/README.md) y [plantilla Marketing](../../N8N/retomar/PLANTILLA-retomar_propuesta.md). Importar, vincular credenciales y comprobar runtime corresponde al destino con Eje; mantener ambos inactivos hasta autorización. La aprobación de la plantilla Meta es un gate separado del consentimiento individual.

Contrato de render versionado: WordPress envía `{id,generacion,render}`; n8n devuelve `{ok,generacion,view_url,pdf_url,nota}` también en fallos. UID interno: token, generación hexadecimal de 16 caracteres y sufijo `-retomar` (57 caracteres). El enlace entregado al prospecto sigue siendo el wrapper con token estable. No mezclar este PHP con un flujo anterior que no devuelva `generacion`.

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

Si se autoriza rollback de código, restaurar los archivos respaldados y la imagen anterior del renderer, reteniendo los datos de Retomar. Antes de restaurar una versión sin soporte de baja, resolver con Eje cómo mantener el endpoint de opt-out compatible y disponible; no retirar `retomar.php` dejando enlaces ya enviados inoperantes. No restaurar una BD antigua sobre bajas o envíos recientes. Conciliar fallos y callbacks sin reenviar automáticamente. Estas son instrucciones de contingencia; no se ejecutó rollback ni despliegue.
