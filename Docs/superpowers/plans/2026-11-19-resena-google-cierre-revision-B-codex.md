# Revisión B del plan «Pedir reseña en Google» — Atlas-Codex (19-nov-2026)

Guardada sin cambios por Eje (Claude) desde la salida de `codex exec` (carpeta 5449c1f0f26f1f98, modelo pedido gpt-6.1-sol con esfuerzo xhigh, shell apagado, búsqueda web en vivo). Revisó el plan del commit `4033a24`. sha256 del texto original: `0cc36d00e2873b3cefbd3dbe1cdacc93a707cfb4209dca837835666ec9dd2159`.

---

**APROBADO CON CAMBIOS.** La idea y el reparto WordPress/n8n son adecuados. El plan necesita corregir la prevención de duplicados, las bajas, la integración de estados de WhatsApp y los envíos desde el panel antes de construir esas partes.

**Revisor:** Atlas-Codex, función de arquitectura en este hilo. **Plataforma:** Codex. **Modelo:** GPT-6; la variante exacta y el esfuerzo no están expuestos en esta sesión.  
**Ruta:** C2, revisión documental, sin delegación. Intenté leer las skills de routing y arquitectura, pero el entorno impidió la lectura; su aplicación queda sin verificar.

**Base revisada:** exclusivamente el material pegado, atribuido al plan `4033a24`, más fuentes oficiales consultadas el 19-nov-2026. No cotejé el commit con Git, no inspeccioné el repositorio, no modifiqué archivos y no ejecuté pruebas. Los escenarios siguientes son análisis del plan, no fallos reproducidos de una implementación.

**D1 — APRUEBO: marcar manualmente el término.**

La evidencia entregada respalda esta decisión: `at_pt_transiciones()` termina en `enviado`, y la guía describe cierres de hitos. Ninguno identifica inequívocamente el término del proyecto.

Guardar fecha, autor y momento de la decisión permite distinguir el término efectivo de cuándo se registró. No propongo agregar un nuevo estado al plan firmado. La posibilidad de deshacer deberá respetar los estados de envío que detallo en D4: un envío con resultado desconocido tampoco puede tratarse como “todavía no salió”.

**D2 — OBJETO: la exclusión por plan no cubre una baja del cliente.**

