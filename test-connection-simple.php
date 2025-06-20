<?php
/**
 * Script simple para verificar conexión con Algolia
 */

echo "🔍 DIAGNÓSTICO SIMPLE DE CONEXIÓN CON ALGOLIA\n";
echo "=============================================\n\n";

// Configuración de Algolia (usar las que aparecen en tu diagnóstico anterior)
$app_id = 'GA06AGLT12';
$admin_api_key = '8d1f0f18...'; // Necesitas completar esta clave
$index_name = 'LexHoy_Despachos';

echo "📊 Configuración:\n";
echo "App ID: {$app_id}\n";
echo "Admin API Key: {$admin_api_key}\n";
echo "Index Name: {$index_name}\n\n";

echo "⚠️ IMPORTANTE: Necesitas completar la clave API completa\n";
echo "La clave actual está truncada. Busca la clave completa en:\n";
echo "1. Tu panel de Algolia\n";
echo "2. La configuración de WordPress del plugin\n";
echo "3. O ejecuta el script de conexión que ya tienes\n\n";

echo "💡 Para obtener la clave completa:\n";
echo "1. Ve a tu panel de Algolia\n";
echo "2. Busca la sección 'API Keys'\n";
echo "3. Copia la 'Admin API Key' completa\n";
echo "4. Reemplaza '8d1f0f18...' con la clave completa\n\n";

echo "🔗 URL de prueba que se usará:\n";
echo "https://{$app_id}-dsn.algolia.net/1/indexes/{$index_name}/query\n\n";

echo "📋 Una vez que tengas la clave completa, ejecuta este script nuevamente\n";
echo "y podremos verificar la conexión y obtener el conteo real de registros.\n";
?> 