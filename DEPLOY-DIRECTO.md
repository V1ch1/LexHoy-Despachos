# 🚀 Sistema de Deploy Directo - LexHoy Despachos

Este sistema te permite trabajar directamente desde tu repositorio local y sincronizar con producción sin usar Local ni el sistema de actualizaciones de WordPress.

## 🎯 ¿Qué resuelve?

- ❌ **Antes**: Local → GitHub → Descargar ZIP → Subir a producción
- ✅ **Ahora**: Editar código → `deploy.bat full` → ¡Listo en producción!

## 🔧 Configuración inicial

### 1. Primera vez (solo ejecutar una vez)

```bash
# En Windows
deploy.bat setup

# En terminal/PowerShell
php setup-git.php
```

### 2. Verificar que todo funciona

```bash
deploy.bat status
```

## 📋 Comandos disponibles

### Windows (recomendado)

```bash
deploy.bat setup   # Configurar Git (solo primera vez)
deploy.bat status  # Ver estado actual
deploy.bat push    # Subir cambios a GitHub
deploy.bat deploy  # Actualizar producción desde GitHub
deploy.bat full    # Push + Deploy completo (recomendado)
```

### Terminal/PowerShell

```bash
php sync-to-production.php status
php sync-to-production.php push
php sync-to-production.php deploy
php sync-to-production.php full
```

### Navegador web

Visita: `https://tudominio.com/wp-content/plugins/LexHoy-Despachos/sync-to-production.php`

## 🔄 Flujo de trabajo diario

1. **Editar archivos** en tu editor favorito
2. **Deploy completo**: `deploy.bat full`
3. **¡Listo!** Los cambios están en GitHub y en producción

## 📁 Archivos importantes

- `sync-to-production.php` - Script principal de deploy
- `setup-git.php` - Configuración inicial de Git
- `deploy.bat` - Comandos fáciles para Windows
- `.gitignore` - Archivos a ignorar en Git

## 🔧 ¿Qué hace cada comando?

### `setup` (solo primera vez)

- Inicializa repositorio Git
- Configura remote de GitHub
- Crea .gitignore
- Verifica configuración

### `status`

- Muestra versión actual
- Estado del repositorio Git
- Archivos modificados
- Información del último commit

### `push`

- Actualiza automáticamente la versión del plugin
- Hace commit de todos los cambios
- Sube los cambios a GitHub

### `deploy`

- Descarga la versión más reciente desde GitHub
- Actualiza archivos en producción
- Limpia caché

### `full` (recomendado)

- Ejecuta `push` + `deploy`
- Una sola acción para todo el proceso

## 🚨 Solución de problemas

### Error: "Git no está instalado"

- Instalar Git desde: https://git-scm.com/downloads
- Reiniciar terminal después de instalar

### Error: "PHP no está disponible"

- Instalar PHP desde: https://www.php.net/downloads
- O usar XAMPP/WAMP que ya incluye PHP

### Error: "No es un repositorio Git"

- Ejecutar: `deploy.bat setup`

### Error al hacer push

- Verificar credenciales de GitHub
- Configurar usuario Git:
  ```bash
  git config --global user.name "Tu Nombre"
  git config --global user.email "tu@email.com"
  ```

## 🎉 Ventajas de este sistema

1. **Rápido**: Un solo comando para todo
2. **Confiable**: No depende del sistema de WordPress
3. **Visible**: Logs detallados de cada paso
4. **Flexible**: Funciona desde terminal o navegador
5. **Automático**: Actualiza versiones automáticamente
6. **Limpio**: Mantiene el historial en Git

## 📝 Notas importantes

- El script actualiza automáticamente la versión en `lexhoy-despachos.php`
- Se crean commits automáticos con la fecha y versión
- Los archivos temporales se limpian automáticamente
- Compatible con Windows, Mac y Linux
- No interfiere con el sistema existente de WordPress

## 🔗 Enlaces útiles

- [Repositorio en GitHub](https://github.com/V1ch1/LexHoy-Despachos)
- [Git para Windows](https://git-scm.com/downloads)
- [PHP Downloads](https://www.php.net/downloads)
