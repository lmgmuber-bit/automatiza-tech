# CumpleClick — Fase 1 implementada

La Fase 1 entrega tres capacidades integradas: foto con personaje recortado,
diploma personalizado y galería privada para familias.

## Foto premium y frames

- Cada personaje puede tener `<base>-cut.png`; si falta, la composición continúa.
- La foto se recorta en cuadrado y se centra dentro del marco dorado ya pintado
  en el fondo usando `party.frameBox`; no se dibuja un segundo marco encima. El
  admin persiste un override por fiesta o restaura el default del tema.
- El personaje sorteado se escala y apoya centrado sobre la pista inferior, sin
  tapar foto ni copy. Preview y Diploma añaden el SVG oficial AT como marca de
  agua discreta, sin tarjeta blanca ni texto recreado.
- Baloo 2 600/700/800 está autoalojada y cargada antes del canvas.

## Diploma

Después del QR se genera un PNG 9:16 con nombre del invitado, título del tema,
fiesta, marco/sello y respaldo AT. La imagen vertical del personaje que ganó la
ruleta se usa como fondo del Diploma, con una veladura para conservar legibilidad;
si no carga, se mantiene el fondo temático de respaldo. La misma espera de fuentes
evita el fallback a Arial/Segoe UI. El archivo se descarga localmente desde la
tablet.

## Galería y fotos

- PIN de 4 dígitos guardado como `password_hash(HMAC(PIN, pepper))`.
- Editar con PIN vacío conserva el actual; una acción explícita lo elimina;
  duplicar una fiesta siempre nace sin PIN.
- Fotos nuevas se guardan fuera del webroot y se sirven con token opaco mediante
  `ver.php`; galería y ZIP consultan metadata de BD.
- Cuotas: 200 fotos/1 GiB por fiesta; aviso de operación al 80 % en admin.
- Compatibilidad de lectura con enlaces/fotos legacy durante estabilización.

## Estado verificado 2026-07-13

- 5/6 personajes de Carreras tienen cut-PNG; falta `mate-cut.png`.
- Chrome real completó intro → invitados → ruleta → personaje → cámara → Preview
  → QR → Diploma. Upload y URL opaca funcionaron; consola sin errores.
- `document.fonts.check()` devolvió `true` para Baloo 2 600/700/800.
- El 2026-07-14 se revalidaron Preview y Diploma en Chrome con cámara simulada:
  foto cuadrada alineada, personaje centrado en pista, marca de agua AT visible y
  consola sin errores.
- Se cambió un frame en admin, la API/kiosco consumieron el nuevo valor y luego se
  restauró el valor original.
- Se corrigió el arranque local de cámara: la UI espera eventos reales del video,
  detecta streams sin fotogramas, ofrece selector cuando hay varias cámaras y no
  habilita el disparador antes de recibir imagen. Si Chrome elige una cámara
  virtual, prioriza automáticamente el primer dispositivo físico; el feed de
  `Integrated Camera` fue verificado localmente. La cámara física de la tablet
  final sigue siendo un gate operativo de hardware.
- El backoffice conserva las cards de temáticas y añade una ficha por tema con
  inventario visual, metadatos y prompts privados editables protegidos por CSRF,
  allowlist y validación obligatoria de camuflaje.

La generación de assets sigue las reglas de camuflaje de
`CUMPLECLICK-HANDOFF-CODEX.md`: describir rasgos físicos y nunca nombrar personaje
o franquicia en prompts para Gemini.

## Extensión de producción manual — 2026-07-26

- Admin/Temáticas incluye prompts de imagen y video, historial, copia, carga
  individual al slot exacto, filtros y previsualización.
- La demo Tropical está lista para QA con sus assets existentes.
- K-Pop y Héroes tienen datos, juegos, prompts y demos de preproducción; el
  inventario del admin muestra exactamente qué multimedia debe adjuntar Luis.
- Los puzzles no se generan: se recortan 900×900 desde el retrato aprobado.
  Tampoco se genera música; `musica-fondo.mp3` y `musica-juego.mp3` los aporta
  Luis.
- La galería permanece desactivada en las demos; se habilita solo por plan
  contratado y decisión del admin.

## Sitio público y captación — 2026-08-01

- La landing comercial aislada está disponible en `sitio/` y no reemplaza ni
  modifica el flujo del kiosco `?p=<slug>`.
- El formulario registra solicitudes en `cc_leads`, dentro de la BD independiente
  de CumpleClick, mediante `POST sitio/api/contacto.php`.
- Cada registro recibe una referencia opaca `CC-...`; la respuesta pública nunca
  revela el ID interno. Se guarda consentimiento versionado y solo HMAC de
  IP/user-agent.
- El endpoint exige JSON, limita el cuerpo a 16 KiB, valida campos y fecha,
  incorpora honeypot y aplica un límite persistente de 5 solicitudes cada
  10 minutos con bloqueo de 15 minutos.
- La migración `006_public_leads` es compatible con MySQL y SQLite. El CRUD base,
  validaciones y lints fueron verificados en PHP 8.0/8.2/8.3/8.4; el envío HTTP
  real fue validado contra WAMP y los datos de QA se limpiaron al finalizar.
