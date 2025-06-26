# 🚀 Sistema de Deploy Automático - LexHoy Despachos

## ✅ ¿Qué tienes configurado?

Tu plugin tiene un **sistema de deploy directo** que te permite actualizar producción en **2 pasos** sin descargas manuales.

---

## 🎯 **Tu Flujo de Trabajo**

### **Paso 1**: Editar en Local

- Haz tus cambios en Local by Flywheel
- Guarda los archivos modificados

### **Paso 2**: Deploy a GitHub + Producción

```bash
# Desde la carpeta del plugin
deploy.bat full
```

### **Paso 3**: Actualizar Servidor

Visita esta URL en tu navegador:

```
https://lexhoy.com/wp-content/plugins/LexHoy-Despachos-main/download-from-github.php?key=lexhoy2024
```

¡**Listo!** Tu plugin está actualizado en producción.

---

## 📋 **Comandos Disponibles**

```bash
# Ver estado actual
php sync-to-production.php status

# Solo push a GitHub
php sync-to-production.php push

# Deploy completo (recomendado)
deploy.bat full

# Deploy con mensaje personalizado
deploy-custom.bat "Descripción del cambio"
```

---

## 🛠️ **Archivos del Sistema**

- `sync-to-production.php` - Script principal de deploy
- `deploy.bat` - Acceso directo para deploy completo
- `deploy-custom.bat` - Deploy con mensaje personalizado
- `download-from-github.php` - Actualizador en el servidor

---

## 🔄 **Versionado Automático**

El sistema incrementa automáticamente la versión:

- `1.0.24` → `1.0.25` → `1.0.26` → etc.
- Se actualiza en `lexhoy-despachos.php` automáticamente

---

## 💾 **Backups Automáticos**

Cada actualización crea un backup:

- `lexhoy-despachos.php.backup.20251226114208`
- Se guardan en la misma carpeta del plugin

---

## ⚡ **Archivos que se Actualizan**

El sistema actualiza automáticamente:

- ✅ `lexhoy-despachos.php` (archivo principal)
- ✅ `assets/css/search.css`
- ✅ `assets/js/search.js`
- ✅ `includes/class-lexhoy-despachos-cpt.php`
- ✅ `includes/class-lexhoy-despachos-shortcode.php`
- ✅ `includes/class-lexhoy-algolia-client.php`
- ✅ `templates/single-despacho.php`

---

## 🎯 **URLs Importantes**

### GitHub Repository

```
https://github.com/V1ch1/LexHoy-Despachos
```

### Actualizador del Servidor

```
https://lexhoy.com/wp-content/plugins/LexHoy-Despachos-main/download-from-github.php?key=lexhoy2024
```

### Ruta del Plugin en Servidor

```
/public_html/wp-content/plugins/LexHoy-Despachos-main/
```

---

## 🔐 **Seguridad**

- El actualizador requiere la clave: `lexhoy2024`
- Sin la clave muestra: "❌ Acceso denegado"
- Solo actualiza archivos específicos (no todo el repositorio)

---

## 🚨 **Solución de Problemas**

### Si el comando `deploy.bat` no funciona:

```bash
php sync-to-production.php full
```

### Si GitHub no está configurado:

```bash
git config --global user.name "V1ch1"
git config --global user.email "blancocasal@hotmail.com"
```

### Si el actualizador da 404:

1. Verifica que el archivo `download-from-github.php` esté en el servidor
2. Ruta: `/public_html/wp-content/plugins/LexHoy-Despachos-main/`
3. Permisos: `644`

---

## 📊 **Ejemplo de Salida Exitosa**

Cuando todo funciona correctamente verás:

```
🚀 Actualizador LexHoy Despachos
11:42:07 - 🔄 Iniciando actualización automática...
11:42:07 - 📥 Descargando desde GitHub...
11:42:08 - ✅ ZIP descargado: 128.0 KB
11:42:08 - 📦 Extrayendo archivos...
11:42:08 - 💾 Backup creado
11:42:08 - ✅ Actualizado: lexhoy-despachos.php
11:42:08 - ✅ Actualizado: assets/css/search.css
11:42:08 - ✅ Actualizado: assets/js/search.js
11:42:08 - 🎯 Actualización completada: 7 archivos
✅ ACTUALIZACIÓN EXITOSA
```

---

## 🎉 **¡Disfruta tu deploy automático!**

Ya no más:

- ❌ Descargas manuales de GitHub
- ❌ Subidas tediosas por FTP
- ❌ Gestión manual de versiones

Solo:

- ✅ `deploy.bat full`
- ✅ Visitar URL del servidor
- ✅ ¡Listo!

---

_Creado el 26 de diciembre de 2024_
