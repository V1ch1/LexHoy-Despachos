# 🖼️ Guía para Añadir Fotos de Perfil a Despachos

Esta guía te explica cómo añadir una propiedad de **foto de perfil** a todos los despachos en Algolia y asignar una imagen predeterminada a todos los que no tengan una.

## 📋 ¿Qué se ha implementado?

### ✅ Cambios Realizados

1. **Nuevo campo en la base de datos**: `foto_perfil`
2. **Métodos de actualización en Algolia**:
   - `partial_update_object()` - Para actualizar un registro individual
   - `batch_partial_update()` - Para actualizar múltiples registros en lotes
3. **Campo en el formulario de WordPress**: Campo para introducir la URL de la foto
4. **Sincronización automática**: El campo se incluye en todas las sincronizaciones
5. **Script de actualización masiva**: Para añadir fotos a todos los despachos existentes

### 🆕 Nueva Estructura en Algolia

Ahora cada despacho en Algolia tendrá esta nueva propiedad:

```json
{
  "objectID": "9c722f03913e0_dashboard_generated_id",
  "nombre": "De Vega y Asociados Cb",
  "localidad": "Huelva",
  "provincia": "Huelva",
  "areas_practica": ["Administrativo"],
  "foto_perfil": "https://tu-dominio.com/path/to/foto-perfil.jpg",
  "...": "... otros campos existentes ..."
}
```

## 🚀 Cómo Usar el Script de Actualización Masiva

### Paso 1: Preparar tu Foto Predeterminada

1. **Sube tu foto** al servidor (recomendado: JPG o PNG, máximo 500x500px)
2. **Obtén la URL pública** de la imagen
3. **Ejemplo de URL**: `https://tu-dominio.com/wp-content/uploads/2024/01/lawyer-default.jpg`

### Paso 2: Configurar el Script

1. **Abre el archivo**: `add-profile-photo-to-despachos.php`
2. **Busca la línea 29**:
   ```php
   $this->default_photo_url = 'https://example.com/path/to/default-lawyer-profile.jpg';
   ```
3. **Cámbiala por tu URL**:
   ```php
   $this->default_photo_url = 'https://tu-dominio.com/wp-content/uploads/2024/01/lawyer-default.jpg';
   ```

### Paso 3: Ejecutar el Script

#### Opción A: Desde el Navegador

1. Ve a: `https://tu-dominio.com/wp-content/plugins/LexHoy-Despachos/add-profile-photo-to-despachos.php`
2. Sigue las instrucciones en pantalla
3. Haz clic en "EJECUTAR ACTUALIZACIÓN MASIVA"

#### Opción B: Añadir como Submenú en WordPress

Añade este código a `includes/class-lexhoy-despachos-cpt.php` en la función `register_import_submenu()`:

```php
add_submenu_page(
    'edit.php?post_type=despacho',
    'Añadir Fotos de Perfil',
    'Añadir Fotos',
    'manage_options',
    'lexhoy-add-photos',
    array($this, 'render_add_photos_page')
);
```

Y añade esta función al mismo archivo:

```php
public function render_add_photos_page() {
    include_once(LEXHOY_DESPACHOS_PLUGIN_DIR . 'add-profile-photo-to-despachos.php');
}
```

## 📊 ¿Qué Hace el Script?

### 🔍 Análisis Inicial

- Obtiene **todos** los registros de Algolia
- Identifica cuáles **ya tienen** foto de perfil
- Identifica cuáles **NO tienen** foto de perfil
- Muestra estadísticas detalladas

### 🚀 Proceso de Actualización

- Procesa en **lotes de 100 registros** (eficiente y seguro)
- **No sobrescribe** fotos existentes
- Muestra **progreso en tiempo real**
- Incluye **manejo de errores** detallado

### 📈 Resultados

- **Estadísticas finales**: registros actualizados, errores, tasa de éxito
- **Log detallado** del proceso
- **Confirmación** de que los cambios están en Algolia

## 🔧 Gestión Manual de Fotos

### Para Nuevos Despachos

1. Ve a **Despachos > Añadir Nuevo** en WordPress
2. Completa los datos del despacho
3. En el campo **"Foto de Perfil"**, introduce la URL de la imagen
4. Al guardar, se sincronizará automáticamente con Algolia

