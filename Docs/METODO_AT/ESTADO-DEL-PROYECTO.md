# Estado del proyecto: guía de uso, formato `at-cierre-v1` y despliegue (Etapa 2)

> **Estado: LOCAL.** Rama `claude/estado-proyecto-e2`, sin desplegar. La Etapa 1 (botón «Ver detalle del avance», cronograma con tercer carril, «Actualizar la vista del plan» y un `ver-estado.php` mínimo) ya está EN PROD desde el 2026-10-14; ver `PLAN-DE-TRABAJO.md`. Diseño completo: `Docs/superpowers/specs/2026-10-13-estado-del-proyecto-design.md`.
> Los datos de esta guía son inventados (código de plan `AbCdEf123456`, `example.com`): el repositorio es público, no se pegan códigos ni nombres reales de clientes.

## 1. Qué es

El cliente tiene tres presentaciones de su proyecto: la **propuesta**, el **plan de actividades congelado** (el que aprobó y se le envió) y el **«Estado del proyecto»**, que está viva y se actualiza cada vez que cerramos un hito. El cliente solo ve los **cierres publicados**: lo que está en borrador es solo nuestro. **Publicar no envía nada** al cliente (ni correo ni WhatsApp); el cliente entra por el enlace del plan (botón «Ver detalle del avance») o por `ver-estado.php?id=<código del plan>`. Mientras no haya ningún cierre publicado, ve «Pronto verás aquí el avance de tu proyecto» con el enlace a su plan.

## 2. Cómo se usa (Luis)

La sección está en **CRM › ficha del cliente › «🗓️ Plan de trabajo» › «📈 Avance del proyecto»**. Solo aparece con el plan ya **enviado** al cliente.

1. **Importar el cierre.** En «Importar cierre» se adjunta el archivo `.json` (formato `at-cierre-v1`, ver la sección 3; hasta 1 MB) o se pega su contenido, y se adjuntan en el mismo formulario las capturas, videos y portadas, con el **nombre exacto** que dice el JSON (da lo mismo mayúsculas o minúsculas). «📥 Guardar como borrador» valida todo; si hay errores no guarda nada y los muestra en una lista. **Importar nunca rehace la presentación del cliente:** el cliente no ve este cierre hasta que lo publiques. Si el archivo trae además `bloques`, `cambios_plan`, `necesitamos` o `alcance`, esas cuatro listas son datos generales del avance: quedan guardadas al tiro, pero el cliente las ve en la próxima presentación (cuando publiques un cierre o uses «Rehacer la presentación»); si ya hay un cierre publicado, el panel te lo recuerda en el detalle.
2. **Subir lo que falte.** Si algún archivo no vino en el formulario, en la fila del cierre (desplegable «Archivos y edición») cada evidencia con archivo tiene su botón **Subir**. Un cierre con archivos faltantes no se puede publicar.
3. **«👁️ Pedir vista previa con borradores».** Genera una presentación solo para nosotros que incluye los borradores; el cliente no la ve. Se abre con «Abrir la vista previa con borradores». Revisarla antes de publicar.
4. **«✅ Publicar».** Pide confirmación (dice que no se le envía nada al cliente). Pasa el cierre a publicado y se pide rehacer la presentación del cliente.
5. **Esperar unos minutos.** El panel muestra el estado de la presentación: **«Generándose»**, **«Lista»** o **«Con error»**. Si sale «Con error» (o el aviso dice que n8n no recibió el pedido) se usa **«🔄 Rehacer la presentación»**. Con «Lista», aparecen «Ver la presentación publicada» y «PDF».
6. **Verla como la ve el cliente:** `ver-estado.php?id=<código del plan>`.

Otras acciones del panel:

