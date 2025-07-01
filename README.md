# LexHoy Despachos - Plugin de WordPress

Plugin para gestionar despachos de abogados con sincronización automática con Algolia.

## ✨ Características Principales

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

## Instalación

1. Sube el plugin a la carpeta `/wp-content/plugins/lexhoy-despachos/`
2. Activa el plugin desde el panel de administración de WordPress
3. Configura las credenciales de Algolia

## Configuración de Algolia

### Obtener credenciales de Algolia

1. Ve a [Algolia](https://www.algolia.com/) y crea una cuenta
2. Crea una nueva aplicación
3. Ve a la sección "API Keys" en tu dashboard
4. Anota los siguientes datos:
   - **Application ID**
   - **Admin API Key** (para escritura)
   - **Search API Key** (para búsquedas públicas)

### Configurar el plugin

1. En WordPress, ve a **Despachos > Configuración de Algolia**
2. Completa los siguientes campos:
   - **App ID**: Tu Application ID de Algolia
   - **Admin API Key**: Tu Admin API Key de Algolia
   - **Search API Key**: Tu Search API Key de Algolia
   - **Index Name**: Nombre del índice (ej: "despachos")
3. Haz clic en "Guardar configuración"

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
- **Estado de verificación**: Pendiente verificación/Verificado/Rechazado

## Shortcodes

### Búsqueda de despachos

```
[lexhoy_despachos_search]
```

Este shortcode muestra un formulario de búsqueda que utiliza Algolia para buscar despachos con filtros avanzados.

## Soporte

Para soporte técnico, contacta con el equipo de desarrollo de LexHoy.
