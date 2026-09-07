# Álbum Recuerdo — guía para validarlo tú mismo

Todo esto se prueba **en tu WAMP local**, sin tocar PROD y sin gastar un solo
crédito. Rama `feat/album-recuerdo`, sin pushear ni mergear.

Tiempo estimado: 20 minutos si lo haces completo.

---

## 0. Preparar (una sola vez)

```bash
cd C:\wamp64\www\automatiza-tech\CumpleBooth
```

La migración ya está aplicada en tu base local. Si quieres confirmarlo:

```bash
php scripts/migrate.php
```

Debe decir `skip 007_event_album`. Si dice `applied`, también está bien.

Después, construye:

```bash
npm run build
```

A partir de acá todo se prueba en `http://localhost/automatiza-tech/CumpleBooth/dist/`.

> Si prefieres no depender de WAMP, `php -S localhost:8099 -t dist` levanta un
> servidor propio y todo funciona igual en `http://localhost:8099/`.

---

## 1. El admin del álbum (2 min)

1. Entra a `admin/index.php` con tu contraseña de siempre.
2. En cualquier fiesta, aprieta el botón nuevo **"Álbum Recuerdo"**.
3. Deberías ver cuatro bloques: Recepción, Enlace y QR, Contenido, Curaduría y
   Publicación.

**Qué mirar:** si la fiesta ya tiene fotos de la cabina, en "Contenido" deben
aparecer contadas y con **0 B de peso**. Eso es a propósito: las fotos de
cabina se referencian, no se copian, así que no consumen la cuota del álbum.

---

## 2. Generar el QR y subir como si fueras un invitado (6 min)

1. En **Recepción**, marca "Recibir fotos y videos de invitados" y "Permitir
   videos". Guarda.
2. En **Enlace y QR**, aprieta **"Generar enlace"**.
3. Copia el enlace que aparece. **Se muestra una sola vez** — si recargas la
   página ya no está. Eso también es a propósito: en la base solo queda su
   huella.
4. Abre ese enlace **en tu celular** (si estás en la misma WiFi, cambia
   `localhost` por la IP de tu PC).

En el celular deberías ver la página de carga con **los colores de la temática
de esa fiesta**, no una paleta genérica. Sube dos o tres fotos.

**Qué mirar:**

- La barra de progreso avanza de verdad, archivo por archivo.
- Escribe un nombre y un mensaje; ambos son opcionales.
- Sin marcar la casilla de permiso, el botón no se habilita.
- Al terminar sale la pantalla de gracias diciendo que el organizador lo va a
  revisar.

**Prueba que debe FALLAR:** intenta subir un PDF o un archivo de Word
renombrado a `.jpg`. Tiene que rechazarlo. No mira la extensión: mira los
bytes.

---

## 3. El cartel para imprimir (1 min)

Abre `cartel-qr.html?t=<el mismo token del paso 2>`.

Sale la hoja lista para imprimir, con el QR grande, el nombre de la fiesta, el
mensaje y la URL en texto para quien no pueda escanear. Aprieta "Imprimir el
cartel" y mira la vista previa: debe salir en una sola página, vertical.

**Escanea el QR de la pantalla con tu celular** — tiene que llevarte a la misma
página de carga del paso 2.

---

## 4. Curaduría (4 min)

Vuelve a `admin/album.php?party=<slug>`. Lo que subiste debe estar ahí, en
**"Por revisar"**.

Prueba, en este orden:

1. **Aprobar** una foto → el contador de "Por revisar" baja.
2. **Portada** en una foto aprobada → le aparece la insignia y deja de
   ofrecerse el botón.
3. **Ocultar** justamente esa portada → el mensaje debe decir *"Se quitó como
   portada"*. El álbum no queda apuntando a algo que ya no se muestra.
4. **Eliminar** una foto → desaparece del listado y aparece en el filtro
   "Eliminados".
5. Entra al filtro **"Eliminados"** y **Restaura** esa foto → vuelve completa.

**Lo importante de la 4 y la 5:** eliminar **no borra el archivo**. Solo lo
marca. Por eso se puede restaurar. El borrado real lo hace la retención, junto
con el resto de la fiesta.

También prueba las **flechas ↑↓** para reordenar, y si estás en computador,
**arrastra una tarjeta** a otra posición: aparece un botón para guardar el
orden nuevo.

