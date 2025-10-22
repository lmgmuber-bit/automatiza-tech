# 🟢 Guía de Instalación - WAMPServer

## Configuración de Automatiza Tech con WAMPServer

### 📋 Prerrequisitos

- ✅ **WAMPServer** instalado y funcionando
- ✅ **Icono WAMP en verde** (todos los servicios activos)
- ✅ **Apache y MySQL** ejecutándose
- ✅ **PHP 7.4+** configurado

### 🚀 Instalación Rápida (5 minutos)

#### Opción 1: Script Automático
```bash
# 1. Descargar el proyecto
# 2. Ejecutar desde el directorio del proyecto:
install-wamp.bat

# 3. Seguir las instrucciones en pantalla
# 4. Abrir: http://localhost/automatiza-tech
```

#### Opción 2: Manual
```bash
# 1. Copiar archivos a WAMP
Copiar todo el proyecto → C:/wamp64/www/automatiza-tech/

# 2. Configurar base de datos
Abrir phpMyAdmin → Crear BD: automatiza_tech_local

# 3. Configurar WordPress
Copiar wp-config-local.php → wp-config.php
```

### 🔧 Configuración Detallada

#### 1. Verificar WAMPServer

```bash
# Verificar estado del icono WAMP:
🟢 Verde = Todo OK (continúa)
🟡 Naranja = Servicios parciales (revisar)
🔴 Rojo = Error (solucionar primero)
```

**Si no está en verde**:
- Clic izquierdo en icono WAMP → **Start All Services**
- Esperar a que cambie a verde
- Si persiste el problema, revisar logs

#### 2. Configurar Base de Datos

```sql
-- Opción A: Desde phpMyAdmin (http://localhost/phpmyadmin)
CREATE DATABASE automatiza_tech_local 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Opción B: Desde línea de comandos
mysql -u root -p
CREATE DATABASE automatiza_tech_local;
```

**Importar configuración**:
1. Ir a phpMyAdmin
2. Seleccionar base de datos `automatiza_tech_local`
3. Importar archivo: `sql/database-setup-local.sql`

#### 3. Configurar Archivos

```bash
# Estructura en WAMP:
C:/wamp64/www/automatiza-tech/
├── wp-config.php              # ← Configuración
├── wp-content/
│   └── themes/automatiza-tech/ # ← Tema personalizado
├── sql/
└── install-automatiza-tech.php
```

**Configurar wp-config.php**:
```php
// Copiar wp-config-local.php como wp-config.php
// Ajustar si es necesario:

define( 'DB_NAME', 'automatiza_tech_local' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' ); // O tu contraseña de WAMP
define( 'DB_HOST', 'localhost' );

// Si WAMP usa puerto diferente:
// define( 'DB_HOST', 'localhost:3307' );
```

### 🌐 Acceso al Sitio

#### URLs Importantes
| Servicio | URL | Descripción |
|----------|-----|-------------|
| **Sitio Web** | `http://localhost/automatiza-tech` | Página principal |
| **WordPress Admin** | `http://localhost/automatiza-tech/wp-admin` | Panel administrativo |
| **phpMyAdmin** | `http://localhost/phpmyadmin` | Gestión de BD |
| **WAMP Homepage** | `http://localhost` | Página de inicio de WAMP |

#### Primera Configuración de WordPress

1. **Ir a**: `http://localhost/automatiza-tech`

2. **Configurar WordPress**:
   - Idioma: `Español`
   - Base de datos: `automatiza_tech_local`
   - Usuario BD: `root`
   - Contraseña BD: `(la de tu WAMP o vacía)`
   - Servidor BD: `localhost`

3. **Crear usuario admin**:
   - Usuario: `admin`
   - Contraseña: `admin123!` (cambiar después)
   - Email: `admin@automatizatech.local`

4. **Configurar tema**:
   - Ir a: `http://localhost/automatiza-tech/install-automatiza-tech.php`
   - Ejecutar configuración automática

### 🛠️ Herramientas de WAMP

#### Menú de WAMP (Clic izquierdo)
- **Start All Services** - Iniciar todos los servicios
- **Stop All Services** - Detener todos los servicios
- **Restart All Services** - Reiniciar todos los servicios
- **localhost** - Ir a página principal
- **phpMyAdmin** - Acceso directo
- **www directory** - Abrir carpeta www

#### Configuración Avanzada (Clic derecho)
- **PHP Settings** - Configurar PHP
- **MySQL Settings** - Configurar MySQL
- **Apache Settings** - Configurar Apache
- **Tools** - Herramientas adicionales

### 🔍 Verificación de la Instalación

