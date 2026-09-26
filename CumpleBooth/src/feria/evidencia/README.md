# AT-CUMPLECLICK-021 — evidencia de revisión

Base: origin/main 4cdc795. Rama: codex/modo-feria-kiosco. Revisor: Claude; aprobador: Luis.
Implementación local, sin merge ni despliegue. Costos: 0 créditos; request_id: no aplica.

## Qué se probó

- Selector compilado y de desarrollo: modos habilitados, mundos, autorización infantil, retorno a espera a los 45 segundos.
- Kiosco infantil: nombre opcional, ruleta, personaje, cámara, foto numerada, QR, diploma con feria y fecha.
- Adultos: acceso directo a cámara sin personajes, sin diploma ni autorización infantil; los tres mundos adultos reales de la rama 020.
- Recorte con ImageSegmenter real en worker, máscara suave, fotograma sincronizado y fondo completo. Filtro blanco y negro sobre toda la foto, incluida la franja.
- Descarga/subida, error de reserva, error de subida y reintento sin reservar otro número. Cámara denegada con reintento.
- Modelo inaccesible y timeout: conserva la misma foto en el marco. Vista previa bajo 15 FPS: recorte solo en foto final.
- Retorno al selector tras 60 segundos desde el QR, incluso si se abre el diploma.
- Regresión: una fiesta normal conserva bienvenida/lista de invitados y no se activa como feria por parámetros URL. El backend real devuelve su respuesta normal idéntica byte a byte.
- PHP de Claude sin modificaciones, SQLite y almacenamiento temporales; cuatro subidas ligadas a número en cc_feria_fotos.

## Resultado

npm test con integración real habilitada: **236 tests, 236 pass, 0 fail, 0 skipped**.
npm run build: **exit 0**. Sigue la advertencia de tamaño del chunk existente de Three.js (~734 kB); no es un fallo.
Capturas: 1200 × 2000. Son Chrome en PC, no una emulación del rendimiento físico de la Tab A7.

Las capturas usan la imagen pública de prueba de MediaPipe (retrato oficial de un adulto) y temáticas existentes.
No son fotos de clientes ni piezas para redes sociales. Fuente de prueba:
https://storage.googleapis.com/mediapipe-assets/portrait.jpg

## Rendimiento en PC

Ver mediciones.json: Chrome 153.0.8010.53, worker CPU, entrada canvas de 600 × 800 y composición de 1080 × 1920.
La composición incluye recorte, franja, filtro y exportación JPEG; excluye reserva/subida HTTP.
Los FPS se redondean a un decimal para registrar; la decisión de 15 FPS se toma antes de redondear.
La prueba inicial mide 2 segundos y continúa reevaluando cada 2 segundos mientras se mantiene la vista previa.

## Reproducir

Desde CumpleBooth, después de npm ci y npm run build:

    $env:CHROME_PATH = 'C:/Program Files/Google/Chrome/Application/chrome.exe'
    $env:PHP_PATH = 'C:/wamp64/bin/php/php8.3.28/php.exe'
    $env:CC_FERIA_BACKEND_ROOT = 'C:/wamp64/www/automatiza-tech/.worktrees/modo-feria/CumpleBooth'
    $env:CC_FERIA_ARTIFACTS = '<carpeta de evidencias fuera del repo>'
    $env:CC_FERIA_PORTRAIT = '<retrato público de prueba local>'
    npm test

Sin CC_FERIA_BACKEND_ROOT, solo se omite el caso opcional contra PHP; las pruebas unitarias y de navegador sí se ejecutan.
Las demás pruebas ya existentes conservan su configuración. El servidor de integración escucha solo en 127.0.0.1 y usa una SQLite nueva, nunca configuración de PROD.

Para repetir en la tablet, conectarla por USB, autorizar depuración, abrir Chrome y ejecutar la prueba anterior con:

    $env:CC_FERIA_ADB = '<ruta a platform-tools/adb.exe>'
    node --test tests/frontend/feria-integracion.test.mjs

La prueba crea un tab separado, abre un túnel USB local y lo retira al terminar. No cierra los otros tabs de la tablet.
No activar CC_FERIA_ADB para toda la suite npm: solo para el caso de integración, una vez que adb indique device.

## Límites y decisiones para Claude

- Medición física en Galaxy Tab A7 y cámara frontal real: pendiente de conexión USB de Luis. No extrapolar los FPS del PC.
- No se probó Hostinger, HTTPS/CSP de PROD, impresión Selphy ni uso continuo durante una feria.
- El backend no informa si una subida quedó ligada al número; acepta reservas inválidas/usadas y guarda la foto de todos modos. Tras una respuesta perdida, un reintento podría crear una copia sin vínculo. No se cambió el contrato: revisar idempotencia/confirmación con el dueño del 020.
- El diploma infantil se descarga en la tablet; el QR corresponde a la foto. No se añadió un endpoint ni una segunda reserva para el diploma.
- Modelo oficial fijado en src/feria/models (dentro del alcance). Vite lo publica en vendor/mediapipe, junto al WASM existente. SDK del worker emitido desde la dependencia ya instalada.
- El video de espera y los fondos adultos los entrega el 020. Si falta el video, el selector mantiene el botón para comenzar.
- No subir dist completo: contiene copias del PHP previo al 020. Usar únicamente la lista FTP de este ticket e integrar primero el manifiesto de Claude.
- Graphify se consultó para navegar. No se persisten cambios de su grafo/caché por la restricción de archivos del ticket.
