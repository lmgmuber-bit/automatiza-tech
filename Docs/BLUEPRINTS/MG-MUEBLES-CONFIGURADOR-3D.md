# MG Muebles — Configurador 3D de muebles de melamina a medida — Blueprint

> Generado por The Architect (Claude) el 2026-09-20 para AutomatizaTech.
> Arquetipo: app web pública sin cuentas de cliente final + panel interno + sitio de marca.
> Cliente: Julio Chirinos, MG Muebles (Chile). Propuesta AT id 14 (`sACmNINLFMbU`).
> Este documento es autocontenido: un agente de desarrollo debe poder construir el producto completo leyendo solo esto.

---

## 0. Cómo usar este blueprint

- **Quién construye:** AutomatizaTech (Luis + Claude Code). Julio Chirinos es **PM y verificador**: revisa cada fase en la URL de pruebas, entrega observaciones y puede proponer criterios de programación, que se registran en `CONTRIBUTING.md` del repositorio. No desarrolla.
- **Dónde corre:** VPS propio de MG Muebles, armado por AT, con Docker y Easypanel. Nada vive en la plataforma de AT ni en Hostinger compartido.
- **Idioma:** todo lo visible para clientes y para Julio en español de Chile. Identificadores de código en inglés, textos y comentarios en español.
- **Orden:** seguir la sección 9 en el orden numerado. Cada fase termina con una entrega revisable por Julio.
- **Lo que NO se hace en la v1:** pagos en línea, cuentas para clientes finales, foto del ambiente real o realidad aumentada (queda diseñada para la fase 6), varias familias de muebles a la vez, app nativa.

---

## 1. Visión del producto

### Qué es
Un sitio web para MG Muebles cuyo corazón es un **configurador 3D**: el cliente final define el espacio que tiene (ancho, alto y profundidad), agrega módulos de melamina desde un catálogo, los acomoda uno junto a otro como un puzzle, toca cada módulo para ajustar medidas, interior (repisas, cajones, barra), frente (puertas o sin puertas), tipo de tirador y color de melamina, ve el precio estimado moverse en vivo, guarda el diseño con un enlace compartible y **cotiza por WhatsApp** con el código del diseño. Julio recibe el aviso, revisa el diseño en su panel, agenda la visita para medir y lleva la cotización por estados hasta la entrega y la postventa.

Alrededor del configurador va el sitio de marca: landing, galería de trabajos realizados, valoraciones de clientes, contacto, chatbot con IA y botón flotante de WhatsApp.

### Referencia de interacción
Planificador IKEA BESTÅ (`https://www.ikea.com/addon-app/storageone/besta/web/latest/cl/es/#/planner`): habitación 3D con pared, hoja inferior con categorías y luego módulos con sus medidas, tocar para agregar, precio corriendo arriba, Guardar y Resumen, alternar puertas abiertas o cerradas, cotas sobre el mueble, deshacer y rehacer. En celular: 3D arriba, panel abajo. **No se copia el diseño visual de IKEA, solo la mecánica.**

### Objetivos
1. Que un cliente sin experiencia arme y cotice un closet en menos de 5 minutos desde el celular.
2. Que cada cotización llegue a Julio con diseño, medidas, precio estimado y datos de contacto, sin transcribir nada.
3. Que Julio administre módulos, acabados y precios sin tocar código.
4. Que el sitio venda solo: galería, opiniones, chatbot y WhatsApp desde todas las pantallas.

### Métricas de éxito
- Cotizaciones por semana creadas desde el configurador (meta inicial: 5).
- Porcentaje de diseños guardados que terminan en cotización (meta: 30 %).
- Tiempo de carga del configurador en un celular Android medio: menos de 4 s hasta poder interactuar.
- Cero cotizaciones con precio distinto entre navegador y servidor (el servidor recalcula siempre).

---

## 2. Stack tecnológico

| Capa | Tecnología | Por qué |
|---|---|---|
| Framework | Next.js 15, App Router, `output: 'standalone'` | Un solo proceso Node para páginas, API y admin; es el stack hablado con Julio; corre en Docker |
| Lenguaje | TypeScript en modo estricto, sin `any` | Contratos claros entre dominio, UI y servidor; Julio puede leer los tipos |
| Estilos | Tailwind CSS v4 + shadcn/ui | Rápido, consistente, mobile-first; componentes de admin (tablas, formularios, diálogos) listos |
| 3D | three + @react-three/fiber + @react-three/drei | Control real de escena, cámara y materiales; se compone como componentes React |
| Estado del configurador | zustand con `temporal` (zundo) para deshacer y rehacer | Ligero, sin boilerplate, historial integrado |
| Validación | zod | Un solo esquema para formularios, API y dominio |
| Base de datos | PostgreSQL 16 | Datos relacionales (módulos, acabados, cotizaciones); JSONB para los diseños |
| ORM | Prisma | Migraciones versionadas, tipos generados, seed |
| Autenticación admin | Auth.js v5 (next-auth) con proveedor Credentials y bcrypt | Un puñado de usuarios internos; sin dependencia externa |
| Imágenes | Sharp en servidor; almacenamiento en volumen Docker `/data/uploads` | Capturas del 3D y fotos de la galería sin servicios externos |
| Correo | Brevo (SMTP) | Ya usado por AT; confirmaciones y avisos |
| WhatsApp | Fase 1: enlace `wa.me` con texto prellenado. Fase 6: WhatsApp Cloud API vía n8n | Sin verificación de Meta para arrancar |
| Automatizaciones y chatbot | n8n (instancia propia de MG en el mismo VPS o la de AT durante el desarrollo) | Aviso de cotización nueva, chatbot con Claude leyendo catálogo y precios |
| Pruebas | Vitest (dominio y utilidades), Playwright (flujos), Testing Library | Precio y geometría con pruebas exactas |
| Infra | VPS Ubuntu 24.04, Docker Compose, Easypanel, Caddy o Traefik del panel para HTTPS | AT ya opera Easypanel; despliegue desde GitHub con vuelta atrás por imagen |
| Gestor de paquetes | pnpm | Rápido y determinista |

**Descartado a propósito (venía del informe previo):** NestJS aparte, GraphQL, MongoDB, Redis, S3, Kubernetes. Ninguno aporta con un servidor y un equipo de una persona más un agente; todos agregan mantenimiento.

---

## 3. Estructura del repositorio

Repositorio privado en GitHub: `lmgmuber-bit/at-mg-muebles`. Rama `main` = producción, `develop` = pruebas.

