# Modo feria del kiosco — diseño y reparto Claude / Codex (2026-09-26)

Escrito por Claude como orquestador el 2026-09-26, día de la primera feria de CumpleClick (Mini Paseo Dieciochero).
**Luis Miguel** aprueba y paga: nada que toque PROD, gaste créditos o se mergee pasa sin su "ok" explícito.
Medido contra `main` = PROD (`4cdc795`, 25-09). Tickets: `Docs/ORCHESTRATION/AT-CUMPLECLICK-020.yaml` (Claude:
datos, API y admin) y `AT-CUMPLECLICK-021.yaml` (Codex: kiosco y selector). Los dos trabajan **en paralelo** contra el
contrato de la sección 4; nadie espera a nadie.

---

## 1. Qué pidió Luis

> "Un módulo para activar las temáticas o mundos, exclusivo de la feria, que sirva para futuras ferias, con adultos
> también. Debe haber una galería de la feria para buscar y reimprimir fotos, y que tenga los datos de la feria como
> recuerdo." — Luis, 25/26-09-2026.

Decisiones de Luis (26-09):
- **Fotos de feria: se guardan 7 días** y se borran solas.
- **Dos entradas: Niños y Adultos.** Los adultos pueden usar las temáticas que ya existen **y además temáticas
  adultas propias** que les llamen la atención (Luis, 26-09: "haz un estudio a ver qué podemos implementar"). Recorrido
  de adulto: sin diploma infantil, sin aviso de "adulto responsable", textos de adulto. Las temáticas adultas nuevas son
  **sin personajes** (marco + fondo + frase + música, como las de baby shower) y sin franquicias; cuáles, sale del
  estudio de temáticas adultas (`docs/ESTUDIO-TEMATICAS-ADULTOS-2026-09-26.md`, lo escribe Claude).
- **Contacto de las familias: fuera de esta versión** (Luis preguntó para qué sirve; queda pendiente).
- Rápido: Claude y Codex en paralelo.

## 2. Por qué no alcanza con una fiesta normal (medido en el código)

- El kiosco arma la temática **una sola vez** al cargar (`buildRuntime(party, theme, slug)`, `src/App.jsx` ~l.133) desde
  `app/api.php?p=<slug>`, que resuelve **una** temática por fiesta (`cb_resolve_party`).
- El kiosco parte con la **lista de invitados** (`ListaInvitados`); en una feria no se conoce a nadie.
- El diploma escribe "en la fiesta de {nombre} · {fecha}" (`eventoFraseEn`, `src/App.jsx` ~l.2752).
- El tipo de evento es binario (`child_birthday` / `baby_shower`) y está repetido en al menos 5 lugares de
  `public/lib.php`: **no se toca**. La feria cuelga de una fiesta normal mediante una tabla aparte.
- Fotos: `cc_photos` con tope de **200 por fiesta** (`public/upload.php` l.109-111): una feria necesita más.
- Borrado: `scripts/retention.php` borra a los 30 días (`retention_days`), por fiesta, si corre como tarea diaria.
- 🔴 Trampas conocidas (leer `CumpleBooth/docs/FTP-MANIFEST.md`): **la migración va antes que el código**; las fiestas
  tienen columnas fijas; los juegos tienen CSP estricta; el CDN reencoda imágenes.

## 3. La experiencia

**Selector (`app/feria.html?f=<slug>`, nuevo, Codex):**
1. **Espera:** video en bucle a pantalla completa (`app/feria/pantalla-espera.mp4`, lo deja Claude) + "Toca para
   empezar". Cualquier pantalla del selector vuelve aquí tras **45 s** sin toques.
2. **Modo:** dos botones grandes, **Niños** y **Adultos** (solo los modos que tengan mundos habilitados).
3. **Mundo:** grilla con los mundos habilitados para ese modo (nombre + imagen de `feria-api.php`).
4. **Solo Niños — autorización:** "Soy el adulto responsable y autorizo tomar la foto" (casilla + botón). Sin marcar,
   no avanza.
5. Navega al kiosco: `./?p=<slug>&tema=<tema>&modo=<infantil|adulto>&volver=<feria.html?f=slug codificado>`.

