# Aurora de Cristal — integración en el menú de la fiesta

Revisión e integración hechas por Claude el 2026-09-11 sobre el paquete que entregó Codex en
`codex/aurora-qa-integracion`. Rama `claude/aurora-integracion`.

**DESPLEGADO EN PROD el 2026-09-11** con autorización de Luis: 31 archivos, los 31 con el
SHA-256 exacto en el servidor, las 10 URL críticas sin cambio de estado. Respaldo del menú en
`~/respaldos/menu-antes-aurora-20260911.tar.gz`. Sigue faltando la prueba en tablet.

Leer antes: `CLAUDE-INTEGRAR-AURORA.md` y `AURORA-QA-INTEGRACION.md`, de Codex. Este
documento registra solo lo que revisé y lo que cambié para conectar el juego.

## Lo que verifiqué del paquete de Codex

| Qué | Resultado |
| --- | --- |
| Los 34 archivos del manifiesto, por SHA-256 y tamaño | **34/34 exactos**; 12.118.908 bytes propios, 16.054.596 en total |
| El amigo aprobado (`amigo.glb`) | 4.023.440 bytes, SHA-256 `69f8c0e8…6cc9a`: **el aprobado**, no el anterior |
| Los seis `vendor/` compartidos contra PROD | **Idénticos byte a byte.** No se tocan: el portón de orden 0 queda cerrado |
| Las 30 pruebas unitarias, con mis cambios aplicados | **30/30** |
| El slug y la edad de Samantha, contra la base | `samantha-hielo`, **4 años**. No hubo que inventar nada |

## Dos cosas que faltaban y se agregaron

**1. Aurora no traía `.htaccess` y son doce módulos `.mjs`.** El `.htaccess` de la carpeta
padre declara `.js` pero **no** `.mjs`. Probado en el servidor real con un archivo de sonda
(subido y borrado en el acto):

```
app/juego/_sonda.mjs        →  text/plain; charset=UTF-8     ← el navegador lo rechaza
app/juego/circuito/sim.mjs  →  text/javascript; charset=UTF-8 ← tiene .htaccess propio
```

Sin ese archivo Aurora habría sido una pantalla negra en PROD, sin error visible. El nuevo
`juego/aurora/.htaccess` declara el tipo y, además, hace que los `.mjs` se revaliden: la
regla de caché del padre tampoco los cubre y un navegador podía mezclar un módulo viejo con
uno nuevo. Mismo problema que ya había mordido en `circuito/` el 2026-09-10.

**2. `nombre` no significa lo mismo en todos los juegos.** En Aurora es **de quién es la
fiesta** (pinta "La fiesta de Samantha · 4 años"), igual que en Impulso Arácnido y al revés
que en el Reino de Hielo o el Festival, donde es quién juega. Si se hubiera cableado con el
patrón por defecto, en el cumpleaños de Samantha habría dicho "La fiesta de Sofía". El menú
ya documenta esa trampa para Impulso; Aurora entra por esa misma rama.

## Qué se cambió, archivo por archivo

- **`public/lib.puntajes.php`** — una entrada en el catálogo: `aurora`, tema `hielo`. Ese
  catálogo es también de donde sale `juegos_disponibles`, o sea qué ofrece el menú. Aurora
  **no anota puntajes todavía**, y la tabla de posiciones solo dibuja juegos que tienen
  filas, así que no aparece ahí ni deja un recuadro vacío (verificado en el código y contra
  el endpoint real).
- **`public/juego/index.html`** — ruta `aurora/`, ícono 🛷, pie de tarjeta, y la rama que le
  manda el nombre de la **fiesta** en vez del jugador.
- **`public/juego/aurora/main.mjs`** — los botones que dicen "menú" ahora vuelven al menú de
  la fiesta cuando el juego se abrió desde ahí, conservando el `?p=`. Y el cartel de la
  fiesta ya no inventa un nombre si no viene ninguno.
- **`public/juego/aurora/sim.mjs`** — sin `p` ya no se asume la fiesta de Samantha. Antes,
  una dirección sin fiesta mostraba su nombre y escribía en **su** guardado.
- **`public/juego/aurora/.htaccess`** — nuevo, el de arriba.

### Qué botón vuelve a dónde (decidido explícitamente)

Abierto **desde el menú de la fiesta** (trae `volver`):

| Botón | A dónde |
| --- | --- |
| El logo CumpleClick | Menú de la fiesta (con confirmación si está corriendo) |
| Pausa → "Volver al menú" | Menú de la fiesta, con confirmación |
| Al terminar → "Al menú" | Menú de la fiesta |
| Despedida → "Volver al menú" | Menú de la fiesta |
| "Salir del juego" | Pantalla de despedida, **igual que antes** |
| En la preparación → "Atrás" | Portada de Aurora, **igual que antes** |

Abierto por dirección directa (sin `volver`): **todo se comporta como antes**. El `volver` se
valida contra el mismo origen, igual que en `juego/pantalla.js`.