```
at-mg-muebles/
  CLAUDE.md                      # instrucciones para el agente (sección 15)
  CONTRIBUTING.md                # criterios de programación; aquí entran los de Julio
  README.md                      # cómo levantar en local y desplegar
  .env.example                   # todas las variables, sin valores
  docker/
    Dockerfile                   # multi-stage, node:22-alpine, standalone
    compose.yml                  # web + postgres (+ n8n opcional) para el VPS
    compose.dev.yml              # solo postgres para desarrollo local
    backup.sh                    # pg_dump + tar de uploads, con retención
  prisma/
    schema.prisma
    migrations/
    seed.ts                      # familias, tipos de módulo, acabados y reglas de referencia
  public/
    texturas/                    # JPG 1024x1024 de cada melamina, nombradas por slug
    modelos/                     # GLB pequeños: tirador, gola, pata (< 200 KB cada uno)
    marca/                       # logo, favicon, imagen OG
  src/
    app/
      (sitio)/                   # rutas públicas con layout de marca
        layout.tsx
        page.tsx                 # landing
        disenar/page.tsx         # configurador (client)
        d/[codigo]/page.tsx      # diseño compartido (vista de solo lectura + cotizar)
        trabajos/page.tsx        # galería
        trabajos/[slug]/page.tsx
        opiniones/page.tsx
        contacto/page.tsx
        privacidad/page.tsx
      admin/                     # protegido por middleware
        layout.tsx
        login/page.tsx
        page.tsx                 # tablero: cotizaciones nuevas, diseños de la semana
        cotizaciones/...
        modulos/...
        acabados/...
        precios/...
        trabajos/...
        opiniones/...
        ajustes/page.tsx
      api/
        catalogo/route.ts        # GET catálogo activo + reglas vigentes
        disenos/route.ts         # POST guardar
        disenos/[codigo]/route.ts# GET
        disenos/[codigo]/captura/route.ts # POST imagen
        cotizaciones/route.ts    # POST
        webhooks/n8n/route.ts    # POST firmado
        health/route.ts
      sitemap.ts, robots.ts, opengraph-image.tsx
    domain/                      # TypeScript puro. PROHIBIDO importar React, Prisma o Next aquí
      tipos.ts                   # Espacio, ModuloParams, ModuloColocado, Diseno, Acabado, ReglasPrecio
      esquemas.ts                # zod de todo lo anterior
      geometria/
        paneles.ts               # de ModuloParams a lista de paneles (posición, tamaño, cantos visibles)
        colocacion.ts            # pegado entre módulos, colisiones, límites del espacio
      precios/
        calcular.ts              # calcularModulo, calcularDiseno
        desglose.ts              # líneas del resumen legibles
      codigos.ts                 # generador MG-XXXX sin caracteres ambiguos
      whatsapp.ts                # arma el texto del mensaje de cotización
    components/
      configurador/
        Configurador.tsx         # orquesta escena + paneles + barra
        Escena.tsx               # Canvas R3F, luces, cámara, controles
        Habitacion.tsx           # piso, pared, cotas del espacio
        Modulo3D.tsx             # renderiza los paneles de un módulo con texturas
        Puerta3D.tsx, Cajon3D.tsx, Tirador3D.tsx
        Cotas.tsx                # líneas y medidas
        HojaInferior.tsx         # panel móvil con pestañas
        PanelModulos.tsx, PanelMedidas.tsx, PanelInterior.tsx, PanelAcabado.tsx
        BarraPrecio.tsx          # precio + Cotizar por WhatsApp + Guardar
        ResumenPedido.tsx
        FallbackSinWebGL.tsx     # vista 2D SVG de elevación
      sitio/                     # header, footer, hero, galería, opiniones, CTA WhatsApp, chatbot
      admin/                     # tablas, formularios, editor de reglas
      ui/                        # shadcn
    lib/
      db.ts                      # cliente Prisma singleton
      auth.ts                    # Auth.js config
      uploads.ts                 # guardar imagen, redimensionar, ruta pública
      n8n.ts                     # enviar evento firmado
      formato.ts                 # CLP, mm a cm, fechas es-CL
    stores/
      configurador.ts            # zustand + zundo
    styles/globals.css
    middleware.ts                # protege /admin
  tests/
    domain/                      # vitest: precios, paneles, colocación, códigos
    e2e/                         # playwright: armar y cotizar, admin
  .github/workflows/ci.yml       # lint, typecheck, test, build de imagen
```

---

## 4. Modelo de datos

### Unidades y convenciones
- Todas las medidas en **milímetros enteros**. Se muestran en cm con un decimal.
- Precios en **CLP enteros**, IVA incluido, redondeados a la centena.
- Posición de un módulo: `x` (desde la pared izquierda) e `y` (desde el piso), en mm. La profundidad la fija el módulo.

### Tipos del dominio (`src/domain/tipos.ts`)

```ts
export type Espacio = { ancho: number; alto: number; prof: number }; // mm

export type FilaInterior =
  | { tipo: 'repisa' }
  | { tipo: 'cajon'; alto: number }          // alto del frente del cajón
  | { tipo: 'barra' }                        // barra de colgar; ocupa el hueco hasta la siguiente fila
  | { tipo: 'vacio' };

export type Frente = 'sin_puertas' | 'puerta_1' | 'puertas_2' | 'correderas_2';
export type Tirador = 'tirador' | 'gola' | 'unero';

export type ModuloParams = {
  ancho: number; alto: number; prof: number;        // mm
  interior: FilaInterior[];                          // de abajo hacia arriba
  frente: Frente;
  tirador: Tirador;
  acabadoSlug: string;                               // exterior y frentes
  acabadoInteriorSlug?: string;                      // por defecto igual al exterior
  zocalo: number;                                    // mm, 0 o 60..120
  fondo: 'fibra_3mm' | 'tablero_18mm';
};

export type ModuloColocado = { id: string; tipoSlug: string; x: number; y: number; params: ModuloParams };

export type Diseno = { espacio: Espacio; modulos: ModuloColocado[] };

export type Panel = {
  nombre: string;                                    // 'lateral_izq', 'tapa', 'repisa_2', 'frente_cajon_1'...
  ancho: number; alto: number; espesor: number;      // mm, en su propio plano
  posicion: [number, number, number];                // centro, mm, relativo al módulo
  rotacion: [number, number, number];
  cantosVisibles: number;                            // metros lineales de tapacanto
  acabadoSlug: string;
  movil?: { tipo: 'puerta' | 'cajon'; eje: 'y' | 'z'; pivote: [number, number, number] };
};
```

### Reglas de precio (`ReglasPrecio.reglas`, JSON versionado)

```json
{
  "espesorTablero": 18,
  "factorDesperdicio": 1.15,
  "manoObra": { "baseModulo": 15000, "porM2Tablero": 12000, "porCajon": 8000, "porPuerta": 5000 },
  "herrajes": { "bisagra": 1800, "correderaPar": 9000, "barraMetro": 6000, "tirador": 2500, "golaMetro": 7000, "patasSet": 4000, "rielCorrederaMetro": 9000 },
  "bisagrasPorPuerta": [ { "hastaAlto": 900, "cantidad": 2 }, { "hastaAlto": 1600, "cantidad": 3 }, { "hastaAlto": 2600, "cantidad": 4 } ],
  "margen": 0.35,
  "descuentoWeb": 0.05,
  "redondeo": 100,
  "textoAviso": "Precio estimado. Se confirma después de la visita a medir."
}
```

> **Todos los números de arriba son de referencia** para que el sistema funcione desde el día uno. Julio los reemplaza desde el admin antes de publicar. El precio por m² de tablero y de tapacanto van en cada acabado, no aquí.

### Esquema Prisma

