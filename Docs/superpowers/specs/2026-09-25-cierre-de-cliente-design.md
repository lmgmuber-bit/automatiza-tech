# Cierre de cliente: respuesta a la propuesta, ficha única, contrato de servicio y bienvenida

- Fecha: 2026-09-25
- Rama: `claude/cierre-cliente`
- Decisiones de Luis (25-sep): ver sección 2.
- Referencia externa: el proceso de las primeras 24 horas de DevMerk (contrato, cuenta de cobro, panel de bienvenida, llamada de hoja de ruta), revisado cuadro por cuadro el 25-sep.

## 1. Problema

Hoy el cliente dice que sí por WhatsApp o en una llamada y el sistema no se entera. Todo lo que sigue es manual:

- La presentación (`ver-presentacion.php`) solo muestra el iframe: no hay forma de aceptar, pedir más tiempo o rechazar.
- «Convertir a Cliente» es un clic de Luis; manda un correo de bienvenida genérico con el enlace a la línea de tiempo.
- El módulo de contratos solo sabe armar el contrato de soporte post-proyecto: `ContractService::load_template()` carga siempre `Docs/CONTRATO_SOPORTE_POSTPROYECTO.md` y el título está fijo en `create_contract()`.
- Hay dos listas de clientes: `wp_crm_clientes` (CRM: ficha, línea de tiempo, proyectos, MAXTECH) y `wp_automatiza_tech_clients` (Contactos: contratos, facturas, boletas, accesos). Se cruzan por el correo (`crm-ai-completo.php`, comentario junto al listado de contratos del portal).
- Datos de PROD al 25-sep: 3 filas en `wp_crm_clientes` (2 clientes y 1 prospecto), 2 en `wp_automatiza_tech_clients` (los mismos 2 clientes, que calzan por correo), 2 contratos (ambos de soporte), 11 propuestas.

## 2. Decisiones de Luis

1. La propuesta se responde en el sitio de AT. El correo de la propuesta le pide al cliente, en forma explícita, que la acepte (botón «Aceptar la propuesta»), y un botón «Pedir respuesta» en el panel manda correo y WhatsApp con el mismo enlace.
2. La página de respuesta ofrece tres salidas: aceptar, seguir evaluando y rechazar.
3. Al aceptar, el prospecto pasa solo a cliente y le llega el correo de bienvenida. «Convertir a Cliente» queda como respaldo manual.
4. El contrato de servicio cubre solo lo que el cliente acepta que parte (normalmente la primera fase). Las fases siguientes van como anexo cuando parten.
5. El contrato necesita la firma de AT antes de llegar al cliente: se crea en borrador al aceptar y le llega cuando Luis lo firma (camino 1; Luis no respondió la pregunta del momento del contrato y este es el que coincide con su primera respuesta).
6. Ficha única de cliente con la opción A: la ficha del CRM es la única que se abre; los datos de contratos, facturas y accesos siguen en su tabla, enlazados por id.
7. Anticipo por transferencia (propuesto por Claude; Luis no respondió la pregunta). Pago con Flow queda fuera de esta etapa.
8. El envío de la propuesta sale también por WhatsApp, en forma automática, y el mensaje trae botones para responder ahí mismo: «Acepto la propuesta», «La sigo evaluando» y «No, gracias». Tocar «Acepto» en WhatsApp dispara lo mismo que aceptar en la página (Luis, 25-sep).

## 3. Recorrido

