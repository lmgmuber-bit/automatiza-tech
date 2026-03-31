# QA-12 — Responsive y Móvil

**Proyecto:** OmniCliente — Portal Omnicanal  
**Módulo:** Todas las vistas — Comportamiento responsive y móvil  
**Versión:** 1.0  
**Fecha:** 2026-03-29  
**Breakpoints Tailwind:** sm (640px), md (768px), lg (1024px)  

---

## 1. Sidebar y Navegación

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MO-001 | Sidebar colapsada en móvil | Ancho < 768px | 1. Abrir portal en dispositivo / viewport móvil | Sidebar colapsada por defecto. Botón hamburguesa visible para abrir. | Alta |
| MO-002 | Abrir sidebar con hamburguesa | Sidebar colapsada | 1. Clic en ícono de menú hamburguesa | Sidebar se abre como overlay. Menú completo visible. | Alta |
| MO-003 | Cerrar sidebar al seleccionar | Sidebar abierta en móvil | 1. Clic en opción del menú | Sidebar se cierra automáticamente. Vista seleccionada se carga. | Alta |
| MO-004 | Sidebar expandida en desktop | Ancho ≥ 1024px | 1. Abrir portal en pantalla grande | Sidebar fija a la izquierda, expandida. No necesita hamburguesa. | Alta |

---

## 2. Inbox / Chat en Móvil

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MO-020 | Lista de conversaciones en móvil | Ancho < 768px, Inbox abierto | 1. Ver sección Inbox | Lista de conversaciones ocupa ancho completo. | Alta |
| MO-021 | Abrir chat en móvil | Lista visible en móvil | 1. Clic en conversación | Vista de chat ocupa pantalla completa. Botón "Atrás" para volver a lista. | Alta |
| MO-022 | Botón atrás desde chat | Chat abierto en móvil | 1. Clic en botón "Atrás" | Vuelve a la lista de conversaciones. | Alta |
| MO-023 | Input de mensaje en móvil | Chat abierto en móvil | 1. Escribir mensaje en input inferior | Teclado virtual no oculta el input. Input visible sobre el teclado. | Alta |
| MO-024 | Scroll de mensajes en móvil | Chat con muchos mensajes | 1. Scroll vertical en el chat | Scroll suave. Mensajes nuevos al fondo. Auto-scroll al recibir mensaje. | Media |

---

## 3. Perfil en Móvil

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MO-040 | Avatar adapta tamaño | Ancho < 640px | 1. Abrir "Mi Perfil" | Avatar se reduce a 144px (w-36). En desktop 176px (w-44). | Media |
| MO-041 | Modal de foto en móvil | Avatar con foto en móvil | 1. Clic en foto de perfil | Modal se abre. Foto 224px (w-56). En desktop 320px (w-80). | Media |
| MO-042 | Formulario de perfil stacked | Ancho < 640px | 1. Ver formulario de edición | Campos apilados verticalmente (1 columna en lugar de 2). | Media |

---

## 4. Tablas y Listados en Móvil

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MO-060 | Tabla de agentes scroll horizontal | Ancho < 768px | 1. Ir a "Agentes" | Tabla con scroll horizontal si no cabe. O vista cards apiladas. | Media |
| MO-061 | Tabla de auditoría responsive | Ancho < 768px | 1. Ir a "Auditoría" | Tabla legible con scroll horizontal o layout adaptado. | Media |
| MO-062 | Tickets master-detail en móvil | Ancho < 768px | 1. Ir a "Soporte" | Lista ocupa ancho completo. Al seleccionar ticket, detalle ocupa pantalla completa con botón "Atrás". | Media |

---

## 5. Formularios en Móvil

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MO-080 | Formulario de login en móvil | Ancho < 640px | 1. Página de login en móvil | Formulario centrado, ancho completo, botones touch-friendly (min 44px alto). | Alta |
| MO-081 | Crear ticket en móvil | Ancho < 640px | 1. Abrir formulario de crear ticket | Campos apilados. Botones de tamaño adecuado para touch. | Media |
| MO-082 | Bot config en móvil | Ancho < 640px | 1. Ver BotConfigUnifiedView | Tabs legibles. Formularios apilados. Slider de temperatura usable en touch. | Media |
| MO-083 | Prompts en móvil | Ancho < 768px | 1. Tab "Prompts" | Secciones colapsables funcionan con tap. Formularios de prompt legibles. | Media |

---

## 6. Widget AI en Móvil

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MO-100 | Botón AI en móvil | Ancho < 640px | 1. Verificar botón flotante | Botón visible y no bloquea contenido importante. | Media |
| MO-101 | Panel AI en móvil | Ancho < 640px | 1. Abrir panel AI | Panel ocupa ancho completo o casi completo. Teclado no oculta input. | Alta |
| MO-102 | Cerrar panel AI en móvil | Panel abierto | 1. Clic en X o fuera del panel | Panel se cierra correctamente. | Media |

---

## 7. Touch Targets y Accesibilidad

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| MO-120 | Botones mínimo 44x44px | Cualquier vista en móvil | 1. Inspeccionar botones de acción | Todos los botones interactivos ≥ 44x44px para touch. | Media |
| MO-121 | Dropdowns usables en touch | Formularios en móvil | 1. Abrir selects y dropdowns | Nativos del OS o custom dropdowns con áreas de tap amplias. | Media |
| MO-122 | No zoom horizontal accidental | Cualquier vista en móvil | 1. Navegar entre vistas | Viewport no se desplaza horizontalmente (no overflow-x oculto). | Media |
