# 🚀 Inicio Rápido - Desarrollo Local

## Instalación Express (5 minutos)

### 1. Prerrequisitos

#### Para WAMPServer (Si ya lo tienes)
- ✅ WAMPServer instalado y funcionando ([Descargar](http://www.wampserver.com/))
- ✅ Icono WAMP en **verde** (todos los servicios activos)
- ✅ Navegador web moderno
- ✅ Editor de código (VS Code recomendado)

#### Para XAMPP (Alternativa)
- ✅ XAMPP instalado ([Descargar](https://www.apachefriends.org/))
- ✅ Apache y MySQL iniciados
- ✅ Navegador web moderno
- ✅ Editor de código (VS Code recomendado)

### 2. Instalación Automática

#### 🟢 Con WAMPServer
```bash
# 1. Clonar o descargar el proyecto
git clone https://github.com/automatizatech/wordpress-site.git
cd wordpress-site

# 2. Ejecutar instalación específica para WAMP
install-wamp.bat

# 3. Abrir en navegador
http://localhost/automatiza-tech
```

#### 🟡 Con XAMPP

#### 🟡 Con XAMPP
```bash
# 1. Clonar o descargar el proyecto
git clone https://github.com/automatizatech/wordpress-site.git
cd wordpress-site

# 2. Ejecutar instalación automática
install-local.bat

# 3. Abrir en navegador
http://localhost/automatiza-tech
```

### 3. Configuración Inicial de WordPress

1. **Primera instalación de WordPress**:
   - Idioma: `Español`
   - Título: `Automatiza Tech - Local`
   - Usuario: `admin`
   - Contraseña: `admin123!` (cambiar en producción)
   - Email: `admin@automatizatech.local`

2. **Configurar tema automáticamente**:
   ```
   http://localhost/automatiza-tech/install-automatiza-tech.php
   ```

## 🔧 URLs Importantes

### WAMPServer
| Servicio | URL | Descripción |
|----------|-----|-------------|
| **Sitio Web** | `http://localhost/automatiza-tech` | Sitio principal |
| **Admin WordPress** | `http://localhost/automatiza-tech/wp-admin` | Panel administrativo |
| **phpMyAdmin** | `http://localhost/phpmyadmin` | Gestión de base de datos |
| **WAMP Homepage** | `http://localhost` | Página principal de WAMP |

### XAMPP  
| Servicio | URL | Descripción |
|----------|-----|-------------|
| **Sitio Web** | `http://localhost/automatiza-tech` | Sitio principal |
| **Admin WordPress** | `http://localhost/automatiza-tech/wp-admin` | Panel administrativo |
| **phpMyAdmin** | `http://localhost/phpmyadmin` | Gestión de base de datos |
| **XAMPP Control** | Panel XAMPP | Control de servicios |

## 📁 Estructura de Desarrollo

```
C:/xampp/htdocs/automatiza-tech/
├── wp-config.php              # ← Configuración local
├── wp-content/
│   ├── themes/
│   │   └── automatiza-tech/   # ← Tema personalizado
│   ├── uploads/               # ← Archivos subidos
│   ├── debug.log             # ← Log de errores
│   └── mail-debug.log        # ← Emails interceptados
├── sql/
│   └── database-setup-local.sql
└── install-automatiza-tech.php
```

## 🛠️ Comandos Útiles

### Base de Datos
```sql
-- Conectar a MySQL
mysql -u root -p

-- Usar base de datos
USE automatiza_tech_local;

-- Ver tablas creadas
SHOW TABLES;

-- Ver leads de contacto
SELECT * FROM at_local_contact_leads;
```

### WordPress
```php
// Activar debug (en wp-config.php)
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Ver logs de error
tail -f wp-content/debug.log

// Limpiar cache
// Ir a: Admin Bar > Dev Tools > Limpiar Cache
```

## 🎯 Testing Rápido

### 1. Probar formulario de contacto
1. Ir a `http://localhost/automatiza-tech/#contact`
2. Llenar y enviar formulario
3. Verificar en `wp-content/mail-debug.log`
4. Revisar en phpMyAdmin: tabla `at_local_contact_leads`

### 2. Probar WhatsApp
1. Hacer clic en botón flotante de WhatsApp
2. Verificar que redirija correctamente
3. En móvil: debe abrir WhatsApp
4. En desktop: debe abrir WhatsApp Web

### 3. Probar responsive
1. Abrir DevTools (F12)
2. Cambiar a modo móvil
3. Probar diferentes tamaños de pantalla
4. Verificar que todo se vea correctamente

## 🐛 Debug Tools

### Admin Bar de Desarrollo
Cuando estés logueado como admin, verás:
- 🔧 **Dev Tools** en la barra superior
- **Ver Queries SQL**: Muestra todas las consultas de la página
- **Limpiar Cache**: Limpia cache y transients
- **PHP Info**: Información del servidor

### Logs Disponibles
- `wp-content/debug.log` - Errores de WordPress
- `wp-content/php-errors.log` - Errores de PHP  
- `wp-content/mail-debug.log` - Emails interceptados

### URLs de Debug
```
# Ver queries SQL
http://localhost/automatiza-tech/?debug_queries=1

# Limpiar cache
http://localhost/automatiza-tech/?clear_cache=1

# PHP Info (admin)
http://localhost/automatiza-tech/wp-admin/tools.php?page=dev-phpinfo
```

## 📝 Datos de Prueba

### Leads de Contacto
```sql
INSERT INTO at_local_contact_leads (name, email, company, phone, message) VALUES
('Test Usuario', 'test@ejemplo.com', 'Test Corp', '+52123456789', 'Mensaje de prueba');
```

### Configuración del Tema
Ir a: **Apariencia > Personalizar > Opciones Automatiza Tech**
- WhatsApp: `+52123456789`
- Email: `info@automatizatech.local`
- Colores: Usar paleta por defecto

## 🚀 Workflow de Desarrollo

### 1. Desarrollo de Funcionalidades
```bash
# 1. Editar archivos del tema
code wp-content/themes/automatiza-tech/

# 2. Ver cambios en tiempo real
http://localhost/automatiza-tech

# 3. Revisar errores
tail -f wp-content/debug.log
```

### 2. Testing
```bash
# 1. Probar en diferentes navegadores
# 2. Probar formularios
# 3. Verificar responsive design
# 4. Revisar velocidad de carga
```

### 3. Deploy a Producción
```bash
# 1. Desactivar debug en wp-config.php
# 2. Activar optimizaciones
# 3. Subir archivos vía FTP/cPanel
# 4. Actualizar base de datos
```

## ⚡ Tips de Productividad

### Editor de Código
```json
// VS Code settings.json recomendados
{
    "php.suggest.basic": false,
    "php.validate.enable": true,
    "emmet.includeLanguages": {
        "php": "html"
    }
}
```

### Extensiones Útiles de VS Code
- PHP Intelephense
- WordPress Snippets
- Auto Rename Tag
- Bracket Pair Colorizer
- GitLens

### Shortcuts Útiles
- `Ctrl+Shift+R` - Refrescar sin cache
- `F12` - Abrir DevTools
- `Ctrl+U` - Ver código fuente
- `Ctrl+Shift+I` - Inspeccionar elemento

## 📞 Soporte

¿Problemas con la instalación local?

1. **Revisar logs de error** en `wp-content/debug.log`
2. **Verificar servicios XAMPP** (Apache y MySQL)
3. **Consultar sección de troubleshooting** en README.md
4. **Contactar**: dev@automatizatech.local

---

**¡Feliz desarrollo! 🎉**

*Automatiza Tech - Conectamos tus ventas, web y CRM*