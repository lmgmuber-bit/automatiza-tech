# Claude Project Memory

<!-- AI-MEMORY-WORKFLOW:START -->
## Claude Project Memory

Antes de trabajar, crea una rama nueva. Usa y manten actualizados `CLAUDE.md`, `AGENTS.md`, `OPENCODE.md`, `docs/AGENTS.md` y `.github/copilot-instructions.md` para que Claude, Codex, OpenCode y GitHub Copilot compartan contexto y ultimos cambios. Existe un job de sincronizacion de documentacion que actualiza/sincroniza archivos de agentes, `CLAUDE.md`, `OPENCODE.md`, `AGENTS.md` y `.github/copilot-instructions.md`; respeta ese flujo y evita duplicar informacion que el job ya mantiene. Revisa tambien `docs/`, `docs/Master`, `doc/`, `documentation/`, `wiki/` o carpetas equivalentes, porque algunos proyectos guardan su documentacion principal en `docs/Master`. Crea archivos de memoria si faltan y son utiles. Actualiza memoria y documentacion cuando cambien arquitectura, comandos, dependencias, flujos, estructura, reglas o decisiones importantes. No guardes secretos. Al terminar, resume cambios, pruebas y estado Git; no hagas merge sin permiso. Espera confirmacion del usuario para cerrar y sugiere crear PR o merge hacia `main`/`master`/`develop`/`dev` segun corresponda.

### Contexto compartido

