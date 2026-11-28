# Finanzas AT: pagos reales, medidor de créditos y agente Balance (diseño)

**Fecha:** 21-oct-2026 · **Autor:** Eje · **Aprobado por Luis:** diseño en conversación, 20/21-oct-2026.

## Para qué

Luis quiere ver **cuánto gasta AutomatizaTech cada mes**, con cada pago real. Por ejemplo: recargas de Higgsfield, Google Cloud, las suscripciones de Claude y Codex, el hosting, Meta.

También quiere un **medidor de cada saldo prepagado**. Si recarga US$50 en Higgsfield, el medidor muestra cuánto de eso ya se usó en imágenes y videos.

Lo lleva un agente de finanzas, **Balance**, con un par en Claude y otro en Codex. Balance registra los gastos que le avisan los demás agentes y pregunta por los que faltan.

## Lo que ya existe y se conserva

- **Módulo «💰 Finanzas AT»** en wp-admin:
  - archivos: `inc/admin-at-finanzas.php` y la clase `OmniATFinanceController` en `omnichannel-atfinance-controller.php`;
  - tabla `wp_omnichannel_at_expenses`;
  - en PROD tiene 13 filas al 20-oct.
- **Qué es ese módulo:** un **catálogo de suscripciones**, con proveedor, monto, moneda y periodo mensual, anual o único. De ahí calcula el gasto mensual proyectado.
- **Qué no es:** un registro de pagos reales. El catálogo no se toca. Los pagos reales pueden enlazarse a una suscripción del catálogo, pero es opcional.
- **Carga del módulo:** lo carga `functions.php`, que no se toca (PROD lo adelanta). El código nuevo se carga desde `inc/admin-at-finanzas.php` con `require_once`.

## Decisiones de Luis

1. **Moneda:** cada pago se guarda en su moneda (CLP o USD) **y además en pesos**, al tipo de cambio de ese día.
2. **Pagos anteriores:** se cargan los que se puedan; los da Luis o los pregunta Balance.
3. **Medidor de créditos:**
   - por cada cuenta prepagada: recargado, consumido y saldo estimado;
   - se cuadra con el saldo real del panel, porque **Higgsfield no tiene endpoint de saldo**: `/v1/credits`, `/v1/balance` y `/v1/me` responden 405 (memoria `reference_higgsfield_api_directa`).
4. **Nombre del agente:** Balance, con los perfiles que ya existen: `at-balance-claude` y `at-balance-codex`.

## Modelo de datos

### Tabla `{prefix}at_fin_cuentas`: cuentas con saldo prepagado

| Columna | Tipo | Nota |
|---|---|---|
| id | BIGINT PK | |
| slug | VARCHAR(60) UNIQUE | ej. `higgsfield-api-n8n` |
| nombre | VARCHAR(120) | ej. «Higgsfield · API N8N» |
| proveedor | VARCHAR(120) | |
| moneda | CHAR(3) | moneda del saldo (USD) |
| activa | TINYINT | |
| notas | TEXT | sin claves ni datos sensibles |
| created_at, updated_at | DATETIME | |

### Tabla `{prefix}at_fin_movimientos`: cada pago, recarga, consumo o ajuste

| Columna | Tipo | Nota |
|---|---|---|
| id | BIGINT PK | |
| fecha | DATE | día del pago o del consumo (hora de Chile) |
| tipo | VARCHAR(10) | `pago` · `recarga` · `consumo` · `ajuste` |
| cuenta_id | BIGINT NULL | obligatorio en recarga, consumo y ajuste |
| proveedor | VARCHAR(120) | |
| categoria | VARCHAR(60) | mismas categorías del catálogo; admite nuevas |
| descripcion | VARCHAR(255) | |
| proyecto | VARCHAR(80) | opcional: AT, CumpleClick, un cliente… |
| monto | DECIMAL(14,4) | en su moneda; positivo (el ajuste puede ser negativo) |
| moneda | CHAR(3) | CLP o USD |
| tipo_cambio | DECIMAL(10,2) NULL | pesos por dólar del día (1 para CLP) |
| monto_clp | DECIMAL(14,0) NULL | monto × tipo_cambio |
| tc_estado | VARCHAR(10) | `ok` · `pendiente` (no se pudo obtener) · `manual` |
| gasto_id | BIGINT NULL | suscripción del catálogo, si corresponde |
| referencia | VARCHAR(120) UNIQUE | la manda quien registra; evita duplicados |
| registrado_por | VARCHAR(60) | Balance, Eje, Claqueta, panel… |
| origen | VARCHAR(10) | `api` · `panel` |
| notas | TEXT | |
| created_at | DATETIME | |

**Reglas de cálculo:**

- **Gasto del mes:** suma de `monto_clp` de los **pagos y recargas** del mes. Los consumos **no** suman al gasto del mes, porque ese dinero ya se contó en la recarga; si sumaran, se contaría dos veces. Los ajustes tampoco suman.
- **Saldo estimado de una cuenta:** recargas + ajustes − consumos, en la moneda de la cuenta.
- **Uso de la última recarga:** cuánto se consumió desde la última recarga, en monto y en porcentaje.
- **Cuadrar con el saldo real:** Balance o Luis ingresa el saldo real que muestra el panel del proveedor y se registra un `ajuste` por la diferencia, con la nota «saldo real del panel: X».

