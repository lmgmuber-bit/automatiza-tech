# Finanzas AT: pagos reales, medidor de créditos y agente Balance

Fecha: 21-oct-2026 · rama `claude/finanzas-balance` (worktree `C:/wamp64/www/automatiza-tech/.worktrees/retomar-eje`). **Estado: construido y probado en local; sin desplegar y sin autorización de PROD.** Diseño y decisiones de Luis: `Docs/superpowers/specs/2026-10-21-finanzas-balance-design.md`.

Esta guía es pública como el resto del repo: **no lleva montos reales ni nombres de clientes**. Todos los números de los ejemplos son inventados.

## Para qué sirve

Luis quiere ver **cuánto gasta AutomatizaTech cada mes**, con cada pago real, y un **medidor de cada saldo prepagado** (por ejemplo, cuánto de una recarga ya se usó en imágenes y videos). Lo lleva un agente de finanzas, **Balance** (`at-balance-claude` / `at-balance-codex`), con la regla de que los demás agentes le avisan lo que gastan (ver la última viñeta de «Orquestacion multiagente AT» en `CLAUDE.md` y `AGENTS.md`).

**Diferencia con lo que ya existía.** El módulo «💰 Finanzas AT» de wp-admin ya tenía un **catálogo de suscripciones** (clase `OmniATFinanceController`, tabla `{prefix}omnichannel_at_expenses`): proveedor, monto, moneda y periodo mensual, anual o único, con el gasto mensual *proyectado*. Eso no es un registro de pagos. El módulo nuevo guarda **los pagos reales** (`{prefix}at_fin_movimientos`) y las cuentas prepagadas (`{prefix}at_fin_cuentas`).

- El catálogo **no cambió de comportamiento** y vive en la pestaña «Suscripciones».
- Un pago real puede enlazarse a una suscripción del catálogo (`gasto_id`), pero es opcional.
- Un solo puente entre los dos: al confirmar «renovación pagada» en una suscripción, se registra además un `pago` con la referencia `renov-<id>-<AAAA-MM>`, así repetir el clic no lo duplica. Si ese registro falla, la renovación queda guardada igual y el panel avisa para anotarlo a mano.

## Los cuatro tipos de movimiento

| Tipo | Qué registra | Cuenta | Suma al gasto del mes | Efecto en el saldo |
|---|---|---|---|---|
| `pago` | Gasto directo: hosting, una suscripción, una licencia | opcional | Sí | Ninguno |
| `recarga` | Dinero que se carga a una cuenta prepagada | obligatoria | Sí | Sube |
| `consumo` | Lo que se gasta de esa cuenta (una imagen, un video) | obligatoria | **No** (ya se contó en la recarga) | Baja |
| `ajuste` | Cuadre con el saldo real del proveedor | obligatoria | **No** | Suma o resta (puede ser negativo) |

Reglas comunes: monedas `CLP` o `USD`; el monto es positivo (solo el `ajuste` puede ser negativo) y distinto de cero; la fecha no puede ser futura; en recarga, consumo y ajuste la **moneda debe ser la de la cuenta** (el saldo se lleva en la moneda de la cuenta y no se mezclan).

## Cómo se calcula

- **Gasto del mes** = suma en pesos (`monto_clp`) de los **pagos y recargas** del mes. Consumos y ajustes no suman; si sumaran, el mismo dinero se contaría dos veces. Los pagos o recargas en dólares con tipo de cambio pendiente todavía **no entran** al total (ver más abajo).
- **Saldo estimado de una cuenta** = recargas + ajustes − consumos, en la moneda de la cuenta. Puede salir negativo (por ejemplo, si el consumo se anotó antes que la recarga): se muestra en rojo, sin error.
- **Uso de la última recarga** = lo consumido desde la fecha de la última recarga, en monto y en porcentaje de esa recarga. Si la cuenta no tiene recargas, el uso queda vacío (`null`), sin división por cero.
- **Resumen** (`/finanzas/resumen`): total del mes, desglose por categoría y por proveedor, cantidad de pendientes y comparación con los **6 meses anteriores**. Un mes sin movimientos da ceros.

## Tipo de cambio

- **Fuente:** dólar observado de `https://mindicador.cl/api/dolar/DD-MM-AAAA`. CLP vale 1.
- **Días sin valor** (fin de semana o feriado): se usa el último valor anterior, **hasta 7 días atrás**. Un pago de un sábado no queda pendiente ni falla.
- **Caché:** cada valor se guarda por fecha en la opción `at_fin_tc_cache` (hasta 120 fechas). Hoy, si el dólar aún no se publicó, se usa el último valor pero **no se guarda** bajo la fecha de hoy, para que el movimiento no quede fijado a un dólar de otro día.
- **Si la fuente falla:** el movimiento se guarda igual con `tc_estado = pendiente` y sin `monto_clp`; la pestaña «Pagos por mes» lo marca «pendiente» y avisa cuántos pagos o recargas todavía no suman al total.
- **Cómo se completan los pendientes:** al abrir «Pagos por mes» y, después de crear un movimiento por la API, se reintentan unos pocos (con la fuente caída no se insiste: cada intento puede tardar hasta 10 s).
- **Manual:** quien registra puede mandar el tipo de cambio (`tipo_cambio`, por ejemplo el que cobró el banco); queda con `tc_estado = manual`.
- **Por eso cada pago se registra con su fecha real:** el dólar es el de ese día.

