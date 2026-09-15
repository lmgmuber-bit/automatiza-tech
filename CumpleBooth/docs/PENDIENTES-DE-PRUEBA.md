# Pendientes de prueba

Lo que está **en producción pero nadie ha visto funcionando de verdad**. Todo lo de acá pasó
verificación técnica (md5, `php -l`, HTTP, mediciones), pero eso comprueba el mecanismo, no el
resultado con gente y hardware real.

Última actualización: **2026-09-12**. Las fiestas reales son el **domingo 13 de septiembre de
2026**: `luciano-spidey` y `samantha-hielo`.

Cuando algo se pruebe, márcalo y anota qué falló, no solo que "funcionó".

---

## 1. Tablet física — nada se ha probado ahí

Es el hueco más grande. La cámara está bloqueada en el entorno donde trabajo, así que **el
flujo con cámara nunca se ha ejecutado**, en ninguna de las dos tablets (Tab A7 y A9+).

- [ ] Asómate completo: elegir personaje → foto → ajustar → guardar.
- [ ] Los tres deslizadores (tamaño, izquierda/derecha y altura). **Cuánto hay que moverlos** para que la cara caiga
      dentro del hueco. Si hay que moverlos mucho, el punto de partida está mal elegido y hay
      que cambiar los valores por defecto, no pedirle al niño que apunte mejor.
- [ ] Que el QR de la foto se pueda escanear y descargar desde otro teléfono.
- [ ] Aventura 3D y el menú de juegos, en las dos tablets.
- [ ] **La foto grupal completa (2026-09-12).** Luis vio en las dos tablets el ícono de abajo a
      la izquierda; falta el flujo entero: tablet acostada, el marco, guardar y verla en la
      pestaña "La foto de todos" de la galería. Probada con cámara falsa en las dos temáticas y
      en PROD, nunca con una cámara de verdad.
- [ ] **La pantalla completa del kiosco (2026-09-12).** El ícono de arriba a la izquierda entra
      y sale, y el toque de "Toca para entrar" también entra. Probada en un Chromium de
      escritorio; el panel del navegador de trabajo la bloquea.
- [ ] **Sacar una foto dentro del juego de Spidey (2026-09-12).** Moría con
      "T.foto.firmaJugador is not a function" y se corrigió; se comprobó leyendo en PROD los
      textos ya mezclados, no sacando la foto en una partida.
- [ ] **Anna de gala en el Reino de Hielo 3D y en el Festival (2026-09-12).** Textura
      repintada con malla, esqueleto y baile idénticos. Vista en un mirador de escritorio, no
      en la tablet.

## 2. Asómate — lo que solo se vio con una cara dibujada

La verificación usó un rostro dibujado en SVG, no la foto de un niño.

- [ ] **Spin con una cara real.** Su hueco estaba corrido y se corrigió el 2026-09-09; se
      confirmó que los dos lentes de la máscara quedan simétricos, pero con cara de prueba.
- [ ] **El diploma con una foto real.** El diploma de Asómate ahora lleva la escena del niño
      hecho héroe, enmarcada. Solo se vio con el rostro dibujado.
- [ ] **Modo grupo, dos y tres niños.** Los cálculos lo cubren y el reparto en carriles está
      escrito, pero **nunca se miró una escena de grupo**. Es donde más probable es que algo
      se vea mal: cada personaje tiene otra proporción cabeza/cuerpo.
- [ ] Los otros cinco personajes arácnidos con cara real. Solo se revisó Spidey y Spin.
- [ ] La temática de hielo entera con los huecos nuevos de Elsa y Olaf (2026-09-10, cierre 7):
      antes se salían del personaje. Luis probó Frozen antes de ese cambio y dijo que estaba bien.
- [ ] **La bienvenida y el pase de artista de Frozen con voces, oídos en la tablet (2026-09-10,
      cierres 8 y 9).** Seis voces verificadas por transcripción; falta el oído de una persona.
- [ ] **La guía del óvalo con un niño real (2026-09-10).** Si "pon tu cara dentro del óvalo" lo
      entiende un niño de cuatro años o hay que decírselo. Desde el cierre 3 la cara se detecta y
      se ajusta sola, así que la guía ya solo pide "más o menos ahí".
