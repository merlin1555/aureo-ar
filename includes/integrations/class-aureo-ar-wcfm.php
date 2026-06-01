<?php
/**
 * Aureo AR — Integración WCFM (WC Frontend Manager — versión FREE)
 *
 * v4 — Soporte para "Aro Colgante" (físicas tipo péndulo):
 *   - Subtipos de accesorio: earring_stud (Aro Pegado) y earring_dangle (Aro Colgante)
 *   - Compatibilidad hacia atrás: 'earring' antiguo se mapea a 'earring_stud'
 *   - Parámetros físicos opcionales para el modo colgante
 *
 * Meta keys:
 *   _aureo_ar_type              → 'none' | 'accessory' | 'object'
 *   _aureo_ar_accessory_type    → 'earring_stud' | 'earring_dangle' | (futuro)
 *   _aureo_ar_model_url         → URL al archivo GLB/GLTF
 *   _aureo_ar_usdz_url          → URL al archivo USDZ (iOS — opcional)
 *
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Aureo_AR_WCFM {

    const ALLOWED_TYPES = array( 'none', 'accessory', 'object' );

    const ALLOWED_ACCESSORY_TYPES = array(
        'earring_stud', 'earring_dangle',
        // futuros: headband, clip, watch, necklace, cosplay
    );

    public function __construct() {
        add_action( 'end_wcfm_products_manage', array( $this, 'render_section' ), 50, 1 );
        add_action( 'after_wcfm_products_manage_meta_save', array( $this, 'save_fields' ), 50, 2 );
        add_action( 'after_wcfm_products_manage', array( $this, 'enqueue_assets' ) );
    }

    /* =====================================================================
     * 1. RENDER de la sección AR
     * ===================================================================== */

    public function render_section( $product_id = 0 ) {
        if ( ! $product_id ) {
            $product_id = $this->resolve_product_id();
        }

        $ar_type     = $product_id ? ( get_post_meta( $product_id, '_aureo_ar_type',           true ) ?: 'none' ) : 'none';
        $acc_type    = $product_id ? ( get_post_meta( $product_id, '_aureo_ar_accessory_type', true ) ?: 'earring_stud' ) : 'earring_stud';
        $glb_url     = $product_id ? ( get_post_meta( $product_id, '_aureo_ar_model_url',      true ) ?: '' ) : '';
        $usdz_url    = $product_id ? ( get_post_meta( $product_id, '_aureo_ar_usdz_url',       true ) ?: '' ) : '';
        $color_mode    = $product_id ? ( get_post_meta( $product_id, '_aureo_ar_color_mode',      true ) ?: 'model_texture' ) : 'model_texture';
        $colors_raw    = $product_id ? ( get_post_meta( $product_id, '_aureo_ar_colors',          true ) ?: '[]' ) : '[]';
        $colors        = json_decode( $colors_raw, true );
        if ( ! is_array( $colors ) || empty( $colors ) ) { $colors = array( '#c0c0c0' ); }
        $mat_slots_raw = $product_id ? ( get_post_meta( $product_id, '_aureo_ar_material_slots',  true ) ?: '{}' ) : '{}';
        $mat_slots     = json_decode( $mat_slots_raw, true );
        if ( ! is_array( $mat_slots ) ) { $mat_slots = array(); }
        // Compatibilidad: 'earring' antiguo → 'earring_stud'
        if ( $acc_type === 'earring' ) {
            $acc_type = 'earring_stud';
        }

        $status_html = $this->render_status_badge( $ar_type, $acc_type, $glb_url, $usdz_url );
        ?>
        <!-- ===== Aureo AR · sección autocontenida ===== -->
        <div class="page_collapsible products_manage_aureo_ar simple variable external grouped"
             id="wcfm_products_manage_form_aureo_ar_head">
            <label class="fa fa-cube"></label>
            <?php esc_html_e( 'Realidad Aumentada (Aureo AR)', 'aureo-ar' ); ?>
            <span></span>
        </div>

        <div class="wcfm-container simple variable external grouped">
            <div id="wcfm_products_manage_form_aureo_ar_expander" class="wcfm-content">

                <div class="aureo-ar-grid"
                     data-aureo-ar-type="<?php echo esc_attr( $ar_type ); ?>"
                     data-aureo-acc-type="<?php echo esc_attr( $acc_type ); ?>"
                     data-aureo-color-mode="<?php echo esc_attr( $color_mode ); ?>">

                    <!-- ───── Columna izquierda: controles ───── -->
                    <div class="aureo-ar-grid-left">

                        <!-- Tipo de AR -->
                        <div class="aureo-ar-field">
                            <label for="_aureo_ar_type" class="aureo-ar-label">
                                <?php esc_html_e( 'Tipo de AR', 'aureo-ar' ); ?>
                            </label>
                            <select name="_aureo_ar_type" id="_aureo_ar_type"
                                    class="aureo-ar-control aureo-ar-select">
                                <option value="none"      <?php selected( $ar_type, 'none' ); ?>>
                                    <?php esc_html_e( '— Sin AR —', 'aureo-ar' ); ?>
                                </option>
                                <option value="accessory" <?php selected( $ar_type, 'accessory' ); ?>>
                                    <?php esc_html_e( '📷 Accesorio — cámara frontal (aros, etc.)', 'aureo-ar' ); ?>
                                </option>
                                <option value="object"    <?php selected( $ar_type, 'object' ); ?>>
                                    <?php esc_html_e( '🏠 Objeto / Mueble — cámara trasera', 'aureo-ar' ); ?>
                                </option>
                            </select>
                            <p class="aureo-ar-hint">
                                <?php esc_html_e( 'Define cómo se activa la experiencia AR para este producto.', 'aureo-ar' ); ?>
                            </p>
                        </div>

                        <!-- Subtipo accesorio -->
                        <div class="aureo-ar-field aureo-row--accessory">
                            <label for="_aureo_ar_accessory_type" class="aureo-ar-label">
                                <?php esc_html_e( 'Tipo de accesorio', 'aureo-ar' ); ?>
                            </label>
                            <select name="_aureo_ar_accessory_type" id="_aureo_ar_accessory_type"
                                    class="aureo-ar-control aureo-ar-select">
                                <option value="earring_stud"   <?php selected( $acc_type, 'earring_stud' ); ?>>
                                    <?php esc_html_e( '📌 Aro Pegado (sin movimiento)', 'aureo-ar' ); ?>
                                </option>
                                <option value="earring_dangle" <?php selected( $acc_type, 'earring_dangle' ); ?>>
                                    <?php esc_html_e( '🪢 Aro Colgante (con física tipo péndulo)', 'aureo-ar' ); ?>
                                </option>
                            </select>
                            <p class="aureo-ar-hint">
                                <?php esc_html_e( 'Aro Pegado: queda fijo a la oreja. Aro Colgante: cuelga y se balancea con el movimiento.', 'aureo-ar' ); ?>
                            </p>
                        </div>

                        <!-- GLB / GLTF -->
                        <div class="aureo-ar-field aureo-row--has-model">
                            <label for="_aureo_ar_model_url" class="aureo-ar-label">
                                <?php esc_html_e( 'Modelo 3D — GLB / GLTF', 'aureo-ar' ); ?>
                            </label>
                            <input type="text"
                                   name="_aureo_ar_model_url"
                                   id="_aureo_ar_model_url"
                                   class="aureo-ar-control aureo-ar-text"
                                   value="<?php echo esc_attr( $glb_url ); ?>"
                                   placeholder="https://…/modelo.glb" />
                            <div class="aureo-ar-tools">
                                <button type="button"
                                        class="aureo-ar-btn aureo-ar-btn--primary aureo-ar-open-media"
                                        data-target="_aureo_ar_model_url"
                                        data-preview="aureo-ar-glb-preview">
                                    <?php esc_html_e( 'Seleccionar GLB', 'aureo-ar' ); ?>
                                </button>
                                <button type="button"
                                        class="aureo-ar-btn aureo-ar-btn--danger aureo-ar-clear-media"
                                        data-target="_aureo_ar_model_url"
                                        data-preview="aureo-ar-glb-preview">
                                    <?php esc_html_e( 'Quitar', 'aureo-ar' ); ?>
                                </button>
                            </div>
                            <p class="aureo-ar-hint">
                                <?php esc_html_e( 'Archivo .glb o .gltf subido a la biblioteca de medios.', 'aureo-ar' ); ?>
                                <br>
                                <strong><?php esc_html_e( 'Para Aro Colgante:', 'aureo-ar' ); ?></strong>
                                <?php esc_html_e( 'el modelo debe tener su origen (pivote) en la parte superior, donde estaría el gancho.', 'aureo-ar' ); ?>
                            </p>
                        </div>

                        <!-- USDZ (solo objeto) -->
                        <div class="aureo-ar-field aureo-row--object">
                            <label for="_aureo_ar_usdz_url" class="aureo-ar-label">
                                <?php esc_html_e( 'Modelo USDZ para iOS (opcional)', 'aureo-ar' ); ?>
                            </label>
                            <input type="text"
                                   name="_aureo_ar_usdz_url"
                                   id="_aureo_ar_usdz_url"
                                   class="aureo-ar-control aureo-ar-text"
                                   value="<?php echo esc_attr( $usdz_url ); ?>"
                                   placeholder="https://…/modelo.usdz" />
                            <div class="aureo-ar-tools">
                                <button type="button"
                                        class="aureo-ar-btn aureo-ar-btn--primary aureo-ar-open-media"
                                        data-target="_aureo_ar_usdz_url">
                                    <?php esc_html_e( 'Seleccionar USDZ', 'aureo-ar' ); ?>
                                </button>
                                <button type="button"
                                        class="aureo-ar-btn aureo-ar-btn--danger aureo-ar-clear-media"
                                        data-target="_aureo_ar_usdz_url">
                                    <?php esc_html_e( 'Quitar', 'aureo-ar' ); ?>
                                </button>
                            </div>
                            <p class="aureo-ar-hint">
                                <?php esc_html_e( 'Si no subes USDZ, iOS usará el GLB como fallback.', 'aureo-ar' ); ?>
                            </p>
                        </div>

                        <!-- Modo de color -->
                        <div class="aureo-ar-field aureo-row--has-model">
                            <label for="_aureo_ar_color_mode" class="aureo-ar-label">
                                <?php esc_html_e( 'Modo de color', 'aureo-ar' ); ?>
                            </label>
                            <select name="_aureo_ar_color_mode" id="_aureo_ar_color_mode"
                                    class="aureo-ar-control aureo-ar-select">
                                <option value="model_texture" <?php selected( $color_mode, 'model_texture' ); ?>>
                                    <?php esc_html_e( 'Colores del modelo (sin cambios)', 'aureo-ar' ); ?>
                                </option>
                                <option value="multi_color" <?php selected( $color_mode, 'multi_color' ); ?>>
                                    <?php esc_html_e( 'Un color (selector en el visor)', 'aureo-ar' ); ?>
                                </option>
                                <option value="per_material" <?php selected( $color_mode, 'per_material' ); ?>>
                                    <?php esc_html_e( 'Color / textura por material (material0, material1…)', 'aureo-ar' ); ?>
                                </option>
                            </select>
                            <p class="aureo-ar-hint">
                                <?php esc_html_e( 'Paleta: el usuario elige entre varios colores dentro del visor AR.', 'aureo-ar' ); ?>
                            </p>
                        </div>

                        <!-- Lista de colores -->
                        <div class="aureo-ar-field aureo-row--has-model aureo-row--colors">
                            <label class="aureo-ar-label">
                                <?php esc_html_e( 'Colores', 'aureo-ar' ); ?>
                            </label>
                            <div id="aureo-ar-color-list" class="aureo-ar-color-list">
                                <?php foreach ( $colors as $hex ) :
                                    if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $hex ) ) continue;
                                ?>
                                <div class="aureo-color-entry">
                                    <input type="color" class="aureo-color-picker" value="<?php echo esc_attr( $hex ); ?>">
                                    <button type="button" class="aureo-ar-btn aureo-ar-btn--danger aureo-remove-color-btn">✕</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button"
                                    class="aureo-ar-btn aureo-ar-btn--primary aureo-row--colors-add-btn"
                                    id="aureo-ar-add-color-btn">
                                <?php esc_html_e( '+ Agregar color', 'aureo-ar' ); ?>
                            </button>
                            <input type="hidden" name="_aureo_ar_colors" id="aureo_ar_colors_data"
                                   value="<?php echo esc_attr( wp_json_encode( $colors ) ); ?>">
                            <p class="aureo-ar-hint">
                                <?php esc_html_e( 'Para aro colgante, solo cambia el aro; el gancho mantiene su color original.', 'aureo-ar' ); ?>
                            </p>
                        </div>

                        <!-- Materiales por nombre (per_material) -->
                        <div class="aureo-ar-field aureo-row--has-model aureo-row--materials">
                            <label class="aureo-ar-label">
                                <?php esc_html_e( 'Materiales del modelo', 'aureo-ar' ); ?>
                            </label>
                            <div id="aureo-ar-material-list" class="aureo-ar-material-list">
                                <p class="aureo-mat-notice"><?php esc_html_e( 'Selecciona un modelo GLB para detectar sus materiales.', 'aureo-ar' ); ?></p>
                            </div>
                            <input type="hidden" name="_aureo_ar_material_slots" id="aureo_ar_material_slots_data"
                                   value="<?php echo esc_attr( wp_json_encode( $mat_slots ) ); ?>">
                            <p class="aureo-ar-hint">
                                <?php esc_html_e( 'Nombra los materiales material0, material1… en tu software 3D. El visor asignará el color o textura configurado a cada uno.', 'aureo-ar' ); ?>
                            </p>
                        </div>

                        <!-- Estado -->
                        <div class="aureo-ar-field aureo-ar-status-wrap">
                            <?php echo $status_html; // ya escapado en render_status_badge ?>
                        </div>

                    </div>

                    <!-- ───── Columna derecha: visor 3D ───── -->
                    <div class="aureo-ar-grid-right aureo-row--has-model">
                        <div class="aureo-ar-preview-label">
                            <?php esc_html_e( 'Vista previa 3D', 'aureo-ar' ); ?>
                        </div>
                        <div id="aureo-ar-glb-preview"
                             class="aureo-ar-preview"
                             data-has-model="<?php echo $glb_url ? '1' : '0'; ?>">
                            <model-viewer
                                id="aureo-ar-mini-viewer"
                                <?php echo $glb_url ? 'src="' . esc_url( $glb_url ) . '"' : ''; ?>
                                alt="<?php esc_attr_e( 'Vista previa 3D', 'aureo-ar' ); ?>"
                                auto-rotate
                                rotation-per-second="25deg"
                                camera-controls
                                touch-action="pan-y">
                            </model-viewer>
                            <div class="aureo-ar-preview-empty">
                                <span class="aureo-ar-preview-empty-icon">📦</span>
                                <p><?php esc_html_e( 'Selecciona un modelo GLB para previsualizarlo aquí', 'aureo-ar' ); ?></p>
                            </div>
                        </div>
                    </div>

                </div><!-- /.aureo-ar-grid -->

            </div><!-- /.wcfm-content -->
        </div><!-- /.wcfm-container -->

        <div class="wcfm_clearfix"></div>
        <!-- ===== /Aureo AR ===== -->
        <?php
    }

    private function resolve_product_id() {
        global $wp;
        if ( isset( $wp->query_vars['wcfm-products-manage'] ) && ! empty( $wp->query_vars['wcfm-products-manage'] ) ) {
            return absint( $wp->query_vars['wcfm-products-manage'] );
        }
        return 0;
    }

    /* =====================================================================
     * 2. GUARDADO de campos
     * ===================================================================== */

    public function save_fields( $product_id, $form_data = array() ) {
        if ( ! $product_id ) { return; }
        if ( ! current_user_can( 'edit_post', $product_id ) ) { return; }

        // _aureo_ar_type
        if ( isset( $form_data['_aureo_ar_type'] ) ) {
            $type = in_array( $form_data['_aureo_ar_type'], self::ALLOWED_TYPES, true )
                ? $form_data['_aureo_ar_type'] : 'none';
            update_post_meta( $product_id, '_aureo_ar_type', $type );
        }

        // _aureo_ar_accessory_type (con migración del antiguo 'earring')
        if ( isset( $form_data['_aureo_ar_accessory_type'] ) ) {
            $acc = $form_data['_aureo_ar_accessory_type'];
            if ( $acc === 'earring' ) { $acc = 'earring_stud'; }
            $acc = in_array( $acc, self::ALLOWED_ACCESSORY_TYPES, true ) ? $acc : 'earring_stud';
            update_post_meta( $product_id, '_aureo_ar_accessory_type', $acc );
        }

        // _aureo_ar_model_url
        if ( isset( $form_data['_aureo_ar_model_url'] ) ) {
            $url = esc_url_raw( trim( (string) $form_data['_aureo_ar_model_url'] ) );
            $url ? update_post_meta( $product_id, '_aureo_ar_model_url', $url )
                 : delete_post_meta( $product_id, '_aureo_ar_model_url' );
        }

        // _aureo_ar_usdz_url
        if ( isset( $form_data['_aureo_ar_usdz_url'] ) ) {
            $url = esc_url_raw( trim( (string) $form_data['_aureo_ar_usdz_url'] ) );
            $url ? update_post_meta( $product_id, '_aureo_ar_usdz_url', $url )
                 : delete_post_meta( $product_id, '_aureo_ar_usdz_url' );
        }

        // _aureo_ar_color_mode
        $allowed_color_modes = array( 'model_texture', 'multi_color', 'per_material' );
        if ( isset( $form_data['_aureo_ar_color_mode'] ) ) {
            $cm = in_array( $form_data['_aureo_ar_color_mode'], $allowed_color_modes, true )
                ? $form_data['_aureo_ar_color_mode'] : 'model_texture';
            update_post_meta( $product_id, '_aureo_ar_color_mode', $cm );
        }

        // _aureo_ar_colors
        if ( isset( $form_data['_aureo_ar_colors'] ) ) {
            $raw    = stripslashes( (string) $form_data['_aureo_ar_colors'] );
            $parsed = json_decode( $raw, true );
            if ( is_array( $parsed ) ) {
                $sanitized = array_values( array_filter( array_map( function ( $c ) {
                    $c = strtolower( trim( (string) $c ) );
                    return preg_match( '/^#[0-9a-f]{6}$/', $c ) ? $c : null;
                }, $parsed ) ) );
                update_post_meta( $product_id, '_aureo_ar_colors', wp_json_encode( $sanitized ) );
            }
        }

        // _aureo_ar_material_slots
        if ( isset( $form_data['_aureo_ar_material_slots'] ) ) {
            $raw    = stripslashes( (string) $form_data['_aureo_ar_material_slots'] );
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
                update_post_meta( $product_id, '_aureo_ar_material_slots', wp_json_encode( $sanitized ) );
            }
        }

    }

    /* =====================================================================
     * 3. ASSETS (CSS, JS, model-viewer, media library)
     * ===================================================================== */

    public function enqueue_assets() {
        wp_enqueue_media();

        wp_enqueue_script(
            'aureo-ar-model-viewer',
            'https://ajax.googleapis.com/ajax/libs/model-viewer/3.4.0/model-viewer.min.js',
            array(),
            '3.4.0',
            true
        );
        add_filter( 'script_loader_tag', array( $this, 'make_model_viewer_module' ), 10, 3 );

        $this->print_styles();
        $this->print_inline_script();
    }

    public function make_model_viewer_module( $tag, $handle, $src ) {
        if ( 'aureo-ar-model-viewer' === $handle ) {
            return '<script type="module" src="' . esc_url( $src ) . '"></script>' . "\n";
        }
        return $tag;
    }

    private function print_styles() {
        ?>
        <style>
        /* ── Sección AR autocontenida ────────────────────────────────── */
        .aureo-ar-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }
        .aureo-ar-grid-left,
        .aureo-ar-grid-right { min-width: 0; }
        .aureo-ar-grid-right {
            position: sticky;
            top: 16px;
        }

        .aureo-ar-field { margin-bottom: 18px; }
        .aureo-ar-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #444;
            margin-bottom: 6px;
        }
        .aureo-ar-control {
            width: 100%;
            box-sizing: border-box;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: #fff;
            font-size: 14px;
            line-height: 1.4;
        }
        .aureo-ar-select { height: 38px; }
        .aureo-ar-hint {
            margin: 6px 0 0;
            font-size: 12px;
            color: #777;
            font-style: italic;
        }

        .aureo-ar-tools {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .aureo-ar-btn {
            display: inline-block;
            padding: 7px 14px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: opacity .15s ease;
        }
        .aureo-ar-btn:hover { opacity: .9; }
        .aureo-ar-btn--primary { background: #2c8aaa; color: #fff; }
        .aureo-ar-btn--danger  { background: #c0392b; color: #fff; }

        /* Sección de física del péndulo */
        .aureo-ar-physics-divider {
            font-size: 13px;
            font-weight: 700;
            color: #2c8aaa;
            border-top: 2px dashed #2c8aaa;
            padding-top: 14px;
            margin-top: 6px;
            margin-bottom: 14px;
        }
        .aureo-ar-physics-tip {
            background: #e7f5fa;
            border-left: 3px solid #2c8aaa;
            padding: 10px 12px;
            font-size: 12px;
            color: #2c5b6b;
            border-radius: 3px;
            margin-bottom: 10px;
        }

        /* Visor 3D */
        .aureo-ar-preview-label {
            font-size: 13px;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }
        .aureo-ar-preview {
            position: relative;
            width: 100%;
            aspect-ratio: 1 / 1;
            max-height: 480px;
            background:
                linear-gradient(45deg,  #f5f5f5 25%, transparent 25%),
                linear-gradient(-45deg, #f5f5f5 25%, transparent 25%),
                linear-gradient(45deg,  transparent 75%, #f5f5f5 75%),
                linear-gradient(-45deg, transparent 75%, #f5f5f5 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, 10px 0;
            background-color: #fafafa;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .aureo-ar-preview model-viewer {
            width: 100%;
            height: 100%;
            background: transparent;
        }
        .aureo-ar-preview-empty {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #999;
            background: #fafafa;
            pointer-events: none;
        }
        .aureo-ar-preview-empty-icon { font-size: 48px; opacity: .4; }
        .aureo-ar-preview-empty p {
            margin: 0;
            font-size: 13px;
            text-align: center;
            padding: 0 16px;
        }
        .aureo-ar-preview[data-has-model="1"] .aureo-ar-preview-empty {
            display: none;
        }

        .aureo-ar-status-wrap { margin-top: 4px; }

        /* ── Visibilidad condicional según tipo AR ───────────────────── */
        .aureo-ar-grid[data-aureo-ar-type="none"] .aureo-row--has-model,
        .aureo-ar-grid[data-aureo-ar-type="none"] .aureo-row--accessory,
        .aureo-ar-grid[data-aureo-ar-type="none"] .aureo-row--object,
        .aureo-ar-grid[data-aureo-ar-type="none"] .aureo-row--earring-dangle {
            display: none !important;
        }
        .aureo-ar-grid[data-aureo-ar-type="accessory"] .aureo-row--object,
        .aureo-ar-grid[data-aureo-ar-type="object"]    .aureo-row--accessory,
        .aureo-ar-grid[data-aureo-ar-type="object"]    .aureo-row--earring-dangle {
            display: none !important;
        }
        /* Solo mostrar parámetros físicos si es accessory + earring_dangle */
        .aureo-ar-grid[data-aureo-acc-type="earring_stud"] .aureo-row--earring-dangle {
            display: none !important;
        }

        /* ── Colores: visibilidad por modo ───────────────────────────── */
        .aureo-ar-grid[data-aureo-color-mode="model_texture"] .aureo-row--colors,
        .aureo-ar-grid[data-aureo-color-mode="per_material"]  .aureo-row--colors,
        .aureo-ar-grid[data-aureo-ar-type="none"] .aureo-row--colors {
            display: none !important;
        }
        /* ── Materiales: solo visible en modo per_material ───────────── */
        .aureo-ar-grid:not([data-aureo-color-mode="per_material"]) .aureo-row--materials,
        .aureo-ar-grid[data-aureo-ar-type="none"] .aureo-row--materials {
            display: none !important;
        }

        /* Color entries */
        .aureo-ar-color-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        .aureo-color-entry {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .aureo-color-picker {
            width: 42px;
            height: 32px;
            padding: 2px;
            border: 1px solid #ccc;
            border-radius: 4px;
            cursor: pointer;
            background: #fff;
        }

        /* Material rows */
        .aureo-ar-material-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 10px; }
        .aureo-material-row {
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
            background: #f8f8f8; padding: 6px 10px; border-radius: 4px; border: 1px solid #ddd;
        }
        .aureo-material-name { font-size: 13px; font-weight: 600; min-width: 80px; color: #2c8aaa; font-family: monospace; }
        .aureo-material-type { padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
        /* Multi-color por material */
        .aureo-mat-colors-wrap { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; flex: 1; }
        .aureo-mat-color-entry { display: flex; align-items: center; gap: 4px; }
        .aureo-mat-color-picker { width: 42px; height: 32px; padding: 2px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; }
        .aureo-add-mat-color-btn { font-size: 11px !important; padding: 4px 8px !important; }
        .aureo-remove-mat-color-btn { background: #c0392b; color: #fff; border: none; border-radius: 4px; padding: 3px 7px; font-size: 13px; cursor: pointer; line-height: 1.4; }
        .aureo-texture-wrap { display: flex; gap: 6px; align-items: center; flex: 1; }
        .aureo-texture-wrap input { flex: 1; padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
        .aureo-mat-notice { font-size: 12px; color: #777; font-style: italic; margin: 4px 0; }
        .aureo-mat-notice--warn { color: #856404; }

        @media (max-width: 900px) {
            .aureo-ar-grid {
                grid-template-columns: 1fr;
            }
            .aureo-ar-grid-right {
                position: static;
            }
        }
        </style>
        <?php
    }

    private function print_inline_script() {
        ?>
        <script>
        (function($){
            $(function(){

                var $grid = $('.aureo-ar-grid');
                if (!$grid.length) return;

                function getInput(target) {
                    return $grid.find('[name="' + target + '"]').first();
                }

                function setPreviewSrc(previewId, url) {
                    if (!previewId) return;
                    var $wrap = $('#' + previewId);
                    if (!$wrap.length) return;
                    var viewer = $wrap.find('model-viewer')[0];
                    if (!viewer) return;

                    if (url) {
                        viewer.setAttribute('src', url);
                        $wrap.attr('data-has-model', '1');
                    } else {
                        viewer.removeAttribute('src');
                        $wrap.attr('data-has-model', '0');
                    }
                }

                /* Tipo AR → visibilidad */
                $grid.on('change', 'select[name="_aureo_ar_type"]', function(){
                    $grid.attr('data-aureo-ar-type', $(this).val() || 'none');
                });

                /* Subtipo accesorio → visibilidad de parámetros físicos */
                $grid.on('change', 'select[name="_aureo_ar_accessory_type"]', function(){
                    $grid.attr('data-aureo-acc-type', $(this).val() || 'earring_stud');
                });

                /* Modo de color → visibilidad */
                $grid.on('change', 'select[name="_aureo_ar_color_mode"]', function(){
                    var mode = $(this).val() || 'model_texture';
                    $grid.attr('data-aureo-color-mode', mode);
                    if (mode === 'per_material') {
                        detectMaterialsWCFM();
                    } else {
                        syncColorsData();
                    }
                });

                /* Sincronizar campo hidden con pickers */
                function syncColorsData() {
                    var colors = [];
                    $('#aureo-ar-color-list .aureo-color-picker').each(function(){
                        colors.push($(this).val());
                    });
                    $('#aureo_ar_colors_data').val(JSON.stringify(colors));
                }

                $grid.on('input change', '#aureo-ar-color-list .aureo-color-picker', function(){
                    syncColorsData();
                });

                $grid.on('click', '#aureo-ar-add-color-btn', function(e){
                    e.preventDefault();
                    var entry = '<div class="aureo-color-entry">' +
                        '<input type="color" class="aureo-color-picker" value="#c0c0c0">' +
                        '<button type="button" class="aureo-ar-btn aureo-ar-btn--danger aureo-remove-color-btn">✕</button>' +
                        '</div>';
                    $('#aureo-ar-color-list').append(entry);
                    syncColorsData();
                });

                $grid.on('click', '.aureo-remove-color-btn', function(e){
                    e.preventDefault();
                    var $entries = $('#aureo-ar-color-list .aureo-color-entry');
                    if ($entries.length <= 1) return;
                    $(this).closest('.aureo-color-entry').remove();
                    syncColorsData();
                });

                syncColorsData();

                /* ── Gestión de materiales (per_material) ──────────── */
                function detectMaterialsWCFM() {
                    var viewer = document.getElementById('aureo-ar-mini-viewer');
                    if (!viewer || !viewer.model) {
                        $('#aureo-ar-material-list').html('<p class="aureo-mat-notice">Cargando modelo… Los materiales aparecerán cuando el preview cargue.</p>');
                        return;
                    }
                    var mats = (viewer.model.materials || []).filter(function(m){
                        return /^material\d+$/i.test(m.name);
                    }).sort(function(a,b){ return a.name.localeCompare(b.name, undefined, {numeric:true}); });

                    if (!mats.length) {
                        $('#aureo-ar-material-list').html('<p class="aureo-mat-notice aureo-mat-notice--warn">⚠ No se encontraron materiales con nombre <strong>materialN</strong> en este modelo.</p>');
                        syncMaterialSlotsData();
                        return;
                    }
                    var savedSlots = {};
                    try { savedSlots = JSON.parse($('#aureo_ar_material_slots_data').val() || '{}'); } catch(e) {}

                    var html = '';
                    mats.forEach(function(mat){
                        var saved = savedSlots[mat.name] || {type:'color', colors:['#c0c0c0']};
                        var isTex = saved.type === 'texture';

                        // Soporte formato nuevo (colors[]) y legado (value)
                        var colors = ['#c0c0c0'];
                        if (!isTex) {
                            if (Array.isArray(saved.colors) && saved.colors.length) {
                                colors = saved.colors;
                            } else if (saved.value) {
                                colors = [saved.value];
                            }
                        }
                        var texVal = isTex ? (saved.value || '') : '';

                        html += '<div class="aureo-material-row" data-material="'+mat.name+'">';
                        html += '<span class="aureo-material-name">'+mat.name+'</span>';
                        html += '<select class="aureo-material-type aureo-ar-control" style="width:auto">';
                        html += '<option value="color"'   +(!isTex?' selected':'')+'>Color</option>';
                        html += '<option value="texture"' +(isTex ?' selected':'')+'>Textura</option>';
                        html += '</select>';
                        html += '<div class="aureo-mat-colors-wrap"'+(isTex?' style="display:none"':'')+' >';
                        colors.forEach(function(c){
                            html += '<div class="aureo-mat-color-entry">';
                            html += '<input type="color" class="aureo-mat-color-picker" value="'+c+'">';
                            html += '<button type="button" class="aureo-remove-mat-color-btn">✕</button>';
                            html += '</div>';
                        });
                        html += '<button type="button" class="aureo-ar-btn aureo-ar-btn--primary aureo-add-mat-color-btn">+ Color</button>';
                        html += '</div>';
                        html += '<div class="aureo-texture-wrap"'+(!isTex?' style="display:none"':'')+' >';
                        html += '<input type="text" class="aureo-material-texture" value="'+texVal+'" placeholder="https://… URL de textura">';
                        html += '<button type="button" class="aureo-ar-btn aureo-ar-btn--primary aureo-wcfm-select-texture" style="font-size:12px;padding:5px 10px">Seleccionar</button>';
                        html += '</div>';
                        html += '</div>';
                    });
                    $('#aureo-ar-material-list').html(html);
                    syncMaterialSlotsData();
                }

                function syncMaterialSlotsData() {
                    var slots = {};
                    $('#aureo-ar-material-list .aureo-material-row').each(function(){
                        var mat  = $(this).data('material');
                        var type = $(this).find('.aureo-material-type').val();
                        if (type === 'color') {
                            var colors = [];
                            $(this).find('.aureo-mat-color-picker').each(function(){
                                colors.push($(this).val());
                            });
                            slots[mat] = {type: 'color', colors: colors};
                        } else {
                            slots[mat] = {type: 'texture', value: $(this).find('.aureo-material-texture').val()};
                        }
                    });
                    $('#aureo_ar_material_slots_data').val(JSON.stringify(slots));
                }

                $grid.on('change', '#aureo-ar-material-list .aureo-material-type', function(){
                    var $row  = $(this).closest('.aureo-material-row');
                    var isTex = $(this).val() === 'texture';
                    $row.find('.aureo-mat-colors-wrap').toggle(!isTex);
                    $row.find('.aureo-texture-wrap').toggle(isTex);
                    syncMaterialSlotsData();
                });

                $grid.on('input change', '#aureo-ar-material-list .aureo-mat-color-picker, #aureo-ar-material-list .aureo-material-texture', function(){
                    syncMaterialSlotsData();
                });

                // Agregar color a un material
                $grid.on('click', '#aureo-ar-material-list .aureo-add-mat-color-btn', function(e){
                    e.preventDefault();
                    var $wrap = $(this).closest('.aureo-mat-colors-wrap');
                    var entry = '<div class="aureo-mat-color-entry">' +
                        '<input type="color" class="aureo-mat-color-picker" value="#c0c0c0">' +
                        '<button type="button" class="aureo-remove-mat-color-btn">✕</button>' +
                        '</div>';
                    $wrap.find('.aureo-add-mat-color-btn').before(entry);
                    syncMaterialSlotsData();
                });

                // Eliminar color de un material (mínimo 1)
                $grid.on('click', '#aureo-ar-material-list .aureo-remove-mat-color-btn', function(e){
                    e.preventDefault();
                    var $wrap = $(this).closest('.aureo-mat-colors-wrap');
                    if ($wrap.find('.aureo-mat-color-entry').length <= 1) return;
                    $(this).closest('.aureo-mat-color-entry').remove();
                    syncMaterialSlotsData();
                });

                $grid.on('click', '.aureo-wcfm-select-texture', function(e){
                    e.preventDefault();
                    var $input = $(this).closest('.aureo-texture-wrap').find('.aureo-material-texture');
                    var frame = wp.media({
                        title: '<?php echo esc_js( __( 'Seleccionar textura', 'aureo-ar' ) ); ?>',
                        button: { text: '<?php echo esc_js( __( 'Usar esta imagen', 'aureo-ar' ) ); ?>' },
                        library: { type: 'image' },
                        multiple: false
                    });
                    frame.on('select', function(){
                        var att = frame.state().get('selection').first().toJSON();
                        if (att && att.url) { $input.val(att.url).trigger('input'); }
                    });
                    frame.open();
                });

                var mvWcfm = document.getElementById('aureo-ar-mini-viewer');
                if (mvWcfm) {
                    mvWcfm.addEventListener('load', function(){
                        if ($grid.attr('data-aureo-color-mode') === 'per_material') {
                            detectMaterialsWCFM();
                        }
                    });
                }
                if ($grid.attr('data-aureo-color-mode') === 'per_material') {
                    detectMaterialsWCFM();
                }

                /* GLB ↔ preview */
                $grid.on('input change', 'input[name="_aureo_ar_model_url"]', function(){
                    setPreviewSrc('aureo-ar-glb-preview', $(this).val().trim());
                });

                /* Media Library */
                $grid.on('click', '.aureo-ar-open-media', function(e){
                    e.preventDefault();
                    e.stopPropagation();

                    var target    = $(this).data('target');
                    var previewId = $(this).data('preview');
                    var $input    = getInput(target);

                    if (!$input.length) {
                        console.warn('[Aureo AR] Input no encontrado:', target);
                        return;
                    }

                    var frame = wp.media({
                        title  : '<?php echo esc_js( __( 'Seleccionar archivo', 'aureo-ar' ) ); ?>',
                        button : { text: '<?php echo esc_js( __( 'Usar este archivo', 'aureo-ar' ) ); ?>' },
                        multiple: false
                    });

                    frame.on('select', function(){
                        var attachment = frame.state().get('selection').first().toJSON();
                        if (!attachment || !attachment.url) return;

                        $input.val(attachment.url).trigger('change').trigger('input');

                        if (previewId) {
                            setPreviewSrc(previewId, attachment.url);
                        }
                    });

                    frame.open();
                });

                $grid.on('click', '.aureo-ar-clear-media', function(e){
                    e.preventDefault();
                    e.stopPropagation();

                    var target    = $(this).data('target');
                    var previewId = $(this).data('preview');
                    var $input    = getInput(target);

                    if ($input.length) {
                        $input.val('').trigger('change').trigger('input');
                    }
                    if (previewId) {
                        setPreviewSrc(previewId, '');
                    }
                });

            });
        })(jQuery);
        </script>
        <?php
    }

    /* =====================================================================
     * 4. Helper: badge de estado
     * ===================================================================== */

    private function render_status_badge( $type, $acc_type, $glb_url, $usdz_url ) {
        $base = 'display:inline-block;padding:5px 12px;border-radius:4px;font-size:13px;font-weight:500;';
        $off  = $base . 'background:#f0f0f0;color:#666;';
        $warn = $base . 'background:#fff3cd;color:#856404;border:1px solid #ffc107;';
        $ok   = $base . 'background:#d4edda;color:#155724;border:1px solid #28a745;';

        if ( $type === 'none' ) {
            return '<span style="' . esc_attr( $off ) . '">⬤ '
                 . esc_html__( 'Sin AR activo', 'aureo-ar' ) . '</span>';
        }

        if ( empty( $glb_url ) ) {
            return '<span style="' . esc_attr( $warn ) . '">⚠ '
                 . esc_html__( 'Falta el modelo GLB para activar AR', 'aureo-ar' ) . '</span>';
        }

        if ( $type === 'accessory' ) {
            $labels = array(
                'earring_stud'   => __( 'Aro Pegado', 'aureo-ar' ),
                'earring_dangle' => __( 'Aro Colgante (con péndulo físico)', 'aureo-ar' ),
                'earring'        => __( 'Aro / Pendiente (legacy)', 'aureo-ar' ), // compat
            );
            $label = isset( $labels[ $acc_type ] ) ? $labels[ $acc_type ] : $acc_type;
            return '<span style="' . esc_attr( $ok ) . '">✓ '
                 . sprintf(
                     /* translators: %s = nombre del tipo de accesorio */
                     esc_html__( 'AR activo — %s (cámara frontal)', 'aureo-ar' ),
                     esc_html( $label )
                   )
                 . '</span>';
        }

        if ( $type === 'object' ) {
            $ios = $usdz_url
                ? ''
                : ' · <em>' . esc_html__( 'iOS: sin USDZ (usará GLB como fallback)', 'aureo-ar' ) . '</em>';
            return '<span style="' . esc_attr( $ok ) . '">✓ '
                 . esc_html__( 'AR activo — Objeto 3D (cámara trasera)', 'aureo-ar' )
                 . $ios . '</span>';
        }

        return '';
    }
}

new Aureo_AR_WCFM();