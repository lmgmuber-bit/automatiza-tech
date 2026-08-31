# Imágenes requeridas (8 archivos, 1080×1920, JPG)

⚠️ REGLA DE ORO: las imágenes NO llevan texto ni nombres.
El nombre del cumpleañero/a y los mensajes salen del código (App.jsx + config.php).
Así el mismo set de imágenes sirve para CUALQUIER niño con la misma temática.

| Archivo             | Contenido                                                        |
|---------------------|------------------------------------------------------------------|
| fondo-banner.jpg    | Escena grupal de la temática (intro). SIN texto ni pancartas con letras |
| fondo-sala.jpg      | Sala de fiesta con MARCO DORADO VACÍO (blanco por dentro) centrado |
| {Personaje1}.jpg    | Personaje 1 en primer plano, resto del grupo desenfocado atrás   |
| {Personaje2}.jpg    | Personaje 2 en primer plano, ídem                                |
| {Personaje3}.jpg    | Personaje 3 en primer plano, ídem                                |
| {Personaje4}.jpg    | Personaje 4 en primer plano, ídem                                |
| {Personaje5}.jpg    | Personaje 5 en primer plano, ídem                                |
| {Personaje6}.jpg    | Personaje 6 en primer plano, ídem                                |

- Nombres de archivo SIN espacios ni acentos (ej: RayoMcQueen.jpg, no "Rayo McQueen.jpg")
- Deben coincidir con CHAR_IMG en src/App.jsx
- Los prompts probados para Gemini están en el skill birthday-photobooth
  (~/.claude/skills/birthday-photobooth/SKILL.md)

## Audio requerido (public/audio/)
- musica-fondo.mp3 → canción de la temática, se reproduce en loop

## Tras calibrar el marco de fondo-sala.jpg
Usar el calibrador visual del admin. El override `x/y/w/h` queda en la BD de la
fiesta; el kiosco consume exactamente el `party.frameBox` resuelto por la API.