- [ ] **El ajuste automático con un niño real (2026-09-10, cierre 3).** Probado con una cara real
      pegada en una cámara falsa, no con una persona. Mirar en Spidey si la cara queda del tamaño
      que a Luis le gustó (ahí el hueco está dentro de la máscara); el número es `factor` en
      `src/caraAuto.js` (1,1). Y que el detector (9,4 MB, se baja al abrir el kiosco) esté listo
      antes de la primera foto del día en el wifi del salón.
- [ ] El deslizador ↔ con una cara real: que la dirección se sienta natural (a la derecha, la
      cara va a la derecha).
- [x] ~~Ghost-Spider: el aro blanco de la capucha como "otro óvalo"~~ — era el CDN achicando las
      imágenes en la tablet (cierre 5 del 2026-09-10); Luis confirmó en la tablet que calza.
- [ ] **El video de despedida en la tablet, con el wifi del salón (2026-09-10).** Luis lo vio
      fallar ahí (salía la tarjeta "gracias por venir" y la portada). Ahora la despedida se
      baja entera al abrir el kiosco y se reproduce desde memoria; hay que verlo en la tablet.
- [ ] **La bienvenida de spidey con las voces nuevas, en la tablet (2026-09-10, cierre 4).**
      Ahora la niña saluda primero ("¡Hola! ¡Bienvenidos a la fiesta!") mientras agita la mano,
      Spidey dice "¡Qué alegría que vinieron!" cuando abre la boca y Spin cierra, ya con su
      frase regrabada porque la toma anterior se cortaba en la última sílaba. Las tres
      niveladas; la transcripción automática las entiende exactas. Falta que lo oiga una
      persona. Si Luis prefiere la toma de Alice para la niña, es cambiar un MP3 y remezclar.
- [ ] **Recargar el kiosco en las tablets después de cada subida.** Es una página que se abre una
      vez y no se actualiza sola: el 2026-09-10 Luis probó Asómate con la versión anterior
      después de que ya estaba subida la nueva. Antes de la fiesta, cerrar la pestaña y abrirla
      de nuevo en las dos tablets.
- [ ] **La bienvenida en la Tab A7, de corrido (2026-09-10, cierre 6).** Luis la vio lenta y
      con Spin cortado; el temporizador que la cortaba ya espera mientras el video avance, y el
      detector de caras ya no se descarga al abrir el kiosco. Falta ver si en esa tablet el video
      además se traba por la tablet misma; comparar con la otra tablet.
- [ ] **El video de bienvenida en la tablet.** Tiene la misma red de seguridad corta (1,8 s)
      y NO se tocó. Si sale como póster fijo en vez de video, es lo mismo y el arreglo es el
      mismo precargador (`videoListo.js`).

## 3. Imprimir desde el admin

El mecanismo está probado por dentro: arma una página por foto, espera a que carguen todas y
recién ahí llama a imprimir. Eso **no prueba la impresora**.

- [ ] **Sacar una hoja de verdad en la Selphy**, desde Admin → Fiestas → Álbum → Fotos del
      kiosco. Empieza con una foto y una copia.
- [ ] Que el diálogo proponga el papel 10×15 sin tener que buscarlo.
- [ ] La opción "llenar la hoja": comprobar cuánto recorta arriba y abajo en papel real.
- [ ] Imprimir varias fotos marcadas de una vez.

## 4. El admin, con sesión iniciada

No tengo la contraseña del admin, así que **varios bloques nuevos nunca se vieron
renderizados**. Se subieron con `php -l` limpio, que solo dice que el PHP es válido.

- [ ] El bloque "Confirmados · enlace para la familia" en la ficha de la fiesta, ya sin los
      botones de generar y anular, con el enlace fijo y el mensaje de WhatsApp desplegado.
- [ ] La papelera de fotos del kiosco. Hasta el 2026-09-09 mostraba miniaturas rotas porque
      `ver.php` rechaza las fotos borradas; ahora una sesión de admin sí las ve. **Nadie ha
      abierto esa pestaña después del arreglo.**
- [ ] La barra de selección múltiple con los controles de impresión metidos dentro.

## 5. La lista de confirmados, del lado de la familia

- [ ] Abrirla **en un teléfono de verdad**. Se probó a 320, 375 y 1366 px de ancho, pero
      emulando: sin scroll horizontal en ninguno y la columna centrada en escritorio.
- [ ] Que la mamá de Luciano la entienda sin explicación.