```
Luis envía la propuesta ──► Correo: presentación, PDF y «✅ Aceptar la propuesta»
                        └─► WhatsApp: resumen + botones «Acepto la propuesta» / «La sigo evaluando» / «No, gracias» + «Ver la propuesta»
        (o Luis aprieta «Pedir respuesta» ──► el mismo correo y el mismo WhatsApp de nuevo)
                                  │
             Responde en la página de automatizatech.cl      Responde tocando un botón en WhatsApp
             (nombre, RUT, qué acepta)                       (desde el teléfono de la propuesta)
                                  └──────────────┬──────────────┘
             ├─ «La sigo evaluando» ─► estado evaluando, aviso a Luis
             ├─ «No, gracias»       ─► estado rechazada, aviso a Luis
             └─ «Acepto la propuesta»
                        │  (todo automático)
                        ├─ registro en Seguimiento con huella de lo aceptado
                        ├─ propuesta aceptada
                        ├─ prospecto → cliente en la ficha única (ambas tablas enlazadas)
                        ├─ correo de bienvenida al cliente (lista de arranque)
                        ├─ borrador del contrato de servicio ─► correo «Revisar y firmar» a Luis
                        └─ aviso a Luis
Luis revisa, ajusta alcance/plazo y firma ─► el cliente recibe el contrato para firmar (flujo actual)
Cliente firma ─► PDF firmado a ambos (flujo actual)
Luis marca el anticipo pagado ─► boleta o factura (flujo actual)
```

## 4. Etapas de construcción

Cada etapa se prueba y puede subirse a PROD por separado, en este orden.

### Etapa 1 · Ficha única (opción A)

- **Enlace por id.** Columna nueva `crm_cliente_id` (BIGINT, índice) en `wp_automatiza_tech_clients`. Migración idempotente que enlaza por correo las filas existentes (hoy 2 de 2) y registra en un informe las que no calzan.
- **Una función puente**, `at_cliente_asegurar(array $datos): array` → `['crm_id' => int, 'tech_id' => int]`. Busca por `crm_cliente_id` y luego por correo normalizado (minúsculas, sin espacios). Crea la fila que falte y deja el enlace. La usan:
  - la aceptación de la propuesta;
  - «Convertir a Cliente» e «Importar & Convertir» del CRM;
  - `move_to_clients()` de Contactos, cuando un lead pasa a contratado.
- **La ficha del CRM muestra todo.** Pestañas nuevas en la ficha del CRM con el contenido del registro enlazado, reutilizando las funciones de render que ya existen en `client-operations-module.php` y `contracts/client-contracts-widget.php`: Contratos, Facturación, Accesos, Técnico y Redes. La Identidad del CRM es la única; la pestaña Identidad de la ficha de operaciones deja de mostrarse. Si hay datos de marca solo en `tech_clients`, la migración los copia a la ficha del CRM cuando ahí estén vacíos.
- **Contactos queda para leads.** La lista de clientes de Contactos se mantiene como listado, pero cada fila abre la ficha del CRM enlazada, no el modal de operaciones.
- **Sin borrar nada.** Ninguna tabla ni columna se elimina en esta etapa.

### Etapa 2 · Contrato de servicio

- **Plantilla nueva** `Docs/CONTRATO_SERVICIO_DESARROLLO.md`, con el mismo formato de marcadores `{{...}}` que la de soporte. Contenido:
  - partes;
  - objeto (lo aceptado);
  - alcance y entregables;
  - valor, con IVA incluido, y forma de pago 50 % al iniciar y 50 % al entregar;
  - plazo;
  - aceptación de entregables;
  - cambios de alcance;
  - propiedad intelectual (del cliente al pagar el total; AT puede mostrar el trabajo en su portafolio);
  - confidencialidad, garantía, término y jurisdicción;
  - fases siguientes como anexo.
  
  Queda marcada como borrador para revisión de abogado.
- **El módulo respeta la plantilla pedida.** `load_template($template_id)` elige el archivo según el id (`soporte_v2` → soporte, `servicio_v1` → servicio). El título del contrato sale de la plantilla o de un mapa por tipo, ya no fijo. Los contratos de soporte existentes no cambian.
- **Luis puede ajustar antes de firmar.** En la página de firma de AT (`contracts/at-sign-contract.php`), cuando el contrato es de servicio, aparecen editables alcance, entregables y plazo, precargados desde la propuesta. Al guardar se regeneran el texto y el PDF antes de la firma.

