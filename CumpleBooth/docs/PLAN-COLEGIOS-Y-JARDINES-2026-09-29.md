# CumpleClick en colegios y jardines: análisis de mejoras y plan (Halloween y Navidad 2026)

Fecha: 29-sep-2026. Autor: Claude (orquestador), a pedido de Luis el 28-sep: "quiero un análisis de las cosas que hay
que mejorar y un plan para ofrecer el servicio en colegios para Halloween y Navidad (Viejito Pascuero), un paquete
para escuelas o jardines infantiles, parecido a la feria: yo agrego el evento y las temáticas se comportan como una
fiesta: el inicio, el intro, la ruleta, todo. En la feria arrancaba directo a la ruleta, hay que corregirlo. Y el marco
más grande para que se vea el niño; los que ya existen los vemos después".

Cada cifra lleva su fuente. Lo que es supuesto va marcado **SUPUESTO**. La investigación de mercado, calendario y
reglas legales la hizo un agente de solo lectura el 28-sep (sección 6 y anexo); lo que sale del código se midió en el
repositorio (rama `claude/feria-prod`, commit `0357996`).

---

## 1. Qué hay que mejorar (lo que dejó la feria del 26-sep)

| # | Problema | Evidencia | Estado |
|---|---|---|---|
| 1 | **La intro de la temática se saltaba** y el niño caía directo a la ruleta. | Reporte de Luis (28-sep). No hubo causa reproducible: el servidor sirve los videos, todas las temáticas infantiles de PROD traen `videos.welcome`; lo más probable es el modo "menos animaciones" de la tablet (mostraba un emoji 1,2 s) o un video que no arrancó a tiempo con el wifi del salón. | **Corregido el 29-sep** (commit `0357996`, sin desplegar): el video se baja entero al abrir el kiosco (`videoListo.js`), se ve aunque la tablet pida menos animaciones, y si no arranca o falla queda una tarjeta con el nombre un mínimo de 4 s (`src/feria/intro.js`, 4 pruebas unitarias). |
| 2 | **No había despedida**: al terminar volvía al selector en seco. En una fiesta la temática se despide. | `FeriaBooth.jsx` antes del 29-sep: `finish = location.replace(destination)`. | **Corregido el 29-sep**: paso `despedida` con el video de la temática (`videos.despedida`) antes de volver al selector; la E2E lo cubre (243 pruebas, 0 fallas). |
| 3 | **El marco de las temáticas infantiles deja al niño chico.** | Medido con `getSquarePhotoGeometry` sobre `themes.json` (inset 8,5 %): la foto ocupa **25 % a 39 %** del ancho en las infantiles (carreras 269 px, hielo 340, kpop 359, tropical 385, spidey 402, familia-canina 423 de 1080) contra **61 % a 71 %** en las adultas y **65 %** en fiestas-patrias (697 px). | Pendiente. Regla para lo nuevo: **foto ≥ 60 % del ancho** (sección 4.3). Las existentes, después (pedido de Luis). |
| 4 | **La rama de la feria no está en GitHub ni en `main`.** PROD corre el bundle de esta rama; `main` no lo tiene. | `git rev-list origin/main..HEAD` = 9 commits en `claude/feria-prod`. | Pendiente: push + PR cuando Luis dé el go. Riesgo: otra sesión parte de `main` y pisa el modo feria. |
| 5 | **Las fotos de feria no se borran a los 7 días.** El ticket 020 lo exige y la ficha guarda `retencion_dias = 7`, pero `retention.php` no tiene el modo `--solo-ferias` y en Hostinger no hay cron por SSH. | Ticket `AT-CUMPLECLICK-020` (criterio 5); estado del 27-sep. | Pendiente. Con colegios es obligatorio: la Ley 21.719 rige desde el **1-dic-2026** y las fotos de niños son datos personales (sección 6.3). |
| 6 | **El consentimiento de la feria no sirve para un colegio.** Hoy es una casilla en la tablet que marca el adulto presente (120 s en `sessionStorage`). En un colegio el apoderado no está: la autorización debe ser **previa, escrita y por cada niño**. | Defensoría de la Niñez, Supereduc (formulario por persona), Ley 19.628 art. 4, Ley 21.719 art. 16 quáter (sección 6.3). | Pendiente: lista de autorizados por curso en el admin y el niño elige su nombre de esa lista (sección 4.4). |
| 7 | **La ficha de feria no sabe de colegios.** Campos de hoy: nombre, organizador, organizador_ig, lugar, fecha, hora_inicio, mesa, notas, mundos por modo, retención, tope de fotos. | `lib.ferias.php` (migración 026). | Pendiente: tipo de evento (feria / colegio / jardín), institución, cursos, contacto, y foto ligada a un curso (sección 4.4). |
| 8 | **No hay temática infantil de Halloween ni de Navidad.** `adulto-noche-brujas` es adulta (sin personajes, con intro); ninguna de Navidad. | `themes.json`: 20 temáticas; ninguna infantil de Halloween ni de Navidad. | Pendiente: dos temáticas completas según `TEMATICA-COMPLETA.md` (sección 5). |
| 9 | **La capacidad la fija la tablet, no la impresora.** | Selphy CP1500: ≈41 s por postal (canon.es, especificaciones). Recorrido completo del niño ≈ 3 min (**SUPUESTO**, sección 7.1). En la feria salieron 14 fotos en 3,5 h (registro de `cc_feria_fotos` del 26-sep), pero eso fue demanda, no capacidad. | Para un curso de 30 en una hora hacen falta **dos tablets** o un recorrido corto (sección 7). |
| 10 | **Nadie ha visto en PROD** la impresión desde la galería de feria en la Selphy ni el kiosco con un niño real después de las correcciones del 29-sep. | `PENDIENTES-DE-PRUEBA.md`. | Probar en la tablet física antes del primer colegio. |
| 11 | **Voces y música de las temáticas nuevas** deben pasar el control que ya costó caro (voz 8 a 14 dB más baja, frase que no se entendía). | `FTP-MANIFEST.md` 2026-09-10 cierres 4, 8 y 9. | Regla: `ebur128` por línea y transcripción con `scribe_v1` antes de dar por buena una intro o despedida. |
| 12 | **El wifi del lugar no se controla.** En la feria la intro dependía de la red; en un colegio el wifi suele estar filtrado. | Experiencia del 26-sep; corrección 1 lo mitiga solo para los videos de la temática. `api.php` y `upload.php` siguen necesitando internet. | Llevar **hotspot propio** (celular) y probar la subida antes de empezar. |

