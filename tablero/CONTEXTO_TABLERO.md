# CONTEXTO DEL TABLERO ÁGIL — AutomatizaTech v8
## Handoff para Codex (orquestador) y cualquier agente que tome el relevo

> Última actualización: 2026-07-10 por OpenCode Go (GLM-5.2) bajo handoff `AT-TABLERO-ARCH-001`.
> Versión actual: **v8** — sync automática local↔PROD via API.

---

## 1. QUÉ ES

Tablero Kanban standalone (1 HTML + 3 logos + seeds locales como fallback) para uso interno. Dos vistas + métricas:

1. **📋 Clientes · Método AT** — entregas al cliente (6 pasos: 01 Diagnóstico → 06 Soporte).
2. **⚙️ Internas · Equipo multi-agente** — tareas internas (Luis / OpenCode Go / Claude / Codex).
3. **📊 Métricas** — KPIs, distribución por etapa/estado/asignatario, throughput, donut CSS.

---

## 2. ARQUITECTURA v8 — Sync automática via API

```
Local (WAMP)                                   PROD (Hostinger)
  tablero/index.html                             tablero/index.html
       │                                              │
       ▼                                              ▼
  fetch https://automatizatech.cl/api-tablero.php (cross-origin, con Bearer token)
                      │
                      ▼
              api-tablero.php (solo en PROD)
              GET: lee BD / POST: escribe BD
                      │
                      ▼
              MySQL Hostinger (única fuente de verdad)
              wp_omnichannel_at_board (clientes)
              wp_omnichannel_at_internas (tareas internas)
              + JOINs a leads/propuestas/tech_clients existentes
```

- **Editar local** → POST a API → BD Hostinger → PROD ve el cambio al refrescar.
- **Editar en PROD** → POST a API → BD Hostinger → local ve el cambio al sync.
- **Sin token o sin internet** → usa la última caché o ejemplos anonimizados en modo de solo lectura.
- **Token** guardado únicamente en `sessionStorage`; se elimina al cerrar la sesión del navegador y nunca viaja en la URL.

### Endpoints
- `GET /api-tablero.php` — devuelve `{ ok, data: { version, clientes[], internas[] } }`.
- `POST /api-tablero.php` con body `{ at_id, tipo: "cli"|"int", ...campos }` — upsert.
- Auth: `Authorization: Bearer <AT_BOARD_TOKEN>` y fallback seguro `X-AT-Board-Token` para Hostinger/PHP-CGI.
- No se acepta `?token=`: evita filtración en historial, logs y proxies.
- CORS: permite localhost, 127.0.0.1 y automatizatech.cl.

### Tablas nuevas (NO toca las existentes)
- `wp_omnichannel_at_board` — clientes del tablero, con FKs opcionales a `automatiza_leads.id`, `automatiza_propuestas.id`, `automatiza_tech_clients.id`, `omnichannel_clients.id`.
- `wp_omnichannel_at_internas` — tareas internas (AT-INT-XXX).

---

## 3. ARCHIVOS

| Archivo | Dónde | Versionado |
|---|---|---|
| `tablero/index.html` | local + PROD | sí |
| `tablero/logo-at.svg` / `logo-at-light.svg` / `logo-at.png` | local + PROD | sí |
| `tablero/.gitignore` | excluye seeds reales + .htpasswd | sí |
| `tablero/.htaccess.example` | auth + headers + CSP | sí |
| `tablero/LEEME.txt` | instrucciones | sí |
| `tablero/CONTEXTO_TABLERO.md` | este doc | sí |
| `tablero/data/seed-*.example.json` | ejemplos anonimizados | sí |
| `tablero/data/seed-*.json` | datos reales (fallback offline) | **NO** |
| `setup-at-board.php` | raíz AT (ejecutar 1 vez en PROD) | sí |
| `api-tablero.php` | raíz AT (endpoint) | sí |

---

## 4. CÓMO PONERLO EN PROD (pasos para Luis)

1. Subir `setup-at-board.php` + `api-tablero.php` a la raíz del hosting (junto a wp-load.php).
2. Abrir `https://automatizatech.cl/setup-at-board.php` (logueado como admin WP) — crea las 2 tablas + migra 6 clientes + 14 internas + genera token.
3. Copiar el token sugerido y pegarlo en `wp-config-secrets.php` como `define('AT_BOARD_TOKEN', '...');` (si no lo agregó solo).
4. Subir `tablero/index.html` + 3 logos + `.htaccess` (de example) + `.htpasswd` (fuera public_html) a `automatizatech.cl/tablero/`.
5. Abrir `automatizatech.cl/tablero/` → botón 🔑 Token → pegar el token → Probar conexión.
6. Abrir local `http://127.0.0.1/automatiza-tech/tablero/` → 🔑 Token → mismo token → 🔄 Sync.
7. Editar en cualquiera de los dos → aparece en el otro al sync/refrescar.

---

## 5. SEGURIDAD v8

- Escapes `esc()` / `escAttr()` en todas las interpolaciones DOM.
- API: Bearer + `X-AT-Board-Token`, CORS restringido, validación de inputs y errores SQL no expuestos.
- Token en `sessionStorage`, no en URL, código fuente ni localStorage persistente.
- CSP permite conexión solo a `self`, `automatizatech.cl` y `www.automatizatech.cl`.
- `.htpasswd` fuera public_html, sin generadores web.
- CSP, noindex, no-store, X-Frame-Options DENY.
- `api-tablero.php` usa `$wpdb->prepare()` (SQL parametrizado) y `hash_equals()` (timing-safe).

---

## 6. ESTADO

- v8 codeada y validada (JS OK, PHP OK).
- **No deploy** hasta aprobación de Luis.
- Validado por Codex en WAMP: preflight, GET, POST cliente/interna, mapeo, rechazo de query token, edición, drag y modo offline de solo lectura.
- Pendiente con autorización de Luis: subir los archivos corregidos a PROD, ejecutar smoke test y eliminar `setup-at-board.php` del hosting.

---

*Documento vivo. v8 = sync real local↔PROD via API.*
