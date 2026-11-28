# Agente Tech Web: casilla anti-robot (Cloudflare Turnstile) en las citas

Desde el 16-nov-2026 (rama `claude/turnstile-web`). Ninguna cita de la web se guarda sin pasar la casilla «No soy un robot».

```
Formulario (portada y landings, ventana de agenda del pie de página, chat y ventana del chat)
  └─ manda turnstileToken junto con action: 'saveLead'
Agente Tech Web (PROD) 3A5Y1NPBxpTjhHdE
  Switch Action (saveLead) → Verificar Turnstile (siteverify de Cloudflare) → ¿Turnstile OK?
        sí → Pedido original → Limpiar sesión → Check Availability → Respond Lead
        no → Rechazo Turnstile (HTTP 403; los formularios muestran «Vuelve a marcar la casilla»)
```

- **Clave del sitio** (pública): en `wp-content/themes/automatiza-tech/inc/at-turnstile.php`; fuera de automatizatech.cl se usa la de prueba de Cloudflare. Se puede cambiar sin código con la opción `at_turnstile_sitekey`.
- **Clave secreta**: solo en la credencial de n8n «AT Turnstile Secret (web)» (Custom Auth, limitada a `challenges.cloudflare.com`). La crea `aplicar.py` desde el archivo de claves, etiqueta `TURNSTILE AT SECRET`. Nunca en WordPress ni en el repo.
- **Widget de Cloudflare**: cuenta de Luis, modo gestionado, dominios `automatizatech.cl` y `www.automatizatech.cl`.
- `aplicar.py --ver`: vista previa. `aplicar.py`: crea la credencial si falta, respalda en `~/respaldos/n8n/web-turnstile/`, sube, reactiva y verifica la versión activa.
- `probar.py`: una cita sin token y otra con token inventado reciben 403. La prueba positiva es una agenda real en la web.
- **Orden de despliegue:** primero el tema (los formularios empiezan a mandar el token; n8n todavía no lo exige), purgar la caché de LiteSpeed, y recién después `aplicar.py`. Al revés, las citas se rechazan hasta que el navegador baje el JS nuevo.
- **Vuelta atrás:** restaurar el respaldo del flujo (o reconectar `Switch Action` salida 1 a `Limpiar sesión`). El tema puede quedarse: sin la revisión en n8n, el token simplemente se ignora.
- Guía para hacerlo con un cliente: `Docs/METODO_AT/GUIA-CAPI-Y-TURNSTILE-CLIENTES.md`.
