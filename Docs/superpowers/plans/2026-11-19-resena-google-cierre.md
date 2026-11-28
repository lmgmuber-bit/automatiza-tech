# Pedir reseña en Google después de terminar un proyecto: plan (v4)

- **Pedido de Luis:** 16-nov-2026.
- **v1:** 19-nov, commit `4033a24`.
- **v2:** 19-nov, incorpora la revisión B de Atlas-Codex (`2026-11-19-resena-google-cierre-revision-B-codex.md`: **APROBADO CON CAMBIOS**). Eje acepta todas sus objeciones. Cada una tiene su lugar en este plan, marcado con **[B-…]**.
- **Autor:** Eje (Claude). **Revisor:** Atlas-Codex. La v2 vuelve a Codex para una segunda vuelta antes de construir.
- **Plantillas de partida:** en la bóveda privada, `10-Projects/2026-11-16-AT-Resena-Google-Cierre-PENDIENTE/`. Su README dice que «el cierre» dispara la cuenta, pero eso es un cierre de hito, no el término del proyecto. **[B-README]** Se corrige al terminar la v2.

## 1. Qué se quiere

Después de que un proyecto queda terminado, el cliente recibe **una sola vez** una invitación a contar su experiencia en el Perfil de Empresa de Google de AT, por correo y por WhatsApp. Queda en el historial del CRM. Si algo falla o queda incierto, se avisa a Luis y **no se reintenta solo**.

**No se busca:**
- pedir reseñas a prospectos;
- reenviar la invitación;
- ofrecer algo a cambio;
- **filtrar por satisfacción.** Google prohíbe pedir reseñas solo a quien quedó conforme y desalentar las negativas: <https://support.google.com/contributionpolicy/answer/7400114>. **[B-D7]**

## 2. Lo que existe hoy (verificado en `origin/main`)

Igual que en la v1:
- no hay estado de «proyecto terminado» (`at_pt_transiciones()` llega hasta `enviado`);
- los cierres de `ESTADO-DEL-PROYECTO.md` son cierres de hito;
- existen la columna JSON `avance`, `at_cc_rest_auth` y `at_cc_historial_crm`, y los correos salen con `wp_mail`;
- WhatsApp solo llega con plantilla aprobada.

**Agregado en la v2:**
- `at_av_guardar()` reemplaza `avance` entero, sin comparar. Solo `at_av_guardar_si_igual()` protege contra pisadas. **[B-D4b]**
- `at_av_normalizar()` se aplica a toda lectura y escritura. Hay que verificar que conserve claves nuevas. **[B-D4b]**
- La ruta de estados de WhatsApp (`at_cc_tipos_recordatorio()` y `/at/v1/whatsapp-envio`) tiene una lista cerrada de tipos y rechaza los desconocidos: hoy no reconocería una reseña. **[B-D4c]**
- `at_cc_historial_crm()` inserta sin devolver confirmación. **[B-D4d]**
- `crm_cliente_id` puede ser `NULL`. `at_cc_enviar_bienvenida()` sí comprueba `tipo === 'cliente'` y valida el correo. **[B-D3]**

## 3. Decisiones

**D1 (aprobada).** El término se marca a mano en el panel «📈 Avance» con «🏁 Marcar proyecto terminado» y se guarda en `avance.termino = {fecha, marcado_at, por}`. Se puede deshacer **solo si no hay ningún intento de envío**, ni en curso, ni enviado, ni desconocido. Deshacer o volver a terminar **nunca borra el registro de envíos**.

**D2 (corregida). [B-D2]** Hay dos niveles de exclusión:
- **Por proyecto:** la casilla «No pedir reseña en este proyecto», con un motivo obligatorio. Los motivos posibles son baja, destinatario equivocado u otro motivo justificado que no tenga que ver con lo satisfecho que esté el cliente [B2-2]. **Nunca sirve para elegir a los conformes.**
- **Por destinatario:** una tabla nueva y chica de supresiones, `wp_at_resena_bajas`, con correo y/o teléfono normalizados, canal (`correo`, `whatsapp` o `todos`), alcance (`resenas` o `todo`), fecha, origen y nota. Todos los envíos de reseña la consultan.

