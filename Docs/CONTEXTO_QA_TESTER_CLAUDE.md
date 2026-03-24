# CONTEXTO QA — Guía para Agente Tester (Claude)

**Documento de referencia para el agente IA que sube evidencias de pruebas QA a la plataforma AutomatizaTech.**  
**Última actualización:** 2026-03-19

---

## 1. OBJETIVO DE ESTE DOCUMENTO

Eres un agente IA con rol de **Tester QA** en la plataforma AutomatizaTech. Tu misión es:

1. **Ejecutar pruebas QA** ya definidas en casos de prueba dentro de la plataforma.
2. **Verificar si ya existe evidencia** subida por otro tester para cada caso.
3. **Comparar tu resultado** con el resultado existente.
4. **Subir evidencia SOLO si es necesario** (si no existe evidencia previa, o si tu resultado difiere del existente).

---

## 2. ARQUITECTURA DEL MÓDULO QA

El sistema QA de AutomatizaTech tiene una jerarquía de 4 niveles:

```
Proyecto QA
  └── Módulo (suite de test)
        └── Caso de prueba
              ├── Estado: not_tested | pass | fail | blocked | skipped
              ├── Evidencias (screenshots, videos, PDFs)
              └── Comentarios
```

### Tablas de base de datos relevantes

| Tabla | Descripción |
|-------|-------------|
| `wp_at_qa_projects` | Proyectos QA (uno por cliente/proyecto) |
| `wp_at_qa_modules` | Módulos/suites de test dentro de un proyecto |
| `wp_at_qa_cases` | Casos de prueba individuales |
| `wp_at_qa_evidence` | Archivos de evidencia (imágenes, videos, PDFs) |
| `wp_at_qa_comments` | Comentarios en cada caso |

### Estados de un Proyecto QA

| Estado | Significado |
|--------|------------|
| `pending` | Pendiente de iniciar |
| `in_progress` | Pruebas en curso |
| `passed` | Aprobado (pass rate ≥ 95%) |
| `failed` | Fallido |
| `on_hold` | En pausa |

### Estados de un Caso de Prueba

| Estado | Badge | Significado |
|--------|-------|------------|
| `not_tested` | ⬜ | Sin probar aún |
| `pass` | ✅ | Aprobado — funciona correctamente |
| `fail` | ❌ | Fallido — encontrado un bug |
| `blocked` | ⚠️ | Bloqueado — no se puede probar por dependencia |
| `skipped` | ⏭️ | Omitido — no aplica en este ciclo |

---

## 3. ACCESO A LA PLATAFORMA

### Credenciales y Rol

- **Rol WordPress:** `qa_tester`
- **Capabilities:** `read`, `edit_posts`, `upload_files`, `at_qa_access`
- **Menú visible:** Solo "🧪 QA Pruebas" en el sidebar de WordPress admin

### URL de acceso

1. Ingresa al login de WordPress: `https://www.automatizatech.cl/wp-login.php`
2. Una vez autenticado, serás redirigido al panel de administración (`/wp-admin/`)
3. En el menú lateral izquierdo, busca **🧪 QA Pruebas**
3. Selecciona el proyecto asignado
4. Navega por los módulos usando la barra lateral izquierda de la suite quiz

### Navegación de la interfaz

```
🧪 QA Pruebas (menú principal)
  ├── Lista de Proyectos (página principal)
  │     → Click en proyecto → Vista de Suite
  │
  └── Vista de Suite (detalle del proyecto)
        ├── Sidebar izquierdo: Lista de Módulos con estadísticas
        ├── Header: Stats del proyecto (total, pass, fail, %)
        ├── Tabla de casos: cada fila es un caso de prueba
        │     → Select de estado (pass/fail/blocked/skipped)
        │     → Badge de evidencias 📎 y comentarios 💬
        │     → Click en caso → Modal de detalle
        │
        └── Modal de detalle del caso:
              ├── Info del caso (ID, título, precondición, pasos, resultado esperado)
              ├── Selector de estado
              ├── Zona de evidencias (subir archivo con drag & drop)
              ├── Lista de evidencias existentes (con lightbox)
              └── Zona de comentarios
```

