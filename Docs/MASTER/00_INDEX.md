# 📚 AutomatizaTech — Documentación Maestra (MASTER)

> **Versión:** 1.0 — Generado tras auditoría exhaustiva del repositorio.
> **Branch base:** `security/hardening-phase-0`
> **Ámbito:** Manual operativo + arquitectura para equipos Dev / DevOps / IAs (Copilot, Claude, GPT) que trabajen sobre el ecosistema AutomatizaTech.
> **Idioma:** Español (consistente con docs existentes).

---

## 🎯 Propósito

Esta carpeta `Docs/MASTER/` es la **única fuente de verdad técnica** del ecosistema AutomatizaTech. Reemplaza/complementa la documentación dispersa previa (ver `99_AUDIT_REPORT.md` para mapeo de docs antiguos). Está diseñada para:

1. **IAs asistentes** que necesitan contexto completo del repo para generar/modificar código sin romper nada.
2. **Equipo Dev** (onboarding, referencia de endpoints, schema de BD).
3. **Equipo DevOps** (despliegue, hardening, monitoreo).
4. **Equipo de Producto/Negocio** (entender flujos end-to-end).

---

## 🗂️ Índice de documentos

| # | Documento | Propósito | Audiencia |
|---|-----------|-----------|-----------|
| 00 | **[00_INDEX.md](./00_INDEX.md)** | Este archivo. Navegación maestra. | Todos |
| 01 | **[01_ARQUITECTURA_GENERAL.md](./01_ARQUITECTURA_GENERAL.md)** | Stack, topología, ambientes, diagrama global | Todos |
| 02 | **[02_MAPEO_ARCHIVOS.md](./02_MAPEO_ARCHIVOS.md)** | Inventario de carpetas y archivos clave | Dev / IAs |
| 03 | **[03_BASE_DE_DATOS.md](./03_BASE_DE_DATOS.md)** | Schema completo (~26 tablas) + ER diagram | Dev / DBA |
| 04 | **[04_THEME_WORDPRESS.md](./04_THEME_WORDPRESS.md)** | Theme `automatiza-tech`, módulos `inc/` | Dev WP |
| 05 | **[05_API_BACKEND_PHP.md](./05_API_BACKEND_PHP.md)** | Controladores raíz, endpoints REST/AJAX | Dev / Integradores |
| 06 | **[06_PORTAL_OMNICLIENTE_FRONTEND.md](./06_PORTAL_OMNICLIENTE_FRONTEND.md)** | SPA React (Vite) | Dev Frontend |
| 07 | **[07_N8N_WORKFLOWS.md](./07_N8N_WORKFLOWS.md)** | ~63 workflows productivos | Automation Eng |
| 08 | **[08_INTEGRACIONES_EXTERNAS.md](./08_INTEGRACIONES_EXTERNAS.md)** | OpenAI, YCloud, Google, Meta, Telegram, Redis | Integradores |
| 09 | **[09_MODULO_CONTRATOS.md](./09_MODULO_CONTRATOS.md)** | Firma electrónica doble | Dev / Legal |
| 10 | **[10_SEGURIDAD_HARDENING.md](./10_SEGURIDAD_HARDENING.md)** | Helpers `at-*.php`, .htaccess, CI/CD | DevOps / SecOps |
| 11 | **[11_DESPLIEGUE_OPERACIONES.md](./11_DESPLIEGUE_OPERACIONES.md)** | Deploy Hostinger, cron, backups | DevOps |
| 12 | **[12_FLUJOS_DE_NEGOCIO.md](./12_FLUJOS_DE_NEGOCIO.md)** | Diagramas Mermaid de procesos | Producto / Dev |
| 13 | **[13_GLOSARIO_Y_CONVENCIONES.md](./13_GLOSARIO_Y_CONVENCIONES.md)** | Naming, env vars, secrets | Todos |
| 99 | **[99_AUDIT_REPORT.md](./99_AUDIT_REPORT.md)** | Estado de docs previas: vigentes/obsoletas | Mantenedores |

---

## 🚦 Cómo usar esta documentación (para IAs)

Si eres una IA asistente que aterriza en este repo:

1. **Lee primero** `01_ARQUITECTURA_GENERAL.md` — entiende el stack en 5 minutos.
2. **Identifica el subsistema** que vas a modificar:
   - ¿Cambias algo del WordPress / theme? → `04_THEME_WORDPRESS.md`
   - ¿Tocas la API PHP raíz (omnichannel)? → `05_API_BACKEND_PHP.md`
   - ¿Frontend del Portal? → `06_PORTAL_OMNICLIENTE_FRONTEND.md`
   - ¿Workflow N8N? → `07_N8N_WORKFLOWS.md`
   - ¿Tabla nueva o migración? → `03_BASE_DE_DATOS.md`
   - ¿Integración externa nueva? → `08_INTEGRACIONES_EXTERNAS.md`
3. **Antes de commitear**, revisa `10_SEGURIDAD_HARDENING.md` (helpers `at-*` obligatorios).
4. **Convenciones de naming y env vars** → `13_GLOSARIO_Y_CONVENCIONES.md`.

---

## 📌 Hechos rápidos del ecosistema

| Concepto | Valor |
|----------|-------|
| Dominio prod | `automatizatech.cl` |
| Hosting | Hostinger (Apache + LiteSpeed Cache) |
| BD prod | MySQL `u402745362_automatizatech` |
| WordPress | Theme custom `automatiza-tech` (~40 archivos PHP, functions.php ~100KB) |
| Tablas custom | ~26 (`wp_automatiza_*`, `wp_omnichannel_*`, `ai_usage_log`) |
| Endpoints REST | 25+ en `/wp-json/automatiza-tech/v1/` + 60+ consumidos por el portal |
| AJAX handlers | 45+ |
| Portal OmniCliente | React 19 + Vite 6 + Tailwind 3 (carpeta `client-portal-omnichannel/`, build en `omnicliente/`) |
| N8N | ~63 workflows en `https://n8n-n8n.kchiba.easypanel.host` |
| Bot principal | `WhatsApp_Bot_v8_Portal_OmniCliente.json` |
| Branch hardening | `security/hardening-phase-0` (actual) |
| Helpers seguridad | 9 archivos `at-*.php` |
| Sistema de contratos | Doble firma con SHA-256, tokens 64-hex |

---

## 🔄 Mantenimiento de esta documentación

- **Cuando cambies código que invalide un doc:** actualiza el doc en el mismo PR.
- **Cuando agregues una tabla:** edita `03_BASE_DE_DATOS.md`.
- **Cuando agregues un endpoint:** edita `05_API_BACKEND_PHP.md` (raíz) o `04_THEME_WORDPRESS.md` (theme).
- **Cuando agregues un workflow N8N nuevo a PROD:** agrega fila en la tabla de `07_N8N_WORKFLOWS.md`.
- **Cuando incluyas un helper de seguridad nuevo:** documenta en `10_SEGURIDAD_HARDENING.md`.
