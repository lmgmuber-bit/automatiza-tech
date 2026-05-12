# 99 — Audit Report: Documentación previa vs Estado real

> Resultado de la auditoría exhaustiva ejecutada al generar `Docs/MASTER/`.
> **Fecha:** Generado en branch `security/hardening-phase-0`.
> **Método:** 6 sub-agentes Explore en paralelo recorrieron el repo + comparación contra docs preexistentes.

---

## 📊 Resumen ejecutivo

| Categoría | Cantidad | Acción |
|-----------|----------|--------|
| Docs **vigentes** (mantener tal cual) | 5 | Referenciados desde MASTER |
| Docs **parcialmente vigentes** (marcar histórico) | 8 | Mover a `Docs/_archive/` o agregar banner |
| Docs **obsoletos** (candidatos a borrado) | 4 | Eliminar tras revisión |
| Docs **nuevos creados** | 14 | Carpeta `Docs/MASTER/` |
| Diagramas Mermaid agregados | 12+ | En `12_FLUJOS_DE_NEGOCIO.md` y otros |

---

## ✅ Docs preexistentes — Estado

### Vigentes (mantener)

| Archivo | Estado | Notas |
|---------|--------|-------|
| `Docs/INTEGRACION_N8N_PORTAL_OMNICLIENTE.md` | ✅ Vigente | Cubre integración bot v8↔Portal con detalle correcto |
| `Docs/ANALISIS_MODULOS_INC.md` | ✅ Vigente | Buen detalle de módulos `inc/` del theme |
| `Docs/CONTRATO_SOPORTE_POSTPROYECTO.md` | ✅ Plantilla activa | Usada por `contracts/create-contract.php` |
| `Docs/CONTRATO_*.md` (otras plantillas) | ✅ | Plantillas legales vigentes |
| `00_COMIENZA_AQUI.md` (raíz) | ✅ Vigente | Índice general; agregar referencia a `Docs/MASTER/` |

### Parcialmente vigentes (agregar banner "histórico")

| Archivo | Vigencia | Razón |
|---------|----------|-------|
| `Docs/AUTOMATIZATECH_TECNICO.md` (Mar 2026, v2.0) | ⚠️ ~70% | Cubre Portal pero no incluye hardening Phase 0, vault completo, contratos doble firma actualizados |
| `Docs/DOCUMENTO_TECNICO_AUTOMATIZATECH.md` (Feb 2026) | ⚠️ ~50% | Pre-Portal SPA; describe versión anterior del CRM |
| `CONTEXTO_COMPLETO.md` (raíz, 37KB) | ⚠️ ~60% | Tiene buen contexto pero mezcla detalles obsoletos |
| `MANUAL_PROGRAMADOR.md` (raíz, 20KB) | ⚠️ ~65% | Buenas guías pero falta capa omnichannel detallada |
| `MANUAL_CONTEXTO_IA.md` (raíz, 16KB) | ⚠️ ~50% | Útil para contexto IA legacy; reemplazar con MASTER/00_INDEX |
| `Docs/PORTAL_OMNICLIENTE.md` (si existe) | ⚠️ | Verificar; superado por `06_PORTAL_OMNICLIENTE_FRONTEND.md` |
| `Docs/N8N.md` o similares | ⚠️ | Reemplazado por `07_N8N_WORKFLOWS.md` |
| `Docs/CONTRATOS.md` | ⚠️ | Reemplazado por `09_MODULO_CONTRATOS.md` |

**Acción recomendada:** agregar al inicio de cada uno:

```markdown
> ⚠️ **DOCUMENTO HISTÓRICO** — Este documento puede contener información obsoleta.
> La fuente única de verdad actual es [`Docs/MASTER/`](./MASTER/00_INDEX.md).
```

### Candidatos a archivar / eliminar