**Cómo se carga una baja:**
- el enlace de baja del correo, de un clic, firmado (HMAC con la sal `auth`), con una página de confirmación y POST, igual que Retomar;
- la respuesta «BAJA» por WhatsApp, procesada en el bot, que ya recibe botones y textos;
- a mano desde el panel.

**Permiso para WhatsApp:**
- La política de WhatsApp exige que la persona haya autorizado recibir mensajes del negocio.
- El WhatsApp sale solo con una **autorización acreditada**: la casilla `wa_autorizado` de la ficha, con su origen y fecha. Una conversación previa sirve solo si en ella la persona autorizó recibir mensajes [B2-1].
- Si no, sale solo el correo.
- **Pendiente de verificar en el código:** dónde vive hoy esa evidencia. Si no existe un campo, se agrega la casilla «WhatsApp autorizado» en la ficha, como en Retomar.

**D3 (corregida). [B-D3]** Es elegible quien cumple todo esto, y se vuelve a comprobar **al reclamar**:
- la ficha del CRM existe y su tipo es `cliente`;
- `termino.fecha` es una fecha válida y `termino.fecha + 7 días <= hoy` en la hora de `America/Santiago`;
- no hay ningún intento previo (ver D4);
- la exclusión por proyecto no está marcada;
- tiene **al menos un canal utilizable**: un correo con formato válido y sin baja, o un teléfono E.164 válido, sin baja y con permiso;
- pasa el corte histórico (**decisión de Luis, pregunta 1**).

«Enviar ahora» puede saltarse **solo** el plazo de 7 días y el corte histórico, nunca las bajas, el tipo de cliente ni la protección contra duplicados.

**D4 (rediseñada). [B-D4a–d]** El envío se registra por intentos, en `avance.resena`:

```
resena = {
  excluido: false, motivo_exclusion: '',
  intento: {
    id: <uuid>, modo: 'real' | 'prueba', origen: 'reloj' | 'panel', creado_at,
    correo:   { estado: 'no_intentado' | 'en_curso' | 'aceptado' | 'fallido' | 'desconocido', at, detalle },
    whatsapp: { estado: (igual) | 'entregado' | 'leido', wamid, at, detalle }
  },
  historial_escrito: { <clave>: true }
}
```

- **Antes de llamar al proveedor** (SMTP o Meta), el estado del canal pasa a `en_curso` con `at_av_guardar_si_igual`. **Después**, se anota el resultado.
- **Si se pierde la respuesta** después de un `en_curso`, el reloj **no reenvía nunca**: pasados 30 minutos, el canal queda `desconocido`, la invitación se bloquea para revisión y se avisa a Luis. **[B-D4a]**
- Un intento real con cualquier canal en `aceptado`, `en_curso` o `desconocido` deja el proyecto fuera de la elegibilidad para siempre, salvo que Luis lo libere a mano después de revisar.
- **Correo:** lo envía WordPress, que anota él mismo su resultado. `wp_mail() === true` quiere decir aceptado por el servidor de correo, no recibido.
- **WhatsApp:** lo envía n8n y lo informa en `/resena/{plan}/resultado` con `intento_id`. WordPress acepta el resultado solo si corresponde al intento vigente, y es idempotente: repetir el resultado no cambia nada ni duplica el historial. `wamid` quiere decir aceptado por Meta; `entregado` y `leído` llegan después por el webhook de estados. **[B-D4d]**
- **Ruta de estados:** se agrega el tipo `resena` a `at_cc_tipos_recordatorio()`, con el registro `wamid → plan/intento`. Si llega un `failed`, el canal pasa a `fallido` y se avisa a Luis una sola vez. Se cubre también el fallo que llega antes de registrar el `wamid`, como ya hace la ruta. Un error inmediato de Meta, sin `wamid`, se informa en `/resultado` y también avisa. **[B-D4c]**
- **Historial del CRM** con clave de deduplicación (`resena:<intento_id>:<evento>`, guardada en `historial_escrito`). Si escribir el historial falla, no provoca otro envío. **[B-D4d]**
- **Escritores de `avance`:** antes de construir, se buscan todos los que llaman a `at_av_guardar()`. Los que pueden correr mientras hay una reseña en curso pasan a releer y comparar (`at_av_guardar_si_igual`, con reintento corto). Si eso no se puede garantizar, el estado de la reseña se va a una **tabla propia** (`wp_at_resenas`, una fila por plan) en vez de vivir en `avance`. **Recomendación de Eje:** la tabla propia. Aísla el estado del envío del JSON que editan el panel y el renderer, y simplifica la concurrencia. Lo revisa Calibre. **[B-D4b]**
- Verificar que `at_av_normalizar()` conserve `termino` (y `resena`, si se queda en `avance`).

