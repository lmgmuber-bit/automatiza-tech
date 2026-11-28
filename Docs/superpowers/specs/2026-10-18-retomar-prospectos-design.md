# Retomar prospectos: recordatorios automáticos para volver a conversar

Fecha: 2026-10-18 · Autor: Eje (Claude, plataforma AT) · Decisiones de Luis (Presidente) marcadas como tales; las que
son de Eje dicen «decisión de Eje». Repositorio público: aquí no van nombres ni datos de prospectos; la lista del
piloto vive fuera del repo, en `C:/Users/luis_/Documents/Codex/Shared-Tools/Retomar-Prospectos-Piloto.md`.

## 1. Para qué

Hay prospectos a los que les presentamos propuesta (y en general nos reunimos), pero la conversación se quedó ahí:
más de dos semanas sin respuesta ni avance. Luis quiere recuperarlos sin rehacer la venta: recordarles en qué quedamos,
mostrarles otra vez la propuesta y la demo, y decirles que **podemos adaptarnos a lo que necesitan y a su
presupuesto**, partiendo por lo prioritario y dejando el resto para más adelante (como se hizo con un cliente que
pasó de una propuesta completa a una fase 1 más chica). Lo único que se le pide al prospecto: **agendar una reunión
con Luis**.

Éxito: el prospecto agenda (o responde) y la conversación se retoma. Fracaso aunque funcione técnicamente: que el
prospecto se sienta hostigado, que Meta o el correo nos marquen como spam, que se le escriba a quien dijo que no, o
que salga algo roto o con datos equivocados.

## 2. Decisiones de Luis (18-oct)

1. **Automático.** Los envíos salen solos; Luis no aprueba cada uno.
2. **Sin hostigar:** como máximo **una vez por semana**, respetando las políticas anti-spam de WhatsApp (Meta) y de
   correo.
3. **Botón «No me interesa»** en cada mensaje: con un clic el prospecto queda de baja y no se le vuelve a escribir.
4. **Llamado a la acción:** agendar una reunión con Luis, con el enlace de agenda, igual que en las propuestas.
5. **Presentación** con la plantilla que ya tenemos (el propuesta-renderer), mostrando: en qué quedamos, la demo, lo
   que proponemos y que se puede ajustar.
6. **WhatsApp automático con plantilla aprobada por Meta.** Luis sabe que Meta puede no entregar mensajes de marketing
   (error 131049) y quiere probar igual: se arma la plantilla y se manda a aprobación.
7. **Parametrizable y reutilizable** para cualquier prospecto, no solo los del piloto.
8. **Piloto con un prospecto**; cuando a Luis le guste, autoriza que siga en automático con los demás.

## 3. Decisiones de Eje (Luis puede cambiarlas)

- **Tope:** 4 recordatorios por prospecto, uno cada 7 días (día 0, 7, 14 y 21). Luis no fijó el número; queda como
  parámetro por prospecto (máximo y días entre envíos).
- **Canales:** correo en los 4; WhatsApp solo en el 1.º y el 4.º, para cuidar el límite de marketing de Meta y el
  costo. Parametrizable por prospecto.
- **Horario:** días hábiles, entre 10:00 y 13:00, hora de Chile. Nunca fines de semana.
- **Se detiene solo** con: baja («No me interesa»), reunión agendada, paso a cliente en el CRM, propuesta aceptada o rechazada,
  tope alcanzado, o pausa manual. Si el prospecto responde por correo o WhatsApp, Luis marca «Respondió» en el panel
  (detectar respuestas automáticamente queda fuera de esta versión).
- **Sin precios nuevos** en la presentación: se dice que se puede ajustar al presupuesto y se conversa en la reunión.
- **Cada envío deja rastro** en el historial del CRM (`wp_crm_historial`) y en la línea de tiempo.

## 4. Qué se construye

### 4.1 Sección del panel «🔁 Retomar prospectos»
Una sección nueva del CRM (submenú) con la lista de prospectos en seguimiento: estado, próximo envío, envíos hechos
y resultado de cada uno. Para cada prospecto, un formulario:
- ficha del CRM (se crea si no existe: varios prospectos hoy no tienen ficha);
- propuesta vinculada, si existe en el sistema; si no, la URL de su propuesta externa;
- URL(s) de la demo (pueden ser dos);
- textos de la presentación: «en qué quedamos», «lo que proponemos», «cómo podemos partir» (fase prioritaria);
- parámetros: máximo, días entre envíos, canales;
- botones: «👁️ Vista previa de la presentación», «📧 Enviarme una prueba» (correo y, si se puede, WhatsApp al número
  de Luis), «▶️ Activar», «⏸️ Pausar», «✅ Respondió», «Dar de baja».
