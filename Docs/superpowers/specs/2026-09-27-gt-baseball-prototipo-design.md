# GT Baseball Academy — prototipo de inscripción con QR (fase 1)

Fecha: 2026-09-27 · Rama: `claude/gt-baseball-prototipo` · Estado: diseño aprobado por Luis en el chat.

## Por qué

El cliente (academia de béisbol en Venezuela, prospecto de la propuesta AT id 42) no aceptó la propuesta
completa de USD 500: primero quiere abrir la academia. El 24-sep se acordó por audio una fase 1 mucho
más chica: los apoderados escanean un QR impreso en la academia, llenan la planilla de inscripción en el
celular con la foto del niño, y a la academia le llega un correo por cada inscrito y una fila en un
Google Sheet. Sin base de datos. Mientras tanto corre en el dominio de AT; después se apunta al dominio
que compre el cliente. La propuesta 42 queda como fase 2.

Los datos personales del chat (correos, teléfonos) no van al repositorio: viven en la bóveda privada.

## Qué se construye

1. **Landing + formulario** — `demos/gt-baseball/` (estático; se publica en
   `https://automatizatech.cl/demos/gt-baseball/`). No toca WordPress ni `functions.php`.
   - Portada corta: logo, «Inscripciones abiertas», tres pasos y el botón. Nada de horarios, categorías
     ni precios: el cliente no los ha dado.
   - Formulario por pasos:
     - **Atleta:** foto (galería o cámara, se achica en el teléfono), nombre completo, fecha de
       nacimiento (la edad se calcula), cédula o pasaporte, nacionalidad, teléfono, correo electrónico,
       dirección.
     - **Representante:** nombre y apellido, teléfono.
     - **Béisbol:** liga o programa, posición, batea, lanza, estatura, peso, referencia de millas
       (opcional), año de firma (opcional).
     - **Aceptación** del uso interno de los datos (texto de su planilla de papel).
   - Quitados a pedido del cliente: nivel académico y entrenador. También se quita «entrena en».
   - Al enviar, el navegador arma la planilla en PDF (jsPDF desde cdnjs, con logo y foto, igual a la de
     papel y con la línea de firma en blanco) y muestra «Descargar mi planilla».
2. **Flujo n8n «GT Baseball · Inscripciones (prototipo)»**:
   Webhook público POST → validar → foto y PDF a una carpeta de Drive → fila en el Google Sheet →
   correo con el PDF y la foto adjuntos → responder `{ok:true}`.
   - Credenciales existentes: `Google Drive account`, `Google Sheets PROD`, `SMTP account PROD`.
   - Validación en el nodo Code: campo trampa vacío, obligatorios presentes, largos máximos, foto JPEG
     y PDF reales (bytes mágicos) y de tamaño acotado. Si falla, 400 y no escribe nada.
   - CORS: solo `https://automatizatech.cl` y el origen local de prueba.
   - Destinatarios en el flujo, nunca en el formulario. Un envío con `prueba=1` se marca PRUEBA y va
     solo a Luis. El cliente se agrega como destinatario recién cuando el prototipo está verificado
     (autorizado por Luis el 27-sep).
   - El constructor `N8N/gt-baseball/build.py` lee los destinatarios y los ids de Drive/Sheet desde un
     archivo local fuera del repo; el JSON generado tampoco se versiona.
3. **Cartel QR** A4 para imprimir (`demos/gt-baseball/cartel.html`) y el QR suelto en PNG/SVG.
4. **Video del recorrido como apoderado** (celular simulado): escanear, llenar, foto, enviar, planilla.

## Qué no se hace

Base de datos, cuentas de usuario, pagos, métricas de entrenadores, planilla de evaluación (fase 2 o
cuando el cliente diga qué lleva), ni compartir el Sheet con el cliente sin el ok de Luis.

## Seguridad y datos

- El webhook es público por naturaleza (cualquier formulario lo es). Protección: campo trampa, límites de
  tamaño, validación estricta y destinatario fijo en el servidor.
- Datos de menores: no se guardan en servidores de AT; quedan en el Drive del Sheet y en el correo.
- El formulario muestra el aviso de uso interno de la academia y pide aceptarlo.