- **↩️ Volver a borrador.** La presentación anterior se oculta **en el acto**, porque ya no refleja lo publicado. El cliente ve «Pronto verás aquí el avance de tu proyecto» hasta que la presentación nueva esté lista, si es que quedan otros cierres publicados (se rehace sola, en unos minutos); si no queda ninguno, se queda con el «Pronto…». Después hay que esperar el «Lista» del panel y revisar `ver-estado.php?id=<código del plan>`. Ojo: la presentación vieja puede seguir abriéndose por su enlace directo del renderer hasta que se rehaga; ese enlace no es la página del cliente.
- **🗑️ Quitar.** Solo un cierre en borrador; borra también sus archivos.
- **Editar un cierre (borrador).** En «Archivos y edición» aparece su JSON en un cuadro de texto («💾 Guardar cambios del cierre»). Se valida igual que una importación. Las evidencias que mantienen su nombre de archivo conservan el que ya subimos. **Un cierre publicado no se edita ni admite archivos nuevos:** hay que volverlo a borrador primero (los formularios de subida y el cuadro de edición solo aparecen en borrador).
- **Bloques del plan.** Tabla «Bloques del plan» (por venir, en curso, listo, con fechas reales). Un bloque con un cierre **publicado** que lo cubre se muestra «listo» por sí solo.
- **Cambios de plan, «necesitamos de ti» y alcance** (un formulario con cuatro cuadros de texto):
  - *Cambios de plan acordados*: una línea por cambio, `AAAA-MM-DD | título | detalle`. Al editar el texto de un cambio ya existente se conservan los bloques que tenía vinculados.
  - *Lo que necesitamos del cliente*: una línea por ítem; un `✓ ` al inicio de la línea lo marca como **resuelto**.
  - *Qué incluye la Fase 1* y *qué incluye el servicio mensual*: una línea por ítem. **Vacío = se usan los entregables del plan (Fase 1) o los mensuales del plan.**

**Ojo al reimportar:** el cierre se identifica por su **título**, comparado sin distinguir mayúsculas ni espacios de más. «Diseño del sitio» y «diseño  del  sitio» son el mismo cierre; «Diseño del sitio web» es **otro cierre distinto** y quedan los dos. Si se cambia el título a propósito, quitar el cierre anterior (en borrador) para que no queden duplicados.

## 3. Formato `at-cierre-v1`

Es un JSON en UTF-8. Lo prepara la sesión de Claude Code o Codex que cerró el hito, en el PC de Luis, y Luis lo importa (la sesión no toca producción). Cualquier error en un solo campo hace que **no se guarde nada**.

| Campo | Tipo y reglas |
|---|---|
| `formato` | Siempre `"at-cierre-v1"`. |
| `plan` | El código del plan (aquí `AbCdEf123456`). Debe coincidir con el de la ficha donde se importa; si no, se rechaza. |
| `cierre.titulo` | Texto, hasta 120 caracteres. Identifica el cierre (ver «Ojo al reimportar»). |
| `cierre.bloques` | Lista de nombres de bloques **que existen en el plan firmado** (no distingue mayúsculas). Al menos uno. |
| `cierre.fecha_real` | `AAAA-MM-DD`, la fecha real en que se cerró el hito. |
| `cierre.entregable_id` | Número; `0` si el cierre no tiene una entrega publicada en «Entregables». Debe ser un entregable **del mismo cliente** del plan: si no existe o es de otro cliente, se rechaza el archivo («El entregable N no existe o no es de este cliente.»). |
| `cierre.antes` / `cierre.despues` | `texto` (hasta 2.000 caracteres), `puntos` (hasta 8, de hasta 200 caracteres cada uno) y `evidencias` (lista de `ref`, que deben existir en `evidencias[]`). |
| `cierre.durante` | `texto` (hasta 2.000) y `actividades` (hasta 30): `{bloque, nombre, real, nota}`. El `bloque` debe estar entre los del cierre; `real` es `AAAA-MM-DD` o vacío; `nota` hasta 300. Si se **omite** `actividades`, se precargan las del plan para esos bloques. Una actividad con un nombre que el plan no tiene entra sin fechas planificadas y con un aviso. |
| `evidencias[]` | Hasta 60 por plan (30 cierres como máximo). Cada una trae `ref`, `tipo` y `titulo` (hasta 120). Detalle abajo. |
| `bloques` | Opcional. Objeto `{ "Nombre del bloque": { "estado": "listo" \| "en_curso" \| "por_venir", "inicio_real": "AAAA-MM-DD", "fin_real": "AAAA-MM-DD" } }`; las fechas pueden ir vacías y el fin no puede ser anterior al inicio. |
| `cambios_plan` | Opcional, hasta 30: `{fecha, titulo (120), detalle (600), bloques[]}`. **Si el archivo trae esta clave, reemplaza la lista completa**; si no la trae, no se toca. |
| `necesitamos` | Opcional, hasta 30: `{texto (200), estado: "abierto" \| "resuelto", fecha}`. Igual: si viene, reemplaza la lista. |
| `alcance` | Opcional: `{fase1: [...], mensual: [...]}`, hasta 20 ítems de 200 caracteres cada lista. Igual: si viene, reemplaza. |

