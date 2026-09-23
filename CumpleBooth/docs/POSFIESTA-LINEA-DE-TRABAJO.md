# Posfiesta CumpleClick: la línea de trabajo (para cualquier agente)

**Léelo antes de tocar nada después de una fiesta.** Desde el 2026-09-22 la entrega posfiesta no se improvisa: se
repite la línea que Luis aprobó pieza por pieza con las fiestas de Samantha (Hielo) y Luciano (Aventura Arácnida) del
13-sep-2026. Este archivo dice **dónde está todo y en qué orden leerlo**; el contenido vive fuera del repositorio porque
son videos, material de familias y herramientas de edición que no se publican en GitHub.

## Dónde está cada cosa

| Qué | Dónde | Para qué |
|---|---|---|
| **La guía (qué se entrega, cómo, reglas, Instagram, lista de cierre)** | `C:\Users\luis_\Videos\cumpleclick-reels\GUIA-POSFIESTA.md` | Es la fuente. Empezar por aquí. |
| El diario técnico (qué se probó, qué falló, por qué, costos) | `C:\Users\luis_\Videos\cumpleclick-reels\LEEME.md` | Antes de repetir un error ya medido. |
| Herramientas de edición (todo ffmpeg, sin DaVinci) | `…\cumpleclick-reels\herramientas\` (`componer.py`, `secuencia.py`, `tarjetas.py`, `voz-alice.py`, `zoom-foto.py`, `encuadrar.py`, `fondo-tematica.py`, `qa-reel.py`, `grabador\mapear-album.js`, `grabador\grabar-album-hojas.js`, `secuencia-album.py`) | Reproducir los tres videos. |
| Scripts de PROD y del álbum (copia durable) | `…\cumpleclick-reels\herramientas\prod\` (`prod_ssh.py` + `album-916\` + `luciano-spidey-hf\` + cliente `hf_api.py`) | Bajar fotos del kiosco, pósters, música por fiesta, silenciar un video, ordenar, Higgsfield por API. Los originales están en el scratchpad de Claude (`scratchpad/album-916/`, `scratchpad/prod_ssh.py`). |
| Material y planes por fiesta | `…\cumpleclick-reels\material\<slug>\` (`plan-reel-v4.json`, `plan-reel-cabina-v10.json`, `recursos\plan-reel-album-v2.json`, `textos-instagram.md`, `mapa-album\`) | Los planes de Luciano son la plantilla. |
| Videos finales | `…\cumpleclick-reels\finales\` (`reel-<slug>-vN-instagram.mp4`, `reel-cabina-…`, `reel-album-…`) | Lo que se publica. |
| Costos de Higgsfield por fiesta | `…\herramientas\prod\luciano-spidey-hf\reporte.md` (request_id y USD por pieza) | Referencia de presupuesto: Luciano $6,79 de lista. |
| Credenciales | `C:\Users\luis_\OneDrive\Documentos\APIS KEy\APIS KEY.txt`, por etiqueta ("ACCESO CUMPLE CLICK SSH", "API KEY Higgsfield + N8N", "alice") | Nunca copiarlas a un archivo, un log ni el chat. |
| Historia y decisiones | Vault `10-Projects/CumpleClick.md` (secciones 2026-09-17 a 2026-09-22) | Contexto largo. |

## La línea, en diez líneas

1. Se entrega: el **Álbum Recuerdo en línea** a los papás (enlace + PIN por WhatsApp, curado, con pósters y música por
   fiesta), **tres videos** (reel de la fiesta ≈ 90 s, reel de la cabina 90–93 s, video del Álbum ≈ 70 s) y **los
   textos** (WhatsApp a los papás, tres textos de Instagram con primer comentario).
2. Caras de niños solo con autorización de sus papás. Sin nombres de franquicias en tarjetas, prompts ni en la voz de
   Alice. Marca de agua arriba a la izquierda. Tres hashtags fijos: `#CumpleClick #CumpleañosInfantiles #PhotoBoothChile`.
3. Alice (ElevenLabs) ≈ 400 caracteres por fiesta, aprobados por Luis; siempre transcribir para comprobar.
4. Higgsfield **solo por la API REST** (`hf_api.py`): estimar, **presentar el costo, esperar el OK**, generar, bajar de
   inmediato, anotar en `reporte.md`. Un OK cubre ese plan, no lo que se descubra después.
5. Reel de la fiesta: gancho → mundo IA → cumpleañero real → "entra al cómic" → fotos → kiosco/juegos → piñata/vela →
   **despedida de los personajes + Alice a la cumpleañera** → foto de los niños → **foto de los papás + gracias** → cierre.
6. Reel de la cabina: personajes IA + "Así funcionó…" → bienvenida completa → niños reales con fondo de la temática (videos
   mudos, cada niño una vez) → ruleta, rompecabezas, Asómate, diplomas, impresión, grupal → despedida completa → gancho.
7. Video del Álbum: **solo hojas importantes** (portada, cumpleañero, dedicatorias, dos del kiosco, dedicatoria de los
   papás, última hoja) con `mapear-album.js` → `grabar-album-hojas.js` → `secuencia-album.py --hojas` → `componer.py`.
8. Todo a 1080×1920, 30 fps; master + `-instagram` a −14 LUFS; QA con `qa-reel.py`.
9. Instagram: fiesta primero, álbum a los 2–3 días, cabina a la semana; papás como colaboradores; portada con el nombre.
10. Cambios en PROD (pósters, música, silenciar videos) siempre con respaldo en `~/respaldos/`, subida a nombre temporal,
    cotejo sha256, `mv` atómico y verificación desde afuera; solo con el go de Luis.

## Lo que un agente nuevo NO debe hacer

- Adivinar la estructura de un video o el tono de un texto: están en la guía y en `material/luciano-spidey/`.
- Generar con Higgsfield sin presentar el costo, o por el MCP (sin saldo desde el 21-sep).
- Usar el recorte cuadro a cuadro con rembg en videos de niños: falló dos veces; lo que sirve es Seedance 2.5 video-edit.
- Grabar el álbum entero hoja por hoja: son 60+ hojas; se mapea, se eligen y se graba solo lo elegido.
- Subir la música del álbum al repositorio (es un cover con derechos; vive solo en el servidor y está en `.gitignore`).
