# CumpleClick: invitación de cumpleaños «Casa de Muñecas» con la Gabby animada (diseño)

- **Fecha:** 2026-10-29. Descripción aprobada por Luis en el chat de Confeti («apruebo»).
- **Datos de la familia:** nombres, edad exacta, dirección, teléfono y referencias que mandó Luis están en
  `C:/Users/luis_/Videos/cumpleclick-reels/material/cumple-gabby-2026-11-29/datos-evento.md`, fuera del repo. Este repo es
  **público**: ningún dato de la familia entra al código, las pruebas, los commits ni este documento.
- **Fiesta:** domingo 29-nov-2026, 15:00, en un salón de eventos. Cumpleaños infantil (`child_birthday`), niña de 4 años.

## 1. Intención

La mamá quiere que **Gabby, la de la serie animada «La casa de muñecas de Gabby», invite en video** al cumpleaños de su hija.
Luis decidió que no sea un video suelto más un enlace, sino **un solo enlace** que se manda por WhatsApp: la invitación con
el video adentro, la confirmación de asistencia y la sección «Conoce a la cumpleañera», como las invitaciones que ya hacemos.

Éxito: una mamá invitada abre el enlace en el celular, ve y escucha a Gabby invitando a la cumpleañera por su nombre,
confirma en pocos toques y **no se le pasa que tiene que llevar una media vieja** (con ella se hace un títere de mano en la
fiesta).

## 2. Qué lleva la invitación (aprobado por Luis)

1. Portada de la temática y, al abrirla, el **video de Gabby animada invitando**, con voz de niña.
2. Datos: día, hora y lugar, con mapa (el campo `address` de la invitación ya arma Google Maps y Waze).
3. **Aviso destacado:** cada invitado trae una media vieja para hacer un títere de mano.
4. **Conoce a la cumpleañera:** el perfil del evento que ya existe para `child_birthday`
   (`CumpleBooth/public/data/event-profile-presets.json`): gustos (colores, música, juegos, comidas, pasatiempos), tallas de
   ropa y número de zapatos. Se llena desde `admin/event-profile.php`; no hace falta programarlo.
5. **Confirmar asistencia** con lo de siempre (`lib.rsvp.php`: nombre de la familia y nombres de los niños que vienen).
6. **Música:** la manda Luis.
7. **Enlace privado de los papás** con los confirmados (`asistencia-papas.php`, el mismo de las otras fiestas).

**No lleva lista de regalos** (Luis: «No lleva lista de regalo, no»). El modo de regalos de la invitación queda apagado.

## 3. Metas, no metas y antimetas

- **Metas:** el video con Gabby nombrando a la cumpleañera; el aviso de la media imposible de pasar por alto; confirmar en
  pocos toques; el perfil completo; que se vea de primera en un celular de 375 px.
- **No metas:** lista de regalos; juegos en la tablet o cabina (si Luis los suma, es otro proyecto); capítulos largos de
  recorrido.
- **Antimetas (fracaso aunque funcione):**
  - Una Gabby **realista** o parecida a la actriz niña que la interpreta en las partes con actores: sería imitar a una niña
    real. Solo la versión **de dibujo**.
  - **Clonar** la voz de la actriz o la del doblaje. La voz es una ya existente, parecida en estilo.
  - Datos de la familia en el repo.
  - Gastar créditos sin el costo aprobado por Luis.
  - Confirmaciones de prueba que queden en la lista de los papás: la de prueba se borra antes de mandar el enlace, y después
    de mandarlo no se hacen más pruebas en esa fiesta.

## 4. Personaje, voz y música (decisiones de Luis)

- **Personaje:** la Gabby **animada**, como en la imagen de referencia que mandó Luis: niña de dibujo con cintillo de orejas
  de gato, polera a rayas blanco y negro y chaqueta rosada, junto a Pandy Paws (gato blanco y negro), MerCat (gata sirena
  turquesa) y Cakey (gato pastelito); fondo rosado y morado con rayos. Paleta de la temática: rosado, morado y turquesa.
- **Voz:** «muy parecida a la de Gabby original». Una voz de niña alegre que ya existe en la biblioteca de ElevenLabs, en
  español. Se graban 2 o 3 muestras cortas y Luis elige. Modelo `eleven_v3`, nivelada a −21,8 LUFS como las demás voces.
- **Música:** la manda Luis. Es para la invitación privada de la familia; si algún día se usa en Instagram, se cambia por
  la biblioteca de la app (misma regla que el álbum).

## 5. Restricciones

- **PROD:** `cumpleclick.com/app`, Hostinger, `main` = PROD. Se despliega por SSH solo con el go de Luis: cotejar,
  respaldar en `~/respaldos/`, subir y verificar desde afuera.
