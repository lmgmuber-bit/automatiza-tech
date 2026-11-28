# Módulo Propuestas del wp-admin — rediseño (diseño aprobado)

Fecha: 2026-09-24. Aprobado por Luis por partes: diseño general, lista, ficha y despliegue.
Rama `claude/propuestas-admin`, que parte de `claude/propuestas-v3` (PR #41, ya en PROD).

## Problema, medido

- **Las propuestas antiguas no aparecen.** La lista consulta
  `SELECT * … ORDER BY created_at DESC LIMIT 10` (`inc/admin-proposals.php:382`) y no tiene paginación, buscador
  ni filtros. PROD tiene 19 propuestas (2025-12-09 … 2026-09-24): 9 no se pueden abrir desde el panel.
- **La lista solo reconoce dos estados.** Muestra etiqueta para `sent` y `pending`. Los estados en uso son `sent`
  (9), `borrador` (7, v3), `pending` (1), `draft` (1, viejo) y `error` (1, v3).
- **El formulario de edición es una sola columna larga.** Tiene 47 atributos `style=` y la tabla de precios v3 y
  los botones v3 no tienen reglas para celular, así que se salen de su cuadro.
- **Todo está en una sola función.** `automatiza_tech_proposals_page()` ocupa las líneas 57–992 y mezcla ruteo, cinco procesos
  de POST, correo y ~600 líneas de HTML y CSS.
- **La transcripción de la reunión no se muestra en ninguna parte,** aunque está guardada en `transcript_text`.

## Objetivo

Que sea fácil **buscar, ver y editar** cualquier propuesta, en computador y en celular, sin cambiar el
comportamiento de nada que funciona hoy: guardado, envío, flujo v3 (Pedir cambios, Aprobar, Destrabar), seguimiento
y enlaces de los correos de n8n.

## Decisiones de Luis

- **Diseño:** lista a pantalla completa, con una ficha aparte organizada en 6 pestañas.
- **Estilo:** WordPress con toques AT. Tablas, botones y menús nativos, y colores AT (`#0d1b2a`, `#00d9c0`) en
  estados, pestañas y botones principales.
- **Búsqueda:** por empresa o cliente, por correo o teléfono, y por fecha.
- **Construcción:** tabla nativa de WordPress (`WP_List_Table`) y ficha propia. El módulo viejo sigue disponible
  en `&clasico=1` como red de seguridad.

## Arquitectura

- **URL:** no cambia. `admin.php?page=automatiza-proposals` muestra la lista y `&edit_id=N` muestra la ficha; son
  los enlaces que usan los correos de n8n. Con `&clasico=1` se abre el módulo anterior, idéntico (función
  renombrada a `automatiza_tech_proposals_page_clasico()`, sin cambios internos). Sus formularios no tienen
  `action`, así que el POST vuelve a la misma URL con `clasico=1` y lo procesa el código clásico.
- **Archivos nuevos** en `wp-content/themes/automatiza-tech/inc/propuestas-admin/`:

| Archivo | Qué hace |
|---|---|
| `consultas.php` | Funciones puras: normalizar filtros, armar WHERE/ORDER/LIMIT, mapear estados a etiquetas y grupos |
| `lista.php` | Clase `AT_Propuestas_Lista extends WP_List_Table`: columnas, vistas por estado, buscador, fechas, orden, paginación y borrado múltiple |
| `ficha.php` | Encabezado, 6 pestañas, barra de guardado fija |
| `acciones.php` | Los mismos procesos de hoy, extraídos sin cambiar lógica ni nombres de campo: guardar, v3 (cambios, aprobar, destrabar), borrar uno, borrar varios y enviar correo |
| `propuestas-admin.css` / `propuestas-admin.js` | Estilos y pestañas; se encolan solo en esta página (`admin_enqueue_scripts` + `$hook`) |

- **`inc/admin-proposals.php`:** queda como cargador y ruteador. Incluye los archivos nuevos, el clásico y la función
  `at_v3_llamar_n8n`, que no cambia.
- **Sin cambios:**
  - `functions.php`: nunca se toca, porque PROD va adelantado con Finanzas.
  - La tabla `wp_automatiza_propuestas`: sin migración.
  - `inc/proposals-flow.php`: se usa tal cual.
  - El widget de seguimiento `automatiza_render_prospect_details()`.

## Lista

- **Vistas por estado** con conteo. Solo aparecen las que tienen propuestas, además de «Todas».

| Grupo | Estados |
|---|---|
| Borrador | `borrador`, `draft` |
| Ajustando | `ajustando` |
| Generando | `generando` |
| Lista para enviar | `lista` |
| Enviadas | `sent` |
| Pendiente | `pending` |
| Error | `error` |

  Un estado desconocido se muestra con su nombre tal cual, en un grupo «Otros».
- **Buscador** (`s`): coincidencia parcial, sin distinguir mayúsculas, en `company_name`, `client_name`,
  `client_email`, `phone` y `unique_link_id`. Usa `$wpdb->esc_like` y `prepare`.
- **Fechas** (`desde`, `hasta`, formato `AAAA-MM-DD`) sobre `created_at`, ambas inclusive. Un valor inválido se
  ignora y se muestra un aviso.
- **Columnas:**
  - *Empresa / cliente*: columna principal, con las acciones *Abrir ficha · Ver presentación · Borrar* (borrar con
    confirmación y el nonce `delete_proposal_<id>` de hoy).
  - *Contacto*: correo y teléfono.
  - *Estado*: etiqueta de color en español, más la marca «v3» si `flujo='v3'`.
  - *Creada*: `d-M-Y` en español.
- **Orden:** `created_at DESC` por defecto. Se puede ordenar por `company_name`, `status` o `created_at`, siempre
  contra una lista blanca.
- **Paginación:** 20 por página por defecto, ajustable en «Opciones de pantalla» entre 5 y 200.
- **Borrado múltiple:** la acción masiva «Borrar» con confirmación. Usa el nonce propio de `WP_List_Table`
  (`bulk-propuestas`) y valida IDs enteros con `prepare`.
- **Sin resultados:** «No hay propuestas con esos filtros» y un enlace para limpiarlos.
- **Celular:** el comportamiento nativo de `WP_List_Table`, con la columna principal visible y el resto desplegable.
- **Filtros en los enlaces:** buscador, vista, fechas, orden y página viajan por GET y se conservan en los
  enlaces a la ficha (`volver=`), para que «← Volver a la lista» regrese igual.

## Ficha

- **Encabezado:**
  - «← Volver a la lista» con los filtros.
  - Empresa, cliente, id, fecha de creación y etiqueta de estado.
  - Los avisos de las acciones.
  - Si `edit_id` no existe: «Esa propuesta no existe» y un enlace a la lista.
- **Un solo `<form method="POST" enctype="multipart/form-data">`** con todas las pestañas adentro.
  - Primer control: el botón oculto por defecto de hoy, para que Enter haga el guardado normal.
  - El `<script>` que bloquea Enter en los `input` de la sección v3 se mantiene.
  - Campo oculto `at_tab`: tras guardar, se reabre la misma pestaña.
- **Pestañas:** botones en el computador, un `<select>` en el celular. Son solo presentación: todos los campos
  están siempre en el DOM y se envían.
- **Barra inferior fija** con «💾 Guardar».

| Pestaña | Contenido |
|---|---|
| Resumen (por defecto) | Solo lectura: contacto, enlaces (presentación, demo del chatbot, PDF), precios del payload (o «—» si no es JSON), `status_note`, último comentario y cuántos van. Botón «Siguiente paso» que solo cambia de pestaña: borrador o error → Revisión; lista → Envío; ajustando o generando → Revisión (Destrabar) |
| Cliente y enlaces | `client_email`, `client_name`, `company_name`, `phone`, `gamma_url`, `n8n_url`, `pdf_file` y el enlace al PDF actual (`esc_url`) |
| Revisión y precios (solo v3) | `at_precio[i][service\|price_label\|emphasis]` (6 filas como hoy; tarjetas en el celular), `at_nota_precio`, `at_comentario`, historial, botones `at_v3_accion=cambios\|aprobar\|destrabar` con los mismos `confirm()` de hoy y nonce `at_v3_<id>` |
| Contenido | Transcripción `transcript_text` (solo lectura, plegable, botón Copiar); `gamma_prompt` (readonly en v3, como hoy); `system_prompt` |
| Seguimiento | `automatiza_render_prospect_details($id)` sin cambios |
| Envío | `send_email` (reglas de hoy: v3 desmarcada y activa solo en `lista`, las viejas marcada), `email_subject`, `email_intro`, `email_highlight`, `email_closing` |

- **Nombres de campo:** los mismos de hoy (`proposal_id`, `automatiza_proposal_nonce`/`save_proposal`, todos los de
  la tabla), así que `acciones.php` recibe exactamente lo que recibe el código actual.

## Seguridad y errores

- `manage_options` en la página y en cada proceso, con los nonces de hoy en cada formulario.
- Todo SQL con `prepare`; LIKE con `esc_like`; orden y columnas por lista blanca; `per_page` acotado.
- Toda salida escapada: `esc_html`, `esc_attr`, `esc_url`, `esc_textarea`. Se corrigen los dos casos sin escapar
  que hay hoy: `created_at` (línea 706) y `pdf_path` en un `href` (línea 809).
- Los procesos se despachan por acción explícita con un `switch`, no por «no vino `at_v3_accion`». Una acción
  desconocida no cae en el guardado.

## Pruebas

1. **Funciones puras** en `tests/propuestas/admin-lista-test.php`, en el estilo de `flow-test.php`: filtros
   normalizados, grupos y etiquetas de estado, fechas válidas e inválidas, orden por lista blanca, límites de
   página y WHERE resultante.
2. **Equivalencia de formularios:** un script renderiza la ficha nueva y la clásica con la misma fila de ejemplo
   (v3 y vieja) y compara el conjunto de nombres de campo que envía cada botón (Guardar, Pedir cambios, Aprobar,
   Destrabar, envío). Deben ser iguales.
3. **WordPress local (WAMP) con el navegador:** búsqueda de una propuesta antigua, vistas, fechas, orden,
   paginación, pestañas, guardado que vuelve a la misma pestaña y vista a 375 px sin desbordes (se comprueba por
   `scrollWidth`). Capturas para Luis. Si el WordPress local no tiene datos, se cargan filas de prueba solo en
   local.

## Despliegue

- Cotejo md5 con PROD, respaldo en `~/respaldos/`, subida con `php -l` en el servidor, verificación HTTP desde afuera.
- Luis hace la revisión con su sesión (el agente no inicia sesión), con una lista corta de qué mirar.
- `&clasico=1` queda unas dos semanas; se quita en un PR aparte si no hubo problemas.
- Nada toca n8n, el renderer ni la base.

## Fuera de alcance

Crear propuestas a mano desde el panel, archivar en vez de borrar, cambios al widget de seguimiento, autenticación
del webhook del Borrador y de `/render` (pendientes de la v3).