---

## 5. La revista (5 min)

1. En **Publicación**, ponle título y subtítulo. Guarda.
2. Aprieta **"Publicar el álbum"**.
3. Copia el enlace de la revista que aparece. **También se muestra una sola
   vez.**
4. Ábrelo.

**Qué mirar:**

- Si dejaste marcado "Exigir el PIN de galería", primero pide el PIN de 4
  dígitos de esa fiesta. Ese es el mismo PIN de la galería de siempre.
- La portada usa la foto que elegiste y muestra el nombre de la temática.
- **Pasa las páginas**: arrastrando con el mouse, con las flechas del teclado,
  o con los botones. En el celular, tocando la mitad derecha o izquierda.
- Al final hay una página de cierre que cuenta cuántos recuerdos hay.
- Prueba el botón **"Ver como lista"**: la misma revista, en columna, sin
  volteo. Eso es lo que van a ver los teléfonos viejos o quien tenga activado
  "reducir movimiento" en su sistema.

**En computador** debe verse como un libro abierto de dos páginas con lomo al
medio. **En celular**, de a una página. Si redimensionas la ventana, cambia
solo.

---

## 6. Las pruebas de seguridad (2 min)

Estas son las que más me importan. Todas deben fallar:

| Prueba | Qué debe pasar |
|---|---|
| Abrir `subir.php` sin `?t=` | Página de "Enlace no válido" |
| Inventar un token de 32 caracteres en `subir.php?t=...` | "Este enlace ya no está disponible" |
| En el admin, **Revocar enlace**, y volver al enlace de carga del paso 2 | Deja de funcionar de inmediato |
| Abrir `album.html?t=<token del QR de carga>` | No sirve: son llaves distintas |
| **Despublicar** el álbum y volver al enlace de la revista | Deja de funcionar de inmediato |
| Abrir la revista de una fiesta con el enlace de otra | No sirve |

---

## 7. Que el kiosco no se rompió (1 min)

Esto es lo que más riesgo tenía, porque toqué `App.jsx`.

Abre el kiosco normal: `index.html?p=<slug de una fiesta>`.

Tiene que verse **exactamente igual que antes**: los colores de la temática, la
ruleta con su fondo, los personajes. Si algo se ve distinto, avísame.

---

## Lo que yo NO pude probar y necesita tus manos

| Qué | Por qué |
|---|---|
| **Subir desde un iPhone** | Los iPhone mandan HEIC y GD no lo lee. Safari suele convertir solo a JPEG, pero no siempre. Necesito saber si funciona con uno real antes de prometerlo. |
| **El póster de los videos** | Lo captura el navegador del invitado del primer fotograma. Mi video de prueba lo armé byte a byte y Chrome no lo decodifica, así que ese camino cayó en su respaldo (subir sin póster). Graba un video con el celular y súbelo. |
| **El arrastre de páginas con el dedo** | Verifiqué el código y el resultado, pero no el gesto real. |
| **Imprimir el cartel en papel** | La vista previa se ve bien; el papel es otra cosa. |

---

## Lo que falta decidir (te lo dejé pendiente)

**Las cuotas.** Están puestas conservadoras y en un solo lugar
(`cb_album_limits()` en `public/lib.album.php`), para que cambiarlas sea una
línea:

| Límite | Ahora |
|---|---|
| Archivos por envío | 10 |
| Peso por foto | 12 MB |
| Videos por envío | 2 |
| Peso por video | 40 MB |
| Duración de video | 30 s |
| Total por álbum | 400 archivos / 3 GB |
| Envíos por invitado | 30 archivos cada 10 min |
| Retención del álbum | 90 días |

Los 3 GB por álbum es el número que necesito revisar contigo: multiplicado por
varias fiestas puede llenar el Hostinger y afectar el sitio de AutomatizaTech.
Dime cuánto espacio real tienes y lo ajusto.

---

# Rediseño de la revista y bugs encontrados (2026-08-25)

Estado: **hecho en local, sin deploy.** Rama `claude/invitacion-url-plan-y-3-temas`.

## Los dos bugs

Ninguno daba error. Los dos se veían como decisiones de diseño feas.

