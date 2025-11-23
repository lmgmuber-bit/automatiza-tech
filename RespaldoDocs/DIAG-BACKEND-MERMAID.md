# 🔧 Diagrama Visual - Administrador Backend (Mermaid)

**Sistema AutomatizaTech - Panel Admin**  
**Versión:** 2.0

---

## 📊 Panel de Administración

```mermaid
graph TB
    A[🎛️ /wp-admin] --> B[✨ Custom]
    A --> C[📄 WordPress]
    
    B --> B1[👥 Contactos]
    B --> B2[💳 Facturación]
    B --> B3[📊 Clientes]
    B --> B4[📄 Facturas]
    
    C --> C1[📊 Dashboard]
    C --> C2[📄 Páginas]
    C --> C3[🎨 Apariencia]
    C --> C4[⚙️ Ajustes]
    
    style A fill:#4CAF50,color:#fff
    style B fill:#2196F3,color:#fff
    style C fill:#9E9E9E,color:#fff
```

---

## 🔄 Flujo de Trabajo Admin

```mermaid
flowchart TD
    Start([👨‍💼 Admin Login]) --> Dash[📊 Dashboard]
    
    Dash --> Tasks{Tareas}
    
    Tasks -->|Diarias| Daily[📅 Diarias]
    Tasks -->|Puntuales| Once[🔧 Puntuales]
    
    Daily --> D1[👥 Revisar contactos]
    Daily --> D2[📧 Ver emails]
    Daily --> D3[📄 Facturas hoy]
    
    Once --> O1[⚙️ Configurar datos]
    Once --> O2[💼 Gestionar servicios]
    Once --> O3[📊 Estadísticas]
    
    D1 --> Conv[🎉 Convertir]
    Conv --> End([✅ Fin])
    D3 --> End
    
    style Start fill:#4CAF50,color:#fff
    style Conv fill:#FF9800,color:#fff
    style End fill:#4CAF50,color:#fff
```

---

## 👥 Gestión de Contactos

```mermaid
flowchart TD
    Menu[📋 Menú Contactos] --> List[📊 Lista]
    
    List --> View[👁️ Ver Detalles]
    List --> Actions[⚡ Acciones]
    
    View --> V1[👤 Nombre]
    View --> V2[📧 Email]
    View --> V3[📱 Teléfono]
    View --> V4[💬 Mensaje]
    
    Actions --> A1[🎉 Convertir]
    Actions --> A2[✏️ Editar]
    Actions --> A3[🗑️ Eliminar]
    
    A1 --> Next[Ver siguiente →]
    
    style Menu fill:#2196F3,color:#fff
    style A1 fill:#FF9800,color:#fff
```

---

## 💳 Conversión: Contacto → Cliente

```mermaid
sequenceDiagram
    actor A as 👨‍💼 Admin
    participant P as 🖥️ Panel
    participant S as ⚙️ Sistema
    participant DB as 💾 BD
    participant PDF as 📄 PDF Gen
    participant E as 📧 Email
    
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
    E->>S: ✅ Enviado
    S->>E: Email admin
    E->>S: ✅ Enviado
    
    S->>DB: UPDATE contacto
    DB->>S: Eliminado
    
    S->>P: Success response
    P->>A: ✅ Conversión exitosa!
```

---

## ⚙️ Configurar Facturación

```mermaid
flowchart TD
    Menu[💳 Datos Facturación] --> Form[📝 Formulario]
    
    Form --> F1[🏢 Empresa]
    Form --> F2[📋 RUT]
    Form --> F3[💼 Giro]
    Form --> F4[📍 Dirección]
    Form --> F5[📧 Email]
    Form --> F6[📞 Teléfono]
    Form --> F7[🌐 Web]
    
    F7 --> Preview[👁️ Vista Previa]
    Preview --> Save[💾 Guardar]
    
    Save --> Success[✅ Guardado]
    
    style Menu fill:#2196F3,color:#fff
    style Success fill:#4CAF50,color:#fff
```

---

## 💼 Gestionar Servicios (BD)

```mermaid
graph TB
    DB[(💾 wp_automatiza_services)] --> Table[📊 Tabla]
    
    Table --> R1[1 | Plan Básico | 150K | 180]
    Table --> R2[2 | Plan Pro | 350K | 400]
    Table --> R3[3 | Plan Empresa | 650K | 750]
    Table --> R4[4 | Plan Premium | 950K | 1100]
    
    R1 --> Actions{Acciones}
    Actions -->|Agregar| Insert[INSERT]
    Actions -->|Actualizar| Update[UPDATE]
    Actions -->|Convertir| Calc[CLP/950=USD]
    
    style DB fill:#4CAF50,color:#fff
    style Insert fill:#2196F3,color:#fff
    style Update fill:#FF9800,color:#fff
```

---

## 📊 Estadísticas y Monitoreo