- Boveda maestra: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`
- Protocolo global: `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\30-Agent-Protocols\Multi-Agent-Workflow.md`
- Job de documentacion: `ClaudeCodexMemorySync` corre cada 60 minutos en modo seguro (`-NoOverwriteExisting`) sobre `C:\wamp64\www\automatiza-tech` y `C:\Users\luis_\Documents\Codex\AI-Memory-Vault`. Incluye `CLAUDE.md`, `AGENTS.md`, `docs/AGENTS.md`, `OPENCODE.md` y `.github/copilot-instructions.md`; crea faltantes y registra diferencias, pero no sobrescribe memorias divergentes. Cada agente sigue siendo responsable de actualizar la memoria compartida al cerrar tareas importantes.
- Claude debe revisar este archivo antes de modificar el proyecto.
<!-- AI-MEMORY-WORKFLOW:END -->

<!-- AT-PROFESSIONAL-STANDARD:START -->
## Estándar profesional transversal

Todos los agentes deben aplicar `Docs/ENGINEERING_STANDARDS/PROFESSIONAL_ENGINEERING_STANDARD.md` y el gate proporcional de `Docs/ENGINEERING_STANDARDS/quality-gates.yaml`, además de las reglas específicas del proyecto. El estándar cubre ownership, flujo, arquitectura, código, testing, CI/CD, Git/PR, seguridad, observabilidad, entrega al cliente y cierre con evidencia.
<!-- AT-PROFESSIONAL-STANDARD:END -->

<!-- AT-FTP-HANDOFF:START -->
## Entrega obligatoria para FTP

Al finalizar cualquier tarea que cambie archivos desplegables, incluir siempre una lista exacta para Luis con: ruta local, destino relativo en PROD, clasificación `OBLIGATORIO` u `OPCIONAL`, orden de subida cuando importe y archivos temporales o sensibles que no deben subirse. Distinguir claramente cambios locales de cambios ya desplegados; nunca afirmar que algo está en PROD sin evidencia. **Claude puede desplegar a Hostinger por SSH/SFTP** (paramiko, credenciales por etiqueta del archivo de claves; deploys hechos el 2026-07-27 y el 2026-09-06): con autorización explícita de Luis, reconocer en modo lectura, respaldar lo que se sobrescriba, subir, verificar por HTTP desde afuera y recién entonces reportar. Procedimiento en `Docs/ORCHESTRATION/CONEXIONES-Y-CREDENCIALES.md` §3.3.
<!-- AT-FTP-HANDOFF:END -->

<!-- AT-ORCHESTRATION:START -->
## Conexiones y credenciales

Antes de decir "no tengo acceso", "no puedo conectarme a n8n" o "no tengo credenciales", **verifica**. La fuente canónica es `Docs/ORCHESTRATION/CONEXIONES-Y-CREDENCIALES.md`: trae el estado real de cada conexión (n8n, Higgsfield, Apify, Drive, budgetpixel) por agente y el comando exacto de chequeo. Asumir que algo no está disponible ya causó trabajo duplicado y respuestas equivocadas a Luis.

Las credenciales viven **fuera del repo**, en `C:\Users\luis_\OneDrive\Documentos\APIS KEy\APIS KEY.txt`. Léelas solo si las necesitas y **nunca copies un valor al repositorio, a un `.env`, a un config, a documentación ni a un log**. Si hay que armar un archivo de configuración, entrégale la plantilla a Luis y que él pegue los valores; un agente no escribe secretos.

## Orquestacion multiagente AT

- Fuente canonica: `Docs/ORCHESTRATION/README.md`.
- Claude es el orquestador principal por defecto y Codex el suplente; solo uno puede estar activo mediante un lease valido.
- Antes de ejecutar, cada agente registra clase, riesgo, incertidumbre, reversibilidad y perfil/modelo segun `Docs/ORCHESTRATION/model-routing.yaml`.
- Codex debe seguir `Docs/ORCHESTRATION/CODEX_ORCHESTRATOR.md` cuando tome el relevo.
- Claude debe seguir `Docs/ORCHESTRATION/CLAUDE_ORCHESTRATOR.md` como orquestador principal.
- Para iniciar o recuperar la orquestación con contexto completo, leer `Docs/ORCHESTRATION/CLAUDE_ORCHESTRATOR_PROMPT_FINAL.md`.
- Estado actual del relevo: `Docs/ORCHESTRATION/current-handoff.yaml`.
- Continuidad cruzada: `Docs/ORCHESTRATION/TASK_TAKEOVER.md`; cualquier agente puede retomar una tarea bloqueada tras reasignar ownership.
- Los chats externos no se comunican por si solos: usar tickets/ledger compartido o a Luis como puente.
- El tablero consume las fuentes canonicas; no crea una segunda verdad permanente.
<!-- AT-ORCHESTRATION:END -->

<!-- AT-METHOD:START -->
## Método AT y casos de uso

Para planificar, cotizar, ejecutar, entregar o retomar trabajo con clientes, leer `Docs/METODO_AT/README.md`. Las fuentes canónicas son `Docs/METODO_AT/APLICACION_DEL_METODO_AT.md` y `Docs/METODO_AT/SERVICIOS_Y_CASOS_DE_USO.md`. Para marketing, nichos y contenido de Instagram/Reels, usar `Docs/MARKETING/2026-07-22-AT-ESTUDIO-NICHOS-Y-ESTRATEGIA-REELS-INSTAGRAM.md`. Distinguir siempre capacidades actuales comprobadas de trabajo en curso y roadmap.
<!-- AT-METHOD:END -->

<!-- AT-VIDEO-RULES:START -->
## AutomatizaTech — Reglas obligatorias para videos/Reels

Estas reglas aplican a cualquier video, Reel Diario, Reel programado, comercial, Story, post audiovisual o prompt generado desde Codex/Claude/n8n para AutomatizaTech.

1. Logo AT obligatorio y discreto: usar solo el logo real de referencia de AutomatizaTech. Debe ir en la esquina superior izquierda como watermark profesional, muy pequeno, sutil y translucido: maximo 4% del ancho del frame, opacidad aproximada 35-45%, reconocible pero nunca dominante. Nunca ubicarlo al centro, nunca agrandarlo, nunca inventar un logo generico ni variar el diseno. Si la herramienta generativa no respeta el tamano o fidelidad, agregar/corregir el overlay real en post con ffmpeg.
2. Texto visible en espanol de Chile: todo texto en pantalla, interfaces, chats, emails, notificaciones, botones, calendarios, dashboards y celulares debe pedirse en espanol (Chile), nunca en ingles. Si Veo/Flow deforma texto menor, no regenerar solo por eso si la pieza es publicable; pero el prompt siempre debe pedir espanol.
3. Antes de generar o publicar, revisar prompts de video para confirmar que incluyan estas reglas. Si un prompt contradice estas reglas, corregirlo primero.
4. Cierre estándar AT para Reels: agregar una sola vez al final del Reel principal el clip completo `N8N/reel-diario-media-worker/assets/outro-at-10s.mp4` (~10s, 720x1280, con audio y CTA `Agenda tu diagnóstico gratis en automatizatech.cl`). Estructura final: `parte_1 + parte_2 + outro-at-10s.mp4`. No insertar el cierre entre partes ni dentro del bonus/Story. No usar el recorte de 3.2s.
5. Log de publicaciones Reel Diario: n8n Plan B debe actualizar el CSV existente `IG-AT/Reel-Diario/Reel-Diario-Log-Publicaciones.csv` (`file_id=1NpJf8VVTiqcN5LdGFfLdi0T9qQ9iGCRw`) con update real del mismo archivo. No crear CSVs duplicados.

Bloque recomendado para prompts:

```text
MANDATORY: The provided reference image is the ONLY logo for AutomatizaTech. You MUST superimpose the EXACT "AT" logo (navy blue circle, white "AT" letters, teal arrow) from the reference image in the top-left corner of the frame. The logo must be clear, sharp, very small, and translucent like a professional TV watermark. Never invent a different logo design or a generic icon.

