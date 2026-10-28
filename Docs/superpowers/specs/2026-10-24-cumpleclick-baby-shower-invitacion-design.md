# CumpleClick — Invitación de baby shower con regalo validado (diseño)

Fecha: 2026-10-24. Pedido de Luis. Es la **primera fiesta de baby shower real** de CumpleClick y la pieza de muestra de la
modalidad: tiene que impresionar. La invitación **sale el 25-oct** y la fiesta es el **31-oct**, por la tarde. También es
fiesta de disfraces de Halloween.

Este repositorio es público. Los datos de la familia (nombres, dirección, teléfono) **no van aquí**: están en la nota privada
`C:/Users/luis_/Videos/cumpleclick-reels/material/baby-shower-2026-10-31/datos-evento.md`, fuera del repositorio. En este
documento se habla de «el bebé», «la mamá», «el papá» y «el hermano».

## 1. Intención

La familia quiere una invitación que, además de invitar, **ordene los regalos**: cada invitado dice qué lleva antes de
confirmar, para que no lleguen dos coches o dos cunas. Los papás siguen en un enlace propio quién viene y qué trae. Para
CumpleClick es la primera muestra de baby shower, así que el primer toque (el sobre que se abre) y el aspecto general tienen
que verse profesionales y únicos.

## 2. Alcance de esta entrega (proyecto 1 de 4)

**Entra:**
1. **Temática visual nueva**, sacada de la imagen de invitación que entregó Luis: osito de peluche, verde y amarillo, globos
   y huellitas, con un toque sutil de Halloween (una calabaza, un fantasmita) porque es fiesta de disfraces.
2. **Intro asociado a la temática:** el sobre que se abre ya existe; se viste con esta temática y se le suma el intro
   cinematográfico opcional por temática (`themes/<tema>/invitation/intro-invitacion-wow-v1.mp4` y su póster), hecho con
   Higgsfield.
3. **La invitación:**
   - Contenido: nombre del bebé, fecha, hora y lugar, «agregar a mi calendario» y cómo llegar con Waze y Google Maps (estos
     dos ya existen).
   - Avisos de la fiesta: «Ven disfrazado, también celebramos Halloween», «trae un obsequio para el bebé» y «podrán jugar
     bingo, barajas o dominó, además de juegos relacionados con el agasajo».
   - Recorrido y voz: el recorrido de capítulos y la narración, con la voz que elija Luis.
4. **Confirmación de asistencia con regalo** (detalle en el punto 4).
5. **Enlaces de los papás con la temática:**
   - quién confirmó, con su equipo y su regalo;
   - la lista de regalos con quién trae qué.

**Queda fuera** (son proyectos aparte, después de que salga la invitación):
- **Proyecto 2:** juegos en la tablet con voz, Team Mamá contra Team Papá. Incluye:
  - los rescatados del cumpleaños de Ana: «¿Quién es más venezolano?», la edición política y «¿Cómo se dice en Chile y en
    Venezuela?»;
  - los nuevos: trivia del bebé, «¿Mamá o papá?», doble sentido de bebé, la palabra prohibida, «¿Quién es este bebé?» y
    «¿Quién trajo este regalo?»;
  - el marco grande de la cabina para las fotos con la mamá, el papá y los invitados.
- **Proyecto 3:** sopa de letras en vivo por QR, con tabla de posiciones y mensaje final según el lugar de cada uno.

Esta entrega **sí debe guardar** los datos que esos juegos van a usar: el equipo de cada invitado, su regalo y su foto de bebé.

## 3. Metas, no metas y antimetas

- **Metas:** que nadie pueda anotar un regalo único que ya está tomado; que la invitación se vea de primera en el celular;
  que los papás vean todo sin pedir nada a nadie; que salga el 25-oct.
- **No metas:**
  - pagos;
  - cuentas de invitado;
  - editar o borrar una confirmación desde la invitación (si alguien se equivoca, lo corrige el admin);
  - lista de regalos sugerida por los papás: este modo es el contrario, el invitado propone.
