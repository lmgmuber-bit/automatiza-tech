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
