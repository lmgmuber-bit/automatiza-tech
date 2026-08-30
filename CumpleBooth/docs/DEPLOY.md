# CumpleClick — deploy seguro a Hostinger

## Dos ambientes (desde 2026-08-29)

Luis compró `cumpleclick.com`. A partir de ahí son dos ambientes, no uno:

| | URL | Rol |
|---|---|---|
| **PROD** | `https://cumpleclick.com/` | El que ven los clientes |
| **Pre-producción** | `https://automatizatech.cl/cumpleclick/` | Pruebas antes de PROD |

**Corrección de este documento:** decía "No hay deploy realizado aún" y era
falso. `https://automatizatech.cl/cumpleclick/` sirve un build real —comprobado
por HTTP el 2026-08-29: la portada del kiosco, `album.html`, `cartel-qr.html` y
`admin/` responden, `invitacion.php` pide token con 400 y `data/parties.json`
da 403—. Ese deploy está ATRASADO: no tiene `regalos-papas.php`,
`predicciones.php` ni las temáticas de baby shower.

`cumpleclick.com` al 2026-08-29 es un dominio estacionado de Hostinger (página
"Parked Domain name", `noindex`). No hay nada de CumpleClick ahí todavía.

### Lo que OBLIGATORIAMENTE tiene que ser distinto entre los dos

Si comparten cualquiera de estas cosas, probar en pre-producción escribe en los
datos reales de un cliente:

- **Base de datos.** Una por ambiente, con usuario propio. Nunca la misma.
- **`photo_dir` y `state_dir`.** Las fotos de una prueba no pueden mezclarse con
  las de una fiesta real. Los dos fuera de `public_html`.
- **`public_base_url`.** Es el único lugar del que salen TODAS las URLs públicas
  (invitación, álbum, QR, pantallas de los papás): todo pasa por
  `cb_public_base_url()` en `public/lib.php`. No se construye nada desde
  `HTTP_HOST`, así que cambiar el dominio es cambiar esta línea.
- **`app_hmac_key`.** Distinta por ambiente, o un token de pruebas vale en PROD.
- **Credenciales de admin.**
- **`robots.txt`.** Pre-producción va con `Disallow: /` o compite con PROD en
  Google y publica fiestas de prueba.

### Como se reparte cumpleclick.com

Decision de Luis (2026-08-29): la cara principal del dominio es el SITIO, no el
kiosco.

    public_html/            <- contenido de sitio/     la landing publica
    public_html/app/        <- contenido de dist/      kiosco, invitaciones,
                                                       album y admin

Con eso `public_base_url` es `https://cumpleclick.com/app`, y las invitaciones
salen como `cumpleclick.com/app/<nombre>-<token>`. Es el precio de que el sitio
mande en la raiz; si algun dia se prefiere el enlace corto, se invierte el
reparto y el sitio pasa a una subcarpeta.

Nada de esto es un cambio de codigo: Vite compila con `base: './'` y ningun
.htaccess usa `RewriteBase`.

### Donde vive la configuracion, y por que hay un puente

`cb_config()` busca `$root/config/cumpleclick.local.php`, y `$root` es
`dirname(__DIR__)` desde `lib.php`. En el servidor `lib.php` queda en
`public_html/app/`, asi que `$root` es **public_html**: por defecto la
configuracion —con la clave de la base y la clave HMAC— caeria DENTRO del
webroot.

La solucion no toca una linea de codigo. Son tres archivos:

    domains/cumpleclick.com/cumpleclick-config.php          <- la de verdad, 0600
    domains/cumpleclick.com/public_html/config/cumpleclick.local.php   <- puente
    domains/cumpleclick.com/public_html/config/.htaccess     <- Require all denied

El puente es una linea: `return require '<ruta absoluta de afuera>';`. Aunque
alguien lo leyera como texto plano, lo unico que veria es una ruta. Verificado
por HTTP: `/config/`, `/config/cumpleclick.local.php` y `/config/.htaccess`
devuelven **403**, y `/cumpleclick-config.php` devuelve **404** porque esta
fuera del webroot.

Los tres valores que faltan los pone Luis, no un agente: `pdo_password`,
`app_hmac_key` (64 caracteres al azar, propios de este ambiente) y
`admin_password_hash`. Mientras el hash este vacio nadie puede entrar al admin
—`admin/index.php` corta antes de `password_verify`— asi que el estado por
defecto es cerrado, no abierto.

### Ojo con el nivel: `domains/<dominio>/` NO es el webroot

Hostinger deja ahi un archivo `DO_NOT_UPLOAD_HERE` justamente porque es el error
comun. El webroot es `domains/<dominio>/public_html`. Subir a un nivel de mas no
da error: los archivos quedan en el servidor, ocupando espacio, sin que nada los
sirva y sin que nada avise.

Paso el 2026-08-30 con el `dist/` completo (449 archivos, 582 MB). No se perdio
nada: como el contenido ya estaba en el servidor, se movio a su lugar con `mv`
—instantaneo— en vez de volver a transferir 570 MB.


### La pagina 404 de marca

`sitio/404.html` es la 404 (y la 403) de TODO el dominio. Va sin una sola
peticion externa —isotipo incrustado, estilos adentro— porque una pagina de
error que a su vez falla al cargar sus assets es peor que la de Apache, y se
muestra justo cuando algo ya anda mal. La unica dependencia es Baloo 2 desde
`/fonts/`, que cae a la del sistema sin romper nada.

Quien la activa es `sitio/.htaccess`, no el del kiosco, y el motivo importa:
`ErrorDocument` resuelve la ruta desde el DocumentRoot del dominio, no desde la
carpeta del .htaccess. Escrita en la raiz, `/404.html` vale para la raiz Y para
`app/`, que la HEREDA sin tener que saber en que carpeta lo montaron.

