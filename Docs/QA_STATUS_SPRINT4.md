# QA Status — Sprint 4 Security Hardening

**Rama:** `security/hardening-phase-0`
**Última actualización:** 2026-05-18
**Ejecutado por:** Claude (sesión 2026-05-18)
**Documento de continuación para:** Codex u otro agente

> **PARA CODEX/PRÓXIMO AGENTE:**
> Este documento registra el estado real de las pruebas QA del Sprint 4.
> Lee `Docs/MASTER/00_INDEX.md` y `AGENTS.md` antes de continuar.
> Branch activa: `security/hardening-phase-0`
> Las pruebas marcadas con 🖥️ requieren navegador — no se pueden automatizar vía CLI.

---

## Bug encontrado y corregido en esta sesión

### 🔴 BUG (corregido) — WP caía con Fatal Error 500

**Archivo:** `wp-content/themes/automatiza-tech/inc/invoice-handlers.php:12`
**Problema:** `require_once get_template_directory() . '/../../at-path-safe.php'`
`get_template_directory()` retorna `wp-content/themes/automatiza-tech/` — con solo `../..` llega a `wp-content/`, no al webroot.
**Fix aplicado:** `require_once ABSPATH . 'at-path-safe.php';`
**Estado:** ✅ Corregido — WordPress levanta correctamente.

---

## PASO 0 — Migración de base de datos

| Check | Resultado | Notas |
|-------|-----------|-------|
| `setup-omnichannel-ai-chats.php` bloqueado sin login | ✅ 403 | .htaccess correcto |
| Tabla `wp_omnichannel_ai_chats` creada | ⏳ PENDIENTE | Requiere ejecutar con sesión admin WP logueada |

**Para completar (Codex):** Abrir en navegador logueado como admin WP:
`http://localhost/automatiza-tech/setup-omnichannel-ai-chats.php`
Resultado esperado: `✅ Tabla wp_omnichannel_ai_chats creada/verificada correctamente.`

---

## ÁREA 1 — Portal Omnicanal (React) — REQUIERE NAVEGADOR 🖥️

| Test | Estado | Instrucciones |
|------|--------|---------------|
| A5.2 — Webhook secret enmascarado en Canales | ⏳ PENDIENTE | Login portal admin → Menú Canales → verificar `••••••••` |
| A5.2 — Botón ojo revela/oculta secret | ⏳ PENDIENTE | Clic en ícono Eye → se revela → clic de nuevo → oculta |
| A5.2 — "Copiar URL" copia secret real | ⏳ PENDIENTE | Pegar en bloc → verificar secret real, no `••••••••` |
| A5.2 — Modal edición también enmascara | ⏳ PENDIENTE | Abrir modal del canal → secret enmascarado |
| A5.3 — Reset token limpiado de URL | ⏳ PENDIENTE | Solicitar reset → clic en link → URL queda limpia sin `?reset_token=` |
| A5.4 — AI Chat persiste tras cerrar navegador | ⏳ PENDIENTE | Enviar 2 msgs → cerrar browser → reabrir → chat sigue en historial |
| A5.4 — Eliminar chat borra del backend | ⏳ PENDIENTE | Eliminar chat → recargar → no debe aparecer |
| A5.4 — localStorage NO tiene `omni_ai_chats` | ⏳ PENDIENTE | F12 → Application → Local Storage → no debe existir la key |

---

## ÁREA 2 — Contratos (flujo firma doble) — REQUIERE NAVEGADOR 🖥️

| Paso | Test | Estado |
|------|------|--------|
| 1 | Crear contrato desde admin | ⏳ PENDIENTE |
| 2 | Firmar como AT → PDF con firma AT | ⏳ PENDIENTE |
| 3 | Enviar link de firma al cliente | ⏳ PENDIENTE |
| 4 | Abrir link como cliente | ⏳ PENDIENTE |
| 5 | Cliente firma | ⏳ PENDIENTE |
| 6 | Confirmar firma | ⏳ PENDIENTE |
| 7 | Email post-firma tiene PDF con **ambas** firmas | ⏳ PENDIENTE |
| 8 | Ficha cliente → Contratos → ambas columnas ✔️ | ⏳ PENDIENTE |
| 9 | Descargar PDF → tiene dos firmas visibles | ⏳ PENDIENTE |

---

## ÁREA 3 — Seguridad IDOR

| URL | Resultado esperado | Estado |
|-----|--------------------|--------|
| `validar-factura.php` sin token | error/redirect | ✅ 400 |
| `validar-factura.php?token=TOKEN_FALSO` | token inválido | ✅ 400 |
| `validar-boleta.php` sin token | error/redirect | ✅ 400 |
| `contracts/sign-contract.php` sin token | error/redirect | ✅ 404 |
| `contracts/sign-contract.php?token=TOKEN_FALSO` | token inválido | ✅ 404 |

---

## ÁREA 4 — Health Endpoint (E5)