Estados: `ok` (obtenido), `pendiente` (no se pudo obtener), `manual` (lo dio quien registró).

## Cuadrar con el saldo real

Higgsfield no tiene endpoint de saldo (`/v1/credits`, `/v1/balance` y `/v1/me` responden 405), así que el saldo de sus cuentas es una **estimación** que se contrasta con el saldo que muestra el panel del proveedor.

En la pestaña «Cuentas», cada cuenta trae un campo para el saldo real y el botón **«Cuadrar»**:

1. Se escribe el saldo real (`30` o `30,50`).
2. El panel calcula `saldo real − saldo estimado` y registra un `ajuste` por esa diferencia, con la nota «saldo real del panel: X».
3. Si ya coincide, no registra nada y lo dice.
4. Si la cuenta tuvo movimientos desde que se abrió la página, **no cuadra a ciegas**: avisa que la cuenta cambió y hay que volver a cuadrar.
5. Dos envíos iguales seguidos no duplican el ajuste (la referencia del cuadre sale de la cuenta, el último movimiento visto y el saldo real).

Por la API o el script el mismo cuadre se hace calculando la diferencia a mano y registrando un `ajuste` con signo (negativo si el real es menor que el estimado).

## API

Prefijo `automatiza-tech/v1`. Base en PROD: `https://automatizatech.cl/?rest_route=/automatiza-tech/v1` (también sirve `/wp-json/automatiza-tech/v1`).

**Autenticación:** cabecera `X-AT-Secret` con el valor de `AT_REST_SECRET` (la misma de Retomar), comparada con `hash_equals`. La clave **nunca** se pega en la línea de comando, en un documento ni en un log.

| Ruta | Qué hace |
|---|---|
| `POST /finanzas/movimiento` | Registra un movimiento. Idempotente por `referencia`. |
| `GET /finanzas/resumen?mes=AAAA-MM` | Total del mes, desglose, pendientes y comparación con 6 meses. Sin `mes`, usa el actual. |
| `GET /finanzas/cuentas` | Cada cuenta con recargado, consumido, ajustes, saldo estimado y uso de la última recarga. |
| `POST /finanzas/cuenta` | Crea o actualiza una cuenta por `slug` (no se cambia la moneda de una cuenta que ya tiene movimientos). |

Campos de `POST /finanzas/movimiento`: `tipo`, `fecha`, `monto`, `moneda`, `proveedor`, `cuenta` (slug), `categoria`, `descripcion`, `proyecto`, `tipo_cambio`, `gasto_id`, `referencia`, `registrado_por`, `notas`. El monto acepta coma o punto decimal y espacios («1,26» = 1.26).

| Código | Cuándo |
|---|---|
| `201` | Movimiento (o cuenta) creado. |
| `200` | El movimiento ya existía con los mismos datos: mismo `id`, no se duplica. También es lo que responde al actualizar una cuenta. |
| `409` | La `referencia` ya existe con **datos distintos**. No reintentar: revisar cuál registro es el correcto. |
| `422` | Datos no válidos (tipo, fecha, monto, moneda, cuenta inexistente o con otra moneda, mes mal escrito). Trae `message` y la lista `errores`. |
| `401` | Falta la cabecera o la clave no coincide (WordPress responde 403 si hay una sesión iniciada sin la clave). |

## Idempotencia y referencias entre agentes

Cada movimiento tiene una `referencia` única:

- **Con referencia** (lo correcto cuando existe un número): se usa el id de la generación, el número de factura o boleta, o el id del pedido. Misma referencia y mismos datos: 200 y no se duplica. Misma referencia con datos distintos: 409.
- **Sin referencia:** el servidor la deriva de los datos (tipo, fecha, cuenta, proveedor, monto, moneda y descripción). Eso solo protege el **mismo registro repetido con exactamente esos datos**; si dos agentes lo anotan con otra descripción, monto o fecha, quedan dos registros.
- **Regla entre agentes:** quien gasta usa como referencia el id, la factura o el pedido que le avisa a Balance, y **Balance reutiliza exactamente esa misma referencia**. Así, aunque los dos lo registren, queda uno solo.
- Dos consumos legítimos idénticos el mismo día se tomarían por repetidos: se distinguen con la descripción o con una referencia propia.

## Script y encargo de Balance (fuera del repo)

Ambos viven fuera del repo porque este es público.

