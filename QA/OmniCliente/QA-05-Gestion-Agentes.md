# QA-05 — Gestión de Agentes

**Proyecto:** OmniCliente — Portal Omnicanal  
**Módulo:** AgentsView — CRUD de Agentes, Roles, Límites de Plan  
**Versión:** 1.0  
**Fecha:** 2026-03-29  
**Roles cubiertos:** Admin AT, Cliente (API Key), Supervisor, Agente (sin acceso)  

---

## 1. Listado de Agentes

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AG-001 | Ver lista de agentes | Login como Cliente o Supervisor | 1. Ir a "Agentes" en sidebar | Tabla con agentes: nombre, email, rol, estado, canal, chats máx, fecha creación. | Alta |
| AG-002 | Agente regular no ve sección | Login como Agente (no supervisor) | 1. Verificar sidebar | Menú "Agentes" no visible para agentes regulares. | Alta |
| AG-003 | Buscar agente por texto | Lista visible | 1. Escribir nombre o email en campo búsqueda 2. Presionar Enter | Lista filtrada por coincidencia de texto. | Media |
| AG-004 | Ordenar por nombre | Lista visible | 1. Clic en header "Nombre" | Alterna entre ASC y DESC por nombre. | Baja |
| AG-005 | Ordenar por rol | Lista visible | 1. Clic en header "Rol" | Alterna entre ASC y DESC por rol. | Baja |
| AG-006 | Ordenar por fecha creación | Lista visible | 1. Clic en header "Fecha" | Alterna entre ASC y DESC por created_at. | Baja |
| AG-007 | Cambiar registros por página | Lista visible | 1. Cambiar selector de per_page a 50 | Muestra 50 registros por página. Opciones: 10, 20, 50, 100. | Baja |
| AG-008 | Paginación | >10 agentes | 1. Navegar páginas con botones de paginación | Cambia de página correctamente. Muestra hasta 5 botones de página. | Media |

---

## 2. Crear Agente

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AG-020 | Abrir formulario de creación | Login como Cliente | 1. Clic en "Agregar Agente" | Formulario con campos: nombre, email, rol, password, chats máx, canal, horario inicio/fin, días disponibles. | Alta |
| AG-021 | Crear agente exitoso | Formulario abierto | 1. Llenar nombre, email, password (min 6 chars) 2. Seleccionar rol "Agente" 3. Clic "Crear Agente" | Agente creado. Aparece en lista. Contador actualizado. | Alta |
| AG-022 | Crear agente como Supervisor | Login como Supervisor | 1. Clic en "Agregar Agente" 2. Verificar opciones de rol | Solo puede asignar roles "Agente" y "Supervisor". NO puede asignar "Admin". | Alta |
| AG-023 | Admin AT ve campo client_id | Login como Admin AT | 1. Abrir formulario de crear | Campo adicional "Cliente" (select) para asignar a qué cliente pertenece. | Alta |
| AG-024 | Email duplicado | Formulario abierto | 1. Ingresar email ya existente 2. Intentar crear | Error del servidor indicando email duplicado. | Alta |
| AG-025 | Password menor a 6 caracteres | Formulario abierto | 1. Ingresar password "123" 2. Intentar crear | Validación: password mínimo 6 caracteres. | Alta |
| AG-026 | Asignar canal específico | Formulario abierto | 1. Seleccionar canal del dropdown 2. Crear | Agente asignado a ese canal. Default: "Todos los canales". | Media |
| AG-027 | Configurar horario de agente | Formulario abierto | 1. Establecer schedule_start: 09:00 2. schedule_end: 18:00 3. available_days: 1,2,3,4,5 | Horario guardado. Se muestra en la lista. | Media |
| AG-028 | Chats máximos concurrentes | Formulario abierto | 1. Cambiar max_concurrent_chats a 10 | Valor guardado. Rango: 1-20, default 5. | Media |

---

## 3. Editar Agente

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AG-040 | Abrir edición de agente | Agente existente | 1. Clic en botón "Editar" de un agente | Formulario de edición con datos precargados. Campos adicionales: estado, departamento. | Alta |
| AG-041 | Cambiar rol de agente | Editando agente | 1. Cambiar rol de "Agente" a "Supervisor" 2. Guardar | Rol actualizado. Permisos cambian inmediatamente. | Alta |
| AG-042 | Cambiar estado del agente | Editando agente | 1. Cambiar estado (activo/inactivo) 2. Guardar | Estado actualizado. Agente inactivo no puede loguearse. | Alta |
| AG-043 | Cambiar password (opcional) | Editando agente | 1. Dejar campo password vacío 2. Guardar | No cambia password. Solo se envía si se llena. | Media |
| AG-044 | Asignar departamento | Editando agente | 1. Escribir "Ventas" en departamento 2. Guardar | Departamento asignado y visible en perfil del agente. | Baja |
| AG-045 | Supervisor no puede editar admin | Login como Supervisor, agente con rol admin | 1. Verificar acciones disponibles | Supervisor solo puede asignar rol "agente" o "supervisor". | Alta |

---

## 4. Eliminar Agente

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AG-060 | Eliminar agente (solo Admin) | Login como Admin AT | 1. Clic en "Eliminar" 2. Confirmar en ConfirmDeleteModal con API key | Agente eliminado. Desaparece de la lista. | Alta |
| AG-061 | Cliente no puede eliminar | Login como Cliente | 1. Verificar acciones disponibles | No aparece botón "Eliminar". Solo Admin AT puede eliminar. | Alta |
| AG-062 | Supervisor no puede eliminar | Login como Supervisor | 1. Verificar acciones disponibles | No aparece botón "Eliminar". | Alta |

---

## 5. Límites de Plan

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AG-080 | Mostrar contador de agentes | Lista visible | 1. Verificar header de la sección | Muestra "{total} de {maxAgents} agentes — Plan {planType}". | Alta |
| AG-081 | Límite alcanzado | Total agentes = máximo del plan | 1. Verificar botón "Agregar Agente" | Botón deshabilitado. Alerta amber: "Límite alcanzado". | Alta |
| AG-082 | Crear agente sobre el límite | Total = max | 1. Intentar agregar agente | Botón deshabilitado. No se puede crear. | Alta |
