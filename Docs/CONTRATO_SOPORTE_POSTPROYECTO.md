# CONTRATO DE PRESTACIÓN DE SERVICIOS, CESIÓN DE PROPIEDAD INTELECTUAL Y SOPORTE TÉCNICO POST-PROYECTO

> **Plantilla legal — versión 2.0 (Chile) — BLINDADA**
> Esta plantilla cubre el **traspaso completo de propiedad** del Proyecto al CLIENTE una vez pagado, la **entrega obligatoria de credenciales y código fuente** en caso de salida hacia otro proveedor, y blinda al PROVEEDOR (AutomatizaTech) frente a uso indebido, modificaciones de terceros, y reclamos de propiedad intelectual sobre componentes preexistentes y `know-how`.
>
> **Antes de usar productivamente, validar con asesoría legal.** Las cláusulas marcadas 🛡️ son las de blindaje principal.

---

## Comparecientes

En **{{ciudad_firma}}**, a **{{fecha_firma_larga}}**, comparecen:

**POR UNA PARTE:**
**AutomatizaTech SpA** (en adelante "**EL PROVEEDOR**" o "**AT**"), RUT **{{rut_at}}**, representada legalmente por **{{representante_at_nombre}}**, RUT **{{representante_at_rut}}**, con domicilio en **{{domicilio_at}}**, correo electrónico **{{email_at}}**.

**Y POR LA OTRA:**
**{{razon_social_cliente}}** (en adelante "**EL CLIENTE**"), {{id_tipo_cliente}} **{{rut_cliente}}**, representada legalmente por **{{representante_cliente_nombre}}**, {{id_tipo_representante}} **{{representante_cliente_rut}}**, de nacionalidad **{{nacionalidad_representante}}**, con domicilio en **{{domicilio_cliente}}**, correo electrónico **{{email_cliente}}**, teléfono **{{telefono_cliente}}**.

Ambas partes, en adelante e indistintamente, "**LAS PARTES**", acuerdan celebrar el siguiente contrato (en adelante el "**Contrato**"), que se regirá por las cláusulas siguientes y, en lo no previsto en ellas, por la legislación chilena vigente.

---

## CLÁUSULA PRIMERA — Antecedentes

1.1. EL PROVEEDOR ha desarrollado y entregado a EL CLIENTE el proyecto denominado **"{{nombre_proyecto}}"** (en adelante el "**Proyecto**"), conforme a la propuesta comercial N° **{{propuesta_id}}** de fecha **{{fecha_propuesta}}**, aceptada por EL CLIENTE con fecha **{{fecha_aceptacion}}**.

1.2. El Proyecto fue entregado y aceptado formalmente el día **{{fecha_entrega}}**, según consta en el informe de QA y el acta de entrega adjuntos como **Anexo A**, y el pago final fue recibido por EL PROVEEDOR el día **{{fecha_pago_final}}**.

1.3. EL CLIENTE requiere asegurar la continuidad operacional del Proyecto, contar con servicios de mantención correctiva, evolutiva y soporte técnico, y dejar formalizada la titularidad y portabilidad del Proyecto, materias todas que se regulan en este Contrato.

---

## CLÁUSULA SEGUNDA — Objeto

2.1. Por el presente Contrato:
- a) EL PROVEEDOR **cede y transfiere** a EL CLIENTE la propiedad del Proyecto en los términos de la cláusula duodécima.
- b) EL PROVEEDOR se obliga a prestar los servicios de **soporte técnico, mantención correctiva y mantención evolutiva** sobre el Proyecto, en los términos del **Plan "{{nombre_plan_soporte}}"** detallado en el **Anexo B**.
- c) EL PROVEEDOR se obliga a entregar al CLIENTE la totalidad de credenciales, accesos, código fuente y documentación necesarios para la explotación autónoma del Proyecto, conforme a la cláusula décimotercera (**Portabilidad y Salida**).

---

## CLÁUSULA TERCERA — Alcance de los servicios de soporte

3.1. **Sí incluye:**
- a) Corrección de errores ("**bugs**") atribuibles directamente al desarrollo entregado por EL PROVEEDOR.
- b) Atención y resolución de incidentes a través del Portal OmniCliente y/o correo electrónico contacto@automatizatech.cl.
- c) Monitoreo básico de disponibilidad (uptime).
- d) Aplicación de actualizaciones menores de seguridad sobre los componentes desarrollados por EL PROVEEDOR.
- e) Hasta **{{horas_evolutivas_mes}}** horas mensuales de mantención evolutiva (mejoras menores).
- f) Asesoría técnica y orientación funcional al equipo del CLIENTE.

