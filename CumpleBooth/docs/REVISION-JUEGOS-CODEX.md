# Revisión de los dos juegos arácnidos de Codex

Fecha: **2026-09-09**. Nada de esto está desplegado. Falta la autorización de Luis.

Revisión hecha comparando **archivo por archivo contra el servidor**, no leyendo la
documentación de la entrega. Lo que sigue son hallazgos verificados, con el comando que los
produjo indicado donde importa.

---

## 1. Lo que ya coincide con PROD y lo que falta

### Circuito Arácnido — no está en producción, nada de esto existe allá

| Pieza | Estado en PROD |
| --- | --- |
| `app/juego/circuito/` | **No existe.** 38 archivos, 34 MB por subir |
| `lib.sala.carrera.php` | **No existe.** Todo el backend de carreras falta |
| Tabla `cc_sala_carreras` | **No existe.** Migración 021 sin aplicar |
| `sala.php`, `puntajes.php`, `lib.puntajes.php` | Existen, y hay que **injertarles** lo de carreras |

### Impulso Arácnido — ya está en producción, y hay 8 archivos nuevos

PROD tiene 66 archivos en `app/juego/impulso-aracnido/`; el repositorio de Codex tiene los
mismos 66 más uno. Comparados por md5:

- **7 distintos:** `estilo.css`, `index.html`, `src/datos.mjs`, `src/entorno.mjs`,
  `src/main.mjs`, `src/mundo.mjs`, `src/reglas.mjs`.
- **1 nuevo:** `src/entrada.mjs` (619 bytes), el control de mantener/soltar.
- **1 solo en PROD:** `.htaccess`. 🔴 Es el que declara el tipo de los `.mjs`; sin él la
  pantalla queda negra sin error visible. **No se toca y no se borra.**
- `src/posiciones.mjs` **coincide byte a byte**: la integración de puntajes que hice antes
  sigue en pie. Verificado además que `src/main.mjs` del árbol local conserva el
  `import { anotar } from './posiciones.mjs'` y la llamada dentro de `terminar()`.

---

## 2. 🔴 Tres cosas que se romperían subiendo la rama tal cual

Este es el hallazgo importante. La rama `codex/narracion-alice` se separó **antes** de tres
cambios que hoy están vivos en producción. Sus versiones de los archivos compartidos están
atrasadas, y subirlas borraría trabajo que ya funciona:

1. **`puntajes.php` perdería la lista de invitados.** La versión de la rama no devuelve
   `invitados` ni `edad`. Es justo lo que pediste el 2026-09-08: que el niño elija su nombre
   de una lista en vez de escribirlo. Volverían a teclearlo a mano.
2. **`lib.puntajes.php` borraría Impulso del catálogo.** La rama no tiene la entrada
   `impulso`, así que Impulso Arácnido **desaparecería del menú** de las fiestas spidey.
3. **`orientacion.css` y `orientacion.js` volverían atrás.** La rama trae la versión que
   **obliga** a girar el aparato; PROD ya tiene el botón "Jugar así". Quien tenga el giro
   bloqueado en los ajustes quedaría encerrado en el aviso.

**Por eso la integración se hizo al revés:** se tomó el archivo de PROD y se le injertó solo
lo que Codex agrega. Los tres archivos ya están armados y pasan `php -l`. Los de orientación
y `vendor/` no se tocan.

Control después del injerto: los seis juegos siguen en el catálogo
(`reino-hielo`, `festival`, `aracnida`, `impulso`, `circuito`, `mision`) y `puntajes.php`
conserva las seis menciones de `invitados`.

---

## 3. Lista exacta de subida

Preparado en `scratchpad/integracion-circuito/`.

### Paso 1 — base de datos (antes que nada)

| Archivo | Qué hace |
| --- | --- |
| `database/migrations/021_sala_carreras.php` | Crea `cc_sala_carreras`, con clave foránea a `cc_salas` y borrado en cascada |
| `database/migrations/021_sala_carreras.down.php` | La vuelta atrás |

Se corre con un runner puntual, **solo la 021**, nunca `migrate.php` ni "todas las
pendientes": la rama numera 018 y salta a 021, y no sé qué hay en 019 y 020.

### Paso 2 — assets del juego (antes que el código que los usa)

`app/juego/circuito/` completo: **38 archivos, 34 MB**. Los pesados son tres modelos nuevos
(elástica 7,3 MB, gigante 6,8 MB, felino 3,0 MB) y tres retratos PNG de 1,8 a 2,8 MB.