```mermaid
graph LR
    Panel[📊 Panel Stats] --> C[👥 Contactos]
    Panel --> V[💰 Ventas]
    
    C --> C1[Hoy: 5]
    C --> C2[Semana: 23]
    C --> C3[Mes: 87]
    C --> C4[Conv: 39%]
    
    V --> V1[Hoy: 2]
    V --> V2[Semana: 8]
    V --> V3[Mes: 34]
    V --> V4[CLP: $12.5M]
    V --> V5[USD: $15.2K]
    
    style Panel fill:#2196F3,color:#fff
    style C fill:#4CAF50,color:#fff
    style V fill:#FF9800,color:#fff
```

---

## 📧 Sistema Emails Admin

```mermaid
flowchart LR
    Bandeja[📬 automatizatech.bots<br/>@gmail.com] --> E1[📧 Tipo 1]
    Bandeja --> E2[📧 Tipo 2]
    
    E1 --> E1A[Nuevo Contacto]
    E1A --> E1B[• Nombre<br/>• Email<br/>• Teléfono<br/>• Mensaje]
    
    E2 --> E2A[Cliente Contratado]
    E2A --> E2B[• Plan<br/>• Monto<br/>• Factura<br/>• Estado]
    
    E1B --> Action1[👨‍💼 Admin contacta]
    E2B --> Action2[✅ Admin verifica]
    
    style Bandeja fill:#4CAF50,color:#fff
    style E1 fill:#2196F3,color:#fff
    style E2 fill:#FF9800,color:#fff
```

---

## 🔍 Acceso a Facturas

```mermaid
flowchart TD
    Ver[👁️ Ver Facturas] --> Opt{Opción}
    
    Opt -->|FTP| FTP[📂 FTP]
    Opt -->|BD| BD[💾 BD]
    
    FTP --> FTP1[/wp-content/uploads/invoices/]
    FTP1 --> Files[📄 AT-*.pdf]
    Files --> Down[💾 Descargar]
    
    BD --> Table[📊 wp_automatiza_invoices]
    Table --> Query[🔍 SELECT * FROM...]
    Query --> Export[💾 Exportar CSV]
    
    Down --> End[✅ Fin]
    Export --> End
    
    style Ver fill:#2196F3,color:#fff
    style End fill:#4CAF50,color:#fff
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
    Start[🎉 Cliente Convertido] --> V1{✅ Verificaciones}
    
    V1 --> C1[☐ Mensaje éxito]
    V1 --> C2[☐ ID asignado]
    V1 --> C3[☐ PDF generado]
    V1 --> C4[☐ Emails enviados]
    
    C4 --> V2{📧 Emails}
    V2 --> E1[☐ Cliente recibió]
    V2 --> E2[☐ PDF adjunto]
    V2 --> E3[☐ Admin notificado]
    
    E3 --> V3{💾 Archivos}
    V3 --> F1[☐ PDF existe]
    V3 --> F2[☐ Tamaño OK]
    V3 --> F3[☐ Se puede abrir]
    
    F3 --> V4{💾 Base Datos}
    V4 --> D1[☐ Cliente en BD]
    V4 --> D2[☐ Factura en BD]
    V4 --> D3[☐ Datos correctos]
    
    D3 --> End[✅ Verificación<br/>Completa]
    
    style Start fill:#FF9800,color:#fff
    style End fill:#4CAF50,color:#fff
```

---

## 🚨 Troubleshooting

```mermaid
graph TB
    Problem[❌ Problema] --> Type{Tipo}
    
    Type -->|Email no llega| P1[📧 Email]
    Type -->|PDF no genera| P2[📄 PDF]
    Type -->|País incorrecto| P3[🌍 País]
    Type -->|IVA mal| P4[💰 IVA]
    
    P1 --> S1[✅ Revisar logs<br/>✅ Verificar spam<br/>✅ Reenviar manual]
    P2 --> S2[✅ Permisos carpeta<br/>✅ Verificar FPDF<br/>✅ Revisar logs]
    P3 --> S3[✅ Formato teléfono<br/>✅ UPDATE BD<br/>✅ Regenerar]
    P4 --> S4[✅ Verificar país<br/>✅ Revisar cálculo<br/>✅ Contactar dev]
    
    S1 --> Fix[✅ Resuelto]
    S2 --> Fix
    S3 --> Fix
    S4 --> Fix
    
    style Problem fill:#F44336,color:#fff
    style Fix fill:#4CAF50,color:#fff
```

---

## 📞 URLs Importantes

```mermaid
mindmap
  root((🔗 URLs))
    🏠 Panel
      /wp-admin
    👥 Contactos
      /admin.php?page=contactos
    💳 Facturación
      /admin.php?page=automatiza-invoice-settings
    📄 Facturas
      /wp-content/uploads/invoices/
    💾 phpMyAdmin
      /phpmyadmin
    📧 Email
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

**Consultar MANUAL-USUARIO.md para detalles completos**

---

**AutomatizaTech Development Team - Nov 2025**

> 💡 Compatible con: GitHub, VSCode (ext. Mermaid), GitLab, Notion, Obsidian