3.2. **No incluye** (se cotiza por separado):
- a) Desarrollo de nuevas funcionalidades o módulos no contemplados en la propuesta original.
- b) Migraciones de infraestructura o cambios de proveedor de hosting iniciados por EL CLIENTE.
- c) Recuperación de datos perdidos por errores u omisiones de usuarios del CLIENTE o terceros.
- d) Soporte sobre integraciones con APIs de terceros que cambien sus términos, precios o disponibilidad.
- e) Capacitaciones formales, talleres o producción de contenido.
- f) Soporte sobre modificaciones realizadas al Proyecto por personas distintas a EL PROVEEDOR sin autorización previa (cláusula 8.2.d).
- g) Servicios de diseño gráfico, copywriting o producción audiovisual.
- h) Costos de infraestructura, licencias de terceros, dominios, certificados SSL, créditos de OpenAI, WhatsApp Business API, hosting, etc., los que serán siempre de cargo del CLIENTE.

---

## CLÁUSULA TERCERA BIS — Garantía post-entrega del Proyecto 🛡️

3A.1. **Período de garantía:** EL PROVEEDOR otorga al CLIENTE una **garantía de {{garantia_meses}} mes(es)** contados desde la fecha de entrega formal del Proyecto (Anexo A), período durante el cual toda **corrección, ajuste o arreglo** derivado directa o indirectamente de un error, omisión o defecto **atribuible al desarrollo original** realizado por EL PROVEEDOR será resuelto **sin costo de honorarios adicional** para EL CLIENTE. **Únicamente será cobrado el IVA** cuando la emisión de una boleta o factura sea exigible por normativa tributaria chilena. Esta garantía opera con independencia de si el CLIENTE ha contratado un plan de soporte mensual.

> ⚠️ **Límite absoluto de la garantía:** La garantía cubre **exclusivamente bugs, errores funcionales y comportamientos no conformes al requerimiento original acordado**. Cualquier solicitud de **nueva funcionalidad, nuevo módulo, nueva pantalla o cambio de alcance** que no esté expresamente contemplado en la propuesta original N° {{propuesta_id}} constituye un **nuevo desarrollo** y será cotizado y cobrado de forma separada, sin excepción.

3A.2. **¿Qué cubre la garantía?** Se consideran cubiertos sin costo adicional:
- a) Corrección de errores ("bugs") funcionales o lógicos en el código desarrollado.
- b) Ajustes de comportamiento que no correspondan a lo especificado en la propuesta aceptada.
- c) Fallas en integraciones entregadas que no dependan de cambios en APIs o servicios de terceros.
- d) Inconsistencias visuales o de rendimiento en las interfaces entregadas.
- e) Cualquier arreglo, corrección o mejora **directamente vinculada** al alcance del Proyecto original.

3A.3. **¿Qué NO cubre la garantía (es un nuevo desarrollo con costo adicional)?** 🛡️
- a) **Nuevas funcionalidades**, módulos o pantallas no contempladas en la propuesta original. **Toda nueva funcionalidad es un nuevo desarrollo y tiene costo adicional, independiente del período de garantía vigente.**
- b) **Cambios de alcance** o requerimientos nuevos solicitados por el CLIENTE tras la entrega.
- c) Funcionalidades **aisladas o independientes** del desarrollo original que constituyan un trabajo nuevo.
- d) Ajustes por cambios en plataformas o APIs de terceros (OpenAI, Meta, Google, etc.) ajenos al control de EL PROVEEDOR.
- e) Correcciones de errores causados por modificaciones al Proyecto realizadas por el CLIENTE o terceros sin autorización.

3A.4. **Procedimiento:** El CLIENTE reportará los defectos cubiertos a través del canal oficial (Portal OmniCliente o correo electrónico). EL PROVEEDOR analizará la solicitud y comunicará dentro de **5 días hábiles** si corresponde a garantía (sin costo) o a un servicio fuera de alcance (con cotización), indicando el fundamento técnico de su decisión.

3A.5. **Cláusula de confianza:** EL PROVEEDOR y EL CLIENTE acuerdan resolver de buena fe cualquier disputa sobre si un arreglo está o no cubierto por garantía, privilegiando la relación comercial de largo plazo sobre la facturación puntual. En caso de duda razonable, EL PROVEEDOR atenderá la solicitud como garantía.

