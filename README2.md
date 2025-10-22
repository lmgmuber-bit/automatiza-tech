# automatiza-tech
# Automatiza Tech - Sitio Web WordPress

## 🚀 Descripción

Sitio web profesional para Automatiza Tech, una empresa especializada en automatización de procesos de negocio mediante chatbots inteligentes. El sitio está construido con WordPress, optimizado para SEO, responsive y con tiempo de carga menor a 3 segundos.

## ✨ Características

- **Responsive Design**: Compatible con todos los dispositivos
- **Optimizado para SEO**: Meta tags, Schema.org, Open Graph
- **Carga Rápida**: Optimizaciones para tiempo de carga < 3 segundos
- **Compatible con Hostinger**: Configurado específicamente para Hostinger
- **Bootstrap 5**: Framework CSS moderno y responsive
- **Tema Personalizado**: Diseñado específicamente para Automatiza Tech
- **Integración WhatsApp**: Botón flotante y enlaces directos
- **Formulario de Contacto**: Sistema AJAX con validación
- **Cache Optimizado**: Sistema de cache personalizado
- **Base de Datos MySQL**: Configuración optimizada

## 🎨 Paleta de Colores

- **Azul Eléctrico**: `#1e40af` (tecnología y confianza)
- **Verde Lima**: `#84cc16` (innovación y energía)
- **Blanco**: `#ffffff` (claridad y simplicidad)

## 📂 Estructura del Proyecto

```
wordpress/
├── wp-content/
│   └── themes/
│       └── automatiza-tech/
│           ├── style.css
│           ├── functions.php
│           ├── index.php
│           ├── header.php
│           ├── footer.php
│           ├── assets/
│           │   ├── css/
│           │   ├── js/
│           │   └── images/
│           ├── inc/
│           │   ├── customizer.php
│           │   └── template-functions.php
│           └── template-parts/
├── wp-config.php
├── .htaccess
├── sql/
│   └── database-setup.sql
└── install-automatiza-tech.php
```

## 🛠️ Instalación

### 🏠 Instalación Local (XAMPP/WAMP/LARAGON)

#### 🟢 Para WAMPServer (Recomendado si ya tienes WAMP)

**Instalación Automática**:
```bash
# 1. Asegúrate de que WAMPServer esté iniciado (icono verde)
# 2. Ejecutar script específico para WAMP
install-wamp.bat

# 3. Abrir en navegador
http://localhost/automatiza-tech
```

**Configuración manual si prefieres**:
1. **Verificar WAMPServer**:
   - Icono en verde (todos los servicios activos)
   - Apache y MySQL ejecutándose

2. **Configurar base de datos**:
   - Ir a `http://localhost/phpmyadmin`
   - Crear base de datos: `automatiza_tech_local`
   - Importar: `sql/database-setup-local.sql`

3. **Copiar archivos**:
   ```bash
   # Copiar proyecto a:
   C:/wamp64/www/automatiza-tech/
   # o
   C:/wamp/www/automatiza-tech/
   ```

4. **Configurar WordPress**:
   - Copiar `wp-config-local.php` como `wp-config.php`
   - Ajustar contraseña de MySQL si es necesaria
   - Ir a `http://localhost/automatiza-tech`

#### 🟡 Para XAMPP

**Instalación Automática**:

#### 🟡 Para XAMPP

**Instalación Automática**:

1. **Descargar e instalar XAMPP** desde [apachefriends.org](https://www.apachefriends.org/)

2. **Clonar o descargar** este proyecto en tu máquina local

3. **Ejecutar script de instalación**:
   ```bash
   # En Windows
   install-local.bat
   
   # El script automáticamente:
   # - Configura la base de datos
   # - Copia archivos a htdocs
   # - Configura WordPress
   ```

4. **Abrir en navegador**: `http://localhost/automatiza-tech`

#### 🔵 Instalación Manual (Cualquier servidor)

1. **Iniciar XAMPP** y activar Apache + MySQL

2. **Configurar base de datos**:
   - Ir a `http://localhost/phpmyadmin`
   - Crear base de datos: `automatiza_tech_local`
   - Importar: `sql/database-setup-local.sql`

3. **Copiar archivos**:
   ```bash
   # Copiar proyecto a:
   C:/xampp/htdocs/automatiza-tech/
   ```

4. **Configurar WordPress**:
   - Copiar `wp-config-local.php` como `wp-config.php`
   - Ir a `http://localhost/automatiza-tech`
   - Seguir instalación de WordPress

5. **Configurar tema**:
   - Ejecutar: `http://localhost/automatiza-tech/install-automatiza-tech.php`

### 🌐 Instalación en Producción (Hostinger)

### Requisitos Previos

- PHP 7.4 o superior
- MySQL 5.7 o superior
- WordPress 6.0 o superior
- Servidor web (Apache/Nginx)
- Extensiones PHP: mysqli, gd, curl, mbstring

### Paso 1: Configurar Base de Datos

1. Accede a tu panel de control de Hostinger
2. Ve a "Bases de Datos MySQL"
3. Crea una nueva base de datos llamada `automatiza_tech_db`
4. Ejecuta el script SQL ubicado en `sql/database-setup.sql`

```sql
-- Ejecutar en phpMyAdmin o similar
source sql/database-setup.sql;
```

### Paso 2: Configurar WordPress

1. Sube todos los archivos al directorio raíz de tu hosting
2. Edita `wp-config.php` con tus datos de base de datos:

```php
define( 'DB_NAME', 'automatiza_tech_db' );
define( 'DB_USER', 'tu_usuario_db' );
define( 'DB_PASSWORD', 'tu_password_db' );
define( 'DB_HOST', 'localhost' );
```

3. Genera nuevas claves de seguridad en https://api.wordpress.org/secret-key/1.1/salt/

### Paso 3: Instalar WordPress

1. Ve a tu dominio en el navegador
2. Sigue el asistente de instalación de WordPress
3. Crea tu usuario administrador

### Paso 4: Configuración Automática

Ejecuta el script de configuración automática:

```
http://tudominio.com/install-automatiza-tech.php
```

Este script configurará:
- Páginas esenciales
- Menús de navegación
- Opciones del tema
- Contenido de ejemplo
- Configuración SEO

### Paso 5: Personalización

1. Ve a **Apariencia > Personalizar**
2. Configura las opciones del tema:
   - Información de contacto
   - Redes sociales
   - Colores (si deseas cambiarlos)
   - Logo personalizado

## ⚙️ Configuración de WhatsApp

Para configurar el número de WhatsApp:

1. Ve a **Apariencia > Personalizar > Opciones Automatiza Tech**
2. Ingresa tu número de WhatsApp en formato internacional: `+52123456789`
3. Guarda los cambios

## 🔧 Optimizaciones Implementadas

### Rendimiento
- Minificación de CSS y JS
- Compresión GZIP
- Cache de navegador
- Lazy loading de imágenes
- Optimización de base de datos
- CDN ready

### SEO
- Meta tags optimizados
- Schema.org structured data
- Open Graph tags
- Sitemap XML
- URLs amigables
- Optimización de imágenes

### Seguridad
- Headers de seguridad
- Protección contra XSS
- Validación de formularios
- Sanitización de datos
- Límites de subida de archivos

## 📱 Secciones del Sitio

### Página Principal
- **Hero Section**: Título, subtítulo, CTA
- **Beneficios**: 6 características principales
- **Integraciones**: WhatsApp, Instagram, Web, CRM
- **Casos de Uso**: Por industria (6 sectores)
- **Planes y Precios**: 3 planes diferentes
- **Formulario de Contacto**: Con validación AJAX

### Páginas Adicionales
- Servicios
- Sobre Nosotros
- Contacto
- Política de Privacidad
- Términos de Servicio

## 🎯 Funcionalidades Principales

### Formulario de Contacto
- Validación en tiempo real
- Envío por AJAX
- Integración con email
- Redirección a WhatsApp opcional

### Botón WhatsApp Flotante
- Siempre visible
- Mensaje predefinido
- Responsive

### Sistema de Cache
- Cache de páginas
- Cache de objetos
- Limpieza automática

### Analytics
- Google Analytics ready
- Facebook Pixel ready
- Eventos personalizados

## 🚀 Optimización para Hostinger

El tema está específicamente optimizado para Hostinger:

- Compatible con PHP 7.4+
- Optimizado para shared hosting
- Cache configurado para LiteSpeed
- Configuración de memoria optimizada
- Compatibilidad con CDN de Hostinger

## 📊 Métricas de Rendimiento

Objetivos de rendimiento:
- **Tiempo de carga**: < 3 segundos
- **PageSpeed Score**: > 90
- **GTmetrix Grade**: A
- **Core Web Vitals**: Todos en verde

## 🔄 Mantenimiento

### Limpieza Automática
El sistema incluye tareas automatizadas de limpieza:
- Logs de errores (conserva 30 días)
- Analytics (conserva 90 días)
- Cache expirado
- Optimización de tablas

### Actualizaciones
- WordPress core: Actualizaciones menores automáticas
- Tema: Versionado manual
- Plugins: Revisar mensualmente

## 🐛 Solución de Problemas

### 🏠 Problemas en Desarrollo Local

#### XAMPP no inicia Apache/MySQL
1. Verificar que no haya otros servidores ejecutándose (IIS, Skype)
2. Cambiar puertos en XAMPP (Apache: 8080, MySQL: 3307)
3. Ejecutar XAMPP como administrador

#### Error "Cannot connect to database"
1. Verificar que MySQL esté iniciado en XAMPP
2. Verificar datos en `wp-config.php`:
   ```php
   define( 'DB_NAME', 'automatiza_tech_local' );
   define( 'DB_USER', 'root' );
   define( 'DB_PASSWORD', '' );
   define( 'DB_HOST', 'localhost' );
   ```

#### Página en blanco o errores PHP
1. Activar debug en `wp-config.php`:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'WP_DEBUG_DISPLAY', true );
   ```
2. Revisar `wp-content/debug.log`
3. Verificar versión de PHP (mínimo 7.4)

#### Formulario de contacto no funciona
1. Verificar configuración SMTP en `wp-config.php`
2. Instalar plugin SMTP local como MailHog
3. Revisar logs de error de WordPress

#### Archivos/imágenes no cargan
1. Verificar permisos de carpeta `wp-content/uploads`
2. Verificar URL base en `wp-config.php`:
   ```php
   define( 'WP_HOME', 'http://localhost/automatiza-tech' );
   define( 'WP_SITEURL', 'http://localhost/automatiza-tech' );
   ```

#### WhatsApp links no funcionan en local
- Los enlaces de WhatsApp funcionarán solo en móviles o con WhatsApp Web instalado
- Para testing local, verificar que los enlaces se generen correctamente

### 🌐 Problemas en Producción

#### Sitio lento
1. Verificar configuración de cache
2. Optimizar imágenes
3. Revisar plugins activos
4. Contactar a Hostinger

### Formulario no funciona
1. Verificar configuración de email en WordPress
2. Revisar logs de error
3. Verificar configuración AJAX

### WhatsApp no redirige
1. Verificar formato del número (incluir código de país)
2. Revisar configuración en Personalizar

## 📞 Soporte

Para soporte técnico o consultas:
- **Email**: dev@automatizatech.com
- **WhatsApp**: +1 (234) 567-890
- **Documentación**: Revisa este README

## 📄 Licencia

Este tema es propietario de Automatiza Tech. Todos los derechos reservados.

## 🔗 Enlaces Útiles

- [WordPress Codex](https://codex.wordpress.org/)
- [Bootstrap 5 Documentation](https://getbootstrap.com/docs/5.3/)
- [Hostinger WordPress Guide](https://www.hostinger.com/tutorials/wordpress)
- [Google PageSpeed Insights](https://pagespeed.web.dev/)

---

**Desarrollado con ❤️ para Automatiza Tech**

*Última actualización: Octubre 2025*