### Etapa 3 · Respuesta del cliente y aceptación automática

**Página de respuesta**

- `ver-presentacion.php?id=<código>` agrega una barra fija con «Acepto la propuesta», «La sigo evaluando» y «No, gracias». El código es el `unique_link_id` de 12 caracteres, no el id numérico. En celular la barra va abajo y no tapa la presentación.
- Cada botón abre un formulario corto en la misma página:
  - **Aceptar:** nombre completo, RUT y las filas de precio de la propuesta como casillas, con la primera marcada. Más una casilla obligatoria: «Acepto la propuesta y sus condiciones».
  - **Evaluando:** comentario opcional.
  - **Rechazar:** motivo opcional.
- Las respuestas se envían por POST a `admin-post.php` (acción pública) con nonce, un campo trampa contra bots y un límite de intentos por IP. Nunca se acepta por un enlace GET: los filtros de correo abren los enlaces solos.
- Una propuesta aceptada no se puede volver a aceptar ni cambiar desde la página: muestra «Ya aceptaste esta propuesta el …». Rechazada o en evaluación sí puede pasar a aceptada.

**Estados nuevos de la propuesta**

`evaluando`, `rechazada` y `aceptada`. Transiciones permitidas:

| Desde | Hacia |
|---|---|
| `sent` | `evaluando`, `rechazada`, `aceptada` |
| `evaluando` | `rechazada`, `aceptada` |
| `rechazada` | `aceptada` |

Aplica a propuestas v3 y a las viejas (como la 43, en `sent`). El panel muestra los estados con su color y filtro.

**Registro de la respuesta**

Cada respuesta queda en `wp_automatiza_propuestas_details` («Seguimiento»), tipo `respuesta`, con: salida, nombre, RUT, comentario, fecha, IP, agente de usuario, filas aceptadas y la huella sha256 del contenido de la propuesta en ese momento. Así se sabe qué versión se aceptó.

**Al aceptar, en orden**

1. Registro y estado `aceptada`.
2. `at_cliente_asegurar()` con los datos de la propuesta: cliente oficial en la ficha única, tipo `cliente`, estado `contratado`, fecha de contrato hoy.
3. Correo de bienvenida.
4. Borrador del contrato de servicio (`type` `servicio`, `template_id` `servicio_v1`, `proposal_id`) con el correo actual «Revisar y firmar contrato» a Luis.
5. Aviso a Luis por correo.

Si falla un paso, los anteriores quedan hechos, el fallo queda en Seguimiento y en el aviso a Luis, y el resto se completa a mano: «Convertir a Cliente» y crear el contrato desde la ficha.

**Correo de bienvenida (lista de arranque)**

Reemplaza a `_enviar_correo_bienvenida()`, también cuando Luis convierte a mano. Si el cliente no tiene propuesta aceptada, omite lo que depende de ella.

1. Tu contrato: «te llega en un correo aparte para firmarlo».
2. Anticipo: el 50 % de lo aceptado, con los datos de transferencia. El monto se calcula de las etiquetas de precio (ej. «$2.000.000 en 2 pagos» → primer pago $1.000.000). Las filas mensuales («al mes») no entran al anticipo. Si una etiqueta no se puede leer, el correo dice «según tu contrato».
3. Marca y accesos: responder el correo o escribir por WhatsApp.
4. Reunión de inicio: enlace para agendar si existe en ajustes; si no, «te escribimos para agendarla».
5. Tu portal: enlace a la línea de tiempo.

**Ajustes nuevos**

En la configuración de facturación (`invoice-settings.php`), junto a los datos de la empresa: banco, tipo de cuenta, número, titular, RUT del titular, correo para avisar el pago y enlace opcional para agendar la reunión de inicio. Los escribe Luis; ningún agente los escribe.

**«Pedir respuesta» y el correo de la propuesta**

