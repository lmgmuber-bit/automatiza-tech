# Invitación: música de fondo + narración de Alice

Estado: **código listo y verificado, audio generado y verificado localmente (sin deploy)**. Este doc es
la referencia canónica para esa parte pendiente y para replicar la función a
las demás temáticas.

## Qué se construyó (código, ya en `invitacion.php` / `invitation.js` / `invitation.css`)

1. **Música de fondo**: reutiliza el MP3 que YA existe por tema en el kiosco
   (`public/themes/<tema>/musica-fondo.mp3`) — no se generó nada nuevo. Arranca
   con el primer toque/scroll/tecla (los navegadores bloquean autoplay con
   sonido), en loop, volumen `0.15` — mismo valor que usa el kiosco
   (`src/App.jsx`, constante `MUSIC_VOL`).
2. **Botón de silenciar música**: mismo componente visual que el kiosco
   (`.mute-btn`, círculo flotante abajo-izquierda, 🎵/🔇). Aparece recién
   cuando la música arranca (antes queda oculto). Este control modifica
   exclusivamente `data-inv-music`: **nunca pausa, silencia ni corta la
   narración de Alice**.
3. **Narración de Alice**, en tres categorías con vida útil distinta:
   - **Inicio** (`personalized_narration_intro`): dinámica, lleva el nombre,
     fecha y hora DE ESA invitación → se aprueba por invitación, igual que la
     imagen personalizada, vía el panel admin (`admin/invitations.php`,
     formulario "Subir narración de inicio").
   - **Despedida** (`narracion-final*.mp3`): texto fijo (no depende del
     nombre/fecha), igual para cualquier temática → **tres archivos
     compartidos**, no por invitación ni por tema. Elegido por
     `cc_invitations.birthday_person_gender` (columna agregada en la
     migración `009_invitation_gender`, editable en `admin/invitations.php`
     al crear o editar una invitación — corrección 2026-08-12: el texto
     anterior decía "toca aquí para ver la invitación", pensado para un link
     compartido, y no calzaba con la pantalla real de cierre, que ya muestra
     los botones de compartir y el CTA "Conoce al cumpleañero/a"):
     - `m` → `public/assets/audio/narracion-final-nino.mp3` ("...para
       conocer **al cumpleañero**.")
     - `f` → `public/assets/audio/narracion-final-nina.mp3` ("...para
       conocer **a la cumpleañera**.")
     - sin especificar (NULL, o invitaciones creadas antes de este campo) →
       cae a `public/assets/audio/narracion-final.mp3`, texto neutro ("...para
       conocer más del cumpleaños."). También es el respaldo si la variante
       de género no existe físicamente en disco.
     - Suena SOLO cuando el invitado llega de verdad a la sección "Guarda y
       comparte tu invitación" (`data-inv-narration-outro-trigger`, dispara
       vía IntersectionObserver). No es lo mismo que el cierre del recorrido
       de personajes de abajo, aunque compartían el mismo audio hasta el
       2026-08-12 — el CTA "Conoce al cumpleañero/a" no existe todavía en
       pantalla en ese punto, así que decir "toca el botón" ahí no calzaba.
   - **Cierre del recorrido de personajes** (`narracion-playlist-final.mp3`):
     archivo aparte, único y compartido para toda invitación/temática, texto
     fijo "Toca aquí para ver la invitación a la fiesta." Es la narración del
     ÚLTIMO capítulo de la playlist automática (slot "¡Te esperamos!" en
     `$playlistOrdersByTheme`/`$playlistCandidateOrdersByTheme`) — invita a
     seguir bajando hacia la ficha/sección final, que todavía no está en
     pantalla en ese momento. En Scroll (sin playlist automática) este audio
     no se usa.
   - **Capítulos del modo video** (`narracion-video/<clip>.mp3`): texto fijo
     por TEMA (no depende del invitado) → un archivo por capítulo, se genera
     una vez por temática y sirve para todas sus invitaciones. Va en
     `public/themes/<tema>/narracion-video/<nombre-del-clip-sin-extension>.mp3`.
4. **Cuándo habla Alice**:
   - Modo scroll (por defecto, y con `?capitulos=1`): **solo al inicio**
     (junto con el primer toque, como la música) **y al final** (cuando la
     sección "Guarda y comparte tu invitación" entra en pantalla — existe
     siempre, sea cual sea el modo).
   - Modo video (`?hero=auto&capitulos=auto`): la de inicio igual, y además
     **cada capítulo de la lista dice su propia línea** mientras ese clip se
     reproduce (los clips van sin audio propio — `muted` en el `<video>` — así
     que Alice reemplaza esa voz que faltaba).
   - La música baja a `0.04` (mismo valor que el kiosco, `MUSIC_VOL_NARRACION`)
     mientras cualquier narración suena, y vuelve sola a `0.15` al terminar.
5. **Resiliencia**: si falta cualquier MP3, esa narración puntual
   simplemente no suena — el resto de la invitación (música, video, scroll)
   sigue funcionando igual. Mismo patrón que la narración de personajes del
   kiosco (`src/App.jsx`).

## Regla transversal para todas las temáticas

- El mismo contrato de audio aplica a Carreras, Familia Canina, Tropical,
  Reino de Hielo, K-Pop y cualquier temática futura, tanto en modo Scroll como
  en modo Automático.
- Al terminar, omitir o fallar el intro temático, la invitación debe iniciar la
  música ambiental, mostrar el botón de música e iniciar Alice si existe una
  narración aprobada para esa invitación.
- El botón 🎵/🔇 controla **solo la música ambiental**. Alice conserva su
  reproducción y volumen de voz aunque el invitado silencie la música.
- La narración personalizada se asocia por invitación; no se debe reutilizar
  una voz con nombre, fecha u hora de otro evento. Si falta, el flujo degrada
  sin romperse, pero el QA editorial debe marcar la narración como pendiente.
- Antes de publicar una temática o invitación nueva, probar este contrato en
  Scroll y Automático, incluyendo el camino `Omitir intro`.

Archivos tocados: `public/invitacion.php`, `public/assets/invitation.js`,
`public/assets/invitation.css`, `public/lib.invitations.php` (nuevo
output_type `personalized_narration_intro`), `public/admin/invitations.php`
(formulario de subida), `public/descargar-invitacion.php` (nuevo `type`
`narracion_inicio`, no fuerza descarga — se reproduce inline).

## Voz e IDE — usar ElevenLabs directo, NO Higgsfield

Luis pidió explícitamente generar estos audios con **ElevenLabs directo**
(la cuenta/API key del proyecto), no con el passthrough de Higgsfield. La voz
Alice ya está aprobada para este uso exacto:

- **voice_id**: `Xb7hH8MSUJpSbSDYk0k2`
- **modelo**: `eleven_multilingual_v2`
- Acento neutro (mismo criterio que la narración de personajes del kiosco,
  ver `docs/CODEX-TEMATICAS-KPOP-Y-VENGADORES.md` §6.6).
- La API key vive fuera del repo en
  `C:\Users\luis_\OneDrive\Documentos\APIS KEy\APIS KEY.txt` — leerla solo
  para usarla, nunca copiarla a un archivo del repo, `.env`, config o log.

## Tarea inmediata (piloto: Vicente / carreras)

Generar y colocar estos 3 archivos:

### 1. Despedida global (una sola vez, sirve para siempre)

- Texto: `"Toca aquí para ver la invitación a la fiesta."`
- Destino: `CumpleBooth/public/assets/audio/narracion-final.mp3`
- Crear la carpeta `public/assets/audio/` si no existe.

### 2. Inicio de la invitación de prueba (Vicente, dinámico — un ejemplo de cómo se hace por invitación)

> Resultado local 2026-08-11: la salida aprobada id=6 usa `demo-narracion-inicio-v2-20260811.mp3`; no se debe subir la base SQLite ni este archivo privado de prueba.

- Texto: `"Tenemos el agrado de invitarte a celebrar el cumpleaños de Vicente. Es el sábado 12 de septiembre a las dieciséis horas."`
- Este es un output APROBADO de la invitación id=1 en la base de datos, no un
  archivo estático de tema. Insertar la fila (mismo patrón que ya usa
  `cb_save_invitation_output`, storage key con el formato
  `<slug>/<asset>-v<versión>-<8 hex>.<ext>`):

  ```sql
  -- storage/event-profile-demo/cumpleclick-demo.sqlite (entorno LOCAL de prueba)
  INSERT INTO cc_invitation_outputs
    (invitation_id, output_type, asset_key, file_storage_key, status,
     visual_source_json, file_mime, file_byte_size, file_sha256, created_at, updated_at)
  VALUES
    (1, 'personalized_narration_intro', 'personalized-narration-intro',
     'demo-perfil-carreras/demo-narracion-inicio-v1-<8 hex aleatorio>.mp3',
     'approved', '', 'audio/mpeg', <tamaño real en bytes>, '<sha256 real del archivo>',
     datetime('now'), datetime('now'));
  ```

  El archivo físico va en
  `storage/event-profile-demo/invitations/demo-perfil-carreras/demo-narracion-inicio-v1-<mismos 8 hex>.mp3`
  (mismo directorio donde ya está `demo-invitacion-v1-45cfc1d0.jpg`).

  En **producción real** esto se hace con el formulario del admin
  ("Subir narración de inicio", en `admin/invitations.php`), no con SQL a
  mano — el SQL de arriba es solo para este entorno de prueba local.

### 3. Capítulos del modo video, tema `carreras` (uno por tema, sirve para todas sus invitaciones)

Carpeta: `CumpleBooth/public/themes/carreras/narracion-video/`

| Archivo | Texto a narrar |
|---|---|
| `saludo-mate.mp3` | "Mate es el primero en llegar." |
| `saludo-sally.mp3` | "Sally viene en camino." |
| `saludo-cruz.mp3` | "Cruz ya calienta motores." |
| `saludo-luigi.mp3` | "Luigi no se queda atrás." |
| `saludo-el-rey.mp3` | "El Rey tampoco se lo pierde." |
| `rayo-mcqueen-estrella.mp3` | "Rayo se prepara para la carrera." |
| `saludo-rayo-mcqueen.mp3` | "¡Y Rayo McQueen cruza la meta!" (el video del capítulo se llama `saludo-rayo-mcqueen-v3.mp4`, pero el mp3 debe llamarse SIN el sufijo `-v3`: `invitacion.php` le quita el sufijo de versión al video para buscar su narración, así que el archivo de audio nunca debe llevarlo — 2026-08-12, corregido: estaba guardado como `saludo-rayo-mcqueen-v3.mp3` y por eso el capítulo quedaba mudo) |

(El último capítulo, "¡Te esperamos!", reutiliza el mp3 de despedida global
del punto 1 — no generar uno aparte para ese.)

## Portada "sobre que se abre" (2026-08-11, Claude)

El gesto que desbloquea audio (`data-inv-entry-open` en `invitacion.php`,
lógica en `invitation.js`) ahora se ve como un sobre CSS que se abre y saca la
carta, en vez de un botón plano — pedido de Luis para que invite a tocar en
vez de solo informar. Es **genérico, no por tema**: mismo sobre, mismos
colores derivados de `--inv-accent`/`--inv-highlight` del tema activo, cero
cambios necesarios por temática nueva.

Detalle no obvio: el desbloqueo de audio (`startMusic()`) se dispara
**síncrono dentro del click**, antes de la animación — si se espera a que la
animación del sobre termine para recién ahí llamar `play()`, el navegador ya
no lo cuenta como gesto del usuario y el audio queda bloqueado. La animación
corre en paralelo (clases `is-opening` / `is-leaving`), nunca antes.

## Pie de página: logo + link a AutomatizaTech + favicon (2026-08-11, Claude)

También genérico (no por tema). Reutiliza marca ya existente en
`public/brand/` (`cumpleclick-lockup.svg` para el pie, `cumpleclick-mark.svg`
como favicon vía `<link rel="icon" type="image/svg+xml">`) — no se generó
ningún asset nuevo.

## Gotcha de entorno local: `event_profile_enabled` (2026-08-11)

La sección "¿Quieres conocer a &lt;nombre&gt;?" (ficha del cumpleañero) solo
se muestra si `cb_config('event_profile_enabled')` es `true`. El default en
`lib.php` es `false`, y ningún servidor PHP de prueba levantado durante esta
sesión lo había seteado — así que la sección nunca aparecía en local aunque
los datos (perfil, campos, persona) estuvieran completos en la sqlite. **No
es un bug de `invitacion.php`.**

Arreglo sin reiniciar el servidor: `lib.php` ya soporta un override local en
`config/cumpleclick.local.php` (gitignored, no se commitea — se lee por
request, no por variable de entorno del proceso, así que no hace falta
reiniciar `php -S`). Con `storage_mode=db` local, agregar:

```php
return [
    'invitation_dir' => '<ruta absoluta a storage/event-profile-demo/invitations>',
    'event_profile_enabled' => true,
];
```

Otro gotcha relacionado: cambiar `birthday_person_name` en `cc_invitations`
(o `cc_parties`) **no** actualiza automáticamente `cc_featured_people.display_name`
ni `cc_event_profiles.public_title`/`cta_label` — son copias independientes
sembradas por separado. Si se renombra una invitación de prueba, hay que
sincronizar esas tres también o el perfil sigue mostrando el nombre viejo.

## Cómo replicar a las demás temáticas

**Hielo (Reino de Hielo / Frozen) ya está hecho — Codex, 2026-08-11**, ver
`docs/CODEX-HANDOFF-INVITACION-HIELO-2026-08-11.md`. `invitacion.php` pasó de
un solo `$playlistOrder` fijo a un mapa `$playlistOrdersByTheme` (una entrada
por tema) y `$heroAutoCandidatesByTheme` (candidato de hero=auto por tema);
`capitulos=auto` ya detecta la temática activa en vez de asumir Carreras.
Hielo usa sus 7 videos existentes (`saludo-elsa.mp4` … `despedida-hielo.mp4`)
con captions que llevan el nombre dinámico de la cumpleañera. Falta SOLO la
narración de audio (ver tabla abajo) — el resto (portada, música, sobre,
footer, playlist) ya funciona para hielo sin tocar código de nuevo.

1. La despedida global (punto 1 de arriba) **no se repite** — ya sirve para
   todas las temáticas, incluida hielo.
2. Por cada tema NUEVO (o para completar hielo), mirar su entrada en
   `$playlistOrdersByTheme` en `invitacion.php` para saber qué clips tiene y
   escribir una línea de narración natural para cada uno (no hace falta
   traducir el caption literal). Guardar en
   `public/themes/<tema>/narracion-video/<nombre-del-clip-sin-.mp4>.mp3`.
   **Pendiente para hielo:**

   | Archivo | Texto a narrar |
   |---|---|
   | `saludo-elsa.mp3` | "La magia de Elsa se enciende." |
   | `saludo-anna.mp3` | "Anna ya está lista para celebrar." |
   | `saludo-olaf.mp3` | "Una sorpresa nevada viene en camino." |
   | `saludo-kristoff.mp3` | "Kristoff también llega a la fiesta." |
   | `saludo-sven.mp3` | "Sven trae la aventura." |
   | `saludo-bruni.mp3` | "El reino completo se prepara para celebrar." |

   (`despedida-hielo.mp4` reutiliza el mp3 de despedida global, igual que en
   carreras.)
3. Por cada invitación NUEVA de cualquier tema, generar su narración de
   inicio con el texto compuesto desde sus propios datos (nombre, fecha,
   hora — plantilla ya en el formulario de admin) y subirla ahí. Esto es
   trabajo recurrente (una vez por invitación), no de una sola vez.
4. Nada de esto necesita tocar `invitacion.php`/`invitation.js` de nuevo — la
   lectura de archivos ya es genérica por tema/invitación, y el mapa
   multi-tema de Codex deja el patrón listo para sumar temas sin duplicar
   código.

## Validación (2026-08-11, verificado por Claude sobre el estado combinado: sobre + pie de página + mapa multi-tema de Codex)

1. `php -l` PHP 8.0.30 / 8.2.29 / 8.3.28 en `invitacion.php`, `lib.invitations.php`,
   `admin/invitations.php`, `descargar-invitacion.php`: limpio.
2. `node --check public/assets/invitation.js`: limpio.
3. `npm test`: 101/101.
4. `npm run build` + `scripts/check-dist-parity.php`: OK, 330 archivos
   (+1 sobre el conteo anterior por `candidate-hielo-auto.mp4`).
5. En navegador (Vicente/carreras, local): sobre se abre, música arranca
   (volumen 0.04 mientras habla Alice y botón mute visible), footer con logo+link+favicon cargan
   200, sección "¿Quieres conocer a Vicente?" visible tras el fix de
   `event_profile_enabled`.
6. La narración de inicio de Alice para Vicente usa un MP3 aprobado ya
   existente, asociado solo en SQLite local ignorado. Tras el intro temático,
   música, Alice y mute pasan QA en Carreras Scroll y Automático. Los audios
   de capítulos o despedida que aún falten siguen el inventario anterior.