- **Temática nueva:** entrada en `public/data/themes.json` (CRLF) y en `event-profile-presets.json`, recursos en
  `public/themes/<slug>/` (la carpeta ya tiene `no-transform` contra el CDN). Igual que `baby-osito`: `vozPropia`, sin Alice.
- **Video:** la API de Higgsfield **no tiene lipsync** (`higgsfield-studio/references/api-rest.md`, línea 55). La voz va
  encima y Gabby aparece hablando y saludando a cámara; no se promete sincronía exacta de labios. Kling puede rechazar
  personajes de la serie; Hailuo 2.3 los acepta (`GUIA-POSFIESTA.md`, sección 4).
- **Saldo:** las dos cuentas de API de Higgsfield quedaron sin saldo para video en el baby shower. **Luis tiene que recargar**
  antes de generar.
- **ElevenLabs:** la clave tiene TTS y STT pero **no permite listar voces** (CLAUDE.md del repo). Para encontrar candidatas
  hace falta otra vía (abajo, pendientes de Luis).
- **Gastos:** cada generación con presupuesto presentado y ok de Luis, y registrada para Balance.

## 6. Presupuesto estimado (a aprobar antes de gastar)

Precios de lista de `api-rest.md` (la cuenta tiene 15 % menos):

| Pieza | Cálculo | US$ de lista |
|---|---|---|
| Imágenes Soul 2 (cuadro inicial del video, fondos de la temática, póster, despedida), unas 15 con iteraciones | 15 × 0,0032 | 0,05 |
| Video de invitación de unos 20 a 30 s, en clips de 10 s | Hailuo 0,0467/s o Kling 0,063/s × 30 s | 1,40 a 1,89 |
| Video de despedida, 10 s | × 10 s | 0,47 a 0,63 |
| Reintentos (50 % más del video) |  | 0,94 a 1,26 |
| **Total** |  | **≈ 2,9 a 3,8** |

**Actualización del 29-oct (sección 7c):** se genera por el **MCP**, así que el presupuesto real se mide en **créditos**
con `get_cost` y se presenta en el plan. La tabla de arriba queda como referencia de la API.

ElevenLabs: unos 1.300 caracteres (3 muestras de voz, guion del video, «gracias por confirmar», guía hasta confirmar y
despedida).

## 7. Lo que queda al constructor (decisiones mías, marcadas)

- **Nombre de la temática:** *decisión mía:* slug `casa-munecas`, nombre público «Casa de Muñecas», genérico como «Reino de
  Hielo». Los personajes aparecen en las imágenes y el video; el nombre de la serie no va en el catálogo.
- **Guion del video:** lo escribo yo y **Luis lo aprueba antes de grabar**. Lleva el nombre y la edad de la cumpleañera, el
  día, la hora, el lugar y el pedido de la media vieja.
- **Recorrido:** el de `baby-osito` como modelo (sobre, intro, voz de inicio, guía hasta confirmar, «gracias por
  confirmar», despedida).
- **Diseño del aviso de la media** y de los colores, desde la imagen de referencia.
- **Crear la fiesta en PROD:** adaptando los scripts privados del baby shower
  (`C:/Users/luis_/Videos/cumpleclick-reels/herramientas/prod/baby-shower/`).

## 7b. Respuestas a la revisión previa (decisiones mías, marcadas)

- **La imagen de referencia** es la captura que Luis mandó por chat: Gabby **de dibujo** con Pandy Paws, MerCat y Cakey
  (verificado al verla). Está en la carpeta privada como `referencia-gabby-captura.webp`. No entra al repo.
- **Cómo sale una Gabby fiel en vertical:** *decisión mía.* Primero, el cuadro inicial se arma desde la referencia y se anima
  con Hailuo 2.3 i2v por API, que acepta personajes de la serie. Si hace falta recomponer el cuadro en 9:16, se usa un modelo
  que acepte imagen de referencia, con el costo presentado antes. Si eso tampoco sirve, se compone con ffmpeg sobre el fondo
  rosado de la temática. Regla de Higgsfield: después de dos rechazos se cambia de fuente.
- **El video con el nombre es propio de esta fiesta,** como la despedida propia de Coquetería Pop
  (`themes/<tema>/despedida-fiesta-<slug>.mp4`, solo en el servidor y en `.gitignore`; `public/lib.php:1527`). *Decisión
  mía:*
  - Los clips de Gabby se generan **sin el nombre** y quedan como intro genérico de la temática, reutilizable.
  - La voz de Gabby, con el nombre, la edad, el día, la hora, el lugar y la media, se mezcla encima con ffmpeg en este PC.
  - El resultado es `themes/casa-munecas/invitation/intro-fiesta-<slug>.mp4`, solo en el servidor.
  - La invitación lo prefiere al intro de la temática cuando existe (cambio de PHP).
  - Si cambia un dato, se vuelve a mezclar el audio sin regenerar el video.
  - Mientras ese video suena, no hay una voz de inicio aparte: una sola voz a la vez.
