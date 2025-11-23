# 🔧 Diagrama Visual - Administrador Backend (Mermaid Preview Plugin)

**Sistema AutomatizaTech - Panel Admin**  
**Versión:** 2.0 - Font Awesome Icons

---

## 📊 Panel de Administración

```mermaid
graph TB
    A("fa:fa-sliders /wp-admin") --> B("fa:fa-star Custom")
    A --> C("fa:fa-wordpress WordPress")
    
    B --> B1("fa:fa-users Contactos")
    B --> B2("fa:fa-credit-card Facturación")
    B --> B3("fa:fa-chart-bar Clientes")
    B --> B4("fa:fa-file-invoice Facturas")
    
    C --> C1("fa:fa-tachometer-alt Dashboard")
    C --> C2("fa:fa-file Páginas")
    C --> C3("fa:fa-palette Apariencia")
    C --> C4("fa:fa-cog Ajustes")
    
    style A color:#FFFFFF, fill:#4CAF50, stroke:#4CAF50
    style B color:#FFFFFF, fill:#2196F3, stroke:#2196F3
    style C color:#FFFFFF, fill:#9E9E9E, stroke:#9E9E9E
```

---

## 🔄 Flujo de Trabajo Admin

```mermaid
flowchart TD
    Start(["fa:fa-user-tie Admin Login"]) --> Dash("fa:fa-tachometer-alt Dashboard")
    
    Dash --> Tasks{"fa:fa-tasks Tareas"}
    
    Tasks -->|Diarias| Daily("fa:fa-calendar Diarias")
    Tasks -->|Puntuales| Once("fa:fa-wrench Puntuales")
    
    Daily --> D1("fa:fa-users Revisar contactos")
    Daily --> D2("fa:fa-envelope Ver emails")
    Daily --> D3("fa:fa-file-invoice Facturas hoy")
    
    Once --> O1("fa:fa-cog Configurar datos")
    Once --> O2("fa:fa-briefcase Gestionar servicios")
    Once --> O3("fa:fa-chart-line Estadísticas")
    
    D1 --> Conv("fa:fa-star Convertir")
    Conv --> End(["fa:fa-check Fin"])
    D3 --> End
    
    style Start color:#FFFFFF, fill:#4CAF50, stroke:#4CAF50
    style Conv color:#FFFFFF, fill:#FF9800, stroke:#FF9800
    style End color:#FFFFFF, fill:#4CAF50, stroke:#4CAF50
```

---

## 👥 Gestión de Contactos

```mermaid
flowchart TD
    Menu("fa:fa-list Menú Contactos") --> List("fa:fa-table Lista")
    
    List --> View("fa:fa-eye Ver Detalles")
    List --> Actions("fa:fa-bolt Acciones")
    
    View --> V1("fa:fa-user Nombre")
    View --> V2("fa:fa-envelope Email")
    View --> V3("fa:fa-mobile Teléfono")
    View --> V4("fa:fa-comment Mensaje")
    
    Actions --> A1("fa:fa-star Convertir")
    Actions --> A2("fa:fa-edit Editar")
    Actions --> A3("fa:fa-trash Eliminar")
    
    A1 --> Next("fa:fa-arrow-right Ver siguiente")
    
    style Menu color:#FFFFFF, fill:#2196F3, stroke:#2196F3
    style A1 color:#FFFFFF, fill:#FF9800, stroke:#FF9800
```

---

## 💳 Conversión: Contacto → Cliente

```mermaid
sequenceDiagram
    actor A as fa:fa-user-tie Admin
    participant P as fa:fa-desktop Panel
    participant S as fa:fa-cog Sistema
    participant DB as fa:fa-database BD
    participant PDF as fa:fa-file-pdf PDF Gen
    participant E as fa:fa-envelope Email
    
    A->>P: Clic Convertir
    P->>A: Modal selección plan
    A->>P: Selecciona Plan Pro
    A->>P: Confirmar
    
    P->>S: POST conversion
    S->>S: Detecta país (+56)
    S->>DB: INSERT cliente
    DB->>S: ID: #0001
    
    S->>PDF: Genera factura
    PDF->>PDF: Calcula IVA 19%
    PDF->>S: AT-20251116-0001.pdf
    
    S->>E: Email cliente
    E->>S: Enviado
    S->>E: Email admin
    E->>S: Enviado
    
    S->>DB: UPDATE contacto
    DB->>S: Eliminado
    
    S->>P: Success response
    P->>A: Conversión exitosa!
```