## Probado (Chrome de escritorio, servidor local)

Servidor `scratchpad/aurora-local/servidor.py` (en `.claude/launch.json` como `aurora-local`):
sirve el árbol `public/` de esta rama y contesta `puntajes.php` con la respuesta **real** de
PROD más Aurora, que es justo lo que devolverá el catálogo parcheado.

- El menú de hielo muestra **tres** juegos y los dos de antes quedan intactos.
- Spidey sigue con sus tres y **no** aparece Aurora. Carreras sigue sin juegos.
- Aurora recibe `p=samantha-hielo`, `nombre=Samantha`, `edad=4`, `volver=/juego/?p=…`, y
  pinta "La fiesta de Samantha · 4 años". Consola limpia.
- Las cuatro vueltas al menú de la fiesta funcionan y conservan la fiesta.
- Sin `volver`, el logo se queda en Aurora; "Salir del juego" llega a la despedida.
- Sin `p`, dice "Una carrera por el reino de hielo" y **no escribe** ningún guardado.
- 30/30 pruebas unitarias.

## Verificado ya en PROD (2026-09-11)

- Los 31 archivos con SHA-256 exacto en el servidor; `php -l` limpio en los dos PHP; los seis
  `vendor/` compartidos siguen con el mismo hash que antes de subir.
- El menú de **samantha-hielo** y el de **isidora-reino-de-hielo** muestran los tres juegos;
  **luciano-spidey** sigue con sus tres y sin Aurora. Así es la lógica: los juegos van por
  temática, y dos fiestas de hielo comparten los mismos (Luis, 2026-09-11).
- `.mjs` y `vendor/*.js` salen como `text/javascript` y se revalidan; los `.glb` como
  `model/gltf-binary` con un día de caché; un archivo inexistente da **404** y no el HTML del
  kiosco.
- **Las 24 peticiones de una carga completa devolvieron 200** (206 el MP3 por rango), los seis
  modelos incluidos. En esa carga **no hubo ningún ERR_ABORTED**.
- Aurora recibe `p=samantha-hielo`, `nombre=Samantha`, `edad=4` y `volver=/app/juego/?p=…`,
  pinta "La fiesta de Samantha · 4 años" y **el logo vuelve al menú de la fiesta**.

## Lo que NO está probado, y hay que decirlo

1. **Ninguna partida completa en PROD**, ni el rendimiento real.
2. **Ningún equipo físico.** Ni Android, ni tablet, ni iPhone. Lo de Codex también fue Chrome
   de escritorio con tamaños emulados. **No afirmar 60 fps ni ≥45 fps en tablet**: en la
   revisión de Codex una carrera WebGPU tuvo P10 de 29,94 fps.
3. **El aviso `ERR_ABORTED` de Codex sigue abierto.** Los seis modelos llegaron completos y
   con el hash exacto, pero la causa del evento no está determinada. Hay que mirarlo en el
   servidor de destino.
4. **Una carrera entera no se completó en mi entorno**: el panel de pruebas frena el juego al
   perder el foco (el reloj de carrera avanzó 2,4 s en 30 s de reloj real). Las ocho carreras
   completas son evidencia de Codex, no mía. Probé los botones del final directamente.
5. **Aurora no anota puntajes** ni consulta la API de la fiesta. No aparece en las posiciones.
6. **La narración Alice sigue pendiente.** Hay música, no voz. No se generó nada ni se gastó
   un crédito.
7. Aurora aparece en las dos fiestas de hielo porque el catálogo es por temática.
   **Luis confirmó que esa es la lógica del proyecto** (2026-09-11): varias fiestas de la
   misma temática comparten los mismos juegos. No hay nada que resolver ahí.
8. El rótulo "Primera versión local" **se conserva**: lo retira Luis cuando la apruebe.

## Subida y rollback

Manifiesto exacto: `AURORA-FTP-CLAUDE.csv` (31 archivos, 12,16 MB), con ruta, destino, bytes
y SHA-256. Orden: 1 recursos, 2 módulos/CSS/`.htaccess`, 3 `index.html` del juego, y **4 al
final** los dos archivos del menú, para que la tarjeta nunca apunte a una carpeta a medias.

**No se suben:** los seis `vendor/` compartidos (idénticos, verificado por hash), el
`README.md` del juego, las pruebas, `qa-evidence`, candidatos, documentos, ni nada de
`public/`/`dist/` de las ramas de trabajo.

**Rollback.** Aurora es nueva en el servidor: no pisa ningún archivo existente salvo los dos
del menú.

1. Restaurar `app/lib.puntajes.php` y `app/juego/index.html` desde el respaldo que se toma
   antes de subirlos. Con eso la tarjeta desaparece del menú y todo queda como hoy.
2. Si hace falta, borrar la carpeta `app/juego/aurora/` completa. No toca datos, ni fotos, ni
   los otros juegos, ni el `vendor/` compartido.
