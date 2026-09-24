# Handoff Codex → Claude — invitación Reino de Hielo

Fecha: 2026-08-11  
Rama de trabajo: `codex/frozen-invitation-parity`  
Estado: local, sin commit, push ni deploy.

## Objetivo

Extender la experiencia ya aprobada de invitación de Carreras al tema
`hielo` (nombre público: Reino de Hielo / Frozen), para una cumpleañera.
La identidad debe salir del nombre dinámico de la invitación, no de texto
quemado en multimedia.

## Implementado

1. `public/invitacion.php` ahora contiene un mapa de playlists por tema.
   - Carreras conserva exactamente su orden y captions.
   - Hielo usa sus siete videos existentes:
     `saludo-elsa.mp4`, `saludo-anna.mp4`, `saludo-olaf.mp4`,
     `saludo-kristoff.mp4`, `saludo-sven.mp4`,
     `saludo-bruni.mp4` y `despedida-hielo.mp4`.
   - El modo `?capitulos=auto` detecta cualquier temática del mapa y no
     depende más de `saludo-mate.mp4`, que era exclusivo de Carreras.

2. La portada de Hielo dice **“El Reino de Hielo te invita a celebrar a:”**.
   Los captions de la playlist incorporan el nombre de la cumpleañera desde
   `birthday_person_name`. Todo se escapa/renderiza como HTML/JSON; no se
   agrega nombre a imágenes o videos reutilizables.

3. Se agregó el candidato de entrada
   `public/themes/hielo/invitation/candidate-hielo-auto.mp4`.
   - Solo se usa con `?hero=auto`.
   - No cambia la portada normal ni reemplaza
     `invitation-motion-v1.mp4`.
   - Está normalizado y verificado: 720×1280, H.264, yuv420p, 5.033 s,
     1,023,686 bytes, sin audio.

4. El candidato fue generado con autorización expresa de Luis en Higgsfield:
   `cinematic_studio_video_v2`, 9:16, 5 s, `sound=off`,
   `genre=spectacle`, costo confirmado de **5 créditos**. El prompt usa
   solamente un palacio cristalino original, nieve, hielo y luz; no nombra
   franquicias, personajes, menores, rostros, logos ni texto.

## Cómo probar

Usar una invitación publicada cuyo `theme_slug` sea `hielo` y agregar:

```text
?hero=auto&capitulos=auto
```

Flujo esperado:

1. La portada de apertura obliga un toque y habilita música/narración.
2. El candidato de palacio se reproduce una vez y bloquea scroll hasta terminar.
3. Aparece “Desliza para seguir”.
4. Corre la playlist de los siete videos de Hielo.
5. El nombre de la cumpleañera aparece en la portada y captions HTML.

La narración de inicio sigue siendo una salida aprobada individual de cada
invitación. Los MP3 de capítulos de `hielo/narracion-video/` aún no existen;
la playlist funciona sin ellos. Generarlos con ElevenLabs solo si Luis vuelve
a autorizar ese gasto.

## Validaciones realizadas

- `php -l public/invitacion.php`: correcto.
- `npm run build`: correcto.
- `npm test`: 101/101 correctos.
- Normalizador de video del proyecto: correcto.

## Límites y entrega

- No se modificó el Show 3D.
- No se tocó WAMP/root, base de datos local ni producción.
- No subir `storage/event-profile-demo/bases/candidate-hielo-auto-source-20260811.mp4`;
  es una copia temporal local del archivo fuente.
- Para FTP, si Luis lo autoriza más adelante:
  1. **OBLIGATORIO:** `dist/invitacion.php` →
     `/public_html/cumpleclick/invitacion.php`.
  2. **OBLIGATORIO:** `dist/themes/hielo/invitation/candidate-hielo-auto.mp4` →
     `/public_html/cumpleclick/themes/hielo/invitation/candidate-hielo-auto.mp4`.
  3. **OPCIONAL / solo tras revisión visual de Luis:** publicar el candidato.
  4. Nunca subir `storage/`, SQLite, archivos temporales, secretos ni
     configuraciones reales.

## Actualización — saludos inmersivos + Alice (2026-08-11)

Luis autorizó explícitamente generar multimedia y narración. Se crearon seis
videos de revisión, sin sobrescribir los saludos vigentes:

