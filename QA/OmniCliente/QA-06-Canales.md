# QA-06 — Canales

**Proyecto:** OmniCliente — Portal Omnicanal  
**Módulo:** ChannelsView + ChannelTypesView  
**Versión:** 1.0  
**Fecha:** 2026-03-29  
**Roles cubiertos:** Admin AT, Cliente (API Key), Supervisor (lectura)  

---

## 1. Listado de Canales

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-001 | Ver lista de canales | Login como Cliente | 1. Ir a "Canales" en sidebar | Lista de canales con nombre, tipo, estado activo/inactivo, fecha creación. | Alta |
| CH-002 | Contador de canales vs plan | Lista visible | 1. Ver header de sección | Muestra "{activeChannels} de {maxChannels} canales — Plan {planType}" (solo cuenta activos). | Alta |
| CH-003 | Copiar webhook URL | Canal existente | 1. Clic en botón "Copiar webhook URL" | URL copiada al portapapeles. Feedback visual de copiado. | Media |

---

## 2. Crear Canal

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-020 | Abrir formulario de creación | Login como Cliente | 1. Clic en "Agregar Canal" | Formulario con campos: tipo de canal (dropdown dinámico), nombre (requerido), campos dinámicos según tipo. | Alta |
| CH-021 | Crear canal WhatsApp | Formulario abierto | 1. Seleccionar tipo "WhatsApp" 2. Ingresar nombre 3. Campos dinámicos de WhatsApp aparecen 4. Ingresar ycloud_api_key 5. Clic "Crear" | Canal creado. Modal muestra webhook_secret (solo se ve una vez). | Alta |
| CH-022 | Campos dinámicos según tipo | Formulario abierto | 1. Cambiar tipo de canal | Campos se adaptan según `fields_json` del channel type seleccionado. | Alta |
| CH-023 | Admin AT ve campo client_id | Login como Admin AT | 1. Abrir formulario | Campo adicional "Cliente" (select) requerido. | Alta |
| CH-024 | Nombre de canal requerido | Formulario abierto | 1. Dejar nombre vacío 2. Intentar crear | Validación: nombre requerido. | Alta |
| CH-025 | Webhook secret en resultado | Canal recién creado | 1. Crear canal exitosamente | Modal de resultado muestra webhook_secret con advertencia "solo se muestra una vez". | Alta |
| CH-026 | Campo ycloud_api_key con toggle | Formulario WhatsApp | 1. Ver campo API key | Campo con ícono de ojo para toggle visibilidad (password ↔ text). | Media |

---

## 3. Editar Canal

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-040 | Editar nombre de canal | Canal existente | 1. Clic "Editar" 2. Cambiar nombre 3. Guardar | Nombre actualizado. | Alta |
| CH-041 | Editar campos técnicos | Canal existente | 1. Editar phone_number, page_id, bot_token, ycloud_api_key, etc. 2. Guardar | Campos técnicos actualizados. | Media |
| CH-042 | Toggle activo/inactivo | Canal existente | 1. Clic en toggle de estado | Canal cambia entre activo e inactivo. Contador de canales activos se actualiza. | Alta |

---

## 4. Eliminar Canal

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-060 | Eliminar canal | Canal existente | 1. Clic en "Eliminar" 2. Confirmar en ConfirmDeleteModal | Canal eliminado. Desaparece de la lista. | Alta |
| CH-061 | Confirmar eliminación requerida | Clic en eliminar | 1. Modal de confirmación aparece | Debe confirmar explícitamente. No se elimina accidentalmente. | Alta |

---

## 5. Límites de Plan

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-080 | Límite de canales alcanzado | Canales activos = máximo del plan | 1. Verificar botón "Agregar Canal" | Botón deshabilitado. Alerta amber: "Límite alcanzado". | Alta |
| CH-081 | Desactivar canal libera cupo | Canales al límite | 1. Desactivar un canal 2. Verificar botón "Agregar Canal" | Botón habilitado nuevamente (solo cuentan activos). | Media |

---

## 6. Tipos de Canal (Admin AT Only)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| CH-100 | Ver tipos de canal | Login como Admin AT | 1. Ir a "Tipos de Canal" | Lista de tipos: slug, label, emoji, color, orden, campos dinámicos. | Alta |
| CH-101 | Crear tipo de canal | Admin AT | 1. Clic "Agregar Tipo" 2. Ingresar slug (lowercase, a-z0-9-_), label, emoji, color 3. Agregar campos dinámicos (key, label, placeholder) 4. Guardar | Tipo creado. Disponible al crear canales. | Alta |
| CH-102 | Validar slug formato | Creando tipo | 1. Ingresar slug con mayúsculas o espacios | Validación: solo `[a-z0-9-_]` permitidos. | Alta |
| CH-103 | Slug no editable en edición | Editando tipo existente | 1. Verificar campo slug | Campo slug deshabilitado en modo edición. | Media |
| CH-104 | Agregar campos dinámicos | Creando/editando tipo | 1. Clic "Agregar Campo" 2. Ingresar key, label, placeholder | Campo agregado a la lista. Se puede eliminar con botón X. | Media |
| CH-105 | Eliminar tipo con canales asignados | Tipo con canales | 1. Intentar eliminar tipo | Error: no se puede eliminar tipo que tiene canales asignados. | Alta |
| CH-106 | Color picker con 11 opciones | Creando/editando tipo | 1. Abrir selector de color | 11 colores disponibles: green-500, pink-500, sky-500, etc. | Baja |
