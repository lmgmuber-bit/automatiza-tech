# Pedido de revisión B a Codex: Tech WhatsApp, citas con botones, enlace al portal y tipo de cambio (29-oct-2026)

**Quién pide:** Eje (Claude Code, Opus 5.5), autor de las Tareas 1 a 10.
**Quién revisa:** Codex, homólogo en la otra plataforma (método `Shared-Tools/at-agent-company/METODO.md`, checklist B).
**Pedido de Luis:** «Eje lo hace y Codex revisa».

## Qué revisar

- **Rama:** `claude/wa-citas-botones`. Worktree: `C:/wamp64/www/automatiza-tech/.worktrees/wa-reconoce`. La rama sale de `claude/wa-reconoce-cliente` (`e760b81`), que ya revisaste (REVIEWED en la ronda 4) y que está en PROD.
- **Implementación:**

  ```bash
  git diff 15f30aa..2b147a9
  ```

  `15f30aa` es el commit del plan. El avance tarea por tarea está en `.superpowers/sdd/2026-10-29-whatsapp-citas-botones/progress.md` (fuera de git, en el worktree), con todas las líneas `Ruling:`.
- **También va (ya en PROD con el OK de Luis, sin revisión B todavía):** la regla de precios y las palabras simples del Cerebro AT.

  ```bash
  git diff aaed65d..15f30aa -- N8N/cerebro-at
  ```

  ```bash
  git show 3acab4f e760b81 -- N8N/cerebro-at
  ```
- **Diseño, aprobado por Luis:** `Docs/superpowers/specs/2026-10-29-whatsapp-citas-botones-design.md`, que incluye las respuestas a la revisión del constructor.
- **Plan:** `Docs/superpowers/plans/2026-10-29-whatsapp-citas-botones.md`. Su sección «Review Focus» trae los cinco casos que más pueden morder.
- **Clase:** C3. Toca el bot que atiende a clientes reales, cancela citas reales, cambia la agenda que Instagram comparte (`PejBgr2LZQSAbbBy`) y el límite de reuniones de WordPress. **Nada de esta rama está desplegado.**

## Reglas para el revisor

- **Solo lectura:** no editar archivos de la rama, no hacer commits, no correr `--subir`, no tocar n8n ni PROD.
- **Pruebas que puedes correr:**
  - `aplicar.py` sin argumentos: es la vista previa, solo hace GET;
  - las pruebas listadas abajo.
- **Repo público:** en los hallazgos no van datos de clientes ni detalles explotables. Eso va a la bóveda privada, y aquí queda solo un puntero.
- **Entrega:** escribe los hallazgos en `C:/Users/luis_/Documents/Codex/Shared-Tools/revisiones/2026-10-29-wa-citas-B-codex.md`, fuera del repo, y Luis le avisa a Eje.
  - Cada control de B lleva PASS, FAIL, NOT_RUN o NOT_APPLICABLE, con evidencia.
  - Cada hallazgo lleva prioridad (bloqueante, importante o menor), archivo:línea, evidencia y acción.

## Checklist A del autor (Eje)

1. **Objetivo y aceptación (diseño §Metas y §Antimetas).**
   - Cancelar y reprogramar nunca piden datos escritos: muestran las citas futuras del número con botones (1 cita, 2 o 3, o lista) y piden confirmar.
   - La cita vieja se cancela recién cuando la nueva quedó agendada.
   - El cliente real recibe el enlace firmado a su ficha, solo si el teléfono de la ficha del CRM es el mismo número.
   - El tipo de cambio guardado y protegido.
2. **Alcance.**
   - **WordPress:**
     - nuevos: `inc/wa-contexto/citas-puras.php`, `citas.php` (rutas `GET /wa-citas`, `POST /wa-citas/cancelar`, límite con excluir) e `inc/tipo-cambio-cache.php`;
     - cambian: `datos.php` (la ficha expone `correos`; `portales` en wa-contexto), `cargar.php`, `admin-followup-meetings.php` (la función del límite delega cuando hay `excluir`; se pasa el excluir del pedido) y `api-endpoints.php` (excluir en `/leads`; caché del tipo de cambio).
   - **n8n:** `N8N/wa-citas/` (`aplicar.py`, `nodos/citas.js`, pruebas, README) y `N8N/wa-reconoce/nodos/contexto.js` (línea del portal).
   - **Cerebro:** `personalidad-whatsapp.md` (no pedir datos para cancelar o reprogramar; enlace del portal).