**Flujo diario:**
1. Schedule de n8n a las 10:00 de Chile → `GET /resena/pendientes` (máximo 10, orden por `termino.fecha`). **[B-lotes]**
2. Por cada uno, `POST /resena/{plan}/reclamar`: crea el intento, vuelve a comprobar D3 y envía el correo. **reclamar no devuelve datos de WhatsApp**: dispara un webhook autenticado con solo referencias de proyecto/intento/modo.
3. El flujo del webhook canjea **autorizar-whatsapp de un uso**, revalida permiso/contacto/baja y obtiene los datos mínimos para enviar la plantilla. El Schedule no despacha a partir de la respuesta de reclamar.
4. `POST /resultado`.

Un plan que falla no frena a los demás. Si hay más de 10, la corrida siguiente toma el resto. Los bloqueados (`desconocido`) dejan de aparecer en `pendientes`, así que nunca tapan la cola.

**D5 (aprobada, precisada).** Basta un canal aceptado para que la invitación quede **tramitada**, con el resultado de cada canal guardado. Si los dos canales fallan, queda **fallida** y se avisa, sin reintento solo. Si alguno queda desconocido, queda **en revisión**. Nunca se dice «llegó» si solo se sabe que lo aceptaron. **Pregunta 3** para Luis.

**D6 (aprobada).** El enlace «Pedir reseñas» va en la opción `at_resena_google_url`, y en el botón fijo de la plantilla de WhatsApp. Sin enlace configurado, **no sale nada**, tampoco desde los botones del panel. Antes de activar, se verifica que los dos destinos abran el perfil de AT.

**D7 (corregida). [B-D7]** Textos neutrales, que no condicionan la reseña ni dicen «hace una semana».

- **WhatsApp** (`at_resena_google_cierre`), cuerpo:
  > Hola {{1}}, gracias por confiar en AutomatizaTech para {{2}}. Si quieres, puedes contar tu experiencia en Google con una reseña honesta: nos ayuda a mejorar y les sirve a otros negocios. Si necesitas ayuda con algo, respóndenos aquí.

  Pie: «AutomatizaTech · automatizatech.cl · Responde BAJA si no quieres recibir estos mensajes». Botón: «Dejar mi reseña» → enlace fijo.
- **Correo:** el mismo sentido. Asunto «¿Nos cuentas tu experiencia con AutomatizaTech?». Pie con un enlace de baja que funcione (D2) y una dirección de respuesta atendida (contacto@).
- **Categoría:** se pide Utility y se acepta lo que decida Meta. Si queda Marketing, el correo es el canal principal y se anota la tarifa efectiva para Balance antes de activar. **[B-costos]**
- **Toga revisa:** los textos, el art. 28 B de la Ley 19.496, el uso de los contactos y la Ley 21.719, que rige desde el 1-dic-2026.

**D8 (corregida). [B-D8]**
- `prueba` es un **modo del intento**, guardado en él y comprobado en cada devolución.
- **El servidor reemplaza todos los destinatarios** (para, copia, copia oculta y WhatsApp) por los de prueba de Luis. Si falta alguno, falla con un mensaje explícito y nunca cae al contacto del cliente.
- Una prueba no toca las marcas reales, no escribe «enviada al cliente» en el historial y no bloquea el envío real posterior.

**D9 (corregida). [B-D9]**
- Los botones del panel, «Enviarme una prueba» y «Enviar ahora», van por `admin-post` con nonce y `current_user_can('manage_options')`.
- WordPress crea el intento, envía el correo y **dispara n8n** por un webhook autenticado: un flujo propio, «Reseña · Enviar WhatsApp», que el Schedule también usa, así que la lógica es una sola. La clave de máquina se queda en el servidor.
- **Estados visibles:**
  - «Se enviará el …»;
  - «En curso»;
  - «Tramitada el … (correo: aceptado · WhatsApp: entregado)»;
  - «Parcial»;
  - «Fallida»;
  - «En revisión (resultado desconocido)»;
  - «Excluido: motivo».