---

## ⚙️ Configurar Facturación

```mermaid
flowchart TD
    Menu("fa:fa-credit-card Datos Facturación") --> Form("fa:fa-edit Formulario")
    
    Form --> F1("fa:fa-building Empresa")
    Form --> F2("fa:fa-id-card RUT")
    Form --> F3("fa:fa-briefcase Giro")
    Form --> F4("fa:fa-map-marker Dirección")
    Form --> F5("fa:fa-envelope Email")
    Form --> F6("fa:fa-phone Teléfono")
    Form --> F7("fa:fa-globe Web")
    
    F7 --> Preview("fa:fa-eye Vista Previa")
    Preview --> Save("fa:fa-save Guardar")
    
    Save --> Success("fa:fa-check Guardado")
    
    style Menu color:#FFFFFF, fill:#2196F3, stroke:#2196F3
    style Success color:#FFFFFF, fill:#4CAF50, stroke:#4CAF50
```

---

## 💼 Gestionar Servicios (BD)

```mermaid
graph TB
    DB[("fa:fa-database wp_automatiza_services")] --> Table("fa:fa-table Tabla")
    
    Table --> R1["fa:fa-box 1 - Plan Básico - 150K - 180"]
    Table --> R2["fa:fa-box-open 2 - Plan Pro - 350K - 400"]
    Table --> R3["fa:fa-cubes 3 - Plan Empresa - 650K - 750"]
    Table --> R4["fa:fa-crown 4 - Plan Premium - 950K - 1100"]
    
    R1 --> Actions{"fa:fa-tasks Acciones"}
    Actions -->|Agregar| Insert("fa:fa-plus INSERT")
    Actions -->|Actualizar| Update("fa:fa-sync UPDATE")
    Actions -->|Convertir| Calc("fa:fa-calculator CLP/950=USD")
    
    style DB color:#FFFFFF, fill:#4CAF50, stroke:#4CAF50
    style Insert color:#FFFFFF, fill:#2196F3, stroke:#2196F3
    style Update color:#FFFFFF, fill:#FF9800, stroke:#FF9800
```

---

## 📊 Estadísticas y Monitoreo

```mermaid
graph LR
    Panel("fa:fa-chart-line Panel Stats") --> C("fa:fa-users Contactos")
    Panel --> V("fa:fa-money-bill Ventas")
    
    C --> C1["fa:fa-clock Hoy: 5"]
    C --> C2["fa:fa-calendar-week Semana: 23"]
    C --> C3["fa:fa-calendar Mes: 87"]
    C --> C4["fa:fa-percentage Conv: 39%"]
    
    V --> V1["fa:fa-calendar-day Hoy: 2"]
    V --> V2["fa:fa-calendar-week Semana: 8"]
    V --> V3["fa:fa-calendar-alt Mes: 34"]
    V --> V4["fa:fa-money-bill CLP: $12.5M"]
    V --> V5["fa:fa-dollar-sign USD: $15.2K"]
    
    style Panel color:#FFFFFF, fill:#2196F3, stroke:#2196F3
    style C color:#FFFFFF, fill:#4CAF50, stroke:#4CAF50
    style V color:#FFFFFF, fill:#FF9800, stroke:#FF9800
```

---

## 📧 Sistema Emails Admin