---

## 2. El producto: "Fiesta escolar CumpleClick"

Es el **modo feria extendido**, no un módulo nuevo. Luis crea el evento en Admin → Ferias (que pasa a llamarse
**Eventos**), elige tipo **Colegio** o **Jardín**, carga los cursos y la lista de niños autorizados, y elige las
temáticas. En la tablet, cada niño vive el recorrido completo de una fiesta:

1. **Elige su nombre** de la lista de su curso (solo aparecen los autorizados). Sin lista, escribe el nombre como hoy.
2. **Intro** de la temática (video con voz), ya garantizada (corrección 1).
3. **Ruleta** y personaje, con la opción de elegir después de tres giros (como en las fiestas).
4. **Juego** del personaje.
5. **Foto** con el marco grande de la temática, o **Asómate** (la cara del niño en el traje del personaje).
6. **Vista previa, QR y diploma** con el nombre del niño y el curso.
7. **Despedida** de la temática (corrección 2).

Lo que cambia respecto de la feria: el nombre sale de una lista (consentimiento), la foto queda ligada a un curso, el
marco es grande, y la entrega es a la familia por QR más la impresión 10×15 que se lleva el niño. Lo que no cambia:
numeración F-###, galería del evento para reimprimir, tope de fotos, borrado a los `retencion_dias` (7 por defecto).

**Viejito Pascuero.** CumpleClick no pone un actor. La temática de Navidad lo trae **dentro**: en la intro y la
despedida, como personaje de la ruleta y en Asómate ("asómate de ayudante del Viejito Pascuero"). Si el colegio
contrata un Viejito Pascuero de verdad, la misma temática ofrece **"Foto con el Viejito Pascuero"** en modo fondo
(`modoFoto: "fondo"`, la misma técnica de las temáticas adultas: recorta a las personas y las pone delante de la
escena), con el actor y el niño juntos. Referencia de precio de un actor: $150.000 por 1 h 30 en la RM (aviso en
Yapo, consultado el 28-sep; ver anexo).

---

## 3. Calendario y ventana de venta (Región Metropolitana, 2026)

