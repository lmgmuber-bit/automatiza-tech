# Incidente 2026-08-25/26 — Errores de n8n, token de Instagram y tres agujeros de seguridad

> Origen: Luis pidió revisar errores que ARGOS reportaba y que el Mecánico no lograba resolver.
> Lo que empezó como una revisión de workflows terminó destapando una cadena explotable.
> **Todo lo descrito aquí está cerrado y verificado**, salvo lo listado en "Pendientes".

---

## 1. Resumen ejecutivo

| Hallazgo | Gravedad | Estado |
|---|---|---|
| Token de Instagram vencido: el Reel Diario no publicaba desde el 14-ago | Alta | ✅ cerrado |
| Bypass de auth en ARGOS: listado de errores **público** | Crítica | ✅ cerrado |
| `n8n_test_token` expuesto → envío masivo de correos disparable por cualquiera | Crítica | ✅ cerrado |
| API key de ARGOS en texto plano en 4 nodos de n8n | Media | ✅ cerrado |
| Google Sheets sin retry ante 500/503 transitorios | Baja | ✅ cerrado |
| Tres secretos filtrados por el agente durante el diagnóstico | Alta | ✅ rotados |

---

## 2. Por qué el Mecánico "no resolvía nada"

**No estaba roto.** En tres días procesó 50 errores, los 50 con `fix_attempts=1` y
`fix_status='requiere_intervencion'`. Los intentó, los clasificó como no reparables y escaló.
Como `requiere_intervencion` es estado terminal, la cola `pending-fix` quedaba vacía y el
Mecánico corría cada 3 minutos sin nada que hacer.

**La clasificación era correcta en 3 de 4 casos.** El Mecánico solo sabe editar configuración
de n8n, y los errores eran de credenciales y de red. Se equivocó en uno: los 500/503 de Google
Sheets sí eran reparables activando `retryOnFail` — el propio mensaje de n8n lo sugiere.

> **Lección:** un agente reparador que escala el 100% de los casos no está necesariamente
> fallando. Antes de tocarlo, revisar si los errores caen dentro de lo que sabe arreglar.

---

## 3. El token de Instagram y el fallo tardío

`WP Media Create` fallaba con `OAuthException code 190: Session has expired on 14-Aug-26`.

Lo caro no fue el token vencido, sino **dónde** se descubría: el workflow completaba descarga
de clips, extracción de frames, QA con Anthropic Vision y render del MP4 (~55 s) — y recién
ahí moría. Tres renders y tres QA de IA desperdiciados **por día, durante una semana**.

El diseño original del Reel Diario ya contemplaba un paso de *pre-flight* que validara el
token antes de empezar. Nunca se implementó.

> **Lección:** en un pipeline caro, validar las credenciales en el primer paso, no en el
> último. El costo de un fallo tardío se multiplica por cada ejecución programada.

**Arreglado con dos piezas:**
- `reel-diario/token-check` — endpoint de pre-flight, hace la llamada más barata posible
  (`GET me`) y devuelve 502 si el token no sirve, para que n8n corte sin necesidad de un IF.
- Nodo `Token Preflight` entre `Trigger Context` y `Drive List Folders`.
- `wp-content/mu-plugins/at-ig-token-refresh.php` — cron diario que renueva el token cuando
  quedan menos de 15 días. No refresca a diario a propósito: cada refresh rota el valor y eso
  invalidaría los respaldos del equipo. Si el token tiene menos de 24 h, salta en silencio
  (Meta lo rechaza y sería una falsa alarma diaria).

### Cómo generar un token de Instagram para este proyecto

La app usa **`graph.instagram.com` v23.0** (Instagram API con Instagram Login), **no** el flujo
de Page Token de `graph.facebook.com`. Los tutoriales genéricos llevan al flujo equivocado.

developers.facebook.com → Casos de uso → *Administrar mensajes y contenido en Instagram* →
Personalizar → *Configuración de la API con inicio de sesión de Instagram* → paso 2
*Generar tokens de acceso*. Sale ya de larga duración (60 días), sin intercambio manual.

Permiso clave: **`instagram_business_content_publish`** habilita los Reels y **no** aparece en
el bloque "permisos necesarios para los mensajes" del paso 1 — hay que verificarlo aparte en
*Permisos y funciones*. El estado "Listo para la prueba" alcanza, porque se publica en la
cuenta propia conectada a la app.

El token se guarda en la columna `bot_token` del canal (tabla `omnichannel_channels`), editable
desde Portal OmniCliente → Canales.

---

## 4. El bypass de `WP_DEBUG` — el hallazgo más grave

`automatiza_verify_n8n_api_key()` en `admin-n8n-errors.php` tenía:

```php
// En desarrollo, permitir sin key
if (defined('WP_DEBUG') && WP_DEBUG) {
    return true;
}
```

**`WP_DEBUG` está encendido en producción**, así que la verificación se saltaba siempre.
Consecuencias medidas:

- El listado de errores era **público**: 50 filas, 180 KB, con stack traces completos y
  **214 referencias a rutas internas del servidor**.
- Cualquiera podía **inyectar registros falsos** por POST.
- **Rotar la clave no tenía ningún efecto.** Así se descubrió: Luis la cambió en WordPress y
  la clave vieja seguía funcionando.

Además, el fallback `get_option('automatiza_n8n_api_key', 'clave-escrita-en-el-codigo')`
significaba que si la opción se borraba, la API aceptaba una credencial publicada en el repo.

> **Lección:** la autenticación nunca puede depender de un flag de depuración, y un
> `get_option($k, 'valor-por-defecto')` en una comprobación de seguridad es una puerta trasera
> latente. **Fallar cerrado**: sin clave configurada, no entra nadie.

---

## 5. La cadena explotable

Conectando dos hallazgos aparecía un ataque completo:

1. El listado público de ARGOS contenía la URL
   `admin-ajax.php?action=send_email_to_new_contacts_n8n&n8n_token=<token>`.
2. Ese endpoint **envía correo a todos los contactos con `status='new'`**.

Es decir: leer el listado → sacar el token → spamear la base de prospectos y quemar la
reputación del SMTP.

El endpoint tenía tres problemas encadenados:
- Token comparado contra un literal escrito en el código.
- Viajaba **en la query string** → queda en logs de nginx, historial y referers.
- Ante fallo **devolvía el token recibido** en el mensaje de error, convirtiéndolo en un espejo
  para quien probara claves. Y lo escribía en claro en `debug.log` vía `error_log`.

> **Lección:** un secreto en la query string no es un secreto. Y un endpoint que refleja lo que
> recibe le regala al atacante un oráculo para probar valores.

---

## 6. El diagnóstico equivocado que casi cuesta un ticket

Se detectaron 35 fallos con timeouts de 30 s (`ECONNABORTED`) y un `307` de nginx desde la IP
de n8n (`72.61.132.193`, confirmada por eco desde el propio n8n, no deducida del DNS). Desde
fuera los mismos endpoints respondían 200 en menos de 1,3 s. La conclusión parecía obvia:
Hostinger estaba bloqueando la IP.

**Era incorrecto.** Al mirar la línea de tiempo completa:

- 19–22 ago: ~35 fallos.
- 22 ago 03:45 → 25 ago 00:35: **cero fallos**, casi tres días limpios.
- Desde entonces: uno solo.

Fue un **episodio transitorio** que se resolvió solo. Se había analizado una ventana **ya
cerrada** y presentado como un problema en curso. El CDN de Hostinger, sospechoso principal
durante un rato, quedó descartado: sus datos empiezan el 24-ago, después de los fallos.

> **Lección:** antes de diagnosticar la causa de una serie de errores, verificar si **siguen
> ocurriendo**. Un histograma por fecha antes que una teoría. El arreglo que sí correspondía
> —retries y bajar la frecuencia de los crons— resultó ser exactamente el adecuado para un
> hipo intermitente.

---

## 7. Filtración de secretos por el propio agente

Durante el diagnóstico, el agente filtró secretos en el chat **tres veces**:

1. Al listar `APIS KEY.txt` "solo etiquetas y longitudes": las líneas con formato
   `define('X','valor')` no tienen `=` ni `:`, cayeron en la rama `else` y se imprimieron crudas.
2. Al hacer `grep -rn` sobre todo el repo buscando consumidores de una constante: el grep
   incluyó `wp-config-secrets.php`.
3. En el mismo grep, un default que vivía en `wp-config.php`.

Costo: rotar `AT_REST_SECRET`, `REEL_DIARIO_SECRET` y `AT_BOARD_TOKEN` de urgencia.

> **Regla obligatoria para cualquier agente en este repo:**
> 1. Excluir siempre `wp-config.php`, `wp-config-secrets.php`, `.env*` y `APIS KEY.txt` de
>    cualquier búsqueda cuya salida se muestre.
> 2. Redactar siempre la salida, aunque parezca innecesario:
>    `| sed -E "s/(['\"])[A-Za-z0-9_/+.=-]{16,}\1/\1<REDACTADO>\1/g"`
> 3. **Prohibida cualquier rama que imprima una línea cruda**, ni siquiera truncada. 40 de 64
>    caracteres hex siguen obligando a rotar.
> 4. Para saber algo *sobre* un secreto, calcular e imprimir solo el dato derivado
>    (`largo=64`, `iguales=True`), nunca el valor.

---

## 8. Trampas de despliegue descubiertas

**Git puede estar atrasado respecto a PROD.** `api-omnichannel.php` tenía ~530 líneas de la
Fase 5 sin comitear que **ya estaban desplegadas**. Se verificó pidiendo el archivo
`omnichannel-atfinance-controller.php` por HTTP: 200 en PROD, 404 para un archivo inventado.
Un parche "mínimo" reconstruido desde la base de git **habría borrado la Fase 5 de producción**.