```prisma
generator client { provider = "prisma-client-js" }
datasource db { provider = "postgresql"; url = env("DATABASE_URL") }

model Familia {
  id          String   @id @default(cuid())
  slug        String   @unique          // closet, comoda, rack_tv, cocina, escritorio
  nombre      String
  descripcion String?
  orden       Int      @default(0)
  activa      Boolean  @default(true)
  tipos       ModuloTipo[]
  trabajos    Trabajo[]
}

model ModuloTipo {
  id                String   @id @default(cuid())
  familia           Familia  @relation(fields: [familiaId], references: [id])
  familiaId         String
  slug              String   @unique          // closet_colgador, closet_repisero, closet_cajonera, closet_zapatero
  nombre            String
  descripcion       String?
  miniaturaUrl      String?
  anchoMin          Int      // mm
  anchoMax          Int
  anchoPaso         Int      @default(10)
  altoMin           Int
  altoMax           Int
  altoPaso          Int      @default(10)
  profMin           Int
  profMax           Int
  profPaso          Int      @default(10)
  interiorPermitido Json     // { tipos: ['repisa','cajon','barra'], maxFilas: 8, maxCajones: 6 }
  frentesPermitidos Json     // ['sin_puertas','puerta_1','puertas_2']
  valoresPorDefecto Json     // ModuloParams completo
  orden             Int      @default(0)
  activo            Boolean  @default(true)
}

model Acabado {
  id                String  @id @default(cuid())
  slug              String  @unique          // blanco, roble_oscuro, grafito, haya, nogal
  nombre            String
  proveedor         String?                  // Arauco, Masisa...
  codigoProveedor   String?
  texturaUrl        String                   // /texturas/<slug>.jpg
  colorHex          String                   // fallback y swatch
  precioM2          Int                      // CLP por m² de tablero 18 mm
  precioTapacantoM  Int                      // CLP por metro lineal
  orden             Int     @default(0)
  activo            Boolean @default(true)
}

model ReglasPrecio {
  id           String   @id @default(cuid())
  version      Int      @unique
  vigenteDesde DateTime @default(now())
  reglas       Json
  notas        String?
  activa       Boolean  @default(false)      // solo una activa a la vez
}

model Diseno {
  id            String   @id @default(cuid())
  codigo        String   @unique              // MG-7K3D
  espacio       Json                          // Espacio
  modulos       Json                          // ModuloColocado[]
  reglasVersion Int
  subtotal      Int
  total         Int
  desglose      Json                          // líneas del resumen al momento de guardar
  capturaUrl    String?
  visitas       Int      @default(0)
  createdAt     DateTime @default(now())
  updatedAt     DateTime @updatedAt
  cotizaciones  Cotizacion[]
}

enum EstadoCotizacion { NUEVA CONTACTADO VISITA_AGENDADA COTIZACION_FINAL APROBADA EN_FABRICACION ENTREGADA POSTVENTA PERDIDA }
enum CanalCotizacion { WHATSAPP FORMULARIO }

model Cotizacion {
  id             String  @id @default(cuid())
  codigo         String  @unique              // COT-2026-0001
  diseno         Diseno  @relation(fields: [disenoId], references: [id])
  disenoId       String
  nombre         String
  telefono       String
  email          String?
  comuna         String?
  mensaje        String?
  canal          CanalCotizacion
  estado         EstadoCotizacion @default(NUEVA)
  totalEstimado  Int
  totalFinal     Int?
  fechaVisita    DateTime?
  notasInternas  String?
  eventos        CotizacionEvento[]
  createdAt      DateTime @default(now())
  updatedAt      DateTime @updatedAt
}

model CotizacionEvento {
  id           String     @id @default(cuid())
  cotizacion   Cotizacion @relation(fields: [cotizacionId], references: [id])
  cotizacionId String
  tipo         String                        // cambio_estado, nota, visita, mensaje
  detalle      String
  autor        String                        // email del usuario o 'sistema'
  createdAt    DateTime   @default(now())
}

model Trabajo {
  id          String   @id @default(cuid())
  slug        String   @unique
  titulo      String
  familia     Familia? @relation(fields: [familiaId], references: [id])
  familiaId   String?
  descripcion String?
  portadaUrl  String
  fotos       Json                            // string[]
  publicado   Boolean  @default(false)
  orden       Int      @default(0)
  createdAt   DateTime @default(now())
  valoraciones Valoracion[]
}

model Valoracion {
  id        String   @id @default(cuid())
  trabajo   Trabajo? @relation(fields: [trabajoId], references: [id])
  trabajoId String?
  nombre    String
  estrellas Int                               // 1..5
  texto     String
  aprobada  Boolean  @default(false)
  createdAt DateTime @default(now())
}

enum RolUsuario { ADMIN EDITOR }

model Usuario {
  id           String     @id @default(cuid())
  email        String     @unique
  nombre       String
  passwordHash String
  rol          RolUsuario @default(EDITOR)
  activo       Boolean    @default(true)
  ultimoAcceso DateTime?
}

model Ajuste {
  clave String @id                            // whatsapp_numero, instagram_url, direccion, horario, texto_aviso_precio
  valor String
}
```

### Relaciones
- Familia 1:N ModuloTipo; Familia 1:N Trabajo.
- Diseno 1:N Cotizacion; Cotizacion 1:N CotizacionEvento.
- Trabajo 1:N Valoracion.
- Diseno guarda el JSON de módulos y el `desglose` como **foto del momento**: si Julio cambia precios después, los diseños viejos no cambian.

---

## 5. Motor de precios y geometría (dominio)

### 5.1 De parámetros a paneles (`domain/geometria/paneles.ts`)
Para un módulo de ancho A, alto H, profundidad P, espesor e = 18:
1. **Laterales** (2): H × P. Cantos visibles: frente (H) de cada uno.
2. **Tapa y base** (2): (A − 2e) × P. Canto visible: frente (A − 2e).
3. **Fondo** (1): A × H en fibra 3 mm o tablero 18 mm según `fondo`. Sin cantos.
4. **Zócalo** (si `zocalo > 0`): (A − 2e) × zocalo, retranqueado 40 mm. Canto: frente.
5. **Filas interiores**, de abajo hacia arriba, repartiendo el alto útil (H − 2e − zocalo):
   - `repisa`: panel (A − 2e) × (P − 20), canto frente.
   - `cajon`: caja de cajón (frente A × alto, laterales 2 × (P − 50) × (alto − 30), fondo fibra, trasero) y frente exterior; canto en los cuatro lados del frente. `movil: cajon, eje z`.
   - `barra`: no es panel; suma `barraMetro × (A − 2e)/1000` en herrajes y ocupa el hueco hasta la siguiente fila (mínimo 900 mm si es colgador largo, 500 si es corto).
   - `vacio`: nada.
6. **Frente**:
   - `puerta_1`: un panel (A − 4) × (H − zocalo − 4), pivote en el lateral izquierdo, `movil: puerta, eje y`. Cantos: 4 lados.
   - `puertas_2`: dos paneles de (A/2 − 3) de ancho, pivotes en cada lateral.
   - `correderas_2`: dos paneles superpuestos en dos rieles; suma `rielCorrederaMetro × A/1000 × 2`.
   - `sin_puertas`: nada.
7. **Tiradores**: `tirador` = 1 pieza GLB por puerta y por cajón; `gola` = perfil por el ancho de cada frente (metros × golaMetro) y los frentes se acortan 30 mm; `unero` = fresado, sin costo de herraje ni pieza.

La función es pura: `panelesDeModulo(params: ModuloParams): Panel[]`. La escena 3D y el cálculo de precio consumen la misma lista.

### 5.2 Cálculo del precio (`domain/precios/calcular.ts`)

