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
