# QA-08 — Auditoría

**Proyecto:** OmniCliente — Portal Omnicanal  
**Módulo:** AuditView — Registro de Eventos y Filtros  
**Versión:** 1.0  
**Fecha:** 2026-03-29  
**Roles cubiertos:** Todos (Agente, Supervisor, Cliente, Admin AT)  

---

## 1. Vista de Auditoría

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AU-001 | Ver registros de auditoría | Login como cualquier rol | 1. Ir a "Auditoría" en sidebar | Tabla con registros: ícono entidad, acción (badge color), tipo+ID entidad, descripción, email usuario, IP, fecha. | Alta |
| AU-002 | Íconos de entidad correctos | Registros visibles | 1. Verificar íconos | 🏢 client, 📡 channel, 🤖 bot_config, 💬 conversation, 👤 agent. | Media |
| AU-003 | Badges de acción con colores | Registros visibles | 1. Verificar badges | create=verde, update=azul, delete=rojo, takeover=púrpura, release=ámbar, transfer=cyan. | Media |
| AU-004 | Mostrar "Sistema" sin email | Evento sin usuario | 1. Verificar columna usuario | Muestra "Sistema" cuando no hay email de usuario asociado. | Baja |

---

## 2. Detalle Expandible

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AU-020 | Expandir detalle de registro | Registro con datos | 1. Clic en "Detalles" de un registro | Se expande mostrando: old_values_json (fondo rojo), new_values_json (fondo verde), user_agent. | Alta |
| AU-021 | Ver valores anteriores | Acción de update | 1. Expandir registro de tipo "update" | Bloque `old_values_json` con fondo rojo muestra estado anterior. | Media |
| AU-022 | Ver valores nuevos | Acción de update | 1. Expandir registro de tipo "update" | Bloque `new_values_json` con fondo verde muestra estado nuevo. | Media |
| AU-023 | Colapsar detalle | Detalle expandido | 1. Clic en "Detalles" nuevamente | Detalle se colapsa. Solo 1 registro expandido a la vez. | Baja |

---

## 3. Filtros y Paginación

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AU-040 | Buscar por texto | Registros visibles | 1. Escribir texto en campo búsqueda 2. Presionar Enter | Filtra registros por descripción, email, entidad. | Media |
| AU-041 | Cambiar registros por página | Registros visibles | 1. Cambiar selector: 10, 20, 50, 100 | Registros por página cambian. | Baja |
| AU-042 | Ordenar por fecha | Registros visibles | 1. Clic en header "Fecha" | Alterna ASC/DESC por created_at. | Media |
| AU-043 | Ordenar por acción | Registros visibles | 1. Clic en header "Acción" | Alterna ASC/DESC por action. | Baja |
| AU-044 | Ordenar por tipo de entidad | Registros visibles | 1. Clic en header "Entidad" | Alterna ASC/DESC por entity_type. | Baja |
| AU-045 | Paginación | >10 registros | 1. Navegar entre páginas | Hasta 5 botones de página visibles. Navegación correcta. | Media |