## Pruebas

- Local: formulario en el servidor de wamp contra el webhook real con `prueba=1`; revisar fila, archivos
  en Drive y correo.
- n8n: envíos inválidos (sin foto, trampa llena, PDF falso) → 400 y sin fila.
- PROD: después de subir, verificar por HTTP desde afuera (200, tipos MIME) y un envío real `prueba=1`.
- Móvil: 375×812 sin scroll horizontal; foto desde archivo.

## Despliegue

Autorizado por Luis el 27-sep: subir `demos/gt-baseball/` a `public_html/demos/gt-baseball/` por SFTP
(carpeta nueva, sin sobrescribir nada) y crear y activar el flujo nuevo en n8n. Verificar desde afuera
antes de reportar.

---

## Versión 2 (diseño aprobado por Luis el 27-sep, noche)

La v1 quedó congelada: etiqueta git `gt-baseball-v1`, tar de lo publicado y JSON del flujo en
`C:\Users\luis_\respaldos\deploy-scripts\2026-09-27-gt-baseball\v1\` y `~/respaldos/gt-baseball-v1-20260927.tar.gz`.

**Lectura de diseño:** portada y formulario de inscripción para apoderados venezolanos que llegan por un QR
desde el celular; lenguaje deportivo sobrio con el negro y dorado de la marca; HTML/CSS/JS nativos (Hostinger
estático, sin build). Diales: variación 5, movimiento 4, densidad 4. Skills usadas: `ui-ux-pro-max`
(instalada para Claude ese día), `design-taste-frontend` (solo portada: la skill excluye formularios de varios
pasos), `frontend-design` y las guías `harden`, `polish` y `clarify` de impeccable.

**Auditoría medida de la v1 (motivo de cada cambio):** botón principal bajo el pliegue en 360×640 (682 px);
borde de campos 1,49:1 (mínimo 3:1), placeholder 3,75:1 y pie 4,07:1 (mínimo 4,5:1); subtítulo de 25 palabras;
validación solo al enviar y errores sin anunciar; en escritorio, columna de celular con los costados vacíos;
íconos dibujados a mano (WhatsApp no oficial); guiones largos en la edad.

**Cambios:**
1. Portada: botón visible sin bajar en cualquier teléfono; subtítulo de 20 palabras o menos; «Cómo funciona» y
   «Qué necesitas a mano» en secciones propias; dos columnas en escritorio con el logo grande (imagen elegida).
2. Fecha de nacimiento con tres listas (día, mes, año) y control de fechas imposibles.
3. Foto con «Tomar foto» y «Elegir de la galería», más consejo de encuadre.
4. Pasos con nombre (Atleta, Contacto, Béisbol, Enviar); validación al salir de cada campo; errores con
   `aria-invalid`, `aria-describedby` y región `aria-live`.
5. Borrador en el teléfono (localStorage, con try/catch): se ofrece continuar; se borra al enviar.
6. Envío con XHR y barra de progreso real; aviso sin conexión, sin perder datos.
7. Final: «Compartir planilla» (Web Share con archivo, si el teléfono lo permite) y «Descargar planilla».
8. Tipografía Barlow Condensed + Barlow; íconos Tabler (una familia) y WhatsApp de Simple Icons; contrastes
   corregidos; transiciones de 250 ms o menos, apagadas con `prefers-reduced-motion`.
9. Tema oscuro por defecto (marca) y **botón para cambiar a modo claro**, recordado en el teléfono; se aplica
   antes de pintar para que no parpadee.
10. **Todo responsivo:** 360, 375, 768, 1024 y 1440 px, en los dos temas; el cartel se ve completo en pantalla.
11. Planilla PDF: se quitan los caracteres que la fuente del PDF no puede dibujar (emojis).

Se mantienen los nombres de los campos y el formato del envío: el flujo de n8n no cambia.
Publicación: primero en local con capturas; se sube a PROD solo con el ok de Luis.

**Agregado durante la construcción de la v2 (pedidos de Luis el 27-sep):**
- Mientras procesa, una pelota de béisbol girando dentro de un anillo dorado con el avance real de la subida.
- Dos correos por inscripción: al apoderado (resumen y planilla; responder a la academia) y a la academia
  (todos los datos, planilla y foto). El correo del representante pasa a ser obligatorio. Límite de envíos por
  IP y por correo contra el uso del formulario para mandar correos a terceros.
- Validación de cada campo, también de los opcionales, repetida en el servidor: nombres con letras y apellido,
  teléfonos de 10 a 15 dígitos, cédula o pasaporte, correo con aviso de dominios mal escritos, estatura, peso,
  millas y año de firma en rangos razonables; teléfonos, millas y año no aceptan letras al escribir.
- Flujo aparte en n8n («GT Baseball · Inscripciones v2», webhook `gt-baseball-inscripcion-v2`) para no romper
  la v1 publicada; se activa con el ok de Luis junto con la publicación.

**Verificado en local:** 50 combinaciones (5 tamaños × 2 temas × 5 pantallas) sin desborde horizontal ni errores;
17 casos de reglas del servidor y 23 del navegador; pantallas de error (límite, servidor, sin correo, sin
conexión) conservando el borrador. Hallazgos corregidos: la barra fija de abajo tapaba toques en el celular
(`pointer-events` y `scroll-padding`, WCAG 2.4.11) y una regla de etiquetas pisaba los botones de foto.

## Paso de pago y v2 en su propia dirección (27-sep, noche)

**Pedido.** Jeffer, por audio (27-sep, 20:16; transcrito en local con Whisper): que el apoderado envíe el
comprobante de la inscripción junto con la planilla; la academia corrobora el pago y recién ahí lo agrega al
grupo de WhatsApp; «por ahora» mostrar los datos de pago móvil, Zelle y efectivo al finalizar la planilla.
Luis: agregar ese paso al final y que el correo también lo indique.

**Diseño.**
- Paso 4 «Pago», entre «Béisbol» y «Enviar» (el formulario pasa a 5 pasos). Formas de pago en tarjetas;
  al elegir una se ven sus datos con «Copiar» (cédula y teléfono sin puntos ni guion, listos para la app
  del banco). Banco de Venezuela con su código 0102, verificado en la lista de códigos de pago móvil.
- Comprobante: captura (se achica a 2000 px por el lado largo y va en JPEG) o PDF de hasta 3 MB.
  Si todavía no pagó, puede marcar «lo envío después» (respondiendo el correo o por WhatsApp): la
  inscripción no se frena y el control lo hace la academia al agregar al grupo. `permitir_despues: false`
  en la configuración lo vuelve obligatorio. El efectivo se paga en la oficina y no pide comprobante.
- Resumen con el pago; pantalla final con el próximo paso según el caso: «Pago en revisión», «Falta el
  comprobante» (con los datos y un botón de WhatsApp con el mensaje escrito) o el efectivo en la oficina.
- Correos: a la academia, el siguiente paso arriba (verificar y agregar al representante, con su teléfono,
  al grupo de WhatsApp), el comprobante adjunto y el estado del pago en el asunto; al apoderado, el paso
  del pago arriba con los datos para pagar cuando falta el comprobante.
- Sheet: dos columnas nuevas al final, «Forma de pago» y «Comprobante» (enlace, Pendiente o No aplica).
- Los datos de pago son personales (cédula, teléfono, correo de Zelle) y el repo es público: viven en el
  bloque `pago` de `gt-config.json`, que el despliegue pone en la página (`#datos-pago`) y `build.py` en el
  flujo. Una sola fuente para la página y los correos.