---

## CLÁUSULA CUARTA — Vigencia y renovación

4.1. El presente Contrato tendrá una vigencia inicial de **{{vigencia_meses}} meses** contados desde el **{{fecha_inicio_soporte}}**.

4.2. Vencido dicho plazo, el Contrato se entenderá **renovado tácita y automáticamente** por períodos iguales y sucesivos, salvo aviso por escrito de cualquiera de las partes con al menos **30 días corridos** de anticipación a la fecha de término.

4.3. Cualquiera de las partes podrá poner término anticipado al Contrato comunicándolo por escrito con al menos **60 días corridos** de anticipación, sin expresión de causa, sin derecho a indemnización para la otra parte. En tal caso operará la cláusula décimotercera (Portabilidad y Salida).

4.4. EL PROVEEDOR podrá poner término **inmediato** al Contrato, sin aviso previo y sin responsabilidad alguna, en caso de:
- a) Mora del CLIENTE en el pago superior a 30 días corridos.
- b) Uso del Proyecto para actividades ilícitas, fraudulentas o contrarias a las buenas costumbres.
- c) Modificación no autorizada del Proyecto que cause daño o impida la prestación del servicio.
- d) Incumplimiento grave de las obligaciones de confidencialidad o seguridad.

---

## CLÁUSULA QUINTA — Precio y forma de pago

5.1. EL CLIENTE pagará a EL PROVEEDOR la suma mensual de **${{monto_mensual}} CLP**, más el Impuesto al Valor Agregado (IVA) correspondiente. En el caso del **Plan de Garantía post-entrega**, el monto base de honorarios es **$0 (cero pesos)** y únicamente se emitirá boleta o factura por el IVA cuando la normativa tributaria chilena lo exija.

5.1bis. **Nueva funcionalidad = nuevo presupuesto:** Toda solicitud que implique agregar funcionalidades, módulos, pantallas o integraciones no contempladas en la propuesta original N° {{propuesta_id}} constituye un **nuevo proyecto de desarrollo**, sujeto a cotización y aprobación expresa por escrito, con independencia del plan contratado y del período de garantía vigente.

5.2. La facturación se emitirá los primeros 5 días hábiles de cada mes, con vencimiento a **{{dias_pago}} días corridos** desde su emisión.

5.3. La mora superior a **15 días corridos** dará derecho a EL PROVEEDOR a:
- a) Suspender la prestación de los servicios sin responsabilidad alguna, previo aviso de 5 días.
- b) Cobrar interés moratorio equivalente a la tasa máxima convencional vigente.
- c) Retener (no eliminar) accesos administrativos a entornos provistos por EL PROVEEDOR hasta la regularización del pago, sin que ello afecte la propiedad del CLIENTE sobre el Proyecto.

5.4. **Reajuste anual:** El precio se reajustará automáticamente cada 12 meses según la variación del Índice de Precios al Consumidor (IPC) publicado por el INE.

5.5. Las horas evolutivas no utilizadas en un mes calendario **no son acumulables**.

5.6. Servicios fuera de alcance (cláusula 3.2) se cotizarán a una tarifa de **${{valor_hora}} + IVA por hora**, requiriendo orden de compra previa del CLIENTE.

---

## CLÁUSULA SEXTA — Niveles de servicio (SLA)

6.1. Clasificación de incidentes y tiempos comprometidos:

| Severidad | Descripción | Respuesta | Resolución objetivo |
|---|---|---|---|
| **Crítica (S1)** | Servicio caído / pérdida total de funcionalidad principal | {{sla_s1_respuesta}} | {{sla_s1_resolucion}} |
| **Alta (S2)** | Funcionalidad importante degradada sin workaround | {{sla_s2_respuesta}} | {{sla_s2_resolucion}} |
| **Media (S3)** | Funcionalidad menor con bajo impacto / con workaround | {{sla_s3_respuesta}} | {{sla_s3_resolucion}} |
| **Baja (S4)** | Consultas, mejoras menores, dudas de uso | {{sla_s4_respuesta}} | {{sla_s4_resolucion}} |

6.2. **Horario de atención:** lunes a viernes hábiles, **{{hora_inicio_soporte}} a {{hora_fin_soporte}}** (hora de Chile continental). Para S1, atención 24/7 a través de **correo electrónico contacto@automatizatech.cl**.

6.3. Los tiempos se computan desde el ingreso formal del ticket en el Portal OmniCliente o canal oficial.

