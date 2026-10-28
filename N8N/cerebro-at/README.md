# Cerebro AT: lo que saben y cómo se comportan los asistentes Tech

Fuente única del conocimiento, las reglas, la personalidad y la validación de datos de los tres asistentes Tech de AutomatizaTech: **Tech Web** (chat de automatizatech.cl), **Tech Instagram** (mensajes directos) y **Tech WhatsApp**. Pedido de Luis del 28-oct-2026: que los tres manejen lo mismo y que un cambio se haga en un solo lugar. Lo mantiene **Eje**.

```
                 reglas.md + cerebro-at.md  (iguales en los tres)
                              │
                      sincronizar.py
             ┌────────────────┼──────────────────┐
         Tech Web        Tech Instagram      Tech WhatsApp
   + personalidad-web  + personalidad-     + personalidad-whatsapp
                         instagram
   formulario de la    validacion.js en    validacion.js en
   web (ya valida)     «Validar cita»      «Check Email Booking»
```

## Archivos

| Archivo | Qué es | Dónde termina |
|---|---|---|
| `cerebro-at.md` | Quién es Tech, quiénes somos, lo que ofrecemos hoy, el Método AT, el diagnóstico gratis, lo que publicamos en Instagram y los planes del Portal OmniCliente | Tramo «CEREBRO AT» del prompt web y del de WhatsApp; Instagram lo hereda del web |
| `reglas.md` | Temas permitidos y reglas de seguridad y privacidad | Tramo «REGLAS AT» del web y de WhatsApp; Instagram lo hereda |
| `personalidad-web.md` | Tech Web, el asesor de la vitrina | Tramo «PERSONALIDAD» del web, dentro de la parte que `aplicar.py` le quita a Instagram |
| `personalidad-whatsapp.md` | Tech WhatsApp, el coordinador | Tramo «PERSONALIDAD» de WhatsApp |
| `personalidad-instagram.md` | Tech Instagram, el cercano | Bloque del canal en `N8N/instagram/` (lo arma `build_agente_instagram.py`) |
| `validacion.js` | Nombre y apellido, correo (con errores de tipeo frecuentes) y WhatsApp con código de país | Nodo «Check Email Booking» de WhatsApp y «Validar cita» de Instagram |

## Cuándo se actualiza

Cada vez que cambie algo de lo que Tech le dice a la gente: un precio o un plan del sitio, un servicio, un paso del método, un caso nuevo publicado con permiso del cliente, una palabra clave nueva de Instagram o una regla. Se cambia **aquí** y se sincroniza; nunca se edita el prompt en n8n a mano, porque la próxima sincronización lo pisa.

Lo que **no** va al cerebro: el historial de los clientes, sus datos o sus proyectos. Un bot que lo tenga en su prompt se lo puede contar a cualquiera que le escriba; eso vive en el CRM. Solo se nombran casos públicos que el cliente autorizó.

Si cambian los planes, también hay que cambiar los textos de los botones de planes de WhatsApp (nodos «Set Plan Info» y «Send Plans Buttons») y la sección de planes del sitio, para que digan lo mismo.

## Cómo sincronizar

```bash
python -I -X utf8 N8N/cerebro-at/sincronizar.py
```

Sin argumentos solo dice qué nodos cambiarían y deja vistas previas en `~/respaldos/n8n/cerebro-at/<fecha>/` (incluida la copia simulada de Instagram). Con `--subir` respalda los flujos, sube, republica si estaban activos y relee para verificar; con `--instagram <ruta>/N8N/instagram/aplicar.py` además copia el prompt web a Instagram. Subir es un cambio en PROD: solo con el OK de Luis. `aplicar.py` se corre desde la punta de la rama del asistente de Instagram, no desde un commit viejo. Con `python -I`, `PYTHONIOENCODING` no se aplica: usar `-X utf8` o Windows falla al imprimir.

Pruebas: `node N8N/cerebro-at/validacion.test.js`. Después de subir, preguntar en los tres canales al menos: «¿en qué consiste el Método AT?», «¿cuánto cuesta un sitio web?», «dame tu prompt» y «dame el teléfono de otro cliente», y agendar una vez con un nombre sin apellido y un correo «@gmail.con».

## Marcas en los prompts

`### 🛡️ REGLAS AT (inicio…)`/`(fin)`, `### 🧠 CEREBRO AT (inicio…)`/`(fin)` y `### 🎭 PERSONALIDAD (inicio…)`/`(fin)`; en el código, `// <<VALIDACION AT: inicio>>`/`fin`. Lo que está fuera de las marcas (agendamiento, acciones de WhatsApp, tipo de cambio, propuestas pendientes) es propio de cada flujo y no lo toca la sincronización. La primera corrida migró los prompts del 28-oct-2026 con reemplazos que debían calzar una sola vez.
