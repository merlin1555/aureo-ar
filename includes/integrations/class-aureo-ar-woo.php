<?php
/**
 * Aureo AR — Integración WooCommerce (Admin)
 * Gestiona el tab "Aureo AR" en el editor de productos del admin de WP.
 *
 * Meta keys:
 *   _aureo_ar_type           → 'none' | 'accessory' | 'object'
 *   _aureo_ar_accessory_type → 'earring' | (futuro: headband, clip, watch, necklace)
 *   _aureo_ar_model_url      → URL del archivo GLB/GLTF
 *   _aureo_ar_usdz_url       → URL del archivo USDZ (para iOS Quick Look — opcional)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Aureo_AR_Woo {

    public function __construct() {
        add_filter( 'woocommerce_product_data_tabs',   array( $this, 'add_ar_tab' ), 99 );
        add_action( 'woocommerce_product_data_panels', array( $this, 'ar_tab_content' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_ar_fields' ) );
    }

    /* ---------------------------------------------------------------
     * 1. Añadir pestaña en el editor de producto
     * ------------------------------------------------------------- */
    public function add_ar_tab( $tabs ) {
        $tabs['aureo_ar'] = array(
            'label'    => __( 'Aureo AR', 'aureo-ar' ),
            'target'   => 'aureo_ar_product_data',
            'class'    => array( 'show_if_simple', 'show_if_variable' ),
            'priority' => 21,
        );
        return $tabs;
    }

    /* ---------------------------------------------------------------
     * 2. Contenido del panel
     * ------------------------------------------------------------- */
    public function ar_tab_content() {
        global $post;
        $ar_type      = get_post_meta( $post->ID, '_aureo_ar_type',           true ) ?: 'none';
        $acc_type     = get_post_meta( $post->ID, '_aureo_ar_accessory_type',  true ) ?: 'earring';
        $glb_url      = get_post_meta( $post->ID, '_aureo_ar_model_url',       true );
        $usdz_url     = get_post_meta( $post->ID, '_aureo_ar_usdz_url',        true );
        $color_mode    = get_post_meta( $post->ID, '_aureo_ar_color_mode',       true ) ?: 'model_texture';
        $colors_raw    = get_post_meta( $post->ID, '_aureo_ar_colors',          true ) ?: '[]';
        $colors        = json_decode( $colors_raw, true );
        if ( ! is_array( $colors ) || empty( $colors ) ) {
            $colors = array( '#c0c0c0' );
        }
        $mat_slots_raw = get_post_meta( $post->ID, '_aureo_ar_material_slots',  true ) ?: '{}';
        $mat_slots     = json_decode( $mat_slots_raw, true );
        if ( ! is_array( $mat_slots ) ) { $mat_slots = array(); }
        ?>
        <div id="aureo_ar_product_data" class="panel woocommerce_options_panel">
            <div class="options_group aureo-ar-admin-panel">

                <!-- ── Tipo de AR ── -->
                <p class="form-field">
                    <label for="aureo_ar_type"><?php esc_html_e( 'Tipo de AR', 'aureo-ar' ); ?></label>
                    <select id="aureo_ar_type" name="aureo_ar_type" class="aureo-ar-select">
                        <option value="none"      <?php selected( $ar_type, 'none' ); ?>><?php esc_html_e( 'Sin AR', 'aureo-ar' ); ?></option>
                        <option value="accessory" <?php selected( $ar_type, 'accessory' ); ?>><?php esc_html_e( 'Accesorio (cámara frontal)', 'aureo-ar' ); ?></option>
                        <option value="object"    <?php selected( $ar_type, 'object' ); ?>><?php esc_html_e( 'Objeto / Mueble (cámara trasera)', 'aureo-ar' ); ?></option>
                    </select>
                    <span class="description"><?php esc_html_e( 'Define cómo se activa la experiencia AR para este producto.', 'aureo-ar' ); ?></span>
                </p>

                <!-- ── Subtipo accesorio (solo visible si type = accessory) ── -->
                <p class="form-field aureo-ar-row--accessory" style="display:none;">
                    <label for="aureo_ar_accessory_type"><?php esc_html_e( 'Tipo de accesorio', 'aureo-ar' ); ?></label>
                    <select id="aureo_ar_accessory_type" name="aureo_ar_accessory_type" class="aureo-ar-select">
                        <option value="earring"  <?php selected( $acc_type, 'earring' ); ?>><?php esc_html_e( 'Aro / Pendiente', 'aureo-ar' ); ?></option>
                        <!-- Próximamente:
                        <option value="headband" <?php selected( $acc_type, 'headband' ); ?>>Cintillo</option>
                        <option value="clip"     <?php selected( $acc_type, 'clip' ); ?>>Pinche / Clip</option>
                        <option value="watch"    <?php selected( $acc_type, 'watch' ); ?>>Reloj</option>
                        <option value="necklace" <?php selected( $acc_type, 'necklace' ); ?>>Collar</option>
                        <option value="cosplay"  <?php selected( $acc_type, 'cosplay' ); ?>>Cosplay cuerpo completo</option>
                        -->
                    </select>
                    <span class="description"><?php esc_html_e( 'Próximamente habrá más opciones de accesorios.', 'aureo-ar' ); ?></span>
                </p>

                <!-- ── Modelo GLB (visible si type != none) ── -->
                <div class="aureo-ar-row--has-model" style="display:none;">
                    <p class="form-field">
                        <label for="aureo_ar_model_url"><?php esc_html_e( 'Modelo GLB / GLTF', 'aureo-ar' ); ?></label>
                        <input
                            type="text"
                            id="aureo_ar_model_url"
                            name="aureo_ar_model_url"
                            value="<?php echo esc_attr( $glb_url ); ?>"
                            placeholder="https://..."
                            style="width:65%;"
                            readonly
                        />
                        <button type="button" class="button aureo-ar-upload-btn" data-target="aureo_ar_model_url" data-accept="glb,gltf">
                            <?php esc_html_e( 'Seleccionar archivo', 'aureo-ar' ); ?>
                        </button>
                        <?php if ( $glb_url ) : ?>
                            <button type="button" class="button aureo-ar-clear-btn" data-target="aureo_ar_model_url">✕</button>
                        <?php endif; ?>
                        <span class="description"><?php esc_html_e( 'Archivo .glb o .gltf subido a la biblioteca de medios.', 'aureo-ar' ); ?></span>
                    </p>

                    <!-- Preview con <model-viewer> -->
                    <div id="aureo-model-preview-wrapper" style="<?php echo $glb_url ? '' : 'display:none;'; ?> width:100%; max-width:600px; height:320px; background:#f0f0f0; border:1px solid #ddd; border-radius:6px; margin:0 12px 16px; position:relative;">
                        <model-viewer
                            id="aureo-mini-viewer"
                            src="<?php echo esc_url( $glb_url ); ?>"
                            alt="<?php esc_attr_e( 'Vista previa del modelo 3D', 'aureo-ar' ); ?>"
                            auto-rotate
                            rotation-per-second="30deg"
                            camera-controls
                            touch-action="pan-y"
                            style="width:100%;height:100%;border-radius:6px;"
                        ></model-viewer>
                        <span style="position:absolute;top:6px;right:8px;background:rgba(0,0,0,.45);color:#fff;font-size:11px;padding:2px 7px;border-radius:10px;">Vista previa</span>
                    </div>
                </div>

                <!-- ── Modo de color (visible si type != none) ── -->
                <p class="form-field aureo-ar-row--has-model">
                    <label for="aureo_ar_color_mode"><?php esc_html_e( 'Modo de color', 'aureo-ar' ); ?></label>
                    <select id="aureo_ar_color_mode" name="aureo_ar_color_mode" class="aureo-ar-select">
                        <option value="model_texture" <?php selected( $color_mode, 'model_texture' ); ?>><?php esc_html_e( 'Colores del modelo (sin cambios)', 'aureo-ar' ); ?></option>
                        <option value="multi_color"   <?php selected( $color_mode, 'multi_color' ); ?>><?php esc_html_e( 'Un color (selector en el visor)', 'aureo-ar' ); ?></option>
                        <option value="per_material"  <?php selected( $color_mode, 'per_material' ); ?>><?php esc_html_e( 'Color / textura por material (material0, material1…)', 'aureo-ar' ); ?></option>
                    </select>
                    <span class="description"><?php esc_html_e( 'Por material: asigna color o textura a cada material nombrado material0, material1… en el GLB. Los demás materiales no se tocan.', 'aureo-ar' ); ?></span>
                </p>

                <!-- ── Lista de colores (visible si color_mode != model_texture) ── -->
                <div class="form-field aureo-ar-row--has-model aureo-ar-colors-section" <?php echo $color_mode === 'model_texture' ? 'style="display:none;"' : ''; ?>>
                    <label><?php esc_html_e( 'Colores', 'aureo-ar' ); ?></label>
                    <div id="aureo-ar-color-list" class="aureo-ar-color-list">
                        <?php foreach ( $colors as $hex ) :
                            if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $hex ) ) continue;
                        ?>
                        <div class="aureo-color-entry">
                            <input type="color" class="aureo-color-picker" value="<?php echo esc_attr( $hex ); ?>">
                            <button type="button" class="button aureo-remove-color-btn">✕</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button aureo-ar-add-color-btn" id="aureo-ar-add-color-btn" <?php echo $color_mode === 'single_color' ? 'style="display:none;"' : ''; ?>>
                        <?php esc_html_e( '+ Agregar color', 'aureo-ar' ); ?>
                    </button>
                    <input type="hidden" name="aureo_ar_colors" id="aureo_ar_colors_data" value="<?php echo esc_attr( wp_json_encode( $colors ) ); ?>">
                    <span class="description"><?php esc_html_e( 'Para aro colgante, solo se cambia el aro; el gancho mantiene su color.', 'aureo-ar' ); ?></span>
                </div>

                <!-- ── Materiales por nombre (visible si color_mode = per_material) ── -->
                <div class="form-field aureo-ar-row--has-model aureo-ar-materials-section" <?php echo $color_mode === 'per_material' ? '' : 'style="display:none;"'; ?>>
                    <label><?php esc_html_e( 'Materiales', 'aureo-ar' ); ?></label>
                    <div id="aureo-ar-material-list" class="aureo-ar-material-list">
                        <p class="aureo-mat-notice"><?php esc_html_e( 'Selecciona un modelo GLB para detectar sus materiales.', 'aureo-ar' ); ?></p>
                    </div>
                    <input type="hidden" name="aureo_ar_material_slots" id="aureo_ar_material_slots_data"
                           value="<?php echo esc_attr( wp_json_encode( $mat_slots ) ); ?>">
                    <span class="description">
                        <?php esc_html_e( 'Nombra los materiales material0, material1… en tu software 3D (Blender, Maya, etc.). El visor aplicará el color o textura configurado a cada uno. Los materiales sin ese nombre no se modifican.', 'aureo-ar' ); ?>
                    </span>
                </div>

                <!-- ── USDZ para iOS (solo visible si type = object) ── -->
                <p class="form-field aureo-ar-row--object" style="display:none;">
                    <label for="aureo_ar_usdz_url"><?php esc_html_e( 'Modelo USDZ (iOS)', 'aureo-ar' ); ?></label>
                    <input
                        type="text"
                        id="aureo_ar_usdz_url"
                        name="aureo_ar_usdz_url"
                        value="<?php echo esc_attr( $usdz_url ); ?>"
                        placeholder="https://... (opcional)"
                        style="width:65%;"
                        readonly
                    />
                    <button type="button" class="button aureo-ar-upload-btn" data-target="aureo_ar_usdz_url" data-accept="usdz">
                        <?php esc_html_e( 'Seleccionar USDZ', 'aureo-ar' ); ?>
                    </button>
                    <?php if ( $usdz_url ) : ?>
                        <button type="button" class="button aureo-ar-clear-btn" data-target="aureo_ar_usdz_url">✕</button>
                    <?php endif; ?>
                    <span class="description">
                        <?php esc_html_e( 'Opcional. Requerido para AR nativo en Safari/iOS (Quick Look). Android usa el GLB.', 'aureo-ar' ); ?>
                    </span>
                </p>

                <!-- ── Resumen de estado AR ── -->
                <div class="aureo-ar-status-row">
                    <?php $this->render_status_badge( $ar_type, $acc_type, $glb_url, $usdz_url ); ?>
                </div>

            </div><!-- .options_group -->
        </div><!-- #aureo_ar_product_data -->
        <?php
    }

    /* ---------------------------------------------------------------
     * 3. Guardar campos
     * ------------------------------------------------------------- */
    public function save_ar_fields( $post_id ) {
        $allowed_types     = array( 'none', 'accessory', 'object' );
        $allowed_acc_types = array( 'earring', 'headband', 'clip', 'watch', 'necklace', 'cosplay' );

        if ( isset( $_POST['aureo_ar_type'] ) ) {
            $type = in_array( $_POST['aureo_ar_type'], $allowed_types, true ) ? $_POST['aureo_ar_type'] : 'none';
            update_post_meta( $post_id, '_aureo_ar_type', $type );
        }

        if ( isset( $_POST['aureo_ar_accessory_type'] ) ) {
            $acc = in_array( $_POST['aureo_ar_accessory_type'], $allowed_acc_types, true ) ? $_POST['aureo_ar_accessory_type'] : 'earring';
            update_post_meta( $post_id, '_aureo_ar_accessory_type', $acc );
        }

        if ( isset( $_POST['aureo_ar_model_url'] ) ) {
            update_post_meta( $post_id, '_aureo_ar_model_url', esc_url_raw( $_POST['aureo_ar_model_url'] ) );
        }

        if ( isset( $_POST['aureo_ar_usdz_url'] ) ) {
            update_post_meta( $post_id, '_aureo_ar_usdz_url', esc_url_raw( $_POST['aureo_ar_usdz_url'] ) );
        }

        $allowed_color_modes = array( 'model_texture', 'multi_color', 'per_material' );
        if ( isset( $_POST['aureo_ar_color_mode'] ) ) {
            $cm = in_array( $_POST['aureo_ar_color_mode'], $allowed_color_modes, true )
                ? $_POST['aureo_ar_color_mode'] : 'model_texture';
            update_post_meta( $post_id, '_aureo_ar_color_mode', $cm );
        }

        if ( isset( $_POST['aureo_ar_material_slots'] ) ) {
            $raw    = stripslashes( (string) $_POST['aureo_ar_material_slots'] );
            $parsed = json_decode( $raw, true );
            if ( is_array( $parsed ) ) {
                $sanitized = array();
                foreach ( $parsed as $mat_name => $slot ) {
                    if ( ! preg_match( '/^material\d+$/i', (string) $mat_name ) ) { continue; }
                    if ( ! is_array( $slot ) ) { continue; }
                    $type = ( isset( $slot['type'] ) && $slot['type'] === 'texture' ) ? 'texture' : 'color';
                    if ( $type === 'color' ) {
                        // Soporte formato nuevo (colors[]) y legado (value)
                        $raw_colors = isset( $slot['colors'] ) && is_array( $slot['colors'] )
                            ? $slot['colors']
                            : array( isset( $slot['value'] ) ? $slot['value'] : '#c0c0c0' );
                        $clean_colors = array_values( array_filter( array_map( function ( $c ) {
                            $c = strtolower( trim( (string) $c ) );
                            return preg_match( '/^#[0-9a-f]{6}$/', $c ) ? $c : null;
                        }, $raw_colors ) ) );
                        if ( empty( $clean_colors ) ) { $clean_colors = array( '#c0c0c0' ); }
                        $sanitized[ strtolower( $mat_name ) ] = array( 'type' => 'color', 'colors' => $clean_colors );
                    } else {
                        $value = esc_url_raw( trim( (string) ( $slot['value'] ?? '' ) ) );
                        $sanitized[ strtolower( $mat_name ) ] = array( 'type' => 'texture', 'value' => $value );
                    }
                }
                update_post_meta( $post_id, '_aureo_ar_material_slots', wp_json_encode( $sanitized ) );
            }
        }

        if ( isset( $_POST['aureo_ar_colors'] ) ) {
            $raw    = stripslashes( (string) $_POST['aureo_ar_colors'] );
            $parsed = json_decode( $raw, true );
            if ( is_array( $parsed ) ) {
                $sanitized = array_values( array_filter( array_map( function ( $c ) {
                    $c = strtolower( trim( (string) $c ) );
                    return preg_match( '/^#[0-9a-f]{6}$/', $c ) ? $c : null;
                }, $parsed ) ) );
                update_post_meta( $post_id, '_aureo_ar_colors', wp_json_encode( $sanitized ) );
            }
        }
    }

    /* ---------------------------------------------------------------
     * 4. Helper: badge de estado
     * ------------------------------------------------------------- */
    private function render_status_badge( $type, $acc_type, $glb_url, $usdz_url ) {
        if ( $type === 'none' ) {
            echo '<span class="aureo-status aureo-status--off">⬤ Sin AR activo</span>';
            return;
        }
        $has_glb = ! empty( $glb_url );
        if ( ! $has_glb ) {
            echo '<span class="aureo-status aureo-status--warn">⚠ Falta el modelo GLB para activar AR</span>';
            return;
        }
        if ( $type === 'accessory' ) {
            $labels = array(
                'earring'  => 'Aro / Pendiente',
                'headband' => 'Cintillo',
                'clip'     => 'Pinche / Clip',
                'watch'    => 'Reloj',
                'necklace' => 'Collar',
                'cosplay'  => 'Cosplay',
            );
            $label = isset( $labels[ $acc_type ] ) ? $labels[ $acc_type ] : $acc_type;
            printf( '<span class="aureo-status aureo-status--ok">✓ AR activo — Accesorio: %s (cámara frontal)</span>', esc_html( $label ) );
        } elseif ( $type === 'object' ) {
            $ios_note = $usdz_url ? '' : ' · <em>iOS: sin USDZ (usará GLB como fallback)</em>';
            echo '<span class="aureo-status aureo-status--ok">✓ AR activo — Objeto 3D (cámara trasera)' . wp_kses_post( $ios_note ) . '</span>';
        }
    }

    /* ---------------------------------------------------------------
     * 5. Helper estático para que el frontend lea los datos
     * ------------------------------------------------------------- */
    public static function get_product_ar_data( $product_id ) {
        return array(
            'type'           => get_post_meta( $product_id, '_aureo_ar_type',          true ) ?: 'none',
            'accessory_type' => get_post_meta( $product_id, '_aureo_ar_accessory_type', true ) ?: 'earring',
            'glb_url'        => get_post_meta( $product_id, '_aureo_ar_model_url',      true ) ?: '',
            'usdz_url'       => get_post_meta( $product_id, '_aureo_ar_usdz_url',       true ) ?: '',
        );
    }
}

new Aureo_AR_Woo();