**Kiosco con feria (`src/App.jsx`, Codex), solo si `api.php` trae `feria`:**
- Sin lista de invitados: pantalla **"¿Cómo te llamas?"** (primer nombre, opcional, máx. 20 caracteres, botón "Saltar").
- **Niños:** ruleta → personaje → foto → diploma, igual que hoy. El diploma dice **"en {feria.nombre} · {fecha_texto}"**.
- **Adultos:** si la temática tiene personajes, ruleta → personaje → foto; si **no tiene personajes** (temáticas adultas
  propias: `personajes` vacío en `themes.json`, igual que `baby-nube`), **sin ruleta**: directo a la foto en el marco.
  En los dos casos **sin diploma** y con textos de adulto (sin "niño", "cumpleañero", etc.).
- Antes de componer la foto final: reserva el número (`POST feria-api.php`, sección 4.3) y dibuja en la foto (y en el
  diploma) la **franja de recuerdo**: `feria.recuerdo` + `@organizador` + el número **F-027**. La franja no tapa la cara:
  va abajo, sobre el marco.
- Sube la foto como hoy, agregando `feria_reserva` (4.4).
- Pantalla del QR: además del QR, **"Tu foto: F-027"** (con eso se pide la reimpresión). Botón "Terminar" y, a los
  **60 s**, vuelve solo a `volver`.
- Todo lo de feria va detrás de `if (FERIA)`: **una fiesta normal no cambia en nada** (con prueba que lo cuide).

**Admin (`app/admin/ferias.php`, nuevo, Claude):** ficha de la feria, mundos por modo (casillas), activar, **duplicar**
para la próxima, y **galería de la feria**: buscar por nombre, número, mundo, modo y hora; ver; **reimprimir** en la
Selphy 10×15 (misma impresión del Álbum); mostrar el QR; contador de impresiones. La galería pública (`galeria.php`) y el
Álbum **no existen** para una feria: son niños ajenos.

## 4. Contrato entre las dos partes (no se cambia sin avisar en el PR)

### 4.1 `GET app/api.php?p=<slug>[&tema=<t>&modo=<infantil|adulto>]` (existente, lo extiende Claude)
Si la fiesta tiene feria, la respuesta suma `feria`:
```json
{"ok": true, "party": {...}, "theme": {...},
 "feria": {"slug": "mini-paseo-dieciochero-2026", "nombre": "Mini Paseo Dieciochero",
           "organizador": "Royal Art Academy", "organizador_ig": "@royalart.cl",
           "lugar": "Grecia 3348", "fecha": "2026-09-26", "fecha_texto": "26 sep 2026", "mesa": "6",
           "recuerdo": "Mini Paseo Dieciochero · Royal Art Academy · 26 sep 2026",
           "modo": "infantil", "modos": {"infantil": ["hielo", "spidey"], "adulto": ["hielo"]}}}
```
Con `tema` y `modo`: `theme` es el de `tema` si está habilitado para ese `modo`; si no, `{"ok": false, "error":
"tema_no_habilitado"}` (HTTP 403). Sin `tema`: primera temática infantil habilitada. Fiesta sin feria: respuesta de siempre.

### 4.2 `GET app/feria-api.php?f=<slug>` (nuevo, Claude)
```json
{"ok": true, "feria": {... igual que 4.1, sin "modo" ...},
 "mundos": {"infantil": [{"slug": "hielo", "nombre": "Reino de Hielo", "imagen": "themes/hielo/<banner>"}],
            "adulto":   [{"slug": "hielo", "nombre": "Reino de Hielo", "imagen": "themes/hielo/<banner>", "personajes": true},
                         {"slug": "adulto-glam", "nombre": "Noche de Gala", "imagen": "themes/adulto-glam/<banner>", "personajes": false}]},
 "video_espera": "feria/pantalla-espera.mp4"}
```
Rutas relativas a `app/`. `personajes: false` = temática sin ruleta. Las temáticas adultas propias llevan en
`themes.json` `"audiencia": "adulto"` y solo se ofrecen en el modo Adultos. Feria inexistente o inactiva: `{"ok": false, "error": "feria_no_disponible"}` (404).

### 4.3 `POST app/feria-api.php` — reservar número (nuevo, Claude)
Cuerpo JSON `{"accion": "numero", "f": "<slug>", "modo": "infantil", "tema": "hielo", "nombre": "Sofía"}` →
`{"ok": true, "numero": 27, "etiqueta": "F-027", "reserva": "<token de 32 hex>"}`. Límite por visitante como el resto.

