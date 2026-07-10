# AT-TAB-003 — Fuente de verdad: WordPress + GitHub Issues

> Estado: **DISEÑO DE EVOLUCIÓN**. v8 ya usa tablas WordPress operativas propias; la integración directa con clientes canónicos, GitHub Issues y lease sigue pendiente de aprobación y credenciales.

## Implementación transitoria v8

`api-tablero.php` usa `wp_omnichannel_at_board` y `wp_omnichannel_at_internas` como fuente operativa compartida entre WAMP y PROD. El navegador conserva únicamente una caché de lectura y bloquea edición/drag cuando no tiene conexión autenticada.

Este cierre resuelve la sincronización real sin exponer tokens: usa `Authorization` más `X-AT-Board-Token` para compatibilidad con PHP-CGI, nunca query strings. No reemplaza el objetivo de este documento: conectar en una iteración posterior los clientes canónicos, GitHub Issues y el lease, evitando duplicidad permanente.
> Tablero v7.1 sigue con seeds locales como fuente interina.

## Objetivo

Reemplazar `data/seed-*.json` (estáticos, editados a mano) por fuentes compartidas:
- **Clientes**: desde WordPress/OmniCliente (BD `wp_omnichannel_*` ya existente).
- **Tareas internas**: desde GitHub Issues/PRs del repo `lmgmuber-bit/automatiza-tech`.
- **Tablero**: vista agregada que consume ambas.
- **Google Sheets**: adaptador opcional, solo si Luis quiere editar manualmente desde celu.
- **Secretos administrativos**: NUNCA en el navegador.

## Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│  Navegador de Luis (tablero index.html)                      │
│  - solo lee, no tiene tokens                                 │
│  - fetch GET /api-tablero.php  (mismo origen que el sitio)   │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTPS, con cookie de sesión WP
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  WordPress (automatizatech.cl)                               │
│  api-tablero.php                                             │
│  - chequea auth WP (current_user_can)                        │
│  - lee clientes de wp_omnichannel_*                          │
│  - proxya a GitHub Issues con token del server               │
│  - devuelve JSON { clientes: [...], internas: [...] }        │
└────────┬───────────────────────────────┬────────────────────┘
         │                               │
         ▼                               ▼
   wp_omnichannel_*              GitHub API REST
   (MySQL local)                 (con token finamente scoped,
                                  read-only, del repo)
```

### Por qué un proxy WP y no GitHub directo desde el navegador
Un token de GitHub en JS es público. Cualquiera lo extrae y tiene acceso al repo. El token debe vivir en el server (en `wp-config-secrets.php`, que ya existe y está fuera del repo). El navegador solo habla con `api-tablero.php` que internamente usa el token.

## Endpoint: GET /api-tablero.php

### Query params
- `vista=clientes|internas|ambas` (default: ambas)
- `cache=60` (TTL en segundos para respuesta; default 60)

### Respuesta 200
```json
{
  "ok": true,
  "version": "v7.1",
  "generadoEn": "2026-07-10T12:00:00Z",
  "clientes": [
    {
      "id": "AT-CLI-001",
      "nombre": "KellsCapilar",
      "contacto": "Kellys Tirado",
      "rubro": "Estética capilar",
      "servicios": ["Chatbot IA WhatsApp", "Portal OmniCliente"],
      "paso": 6,
      "prioridad": "P3",
      "estado": "done",
      "estadoLabel": "Soporte activo",
      "ultima": "2026-06-12",
      "notas": "..."
    }
  ],
  "internas": [
    {
      "id": "AT-INT-001",
      "titulo": "...",
      "asignadoA": "Luis",
      "tipo": "ops",
      "estado": "progress",
      "prioridad": "P1",
      "ultima": "2026-07-10",
      "notas": "..."
    }
  ]
}
```

### Mapeo wp_omnichannel_* → cliente tablero
La tabla `wp_omnichannel_clients` (ver `setup-omnichannel-db.php` en el repo AT) ya tiene clientes. Faltan campos del método AT (paso 1-6, prioridad, estadoLabel, ultima, notas). Dos opciones:

**Opción A (recomendada)**: agregar columnas a la tabla existente:
```sql
ALTER TABLE wp_omnichannel_clients
  ADD COLUMN at_paso TINYINT DEFAULT 1,
  ADD COLUMN at_prioridad VARCHAR(3) DEFAULT 'P2',
  ADD COLUMN at_estado VARCHAR(12) DEFAULT 'progress',
  ADD COLUMN at_estado_label VARCHAR(60) DEFAULT '',
  ADD COLUMN at_ultima DATE DEFAULT NULL,
  ADD COLUMN at_notas TEXT;