**v2 aparte.** Luis pidió que la v2 no reemplace a la v1: la v1 sigue en `/demos/gt-baseball/` (la
dirección que ya tiene Jeffer) con su flujo activo, y la v2 va en `/demos/gt-baseball/v2/` con su flujo
propio. El despliegue compara la huella md5 de cada archivo de la v1 antes y después de subir, y la
verificación revisa la v1 contra su respaldo congelado. El cartel y el QR de la v2 apuntan a `/v2/`.

**Verificado en local** (copia armada con los datos como en PROD y receptor simulado): 45 comprobaciones
del paso de pago en el navegador (formas de pago, copiar, validaciones, captura, PDF, PDF falso o de más
de 3 MB, borrador, tres finales, otro atleta); 14 reglas de pago y 22 comprobaciones de fila, adjuntos,
asuntos y correos en el código de los nodos; las 17 reglas anteriores; 60 combinaciones (5 tamaños ×
2 temas × 6 pantallas) y 17 anchos de 320 a 1920 px sin desborde ni errores; contraste de lo nuevo en los
dos temas (el borde de la tarjeta elegida usa el dorado oscuro en modo claro: 3,9:1).

**Pendiente:** publicar con el ok de Luis (flujo v2 primero, después la página en `/v2/`, prueba real con
`?prueba=1`); agregar en el Sheet los encabezados de las dos columnas nuevas; preguntar a Jeffer el monto
de la inscripción y si el comprobante debe ser obligatorio.

