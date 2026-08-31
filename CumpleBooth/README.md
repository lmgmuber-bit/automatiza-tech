# CumpleClick by AutomatizaTech

Servicio de photo booth infantil. El nombre técnico del proyecto sigue siendo
`CumpleBooth`; la marca y ruta pública son **CumpleClick by AutomatizaTech** y
`https://automatizatech.cl/cumpleclick/`.

El kiosco recorre: intro → invitado → ruleta → personaje → cámara → composición
con foto cuadrada alineada al marco y personaje centrado en la pista → subida/QR
→ diploma. React/Vite dibuja los PNG
en canvas; PHP 8+ atiende API, admin, galería y uploads; MySQL guarda fiestas,
invitados, fotos, leads comerciales y rate limits en una BD independiente de
WordPress.

## Requisitos

- PHP 8.0 mínimo; baseline 8.2. Validado en 8.0, 8.2, 8.3 y 8.4.
- MySQL 8+ o compatible, InnoDB y `utf8mb4`.
- Node.js + npm solo para compilar el frontend.
- Apache con `mod_rewrite` y `mod_headers`; HTTPS obligatorio en producción.

## Desarrollo local

```powershell
npm install
php scripts/bootstrap.php
php scripts/migrate.php
php scripts/import-json-to-db.php       # dry-run
php scripts/import-json-to-db.php --apply
php scripts/parity-check.php
npm test
npm run build
php scripts/check-dist-parity.php
```

En WAMP: `http://localhost/automatiza-tech/CumpleBooth/dist/?p=demo` y
`http://localhost/automatiza-tech/CumpleBooth/dist/admin/`.

Sitio comercial local:
`http://localhost/automatiza-tech/CumpleBooth/sitio/`. Su formulario envía JSON a
`sitio/api/contacto.php`, guarda la solicitud en `cc_leads` y muestra al usuario
una referencia opaca `CC-...`. Ejecuta `php scripts/migrate.php --apply` para
aplicar la migración `006_public_leads` antes de probarlo.

La configuración real no se versiona. `CUMPLECLICK_CONFIG_FILE` puede apuntar a
un PHP externo; `config/cumpleclick.example.php` solo documenta el formato. Las
rutas `photo_dir` y `state_dir` deben quedar fuera de `DOCUMENT_ROOT` o la app
falla de forma cerrada.

## Fuentes de verdad

- `src/`: kiosco React.
- `public/`: PHP, catálogo `themes.json` y assets versionados.
- MySQL: estado mutable (`cc_parties`, `cc_guests`, `cc_photos`,
  `cc_rate_limits`, `cc_theme_prompts`, `cc_leads`,
  `cc_schema_migrations`).
- `dist/`: artefacto generado; nunca editarlo a mano.
- `docs/CUMPLECLICK-HANDOFF-CODEX.md`: continuidad y regla obligatoria de
  camuflaje para cualquier prompt futuro de imágenes.

La cámara confirma fotogramas reales antes de habilitar la captura y ofrece un
selector si Chrome detecta más de un dispositivo. En Admin → Temáticas, cada card
abre un estudio manual con assets, metadatos, prompts privados versionados de
imagen/video y carga directa al slot exacto. Para completar solo prompts que aún
falten: `php scripts/backfill-theme-production-prompts.php` (dry-run) y después
`php scripts/backfill-theme-production-prompts.php --apply`.
Para normalizar prompts históricos conservando su contenido:
`php scripts/backfill-theme-production-prompts.php --normalize-disclaimer`
(dry-run) y después agregar `--apply`.

Demos locales:

- `?p=demo-tropical` — temática Tropical con multimedia instalada.
- `?p=demo-kpop` — preproducción; completar inventario desde Admin.
- `?p=demo-heroes` — preproducción; completar inventario desde Admin.

Las tres se crean de forma idempotente con
`php scripts/seed-demo-theme-parties.php --apply`. K-Pop y Héroes no quedan
visualmente aprobadas hasta adjuntar sus assets y calibrar `frameBox`.

No subir a Git/FTP configuración real, credenciales, backups, dumps, fotos,
`storage/`, `node_modules/` ni evidencias de QA.