**Dos copias divergentes del mismo archivo.** `admin-n8n-errors.php` existe en el árbol
principal y en `.worktrees/argos-mecanico/`. La del árbol principal **no tiene los endpoints
del Mecánico**; la del worktree sí, y es la que coincide con PROD. Subir la equivocada rompe
producción.

> **Lección:** antes de subir por FTP, verificar contra el servidor qué versión está viva.
> No asumir que el repositorio es la fuente de verdad.

---

## 9. Técnicas reutilizables

**Verificar un token sin publicar.** Llamar `reel-diario/media-status` con un `creation_id`
falso: usa el mismo `bot_token` del canal. Token vencido → `OAuthException 190`. Token vivo →
`IGApiException 100/33` ("Object with ID '1' does not exist"). Distingue los dos casos sin
tocar nada.

**Obtener la IP de salida real de n8n.** Un workflow temporal con webhook → `api.ipify.org`.
El registro DNS da la IP de entrada, que no siempre es la de salida.

**Escanear todos los workflows sin exponer valores.** Recorrer la API de n8n en un script que
reporte solo nombre de workflow, nombre de nodo y si el secreto está en texto plano o en
credencial. Nunca el valor.

**El bloqueo de `settings` del MCP de n8n.** Los updates fallan con
`settings must NOT have additional properties` porque la UI guarda `timeSavedMode` y
`availableInMCP`, que el schema público rechaza al reescribir. El MCP fusiona los settings
guardados e ignora los que uno mande, así que **no hay solución por MCP**: hay que hacer
`PUT /api/v1/workflows/<id>` filtrando esas dos claves. `callerPolicy` sí es válida.

---

## 10. Estado final

**Código desplegado en PROD:**

| Archivo | Cambio |
|---|---|
| `wp-content/mu-plugins/at-ig-token-refresh.php` | nuevo — cron de auto-renovación del token |
| `api-omnichannel.php` | endpoint `reel-diario/token-check` |
| `omnichannel-controller.php` | método `check_instagram_token()` |
| `wp-content/themes/automatiza-tech/inc/contact-form.php` | token desde constante, sin query string, sin eco, sin `error_log` |
| `wp-content/themes/automatiza-tech/inc/admin-n8n-errors.php` | sin bypass de `WP_DEBUG`, sin fallback, `hash_equals` |
| `wp-config-secrets.php` | tres secretos rotados + `AT_N8N_CONTACTS_TOKEN` |
| `wp-config.php` | default filtrado de `AT_REST_SECRET` → `''` |

**Configuración de n8n:**

- `Purge Cache Cron` y `WhatsApp Recordatorio 1h`: cron 5 → 15 min, retry ×3, timeout 60 s.
- `Reporte Diario KELLS` y `AT_IG_Flujo1_Preview_Poller`: retry ×3 en los nodos de Sheets.
- `Argos detección de Errores`: retry en `Guardar en BD`.
- Credenciales nuevas: `AT ARGOS WP Key`, `AT Contactos n8n Token`.
- 4 nodos de ARGOS y 1 de contactos migrados de texto plano a credencial.
- Nodo de contactos migrado de `typeVersion 1` a `4.2`.
- `AT_Reel_Diario_Checkpoints_PlanB`: nodo `Token Preflight` + los 3 checkpoints reactivados.

**Verificaciones finales:** listado de ARGOS sin clave → 401 · token viejo de contactos →
`Token invalido` · pre-flight → `{"ok":true,"username":"automatizatech.cl"}` · sitio 200 ·
`validate_workflow` del Reel Diario → `valid: true`, 89 conexiones, 0 errores.

---

## 11. Pendientes

1. **Rotar el `webhook_secret` del canal 2** — se pegó en el chat durante la sesión. Es el
   último secreto comprometido sin rotar. Aprovechar para migrar del modo legacy `?secret=`
   al HMAC por cabecera (`X-AT-Signature`), que el propio código ya soporta y recomienda.
2. **Borrar la credencial n8n `AT Reel Diario WP Secret`** (sin "v2") — 0 usos.
3. **Apagar `WP_DEBUG` en producción.** Ya no abre ARGOS, pero puede filtrar avisos de PHP en
   las respuestas.
4. **Quitar el fallback `'omni_default_secret'`** en `api-omnichannel.php` y
   `omnichannel-controller.php` — mismo patrón que se corrigió en ARGOS. Hoy no es explotable
   porque `OMNI_ADMIN_SECRET` sí está definida por variable de entorno en PROD (verificado con
   tres firmas HMAC distintas, las tres rechazadas con 403), pero si esa variable se cae, el
   sistema pasaría a firmar con una clave escrita en el código.
5. **Mergear `feat/argos-mecanico` a `main`** — lleva meses sin mergear y su versión de
   `admin-n8n-errors.php` es la que está en producción. Mientras siga sin mergear, el árbol
   principal tiene una copia vieja que rompe PROD si alguien la sube por error.