- **«Casa de Muñecas» es temática de catálogo,** reutilizable para otras fiestas. *Decisión mía:* los fondos, los colores y
  el intro genérico quedan para la próxima.
- **Envío:** *decisión mía.* Hay un mes, así que se manda **una sola vez y completa** (video, perfil y música). Antes de
  mandarla se hace una confirmación de prueba en PROD y se borra. Después, los cambios van por la misma URL, sin
  confirmaciones de prueba.
- **Código que hay que tocar (no son solo datos):**
  - La guía hasta confirmar hoy solo se carga en el modo de regalos `rsvp` (`invitacion.php`, líneas 271 a 280). Se
    generaliza para los cumpleaños sin regalos.
  - El recorrido con videos está escrito a mano por temática (`$playlistOrdersByTheme`), y las variantes de narración
    dependen de `$esBabyShower`. La temática nueva necesita su entrada en el código.
  - La sección de regalos nunca se dibuja en `child_birthday`: «sin lista de regalos» se cumple solo.

## 7c. Respuestas de Luis (29-oct)

- **Cortesía.** La fiesta se **exime de Términos**, como el baby shower, y se activa. Para Balance, los gastos van como
  cortesía de CumpleClick, sin ingreso. *Decisión mía:* plan `full`, como el baby shower, para que la página lleve sus
  videos.
- **El salón es el del condominio** y la mayoría de los invitados son vecinos: basta la dirección. *Decisión mía:* el texto
  del lugar dice «Salón del condominio».
- **Higgsfield: primero la API, en la cuenta que tenga saldo, y si no hay, el MCP** (Luis, 29-oct, dos veces).
  - La API no tiene endpoint de saldo. Lo último medido es del 24-oct: la cuenta `n8n` rechazó un video por saldo, y en
    `api-n8n` el clip de 10 s se rechazó y el de 5 s salió.
  - El saldo se comprueba con el primer video real: un rechazo por saldo (`403 not_enough_credits`) no se cobra
    (`api-rest.md`). Se intenta en `api-n8n`, después en `n8n`, y si las dos rechazan se pasa al MCP.
  - En la API, Wan 3.0 cuesta US$0,05 por segundo de lista y acepta imágenes de referencia de identidad. Falta comprobar si
    la API acepta audio de referencia; si no lo acepta, el lipsync va por el MCP.
  - **El MCP tiene 160,06 créditos (plan starter, consultado el 29-oct con `balance`)**. Allí, a 720p, Seedance 2.0 mini
    cuesta 1,0 crédito por segundo, Seedance 1.5 cuesta 1,2 y Wan 3.0 cuesta 1,75 (`get_cost`, 29-oct).
  - Si el trabajo pasa al MCP, cambian dos restricciones de la sección 5:
  - Hay **Nano Banana con imagen de referencia**, para sacar el cuadro inicial de Gabby fiel y en 9:16.
  - Hay **lipsync**, así que Gabby puede hablar con los labios sincronizados con la voz. El costo de cada paso se mide antes
    con `get_cost`, que es gratis, y se presenta a Luis antes de generar.
- **La voz** (Luis no la definió). *Decisión mía:* acento latino neutro, como el doblaje. Se graban **tres muestras** con
  la misma frase y Luis elige escuchándolas:
  - Matilda v3 en tono infantil, por ElevenLabs.
  - Dos voces femeninas de la lista del MCP de Higgsfield (`list_voices`).
  - Ninguna es clon de la actriz ni del doblaje.

## 8. Cómo se acepta

- Revisión visual en un celular de 375 px: portada, video, aviso de la media, perfil, confirmación y enlace de los papás.
- Las voces se transcriben con `scribe_v1`: el nombre de la cumpleañera tiene que entenderse.
- Una confirmación de prueba en PROD antes de mandar el enlace, y se borra.
- Despliegue con respaldo y verificación desde afuera; `main` igual a PROD al cerrar.
- **Cargos:** Lienzo (temática e invitación), Plano (video), Forja (crear la fiesta, enlaces), Balance (gasto),
  Centinela (pruebas), Vigía (privacidad y la regla de no imitar a la actriz), Contralor (gates). Revisión B por Codex.

## 9. Pendientes de Luis

- La música.
- Los gustos, las tallas y el número de zapatos de la cumpleañera.
- Recargar Higgsfield.
- La vía para encontrar voces candidatas en ElevenLabs.
