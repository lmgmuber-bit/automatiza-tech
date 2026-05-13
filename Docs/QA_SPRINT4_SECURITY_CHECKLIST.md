# Plan de Pruebas — Sprint 4 Security Hardening

**Rama:** `security/hardening-phase-0`  
**Última actualización:** 2026-05-13  
**Objetivo:** Verificar que todos los cambios de seguridad implementados funcionan correctamente antes de mergear a `main`.

---

## PASO 0 — Migración de base de datos (solo una vez)

Abre en el navegador **logueado como admin WordPress**:

```
http://localhost/automatiza-tech/setup-omnichannel-ai-chats.php
```

**Resultado esperado:**
> ✅ Tabla `wp_omnichannel_ai_chats` creada/verificada correctamente.

---

## ÁREA 1 — Portal Omnicanal (React)

**URL base:** `http://localhost/automatiza-tech/omnicanal/` (o donde lo sirves localmente)

---

### A5.2 — Webhook secret enmascarado en Canales

1. Login como **admin** del portal omnicanal
2. Menú → **Canales**
3. Localiza una tarjeta con canal YCloud que tenga webhook configurado
4. Busca la sección "URL Webhook YCloud" en la tarjeta

| Acción | Resultado esperado |
|--------|--------------------|
| Ver la tarjeta sin tocar nada | `...&secret=••••••••` (enmascarado) |
| Clic en ícono 👁️ (Eye) | Se revela el secret real |
| Clic en 👁️ nuevamente | Vuelve a mostrar `••••••••` |
| Clic en **"Copiar URL"** → pegar en bloc | La URL copiada tiene el secret real (no `••••••••`) |
| Abrir modal de edición del canal | Secret también aparece enmascarado |
| Clic en 👁️ dentro del modal | Se revela |
| Botón copiar dentro del modal | Copia la URL real |

---

### A5.3 — Reset token limpiado de URL antes del request

1. En la pantalla de login del portal omnicanal, clic en **"¿Olvidaste tu contraseña?"**
2. Ingresa el email del agente y solicita el reset
3. Revisa el email recibido → haz clic en el link de reset
   - La URL del link tiene la forma: `...?reset_token=XXX&email=YYY`
4. Al cargar la página del portal

| Verificación | Resultado esperado |
|-------------|--------------------|
| URL del navegador inmediatamente al cargar | Solo muestra la ruta base, **sin** `?reset_token=` ni `?email=` |
| Pantalla mostrada | Formulario de nueva contraseña con nombre del agente |

---

### A5.4 — AI Chat History persistido en backend

**Preparación:** Asegúrate de haber ejecutado el PASO 0 (migración BD).

#### Prueba 1 — Persistencia entre sesiones
1. Login como **agente** o **admin** en el portal omnicanal
2. Haz clic en el botón flotante del asistente IA (esquina inferior derecha ↘)
3. Inicia una conversación nueva y envía al menos 2 mensajes
4. Espera las respuestas del asistente
5. **Cierra el navegador completamente** (no solo la pestaña)
6. Vuelve a abrir el portal y el asistente IA
7. Abre el historial de chats

| Verificación | Resultado esperado |
|-------------|--------------------|
| Chat anterior visible | ✅ Aparece en el historial |
| Mensajes dentro del chat | ✅ Se muestran completos |
| `localStorage` en DevTools (F12 → Application → Local Storage) | ❌ **No** debe existir la key `omni_ai_chats` |

#### Prueba 2 — Eliminar chat
1. En el historial de chats, elimina una conversación
2. Recarga la página

| Verificación | Resultado esperado |
|-------------|--------------------|
| Chat eliminado tras recargar | ✅ Ya no aparece |
| Registro en BD | ❌ Eliminado de `wp_omnichannel_ai_chats` |

#### Verificación en base de datos (opcional)
```sql
-- En phpMyAdmin, HeidiSQL o WP-CLI:
SELECT id, agent_key, LEFT(messages, 80) AS preview, updated_at
FROM wp_omnichannel_ai_chats
ORDER BY updated_at DESC
LIMIT 10;
```
Debe mostrar los chats guardados con su `agent_key` (`agent:ID` o `admin:ID`).

#### Prueba 3 — Migración desde localStorage (si tenías chats viejos)
1. En DevTools (F12) → Application → Local Storage → busca `omni_ai_chats`
2. Si existe → al recargar el portal, los chats deben migrar automáticamente al backend
3. Tras la migración → la key `omni_ai_chats` debe desaparecer de localStorage
4. Los chats migrados deben aparecer en la BD