Mientras no se active, nada sale. Activar el piloto y los siguientes es decisión de Luis.

### 4.2 La presentación «Retomemos»
Nuevo `document_type` en el propuesta-renderer, con el estilo de las propuestas y sin generar fotos nuevas (usa las de
la propuesta o las que se carguen). Láminas: portada con el negocio del prospecto · en qué quedamos · tu demo (captura
y enlace) · lo que proponemos (resumen) · podemos partir por lo prioritario y ajustarnos a tu presupuesto · agenda
una reunión. Se publica en una página propia con token (como `ver-plan.php`), `noindex`, con el botón de agenda.

### 4.3 El correo
HTML con el estilo de los correos de AT y alternativa en texto. Asunto distinto en cada envío (ej.: «¿Retomamos tu
proyecto, <nombre>?»). Botones: «Ver la presentación», «Agendar una reunión» y, al pie, «No me interesa, no volver a
escribirme». Cabeceras `List-Unsubscribe` (URL de baja firmada y `mailto:`) y `List-Unsubscribe-Post: List-Unsubscribe=One-Click`.
Remitente y SMTP actuales de AT. Nunca enlaces con `easypanel` (regla D8: Hostinger los marca como spam).

### 4.4 El WhatsApp
Plantilla nueva de Meta, categoría Marketing, español, enviada por la Cloud API con el número de WhatsApp Business de
AT, por el mismo camino que ya usan las plantillas de propuestas (flujo n8n con `X-AT-Secret`, wamid de vuelta y
estados `failed` que llegan por el webhook del bot a WordPress). Borrador de la plantilla (nombre `retomar_propuesta`):

> Hola {{1}} 👋 Te saluda el equipo de AutomatizaTech. Hace unas semanas conversamos sobre {{2}} y quedó pendiente.
> Te dejamos un resumen de lo que vimos y tu demo. Si algo no calza con lo que necesitas o con tu presupuesto, lo
> ajustamos y podemos partir por lo más importante. ¿Agendamos 20 minutos para conversarlo?

Botones (dos de tipo URL, con sufijo dinámico): «Ver y agendar» → página de la presentación con agenda; «No me
interesa» → página de baja firmada. Así la baja no depende de procesar respuestas del webhook.
Reglas del checklist anti-spam de AT: estados `pendiente` → `enviado` | `error`; un error se registra y **no se
reintenta solo**; un 131049 no se reintenta antes de 24 h y, en esta versión, simplemente no se reintenta.

### 4.5 El reloj
Un flujo n8n con disparador programado (días hábiles, 10:00 Chile) llama a un endpoint de WordPress protegido con el
secreto existente. WordPress decide quién toca hoy, envía los correos, registra todo y devuelve a n8n los WhatsApp que
corresponden; n8n los envía y reporta el resultado (wamid o error). No se usa el cron de WordPress (depende de visitas)
ni crontab (Hostinger no lo da por SSH).

### 4.6 Baja y agenda
- **Baja:** página pública con token firmado (HMAC); muestra «Listo, no te volveremos a escribir» y deja el seguimiento
  en `baja` con fecha. Idempotente; sin login.
- **Agenda:** el mismo agendador de las propuestas (crea la reunión de seguimiento, horario hasta las 21:00). Si se
  crea una reunión con el correo del prospecto después de activar el seguimiento, el seguimiento pasa a `agendo` y se
  detiene.

### 4.7 Datos
Tabla nueva `wp_at_retomar` (migración con opción de esquema, como en el resto del tema): prospecto (ficha CRM),
propuesta, URLs, textos, parámetros, estado (`borrador`, `activo`, `pausado`, `agendo`, `respondio`, `cliente`,
`baja`, `agotado`), token, próximo envío y un registro de envíos (n.º, fecha, canal, resultado, wamid o error). La
presentación se guarda como las demás (URL de vista y PDF del renderer).

## 5. Lo que no hace (esta versión)

- No genera propuestas ni precios nuevos, ni demos (las demos se hacen aparte; ver la lista privada del piloto).
- No detecta respuestas de correo ni de WhatsApp: Luis marca «Respondió».
- No le escribe a clientes ni a prospectos sin seguimiento activo.
- No reintenta envíos fallidos solo.

## 6. Pruebas y salida a producción