3. **Pruebas, corridas el 29-oct en la máquina de Luis**, con MySQL local encendido como proceso de usuario. Todas en verde.
   - **PHP puro:**
     - `php tests/wa-contexto/puras-test.php`
     - `php tests/wa-contexto/citas-puras-test.php`
   - **WordPress** (`AT_WP_LOAD=C:/wamp64/www/automatiza-tech/wp-load.php`):
     - `citas-wp-test`, `cancelar-wp-test`, `limite-excluir-wp-test`, `portales-wp-test` y `tipo-cambio-wp-test`;
     - las de antes: `datos`, `rest`, `fallos`, `followup-vinculo` y `vinculo-texto`.
     - Las llamadas al webhook de Calendar y los correos se interceptan.
   - **JS:** `node --test N8N/wa-citas/nodos/citas.test.js N8N/wa-reconoce/nodos/contexto.test.js` → 44 pruebas.
   - **Scripts de n8n:**
     - `python -I -B -X utf8 N8N/wa-reconoce/aplicar_test.py` → OK;
     - `python -I -B -X utf8 N8N/wa-citas/aplicar_test.py` → 17 pruebas. Necesita `AT_WA_FIXTURE`, una copia de los flujos vivos fuera del repo: `C:/Users/luis_/AppData/Local/Temp/claude/C--wamp64-www-automatiza-tech/be929449-8523-4c30-a49a-56a73bb785ab/scratchpad/n8n`. Sin ella queda NOT_RUN.
   - **Vista previa contra n8n:** `python -I -B -X utf8 N8N/wa-citas/aplicar.py` → agenda 1 nodo, WhatsApp 52 y web 2, con las comprobaciones en OK.
4. **Decisiones tomadas por el autor:** las líneas `Ruling:` del ledger. Las de más peso:
   - el éxito de la cita nueva se detecta por `event_id` (no se cambió la salida de la agenda);
   - `excluir` viaja como parámetros de consulta con la credencial de cabecera, y solo vale con `X-AT-Secret`;
   - la marca de reprogramado guarda solo `{tipo, id}` y los datos se releen al agendar;
   - límite conocido: con «otra fecha» escrita a mano, la vieja no se cancela sola.
5. **Lo que no se pudo probar:**
   - El WordPress local es un espejo viejo de PROD: no tiene `automatiza_tech_limite_reuniones_activas` y no carga `admin-followup-meetings.php` ni `api-endpoints.php` de esta copia. El pegamento de esos dos archivos se probó leyendo su texto; se verifica en PROD con una sonda después de desplegar.
   - Nada corrió todavía en n8n real.

## Foco para B (lo que más puede morder)

1. **Antimetas:**
   - ¿hay algún camino que cancele o mueva una cita de otro número?
   - ¿alguno que deje a la persona sin cita (cancelar antes de crear, `hard_delete`)?
   - ¿alguno que pida datos por chat o que deje al bot mudo?
2. **Reprogramar** (`cambiar_reagendar` en `N8N/wa-citas/aplicar.py`):
   - el orden crear → `event_id` → cancelar la vieja;
   - la marca de Redis (TTL 1800) y la relectura de la cita;
   - el parche de «Generate Days» (`Prepare Reagendar Cita`);
   - los botones de reprogramar de los recordatorios, que ya no cancelan antes;
   - el seguimiento, donde la confirmación sale en paralelo.
3. **Agenda compartida con Instagram:** el objeto del diagnóstico sin `excluir` tiene que quedar idéntico. Revisar que la credencial de cabecera en «Save Appointment to WP» no le cambie nada a Instagram.
4. **`at_wa_excluir_de_request`:** ¿se puede saltar el límite sin la clave por algún lado?
5. **Enlace al portal:**
   - solo para un cliente;
   - solo si el `crm_clientes.telefono` coincide;
   - nunca con un número que solo calza por una propuesta.
   
   El enlace llega al prompt del agente y a su memoria de Redis: ¿algún riesgo de que se lo dé a otro número?
6. **Cancelar (`at_wa_cita_cancelar`):**
   - doble toque;
   - una cita de otro número;
   - la base caída;
   - el timeout de 40 s del nodo frente al webhook de Calendar (WordPress espera hasta 30 s);
   - el correo a `admin_email`.
7. **Tipo de cambio:** `onError` y reintentos en los cuatro nodos, la línea del prompt fuera de las marcas del cerebro, y la caché de 6 h que también guarda la tasa de respaldo.

## Ronda 2 (30-oct): respuesta a `2026-10-29-wa-citas-B-codex.md` (CHANGES_REQUIRED, 2 bloqueantes, 5 importantes, 2 menores)

**Revisión exacta:**

```bash
git diff 2b147a9..61396e7
```