**1. La página de nota salía vacía.** `pages.js` mandaba a diseño de
dedicatoria cualquier recuerdo con **mensaje o autor**. Como el formulario del
invitado pide el nombre y deja el mensaje opcional, la mayoría de las fotos
caían ahí: la revista armaba una cita con su comilla, su firma abajo y un hueco
enorme en el medio donde no había nada escrito. Ahora sólo el **mensaje** hace
página de nota; sin mensaje la foto sigue el flujo normal y el nombre aparece
como crédito al pie.

**2. El precheck del seed pedía recortes de Reino de Hielo para cualquier
tema.** `_at-seed-cita-completa.php` exigía `elsa-cut.png`, `anna-cut.png`, etc.
aun cuando había una carpeta `_fotos-demo` con fotos reales, que es justo el
caso en que esos recortes no se usan. Resultado: el script no podía armar una
fiesta de ningún tema que no fuera hielo. Ahora la guía del tema sólo se exige
cuando de verdad hay que componer con GD.

## Qué cambió visualmente

El álbum se veía igual en todas las temáticas: `--paper` era una crema fija
(`#fffdf8`), así que la temática vivía sólo dentro de las fotos y el resto de la
revista no se enteraba. Ahora:

| Elemento | Antes | Ahora |
|---|---|---|
| Papel | crema fija para todos los temas | derivado de `bgLight1` del tema |
| Fondo de hoja | plano | confeti impreso en dos colores del tema, más un tinte suave arriba |
| Canto de página | nada | franja superior en el acento del tema, más filete alrededor |
| Marco de foto | `box-shadow` interior que la foto tapaba, o sea invisible | passe-partout blanco real (`border`) + aro del tema + sombra teñida |
| Composición | rectángulos iguales, todos derechos | fotos inclinadas alternando lado, foto sola a sangre en páginas impares, dúo asimétrico, mosaico desalineado |
| Dedicatoria | serif de sistema, raya larga antes del nombre | manuscrita Caveat, cinta washi en el color del tema, firma con guión como elemento |
| Entrada | nada | foto que se acerca, tarjeta y texto que suben, escalonados; todo detrás de `prefers-reduced-motion` |

El passe-partout es el caso que vale contar: estaba escrito como
`box-shadow: inset 0 0 0 0.9cqw #fff`, y un `box-shadow` interior se pinta
**debajo del contenido**. La foto lo tapaba entero. El marco blanco llevaba
tiempo en el CSS sin verse nunca.

## Video en la revista

La página de video existía en el código desde el principio pero no había ningún
video con qué verla. El seed ahora acepta un `_video-demo.mp4` al lado del
script y le arma su página, con insignia y pie de autor.

El póster (el primer cuadro que se ve antes de apretar play) sale de un
`_video-demo.jpg` hermano. Se intentó primero con ffmpeg y **no sirve**: PHP
bajo Apache no lo encuentra en el PATH, y en hosting compartido muchas veces
`exec()` viene deshabilitado. O sea que justo en producción no habría póster.
El archivo hermano es el camino confiable; ffmpeg quedó de respaldo.

## Mensajes del seed

Estaban escritos para una niña llamada Isidora: "reina del hielo", "Perdón,
prima", "Mi niña querida". Al correr el script con otro tema y otro
protagonista la revista felicitaba a alguien que no era. Ahora son neutros en
género y toman `$NOMBRE`.

## Cómo se probó

Dos fiestas completas en una instancia local aparte
(`C:\wamp64\www\cumpleclick-local`, storage privado fuera del webroot):

- **Reino de Hielo**, 9 fotos + 1 video, 5 con mensaje.
- **Carreras**, 9 fotos, 5 con mensaje.

Medido con Chromium en 1280x860 y 390x844, en revista y en lista:
0 imágenes rotas, 0 errores de consola, sin desborde horizontal, Caveat
cargando, y el papel devolviendo `srgb(0.95 0.98 1.0)` en hielo contra
`srgb(1 0.98 0.93)` en carreras — o sea que el tema sí llega al papel.

Las fotos de las dos demos se generaron con Higgsfield (`soul_2`, 3:4) y el
video con `kling3_0_turbo`. Viven en `storage/fotos-demo-hielo/` y
`storage/fotos-demo-carreras/`, fuera de git.

## Lo que sigue sin probarse

Lo mismo de la lista de arriba: el arrastre con el dedo en un teléfono de
verdad y el cartel impreso en papel.