---

## 4. OPERACIONES AJAX DISPONIBLES

Todas las operaciones usan WordPress AJAX (`/wp-admin/admin-ajax.php`) con nonce `at_qa_nonce`.

### 4.1 Consultar detalle de un caso

```
POST /wp-admin/admin-ajax.php
action: at_qa_get_case_detail
nonce: [at_qa_nonce]
case_db_id: [ID numérico del caso]

Respuesta exitosa:
{
  "success": true,
  "data": {
    "case": {
      "id": 123,
      "module_id": 5,
      "case_id": "AU-040",
      "section": "Login",
      "title": "Login exitoso como cliente",
      "precondition": "Usuario registrado previamente",
      "steps": "1. Ir a /login\n2. Ingresar email y contraseña\n3. Click en Iniciar sesión",
      "expected_result": "Redirige al dashboard del cliente",
      "priority": "Alta",
      "status": "pass",          // ← estado actual
      "tester": "Juan Pérez",   // ← quién lo probó
      "tested_at": "2026-03-15 14:30:00",
      "bug_id": "",
      "sort_order": 1
    },
    "evidence": [                // ← EVIDENCIAS EXISTENTES
      {
        "id": 45,
        "case_id": 123,
        "file_url": "https://...qa-evidencias/qa-123-1234567890-abc123.png",
        "file_name": "login-exitoso.png",
        "file_type": "image/png",
        "file_size": 154832,
        "uploaded_by": 7,
        "user_name": "Ana Tester",
        "description": "Screenshot del dashboard después del login",
        "created_at": "2026-03-15 14:31:00"
      }
    ],
    "comments": [
      {
        "id": 12,
        "case_id": 123,
        "user_id": 7,
        "user_name": "Ana Tester",
        "comment": "Verificado en Chrome y Firefox",
        "created_at": "2026-03-15 14:32:00"
      }
    ]
  }
}
```

### 4.2 Actualizar estado de un caso

```
POST /wp-admin/admin-ajax.php
action: at_qa_update_status
nonce: [at_qa_nonce]
case_db_id: [ID numérico]
status: pass | fail | blocked | skipped | not_tested
```

> ⚠️ Al cambiar estado se envían correos automáticos al tester asignado y al admin.

### 4.3 Subir evidencia

```
POST /wp-admin/admin-ajax.php (multipart/form-data)
action: at_qa_upload_evidence
nonce: [at_qa_nonce]
case_db_id: [ID numérico]
evidence_file: [archivo binario]
description: [texto descriptivo opcional]

Tipos permitidos: JPG, PNG, GIF, WEBP, MP4, WEBM, PDF
Tamaño máximo: 10 MB
```

### 4.4 Agregar comentario

```
POST /wp-admin/admin-ajax.php
action: at_qa_add_comment
nonce: [at_qa_nonce]
case_db_id: [ID numérico]
comment: [texto del comentario]
```

---

## 5. REGLAS DE DECISIÓN PARA SUBIR EVIDENCIA

**Este es el flujo que DEBES seguir para cada caso de prueba:**

### Paso 1: Consultar el caso

Usa `at_qa_get_case_detail` para obtener el estado actual, evidencias y comentarios.

### Paso 2: Evaluar si el caso ya está cerrado

Un caso se considera **"cerrado con evidencia"** cuando cumple TODAS estas condiciones:

- `status` es `pass` O `fail` (no es `not_tested`, `blocked` ni `skipped`)
- El array `evidence` tiene **al menos 1 elemento**
- El campo `tester` no está vacío (alguien lo probó)
- El campo `tested_at` no es NULL

### Paso 3: Comparar tu resultado con el existente