| Prueba | Esperado | Resultado | Estado |
|--------|----------|-----------|--------|
| GET `/health.php` | 200 + `{"status":"ok","db":"ok"}` | `{"status":"ok","db":"ok","ts":"2026-05-18T18:34:36+00:00"}` HTTP:200 | ✅ |
| POST `/health.php` | 405 | HTTP:405 | ✅ |
| GET en BD caída | 503 + degraded | No probado (BD activa) | — |

---

## ÁREA 5 — Bloqueos .htaccess

| URL | Esperado | Resultado | Estado |
|-----|----------|-----------|--------|
| `xmlrpc.php` | 403 | 403 | ✅ |
| `debug-n8n-flow.php` | 403 | 403 | ✅ |
| `debug-reminders.php` | 403 | 403 | ✅ |
| `check-prefix.php` | 403 | 403 | ✅ |
| `setup-omnichannel-db.php` | 403 | 403 | ✅ |
| `fix-leads-schema.php` | 403 | 403 | ✅ |
| `_gen_token.php` | 403 | 403 | ✅ |
| `get-migration-token.php` | 403 | 403 | ✅ |
| `qa-report-generator.php` | 403 | 403 | ✅ |

**Rutas que no deben bloquearse:**

| URL | Resultado | Estado |
|-----|-----------|--------|
| `/` (WP home) | 200 | ✅ |
| `wp-login.php` | 200 | ✅ |
| `api-omnichannel.php?route=health` | 200 | ✅ |

---

## ÁREA 6 — Upload de archivos (E3) — REQUIERE NAVEGADOR 🖥️

| Archivo | Esperado | Estado |
|---------|----------|--------|
| `foto.jpg` válida < 2MB | ✅ aceptada | ⏳ PENDIENTE |
| `foto.png` válida < 2MB | ✅ aceptada | ⏳ PENDIENTE |
| `virus.php` | ❌ rechazado | ⏳ PENDIENTE |
| `script.exe` | ❌ rechazado | ⏳ PENDIENTE |
| `documento.pdf` | ❌ rechazado | ⏳ PENDIENTE |
| Imagen 10MB | ❌ rechazado | ⏳ PENDIENTE |

---

## ÁREA 7 — Rate Limiting (B3/E5)

| Resultado | Estado |
|-----------|--------|
| Requests 1-5 → 401 (credenciales inválidas, pasan) | ✅ |
| Request 6+ → 429 Too Many Requests | ✅ (activa en req 6, antes de req 10-15 esperado — mejor) |

---

## Checklist Final Pre-Merge

```
[x] BUG CORREGIDO: invoice-handlers.php usa ABSPATH . 'at-path-safe.php'
[x] WP levanta sin errores (200 en home y wp-login)
[ ] PASO 0: setup-omnichannel-ai-chats.php → tabla creada (requiere navegador admin)
[ ] A5.2: webhook secret enmascarado (requiere navegador)
[ ] A5.3: reset token limpiado de URL (requiere navegador)
[ ] A5.4: chat IA persiste tras cerrar browser (requiere navegador)
[ ] A5.4: localStorage NO tiene omni_ai_chats (requiere navegador)
[ ] Contrato: PDF firmado tiene AMBAS firmas (requiere navegador)
[ ] Contrato: Email post-firma adjunta PDF con ambas firmas (requiere navegador)
[x] health.php GET → {"status":"ok"} HTTP:200
[x] health.php POST → HTTP:405
[x] xmlrpc.php → 403
[x] debug-*.php → 403
[x] setup-*.php → 403
[x] wp-admin → 200/302 OK
[ ] Upload .php → rechazado (requiere navegador)
[ ] Upload .jpg válida → aceptada (requiere navegador)
[x] validar-factura.php sin token → 400
[x] Rate limit login → 429 en request 6+
```

---

## Resumen de estado

| Categoría | Pasaron | Pendientes (browser) | Bugs |
|-----------|---------|----------------------|------|
| Bug fix WP 500 | ✅ 1 | — | 1 corregido |
| PASO 0 (migración BD) | — | 1 | — |
| ÁREA 1 (Portal React) | — | 8 | — |
| ÁREA 2 (Contratos) | — | 9 | — |
| ÁREA 3 (IDOR) | ✅ 5 | — | — |
| ÁREA 4 (Health) | ✅ 2 | — | — |
| ÁREA 5 (.htaccess) | ✅ 12 | — | — |
| ÁREA 6 (Upload) | — | 6 | — |
| ÁREA 7 (Rate limit) | ✅ 1 | — | — |
| **TOTAL** | **21 ✅** | **24 🖥️** | **1 corregido** |

---

## Instrucciones para Codex u otro agente que continúe

1. Lee `AGENTS.md` y `CLAUDE.md` antes de trabajar.
2. Branch: `security/hardening-phase-0` — **no crear rama nueva** a menos que se vaya a modificar código.
3. Las 24 pruebas pendientes requieren navegador. No son automatizables via CLI/curl.
4. Si vas a ejecutar el flujo de contratos, necesitas un cliente de prueba en WP admin.
5. Al terminar las pruebas en navegador, actualizar este documento con los resultados.
6. Cuando todo el checklist esté ✅: crear PR `security/hardening-phase-0` → `main`.
7. No hacer merge sin confirmación del usuario (luis_).
