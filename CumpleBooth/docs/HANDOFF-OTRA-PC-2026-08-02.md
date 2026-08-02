# CumpleClick — handoff canónico para continuar en otra PC

Fecha de cierre: **2026-08-02**
Proyecto técnico: `CumpleBooth`
Producto: **CumpleClick by AutomatizaTech**
Repositorio: `https://github.com/lmgmuber-bit/automatiza-tech.git`
Rama de continuidad: `codex/cumpleclick-site-frontend-fixes`

Este documento reemplaza como punto de entrada los estados históricos dispersos. Los
handoffs anteriores siguen siendo evidencia de decisiones, pero si contradicen este
archivo prevalece este cierre.

## 1. Estado de entrega

- Todo el trabajo versionable de `CumpleBooth/**` queda incluido en la rama indicada.
- La rama se entrega publicada en `origin`, sin commits locales por empujar y sin
  cambios versionables pendientes dentro de CumpleClick.
- **No se hizo merge ni deploy.** PROD no debe considerarse actualizado.
- Los cambios ajenos existentes en el monorepo raíz se conservaron sin incluirlos en
  este cierre.
- No se versionaron secretos, configuración real, BD, backups, fotos privadas,
  `storage/`, `dist/`, `node_modules/`, `tmp`, capturas de QA ni renders
  intermedios regenerables. Los assets públicos y piezas finales sí se versionan.

## 2. Cómo retomar en la otra PC

```powershell
cd C:\wamp64\www\automatiza-tech
git fetch origin
git switch codex/cumpleclick-site-frontend-fixes
git pull --ff-only
cd CumpleBooth
npm ci
```

Crear fuera del webroot la configuración real siguiendo
`config/cumpleclick.example.php` y definir `CUMPLECLICK_CONFIG_FILE`. Nunca copiar
credenciales al repositorio. Con WAMP verde:

```powershell
C:\wamp64\bin\php\php8.3.14\php.exe scripts\migrate.php --apply
npm test
npm run build
C:\wamp64\bin\php\php8.3.14\php.exe scripts\check-dist-parity.php
```

URLs locales principales:

- Kiosco: `http://localhost/automatiza-tech/CumpleBooth/dist/?p=demo-carreras`
- Admin: `http://localhost/automatiza-tech/CumpleBooth/dist/admin/`
- Sitio comercial: `http://localhost/automatiza-tech/CumpleBooth/sitio/`
- API: `http://localhost/automatiza-tech/CumpleBooth/dist/api.php?p=demo-carreras`

## 3. Arquitectura vigente

- React 18 + Vite 6 para el kiosco; `src/` es la fuente y `dist/` se genera.
- PHP mínimo 8.0, baseline 8.2; PDO y prepared statements.
- MySQL/MariaDB independiente de WordPress para fiestas, invitados, fotos,
  invitaciones, prompts, leads y rate limits.
- Assets públicos versionados en `public/themes/`; fotos de clientes y outputs
  privados fuera del webroot.
- `public/data/themes.json` es el catálogo visual versionado. La BD es la fuente de
  verdad del estado mutable cuando `storage_mode=db`.
- Migraciones vigentes: `001` a `006`, incluida la corrección Gate A de invitaciones
  y `006_public_leads`.

## 4. Producto implementado

Flujo del kiosco: intro → invitado → ruleta → secuencia especial opcional → saludo
del personaje → juegos → cámara → composición → subida/QR → diploma → despedida.
El plan Full añade la misión `concierto3d`; Booth no recibe esa misión.

Mundos con Full configurado y escenario propio:

| Slug | Estrella | Juego propio | Escenario Full |
|---|---|---|---|
| `carreras` | Rayo McQueen | ritmo | `podium-night` |
| `familia-canina` | Bluey | escudo | `backyard-fiesta` |
| `tropical` | Stitch | escudo | `beach-luau` |
| `hielo` | Olaf | armar muñeco | `ice-gala` |
| `kpop` | Rumi | ritmo | `neon-arena` |
| `heroes` | Capitán América | escudo | `rooftop-city` |

Carreras, Familia Canina, Tropical, Hielo y K-Pop cumplen la tabla multimedia A de
`docs/TEMATICA-COMPLETA.md`. Héroes tiene catálogo, retratos, recortes, fondos,
música, juegos y Full; no se marca como temática comercial completa hasta producir
sus seis saludos y despedida. Esto es producción futura de medios, no trabajo Git
sin guardar.

El Admin incluye fiestas, temáticas, calibración, prompts privados versionados,
slots manuales para imágenes/video/audio, invitaciones y plan/galería. Gate A de
invitaciones está cerrado: datos obligatorios al publicar, token opaco y revocable,
outputs aprobados, ownership, uploads inspeccionados y descarga sin IDs internos.

