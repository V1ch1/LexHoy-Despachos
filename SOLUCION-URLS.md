# Solución para URLs de Despachos

## Problema

Las URLs de los despachos siguen mostrando `/despacho/` en lugar de `/abogado/` o directamente `/nombre-despacho/`.

## Soluciones

### Opción 1: Limpiar reglas de reescritura desde WordPress Admin

1. **Ve a Ajustes > Enlaces permanentes**
2. **Cambia temporalmente** a "Nombre de la entrada"
3. **Haz clic en "Guardar cambios"**
4. **Cambia de vuelta** a tu configuración actual (ej: "Nombre de la entrada")
5. **Haz clic en "Guardar cambios"** nuevamente

### Opción 2: Usar el botón del plugin

1. Ve a **Despachos** en el admin de WordPress
2. Busca la notificación azul que dice "URLs de Despachos"
3. Haz clic en **"Limpiar Reglas de Reescritura"**
4. Confirma la acción

### Opción 3: Desactivar y reactivar el plugin

1. Ve a **Plugins**
2. **Desactiva** "LexHoy Despachos"
3. **Actívalo** nuevamente
4. Ve a **Ajustes > Enlaces permanentes** y haz clic en "Guardar cambios"

### Opción 4: Limpiar desde la base de datos (Avanzado)

Si las opciones anteriores no funcionan, puedes limpiar manualmente:

1. **Accede a tu base de datos** (phpMyAdmin, etc.)
2. **Ve a la tabla `wp_options`**
3. **Busca y elimina** la fila con `option_name = 'rewrite_rules'`
4. **Ve a WordPress Admin > Ajustes > Enlaces permanentes**
5. **Haz clic en "Guardar cambios"**

### Opción 5: Usar WP-CLI (Si tienes acceso)

```bash
wp rewrite flush
```

## Configuración actual del plugin

El plugin está configurado para usar:

- **Slug**: `abogado`
- **URLs esperadas**: `http://lexhoy.local/abogado/jose-blanco/`

## Verificación

Después de aplicar cualquiera de las soluciones:

1. **Crea un nuevo despacho** o edita uno existente
2. **Verifica la URL** - debería ser `http://lexhoy.local/abogado/nombre-despacho/`
3. **Prueba acceder** a la URL directamente

## Si el problema persiste

1. **Verifica que no haya conflictos** con otros plugins
2. **Desactiva temporalmente** otros plugins de SEO o URLs
3. **Cambia a un tema por defecto** temporalmente
4. **Revisa los logs de error** de WordPress

## Logs de depuración

El plugin registra información en los logs de WordPress. Puedes verificar:

- Si las reglas se están limpiando correctamente
- Si hay errores en la configuración

## Contacto

Si ninguna de estas soluciones funciona, contacta con el equipo de desarrollo.
