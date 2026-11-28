# ARGOS Mecánico — diseño

**Fecha:** 2026-08-16
**Estado:** aprobado por Luis, correo de ejemplo ya probado (ver sección "Prueba realizada")

## Contexto

ARGOS (`Argos detección de Errores N8N`, workflow n8n id `p7ISUf0J5GycHscc`) detecta errores de cualquier workflow n8n conectado (vía `settings.errorWorkflow`), los analiza con IA, avisa por WhatsApp/email a Luis y guarda cada error en la tabla `automatiza_n8n_errors` (vía la API REST `automatiza/v1/n8n-errors`, manejada por `wp-content/themes/automatiza-tech/inc/admin-n8n-errors.php`).

Hoy, cuando ARGOS reporta un error, Luis tiene que revisarlo y arreglarlo manualmente. El objetivo de este proyecto es agregar un segundo agente — el **Mecánico** — que intente reparar automáticamente los errores que ARGOS detecta, dentro de límites de seguridad estrictos, y siempre le avise a Luis el resultado.

## Objetivo

Cuando ARGOS guarda un error nuevo, un workflow n8n adicional (el Mecánico) debe:
1. Detectarlo en menos de ~3 minutos.
2. Diagnosticar y, si es posible, repararlo — **solo dentro de la configuración del propio workflow n8n que falló** (nunca código PHP, nunca deploys).
3. Si lo repara: avisar por correo que se solucionó y qué se cambió.
4. Si no puede (máximo 3 intentos, o el diagnóstico indica que el problema está fuera de su alcance): avisar por correo que requiere la intervención de Luis, con un informe detallado del historial de intentos.

## Arquitectura

### Por qué esta arquitectura (descartado y por qué)

Se evaluaron y descartaron dos alternativas antes de llegar a esta:

- **Rutina Claude en la nube (`/schedule`) con cron cada 3 min** — descartado: las rutinas cloud de Claude Code tienen un **intervalo mínimo de 1 hora** (`RemoteTrigger`/`schedule` skill lo confirma explícitamente: `*/30 * * * *` se rechaza). No es viable para un chequeo cada 3 minutos.
- **Push/webhook instantáneo desde n8n hacia una rutina Claude** — descartado: no hay una fuente de webhook genérica confirmada en `RemoteTrigger.create_webhook_trigger` (solo se documentan eventos tipo GitHub). Confirmarlo hubiera requerido empezar a construir infraestructura sin certeza de que funcione.

### Diseño elegido: todo dentro de n8n

```
[Cualquier workflow n8n] --error--> [ARGOS, sin cambios]
                                          |
                                          v
                        avisa WhatsApp/email + guarda en automatiza_n8n_errors (status=new)

[Mecánico, nuevo workflow n8n]
  Schedule Trigger (cada 3 min, nativo de n8n — sin el límite de 1h de las rutinas cloud)
    -> GET automatiza_n8n_errors?status=new&fix_attempts<3   (chequeo barato, sin IA)
    -> si no hay nada nuevo: termina, costo ~0
    -> si hay error(es) nuevo(s), por cada uno:
         AI Agent node (Claude claude-sonnet-5 vía credencial "AT Anthropic API",
                         fallback a OpenAI "OpenAi account" si Claude falla —
                         mismo patrón que AT_Reel_Diario_Checkpoints_PlanB)
           herramientas: leer workflow / modificar workflow
                         (HTTP Request Tool -> API REST propia de n8n)
         -> intenta reparar (solo config n8n)
         -> si se arregla: marca resuelto en BD + correo "✅ solucionado"
         -> si no: incrementa fix_attempts; si llega a 3 (o el diagnóstico dice
            que no es reparable en n8n): marca requiere_intervencion + correo
            "⚠️ requiere tu intervención" con informe completo
```

El "agente" que diagnostica y repara **no es la sesión de Claude Code que habla con Luis** — es un nodo AI Agent embebido en el propio workflow de n8n, corriendo sin supervisión, con su propio system prompt (a escribir, con el mismo nivel de detalle que el de ARGOS) y herramientas acotadas.

## Alcance y límites de seguridad

- **Solo config de n8n**: parámetros de nodo, URLs, expresiones rotas, credenciales mal referenciadas, `Error Workflow` faltante, nodos desconectados. Nunca código PHP/WordPress, nunca despliegues a producción.
- **Máximo 3 intentos** por error específico (identificado por workflow + nodo + mensaje de error). Al tercero sin éxito, deja de reintentar automáticamente.
- **Escalamiento inmediato** si el diagnóstico determina que el problema no es de configuración de n8n (ej. credencial OAuth2 expirada, servicio externo caído): no insiste, avisa directo.
- **Todos los errores nuevos** activan un intento (sin filtro de severidad).

## Datos

Se agregan columnas a `automatiza_n8n_errors` (vía `wp-content/themes/automatiza-tech/inc/admin-n8n-errors.php` y `setup-n8n-errors-db.php` — cambio **aditivo únicamente**, sin tocar columnas existentes):

- `fix_attempts` (int, default 0)
- `fix_status` (`pendiente` / `resuelto` / `requiere_intervencion`)
- `fix_history` (JSON o texto: diagnóstico + acción + resultado de cada intento — insumo del informe del correo de escalamiento)
- `last_fix_attempt_at` (datetime)

## Correos de resultado

- Mismo estilo visual que ARGOS (header oscuro, banda de severidad, tarjetas de info, footer).
- Mismos destinatarios que ARGOS: para `lgonzalez@automatizatech.cl`, bcc `automatizacionesbotcore@gmail.com`.
- Se envían como **correo de seguimiento aparte** del correo original de ARGOS (no lo reemplazan).
- **Éxito** → asunto `✅ Mecánico solucionó: <workflow> - <nodo>`, cuerpo con qué se diagnosticó y qué se cambió.
- **Requiere intervención** → asunto `⚠️ Mecánico no pudo solucionar: <workflow> - <nodo>`, cuerpo con un informe (markdown renderizado **dentro del cuerpo del correo**, no adjunto) del historial de los 3 intentos: diagnóstico, acción, resultado de cada uno, y una recomendación final.

## Prueba realizada

Se creó un workflow temporal (`TEST - Mecanico Email Preview (borrar)`, borrado después de la prueba) que armó y envió ambos correos de ejemplo (caso éxito con 1 intento, caso escalado con 3 intentos e informe completo) a `lgonzalez@automatizatech.cl`. Luis confirmó que el formato, tono y nivel de detalle son correctos.

## Fuera de alcance (explícitamente descartado)

- Reparar código PHP o hacer deploys automáticos a producción.
- Trigger en tiempo real vía webhook (no confirmado como viable con las herramientas disponibles hoy).
- Filtrar por severidad (se decidió actuar sobre todos los errores nuevos).