```
para cada módulo:
  m2Tablero   = Σ paneles de 18 mm (ancho × alto) / 1e6 × factorDesperdicio
  m2Fibra     = Σ paneles de fibra / 1e6 × factorDesperdicio           (precio fibra = 25 % del tablero del acabado)
  tapacantoM  = Σ cantosVisibles
  tablero     = m2Tablero × acabado.precioM2 + m2Fibra × acabado.precioM2 × 0.25
  tapacanto   = tapacantoM × acabado.precioTapacantoM
  herrajes    = bisagras(puertas, alto) × bisagra + cajones × correderaPar + barrasM × barraMetro
              + tiradores × tirador + golaM × golaMetro + (patasSet si y == 0 y zocalo == 0)
  manoObra    = baseModulo + m2Tablero × porM2Tablero + cajones × porCajon + puertas × porPuerta
  costo       = tablero + tapacanto + herrajes + manoObra
  precioModulo = redondear(costo × (1 + margen), redondeo)

diseño:
  subtotal    = Σ precioModulo
  descuento   = redondear(subtotal × descuentoWeb, redondeo)
  total       = subtotal − descuento
```

`calcularDiseno(diseno, catalogo, reglas): Resultado` devuelve total, subtotal, descuento y un `desglose` con una línea por módulo y líneas de detalle (tablero, tapacanto, herrajes, mano de obra) para el resumen. **Se ejecuta igual en el navegador (precio en vivo) y en el servidor al guardar y al cotizar. El valor que se guarda es siempre el del servidor.**

### 5.3 Colocación (`domain/geometria/colocacion.ts`)
- Un módulo nuevo entra pegado a la derecha del último, o en `x = 0` si es el primero.
- Al arrastrar, se ajusta a los bordes de los vecinos (imán de 20 mm) y nunca se superpone.
- Apilar: permitido si el de abajo tiene el mismo ancho o mayor y la suma de altos cabe en el espacio. `y` = alto del de abajo.
- Validaciones (zod + funciones): ancho total ≤ espacio.ancho, alto ≤ espacio.alto, prof ≤ espacio.prof, cada parámetro dentro de los rangos del `ModuloTipo`.

### 5.4 Casos de prueba obligatorios (Vitest)
1. Closet colgador 900 × 2100 × 550, blanco, 1 barra + 1 repisa, 2 puertas, tirador: cantidad de paneles, m² de tablero, metros de tapacanto y precio exacto con las reglas de referencia. Fijar el número esperado en la prueba una vez calculado a mano.
2. Cajonera 600 × 900 × 500 con 4 cajones, gola: 4 pares de correderas, gola en metros, cero tiradores.
3. Cambiar solo el acabado cambia tablero y tapacanto, no herrajes ni mano de obra.
4. Redondeo a 100 y descuento web.
5. Colocación: tres módulos de 800 en un espacio de 2400 caben; el cuarto no.
6. Diseño guardado con `reglasVersion` 1 no cambia su total al activar la versión 2.

---

## 6. API

Todas las rutas devuelven `{ ok: true, data }` o `{ ok: false, error: { codigo, mensaje } }`. Validación con zod en la entrada. Sin autenticación salvo `/admin` y el webhook.

| Método | Ruta | Qué hace | Auth |
|---|---|---|---|
| GET | `/api/catalogo` | Familias, tipos de módulo, acabados activos y reglas vigentes. Cache 5 min, `ETag` | no |
| POST | `/api/disenos` | Valida el diseño, recalcula precio en servidor, genera código, guarda. Devuelve `{ codigo, total, desglose, url }` | no, límite 30/h por IP |
| GET | `/api/disenos/[codigo]` | Diseño completo para `/d/[codigo]` y para el admin | no |
| POST | `/api/disenos/[codigo]/captura` | Recibe PNG (máx. 2 MB) del canvas, lo convierte a JPG 1200 px y lo guarda | no, solo dentro de los 10 min de creado |
| POST | `/api/cotizaciones` | Crea cotización sobre un diseño, guarda contacto, dispara evento a n8n, devuelve código y el enlace `wa.me` armado | no, límite 10/h por IP |
| POST | `/api/webhooks/n8n` | n8n devuelve resultados (por ejemplo, mensaje enviado) | cabecera `X-MG-Firma` HMAC |
| GET | `/api/health` | `{ ok, version, db }` para Easypanel | no |
| — | server actions en `/admin/*` | CRUD de módulos, acabados, reglas, trabajos, opiniones, ajustes; cambio de estado de cotizaciones | sesión admin |

### Detalle de los endpoints críticos

**POST `/api/disenos`**
```ts
// entrada (zod: DisenoEntradaSchema)
{ espacio: { ancho: 2400, alto: 2400, prof: 600 }, modulos: ModuloColocado[] }
// salida
{ ok: true, data: { codigo: 'MG-7K3D', total: 486500, subtotal: 512000, descuento: 25500, desglose: [...], url: 'https://mgmuebles.cl/d/MG-7K3D' } }
// errores: DISENO_INVALIDO (detalle por campo), FUERA_DE_ESPACIO, TIPO_INACTIVO, ACABADO_INACTIVO
```
El servidor **ignora cualquier precio que venga del cliente**; recalcula con las reglas activas y guarda `reglasVersion`.

**POST `/api/cotizaciones`**
```ts
{ codigoDiseno: 'MG-7K3D', nombre: 'María Pérez', telefono: '+56912345678', email?: '', comuna?: 'Maipú', mensaje?: '', canal: 'WHATSAPP' }
// salida
{ ok: true, data: { codigo: 'COT-2026-0007', whatsappUrl: 'https://wa.me/569XXXXXXXX?text=...' } }
```
Texto del mensaje (`domain/whatsapp.ts`): "Hola MG Muebles, quiero cotizar mi diseño MG-7K3D: closet de 2 módulos, 180 × 210 cm, melamina Roble Oscuro, 2 puertas + 3 cajones. Precio estimado $486.500. Ver diseño: https://mgmuebles.cl/d/MG-7K3D". Teléfono validado como chileno (+56 9 y 8 dígitos).

**Evento a n8n** (`lib/n8n.ts`): `POST N8N_WEBHOOK_URL` con `{ evento: 'cotizacion_nueva', cotizacion, diseno, urlAdmin }` firmado con HMAC-SHA256 en `X-MG-Firma`. n8n avisa a Julio por WhatsApp o correo y, en la fase 6, contesta al cliente.

---

## 7. Frontend

### Rutas públicas
| Ruta | Página | Qué ve el cliente |
|---|---|---|
| `/` | Landing | Hero con el configurador como promesa ("Diseña tu closet y cotízalo en 5 minutos"), 3 trabajos destacados, cómo funciona en 4 pasos (diseñas, visitamos a medir, fabricamos, entregamos), opiniones, CTA WhatsApp |
| `/disenar` | Configurador | La app 3D completa (client component, carga diferida del canvas) |
| `/d/[codigo]` | Diseño compartido | Captura grande, resumen de módulos y precio, botón "Cotizar por WhatsApp" y "Editar una copia" |
| `/trabajos`, `/trabajos/[slug]` | Galería | Fotos por familia, con lightbox |
| `/opiniones` | Valoraciones aprobadas | Estrellas, texto, trabajo asociado |
| `/contacto` | Contacto | WhatsApp, dirección, horario, formulario simple |

