<?php
/**
 * Tab "Vendors" — Aureo AR
 *
 * Lista todos los vendors del marketplace y permite cambiarles el plan
 * de membresía manualmente sin pasar por el flujo de pago de WCFM.
 *
 * El cambio se hace vía Aureo_AR_Plans::change_vendor_plan() que se encarga de:
 *   - Actualizar meta keys de WCFM
 *   - Setear suscripción como activa/free
 *   - Aplicar capabilities del nuevo plan
 *   - Disparar hooks de WCFM para compatibilidad
 *   - Enviar email al vendor (opcional)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Aureo_AR_Plans' ) ) {
    echo '<h3>Vendors</h3>';
    echo '<p><strong>WCFM Membership</strong> no está activo.</p>';
    return;
}

/* =====================================================================
 * 1. Procesar cambio de plan (POST)
 * ===================================================================== */

if ( isset( $_POST['aureo_ar_change_plan'] ) && check_admin_referer( 'aureo_ar_change_vendor_plan' ) ) {

    $vendor_id   = isset( $_POST['vendor_id'] )   ? intval( $_POST['vendor_id'] )   : 0;
    $new_plan_id = isset( $_POST['new_plan_id'] ) ? intval( $_POST['new_plan_id'] ) : 0;
    $send_email  = ! empty( $_POST['send_email'] );

    if ( $vendor_id && $new_plan_id ) {
        $result = Aureo_AR_Plans::change_vendor_plan( $vendor_id, $new_plan_id, $send_email );

        if ( is_wp_error( $result ) ) {
            printf(
                '<div class="notice notice-error is-dismissible"><p>❌ %s</p></div>',
                esc_html( $result->get_error_message() )
            );
        } else {
            $vendor    = get_userdata( $vendor_id );
            $plan_post = get_post( $new_plan_id );
            $email_msg = $send_email ? __( ' Email enviado al vendor.', 'aureo-ar' ) : '';

            printf(
                '<div class="notice notice-success is-dismissible"><p>✅ %s%s</p></div>',
                sprintf(
                    esc_html__( 'Plan de %1$s cambiado a "%2$s".', 'aureo-ar' ),
                    '<strong>' . esc_html( $vendor->display_name ) . '</strong>',
                    '<strong>' . esc_html( $plan_post->post_title ) . '</strong>'
                ),
                esc_html( $email_msg )
            );
        }
    }
}

/* =====================================================================
 * 2. Filtros y búsqueda
 * ===================================================================== */

$search        = isset( $_GET['s'] )         ? sanitize_text_field( $_GET['s'] ) : '';
$filter_plan   = isset( $_GET['plan'] )      ? intval( $_GET['plan'] )           : 0;
$current_page  = isset( $_GET['paged'] )     ? max( 1, intval( $_GET['paged'] ) ): 1;
$per_page      = 20;

/* =====================================================================
 * 3. Obtener vendors
 * ===================================================================== */

$args = array(
    'role__in' => array( 'wcfm_vendor', 'seller', 'vendor', 'shop_vendor' ),
    'number'   => $per_page,
    'offset'   => ( $current_page - 1 ) * $per_page,
    'orderby'  => 'display_name',
    'order'    => 'ASC',
);

if ( $search ) {
    $args['search']         = '*' . esc_attr( $search ) . '*';
    $args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
}

$query   = new WP_User_Query( $args );
$vendors = $query->get_results();
$total   = $query->get_total();

/* ── Filtrar por plan en PHP (no podemos hacer JOIN limpio en WP_User_Query) ── */
if ( $filter_plan ) {
    $vendors = array_filter( $vendors, function( $v ) use ( $filter_plan ) {
        return Aureo_AR_Plans::resolve_membership_id( $v->ID ) === $filter_plan;
    } );
}

/* =====================================================================
 * 4. Cargar planes disponibles
 * ===================================================================== */

$all_memberships = get_posts( array(
    'post_type'      => 'wcfm_memberships',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
) );

$plans_config = Aureo_AR_Plans::get_plans();

/* =====================================================================
 * 5. Render
 * ===================================================================== */
?>

