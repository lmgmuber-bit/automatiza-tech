# Métricas de Instagram en el Portal OmniCliente

> Estado (21-oct-2026): **LOCAL, en la rama `claude/omni-metricas-ig-b7cf03`**, con pruebas en verde. Nada de esto está en PROD
> hasta que Luis autorice el despliegue (lista exacta en §7). Dueño del área: Eje (plataforma AT).

La vista **Marketing IG** del portal dejó de ser una maqueta: muestra las métricas reales de @automatizatech.cl, las guarda en la
base y las entrega a los agentes (Claqueta, la sesión de redes) por una ruta protegida. Si falta el permiso de Meta o todavía no
hay tomas, la pantalla lo dice; nunca muestra cifras de ejemplo.

## 1. Cómo funciona

```
n8n «OmniCliente · Métricas IG» (cada hora, minuto 7)
   └─ POST api-omnichannel.php?route=ig-metrics/sync   (X-IG-Metrics-Secret)
        └─ OmniIgInsightsController::sync()
             ├─ GET me                → seguidores, seguidos, cantidad de publicaciones
             ├─ GET me/media          → publicaciones (feed y reels) con likes y comentarios
             ├─ GET me/stories        → historias vigentes
             ├─ GET {media}/insights  → solo las publicaciones con una toma pendiente
             └─ GET me/insights       → los 3 últimos días completos de la cuenta (cada 6 h)
Portal (admin) ─ GET admin/ig-insights ─┐
Agentes ─ GET ig-metrics (secret) ──────┴─ lee las tablas, no llama a Meta
```

- **Solo lee de Instagram.** Usa el mismo token del canal que el publicador (`bot_token` del canal 2,
  `REEL_DIARIO_CHANNEL_ID`) a través de `OmnichannelController::ig_graph_get()`, que solo hace GET. Los métodos del
  publicador (`reel-diario/*`, `ig_api_request`, `check_instagram_token`) no cambiaron.
- **Tomas por publicación:** a las **24 h, 48 h, 7 días y 30 días** de publicada (fijas, no se pisan) y una **«última»** que se
  refresca cada 6 h mientras la publicación tenga hasta 35 días. Una toma fija se registra solo hasta 1,5 veces su edad (la de
  24 h, hasta las 36 h): si se descubrió tarde, queda vacía en vez de mal rotulada. Las historias se leen en cada pasada mientras
  duran (24 h).
- **Cuenta por día:** alcance, visualizaciones, cuentas que interactuaron, interacciones, likes, comentarios, compartidos,
  guardados, respuestas y toques de enlaces del perfil, de ayer y dos días antes (Meta tarda hasta 48 h en asentar las cifras), más
  la foto de seguidores de cada día. Los seguidores nuevos por día (`follower_count`) Meta solo los da a cuentas con 100 o más.
- **Costo en llamadas:** unas 5 por hora en régimen (11 en la primera toma de la prueba). El tope de Meta es 4.800 × impresiones
  de 24 h por cuenta (doc de Meta, [overview](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/overview)).
- **Freno:** dos tomas seguidas con menos de 2 minutos de diferencia se saltan (también con `force`), y hay un candado para que no
  corran dos a la vez.

### Métricas que se piden (verificadas en la doc de Meta el 21-oct-2026)