### El configurador, paso a paso
1. **Pantalla de espacio:** tres campos grandes (ancho, alto, profundidad) con valores típicos precargados (240 × 240 × 60 cm) y una ilustración. Botón "Empezar a diseñar".
2. **Escena:** habitación con piso y pared del tamaño del espacio, cotas del espacio en gris. Cámara en perspectiva frontal ligeramente elevada; orbitar limitado a ±60° horizontal y 0°–40° vertical; zoom limitado. Luces: ambiente + direccional suave + sombras de contacto (drei `ContactShadows`).
3. **Hoja inferior (móvil) o columna izquierda (escritorio)** con pestañas: **Módulos** (miniaturas por tipo con nombre y medida base, tocar para agregar), **Medidas** (sliders con paso de 1 cm y campo numérico), **Interior** (lista editable de filas: agregar repisa, cajón o barra, reordenar), **Frente y tirador**, **Acabado** (swatches con foto real de la melamina).
4. **Seleccionar un módulo:** toque en el 3D lo resalta con borde y muestra un menú flotante: duplicar, eliminar, mover izquierda o derecha, abrir puertas. Las pestañas editan el módulo seleccionado.
5. **Barra inferior fija:** precio total estimado grande, texto "Precio estimado, se confirma en la visita", botón verde "Cotizar por WhatsApp" y botón secundario "Guardar y compartir". Deshacer y rehacer arriba a la derecha; "Puertas abiertas o cerradas" y "Cotas" abajo a la izquierda.
6. **Cotizar:** hoja con nombre, teléfono, comuna, mensaje opcional. Al enviar: se guarda el diseño si no estaba guardado, se sube la captura, se crea la cotización, se abre WhatsApp con el texto. Pantalla de confirmación con el código y "Julio te escribirá para coordinar la visita".
7. **Guardar y compartir:** genera `/d/MG-XXXX`, copia el enlace, botón de compartir nativo del celular.

### Escena 3D: reglas de implementación
- `Canvas` con `frameloop="demand"`, `dpr={[1, 1.5]}`, `gl={{ antialias: true, powerPreference: 'high-performance' }}`. Invalidar solo cuando cambia el estado.
- Un `Modulo3D` renderiza `panelesDeModulo(params)`; cada panel es un `<mesh>` con `BoxGeometry` y `MeshStandardMaterial` con la textura del acabado; `map.repeat` proporcional al tamaño real del panel (la veta no se estira) y `map.wrapS/T = RepeatWrapping`. Cantos visibles con un material liso del `colorHex`.
- Puertas y cajones son grupos con pivote; abrir y cerrar con `@react-spring/three` (200 ms). Respeta `prefers-reduced-motion`: sin animación, cambio directo.
- Tiradores y golas: GLB cargados una vez con `useGLTF` e instanciados.
- Cotas con `drei/Line` y `drei/Html` para las etiquetas; solo del módulo seleccionado y del espacio.
- Texturas: JPG 1024 × 1024, peso máximo 150 KB cada una, precargadas con `useTexture.preload` al abrir el configurador. Sin KTX2 en la v1 salvo que las pruebas en Android lo exijan.
- Captura: `gl.domElement.toBlob('image/png')` con `preserveDrawingBuffer: true` solo en el frame de captura (forzar un render antes).
- Sin WebGL (detección al montar): `FallbackSinWebGL` dibuja la elevación frontal en SVG con los mismos paneles; todo lo demás funciona igual.

### Estado
- `stores/configurador.ts` (zustand + zundo): `espacio`, `modulos`, `seleccionadoId`, `puertasAbiertas`, `mostrarCotas`, acciones puras que llaman al dominio. El precio es un selector derivado (`calcularDiseno`), memoizado.
- Catálogo y reglas: `GET /api/catalogo` una vez, en React Query o `useSWR`, con revalidación al volver al foco.
- Persistencia local: el diseño en curso se guarda en `localStorage` cada cambio para no perderlo si se cierra la pestaña.

### Layout y responsive
- Móvil (< 768 px): canvas ocupa `55dvh`, hoja inferior arrastrable de 35 % a 85 %, barra de precio fija abajo. Medidas con `dvh`, nunca `vh`.
- Escritorio: tres columnas como el wireframe (panel 320 px, escena flexible, resumen 360 px).
- Pantalla completa opcional con la API Fullscreen.
- Objetivo de rendimiento en un Android medio: 30 fps con 6 módulos y puertas abiertas; primer render del configurador en menos de 4 s en 4G.

### Jerarquía de componentes (configurador)
```
Configurador
  ├─ Escena (Canvas)
  │    ├─ Habitacion → Cotas(espacio)
  │    └─ Modulo3D[] → Puerta3D[], Cajon3D[], Tirador3D[], Cotas(seleccionado)
  ├─ HojaInferior | ColumnaIzquierda
  │    ├─ PanelModulos, PanelMedidas, PanelInterior, PanelFrente, PanelAcabado
  ├─ MenuModulo (flotante sobre el seleccionado)
  ├─ BarraPrecio → ResumenPedido (hoja) → FormularioCotizar
  └─ FallbackSinWebGL (condicional)
```

---

## 8. Sistema de diseño (provisional hasta recibir el logo de MG)

| Rol | Hex | Uso |
|---|---|---|
| Primario | `#E8590C` | botones principales, acentos, módulo seleccionado (viene del naranja del wireframe) |
| Primario oscuro | `#C2410C` | hover y estados activos |
| WhatsApp | `#25D366` | solo el botón de cotizar y el flotante |
| Fondo | `#FAFAF9` | páginas |
| Superficie | `#FFFFFF` | tarjetas, hojas, paneles |
| Texto | `#1C1917` | cuerpo |
| Texto secundario | `#78716C` | ayudas, notas de precio |
| Borde | `#E7E5E4` | separadores |
| Éxito | `#16A34A` | confirmaciones |
| Error | `#DC2626` | validaciones |
| Escena | pared `#EDEAE4`, piso madera clara | habitación 3D |

- **Tipografía:** Plus Jakarta Sans para títulos (600 y 700), Inter para cuerpo (400 y 500), tabular en precios. Tamaños: 32/24/20/16/14 px.
- **Espaciado:** base 4 px; radios 12 px en tarjetas y hojas, 999 px en pastillas; sombras suaves solo en hojas flotantes.
- **Estética:** limpia, cálida, con mucha foto de mueble real; nada de degradados oscuros ni "tecnológicos". El 3D es el protagonista.
- Todos los botones y áreas táctiles de al menos 44 px. Contraste AA. Estados de foco visibles.
- Cuando llegue el logo y los colores de MG, se reemplazan los tokens en `globals.css`; nada más cambia.

---

## 9. Orden de construcción

Cada paso deja el repositorio compilando, con pruebas verdes y desplegable. Las fases marcan entregas para Julio.

**FASE 1 — Base y dominio (revisable con pruebas y una página de demo)**