- public/themes/hielo/invitation/candidates/saludo-elsa-v2.mp4
- public/themes/hielo/invitation/candidates/saludo-anna-v2.mp4
- public/themes/hielo/invitation/candidates/saludo-olaf-v2.mp4
- public/themes/hielo/invitation/candidates/saludo-kristoff-v2.mp4
- public/themes/hielo/invitation/candidates/saludo-sven-v2.mp4
- public/themes/hielo/invitation/candidates/saludo-bruni-v2.mp4

Cada uno parte de su respectivo archivo *-cut.png, se generó con
kling3_0_turbo usando imagen inicial, 9:16, 720p y 5 s. Los prompts describen
solo rasgos visuales, palacio cristalino, movimiento hacia adelante y nieve;
no incluyen nombres de franquicias, personajes, logos ni texto. Costo
confirmado antes de enviar: 7,5 créditos por clip, 45 créditos total.

Los seis están normalizados: H.264, yuv420p, 720×1280, duración 5,039 s y
peso entre 1,0 y 1,7 MB. Las fuentes descargadas quedan solo en
storage/event-profile-demo/bases/candidate-hielo-*-source-20260811.mp4;
storage/ está ignorado y nunca se sube.

También se generaron con ElevenLabs (voz Alice Xb7hH8MSUJpSbSDYk0k2,
modelo eleven_multilingual_v2) estas locuciones fijas reutilizables:

- public/themes/hielo/narracion-video/saludo-elsa.mp3
- public/themes/hielo/narracion-video/saludo-anna.mp3
- public/themes/hielo/narracion-video/saludo-olaf.mp3
- public/themes/hielo/narracion-video/saludo-kristoff.mp3
- public/themes/hielo/narracion-video/saludo-sven.mp3
- public/themes/hielo/narracion-video/saludo-bruni.mp3

La clave externa permitió TTS; el endpoint de saldo no tiene permiso
user_read, por lo que no se pudo consultar crédito disponible. No se guardó ni
expuso ninguna credencial.

### Prueba aislada

public/invitacion.php acepta ahora el parámetro capitulos=candidatos
exclusivamente para hielo. Este modo reproduce los seis MP4 nuevos más
despedida-hielo.mp4, mientras la playlist normal capitulos=auto conserva los
MP4 anteriores. Al derivar Alice, quita el sufijo de versión -v2, por lo que
saludo-elsa-v2.mp4 usa narracion-video/saludo-elsa.mp3. La despedida reutiliza
assets/audio/narracion-final.mp3.

Para revisar, usar una invitación local de Hielo con hero=auto y
capitulos=candidatos.

No hubo sustitución automática: Luis debe revisar los seis clips y dar la
orden expresa antes de reemplazar saludo-*.mp4 o cambiar el modo normal.

### Validación posterior

- PHP 8.2: sintaxis correcta en public/invitacion.php.
- npm run build: correcto (solo advertencia existente de chunk grande).
- npm test: 101/101 correctos.
- scripts/check-dist-parity.php: correcto (342 archivos).
- git diff --check: correcto.

### FTP si Luis aprueba un deploy futuro

Destino base según docs/FTP-MANIFEST.md: /public_html/cumpleclick/.

1. OBLIGATORIO: dist/invitacion.php →
   /public_html/cumpleclick/invitacion.php.
2. OPCIONAL, solo para revisión remota: los seis
   dist/themes/hielo/invitation/candidates/saludo-*-v2.mp4 →
   themes/hielo/invitation/candidates/.
3. OBLIGATORIO junto al modo de prueba: los seis
   dist/themes/hielo/narracion-video/saludo-*.mp3 →
   themes/hielo/narracion-video/.
4. No subir storage/, SQLite, fuentes descargadas, logs, secretos ni
   configuraciones reales.

### Guion fijo de Alice

- saludo-elsa.mp3: “Las puertas del reino se abren y Elsa llega para celebrar.”
- saludo-anna.mp3: “Anna trae una chispa de alegría para esta gran fiesta.”
- saludo-olaf.mp3: “Olaf prepara una sorpresa llena de nieve y risas.”
- saludo-kristoff.mp3: “Kristoff ya está listo para acompañar esta aventura mágica.”
- saludo-sven.mp3: “Sven viene galopando entre copos para celebrar.”
- saludo-bruni.mp3: “Bruni enciende una lucecita de magia: la fiesta está por comenzar.”

### Corrección de rutas de candidatos

