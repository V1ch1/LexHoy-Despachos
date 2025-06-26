<?php
/**
 * Script para configurar Git en el repositorio
 * Ejecutar una sola vez: php setup-git.php
 */

echo "🔧 Configurando repositorio Git para LexHoy Despachos\n";
echo "==================================================\n\n";

// Verificar si Git está instalado
$git_check = shell_exec('git --version 2>&1');
if (empty($git_check) || strpos($git_check, 'git version') === false) {
    echo "❌ ERROR: Git no está instalado\n";
    echo "💡 Instala Git desde: https://git-scm.com/downloads\n";
    exit(1);
}

echo "✅ Git disponible: " . trim($git_check) . "\n\n";

// Verificar si ya es un repositorio
if (is_dir('.git')) {
    echo "✅ Ya es un repositorio Git\n";
    
    // Verificar remote
    $remotes = shell_exec('git remote -v 2>/dev/null');
    if (strpos($remotes, 'LexHoy-Despachos') !== false) {
        echo "✅ Remote configurado correctamente\n";
        echo "🎯 Repositorio listo para usar\n\n";
        echo "📋 Comandos disponibles:\n";
        echo "   php sync-to-production.php status\n";
        echo "   php sync-to-production.php push\n";
        echo "   php sync-to-production.php full\n";
        exit(0);
    } else {
        echo "⚠️  Remote no configurado correctamente\n";
        echo "🔧 Configurando remote...\n";
        shell_exec('git remote add origin https://github.com/V1ch1/LexHoy-Despachos.git 2>/dev/null');
        shell_exec('git remote set-url origin https://github.com/V1ch1/LexHoy-Despachos.git 2>/dev/null');
        echo "✅ Remote configurado\n";
    }
} else {
    echo "🔧 Inicializando repositorio Git...\n";
    
    // Inicializar Git
    $commands = [
        'git init',
        'git remote add origin https://github.com/V1ch1/LexHoy-Despachos.git',
        'git branch -M main'
    ];
    
    foreach ($commands as $cmd) {
        echo "   Ejecutando: $cmd\n";
        $output = shell_exec("$cmd 2>&1");
        if (!empty($output)) {
            echo "   $output\n";
        }
    }
    
    echo "✅ Repositorio Git inicializado\n";
}

// Crear .gitignore si no existe
if (!file_exists('.gitignore')) {
    $gitignore_content = <<<EOL
# WordPress
wp-config.php
wp-content/uploads/
wp-content/cache/
wp-content/backup-db/
wp-content/advanced-cache.php
wp-content/wp-cache-config.php

# Plugin específicos
*.log
*.tmp
/temp/
/backup/

# Sistema
.DS_Store
Thumbs.db
*.swp
*.swo

# IDE
.vscode/
.idea/
*.sublime-*

# Node modules (si usas npm)
node_modules/
package-lock.json

EOL;
    
    file_put_contents('.gitignore', $gitignore_content);
    echo "✅ Archivo .gitignore creado\n";
}

// Verificar configuración de usuario Git
$user_name = trim(shell_exec('git config user.name 2>/dev/null'));
$user_email = trim(shell_exec('git config user.email 2>/dev/null'));

if (empty($user_name) || empty($user_email)) {
    echo "⚠️  Configuración de usuario Git no encontrada\n";
    echo "💡 Configura tu usuario Git:\n";
    echo "   git config --global user.name \"Tu Nombre\"\n";
    echo "   git config --global user.email \"tu@email.com\"\n\n";
} else {
    echo "✅ Usuario Git configurado: $user_name <$user_email>\n";
}

// Estado final
echo "\n🎯 Configuración completada\n";
echo "=====================================\n";
echo "📋 Próximos pasos:\n";
echo "1. php sync-to-production.php status  - Ver estado actual\n";
echo "2. php sync-to-production.php push    - Subir cambios a GitHub\n";
echo "3. php sync-to-production.php deploy  - Actualizar producción\n";
echo "4. php sync-to-production.php full    - Push + Deploy completo\n\n";

echo "🌐 También puedes usar desde navegador:\n";
echo "   https://tudominio.com/wp-content/plugins/LexHoy-Despachos/sync-to-production.php\n\n";
?> 