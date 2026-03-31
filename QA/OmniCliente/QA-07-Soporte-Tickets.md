# QA-07 — Soporte / Tickets

**Proyecto:** OmniCliente — Portal Omnicanal  
**Módulo:** SupportView — Tickets de Soporte, Hilo de Mensajes, Adjuntos  
**Versión:** 1.0  
**Fecha:** 2026-03-29  
**Roles cubiertos:** Todos (Agente, Supervisor, Cliente, Admin AT)  

---

## 1. Listado de Tickets

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| TK-001 | Ver lista de tickets | Login como cualquier rol | 1. Ir a "Soporte" en sidebar | Lista de tickets con asunto, estado, prioridad, categoría, fecha. Layout master-detail. | Alta |
| TK-002 | Buscar tickets por texto | Lista visible | 1. Escribir texto en campo búsqueda 2. Submit | Lista filtrada por coincidencia en asunto/descripción. | Media |
| TK-003 | Filtrar por estado | Lista visible | 1. Seleccionar estado del dropdown (open, in-progress, resolved, closed) | Solo muestra tickets del estado seleccionado. | Media |
| TK-004 | Paginación de tickets | >15 tickets | 1. Navegar entre páginas | 15 tickets por página. Paginación funcional. | Baja |
| TK-005 | Badges de estado | Lista visible | 1. Verificar colores de estado | open=azul, in-progress=amarillo, resolved=verde, closed=gris. | Baja |
| TK-006 | Badges de prioridad | Lista visible | 1. Verificar colores de prioridad | low=slate, medium=azul, high=naranja, urgent=rojo. | Baja |

---

## 2. Crear Ticket

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| TK-020 | Prompt de asistente AI | Clic en "Crear Ticket" | 1. Clic en "Crear Ticket" | Antes del formulario aparece sugerencia de usar el Asistente AI. Botón para abrir AI Assistant. | Media |
| TK-021 | Crear ticket completo | Formulario abierto | 1. Ingresar asunto (requerido) 2. Descripción (requerida) 3. Categoría: "technical" 4. Prioridad: "high" 5. Adjuntar 2 imágenes 6. Clic "Crear" | Ticket creado con todos los datos. Aparece en lista con estado "open". | Alta |
| TK-022 | Validar campos requeridos | Formulario abierto | 1. Dejar asunto y descripción vacíos 2. Intentar crear | Error: "Asunto y descripción son requeridos". | Alta |
| TK-023 | Adjuntar hasta 5 imágenes | Formulario abierto | 1. Seleccionar 5 imágenes (jpeg, png, webp, gif) | Las 5 se adjuntan. Formatos: jpeg, png, webp, gif aceptados. | Media |
| TK-024 | Categorías disponibles | Formulario abierto | 1. Abrir dropdown de categoría | Opciones: general, technical, billing, feature-request, bug. Labels: General, Técnico, Facturación, Solicitud, Error/Bug. | Media |
| TK-025 | Prioridades disponibles | Formulario abierto | 1. Abrir dropdown de prioridad | Opciones: low, medium, high, urgent. | Media |

---

## 3. Detalle del Ticket e Hilo de Mensajes

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| TK-040 | Ver detalle de ticket | Ticket existente | 1. Clic en ticket de la lista | Panel derecho muestra: asunto, estado, prioridad, categoría, descripción, hilo de mensajes. | Alta |
| TK-041 | Enviar mensaje en hilo | Ticket abierto (no closed) | 1. Escribir mensaje 2. Clic "Enviar" | Mensaje aparece como burbuja (usuario = derecha). Timestamp visible. | Alta |
| TK-042 | Mensaje de admin | Admin responde al ticket | 1. Admin envía mensaje | Burbuja del admin a la izquierda. Estilo diferente al usuario. | Alta |
| TK-043 | Adjuntar imágenes en mensaje | Hilo abierto | 1. Adjuntar hasta 5 imágenes al mensaje 2. Enviar | Imágenes visibles en la burbuja del mensaje. | Media |
| TK-044 | Lightbox de imagen | Mensaje con imagen | 1. Clic en imagen adjunta | Se abre lightbox/modal con imagen a tamaño completo. | Media |
| TK-045 | Ticket cerrado no permite mensajes | Ticket con status=closed | 1. Verificar campo de mensaje | Campo de envío deshabilitado. No se pueden agregar mensajes. | Alta |
| TK-046 | Admin ve datos del agente | Login como Admin AT, viendo ticket | 1. Verificar info del ticket | Muestra agent_name y agent_email del creador. | Media |

---

## 4. Gestión de Estado (Admin Only)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| TK-060 | Cambiar estado a in-progress | Login como Admin, ticket open | 1. Clic en botón "In Progress" | Estado cambia a in-progress (amarillo). | Alta |
| TK-061 | Cambiar estado a resolved | Ticket in-progress | 1. Clic en botón "Resolved" | Estado cambia a resolved (verde). | Alta |
| TK-062 | Cerrar ticket | Ticket resolved | 1. Clic en botón "Closed" | Estado cambia a closed (gris). Mensajes se deshabilitan. | Alta |
| TK-063 | Reabrir ticket | Ticket closed | 1. Clic en botón "Open" | Estado vuelve a open (azul). Mensajes se habilitan nuevamente. | Media |
| TK-064 | Los 4 botones de estado visibles | Login como Admin | 1. Verificar botones de acción | 4 botones: Open, In Progress, Resolved, Closed. Solo el admin los ve. | Alta |
| TK-065 | Usuario normal no ve botones de estado | Login como Agente/Cliente | 1. Verificar detalle del ticket | No aparecen botones para cambiar estado. Solo el admin los tiene. | Alta |