<style>
    .aureo-vendors-toolbar {
        display: flex; gap: 10px; align-items: center;
        margin: 15px 0; flex-wrap: wrap;
    }
    .aureo-vendors-toolbar input[type="search"] { min-width: 220px; }

    .aureo-vendors-table { border-collapse: collapse; width: 100%; margin-top: 10px; }
    .aureo-vendors-table th, .aureo-vendors-table td {
        border: 1px solid #e0e0e0; padding: 12px; vertical-align: middle;
    }
    .aureo-vendors-table th { background: #f6f7f7; text-align: left; }
    .aureo-vendors-table tr:hover { background: #fafafa; }

    .aureo-vendor-name    { font-weight: 600; }
    .aureo-vendor-meta    { color: #666; font-size: 12px; margin-top: 3px; }
    .aureo-vendor-meta a  { text-decoration: none; }

    .aureo-plan-pill {
        display: inline-block; padding: 3px 10px; border-radius: 10px;
        color: #fff; font-size: 11px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    .aureo-plan-pill--free    { background: #6c757d; }
    .aureo-plan-pill--pro     { background: #0d6efd; }
    .aureo-plan-pill--premium { background: #6f42c1; }
    .aureo-plan-pill--none    { background: #dc3545; }

    .aureo-change-form {
        display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
    }
    .aureo-change-form select { max-width: 180px; }
    .aureo-change-form label  {
        font-size: 12px; color: #666; display: flex; align-items: center; gap: 4px;
    }
    .aureo-empty {
        padding: 30px; text-align: center; color: #888;
        background: #fafafa; border: 1px dashed #ddd; border-radius: 4px;
    }
    .aureo-pagination { margin-top: 20px; text-align: right; }
    .aureo-pagination a, .aureo-pagination span {
        display: inline-block; padding: 5px 10px; margin: 0 2px;
        border: 1px solid #ddd; text-decoration: none; border-radius: 3px;
    }
    .aureo-pagination .current { background: #2271b1; color: #fff; border-color: #2271b1; }
</style>

<h3>Gestión de Vendors</h3>

<p class="description" style="margin-bottom:15px;">
    Cambia manualmente el plan de cualquier vendor sin pasar por el flujo de pago.
    Las capacidades del nuevo plan se aplican automáticamente.
</p>

<!-- ── Filtros y búsqueda ── -->
<form method="get" class="aureo-vendors-toolbar">
    <input type="hidden" name="page" value="aureo-ar-settings" />
    <input type="hidden" name="tab"  value="vendors" />

    <input type="search"
           name="s"
           value="<?php echo esc_attr( $search ); ?>"
           placeholder="<?php esc_attr_e( 'Buscar por nombre, email o usuario…', 'aureo-ar' ); ?>" />

    <select name="plan">
        <option value="0"><?php esc_html_e( 'Todos los planes', 'aureo-ar' ); ?></option>
        <?php foreach ( $all_memberships as $m ) : ?>
            <option value="<?php echo esc_attr( $m->ID ); ?>"
                <?php selected( $filter_plan, $m->ID ); ?>>
                <?php echo esc_html( $m->post_title ); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'aureo-ar' ); ?></button>

    <?php if ( $search || $filter_plan ) : ?>
        <a href="?page=aureo-ar-settings&tab=vendors" class="button">
            <?php esc_html_e( 'Limpiar', 'aureo-ar' ); ?>
        </a>
    <?php endif; ?>

    <span style="margin-left:auto;color:#666;font-size:13px;">
        <?php
        printf(
            esc_html__( '%d vendors en total', 'aureo-ar' ),
            intval( $total )
        );
        ?>
    </span>
</form>

<!-- ── Tabla ── -->
<?php if ( empty( $vendors ) ) : ?>

    <div class="aureo-empty">
        <?php esc_html_e( 'No se encontraron vendors con esos criterios.', 'aureo-ar' ); ?>
    </div>

<?php else : ?>

    <table class="aureo-vendors-table">
        <thead>
            <tr>
                <th style="width: 30%;"><?php esc_html_e( 'Vendor', 'aureo-ar' ); ?></th>
                <th style="width: 18%;"><?php esc_html_e( 'Plan actual', 'aureo-ar' ); ?></th>
                <th style="width: 12%;"><?php esc_html_e( 'Productos', 'aureo-ar' ); ?></th>
                <th><?php esc_html_e( 'Cambiar plan', 'aureo-ar' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $vendors as $vendor ) :
                $current_plan_id = Aureo_AR_Plans::resolve_membership_id( $vendor->ID );
                $current_plan    = $current_plan_id ? Aureo_AR_Plans::get_plan( $current_plan_id ) : null;
                $current_post    = $current_plan_id ? get_post( $current_plan_id ) : null;

                $slug      = $current_plan ? strtolower( $current_plan['slug'] ) : 'none';
                $pill_text = $current_post ? $current_post->post_title : __( 'Sin plan', 'aureo-ar' );

                $product_count = ( new WP_Query( array(
                    'post_type'      => 'product',
                    'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
                    'author'         => $vendor->ID,
                    'fields'         => 'ids',
                    'posts_per_page' => -1,
                    'no_found_rows'  => false,
                ) ) )->found_posts;

                $limit = $current_plan ? intval( $current_plan['product_limit'] ) : null;
                $limit_display = ( $limit === null ) ? '—' : ( $limit < 0 ? '∞' : $limit );

                $store_url = function_exists( 'wcfmmp_get_store_url' )
                             ? wcfmmp_get_store_url( $vendor->ID )
                             : '';
                $edit_url  = get_edit_user_link( $vendor->ID );
            ?>
            <tr>
                <td>
                    <div class="aureo-vendor-name"><?php echo esc_html( $vendor->display_name ); ?></div>
                    <div class="aureo-vendor-meta">
                        <?php echo esc_html( $vendor->user_email ); ?>
                        &nbsp;·&nbsp; ID: <?php echo intval( $vendor->ID ); ?>
                        <?php if ( $store_url ) : ?>
                            &nbsp;·&nbsp; <a href="<?php echo esc_url( $store_url ); ?>" target="_blank">tienda ↗</a>
                        <?php endif; ?>
                        <?php if ( $edit_url ) : ?>
                            &nbsp;·&nbsp; <a href="<?php echo esc_url( $edit_url ); ?>" target="_blank">editar usuario ↗</a>
                        <?php endif; ?>
                    </div>
                </td>

                <td>
                    <span class="aureo-plan-pill aureo-plan-pill--<?php echo esc_attr( $slug ); ?>">
                        <?php echo esc_html( $pill_text ); ?>
                    </span>
                    <?php if ( $current_plan_id && ! $current_plan ) : ?>
                        <div style="font-size:11px;color:#dc3545;margin-top:4px;">
                            ⚠️ <?php esc_html_e( 'Plan no configurado en Aureo AR', 'aureo-ar' ); ?>
                        </div>
                    <?php endif; ?>
                </td>

                <td>
                    <?php echo intval( $product_count ); ?> / <?php echo esc_html( $limit_display ); ?>
                </td>

                <td>
                    <form method="post" class="aureo-change-form"
                          onsubmit="return confirm('<?php echo esc_js( __( '¿Confirmar cambio de plan?', 'aureo-ar' ) ); ?>');">

                        <?php wp_nonce_field( 'aureo_ar_change_vendor_plan' ); ?>
                        <input type="hidden" name="vendor_id" value="<?php echo esc_attr( $vendor->ID ); ?>" />

                        <select name="new_plan_id" required>
                            <?php foreach ( $all_memberships as $m ) : ?>
                                <option value="<?php echo esc_attr( $m->ID ); ?>"
                                    <?php selected( $current_plan_id, $m->ID ); ?>>
                                    <?php echo esc_html( $m->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label>
                            <input type="checkbox" name="send_email" value="1" />
                            <?php esc_html_e( 'Notificar por email', 'aureo-ar' ); ?>
                        </label>

                        <button type="submit" name="aureo_ar_change_plan" class="button button-primary button-small">
                            <?php esc_html_e( 'Aplicar', 'aureo-ar' ); ?>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ── Paginación ── -->
    <?php
    $total_pages = max( 1, ceil( $total / $per_page ) );
    if ( $total_pages > 1 ) :
        $base_url = add_query_arg( array(
            'page' => 'aureo-ar-settings',
            'tab'  => 'vendors',
            's'    => $search,
            'plan' => $filter_plan,
        ), admin_url( 'admin.php' ) );
    ?>
        <div class="aureo-pagination">
            <?php
            for ( $i = 1; $i <= $total_pages; $i++ ) {
                $url = add_query_arg( 'paged', $i, $base_url );
                if ( $i === $current_page ) {
                    echo '<span class="current">' . $i . '</span>';
                } else {
                    echo '<a href="' . esc_url( $url ) . '">' . $i . '</a>';
                }
            }
            ?>
        </div>
    <?php endif; ?>

<?php endif; ?>