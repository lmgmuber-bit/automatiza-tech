# Usuarios del backoffice: superadministrador y operadores (2026-09-13)

**EN PROD desde el 2026-09-13 a las 07:05** (commit `4f59f05` de `claude/admin-usuarios`; respaldo
`~/respaldos/admin-usuarios-antes-20260913-0705.tar.gz`). Detalle del despliegue en `FTP-MANIFEST.md`.
**Correo de bienvenida verificado en PROD:** Luis creó los usuarios para las fiestas del 13-sep y
confirmó el 15-sep que todos los correos llegaron.

Luis pidió, la madrugada del día de las dos fiestas, poder **delegar el admin de una fiesta** a una
persona: crearle un usuario, que le llegue un correo corporativo con una contraseña temporal que
tiene que cambiar, y decidir él —como superadministrador— qué fiestas y qué opciones ve. La
persona solo ve sus fiestas y su perfil (nombre y contraseña); el correo se lo cambia solo Luis.

## Cómo queda

| Pantalla | Quién | Qué hace |
|---|---|---|
| `admin/login.php` | todos | **Correo + contraseña.** Es la entrada por defecto: cualquier página del admin sin sesión manda aquí. |
| `admin/maestro.php` | Luis | La pantalla de **solo contraseña** de siempre, intacta, en otra dirección. Entra como superadministrador. Es el respaldo si el login por correo fallara. `recuperar.php` sigue cambiando esa clave. |
| `admin/usuarios.php` | superadministrador | Tabla maestra de usuarios del backoffice: crear, habilitar/deshabilitar, cambiar fiestas y módulos, mandar contraseña temporal nueva. No se borra: se deshabilita. |
| `admin/perfil.php` | quien entró con correo | Nombre y cambio de contraseña (pide la actual). El correo es de solo lectura. |

**Roles:** `super` (todo) y `operador` (solo sus fiestas y sus módulos). Quien entra por
`maestro.php` es superadministrador sin fila en la tabla (id 0).

**Módulos de un operador** (casillas en Usuarios):

| Clave | Qué abre |
|---|---|
| `fotos` | Fotos del kiosco: ver la galería sin PIN, borrar, imprimir (`album.php#fotos-kiosco`, `galeria.php`, `ver-media.php`) |
| `juegos` | Prender/apagar los juegos 3D de la fiesta y el menú de juegos con la tabla de posiciones |
| `carteles` | Carteles QR (`carteles.html` y `carteles-api.php`) |
| `invitados` | Agregar invitados durante la fiesta, con un formulario que solo toca la lista |
| `mensajes` | Pestaña Mensajes de su fiesta |
| `album` | Álbum Recuerdo |
| `perfil` | Perfil del protagonista |

La ficha de la fiesta (nombre, temática, dirección, invitados, enlaces) la ve todo operador con la
fiesta asignada; no es un módulo aparte.

**Solo superadministrador, siempre:** crear/editar/duplicar/eliminar fiestas, Temáticas,
Solicitudes, Invitaciones, Comprobante, Aceptaciones, Planes, Finanzas, Ajustes, Marca y Usuarios.

**Recomendación para la ayudante de la fiesta de Samantha:** fiesta `samantha-hielo` y módulos
`fotos`, `juegos`, `carteles` e `invitados`.

## Cómo funciona por dentro

- **Tablas** (migración `023_admin_users`): `cc_admin_users` (correo único, nombre, hash de la
  contraseña, rol, activo, debe_cambiar, módulos como JSON, último acceso) y
  `cc_admin_user_parties` (usuario ↔ fiesta). Sin base de datos (`storage_mode=json`) no hay
  usuarios: se entra solo por la clave maestra.
- **Portero único** `admin/_acceso.php`: cada página del admin lo carga justo después de abrir
  la sesión. Si no hay sesión, redirige a `login.php` (con `volver`); si la sesión venció, la
  cierra; carga el usuario **desde la base en cada petición**, así que deshabilitarlo lo saca en
  el acto; si tiene contraseña temporal, solo puede ir a `perfil.php`; y aplica los permisos por
  página, fiesta (`?party=` o `?p=`) y módulo. Sin permiso responde 403 con la página "No tienes
  acceso", nunca el contenido.
- **`index.php` para operadores:** lista solo sus fiestas; sin "Nueva fiesta", sin editar,
  duplicar ni eliminar; sin las pestañas de superadministrador; en cada tarjeta solo los botones
  de sus módulos; y las acciones POST se reducen a salir, prender/apagar juegos y agregar un
  invitado, cada una revisando la fiesta.
- **Galería y visor** (`galeria.php`, `ver-media.php`, `ver.php`): la sesión de admin salta el
  PIN y muestra la papelera **solo en las fiestas del usuario**.
- **Sesión:** la misma cookie `cc_admin` de siempre, con `admin_usuario_id` (0 = clave maestra).
  Mismos tiempos de vencimiento.
- **Contraseñas:** hash con `password_hash`; temporal de 12 letras y números sin caracteres
  ambiguos; la nueva pide 10 caracteres como mínimo, igual que la maestra.
- **Límites:** 5 intentos por 15 minutos por IP (compartido con la clave maestra) y 10 por hora
  por correo.
- **Correo de bienvenida:** plantilla corporativa (`cc_mail_shell`), con quién dio el acceso,
  las fiestas, el enlace de entrada, el correo y la contraseña temporal. Si el SMTP falla, la
  pantalla de Usuarios muestra la contraseña temporal **una sola vez** para pasarla a mano.
- **Las páginas viejas conservan su bloque de login por contraseña** debajo del portero. Es
  código muerto en la práctica (el portero redirige antes) y se dejó para no tocar 13 archivos
  en el día de la fiesta. Se puede retirar después.

## Pruebas

- `tests/backend/usuarios.php`: crear, entrar, clave mala, deshabilitado, contraseña temporal
  obligatoria, permisos por fiesta y módulo, correo de bienvenida.
- `tests/backend/usuarios-http.php`: levanta `php -S` sobre `public/` con SQLite y recorre el
  admin como operador y como clave maestra: redirección al login, entrar, cambio obligatorio,
  lista filtrada, 403 en las páginas ajenas, deshabilitar corta la sesión.

## Despliegue

1. `database/migrations/023_admin_users.php` y `database/aplicar-023.php` → `domains/cumpleclick.com/database/`; correr `php aplicar-023.php` por SSH. **Antes que el código.**
2. `public/lib.admin-usuarios.php`, `public/lib.php`, `public/galeria.php`, `public/ver-media.php`, `public/ver.php` → `app/`.
3. `public/admin/_acceso.php`, `login.php`, `maestro.php`, `usuarios.php`, `perfil.php` y las 13 páginas del admin modificadas → `app/admin/`.
4. Respaldo previo en `~/respaldos/`; rollback = restaurar el tar. Las tablas nuevas no estorban al código viejo.

**No subir:** `tests/`.