### 4.4 `POST app/upload.php` (existente, lo extiende Claude)
Acepta el campo opcional `feria_reserva` (el token de 4.3). Si la fiesta es feria y el token es válido y sin usar, la
foto queda ligada a su número; si no, la foto se guarda igual (nunca se pierde una foto por esto). En feria, el tope
de fotos es el de la feria (1.000 por defecto) en vez de 200.

### 4.4 bis — Agregados del 26-09 (tarde), ya implementados por Claude
- `theme.filtro` (opcional): si vale `"bn"`, la foto final (y su vista previa en cámara) va en **blanco y negro**
  (`ctx.filter = 'grayscale(1) contrast(1.08)'` al componer, o equivalente). Sin el campo, a color como siempre. Sale
  del estudio de temáticas adultas: el blanco y negro de estudio es el formato adulto que vende la competencia
  (`docs/ESTUDIO-TEMATICAS-ADULTOS-2026-09-26.md`). La primera temática con filtro será `adulto-estudio-bn`.
- En feria, los personajes llegan **sin minijuego** (`game` vacío): la fila avanza. No hace falta lógica nueva.
- **Sin personajes = sin ruleta en cualquier modo**, también en Niños: viene la temática `fiestas-patrias` (Chile,
  sin personajes, para Niños y Adultos). En Niños sin personajes: autorización → foto → diploma, sin ruleta.
- En feria, `party.invitados` llega vacío, `party.nombre` = nombre de la feria y `party.frameBox` = el de la temática
  elegida.
- Backend listo y probado en la rama `claude/modo-feria` (`tests/backend/ferias-http.php`, 74 comprobaciones):
  sirve para la prueba de punta a punta.

### 4.4 ter — Foto sobre fondo completo (Luis, 26-09: "no necesariamente con marcos, sino con fondos")
Las temáticas adultas traen `theme.modoFoto`:
- `"marco"`: como hoy, la foto cuadrada dentro de `frameBox` sobre `images.sala`.
- `"fondo"`: la foto final es la **escena completa** `theme.images.escena` (9:16) con **las personas recortadas
  delante**. Recorte con MediaPipe **ImageSegmenter** (mismo paquete `@mediapipe/tasks-vision` y el mismo `.wasm` de
  `public/vendor/mediapipe/`, que ya usa `src/detectorCara.js`; falta solo el modelo de segmentación de personas,
  p. ej. `selfie_segmenter.tflite` o `selfie_multiclass_256x256.tflite` de los modelos oficiales de MediaPipe, en
  `public/vendor/mediapipe/`, anotado en la lista FTP). Borde de la máscara suavizado (sin serrucho), personas del
  tamaño en que las ve la cámara, centradas abajo. La vista previa en cámara también muestra el fondo.
- Si el segmentador no carga o tarda más de ~4 s en la tablet, **cae solo a `"marco"`** con `frameBox`: nunca se
  queda sin foto. Cargarlo al entrar a la temática, no al abrir el kiosco (misma lección que Asómate).
- `filtro: "bn"` aplica a la foto final entera en los dos modos.
- Medir en la Tab A7 (1200 × 2000): cuadros por segundo de la vista previa y tiempo de composición. Si la vista previa
  con recorte no llega a ~15 fps, recortar solo la foto final y mostrar la vista previa sin recorte.

### 4.4 quater — Asómate en feria (Luis, 26-09: huasos y huasitas en Fiestas Patrias)
Si la temática elegida trae `theme.asomate` (hoy `hielo`, `spidey` y, cuando estén sus seis personajes,
`fiestas-patrias`), el recorrido de feria ofrece **dos botones** antes de la cámara: **"Foto"** (el recorrido normal:
marco o fondo completo) y el botón de Asómate con el texto de `theme.asomate.boton`. Asómate en feria usa lo que ya
existe en el kiosco (elegir personaje, guía del hueco, cara automática, `componerAsomate`), con la franja de recuerdo
y el número F-### igual que la foto normal. En `fiestas-patrias` los personajes de Asómate **no** son de la ruleta
(la temática no tiene ruleta): el nombre y el emoji vienen en el propio bloque `asomate.personajes`, y `api.php` ya
los publica como siempre (`nombre`, `emoji`, `png`, geometría). Niños y adultos pueden usarlo.

### 4.4 quinquies — Video de bienvenida en feria (Luis, 26-09: "un video de introducción con voces")
Si la temática elegida trae `theme.videos.welcome` (las infantiles ya lo tienen; vienen `fiestas-patrias` y
`adulto-noche-brujas`), al entrar al mundo desde el selector se reproduce una vez, a pantalla completa y con sonido,
con un botón **"Saltar"** visible desde el primer segundo (la fila no espera). Mismo reproductor y misma red de
seguridad que la bienvenida del kiosco (`src/videoListo.js`, tope de espera): si el video no carga, se sigue sin él.

