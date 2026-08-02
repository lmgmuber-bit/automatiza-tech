# Reels listos para publicar — CumpleClick Fase 1

Generados 2026-07-19 (ffmpeg, 720×1280@25fps, h264). **Sin audio a propósito**:
la música se agrega NATIVA en la app de Instagram al publicar (mejor alcance del
algoritmo y cero riesgo de derechos — la música de las temáticas es licenciada
solo para el kiosco).

| Archivo | Duración | Contenido | Semana del calendario |
|---|---|---|---|
| `reel-00-presentacion.mp4` | 14.2s | Kiosco bajo arco → flash del globo-lente → endcard CTA | S1 |
| `reel-01-hook.mp4` | 9.6s | "¿Qué hace que TODOS los niños corran al mismo lugar?" → endcard | S2 lunes |
| `reel-02-demo.mp4` | 13.3s | Demo real: 1) elige tu nombre 2) tu personaje te recibe (pantallas reales del kiosco Bluey) 3) ¡Click! → endcard | S2 jueves |
| `reel-03-tematicas.mp4` | 14.2s | Salas de Carreras y Bluey ("…y muchos mundos más") → endcard | S3 |
| `reel-04-fechas.mp4` | 9.6s | Etiqueta reclamada + "AGOSTO: quedan 3 fechas" + precios → endcard | S4 |

Al publicar cada uno:
1. Agregar música trending desde la app de IG.
2. Caption según voz de marca (`../../MANUAL-DE-MARCA.md` §5) + máx. 3 hashtags.
3. Link de WhatsApp en bio; el CTA del endcard apunta ahí.
4. `reel-04`: cuando cambien las fechas del mes, regenerar SOLO el overlay de
   texto y remontar (fuente del pipeline: `scratchpad build-reels.sh` de la
   sesión, overlays HTML en design/… — o pedirle a Claude que lo remonte).

Fuentes: clips Higgsfield en `../` y `../campania-fase1/`; overlays renderizados
con la Baloo 2 real del producto (HTML→PNG transparente, Chrome headless).
Las capturas del kiosco son del flujo real en `?p=demo-bluey` (nota: la ruleta
sacó a Chloe y fue directo al saludo — el pase condicional Bluey/Bingo quedó
comprobado E2E en esa sesión).