**Evidencias por tipo.** `ref` solo admite **minúsculas, números y guiones, hasta 40 caracteres, empezando con letra o número**. Usa siempre refs con al menos una letra (`portada`, `velocidad-antes`): los refs formados solo por dígitos (`1`, `2`) tienen un problema conocido, no los uses.

| `tipo` | Campos |
|---|---|
| `cifra` | `valor` (hasta 40) **obligatorio**, `unidad` (hasta 60), `fuente` **obligatoria** (hasta 200) y `fecha` **obligatoria**. Una cifra sin fuente y fecha no se acepta. |
| `enlace` | `url` obligatoria: `https://`, sin espacios ni comillas, hasta 500 caracteres, y **nunca** del servidor de Easypanel (el correo de Hostinger rechaza esos enlaces). |
| `captura` | `archivo`: nombre `.jpg`, `.jpeg`, `.png` o `.webp`. |
| `video` | `archivo`: nombre `.mp4` o `.webm`; `poster` opcional: nombre de imagen. |

Reglas de los textos: se guardan tal cual (un «<90» o «menos de <3 segundos» no se corta), pero un texto que lleve una **etiqueta HTML** (p. ej. `<script>`) rechaza el archivo entero. Las fechas van entre 2020 y 2100. La `ref` de una evidencia lleva al menos una letra (`a1` vale, `1` no).

`fuente` y `fecha` son opcionales en los demás tipos. Los nombres de archivo llevan letras, números, punto, guion y guion bajo (hasta 120).

**Qué se guarda de lo importado.** Solo se guardan las evidencias que **«Antes» o «Después» nombran**. Una evidencia que ninguna de las dos usa se avisa («no se mostrará») y **no se guarda**; tampoco se le sube su archivo.

**Reimportar el mismo título.**

- *Borrador:* se reemplaza el cierre conservando su identificador. Las evidencias con la **misma `ref` y el mismo nombre de archivo** conservan el archivo ya subido; las demás se descartan y sus archivos viejos se borran.
- *Publicado:* se rechaza con «pásalo a borrador antes de importarlo de nuevo».

### Archivos: qué acepta el panel

Los archivos se asocian a cada evidencia **por nombre** (sin distinguir mayúsculas) con su `archivo` o su `poster`. Un archivo que no corresponde a ninguna evidencia del cierre se avisa y no se guarda. Los archivos quedan en `wp-content/uploads/at-estado/<id del plan>/` con nombres aleatorios.

- **Imágenes (capturas y portadas):** JPEG, PNG o WebP, hasta **15 MB**, hasta **12.000 px por lado y 25 millones de píxeles**. Se reducen a **2.000 px** de lado mayor, se les quita el EXIF (incluido el GPS) y las fotos de celular se giran a su posición correcta. Un WebP con transparencia se guarda como JPG.
- **Videos:** MP4 o WebM, hasta **200 MB**, se guardan tal cual. El tipo se mira por el contenido, no por la extensión. El MP4 debe tener una marca de video común: sirven las grabaciones del celular, de grabadores de pantalla y las exportaciones de editores. Una foto HEIC o AVIF **no** es un video y se rechaza.
- **Portada («poster») del video:** opcional. Se sube en WordPress junto con el video, ya sea en «Importar cierre» (adjuntándola en el mismo formulario) o después, con el botón **Subir** de esa evidencia (fila «Póster (opcional)»). **Hostinger no puede generarla** (no tiene ffmpeg). Quien prepara el archivo del cierre en el PC de Luis puede generarla con ffmpeg y entregarla junto al video. Sin portada, el recuadro del video se ve oscuro hasta reproducirlo y el PDF muestra «▶ Ver el video» con el enlace.

