# 📧 Cómo Evitar la Carpeta de Promociones de Gmail

## ✅ Cambios ya Implementados

### 1. **Subject Line Mejorado**
```php
// ❌ ANTES (trigger de Promociones)
$subject = '¡Descubre cómo Automatiza Tech puede transformar tu negocio! 🚀';

// ✅ AHORA (más conversacional)
$subject = 'Re: Tu consulta en Automatiza Tech - ' . $contact->name;
```

**Por qué funciona:**
- ✅ Personalizado con el nombre del contacto
- ✅ Usa "Re:" para parecer una respuesta
- ✅ Sin emojis en el asunto
- ✅ Sin palabras trigger como "Descubre", "Oferta", "Gratis"

### 2. **Headers Optimizados**
```php
$headers = array(
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . $contact->name . ' en Automatiza Tech <' . get_option('admin_email') . '>',
    'Reply-To: Automatiza Tech <contacto@automatizatech.cl>',
    'Bcc: automatizatech.bots@gmail.com',
    'X-Priority: 3',                    // Prioridad normal (no urgente)
    'X-Mailer: WordPress/' . get_bloginfo('version'),
    'Importance: Normal',               // No marcar como importante
    'List-Unsubscribe: <mailto:contacto@automatizatech.cl?subject=unsubscribe>',
    'Precedence: bulk'                  // Identifica como email masivo legítimo
);
```

**Por qué funciona:**
- ✅ **From personalizado**: Incluye el nombre del contacto
- ✅ **Prioridad Normal**: No parece spam urgente
- ✅ **List-Unsubscribe**: Muestra profesionalismo y cumplimiento
- ✅ **Precedence: bulk**: Identifica que es email masivo legítimo

### 3. **Diseño del Email Simplificado**
```php
// ❌ EVITAR (trigger de Promociones)
- Muchos emojis en el contenido (🚀🎉💰💪⚡)
- Palabras en MAYÚSCULAS
- Gradientes llamativos en todo el email
- Múltiples CTAs grandes y coloridos
- Lenguaje muy promocional ("¡Oferta!", "¡Compra ya!", "¡Gratis!")

// ✅ HACER (parece email personal)
- Header simple con logo
- Saludo conversacional: "Hola {nombre},"
- Texto corrido como si fuera un email personal
- Un solo CTA discreto
- Lenguaje profesional y útil
```

## 📊 Factores que Analiza Gmail

### 🔴 Señales de SPAM/Promoción:
1. **Subject Line:**
   - Emojis excesivos 🚀💰🎁🎉
   - Palabras trigger: "Gratis", "Oferta", "Descuento", "Compra", "Limitado"
   - Todo en MAYÚSCULAS
   - Múltiples signos de exclamación!!!

2. **Contenido del Email:**
   - Ratio imagen/texto muy alto (>40% imágenes)
   - Muchos enlaces externos
   - Palabras financieras: "precio", "oferta", "descuento"
   - CTAs llamativos y múltiples
   - Diseño muy "marketero"

3. **Headers y Configuración:**
   - No tiene List-Unsubscribe
   - From genérico o sospechoso
   - Sin autenticación SPF/DKIM/DMARC
   - IP de servidor con mala reputación

4. **Comportamiento del Usuario:**
   - Si otros usuarios marcan como spam
   - Si nadie abre tus correos
   - Si nadie responde a tus correos
   - Si borran sin leer

### 🟢 Señales de Email Legítimo:
1. **Personalización:**
   - Nombre del destinatario en Subject y saludo
   - From personalizado
   - Contenido relevante a una interacción previa

2. **Interacción Previa:**
   - Usuario llenó un formulario
   - Usuario está en tu base de datos
   - Es una respuesta a una consulta

3. **Profesionalismo:**
   - Opción de desuscribirse
   - Firma con datos de contacto reales
   - Dominio verificado (SPF/DKIM/DMARC)

