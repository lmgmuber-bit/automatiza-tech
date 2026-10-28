# Colaborador AT con acceso a clientes asignados — diseño

**Fecha:** 27-oct-2026 · **Pedido:** Luis · **Diseño:** Eje · **Rama:** `claude/crm-colaborador-por-cliente`

## 1. Qué se pide

Una persona del equipo AT entra al CRM y **solo** ve los clientes que Luis le asigna (por ejemplo, Orly).
De cada cliente ve el **historial** y los **entregables**, puede **agregar notas** al historial y **responder**
los comentarios del cliente en los entregables. Nada más: ni los otros clientes, ni montos, ni contratos, ni
propuestas, ni el resto del panel.

Decisiones de Luis (27-oct): camino 1 (rol propio con página propia, sin abrir la ficha grande del CRM) y
solo historial y entregables.

## 2. Por qué una página propia y no la ficha del CRM

La ficha del CRM (`wp-content/mu-plugins/crm-ai-completo.php`, ~8.800 líneas) se abre con `edit_posts` y
tiene 15 acciones AJAX y montos, contratos y propuestas en la misma pantalla. Abrirla a un colaborador
obligaría a filtrar cliente por cliente cada una de esas rutas, y un filtro olvidado deja ver otro cliente.
La página nueva solo lee lo necesario y cada acción revisa el permiso en el servidor.

## 3. Piezas

| Pieza | Qué hace |
| --- | --- |
| Rol `at_colaborador` («Colaborador AT») | Capacidades: `read` y `at_colaborador`. Sin `edit_posts`: no entra al CRM ni edita contenido. Se crea en `init` si no existe (sin migración). |
| Meta de usuario `at_clientes_asignados` | Lista de ids de `wp_crm_clientes`. |
| Campo «Clientes asignados» en el perfil | Casillas con los clientes del CRM. Se muestra y se guarda **solo** si quien edita tiene `manage_options`; el colaborador no puede cambiar su propia lista. |
| `at_colab_puede(int $crm_id, int $user_id = 0): bool` | Administrador: siempre sí. Colaborador: sí solo si el id está en su lista. Cualquier otro: no. Es la única puerta: la usan la página y las dos acciones. |
| Página «Mis clientes» (menú propio, capacidad `at_colaborador`) | Sin `cliente`: lista de los asignados (nombre y empresa). Con `&cliente=ID`: si `at_colab_puede` dice que no, «No tienes acceso a este cliente» y nada más. |
| Acción «Agregar nota» (`admin-post.php`, nonce por cliente) | Inserta en `wp_crm_historial` `tipo_evento='nota'`, `titulo='Nota agregada'`, `usuario_id` = colaborador. Avisa por correo a `at_cc_correo_avisos()` con el nombre del cliente y el texto. |
| Acción «Responder comentario» (`admin-post.php`, nonce por nota) | La misma secuencia que la respuesta del panel de entregables (`inc/entregables/panel.php`): reclamar la nota, imágenes opcionales, `at_en_agregar_nota(..., 'at', nombre del colaborador, ...)`, `at_en_historial(... 'entregable_respuesta' ...)` y aviso al cliente si se marca «Avisar». Verifica que el entregable sea de un cliente asignado. |
| Al iniciar sesión | El colaborador llega directo a «Mis clientes». |

## 4. Qué muestra el historial

- Eventos de `wp_crm_historial` del cliente y detalles de `client-details-module.php`, en orden de fecha, con la
  etiqueta de origen de `at-timeline-origen.php` (👤 Cliente / 👥 Equipo AutomatizaTech / ⚙️ Automático) y el
  nombre de quien lo escribió.
- **Nunca** el monto (`amount`) ni archivos adjuntos.
- Solo tipos **permitidos** (`at_colab_tipos_permitidos`): notas, reuniones, llamadas, seguimiento, proyecto, hitos y
  entregables. Quedan fuera dinero y contratos, propuestas y su aceptación (traen precios o la llave pública de la
  propuesta), correos, WhatsApp, avisos internos, documentos y transcripciones. Un tipo nuevo no sale hasta agregarlo.
  (Corrección tras la revisión final: la primera versión usaba una lista de excluidos y dejaba pasar montos.)
- Los detalles se leen por la ficha operativa del cliente (`at_cc_techs_de_crm`), como la ficha del CRM.
- Solo lectura: no hay editar ni borrar eventos.

## 5. Qué muestra en entregables

Por cada entregable del cliente (`at_en_de_crm`): título, versiones enviadas (número, fecha, enlace, mensaje) y
los comentarios del cliente con sus respuestas. Las imágenes de los comentarios se muestran igual que en el panel
de Luis. Un comentario sin responder trae el formulario de respuesta.

## 6. Seguridad

- Toda acción vuelve a preguntar `at_colab_puede` con el id que llega, aunque venga de un formulario propio.
- Los ids se leen con `absint` y se rechazan si no existen.
- La página no imprime el nonce del CRM (`crm_nonce`) ni enlaza a la ficha del CRM.
- El colaborador no ve correo ni teléfono del cliente: solo nombre y empresa.
- Para este rol el resto del admin queda cerrado: solo «Mis clientes», su perfil, las dos acciones de
  `admin-post.php` y el heartbeat de `admin-ajax.php`; el widget MAXTECH no se dibuja (sus acciones ajax solo piden
  un nonce que se imprime en cualquier página del admin).
- Las notas del historial también las ve el cliente en su portal; el formulario lo avisa.
- Quitar un cliente de la lista corta el acceso en el acto; deshabilitar al usuario lo saca.

## 7. Pruebas

Puras (`tests/colaboradores/puras-test.php`): la puerta con administrador, con colaborador asignado y no
asignado, con usuario sin rol, con id 0 o inexistente; el filtro de tipos del historial y que el monto nunca
salga.

Con WordPress local (`tests/colaboradores/*-wp-test.php`): el colaborador con Orly asignada ve a Orly;
con el id de otro cliente recibe «No tienes acceso»; no abre `automatiza-crm-ficha`; su nota queda con su
`usuario_id` y llega el aviso; responde un comentario de un entregable de Orly y no puede responder uno de otro
cliente cambiando el id; un colaborador no puede guardar su propia lista de clientes.

## 8. Archivos

- `wp-content/mu-plugins/at-colaboradores.php`: carga el módulo (no se toca `functions.php`).
- `wp-content/themes/automatiza-tech/inc/colaboradores/`: `permisos.php` (puras), `datos.php`, `perfil.php`,
  `pagina.php`, `acciones.php`, `cerrar.php`.
- `tests/colaboradores/` y guía corta en `Docs/METODO_AT/COLABORADORES.md`.

## 9. Puesta en PROD

Sin migración. Con el ok de Luis: respaldo, subir primero la carpeta `inc/colaboradores/` y después el
mu-plugin, `php -l`, md5 y purga de caché. Luego crear el usuario con `wp user create ... --role=at_colaborador`
y el enlace para que ponga su contraseña (no la vemos), y asignarle Orly desde su perfil.

## 10. Fuera de alcance

Montos, contratos, propuestas, edición de eventos, la ficha completa del CRM, prospectos y el portal del cliente.
