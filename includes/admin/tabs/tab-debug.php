<?php
/**
 * Vista de la pestaña Debug - Aureo AR
 * Herramientas de diagnóstico para el desarrollo.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Obtenemos información del sistema para diagnóstico
$upload_dir = wp_upload_dir();
$allowed_mimes = get_allowed_mime_types();
?>

<div class="aureo-ar-debug-container">
    <h3>Panel de Diagnóstico</h3>
    <p>Utiliza esta información para verificar que el entorno de WordPress está configurado correctamente para objetos 3D.</p>

    <div class="debug-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
        
        <div class="debug-card" style="background: #fff; padding: 15px; border: 1px solid #ccd0d4;">
            <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">MIME Types Permitidos</h4>
            <ul style="list-style: none; padding: 0;">
                <li>
                    <strong>.glb:</strong> 
                    <?php echo isset($allowed_mimes['glb']) ? '<span style="color:green;">✅ ' . $allowed_mimes['glb'] . '</span>' : '<span style="color:red;">❌ No permitido</span>'; ?>
                </li>
                <li>
                    <strong>.gltf:</strong> 
                    <?php echo isset($allowed_mimes['gltf']) ? '<span style="color:green;">✅ ' . $allowed_mimes['gltf'] . '</span>' : '<span style="color:red;">❌ No permitido</span>'; ?>
                </li>
            </ul>
        </div>

        <div class="debug-card" style="background: #fff; padding: 15px; border: 1px solid #ccd0d4;">
            <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">Rutas y Directorios</h4>
            <p><strong>Path del Plugin:</strong><br><code style="font-size: 10px;"><?php echo AUREO_AR_PATH; ?></code></p>
            <p><strong>Carpeta de Cargas:</strong><br><code style="font-size: 10px;"><?php echo $upload_dir['basedir']; ?></code></p>
        </div>

        <div class="debug-card" style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; grid-column: span 2;">
            <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">Scripts en Cola (Enqueued)</h4>
            <p>Si ves estos nombres, los scripts base están registrados en esta página:</p>
            <code>three-js</code>, <code>three-gltf-loader</code>, <code>aureo-ar-visor-js</code>, <code>aureo-ar-general-js</code>
        </div>
    </div>

    <div style="margin-top: 20px; padding: 15px; background: #fffbe5; border-left: 4px solid #ffb900;">
        <strong>Nota de Desarrollo:</strong> Si los archivos <code>.glb</code> no aparecen en el Visor pero aquí salen como "Permitidos", intenta subir un archivo nuevo. WordPress no actualiza automáticamente el tipo de archivo de los elementos que ya estaban en la biblioteca antes de instalar el plugin.
    </div>
</div>