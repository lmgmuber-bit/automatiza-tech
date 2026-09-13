# Mapa PROD ↔ repositorios de CumpleClick

El 2026-09-13 se cotejó por sha256 cada archivo de `domains/cumpleclick.com` (`public_html/`,
`database/` y `scripts/`) contra los repositorios. Son 1265 archivos:

- **1129 son iguales a un repositorio que está en GitHub.**
- **134 quedan fuera de git a propósito** (tabla más abajo).
- **2 difieren, y en los dos el repositorio es igual o más nuevo que PROD.**

Antes de desplegar cualquier cosa, esta es la tabla que dice de dónde sale cada carpeta. Un
archivo que se sube sin salir de aquí vuelve a dejar a PROD como única copia.

## Qué carpeta de PROD sale de dónde

| Carpeta en PROD | Repositorio | Rama | Carpeta en el repo |
|---|---|---|---|
| `public_html/` (portada del dominio) | `automatiza-tech` (público) | `claude/foto-grupal` | `CumpleBooth/sitio/` |
| `public_html/app/` (kiosco, admin, galería, álbum, invitación, PHP) | `automatiza-tech` | `claude/foto-grupal` | `CumpleBooth/public/` + compilado de `npm run build` (`CumpleBooth/dist/`) |
| `app/juego/index.html` (menú), `pantalla.js`, `orientacion.*`, `.htaccess`, `fuentes/` | `automatiza-tech` | `claude/foto-grupal` | `CumpleBooth/public/juego/` |
| `app/juego/mundo.html`, `game/`, `models/`, `temas/`, `texturas/`, `audio/`, `vendor/`, `ayudante.*`, `client.js`, `portada.jpg` | `cumpleclick-juego-reino-hielo-3d` (privado) | `main` | `app/public/`. 🔴 Su `index.html` se sube como **`mundo.html`** |
| `app/juego/festival/` | `cumpleclick-juego-festival-estrellas` (privado) | `prod` | raíz |
| `app/juego/impulso-aracnido/` | `cumpleclick-juego-impulso-aracnido` (privado) | `prod` | raíz |
| `app/juego/aurora/` | `cumpleclick-juego-aurora-cristal` (privado) | `main` | raíz |
| `app/juego/circuito/` | `cumpleclick-juego-circuito-aracnido` (privado) | `main` | raíz |
| `database/` (fuera de la web) | `automatiza-tech` | `claude/foto-grupal` | `CumpleBooth/database/` |
| `scripts/` (fuera de la web) | `automatiza-tech` | `claude/foto-grupal` | `CumpleBooth/scripts/` |

El backend de los juegos es parte del kiosco: `lib.puntajes.php` (menú y tabla de posiciones),
`sala.php`, `lib.sala.php`, `lib.sala.carrera.php` y las migraciones `014_salas_ayudantes` y
`021_sala_carreras`.

## Repositorios de la cuenta `lmgmuber-bit`

| Repositorio | Visibilidad | Rama por defecto | Carpeta local | Nota |
|---|---|---|---|---|
| `automatiza-tech` | público | `main` | `C:\wamp64\www\automatiza-tech` | el kiosco vive en `CumpleBooth/` |
| `cumpleclick-juego-reino-hielo-3d` | privado | `main` | `C:\wamp64\www\tucumple-repo` | remoto `github`; `origin` sigue en Higgsfield, donde el push se cuelga |
| `cumpleclick-juego-festival-estrellas` | privado | `prod` | `C:\wamp64\www\tucumple-codex` | `codex/festival-estrellas` es el original intacto de Codex |
| `cumpleclick-juego-impulso-aracnido` | privado | `prod` | `C:\wamp64\www\impulso-aracnido` | las seis ramas de Codex subidas tal cual |
| `cumpleclick-juego-aurora-cristal` | privado | `main` | `C:\wamp64\www\cumpleclick-juego-aurora-cristal` | `historia-codex` = carpeta extraída de `codex/aurora-qa-integracion` |
| `cumpleclick-juego-circuito-aracnido` | privado | `main` | `C:\wamp64\www\cumpleclick-juego-circuito-aracnido` | igual; con docs, pruebas y scripts de Codex |
| `cumpleclick-juego-reino-hielo-codex` | privado | `codex/princesa-invitacion-corregida` | `C:\wamp64\www\juego-frozen-codex` | **no está en PROD**; `respaldo/sin-commit-20260913` guarda los 12 cambios sin commit de Codex |
| `cumpleclick-respaldos` | privado | `main` | `C:\wamp64\www\cumpleclick-respaldos` | bundles de ramas locales, trabajo sin commit de otras sesiones y copias sin git |

Cada repositorio de juego trae un `ORIGEN-Y-PROD.md` con su historia y sus trampas.

## Lo que queda fuera de git a propósito (134)