- **El correo de la propuesta pide la aceptación en forma explícita** (pedido de Luis, 25-sep). En `propuestas-admin/acciones.php`, después de los botones de la presentación y la demo, va un bloque destacado con:
  - el texto «Si estás de acuerdo con la propuesta, acéptala aquí. Con tu aceptación te enviamos el contrato y los primeros pasos para partir»;
  - un botón principal «✅ Aceptar la propuesta», que abre la página de respuesta con el formulario de aceptación ya desplegado (`ver-presentacion.php?id=<código>&responder=aceptar`);
  - un enlace secundario «¿Tienes dudas o necesitas más tiempo? Cuéntanos aquí», que abre la misma página en «La sigo evaluando».
  
  El botón del correo no acepta por sí solo: los filtros de correo abren los enlaces sin que nadie los toque. La aceptación se confirma en la página, con nombre, RUT y la casilla, en un clic más.
- En la pestaña Envío de la ficha, el botón nuevo «Pedir respuesta»:
  - manda un correo corto con el mismo bloque «Aceptar la propuesta»;
  - manda el mismo WhatsApp del envío (etapa 4), o, mientras la plantilla no esté aprobada, ofrece «Enviar por mi WhatsApp» (`wa.me` con el mensaje y el enlace ya escritos, desde el teléfono de Luis);
  - deja el envío en Seguimiento.
- Disponible para propuestas en `sent`, `evaluando` o `rechazada` que tengan correo o teléfono.
- El envío normal de la propuesta, en la pestaña Envío, suma la casilla «También por WhatsApp», marcada cuando la propuesta tiene teléfono.

### Etapa 4 · WhatsApp automático con botones para responder

**Envío**

- Plantilla nueva en Meta, `propuesta_respuesta`, en español, con variables para el nombre, la empresa y lo que se propone partir con su precio (ej. «Fase 1: Configurador 3D, $2.000.000 IVA incluido»). Lleva cuatro botones sin emoji (Meta rechaza emojis en botones: lo medimos el 24-sep):
  - tres de respuesta rápida: «Acepto la propuesta», «La sigo evaluando» y «No, gracias», con carga `btn_propuesta_acepta_<código>`, `btn_propuesta_evalua_<código>` y `btn_propuesta_rechaza_<código>`;
  - uno de enlace: «Ver la propuesta», a la página de respuesta.
- Categoría Utility; si Meta la reclasifica como Marketing, se informa a Luis antes de usarla.
- Flujo nuevo en n8n, «Propuesta · WhatsApp», con webhook protegido por cabecera igual que el Borrador. WordPress lo llama al enviar la propuesta (casilla «También por WhatsApp») y desde «Pedir respuesta», con nombre, teléfono, código y texto de lo propuesto. n8n manda la plantilla con la credencial de WhatsApp de Meta que ya usan los recordatorios.
- Mientras la plantilla no esté aprobada, la vía es el botón `wa.me` de la etapa 3.
- Un 200 con `wamid` no significa entregado (error 131047, medido el 24-sep). El panel dice «enviado a WhatsApp», no «entregado». El correo siempre sale.

**Respuesta con un toque en WhatsApp**

- Cuando el cliente toca un botón, Meta avisa al bot principal de WhatsApp de AT, que ya enruta las cargas de los botones de plantilla (`button.payload`) desde el 24-sep. Se le agrega la ruta `btn_propuesta_*`, que llama a un endpoint REST nuevo de WordPress, `at/v1/propuesta-respuesta`, protegido con clave de cabecera, con la salida, el código, el teléfono que tocó y el `wamid`.
- WordPress acepta la respuesta solo si el teléfono que tocó coincide con el de la propuesta, comparando los números normalizados (solo dígitos, con 56 delante). Si no coincide, no cambia el estado: lo anota en Seguimiento y avisa a Luis.
- «Acepto la propuesta» en WhatsApp dispara exactamente lo mismo que aceptar en la página (sección «Al aceptar, en orden»). Lo aceptado es lo que decía el mensaje, que es la primera fila de precios, y el registro guarda canal `whatsapp`, teléfono, `wamid` y la huella del contenido. No pide RUT: el RUT lo pide el contrato al firmar.
- Tocar un botón abre la ventana de 24 horas, así que el bot contesta en el mismo chat sin plantilla:
  - aceptar: «¡Gracias! Recibimos tu aceptación. Te enviamos por correo los primeros pasos y el contrato llega para firmar»;
  - evaluar: «Gracias por contarnos. Si tienes dudas, escríbenos por aquí»;
  - rechazar: «Gracias por tu respuesta. Si algo cambia, aquí estamos».
