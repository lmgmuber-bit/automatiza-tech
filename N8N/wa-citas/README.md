# Tech WhatsApp: citas con botones, enlace al portal y tipo de cambio protegido

Diseño: `Docs/superpowers/specs/2026-10-29-whatsapp-citas-botones-design.md`. Plan: `Docs/superpowers/plans/2026-10-29-whatsapp-citas-botones.md`.

Para cancelar o reprogramar, la persona ya no escribe nombre, correo, fecha ni hora. El bot le muestra sus citas futuras (diagnósticos y seguimientos) y ella elige y confirma con botones:
- **con una cita:** la confirmación directa;
- **con dos o tres:** un botón por cita;
- **con más:** una lista.

## Piezas

| Pieza | Dónde |
|---|---|
| **Citas del número** y **cancelar con todos los efectos:** estado cancelado sin borrar, evento de Calendar, correo a la persona y aviso a Luis | `wp-content/themes/automatiza-tech/inc/wa-contexto/citas.php`, rutas `GET /wa-citas` y `POST /wa-citas/cancelar` (clave `X-AT-Secret`) |
| **Lógica pura de las citas:** futuras, orden, tope de 10 y etiqueta | `inc/wa-contexto/citas-puras.php` |
| **Lógica de los nodos:** mensajes, botones `cita_…` y botón viejo | `N8N/wa-citas/nodos/citas.js` (bloque `WA CITAS`), con pruebas |
| **Línea del portal del cliente** | `N8N/wa-reconoce/nodos/contexto.js` (`waLineaContexto`) |
| **Script que aplica los cambios en n8n** | `N8N/wa-citas/aplicar.py` (vuelve a aplicar primero lo de `wa-reconoce`, que es idempotente) |

## Qué cambia en n8n

**WhatsApp** (`bBcNlFgBzQ0766Mq`):
- **Las acciones `cancel_appointment`, `reschedule_appointment` y `reschedule_followup`** van a este camino: «Citas del Número» → «Mensaje Citas» → «Send Citas».
  - Si la consulta se cae, «Notify Admin Citas» le avisa a Luis.
  - «Ask Cancel Data», «Ask Reschedule Data» y «Ask Reschedule Followup Data» quedan sin conectar, para volver atrás.
- **«Button Action»** tiene la regla `cita_`, que va justo antes de `seg_`. Sigue este camino: «Citas Vigentes» (lista fresca) → «Decidir Botón Cita» → «Ruta Botón Cita».
  - **mensaje:** confirmar, «Esa cita ya no está disponible» o «Tengo una cita».
  - **cancelar:** «Cancelar Cita» → «¿Cita cancelada?». El bot dice «cancelada» solo si WordPress lo confirma.
  - **reprogramar:** ver la Tarea 8 del plan.
- **Los tres «Get Exchange Rate»** van con `onError`, timeout de 8 s y 3 intentos. El prompt dice que, sin tasa, los precios van solo en USD.

**Web** (`3A5Y1NPBxpTjhHdE`): «Get Exchange Rate» protegido y la misma línea en el prompt.

## Cómo se corre

```bash
python -I -B -X utf8 N8N/wa-citas/aplicar.py
```

Ese comando es la vista previa: hace el GET de los tres flujos y aplica los cambios en memoria. Comprueba lo de `wa-reconoce` y además:
- que las rutas de cancelar y reprogramar vayan a los botones;
- que los bloques sean idénticos y pasen `node --check`;
- que una consulta caída avise a Luis y que un botón viejo vuelva a mostrar lo vigente;
- que la regla `cita_` esté en su lugar y que el tipo de cambio quede protegido;
- que los cambios sean idempotentes.

```bash
python -I -B -X utf8 N8N/wa-citas/aplicar.py --subir
```

Esta orden sube de verdad, y solo se corre con el OK de Luis. Respalda en `~/respaldos/n8n/wa-citas/<fecha>/`, sube la agenda, WhatsApp y la web, republica y verifica la `activeVersion`, incluidos los reintentos. Antes, hay que avisar a la sesión de Instagram, porque la agenda la comparte.

Pruebas:

```bash
node --test N8N/wa-citas/nodos/citas.test.js
```

`aplicar_test.py` usa una copia de los flujos vivos guardada fuera del repositorio, que es público (variable `AT_WA_FIXTURE`):

```bash
python -I -B -X utf8 N8N/wa-citas/aplicar_test.py
```

## Vuelta atrás

- **n8n:** subir los `*.antes.json` del respaldo, con el mismo PUT de settings filtrados, y republicar.
- **WordPress:** restaurar los archivos del respaldo del despliegue. Las rutas nuevas no cambian datos por sí solas.