6.4. **Crédito por incumplimiento de SLA:** ante incumplimientos atribuibles a EL PROVEEDOR, EL CLIENTE tendrá derecho a un crédito en su factura del mes siguiente equivalente a **{{credito_sla}}%** del valor mensual por cada incidente S1 o S2 incumplido, **con un tope mensual del 30% del valor del Plan**. Este crédito constituye el **único y exclusivo remedio** del CLIENTE por incumplimiento de SLA, renunciando expresamente a cualquier otra acción o indemnización.

6.5. **Exclusiones de SLA:**
- a) Fallas de proveedores externos (hosting, APIs, redes, WhatsApp, OpenAI, Google, Meta, etc.).
- b) Fuerza mayor o caso fortuito.
- c) Mantenciones programadas comunicadas con 48h de anticipación.
- d) Cambios o modificaciones realizadas por EL CLIENTE o terceros sin autorización.
- e) Fallas derivadas de licencias vencidas, dominios caídos o créditos agotados de servicios contratados directamente por el CLIENTE.
- f) Ataques cibernéticos no atribuibles a negligencia grave de EL PROVEEDOR.

---

## CLÁUSULA SÉPTIMA — Canal de comunicación y reporte de incidentes

7.1. Canal oficial: **Portal OmniCliente** (URL: **{{url_portal}}**), credenciales personales y nominativas entregadas por EL PROVEEDOR.

7.2. Canal secundario: correo electrónico **contacto@automatizatech.cl**. Toda comunicación por este canal se trasladará al Portal para trazabilidad.

7.3. EL CLIENTE designa como contraparte técnica autorizada a **{{contraparte_tecnica_nombre}}** ({{contraparte_tecnica_email}}).

---

## CLÁUSULA OCTAVA — Obligaciones de las partes

8.1. **EL PROVEEDOR se obliga a:**
- a) Prestar los servicios con diligencia profesional.
- b) Cumplir los SLAs definidos.
- c) Mantener registro auditable de todas las intervenciones.
- d) Guardar confidencialidad sobre la información del CLIENTE.
- e) Informar oportunamente riesgos, brechas e incidentes relevantes.
- f) Entregar credenciales y accesos conforme a la cláusula décimotercera al término del Contrato.

8.2. **EL CLIENTE se obliga a:** 🛡️
- a) Pagar oportunamente los honorarios pactados.
- b) Proporcionar accesos, credenciales e información necesaria para la prestación.
- c) Reportar incidentes a través de los canales oficiales.
- d) **NO modificar ni autorizar a terceros a modificar el código fuente, configuraciones, infraestructura o credenciales entregadas sin autorización previa y por escrito de EL PROVEEDOR durante la vigencia de este Contrato.** El incumplimiento libera automáticamente a EL PROVEEDOR de toda responsabilidad sobre fallas, pérdidas o daños derivados, y suspende los SLAs hasta que el Proyecto sea restaurado a su estado autorizado, lo que se cobrará a la tarifa de servicios fuera de alcance.
- e) Mantener vigentes y al día hosting, dominios, licencias, certificados SSL, créditos de APIs (OpenAI, WhatsApp Business API, Meta, Google) y demás servicios de terceros necesarios para la operación.
- f) Custodiar las credenciales y accesos entregados, siendo de su exclusiva responsabilidad cualquier uso indebido, filtración o pérdida de los mismos.
- g) Realizar respaldos periódicos de datos operativos. EL PROVEEDOR no será responsable por pérdida de datos del negocio del CLIENTE salvo cuando sea responsable de los respaldos por contrato expreso.
- h) Usar el Proyecto únicamente para fines lícitos y conforme a su propósito original.

---

## CLÁUSULA NOVENA — Limitación de responsabilidad 🛡️

9.1. La responsabilidad **total, acumulada y máxima** de EL PROVEEDOR bajo este Contrato, por cualquier causa, motivo o fundamento, se limita al **monto equivalente a los últimos {{meses_topes}} meses** de honorarios efectivamente pagados por EL CLIENTE inmediatamente anteriores al hecho que generó la responsabilidad.