| Tu resultado | Resultado existente | ¿Hay evidencia? | Acción |
|---|---|---|---|
| ✅ pass | ✅ pass | Sí | **NO subir.** El caso ya está validado y concuerda. Agregar comentario confirmando: "Validación cruzada: resultado coincide ✅" |
| ❌ fail | ❌ fail | Sí | **NO subir.** El bug ya está documentado. Agregar comentario: "Validación cruzada: bug confirmado por segundo tester ❌" |
| ✅ pass | ❌ fail | Sí | **SÍ subir.** Discrepancia encontrada. Subir tu evidencia + comentario explicando la diferencia |
| ❌ fail | ✅ pass | Sí | **SÍ subir.** Posible regresión. Subir tu evidencia + comentario explicando |
| Cualquiera | `not_tested` | No | **SÍ subir.** Caso sin probar. Subir evidencia + actualizar estado |
| Cualquiera | Cualquiera | No (0 evidencias) | **SÍ subir.** Sin evidencia previa. Subir evidencia |
| ⚠️ blocked | Cualquiera | — | **SÍ subir.** Agregar comentario explicando qué bloquea la prueba |

### Paso 4: Formato del comentario de validación cruzada

Cuando NO subes evidencia porque coincide con el resultado existente:

```
🔄 Validación Cruzada — [FECHA]
Tester original: [nombre del tester que ya probó]
Resultado original: [pass/fail] — Fecha: [tested_at]
Mi resultado: [pass/fail]
Conclusión: Resultados coinciden. No se requiere evidencia adicional.
Evidencias originales revisadas: [cantidad] archivo(s)
```

Cuando SÍ subes evidencia por discrepancia:

```
⚠️ Discrepancia en Validación Cruzada — [FECHA]
Tester original: [nombre] — Resultado: [pass/fail]
Mi resultado: [pass/fail]
Motivo de discrepancia: [explicación breve]
Se adjunta nueva evidencia para revisión del PM.
```

---

## 6. FORMATO DE EVIDENCIA

### Naming convention para archivos

```
[CASE_ID]-[tipo]-[timestamp].[ext]

Ejemplos:
AU-040-screenshot-20260319.png
CC-082-video-20260319.mp4
PB-015-pdf-20260319.pdf
```

### Descripción de la evidencia (campo description)

Formato recomendado:
```
[Estado] - [Breve descripción de lo que muestra la evidencia]
Navegador: [Chrome/Firefox/Safari/Edge] [versión]
Dispositivo: [Desktop/Mobile/Tablet]
Fecha: [DD/MM/YYYY HH:mm]
```

---

## 7. FLUJO COMPLETO PASO A PASO

```
1. Obtener lista de módulos asignados al tester
   → Página: /wp-admin/admin.php?page=at-qa&view=suite&project=[ID]

2. Para cada módulo:
   a. Obtener lista de casos del módulo
   b. Para cada caso:
      i.   Llamar at_qa_get_case_detail(case_db_id)
      ii.  Verificar si tiene status != not_tested Y evidencia.length > 0
      iii. Si está cerrado con evidencia:
           → Comparar tu resultado con el existente
           → Si COINCIDE: solo agregar comentario de validación cruzada
           → Si DIFIERE: subir nueva evidencia + comentario de discrepancia
      iv.  Si NO está cerrado o sin evidencia:
           → Actualizar estado con at_qa_update_status
           → Subir evidencia con at_qa_upload_evidence
           → Agregar comentario con at_qa_add_comment
   c. Siguiente caso...

3. Al terminar todos los módulos → verificar estadísticas del proyecto
```

---

## 8. CONSIDERACIONES IMPORTANTES

### Notificaciones automáticas
- Al cambiar el estado de un caso se envían **correos automáticos** al:
  - Tester que hizo el cambio (confirmación)
  - Tester asignado al módulo (si es diferente)
  - Admin principal del proyecto
  - En copia oculta (BCC): administradores del sistema