- **Script `registrar.py`:** `C:/Users/luis_/Documents/Codex/Shared-Tools/finanzas/registrar.py`; manual completo en `C:/Users/luis_/Documents/Codex/Shared-Tools/finanzas/README.md`. Python 3.10 o superior, sin instalar nada. Registra pagos, recargas, consumos y ajustes; consulta `resumen` y `cuentas`; crea o actualiza cuentas. Lee `AT_REST_SECRET` de la variable de entorno o por su etiqueta exacta en el archivo de claves, y no la imprime. `--seco` muestra el pedido sin enviarlo. Rechaza «15.990» (punto de miles) y decimales en pesos antes de enviar.
- **Encargo de Balance:** `C:/Users/luis_/Documents/Codex/Shared-Tools/Encargo-Balance-Finanzas-AT.md`; es el mismo para `at-balance-claude` y `at-balance-codex`. Trae el contexto, la rutina semanal (preguntar, registrar solo lo confirmado, cuadrar, informar), los límites (no paga ni recarga, no muestra claves, no registra sin dato confirmado) y los comandos.

Ejemplo ficticio de un consumo (el monto no es real):

```
python registrar.py consumo --monto 1,26 --moneda USD --proveedor Higgsfield --cuenta higgsfield-demo --proyecto CumpleClick --descripcion "Video de prueba" --fecha 2026-10-20 --referencia gen-12345 --por Balance
```

## Las tres pestañas del panel

wp-admin › **Finanzas AT** (`admin.php?page=at-finanzas`, con `&tab=pagos` o `&tab=cuentas`):

1. **Suscripciones:** el catálogo de siempre, sin cambios.
2. **Pagos por mes:** selector de mes, total en pesos, desglose por categoría, comparación con los 6 meses anteriores, lista de movimientos (moneda original y equivalente en pesos, con los pendientes marcados) y un formulario para registrar un pago o recarga a mano.
3. **Cuentas:** cada cuenta con su saldo estimado, una barra de uso de la última recarga, sus últimos movimientos y el botón «Cuadrar».

## Despliegue a PROD

**Solo con el OK de Luis**, por SSH como siempre. Nada de esto está desplegado.

Archivos, todos bajo `wp-content/themes/automatiza-tech/`:

| Orden | Archivo | Clasificación |
|---|---|---|
| 1 | `inc/finanzas/puras.php` | OBLIGATORIO |
| 1 | `inc/finanzas/datos.php` | OBLIGATORIO |
| 1 | `inc/finanzas/cambio.php` | OBLIGATORIO |
| 1 | `inc/finanzas/api.php` | OBLIGATORIO |
| 1 | `inc/finanzas/panel.php` | OBLIGATORIO |
| 1 | `inc/finanzas/cargar.php` | OBLIGATORIO |
| 2 (al final) | `inc/admin-at-finanzas.php` | OBLIGATORIO: es el que carga los anteriores |

`functions.php` no se toca. Primero la carpeta `inc/finanzas/` completa y recién después `inc/admin-at-finanzas.php`: si este último llegara primero, buscaría archivos que aún no existen.

Pasos:

1. Respaldo de `inc/admin-at-finanzas.php` actual (y de la carpeta `inc/finanzas/` si ya existiera).
2. Subir los seis archivos de `inc/finanzas/`.
3. `php -l` sobre los siete archivos en el servidor.
4. Subir `inc/admin-at-finanzas.php`.
5. Comparar md5 local contra PROD de los siete archivos.
6. Verificar las **tres pestañas con sesión iniciada** y la **API desde afuera** (sin clave debe responder 401).
7. Después, con Balance: crear las cuentas iniciales y cargar los pagos anteriores que Luis confirme.

Las tablas se crean solas la primera vez que se abre el panel o llega una petición REST (`dbDelta`, versión guardada en la opción `at_fin_schema` = `1`). No hay migración manual.

No subir: los archivos de `tests/finanzas/`, ni archivos temporales o de respaldo.

## Rollback

Restaurar `inc/admin-at-finanzas.php` desde el respaldo. Eso **desconecta el módulo** (las rutas REST y las pestañas nuevas dejan de existir) y la página vuelve a mostrar solo el catálogo. Las tablas `at_fin_cuentas` y `at_fin_movimientos` y las opciones pueden quedarse: no afectan a nada. Los archivos de `inc/finanzas/` quedan inertes sin quien los cargue.

## Verificación local (21-oct-2026)

Todas las suites en verde con PHP 8.3.28:

- `tests/finanzas/`: puras 101, API 76, datos 71 y panel 103 (351 controles).
- Regresión `tests/retomar/`: puras 251 y once suites con WordPress (1.252 controles en total), sin fallas.

Esto prueba el código en local; **no acredita PROD** ni la lectura real de mindicador desde el servidor.

## Pendientes y límites conocidos

- **«15.990» en la API:** la API todavía lo acepta y lo lee como 15,99. El script ya lo rechaza antes de enviar; la API no, así que quien llame directo debe escribir `15990` o `15,99`.
- **Categorías:** una categoría escrita con mayúsculas distintas («Hosting» y «hosting») sale en dos filas del desglose.
- **Conteo de pendientes del resumen:** incluye consumos y ajustes en dólares sin tipo de cambio, aunque estos no cambian el total del mes. El aviso de la pestaña «Pagos por mes» sí cuenta solo pagos y recargas.
- **Segunda etapa:** registro automático del consumo de fotos que generan solos el renderer de propuestas y el de Retomar. Hoy esos consumos los anota Balance o quien los gasta.
- **Fuera de alcance:** no hay importación de extractos bancarios ni pagos o recargas automáticos.