### Para Despachos Existentes

1. Ve a **Despachos > Todos los Despachos**
2. Edita el despacho que desees
3. Añade o modifica el campo **"Foto de Perfil"**
4. Guarda los cambios

## 🛠️ Uso en el Frontend

### Para Mostrar la Foto en Búsquedas

```javascript
// En tu código JavaScript de búsqueda
const fotoPerfil =
  hit.foto_perfil || "https://tu-dominio.com/default-lawyer.jpg";

const despachoHTML = `
  <div class="despacho-card">
    <img src="${fotoPerfil}" alt="Foto de ${hit.nombre}" class="despacho-foto">
    <h3>${hit.nombre}</h3>
    <p>${hit.localidad}, ${hit.provincia}</p>
  </div>
`;
```

### Para Mostrar en Páginas Individuales

```php
// En la plantilla single-despacho.php
$foto_perfil = get_post_meta(get_the_ID(), '_despacho_foto_perfil', true);
$foto_default = 'https://tu-dominio.com/default-lawyer.jpg';

echo '<img src="' . ($foto_perfil ?: $foto_default) . '" alt="Foto de perfil" class="despacho-foto-perfil">';
```

## 🎨 CSS Recomendado

```css
.despacho-foto,
.despacho-foto-perfil {
  width: 150px;
  height: 150px;
  object-fit: cover;
  border-radius: 50%;
  border: 3px solid #0073aa;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.despacho-card {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px;
  border: 1px solid #ddd;
  border-radius: 8px;
  margin-bottom: 15px;
}
```

## 🔍 Verificación de Resultados

### En Algolia Dashboard

1. Ve a tu dashboard de Algolia
2. Busca tu índice de despachos
3. Revisa algunos registros para ver el campo `foto_perfil`

### En WordPress

1. Edita cualquier despacho
2. Verifica que el campo "Foto de Perfil" esté visible
3. Prueba guardando una nueva URL

### En Frontend

1. Realiza una búsqueda de despachos
2. Verifica que las fotos se muestren correctamente
3. Comprueba que la imagen predeterminada aparezca para despachos sin foto específica

## ⚠️ Importantes Consideraciones

### Rendimiento

- ✅ **Lotes optimizados**: El script procesa en lotes de 100 para no sobrecargar la API
- ✅ **Pausas inteligentes**: 2 segundos entre lotes para respetar límites de la API
- ✅ **Timeout ampliado**: 60 segundos para operaciones batch

### Seguridad

- ✅ **No sobrescribe**: Solo añade fotos a despachos que no tengan
- ✅ **Validación de URLs**: Los campos se validan como URLs
- ✅ **Permisos**: Solo administradores pueden ejecutar el script

### Mantenimiento

- 🔄 **Sincronización automática**: Todos los cambios en WordPress se sincronizan automáticamente
- 🔄 **Importaciones**: El campo se incluye en importaciones desde Algolia
- 🔄 **Respaldo**: No modifica datos existentes, solo añade el nuevo campo

## 🆘 Solución de Problemas

### Error: "Configuración de Algolia incompleta"

- Verifica que tengas configurados: App ID, Admin API Key, Search API Key e Index Name
- Ve a **Despachos > Configuración de Algolia** para completar la configuración

### Error: "No se puede acceder a la imagen"

- Verifica que la URL de la foto sea accesible públicamente
- Comprueba que la imagen no esté protegida por contraseña
- Asegúrate de que el servidor permite acceso a la imagen

### La foto no aparece en las búsquedas

- Espera unos minutos para que Algolia procese los cambios
- Verifica que tu código de búsqueda esté usando el campo `foto_perfil`
- Comprueba la consola del navegador para errores de CORS o 404

### Proceso interrumpido

- El script es seguro de reanudar: no duplicará fotos ya añadidas
- Simplemente ejecuta el script de nuevo y continuará donde se quedó

---

## 📞 Soporte

Si tienes problemas o preguntas sobre la implementación, revisa:

1. **Logs de WordPress**: `wp-content/lexhoy-debug.log`
2. **Logs del servidor**: Para errores de PHP
3. **Consola del navegador**: Para errores de JavaScript
4. **Dashboard de Algolia**: Para verificar que los datos llegaron correctamente