El sitio `sitio/` es responsive, conserva precios/descuentos aprobados y registra el
formulario en `cc_leads` con referencia opaca, consentimiento, honeypot, rate limit e
IP en HMAC. El número oficial de WhatsApp continúa como `[WHATSAPP_NUMBER]` porque
Luis no lo ha definido; la cotización por BD funciona sin WhatsApp.

## 5. Video estrella de Carreras — cierre más reciente

Archivo: `public/themes/carreras/rayo-mcqueen-estrella.mp4`
SHA-256: `50CFACD7C91E6A10E99B06540BC5C2607BE3ACAD5B76106FD9CA445146FF00D4`

- Higgsfield `wan2_7`, job sanitizado
  `9bbc09ac-9ddb-4bd5-bfd7-be2976e60956`.
- Costo real: 7,5 créditos; saldo reportado después: 57,67.
- 720×1280, H.264, yuv420p, 30 fps, 5 s; AAC estéreo 44,1 kHz.
- Solo se ofrece en plan Full, cuando la ruleta elige Rayo McQueen y el invitado
  acepta el Show 3D. Se reproduce inmediatamente antes de `concierto3d`.
- Durante el clip la música del kiosco baja a 0,02; al terminar, pausar u omitir se
  restaura. Si autoplay con sonido falla aparece `Toca para escuchar`.
- No aplica a otro personaje, a plan Booth ni si el invitado elige ir a la foto.

La técnica que sí pasó fue prompt camuflado + modelo alternativo Wan 2.7. No repetir
los intentos documentados como fallidos en
`docs/CODEX-HANDOFF-VIDEO-JUEGO-ESTRELLA.md`. Para cualquier generación futura se
mantiene la regla inquebrantable: describir rasgos físicos, nunca nombres de
franquicia/personaje dentro del prompt generativo.

## 6. Validación del cierre

El cierre debe conservar estos gates verdes:

- `npm test`: **81/81**.
- Build Vite desde cero: correcto.
- Paridad `public → dist`: **283 archivos**.
- Video estrella: audio AAC decodificado en Chrome, `muted=false`, duración 5 s.
- Backend: **125/125** en PHP 8.0.30, 8.2.26, 8.3.14 y 8.4.0.
- Lint: **49 archivos + 8 entrypoints** en esas cuatro versiones.
- Smoke HTTP real contra WAMP/MySQL: **28/28**, con limpieza posterior.
- No usar el PHP 7.4 del `PATH`. PHP 8.4 imprime un aviso por una ruta Xdebug
  local antigua (`E:/...`), pero lint y pruebas terminan en exit 0.
- `git diff --check`: sin errores de whitespace.

Los resultados exactos de la última ejecución quedan también en el mensaje de cierre
de la tarea. Si una prueba cambia en la otra PC, no editar `dist/` a mano: borrar
`dist/`, ejecutar `npm run build` y volver a comprobar paridad.

## 7. Qué queda fuera de este cierre

No hay cambios de código o commits CumpleClick pendientes. Sí hay decisiones o
acciones externas que requieren a Luis y no deben inventarse:

1. número oficial de WhatsApp;
2. URL final del sitio comercial respecto del kiosco;
3. aprobación y ejecución de deploy/FTP;
4. producción audiovisual restante de Héroes y clips estrella adicionales;
5. revisión de licencias de música/propiedad intelectual antes de vender cada mundo.

Estas acciones no autorizan merge, deploy, generación con créditos ni uso de datos
reales por defecto.

## 8. Reglas de continuidad

- Leer primero este archivo, luego `CUMPLECLICK-HANDOFF-CODEX.md`,
  `ARQUITECTURA.md`, `FASE1.md`, `TEMATICA-COMPLETA.md`, `AGENTS.md` y
  `OPENCODE.md`.
- No reordenar prioridades históricas sin avisar a Luis.
- No generar multimedia sin preflight y autorización de gasto cuando corresponda.
- No copiar tokens, outputs personalizados, fotos, invitados, PIN ni galería al
  duplicar una fiesta.
- Tras modificar código: pruebas proporcionales, build único integrado,
  `graphify update .`, paridad y lista FTP exacta.
- Nunca afirmar que algo está en PROD sin evidencia.

## 9. Entrega FTP de este cierre

No se desplegó. Si Luis autoriza PROD, usar `docs/FTP-MANIFEST.md` y subir el build
completo coherente; no mezclar `index.html` con bundles hash de otro build. El video
estrella es obligatorio para la nueva experiencia de Carreras. No subir `src/`,
`tests/`, `docs/`, `design/`, `graphify-out/`, configuración, BD, backups, fotos,
`storage/`, `tmp/` ni evidencias QA.
