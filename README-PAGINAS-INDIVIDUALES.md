# Páginas Individuales de Despachos - LexHoy

## Descripción

Se ha implementado un nuevo sistema de páginas individuales para cada despacho que mantiene la consistencia visual con el buscador principal. Cada despacho ahora tiene su propia página personalizada que muestra toda la información disponible de manera organizada y atractiva.

## Características

### 🎨 Diseño Consistente

- **Misma fuente**: Inter, Helvetica, Arial, sans-serif
- **Mismos colores**: Rojo #e10000, grises y blancos
- **Mismo estilo**: Bordes, sombras y efectos hover
- **Responsive**: Adaptado para móviles, tablets y desktop

### 📱 Layout Responsive

- **Desktop**: Layout de 2 columnas (principal + sidebar)
- **Tablet**: Layout adaptativo con columnas apiladas
- **Móvil**: Layout de una columna optimizado para pantallas pequeñas

### 🔧 Funcionalidades

#### Cabecera del Despacho

- **Título prominente** con el nombre del despacho
- **Badge de verificación** (si está verificado)
- **Botón de regreso** al buscador
- **Ubicación** del despacho

#### Información de Contacto

- **Teléfono** con enlace clickeable
- **Email** con enlace mailto
- **Sitio web** con enlace externo
- **Dirección** completa

#### Información Detallada

- **Descripción** del despacho
- **Experiencia** profesional
- **Especialidades** en tags
- **Áreas de práctica** (taxonomía)
- **Horario** de atención por día
- **Redes sociales** con iconos
- **Información adicional** (tamaño, año fundación, etc.)

## Archivos Creados/Modificados

### Nuevos Archivos

1. **`assets/css/single-despacho.css`** - Estilos específicos para páginas individuales
2. **`templates/single-despacho.php`** - Plantilla personalizada (actualizada)
3. **`demo-single-despacho.html`** - Demostración del diseño

### Archivos Modificados

1. **`includes/class-lexhoy-despachos-cpt.php`** - Agregado método para cargar plantilla personalizada

## Cómo Funciona

### 1. Carga de Plantilla

```php
// En el constructor de LexhoyDespachosCPT
add_filter('single_template', array($this, 'load_single_despacho_template'));

// Método que carga la plantilla personalizada
public function load_single_despacho_template($template) {
    if (is_singular('despacho')) {
        return LEXHOY_DESPACHOS_PLUGIN_DIR . 'templates/single-despacho.php';
    }
    return $template;
}
```

### 2. URLs Limpias

- **URL anterior**: `lexhoy.com/despacho/nombre-despacho`
- **URL nueva**: `lexhoy.com/nombre-despacho`
- **Redirección automática** de URLs antiguas a nuevas

### 3. Metadatos Mostrados

La plantilla muestra todos los metadatos disponibles:

```php
// Información básica
$nombre = get_post_meta($post_id, '_despacho_nombre', true);
$localidad = get_post_meta($post_id, '_despacho_localidad', true);
$provincia = get_post_meta($post_id, '_despacho_provincia', true);
$telefono = get_post_meta($post_id, '_despacho_telefono', true);
$email = get_post_meta($post_id, '_despacho_email', true);
$web = get_post_meta($post_id, '_despacho_web', true);

// Información adicional
$especialidades = get_post_meta($post_id, '_despacho_especialidades', true);
$horario = get_post_meta($post_id, '_despacho_horario', true);
$redes_sociales = get_post_meta($post_id, '_despacho_redes_sociales', true);
$experiencia = get_post_meta($post_id, '_despacho_experiencia', true);
$tamano_despacho = get_post_meta($post_id, '_despacho_tamaño', true);
$ano_fundacion = get_post_meta($post_id, '_despacho_año_fundacion', true);
$estado_registro = get_post_meta($post_id, '_despacho_estado_registro', true);

// Áreas de práctica (taxonomía)
$areas_practica = wp_get_post_terms($post_id, 'area_practica', array('fields' => 'names'));
```

## Estructura CSS

### Clases Principales

- `.lexhoy-despacho-single` - Contenedor principal
- `.despacho-header` - Cabecera con título y badges
- `.despacho-content` - Contenido principal con layout de columnas
- `.despacho-section` - Secciones de información
- `.contact-grid` - Grid de información de contacto
- `.info-grid` - Grid de información adicional

### Componentes

- `.contact-item` - Elementos de contacto con iconos
- `.area-tag` - Tags para áreas de práctica
- `.specialty-tag` - Tags para especialidades
- `.schedule-item` - Elementos del horario
- `.social-link` - Enlaces de redes sociales
- `.registration-status` - Estados de registro

## Responsive Design

### Breakpoints

- **Desktop**: > 768px - Layout de 2 columnas
- **Tablet**: 768px - Layout adaptativo
- **Móvil**: < 480px - Layout de una columna

### Adaptaciones Móviles

- Botones y badges reposicionados
- Grids convertidos a columnas únicas
- Tamaños de fuente ajustados
- Espaciado optimizado

## Integración con el Buscador

### Enlaces "Ver más"

Los enlaces "Ver más" en el buscador ahora apuntan a las páginas individuales:

```php
// En class-lexhoy-despachos-shortcode.php
'link' => get_permalink($post_id)
```

### Navegación

- **Botón de regreso** en cada página individual
- **URLs limpias** sin el prefijo `/despacho/`
- **Redirecciones automáticas** para compatibilidad

## Personalización

### Colores

Los colores principales se pueden modificar en `single-despacho.css`:

```css
/* Color principal */
color: #e10000;

/* Colores de estado */
.registration-status.active {
  background: #2ecc71;
}
.registration-status.inactive {
  background: #e74c3c;
}
```

### Layout

El layout se puede ajustar modificando las clases CSS:

```css
/* Ancho del contenedor */
.lexhoy-despacho-single > * {
  width: 80% !important;
}

/* Layout de columnas */
.despacho-main {
  flex: 2;
}
.despacho-sidebar {
  flex: 1;
}
```

## Compatibilidad

### WordPress

- Compatible con WordPress 5.0+
- Funciona con cualquier tema
- No interfiere con otros plugins

### Navegadores

- Chrome/Edge (últimas versiones)
- Firefox (últimas versiones)
- Safari (últimas versiones)
- Internet Explorer 11+

## Próximas Mejoras

1. **SEO optimizado** con meta tags específicos
2. **Schema.org markup** para mejor indexación
3. **Compartir en redes sociales** con Open Graph
4. **Mapa interactivo** con la ubicación
5. **Formulario de contacto** integrado
6. **Galería de imágenes** del despacho
7. **Testimonios de clientes**
8. **Blog/noticias** del despacho

## Soporte

Para cualquier pregunta o problema con las páginas individuales, contactar al equipo de desarrollo de LexHoy.
