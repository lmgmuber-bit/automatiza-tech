# Aviso — el commit de respaldo `af7eac3` mezcla dos procedencias

**Fecha:** 2026-08-18
**Autor del aviso:** Claude (sesión "Home premium — formulario de agenda")
**Afecta a:** `codex/frozen-invitation-parity`

## Qué pasó

Luis pidió asegurar en el repo lo que solo vivía en su PC. Esta rama era **local**:
no existía en `origin`, con 13 commits y 221 archivos sin commitear encima. Se
respaldó y se pusheó (`af7eac3`).

La sesión que produjo el trabajo — *Auditoría y mejora UX Perfil del protagonista* —
había advertido explícitamente que el working tree mezclaba sus cambios con trabajo
pendiente de Codex en los mismos archivos, y que **no había que commitear todo junto
a ciegas**. El respaldo se hizo antes de recibir esa advertencia, así que el commit
`af7eac3` sí quedó mezclado.

El respaldo en sí es correcto y no se revierte: sin él, 25 archivos seguirían sin
copia fuera del disco de Luis. Lo que hay que separar es **qué se despliega**.

## Los 5 archivos que NO pertenecen a la entrega del Perfil del Protagonista

No los despliegues con esa entrega. Son de Codex y **nadie los revisó ni aprobó**
en la sesión que los tocó:

| Archivo | Motivo |
|---|---|
| `public/assets/invitation.css` | La sesión lo excluyó a propósito: sus cambios quedaron revertidos (sin diferencia neta) y encima hay trabajo pendiente de Codex del intro cinematográfico. |
| `public/themes/carreras/invitation/intro-invitacion-wow-v1.mp4` | Intro de Codex, no de esa sesión. |
| `public/themes/carreras/invitation/intro-invitacion-wow-v1-poster.jpg` | Ídem. |
| `public/themes/hielo/invitation/intro-invitacion-wow-v1.mp4` | Ídem. |
| `public/themes/hielo/invitation/intro-invitacion-wow-v1-poster.jpg` | Ídem. |

Antes de desplegarlos, que Codex confirme ese bloque por separado.

## Los 20 que sí son la entrega del Perfil del Protagonista

Migración `009_invitation_gender` (up y down) · `public/invitacion.php` ·
`public/lib.invitations.php` · `public/admin/invitations.php` ·
`public/assets/invitation.js` · los cuatro audios de Alice
(`narracion-final.mp3` y sus variantes niño/niña, más `narracion-playlist-final.mp3`) ·
el renombrado `saludo-rayo-mcqueen-v3.mp3` → `saludo-rayo-mcqueen.mp3` ·
y la documentación (`CLAUDE-HANDOFF-PERFIL-PROTAGONISTA-2026-08-12.md`,
`CAMPANA-INVITACIONES-*`, `OPENCODE-HANDOFF-POSTPRODUCCION-REEL-*`,
`HIGGSFIELD-PROMPTS-*`, `ARQUITECTURA.md`, `INVITACION-*`, `MANUAL-DE-MARCA.md`).

La documentación es interna y **no se sirve**: queda versionada, no se sube por FTP.

## Estado de despliegue

Nada de esta rama está en PROD. Comprobado desde fuera el 2026-08-18:
`https://automatizatech.cl/cumpleclick/invitacion.php` responde **400**, no 404 —
o sea el archivo existe en PROD pero con el código anterior; los cambios de esta
rama no están arriba. No hay forma de verificar desde fuera qué versión corre, así
que vale la palabra de la sesión que lo produjo: **no desplegado**.

## Orden de despliegue cuando se autorice

1. Subir la migración `009_invitation_gender.php` a la ruta privada de
   `database/migrations/`, fuera de `public_html`.
2. Correr `php scripts/migrate.php` por SSH **antes** de subir cualquier PHP.
   Agrega `birthday_person_gender` a `cc_invitations`; es aditiva. Si se sube el
   PHP sin correrla, crear o editar una invitación tira error fatal de SQL porque
   el `INSERT` ya nombra esa columna.
3. `lib.invitations.php` primero — los otros dos PHP lo requieren.
4. `invitacion.php` y `admin/invitations.php`.
5. `assets/invitation.js` y los audios.
6. Borrar de PROD `themes/carreras/narracion-video/saludo-rayo-mcqueen-v3.mp3`,
   que quedó huérfano por el renombrado.

No hay nada opcional: o va el bloque completo, o la narración por género queda a
medias.