### 4.5 Recursos
- `app/feria/pantalla-espera.mp4`: el video en bucle de 15 s (1200 × 2000) hecho el 25-09 (lo sube Claude).
- Imagen de cada mundo: la que devuelve 4.2.

## 5. Reparto de archivos (para que los dos PR mergeen sin conflicto)

| Claude — ticket 020, rama `claude/modo-feria` | Codex — ticket 021, rama `codex/modo-feria-kiosco` |
|---|---|
| `database/migrations/026_modo_feria.php` (+ `.down.php`), `database/aplicar-026.php` | `public/feria.html` y `src/feria/**` (selector, nuevo) |
| `public/lib.ferias.php`, `public/feria-api.php` | `src/App.jsx` (solo ramas `if (FERIA)`) y lo que haga falta en `src/` |
| `public/api.php`, `public/upload.php`, `public/galeria.php` (bloqueo), `scripts/retention.php` (7 días) | `vite.config.*` (entrada `feria.html`, como la de `album.html`) |
| `public/admin/ferias.php`, pestaña en `public/admin/_acceso.php`, módulo `ferias` en `public/lib.admin-usuarios.php` (al final) | `tests/frontend/feria*.test.mjs` |
| `tests/backend/ferias*.php`, `public/feria/pantalla-espera.mp4` | build (`npm run build`) para verificar; el `dist/` lo sube Claude |
| `docs/FTP-MANIFEST.md`: anexa su sección | `docs/FTP-MANIFEST.md`: anexa su sección |

`CLAUDE.md`, `CumpleBooth/CLAUDE.md` y `AGENTS.md`: no tocar (Claude los actualiza al cerrar).

## 6. Cómo trabajar

1. **Codex parte de `main`** (`4cdc795` o posterior), rama `codex/modo-feria-kiosco`, en su propio worktree.
2. Mientras el backend no esté, Codex prueba contra **datos de ejemplo** con la forma exacta de la sección 4 (un
   `fetch` falso o un fixture en las pruebas). Cuando Claude publique su rama, se prueba de punta a punta en local.
3. **Nada se despliega desde Codex** ni se gasta ningún crédito. Codex entrega rama + PR + lista FTP (ruta local →
   destino en PROD, `OBLIGATORIO`/`OPCIONAL`, orden). Claude revisa, integra, despliega por SSH con el go de Luis y
   mergea. 🔴 La migración 026 va antes que el código.
4. Sin secretos en nada (el repositorio es **público**). Sin nombres de franquicias en textos nuevos.
5. Español de Chile en todo lo visible. Botones de 44 px o más. `prefers-reduced-motion` respetado.

## 7. Aceptación (ticket 021, Codex)

- [ ] `feria.html?f=<slug>`: espera con video → modo → mundo → autorización (niños) → kiosco con `tema`, `modo` y
      `volver`; vuelve a espera tras 45 s sin toques.
- [ ] Con feria: "¿Cómo te llamas?" en vez de la lista; niños con diploma "en {feria} · {fecha}"; adultos sin diploma
      y sin textos infantiles; con una temática **sin personajes** el kiosco salta la ruleta y va directo a la foto
      (probar con una temática de ejemplo sin personajes, por ejemplo `baby-nube` forzada en modo adulto).
- [ ] Franja de recuerdo con el número en la foto (y en el diploma de niños), sin tapar la cara.
- [ ] Pantalla del QR con "Tu foto: F-027"; vuelve sola a los 60 s.
- [ ] Una fiesta **sin** feria se ve y funciona exactamente igual (prueba que lo cuide).
- [ ] `npm test` y `npm run build` limpios; pruebas nuevas en `tests/frontend/feria*.test.mjs`.
- [ ] PR con capturas a 1200 × 2000 (vertical, Galaxy Tab A7) y lista FTP.

## 8. Fuera de esta versión

Contacto de las familias; la **producción del arte** de las temáticas adultas (la hace Claude aparte, según el estudio:
el kiosco solo necesita soportar temáticas sin personajes); tira de fotos, boomerang, accesorios en la
cara; cobro de impresiones enlazado a Finanzas (la galería cuenta impresiones, no cobra).