1. **Andamiaje.** `pnpm create next-app@latest at-mg-muebles --ts --tailwind --app --src-dir --import-alias "@/*"`. Agregar shadcn/ui, Prisma, zod, Vitest, Playwright, ESLint estricto, Prettier. Crear `CLAUDE.md` (sección 15), `CONTRIBUTING.md`, `.env.example`, `docker/compose.dev.yml` con PostgreSQL. Commit inicial.
2. **Tipos y esquemas del dominio.** `domain/tipos.ts` y `domain/esquemas.ts` con zod. Pruebas de validación.
3. **Geometría.** `panelesDeModulo` con los casos de la sección 5.4. Snapshot de la lista de paneles para el closet de referencia.
4. **Precios.** `calcularModulo`, `calcularDiseno`, `desglose`. Pruebas exactas. Documentar en `docs/precios.md` cómo se calcula, en lenguaje de mueblista, para que Julio lo revise.
5. **Colocación.** Pegado, imán, apilado y validaciones de espacio. Pruebas.
6. **Base de datos y seed.** `schema.prisma`, migración inicial, `seed.ts` con: familia `closet` con 4 tipos (colgador, repisero, cajonera, zapatero), 6 acabados de referencia con texturas provisionales, reglas versión 1, ajustes con número de WhatsApp desde `.env`, usuario admin desde `.env`.

**FASE 2 — Escena 3D (revisable en `/disenar` con un módulo fijo)**

7. **Escena base.** `Escena`, `Habitacion`, cámara y controles con límites, luces, sombras de contacto, cotas del espacio. Página `/disenar` con la pantalla de espacio.
8. **Módulo 3D.** `Modulo3D` desde `panelesDeModulo`, texturas con repetición real, cantos, `Puerta3D` y `Cajon3D` con apertura animada, `Tirador3D` con GLB. `FallbackSinWebGL`.
9. **Rendimiento.** `frameloop="demand"`, DPR, precarga de texturas, medición en Chrome con emulación de CPU ×4 y en un Android real. Ajustar hasta cumplir 30 fps con 6 módulos.

**FASE 3 — Configurador completo (revisable de punta a punta)**

10. **Estado y paneles.** Store con zundo, `HojaInferior`, paneles de módulos, medidas, interior, frente y acabado; selección en el 3D; menú flotante; deshacer y rehacer; persistencia local.
11. **API pública.** `/api/catalogo`, `/api/disenos`, `/api/disenos/[codigo]`, captura, `/api/cotizaciones`, límites por IP, `/api/health`. Pruebas de integración contra PostgreSQL de pruebas.
12. **Guardar, compartir y cotizar.** `BarraPrecio`, `ResumenPedido`, `FormularioCotizar`, página `/d/[codigo]`, mensaje de WhatsApp, evento a n8n. Prueba Playwright: armar dos módulos, cambiar acabado, cotizar, verificar el enlace `wa.me` y la fila en la base.

**FASE 4 — Panel de Julio (revisable con su usuario)**

13. **Auth y layout del admin.** Auth.js Credentials, `middleware.ts`, login, cambio de contraseña, usuarios.
14. **Cotizaciones.** Lista con filtros por estado, ficha con diseño (captura + resumen), cambio de estado con evento, notas, fecha de visita, enlace directo a WhatsApp del cliente.
15. **Catálogo y precios.** CRUD de familias, tipos de módulo (rangos, interiores permitidos, valores por defecto), acabados (con subida de textura y precios), reglas de precio con **vista previa**: al editar una regla se muestra el precio del closet de referencia antes y después; publicar crea una versión nueva.
16. **Ajustes.** Número de WhatsApp, Instagram, dirección, horario, texto del aviso de precio.

**FASE 5 — Sitio de marca y chatbot (revisable en la URL de pruebas)**

17. **Landing, galería, opiniones, contacto.** Con el logo y las fotos reales de Julio. Admin de trabajos (subida múltiple, orden, portada) y moderación de opiniones. SEO: metadatos, OG, sitemap, datos estructurados `LocalBusiness`.
18. **Chatbot y WhatsApp flotante.** Widget en todas las páginas; el bot usa el webhook de n8n con un prompt que conoce familias, acabados, rangos de precio por m² y el enlace al configurador. Botón flotante de WhatsApp.

**FASE 6 — Producción**

19. **Docker y VPS.** `Dockerfile` standalone, `compose.yml`, app en Easypanel desde GitHub (`develop` → pruebas, `main` → producción), PostgreSQL con volumen, volumen `/data/uploads`, dominio y HTTPS, variables de entorno, `backup.sh` diario a un almacenamiento fuera del VPS con 30 días de retención, prueba de restauración documentada.
20. **Cierre.** Pruebas en 3 celulares reales (Android gama media, Android gama alta, iPhone), Lighthouse móvil ≥ 85 en rendimiento y ≥ 95 en accesibilidad, lista de aceptación firmada por Julio, capacitación de 1 hora grabada, entrega de credenciales por canal seguro.
21. **Después (cotizar aparte):** WhatsApp Cloud API con plantillas (aviso automático al cliente y a Julio), "Ver en tu pieza" exportando GLB y abriendo `<model-viewer ar>`, foto del ambiente como fondo de la escena, familias cocina y escritorio, encuesta de postventa automática a los 15 días de la entrega.

---

## 10. Entorno

### Prerrequisitos
- Node.js 22 LTS, pnpm 9, Docker Desktop (local) o Docker Engine (VPS), Git.

### Variables de entorno
| Variable | Descripción | De dónde sale |
|---|---|---|
| `DATABASE_URL` | PostgreSQL | compose local o Easypanel |
| `AUTH_SECRET` | Firma de sesiones Auth.js | `openssl rand -base64 32` |
| `AUTH_URL` | URL pública de la app | dominio |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD_INICIAL` | Usuario admin del seed | Luis; se cambia al primer ingreso |
| `WHATSAPP_NUMERO` | Número de MG en formato internacional sin `+` | Julio |
| `N8N_WEBHOOK_URL`, `N8N_FIRMA_SECRETO` | Eventos a n8n | n8n de MG o de AT |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM` | Correo | Brevo |
| `UPLOADS_DIR` | Carpeta de imágenes (`/data/uploads` en Docker) | compose |
| `NEXT_PUBLIC_SITE_URL` | Para enlaces compartidos y OG | dominio |
| `NEXT_PUBLIC_CHATBOT_URL` | Webhook del chatbot | n8n |

Ningún valor real entra al repositorio. `.env.example` lleva las claves vacías.

### Levantar en local
```bash
pnpm install
docker compose -f docker/compose.dev.yml up -d
cp .env.example .env
pnpm db:migrate && pnpm db:seed
pnpm dev
```

---

## 11. Dependencias

### Producción
| Paquete | Para qué |
|---|---|
| next, react, react-dom | framework |
| three, @react-three/fiber, @react-three/drei, @react-spring/three | escena 3D y animación |
| zustand, zundo | estado con deshacer |
| zod | validación |
| @prisma/client | base de datos |
| next-auth@beta, bcryptjs | admin |
| sharp | imágenes |
| nodemailer | correo |
| @tanstack/react-query | catálogo en cliente |
| tailwindcss, class-variance-authority, clsx, lucide-react | UI |

### Desarrollo
| Paquete | Para qué |
|---|---|
| typescript, @types/three | tipos |
| prisma, tsx | migraciones y seed |
| vitest, @testing-library/react, jsdom | pruebas |
| @playwright/test | flujos |
| eslint, eslint-config-next, prettier | calidad |

---

## 12. Despliegue

