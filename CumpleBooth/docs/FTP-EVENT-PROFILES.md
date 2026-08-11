# Handoff FTP — Perfil del protagonista

Estado: preparado localmente. **No desplegado** y **sin migraciones ejecutadas en
producción**.

## Orden seguro

1. Respaldar base de datos y configuración privada.
2. Subir los archivos privados `OBLIGATORIO` fuera de `public_html`.
3. Mantener `event_profile_enabled=false` y ejecutar `008_event_profiles.php`
   una sola vez mediante el migrador, con aprobación explícita.
4. Subir los archivos públicos `OBLIGATORIO` desde `dist/`.
5. Probar admin, invitación sin perfil y una invitación con perfil de ensayo.
6. Recién entonces cambiar el config privado a `event_profile_enabled=true`.

## Privado, fuera del webroot

| Local | Destino relativo PROD | Clase |
|---|---|---|
| `database/migrations/008_event_profiles.php` | `database/migrations/008_event_profiles.php` | OBLIGATORIO |
| `scripts/retention.php` | `scripts/retention.php` | OBLIGATORIO |
| `database/migrations/008_event_profiles.down.php` | `database/migrations/008_event_profiles.down.php` | OPCIONAL; rollback manual, no ejecutar rutinariamente |
| `scripts/normalize-event-profile-video.mjs` | `scripts/normalize-event-profile-video.mjs` | OPCIONAL hasta activar generación real |
| `config/cumpleclick.example.php` | plantilla privada de referencia | OPCIONAL; nunca reemplaza el config real |

En el config real —sin copiar secretos— agregar `event_profile_dir` como ruta
absoluta privada y `event_profile_enabled=false` durante el rollout.

## Público, desde `dist/`

| Local | Destino relativo PROD | Clase |
|---|---|---|
| `dist/lib.php` | `public_html/cumpleclick/lib.php` | OBLIGATORIO |
| `dist/lib.event-profiles.php` | `public_html/cumpleclick/lib.event-profiles.php` | OBLIGATORIO |
| `dist/lib.invitations.php` | `public_html/cumpleclick/lib.invitations.php` | OBLIGATORIO |
| `dist/descargar-invitacion.php` | `public_html/cumpleclick/descargar-invitacion.php` | OBLIGATORIO |
| `dist/invitacion.php` | `public_html/cumpleclick/invitacion.php` | OBLIGATORIO |
| `dist/event-profile-media.php` | `public_html/cumpleclick/event-profile-media.php` | OBLIGATORIO |
| `dist/data/event-profile-presets.json` | `public_html/cumpleclick/data/event-profile-presets.json` | OBLIGATORIO |
| `dist/assets/event-profile.css` | `public_html/cumpleclick/assets/event-profile.css` | OBLIGATORIO |
| `dist/assets/event-profile.js` | `public_html/cumpleclick/assets/event-profile.js` | OBLIGATORIO |
| `dist/assets/invitation.css` | `public_html/cumpleclick/assets/invitation.css` | OBLIGATORIO |
| `dist/assets/invitation.js` | `public_html/cumpleclick/assets/invitation.js` | OBLIGATORIO |
| `dist/admin/event-profile.php` | `public_html/cumpleclick/admin/event-profile.php` | OBLIGATORIO |
| `dist/admin/index.php` | `public_html/cumpleclick/admin/index.php` | OBLIGATORIO |
| `dist/admin/_style.css.php` | `public_html/cumpleclick/admin/_style.css.php` | OBLIGATORIO |

Los cuatro `assets/*` van **antes** que `invitacion.php`: si la página sube
primero, una invitación abierta en ese instante pide hojas de estilo que aún no
existen. `_style.css.php` antes que `admin/event-profile.php`, por lo mismo.

`assets/invitation.css` y `assets/invitation.js` son **archivos nuevos**: no
existen todavía en PROD y sin ellos la invitación queda sin estilos.

Opcional, solo cuando haya videos generados y aprobados:

| Local | Destino relativo PROD | Clase |
|---|---|---|
| `dist/themes/<slug>/invitation/invitation-motion-v1.mp4` | `public_html/cumpleclick/themes/<slug>/invitation/invitation-motion-v1.mp4` | OPCIONAL; el hero cae al fondo estático si falta |

## No subir

- `node_modules/`, `tests/`, `storage/`, bases SQLite, dumps y backups.
- El config local real, credenciales, tokens OAuth, cookies o secretos.
- Fotos o videos de prueba y prompts que contengan datos personales.
- `graphify-out/cache/last_query_stamp`.


## Assets de plantilla por temática (2026-08-09)

Cinco temáticas con imagen base y video de fondo. **Pendientes de aprobación de
Luis** antes de considerarlos definitivos.

| Local | Destino relativo PROD | Clase |
|---|---|---|
| `dist/themes/<slug>/invitation/invitation-base-v1.jpg` | `public_html/cumpleclick/themes/<slug>/invitation/invitation-base-v1.jpg` | OBLIGATORIO si se usa plantilla |
| `dist/themes/<slug>/invitation/invitation-motion-v1.mp4` | `public_html/cumpleclick/themes/<slug>/invitation/invitation-motion-v1.mp4` | OPCIONAL; el hero cae a la imagen si falta |

`<slug>` ∈ `carreras`, `familia-canina`, `tropical`, `hielo`, `kpop`.

Sin la imagen base, esa temática usa la invitación personalizada de siempre: no
se rompe nada, solo se pierde la plantilla reutilizable.

Pesos: bases 150–250 KB (JPG 1080×1920), videos 0,7–1,7 MB (720×1280 h264 sin
audio). El PNG original de cada base pesaba 1,5–6 MB y se descartó.