| Archivo | Razón |
|---------|-------|
| Workflows en `N8N/` raíz (no PROD ni TEMPLATES) | Legacy/sandbox |
| Versiones `WhatsApp_Bot_v1`...`v7` | Reemplazadas por `v8` |
| Carpetas `tema-backup/`, `RespaldoDocs/`, `RespaldoTest/`, `archivos-eliminados-backup/`, `archive/` | Ya bloqueadas por `.htaccess`; mover a backup externo y eliminar del repo |
| Scripts `*.bak`, `*.old`, `*-backup.*` | Limpieza |

---

## 🔍 Hallazgos de la auditoría

### Hallazgos críticos (no bloqueantes)

1. **Migraciones BD sin versionado:** Los `setup-*.php` se ejecutan en `init` sin tabla `db_version`. Riesgo: migraciones idempotentes pero costosas + difícil saber qué se aplicó. → Implementar `wp_automatiza_db_version`.

2. **`omnichannel-controller.php` (222 KB) monolítico:** Difícil de mantener. → Refactor incremental a clases por dominio.

3. **`functions.php` (~100 KB):** mismo problema que el controller. → Extraer más a `inc/`.

4. **Webhooks N8N hardcoded:** URLs como `https://n8n-n8n.kchiba.easypanel.host/webhook/becd5a16-...` hardcoded en código. → Mover a `wp_options` o catálogo `wp_omnichannel_n8n_workflows`.

5. **`omnicliente/` (build) commiteado al repo:** mancha la historia con minified JS. → `.gitignore` + pipeline CI build.

6. **Sin tests automatizados:** ni PHPUnit ni Vitest/Playwright. → Añadir al menos smoke tests para endpoints críticos.

### Hallazgos de seguridad

| Hallazgo | Severidad | Estado |
|----------|-----------|--------|
| HSTS comentado en `.htaccess` | Media | Activar tras 100% HTTPS confirmado |
| MFA en backoffice WP no configurado | Media | Plugin TFA pendiente |
| Algunos `error_log(print_r($_POST))` históricos | Baja | CI los flagea; limpieza incremental |
| Sin WAF externo (Cloudflare) | Media | Phase 1 hardening |
| `setup-contracts-db.php` con clave simple `AT_SETUP_2026` | Baja | Combinado con `at-maintenance-guard` y `.htaccess` es aceptable |
| `XML-RPC` bloqueado | OK | ✅ |

### Hallazgos de operación

- **Sin staging environment** — riesgo en migraciones grandes.
- **Sin pipeline CI/CD de deploy** — manual SFTP/Git pull.
- **Sin monitoring APM** — no hay visibilidad de latencias del bot.
- **Backups N8N** ejecutados nightly pero verificar destino persistente (S3/GDrive).

---

## 🆕 Documentos nuevos creados

Lista completa en `Docs/MASTER/`:

| # | Archivo | Propósito |
|---|---------|-----------|
| 00 | `00_INDEX.md` | Índice maestro |
| 01 | `01_ARQUITECTURA_GENERAL.md` | Stack, topología, ambientes |
| 02 | `02_MAPEO_ARCHIVOS.md` | Inventario archivos/carpetas |
| 03 | `03_BASE_DE_DATOS.md` | 26+ tablas + ER diagram |
| 04 | `04_THEME_WORDPRESS.md` | Theme + módulos `inc/` |
| 05 | `05_API_BACKEND_PHP.md` | API REST/AJAX raíz |
| 06 | `06_PORTAL_OMNICLIENTE_FRONTEND.md` | SPA React |
| 07 | `07_N8N_WORKFLOWS.md` | 63 workflows |
| 08 | `08_INTEGRACIONES_EXTERNAS.md` | OpenAI, Meta, Google, etc. |
| 09 | `09_MODULO_CONTRATOS.md` | Firma electrónica |
| 10 | `10_SEGURIDAD_HARDENING.md` | Helpers + .htaccess + CI |
| 11 | `11_DESPLIEGUE_OPERACIONES.md` | Deploy, cron, backups |
| 12 | `12_FLUJOS_DE_NEGOCIO.md` | Diagramas Mermaid |
| 13 | `13_GLOSARIO_Y_CONVENCIONES.md` | Naming, env vars |
| 99 | `99_AUDIT_REPORT.md` | Este archivo |

