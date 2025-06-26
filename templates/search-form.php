<?php
/**
 * Template para el formulario de búsqueda de despachos
 * Este archivo estaba faltando y causaba el error fatal
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="lexhoy-search-form-container">
    <form id="lexhoy-search-form" class="lexhoy-search-form" method="get">
        <div class="search-field-group">
            <label for="lexhoy-search-input">Buscar despachos:</label>
            <input 
                type="text" 
                id="lexhoy-search-input" 
                name="s" 
                placeholder="Escribe tu búsqueda..." 
                value="<?php echo esc_attr(get_search_query()); ?>"
                autocomplete="off"
            >
        </div>
        
        <div class="search-filters">
            <!-- Filtros adicionales se pueden agregar aquí -->
        </div>
        
        <div class="search-submit-group">
            <button type="submit" class="lexhoy-search-submit">
                🔍 Buscar
            </button>
        </div>
    </form>
    
    <div id="lexhoy-search-results" class="lexhoy-search-results">
        <!-- Los resultados se cargan aquí vía AJAX -->
    </div>
</div> 