# QA-02 — Bandeja de Entrada y Conversaciones

**Proyecto:** OmniCliente — Portal Omnicanal  
**Módulo:** Inbox, Chat, Transferencias y Notificaciones  
**Versión:** 1.0  
**Fecha:** 2026-03-29  
**Roles cubiertos:** Agente, Supervisor, Cliente, Super Admin  

---

## 1. Vista General del Inbox

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| IN-001 | Cargar inbox como agente | Login como agente | 1. Navegar a "Mis Conversaciones" | Muestra lista de conversaciones del agente. Panel derecho vacío o con mensaje "Selecciona una conversación". | Alta |
| IN-002 | Cargar inbox como supervisor | Login como supervisor | 1. Navegar a "Bandeja Unificada" | Muestra TODAS las conversaciones del cliente, no solo las propias. | Alta |
| IN-003 | Toggle "Todas / Mis conversaciones" (agente) | Login como agente | 1. Cambiar entre "Todas" y "Mis conversaciones" | En "Mis": solo conv. asignadas al agente. En "Todas": todas las del cliente. | Alta |
| IN-004 | Polling automático (5 segundos) | Inbox abierto | 1. Abrir inbox 2. Desde otro sistema, enviar un mensaje al canal 3. Esperar ~5 segundos | Nuevo mensaje aparece automáticamente sin recargar. Badge de no leído se actualiza. | Alta |
| IN-005 | Badge de no leídos en sidebar | Hay conversaciones con mensajes nuevos | 1. Verificar sidebar | Número de no leídos aparece junto a "Inbox" en la sidebar. Título del browser muestra `(N) OmniCliente`. | Media |

---

## 2. Filtros y Búsqueda

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| IN-020 | Filtrar por canal (WhatsApp, Telegram, etc.) | Conversaciones de múltiples canales | 1. Seleccionar filtro de canal 2. Elegir "WhatsApp" | Solo muestra conversaciones de WhatsApp. | Media |
| IN-021 | Filtrar por estado | Conversaciones en distintos estados | 1. Seleccionar filtro de estado 2. Elegir "Sin asignar" | Solo muestra conversaciones sin agente asignado. | Media |
| IN-022 | Filtrar por estado "Asignadas" | Conv. asignadas existen | 1. Filtrar por "Asignadas" o "Activas" | Muestra solo las conversaciones con agente asignado. | Media |
| IN-023 | Buscar por nombre de contacto | Conversaciones con contactos nombrados | 1. Escribir nombre parcial en campo de búsqueda | Lista se filtra mostrando solo las que coinciden. | Media |
| IN-024 | Buscar por número de teléfono | Conversaciones con teléfonos | 1. Escribir número parcial en búsqueda | Filtra por número de teléfono del contacto. | Media |
| IN-025 | Limpiar filtros | Filtros aplicados | 1. Clic en "Limpiar" o borrar filtros | Vuelve a mostrar todas las conversaciones. | Baja |

---

## 3. Chat / Mensajes

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| IN-040 | Seleccionar conversación y ver mensajes | Conv. con historial | 1. Clic en una conversación de la lista | Panel derecho muestra el historial de mensajes con nombre, hora, contenido. Scroll al último mensaje. | Alta |
| IN-041 | Enviar mensaje de texto | Conv. asignada al agente | 1. Seleccionar conv. asignada 2. Escribir mensaje en textarea 3. Clic en enviar (o Enter) | Mensaje aparece en el chat. Se envía al canal del contacto. | Alta |
| IN-042 | Enviar imagen | Conv. asignada al agente | 1. Clic en ícono de adjuntar imagen 2. Seleccionar imagen 3. Enviar | Imagen se sube, aparece en el chat como miniatura. | Alta |
| IN-043 | Enviar mensaje en conv. no asignada | Conv. sin asignar | 1. Seleccionar conv. sin asignar 2. Intentar escribir mensaje | Campo de texto deshabilitado o muestra botón "Tomar conversación" primero. | Alta |
| IN-044 | Mensajes del bot vs agente | Conv. con mensajes mixtos | 1. Ver historial de una conversación | Mensajes del cliente a la izquierda (verde), del agente/bot a la derecha. Etiquetas claras de remitente. | Media |
| IN-045 | Scroll en historial largo | Conv. con muchos mensajes | 1. Abrir conv. con 50+ mensajes 2. Hacer scroll | Scroll fluido. Mensajes antiguos arriba, recientes abajo. | Baja |

---

## 4. Acciones: Tomar, Liberar, Transferir

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| IN-060 | Tomar conversación (takeover) | Conv. sin asignar o del bot | 1. Seleccionar conv. sin asignar 2. Clic en "Tomar conversación" | Estado cambia a "assigned/active". Agente puede enviar mensajes. | Alta |
| IN-061 | Liberar conversación al bot | Conv. asignada al agente | 1. Clic en "Liberar al bot" 2. Confirmar | Conv. vuelve a estado "bot". Agente ya no puede enviar mensajes. | Alta |
| IN-062 | Transferir a otro agente | Conv. asignada, otros agentes activos | 1. Clic en "Transferir" 2. Seleccionar agente del dropdown 3. Confirmar | Conv. se reasigna al nuevo agente. Aparece en su inbox. | Alta |
| IN-063 | Transferir a supervisor | Conv. asignada, supervisor activo | 1. Clic en "Transferir" 2. Seleccionar supervisor (aparece con badge "Supervisor") 3. Confirmar | Conv. se transfiere al supervisor correctamente. | Alta |
| IN-064 | Transferir — dropdown muestra roles | Agentes y supervisores activos | 1. Abrir dropdown de transferencia | Agentes agrupados por rol (Supervisores arriba, Agentes abajo) con badges de color. | Media |
| IN-065 | Transferir a agente con chats al máximo | Agente destino con active_chats >= max_concurrent | 1. Intentar transferir a agente saturado | Muestra error indicando que el agente tiene demasiados chats activos. | Alta |
| IN-066 | Transferir con max_concurrent_chats = 0 | Agente destino con max=0 (ilimitado) | 1. Transferir a agente con max_concurrent_chats = 0 | Transferencia exitosa (0 = sin límite). | Alta |
| IN-067 | No transferir a sí mismo | Conv. asignada al agente actual | 1. Abrir dropdown de transferencia | El agente actual NO aparece en la lista de transferencia. | Media |

---

## 5. Notificaciones del Navegador

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| IN-080 | Solicitud de permiso de notificaciones | Primer login | 1. Iniciar sesión como agente | Browser solicita permiso de notificaciones. | Media |
| IN-081 | Notificación de nuevo mensaje | Permisos concedidos, pestaña en segundo plano | 1. Tener inbox abierto 2. Recibir mensaje nuevo desde otro sistema | Notificación del navegador aparece con nombre del contacto y preview del mensaje. | Media |
| IN-082 | Clic en notificación abre conversación | Notificación recibida | 1. Clic en la notificación del browser | Pestaña se enfoca y selecciona la conversación correspondiente. | Baja |

---

## 6. Email de Notificación

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| IN-100 | Email al asignar conversación | Agente con email | 1. Asignar/tomar una conversación | Agente recibe email con datos del contacto, últimos mensajes, y link al portal. Email tiene logo AutomatizaTech. | Media |
| IN-101 | Email al transferir conversación | Agente destino con email | 1. Transferir conversación a otro agente | Agente destino recibe email de notificación con info de la conversación. | Media |