4. **Engagement Positivo:**
   - Usuarios abren tus correos
   - Usuarios responden
   - Usuarios hacen clic en enlaces
   - Nadie marca como spam

## 🎯 Mejores Prácticas Adicionales

### 1. **Autenticación de Dominio** (CRÍTICO)
```bash
# Verificar en Hostinger Panel > Email > Autenticación
✅ SPF: Configurado
✅ DKIM: Configurado
✅ DMARC: Configurado
```

### 2. **Warm-up del Dominio**
```
Día 1-3:   Envía 10-20 correos/día
Día 4-7:   Envía 30-50 correos/día
Día 8-14:  Envía 80-100 correos/día
Día 15+:   Envía hasta 200 correos/día
```

### 3. **Limpieza de Lista**
```php
// ✅ Ya implementado en el sistema
- Solo enviar a contactos con status='new'
- Excluir emails invalidos
- Cambiar status a 'contacted' después de enviar
- No enviar al mismo contacto múltiples veces
```

### 4. **Timing de Envíos**
```
✅ Mejor horario: Martes-Jueves, 10:00-16:00
⚠️ Evitar: Lunes temprano, Viernes tarde, Fines de semana
✅ Pausa entre envíos: 0.5 segundos (ya implementado)
```

### 5. **Monitorear Métricas**
```
📊 Open Rate > 20% = Bueno
📊 Click Rate > 2% = Bueno
📊 Bounce Rate < 5% = Bueno
📊 Spam Rate < 0.1% = Excelente
```

## 🛠️ Herramientas de Verificación

### Mail-Tester (https://www.mail-tester.com)
```bash
1. Envía un email de prueba a: test-xxxxx@mail-tester.com
2. Revisa el score (debe ser > 8/10)
3. Corrige los problemas que detecte
```

### GlockApps (https://glockapps.com)
```bash
- Test de inbox placement
- Verifica si llega a Promociones, Spam o Inbox
- Analiza autenticación SPF/DKIM/DMARC
```

## 📝 Checklist de Envío

Antes de enviar correos masivos, verifica:

- [ ] SPF/DKIM/DMARC configurados en Hostinger
- [ ] Subject sin emojis y personalizado
- [ ] From incluye nombre del contacto
- [ ] Headers con List-Unsubscribe
- [ ] Contenido 70% texto, 30% imágenes
- [ ] Un solo CTA claro
- [ ] Lenguaje conversacional (no promocional)
- [ ] Opción de desuscribirse visible
- [ ] Test con mail-tester.com > 8/10
- [ ] Warm-up del dominio completado
- [ ] Lista limpia (sin emails inválidos)

## 🚀 Próximos Pasos Recomendados

### 1. **Implementar Segmentación**
```php
// Enviar diferentes emails según:
- Tipo de industria del contacto
- Tamaño de empresa
- Tiempo desde que contactó
```

### 2. **A/B Testing**
```php
// Probar diferentes:
- Subject lines
- Horarios de envío
- Contenido del email
```

### 3. **Automatización Avanzada**
```php
// Implementar:
- Drip campaigns (secuencia de emails)
- Follow-ups automáticos
- Re-engagement campaigns
```

### 4. **Tracking y Analytics**
```php
// Medir:
- Tasa de apertura
- Tasa de clicks
- Conversiones
- Desuscripciones
```

## 📞 Soporte

Si los correos siguen llegando a Promociones después de estos cambios:

1. **Espera 2-3 días** para que Gmail aprenda el nuevo patrón
2. **Pide a los destinatarios** que muevan tu correo a Principal
3. **Pide que respondan** tu correo (aumenta engagement)
4. **Verifica autenticación** con mail-tester.com
5. **Contacta a Hostinger** si hay problemas con SPF/DKIM

---

**Última actualización:** Noviembre 11, 2025
**Sistema:** Automatiza Tech Email Marketing v2.0
