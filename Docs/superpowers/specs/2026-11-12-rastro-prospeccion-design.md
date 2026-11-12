# Rastro · Prospección: pestaña de prospectos en el panel de AT

Fecha: 12-nov-2026 · Responsable: Eje (plataforma AT) para Rastro · Estado: **borrador para aprobación de Luis**

## Intención

AutomatizaTech recibe muy pocos leads por mes y casi todos llegan solos. Luis quiere salir a buscarlos: encontrar negocios que necesitan lo que vendemos, contactarlos con algo real que vimos de ellos, y ver en el panel quién respondió y quién llenó el formulario. Cuando alguien llena el formulario, debe quedar en el CRM como prospecto y Luis debe enterarse.

Rastro es la oficina que hace la búsqueda y la calificación. Esta pestaña es donde vive su trabajo.

## Objetivos

1. Una lista de negocios prospectados con su estado: **por contactar → contactado → respondió → llenó formulario**, más **descartado** y **pidió baja**.
2. Cargar prospectos desde un CSV (Lead Scout o Sellbird) o a mano, sin duplicados.
3. Para cada prospecto, un enlace personal al formulario (`?r=<token>`). Si llena el formulario con ese enlace, el prospecto pasa solo a **llenó formulario**, entra a `crm_clientes` con `origen = 'prospeccion'` y Luis recibe el aviso.
4. Un enlace de baja por prospecto que lo saca de la lista al instante.
5. Números simples arriba de la lista: cuántos hay en cada estado, por fuente y por rubro.

## No objetivos (v1)

- **No envía nada.** Rastro prepara el texto y Luis lo manda desde su correo o WhatsApp; en el panel se marca «contactado». El envío automático es otra etapa, después de la revisión de Toga.
- No busca prospectos desde el servidor: la búsqueda corre en local (Lead Scout, Sellbird) y se importa el CSV. Ninguna llave de API se sube al servidor.
- No reemplaza el CRM ni las citas: termina donde empieza el CRM.

## Anti-objetivos

- Que salga un mensaje sin el OK de Luis.
- Guardar datos de personas particulares: solo datos que el negocio publica (nombre comercial, web, teléfono y correo de contacto).
- Que un prospecto que pidió baja vuelva a aparecer por una importación nueva.

## Diseño

### Datos: tabla nueva `{prefix}at_prospectos`

| Columna | Tipo | Nota |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| negocio | VARCHAR(190) | nombre comercial |
| rubro, comuna | VARCHAR(100) | |
| web | VARCHAR(255) | |
| dominio | VARCHAR(190) | normalizado, sin `www`; clave de duplicados |
| telefono, correo | VARCHAR(30) / VARCHAR(190) | publicados por el negocio |
| fuente | VARCHAR(30) | `lead_scout`, `sellbird`, `manual`, `instagram` |
| senales | TEXT | lo que vio Rastro (sin reservas en línea, sin WhatsApp…) |
| servicio_sugerido | VARCHAR(100) | servicio de AT que le calza |
| estado | VARCHAR(20) | `por_contactar`, `contactado`, `respondio`, `formulario`, `descartado`, `baja` |
| canal | VARCHAR(20) | por dónde se contactó |
| token | CHAR(16) UNIQUE | para el enlace del formulario y la baja |
| crm_cliente_id | BIGINT NULL | al pasar al CRM |
| contactado_at, respondio_at, formulario_at, baja_at | DATETIME NULL | |
| notas | TEXT | |
| created_at, updated_at | DATETIME | |

Índices: `UNIQUE(token)`, `UNIQUE(dominio)` cuando hay dominio, índice por `estado` y por `telefono`. Migración versionada con opción `at_prospectos_db_version`. Calibre revisa esquema, índices y largos (formulario ≤ servidor ≤ columna).

### Pantalla: submenú «Prospección» bajo CRM

- `add_submenu_page('automatiza-crm', …, 'manage_options', 'at-prospeccion', …)`.
- Lista con `WP_List_Table` siguiendo el patrón de `propuestas-admin/lista.php`: vistas por estado, filtros por fuente y rubro, búsqueda, paginación.
- Acciones por fila: **Copiar mensaje** (texto con la señal real y el enlace personal), **Abrir WhatsApp** (`wa.me` con el texto prellenado; Luis aprieta enviar), **Marcar contactado**, **Marcar respondió**, **Descartar**, **Ver en CRM**.
- Acciones masivas: marcar contactado, descartar.
- **Importar CSV**: mapea columnas, salta duplicados por dominio o teléfono, nunca reimporta un `baja` y muestra cuántos entraron, cuántos se repetían y cuántos eran baja.
- Íconos Phosphor 2.1.1 en menú, acciones y estados, con `aria-label` en los botones que solo tienen ícono.

### Formulario y baja

- El enlace personal apunta al formulario público que elija Luis (ver pregunta 1) con `?r=<token>`. El formulario lo lleva en un campo oculto.
- Al guardar el formulario: si el token existe y no está en `baja`, el prospecto pasa a `formulario`, se crea o actualiza el cliente con `at_cc_asegurar_cliente([... 'origen' => 'prospeccion'])` y se guarda `crm_cliente_id`. El formulario sigue funcionando igual sin token.
- Aviso: el correo que ya manda el formulario, con una línea «Viene de Prospección: <negocio>, contactado el <fecha>».
- Baja: una página pública `?baja=<token>` marca `baja` y lo confirma en pantalla. El texto del mensaje siempre incluye ese enlace.

### Cómo sirve a más de un cliente

La misma pieza (lista con estados, enlace personal al formulario y paso al CRM) es el corazón de la «fase 1» que AT ofrece a clientes que prospectan. Se construye primero para AT y se reutiliza.

## Restricciones

- Repositorio público: sin datos de prospectos, sin CSV reales y sin listas en el repo ni en el webroot.
- Toda consulta con `$wpdb->prepare`; nonce y `manage_options` en cada acción del panel; el token del formulario no da acceso a nada más que su propio prospecto.
- Despliegue por el camino normal: rama → PR → OK de Luis → respaldo → subida → verificación.
- Documentación de entrega al día (diccionario de datos y CHANGELOG).

## Decidido por Luis (12-nov-2026)

- Nombre de la oficina: Rastro.
- Motor en paralelo: piloto de Sellbird solo hasta la lista y motor propio (Lead Scout + n8n), se decide con datos.

## Llamadas de Eje (revisables)

- Submenú bajo CRM y no menú propio, para que todo lo comercial quede junto.
- Importación por CSV en v1 en vez de una conexión directa con n8n.
- Estados propios de la prospección (no los del glosario de leads, que describen otra etapa).

## Preguntas para Luis

1. ¿A qué formulario apunta el enlace: al de agendar diagnóstico que ya existe, o a uno nuevo y corto solo para prospección?
2. ¿El aviso basta por correo, o también quieres WhatsApp?
3. ¿Confirmas que en v1 el envío lo haces tú (copiar o abrir WhatsApp) y que el envío automático espera a Toga?

## Lo que queda al constructor

Textos de la pantalla, orden de columnas, diseño de la tarjeta de números y el texto base del mensaje (con la señal real, sin promesas de resultados ni precios).