Verificado contra Apache, no solo leido: pedir un `.php`, un medio de tematica
o un asset inexistentes dentro de `app/` devuelve **404 con la pagina de
CumpleClick**, y `fotos/`, `admin/config.php` y `data/themes.json` devuelven
**403 con la misma pagina**. El status se conserva en los dos casos.

En pre-produccion el kiosco vive dentro de OTRO dominio, asi que hereda la
pagina de error de ese dominio. Si se quiere la de CumpleClick tambien ahi:
copiar `404.html` a `/cumpleclick/` y agregar `ErrorDocument 404
/cumpleclick/404.html` al .htaccess de esa carpeta.


### Sobre el cambio de dominio

La aplicación es agnóstica de la ruta a propósito: Vite compila con `base: './'`
y el `.htaccess` no usa `RewriteBase`. Servirla desde la raíz de un dominio o
desde una subcarpeta **no requiere ningún cambio de código**, solo la
configuración externa. Las únicas menciones a `automatizatech.cl` en el código
son la atribución del pie de la invitación (`public/invitacion.php`), que es
correcta y se queda, y un comentario en `scripts/web/_at-migrar.php`.

Como PROD es una instalación nueva, no hay invitaciones ni QR ya repartidos
apuntando al dominio viejo: no hace falta redirección de compatibilidad.

## Verificar un deploy: mirar el CONTENIDO, no el status

Un archivo que no se subió **respondía 200 con la portada del kiosco**, porque
caía en el catch-all del SPA. Así se veía `regalos-papas.php` en producción: 200,
803 bytes, y nada avisaba. Cualquier chequeo que mire solo el código HTTP da por
bueno un deploy incompleto.

Desde 2026-08-29 el `.htaccess` cierra ese agujero: un `.php` inexistente y un
medio de temática inexistente devuelven **404**, igual que ya hacían los assets
con hash. Verificado contra Apache real, y comprobado que la URL bonita de
invitación, el fallback del SPA, `fotos/` (403) y `admin/config.php` (403)
siguen intactos.

Aun así, al verificar un deploy hay que mirar el `Content-Type` y el tamaño:
- un `.php` que responde `text/html` de ~800 bytes es la portada del kiosco;
- un `.jpg` que responde `text/html`, o un 422 "Invalid source image" de
  Hostinger, es un archivo que no está.

---

## Instalación (vale para los dos ambientes)

## 1. Preparación privada

1. Crear una BD y usuario exclusivos para CumpleClick; InnoDB + utf8mb4. No usar
   credenciales de WordPress/AutomatizaTech.
2. Colocar `scripts/`, `database/` y una copia de
   `config/cumpleclick.example.php` fuera de `public_html`.
3. Ejecutar `php scripts/bootstrap.php` o crear manualmente la configuración
   externa. Definir `CUMPLECLICK_CONFIG_FILE`; `public_base_url` es
   `https://cumpleclick.com` en PROD y `https://automatizatech.cl/cumpleclick`
   en pre-produccion, y `photo_dir`/`state_dir` deben estar fuera de
   DocumentRoot y ser DISTINTOS entre los dos ambientes.
4. No subir ni mostrar el archivo real, passwords, HMAC key, dumps o backups.

## 2. Migración y cutover

```bash
php scripts/migrate.php
php scripts/import-theme-prompts.php          # dry-run obligatorio
php scripts/import-theme-prompts.php --apply  # carga prompts privados en la BD
php scripts/import-json-to-db.php          # dry-run obligatorio
php scripts/import-json-to-db.php --apply  # crea backups privados fechados
php scripts/parity-check.php
php scripts/retention.php                  # dry-run
```

Confirmar `storage_mode=db`. Programar `retention.php --apply` diariamente por
cron. Durante estabilización conservar BD, JSON y backups.

Rollback: `php scripts/rollback.php` (dry-run), después `--apply`; si la BD está
caída usar `--snapshot=<snapshot-cutover.json>` y documentar que el RPO es la
fecha del snapshot. El script no borra tablas ni fotos.

## 3. Build y publicación web

```bash
npm ci
npm test
npm run build
php scripts/check-dist-parity.php
```

Subir el contenido de `dist/` al webroot del ambiente —en PROD la raiz del
dominio `cumpleclick.com`, en pre-produccion `/public_html/cumpleclick/`—,
incluidos
`.htaccess` y `.user.ini`. No subir `src/`, `tests/`, `node_modules/`, config,
scripts, migraciones, fotos ni backups al webroot. Configurar HTTPS en el
vhost/proxy con host canónico; no usar el Host header para redirects.

## 4. Gate posterior

- `api.php?p=<slug real>` → 200 y `ok:true` sin campos internos. Mirar el
  CUERPO, no el status: un 200 de ~800 bytes de HTML es la portada del kiosco
  cayendo al fallback del SPA, no la API.
- Un `.php` inventado y un medio de temática inventado → **404**, no 200. Si
  responden 200 falta el `.htaccess` nuevo y no se puede confiar en ningún
  otro chequeo por HTTP.
- `/data/parties.json`, `/admin/config.php`, config y storage → 403/404.
- Un único header `Permissions-Policy: camera=(self), microphone=(), geolocation=()`.
- Login/CSRF/logout admin; editar frame y confirmar persistencia.
- Upload PNG válido y negativos; QR abre `ver.php?t=<token>`.
- Chrome/tablet real: Preview, personaje, Baloo 2, QR, Diploma, consola limpia y
  cámara apagada al salir de Capture.

Nunca afirmar que PROD fue actualizado sin evidencia HTTP/FTP explícita.
