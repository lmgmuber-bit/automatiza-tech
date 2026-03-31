# QA-10 — Asistente AI

**Proyecto:** OmniCliente — Portal Omnicanal  
**Módulo:** AiAssistantChat — Widget Flotante de IA  
**Versión:** 1.0  
**Fecha:** 2026-03-29  
**Roles cubiertos:** Todos (Agente, Supervisor, Cliente, Admin AT)  

---

## 1. Widget Flotante

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AI-001 | Botón de AI visible | Login como cualquier rol | 1. Verificar esquina inferior derecha | Botón flotante con robot animado SVG y anillos de pulso visibles. | Alta |
| AI-002 | Tooltips rotativos | Botón visible, sin interacción | 1. Observar tooltip cerca del botón por 60+ segundos | 8 mensajes se rotan cada 12 segundos. Incluyen nombre del usuario (ej: "¡Hola, Luis! 👋"). | Baja |
| AI-003 | Abrir panel de chat | Botón visible | 1. Clic en botón flotante | Panel de chat se abre como overlay. Saludo personalizado por hora ("Buenos días/tardes/noches") + nombre. | Alta |
| AI-004 | Cerrar panel de chat | Panel abierto | 1. Clic en botón flotante nuevamente (o botón X) | Panel se cierra. Conversación preservada. | Alta |

---

## 2. Conversación con IA

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AI-020 | Enviar mensaje | Panel abierto | 1. Escribir pregunta 2. Presionar Enter o clic "Enviar" | Mensaje aparece como burbuja usuario. Respuesta de IA aparece después de loading. | Alta |
| AI-021 | Loading indicator | Mensaje enviado | 1. Enviar mensaje y observar | Indicador de carga visible mientras la IA procesa la respuesta. | Media |
| AI-022 | Respuesta con formato | IA responde | 1. Verificar formato de respuesta | Texto con `whitespace-pre-wrap`. Saltos de línea y formato respetados. | Media |
| AI-023 | Sugerencias rápidas | Chat vacío (nuevo) | 1. Abrir chat nuevo | 4 botones de sugerencias pre-construidas visibles. | Media |
| AI-024 | Usar sugerencia rápida | Sugerencias visibles | 1. Clic en una sugerencia | Pregunta se envía automáticamente. Respuesta de IA aparece. | Media |
| AI-025 | Error de API | Servicio IA no disponible | 1. Enviar mensaje cuando API falla | Mensaje de error visible. Chat no se rompe. | Alta |

---

## 3. Historial de Chats

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AI-040 | Crear nuevo chat | Panel abierto | 1. Clic en botón "+" (nuevo chat) | Se crea chat vacío. Chat anterior preservado en historial. | Alta |
| AI-041 | Ver historial | Múltiples chats creados | 1. Clic en ícono de reloj (historial) | Lista de chats anteriores con preview del último mensaje. | Alta |
| AI-042 | Buscar en historial | Historial abierto | 1. Escribir texto en campo búsqueda | Filtra chats por contenido de mensajes. | Media |
| AI-043 | Cambiar entre chats | Historial con múltiples chats | 1. Clic en chat del historial | Se carga el chat seleccionado con todos sus mensajes. | Alta |
| AI-044 | Eliminar chat | Historial abierto | 1. Clic en ícono de basura en un chat | Chat eliminado del historial. | Media |
| AI-045 | Máximo 30 chats en localStorage | 30+ chats creados | 1. Crear chat #31 | El chat más antiguo se elimina automáticamente. Máximo 30 en localStorage. | Baja |
| AI-046 | Persistencia en localStorage | Chat activo | 1. Cerrar y reabrir panel 2. Refrescar página | Chats persisten en `localStorage('omni_ai_chats')`. | Alta |

---

## 4. Identificación de Usuario

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AI-060 | Nombre de agente | Login como agente | 1. Abrir AI chat | Saludo usa nombre del agente (de `getAgentData().name`). | Media |
| AI-061 | Nombre de admin | Login como Admin AT | 1. Abrir AI chat | Saludo usa nombre del admin (de `localStorage('omni_admin_user')`). | Media |
| AI-062 | Fallback "Usuario" | Login como cliente | 1. Abrir AI chat | Saludo dice "¡Hola, Usuario!". | Baja |

---

## 5. Trigger Externo (desde Soporte)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AI-080 | Abrir desde SupportView | Creando ticket de soporte | 1. Clic en "Crear Ticket" 2. Clic en "Prueba con el Asistente AI" | Panel de AI chat se abre automáticamente (evento `openOmniAssistant`). | Media |
