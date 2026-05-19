<?php
/**
 * Aureo AR — Planes WCFM (simulación parcial de Groups & Staffs)
 *
 * v2.4 — Widget "Mi plan" disponible en:
 *   - Inicio del dashboard del vendor
 *   - Editor de productos (excepto cuando ya hay banner de límite alcanzado)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Aureo_AR_Plans {

    const OPTION_KEY = 'aureo_ar_plans_config';

    const MEMBERSHIP_META_CANDIDATES = array(
        'wcfm_membership',
        'wcfmvm_membership',
        '_wcfm_membership',
    );

    public function __construct() {

        /* ── 1. Sincronización: ciclo de vida de la membresía ─────── */
        add_action( 'wcfmvm_after_membership_subscribe', array( $this, 'sync' ),       20, 2 );
        add_action( 'wcfmvm_membership_changed',         array( $this, 'sync' ),       20, 2 );
        add_action( 'wcfmvm_after_membership_renewal',   array( $this, 'sync' ),       20, 2 );
        add_action( 'wcfmvm_after_membership_expired',   array( $this, 'on_expired' ), 20, 2 );

        /* ── 2. Enforcement en runtime ───────────────────────────── */
        add_action( 'before_wcfm_products_manage', array( $this, 'block_if_over_limit' ) );
        add_filter( 'wcfm_is_allow_add_product',   array( $this, 'allow_add_product' ), 99 );
        add_filter( 'wcfm_product_types',          array( $this, 'filter_product_types' ), 99 );
        add_filter( 'wcfm_product_manager_args',   array( $this, 'validate_on_save' ), 99, 2 );
        add_action( 'after_wcfm_products_manage',  array( $this, 'inject_limit_check_script' ) );

        // 🔒 Bloqueo a nivel de WP
        add_filter( 'wp_insert_post_empty_content', array( $this, 'block_post_creation' ), 10, 2 );

        // 🎨 Ocultar botón "Añadir producto" cuando límite alcanzado
        add_action( 'wp_footer',    array( $this, 'maybe_hide_add_button' ) );
        add_action( 'admin_footer', array( $this, 'maybe_hide_add_button' ) );

        /* ── 3. Widget "Mi plan" en varias páginas del dashboard ──── */
        add_action( 'wcfm_after_dashboard_stats_box', array( $this, 'render_plan_widget' ), 10 );
        add_action( 'wcfm_product_filters_before',    array( $this, 'render_plan_widget' ), 5 );

        /* ── 4. Resync masivo manual ─────────────────────────────── */
        add_action( 'admin_init',    array( $this, 'maybe_resync_all' ) );
        add_action( 'admin_notices', array( $this, 'admin_resync_notice' ) );
    }

    /* =====================================================================
     * HELPER CRÍTICO: detectar membership_id del vendor
     * ===================================================================== */

    public static function resolve_membership_id( $vendor_id ) {
        if ( function_exists( 'wcfm_get_membership' ) ) {
            $original_user = get_current_user_id();
            wp_set_current_user( $vendor_id );
            $mid = wcfm_get_membership();
            if ( $original_user ) { wp_set_current_user( $original_user ); }
            if ( $mid ) {
                return intval( $mid );
            }
        }

        foreach ( self::MEMBERSHIP_META_CANDIDATES as $key ) {
            $value = get_user_meta( $vendor_id, $key, true );
            if ( ! empty( $value ) ) {
                return intval( $value );
            }
        }

        return null;
    }

    /* =====================================================================
     * CONFIGURACIÓN DE PLANES
     * ===================================================================== */

    public static function get_plans() {
        $plans = get_option( self::OPTION_KEY, array() );
        return is_array( $plans ) ? $plans : array();
    }

    public static function get_plan( $membership_id ) {
        $plans         = self::get_plans();
        $membership_id = intval( $membership_id );
        return isset( $plans[ $membership_id ] ) ? $plans[ $membership_id ] : null;
    }

    public static function get_vendor_plan( $vendor_id ) {
        $mid = self::resolve_membership_id( $vendor_id );
        if ( ! $mid ) { return null; }
        return self::get_plan( $mid );
    }

    private function get_free_plan_id() {
        foreach ( self::get_plans() as $id => $plan ) {
            if ( ! empty( $plan['slug'] ) && $plan['slug'] === 'free' ) {
                return $id;
            }
        }
        return null;
    }

    /* =====================================================================
     * 1. SINCRONIZACIÓN
     * ===================================================================== */

    public function sync( $vendor_id, $membership_id ) {
        $plan = self::get_plan( $membership_id );

        if ( ! $plan ) {
            $this->log( "sync() abortado: plan {$membership_id} no configurado para vendor {$vendor_id}" );
            return;
        }

        update_user_meta( $vendor_id, '_wcfm_vendor_custom_capability', 'yes' );

        $limit = isset( $plan['product_limit'] ) ? intval( $plan['product_limit'] ) : -1;
        update_user_meta( $vendor_id, '_wcfm_product_limit', $limit );

        $types_meta = array();
        $types      = isset( $plan['product_types'] ) && is_array( $plan['product_types'] )
                      ? $plan['product_types']
                      : array( 'simple' );

        foreach ( $types as $type ) {
            $types_meta[ $type ] = 'yes';
        }
        update_user_meta( $vendor_id, '_wcfm_product_types', $types_meta );

        update_user_meta( $vendor_id, '_aureo_ar_plan_slug', $plan['slug'] );

        $this->log( "sync() OK: vendor {$vendor_id} → plan '{$plan['slug']}' (limit={$limit}, types=" . implode( ',', $types ) . ")" );

        do_action( 'aureo_ar_plan_synced', $vendor_id, $membership_id, $plan );
    }

    public function on_expired( $vendor_id, $membership_id ) {
        $free_id = $this->get_free_plan_id();
        if ( $free_id ) {
            foreach ( self::MEMBERSHIP_META_CANDIDATES as $key ) {
                if ( get_user_meta( $vendor_id, $key, true ) ) {
                    update_user_meta( $vendor_id, $key, $free_id );
                }
            }
            $this->sync( $vendor_id, $free_id );
        }
    }

    /* =====================================================================
     * 2. ENFORCEMENT EN RUNTIME
     * ===================================================================== */

    private function count_vendor_products( $vendor_id ) {
        $query = new WP_Query( array(
            'post_type'      => 'product',
            'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
            'author'         => $vendor_id,
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => false,
        ) );
        return $query->found_posts;
    }

    public function block_if_over_limit() {
        $plan = self::get_vendor_plan( get_current_user_id() );
        if ( ! $plan ) { return; }

        $limit = intval( $plan['product_limit'] );
        if ( $limit < 0 ) { return; }

        global $wp;
        if ( ! empty( $wp->query_vars['wcfm-products-manage'] ) ) { return; }

        $count = $this->count_vendor_products( get_current_user_id() );
        if ( $count < $limit ) { return; }

        // Red de seguridad: el banner del editor maneja el caso visual
    }

    public function allow_add_product( $allow ) {
        $plan = self::get_vendor_plan( get_current_user_id() );
        if ( ! $plan ) { return $allow; }

        $limit = intval( $plan['product_limit'] );
        if ( $limit < 0 ) { return $allow; }

        $count = $this->count_vendor_products( get_current_user_id() );
        return ( $count < $limit ) ? $allow : false;
    }

    public function filter_product_types( $types ) {
        $plan = self::get_vendor_plan( get_current_user_id() );
        if ( ! $plan ) { return $types; }

        $allowed = isset( $plan['product_types'] ) && is_array( $plan['product_types'] )
                   ? array_flip( $plan['product_types'] )
                   : array();

        if ( empty( $allowed ) ) { return $types; }

        return array_intersect_key( $types, $allowed );
    }

    public function validate_on_save( $args, $form_data ) {
        $plan = self::get_vendor_plan( get_current_user_id() );
        if ( ! $plan ) { return $args; }

        $is_new_product = empty( $args['ID'] ) && empty( $args['id'] );

        if ( $is_new_product ) {
            $limit = intval( $plan['product_limit'] );
            if ( $limit >= 0 ) {
                $count = $this->count_vendor_products( get_current_user_id() );
                if ( $count >= $limit ) {
                    $message = sprintf(
                        __( 'Has alcanzado el límite de %1$d productos de tu plan %2$s. Actualiza tu plan para añadir más.', 'aureo-ar' ),
                        $limit,
                        strtoupper( $plan['slug'] )
                    );
                    $this->send_wcfm_error( $message );
                }
            }
        }

        $allowed = isset( $plan['product_types'] ) ? $plan['product_types'] : array();

        if ( isset( $args['product-type'] ) && ! in_array( $args['product-type'], $allowed, true ) ) {
            $message = sprintf(
                __( 'Tu plan %1$s no permite crear productos del tipo: %2$s', 'aureo-ar' ),
                strtoupper( $plan['slug'] ),
                $args['product-type']
            );
            $this->send_wcfm_error( $message );
        }

        return $args;
    }

    private function send_wcfm_error( $message ) {
        if ( ob_get_length() ) { ob_end_clean(); }
        if ( ! headers_sent() ) {
            header( 'Content-Type: application/json; charset=utf-8' );
        }
        echo wp_json_encode( array(
            'status'   => false,
            'message'  => $message,
            'redirect' => null,
        ) );
        exit;
    }

    /* =====================================================================
     * 3. WIDGET "MI PLAN" (renderizado en múltiples páginas)
     * ===================================================================== */

    /**
     * Renderiza el cuadro "Mi plan" en el dashboard del vendor.
     * Se conecta a 2 hooks:
     *   - wcfm_after_dashboard_stats_box  → inicio del dashboard
     *   - before_wcfm_products_manage     → editor de productos
     *
     * En el editor, se autoexcluye cuando el vendor está al límite creando
     * un producto nuevo, porque en ese caso el banner del editor ya cubre
     * la información crítica y mostrar ambos sería redundante.
     */
    public function render_plan_widget() {

        if ( ! function_exists( 'wcfm_is_vendor' ) || ! wcfm_is_vendor() ) {
            return;
        }

        $vendor_id = get_current_user_id();
        $plan      = self::get_vendor_plan( $vendor_id );

        if ( ! $plan ) { return; }

        $slug         = isset( $plan['slug'] ) ? strtoupper( $plan['slug'] ) : '—';
        $limit        = isset( $plan['product_limit'] ) ? intval( $plan['product_limit'] ) : -1;
        $count        = $this->count_vendor_products( $vendor_id );
        $is_unlimited = ( $limit < 0 );

        $percent = ( $is_unlimited || $limit === 0 )
                   ? 0
                   : min( 100, round( ( $count / $limit ) * 100 ) );

        $bar_color = '#28a745';
        if ( $percent >= 90 )      { $bar_color = '#dc3545'; }
        elseif ( $percent >= 70 )  { $bar_color = '#ffc107'; }

        $usage_text = $is_unlimited
            ? sprintf( esc_html__( '%d productos · sin límite', 'aureo-ar' ), $count )
            : sprintf( esc_html__( '%1$d / %2$d productos usados', 'aureo-ar' ), $count, $limit );

        $badge_bg = '#6c757d';
        if ( strtolower( $slug ) === 'free' )    { $badge_bg = '#6c757d'; }
        if ( strtolower( $slug ) === 'pro' )     { $badge_bg = '#0d6efd'; }
        if ( strtolower( $slug ) === 'premium' ) { $badge_bg = '#6f42c1'; }
        ?>

        <div class="wcfm_dashboard_aureo_plan">
            <div class="wcfm_dashboard_aureo_plan_data">
                <div class="page_collapsible" id="wcfm_dashboard_aureo_plan_widget">
                    <span class="fa fa-id-badge"></span>
                    <span class="dashboard_widget_head">
                        <?php esc_html_e( 'Mi plan', 'aureo-ar' ); ?>
                    </span>
                </div>

                <div class="wcfm-container">
                    <div id="wcfm_dashboard_aureo_plan_expander" class="wcfm-content">
                        <div class="aureo-plan-widget">

                            <div class="aureo-plan-widget__header">
                                <span class="aureo-plan-widget__label">
                                    <?php esc_html_e( 'Plan actual', 'aureo-ar' ); ?>
                                </span>
                                <span class="aureo-plan-widget__badge"
                                      style="background:<?php echo esc_attr( $badge_bg ); ?>;">
                                    <?php echo esc_html( $slug ); ?>
                                </span>
                            </div>

                            <div class="aureo-plan-widget__usage">
                                <?php echo esc_html( $usage_text ); ?>
                                <?php if ( ! $is_unlimited ) : ?>
                                    <span class="aureo-plan-widget__percent">
                                        (<?php echo esc_html( $percent ); ?>%)
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ( ! $is_unlimited ) : ?>
                            <div class="aureo-plan-widget__bar">
                                <div class="aureo-plan-widget__bar-fill"
                                     style="width:<?php echo esc_attr( $percent ); ?>%;
                                            background:<?php echo esc_attr( $bar_color ); ?>;"></div>
                            </div>
                            <?php endif; ?>

                            <?php if ( ! $is_unlimited && $count >= $limit ) : ?>
                                <p class="aureo-plan-widget__warning">
                                    ⚠️ <?php esc_html_e( 'Has alcanzado el límite de tu plan. Actualiza para añadir más productos.', 'aureo-ar' ); ?>
                                </p>
                            <?php elseif ( ! $is_unlimited && $percent >= 70 ) : ?>
                                <p class="aureo-plan-widget__notice">
                                    <?php
                                    printf(
                                        esc_html__( 'Te quedan %d productos disponibles.', 'aureo-ar' ),
                                        max( 0, $limit - $count )
                                    );
                                    ?>
                                </p>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .wcfm_dashboard_aureo_plan { margin-top: 15px; }
            .aureo-plan-widget { padding: 15px 5px; }
            .aureo-plan-widget__header {
                display: flex; align-items: center; justify-content: space-between;
                margin-bottom: 12px;
            }
            .aureo-plan-widget__label {
                color: #666; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;
            }
            .aureo-plan-widget__badge {
                color: #fff; padding: 4px 12px; border-radius: 12px;
                font-size: 12px; font-weight: 600; letter-spacing: 0.5px;
            }
            .aureo-plan-widget__usage {
                font-size: 16px; color: #333; margin-bottom: 8px; font-weight: 500;
            }
            .aureo-plan-widget__percent { color: #999; font-size: 13px; font-weight: normal; }
            .aureo-plan-widget__bar {
                width: 100%; height: 8px;
                background: #e9ecef; border-radius: 4px; overflow: hidden;
            }
            .aureo-plan-widget__bar-fill { height: 100%; transition: width 0.3s ease; }
            .aureo-plan-widget__warning {
                margin: 12px 0 0; padding: 8px 12px;
                background: #f8d7da; color: #721c24; border-radius: 4px;
                font-size: 13px;
            }
            .aureo-plan-widget__notice {
                margin: 12px 0 0; padding: 8px 12px;
                background: #fff3cd; color: #856404; border-radius: 4px;
                font-size: 13px;
            }
        </style>

        <?php
    }

    /* =====================================================================
     * 4. RESYNC MASIVO
     * ===================================================================== */

    public function maybe_resync_all() {
        if ( ! current_user_can( 'manage_options' ) )       { return; }
        if ( empty( $_GET['aureo_ar_resync_plans'] ) )      { return; }
        if ( ! check_admin_referer( 'aureo_ar_resync' ) )   { return; }

        $vendors = get_users( array(
            'role__in' => array( 'wcfm_vendor', 'seller', 'vendor', 'shop_vendor' ),
            'fields'   => 'ID',
        ) );

        $synced  = 0;
        $skipped = 0;

        foreach ( $vendors as $vid ) {
            $mid = self::resolve_membership_id( $vid );
            if ( $mid && self::get_plan( $mid ) ) {
                $this->sync( $vid, $mid );
                $synced++;
            } else {
                $skipped++;
                $this->log( "resync skip: vendor {$vid} (mid=" . var_export( $mid, true ) . ")" );
            }
        }

        set_transient( 'aureo_ar_resync_count', array( 'synced' => $synced, 'skipped' => $skipped ), 60 );

        $redirect = remove_query_arg( array( 'aureo_ar_resync_plans', '_wpnonce' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    public function admin_resync_notice() {
        $data = get_transient( 'aureo_ar_resync_count' );
        if ( $data === false ) { return; }
        delete_transient( 'aureo_ar_resync_count' );

        if ( ! is_array( $data ) ) {
            $data = array( 'synced' => intval( $data ), 'skipped' => 0 );
        }

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            sprintf(
                esc_html__( 'Aureo AR — Planes: %1$d vendors sincronizados, %2$d omitidos (sin plan configurado).', 'aureo-ar' ),
                intval( $data['synced'] ),
                intval( $data['skipped'] )
            )
        );
    }

    /* =====================================================================
     * 5. BLOQUEO A NIVEL DE WP CORE (red de seguridad final)
     * ===================================================================== */

    public function block_post_creation( $maybe_empty, $postarr ) {

        if ( empty( $postarr['post_type'] ) || $postarr['post_type'] !== 'product' ) {
            return $maybe_empty;
        }

        $author_id = isset( $postarr['post_author'] )
                     ? intval( $postarr['post_author'] )
                     : get_current_user_id();

        if ( ! $author_id ) { return $maybe_empty; }

        if ( ! empty( $postarr['ID'] ) ) {
            return $maybe_empty;
        }

        $plan = self::get_vendor_plan( $author_id );
        if ( ! $plan ) { return $maybe_empty; }

        $limit = intval( $plan['product_limit'] );
        if ( $limit < 0 ) { return $maybe_empty; }

        $count = $this->count_vendor_products( $author_id );

        if ( $count >= $limit ) {
            if ( wp_doing_ajax() ) {
                $this->send_wcfm_error( sprintf(
                    __( 'Has alcanzado el límite de %1$d productos de tu plan %2$s.', 'aureo-ar' ),
                    $limit,
                    strtoupper( $plan['slug'] )
                ) );
            }

            wp_die(
                sprintf(
                    esc_html__( 'Has alcanzado el límite de %1$d productos de tu plan %2$s.', 'aureo-ar' ),
                    $limit,
                    esc_html( strtoupper( $plan['slug'] ) )
                ),
                esc_html__( 'Límite alcanzado', 'aureo-ar' ),
                array( 'back_link' => true )
            );
        }

        return $maybe_empty;
    }

    /* =====================================================================
     * 6. OCULTAR BOTÓN "AÑADIR PRODUCTO" CUANDO LÍMITE ALCANZADO
     * ===================================================================== */

    public function maybe_hide_add_button() {

        if ( ! function_exists( 'wcfm_is_vendor' ) || ! wcfm_is_vendor() ) {
            return;
        }

        $plan = self::get_vendor_plan( get_current_user_id() );
        if ( ! $plan ) { return; }

        $limit = intval( $plan['product_limit'] );
        if ( $limit < 0 ) { return; }

        $count = $this->count_vendor_products( get_current_user_id() );
        if ( $count < $limit ) { return; }

        ?>
        <style>
            a[href*="wcfm-products-manage"][href*="action=new"],
            a.wcfm_products_add_new,
            a.text_tip[href*="products-manage"]:not([href*="?"]),
            .wcfm-products-add-new,
            .wcfm_products_add_new_btn,
            #wcfm_products_listing_wrapper .wcfm_submit_button[href*="action=new"] {
                display: none !important;
            }
        </style>
        <script>
        (function($) {
            $(function() {
                $('a, button').each(function() {
                    var $el  = $(this);
                    var text = ($el.text() || '').trim().toLowerCase();
                    var href = ($el.attr('href') || '').toLowerCase();

                    var isAddProductLink = (
                        href.indexOf('wcfm-products-manage') !== -1 &&
                        (href.indexOf('action=new') !== -1 || !href.match(/wcfm-products-manage[\/=]\d+/))
                    );

                    var isAddProductText = (
                        text.indexOf('añadir producto')   !== -1 ||
                        text.indexOf('agregar producto')  !== -1 ||
                        text.indexOf('add new product')   !== -1 ||
                        text.indexOf('nuevo producto')    !== -1
                    );

                    if (isAddProductLink && isAddProductText) {
                        $el.hide();
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    /* =====================================================================
     * 7. BLOQUEO PREVENTIVO EN EL EDITOR (banner + bloqueo de submit)
     * ===================================================================== */

    public function inject_limit_check_script() {

        if ( ! function_exists( 'wcfm_is_vendor' ) || ! wcfm_is_vendor() ) {
            return;
        }

        $plan = self::get_vendor_plan( get_current_user_id() );
        if ( ! $plan ) { return; }

        $limit = intval( $plan['product_limit'] );
        if ( $limit < 0 ) { return; }

        global $wp;
        $is_editing_existing = ! empty( $wp->query_vars['wcfm-products-manage'] );
        if ( $is_editing_existing ) { return; }

        $count = $this->count_vendor_products( get_current_user_id() );
        if ( $count < $limit ) { return; }

        $message = sprintf(
            __( 'Has alcanzado el límite de %1$d productos de tu plan %2$s.', 'aureo-ar' ),
            $limit,
            strtoupper( $plan['slug'] )
        );

        $dashboard_url = function_exists( 'get_wcfm_url' ) ? get_wcfm_url() : home_url();
        ?>
        <script>
        (function($){
            $(function(){
                var msg          = <?php echo wp_json_encode( $message ); ?>;
                var dashboardUrl = <?php echo wp_json_encode( $dashboard_url ); ?>;

                $(document).on('submit', '#wcfm_products_manage_form', function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    alert(msg);
                    window.location.href = dashboardUrl;
                    return false;
                });

                $(document).on('click', '.wcfm_submit_button, button[type="submit"]', function(e) {
                    if ($(this).closest('#wcfm_products_manage_form').length) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        alert(msg);
                        window.location.href = dashboardUrl;
                        return false;
                    }
                });

                var $form = $('#wcfm_products_manage_form');
                if ($form.length) {
                    $form.before(
                        '<div style="background:#f8d7da;color:#721c24;padding:15px 20px;' +
                        'margin-bottom:20px;border-radius:6px;border:1px solid #f5c6cb;' +
                        'font-size:15px;font-weight:500;line-height:1.5;">' +
                        '⚠️ ' + msg +
                        ' <a href="' + dashboardUrl + '" ' +
                        'style="color:#721c24;text-decoration:underline;margin-left:10px;font-weight:600;">' +
                        '← Volver al dashboard</a></div>'
                    );
                }
            });
        })(jQuery);
        </script>
        <?php
    }

    /* =====================================================================
     * 8. API PÚBLICA: Cambio manual de plan por admin
     * ===================================================================== */

    /**
     * Cambia el plan de un vendor de forma segura y consistente.
     * Replica el flujo completo que haría WCFM al subscribirse.
     *
     * @param int  $vendor_id   ID del usuario vendor
     * @param int  $new_plan_id ID del wcfm_memberships del nuevo plan
     * @param bool $send_email  Notificar al vendor por email
     * @return true|WP_Error
     */
    public static function change_vendor_plan( $vendor_id, $new_plan_id, $send_email = false ) {

        $vendor_id   = intval( $vendor_id );
        $new_plan_id = intval( $new_plan_id );

        $user = get_userdata( $vendor_id );
        if ( ! $user ) {
            return new WP_Error( 'invalid_vendor', __( 'Usuario no encontrado.', 'aureo-ar' ) );
        }

        $plan_post = get_post( $new_plan_id );
        if ( ! $plan_post || $plan_post->post_type !== 'wcfm_memberships' ) {
            return new WP_Error( 'invalid_plan', __( 'Plan WCFM no encontrado.', 'aureo-ar' ) );
        }

        $plan_config = self::get_plan( $new_plan_id );
        $old_plan_id = self::resolve_membership_id( $vendor_id );

        // 1. Actualizar todos los meta keys de membership
        foreach ( self::MEMBERSHIP_META_CANDIDATES as $key ) {
            update_user_meta( $vendor_id, $key, $new_plan_id );
        }

        // 2. Estado de suscripción activa y gratuita (sin pago)
        update_user_meta( $vendor_id, 'wcfm_membership_paymode',     'free' );
        update_user_meta( $vendor_id, 'wcfm_subscription_status',    'active' );
        update_user_meta( $vendor_id, 'wcfm_subscription_type',      'free' );
        update_user_meta( $vendor_id, 'wcfm_membership_subscribe_on', time() );
        delete_user_meta( $vendor_id, 'wcfm_transaction_id' );

        // 3. Aplicar capabilities (si el plan está configurado en Aureo AR)
        if ( $plan_config ) {
            $instance = new self();
            $instance->sync( $vendor_id, $new_plan_id );
        }

        // 4. Disparar hook nativo de WCFM
        do_action( 'wcfmvm_membership_changed', $vendor_id, $new_plan_id );

        // 5. Hook propio
        do_action( 'aureo_ar_vendor_plan_changed_by_admin', $vendor_id, $new_plan_id, $old_plan_id );

        // 6. Email opcional
        if ( $send_email ) {
            self::send_plan_change_email( $vendor_id, $new_plan_id, $plan_post->post_title );
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( "[Aureo_AR_Plans] Admin cambió plan: vendor={$vendor_id}, {$old_plan_id} → {$new_plan_id}" );
        }

        return true;
    }

    /**
     * Email simple al vendor notificando el cambio de plan.
     */
    private static function send_plan_change_email( $vendor_id, $new_plan_id, $plan_name ) {
        $user = get_userdata( $vendor_id );
        if ( ! $user || ! $user->user_email ) { return false; }

        $site_name = get_bloginfo( 'name' );
        $dashboard = function_exists( 'get_wcfm_url' ) ? get_wcfm_url() : home_url();

        $subject = sprintf( __( '[%s] Tu plan ha sido actualizado', 'aureo-ar' ), $site_name );

        $body  = sprintf( __( 'Hola %s,', 'aureo-ar' ), $user->display_name ) . "\n\n";
        $body .= sprintf(
            __( 'Te informamos que el equipo de %1$s ha actualizado tu plan de membresía a: %2$s.', 'aureo-ar' ),
            $site_name,
            $plan_name
        ) . "\n\n";
        $body .= __( 'Los cambios ya están activos en tu cuenta.', 'aureo-ar' ) . "\n\n";
        $body .= sprintf( __( 'Accede a tu panel: %s', 'aureo-ar' ), $dashboard ) . "\n\n";
        $body .= __( 'Saludos,', 'aureo-ar' ) . "\n" . $site_name;

        return wp_mail( $user->user_email, $subject, $body );
    }

    /* =====================================================================
     * LOGGING INTERNO
     * ===================================================================== */

    private function log( $msg ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( '[Aureo_AR_Plans] ' . $msg );
        }
    }
}

new Aureo_AR_Plans();