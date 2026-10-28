# Colaborador AT por cliente

Spec: `Docs/superpowers/specs/2026-10-27-crm-colaborador-por-cliente-design.md` · Plan: `Docs/superpowers/plans/2026-10-27-crm-colaborador-por-cliente.md`

## Qué es

Un usuario de WordPress con el rol **«Colaborador AT»** entra a una página propia, **«Mis clientes»**, y solo ve
los clientes del CRM que un administrador le asigna. De cada uno:

- **Ve** el historial (la misma línea de tiempo del CRM, con quién hizo cada cosa) y los entregables (versiones
  enviadas, comentarios del cliente y respuestas).
- **Puede** agregar notas al historial (a AT le llega un correo con cada una) y responder los comentarios del
  cliente en los entregables. La respuesta lleva su nombre. **Ojo:** las notas del historial también las ve el
  cliente en su portal (como las de Luis); el formulario lo avisa.
- **No ve** otros clientes, montos, adjuntos, propuestas ni su aceptación, cotizaciones, boletas, facturas, pagos,
  contratos, correos, WhatsApp ni avisos internos, ni el correo o el teléfono del cliente. El historial usa una
  lista de tipos **permitidos** (`at_colab_tipos_permitidos`): un tipo nuevo no se muestra hasta agregarlo ahí.
- **No sale de «Mis clientes»:** para este rol el resto del panel, `admin-post.php` (salvo sus dos acciones) y
  `admin-ajax.php` (salvo el heartbeat) quedan cerrados, y el widget MAXTECH no se dibuja (`cerrar.php`).

## Crear el usuario

Por SSH en el WordPress de PROD (o desde Usuarios → Añadir nuevo, con el rol «Colaborador AT»):

```bash
wp user create <usuario> <correo> --role=at_colaborador --display_name="Nombre Apellido" --send-email
```

`--send-email` le manda el enlace para que ponga su propia contraseña: nadie más la ve.

## Asignar y quitar clientes

Usuarios → editar al colaborador → sección **«Clientes asignados»** → marcar los clientes → Actualizar. Solo un
administrador ve ese campo; el colaborador no puede cambiar su propia lista.

Para quitar el acceso: desmarcar el cliente (corta en el acto, aunque tenga la página abierta), cambiarle el rol o
borrar el usuario.

## Cómo funciona por dentro

- `wp-content/mu-plugins/at-colaboradores.php` carga `wp-content/themes/automatiza-tech/inc/colaboradores/`.
  `functions.php` y `crm-ai-completo.php` no se tocan.
- Una sola puerta por cliente: `at_colab_puede($crm_id)`. La usan la página y las dos acciones (`admin-post.php`
  `at_colab_nota` y `at_colab_responder`), siempre con el cliente real del dato.
- Los detalles del cliente se leen por su ficha operativa (`at_cc_techs_de_crm`), como la ficha del CRM.
- Sin migración: el rol se crea solo en `init` y la lista vive en la meta de usuario `at_clientes_asignados`.

## Pruebas

```bash
bash tests/colaboradores/correr.sh
```

Necesitan WAMP encendido. Usan el WordPress local (`C:/wamp64/www/automatiza-tech/wp-load.php`) y cargan el
módulo desde la rama; crean y borran sus propios datos.

## Desplegar en PROD

Con el ok de Luis:

1. Respaldo de lo que exista con el mismo nombre (la primera vez, nada).
2. Subir `wp-content/themes/automatiza-tech/inc/colaboradores/` completo.
3. Subir al final `wp-content/mu-plugins/at-colaboradores.php` (sin la carpeta, el mu-plugin no carga nada).
4. `php -l` de cada archivo, md5 contra el commit y `wp litespeed-purge all`.
5. Crear el usuario y asignarle sus clientes.

Para revertir: borrar `mu-plugins/at-colaboradores.php`. El rol y la meta quedan sin uso.