## 4. Archivos

- **Nuevos:**
  - `inc/plan-trabajo/resena-puras.php`: elegibilidad completa, estados y armado de los datos;
  - `inc/plan-trabajo/resena.php`: REST, intentos, correo, historial y webhook a n8n;
  - `inc/plan-trabajo/resena-correo.php`;
  - `inc/plan-trabajo/resena-bajas.php`: tabla, página de baja y consulta;
  - y, si gana la recomendación de D4, `inc/plan-trabajo/resena-datos.php`, con la tabla `wp_at_resenas` y su migración.
- **Cambian:**
  - `inc/plan-trabajo/avance-panel.php`;
  - `inc/plan-trabajo/cargar.php`;
  - `inc/cierre-cliente/whatsapp-recordatorios.php` (tipo `resena`);
  - el archivo de `at_av_normalizar()` si `termino` no se conserva;
  - los escritores de `avance` según D4.
- **n8n:**
  - `N8N/resena-google/aplicar.py`: Schedule + «Reseña · Enviar WhatsApp», creados inactivos, con respaldo y verificación de `activeVersion`;
  - el bot de WhatsApp, para reconocer «BAJA» en esta plantilla;
  - `plantilla_meta.py`, que crea y consulta la plantilla con el OK de Luis y anota idioma, id, categoría y estado.
- **Pruebas** (comportamiento, no solo 200/409): **[B-pruebas]**
  - **Funciones puras:** los 7 días con cambio de horario; tipo de cliente; fecha inválida; correo o teléfono inválido; bajas por canal y alcance; permiso de WhatsApp; corte histórico; «Enviar ahora» que salta solo lo permitido.
  - **HTTP y estado:**
    - una caída después del correo deja `desconocido` y no reenvía;
    - un `/resultado` repetido no cambia nada y no duplica el historial;
    - dos guardados de `avance` a la vez no borran la marca;
    - un clic en el panel al mismo tiempo que el Schedule produce un solo intento;
    - si fallan los dos canales, queda fallida y avisa;
    - un canal ausente queda «no_intentado»;
    - un `failed` correlacionado y un webhook repetido avisan una sola vez; un wamid previo a `/resultado` no se guarda por no poder distinguirlo de uno ajeno (límite para revisión de Eje antes de activar);
    - una baja entre `pendientes` y `reclamar` hace que no salga;
    - una prueba seguida del envío real envía bien el real;
    - un 401 sin la clave.
- **Documentación:** «Pedir reseña» en `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md`: uso, bajas, estados inciertos, cómo revisar y liberar, despliegue y vuelta atrás. La vuelta atrás **conserva las marcas de lo ya intentado**. **[B-rollback]**

## 5. Orden y gates

1. **Luis** responde las preguntas 1 a 4 y aprueba los textos de D7. **Faro** entrega el enlace. **Toga** revisa. **Calibre** revisa la tabla de bajas y, si va, la tabla de reseñas.
2. **Segunda vuelta de Codex** sobre la v3, con las respuestas de Luis.
3. Se envía la plantilla a Meta (**con el OK de Luis**). Se habilita según el estado real que informe Meta, no por el tiempo que pase.
4. **Construcción:** rama desde `main`, TDD. Revisión B del código por el homólogo; Centinela en las pruebas; Vigía en las rutas REST, la página de baja y el webhook a n8n.
5. **Despliegue con el OK de Luis:**
   - cotejo, respaldo, migración y luego WordPress;
   - n8n inactivo;
   - prueba completa a Luis (D8);
   - Luis activa el Schedule.
   Desactivar el Schedule no cancela las ejecuciones que ya partieron: hay que revisarlas. **[B-operación]**
6. **Primer caso real** elegido por Luis.

## 6. Riesgos (v2)