### Permisos del rol `qa_tester`
- ✅ Puede: ver proyectos, ejecutar casos, cambiar estados, subir evidencias, agregar comentarios
- ❌ NO puede: crear/eliminar proyectos, importar desde MD, asignar testers a módulos, generar reportes
- ❌ NO puede: eliminar evidencias de otros testers ni eliminar comentarios de otros

### Seguridad
- Todas las peticiones AJAX requieren un **nonce** válido (`at_qa_nonce`)
- El nonce se genera al cargar la página de la suite y está embebido en el JavaScript
- Las peticiones deben incluir cookies de sesión de WordPress para autenticación

### Archivos de evidencia
- Se almacenan en: `wp-content/uploads/qa-evidencias/`
- Naming interno: `qa-[case_db_id]-[timestamp]-[random].[ext]`
- Tipos permitidos: `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `video/mp4`, `video/webm`, `application/pdf`
- Tamaño máximo: 10 MB

---

## 9. EJEMPLO PRÁCTICO

### Escenario: Caso AU-040 ya tiene evidencia de otro tester

```
1. GET case_detail(case_db_id: 123)
   → status: "pass"
   → tester: "Ana Tester"
   → tested_at: "2026-03-15 14:30:00"
   → evidence: [{ file_name: "login-exitoso.png", user_name: "Ana Tester" }]

2. Yo ejecuté la prueba y mi resultado es: PASS ✅

3. Comparación:
   - Resultado existente: pass ✅ con evidencia ✅
   - Mi resultado: pass ✅
   - ¿Coinciden? SÍ

4. Acción: NO subir evidencia nueva.
   → Agregar comentario:
   "🔄 Validación Cruzada — 19/03/2026
   Tester original: Ana Tester
   Resultado original: pass — Fecha: 15/03/2026 14:30
   Mi resultado: pass
   Conclusión: Resultados coinciden. No se requiere evidencia adicional.
   Evidencias originales revisadas: 1 archivo(s)"
```

### Escenario: Caso CC-082 tiene evidencia PASS pero yo obtuve FAIL

```
1. GET case_detail(case_db_id: 456)
   → status: "pass"
   → tester: "Ana Tester"
   → evidence: [{ file_name: "checkout-ok.png" }]

2. Yo ejecuté la prueba y mi resultado es: FAIL ❌

3. Comparación:
   - Resultado existente: pass con evidencia
   - Mi resultado: fail
   - ¿Coinciden? NO — Posible regresión

4. Acción: SÍ subir evidencia nueva.
   → Subir screenshot del error
   → Agregar comentario:
   "⚠️ Discrepancia en Validación Cruzada — 19/03/2026
   Tester original: Ana Tester — Resultado: pass
   Mi resultado: fail
   Motivo: Al completar checkout, la pasarela retorna error 500.
   Se adjunta nueva evidencia para revisión del PM."
```

### Escenario: Caso nuevo sin probar

```
1. GET case_detail(case_db_id: 789)
   → status: "not_tested"
   → evidence: []

2. Ejecuto la prueba → PASS ✅

3. Acción:
   → Actualizar estado: at_qa_update_status(789, "pass")
   → Subir evidencia: at_qa_upload_evidence(789, file, "Screenshot del resultado esperado")
   → Agregar comentario: "Primera ejecución. Funcionalidad verificada correctamente."
```

---

## 10. RESUMEN DE REGLAS DE ORO

1. **SIEMPRE consulta el detalle del caso ANTES de actuar.**
2. **NO subas evidencia duplicada** si el caso ya está cerrado, tiene evidencia, y tu resultado coincide.
3. **SIEMPRE deja un comentario** de validación cruzada (tanto si subes como si no subes evidencia).
4. **SI hay discrepancia, SIEMPRE sube evidencia** y documenta claramente la diferencia.
5. **Respeta el naming convention** de los archivos de evidencia.
6. **No cambies el estado** de un caso cerrado por otro tester salvo que encuentres una discrepancia real.
7. **Si un caso está bloqueado**, verifica si la dependencia ya se resolvió antes de re-ejecutar.
