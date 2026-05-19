<?php
/**
 * Aureo AR — Frontend (visor del cliente final)
 *
 * v4 — Soporte para Aro Colgante:
 *   - Nuevos meta keys expuestos a JS: chainLength, mass, damping
 *   - Importmap incluye cannon-es cuando es earring_dangle
 *   - Migración silenciosa del antiguo 'earring' → 'earring_stud'
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Aureo_AR_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 99 );
        add_action( 'wp_head', array( $this, 'print_importmap' ), 1 );
        add_action( 'wp_footer', array( $this, 'print_assets_fallback' ), 5 );

        add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_ar_button' ) );
        add_action( 'wp_footer', array( $this, 'render_modal' ), 50 );
    }

    /* ---------------------------------------------------------------
     * Helpers para leer los datos AR del producto
     * ------------------------------------------------------------- */
    public static function get_product_glb_url( $product_id = null ) {
        $product_id = self::resolve_product_id( $product_id );
        if ( ! $product_id ) { return ''; }
        $url = get_post_meta( $product_id, '_aureo_ar_model_url', true );
        return $url ? esc_url( $url ) : '';
    }

    public static function get_product_ar_type( $product_id = null ) {
        $product_id = self::resolve_product_id( $product_id );
        if ( ! $product_id ) { return 'none'; }
        $type = get_post_meta( $product_id, '_aureo_ar_type', true );
        return $type ?: 'none';
    }

    public static function get_product_usdz_url( $product_id = null ) {
        $product_id = self::resolve_product_id( $product_id );
        if ( ! $product_id ) { return ''; }
        $url = get_post_meta( $product_id, '_aureo_ar_usdz_url', true );
        return $url ? esc_url( $url ) : '';
    }

    public static function get_product_accessory_type( $product_id = null ) {
        $product_id = self::resolve_product_id( $product_id );
        if ( ! $product_id ) { return 'earring_stud'; }
        $acc = get_post_meta( $product_id, '_aureo_ar_accessory_type', true );
        // Migración silenciosa: 'earring' antiguo → 'earring_stud'
        if ( $acc === 'earring' ) { return 'earring_stud'; }
        return $acc ?: 'earring_stud';
    }

    /* Parámetros de física para earring_dangle */
    public static function get_product_chain_length( $product_id = null ) {
        $product_id = self::resolve_product_id( $product_id );
        if ( ! $product_id ) { return 2.5; }
        $val = get_post_meta( $product_id, '_aureo_ar_chain_length', true );
        return $val !== '' ? floatval( $val ) : 2.5;
    }

    public static function get_product_earring_mass( $product_id = null ) {
        $product_id = self::resolve_product_id( $product_id );
        if ( ! $product_id ) { return 5.0; }
        $val = get_post_meta( $product_id, '_aureo_ar_earring_mass', true );
        return $val !== '' ? floatval( $val ) : 5.0;
    }

    public static function get_product_swing_damping( $product_id = null ) {
        $product_id = self::resolve_product_id( $product_id );
        if ( ! $product_id ) { return 0.4; }
        $val = get_post_meta( $product_id, '_aureo_ar_swing_damping', true );
        return $val !== '' ? floatval( $val ) : 0.4;
    }

    private static function resolve_product_id( $product_id ) {
        if ( ! function_exists( 'is_product' ) ) { return 0; }
        if ( ! $product_id ) {
            global $product;
            if ( $product && is_object( $product ) && method_exists( $product, 'get_id' ) ) {
                $product_id = $product->get_id();
            }
        }
        if ( ! $product_id ) {
            $pid = get_the_ID();
            if ( $pid && get_post_type( $pid ) === 'product' ) {
                $product_id = $pid;
            }
        }
        return (int) $product_id;
    }

    /* ---------------------------------------------------------------
     * ¿Debemos cargar assets AR en esta página?
     * ------------------------------------------------------------- */
    public function should_load() {
        if ( ! function_exists( 'is_product' ) ) { return false; }
        $is_prod = is_product() || ( get_post_type( get_the_ID() ) === 'product' );
        if ( ! $is_prod ) { return false; }
        $type = self::get_product_ar_type();
        if ( $type === 'none' ) { return false; }
        return (bool) self::get_product_glb_url();
    }

    public function is_accessory_mode() {
        return self::get_product_ar_type() === 'accessory';
    }

    public function is_object_mode() {
        return self::get_product_ar_type() === 'object';
    }

    public function is_dangle_mode() {
        return $this->is_accessory_mode()
            && self::get_product_accessory_type() === 'earring_dangle';
    }

    /* ---------------------------------------------------------------
     * Importmap: solo para modo accesorio
     * Si es dangle, incluye cannon-es
     * ------------------------------------------------------------- */
    public function print_importmap() {
        if ( ! $this->should_load() ) { return; }
        if ( ! $this->is_accessory_mode() ) { return; }

        $imports = array(
            'three'            => 'https://cdn.jsdelivr.net/npm/three@0.147.0/build/three.module.js',
            'three/addons/'    => 'https://cdn.jsdelivr.net/npm/three@0.147.0/examples/jsm/',
            'mindar-face-three'=> 'https://cdn.jsdelivr.net/npm/mind-ar@1.2.5/dist/mindar-face-three.prod.js',
        );

        // cannon-es solo para earring_dangle (ahorra ~50 KB en stud)
        if ( $this->is_dangle_mode() ) {
            $imports['cannon-es'] = 'https://cdn.jsdelivr.net/npm/cannon-es@0.20.0/dist/cannon-es.js';
        }

        ?>
<script type="importmap">
{
  "imports": <?php echo wp_json_encode( $imports, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); ?>
}
</script>
        <?php
    }

    /* ---------------------------------------------------------------
     * Encolado principal
     * ------------------------------------------------------------- */
    public function enqueue_assets() {
        if ( ! $this->should_load() ) { return; }

        $glb       = self::get_product_glb_url();
        $usdz      = self::get_product_usdz_url();
        $ar_type   = self::get_product_ar_type();
        $acc_type  = self::get_product_accessory_type();

        wp_enqueue_style(
            'aureo-ar-frontend',
            AUREO_AR_URL . 'assets/css/frontend/frontend.css',
            array(),
            AUREO_AR_VERSION
        );

        wp_enqueue_script(
            'aureo-ar-ui',
            AUREO_AR_URL . 'assets/js/frontend/tryon-ui.js',
            array(),
            AUREO_AR_VERSION,
            true
        );

        wp_add_inline_script(
            'aureo-ar-ui',
            'window.AUREO_AR_CFG = ' . wp_json_encode( $this->build_config() ) . ';',
            'before'
        );

        if ( $ar_type === 'accessory' ) {
            wp_enqueue_script(
                'aureo-ar-tryon-face',
                AUREO_AR_URL . 'assets/js/frontend/tryon-face.js',
                array( 'aureo-ar-ui' ),
                AUREO_AR_VERSION,
                true
            );
        } elseif ( $ar_type === 'object' ) {
            wp_enqueue_script(
                'model-viewer-js',
                'https://ajax.googleapis.com/ajax/libs/model-viewer/3.4.0/model-viewer.min.js',
                array(),
                '3.4.0',
                true
            );
            wp_enqueue_script(
                'assets/js/frontend/tryon-object.js',
                AUREO_AR_URL . 'assets/js/tryon-object.js',
                array( 'aureo-ar-ui' ),
                AUREO_AR_VERSION,
                true
            );
        }
    }

    /**
     * Construye el objeto de configuración expuesto a JS.
     */
    private function build_config() {
        $cfg = array(
            'arType'        => self::get_product_ar_type(),
            'accessoryType' => self::get_product_accessory_type(),
            'glbUrl'        => self::get_product_glb_url(),
            'usdzUrl'       => self::get_product_usdz_url(),
            'pluginUrl'     => AUREO_AR_URL,
            'productName'   => get_the_title(),
        );

        // Parámetros físicos solo si es earring_dangle
        if ( $this->is_dangle_mode() ) {
            $cfg['physics'] = array(
                'chainLength' => self::get_product_chain_length(),
                'mass'        => self::get_product_earring_mass(),
                'damping'     => self::get_product_swing_damping(),
            );
        }

        return $cfg;
    }

    public function print_assets_fallback() {
        if ( ! $this->should_load() ) { return; }

        $ar_type = self::get_product_ar_type();

        if ( ! wp_style_is( 'aureo-ar-frontend', 'done' ) && ! wp_style_is( 'aureo-ar-frontend', 'enqueued' ) ) {
            echo '<link rel="stylesheet" href="' . esc_url( AUREO_AR_URL . 'assets/css/frontend/frontend.css?ver=' . AUREO_AR_VERSION ) . '">' . "\n";
        }

        if ( ! wp_script_is( 'aureo-ar-ui', 'done' ) && ! wp_script_is( 'aureo-ar-ui', 'enqueued' ) ) {
            echo '<script>window.AUREO_AR_CFG = ' . wp_json_encode( $this->build_config() ) . ';</script>' . "\n";
            echo '<script src="' . esc_url( AUREO_AR_URL . 'assets/js/frontend/tryon-ui.js?ver=' . AUREO_AR_VERSION ) . '"></script>' . "\n";

            if ( $ar_type === 'accessory' ) {
                echo '<script type="module" src="' . esc_url( AUREO_AR_URL . 'assets/js/frontend/tryon-face.js?ver=' . AUREO_AR_VERSION ) . '"></script>' . "\n";
            } elseif ( $ar_type === 'object' ) {
                echo '<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.4.0/model-viewer.min.js"></script>' . "\n";
                echo '<script src="' . esc_url( AUREO_AR_URL . 'assets/js/frontend/tryon-object.js?ver=' . AUREO_AR_VERSION ) . '"></script>' . "\n";
            }
        }
    }

    /* ---------------------------------------------------------------
     * Botón "Probar AR" / "Ver en espacio"
     * ------------------------------------------------------------- */
    public function render_ar_button() {
        if ( ! $this->should_load() ) { return; }
        $ar_type = self::get_product_ar_type();

        if ( $ar_type === 'accessory' ) {
            $icon  = '📷';
            $label = __( 'Probar en AR', 'aureo-ar' );
        } else {
            $icon  = '🏠';
            $label = __( 'Ver en espacio', 'aureo-ar' );
        }
        ?>
        <button type="button" id="aureo-ar-open-btn" class="button aureo-ar-btn" data-ar-type="<?php echo esc_attr( $ar_type ); ?>">
            <span class="aureo-ar-icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
            <?php echo esc_html( $label ); ?>
        </button>
        <?php
    }

    /* ---------------------------------------------------------------
     * Modal: se adapta según el tipo AR
     * ------------------------------------------------------------- */
    public function render_modal() {
        if ( ! $this->should_load() ) { return; }
        $ar_type = self::get_product_ar_type();

        if ( $ar_type === 'accessory' ) {
            $this->render_modal_accessory();
        } else {
            $this->render_modal_object();
        }
    }

    private function render_modal_accessory() {
        ?>
        <div id="aureo-ar-modal" class="aureo-ar-modal aureo-ar-modal--accessory" aria-hidden="true" role="dialog" aria-label="<?php esc_attr_e( 'Visor de Realidad Aumentada', 'aureo-ar' ); ?>">
            <div class="aureo-ar-modal__backdrop" data-aureo-close="1"></div>
            <div class="aureo-ar-modal__content">
                <button type="button" class="aureo-ar-modal__close" data-aureo-close="1" aria-label="<?php esc_attr_e( 'Cerrar', 'aureo-ar' ); ?>">&times;</button>
                <div class="aureo-ar-modal__header">
                    <h3><?php esc_html_e( 'Probador AR', 'aureo-ar' ); ?></h3>
                    <p class="aureo-ar-modal__hint"><?php esc_html_e( 'Permite el acceso a la cámara y mantén el rostro centrado.', 'aureo-ar' ); ?></p>
                </div>
                <div id="aureo-ar-stage" class="aureo-ar-stage">
                    <div id="aureo-ar-loader" class="aureo-ar-loader">
                        <div class="aureo-ar-spinner"></div>
                        <p><?php esc_html_e( 'Iniciando cámara y modelo...', 'aureo-ar' ); ?></p>
                    </div>
                    <div id="aureo-ar-error" class="aureo-ar-error" hidden></div>
                </div>
                <div class="aureo-ar-modal__footer">
                    <small><?php esc_html_e( 'La imagen de la cámara se procesa localmente en tu dispositivo.', 'aureo-ar' ); ?></small>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_modal_object() {
        $glb  = self::get_product_glb_url();
        $usdz = self::get_product_usdz_url();
        $alt  = get_the_title();
        ?>
        <div id="aureo-ar-modal" class="aureo-ar-modal aureo-ar-modal--object" aria-hidden="true" role="dialog" aria-label="<?php esc_attr_e( 'Visor 3D y AR', 'aureo-ar' ); ?>">
            <div class="aureo-ar-modal__backdrop" data-aureo-close="1"></div>
            <div class="aureo-ar-modal__content">
                <button type="button" class="aureo-ar-modal__close" data-aureo-close="1" aria-label="<?php esc_attr_e( 'Cerrar', 'aureo-ar' ); ?>">&times;</button>
                <div class="aureo-ar-modal__header">
                    <h3><?php esc_html_e( 'Ver en tu espacio', 'aureo-ar' ); ?></h3>
                    <p class="aureo-ar-modal__hint"><?php esc_html_e( 'Gira el modelo con el dedo. Toca "Ver en AR" para colocarlo en tu habitación.', 'aureo-ar' ); ?></p>
                </div>
                <div id="aureo-ar-stage" class="aureo-ar-stage aureo-ar-stage--object">
                    <model-viewer
                        id="aureo-ar-mv"
                        src="<?php echo esc_url( $glb ); ?>"
                        <?php if ( $usdz ) : ?>ios-src="<?php echo esc_url( $usdz ); ?>"<?php endif; ?>
                        alt="<?php echo esc_attr( $alt ); ?>"
                        ar
                        ar-modes="webxr scene-viewer quick-look"
                        ar-scale="auto"
                        ar-placement="floor"
                        camera-controls
                        touch-action="pan-y"
                        auto-rotate
                        rotation-per-second="20deg"
                        shadow-intensity="1"
                        exposure="1"
                        environment-image="neutral"
                        style="width:100%;height:100%;background:#1a1a1a;"
                    >
                        <button slot="ar-button" id="aureo-ar-launch-btn" class="aureo-ar-launch-btn">
                            <?php esc_html_e( '👁 Ver en AR', 'aureo-ar' ); ?>
                        </button>
                        <div id="aureo-ar-loader" class="aureo-ar-loader" slot="progress-bar">
                            <div class="aureo-ar-spinner"></div>
                            <p><?php esc_html_e( 'Cargando modelo 3D...', 'aureo-ar' ); ?></p>
                        </div>
                        <div id="aureo-ar-error" class="aureo-ar-error" hidden></div>
                    </model-viewer>
                </div>
                <div class="aureo-ar-modal__footer">
                    <small><?php esc_html_e( 'Requiere un dispositivo compatible con ARCore (Android) o ARKit (iOS).', 'aureo-ar' ); ?></small>
                </div>
            </div>
        </div>
        <?php
    }
}

new Aureo_AR_Frontend();