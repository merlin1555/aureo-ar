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