### VPS
- Ubuntu 24.04, 2 vCPU, 4 a 8 GB RAM, 80 GB SSD, en el proveedor que Julio contrate (Hostinger VPS, DigitalOcean o Contabo). Lo instala AT: usuario no root con sudo, `ufw` con 22, 80 y 443, `fail2ban`, actualizaciones automáticas de seguridad, Docker, Easypanel.
- Easypanel: proyecto `mg-muebles` con servicios `web` (desde GitHub, Dockerfile en `docker/Dockerfile`), `postgres` (imagen oficial 16 con volumen) y, si se decide, `n8n`. Dominio `mgmuebles.cl` o el que compre Julio; HTTPS automático.
- Dos ambientes en el mismo VPS: `pruebas.mgmuebles.cl` desde `develop` y `mgmuebles.cl` desde `main`, cada uno con su base.

### Dockerfile (resumen)
Multi-stage: `deps` (pnpm install con lockfile), `build` (`prisma generate`, `next build` standalone), `runner` (node:22-alpine, usuario `node`, copia `.next/standalone`, `.next/static`, `public`, `prisma`; `CMD ["node", "server.js"]`). Las migraciones corren en el arranque con `prisma migrate deploy` desde un `entrypoint.sh`.

### CI/CD
- GitHub Actions en cada PR: `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build`. Playwright en `develop` y `main`.
- Easypanel despliega al hacer push en `develop` y `main`. Vuelta atrás: redeploy de la imagen anterior desde el panel (un minuto).

### Respaldos
- `docker/backup.sh` en cron diario a las 03:00: `pg_dump` comprimido + tar de `/data/uploads`, subida con `rclone` a un bucket fuera del VPS (Backblaze B2 o Cloudflare R2, decisión de Luis), retención 30 días. Restauración probada y documentada en `README.md` antes de la entrega.

### Dominio y correo
- DNS del dominio de MG apuntando al VPS (A y AAAA), `www` como CNAME. SPF y DKIM de Brevo para que los correos no caigan en spam.

---

## 13. Estrategia de pruebas

- **Unitarias (Vitest):** todo `src/domain` con cobertura 100 % de ramas en precios y geometría; casos de la sección 5.4 con valores exactos; `codigos.ts` sin caracteres ambiguos (0, O, 1, I, L); `whatsapp.ts` con el texto esperado.
- **Integración:** rutas de API contra una base PostgreSQL de pruebas (`DATABASE_URL_TEST`): guardar diseño recalcula precio aunque el cliente mande otro; límites por IP; captura rechaza archivos que no son PNG o pesan más de 2 MB.
- **E2E (Playwright):** 1) armar dos módulos, cambiar acabado, cotizar, verificar enlace `wa.me` y fila en la base; 2) abrir `/d/[codigo]` y editar una copia; 3) admin: cambiar reglas, ver la vista previa, publicar versión, comprobar que un diseño viejo no cambia; 4) móvil 375 × 812 con emulación táctil.
- **Rendimiento:** script de Playwright que mide el tiempo hasta el primer frame del canvas y los fps con 6 módulos; umbral en CI con CPU ×4.
- **Visual:** capturas de referencia del closet de prueba en 3 acabados para detectar regresiones de texturas.
- **Manual con Julio:** lista de aceptación por fase en `docs/aceptacion-fase-N.md`, con casilla, resultado y observación.

---

## 14. Skills a usar durante la construcción

| Skill | Cuándo | Para qué |
|---|---|---|
| `superpowers:test-driven-development` | pasos 2 a 5, 11 | precio y geometría exactos antes de tocar UI |
| `frontend-motion-toolkit` + `design-taste-frontend` | pasos 7 a 10, 17 | escena 3D con R3F, animaciones mínimas, criterio visual |
| `frontend-design` | pasos 10, 17 | interfaz distintiva, no genérica |
| `superpowers:systematic-debugging` | paso 9 | rendimiento en Android |
| `at-qa-automation` y Playwright | pasos 12, 20 | flujos y evidencia para Julio |
| `superpowers:verification-before-completion` | fin de cada fase | nada se declara listo sin evidencia |
| `code-review` | antes de cada entrega a Julio | revisión independiente |

---

## 15. CLAUDE.md para el repositorio `at-mg-muebles`

```markdown
# MG Muebles — Configurador 3D

Sitio y configurador 3D de muebles de melamina a medida para MG Muebles (Chile). El cliente arma módulos en una habitación 3D, ve el precio estimado y cotiza por WhatsApp; Julio administra catálogo, precios y cotizaciones en /admin.

## Comandos
- `pnpm dev` — servidor de desarrollo (http://localhost:3000)
- `pnpm build` / `pnpm start` — producción
- `pnpm lint` / `pnpm typecheck` — calidad
- `pnpm test` — Vitest (dominio y API); `pnpm test:e2e` — Playwright
- `pnpm db:migrate` — `prisma migrate dev`; `pnpm db:deploy` — `prisma migrate deploy`; `pnpm db:seed`; `pnpm db:studio`
- `docker compose -f docker/compose.dev.yml up -d` — PostgreSQL local

## Stack
Next.js 15 App Router + TypeScript estricto + Tailwind v4 + shadcn/ui + React Three Fiber/drei + zustand/zundo + zod + Prisma/PostgreSQL 16 + Auth.js v5 (Credentials) + Docker en VPS con Easypanel

## Arquitectura
- `src/domain/` — TypeScript puro: tipos, zod, geometría (`panelesDeModulo`, colocación) y precios (`calcularDiseno`). **Prohibido importar React, Next o Prisma aquí.** Es la única fuente de verdad del precio y de la forma del mueble; la escena 3D y el servidor la consumen.
- `src/app/(sitio)/` — páginas públicas. `src/app/admin/` — panel protegido por `middleware.ts`. `src/app/api/` — rutas públicas con zod y límites por IP.
- `src/components/configurador/` — escena R3F y paneles. `src/stores/configurador.ts` — estado con deshacer.
- `src/lib/` — Prisma (`db.ts`), Auth.js (`auth.ts`), uploads, n8n, formato es-CL.
- Flujo: cliente arma el diseño en el store → precio en vivo con `calcularDiseno` en el navegador → `POST /api/disenos` valida y **recalcula en el servidor** → guarda con `reglasVersion` → `POST /api/cotizaciones` crea la cotización, avisa a n8n y devuelve el enlace `wa.me`.

## Reglas de código
1. Un componente por archivo, máximo 300 líneas. Server Components por defecto; `"use client"` solo donde hay interacción o R3F.
2. Medidas en mm enteros en dominio y base; se convierten a cm solo al mostrar (`lib/formato.ts`). Precios en CLP enteros.
3. Toda entrada externa pasa por zod antes de tocar el dominio o la base.
4. El precio que se guarda es el del servidor. Nunca confiar en un total que venga del cliente.
5. Los diseños guardan una foto del desglose; cambiar reglas crea una versión nueva, nunca edita la activa.
6. Texturas: JPG 1024 × 1024, máximo 150 KB, en `public/texturas/<slug>.jpg`. GLB en `public/modelos/`, máximo 200 KB.
7. Canvas con `frameloop="demand"` y `dpr` tope 1.5. Cualquier cambio de escena se prueba en un Android real antes de cerrar la tarea.
8. Textos visibles en español de Chile. Identificadores en inglés. Sin em-dashes en la UI.
9. Alias `@/` para `src/`. Sin barrel files.
10. Nada de secretos en el repositorio. `.env.example` con claves vacías.

## Sistema de diseño
Primario `#E8590C` (hover `#C2410C`), WhatsApp `#25D366`, fondo `#FAFAF9`, superficie `#FFFFFF`, texto `#1C1917`, secundario `#78716C`, borde `#E7E5E4`, éxito `#16A34A`, error `#DC2626`. Títulos Plus Jakarta Sans 600/700, cuerpo Inter 400/500, precios tabulares. Base 4 px, radio 12 px, áreas táctiles ≥ 44 px, `dvh` nunca `vh`, `prefers-reduced-motion` respetado.