| Qué | Cuántos | Por qué |
|---|---|---|
| Respaldos `*.bak-*` junto al original | 63 | Dan 403 por el `FilesMatch` de `public_html/.htaccess` y de `app/.htaccess`. Falta decidir el borrado |
| Bundles de compilaciones anteriores en `app/assets/` | 62 | Salidas viejas de `npm run build`; ninguna página actual los pide |
| `public_html/config/` | 2 | Configuración del servidor con secretos. Nunca va a un repositorio |
| `database/respaldo-*.json` | 2 | Respaldos con datos de invitados |
| `app/album.html`, `app/cartel-qr.html` | 2 | Compilados: solo cambian los nombres de los bundles |
| `app/juego/mundo.html` | 1 | Igual a `index.html` de `cumpleclick-juego-reino-hielo-3d`, salvo el comentario de advertencia |
| `app/data/parties.json` | 1 | Datos de fiestas; PROD corre en modo base de datos |
| `public_html/default.php` | 1 | Página por defecto de Hostinger |

Tampoco entran en el cotejo, porque no son código: `almacen/` (fotos de las fiestas),
`cumpleclick-config.php` (secretos) y `config/`, todos fuera de `public_html`.

## Las dos diferencias explicadas

- `database/migrations/003_invitations_and_plan.php`: el repositorio agrega guardas para poder
  aplicarla dos veces. En PROD ya está aplicada desde el 2026-08-30, así que el archivo del
  servidor no se vuelve a usar.
- `scripts/retention.php`: solo cambian los fines de línea.

La subida de las dos versiones del repositorio quedó **bloqueada por permisos** el 13-sep. Es
opcional: ninguno de los dos archivos se sirve por la web ni se ejecuta solo.

## La base de datos

`cc_schema_migrations` registra **24 migraciones aplicadas**, de la `001` a la `022`. Hay **dos
`013`** (`013_narration_intro_output` y `013_plan_acceptances`) y **dos `014`** (`014_rsvp` y
`014_salas_ayudantes`), con nombres distintos. La carpeta `database/migrations/` del servidor
llegaba solo hasta la `016` y no reflejaba la base. El 13-sep se le subieron las 12 que faltaban
(respaldo `~/respaldos/herramientas-antes-alinear-20260913-0035.tar.gz`). **Para saber qué está
aplicado hay que preguntarle a la base, no mirar la carpeta.**

## Ramas locales y trabajo sin commit

Todo está en `cumpleclick-respaldos`:

- **`automatiza-tech-ramas-juegos-20260913.bundle`:** las 13 ramas que solo existían en este PC,
  entre ellas `claude/aurora-integracion`, `codex/aurora-*`, `codex/cierre-juegos`,
  `codex/carrera-grupal` y `codex/invitacion-narracion-3-temas`.
- **`automatiza-tech-wip-otras-sesiones-20260913.bundle`:** una foto de los 153 archivos sin
  commit del checkout principal. No incluye `api/process-at-ig.config.php`, que trae un token en
  texto plano, ni `media/`, que pesa 810 MB.
- **`copias-sin-git/`:** los archivos de las carpetas sin git de `C:\wamp64\www` que no estaban en
  ningún repositorio.

## Cómo volver a cotejar

1. Sacar la huella de PROD por SSH, desde `~/domains/cumpleclick.com`:
   ```bash
   find public_html database scripts -type f -print0 | xargs -0 sha256sum > prod-manifest.txt
   ```
2. Comparar cada ruta con la fuente de la tabla de arriba, tolerando fines de línea. Los scripts
   de la sesión del 13-sep están en `cumpleclick-respaldos/herramientas-cotejo/`: `cotejo-final.py`
   separa lo igual, lo que queda fuera por regla y lo inesperado.
3. Todo lo inesperado se resuelve antes de desplegar. O el repositorio recibe lo de PROD, o PROD
   recibe lo del repositorio, con respaldo en `~/respaldos/`.

## Reglas que salieron de este cotejo

- **Los respaldos de un despliegue van a `~/respaldos/`**, nunca como `<archivo>.bak` al lado del
  original: dentro de `public_html` quedaban públicos.
- **El `.gitignore` de `CumpleBooth` ignora los `.htaccess`.** Los de `brand/carteles/` y
  `juego/fuentes/` estaban solo en PROD. Se agregan con `git add -f`.
- **Un export de Codex no se sube sin cotejar** la carpeta del juego contra PROD. Los arreglos del
  Circuito del 10-sep estuvieron a un export de perderse.
- **Hay preguntas a Codex pendientes** sobre trabajo suyo fuera de este PC, `juego-frozen-codex` y
  la rama definitiva de Impulso. Codex debe responder en
  `Docs/ORCHESTRATION/CODEX-RESPUESTA-ALINEACION-JUEGOS-2026-09-13.md`.