- **Antimetas:**
  - un regalo único repetido porque se escribió distinto («coche» y «cochecito»);
  - que un invitado vea **quién** trae cada cosa: ve qué está tomado, nunca el nombre;
  - la voz de Alice, que a Luis le suena española;
  - una página pesada que tarde en abrir en un celular con datos móviles;
  - datos de la familia en el repositorio;
  - gastar créditos sin el ok de Luis.

## 4. Confirmación con regalo (decisiones de Luis)

Al tocar «Confirmar asistencia», el invitado completa, en este orden:

1. **Su nombre.** Obligatorio, como hoy.
2. **El regalo que llevará.** Obligatorio, en texto libre. Antes de guardar se valida:
   - **Únicos:** coche, cuna y en general cualquier regalo que no sea pañales ni ropa. **Solo una persona.** Si ya está
     tomado: «Ese regalo ya lo trae alguien, elige otro».
   - **Pañales:** como **máximo 3 invitados**. El cuarto ve un aviso para que elija otra cosa.
   - **Ropa:** libre, sin tope.
   - Un regalo escrito de otra forma tiene que reconocerse como el mismo: mayúsculas, tildes, plural, «de paseo» y
     sinónimos comunes.
3. **Su equipo:** **Team Mamá** o **Team Papá**. Obligatorio.
4. **Una foto suya de bebé.** **Opcional**, «si quieres jugar a "¿Quién es este bebé?"». Solo la ven la tablet de la fiesta
   y el admin; no es pública ni la ven los demás invitados.

Si el regalo no pasa la validación, la confirmación **no** se guarda y el invitado no pierde lo que ya escribió.

En la invitación, la sección de regalos muestra **lo que ya está anotado**, sin nombres, para que la gente elija otra cosa
antes de intentarlo.

Hoy la confirmación (`cc_rsvps`, por fiesta) y los regalos (`cc_gift_items`, por invitación, con su modo `open`) son tablas
distintas, y el regalo se toma en otra sección de la página. En esta entrega se unen: **confirmar crea el regalo a nombre de
quien confirma**, de modo que la lista de los papás se arma sola.

## 5. Voz y música

- **Narración:** sin Alice. La voz es **venezolana o chilena**, «más neutra» y suave, con tono tierno. Luis elige entre
  **dos muestras cortas** antes de grabar el resto (ElevenLabs; el costo se presenta antes).
- **Música:** suave y alegre, con licencia para uso comercial (por ejemplo, la biblioteca de Pixabay revisada que no esté en
  Content ID). La elige Luis o se le proponen opciones.

## 6. Restricciones

- **PROD de CumpleClick:** `cumpleclick.com/app`, en Hostinger. Se despliega por SSH con el go de Luis: cotejar, respaldar,
  subir y verificar desde afuera. `main` = PROD. **La migración va antes que el código.**
- **Base de datos:** la tabla de confirmaciones tiene columnas fijas, y el equipo y la foto necesitan migración. Hay que
  verificar en PROD que las migraciones de regalos (`010`, `011`) estén aplicadas; una nota de agosto decía que no.
- **CDN:** reencoda las imágenes salvo `no-transform`; los `themes/` ya lo tienen.
- **Gastos:** cada generación de Higgsfield o ElevenLabs con presupuesto presentado y ok de Luis, y registrada para Balance.
- **Reglas de CumpleClick:** sin nombres de franquicias. El osito es genérico, no un personaje con marca.

## 7. Lo que queda al constructor

- **Cómo reconocer regalos equivalentes:** normalización y grupos de sinónimos. *Decisión mía:* empezar con una tabla
  explícita y conservadora:
  - coche = cochecito = carrito = coche de paseo = carriola;
  - cuna = cuna corral = corral;
  - silla de auto = silla para auto = butaca;
  - bañera = tina;
  - **moisés va aparte de cuna.**

  Lo que no calce con ningún grupo se compara por el texto normalizado.
- **Modelo de datos:** cómo se relaciona la confirmación con el regalo y dónde se guarda la foto (almacén privado, como las
  fotos del álbum).
