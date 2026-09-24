# Propuestas AT — flujo automático v3

Complementa la guía de plantilla única (`Docs/METODO_AT/PROPUESTAS-PLANTILLA-UNICA.md`, PR #36): toda propuesta
se arma con el propuesta-renderer; aquí se describe cómo nace, se ajusta y se envía sin trabajo manual.
Plan de implementación: `Docs/superpowers/plans/2026-09-23-flujo-propuestas-v3.md`.

## Recorrido

1. **Llamada con el cliente** → Google Meet deja la transcripción en Drive › Transcripciones
   (`14Qy7majCZlelxzkyUcjyJZjZ0mgMoGWW`) → workflow «Google Meet → Propuesta» (`FrWZcgbizlipK5pb`) la lee y la
   manda a `POST /webhook/propuesta-v3-borrador` con `{transcript, client_email, drive_file_id}`.
   Si no encuentra el correo del cliente, avisa a Luis y no crea nada.
2. **«Propuestas v3 · 1 Borrador»** (`7hglMG2j17HdOh6U`): GPT-4o redacta con la plantilla, precios «Por confirmar»,
   fotos descritas por rubro (ver abajo), asistente de demo; crea la fila en WordPress (`borrador`, `flujo='v3'`),
   vista previa **sin fotos** con sello Borrador y correo de marca a Luis. Gasto en fotos: cero.
3. **Panel WordPress** (Propuestas › la propuesta › «Revisión v3»): Luis escribe precios y comentarios →
   **Pedir cambios** → «2 Cambios» (`25rjGcjDYPDECn6p`) aplica solo los comentarios (nunca toca precios: WordPress
   los restaura), rehace la vista previa y avisa. Se repite las veces que haga falta. Si falla, la propuesta
   queda en `error` con el motivo y llega un correo.
4. **Aprobar** (el botón muestra cuántas fotos y su costo) → «3 Final» (`elReU26Sju1lpdO5`): filtra las
   descripciones, genera las fotos con Soul 2, las guarda en `/p/<id>/img/` junto a la presentación y renderiza.
   Si faltan fotos, **reintenta hasta 3 renders**; el renderer reutiliza las ya guardadas con el mismo prompt
   (`img/manifest.json`), así que no se pagan dos veces. Verifica presentación, PDF y chatbot. Queda `lista` o
   `error` con el detalle, y llega el correo.
5. **Envío**: solo desde `lista`, con la casilla «Enviar correo» del panel. Nunca automático.

Estados: `borrador → ajustando|generando`, `ajustando → borrador|error`, `generando → lista|error`,
`lista → ajustando|sent`, `error → borrador|ajustando|generando`. Las propuestas viejas (`flujo` NULL) siguen igual.

## Fotos por rubro (regla de Luis, 2026-09-24)

Las fotos son del rubro de cada cliente, como las de Jeffer (béisbol) y Orly (funeraria): su gente, sus clientes,
sus productos y lugares, **con personas en acción**. Lo medido con Soul 2 a 720p:

| Caso | Resultado |
|---|---|
| Béisbol con estadio o muro de fondo (3 fotos) | 3/3 con texto inventado (marcador, carteles, polera) |
| Portada de funeraria en primer plano, fondo desenfocado (2 fotos) | 2/2 limpias |
| Celular «con la pantalla hacia el otro lado» (Orly) | limpia |

Reglas que aplica `N8N/propuestas-v3/fotos_guard.py` (compartido por Borrador y Final; pruebas en `probar_fotos.py`):
- Se reemplaza toda descripción que pida pantallas con contenido, sitios web, gráficos, documentos, pizarras,
  letreros, carteles, marcadores, menús o texto.
- Productos con etiqueta o pantalla (botellas, latas, cajas, paquetes, celulares, laptops, libros, estantes):
  el filtro los **neutraliza** («plain unlabeled …», «every screen dark and facing away») en vez de perder la
  escena, porque GPT-4o no escribe «sin etiqueta» aunque se le pida. Probado con una botillería ficticia
  (filas 49–51): de 4 de 8 fotos neutras a 8 de 8 del rubro. Falta ver las fotos reales de licores.
- Cada lámina muestra el mundo del cliente, nunca la solución de AutomatizaTech (nada de celulares navegando,
  computadores, oficinas ni reuniones); el prompt de Borrador dice qué mostrar por lámina.
- La portada va siempre en primer plano con el fondo desenfocado; se reemplaza si pide estadio, fachada, calle o muro.
- Los reemplazos son escenas neutras que sirven para cualquier rubro, nunca las de otro cliente.
- Siempre se agrega el cierre `no signs, no labels, no text, no lettering, no logos, no watermarks`.

Revisar una foto siempre **dentro de la plantilla** (la capa oscura tapa detalles en las láminas interiores,
no en la portada), con `renderProposalHtml` y Playwright en local, sin gastar.

## Correos

Los tres flujos envían a Luis un correo de marca (`N8N/propuestas-v3/email_tpl.py` y `correos.py`).
**Nunca enlazar `*.easypanel.host`** en un correo: el SMTP de Hostinger lo rechaza como spam (554 5.7.1).
Se enlaza `automatizatech.cl/ver-presentacion.php?id=`.

## Cómo se publica y cómo se revierte

- Workflows: `python N8N/propuestas-v3/build_N_*.py` genera el JSON y `python N8N/propuestas-v3/deploy.py N-*.json`
  lo publica (crea o actualiza por nombre, filtra por el host de AT; la clave no se imprime).
  Respaldos antes de cada publicación en `C:/Users/luis_/respaldos/n8n/<fecha>...`.
- Renderer: lo despliega Luis soltando el zip (`git archive --format=zip -o x.zip <commit>:renderer`) en Easypanel.
  Rollback = el zip anterior.
- 🔴 **Editar el workflow de Meet reprocesa transcripciones viejas.** El 2026-09-24, al cambiar solo la URL del
  nodo de envío por la API, el disparador de Drive volvió a tomar la transcripción real de Orly que ya estaba en
  la carpeta y creó la fila 48 (borrador, con su correo real; no se le envió nada). Antes de tocar ese workflow,
  sacar de Transcripciones lo ya procesado (por ejemplo a una subcarpeta «Procesadas») o contar con que se
  repetirá el borrador.
- Meet: para volver al flujo viejo, la URL del nodo «Enviar a Workflow Propuestas» vuelve a
  `https://n8n-n8n.kchiba.easypanel.host/webhook/generar-propuesta-v2` (`APuTGmusbjLAJ74w`, queda de respaldo).