- Pruebas en el sitio WordPress local de pruebas, como las de plan de trabajo y entregables: cálculo de quién toca hoy
  (tope, intervalo, días hábiles, pausas), cada condición de parada, firma y uso único del token de baja, cabeceras del
  correo, regla D8, y que un error de WhatsApp no se reintente.
- Renderer: prueba del nuevo `document_type` en Chromium (que nada se salga de las láminas), como `template-estado`.
- Piloto: prueba a Luis («Enviarme una prueba»), Luis revisa, Luis activa el piloto. Después, Luis autoriza a los demás.
- La plantilla de Meta se manda a aprobación con el OK de Luis; mientras no esté aprobada, el WhatsApp queda apagado y
  sale solo el correo.
- Despliegue con el procedimiento de siempre (respaldo, subida, verificación desde afuera) y la autorización de Luis.

## 7. Precisiones tras la revisión de un constructor (decisiones de Eje, 18-oct)

1. **Baja sin falsos positivos.** Los antivirus del correo (Defender, Gmail) abren todos los enlaces. Por eso:
   - el enlace «No me interesa» abre una página con **un solo botón grande**, «Sí, no volver a escribirme», que da de
     baja por POST;
   - la cabecera `List-Unsubscribe-Post` (un clic desde el propio cliente de correo, RFC 8058) da de baja directo;
   - no se usa `mailto:` en la cabecera, porque nadie procesa ese buzón;
   - el token es **idempotente**, no de un solo uso: volver a entrar muestra «ya no te escribiremos».
2. **Baja por persona.** Lista de supresión por correo y por teléfono normalizado, que se consulta al activar y en
   cada envío, también si el mismo correo aparece en otra ficha. Solo afecta a «Retomar»: los avisos de clientes y
   de reuniones ya agendadas siguen igual. Luis puede revertir una baja desde el panel, con motivo anotado.
3. **Agenda propia de Retomar.** No hay agendador en `ver-presentacion.php`. Se clona el de `plan-seguimiento`
   (`inc/plan-trabajo/agenda.php`) como ruta de Retomar, con su token, horarios hasta las 21:00, el mismo candado y
   el mismo límite de reuniones activas. La reunión se guarda en `automatiza_followup_meetings`. Se corrige el aviso
   que hoy trata como «cliente» cualquier correo que exista en `wp_crm_clientes` aunque sea `tipo='prospecto'`.
4. **Intervalo mínimo de 7 días**, que el formulario no deja bajar. El día de un envío, el correo y el WhatsApp
   cuentan como un solo contacto.
5. **Imagen de la demo:** Luis la sube en el formulario, o se reutiliza una foto de la propuesta vinculada. El
   renderer no saca capturas de sitios: evita riesgos y tiempos nuevos.
6. **Render:** con «Vista previa» y al guardar. La dirección es fija, así que los enlaces ya enviados muestran la
   última versión. Si el día del envío la presentación no está «Lista», ese envío espera al día hábil siguiente y
   queda anotado. Nunca se manda un enlace roto.
7. **n8n:** tres flujos nuevos, clonados de los que ya existen: el reloj, el render (el patrón de `plan-v1-render`,
   porque WordPress no tiene la clave del renderer) y el WhatsApp (el patrón de `Ex5wZac9VCc66WOm`). Quien construye
   entrega los JSON y los scripts. **Crearlos y activarlos en n8n es parte del despliegue, con OK de Luis.**
8. **«Lo que proponemos»** es texto libre que escribe Luis, sin importar tablas de precios.

**Respuestas de Luis:** ver §8.

## 8. Respuestas de Luis (18-oct)

- **A. Propuestas rechazadas:** no se le escribe a quien marcó «No acepto» en su propuesta (estado `rechazada`): se
  bloquea al activar y detiene un seguimiento activo. Las `archivada`, `sent` y `evaluando` sí entran.
- **B. Permiso para WhatsApp:** casilla «WhatsApp autorizado» por prospecto, que marca Luis cuando el prospecto le
  escribió o conversó con él por WhatsApp. Sin la casilla, solo sale el correo.
- Luis aprobó el diseño completo («tienes mi ok»).

## 9. Queda para quien lo construya

Nombres de funciones y archivos, el diseño visual exacto de las láminas y del correo dentro del estilo AT, y el
detalle del endpoint de n8n, siguiendo los patrones existentes de `inc/plan-trabajo/`, `inc/cierre-cliente/` y
`renderer/src/template-estado.js`.