- **Nombre de la temática:** *decisión mía:* `baby-osito`.
- **Recorrido de la invitación:** cuántos capítulos y cuáles. Los de las otras temáticas de baby shower sirven de modelo.
- **Imágenes y videos:** qué modelos de Higgsfield usar, dentro del presupuesto que apruebe Luis.
- **Diseño de las pantallas** de la confirmación y de los enlaces de los papás.

## 7b. Respuestas a la revisión previa (decisiones mías, marcadas)

- **La imagen de Luis** está en la carpeta privada (`invitacion-original.jpg`). Se usa **como guía de estilo** para generar
  con Higgsfield las imágenes de la temática, que salen sin texto (el texto lo pone la invitación). No se publica tal cual.
- **Una confirmación = una persona o una pareja** que escribe su nombre (por ejemplo, «Carolina y Pedro»), con un regalo y
  un equipo. Si llega otra confirmación con un nombre que ya existe, **no se pisa**: se le pide agregar el apellido. Así no
  se edita desde la invitación y no se mezclan dos personas.
- **Convivencia con lo que ya existe:** la confirmación con regalo es un **modo nuevo de regalos por invitación**, que se
  activa solo en esta fiesta. Las otras invitaciones (`list` y `open`) quedan como están.
  - **En este modo**, la sección de regalos de la invitación es **de solo lectura**: muestra lo ya anotado, sin nombres,
    sin «Yo lo regalo» y sin «soltar».
  - **Los papás** ven en su enlace quién trae qué. Pueden **ocultar** un regalo, como hoy, y eso lo libera.
  - **El admin** suma solo lo mínimo: **borrar una confirmación con su regalo y su foto**, para corregir errores y limpiar
    la confirmación de prueba en PROD.
- **El kiosco el 31-oct:** el fondo de la cabina con el marco grande se genera **ahora**, en la misma tanda de Higgsfield,
  porque cuesta poco. La calibración del marco y la música de la cabina van en el proyecto 2, que también tiene que estar
  listo antes del 31.
- **Ruta crítica del 25-oct:** la migración, la confirmación con la validación, el modo de regalos, los enlaces de los papás
  y la portada de la temática **tienen que estar desde la primera confirmación**. El intro en video, los capítulos y la
  narración pueden llegar después en la misma URL, porque el código ya los muestra solo si los archivos existen.

## 7c. Respuestas de Luis (24-oct, aprobadas)

- **Lanzamiento inmediato:** «Mándala ahora mismo». El enlace sale apenas pase la ruta crítica; el intro, los capítulos y
  la narración llegan después en la misma URL.
- **Presupuesto aprobado:** Higgsfield ~US$4 de lista (imágenes Soul 2 y videos Kling) y ElevenLabs ~1.500 caracteres
  (dos muestras de voz y la narración).
- **Reglas de regalos:**
  - **Únicos**, una sola persona: coche, cuna, corral, moisés, silla de auto, bañera, mochila portabebé, monitor y, en
    general, cualquier artículo concreto que no sea de las otras categorías. Corral va **aparte** de cuna (la lista de
    Luis los nombra por separado; corrige la tabla del punto 7).
  - **Pañales:** máximo 3 invitados.
  - **Libres**, sin tope: ropa (body, pilucho, osito, pijama, calcetines, gorro, mantita), toallitas y artículos de aseo,
    dinero o gift card.
  - **Genéricos** («sorpresa», «regalito», «juguete» a secas): no se aceptan; se le pide al invitado que diga qué es.
  - Mensaje para un único repetido: «Ese regalo ya lo trae alguien, elige otro».

## 8. Cómo se acepta

- **Pruebas por HTTP con base temporal:**
  - un regalo único repetido, escrito de otra forma, se rechaza;
  - el cuarto «pañales» se rechaza;
  - «ropa» pasa siempre;
  - sin regalo o sin equipo no se confirma;
  - la foto es opcional;
  - los papás ven quién trae qué y los invitados no ven nombres.
- **Revisión visual en un celular de 375 px:** el sobre, el intro, la portada, la confirmación completa y los dos enlaces
  de los papás.
- **Despliegue:** con respaldo y verificación desde afuera. Antes de mandar el enlace, una confirmación de prueba en PROD
  que después se borra.
