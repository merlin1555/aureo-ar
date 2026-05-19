<?php
/**
 * Vista de la pestaña Visor AR
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div class="aureo-ar-visor-container">
    <aside class="visor-sidebar-left">
        <h4>Modelos Disponibles</h4>
        <ul id="glb-model-list">
            <?php
            // Consulta mejorada: Buscamos cualquier archivo que termine en .glb
            $args = array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => -1,
                'post_mime_type' => array('model/gltf-binary', 'application/octet-stream'), // A veces WP los sube como stream
            );
            
            $query_models = new WP_Query( $args );
            $found = false;

            if ( $query_models->have_posts() ) {
                foreach ( $query_models->posts as $model ) {
                    $url = wp_get_attachment_url( $model->ID );
                    // Verificación extra por extensión de archivo
                    if ( pathinfo($url, PATHINFO_EXTENSION) === 'glb' ) {
                        $found = true;
                        echo '<li><button class="select-model-btn" data-url="' . esc_url( $url ) . '">' . esc_html( $model->post_title ) . '</button></li>';
                    }
                }
            }

            if ( !$found ) {
                echo '<li><small>No se detectaron archivos .glb. Intenta subir uno nuevo para refrescar la biblioteca.</small></li>';
            }
            ?>
        </ul>
    </aside>

    <main class="visor-canvas-center" id="aureo-canvas-container">
        <div id="threejs-loader">Selecciona un modelo para renderizar</div>
    </main>

    <aside class="visor-sidebar-right">
        <h4>Controles del Modelo</h4>
        
        <div class="control-group">
            <label>Escala</label>
            <input type="range" id="control-scale" min="0.1" max="3" step="0.1" value="1">
        </div>

        <div class="control-group">
            <label>Altura (Eje Y)</label>
            <input type="range" id="control-pos-y" min="-2" max="2" step="0.05" value="0">
        </div>

        <div class="control-group">
            <label>Posición X</label>
            <input type="range" id="control-pos-x" min="-2" max="2" step="0.05" value="0">
        </div>
        <h4>Rotación Manual</h4>
        <div class="control-group">
            <label>Giro Horizontal (Y)</label>
            <input type="range" id="control-rot-y" min="-3.14" max="3.14" step="0.01" value="0">
        </div>

        <div class="control-group">
            <label>Inclinación Vertical (X)</label>
            <input type="range" id="control-rot-x" min="-3.14" max="3.14" step="0.01" value="0">
        </div>
        <div class="control-group">
            <label>Rotación Automática</label>
            <input type="checkbox" id="control-rotation">
        </div>

        <div class="control-group">
            <label>Fondo</label>
            <input type="color" id="control-bg-color" value="#eeeeee">
        </div>
    </aside>
</div>