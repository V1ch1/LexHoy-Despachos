# LexHoy Despachos - Plugin de WordPress

Plugin para gestionar despachos de abogados con sincronización automática con Algolia.

## 🎉 Versión 1.0.0 - Primera Versión Completa y Estable

Esta es la primera versión completa y estable del plugin LexHoy Despachos. Incluye todas las funcionalidades principales para la gestión integral de despachos de abogados con sincronización automática con Algolia.

### ✨ Características Principales

- **Custom Post Type** completo para despachos
- **Sincronización bidireccional** con Algolia
- **Búsqueda avanzada** con filtros por ubicación y especialidades
- **Plantillas personalizadas** para páginas individuales
- **URLs limpias** sin el prefijo "despacho"
- **Sistema de verificación** de despachos
- **Importación masiva** desde Algolia
- **Limpieza automática** de registros generados
- **Formulario de contacto** para actualización de datos
- **Diseño responsive** y moderno
- **Integración completa** con WordPress

## Instalación

1. Sube el plugin a la carpeta `/wp-content/plugins/lexhoy-despachos/`
2. Activa el plugin desde el panel de administración de WordPress
3. Configura las credenciales de Algolia

## Configuración de Algolia

### Paso 1: Obtener credenciales de Algolia

1. Ve a [Algolia](https://www.algolia.com/) y crea una cuenta
2. Crea una nueva aplicación
3. Ve a la sección "API Keys" en tu dashboard
4. Anota los siguientes datos:
   - **Application ID**
   - **Admin API Key** (para escritura)
   - **Search API Key** (para búsquedas públicas)

### Paso 2: Configurar el plugin

1. En WordPress, ve a **Despachos > Configuración de Algolia**
2. Completa los siguientes campos:
   - **App ID**: Tu Application ID de Algolia
   - **Admin API Key**: Tu Admin API Key de Algolia
   - **Search API Key**: Tu Search API Key de Algolia
   - **Index Name**: Nombre del índice (ej: "despachos")
3. Haz clic en "Guardar configuración"

### Paso 3: Verificar la conexión

1. Después de guardar la configuración, el plugin verificará automáticamente la conexión
2. Si todo está correcto, verás un mensaje de éxito
3. Si hay errores, verifica que las credenciales sean correctas

## Uso

### Crear un nuevo despacho

1. Ve a **Despachos > Añadir nuevo**
2. Completa los campos del formulario
3. El despacho se guardará automáticamente en WordPress y se sincronizará con Algolia

### Campos disponibles

- **Nombre**: Nombre del despacho
- **Localidad**: Ciudad donde se encuentra
- **Provincia**: Provincia/estado
- **Código Postal**: Código postal
- **Dirección**: Dirección completa
- **Teléfono**: Número de teléfono
- **Email**: Dirección de correo electrónico
- **Web**: Sitio web
- **Descripción**: Descripción del despacho
- **Áreas de práctica**: Especialidades legales
- **Horario**: Horarios de atención
- **Redes sociales**: Enlaces a redes sociales
- **Experiencia**: Años de experiencia
- **Tamaño del despacho**: Número de abogados
- **Año de fundación**: Año en que se fundó el despacho
- **Estado de registro**: Activo/Inactivo
- **Estado de verificación**: Pendiente/Verificado/Rechazado

### Sincronización

- Los despachos se sincronizan automáticamente con Algolia al guardar
- Puedes sincronizar manualmente desde la página de configuración
- La sincronización es bidireccional (WordPress ↔ Algolia)

### Importación Masiva

1. Ve a **Despachos > Importación Masiva**
2. Configura las opciones de importación
3. El plugin importará todos los registros de Algolia en bloques de 1000
4. Progreso en tiempo real con estadísticas detalladas

### Limpieza de Registros

1. Ve a **Despachos > Limpieza Algolia**
2. El plugin detectará registros generados automáticamente sin datos
3. Opción para eliminar estos registros de forma segura

## Shortcodes

### Búsqueda de despachos

```
[lexhoy_despachos_search]
```

Este shortcode muestra un formulario de búsqueda que utiliza Algolia para buscar despachos con filtros avanzados.

## Plantillas Personalizadas

### Páginas Individuales

El plugin incluye una plantilla personalizada para las páginas individuales de cada despacho con:

- **Diseño moderno** y responsive
- **Información completa** del despacho
- **Botón de contacto** para actualización de datos
- **Popup modal** para formularios de contacto
- **Integración con Font Awesome** para iconos
- **Tipografía Inter** para mejor legibilidad

## Solución de problemas

### Error: "Configuración incompleta de Algolia"

Si ves este error al crear un despacho:

1. Ve a **Despachos > Configuración de Algolia**
2. Verifica que todos los campos estén completos
3. Asegúrate de que las credenciales sean correctas
4. Haz clic en "Guardar configuración"

### Los despachos no se sincronizan

1. Verifica que la configuración de Algolia sea correcta
2. Revisa los logs de error de WordPress
3. Asegúrate de que el índice de Algolia exista
4. Verifica que las API Keys tengan los permisos correctos

### Error de conexión con Algolia

1. Verifica tu conexión a internet
2. Asegúrate de que las credenciales de Algolia sean válidas
3. Verifica que el Application ID sea correcto
4. Comprueba que las API Keys no hayan expirado

### Problemas con URLs limpias

1. Ve a **Ajustes > Enlaces permanentes**
2. Haz clic en "Guardar cambios" para refrescar las reglas de rewrite
3. Verifica que el servidor soporte mod_rewrite

## Soporte

Para soporte técnico, contacta con el equipo de desarrollo de LexHoy.

## Changelog

### Versión 1.0.0 - Primera Versión Completa y Estable

#### 🎉 Nuevas Funcionalidades

- **Custom Post Type** completo para despachos
- **Sincronización bidireccional** con Algolia
- **Búsqueda avanzada** con filtros por ubicación y especialidades
- **Plantillas personalizadas** para páginas individuales
- **URLs limpias** sin el prefijo "despacho"
- **Sistema de verificación** de despachos
- **Importación masiva** desde Algolia
- **Limpieza automática** de registros generados
- **Formulario de contacto** para actualización de datos
- **Diseño responsive** y moderno
- **Integración completa** con WordPress

#### 🔧 Mejoras Técnicas

- **Sincronización automática** al guardar despachos
- **Control de importación** para evitar bucles infinitos
- **Logging detallado** para debugging
- **Validación de datos** mejorada
- **Manejo de errores** robusto
- **Optimización de rendimiento** para grandes volúmenes de datos

#### 🎨 Diseño y UX

- **Diseño consistente** con colores negro/blanco y acentos rojos
- **Tipografía Inter** importada desde Google Fonts
- **Iconos Font Awesome** para mejor experiencia visual
- **Animaciones suaves** y transiciones
- **Fully responsive** para todos los dispositivos
- **Accesibilidad** mejorada

#### 📱 Responsive Design

- **Mobile-first** approach
- **Breakpoints optimizados** para tablets y móviles
- **Touch-friendly** interfaces
- **Optimización de carga** en dispositivos móviles

#### 🔒 Seguridad

- **Nonces** para todas las operaciones AJAX
- **Validación de permisos** de usuario
- **Sanitización** de datos de entrada
- **Escape** de datos de salida
- **Protección CSRF** implementada