## 6. Los dos botones de los juegos y el fondo del menú (2026-09-09)

- [x] ~~Pantalla completa en un aparato de verdad.~~ **Luis la probó el 2026-09-09 y
      funciona.** No se podía verificar desde el entorno de trabajo: el panel de revisión
      bloquea la pantalla completa y responde "Permissions check failed", igual para el botón
      propio de Circuito. En Safari de iPhone el botón no se dibuja a propósito, porque ese
      navegador no permite pantalla completa en una página; eso sigue sin verse en un iPhone.
- [ ] Que el botón de volver no le quede a mano a un niño en plena partida. Pide dos toques,
      pero eso solo se sabe viendo a un niño usarlo.
- [ ] El fondo temático en los cuatro juegos, con la tablet en horizontal.

⚠️ **Riesgo anotado, no un pendiente de prueba:** la línea `<script src="pantalla.js">` se
agregó editando los HTML **bajados de PROD**, porque `mundo.html` y `festival/index.html`
vienen compilados de otros repositorios. **Un export nuevo de cualquiera de esos dos borra
esa línea** y los juegos se quedan sin los botones, sin que nada falle a la vista. Si se
vuelve a exportar el mundo 3D o el Festival, hay que volver a agregarla.

## 7. Cosas de fiesta, no de código

- [x] ~~La lista de invitados de Samantha está vacía.~~ Cargada el 2026-09-09 con los 10
      niños de las confirmaciones. La de Luciano se rehizo con los 17 confirmados; los 6 que
      tenía eran de prueba.
- [ ] **Faltan los niños de cuatro familias** que confirmaron sin anotarlos: Irais Caldera en
      la de Luciano, y "Madrina favorita con Raymond" y Richard González en la de Samantha.
      Esos van a tener que escribir su nombre a mano en la tablet.
- [ ] Confirmar que los QR impresos apuntan a lo que corresponde, con los carteles ya
      montados en sus soportes.

---

## 7-B. Circuito Arácnido (2026-09-10, cierre 10)

- [ ] **Pisar una rampa de flechas y sentir el empujón.** Ahora empuja de una (23 a 37 de
      velocidad, 1,5 s) en vez de recargar el turbo. Si queda flojo o exagerado, el número está
      en `sim.mjs`.
- [ ] **Recoger los rayos amarillos.** Ocho por vuelta; recargan la barra +30. Ver si se ven
      bien en la tablet y si los niños los buscan.
- [ ] **El podio en un iPhone.** Es donde el usuario reportó que ningún botón respondía. Se
      cerraron las dos causas posibles, sin iPhone para comprobarlo.

## 7-C. Aurora de Cristal, nueva en el menú de hielo (2026-09-11)

Tercer juego de la temática de hielo, integrado desde el paquete de Codex. En PROD, sin ver
en tablet. Detalle en `AURORA-INTEGRACION-CLAUDE.md`; lo de Codex, en `AURORA-QA-INTEGRACION.md`.

- [ ] **Una carrera completa en la tablet**, en las dos, y mirar si va fluido. Codex midió una
      carrera con décimo percentil de 29,94 fps en escritorio: **no está prometido que corra
      bien en la Tab A7**. Si va a tirones, es el primer candidato a quedar fuera del domingo.
- [ ] Las dos orientaciones, la pantalla completa, el audio y la vuelta al menú de la fiesta,
      tocadas por una persona.
- [ ] Varias partidas seguidas y que las preferencias se guarden entre una y otra.
- [ ] Que convivan los tres juegos de hielo sin pisarse.
- [ ] **El aviso de red de Codex** (`ERR_ABORTED` en los modelos). En la primera carga desde
      PROD no apareció ninguno, pero el asunto sigue abierto: mirarlo en la tablet.
- [ ] Decidir si Aurora debe anotar puntajes. Hoy no lo hace, así que no sale en las
      posiciones; su mejor marca vive solo en el navegador del niño.
- [ ] El rótulo "Primera versión local" se retira cuando Luis la apruebe.

## 8. El CDN y las tablets

- [ ] **Cualquier imagen nueva de temática, verla en la tablet, no en el PC.** El CDN de
      Hostinger sirve a Android copias achicadas a 800 px. Está frenado con `no-transform` en
      `themes/.htaccess` y el kiosco dibuja con medidas anotadas, pero una carpeta nueva fuera
      de `themes/` no tiene esa cabecera. Para comprobar sin tablet: pedir la imagen con el
      User-Agent de Android y mirar el tamaño.