## 4. Ejemplo completo

Archivo: `Docs/METODO_AT/ejemplos/cierre-at-cierre-v1-ejemplo.json`. Valida contra un plan de prueba con los bloques «Diseño», «Desarrollo» y «Puesta en marcha» (lo comprueba `at_av_validar_import`). Los archivos `portada-nueva.png`, `recorrido.mp4` y `recorrido-poster.jpg` son ficticios: hay que adjuntar los reales.

```json
{
  "formato": "at-cierre-v1",
  "plan": "AbCdEf123456",
  "cierre": {
    "titulo": "Diseño del sitio",
    "bloques": ["Diseño"],
    "fecha_real": "2026-10-09",
    "entregable_id": 0,
    "antes": {
      "texto": "El sitio usaba una plantilla genérica, sin las fotos del equipo actual.",
      "puntos": ["Plantilla genérica", "Fotos de personal que ya no trabaja ahí"],
      "evidencias": ["velocidad-antes"]
    },
    "durante": {
      "texto": "Diseñamos la portada y las páginas internas, y las revisamos contigo.",
      "actividades": [
        { "bloque": "Diseño", "nombre": "Maqueta de la portada", "real": "2026-10-06", "nota": "" }
      ]
    },
    "despues": {
      "texto": "Quedó aprobada la maqueta del sitio nuevo.",
      "puntos": ["Maqueta aprobada el 9 de octubre"],
      "evidencias": ["velocidad-despues", "portada", "recorrido", "maqueta"]
    }
  },
  "evidencias": [
    { "ref": "velocidad-antes", "tipo": "cifra", "titulo": "Velocidad en celular", "valor": "56", "unidad": "puntos de 100", "fuente": "PageSpeed Insights", "fecha": "2026-10-06" },
    { "ref": "velocidad-despues", "tipo": "cifra", "titulo": "Velocidad en celular", "valor": "92", "unidad": "puntos de 100", "fuente": "medición local", "fecha": "2026-10-09" },
    { "ref": "portada", "tipo": "captura", "titulo": "Portada nueva", "archivo": "portada-nueva.png" },
    { "ref": "recorrido", "tipo": "video", "titulo": "Recorrido de la maqueta", "archivo": "recorrido.mp4", "poster": "recorrido-poster.jpg" },
    { "ref": "maqueta", "tipo": "enlace", "titulo": "La maqueta en línea", "url": "https://example.com/maqueta" }
  ],
  "bloques": { "Desarrollo": { "estado": "en_curso", "inicio_real": "2026-10-12", "fin_real": "" } },
  "cambios_plan": [
    { "fecha": "2026-10-08", "titulo": "La publicidad pasa al servicio mensual", "detalle": "Lo acordamos por correo.", "bloques": [] }
  ],
  "necesitamos": [ { "texto": "Logo en alta resolución", "estado": "abierto", "fecha": "2026-10-08" } ],
  "alcance": { "fase1": ["Sitio web publicado"], "mensual": ["Publicidad y visibilidad"] }
}
```

## 5. Dónde viven las cosas

| Qué | Dónde |
|---|---|
| Datos del avance | Columna `avance` de la tabla de planes. La migración la crea y deja `at_plan_schema` = `2` (`at_av_migrar_esquema()`). |
| Archivos de evidencias | `wp-content/uploads/at-estado/<id del plan>/` (con su `.htaccess` propio: sin código, sin listado, `noindex`, `no-transform`). |
| Lógica en WordPress | `wp-content/themes/automatiza-tech/inc/plan-trabajo/`: `avance-puras.php` (validación, sin WordPress), `avance-datos.php`, `avance-evidencias.php`, `avance-render.php` y `avance-panel.php`; se enganchan en `cargar.php`. Cambiados: `datos.php`, `rest.php`, `panel.php`, `vista.php` y `assets/css/plan-trabajo.css`. |
| Página del cliente | `ver-estado.php` (raíz del sitio) → `at_pt_html_ver_estado()` en `vista.php`. |
| Presentación | `renderer/src/template-estado.js` (servicio del renderer en Easypanel). |
| Flujo n8n | Rama `estado` de «Plan de trabajo · 3 Render» (`N8N/plan-trabajo/plan-3-render.json`, generado por `build_plan_3_render.py`, 34 nodos). **Nunca manda correo a Luis**: el resultado se ve en el panel («Lista» / «Con error» / «Generándose»). |
| Pruebas | `tests/plan/` (WordPress y sin WordPress), `renderer/test/`, `N8N/plan-trabajo/probar_plan.py`. |