- **Qué está mal:** `avance.resena.excluido` pertenece a un plan. El plan posterga el respeto de una baja general hasta que exista otro campo.
- **Evidencia y escenario:** un cliente tiene proyectos A y B; solicita no recibir más invitaciones y se excluye A; B sigue elegible y recibe otra invitación.
- **Impacto:** incumplimiento del requisito pegado de no contactar a quien lo haya solicitado. WhatsApp exige respetar las solicitudes de suspensión, incluso recibidas fuera de WhatsApp. Si corresponde aplicar el artículo 28 B, tampoco basta con ofrecer la baja: deben suspenderse los nuevos envíos. [Política de WhatsApp](https://business.whatsapp.com/policy), [artículo 28 B](https://www.bcn.cl/leychile/navegar?idNorma=1160403).
- **Alternativa:** mantener la exclusión particular del proyecto y agregar una supresión por destinatario, con alcance definido —estas invitaciones, canal determinado o toda comunicación, según lo solicitado—. Todos los envíos de reseña deben consultarla. Puede ser un registro acotado; no exige rediseñar todo el CRM.

También falta verificar el permiso para escribir por WhatsApp. Tener teléfono, ser cliente o tener una plantilla aprobada no demuestra ese permiso. La política exige disponer del número y de autorización para recibir comunicaciones posteriores. No doy por inexistentes los permisos actuales: **su existencia y alcance no están acreditados en lo pegado**. [Política de WhatsApp](https://business.whatsapp.com/policy).

La exclusión tampoco debe utilizarse para escoger solamente clientes satisfechos; fundamento esto en D7.

**D3 — OBJETO: la elegibilidad está incompleta.**

**Apruebo** los siete días calendario en `America/Santiago` y el corte histórico, sujeto a la decisión de Luis. Objeciones:

- **Qué está mal:** las cinco condiciones no comprueban que el destinatario siga siendo cliente ni que tenga un canal válido y permitido.
- **Evidencia:** `crm_cliente_id` admite `NULL`. El ejemplo `at_cc_enviar_bienvenida()` sí comprueba `tipo === 'cliente'` y valida el correo; el plan no incorpora comprobaciones equivalentes.
- **Escenario:** queda un plan asociado a un registro reclasificado como lead, con un correo no vacío pero inválido; cumple las cinco condiciones descritas y se intenta procesar.
- **Impacto:** se puede contactar a alguien fuera del alcance o registrar intentos destinados al fracaso.
- **Alternativa:** exigir cliente existente de tipo `cliente`, fecha válida y al menos un canal utilizable. Para cada canal: formato válido, sin baja aplicable y permiso cuando corresponda.

Estas condiciones deben comprobarse **otra vez al reclamar**, porque pueden cambiar después de consultar `pendientes`. “Enviar ahora” podrá exceptuar el plazo y el corte histórico, pero no la baja, la identidad del cliente ni la prevención de duplicados.

Hay una ambigüedad que debe resolver Luis: el predicado usa **fecha efectiva de término**, mientras la pregunta final habla de proyectos **marcados terminados desde el 19-nov**. No son equivalentes: registrar hoy un término del 10-nov produce resultados distintos.

**D4 — OBJETO: el flujo todavía no sostiene sus garantías.**

Apruebo que WordPress decida la elegibilidad y envíe correo, mientras n8n conserva la credencial y envía WhatsApp. Las objeciones afectan al contrato entre ambos.

**D4.a. Un reclamo vencido puede duplicar el correo.**

- **Evidencia:** `/reclamar` manda el correo, pero `enviado_at` recién se escribe en `/resultado`; el reclamo vuelve a admitirse después de 30 minutos.
- **Escenario:** WordPress envía el correo → se pierde la respuesta HTTP o cae n8n → no llega `/resultado` → vence el reclamo → una ejecución posterior vuelve a enviar.
- **Impacto:** dos invitaciones, pese a que cada reclamo individual fue atómico.
- **Alternativa:** guardar un identificador de intento y estados persistentes por canal. Antes de llamar al proveedor, registrar el intento; después, su resultado. Si se pierde la respuesta tras una operación que pudo enviar, dejarlo **desconocido y bloqueado para revisión**, sin reenviarlo por vencimiento del reloj. El resultado debe corresponder al intento vigente y aceptar repeticiones sin repetir efectos.

Con las interfaces descritas no corresponde prometer entrega “exactamente una vez” ante cualquier caída. Sí se puede priorizar no duplicar y hacer visibles los casos inciertos.

**D4.b. Otro guardado del JSON puede borrar las marcas.**

- **Evidencia:** `at_av_guardar()` reemplaza `avance` completo sin comparación. `at_av_guardar_si_igual()` protege únicamente las escrituras que lo utilizan.
- **Escenario:** un proceso lee el avance → otro registra el envío → el primero guarda su copia anterior con `at_av_guardar()` → desaparece la marca → el proyecto vuelve a ser elegible.
- **Impacto:** pérdida de estado y posible reenvío.
- **Alternativa:** incluir en el alcance los escritores que puedan sobrescribir estas claves y hacer que actualicen sobre el valor vigente, con comparación y relectura cuando corresponda. Si no puede garantizarse eso, separar el registro de envíos del documento compartido.

Además, **no se entregó `at_av_normalizar()`**. Como todas las lecturas y escrituras pasan por ella, falta verificar que conserve `termino` y `resena`. No afirmo que hoy las descarte; es una dependencia que el plan debe comprobar y, si corresponde, modificar.

**D4.c. La ruta existente no reconoce las reseñas.**

- **Evidencia:** `at_cc_tipos_recordatorio()` contiene una lista cerrada sin reseñas. `/at/v1/whatsapp-envio` rechaza tipos desconocidos y guarda una asociación necesaria para reconocer el `wamid`.
- **Escenario:** Meta devuelve un `wamid` → el envío no queda asociado como reseña → llega `failed` → el mecanismo descrito no puede resolverlo como ese nuevo tipo.
- **Impacto:** la promesa de avisar a Luis no queda cubierta.
- **Alternativa:** agregar expresamente el registro `wamid → intento/proyecto`, su resolución y el aviso. Puede extenderse el mecanismo existente o incorporarse un adaptador. Debe conservarse el manejo del fallo que llega antes del registro y evitar avisos repetidos.

También hace falta avisar por **errores inmediatos sin `wamid`**, no solamente por fallos posteriores de Meta.

**D4.d. Faltan resultados verificables y estados para los fallos.**

- **Evidencia:** WordPress realiza el correo, pero `/resultado` recibe desde n8n “el estado del correo” y escribe un título inicialmente presentado como envío de ambos canales.
- **Escenario:** el correo falla y Meta rechaza WhatsApp → se ejecuta el paso 4 tal como está descrito → queda `enviado_at` y un historial de invitación enviada.
- **Impacto:** éxito ficticio y pérdida de capacidad para investigar.
- **Alternativa:** WordPress conserva su propio resultado del correo; n8n informa el resultado de WhatsApp asociado al intento. Distinguir, como mínimo: no intentado, aceptado, fallido y desconocido; añadir entrega de WhatsApp cuando el webhook la confirme.

`wp_mail() === true` no acredita recepción. Un `wamid` tampoco acredita entrega: Meta lo devuelve al aceptar la solicitud. El panel y el historial deben reflejar esa diferencia. [WordPress: `wp_mail()`](https://developer.wordpress.org/reference/functions/wp_mail/), [Meta: respuesta de envío](https://developers.facebook.com/documentation/business-messaging/whatsapp/messages/send-messages).

El historial necesita además una clave de deduplicación. `at_cc_historial_crm()` inserta directamente y no devuelve confirmación de éxito; repetir `/resultado` no debe crear otra nota, y fallar al escribirla no debe provocar otro envío.

**D5 — APRUEBO: un canal basta y no se reintenta automáticamente.**

Es coherente con evitar solicitudes repetidas. Debe entenderse como **invitación tramitada por al menos un canal**, conservando el resultado de cada uno.

Si ambos fallan, queda fallida y se avisa; si alguno queda desconocido, se bloquea su repetición y se revisa. Ninguna de esas situaciones justifica declarar que ambos llegaron. Estas precisiones deben incorporarse al contrato de D4.

**D6 — APRUEBO: enlace público configurable y botón fijo.**

Google permite pedir reseñas mediante el enlace del Perfil de Empresa. No es un secreto y puede estar en una opción de WordPress. [Google: solicitar reseñas](https://support.google.com/business/answer/3474122).

El enlace concreto no fue entregado: **su validez y correspondencia con AT quedan NOT_RUN**. Antes de habilitar los envíos deben verificarse ambos destinos, correo y plantilla aprobada. La prohibición de enviar sin enlace debe aplicarse también a los botones manuales, no solo a `pendientes`.

**D7 — OBJETO: selección por satisfacción y texto incompatible con los envíos manuales.**

**Primera objeción: orientación selectiva a reseñas positivas.**

- **Evidencia:** el WhatsApp pide reseña “si quedaste conforme” y dirige a responder por privado “si algo no quedó como esperabas”.
- **Impacto:** condiciona la solicitud pública a la satisfacción. Google prohíbe solicitar selectivamente reseñas positivas y desalentar las negativas; no basta con evitar incentivos. [Política de Google](https://support.google.com/contributionpolicy/answer/7400114).
- **Alternativa concreta:** pedir una experiencia honesta sin condicionar el enlace al resultado:

> Hola {{1}}, gracias por confiar en AutomatizaTech para {{2}}. Si quieres, puedes compartir tu experiencia en Google con una reseña honesta. También puedes responder aquí si necesitas ayuda.

La exclusión de D2 debe responder a bajas, errores u otras razones legítimas, sin convertirse en un filtro por satisfacción.

**Segunda objeción: “hace una semana” puede ser falso.**

- **Escenario:** Luis usa “Enviar ahora” dos días después del término, o para un proyecto antiguo; también puede ejecutarse tarde por una interrupción.
- **Impacto:** mensaje incorrecto al cliente.
- **Alternativa:** usar el texto neutral anterior, que sirve para todos esos casos.

**Sobre Utility: APRUEBO solicitar esa categoría, sin asegurar su aprobación.** Meta admite solicitudes de opinión vinculadas a una interacción concreta, pero distingue contenido promocional o genérico. No hay fundamento suficiente para afirmar que esta plantilla necesariamente será Utility o necesariamente Marketing. El plan ya contempla aceptar la clasificación de Meta. [Categorías oficiales](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/template-categorization).

**Sobre BAJA:** apruebo incorporar el mecanismo, pero objetaría considerarlo cubierto solo por el pie. Deben existir una dirección de respuesta válida, un responsable que procese las solicitudes y la supresión efectiva de D2; también un mecanismo claro en WhatsApp. La aplicabilidad jurídica concreta queda para Toga. El HTML completo del correo no fue entregado y no está revisado.

**D8 — OBJETO: `prueba=1` necesita aislamiento durante todo el recorrido.**

- **Qué falta:** el plan identifica destinatarios de prueba, pero no define cómo `/resultado` y los estados posteriores distinguen ese intento del real.
- **Escenario:** se reclama en prueba → n8n informa el resultado por la ruta común → se aplica la finalización real porque el intento no conserva su modo.
- **Impacto:** puede quedar una invitación real marcada sin haberse enviado al cliente.
- **Alternativa:** persistir el modo de prueba en el intento y verificarlo en todas sus devoluciones. La prueba no modifica marcas de producción ni crea un evento de envío al cliente en su historial.

El servidor debe sustituir **todos** los destinatarios —incluidos CC/BCC y WhatsApp— por los autorizados de Luis. Si falta alguna configuración necesaria, debe fallar explícitamente, sin recurrir al contacto del cliente. La prueba tampoco debe bloquear un envío real posterior.

**D9 — OBJETO: falta el recorrido completo desde el panel hasta n8n.**

- **Evidencia:** `/reclamar` envía correo y devuelve datos de WhatsApp; quien envía WhatsApp es n8n. No se define qué inicia ese flujo cuando se presiona un botón del panel.
- **Escenario:** el navegador reclama → WordPress manda correo → la respuesta contiene los datos → nadie ejecuta el envío de WhatsApp.
- **Impacto:** “Enviar ahora” y “Enviarme una prueba” quedan incompletos.
- **Alternativa:** definir un único recorrido de ejecución, por ejemplo un disparador autenticado desde WordPress hacia n8n. Tanto el Schedule como el panel deben utilizar los mismos intentos y controles.

Las acciones del panel requieren permisos de usuario y protección contra solicitudes falsificadas. La clave de máquina debe permanecer en el servidor, nunca en el navegador. “Enviar ahora” solo exceptúa las restricciones temporales autorizadas; conserva las demás guardas.

Los estados visibles deben incluir en curso, fallido, parcial y desconocido. Un ✓ de WhatsApp debe indicar claramente si significa aceptado o entregado.

Para **los archivos previstos**, el dictamen corresponde al alcance propuesto; no certifica código que todavía no existe:

| Archivo o artefacto | Dictamen | Fundamento y acción |
|---|---|---|
| Plan `2026-11-19-resena-google-cierre.md` | **OBJETO** | Incorporar las correcciones D2–D4 y D7–D9 y presentar una nueva revisión identificada. |
| `inc/plan-trabajo/resena-puras.php` | **OBJETO** | La separación es adecuada, pero debe incluir la elegibilidad completa de D3 y los estados acordados. |
| `inc/plan-trabajo/resena.php` | **OBJETO** | Corregir intentos, resultados, concurrencia, historial y aislamiento de pruebas conforme a D4/D8. |
| `inc/plan-trabajo/resena-correo.php` | **APRUEBO** | Adecuado separar la plantilla. HTML final, destinatarios y presentación: NOT_RUN. Aplicar el texto aprobado de D7. |
| `inc/plan-trabajo/avance-panel.php` | **OBJETO** | Falta el recorrido a n8n, permisos y representación de estados de D9. |
| `inc/plan-trabajo/cargar.php` | **APRUEBO** | Incorporar los módulos mediante `require` es consistente con el diseño entregado. Orden concreto: por verificar. |
| `N8N/resena-google/aplicar.py` | **OBJETO** | Debe contemplar la ejecución manual, errores parciales y resultados perdidos. Apruebo crearlo inactivo, respaldar y verificar la versión publicada. |
| `N8N/resena-google/plantilla_meta.py` | **APRUEBO** | Crear/consultar la plantilla con autorización de Luis es adecuado. Registrar idioma, identificador, categoría y estado efectivos. |
| `tests/plan-trabajo/resena-puras-test.php` | **OBJETO** | Ampliar con tipo de cliente, fechas inválidas, canales inválidos, bajas y permisos aplicables. |
| Prueba HTTP, sin archivo identificado | **OBJETO** | 401 y doble reclamo son insuficientes para las garantías prometidas; agregar los casos de fallos y concurrencia indicados abajo. |
| `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md` | **APRUEBO** | Es una ubicación coherente para la guía. Incluir bajas, estados inciertos, diagnóstico y recuperación segura. |
| README privado de partida | **OBJETO** | Afirma que el cierre existente dispara la cuenta, mientras la evidencia muestra cierres de hitos. Actualizarlo para evitar dos instrucciones contradictorias. |

Faltan además en el alcance: el archivo que define `at_av_normalizar()` —ruta no entregada—, los escritores relevantes de `avance`, la integración del registro/aviso de WhatsApp y el almacenamiento de las bajas. No exijo que todos cambien: exijo inspeccionarlos y definir qué necesita cambiar antes de prometer compatibilidad.

Para **cada riesgo declarado**:

| Riesgo | Dictamen sobre su cobertura | Evidencia, impacto y alternativa |
|---|---|---|
| Que salga dos veces | **OBJETO** | El vencimiento y la sobrescritura permiten los escenarios D4.a/b. Incorporar intentos persistentes y conservación del estado. |
| Escribir a clientes antiguos sin querer | **APRUEBO** | El corte lo cubre si se define por fecha efectiva y el envío manual es explícito. Resolver la ambigüedad de D3. |
| Marketing/131049 | **OBJETO** | El respaldo por correo es válido, pero el aviso de reseñas aún no está integrado. Resolver D4.c y errores sin `wamid`. |
| Que se perciba como spam | **OBJETO** | Una invitación y un pie no cubren permisos ni bajas entre proyectos. Resolver D2 y el texto D7. |
| Cambio del enlace | **APRUEBO** | Los dos lugares están identificados. Comprobar ambos después de cualquier cambio y el estado de la plantilla editada. |
| Clave `X-AT-Secret` en n8n | **APRUEBO** | Reutilizar la autenticación existente no añade por sí mismo un defecto. Mantenerla en credenciales del servidor; vigencia y configuración real: NOT_RUN. |

**Faltantes que deben agregarse al plan:**

- **Pruebas de las garantías:** caída después de enviar correo; resultado HTTP repetido; intento vencido con resultado desconocido; guardado concurrente de avance; clic manual simultáneo con Schedule; ambos canales fallidos; un canal ausente; fallo de WhatsApp anterior al registro y webhook repetido; baja entre consulta y reclamo; prueba seguida de envío real. Deben comprobar comportamiento, no solo respuestas 200/409.
- **Lotes y continuidad:** con once pendientes, el límite de diez posterga al menos uno. Si los primeros diez fallan sin salir de la selección, pueden bloquear a los demás. Definir orden, aislamiento de errores y continuación acotada del lote.
- **Reapertura y cambios de fecha:** no borrar registros de envío al deshacer o volver a terminar un proyecto. Resolver qué ocurre si se solicita cancelar cuando el intento ya está en curso.
- **Persistencia y Calibre:** no hacer una migración no elimina la revisión de datos. El método pegado exige Calibre en tareas que toquen la base; revisar estructura, tamaños, normalización y cualquier campo nuevo.
- **Privacidad:** minimizar los datos guardados en ejecuciones de n8n, controlar su acceso y definir conservación. Toga debe revisar el uso de los contactos, además del pie de baja. La ficha oficial consultada fija la entrada del nuevo régimen de la Ley 21.719 para el **1-dic-2026**, fecha próxima a esta operación; confirmar la normativa aplicable al activar. [Ley 21.719](https://www.bcn.cl/leychile/navegar?idNorma=1209272).
- **Operación y rollback:** detectar intentos atascados y fallos del reloj. Desactivar el Schedule no cancela ejecuciones iniciadas. El rollback debe conservar las marcas de lo ya intentado/enviado: restaurar datos anteriores podría generar duplicados.
- **Costos:** identificar la categoría y tarifa efectivas de WhatsApp antes de activar, y aplicar el registro de gastos de Balance. No hace falta presumir un monto.
- **Identificación de la implementación:** registrar base exacta, owner y revisión A/B. Las pruebas futuras de código permanecen NOT_RUN.

**Sugerencia menor:** reemplazar “Meta responde en menos de 24 h” por una estimación basada en la experiencia anterior. La habilitación debe depender del estado real de aprobación, no del tiempo transcurrido. También conviene llamar “Tramitada” al estado agregado si solo se conoce aceptación del transporte.

**Decisiones que Luis debe resolver antes del trabajo dependiente:**

1. Fecha efectiva de término o fecha de marcado para el corte histórico.
2. WhatsApp automatizado desde AT o envío manual desde su teléfono: cambia la arquitectura y lo que puede acreditarse como enviado.
3. Aceptar explícitamente que un canal basta y que los resultados desconocidos requieren revisión sin reenvío automático; es la propuesta de D5.
4. Aprobar los textos corregidos antes de presentarlos a Meta.

El enlace y los permisos de los destinatarios son verificaciones pendientes, no preguntas de preferencia. Las funciones independientes pueden construirse mientras se resuelven estos puntos.

**Checklist B del método:**

| Control | Estado | Evidencia o límite |
|---|---|---|
| B1. Actor distinto y revisión identificada | **PASS** | Autor declarado Eje/Claude; revisión por Codex sobre la transcripción atribuida a `4033a24`. Correspondencia con Git: **NOT_RUN**. |
| B2. Inspección independiente de insumos y artefacto | **PASS** | Examinados el plan, método, rol, plantillas disponibles y fragmentos de código; no solo el resumen del autor. |
| B3. Necesidad, aceptación y comprobaciones | **FAIL** | El objetivo está respaldado, pero faltan garantías de envío único, bajas y recorrido manual. Pruebas ejecutables: **NOT_RUN**. |
| B4. Fuentes y prácticas aplicables | **PASS** | Contrastadas fuentes oficiales de Google, Meta, WordPress y legislación chilena, sin proponer migraciones por novedad. |
| B5. Riesgos, privacidad, marca y permisos | **FAIL** | Pendientes D2, D7, D8 y permisos del panel. Se conservan los OK de Luis para Meta y despliegue. |
| B6. Hallazgos, acciones y pendientes explícitos | **PASS** | Objeciones con evidencia o escenario, impacto y alternativa; límites de verificación declarados. |

**Checklist A:** no fue adjuntado; su contraste formal queda **PEER_REVIEW_PENDING**. No lo reconstruyo como aprobación del autor.

**Dictamen final: APROBADO CON CAMBIOS**, sujeto a incorporar bajas y permisos por destinatario; completar elegibilidad; corregir intentos y concurrencia; integrar los estados de WhatsApp; neutralizar el texto; aislar las pruebas; definir el recorrido del panel; y ampliar pruebas y recuperación. La arquitectura general puede mantenerse. Esta revisión no autoriza envíos, gastos ni despliegue.