- [ ] Después de subir imágenes, si la tablet sigue mostrando las viejas: vaciar el caché del
      CDN en hPanel (Luis lo hizo el 2026-09-10 y funcionó).

## 9. Si el servidor se cae en plena fiesta

- [ ] **Qué hacer con un 504.** El 2026-09-10 a las 13:37 `cumpleclick.com/app/` estuvo un
      minuto devolviendo 504 por carga del servidor compartido de Hostinger (load 65), con
      nuestro código sano. Si pasa el domingo: esperar un minuto y reintentar; el kiosco ya
      abierto sigue andando (las fotos se componen en la tablet), lo que falla es guardar,
      subir y entrar a las salas de los juegos. Tener a mano el soporte de Hostinger.
- [ ] **La copia de la foto en la tablet, en una tablet de verdad (2026-09-12).** Desde ese día
      los tres caminos (cabina, Asómate y foto grupal) bajan la foto a la tablet **antes** de
      subirla. Se probó con cámara falsa y la subida caída, en local y en PROD; nunca en una Tab
      A7. Con la primera foto de cada fiesta, abrir **Descargas** y comprobar que el archivo está:
      el código dispara la descarga y **no comprueba que haya ocurrido**, y la pantalla del QR
      afirma "La descarga local está segura" igual.
- [ ] **La galería con muchos papás en el mismo wifi.** El límite del PIN es 5 intentos por
      minuto **por IP** y cuenta también los aciertos: los teléfonos del wifi del salón comparten
      una IP y el sexto ve "Demasiados intentos" con el PIN correcto. Leído en el código, no
      visto. Mientras no se arregle: datos móviles y por tandas.
- [ ] **Reintentar una subida fallida una sola vez.** `upload.php` no reconoce la misma foto dos
      veces: cada reintento crea otra copia y gasta otro de los 200 cupos de la fiesta.
- [ ] **No recargar el kiosco sin red.** No hay service worker: una recarga con la red caída deja
      la tablet sin kiosco. Mientras la pestaña siga abierta, cámara y composición funcionan.

El respaldo completo —cola de fotos en la tablet que se vacía sola, rechazo de duplicados en el
servidor, pantallas que digan la verdad y arranque sin red— está diseñado y **no construido**:
unas tres sesiones.

## 10. Las demás temáticas — DESPUÉS del 13 de septiembre

Decisión de Luis (2026-09-10): hasta la fiesta se trabaja solo en `luciano-spidey` y
`samantha-hielo`. Todo lo que se descubrió hoy hay que revisarlo en cada temática antes de
su primera fiesta, con las mismas herramientas:

- [ ] **Audio de los videos** (bienvenida, pase de artista, revelación, despedida): `ffmpeg -af
      ebur128` por archivo; bajo −40 LUFS está mudo (el pase de Frozen daba −52). Si hay voz,
      transcribir con ElevenLabs `scribe_v1` y comparar con la frase escrita; nivelar las
      líneas entre sí. Receta completa en `FTP-MANIFEST.md`, cierres 4, 8 y 9 del 2026-09-10.
- [ ] **Huecos de Asómate** (carreras, familia-canina, heroes, kpop, tropical, cuando se hagan):
      el borde exterior del óvalo tiene que caer 100 % dentro de la figura. Regla y método en
      `ASOMATE-NUEVAS-TEMATICAS.md`.
- [ ] **Imágenes de más de 800 px de ancho fuera de `themes/`:** el CDN se las achica a Android.
      `themes/.htaccess` ya manda `no-transform`; cualquier carpeta nueva con imágenes que se
      dibujen con geometría anotada necesita lo mismo.
- [ ] **Videos pesados**: la despedida se precarga en memoria; las bienvenidas largas de otras
      temáticas se apoyan en el temporizador que ahora espera mientras el video avance.

## Por qué esta lista existe

Porque "verificado" y "probado" no son lo mismo, y confundirlos ya costó caro acá: el md5 de
un archivo puede calzar perfecto mientras la página que lo usa está rota. Ver
`reference_cumpleclick_prod_vs_repo` y la sección de cada cambio en `FTP-MANIFEST.md`, donde
cada entrada tiene su propio apartado **No probado**.