9.2. **EL PROVEEDOR en ningún caso responderá por:**
- a) Daños indirectos, mediatos, consecuenciales, punitivos o ejemplares.
- b) Lucro cesante, pérdida de ingresos, oportunidades comerciales, clientes o reputación.
- c) Pérdida de datos, salvo dolo o culpa grave acreditada.
- d) Multas o sanciones de organismos reguladores (SII, CMF, SBIF, Sernac, etc.) impuestas al CLIENTE.
- e) Responsabilidad frente a terceros derivada del uso del Proyecto por parte del CLIENTE.
- f) Decisiones automatizadas tomadas por agentes de IA o bots configurados a partir de prompts y datos provistos por el CLIENTE.
- g) Resultados comerciales, conversiones, ventas o KPIs proyectados.
- h) Disponibilidad o cambios en servicios de terceros (OpenAI, Meta WhatsApp, Google Calendar, hosting, etc.).
- i) Cumplimiento normativo del CLIENTE (Ley 19.628, Ley 21.719, Ley del Consumidor, normativa SII, etc.) salvo asesoría expresa y por escrito.

9.3. EL PROVEEDOR no será responsable por interrupciones, defectos o pérdidas causadas por (i) fuerza mayor o caso fortuito, (ii) actos u omisiones del CLIENTE o terceros, (iii) fallas en servicios de terceros, (iv) ataques cibernéticos no atribuibles a negligencia grave de EL PROVEEDOR, (v) modificaciones no autorizadas, (vi) uso fuera del propósito original del Proyecto.

9.4. **Garantía limitada:** EL PROVEEDOR garantiza que el Proyecto fue desarrollado conforme a las especificaciones aceptadas. **NO otorga garantía alguna** —expresa o implícita— de comerciabilidad, idoneidad para un propósito particular distinto al pactado, exactitud de las respuestas de modelos de IA, ni de no infracción derivada del uso por el CLIENTE de los entregables.

9.5. **Indemnidad del CLIENTE hacia EL PROVEEDOR:** EL CLIENTE mantendrá indemne a EL PROVEEDOR frente a cualquier reclamo, demanda o acción de terceros derivada de: (i) el uso que el CLIENTE dé al Proyecto, (ii) los datos, contenidos, prompts y configuraciones cargados por el CLIENTE, (iii) decisiones tomadas en base a outputs del Proyecto, (iv) incumplimientos del CLIENTE a la normativa aplicable.

---

## CLÁUSULA DÉCIMA — Confidencialidad

10.1. LAS PARTES se obligan a guardar reserva absoluta sobre toda información técnica, comercial, financiera, estratégica o personal a la que accedan con motivo del Contrato, durante su vigencia y por **3 años** posteriores a su término.

10.2. La obligación se extiende a empleados, subcontratistas y representantes de cada parte.

10.3. El incumplimiento dará derecho a la parte afectada a indemnización por perjuicios y a las acciones legales que correspondan.

10.4. **No se considera información confidencial:** (i) la que sea de dominio público sin culpa de la parte receptora, (ii) la desarrollada independientemente, (iii) la requerida por autoridad judicial o administrativa competente.

---

## CLÁUSULA UNDÉCIMA — Tratamiento de datos personales

11.1. EL PROVEEDOR actuará como **encargado del tratamiento** de datos personales por cuenta del CLIENTE en lo estrictamente necesario para la prestación, conforme a la **Ley 19.628** y, cuando entre en vigor, la **Ley 21.719**.

11.2. EL PROVEEDOR adoptará medidas técnicas y organizativas razonables, y notificará al CLIENTE cualquier incidente de seguridad relevante dentro de **72 horas** de tomar conocimiento.

11.3. EL CLIENTE es **responsable del tratamiento** y declara contar con la base legal y consentimientos necesarios para los datos que carga, almacena o procesa a través del Proyecto. EL CLIENTE indemnizará a EL PROVEEDOR ante cualquier reclamo derivado de la falta de dichos consentimientos o bases legales.

11.4. Al término del Contrato, EL PROVEEDOR devolverá o eliminará los datos personales del CLIENTE dentro de **30 días corridos**, salvo obligación legal de conservación, dejando constancia escrita.

---

## CLÁUSULA DUODÉCIMA — Propiedad intelectual y cesión de derechos 🛡️

12.1. **Cesión al CLIENTE:** Habiendo el CLIENTE pagado íntegramente el valor del Proyecto, EL PROVEEDOR **cede y transfiere** al CLIENTE, en forma exclusiva, perpetua, irrevocable y para todos los territorios, los derechos patrimoniales de autor sobre los **entregables específicos** desarrollados a medida para el Proyecto, conforme al art. 17 y siguientes de la Ley 17.336. Esto incluye:
- a) Código fuente desarrollado a medida para el Proyecto.
- b) Diseños, interfaces gráficas y assets visuales creados específicamente para el Proyecto.
- c) Documentación técnica y funcional del Proyecto.
- d) Configuraciones, prompts, workflows N8N y plantillas creadas exclusivamente para el Proyecto.
- e) Bases de datos estructurales del Proyecto (esquemas, modelos), excluyendo datos operativos del negocio del CLIENTE que ya son de su propiedad.