Los nombres de clips candidatos incluyen subcarpetas. invitacion.php ahora codifica
cada segmento de la ruta por separado, conservando la barra entre invitation y
candidates. Así el navegador solicita el MP4 real y no una ruta con la barra
codificada como %2F.

## Actualización — apertura celeste y variante scroll (2026-08-11)

La portada de entrada de Hielo ya no usa la cortina genérica oscura. Solo para
data-theme=hielo, usa una composición celeste de cristal y la tarjeta clara;
hero y playlist también tienen fondo celeste mientras carga el primer frame.
Los demás temas no cambian. Se subió la versión de CSS a v=4 para evitar caché.

Se añadió public/themes/hielo/invitation/candidate-hielo-scroll.mp4. Es una
derivación sin gasto nuevo de candidate-hielo-auto.mp4, reencodada para
scroll-scrub: H.264, yuv420p, 540×960, 30 fps, keyframe en cada cuadro,
sin audio, 5,033 s y 2.819.858 bytes.

public/invitacion.php ahora habilita hero=scroll para Hielo mediante un mapa
por tema; Carreras conserva exactamente su propio candidato scroll. La ruta
del asset se codifica por segmentos, por lo que las subcarpetas invitation/
candidates no se transforman en %2F.

Pruebas locales:
- hero=auto y capitulos=candidatos: entrada automática, luego seis candidatos
  de personajes con Alice.
- hero=scroll y capitulos=candidatos: la entrada responde al scroll, luego la
  lista de los mismos seis candidatos con Alice.

Validación: PHP 8.2 correcto, build correcto, 101/101 tests, paridad
public a dist correcta (343 archivos), y video scroll servido por HTTP 200.
No commit, push ni deploy.

FTP futuro, solo si Luis lo autoriza: añadir como OBLIGATORIO junto a la
variante scroll dist/themes/hielo/invitation/candidate-hielo-scroll.mp4 hacia
/public_html/cumpleclick/themes/hielo/invitation/candidate-hielo-scroll.mp4.
No subir storage/ ni fuentes temporales.

## Actualización — marco de invitación y sobre (2026-08-11)

A pedido de Luis, el marco dorado de Hielo ya no presenta texto pequeño sobre
un área blanca vacía. Solo cuando theme_slug es hielo, invitacion.php compone
sobre el fondo reutilizable una tarjeta de cristal HTML con:

- copos y estrella decorativos;
- nombre dinámico en tamaño protagonista;
- cápsulas con iconos de fecha, hora y ubicación;
- fecha y dirección provenientes de la invitación, sin texto quemado.

Los demás temas conservan exactamente el marcado previo. invitation.css v=5
añade estilos Hielo de tarjeta celeste y agranda/contrasta el texto del sobre:
título azul profundo y pista en cápsula clara. No se regeneró ninguna imagen ni
video.

Validación posterior: PHP 8.2 correcto, build correcto, 101/101 tests y
paridad public a dist correcta (343 archivos). El servidor local de revisión
continúa en el puerto 8094; refrescar la URL para recibir invitation.css?v=5.

FTP futuro si Luis lo autoriza: OBLIGATORIO subir dist/invitacion.php y
dist/assets/invitation.css a sus rutas equivalentes bajo
/public_html/cumpleclick/. No subir storage/, SQLite, logs o fuentes privadas.

## Ajuste v3: CTA, ambiente celeste y protagonistas

- El CTA visual de la invitación ahora dice **“Ver invitación”** en todas sus apariciones.
- `public/assets/audio/narracion-final.mp3` fue regenerado con Alice y el texto: “Toca aquí para ver la invitación a la fiesta.”
- Se añadió una capa celeste exclusiva del reproductor Hielo para que los capítulos no arranquen sobre negro y subtítulos/controles sigan por encima.
- En modo `?capitulos=candidatos`, Anna, Sven y Bruni usan nuevos videos `v3`; los archivos anteriores `v2` siguen intactos:
  - `saludo-anna-v3.mp4`: protagonista joven-adulta con vestido formal azul hielo.
  - `saludo-sven-v3.mp4`: reno a distancia media/lejana, máximo 35% del alto.
  - `saludo-bruni-v3.mp4`: espíritu pequeño, máximo 22% del alto.
- Los tres fueron generados con imagen inicial, Kling 3.0 Turbo, 5 s verticales, y normalizados a H.264/AAC, 720x1280, 5.039 s.
- No se cambió el recorrido aprobado por defecto; esta revisión queda tras `?capitulos=candidatos`.