## Firma del atleta desde los 15 años (27-sep, noche)

Pedido de Luis: desde los 15 años el atleta también firma la planilla, junto a su representante, y hay que
indicarlo. Con 15 años o más (edad a la fecha de la inscripción), la fila de firmas pasa a tener tres líneas
(«Firma del representante», «Firma del atleta» y «Fecha») y debajo la nota «A partir de los 15 años, el
atleta también firma la planilla, junto a su representante». Con menos de 15 queda como antes. El correo al
representante («Como Santiago tiene 16 años, al imprimirla la firma también, junto a ti») y la pantalla final
(«Al imprimirla, la firman Santiago y su representante») lo avisan. La edad límite es una constante:
`EDAD_FIRMA_ATLETA` en `assets/planilla.js` (página) y en `build.py` (correo). Verificado con planillas de
12, 14, 15 y 16 años, una con datos muy largos (la nota queda 10 mm sobre el pie, todo en una hoja) y los
PDF de envíos reales de 13 y 16 años; las pruebas anteriores siguen pasando.

## Monto de la inscripción (27-sep, noche)

Luis: la inscripción es de 25 $ (dólares); Zelle también se usa en Venezuela (no decir «desde Estados Unidos»);
por el Banco de Venezuela son 25 $ a la tasa oficial, y eso debe quedar escrito y claro. Quedó así, desde el
bloque `pago` de `gt-config.json`:
- Portada: «La inscripción es de 25 $ (dólares).» Paso de pago: «Monto de la inscripción: 25 $ (dólares)».
- Tarjetas: pago móvil «Banco de Venezuela, en bolívares a tasa oficial»; Zelle «En dólares»; efectivo «En la
  oficina de la academia».
- Al elegir, la primera fila es «Monto a pagar»: por pago móvil «El equivalente a 25 $ en bolívares, a la tasa
  oficial del BCV del día» (el BCV publica a diario el tipo de cambio oficial); por Zelle y en efectivo «25 $».
- La misma fila va en la pantalla final, en el recuadro del correo al representante cuando falta el comprobante
  o paga en efectivo, y en la sección «PAGO» de los dos correos.
- Supuesto a confirmar con Jeffer: en efectivo, 25 $ (dólares); si también acepta bolívares, se cambia en la
  configuración.

Sigue sin poder saltarse el pago: elegir la forma de pago es obligatorio y, por pago móvil o Zelle, hay que
adjuntar el comprobante o marcar «lo envío después» (página y servidor). Verificado: 49 comprobaciones en el
navegador, 44 en el código de los nodos, 17 reglas, 6 de la firma, 17 anchos y 60 combinaciones.

## Portada con preguntas, 5 pasos y botón «Salir» (27-sep, noche)

- «¿Cómo funciona?» y «¿Qué necesitas a mano?» llevan sus signos de pregunta (Luis).
- «¿Cómo funciona?» pasó de 3 pasos a los 5 del formulario, con los mismos nombres de la barra (Paso 1 · Atleta,
  Paso 2 · Contacto, Paso 3 · Béisbol, Paso 4 · Pago, Paso 5 · Enviar) y una línea de en qué consiste cada uno;
  el paso 4 dice el monto (desde la configuración), las tres formas de pago y que el comprobante se puede enviar
  después. En escritorio, título a la izquierda y pasos en vertical a la derecha.