## Variables de entorno
`DATABASE_URL`, `AUTH_SECRET`, `AUTH_URL`, `ADMIN_EMAIL`, `ADMIN_PASSWORD_INICIAL`, `WHATSAPP_NUMERO`, `N8N_WEBHOOK_URL`, `N8N_FIRMA_SECRETO`, `SMTP_*`, `MAIL_FROM`, `UPLOADS_DIR`, `NEXT_PUBLIC_SITE_URL`, `NEXT_PUBLIC_CHATBOT_URL`.

## Flujo de trabajo
- Rama por tarea desde `develop`; PR con CI verde; `develop` se despliega a pruebas; `main` a producción solo con la aceptación de la fase firmada por Julio en `docs/aceptacion-fase-N.md`.
- Los criterios de programación que aporte Julio se agregan a `CONTRIBUTING.md` y se cumplen desde la tarea siguiente.

## Reglas no negociables
1. `src/domain` sin dependencias de framework y con pruebas exactas de precio.
2. El servidor recalcula todo precio antes de guardar.
3. Mobile-first: cada pantalla se construye primero a 375 px.
4. Ningún despliegue a `main` sin la lista de aceptación de la fase aprobada por Julio.
5. Sin secretos en código, logs ni capturas.
```

---

## 16. Reglas no negociables del proyecto

1. **El dominio es puro.** `src/domain` no importa React, Next ni Prisma. Toda la lógica de precio y de forma vive ahí y tiene pruebas exactas.
2. **El servidor manda en el precio.** Se recalcula en `POST /api/disenos` y `POST /api/cotizaciones`; el total del cliente se ignora.
3. **Versionado de reglas.** Editar precios crea una versión; los diseños guardados conservan la suya.
4. **Mobile-first y `dvh`.** Se diseña y prueba primero a 375 × 812 en un celular real.
5. **Español de Chile en todo lo visible.** Precios como `$486.500`, medidas como `180 × 210 cm`.
6. **Aceptación por fase.** Nada llega a producción sin la lista de aceptación de Julio.
7. **Sin secretos en el repositorio.** Variables por entorno; capturas y logs sin datos de clientes.
8. **Sin servicios externos innecesarios.** Sin Redis, MongoDB, S3 ni colas mientras el producto no lo exija con datos.
9. **Rendimiento medido, no supuesto.** 30 fps con 6 módulos en Android medio; se mide en cada cambio de escena.
10. **Accesibilidad.** Contraste AA, foco visible, áreas táctiles de 44 px, `prefers-reduced-motion`.

---

## 17. Insumos pendientes para construir el frontend (la cara al cliente)

Lo que hay que conseguir de Julio o decidir con Luis antes o durante la fase 2 y 5. Ordenado por cuándo bloquea.

| # | Insumo | Bloquea | Quién | Estado |
|---|---|---|---|---|
| 1 | **Familia y tipos de módulo de la fase 1**: confirmar closet con colgador, repisero, cajonera y zapatero; rangos reales de medidas (mínimos, máximos) y valores típicos | fase 1, paso 6 | Julio | pendiente |
| 2 | **Lista de precios real**: precio por m² de tablero 18 mm por acabado, metro de tapacanto, bisagra, par de correderas, barra, tirador, gola, patas; mano de obra; margen; descuento web | fase 1, paso 4 (se arranca con referencia) | Julio | pendiente |
| 3 | **Acabados de melamina** que trabaja (proveedor y nombre comercial, 6 a 10) y foto o muestra de cada uno para la textura | fase 2, paso 8 (se arranca con texturas provisionales) | Julio | pendiente |
| 4 | **Número de WhatsApp de MG** y horario de atención | fase 3, paso 12 | Julio | pendiente |
| 5 | **Logo en vector o PNG grande**, colores si los tiene, nombre exacto de la marca | fase 3 para la barra y fase 5 para el sitio | Julio | pendiente (Drive del cliente no lo tiene) |
| 6 | **Fotos de trabajos realizados** (20 a 40, agrupadas por tipo: closet, cocina, rack TV, cómoda) y 3 a 5 opiniones de clientes con nombre | fase 5, paso 17 | Julio | pendiente |
| 7 | **Textos del sitio**: quiénes son, comunas que atienden, cómo es el proceso (visita, plazo de fabricación, garantía), dirección si atiende público | fase 5, paso 17 | Julio, redacta AT | pendiente |
| 8 | **Dominio** (comprar o apuntar el existente) y correo de contacto | fase 6, paso 19 | Julio compra, AT configura | pendiente |
| 9 | **VPS**: proveedor y plan; acceso SSH para AT | fase 6, paso 19 (pruebas pueden correr antes en el VPS de AT) | Julio contrata, AT arma | pendiente |
| 10 | **Dirección visual**: aprobar el sistema de diseño provisional de la sección 8 o pedir un prototipo en Claude Design / Open Design antes de la fase 3 | fase 3, paso 10 | Luis y Julio | pendiente |
| 11 | **Tiradores y golas** que usa (foto de 2 o 3 modelos) para modelar los GLB una sola vez | fase 2, paso 8 | Julio | pendiente |
| 12 | **Decisión sobre n8n**: instancia propia de MG en el VPS o usar la de AT hasta la entrega | fase 5, paso 18 | Luis | pendiente |
| 13 | **Cuenta de WhatsApp Business** de MG (para la fase posterior con Cloud API) | después de la v1 | Julio | pendiente |

Mientras 1, 2, 3 y 11 no lleguen, el sistema se construye con los valores de referencia de este documento y se marcan en el admin como "referencia, reemplazar". Ningún dato de referencia debe llegar a producción.

---

## 18. Riesgos y supuestos

| Riesgo | Mitigación |
|---|---|
| Rendimiento del 3D en Android de gama media | `frameloop="demand"`, DPR 1.5, texturas de 150 KB, medir desde el paso 9, fallback 2D |
| Julio cambia la forma de cotizar a mitad del proyecto | reglas versionadas y editables; el motor acepta nuevas líneas sin tocar la UI |
| El presupuesto de la propuesta 14 ($300 USD) no cubre las fases 4 a 6 | el blueprint entrega por fases; la fase 3 ya es un producto usable; las siguientes se cotizan aparte |
| Spam de cotizaciones | límites por IP, honeypot en el formulario, validación de teléfono chileno |
| Pérdida de datos en el VPS | respaldo diario fuera del servidor con restauración probada |
| Dependencia de un solo desarrollador | este documento, `CLAUDE.md`, pruebas y `README.md` permiten que otro agente o persona continúe |

Supuestos: la fase 1 arranca con la familia closet; moneda CLP con IVA incluido; el cliente final no crea cuenta; WhatsApp por enlace en la v1; las texturas provisionales se reemplazan por fotos reales antes de producción.