| Riesgo | Qué lo cubre |
|---|---|
| Que salga dos veces | Intentos persistentes con estados por canal; `desconocido` bloquea en vez de reintentar; escrituras comparadas o tabla propia; historial con clave. |
| Escribirles a clientes viejos sin querer | Corte histórico (pregunta 1); «Enviar ahora» explícito. |
| Marketing / 131049 | El correo es el canal principal; aviso de fallos integrado en la ruta de estados y en los errores sin `wamid`. |
| Que se perciba como spam, o una baja no respetada | Bajas por destinatario y canal; permiso de WhatsApp; texto neutral; enlace de baja que funciona. |
| Pedir reseñas selectivamente (prohibido por Google) | Texto neutral; la exclusión exige un motivo que no sea la satisfacción. |
| Que cambie el enlace | La opción de WordPress y la plantilla de Meta; se verifican los dos después de cada cambio. |
| Datos personales en n8n | Ejecuciones con lo mínimo (primer nombre, teléfono, proyecto); retención según la configuración de n8n; revisión de Toga. **[B-privacidad]** |
| La clave `X-AT-Secret` | La misma credencial de máquina, solo en el servidor. |

## 7-bis. Respuestas de Luis (19-nov) → v3

- **Primera tanda: los tres primeros clientes.** Luis los da por terminados. Se marcan a mano con «🏁 Marcar proyecto terminado», con la fecha real de término, y salen con «Enviar ahora» (D3 ya lo permite). Desde ahí, los proyectos nuevos siguen la regla automática de los 7 días. Así se resuelve la pregunta 1: el corte histórico solo aplica al envío automático.
  - **cliente C:** confirmar que tenga un plan de trabajo en el sistema. Si no lo tiene, hace falta un punto de entrada sin plan (ver D10).
  - **cliente A:** según Sereno, tiene cobros abiertos. Que Luis confirme que la invitación no choque con eso.
- **Automático por correo y por WhatsApp**, desde el número de AT con la plantilla. Resuelve la pregunta 2.
- **D10 (nueva): «Volver a enviar».** En el panel del proyecto hay un botón «🔁 Volver a enviar la invitación», para cuando el cliente no dejó la reseña. Lo activa Luis desde el wp-admin; nunca es automático.
  - **Guardas:** pasaron al menos 7 días desde el intento anterior; el intento anterior no está `en_curso` ni `desconocido`; las bajas y el permiso de WhatsApp se vuelven a revisar; hay **un solo reenvío por proyecto** (máximo dos invitaciones en total).
  - **Registro:** crea un intento nuevo, con `intentos[]` en vez de `intento`, que guarda el historial de los dos, y lo anota en el CRM.
  - **Texto:** el mismo de siempre (D7), que no dice «otra vez».
  - **Por qué hay tope:** no hay forma confiable de saber si una persona ya dejó la reseña. La API del Perfil de Empresa muestra el nombre público, no el correo. Reenviar sin límite se percibe como spam.
  - **Punto de entrada sin plan:** si cliente C no tiene plan de trabajo, el botón de término y los de invitación van también en la ficha del cliente del CRM (pestaña «⭐ Reseña»), guardados por `crm_id`. Esto lo decide la revisión: o la tabla `wp_at_resenas` usa la clave `(crm_id, plan_id NULL)`, o solo se trabaja con planes.
- **Pregunta 3** (basta un canal; lo desconocido se revisa a mano): **sin respuesta explícita.** El plan la mantiene así; Luis puede cambiarla.
- **Pregunta 4** (textos neutrales): **sin aprobación explícita todavía.** No se envía nada a Meta hasta que Luis los apruebe.

## 7. Preguntas para Luis (antes de lo que depende de ellas)

1. **Corte histórico:** ¿cuentan los proyectos con **fecha de término** desde el 19-nov, o los **marcados** desde el 19-nov aunque hayan terminado antes? Recomendación: la fecha de término, porque es lo que el cliente vivió. Los anteriores, solo con «Enviar ahora».
2. **WhatsApp automático** desde el número de AT con la plantilla, o **manual** desde tu teléfono (como el aviso de cierre de hito). El manual es más simple, pero no se puede saber si llegó y depende de que te acuerdes.
3. ¿Aceptas que **un canal basta** y que un resultado **desconocido se revisa a mano, sin reenvío automático**?
4. ¿Apruebas los **textos neutrales** de D7? Ya no dicen «si quedaste conforme», porque Google lo prohíbe.