## 6. Para PROD (en este orden; cada paso con el OK de Luis, respaldo y verificación desde afuera)

Luis sube solo el zip del renderer a Easypanel; el resto se despliega por API y SSH con su autorización explícita. **Antes de subir cualquier archivo de WordPress, respaldar el que se sobrescribe y cotejar su md5 contra `main`** (una copia local vieja hace revisar el archivo equivocado).

| Orden | Local | Destino | Clase |
|---|---|---|---|
| 1 | zip de `renderer/` de esta rama | Easypanel, servicio del renderer (lo sube Luis) | OBLIGATORIO |
| 2 | `N8N/plan-trabajo/plan-3-render.json` (34 nodos) | n8n: `python N8N/propuestas-v3/deploy.py <ruta absoluta>` | OBLIGATORIO |
| 3 | `inc/plan-trabajo/avance-puras.php`, `avance-datos.php`, `avance-evidencias.php`, `avance-render.php`, `avance-panel.php` (nuevos) | mismas rutas en `wp-content/themes/automatiza-tech/` | OBLIGATORIO, **antes** que `cargar.php` |
| 4 | `inc/plan-trabajo/datos.php`, `rest.php`, `panel.php`, `vista.php` y `assets/css/plan-trabajo.css` | mismas rutas | OBLIGATORIO (respaldar y cotejar md5 contra `main`) |
| 5 | `inc/plan-trabajo/cargar.php` | misma ruta | OBLIGATORIO, **al final** de WordPress |
| 6 | `ver-estado.php` | raíz de `public_html` | OBLIGATORIO |
| 7 | migración: `wp eval 'at_av_migrar_esquema(); echo get_option("at_plan_schema");'` → `2` | PROD | OBLIGATORIO |
| — | `tests/`, `Docs/`, `.superpowers/` | — | NO subir |

La migración (paso 7) va **justo después de subir** los archivos (o entrando una vez al escritorio de WordPress, que la corre sola). Sin ella el panel muestra el aviso «Falta preparar la base para el avance» y el `GET` del render del estado responde **409**.

Verificación desde afuera tras el despliegue: `ver-estado.php?id=<código>` con un plan enviado (sin cierres: «Pronto…»); importar un cierre de prueba en una ficha de prueba, vista previa, publicar y comprobar «Lista»; abrir un archivo subido bajo `uploads/at-estado/` y revisar sus cabeceras (`no-transform`, `noindex`).

**Rollback:** reponer el zip anterior del renderer; reimportar el `plan-3-render.json` de `main`; restaurar los archivos de WordPress desde el respaldo y borrar los `avance-*.php`. La columna `avance` puede quedar: nadie la lee sin el código.

## 7. Qué no se ha probado

Todo lo de esta etapa corrió en local (pruebas automáticas, WordPress local, renderer y flujo n8n contra datos de prueba). **No se ha probado:**

- El **flujo n8n real** y el **renderer** en producción (Easypanel): solo el generador y las pruebas locales.
- Un **video real de 13 MB subido por el navegador** a producción. Los topes de PHP en Hostinger se midieron el 13-oct (acepta subidas de hasta 2 GB, según la especificación), pero nunca se subió un video de verdad por este panel.
- Que LiteSpeed **respete el `.htaccess`** de `uploads/at-estado/` (`mod_headers`: `noindex`, `no-transform`, caché) y que el **CDN de Hostinger respete `no-transform`** (hoy recodifica y achica imágenes si no se le dice).
- La **presentación vista en un celular** real.
- La **Etapa 3** (correo de cierre de hito, texto de WhatsApp, registro en el historial del CRM) y la Etapa 4 (piloto con un cliente) **no están construidas**.
