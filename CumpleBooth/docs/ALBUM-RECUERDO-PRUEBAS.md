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