## 8. v4: cambios por la segunda vuelta (`2026-11-19-resena-google-cierre-revision-B2-codex.md`, APROBADO CON CAMBIOS)

- **[B2-1] Permiso de WhatsApp:** hace falta una autorización acreditada, con su origen. Sin ella, la invitación va solo por correo (D2 corregido arriba). En la primera tanda (los tres primeros clientes), Luis confirma el permiso de cada uno antes del envío.
- **[B2-2] Motivo de exclusión:** se quitó «malos términos» (D2 corregido arriba). Revisar los cobros abiertos de cliente A es una comprobación de operación, no un filtro por la opinión que se espera de él.
- **[B2-3] Contrato de D10:**
  - `intentos[]` distingue la invitación del reenvío (`tipo: 'invitacion' | 'reenvio'`);
  - el reenvío se reclama de forma atómica y cuenta solo los intentos reales: un tercero se rechaza, y liberar un bloqueo no devuelve el cupo;
  - cada devolución se procesa por su `intento_id` y su modo, y actualiza solo ese intento, así que una entrega tardía del primero se registra igual;
  - el botón «Volver a enviar» lleva nonce y `manage_options`, igual que los de D9.
  - **Pruebas nuevas:** doble clic en el reenvío; tercer intento rechazado; una prueba no consume el cupo; la devolución tardía del primero se registra.
- **[B2-4] Una identidad estable por proyecto:** la tabla propia `wp_at_resenas` lleva `proyecto_clave` NOT NULL UNIQUE (`crm:<id>` o `plan:<id>`), `crm_id` obligatorio y `plan_id` opcional. No se usa un índice único con NULL, porque MySQL permite varios NULL. El panel del plan y el del CRM resuelven **el mismo registro**, y vincular un plan después conserva los intentos y el contador. Lo revisa Calibre. Pruebas: creación concurrente y vinculación posterior.
- **Corte histórico** (pregunta 1, ya definida): el envío automático exige `termino.fecha >= 2026-11-19`. Los proyectos anteriores, como la primera tanda, salen solo con «Enviar ahora».
- **Monitoreo:** se guarda `at_resena_ultima_corrida`. Si pasan más de 26 horas sin una corrida exitosa, se avisa a Luis por el monitoreo que ya existe (ARGOS o correo).
- **Privacidad de n8n:** antes de activar, se anota la retención real de las ejecuciones del flujo y quién tiene acceso a n8n. El flujo guarda solo datos mínimos.
- **Costos:** antes de activar, se anotan la categoría y la tarifa efectivas de la plantilla, sea cual sea la categoría, y cada envío pagado se registra con Balance.
- **README de la bóveda:** se corrige para que diga «término del proyecto, marcado a mano», no «cierre de hito».
- **Checklist A del autor:** va junto con el código.

## 9. Avance (19-nov)

- **Luis aprobó** el texto de D7, el permiso de WhatsApp de los tres primeros clientes, y «basta un canal».
- **Plantilla enviada a Meta el 19-nov:** `at_resena_google_cierre`, id `1081745068247155`, idioma `es`, **PENDING**, pedida como UTILITY. Script: `N8N/resena-google/plantilla_meta.py`.
- **Botón** «Dejar mi reseña» → `https://automatizatech.cl/resena/`. WordPress redirige con 302 a la opción `at_resena_google_url`, así que el enlace de Google se cambia en un solo lugar y sin tocar la plantilla (mejora de D6). Hay que construir esa redirección antes del primer envío.

## 10. Correcciones de revisión B, vuelta 2 (LOCAL)