## Tipo de cambio

- **Fuente:** dólar observado de `https://mindicador.cl/api/dolar/DD-MM-AAAA`. Verificado el 21-oct: el 20-oct respondió 981,86.
- **Días sin valor** (fin de semana o feriado): se usa el último valor anterior, hasta 7 días atrás.
- **Caché:** cada valor se guarda por fecha, para no consultar dos veces el mismo día.
- **Si la fuente falla:**
  - el movimiento se guarda igual, con `tc_estado=pendiente` y `monto_clp` vacío;
  - la página de Finanzas lo muestra marcado;
  - una revisión posterior (al abrir el módulo o en el próximo registro) completa los pendientes.
- **Tipo de cambio manual:** quien registra puede mandarlo (`tc_estado=manual`), por ejemplo el que cobró el banco.

## Entrada segura (API)

Prefijo `automatiza-tech/v1`, autenticación con la cabecera `X-AT-Secret` = `AT_REST_SECRET`, igual que Retomar.

- **`POST /finanzas/movimiento`:** registra un movimiento.
  - **Validaciones:** tipo válido; monto distinto de cero (positivo salvo en `ajuste`); moneda CLP o USD; fecha válida y no futura; cuenta existente cuando el tipo la exige; textos acotados.
  - **Idempotente por `referencia`:**
    - si la referencia ya existe con los mismos datos, responde 200 con el mismo id y no duplica;
    - si existe con datos distintos, responde 409.
- **`GET /finanzas/resumen?mes=AAAA-MM`:** total del mes en pesos, desglose por categoría y por proveedor, comparación con los 6 meses anteriores y los movimientos con tipo de cambio pendiente.
- **`GET /finanzas/cuentas`:** cada cuenta con su recargado, consumido, ajustes, saldo estimado y uso de la última recarga.
- **`POST /finanzas/cuenta`:** crea o actualiza una cuenta, por `slug`.

## Panel en wp-admin («Finanzas AT»)

Pestañas en la misma página:
1. **Suscripciones:** lo que existe hoy, sin cambios.
2. **Pagos por mes:**
   - selector de mes, total del mes en pesos, desglose por categoría y comparación con los 6 meses anteriores;
   - lista de movimientos, con su moneda original y su equivalente en pesos;
   - formulario para registrar un pago o recarga a mano.
3. **Cuentas y créditos:**
   - cada cuenta con su saldo estimado y una barra de uso de la última recarga;
   - sus últimos movimientos;
   - el botón «Cuadrar con el saldo real».

## Script para los agentes

Archivo `Shared-Tools/finanzas/registrar.py`, fuera del repo y compartido por Claude y Codex:
- registra pagos, recargas, consumos y ajustes;
- consulta el resumen del mes y los saldos;
- lee `AT_REST_SECRET` por su etiqueta y nunca lo imprime;
- genera la referencia a partir de los datos, para que repetir el mismo registro no lo duplique.

## Agente Balance y regla para todos los agentes

- **Encargo:** `Shared-Tools/Encargo-Balance-Finanzas-AT.md`, el mismo para Claude y Codex. Trae:
  - el contexto: catálogo actual, cuentas, proveedores, cómo registrar y cómo cuadrar;
  - la rutina: cada semana pregunta a los agentes y a Luis por recargas y pagos nuevos;
  - los límites: no paga ni recarga nada, solo registra; nunca muestra claves.
- **Regla nueva para todos los agentes:** cada vez que un agente gasta créditos o dinero (por ejemplo una generación en Higgsfield, una recarga o una suscripción), le avisa a Balance con un mensaje corto: qué fue, cuánto, en qué cuenta, para qué proyecto y con qué referencia. Si Balance no está disponible, lo registra él mismo con el script. Esta regla se agrega a las instrucciones de agentes del repo (`CLAUDE.md` y `AGENTS.md`).
- **Chats:** Luis abre los dos chats de Balance, uno en Claude y otro en Codex, y les pega el encargo.

## Fuera de esta etapa

- **Segunda etapa:** registro automático del consumo de fotos que generan solos el renderer de propuestas y el de Retomar.
- No hay importación de extractos bancarios.
- No hay pagos ni recargas automáticas.

## Pruebas

- **Puras:**
  - gasto del mes (pagos y recargas sí, consumos y ajustes no);
  - saldo y uso de la última recarga;
  - tipo de cambio de un día sin valor;
  - validación de movimientos;
  - referencia determinista.
- **Con WordPress:**
  - creación de tablas;
  - la API rechaza sin clave (401);
  - un registro repetido no duplica (200) y un dato distinto con la misma referencia da 409;
  - un tipo de cambio pendiente se completa después;
  - el resumen mensual y los saldos cuadran con los movimientos de prueba.
- **Regresión:** el catálogo y la página actual de Finanzas no cambian.

## Despliegue

Solo con el OK de Luis, por SSH como siempre: respaldo, `php -l`, comparación de md5 y verificación desde afuera. Las tablas se crean solas la primera vez, con `dbDelta` y una versión guardada en una opción. El repo es público: los montos reales nunca van en commits ni en la documentación.