```mermaid
flowchart LR
    Bandeja("fa:fa-inbox automatizatech.bots@gmail.com") --> E1("fa:fa-envelope Tipo 1")
    Bandeja --> E2("fa:fa-envelope-open Tipo 2")
    
    E1 --> E1A("fa:fa-user-plus Nuevo Contacto")
    E1A --> E1B("fa:fa-list-ul Nombre, Email, Teléfono, Mensaje")
    
    E2 --> E2A("fa:fa-handshake Cliente Contratado")
    E2A --> E2B("fa:fa-list-ol Plan, Monto, Factura, Estado")
    
    E1B --> Action1("fa:fa-phone Admin contacta")
    E2B --> Action2("fa:fa-check-circle Admin verifica")
    
    style Bandeja color:#FFFFFF, fill:#4CAF50, stroke:#4CAF50
    style E1 color:#FFFFFF, fill:#2196F3, stroke:#2196F3
    style E2 color:#FFFFFF, fill:#FF9800, stroke:#FF9800
```

---

## 🔍 Acceso a Facturas

```mermaid
flowchart TD
    Ver("fa:fa-eye Ver Facturas") --> Opt{"fa:fa-sitemap Opción"}
    
    Opt -->|FTP| FTP("fa:fa-folder-open FTP")
    Opt -->|BD| BD("fa:fa-database BD")
    
    FTP --> FTP1("fa:fa-folder /wp-content/uploads/invoices/")
    FTP1 --> Files("fa:fa-file-pdf AT-*.pdf")
    Files --> Down("fa:fa-download Descargar")
    
    BD --> Table("fa:fa-table wp_automatiza_invoices")
    Table --> Query("fa:fa-search SELECT * FROM...")
    Query --> Export("fa:fa-file-export Exportar CSV")
    
    Down --> End("fa:fa-check Fin")
    Export --> End
    
    style Ver color:#FFFFFF, fill:#2196F3, stroke:#2196F3
    style End color:#FFFFFF, fill:#4CAF50, stroke:#4CAF50
```

---

## 🛠️ Calendario Tareas

```mermaid
gantt
    title Tareas del Administrador
    dateFormat YYYY-MM-DD
    section Diarias
    Revisar contactos       :d1, 2025-11-16, 1d
    Verificar emails        :d2, 2025-11-16, 1d
    Revisar facturas        :d3, 2025-11-16, 1d
    section Semanales
    Estadísticas            :w1, 2025-11-18, 7d
    Actualizar precios      :w2, 2025-11-18, 7d
    Revisar logs            :w3, 2025-11-18, 7d
    section Mensuales
    Backup facturas         :m1, 2025-12-01, 30d
    Reporte ventas          :m2, 2025-12-01, 30d
    Auditoría BD            :m3, 2025-12-01, 30d
```

---

## ✅ Checklist Post-Conversión

```mermaid
flowchart TD
    Start("fa:fa-star Cliente Convertido") --> V1{"fa:fa-check-circle Verificaciones"}
    
    V1 --> C1("fa:fa-comment Mensaje éxito")
    V1 --> C2("fa:fa-hashtag ID asignado")
    V1 --> C3("fa:fa-file-pdf PDF generado")
    V1 --> C4("fa:fa-envelope Emails enviados")
    
    C4 --> V2{"fa:fa-inbox Emails"}
    V2 --> E1("fa:fa-user Cliente recibió")
    V2 --> E2("fa:fa-paperclip PDF adjunto")
    V2 --> E3("fa:fa-bell Admin notificado")
    
    E3 --> V3{"fa:fa-folder Archivos"}
    V3 --> F1("fa:fa-file-check PDF existe")
    V3 --> F2("fa:fa-weight Tamaño OK")
    V3 --> F3("fa:fa-folder-open Se puede abrir")
    
    F3 --> V4{"fa:fa-database Base Datos"}
    V4 --> D1("fa:fa-user-check Cliente en BD")
    V4 --> D2("fa:fa-file-invoice Factura en BD")
    V4 --> D3("fa:fa-check-double Datos correctos")
    
    D3 --> End("fa:fa-check-circle Verificación Completa")
    
    style Start color:#FFFFFF, fill:#FF9800, stroke:#FF9800
    style End color:#FFFFFF, fill:#4CAF50, stroke:#4CAF50
```