- Forja-Codex corrige `50c9410` en `codex/resena-google-impl`; Eje verifica la nueva revisión. Sin despliegue ni conexiones n8n/Meta reales.
- Un `/resultado` tardío válido puede promover **desconocido→aceptado**, sin enviar otra vez; el panel lo explica. Máximo **20 pruebas** conservadas por proyecto, sin limpieza automática; aviso visible al operador.
- **Decisión de Luis M4 (19-nov, Vuelta 4):** un intento real totalmente fallido no consume cupo; incluye `fallido + no_intentado`, pero exige al menos un canal fallido. «Enviar ahora» vuelve como invitación sin esperar, D10 queda intacto y el automático puede retomarlo si sigue elegible. Cualquier `aceptado`, `entregado`, `leido`, `en_curso` o `desconocido` consume/bloquea. Tope acumulado de **3 intentos reales totalmente fallidos por proyecto**: al tercero se bloquean nuevos envíos reales y se reserva un solo aviso a Luis; las pruebas no cuentan. Los siete días de D10 parten de la invitación que consumió cupo. Regla en `at_rg_consume_cupo()` y dependencias directas; M7 intacto. Evidencia y limitaciones: informe A, «Vuelta 4».
- Identidad solo al actuar; abrir la pestaña no crea filas y la pestaña es solo para clientes. Plan reasignado: bloquear y revisar a mano sin mover el registro.
- Esquema 2: migración v1→v2 conservadora; bigint(20) unsigned, holgura origen 50/nota 1000, índice binario completo por wamid, abierto indexado y cola por rango de fecha. La migración manual ignora el transient de cinco minutos.
- Vuelta 3: el módulo no crea una caché nueva por wamids ajenos ni usa LIKE sobre el longtext. Un failed temprano consume el transient existente de cierre-cliente (15 minutos) al indexar el wamid; la ruta reconsulta tras anotarlo para cubrir el intercalado. sent/delivered/read previos al índice pueden perderse, cubiertos por aceptado, sin afirmar entrega.
- Webhook fijo en `AT_N8N_RESENA_WHATSAPP`, coincidente con `aplicar.py`; no se usa una opción de BD para enviar la clave de máquina. Secreto propio pendiente de provisión coordinada; no se modifica wp-config ni se inventan secretos.
- **H11, zonas:** tablas propias `updated_at` y baja `fecha` en UTC; término/plazo en fecha civil America/Santiago; instantes JSON en ISO-8601 con offset. `crm_historial.created_at` usa hora local de WordPress. Convertir zonas antes de comparar y comprobar configuración del hosting.
- **I2, compatibilidad:** verificar versión/motor de Hostinger antes de desplegar. El `INSERT…SELECT` sin `FROM` no funciona en MySQL 5.7 según revisión B; MySQL 8.4 local no acredita el hosting. Bloquear publicación si el motor no es compatible.
- Instalación, rollback conservando las tres tablas y lista exacta de archivos: guía §8 e informe A «Vuelta 2».

## 11. Vuelta 3 (LOCAL, revisión de Eje pendiente)