A consecuencia de lo anterior, **EL CLIENTE es el ÚNICO Y EXCLUSIVO PROPIETARIO del Proyecto** y podrá usarlo, modificarlo, cederlo, sublicenciarlo y explotarlo sin restricción, en Chile o en el extranjero, sin necesidad de autorización ni pago adicional a EL PROVEEDOR.

12.2. **Reserva del PROVEEDOR (componentes preexistentes y `know-how`):** Permanecen de propiedad exclusiva de EL PROVEEDOR y se otorgan al CLIENTE bajo **licencia no exclusiva, perpetua, intransferible y de uso interno** para operar el Proyecto:
- a) Frameworks, librerías, scripts genéricos, módulos reutilizables, componentes base y `boilerplates` previos al Proyecto o desarrollados de forma genérica.
- b) Metodologías, plantillas de prompts genéricas, conocimientos técnicos y `know-how` profesional acumulado.
- c) Herramientas internas de desarrollo, despliegue y monitoreo de EL PROVEEDOR.
- d) Software de terceros sujeto a sus propias licencias (open source o comerciales).

EL PROVEEDOR conserva el derecho de seguir usando estos elementos en otros proyectos para otros clientes, sin restricción alguna y sin que ello implique competencia desleal.

12.3. **Componentes de terceros:** El Proyecto puede incluir o depender de software, APIs, servicios cloud y librerías de terceros sujetos a sus propios términos y condiciones (open source, SaaS, etc.). Es responsabilidad del CLIENTE mantener vigentes las licencias y créditos correspondientes.

12.4. **Derechos morales:** EL PROVEEDOR conserva los derechos morales irrenunciables sobre la obra, conforme a la ley chilena (paternidad e integridad), pero renuncia expresamente al derecho de mención pública salvo autorización del CLIENTE.

12.5. **Portfolio:** EL PROVEEDOR podrá incluir el nombre y logo del CLIENTE y una descripción genérica del Proyecto en su portfolio comercial y referencias, salvo que el CLIENTE manifieste lo contrario por escrito.

---

## CLÁUSULA DÉCIMOTERCERA — Portabilidad y Salida (Exit Clause) 🛡️

> **Esta cláusula garantiza al CLIENTE la independencia total y la posibilidad de continuar el Proyecto con otro proveedor.**

13.1. **Entrega de credenciales y accesos.** Al término del Contrato por cualquier causa —o ante solicitud expresa del CLIENTE durante su vigencia siempre que se encuentre al día en los pagos— EL PROVEEDOR se obliga a entregar al CLIENTE, dentro de **{{dias_handover}} días hábiles** contados desde la solicitud o término, **la totalidad** de los siguientes elementos:

- a) **Código fuente** completo y actualizado del Proyecto, en repositorio Git (preferentemente transferencia de la titularidad del repositorio o entrega de un export completo con historial).
- b) **Credenciales y accesos** a todos los servicios, sistemas y plataformas del Proyecto, incluyendo:
  - Panel de administración WordPress (o el CMS aplicable).
  - Bases de datos (host, usuario, contraseña, certificados).
  - Hosting y panel de control (cPanel, Hostinger, AWS, etc.).
  - Dominios y DNS (con cesión de titularidad si están a nombre de AT).
  - Cuentas de email corporativo asociadas al Proyecto.
  - Certificados SSL.
  - Cuentas de servicios de terceros creadas para el Proyecto: OpenAI, WhatsApp Business API (Meta), Google Cloud / Workspace, N8N, Redis, etc., en la medida que estén a nombre de AT y sean transferibles.
  - APIs internas: API keys, secretos, tokens y webhooks.
  - Repositorios de código (GitHub, GitLab, Bitbucket).
  - Cuentas de servicios de pago (Khipu, MercadoPago, Stripe) en cuanto sean transferibles.
