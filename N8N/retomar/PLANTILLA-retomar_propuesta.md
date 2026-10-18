# Plantilla para revisión de Meta: retomar_propuesta

Estado: preparada localmente; no enviada a aprobación ni activada. Sólo Luis/Eje autoriza Meta y el piloto.

- Nombre: `retomar_propuesta`
- Categoría: **MARKETING**
- Idioma: **es**
- Encabezado y pie: ninguno.

Texto exacto del cuerpo (un párrafo):

Hola {{1}} 👋 Te saluda el equipo de AutomatizaTech. Hace unas semanas conversamos sobre {{2}} y quedó pendiente. Te dejamos un resumen de lo que vimos y tu demo. Si algo no calza con lo que necesitas o con tu presupuesto, lo ajustamos y podemos partir por lo más importante. ¿Agendamos 20 minutos para conversarlo?

Parámetros de cuerpo: `{{1}}` = nombre; `{{2}}` = negocio. Ejemplos ficticios: `Persona Ejemplo`, `Negocio Demostración`.

| Índice | Botón URL | URL configurada en Meta | Ejemplo ficticio del sufijo dinámico |
| --- | --- | --- | --- |
| 0 | Ver y agendar | `https://automatizatech.cl/retomar.php?{{1}}` | `t=TOKEN_FICTICIO_NO_VALIDO&agendar=1` |
| 1 | No me interesa | `https://automatizatech.cl/retomar.php?{{1}}` | `t=TOKEN_FICTICIO_NO_VALIDO&baja=1` |

En cada botón `{{1}}` recibe el sufijo completo; es independiente del parámetro del cuerpo. No cargar datos de clientes como ejemplos. El workflow codifica el token con `encodeURIComponent`.

Un rechazo, incluido `131049`, se registra como error sin reintento automático. La entrega al destinatario no está garantizada por una respuesta de aceptación de Graph.