- «Salir» en la barra de pasos (bajo la lista en escritorio): pregunta «¿Deseas salir de la inscripción?», guarda
  el borrador y el inicio ofrece continuar o empezar de nuevo. Pedido de Luis: desde el paso 3 había que tocar
  «atrás» varias veces para volver al inicio.
- Video del recorrido con la voz de Alice (ElevenLabs): la frase del pago dice Zelle, efectivo o bolívares por pago
  móvil, a la tasa oficial del Banco Central de Venezuela (Luis). 102 s; guion y tomas fuera del repo.

Verificado: 18 comprobaciones de «Salir» (celular, 320 px y escritorio) y las pruebas anteriores (49 + 44 + 17 + 6,
17 anchos, 60 combinaciones).

## Campos blindados y cartel con 5 pasos (28-sep, madrugada)

Pedido de Luis: el teléfono debe ser un número venezolano válido de 11 dígitos, y blindar los demás campos para
que la planilla sea lo más fidedigna posible. Mismas reglas en la página y en el servidor:
- Teléfono: 11 dígitos con el 0. Celulares 0412 y 0422 (Digitel; el 0422 desde julio de 2025, según la sala de
  prensa de Digitel), 0414 y 0424 (Movistar), 0416 y 0426 (Movilnet); fijos 02XX. Se acepta sin el 0 o con +58 y
  queda como 0412-1234567. El del representante debe ser celular (es el WhatsApp del grupo) o un número de otro
  país con + y su código (hay representantes con WhatsApp de afuera); el del atleta acepta además fijos.
- Nombres: nombre y apellido completos, sin iniciales («Juan P.» no pasa); «de», «la», «del»… no cuentan como
  palabra. Todo en minúsculas o todo en mayúsculas se ordena al salir del campo.
- Cédula: V o E y 6 a 8 números, queda V-30.123.456; si no, pasaporte de 6 a 12 letras y números con algún número.
- Dirección: al menos dos palabras y 10 caracteres. Correo en minúsculas y más errores típicos detectados.
- Estatura y peso quedan uniformes («1,65 m», «55 kg» o «120 lb»). La foto de menos de 300 px por lado se rechaza.
- Los datos ordenados son los que van al resumen, la planilla, el envío, los correos y el Sheet.
- Cartel A4: los 5 pasos con los nombres del formulario (Atleta, Contacto, Béisbol, Pago, Enviar).

Verificado: 31 casos del servidor y 33 del navegador para estas reglas; las pruebas anteriores siguen pasando
(49 + 44 + 17 + 6 + 18); 17 anchos y 60 combinaciones sin desborde ni errores; cartel en una hoja con el QR legible.

## Video guía, secciones de la academia y publicación de la v2 (28-sep)

**Video guía de ejemplo.** Luis: montar el video del recorrido en la plataforma como guía, sin hacer uno nuevo;
el definitivo, pensado para los representantes, se graba cuando la plataforma pase al dominio comprado.
- En la portada, un enlace en el encabezado («Mira el video guía (1:42)») y una tarjeta en «¿Cómo funciona?».
- Se abre en un diálogo. No se descarga hasta abrirlo (`preload="none"`) y se pausa al cerrar con la X, con Esc
  o tocando fuera.
- Los subtítulos están disponibles pero apagados, porque el video ya trae el texto en pantalla.
- El video muestra los datos de pago, así que no va al repo: `deploy_gt.py` lo publica desde `archivos_extra`
  de `gt-config.json`.

**Secciones de la academia.** «¿Quiénes somos?» (texto, misión y visión) y «¿Dónde estamos?» (dirección, pago
en efectivo en la oficina y WhatsApp). Llevan la etiqueta «Texto de ejemplo» o «Dirección de ejemplo» hasta que
Jeffer mande lo real. Si la dirección es de una casa particular, se pone desde la configuración y no desde el repo.