Fuente principal: Resolución Exenta de la Seremi de Educación RM "Aprueba calendario escolar del año 2026", 15-dic-2025
(https://metropolitana.mineduc.cl/wp-content/uploads/sites/9/2025/12/DD_4079499_251215_P.pdf). Detalle en el anexo.

| Hito | Fecha | Qué significa para la venta |
|---|---|---|
| Día del Profesor, sin clases | vie 16-oct | **Halloween se vende antes del 16-oct.** |
| Halloween | sáb 31-oct (feriado: Día de las Iglesias Evangélicas) | Los colegios lo celebran el **vie 30-oct o jue 29-oct** (**SUPUESTO**; el único ejemplo con fecha, Colegio Santiago Quilicura 2021, lo hizo un jueves). |
| Día del apoderado | mié 18-nov | Gancho para el paquete de Navidad. |
| Día de los derechos del niño | vie 20-nov | Ídem. |
| Cierre actas 4.º medio | vie 20-nov | Licenciaturas de media desde esa semana. |
| Último día de clases con JEC (38 semanas) | **vie 4-dic** | La mayoría de los subvencionados: licenciaturas y Navidad entre el **23-nov y el 4-dic**. |
| Último día de clases sin JEC (40 semanas) | **vie 18-dic** | Colegios sin JEC: hasta el 18-dic. |
| Jardines JUNJI e Integra | atienden hasta ≈ 14-ene-2027 (ciclo 2025-26: JUNJI sin atención desde el 14-ene-2026; Integra "Vacaciones en mi Jardín" 12-ene al 20-feb-2026) | Navidad de jardines: **todo diciembre y hasta la 2.ª semana de enero** (**SUPUESTO** por analogía; el calendario 2026-27 no está publicado). |
| Ley 21.719 entra en vigencia | **mar 1-dic-2026** | Toda la temporada de Navidad se opera bajo la ley nueva (sección 6.3). |

Ejemplos reales de fechas (anexo A.1): licenciatura de kínder el 17-dic-2024 (Colegio Santiago La Florida) y el
18-dic-2025 (Colegio San Cristóbal); licenciatura de 8.º el 11-dic-2025 (Bicentenario Santa María de Paine); Viejito
Pascuero en 8 jardines municipales el 10, 11 y 12-dic-2018 (San Antonio).

**Plazos de compra.** Compras públicas: las cotizaciones (Compra Ágil, hasta 100 UTM) cierran 1 a 7 días después de
publicarse y exigen estar **hábil en el Registro de Proveedores**; la orden de compra va **antes** de la factura.
Privados: 5 a 7 días de reserva es lo habitual en cabinas; la fotografía escolar de jardines se reserva con hasta 12
meses (Magic Photography). Ver anexo A.4.

---

## 4. Cambios en el software

### 4.1 Ya hechos el 29-sep (rama `claude/feria-prod`, sin desplegar)

- `src/feria/intro.js` + `FeriaVideo` en `FeriaBooth.jsx`: intro garantizada (mínimo 4 s, arranque 8 s, tope 45 s,
  tarjeta de respaldo), despedida con el video de la temática, prefetch de los dos videos al abrir el kiosco.
- `App.jsx`: `despedidaSrc` desde `FERIA_THEME.videos.despedida`.
- Pruebas: `tests/frontend/feriaIntro.test.mjs` (4) y la E2E `feria-integracion.test.mjs` (despedida y tarjeta de
  respaldo con el video abortado). Suite completa: 243 pruebas, 242 pasan, 1 omitida (la de ADB), 0 fallas.

**Para PROD (con respaldo y verificación desde afuera):** cotejado contra PROD el 29-sep, solo hacen falta dos
archivos: `app/assets/main-B0T6cgEh.js` (nuevo, no reemplaza nada) y `app/index.html` al final. Los demás assets que
referencia el `index.html` ya están en PROD con los mismos bytes. `feria.html` **no se toca**: el selector no cambió
y PROD sirve el suyo con otros nombres de trozo. Sin migración. Detalle y vuelta atrás en `FTP-MANIFEST.md`
(PENDIENTE 2026-09-29).

### 4.2 Eventos escolares (ticket `AT-CUMPLECLICK-024`)

Backend y admin, sobre `cc_ferias`:

- Migración **028** (la 027 es el publicador de Instagram del ticket 019, pendiente de renumerar): en `cc_ferias`
  columnas `tipo` (`feria` | `colegio` | `jardin`), `institucion`, `contacto`, `contacto_tel`; tabla nueva
  `cc_evento_cursos` (evento, curso, orden) y `cc_evento_autorizados` (evento, curso, nombre, apoderado, autorizado,
  observacion, creado); en `cc_feria_fotos` la columna `curso`. 🔴 Regla de la casa: **la migración va antes que el
  código**, y `cc_ferias` tiene lista fija de columnas (tocar los seis lugares).
- Admin → Eventos: tipo, institución, cursos, **pegar la lista de autorizados** desde una planilla (una línea por
  niño: curso; nombre; apoderado; sí/no), y ver por curso cuántos autorizados y cuántas fotos.
- `feria-api.php` publica al kiosco los cursos y los nombres autorizados (nunca los no autorizados ni apoderados).
- Galería del evento: filtro por curso, impresión por curso, y descarga de un ZIP por curso solo para la sesión de
  admin (para entregar al colegio lo que cada familia autorizó).
- `retention.php --solo-ferias` (borra fotos y registros de eventos vencidos) y el comando de cron para hPanel, como
  el de la Agenda.

Kiosco (`src/feria/`):

- Paso `name` con lista: si el evento trae cursos, el niño toca su curso y su nombre (botones grandes, búsqueda por
  inicial si hay más de 12). Sin lista, el campo de texto de hoy.
- `curso` viaja en `upload.php` con `feria_reserva`.
- Diploma con curso e institución.

### 4.3 Marco grande (nuevo, para la experiencia escolar)

Hoy la foto se inscribe en el marco pintado del `fondo-sala.jpg` (`frameBox` de la temática, inset 8,5 %). No se puede
"agrandar" la foto sin tapar el borde pintado, así que el marco grande **se pide al generar el fondo**, como se aprendió
con Bebé entre Rosas (marco grande, rectangular y centrado: 552 px contra 352 px del óvalo alto).

Regla para las temáticas nuevas (sección 5): `frameBox` con `w ≥ 0,78` y `h ≥ 0,44` sobre 1080×1920, foto resultante
**≥ 650 px (≥ 60 % del ancho)**, referencia `fiestas-patrias` (0,779 × 0,438 → 697 px). La prueba de Tabla A debe
exigirlo para las temáticas marcadas `marcoGrande: true`.

Para las temáticas infantiles existentes, dos caminos, a decidir después (pedido de Luis): regenerar `fondo-sala.jpg`
con marco grande y recalibrar `frameBox` (una imagen por temática, USD 0,0162 de lista cada una), o usar en eventos
el modo fondo (`fondo-escena.jpg` + recorte de la persona), que muestra al niño grande sin marco pintado.

### 4.4 Consentimiento en el producto

- El apoderado firma el formulario de CumpleClick (sección 6.4) que reparte y recoge el colegio; Luis carga la lista.
- En la tablet solo existen los autorizados. El niño sin autorización **participa del juego y no se fotografía**
  (opción "Jugar sin foto" en el menú), para que nadie quede fuera del panorama y el consentimiento siga siendo libre.
- Ninguna foto de colegio va a redes: la casilla de uso promocional del formulario es aparte y no viene marcada; si el
  apoderado la marca, queda anotado en `cc_evento_autorizados.observacion` y se usa solo esa foto.

---

## 5. Temáticas nuevas (ticket `AT-CUMPLECLICK-025`)

Dos temáticas infantiles **completas** según la Tabla A de `TEMATICA-COMPLETA.md` (banner, sala, música, seis
personajes con foto, recorte, rompecabezas y saludo en video, despedida, fondo de la ruleta), más Asómate y marco
grande desde el primer día. Sin franquicias ni personajes con dueño (regla de Luis): personajes propios.

| Temática | Slug | Personajes propuestos | Modos |
|---|---|---|---|
| **Noche de Brujas para niños** | `brujitas` | Calabaza sonriente, fantasmita amistoso, bruja buena, gato negro, murcielaguito, momia despistada | Niños (ruleta y juegos) y Adultos (foto con la escena, como `adulto-noche-brujas`) |
| **Navidad del Viejito Pascuero** | `navidad` | Viejito Pascuero, Señora Pascuera, reno de nariz roja, duende ayudante, muñeco de nieve, pingüino | Niños y Adultos (foto con el Viejito Pascuero en modo fondo) |

Costo estimado con precios de lista de la API de Higgsfield (`references/api-rest.md`; la cuenta tiene 15 % menos):

| Pieza | Cantidad por temática | Precio unitario | Subtotal |
|---|---|---|---|
| Imágenes (banner, sala, escena, 6 personajes, 6 Asómate, ruleta) | 16 | USD 0,0162 (`marketing-studio/image/flare`, 9 imágenes costaron USD 0,1458 el 26-sep) | USD 0,26 |
| Videos Kling 3.0 Pro de 5 s (intro, despedida, 6 saludos) | 8 | USD 0,42 (USD 0,084/s) | USD 3,36 |
| Reintentos (la experiencia del 26-sep: 4 tomas para 2 intros) | ×2 en videos | | USD 3,36 |
| Voz de Matilda en Eleven v3 (ElevenLabs; era Alice hasta el 29-09) | 8 líneas | plan vigente | USD 0 |
| Música | 2 pistas | Gemini (Lyria 3), la genera Luis | USD 0 |
| **Total por temática** | | | **≈ USD 7 de lista; las dos ≈ USD 14** |

#### Lo que se produjo hasta el 30-sep (registros de generación, precios de lista)

| Pieza | Noche de Brujas | Navidad | Costo |
|---|---|---|---|
| Imágenes `flare` (banner, sala, escena, evento, 6 personajes, ruleta) | 12 | 12 | USD 0,389 |
| Videos Kling 3.0 Pro de 5 s (6 saludos, 2 tomas de bienvenida, despedida) | 9 | 10 (uno repetido) | USD 7,98 |
| Asómate: cuerpos de pie con Qwen Image 3 edit (USD 0,04 c/u) | 10 | 6 | USD 0,64 |
| Voz: Matilda en Eleven v3, 8 líneas por temática | 437 caracteres | 438 caracteres | 875 caracteres más unos 200 de pruebas |
| **Higgsfield, total de lista** | | | **USD 9,01** (aprobados: USD 14 más unos USD 1,20 cotizados para Asómate) |

Faltan la música (dos pistas, Luis con `MUSICA-TEMATICAS-INFANTILES-PROMPTS.md`), el visto bueno de Luis a los nombres de los
personajes y una corrida en la tablet. Los intentos que Higgsfield rechazó con "temporarily unavailable" (13 en Asómate) no se
cobran según la referencia de la API; no se comprobó en la consola de facturación.

Tiempo: `fiestas-patrias` con Asómate e intro se produjo en un día (26-sep). Cada temática nueva: 1 día de producción
más medio día de verificación (voces con `ebur128` y `scribe_v1`, Tabla A en verde, tablet). **Nada se genera sin el OK
de Luis con el costo en la mano.**

Orden: `brujitas` primero (se vende antes del 16-oct), `navidad` lista antes del 31-oct para venderla con el reel de
Halloween en la mano.

---

## 6. Paquetes y precios (recomendación; la decisión es de Luis)

### 6.1 Referencias de mercado (Santiago, consultadas el 28-sep-2026; enlaces en el anexo A.2)

| Referencia | Precio | Fuente |
|---|---|---|
| Cabina de fotos básica, 2 h, fotos digitales | $120.000 a $200.000 | Invitalo.cl, guía de precios Santiago |
| Cabina completa, 3 a 4 h, impresiones ilimitadas y asistente | $180.000 a $350.000 | Invitalo.cl |
| Cabina básica, 3 h, impresión ilimitada, operador y traslado dentro de Vespucio | $250.000 | Toro Mecánico Eventos |
| Hora extra de cabina | $50.000 | cabinafotografica.cl y TuFotoCabina (dos proveedores distintos) |
| Cabina "magazine", 4 h | $389.900 + IVA | Mágika Producciones |
| Arriendo de cabina en compras públicas (sep-2026) | $200.000 (Chillán Viejo), $300.000 (Hospital Padre Las Casas, 3 h), $400.000 (Puente Alto) | Mercado Público vía todolicitaciones.cl |
| Animación de fiesta de fin de curso | ≈ $150.000; con 40+ niños $150.000 a $200.000 | Cronoshare.cl (6-ene-2026) |
| Viejito Pascuero, 1 h 30, RM | $150.000 | Yapo (aviso) |
| Foto impresa con el Viejito Pascuero en malls | $2.000 (Parque Arauco), $3.000 (Barrio Independencia) | Chócale, 19-dic-2025 |
| Fotografía escolar, sesión en el colegio | $5.000 por estudiante | Anuarios Chile |
| Show de Navidad municipal completo | $6.890.756 (Arauco, 2025), $3.361.344 (Los Ángeles, jardines, 2021) | Mercado Público |

Lectura: el estándar es **2 a 3 horas por $120.000 a $250.000** con impresiones ilimitadas y operador; nadie publica
un paquete para colegios ni fotos con el Viejito Pascuero ni juegos en tablet ni álbum con PIN por familia. El ancla
mental del apoderado es **$2.000 a $3.000 por una foto impresa con el Pascuero** y **$5.000 por alumno** en fotografía
escolar.

### 6.2 Costos propios

| Concepto | Valor | Fuente |
|---|---|---|
| Foto impresa (papel y cinta KP-36 + dos tiras de imán) | ≈ $400 | Finanzas de PROD (leído el 12-sep) |
| Tiempo de impresión | ≈ 41 s por postal | Canon, especificaciones CP1500 |
| Papel KP-108IN (3 × 36 hojas) | ≈ $39.990 | Eco Color (12-sep) |
| Planes de fiesta vigentes | Mágico $69.990 / Premium $99.990 de lista; 50 % de lanzamiento hasta el 31-dic-2026 ($34.995 / $49.995) | `design/generadores/arte/piezas.py` y cumpleclick.com |

### 6.3 Paquetes propuestos

Desde el 27-sep las propuestas nuevas dicen **+ IVA** (regla de Luis). Precios netos.

| Paquete | Incluye | Precio propuesto | Por niño (lleno) | Costo directo | Margen bruto |
|---|---|---|---|---|---|
| **Jardín** | 2 h, 1 tablet + Selphy, hasta 40 niños, temática Halloween o Navidad, intro y despedida, ruleta y juego, 1 foto impresa 10×15 por niño con imán, QR a la familia, diploma | **$149.000 + IVA** | $3.725 | 40 × $400 = $16.000 | ≈ $133.000 |
| **Colegio** | 3 h, 2 tablets + Selphy, hasta 90 niños (3 cursos), lo mismo | **$249.000 + IVA** | $2.767 | 90 × $400 = $36.000 | ≈ $213.000 |
| **Hora extra** | | $50.000 + IVA | | | |
| **Foto adicional** | con imán | $2.000 | | $400 | $1.600 |
| **Por familia** (alternativa cuando el colegio no paga) | inscripción previa con el formulario, cada familia paga en línea; mínimo 30 niños | **$4.500 por niño** (IVA incl.) | | $400 | ≈ $3.400 |

Por qué esos números: Jardín queda dentro del rango de una cabina de 2 h ($120.000 a $200.000) y ofrece más
(juegos, intro, diploma, QR); Colegio queda igual que la cabina básica de 3 h de Toro Mecánico ($250.000) con dos
tablets; la foto adicional copia el ancla del Pascuero en malls ($2.000); el precio por familia queda bajo la fotografía
escolar ($5.000) y arriba del modelo "foto con Pascuero" ($2.000 a $3.000) porque incluye el recorrido y el diploma.
**SUPUESTO:** el margen no descuenta traslado, tiempo de Luis ni el ayudante.

Lo que no se promete: fotos ilimitadas (la Selphy imprime una por vez), subir fotos a redes, ni "todos los niños en 1
hora" (sección 7).

### 6.4 Legal: lo mínimo para operar en un colegio

Hasta el 30-nov rige la Ley 19.628 (art. 4: consentimiento **expreso y por escrito**, informado del propósito) y la
Ley 21.430 (art. 34: la imagen del niño la protegen sus padres y se escucha la opinión del niño). Desde el **1-dic-2026**
rige la Ley 21.719: consentimiento libre, informado, específico, previo e inequívoco; para menores de 14 lo dan los
padres o quien tenga el cuidado personal (art. 16 quáter); tratar datos de niños sin base es infracción **gravísima
(hasta 20.000 UTM)**. La Defensoría de la Niñez y la propia Superintendencia de Educación exigen autorización **por
cada persona**, no del colegio en bloque. Fuentes en el anexo A.3.

Formulario `autorizacion-imagen-eventos.md` (borrador para abogado, como los T&C):

1. Niño (nombre, curso) y apoderado (nombre, RUT, firma, fecha). Una firma por niño.
2. Responsable: CumpleClick (razón social y RUT de AT), y el colegio como quien recoge las firmas.
3. Finalidad específica: fotos y video en el evento X del día Y, entrega a la familia (impresa y QR privado).
4. Casilla **aparte y sin marcar** para uso promocional (web, Instagram). Sin ella, nada sale a redes.
5. Quién ve las fotos (la familia; el colegio solo lo que se autorice) y que no se ceden a terceros.
6. Conservación: 7 días en el servidor (`retencion_dias`); cómo pedir borrado o revocar, gratis, por WhatsApp o correo.
7. Lenguaje simple y una línea de asentimiento del niño.
8. Alternativa: el niño participa sin foto.

---

## 7. Operación

### 7.1 Capacidad

Recorrido por niño (**SUPUESTO**, a medir en la primera prueba con la tablet): intro 8 s + ruleta y personaje 40 s +
juego 60 s + foto 30 s + vista previa y QR 20 s + despedida 8 s ≈ **3 minutos** → ≈ 20 niños por hora por tablet. La
Selphy imprime ≈ 87 por hora (41 s), así que no es el cuello de botella.

| Paquete | Tablets | Niños por hora | Niños en el tiempo del paquete |
|---|---|---|---|
| Jardín (2 h) | 1 | 20 | 40 |
| Colegio (3 h) | 2 | 40 | 120 (se venden 90 para tener holgura) |

Con un curso por turno de 45 min (la clase entra, juega y sale) y el resto del curso jugando en el patio, el colegio no
se desordena. Recorrido corto opcional "solo foto y diploma" (sin juego) ≈ 1,5 min si un curso viene atrasado.

### 7.2 Equipo y logística

- 2 tablets (hoy: Galaxy Tab A9+ y Tab A7), Selphy CP1500 con **dos** cartuchos KP-108IN por evento de colegio
  (108 hojas cada uno), imanes cortados en casa, la base del kiosco 33×48, el pendón, alargador y zapatilla.
- **Hotspot propio** (celular): `api.php`, `upload.php` y la subida de fotos necesitan internet; el wifi de un colegio
  suele estar filtrado.
- Montaje 45 min antes (estándar del rubro: Toro Mecánico).
- Luis + 1 ayudante (fila, imanes, entrega de fotos).
- Prueba previa obligatoria en la tablet física: intro, despedida, foto, impresión desde la galería (pendientes 10 y 11).

---

## 8. Venta

**A quién:** dirección del colegio (decide el evento), **Centro de Padres** (Decreto 565/1990, con personalidad
jurídica por la Ley 19.418; suele pagar fiestas de fin de año), DAEM o Corporación Municipal (colegios públicos, por
**Compra Ágil** hasta 100 UTM, con tres cotizaciones y orden de compra antes de la factura), municipios (Oficina de la
Infancia o DIDECO para jardines JUNJI/Integra/VTF). Los municipios compran "shows de Navidad" grandes ($4 a $8
millones): ahí CumpleClick entra como proveedor de la productora, no solo.

**Qué hace falta para vender a públicos:** inscripción hábil en el Registro de Proveedores de Mercado Público, factura,
y responder cotizaciones en 24 h. **SUPUESTO:** centros de padres y colegios particulares pagan por transferencia con
boleta o factura (sin fuente).

**Argumento:** ningún proveedor chileno publica un paquete para colegios, ni fotos con el Viejito Pascuero, ni juegos en
tablet, ni entrega privada por familia con QR (anexo A.5). La foto impresa que el niño se lleva a la casa el mismo día es
lo que las familias ya pagan $2.000 a $3.000 en los malls.

**Material (sin créditos):** una hoja A4 "Fiesta escolar" con los dos paquetes y el QR de WhatsApp (misma línea que
`cartel-feria.py`), texto de WhatsApp para directoras y centros de padres, el reel de la feria como prueba, y después
del primer Halloween un reel escolar (con el permiso del formulario).

**Primeros contactos:** Royal Art Academy (Grecia 3348; pedirle presentación a colegios y jardines de la comuna), los
colegios y jardines de las familias de las fiestas del 13-sep y de la feria, y los jardines VTF de la comuna vía la
Oficina de la Infancia.

---

## 9. Cronograma

| Semana | Trabajo | Responsable | Depende de |
|---|---|---|---|
| 29-sep al 3-oct | Go de Luis para desplegar la intro y la despedida; push y PR de `claude/feria-prod`; decidir precios y modelo (por evento / por familia); OK de créditos para `brujitas` (≈ USD 7); producir `brujitas` | Luis decide; Claude produce | Go de Luis |
| 6 al 10-oct | Ticket 024: migración 028, admin de eventos con cursos y autorizados, kiosco con lista, `retention --solo-ferias` + cron; borrador del formulario de autorización; hoja A4 y texto de WhatsApp | Codex backend y admin, Claude kiosco y revisión | Precios decididos |
| 13 al 16-oct | Prueba completa en tablet; venta de Halloween (tope: vie 16-oct) | Luis vende | 024 en PROD |
| 19 al 30-oct | Eventos de Halloween (jue 29 y vie 30); producir `navidad` (OK de créditos ≈ USD 7) | Luis opera; Claude produce | |
| 2 al 20-nov | Reel escolar; venta de Navidad y licenciaturas (Día del apoderado 18-nov como gancho); inscripción en Mercado Público si se decide | Luis | |
| 23-nov al 18-dic | Eventos de Navidad y licenciaturas (JEC hasta el 4-dic; sin JEC hasta el 18-dic); desde el 1-dic, Ley 21.719 | Luis | |
| enero 2027 | Jardines JUNJI/Integra hasta ≈ 14-ene | Luis | |

---

## 10. Decisiones que necesita Luis

1. Go para desplegar la intro y la despedida a PROD (sección 4.1) y para push + PR de la rama de la feria.
2. Precios y modelo de cobro (sección 6.3): por evento, por familia o los dos.
3. OK de créditos para las dos temáticas (≈ USD 14 de lista en total; se presenta pieza por pieza antes de generar).
4. Nombres y personajes de `brujitas` y `navidad` (sección 5).
5. Si se inscribe en el Registro de Proveedores de Mercado Público (abre colegios públicos y municipios).
6. Segunda tablet para el paquete Colegio (la Tab A7 sirve, pero la intro larga y el 3D ya la exigieron).
7. Quién ejecuta el ticket 024 (propuesta: Codex backend y admin, Claude kiosco), y si el formulario legal pasa por
   abogado antes del primer colegio.

---

## Anexo A. Investigación del 28-sep-2026 (agente de solo lectura)

### A.1 Calendario escolar y fechas de eventos

- Resolución Exenta Seremi RM, 15-dic-2025 (número en blanco en el PDF; calendarioescolar.cl la cita como N.º 2803,
  sin confirmar): https://metropolitana.mineduc.cl/wp-content/uploads/sites/9/2025/12/DD_4079499_251215_P.pdf
- Mineduc, 17-dic-2025, término del año escolar entre el 4 y el 18-dic según jornada:
  https://www.mineduc.cl/ministerio-de-educacion-oficializa-el-calendario-escolar-2026/
- Halloween 2026, sábado 31, feriado por el Día de las Iglesias Evangélicas: CNN Chile,
  https://www.cnnchile.com/tendencias/cuanto-falta-para-halloween-2026-en-chile-fecha-feriado-y-tradiciones/
- Colegio Santiago Quilicura, Halloween el jueves 28-oct-2021:
  https://www.redcrecemos.cl/colegios/santiago-quilicura/galeria-de-fotos/diversas-actividades-deportivas-y-concursos-se-desarrollaron-en-fiesta
- Licenciatura de kínder 17-dic-2024 (Colegio Santiago La Florida): https://www.redcrecemos.cl/constitucion-del-consejo-escolar-2026
- Licenciatura de 8.º 11-dic-2025: https://colegiobicentenariosantamariadepaine.cl/2025/licenciatura-de-octavos-basicos-2025-un-paso-firme-hacia-nuevos-horizontes/
- Viejito Pascuero en jardines municipales, 10 al 12-dic-2018 (San Antonio):
  https://www.sanantonio.cl/municipalidad/noticias/item/7852-viejito-pascuero-visita-a-ninos-y-ninas-de-jardines-infantiles.html
- JUNJI sin atención desde el 14-ene-2026: https://junji.cl/comunicado-sobre-suspension-de-actividades/
- Integra, "Vacaciones en mi Jardín" 12-ene al 20-feb-2026:
  https://integra.cl/programa-vacaciones-en-mi-jardin-de-fundacion-integra-beneficiara-este-verano-a-mas-de-4-300-ninas-y-ninos/

### A.2 Precios de mercado

- Invitalo.cl, guía de precios de photobooth en Santiago: https://invitalo.cl/proveedores/photobooth/santiago
- Toro Mecánico Eventos, cabina $250.000 por 3 h: https://www.toromecanicoeventos.cl/juegos/cabina-fotografica/
- cabinafotografica.cl, hora extra $50.000: https://cabinafotografica.cl/
- TuFotoCabina (Punta Arenas), 1 h $150.000 a 4 h $300.000, hora extra $50.000: https://www.tufotocabina.cl/
- Mágika Producciones, $389.900 + IVA por 4 h: https://magikaproducciones.cl/products/cabina-fotos-vogue-arriendo-eventos
- SmartPhoto, $100.000 (2 h) a $149.000: https://www.ineventos.com/cl/smartphoto
- Compras públicas de cabinas, sep-2026: https://www.todolicitaciones.cl/busqueda/licitaciones?textSearch=cabina%20fotografica
- Cronoshare, animación infantil (6-ene-2026): https://www.cronoshare.cl/cuanto-cuesta/animacion-infantil
- Viejito Pascuero, avisos: https://www.yapo.cl/anuncios-casificados-negocios-servicios-otros?q=keyword.viejito+pascuero
- Foto con el Pascuero en malls 2025 (Chócale, 19-dic-2025): https://chocale.cl/2025/12/navidad-2025-donde-encontrar-al-viejito-pascuero-en-los-malls/
- Anuarios Chile, $5.000 por estudiante: https://www.anuarioschile.cl/sesion-fotografica-santiago
- Magic Photography, jardines, pago por familia sin obligación de compra, reserva 12 meses: https://magicphotography.cl/kinder_info/
- Show de Navidad Arauco 2025, adjudicado $6.890.756: https://www.todolicitaciones.cl/licitacion/4414-91-LE25
- Navidad jardines Los Ángeles 2021, $3.361.344: https://www.todolicitaciones.cl/licitacion/2408-346-L121

### A.3 Reglas para fotografiar niños

- Ley 21.430, arts. 33 y 34 (PDF oficial BCN alojado en U. de Chile):
  https://facso.uchile.cl/dam/jcr:604b0b3a-0fa5-4171-bcbb-87f791d5b12d/Ley-21430_15-MAR-2022.pdf
- Informe BCN "Protección del derecho a la imagen de NNA" (enero 2023):
  https://obtienearchivo.bcn.cl/obtienearchivo?id=repositorio%2F10221%2F33931%2F1%2FBCN_derecho_imagen_NNA_VF_pdf.pdf
- Ley 19.628 art. 4 (texto vía SUSESO; bcn.cl no cargó): https://www.suseso.cl/612/w3-propertyvalue-121254.html
- Ley 21.719, síntesis oficial BCN (8-abr-2025), vigencia 1-dic-2026, sanciones:
  https://obtienearchivo.bcn.cl/obtienearchivo?id=repositorio%2F10221%2F37137%2F1%2FInforme_12_25_Ley_Datos_Personales_rev.pdf
- Art. 16 quáter (menores de 14, consentimiento de padres), extractos: https://www.diarioconstitucional.cl/cartas-al-director/consentimiento-para-el-tratamiento-de-datos-personales-de-ninos-ninas-y-adolescentes-y-seguros-de-salud/ y https://idonea.cl/ley-proteccion-datos-personales-chile-guia/
- Defensoría de la Niñez, uso de imágenes de NNA en establecimientos:
  https://www.defensorianinez.cl/preguntas_frecuentes/sobre-el-uso-de-imagenes-de-ninos-ninas-y-adolescentes-en-redes-sociales/
- Supereduc, formulario de autorización por persona (2022):
  https://buenasideas.supereduc.cl/wp-content/uploads/2022/09/Anexo_Autorizacion_de_inscripcion_y_uso-de-_imagen.docx
- Corte Suprema rol 2506-2009 sobre derecho a la propia imagen: https://derecho-chile.cl/uso-de-fotografia-sin-autorizacion/

### A.4 Cómo compran

- Centros de padres, Decreto 565/1990 y Ley 19.418: https://www.ayudamineduc.cl/ficha/participacion-de-los-apoderados-en-el-establecimiento-educacional
- Compra Ágil hasta 100 UTM, proveedor hábil: https://www.chilecompra.cl/compraagil/
- Orden de compra antes de la factura, tres cotizaciones (guía SEP): https://www.softpme.cl/blogs/post/guia-adquisiciones-sep
- Licenciatura de kínder comprada por Compra Ágil en septiembre (Peñaflor, $410.000):
  https://www.todolicitaciones.cl/busqueda/licitaciones?textSearch=licenciatura

### A.5 Competencia (ninguno publica paquete escolar, Pascuero, juegos ni álbum con PIN)

Egobox (https://www.egobox.cl/), PhotoFest (https://www.photofest.cl/), Blue Monkey (https://invitalo.cl/p/bluemonkeyproducciones),
SmartPhoto, OpenBOOTH (https://openbooth.cl/), Toro Mecánico, Mágika, iBooth (https://www.ibooth.cl/), Cabinas de Fotos
Chile (https://cabinasdefotos.cl/); fotógrafos escolares AIMAGEN (https://www.aimagen.cl/fotograf%C3%ADa-colegios) y Magic
Photography.

### A.6 Lo que no se pudo verificar

Número de la resolución RM; que los colegios celebren Halloween el viernes 30; calendario 2026-27 de JUNJI/Integra;
texto literal de las leyes en bcn.cl (no cargó); dictamen de la Supereduc sobre fotos por terceros; protocolo de imagen
de JUNJI/Integra; precios de 2x3.cl y de OpenBOOTH; si los centros de padres exigen factura; valor de la UTM de
septiembre 2026.