- Tocar dos veces o tocar después de aceptar no repite nada: la respuesta es idempotente, igual que en la página.
- Tocar el bot principal requiere el ok explícito de Luis en el momento de hacerlo: es el que atiende a todos los contactos.

## 5. Seguridad

- La página de respuesta es pública: solo acepta POST, con nonce, campo trampa, límite por IP y el código de 12 caracteres. Responde igual si el código no existe, para no revelar cuáles existen.
- El RUT se valida con dígito verificador.
- El endpoint de respuestas por WhatsApp (`at/v1/propuesta-respuesta`) exige clave de cabecera, guardada solo en n8n y en `wp-config.php`, y solo acepta la respuesta si el teléfono que tocó el botón es el de la propuesta.
- Los datos bancarios se guardan como opciones de WordPress. No son secretos, pero no se escriben en el repositorio.
- El enlace al portal que va en la bienvenida usa la función de firma de enlaces vigente. La rama `claude/crm-enlaces-fichas` la reemplaza: si se despliega antes, la bienvenida usa la nueva.
- El repositorio es público: nada de esta obra describe ni deja expuestos datos de clientes.

## 6. Pruebas

Scripts PHP con el mismo patrón de las pruebas del panel de propuestas, en local (WAMP), más pruebas HTTP de la página de respuesta:

- Transiciones de estado: cada una permitida y rechazada.
- `at_cliente_asegurar()`: cliente nuevo, existente en una tabla, existente en ambas, correo con mayúsculas y espacios, dos llamadas seguidas sin duplicar.
- Migración del enlace sobre una copia con los datos de PROD (2 filas).
- Cálculo del anticipo desde etiquetas reales: «$2.000.000 en 2 pagos», «$250.000 en 2 pagos», «$120.000 al mes», «Incluido», «Por confirmar».
- Plantilla de servicio: todos los marcadores reemplazados; la de soporte sigue igual (comparación del texto generado antes y después).
- Página de respuesta: aceptar, evaluar y rechazar; aceptar dos veces; GET que no acepta; nonce vencido; campo trampa lleno; límite por IP; RUT inválido.
- Aceptación completa en local con una propuesta de prueba: estado, Seguimiento, ficha única, correo de bienvenida capturado y contrato en borrador.
- Endpoint de WhatsApp: sin clave, con clave mala, teléfono que no coincide, las tres salidas, dos toques seguidos, código inexistente. Normalización de teléfonos chilenos («+56 9 …», «9 …», «569…»).
- Una prueba real de punta a punta con el teléfono de Luis como cliente de prueba, antes de usarlo con clientes.

## 7. Fuera de esta etapa

Pago en línea con Flow, formulario del cliente para subir logo y archivos, hoja de ruta semana a semana (idea tomada de DevMerk), portal del cliente con login, eliminar la tabla `wp_automatiza_tech_clients`.

## 8. Despliegue

- Todo local primero.
- A PROD por SSH solo con el ok de Luis y por etapa: respaldo de la base y de los archivos, luego migración antes que el código, luego verificación desde afuera.
- `ver-presentacion.php` vive en la raíz del sitio: se respalda y se sube aparte.
- Primer caso real: la propuesta 43, cuyo cliente ya dijo que sí por WhatsApp, con «Pedir respuesta».