```
El endpoint lee esas columnas. Edición desde el tablero → POST a `api-tablero.php` que las actualice (con auth).

**Opción B**: tabla nueva `wp_omnichannel_at_board` con FK a `wp_omnichannel_clients.id`. Más limpio si no querés tocar la tabla existente.

### Mapeo GitHub Issues → tarea interna tablero
Labels del repo definen los campos:
- `asig:luis`, `asig:opencode-go`, `asig:claude`, `asig:codex` → asignadoA
- `tipo:dev`, `tipo:design`, `tipo:ops`, `tipo:research`, `tipo:docs` → tipo
- `estado:backlog`, `estado:todo`, `estado:progress`, `estado:review`, `estado:wait`, `estado:blocked` → estado (o usar project board de GitHub)
- `P0`/`P1`/`P2`/`P3` → prioridad
- `AT-INT-XXX` en el título o un label `tablero-id:AT-INT-XXX` → id
- `ultima` = updated_at del issue
- `notas` = body del issue + último comment

El endpoint proxya `GET /repos/lmgmuber-bit/automatiza-tech/issues?state=open&labels=tablero` con el token del server, mapea y devuelve.

## Google Sheets como adaptador opcional

Solo si Luis quiere editar desde celu sin tocar WP/GitHub:
- Google Sheet privado con columns = campos del cliente/tarea.
- Apps Script publica `GET /exec` que devuelve JSON (con un secret compartido en URL, NO token admin).
- El tablero puede configurarse para consumir esa URL además de WP.
- Es opcional y no reemplaza WP/GitHub — es un tercer adaptador.

## Cambios necesarios para implementar (próxima iteración)

1. **PHP**: crear `api-tablero.php` en `C:\wamp64\www\automatiza-tech\` (no en `tablero/`). Reusa `at-cors.php`, `at-rate-limit.php`, `at-ownership.php` que ya existen.
2. **SQL**: migración `setup-at-board.php` con las columnas de Opción A.
3. **Token GitHub**: crear fine-grained PAT read-only scoped al repo, guardarlo en `wp-config-secrets.php` como `AT_GITHUB_TOKEN`. **NUNCA** en el tablero JS.
4. **JS**: en `index.html`, reemplazar `cargarSeeds()` (que hoy hace fetch a `data/seed-*.json`) por fetch a `/api-tablero.php`. Mantener `data/seed-*.json` como fallback offline.
5. **Auth del endpoint**: validar sesión WP (`is_user_logged_in() && current_user_can('edit_posts')` o rol custom `at_board`). Luis ya está logueado en WP cuando usa el tablero → cookie válida.
6. **CSP**: `connect-src 'self'` ya permite el endpoint mismo origen. Sin cambios.

## Riesgos y mitigaciones

- **Token GitHub filtrado**: si el server se compromete, el token se expone. Mitigación: token fine-grained read-only, scoped solo a issues del repo, rotación cada 90 días.
- **Rate limit GitHub**: 5000 req/hora con token. El endpoint cachea 60s → max ~60 req/hora. OK.
- **Editar desde el tablero**: v7.1 solo lee. Para escribir (POST), hace falta otro endpoint + validación + auth. Fuera de scope de v7.1.
- **Datos sensibles en GitHub Issues**: si las notas internas tienen info comercial (precios, nombres), NO meterlas en issues públicos. El repo `lmgmuber-bit/automatiza-tech` es privado, pero igual filtrar lo sensible. Mitigación: las notas comerciales van en WP, no en GitHub.

## Criterio de cierre de este diseño

- [ ] Luis aprueba Opción A (alter table) vs Opción B (tabla nueva).
- [ ] Luis genera token GitHub fine-grained y lo carga en `wp-config-secrets.php`.
- [ ] Luis aprueba el schema de labels del repo (`asig:*`, `tipo:*`, `estado:*`, `P0-P3`).
- [ ] Implementar `api-tablero.php` + `setup-at-board.php` en branch aparte.
- [ ] PR con diff, pruebas locales (WAMP), y review de seguridad (token no expuesto, auth WP, SQL parametrizado).