---

## ÁREA 2 — Contratos (flujo completo de firma)

**Acceso:** WordPress Admin → Clientes → selecciona un cliente de prueba

### Flujo completo

| Paso | Acción | Resultado esperado |
|------|--------|--------------------|
| 1 | Crear contrato desde admin | Contrato aparece en ficha del cliente |
| 2 | Clic en "Firmar como AT" | PDF descargado/mostrado con firma de AT visible |
| 3 | Clic en "Enviar link de firma al cliente" | Cliente recibe email corporativo con link de firma |
| 4 | Abrir el link como cliente | Se muestra el contrato completo para leer |
| 5 | Cliente firma (dibuja o sube imagen) | Firma queda registrada |
| 6 | Confirmar firma | — |
| 7 | **Email recibido por cliente** | Asunto: "Contrato firmado" — adjunto PDF con **ambas firmas** (AT + cliente) |
| 8 | Ficha del cliente → Contratos | Ambas columnas muestran ✔️ |
| 9 | Descargar PDF desde la ficha | El PDF tiene las **dos firmas visibles** |

> ⚠️ Bug corregido: antes el email post-firma del cliente adjuntaba el PDF viejo (solo firma AT).
> Ahora debe adjuntar el PDF final con ambas firmas.

---

## ÁREA 3 — Seguridad de documentos / IDOR

Prueba estas URLs **sin estar logueado** o con un usuario que NO sea el propietario:

```powershell
# PowerShell — ejecutar uno a uno y ver el resultado:
$urls = @(
    "http://localhost/automatiza-tech/validar-factura.php",
    "http://localhost/automatiza-tech/validar-factura.php?token=TOKEN_FALSO_CUALQUIERA",
    "http://localhost/automatiza-tech/validar-boleta.php",
    "http://localhost/automatiza-tech/contracts/sign-contract.php",
    "http://localhost/automatiza-tech/contracts/sign-contract.php?token=TOKEN_FALSO"
)
foreach ($url in $urls) {
    try {
        $r = Invoke-WebRequest $url -ErrorAction Stop
        Write-Host "$($r.StatusCode) → $url"
    } catch {
        Write-Host "$($_.Exception.Response.StatusCode.value__) → $url"
    }
}
```

| URL | Resultado esperado |
|-----|--------------------|
| `validar-factura.php` sin token | Error / redirect a login |
| `validar-factura.php?token=TOKEN_FALSO` | Error "token inválido" |
| `validar-boleta.php` sin token | Error / redirect |
| `sign-contract.php` sin token | Error / redirect |
| `sign-contract.php?token=TOKEN_FALSO` | Error "token inválido" |

---

## ÁREA 4 — Health Endpoint (E5)

```powershell
# GET debe responder 200 con JSON:
$r = Invoke-WebRequest "http://localhost/automatiza-tech/health.php"
Write-Host "Status: $($r.StatusCode)"
Write-Host "Body: $($r.Content)"

# POST debe responder 405:
try {
    Invoke-WebRequest "http://localhost/automatiza-tech/health.php" -Method POST
} catch {
    Write-Host "POST Status: $($_.Exception.Response.StatusCode.value__)"
    # Esperado: 405
}
```

| Prueba | Resultado esperado |
|--------|--------------------|
| GET `/health.php` | `200` + `{"status":"ok","db":"ok","ts":"..."}` |
| POST `/health.php` | `405 Method Not Allowed` |
| GET en BD caída | `503` + `{"status":"degraded","db":"error","ts":"..."}` |

---

## ÁREA 5 — Bloqueos .htaccess

```powershell
# Todos deben devolver 403:
$blocked = @(
    "http://localhost/automatiza-tech/xmlrpc.php",
    "http://localhost/automatiza-tech/debug-n8n-flow.php",
    "http://localhost/automatiza-tech/debug-reminders.php",
    "http://localhost/automatiza-tech/check-prefix.php",
    "http://localhost/automatiza-tech/setup-omnichannel-db.php",
    "http://localhost/automatiza-tech/fix-leads-schema.php",
    "http://localhost/automatiza-tech/_gen_token.php",
    "http://localhost/automatiza-tech/get-migration-token.php",
    "http://localhost/automatiza-tech/qa-report-generator.php"
)
foreach ($url in $blocked) {
    try {
        $r = Invoke-WebRequest $url -ErrorAction Stop
        Write-Host "$($r.StatusCode) ⚠️ DEBERÍA SER 403 → $url"
    } catch {
        $code = $_.Exception.Response.StatusCode.value__
        $icon = if ($code -eq 403) { "✅" } else { "❌" }
        Write-Host "$code $icon → $url"
    }
}
```