- c) **Documentación técnica** vigente: arquitectura, diagramas, esquema de base de datos, endpoints, configuraciones, manuales de despliegue y operación.
- d) **Backup completo** de la base de datos en formato SQL estándar, incluyendo todos los datos operativos.
- e) **Archivos y assets** alojados en almacenamiento (uploads, media, attachments).
- f) **Configuraciones y workflows** de automatización (N8N, cron jobs, plantillas de prompts).
- g) **Listado de servicios de terceros activos**, fechas de renovación, costos y procedimiento de transferencia o re-contratación.

13.2. **Sesión de transferencia (handover):** EL PROVEEDOR realizará una sesión técnica de transferencia de hasta **{{horas_handover}} horas** sin costo adicional con el equipo o nuevo proveedor designado por EL CLIENTE, para entregar el conocimiento operativo del Proyecto.

13.3. **Soporte de transición:** A solicitud del CLIENTE y previo acuerdo escrito, EL PROVEEDOR podrá prestar soporte de transición adicional al nuevo proveedor durante hasta **30 días corridos** posteriores al término, cobrado a la tarifa de servicios fuera de alcance.

13.4. **Acta de entrega.** El cumplimiento de esta cláusula se formalizará mediante un **Acta de Handover** firmada por LAS PARTES, donde se detallarán los elementos entregados. Una vez suscrita, EL PROVEEDOR queda liberado de toda responsabilidad ulterior sobre la operación del Proyecto.

13.5. **Eliminación de accesos residuales.** Dentro de los **15 días hábiles** siguientes a la firma del Acta de Handover, EL PROVEEDOR eliminará todos sus accesos administrativos al Proyecto, salvo aquellos expresamente requeridos por EL CLIENTE para soporte de transición.

13.6. **Limitación.** Esta cláusula no obliga a EL PROVEEDOR a entregar:
- a) Componentes preexistentes y `know-how` reservados conforme a la cláusula 12.2 (el CLIENTE conserva la licencia de uso interno).
- b) Datos de otros clientes o información confidencial de terceros.
- c) Software de terceros cuya licencia esté a nombre de AT y no sea transferible (en cuyo caso AT informará el procedimiento de re-contratación a nombre del CLIENTE).

13.7. **Mora del CLIENTE.** Si al término existieran sumas adeudadas, EL PROVEEDOR podrá retener la entrega de credenciales hasta el pago efectivo, sin perjuicio de la propiedad del CLIENTE sobre el Proyecto. La retención cesará de pleno derecho una vez regularizada la deuda.

---

## CLÁUSULA DÉCIMOCUARTA — No competencia y no contratación de personal

14.1. Durante la vigencia y por **12 meses** posteriores al término, EL CLIENTE se obliga a no contratar directa o indirectamente a personal de EL PROVEEDOR que haya participado en el Proyecto o el soporte, sin autorización previa y por escrito.

14.2. El incumplimiento dará derecho a EL PROVEEDOR a exigir, a título de cláusula penal, una suma equivalente a **6 meses** de la última remuneración bruta del trabajador contratado.

---

## CLÁUSULA DÉCIMOQUINTA — Cesión

Ninguna parte podrá ceder total o parcialmente sus derechos u obligaciones sin autorización previa y por escrito de la otra, salvo en caso de reorganización societaria del mismo grupo empresarial.

---

## CLÁUSULA DÉCIMOSEXTA — Notificaciones

Toda notificación deberá efectuarse por escrito al correo electrónico indicado en la comparecencia, entendiéndose practicada al día hábil siguiente de su envío.

---

## CLÁUSULA DÉCIMOSÉPTIMA — Fuerza mayor

Ninguna parte será responsable del incumplimiento causado por fuerza mayor o caso fortuito, incluyendo —de forma enunciativa y no taxativa— catástrofes naturales, pandemias, actos de autoridad, ciberataques masivos no dirigidos específicamente a la parte, caídas masivas de proveedores cloud, conflictos armados o disturbios civiles.

---

## CLÁUSULA DÉCIMOCTAVA — Domicilio, ley aplicable y jurisdicción

18.1. Para todos los efectos, las partes fijan domicilio en la ciudad de **{{ciudad_jurisdiccion}}**, sometiéndose a la competencia de sus Tribunales Ordinarios de Justicia.

18.2. El presente Contrato se rige por la legislación de la República de Chile.

18.3. **Mediación previa.** Antes de iniciar acciones judiciales por controversias relativas a este Contrato, las partes intentarán de buena fe una **mediación** ante un mediador independiente designado de común acuerdo, por un plazo no inferior a 30 días corridos.

---

## CLÁUSULA DÉCIMONOVENA — Firma electrónica

