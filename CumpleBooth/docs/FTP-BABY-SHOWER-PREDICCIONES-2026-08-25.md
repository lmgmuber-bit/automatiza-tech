# Manifiesto FTP — Baby Shower y predicciones

**Estado:** preparado y verificado en local. **No desplegado.** No se ejecutó la
migración 010 en producción.

Worktree de origen:
`C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth`

## Orden seguro

1. Respaldar la BD y confirmar el config privado vigente.
2. Subir fuera de `public_html` la migración, su reversa y retención.
3. Con autorización explícita, ejecutar el migrador y comprobar que la 010 quedó
   aplicada. No subir el nuevo PHP público antes de tener la columna
   `cc_invitations.event_type`.
4. Subir primero los helpers PHP, luego las páginas que los consumen.
5. Subir el bundle JS/CSS y al final `index.html`.
6. Probar un cumpleaños existente, un baby shower, el guardado, ambos QR, la
   vista móvil del recuerdito y emisión/revocación del tablero.

## Privado, fuera de `public_html`

| Orden | Ruta local exacta | Destino PROD relativo | Clase |
|---:|---|---|---|
| 1 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\database\migrations\010_baby_shower_predictions.php` | `database/migrations/010_baby_shower_predictions.php` | OBLIGATORIO |
| 2 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\database\migrations\010_baby_shower_predictions.down.php` | `database/migrations/010_baby_shower_predictions.down.php` | OPCIONAL; sólo rollback manual autorizado |
| 3 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\scripts\retention.php` | `scripts/retention.php` | OBLIGATORIO; conserva dry-run por defecto |

## Público, desde `dist/`

| Orden | Ruta local exacta | Destino PROD relativo | Clase |
|---:|---|---|---|
| 4 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\dist\lib.predictions.php` | `/public_html/cumpleclick/lib.predictions.php` | OBLIGATORIO; antes que `lib.php` |
| 5 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\dist\lib.invitations.php` | `/public_html/cumpleclick/lib.invitations.php` | OBLIGATORIO |
| 6 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\dist\lib.php` | `/public_html/cumpleclick/lib.php` | OBLIGATORIO; carga el helper nuevo |
| 7 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\dist\prediction-api.php` | `/public_html/cumpleclick/prediction-api.php` | OBLIGATORIO |
| 8 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\dist\predicciones.php` | `/public_html/cumpleclick/predicciones.php` | OBLIGATORIO |
| 9 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\dist\ver.php` | `/public_html/cumpleclick/ver.php` | OBLIGATORIO; reconoce `recuerdito-` |
| 10 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\dist\admin\index.php` | `/public_html/cumpleclick/admin/index.php` | OBLIGATORIO |
| 11 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\dist\admin\invitations.php` | `/public_html/cumpleclick/admin/invitations.php` | OBLIGATORIO |
| 12 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\dist\assets\main-DhmcFP52.js` | `/public_html/cumpleclick/assets/main-DhmcFP52.js` | OBLIGATORIO; antes de `index.html` |
| 13 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\dist\assets\main-BIWv8t-H.css` | `/public_html/cumpleclick/assets/main-BIWv8t-H.css` | OBLIGATORIO; antes de `index.html` |
| 14 | `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones\CumpleBooth\dist\index.html` | `/public_html/cumpleclick/index.html` | OBLIGATORIO; subir último |

Los nombres hash de `assets/` corresponden al build local del 2026-08-25. Si se
vuelve a compilar, regenerar esta tabla antes de subir.

## No subir

- `storage/`, `qa-output/`, SQLite local, fotos de QA, tokens de prueba, logs,
  dumps o backups.
- `src/`, `tests/`, `node_modules/`, `graphify-out/` y documentación al webroot.
- Config real, secretos, cookies, tokens OAuth o credenciales.
- La migración `.down.php` no se ejecuta como parte del rollout normal.

No borrar bundles antiguos durante la primera subida. Se pueden limpiar después
de comprobar que `index.html` nuevo responde y sólo con autorización expresa.
