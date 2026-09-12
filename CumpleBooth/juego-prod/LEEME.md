# Archivos del juego que viven en PROD

Esta carpeta existe para que estos archivos no dependan de una carpeta temporal.

El juego 3D no se construye desde este repositorio —su código vive en
`C:\wamp64\www\tucumple-repo\` y el Festival en `C:\wamp64\www\tucumple-codex\`—, pero varias
piezas se escribieron directamente contra producción durante la sesión del 2026-09-07 y
quedaban solo en el scratchpad de esa sesión. Al borrarse el scratchpad, la única copia habría
sido el servidor.

## Qué hay acá y dónde va en PROD

| Archivo local | Destino |
|---|---|
| `menu/index.html` | `app/juego/index.html` — el menú de juegos |
| `mundo.html` | `app/juego/mundo.html` — el juego de siempre, con el aviso de girar |
| `orientacion.js`, `orientacion.css` | `app/juego/` — aviso de girar y pantalla completa |
| `game/posiciones.js` | `app/juego/game/posiciones.js` — reporta el resultado |
| `game/main.js` | `app/juego/game/main.js` — parcheado para llamar a lo anterior |
| `festival.htaccess` | `app/juego/festival/.htaccess` — **sin esto el Festival no arranca** |
| `festival-src/*` | `app/juego/festival/` (los `.mjs` van en `src/`) — solo los archivos que se modificaron |
| `impulso-aracnido.htaccess` | `app/juego/impulso-aracnido/.htaccess` — **sin esto Impulso no arranca** |

`impulso-aracnido.htaccess` es el mismo caso que el del Festival: el juego llegó sin
`.htaccess` propio y el servidor no conoce el tipo `.mjs`. Sin esa línea la pantalla queda
negra y la consola no dice nada útil. El juego completo (64 archivos de ejecución, 20,6 MB)
sale de `C:\wamp64\www\impulso-aracnido\`, rama `codex/equipo-y-colores`, commit `5fff942`;
acá no se versiona, igual que el Festival.

`festival-src/` **no es el juego completo**: son únicamente los archivos que se cambiaron
respecto de la entrega de Codex. El juego entero (58 archivos, 19,44 MB) sale de
`C:\wamp64\www\tucumple-codex\` en la rama `codex/festival-estrellas`, commit `0b68f50`.

## Antes de tocar cualquiera de estos archivos

`app/juego/?p=<slug>` **está impresa en los carteles QR** de las fiestas del 13 de septiembre.
Un código QR impreso no se puede corregir el día de la fiesta.

```bash
python scratchpad/urls-criticas.py antes    # guarda el estado de las 10 direcciones en uso
# ... el cambio ...
python scratchpad/urls-criticas.py despues  # compara y avisa si alguna cambió
```

El detalle completo, con las decisiones de diseño y lo que no está probado, está en
`docs/FTP-MANIFEST.md`, sección **DESPLEGADO 2026-09-07 (noche)**.