19.1. LAS PARTES aceptan expresamente que el presente Contrato puede ser suscrito mediante **firma electrónica simple o avanzada**, conforme a la **Ley 19.799** sobre Documentos Electrónicos, Firma Electrónica y Servicios de Certificación.

19.2. Se entenderá perfeccionado y plenamente válido al momento en que EL CLIENTE acepte digitalmente sus términos a través del Portal OmniCliente, dejándose constancia del nombre, RUT, dirección IP, fecha y hora de aceptación, navegador, dispositivo y un código **hash SHA-256** que garantiza la integridad del documento. Adicionalmente, podrá adjuntarse imagen de firma manuscrita escaneada o trazo digital realizado en pantalla.

19.3. El registro digital de la firma será almacenado por EL PROVEEDOR de forma íntegra y podrá ser exhibido como medio de prueba conforme al art. 5 de la Ley 19.799.

19.4. Una vez firmado, el documento PDF resultante se enviará por correo electrónico a ambas partes y quedará disponible permanentemente en la ficha del CLIENTE dentro del Portal OmniCliente.

---

## CLÁUSULA VIGÉSIMA — Cláusulas finales

20.1. **Integralidad.** Este Contrato, junto con sus Anexos, constituye el acuerdo íntegro entre las partes y reemplaza cualquier acuerdo previo, escrito o verbal, sobre la misma materia.

20.2. **Modificaciones.** Solo serán válidas si constan por escrito firmado por ambas partes.

20.3. **Divisibilidad.** Si alguna cláusula fuere declarada nula o inaplicable, las demás conservarán plena validez.

20.4. **Encabezados.** Los títulos son referenciales y no afectan la interpretación.

---

## ACEPTACIÓN

En señal de conformidad, las partes firman el presente Contrato en **{{ciudad_firma}}**, a **{{fecha_firma_larga}}**, en dos ejemplares de igual tenor (o en formato electrónico único firmado digitalmente).

| **POR EL PROVEEDOR** | **POR EL CLIENTE** |
|---|---|
| {{firma_at_img}} | {{firma_cliente_img}} |
| **{{representante_at_nombre}}** | **{{representante_cliente_nombre}}** |
| RUT: {{representante_at_rut}} | RUT: {{representante_cliente_rut}} |
| AutomatizaTech SpA — RUT: {{rut_at}} | {{razon_social_cliente}} — RUT: {{rut_cliente}} |

---

## ANEXO A — Acta de entrega del Proyecto

- **Proyecto:** {{nombre_proyecto}}
- **Fecha de entrega:** {{fecha_entrega}}
- **Informe QA N°:** {{informe_qa_id}}
- **URL de la aplicación / entregables:** {{url_app}}
- **Hash de integridad del entregable:** {{hash_entregable}}
- **Pago final recibido:** {{fecha_pago_final}}

---

## ANEXO B — Plan de Soporte "{{nombre_plan_soporte}}"

| Concepto | Detalle |
|---|---|
| Horas evolutivas/mes | {{horas_evolutivas_mes}} |
| Tickets ilimitados | Sí |
| Horario L-V | {{hora_inicio_soporte}} a {{hora_fin_soporte}} hrs |
| Atención 24/7 incidentes S1 | Sí, por {{canal_24x7}} |
| Monitoreo de uptime | Básico |
| Backups gestionados | {{backups_incluidos}} |
| Reportes mensuales | Sí, primer día hábil del mes siguiente |
| Reuniones de seguimiento | {{frecuencia_reuniones}} |
| Valor hora fuera de alcance | ${{valor_hora}} + IVA |
| Valor mensual del plan | ${{monto_mensual}} + IVA |

---

## ANEXO C — Hash de integridad y registro de firma (autogenerado)

| Campo | Valor |
|---|---|
| Hash SHA-256 del documento | `{{document_hash}}` |
| ID de contrato | `{{contract_id}}` |
| Token de firma | `{{sign_token}}` |
| Fecha de envío para firma | `{{sent_at}}` |
| Fecha de aceptación | `{{signed_at}}` |
| IP del firmante | `{{signer_ip}}` |
| User-Agent | `{{signer_user_agent}}` |
| Método de firma | `{{signature_method}}` (canvas / image-upload / advanced) |

---

_Versión 2.0 BLINDADA — generada el 2026-04-29. Validar con asesoría legal antes de uso productivo. Cláusulas 12 y 13 son las claves para garantizar al CLIENTE la propiedad y portabilidad del Proyecto._
