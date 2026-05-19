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
 *   Solo para earring_dangle:
 *   _aureo_ar_chain_length      → Longitud cadena (cm, default 2.5)
 *   _aureo_ar_earring_mass      → Peso del aro (gramos, default 5)
 *   _aureo_ar_swing_damping     → Amortiguamiento (0–1, default 0.4)
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
        $chain_len   = $product_id ? ( get_post_meta( $product_id, '_aureo_ar_chain_length',   true ) ?: '2.5' ) : '2.5';
        $mass        = $product_id ? ( get_post_meta( $product_id, '_aureo_ar_earring_mass',   true ) ?: '5' ) : '5';
        $damping     = $product_id ? ( get_post_meta( $product_id, '_aureo_ar_swing_damping',  true ) ?: '0.4' ) : '0.4';

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
                     data-aureo-acc-type="<?php echo esc_attr( $acc_type ); ?>">

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

                        <!-- ── Parámetros físicos (solo earring_dangle) ── -->
                        <div class="aureo-row--earring-dangle">
                            <div class="aureo-ar-physics-divider">
                                <?php esc_html_e( '🎚 Parámetros del péndulo', 'aureo-ar' ); ?>
                            </div>

                            <div class="aureo-ar-field">
                                <label for="_aureo_ar_chain_length" class="aureo-ar-label">
                                    <?php esc_html_e( 'Longitud de cadena (cm)', 'aureo-ar' ); ?>
                                </label>
                                <input type="number"
                                       name="_aureo_ar_chain_length"
                                       id="_aureo_ar_chain_length"
                                       class="aureo-ar-control aureo-ar-text"
                                       value="<?php echo esc_attr( $chain_len ); ?>"
                                       min="0.5" max="15" step="0.1" />
                                <p class="aureo-ar-hint">
                                    <?php esc_html_e( 'Distancia desde la oreja hasta el aro. Mayor = más balanceo.', 'aureo-ar' ); ?>
                                </p>
                            </div>

                            <div class="aureo-ar-field">
                                <label for="_aureo_ar_earring_mass" class="aureo-ar-label">
                                    <?php esc_html_e( 'Peso del aro (gramos)', 'aureo-ar' ); ?>
                                </label>
                                <input type="number"
                                       name="_aureo_ar_earring_mass"
                                       id="_aureo_ar_earring_mass"
                                       class="aureo-ar-control aureo-ar-text"
                                       value="<?php echo esc_attr( $mass ); ?>"
                                       min="0.5" max="50" step="0.5" />
                                <p class="aureo-ar-hint">
                                    <?php esc_html_e( 'Influye en la inercia. Más pesado = balanceo más lento.', 'aureo-ar' ); ?>
                                </p>
                            </div>

                            <div class="aureo-ar-field">
                                <label for="_aureo_ar_swing_damping" class="aureo-ar-label">
                                    <?php esc_html_e( 'Amortiguamiento (0–1)', 'aureo-ar' ); ?>
                                </label>
                                <input type="number"
                                       name="_aureo_ar_swing_damping"
                                       id="_aureo_ar_swing_damping"
                                       class="aureo-ar-control aureo-ar-text"
                                       value="<?php echo esc_attr( $damping ); ?>"
                                       min="0" max="1" step="0.05" />
                                <p class="aureo-ar-hint">
                                    <?php esc_html_e( '0 = oscila para siempre. 1 = se detiene casi al instante. 0.4 es realista.', 'aureo-ar' ); ?>
                                </p>
                            </div>

                            <div class="aureo-ar-physics-tip">
                                💡 <?php esc_html_e( 'En el visor frontend hay sliders para ajustar estos valores en vivo y luego puedes copiarlos aquí.', 'aureo-ar' ); ?>
                            </div>
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

        // Parámetros físicos del péndulo (con clamps de seguridad)
        if ( isset( $form_data['_aureo_ar_chain_length'] ) ) {
            $val = max( 0.5, min( 15.0, floatval( $form_data['_aureo_ar_chain_length'] ) ) );
            update_post_meta( $product_id, '_aureo_ar_chain_length', $val );
        }
        if ( isset( $form_data['_aureo_ar_earring_mass'] ) ) {
            $val = max( 0.5, min( 50.0, floatval( $form_data['_aureo_ar_earring_mass'] ) ) );
            update_post_meta( $product_id, '_aureo_ar_earring_mass', $val );
        }
        if ( isset( $form_data['_aureo_ar_swing_damping'] ) ) {
            $val = max( 0.0, min( 1.0, floatval( $form_data['_aureo_ar_swing_damping'] ) ) );
            update_post_meta( $product_id, '_aureo_ar_swing_damping', $val );
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