**Publicación (28-sep, con el ok de Luis).**
- Flujo «GT Baseball · Inscripciones v2» (`jsqxDfoWvJbDVAUa`, 17 nodos) activo; no guarda las ejecuciones que
  salen bien. El flujo de la v1 sigue activo.
- Sheet: encabezados X1 «Forma de pago» e Y1 «Comprobante», escritos solo después de confirmar que la fila 1
  llegaba hasta la W.
- 16 archivos en `/demos/gt-baseball/v2/`, iguales a los locales por md5; son los 13 del repo más los 3 del video
  guía. La v1 tiene sus 13 archivos con la misma huella antes y después de subir, e iguales a su respaldo congelado.
- Verificación desde afuera: todo responde 200. El video sale como `video/mp4`, los subtítulos como `text/vtt`, y
  el `.htaccess` está bloqueado (403).
- Prueba real con `?prueba=1` (inscripción `GT-MUKP0MF9PL`, atleta de 16 años, pago móvil con comprobante):
  - respuesta 200 en 16,2 s;
  - pantalla final «Pago en revisión», con el aviso de la firma;
  - fila 12 del Sheet, con la forma de pago y el enlace del comprobante;
  - dos correos, fuera de spam: el de la academia con la planilla, la foto y el comprobante, y el del
    representante con la planilla y la frase de la firma.

**Dominio.** Luis quiere un .com y eligió `gtbaseball.com`. Al 28-sep está libre: el RDAP de Verisign responde 404
(con `google.com` como control, que responde 200) y el DNS da NXDOMAIN. Falta comprarlo. Al mudar hay que cambiar:
- la dirección de la página, el QR y el cartel;
- `ORIGENES` (CORS) en `build.py`;
- la URL del logo en los correos;
- las redirecciones desde `/demos/gt-baseball/` y `/v2/`.
El video definitivo se graba ahí.

## Textos de la academia y dirección real (28-sep, tarde)

- **Quiénes somos, misión y visión.** Jeffer no los tenía y Luis pidió que los redactara AutomatizaTech, de acuerdo
  con el servicio. Salen de lo que Jeffer contó en sus audios y de su planilla, sin inventar años de experiencia,
  logros ni cifras:
  - es una academia familiar (él y su esposa);
  - entrena a niños y jóvenes;
  - la planilla lleva posición, estatura, peso, velocidad en millas y año de firma: de ahí salen el seguimiento de
    cada atleta y la proyección al béisbol profesional.
  La academia puede ajustarlos.
- **Dirección**, enviada por la academia: Av. 19, calle 8, Sierra Maestra, Maracaibo, estado Zulia; detrás del Centro
  Clínico Sergio Pérez. El nombre de la clínica no aparece en internet, así que no se agregó un botón de mapa: se
  agrega cuando la academia mande su ubicación exacta.
- Se quitaron las etiquetas de ejemplo, y la descripción de la página ahora nombra la ciudad.
- **Verificado:**
  - 26 comprobaciones de la portada;
  - de 320 a 1440 px sin desborde, en modo oscuro y claro, con contraste de 7,3:1 o más;
  - publicado en `/v2/` con respaldo previo de la v2; la v1 quedó igual a su respaldo congelado.

## Pruebas exhaustivas antes del dominio (28-sep)

Jeffer compró el dominio; antes de mudar, se corrió una batería amplia.

**Sin efectos, todo verde:** las suites anteriores (224 comprobaciones) más `probar-seguridad.cjs` (30): inyección
(honeypot, id con `<script>`, HTML en nombres → rechazados), **escape del correo** (la dirección no filtra `< >`,
pero los dos correos la escapan: sin XSS almacenado), tamaños y bytes mágicos (foto > 2 MB, PNG disfrazado de foto,
planilla sin `%PDF`, comprobante que no es JPEG ni PDF → rechazados), campos gigantes recortados, rangos de edad,
posición y forma de pago fuera de lista.

**Seguridad desde afuera:** el Sheet responde 401 y los archivos de Drive redirigen a iniciar sesión (los datos y
las fotos de menores **no son públicos**); el listado de carpetas está apagado (403). Falta endurecer cabeceras:
se agregó `demos/gt-baseball/.htaccess` (se despliega a `/v2/.htaccess`) con `X-Content-Type-Options`,
`X-Frame-Options: DENY`, `Referrer-Policy` y `Permissions-Policy`. HSTS y una CSP estricta se dejan para el dominio
propio (son políticas de todo el host).

