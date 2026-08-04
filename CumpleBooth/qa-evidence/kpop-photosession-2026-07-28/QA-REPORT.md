# QA local — K-Pop photoSession

Fecha: 2026-07-28  
Entorno: WAMP local, `http://localhost/automatiza-tech/CumpleBooth/dist/?p=demo-kpop`

## Resultado

**APROBADO PARA REVISIÓN VISUAL DE LUIS.** No se realizó ningún deploy.

## Cobertura funcional

- El API público de `demo-kpop` respondió `ok:true`, con los diez invitados reales de demo y la configuración resuelta de `photoSession`.
- Caso fuera del trío: la ruleta eligió **Derpy** para Sofía. Cargó `saludo-derpy.mp4` y pasó al juego individual, sin solicitar `entrada-escenario.mp4`.
- Caso del trío: la ruleta eligió **Zoey** para Martina. Cargó `entrada-escenario-poster.jpg`, `entrada-escenario.mp4` y después `saludo-zoey.mp4`.
- Los recursos de escena, teasers, cortes PNG, juegos y música devolvieron HTTP 200/206 sin fallos de red.

## Pruebas automáticas

- `npm test`: **49/49** pruebas frontend correctas.
- `C:\\wamp64\\bin\\php\\php8.3.14\\php.exe scripts\\check-dist-parity.php`: **OK**, 237 archivos con paridad `public → dist`.
- Consola y errores del navegador: sin errores durante los recorridos comprobados.

## Pruebas visuales

- 8 pulgadas vertical: 768×1024 — inicio legible, sin corte de botón, marca y contenido dentro del kiosco.
- 10 pulgadas vertical: 800×1280 — inicio legible, sin corte de botón, marca y contenido dentro del kiosco.
- 10 pulgadas horizontal: 1280×800 — kiosco vertical centrado; quedan márgenes laterales esperables al preservar 9:16.

## Evidencia

- `screenshots/01-inicio.png`
- `screenshots/04-ruleta-lista.png`
- `screenshots/05-post-ruleta.png` (caso Derpy, sin photoSession)
- `screenshots/07-segunda-ruleta.png` (caso Zoey, pase de artista)
- `screenshots/09-tablet-8in-768x1024.png`
- `screenshots/10-tablet-10in-1280x800.png`
- `screenshots/11-tablet-10in-portrait-800x1280.png`

## Nota de alcance

La cámara física no se validó desde el navegador automatizado, porque requiere el permiso de dispositivo real de la tablet. Debe revisarse una vez manualmente en el equipo final antes de publicar.
