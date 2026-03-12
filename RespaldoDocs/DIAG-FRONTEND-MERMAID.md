# 🌐 Diagrama Visual - Usuario Frontend (Mermaid)

**Sistema AutomatizaTech - Documentación Visual**  
**Versión:** 2.0

---

## 📊 Vista General del Sitio

```mermaid
graph TB
    A[🏠 automatizatech.shop] --> B[📖 Inicio]
    A --> C[💼 Servicios]
    A --> D[📞 Contacto]
    A --> E[ℹ️ Nosotros]
    
    C --> C1[📦 Plan Básico $150K]
    C --> C2[⭐ Plan Pro $350K]
    C --> C3[🏢 Plan Empresa $650K]
    C --> C4[💎 Plan Premium $950K]
    
    D --> D1[📝 Formulario]
    D --> D2[📧 Email]
    D --> D3[📱 WhatsApp]
    
    style A fill:#4CAF50,color:#fff
    style C fill:#2196F3,color:#fff
    style D fill:#FF9800,color:#fff
```

---

## 🚀 Flujo Completo: Visita → Factura

```mermaid
flowchart TD
    Start([👤 Usuario]) --> Nav{¿Qué hacer?}
    
    Nav -->|Ver servicios| Serv[💼 Servicios]
    Nav -->|Contactar| Cont[📞 Contacto]
    
    Serv --> Comp[📊 Compara]
    Comp --> Dec{¿Interesa?}
    Dec -->|Sí| Cont
    Dec -->|No| Nav
    
    Cont --> Form[📝 Formulario]
    Form --> Valid{✅ ¿Válido?}
    
    Valid -->|❌| Err[⚠️ Error]
    Err --> Form
    
    Valid -->|✅| Proc[⏳ Procesa]
    Proc --> Conf[✅ Confirmación]
    
    Conf --> Email1[📧 Email Usuario]
    Conf --> Email2[📧 Email Admin]
    
    Email2 --> Admin[👨‍💼 Admin Revisa]
    Admin --> Contact[📞 Contacta]
    Contact --> Neg[💬 Negocia]
    
    Neg --> Dec2{¿Acepta?}
    Dec2 -->|❌| Fin1([Fin])
    Dec2 -->|✅| Conv[🎉 Convierte]
    
    Conv --> PDF[📄 Genera PDF]
    PDF --> Send[📧 Envía Factura]
    Send --> Rec[📬 Usuario Recibe]
    Rec --> Fin2([✅ Completado])
    
    style Start fill:#4CAF50,color:#fff
    style Conf fill:#4CAF50,color:#fff
    style Conv fill:#FF9800,color:#fff
    style Fin2 fill:#4CAF50,color:#fff
```

---

## 📝 Formulario - Secuencia

```mermaid
sequenceDiagram
    actor U as 👤 Usuario
    participant F as 📝 Form
    participant S as 🖥️ Server
    participant DB as 💾 BD
    participant E as 📧 Email
    
    U->>F: Completa campos
    U->>F: Clic Enviar
    F->>F: Valida JS
    F->>S: POST AJAX
    S->>S: Valida Server
    S->>DB: INSERT contacto
    DB->>S: ID: #0025
    S->>E: Email usuario
    E->>U: 📧 Confirmación
    S->>E: Email admin
    E->>S: 📧 Notificación
    S->>F: Success
    F->>U: ✅ Enviado!
```

---

## 🌍 Chile vs Internacional

```mermaid
graph TB
    U[👤 Usuario] --> Sys{🔍 Detecta País}
    
    Sys -->|+56| CL[🇨🇱 Chile]
    Sys -->|Otro| INT[🌎 Internacional]
    
    CL --> CLP[💰 CLP]
    INT --> USD[💵 USD]
    
    CLP --> IVA[📋 IVA 19%]
    USD --> NOIVA[📋 Sin IVA]
    
    IVA --> FCL[📄 Factura CLP<br/>$350.000]
    NOIVA --> FUS[📄 Factura USD<br/>$400.00]
    
    style CL fill:#0033A0,color:#fff
    style INT fill:#4CAF50,color:#fff
```

---

## ⏱️ Línea de Tiempo

```mermaid
gantt
    title Usuario: Contacto a Factura
    dateFormat HH:mm
    section Usuario
    Visita sitio          :a1, 00:00, 5m
    Llena formulario      :a2, 00:05, 3m
    Envía                 :a3, 00:08, 1m
    Espera admin          :crit, a4, 00:09, 1440m
    section Sistema
    Procesa               :b1, 00:09, 10s
    Emails                :b2, 00:09, 5s
    section Admin
    Revisa                :c1, 24:09, 10m
    Contacta              :c2, 24:19, 30m
    Negocia               :c3, 24:49, 60m
    Convierte             :c4, 25:49, 2m
    section Factura
    Genera PDF            :d1, 25:51, 3s
    Envía email           :crit, d2, 25:51, 2s
    Usuario recibe        :milestone, 25:51, 0m
```

---

## 📊 Estados del Contacto

```mermaid
stateDiagram-v2
    [*] --> Nuevo
    Nuevo --> Pendiente
    Pendiente --> EnRevision
    EnRevision --> Contactado
    Contactado --> EnNegociacion
    EnNegociacion --> Aceptado
    EnNegociacion --> Rechazado
    Aceptado --> Convertido
    Convertido --> FacturaEnviada
    FacturaEnviada --> Completado
    Rechazado --> [*]
    Completado --> [*]
```

---

## 🔐 Validación Online

```mermaid
flowchart TD
    A[👤 Usuario con PDF] --> B[🌐 /validar-factura]
    B --> C[⌨️ Ingresa Número]
    C --> D[🖱️ Validar]
    D --> E{🔍 Busca}
    E -->|❌| F[⚠️ No existe]
    E -->|✅| G[✅ Encontrada]
    G --> H[📊 Muestra datos]
    H --> I[💾 Descargar PDF]
    
    style B fill:#2196F3,color:#fff
    style G fill:#4CAF50,color:#fff
    style F fill:#F44336,color:#fff
```

---

## 📱 Canales Contacto

```mermaid
mindmap
  root((📞 CONTACTO))
    📝 Formulario
      24/7
      Ticket auto
    📧 Email
      24-48h
      Archivos
    📱 WhatsApp
      Horario
      Chat real
    🌐 Redes
      LinkedIn
      Instagram
```

---

**Consultar MANUAL-USUARIO.md para detalles**

---

**AutomatizaTech Development Team - Nov 2025**