**Concurrencia** (`carga-concurrencia.py`: flujo temporal aislado con el código real de «Validar», sin Drive, Sheet
ni correos; borrado al terminar):
- **20 inscripciones distintas a la vez → las 20 se guardan con id único.** El caso real de un día de inscripciones
  funciona: la escritura del Sheet (`values:append` con `INSERT_ROWS`) es atómica y no pierde filas.
- 🔴 **El límite por IP/correo y el anti-duplicado NO frenan una ráfaga simultánea:** 5 envíos del mismo id a la vez
  pasaron los 5, y 15 desde una misma IP a la vez pasaron las 15. Es una carrera de los datos estáticos de n8n: las
  ejecuciones corren en paralelo y todas leen el contador antes de que alguna lo escriba. Sí funcionan cuando los
  envíos llegan **separados** (el reintento tras timeout —el caso común— queda cubierto por el dedupe de 10 min).

**Correcciones aplicadas (en `build.py`, sin publicar aún):**
- **Solo v2:** el flujo v2 ahora rechaza cualquier envío que no sea `v:2` (antes aceptaba el formato viejo, más laxo).
- **Anti-duplicado por id (10 min):** un reintento del mismo id se responde `ok` sin volver a guardar la fila ni
  mandar los correos; «Responder error» devuelve 200 en ese caso.
- Las dos, verificadas en `probar-seguridad.cjs`; las 110 comprobaciones del flujo siguen verdes.

**Recomendado para el dominio (no bloquea, pero cierra la ráfaga):** Cloudflare Turnstile (gratis, casi invisible)
como barrera anti-bot en el borde. Es la capa que frena una ráfaga simultánea antes de llegar a n8n; el límite de
n8n queda como mejor-esfuerzo para repeticiones sueltas. Necesita la cuenta de Cloudflare de Luis y una clave.

## Mudanza al dominio propio gtbaseball.com (28-sep, EN PROD)

Jeffer compró el dominio en Hostinger; Luis lo agregó al mismo plan (Business, 7/50 sitios) como sitio «Sube tu
PHP o HTML», con su **carpeta propia** `domains/gtbaseball.com/public_html`, aislada de los otros seis dominios.

- **Despliegue:** `deploy_dominio.py` (en los respaldos), con la carpeta destino FIJA y un candado que aborta si la
  ruta real no es la de gtbaseball.com; respalda y quita el `default.php` de Hostinger solo de esa carpeta. 17
  archivos, iguales por md5. El sitio queda en la **raíz** de `https://gtbaseball.com/` (no en `/v2/`).
- **Cambios del dominio:** QR y cartel al dominio (QR v2, 25 módulos, decodifica `https://gtbaseball.com/`); `ORIGENES`
  del flujo v2 con gtbaseball.com + www; logo de los correos al dominio; cabeceras completas con **HSTS** y una **CSP**
  a la medida (propios + Google Fonts + jsPDF por cdnjs + el webhook de n8n en `connect-src`).
- **Redirecciones:** las viejas `/demos/gt-baseball/` (v1) y `/v2/` dan **301** a `https://gtbaseball.com/` (un
  `.htaccess` con `RewriteRule` en cada carpeta; respaldo en `~/respaldos/gt-redirect-*`; no había `.htaccess`
  antes). La v1 y su flujo quedan intactos, solo redirigen.
- **Verificado desde afuera:** 16 archivos iguales por md5; las seis cabeceras de seguridad presentes; `.htaccess`
  bloqueado (403); envío real `GT-MULSF0R6OP` en el dominio con respuesta 200, **sin bloqueos de CSP ni CORS ni
  errores**, y los dos correos recibidos (con el logo del dominio); redirects 301 sin bucle.
- **Pendiente:** el video para los representantes apuntando al dominio (borrador `guion-guia.json`) y Cloudflare
  Turnstile (necesita la cuenta de Cloudflare de Luis).