---

## 🚨 Troubleshooting

```mermaid
graph TB
    Problem("fa:fa-exclamation-triangle Problema") --> Type{"fa:fa-question Tipo"}
    
    Type -->|Email no llega| P1("fa:fa-envelope Email")
    Type -->|PDF no genera| P2("fa:fa-file-pdf PDF")
    Type -->|País incorrecto| P3("fa:fa-globe País")
    Type -->|IVA mal| P4("fa:fa-percentage IVA")
    
    P1 --> S1("fa:fa-list Revisar logs - Verificar spam - Reenviar manual")
    P2 --> S2("fa:fa-list Permisos carpeta - Verificar FPDF - Revisar logs")
    P3 --> S3("fa:fa-list Formato teléfono - UPDATE BD - Regenerar")
    P4 --> S4("fa:fa-list Verificar país - Revisar cálculo - Contactar dev")
    
    S1 --> Fix("fa:fa-check-circle Resuelto")
    S2 --> Fix
    S3 --> Fix
    S4 --> Fix
    
    style Problem color:#FFFFFF, fill:#F44336, stroke:#F44336
    style Fix color:#FFFFFF, fill:#4CAF50, stroke:#4CAF50
```

---

## 📞 URLs Importantes

```mermaid
mindmap
  root(("fa:fa-link URLs"))
    fa:fa-home Panel
      /wp-admin
    fa:fa-users Contactos
      /admin.php?page=contactos
    fa:fa-credit-card Facturación
      /admin.php?page=automatiza-invoice-settings
    fa:fa-file-invoice Facturas
      /wp-content/uploads/invoices/
    fa:fa-database phpMyAdmin
      /phpmyadmin
    fa:fa-envelope Email
      automatizatech.bots@gmail.com
```

---

## 🎯 Flujo de Estados

```mermaid
stateDiagram-v2
    [*] --> NuevoContacto: Usuario envía
    NuevoContacto --> Pendiente: Sistema registra
    Pendiente --> EnRevision: Admin abre
    EnRevision --> Contactado: Admin llama
    Contactado --> Negociando: Conversación
    Negociando --> Aceptado: Usuario acepta
    Negociando --> Rechazado: Usuario rechaza
    Aceptado --> ClienteCreado: Admin convierte
    ClienteCreado --> FacturaGenerada: Sistema genera
    FacturaGenerada --> EmailEnviado: Emails enviados
    EmailEnviado --> Completado: Proceso fin
    Rechazado --> [*]: Archivado
    Completado --> [*]: Finalizado
```

---

## 🔄 Ejemplo Complejo con Decoradores

```mermaid
flowchart TD
    %% Nodes con iconos y decoradores especiales
    inicio@{ icon: "fa:rocket", pos: "b", h: 30}
    A("fa:fa-user-tie Admin Login")
    B("fa:fa-cog Sistema")
    success@{ icon: "fa:check-circle", pos: "t", h: 30}
    
    %% Nodos con formas especiales
    proceso1(Revisar Contactos)@{ shape: stadium}
    proceso2(Convertir Cliente)@{ shape: delay}
    
    %% Conexiones
    inicio --> A
    A --> B
    B --> proceso1
    proceso1 --> proceso2
    proceso2 --> success
    
    %% Estilos personalizados
    style A color:#FFFFFF, fill:#2196F3, stroke:#2196F3
    style B color:#FFFFFF, fill:#FF9800, stroke:#FF9800
    style proceso1 color:#FFFFFF, fill:#4CAF50, stroke:#4CAF50
    style proceso2 color:#FFFFFF, fill:#9C27B0, stroke:#9C27B0
```

---

**Consultar MANUAL-USUARIO.md para detalles completos**

---

**AutomatizaTech Development Team - Nov 2025**

> 💡 Compatible con: Mermaid Preview Plugin (VSCode), GitHub, GitLab, Notion
> 📦 Usa Font Awesome Icons en lugar de emojis para mejor renderizado
