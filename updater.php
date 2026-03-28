<?php
/**
 * 🕵️ Sincronizador de Alta Seguridad - Proyecto Maltir
 * Autodetector de Dominio, Ruta y Doble Validación Estricta
 * Licencia Mit
 * Autor: Alfonso Orozco Aguilar
 * Fecha : 25/03/2026
 */

$current_path = getcwd();
$dominio = $_SERVER['HTTP_HOST'];
$confirmado_check = isset($_POST['confirmar_check']);
$confirmado_texto = isset($_POST['confirmar_texto']) ? $_POST['confirmar_texto'] : '';

echo "<h2 style='font-family:sans-serif;'>🌐 Dominio Detectado: <span style='color:red;'>" . htmlspecialchars($dominio) . "</span></h2>";
echo "<h3 style='font-family:sans-serif; color:gray;'>📁 Ruta Local: " . htmlspecialchars($current_path) . "</h3>";

// Aviso de Configuración Previa
echo "<div style='background:#e7f3ff; border-left:5px solid #2196F3; padding:15px; margin-bottom:20px; font-family:sans-serif;'>";
echo "<b>ℹ️ AVISO DE PROTOCOLO:</b> Se asume que el administrador ya configuró el <code>remote origin</code> ";
echo "utilizando un <b>Personal Access Token (PAT)</b> de GitHub o Gitea para este dominio.";
echo "</div>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $confirmado_check && $confirmado_texto === "SI") {
    
    echo "<pre style='background:#000; color:#0f0; padding:15px; border-radius:5px; font-family:monospace;'>";
    echo "🚨 VALIDACIÓN ACEPTADA EN [" . htmlspecialchars($dominio) . "]\n";
    echo "Iniciando sincronización...\n\n";

    // A. Configurar como Safe Directory
    exec("git config --global --add safe.directory " . escapeshellarg($current_path));

    // B. Fetch
    echo "📡 [FETCH] Conectando con el origen remoto...\n";
    exec("cd " . escapeshellarg($current_path) . " && git fetch --all 2>&1", $out_fetch);
    echo implode("\n", $out_fetch) . "\n";

    // C. Reset Hard
    echo "🔄 [RESET] Forzando espejo de 'origin/main'...\n";
    exec("cd " . escapeshellarg($current_path) . " && git reset --hard origin/main 2>&1", $out_reset);
    echo implode("\n", $out_reset) . "\n";

    // D. Clean (Solo con SI en mayúsculas)
    $borrar_basura = isset($_POST['borrar_basura']) && $_POST['borrar_basura'] === "SI";
    if ($borrar_basura) {
        echo "🧹 [CLEAN] Ejecutando limpieza física de archivos huérfanos...\n";
        exec("cd " . escapeshellarg($current_path) . " && git clean -fd 2>&1", $out_clean);
        echo implode("\n", $out_clean) . "\n";
    }

    echo "\n✅ OPERACIÓN TERMINADA. El servidor está sincronizado.";
    echo "</pre>";
    echo '<br><a href="?">⬅️ Volver al Panel</a>';

} else {
    // Formulario de Seguridad
    echo '<form method="POST" style="background:#fff3cd; padding:25px; border:2px solid #ffeeba; border-radius:10px; font-family:sans-serif; max-width:650px;">';
    echo '<h3 style="margin-top:0; color:#856404;">⚠️ Confirmación de Sincronización Forzada</h3>';
    echo '<p>Estás a punto de resetear el contenido en <b>' . htmlspecialchars($dominio) . '</b>.</p>';
    
    echo '<label style="display:block; margin-bottom:15px; cursor:pointer;">';
    echo '<input type="checkbox" name="confirmar_check"> 1. Acepto que los archivos locales serán sobreescritos.';
    echo '</label>';

    echo '<div style="margin-bottom:20px;">';
    echo '2. Escribe <b>SI</b> para autorizar el Reset Hard:<br>';
    echo '<input type="text" name="confirmar_texto" placeholder="Escribe SI" style="padding:8px; width:100px; margin-top:5px; text-align:center;">';
    echo '</div>';

    echo '<div style="background:#f8d7da; padding:15px; border-radius:5px;">';
    echo '<b>🔥 OPCIONAL: ¿Borrar archivos que no están en Git?</b><br>';
    echo 'Escribe <b>SI</b> para confirmar <code>git clean</code>: ';
    echo '<input type="text" name="borrar_basura" value="NO" placeholder="SI o NO" style="padding:5px; width:80px; text-align:center;">';
    echo '</div>';

    echo '<br><button type="submit" style="padding:15px; cursor:pointer; background:#dc3545; color:#fff; border:none; border-radius:5px; font-weight:bold; width:100%;">🚀 SINCRONIZAR AHORA</button>';
    echo '</form>';
}
?>