#### Checklist de Verificación
```bash
✅ WAMPServer en verde
✅ Base de datos creada y configurada
✅ Archivos en C:/wamp64/www/automatiza-tech/
✅ WordPress instalado y funcionando
✅ Tema Automatiza Tech activo
✅ Formulario de contacto funcional
✅ WhatsApp button visible
```

#### URLs de Prueba
```bash
# Página principal
http://localhost/automatiza-tech

# Secciones específicas
http://localhost/automatiza-tech/#beneficios
http://localhost/automatiza-tech/#integraciones
http://localhost/automatiza-tech/#planes
http://localhost/automatiza-tech/#contact

# Admin WordPress
http://localhost/automatiza-tech/wp-admin
```

### 🐛 Solución de Problemas WAMP

#### Error: "Could not connect to database"
```bash
# Verificar:
1. MySQL está ejecutándose (WAMP en verde)
2. Contraseña en wp-config.php es correcta
3. Puerto MySQL (por defecto 3306)

# Solución:
- Ir a WAMP → MySQL → Service → Start/Restart
- Verificar configuración en wp-config.php
```

#### Error: "Forbidden - You don't have permission"
```bash
# Causa: Configuración de Apache restrictiva
# Solución:
1. WAMP → Apache → httpd.conf
2. Buscar: "Require local"
3. Cambiar a: "Require all granted"
4. Reiniciar Apache
```

#### Error: "Port 80 already in use"
```bash
# Causa: Otro servicio usando puerto 80 (IIS, Skype)
# Solución:
1. WAMP → Apache → httpd.conf
2. Cambiar puerto: "Listen 8080"
3. Acceder: http://localhost:8080/automatiza-tech
```

#### WAMP se queda en naranja
```bash
# Posibles causas:
- Skype usando puerto 80/443
- IIS activo
- Otro Apache/MySQL ejecutándose

# Soluciones:
1. Cerrar Skype o cambiar sus puertos
2. Desactivar IIS: Panel Control → Programas → Windows Features
3. Matar procesos: apache.exe, mysqld.exe
```

#### Error de permisos en archivos
```bash
# Verificar permisos de carpeta:
C:/wamp64/www/automatiza-tech/wp-content/uploads/

# Solución:
- Clic derecho → Propiedades → Seguridad
- Dar permisos completos a "Users"
```

### ⚡ Optimización para Desarrollo

#### Configuración PHP recomendada
```ini
# En php.ini (WAMP → PHP → php.ini):
memory_limit = 512M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
display_errors = On
log_errors = On
```

#### Activar mod_rewrite
```bash
# WAMP → Apache → Apache Modules → rewrite_module
# Verificar que esté activado (✅)
```

#### Configurar Virtual Host (Opcional)
```apache
# Para usar automatizatech.local en lugar de localhost/automatiza-tech

# 1. WAMP → Apache → httpd.conf
# Descomentar: Include conf/extra/httpd-vhosts.conf

# 2. Editar: C:/wamp64/bin/apache/apache2.4.x/conf/extra/httpd-vhosts.conf
<VirtualHost *:80>
    DocumentRoot "C:/wamp64/www/automatiza-tech"
    ServerName automatizatech.local
    <Directory "C:/wamp64/www/automatiza-tech">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

# 3. Editar: C:/Windows/System32/drivers/etc/hosts
127.0.0.1 automatizatech.local

# 4. Reiniciar Apache
# 5. Acceder: http://automatizatech.local
```

### 📊 Monitoreo y Logs

#### Logs de WAMP
```bash
# Apache Error Log
C:/wamp64/logs/apache_error.log

# MySQL Error Log  
C:/wamp64/logs/mysql.log

# PHP Error Log
C:/wamp64/logs/php_error.log
```

#### Logs de WordPress
```bash
# Debug Log
C:/wamp64/www/automatiza-tech/wp-content/debug.log

# Mail Debug (desarrollo)
C:/wamp64/www/automatiza-tech/wp-content/mail-debug.log
```

### 🚀 Siguientes Pasos

1. **Personalizar el sitio**:
   - Ir a: **Apariencia → Personalizar**
   - Configurar WhatsApp, colores, textos

2. **Probar funcionalidades**:
   - Formulario de contacto
   - Botón WhatsApp
   - Responsive design

3. **Desarrollo**:
   - Editar archivos en: `C:/wamp64/www/automatiza-tech/`
   - Ver cambios en tiempo real
   - Usar herramientas de debug incluidas

4. **Preparar para producción**:
   - Desactivar debug
   - Optimizar base de datos
   - Crear backup

---

**¡Listo para desarrollar con WAMP! 🎉**

*¿Necesitas ayuda? Revisa la sección de troubleshooting o contacta al equipo de desarrollo.*