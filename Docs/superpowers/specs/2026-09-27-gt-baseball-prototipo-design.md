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
