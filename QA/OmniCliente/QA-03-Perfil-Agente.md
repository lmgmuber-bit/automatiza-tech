# QA-03 — Perfil del Agente

**Proyecto:** OmniCliente — Portal Omnicanal  
**Módulo:** Perfil, Avatar, Cambio de Contraseña  
**Versión:** 1.0  
**Fecha:** 2026-03-29  
**Roles cubiertos:** Agente, Supervisor  

---

## 1. Vista de Perfil

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| PF-001 | Cargar perfil del agente | Login como agente | 1. Clic en "Mi Perfil" en sidebar | Muestra foto de perfil grande (144-176px), nombre, email, rol (Agente/Supervisor), departamento, empresa. | Alta |
| PF-002 | Mostrar avatar o inicial | Agente sin foto subida | 1. Abrir perfil | Muestra círculo con gradiente y la inicial del nombre en lugar de foto. | Media |
| PF-003 | Mostrar estadísticas | Agente activo | 1. Abrir perfil | Sección "Estadísticas" muestra chats activos y máximo simultáneos. | Baja |

---

## 2. Foto de Perfil (Avatar)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| PF-020 | Ver foto en modal grande | Agente con avatar subido | 1. Clic en la foto de perfil | Se abre modal con foto grande (224-320px), nombre, y botón "Cambiar foto de perfil". | Alta |
| PF-021 | Cambiar foto desde modal | Modal de foto abierto | 1. Clic en "Cambiar foto de perfil" 2. Seleccionar imagen (JPG/PNG/WebP/GIF) | Modal se cierra, se abre file picker, foto se sube y actualiza en perfil y sidebar. | Alta |
| PF-022 | Cambiar foto con botón rápido | Perfil abierto | 1. Clic en ícono de cámara (botón pequeño sobre la foto) | File picker se abre directamente. Foto se sube y actualiza. | Alta |
| PF-023 | Hover sobre foto muestra overlay | Perfil abierto, desktop | 1. Pasar cursor sobre la foto de perfil | Overlay oscuro con ícono de zoom y texto "Ver foto". | Baja |
| PF-024 | Cerrar modal con clic fuera | Modal abierto | 1. Clic fuera del modal (en el backdrop) | Modal se cierra. | Baja |
| PF-025 | Cerrar modal con botón X | Modal abierto | 1. Clic en botón X de la esquina | Modal se cierra. | Baja |
| PF-026 | Subir formato no soportado | Perfil abierto | 1. Intentar subir archivo .txt o .pdf | No se sube. El file picker solo acepta image/jpeg, png, webp, gif. | Media |

---

## 3. Editar Información

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| PF-040 | Editar nombre | Perfil abierto | 1. Cambiar nombre en el campo "Nombre completo" 2. Clic en "Guardar Cambios" | Nombre se actualiza. Sidebar refleja el cambio. Mensaje de éxito verde. | Alta |
| PF-041 | Editar departamento | Perfil abierto | 1. Escribir departamento (ej: "Ventas") 2. Clic en "Guardar Cambios" | Departamento se actualiza. Badge de departamento aparece en perfil. | Media |
| PF-042 | Email no editable | Perfil abierto | 1. Verificar campo de email | Campo de email en gris, disabled, con nota "(no editable)". | Media |
| PF-043 | Rol no editable | Perfil abierto | 1. Verificar campo de rol | Campo de rol en gris, disabled. Muestra "Agente", "Supervisor" o "Administrador". | Media |
| PF-044 | Guardar con nombre vacío | Perfil abierto | 1. Borrar nombre completo 2. Intentar guardar | Validación impide guardar. Campo requerido. | Media |

---

## 4. Cambio de Contraseña (3 pasos)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| PF-060 | Iniciar flujo de cambio | Perfil abierto | 1. Clic en "Cambiar Contraseña" | Aparece Paso 1: campo para contraseña actual. | Alta |
| PF-061 | Paso 1: verificar contraseña actual correcta | Flujo iniciado | 1. Ingresar contraseña actual correcta 2. Clic en "Verificar" | Avanza a Paso 2: "Nueva contraseña". | Alta |
| PF-062 | Paso 1: contraseña actual incorrecta | Flujo iniciado | 1. Ingresar contraseña actual incorrecta 2. Clic en "Verificar" | Muestra error "Contraseña actual incorrecta". No avanza. | Alta |
| PF-063 | Paso 2: nueva contraseña cumple requisitos | Paso 2 activo | 1. Ingresar contraseña con mayúscula, número, especial, 8+ chars 2. Confirmar contraseña igual 3. Clic en "Continuar" | Indicadores verdes para cada requisito. Avanza a Paso 3. | Alta |
| PF-064 | Paso 2: contraseñas no coinciden | Paso 2 activo | 1. Ingresar nueva contraseña 2. Confirmar con texto distinto | Error "Las contraseñas no coinciden". | Alta |
| PF-065 | Paso 2: contraseña igual a la actual | Paso 2 activo | 1. Ingresar misma contraseña que la actual | Error "La nueva contraseña no puede ser igual a la actual". | Media |
| PF-066 | Paso 2: caracteres prohibidos | Paso 2 activo | 1. Ingresar contraseña con `< > " ' ; \` | Error de caracteres no permitidos. | Alta |
| PF-067 | Paso 2: validación de requisitos en tiempo real | Paso 2 activo | 1. Escribir contraseña letra por letra | Indicadores cambian de gris a verde conforme se cumplen: mín 8 chars, mayúscula, número, especial, sin prohibidos. | Media |
| PF-068 | Paso 3: código de verificación correcto | Email recibido con código 6 dígitos | 1. Ingresar código de 6 dígitos del email 2. Clic en "Confirmar" | Contraseña actualizada. Mensaje de éxito "¡Contraseña actualizada exitosamente!". | Alta |
| PF-069 | Paso 3: código incorrecto | Código enviado | 1. Ingresar código incorrecto | Error "Código de verificación incorrecto". | Alta |
| PF-070 | Paso 3: código expirado (>5 min) | Código enviado hace >5 min | 1. Ingresar código después de 5 minutos | Error "El código ha expirado. Solicita uno nuevo". | Media |
| PF-071 | Cancelar en cualquier paso | Flujo en paso 1, 2 o 3 | 1. Clic en "Cancelar" | Vuelve al estado inicial. Campos limpiados. | Media |
| PF-072 | Toggle visibilidad contraseña | Campos de contraseña | 1. Clic en ícono de ojo en campo de contraseña | Alterna entre tipo password (oculto) y text (visible). | Baja |