---

## 🔎 Comparación directa: docs viejos vs estado real del código

> Ejecutada en Mayo 2026 mediante lectura directa de docs preexistentes + contraste con exploración del código.

### Discrepancias concretas encontradas

| Documento | Campo | Valor en doc | Valor real (código) | Severidad |
|-----------|-------|-------------|---------------------|-----------|
| `CONTEXTO_COMPLETO.md` | Rama activa | `prod-sync-2025-06-26` | `security/hardening-phase-0` | ⚠️ Media |
| `CONTEXTO_COMPLETO.md` | Build Portal | `portal-omnichannel/` | `omnicliente/` | ⚠️ Media |
| `CONTEXTO_COMPLETO.md` | Vite version | `6.4.1` | `6.3.5` | 🔵 Baja |
| `CONTEXTO_COMPLETO.md` | Helpers seguridad | No mencionados | 9 `at-*.php` + 3 mu-plugins | 🔴 Alta |
| `CONTEXTO_COMPLETO.md` | Módulo contratos | No mencionado | `contracts/` con doble firma | 🔴 Alta |
| `CONTEXTO_COMPLETO.md` | N8N workflows | ~40 | ~63 (38 PROD + 14 TEMPLATES + 11 raíz) | ⚠️ Media |
| `Docs/AUTOMATIZATECH_TECNICO.md` | Rama activa | `prod-sync-2025-06-26` | `security/hardening-phase-0` | ⚠️ Media |
| `Docs/AUTOMATIZATECH_TECNICO.md` | Vite version | `6.4.1` | `6.3.5` | 🔵 Baja |
| `Docs/AUTOMATIZATECH_TECNICO.md` | N8N workflows | ~40 | ~63 | ⚠️ Media |
| `Docs/AUTOMATIZATECH_TECNICO.md` | Módulo contratos | No mencionado | `contracts/` activo | 🔴 Alta |
| `Docs/AUTOMATIZATECH_TECNICO.md` | MU-plugins | No mencionados | 3 mu-plugins activos | 🔴 Alta |
| `MANUAL_PROGRAMADOR.md` | Vite version | `6.4` | `6.3.5` | 🔵 Baja |
| `MANUAL_PROGRAMADOR.md` | Build Portal | `portal-omnichannel/` | `omnicliente/` | ⚠️ Media |
| `MANUAL_PROGRAMADOR.md` | At-helpers | No documentados | 9 `at-*.php` | 🔴 Alta |
| `MANUAL_PROGRAMADOR.md` | Módulo contratos | No documentado | Módulo completo activo | 🔴 Alta |
| `00_COMIENZA_AQUI.md` | Rama activa | `prod-sync-2025-06-26` | `security/hardening-phase-0` | ✅ Corregido |
| `00_COMIENZA_AQUI.md` | Build Portal path | `portal-omnichannel/` | `omnicliente/` | ✅ Corregido |
| `00_COMIENZA_AQUI.md` | Link a MASTER | No existía | Agregado | ✅ Corregido |

### Docs encontrados VIGENTES (sin discrepancias)

| Archivo | Razón de vigencia |
|---------|-------------------|
| `Docs/HARDENING_FINAL_REPORT.md` | ✅ Descripción precisa de Phase 0; 25 controles PASS/0 FAIL documentados |
| `Docs/MODULO_FIRMA_CONTRATOS.md` | ✅ Flujo correcto: `at_pending → at_signed → sent → signed`; archivos y endpoints precisos |
| `Docs/HARDENING_HTACCESS_DEPLOY.md` | ✅ Operativo: instrucciones de despliegue en Hostinger |
| `Docs/BACKUPS_CIFRADOS.md` | ✅ Script `mysqldump + tar + age + B2` documentado |
| `Docs/INTEGRACION_N8N_PORTAL_OMNICLIENTE.md` | ✅ Cubre integración bot v8 ↔ Portal correctamente |

### Info de `HARDENING_FINAL_REPORT.md` que faltaba en MASTER