- Base `dda5edb`; correcciones y TDD por punto en el [informe A](2026-11-19-resena-google-cierre-implementacion-A.md#vuelta-3). Sin despliegue ni servicios/datos reales; M4 y M7 intactos.
- +56 exige móvil chileno exacto. `crm_clientes` no tiene país en el esquema versionado, por lo que los nueve dígitos sin prefijo se rechazan con explicación; el panel muestra el teléfono normalizado.
- `/whatsapp-envio` tipo resena pasa proyecto por parámetros de URL. Migración automática solo con manage_options; manual sin cambios. El backfill fallido mantiene el bloqueo de cinco minutos.
- Índice wamid reservado antes del CAS: un identificador ajeno no entra al JSON. Prueba de capacidad detecta un validador que acepte 257 bytes frente a columna 256.
- `pendientes` excluye en SQL intentos reales anteriores; conserva los de prueba, sin nuevo esquema. JSON_VALID explícito cubre la diferencia de MariaDB ante rutas inexistentes en JSON.
- Vuelta a v1 detallada en la guía §8: sistema detenido, enlace vacío, respaldo vigente, límites nota/origen 500/30, restauración completa del código v1 y su migración a bandera 1. No borrar tablas ni marcas; la vuelta no se ejecutó en el sitio.
- Referencias de clientes anonimizadas para el repo público; comprobaciones comerciales y permisos se conservan en sus fuentes privadas.

## 12. Resumen de envíos por correo (24-nov-2026)

**Estado: EN PROD desde el 24-nov-2026 08:48–08:51 (OK de Luis).** WordPress: los cuatro archivos por SSH con llave, cotejados antes contra la base de la rama, `php -l` y sha256 verificados; respaldo `~/respaldos/resena-resumen-antes-20261124-084837/`. n8n: «Reseña · Resumen» creado y encendido, y el diario parchado; los dos con la versión publicada igual a la última. Respaldo del diario fuera del repo, en la carpeta privada de respaldos de n8n. La credencial de entrada del webhook y la REST son la misma (la cabecera coincide). Prueba en PROD: la ruta respondió 200 sin envíos y 400 con `desde` inválido; el webhook respondió 200 y la ejecución quedó «en espera» 15 minutos aunque el flujo no guarde su avance. Falta ver el primer correo real (el próximo envío).

### Qué hace
- Después de cada «Enviar ahora» o «Volver a enviar» que salió bien, el panel avisa al webhook `at-resena-resumen` de n8n. No avisa con «Enviarme una prueba» ni cuando el envío dio error.
- El flujo nuevo «Reseña · Resumen» espera 15 minutos (para que Meta alcance a confirmar las entregas), pide a WordPress `GET /wp-json/at/v1/resena/resumen?desde=` (con la cabecera `X-AT-Secret`) y arma un correo para la casilla de avisos del cierre.
- El flujo diario «Reseña · Diario 10:00» llama al mismo webhook una sola vez al final, y solo si ese día hubo pendientes.
- Si no hubo envíos, no sale correo.

### Cómo se lee el correo
- Asunto: «Reseñas enviadas: N de M con todo OK». Si fue manual, agrega « (envío manual)».
- ✓ llegó: por correo, el servidor lo aceptó; por WhatsApp, quedó entregado o leído.
- ✗ no salió, o Meta avisó que no se entregó.
- ⏳ sin confirmar todavía.
- — no correspondía, con el motivo: sin correo, sin teléfono, sin permiso de WhatsApp o pidió la baja.
- Las filas de prueba van marcadas «(prueba)».

### Pruebas (todas en `resena-suite.ps1`)
- `tests/plan-trabajo/resena-resumen-puras-test.php`
- `tests/plan-trabajo/resena-estado-test.php` (casos v8)
- `resena-resumen-correo-test.js`
- `resena-resumen-n8n-test.py` (flujo generado por `N8N/resena-google/resumen.py`)

### Despliegue (no lo hacen los subagentes; requiere el OK de Luis)
Orden obligatorio, porque ni el panel ni el diario deben llamar a un flujo que no existe:

1. **WordPress por SSH con llave** (`prod_ssh.py`), con respaldo de cada archivo en `~/respaldos/resena-resumen-antes-<fecha>/`, en este orden: `resena-puras.php` → `resena-datos.php` → `resena.php` → `avance-panel.php` (todos en `wp-content/themes/automatiza-tech/inc/plan-trabajo/`). Verificar desde afuera: `GET /wp-json/at/v1/resena/resumen?desde=…` sin cabecera → 401.
2. **n8n:** `resumen.py --apply` con las credenciales de los flujos actuales (la de entrada `X-AT-Secret` del webhook `at-resena-whatsapp`, la REST y la SMTP de los avisos de AT), con respaldo fuera del repo. Queda «Reseña · Resumen» inactivo y el diario parcheado y publicado.
3. **Activar** «Reseña · Resumen» y probar: POST al webhook con `{desde: <hace 1 hora>, origen: "manual"}`. Si no hubo envíos en esa hora, no llega correo (es lo correcto); la prueba real es el siguiente envío con clientes reales, cuando Luis dé las fechas de término.
4. Recién después, push y PR con el OK de Luis.

### Comprobaciones en la primera corrida real
- La credencial REST envía el mismo nombre de cabecera, `X-AT-Secret`, que valida el webhook. Comparar solo los nombres, nunca los valores.
- La espera de 15 minutos funciona aunque el flujo tenga `saveExecutionProgress=false`: en la primera corrida, ver en n8n que la ejecución queda «en espera» y que retoma sola y manda el correo.
- Hacer los pasos 2 y 3 seguidos y fuera de las 10:00, para que el diario no corra mientras el flujo del resumen todavía está inactivo.
- Después del PUT al diario, comprobar que `versionId == activeVersionId`.

### Si `--apply` falla a medias
Si falla después de crear «Reseña · Resumen», ese flujo queda creado e inactivo (no hace daño). Después, o se restaura el diario desde el respaldo, o se vuelve a correr a mano solo el parche del diario. El diario no debe quedar llamando a un flujo inactivo: mientras eso no se resuelva, no activar nada.