| Tipo | Métricas | Fuente |
|---|---|---|
| Reel | `views, reach, likes, comments, saved, shares, total_interactions, ig_reels_avg_watch_time, ig_reels_video_view_total_time` | [Media insights](https://developers.facebook.com/docs/instagram-platform/reference/instagram-media/insights) |
| Publicación (imagen o carrusel) | `views, reach, likes, comments, saved, shares, total_interactions, follows, profile_visits` | ídem |
| Historia | `views, reach, replies, shares, total_interactions, follows, profile_visits` | ídem |
| Cuenta (día, `metric_type=total_value`) | `reach, views, accounts_engaged, total_interactions, likes, comments, shares, saves, replies, profile_links_taps` + `follower_count` | [User insights](https://developers.facebook.com/docs/instagram-platform/api-reference/instagram-user/insights) |

`views` reemplazó a `impressions`, `plays` y `video_views`, que Meta retiró de todas las versiones el 21-abr-2025 y el 8-ene-2025
([changelog](https://developers.facebook.com/docs/instagram-platform/changelog)). En Reels Meta **no** entrega seguidores ganados por
publicación: esa columna queda vacía (`null`), no en cero. Si Meta rechaza una métrica para un medio, la toma se repite con un
juego más chico y se guarda lo demás. Un valor sin dato es `null`, nunca `0`.

**Por comprobar con la cuenta real** (la doc no lo dice o es ambigua): si los insights de un carrusel salen a nivel álbum, si
`caption` y `media_product_type` llegan con Instagram Login (el código igual funciona sin ellos) y si el acceso estándar de la app
basta para insights.

## 2. Tablas (se crean solas)

`omnichannel-ig-insights-controller.php::maybe_create_tables()` con `dbDelta`, protegido por la opción
`omni_ig_insights_db_version` (mismo patrón que Finanzas AT): la primera llamada a `api-omnichannel.php` las crea y las siguientes
no hacen nada. No hace falta subir ni borrar un `setup-*.php` público.

| Tabla | Qué guarda |
|---|---|
| `wp_omnichannel_ig_posts` | Una fila por publicación: `media_id`, tipo, texto, enlace, fecha (UTC), likes, comentarios, `status` (`published`, `deleted`, `expired`) e `insights_state` |
| `wp_omnichannel_ig_post_metrics` | Una fila por toma: `checkpoint` (`24h`, `48h`, `7d`, `30d`, `latest`), `taken_at`, `age_hours` y cada métrica |
| `wp_omnichannel_ig_account_daily` | Un día por fila (hora de Chile): métricas del día y foto de seguidores |

Estado de la última toma: opción `omni_ig_insights_state_<canal>`; perfil: `omni_ig_insights_profile_<canal>`.

## 3. Ruta para agentes (Claqueta)

**Base:** `https://automatizatech.cl/api-omnichannel.php?route=…`
**Cabecera:** `X-IG-Metrics-Secret: <AT_IG_METRICS_SECRET>` (credencial de n8n `AT IG Metrics Secret`; el valor nunca va en la URL ni
en el repo).
**Canal:** siempre el de Instagram de AutomatizaTech; con este secreto no se leen canales de clientes.

| Método y ruta | Qué devuelve |
|---|---|
| `GET ig-metrics&days=30&posts_limit=30` | Resumen completo: cuenta, estado, totales del periodo, días y publicaciones con sus tomas (`days` 1–90, `posts_limit` 1–100) |
| `GET ig-metrics/posts&limit=30` | `{ok, insights_status, posts[]}` |
| `GET ig-metrics/posts/{media_id}` | `{ok, insights_status, post}`; 404 si la publicación no está registrada |
| `POST ig-metrics/sync` (cuerpo `{}` o `{"force":true}`) | Pide una toma ahora; responde el resumen de la toma. 502 si falló (token vencido, etc.) |

Códigos: `403` secreto ausente o malo · `503` falta `AT_IG_METRICS_SECRET` en el servidor · `429` más de 240 llamadas por hora
desde la misma IP · `404` subruta o publicación inexistente. El secreto de métricas no abre `reel-diario/*` ni `admin/*`, y los otros
secretos no abren `ig-metrics/*` (probado).

**Por qué un secreto propio:** `X-Reel-Diario-Secret` publica y borra en Instagram; un agente que solo mira no debe poder
publicar. `OMNICHANNEL_CRON_SECRET` viaja en la URL (`?secret=`) y queda en los registros de acceso. Este secreto solo lee y pide
tomas, y se puede rotar sin tocar el publicador.

### Formato de respuesta de `GET ig-metrics`

```json
{
  "ok": true,
  "generated_at": "2026-10-22T13:07:02Z",
  "channel_id": 2,
  "account": {
    "ig_user_id": "1784…", "username": "automatizatech.cl",
    "followers_count": 262, "follows_count": 48, "media_count": 37,
    "snapshot_at": "2026-10-22T13:07:01Z"
  },
  "insights_status": {
    "status": "ok", "message": "Métricas de Instagram al día.", "code": 0,
    "last_sync_at": "2026-10-22T13:07:02Z", "last_sync_ok": true, "last_errors": []
  },
  "period": {
    "days": 30, "from": "2026-09-22", "to": "2026-10-21", "days_with_data": 28,
    "totals": { "reach": 13366, "views": 22850, "accounts_engaged": 1069, "total_interactions": 2712,
                "likes": 3139, "comments": 2528, "shares": 1334, "saves": 120, "replies": 40, "profile_links_taps": 55 },
    "followers_gained": null, "followers_net_change": 62,
    "note": "reach y accounts_engaged son sumas de valores diarios: una cuenta que vio contenido dos días cuenta dos veces."
  },
  "daily": [
    { "day": "2026-10-21", "reach": 512, "views": 880, "accounts_engaged": 41, "total_interactions": 70,
      "likes": 50, "comments": 6, "shares": 4, "saves": 8, "replies": 2, "profile_links_taps": 3,
      "followers_count": 262, "followers_gained": null }
  ],
  "posts": [
    {
      "media_id": "1797030896…", "posted_at": "2026-10-21T15:30:12Z",
      "media_type": "VIDEO", "media_product_type": "REELS", "kind": "REELS",
      "caption": "…", "permalink": "https://www.instagram.com/reel/…/",
      "status": "published", "like_count": 41, "comments_count": 6,
      "insights_state": "ok", "insights_error": null, "last_seen_at": "2026-10-22T13:07:01Z",
      "latest": { "taken_at": "2026-10-22T13:07:02Z", "age_hours": 21.6, "views": 1000, "reach": 800, "likes": 41,
                  "comments": 6, "saved": 7, "shares": 3, "total_interactions": 57, "follows": null,
                  "profile_visits": null, "replies": null, "avg_watch_time_ms": 4321, "total_watch_time_ms": 99999 },
      "checkpoints": { "24h": null, "48h": null, "7d": null, "30d": null }
    }
  ]
}
```

Las cifras del ejemplo son ilustrativas. Fechas en ISO 8601 UTC (`Z`); los días de `daily` son de Chile.

| `insights_status.status` | Significado | Qué hacer |
|---|---|---|
| `ok` | Última toma bien | — |
| `never` | Aún no hay tomas | `POST ig-metrics/sync` o esperar al cron |
| `no_permission` | El token no tiene `instagram_business_manage_insights`: hay likes, comentarios y seguidores, no alcance ni vistas | §5 |
| `token_invalid` | Token vencido o inválido (código 190): **el publicador también falla** | Renovar el token (§5) |
| `no_token` | El canal no tiene token | Pegar el token en Canales → Instagram |
| `error` | Otro fallo; detalle en `last_errors` | Revisar el mensaje |

`posts[].insights_state`: `ok`, `pending`, `no_permission`, `token_invalid`, `not_enough_viewers` (Meta no da cifras de historias con
menos de 5 espectadores), `deleted` o `error`. `posts[].status`: `published`, `deleted` (ya no está en Instagram) o `expired`
(historia de más de 24 h).

Ejemplo para un agente (el secreto se carga desde la credencial, nunca escrito):

```bash
curl -s -H "X-IG-Metrics-Secret: $AT_IG_METRICS_SECRET" "https://automatizatech.cl/api-omnichannel.php?route=ig-metrics&days=7"
```

## 4. Portal

- **Marketing IG** (solo admin): periodo de 7, 30 o 90 días, botón «Actualizar ahora» (`admin/ig-insights/sync`), aviso del
  estado, cuatro indicadores (seguidores, visualizaciones, alcance sumado, interacciones), gráfico por día con selector de métrica y
  vista de tabla, y la lista de publicaciones con la última toma y las fichas 24h/48h/7d/30d. Se quitó lo que no tenía fuente (embudo
  DMs→leads→demos, ROI por pilar, créditos de Higgsfield, costo de Gemini).
- **Canales:** el token de Instagram ya no viaja al navegador (antes `SELECT *` lo mandaba completo a admin, agentes y clientes) ni
  queda en la auditoría (se guarda `[oculto]`). La tarjeta muestra «✓ Configurado» con `has_bot_token`. Al editar, el campo vacío
  conserva el token guardado; para cambiarlo se pega uno nuevo.

## 5. Pasos manuales de Luis

### 5.1 Token con el permiso de métricas (en Meta)

El permiso `instagram_business_manage_insights` nunca se pidió. La ruta del panel es la misma que se usó al renovar el token a mano
en agosto (la cita el correo de `wp-content/mu-plugins/at-ig-token-refresh.php`) y coincide con la doc de Meta
([get started](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/get-started)):

1. developers.facebook.com → **Mis apps** → la app de AutomatizaTech con Instagram.
2. **Casos de uso → Administrar mensajes y contenido en Instagram → Personalizar / Permisos:** confirmar que
   `instagram_business_manage_insights` está agregado junto a `instagram_business_basic`, `…_content_publish` y
   `…_manage_messages`. Si no está, agregarlo.
3. **Configuración de la API con inicio de sesión de Instagram → Generar tokens de acceso → Generar token** junto a
   @automatizatech.cl, iniciar sesión en Instagram y aceptar todos los permisos que pida (incluido el de estadísticas).
4. Copiar el token y pegarlo en el portal: **Canales → Instagram (canal 2) → Editar → Token de Instagram → Guardar**. No pegarlo en
   ningún otro lado.

Supuestos sin verificar (la doc no lo dice): el nombre exacto del botón del paso 2 en el panel de hoy, y si generar un token nuevo
invalida el anterior. Por eso el token se pega de inmediato en el portal, que es la única copia que usan el publicador, los DM y las
métricas. La cuenta es del dueño de la app, así que el acceso estándar debería bastar sin App Review; Meta advierte que con acceso
estándar los insights «podrían no funcionar bien»: se comprueba en la primera toma. El token nuevo dura 60 días y lo sigue
renovando solo `at-ig-token-refresh.php` (lee el token directo de la base, no por la API de canales, así que el tachado no lo afecta).

**Después de pegarlo** (lo comprueba quien despliega): `reel-diario/token-check` debe responder `{"ok":true}` (publicador) y
`POST ig-metrics/sync` debe responder `status: "ok"` (métricas).

### 5.2 Secreto `AT_IG_METRICS_SECRET`

Va en `public_html/wp-config.php`, antes de `require_once ABSPATH . 'wp-settings.php'` (en PROD `wp-config-secrets.php` no se
carga). Mismo valor en la credencial de n8n **AT IG Metrics Secret** (tipo Header Auth, nombre `X-IG-Metrics-Secret`) y en la
etiqueta del archivo de claves para Claqueta. Con autorización, Claude puede generarlo y ponerlo en los tres lugares con un script
que no lo imprime; si no, Luis lo pega. Sin la constante, `ig-metrics/*` responde 503 y el portal igual funciona.

## 6. Pruebas

```bash
AT_WP_LOAD=<wp-load.php local> php tests/ig-insights/ig-insights-wp-test.php   # 67 casos: tomas, estados, tachado del token
AT_WP_LOAD=<wp-load.php local> php tests/ig-insights/rutas-http-wp-test.php    # 28 casos por HTTP real con php -S
```

Las dos usan el WordPress local y una Graph API simulada: ninguna llamada sale a Meta. Cubren: tomas por edad (24 h, 48 h, 7 y
30 días), token sin permiso (aviso y sin cifras inventadas), token vencido, canal sin token, métrica rechazada, publicación
borrada, freno de 2 minutos, token solo en la cabecera, 403/503/404 de las rutas y que los secretos no se crucen. La vista se
revisó en el navegador (claro y oscuro, sin y con permiso) contra la API de la rama.

## 7. Despliegue a PROD (requiere autorización de Luis)

Base: `main` = PROD para estos archivos (cotejado el 21-oct por SSH, solo lectura: `api-omnichannel.php` y
`omnichannel-controller.php` iguales salvo el fin de línea; el build `omnicliente/` también).

| Orden | Local | Destino en PROD | Clase |
|---|---|---|---|
| 0 | — | Respaldo de los tres PHP y de `omnicliente/index.html` en `~/respaldos/omni-metricas-ig-antes-<fecha>/` | OBLIGATORIO |
| 1 | `omnichannel-ig-insights-controller.php` | `public_html/omnichannel-ig-insights-controller.php` | OBLIGATORIO (antes que la API: si no, fatal) |
| 2 | `omnichannel-controller.php` | `public_html/omnichannel-controller.php` | OBLIGATORIO |
| 3 | `api-omnichannel.php` | `public_html/api-omnichannel.php` | OBLIGATORIO, último PHP |
| 4 | `define('AT_IG_METRICS_SECRET', …)` | `public_html/wp-config.php` | OBLIGATORIO para agentes y cron |
| 5 | `omnicliente/assets/*` nuevos (18 `.js` y 1 `.css` del build) | `public_html/omnicliente/assets/` | OBLIGATORIO, antes del `index.html` |
| 6 | `omnicliente/index.html` | `public_html/omnicliente/index.html` | OBLIGATORIO, al final |
| 7 | `N8N/PROD/Omnichannel_IG_Insights_Cron.json` + credencial `AT IG Metrics Secret` | n8n (importar y activar) | OBLIGATORIO para las tomas cada hora |
| 8 | Token nuevo con insights (§5.1) | Canales → Instagram | OBLIGATORIO para alcance y vistas |

No subir: `tests/ig-insights/*`, `client-portal-omnichannel/` (fuente), `package-lock.json`, `node_modules/`. Los bundles viejos de
`omnicliente/assets/` quedan en PROD (no se borran).

**Verificación desde afuera:** `GET ig-metrics` sin cabecera → 403; con la credencial → 200 y `insights_status`; `reel-diario/token-check`
con su credencial → `{"ok":true}`; el portal abre Marketing IG y Canales sin errores de consola.

**Reversa:** volver a subir los tres PHP y el `index.html` del respaldo (el `index.html` viejo apunta a los bundles viejos, que siguen
en el servidor), desactivar el flujo de n8n y, si se quiere, quitar la constante. Las tablas nuevas pueden quedar: nadie más las lee.

## 8. Pendientes conocidos

- `ycloud_api_key` (WhatsApp) sigue viajando al navegador igual que lo hacía `bot_token`, y la auditoría vieja conserva tokens de
  Instagram anteriores en claro (los registros nuevos ya no). Tarea aparte.
- Revisión B por el homólogo en Codex (Forja/Vigía) antes del despliegue (tarea C3).
- El embudo DMs → leads → demos no tiene fuente conectada; se puede armar con los mensajes del canal de Instagram y el CRM.