| URL | Resultado esperado |
|-----|--------------------|
| `xmlrpc.php` | `403` ✅ |
| `debug-*.php` | `403` ✅ |
| `check-*.php` | `403` ✅ |
| `setup-*.php` | `403` ✅ |
| `fix-*.php` | `403` ✅ |
| `_gen_token.php` | `403` ✅ |

**Lo que NO debe bloquearse:**
```powershell
# Estos deben funcionar normalmente:
$allowed = @(
    "http://localhost/automatiza-tech/",
    "http://localhost/automatiza-tech/wp-login.php",
    "http://localhost/automatiza-tech/api-omnichannel.php?route=health"
)
foreach ($url in $allowed) {
    $r = Invoke-WebRequest $url -ErrorAction SilentlyContinue
    Write-Host "$($r.StatusCode) → $url"
}
# Todos deben mostrar 200 o 302 (redirect login)
```

---

## ÁREA 6 — Upload de archivos (E3)

En el portal omnicanal como agente:

1. Ve a tu **perfil de agente** → sección de avatar
2. Intenta subir cada uno de estos archivos:

| Archivo | Resultado esperado |
|---------|--------------------|
| `foto.jpg` válida (< 2MB) | ✅ Se sube correctamente |
| `foto.png` válida (< 2MB) | ✅ Se sube correctamente |
| `virus.php` (cualquier contenido) | ❌ Rechazado — error de tipo |
| `script.exe` | ❌ Rechazado — error de tipo |
| `documento.pdf` | ❌ Rechazado — solo imágenes |
| Imagen de 10MB | ❌ Rechazado — excede tamaño máximo |

---

## ÁREA 7 — Rate Limiting (B3/E5)

```powershell
# Enviar 25 requests rápidos al login del portal — debe empezar a rechazar:
$url = "http://localhost/automatiza-tech/api-omnichannel.php?route=agent/login"
$body = '{"email":"test@test.com","password":"wrong"}'
$headers = @{ "Content-Type" = "application/json" }

for ($i = 1; $i -le 25; $i++) {
    try {
        $r = Invoke-WebRequest $url -Method POST -Body $body -Headers $headers -ErrorAction Stop
        Write-Host "Request $i → $($r.StatusCode)"
    } catch {
        $code = $_.Exception.Response.StatusCode.value__
        Write-Host "Request $i → $code $(if ($code -eq 429) { '✅ Rate limited' })"
    }
}
# A partir del request 10-15 debe aparecer 429 Too Many Requests
```

---

## 📋 Checklist Final Rápido

Marca cada ítem antes de hacer merge a `main`:

```
[ ] PASO 0: setup-omnichannel-ai-chats.php → "Tabla creada"
[ ] A5.2: Canales → webhook secret muestra ••••••••
[ ] A5.2: Botón ojo revela/oculta el secret correctamente
[ ] A5.2: "Copiar URL" copia la URL con secret real
[ ] A5.2: Modal de edición también enmascara el secret
[ ] A5.3: Reset link → URL queda limpia antes del fetch
[ ] A5.4: Chat IA persiste después de cerrar el navegador
[ ] A5.4: Eliminar chat lo borra del backend
[ ] A5.4: localStorage NO tiene key "omni_ai_chats" tras migración
[ ] Contrato: PDF firmado por cliente tiene AMBAS firmas
[ ] Contrato: Email post-firma tiene PDF con ambas firmas
[ ] health.php GET → {"status":"ok"} código 200
[ ] health.php POST → 405
[ ] xmlrpc.php → 403
[ ] debug-*.php → 403
[ ] setup-*.php → 403
[ ] wp-admin → funciona con normalidad (200/302)
[ ] Upload .php → rechazado
[ ] Upload .jpg válida → aceptada
[ ] validar-factura.php sin token → error/redirect
[ ] Rate limit login → 429 después de ~10 intentos
```

---

## Notas

- Todos los tests de bloqueo `.htaccess` aplican igual en **producción (Hostinger)** después de aplicar D4.
- Para producción, reemplaza `http://localhost/automatiza-tech` por `https://automatizatech.cl`.
- Si algún test falla, revisar los logs en: `wp-content/debug.log` o la consola del navegador (F12).