### Paso 3 — backend compartido, en este orden

| Orden | Archivo | Clase | Qué es |
| ---: | --- | --- | --- |
| 1 | `lib.sala.carrera.php` | OBLIGATORIO | Nuevo, 292 líneas, tal cual de la rama |
| 2 | `lib.puntajes.php` | OBLIGATORIO | PROD + la entrada `circuito` |
| 3 | `puntajes.php` | OBLIGATORIO | PROD + el beacon de resultado |
| 4 | `sala.php` | OBLIGATORIO | PROD + las cinco operaciones `carrera_*` |

`lib.sala.carrera.php` primero: los otros tres lo cargan.

### Paso 4 — Impulso Arácnido

| Orden | Archivo | Clase |
| ---: | --- | --- |
| 1 | `src/entrada.mjs` | OBLIGATORIO (nuevo) |
| 2 | `src/reglas.mjs`, `src/datos.mjs`, `src/mundo.mjs`, `src/entorno.mjs` | OBLIGATORIO |
| 3 | `src/main.mjs`, `estilo.css` | OBLIGATORIO |
| 4 | `index.html` | OBLIGATORIO |

`index.html` al final, y de una vez con el resto: son módulos que se importan entre sí y
mezclarlos a media partida deja el juego roto.

### No subir

Tests, documentación, `docs/`, registros de generación de voz, grafos de graphify, `.git`,
fixtures, capturas, el CSV de entrega, `orientacion.css`, `orientacion.js`, `vendor/`, y el
menú histórico de la rama.

---

## 4. Riesgos

| Riesgo | Qué tan grave | Qué lo contiene |
| --- | --- | --- |
| Subir la rama sin injertar | Alto: borra la lista de invitados y saca Impulso del menú | Ya evitado: se injertó sobre PROD |
| `.htaccess` de impulso borrado | Alto: pantalla negra sin error | No está en la lista de subida |
| Migración sobre `cc_salas` en curso | Medio | Correrla sin salas activas; la tabla es nueva y no altera las existentes |
| 34 MB por dispositivo en datos móviles | Medio, es real | Sin resolver. Los padres que entren con datos y no con el wifi de la casa pagan esa descarga |
| `carrera_*` sin límite por IP | Bajo en una fiesta | Es a propósito: seis dispositivos salen por la misma IP. La autorización va por código de sala más token de ayudante, y valida host para configurar e iniciar |
| Cliente y servidor desincronizados | Medio | Subir todo seguido, fuera de una fiesta, y recargar |

### Respaldo y vuelta atrás

- `tar czf ~/respaldos/juegos-antes-circuito-<fecha>.tar.gz` de los cuatro PHP compartidos y
  de `juego/impulso-aracnido/` completo, **antes** de tocar nada.
- Vuelta atrás del código: restaurar ese tar.
- Vuelta atrás de la base: `021_sala_carreras.down.php`. La tabla es nueva y nada más la usa,
  así que quitarla no afecta a los otros juegos.
- Circuito se puede apagar solo, sin tocar nada más: sacando su entrada de `cb_juegos()`
  desaparece del menú y el resto sigue igual.

### Pruebas previstas, después de subir

1. Las 10 URL impresas en los carteles, antes y después (`urls-criticas.py`).
2. `php -l` en el servidor sobre los cuatro PHP.
3. Que el menú de `luciano-spidey` siga ofreciendo **la lista de invitados** y muestre los
   tres juegos: Aventura, Impulso y Circuito.
4. Que Impulso siga cargando sus once módulos sin error de consola.
5. Abrir una sala de carrera, entrar con dos navegadores, elegir vueltas y correr una.
6. Que el puntaje quede anotado y aparezca en la tabla de posiciones.

Nada de eso lo prueba un HTTP 200.

---

## 5. Lo que sigue sin probarse, dicho por Codex y no verificado por mí

- Safari/iOS, y celulares y tablets físicos.
- Varios dispositivos en una red real de fiesta.
- El backend contra MySQL del hosting (las pruebas fueron locales).
- Escucha humana de la voz Alice; no está certificado el acento chileno.
- Los 45 fps en móvil **no están certificados**: seis renderizados a la vez en un PC de
  escritorio dieron unos 30 fps de mediana.
- Doce turnos completos de Impulso.
- Niños de verdad, de tres años.
