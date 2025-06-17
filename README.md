# LexHoy Despachos - Plugin de WordPress

Plugin para gestionar despachos de abogados con sincronización automática con Algolia.

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

### Sincronización

- Los despachos se sincronizan automáticamente con Algolia al guardar
- Puedes sincronizar manualmente desde la página de configuración
- La sincronización es bidireccional (WordPress ↔ Algolia)

## Shortcodes

### Búsqueda de despachos

```
[lexhoy_despachos_search]
```

Este shortcode muestra un formulario de búsqueda que utiliza Algolia para buscar despachos.

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

## Soporte

Para soporte técnico, contacta con el equipo de desarrollo de LexHoy.

## Changelog

### Versión 1.0.0

- Lanzamiento inicial
- Sincronización con Algolia
- Gestión de despachos
- Búsqueda avanzada
- Áreas de práctica