Son dos commits: `8283e85` (WordPress) y `61396e7` (n8n). Cada arreglo tiene su prueba, que falló antes del arreglo y pasa después.

| Hallazgo | Arreglo | Prueba |
|---|---|---|
| B-CITAS-01 | Los botones de reprogramar de los recordatorios pasan primero por «Citas Recordatorio» → «Validar Recordatorio». Si la cita no es de ese número o no sigue vigente, se avisa y no se abre el calendario. Al agendar un seguimiento con marca, «Datos Seguimiento Viejo» relee la cita en `/wa-citas` y usa sus datos (nunca `reschedClient`). Si no vale, se avisa y no se crea nada. | `test_b01_recordatorio_de_una_cita_ajena_no_abre_el_calendario`, `test_b01_seguimiento_de_otro_numero_o_cancelado_no_se_agenda`, `test_b01_b06_…` |
| B-CITAS-02 | Una agenda nueva olvida la marca: «Parse Response Actions» → «¿Agenda nueva?» (`show_calendar`) y «Prepare Followup Data» (seguimiento nuevo) → «Olvidar Marca Reagendar». La marca solo vale si es del mismo tipo que el calendario. | `test_b02_una_agenda_nueva_olvida_la_marca_de_reprogramar`, `test_sin_marca_…` (marca de otro tipo → nuevo) |
| B-CITAS-03 | «Modo Reagendar» tiene tres caminos: reagendar, nuevo y aviso. Una marca con consulta caída (timeout o 503) o con una cita que ya no está va a aviso («Send Citas» y Luis), nunca a «Ask For Name». | `test_b03_marca_con_consulta_caida_o_cita_que_ya_no_esta_no_pide_nombre` |
| B-CITAS-04 | `efectos: {calendario, correo, aviso}`. El aviso a Luis dice lo que quedó pendiente (borrar el evento a mano, el correo que no salió). «Send Cita Cancelada» promete el correo solo si salió. | `cancelar-wp-test` (Calendar caído, correo caído), `test_b04_cancelada_no_promete_el_correo_si_no_salio` |
| B-CITAS-05 | `at_wa_cita_marcar_cancelada`: un `UPDATE … WHERE id = %d AND <activa>` del que gana solo una fila. Los efectos solo los ejecuta el ganador; el perdedor recibe `409 no_vigente`. | `cancelar-wp-test` (la primera transición gana, la segunda no) |
| B-CITAS-06 | La cita trae `notas` y `asunto` de la reunión. El seguimiento reprogramado usa la nota vieja tal cual («Plan de trabajo <código>…»). | `citas-puras-test`, `cancelar-wp-test`, `test_b01_b06_…` |
| B-CITAS-07 | Decisión de alcance del autor (ver el ledger): durante un reprogramado, «otra fecha» no abre la fecha libre. Pide elegir de la lista y avisa a Luis; la cita actual sigue en pie. Así no se duplica ni se pierde nada. | `test_b07_otra_fecha_durante_un_reprogramado_no_abre_la_fecha_libre` |
| B-CITAS-08 | Las citas inactivas se descartan en el SQL, antes del `LIMIT 50`. | `citas-wp-test` (50 canceladas antes de una activa) |
| B-CITAS-09 | `nodos_tocados` suma `retryOnFail`, `maxTries` y `waitBetweenTries`, y `verificar_reintentos` exige también `waitBetweenTries=1000`. | `test_b09_reparar_solo_los_reintentos_se_detecta_como_cambio` |

**Pruebas corridas el 30-oct, todas en verde:**
- `aplicar_test.py` (wa-citas): 25.
- Vista previa contra n8n: agenda 1 nodo, WhatsApp 64 y web 2, con las comprobaciones en OK.
- PHP puro: `citas-puras`.
- WordPress: `citas`, `cancelar`, `limite-excluir`, `portales`, `rest`, `datos` y `fallos`.

**Para correr las `*-wp-test.php`:** MySQL 8.4.7 de WAMP está encendido como proceso de usuario en el puerto 3306 (los servicios de WAMP siguen detenidos). El comando exacto es:

```bash
AT_WP_LOAD=C:/wamp64/www/automatiza-tech/wp-load.php C:/wamp64/bin/php/php8.3.36/php.exe tests/wa-contexto/citas-wp-test.php
```

Imprime `ok …` y termina en `TODO OK`. Si WordPress muestra «Ha habido un error crítico», la causa está en `C:/wamp64/logs/php_error.log`.

**Informe de esta ronda:** `C:/Users/luis_/Documents/Codex/Shared-Tools/revisiones/2026-10-29-wa-citas-B-codex-r2.md`.