> Añadida a `10_SEGURIDAD_HARDENING.md` durante la comparación:

- `wp-content/mu-plugins/at-security-headers.php` (CSP Report-Only, cookies Secure/HttpOnly/SameSite, bloqueo enumeración usuarios)
- `wp-content/mu-plugins/at-login-hardening.php` (5 fallos/15min → bloqueo IP)
- `wp-content/mu-plugins/at-security-monitor.php` (rolling 200 eventos, alertas email por umbrales)
- `wp-content/uploads/.htaccess` (deny PHP exec en uploads, versionado)
- `wp-config-secrets.example.php` (plantilla separación de secretos)

---

## ✅ Acciones recomendadas (post-auditoría)

### Completadas ✅

1. ✅ Crear `Docs/MASTER/` (14 documentos modulares).
2. ✅ Agregar banners "documento histórico" en: `CONTEXTO_COMPLETO.md`, `MANUAL_PROGRAMADOR.md`, `MANUAL_CONTEXTO_IA.md`, `Docs/AUTOMATIZATECH_TECNICO.md`, `Docs/DOCUMENTO_TECNICO_AUTOMATIZATECH.md`.
3. ✅ Actualizar `00_COMIENZA_AQUI.md` con link a `Docs/MASTER/`, rama actual correcta, build path correcto.
4. ✅ Actualizar `10_SEGURIDAD_HARDENING.md` con mu-plugins faltantes del HARDENING_FINAL_REPORT.

### Pendientes
4. **Pendiente:** Limpiar workflows N8N legacy (raíz `N8N/`) consolidando en `PROD/` o `TEMPLATES/`.
5. **Pendiente:** Crear `.gitignore` para `omnicliente/` + pipeline CI que regenere build.

### Pendientes

5. **Pendiente:** Limpiar workflows N8N legacy (raíz `N8N/`) consolidando en `PROD/` o `TEMPLATES/`.
6. **Pendiente:** Crear `.gitignore` para `omnicliente/` + pipeline CI que regenere build.

### Corto plazo

7. Implementar tabla `wp_automatiza_db_version` y refactor de migraciones.
8. Mover URLs N8N hardcoded a opciones WP.
9. Añadir smoke tests (PHPUnit minimal) para endpoints críticos.
10. Configurar UptimeRobot + alerta email/Telegram.
11. Activar HSTS tras verificar HTTPS 100%.

### Mediano plazo

12. Refactor incremental de `omnichannel-controller.php` (222 KB) y `functions.php` (100 KB).
13. Crear staging environment.
14. Pipeline CI/CD de deploy (GitHub Actions → Hostinger SSH).
15. Monitoring APM (Sentry o equivalente).
16. WAF externo (Cloudflare).

### Largo plazo (Phase 2/3 hardening)

17. MFA backoffice.
18. Logs centralizados (Loki/ELK).
19. Firma con certificado digital eIDAS (FEA Chile) en contratos.

---

## 📋 Cómo mantener esta documentación viva

| Cuándo | Qué actualizar |
|--------|----------------|
| Agregas tabla BD | `03_BASE_DE_DATOS.md` |
| Agregas endpoint REST | `04_THEME_WORDPRESS.md` o `05_API_BACKEND_PHP.md` |
| Agregas workflow N8N a PROD | `07_N8N_WORKFLOWS.md` |
| Agregas integración externa | `08_INTEGRACIONES_EXTERNAS.md` |
| Cambias hardening | `10_SEGURIDAD_HARDENING.md` |
| Agregas componente Portal | `06_PORTAL_OMNICLIENTE_FRONTEND.md` |
| Cambias proceso de despliegue | `11_DESPLIEGUE_OPERACIONES.md` |
| Cambias flujo de negocio | `12_FLUJOS_DE_NEGOCIO.md` |
| Renombras / convenciones | `13_GLOSARIO_Y_CONVENCIONES.md` |

> **Regla de oro:** ningún PR que cambie comportamiento del sistema debería mergearse sin actualizar el doc correspondiente en `Docs/MASTER/`.