MANDATORY: All on-screen text, UI elements, app interfaces, phone screens, emails, notifications, and any visible written content in every video or image must be in Spanish (Chile), never in English. This applies to chat bubbles, dashboards, buttons, calendars, and any readable text shown on any screen.
```
<!-- AT-VIDEO-RULES:END -->

## Punteros de proyecto (estado vivo)

- **CumpleClick — PROD es `https://cumpleclick.com/app/` (desde 2026-08-29); `automatizatech.cl/cumpleclick/` es pre-producción.** Código de PROD = línea `codex/baby-shower-predicciones` (`369da38`), no `feat/cumpleclick-sala-ayudantes`. Fiestas reales del domingo 13-sep-2026: `?p=isidora-reino-de-hielo` (Hielo) y `?p=luciano-spidey` (Spidey), PIN de galería 1234; los slugs solo se cambian con script (`database/renombrar-slugs.php`), nunca a mano.
- **CumpleClick — Juego 3D "Tu Cumple en 3D" EN PROD (2026-09-06):** `https://cumpleclick.com/app/juego/?p=<slug>` (también en pre-producción `automatizatech.cl/cumpleclick/juego/`) (temática por la fiesta; `?tema=heroes` fuerza Aventura Arácnida v2). Backend `sala.php`/`lib.sala.php` + migración 014 desplegados por SSH (rama `feat/cumpleclick-sala-ayudantes`, sin merge). Código del juego en repo aparte `C:\wamp64\www\tucumple-repo\` (`main`), export limpio `C:\wamp64\www\juego-prod\`. El kiosco es la app principal: botón "🎮 Aventura 3D" en su bienvenida (temáticas con mundo 3D) abre el juego con `&kiosco=1` y el juego vuelve al kiosco desde pausa/final. PIN de galería `1234` en todas las fiestas de PROD y por defecto en fiestas nuevas (admin, LOCAL) y en el juego. Manifiesto: `CumpleBooth/docs/FTP-MANIFEST.md` (sección DESPLEGADO 2026-09-06).
- **CumpleClick — Carteles QR por fiesta EN PROD (2026-09-06):** `https://cumpleclick.com/app/carteles.html?p=<slug>`, botón **Carteles QR** en cada fiesta del admin. Imprime a escala real (`@page` en mm, A6..A4 y medida a pedido) los avisos de galería (con PIN), juego 3D e invitación, con el fondo de la temática a página completa. Detalle y lista subida en `CumpleBooth/docs/FTP-MANIFEST.md` (sección 2026-09-07). Pendiente: medidas de los soportes acrílicos que compre Luis.
- **CumpleClick — DOS JUEGOS POR FIESTA + menú + tabla de posiciones EN PROD (2026-09-07):** `https://cumpleclick.com/app/juego/?p=<slug>` ya **no abre el juego directo: abre un menú** (el juego de siempre pasó a `mundo.html`, misma carpeta). 🔴 **Esa URL está impresa en los carteles QR de las fiestas del 13-sep: no se puede cambiar nunca.** Antes de tocar cualquier cosa del juego, correr `scratchpad/urls-criticas.py antes` y después `despues`; compara las 10 direcciones en uso y avisa si alguna cambió de estado. Qué juegos ofrece el menú **depende de la temática** (lo decide `puntajes.php`): `hielo` tiene dos, `spidey` y `heroes` uno, las otras siete ninguno. Tabla de posiciones por **medallas y no por suma de puntos** (dos juegos con escalas distintas: sumando, uno decide la fiesta entera). Migración 018 (`cc_puntajes`), 20 versiones. Todo el detalle, con las decisiones y lo que NO está probado, en `CumpleBooth/docs/FTP-MANIFEST.md` (sección 2026-09-07 noche).
- **CumpleClick — "El Festival de las Estrellas" (juego de Codex) EN PROD (2026-09-07):** `app/juego/festival/`, temática **hielo/Frozen** (fiesta de Samantha). Origen `C:\wamp64\www\tucumple-codex\` rama `codex/festival-estrellas` commit `0b68f50`, **original intacto**. 🔴 **El servidor no conoce `.mjs`**: sin el `AddType text/javascript .mjs` del `.htaccess` propio de `festival/`, los once módulos se rechazan y la pantalla queda negra sin error visible. ⚠️ **Nunca ha corrido en tablet física**; sus 45 fps no están certificados. Rollback: `~/respaldos/juego-antes-festival-20260907.tar.gz`.
- **CumpleClick — ojo con `spidey` vs `heroes`:** son **dos temáticas de fiesta distintas** en el admin (`Aventura Arácnida en 3D` y `Misión 3D`), pero **comparten el mismo mundo 3D**: el motor mapea las dos a su tema interno `heroes` (`juego/game/temas/index.js` línea 20), y por eso los recursos arácnidos viven en `juego/temas/heroes/`. Un agente nuevo que vea esa carpeta puede creer que Spidey no tiene recursos, o construir sobre la temática equivocada.
- **CumpleClick — Finanzas EN PROD (2026-09-07):** pestaña **Finanzas** en el admin. Inversión + ingresos con gráficos; migración 017 (`cc_finanzas`). Los ingresos de las fiestas **no se copian**: se leen del cobro de cada ficha con el mismo `cb_party_billing()` que arma el comprobante, para que finanzas y la boleta no puedan decir números distintos. Lo regalado se cuenta aparte y no como ingreso cero.
- **CumpleClick — Aceptación de T&C + firma electrónica simple (2026-09-05, LOCAL, rama `feat/cumpleclick-aceptacion-terminos`):** blueprint, marco legal (Ley 19.799 FES vs FEA, 19.496, 19.628/21.719), pendientes `TODO-LUIS` y lista FTP en `Docs/BLUEPRINTS/CUMPLECLICK-ACEPTACION-TERMINOS-Y-FIRMA.md`. Una fiesta ya no se activa sin aceptación firmada (`cc_plan_acceptances`) o exención explícita; textos legales en `CumpleBooth/public/legal/` son borradores pendientes de abogado. Nada desplegado.

- **Radiografía AT 2026-09-04 (estudio exhaustivo, LEER antes de priorizar trabajo comercial):** `Docs/2026-09-04-RADIOGRAFIA-AT-ESTUDIO-EXHAUSTIVO.md` + vault `10-Projects/2026-09-04-AT-Radiografia-Estudio-Exhaustivo.md`. Embudo real ~2 leads/mes; sin analítica; landings huérfanas; metadatos viejos pisan nuevos (`functions.php:1149`); Pixel sin consentimiento; 4 tablas de CRM sin sincronía; Reel Diario detenido desde 26-ago; marca con homónimos; plan 30/60/90 y decisiones pendientes de Luis.

- **Incidente de seguridad 2026-08-26 (LEER antes de tocar ARGOS, secretos o el Reel Diario):** `Docs/2026-08-26-INCIDENTE-SEGURIDAD-N8N-Y-REEL-DIARIO.md`. Cerró tres agujeros (bypass de `WP_DEBUG` que dejaba público el listado de errores de ARGOS, `n8n_test_token` que permitía disparar envío masivo de correos, API keys en texto plano en nodos de n8n) y rotó cinco secretos. Contiene además la regla obligatoria de redacción al buscar en el repo, cómo generar el token de Instagram para esta app (`graph.instagram.com`, no el flujo de Page Token), y el workaround del bloqueo de `settings` al escribir workflows por API.
- **Portal OmniCliente — rediseño visual EN PROD (2026-06-01):** ver `Docs/MASTER/06_PORTAL_OMNICLIENTE_FRONTEND.md` y vault `10-Projects/Portal-OmniCliente-Redseno.md`. Rama `feat/inbox-premium-ui` (PR sin mergear). 🔴 Deploy: subir helpers `at-*.php` + `lib/at-auth-middleware.php` ANTES que el resto, o fatal `at-path-safe.php`.
- **Instagram CM AT (automatizar IG con Higgsfield):** plan en vault `10-Projects/Instagram-CM-AT.md` + adaptación `2026-05-29-Adaptacion-Master-Fusion-a-Plan-IG-CM.md` + config canal `Config-Canal-Instagram-Omnichannel.md`. Estado: pendiente config del usuario (upgrade Higgsfield + conectar canal IG en Meta).
- **Reel Diario Automático — Plan B n8n (estado 2026-07-09):** workflow remoto `AT_Reel_Diario_Checkpoints_PlanB` (`mQrDSdfIkNy9LIW8`) está `active=true` y los Schedule Trigger reales `12:30`, `19:00` y `21:30` están habilitados (`disabled=false`). `Webhook Dia-Test` y `Manual Dia-Test` quedan para pruebas controladas.
- **Pruebas Plan B confirmadas:** ejecución n8n `248618` (`Dia-2`, `publish=false`, `force=true`) pasó Drive -> frames -> QA Anthropic Vision `APPROVE/high` -> render MP4 -> dry-run sin publicar. Luego `Dia-3` se probó por webhook con `publish=false`, `force=true`, QA por fallback OpenAI Vision `APPROVE/high`, seleccionó `parte_1 + parte_2`, renderizó y terminó: "QA y render completados; no se publicó." No repetir ni guardar tokens de webhook/worker en archivos o chat.
- **Checkpoints locales Claude neutralizados:** `at-reel-diario-checkpoint-1230`, `at-reel-diario-checkpoint-1900` y `at-reel-diario-checkpoint-2130` fueron convertidos en no-op el 2026-07-09; cada carpeta conserva backup `SKILL.md.bak-20260709-planb-n8n`. El brief diario `at-reel-diario-brief` de 9:10 sigue activo porque concentra estrategia creativa, scraping/búsqueda y prompts Flow.
- **Publicación real Dia-3:** `249310` falló por bug de binario y se corrigió `Attach Render Context` + `responseFormat=file`; `249336` fue bloqueada por QA por logo AT grande. Luis autorizó bypass temporal solo para probar publicación. Se agregó `ignore_qa=true` solo para `test_mode`, se corrigió credencial WP `AT Reel Diario WP Secret v2`, y Dia-3 publicó correctamente desde n8n con media id `17946965511220502`.
- **Hotfix caption Plan B:** Dia-3 salió con el prompt completo como caption porque la carpeta solo tenía `prompt-dia-3.txt`. El workflow n8n ya fue corregido: `Prepare Lock` extrae solo `CAPTION INSTAGRAM` + `HASHTAGS`; si no encuentra caption explícito, usa fallback genérico y no publica briefs/prompts/instrucciones.
- **Republicación Dia-3 limpia:** Luis borró manualmente el Reel con caption incorrecto. Se republicó desde n8n con el extractor de caption corregido; nuevo media id `17985703272009675`.
- **Publicación Dia-4:** Luis pidió publicar manualmente el contenido de `Dia-4` el 2026-07-11. n8n Plan B publicó con QA normal/fallback OpenAI, seleccionó `parte_1 + parte_2`, sin `ignore_qa`; media id `17974856358119315`.
- **Dia-4 corregido y publicado:** la republicación forzada inicial del 2026-07-13 (`media_id=18134328133592877`) fue incorrecta porque n8n tomó `DIA4_BONUS.mp4` como `parte_1`; Luis la borró manualmente. Se corrigió Plan B para clasificar clips por nombre y bloquear que bonus/repuesto reemplace clips del Reel principal. Luis confirmó que el logo de esquina ya pasa por su aprobación manual antes de subir a Drive, así que el QA ya no rechaza solo por logo AT grande/imperfecto; lo reporta como informativo. Republicación correcta Dia-4 publicada desde n8n con `parte_1=Dia4 1era Parrte.mp4`, `parte_2=Di 4 2da parte.mp4`, `repuesto=DIA4_BONUS.mp4` fuera del Reel; nuevo `media_id=18159350482472391`.
- **Regla definitiva bonus/Reel:** cuando hay tres videos, el Reel principal usa solo `parte_1 + parte_2` (los dos clips principales/no-bonus). Cualquier archivo cuyo nombre diga `bonus`, `story`, `historia`, `repuesto` o `extra` queda excluido del Reel principal y, si aprueba QA y `publish=true`, n8n lo publica después como Historia (`media_type=STORIES`).
- **Cierre estándar AT para Reels:** desde 2026-07-14, el worker de medios debe agregar una sola vez al final del Reel principal el clip completo `N8N/reel-diario-media-worker/assets/outro-at-10s.mp4` (~10s, 720x1280, con audio y CTA `Agenda tu diagnóstico gratis en automatizatech.cl`). Estructura final: `parte_1 + parte_2 + outro-at-10s.mp4`. No insertar el cierre entre partes ni dentro del bonus/Story. No usar el recorte de 3.2s porque puede cortar el logo; el banner turquesa anterior queda prohibido salvo instruccion expresa de Luis. Producción requiere redeploy del worker `n8n/reel-media-worker` para tomar el asset.
- **Log canonico de publicaciones Reel Diario:** desde 2026-07-13, n8n Plan B actualiza el CSV existente `IG-AT/Reel-Diario/Reel-Diario-Log-Publicaciones.csv` (`file_id=1NpJf8VVTiqcN5LdGFfLdi0T9qQ9iGCRw`) después de cada intento real de publicación: éxito, fallo de contenedor/publicación o rechazo QA. No crea CSVs nuevos; descarga el archivo, actualiza/agrega la fila por `Dia`, acumula `Intentos / Checkpoints` y sube el mismo archivo por Drive API `PATCH uploadType=media`. No loguear skips operativos como `already_published`, `fresh_lock` o `folder_not_found`.
- **Regla Plan B:** los schedules automáticos mantienen QA normal; no usar `ignore_qa` salvo instrucción explícita de Luis para una prueba manual. El brief diario `at-reel-diario-brief` sigue en Claude; los checkpoints locales Claude siguen no-op.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
