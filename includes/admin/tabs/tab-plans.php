<?php
/**
 * Tab "Planes" — Aureo AR
 *
 * Configura por cada plan de membresía WCFM:
 *   - Slug interno (uno debe ser "free" para downgrade en expiración)
 *   - Límite de productos (-1 = ilimitado)
 *   - Tipos de producto permitidos
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── Verificar dependencia ─────────────────────────────────────── */
if ( ! class_exists( 'Aureo_AR_Plans' ) ) {
    echo '<h3>Planes</h3>';
    echo '<p><strong>WCFM Membership</strong> no está activo. Activa el plugin para configurar los planes.</p>';
    return;
}

/* ── Obtener planes WCFM disponibles ────────────────────────────── */
$wcfm_memberships = get_posts( array(
    'post_type'      => 'wcfm_memberships',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
) );

if ( empty( $wcfm_memberships ) ) {
    echo '<h3>Planes</h3>';
    echo '<p>No hay planes de membresía creados todavía. Ve a <strong>WCFM → Membership</strong> y crea al menos uno.</p>';
    return;
}

/* ── Tipos de producto disponibles en WooCommerce ──────────────── */
$available_types = wc_get_product_types();
// Tipos básicos seguros (ocultamos los muy raros)
$basic_types = array(
    'simple'       => $available_types['simple']       ?? 'Simple',
    'variable'     => $available_types['variable']     ?? 'Variable',
    'grouped'      => $available_types['grouped']      ?? 'Grouped',
    'external'     => $available_types['external']     ?? 'External / Affiliate',
);
// Sumar cualquier otro tipo que tenga registrado WC (bookings, subscription, etc.)
$product_types = array_merge( $basic_types, $available_types );

/* ── Procesar guardado ─────────────────────────────────────────── */
if ( isset( $_POST['aureo_ar_save_plans'] ) && check_admin_referer( 'aureo_ar_save_plans' ) ) {

    $new_config = array();

    if ( ! empty( $_POST['plans'] ) && is_array( $_POST['plans'] ) ) {
        foreach ( $_POST['plans'] as $membership_id => $data ) {

            $membership_id = intval( $membership_id );
            if ( $membership_id <= 0 ) { continue; }

            $slug          = sanitize_key( $data['slug'] ?? '' );
            $limit         = isset( $data['product_limit'] ) ? intval( $data['product_limit'] ) : -1;
            $types_raw     = isset( $data['product_types'] ) && is_array( $data['product_types'] )
                             ? $data['product_types']
                             : array();
            $types_clean   = array_values( array_unique( array_map( 'sanitize_key', $types_raw ) ) );

            // Solo guardar si tiene slug definido (significa que el admin lo configuró)
            if ( empty( $slug ) ) { continue; }

            $new_config[ $membership_id ] = array(
                'slug'          => $slug,
                'product_limit' => $limit,
                'product_types' => $types_clean,
            );
        }
    }

    update_option( Aureo_AR_Plans::OPTION_KEY, $new_config );
    echo '<div class="notice notice-success is-dismissible"><p>Planes guardados correctamente.</p></div>';
}

/* ── Cargar configuración actual ───────────────────────────────── */
$current_config = Aureo_AR_Plans::get_plans();
$resync_url     = wp_nonce_url(
    add_query_arg( array(
        'page'                    => 'aureo-ar-settings',
        'tab'                     => 'plans',
        'aureo_ar_resync_plans'   => '1',
    ), admin_url( 'admin.php' ) ),
    'aureo_ar_resync'
);
?>

<style>
    .aureo-plans-table { border-collapse: collapse; width: 100%; margin-top: 15px; }
    .aureo-plans-table th, .aureo-plans-table td {
        border: 1px solid #e0e0e0; padding: 12px; vertical-align: top;
    }
    .aureo-plans-table th { background: #f6f7f7; text-align: left; }
    .aureo-plans-table td label { display: block; margin-bottom: 4px; font-weight: normal; }
    .aureo-plans-table input[type="text"], .aureo-plans-table input[type="number"] {
        width: 100%; max-width: 200px;
    }
    .aureo-plans-help {
        background: #f0f6fc; border-left: 4px solid #2271b1;
        padding: 10px 14px; margin: 15px 0;
    }
    .aureo-plan-name { font-weight: 600; font-size: 14px; }
    .aureo-plan-id   { color: #888; font-size: 12px; font-family: monospace; }
</style>

<h3>Planes WCFM — Capacidades por plan</h3>

<div class="aureo-plans-help">
    <p style="margin:0 0 6px;"><strong>¿Qué hace esta sección?</strong></p>
    <p style="margin:0;">
        Define el <strong>límite de productos</strong> y los <strong>tipos de producto permitidos</strong> para cada plan de membresía.
        Las capacidades se aplican automáticamente cuando un vendor: se subscribe, cambia de plan, renueva o expira.
        Si un plan expira, el vendor pasa al plan con slug <code>free</code> (si existe).
    </p>
</div>

<form method="post" action="">
    <?php wp_nonce_field( 'aureo_ar_save_plans' ); ?>

    <table class="aureo-plans-table">
        <thead>
            <tr>
                <th style="width: 25%;">Plan WCFM</th>
                <th style="width: 15%;">Slug interno</th>
                <th style="width: 15%;">Límite productos</th>
                <th>Tipos permitidos</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $wcfm_memberships as $membership ) :
                $mid     = $membership->ID;
                $current = isset( $current_config[ $mid ] ) ? $current_config[ $mid ] : array(
                    'slug'          => '',
                    'product_limit' => -1,
                    'product_types' => array( 'simple' ),
                );
            ?>
            <tr>
                <td>
                    <span class="aureo-plan-name"><?php echo esc_html( $membership->post_title ); ?></span><br>
                    <span class="aureo-plan-id">ID: <?php echo esc_html( $mid ); ?></span>
                </td>

                <td>
                    <input type="text"
                           name="plans[<?php echo esc_attr( $mid ); ?>][slug]"
                           value="<?php echo esc_attr( $current['slug'] ); ?>"
                           placeholder="free / pro / premium" />
                    <p class="description" style="font-size:11px;margin:4px 0 0;">
                        Uno debe ser <code>free</code>.
                    </p>
                </td>

                <td>
                    <input type="number"
                           name="plans[<?php echo esc_attr( $mid ); ?>][product_limit]"
                           value="<?php echo esc_attr( $current['product_limit'] ); ?>"
                           min="-1" />
                    <p class="description" style="font-size:11px;margin:4px 0 0;">
                        <code>-1</code> = ilimitado
                    </p>
                </td>

                <td>
                    <?php
                    $rendered = array();
                    foreach ( $product_types as $type_key => $type_label ) :
                        if ( in_array( $type_key, $rendered, true ) ) { continue; }
                        $rendered[] = $type_key;
                        $checked = in_array( $type_key, $current['product_types'], true ) ? 'checked' : '';
                    ?>
                        <label>
                            <input type="checkbox"
                                   name="plans[<?php echo esc_attr( $mid ); ?>][product_types][]"
                                   value="<?php echo esc_attr( $type_key ); ?>"
                                   <?php echo $checked; ?> />
                            <?php echo esc_html( $type_label ); ?>
                            <code style="font-size:10px;color:#888;">(<?php echo esc_html( $type_key ); ?>)</code>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top: 20px;">
        <button type="submit" name="aureo_ar_save_plans" class="button button-primary">
            Guardar planes
        </button>

        <a href="<?php echo esc_url( $resync_url ); ?>"
           class="button"
           onclick="return confirm('Esto re-aplicará las capacidades a TODOS los vendors según su plan actual. ¿Continuar?');">
            🔄 Re-sincronizar todos los vendors
        </a>
    </p>

    <p class="description" style="margin-top: 15px;">
        <strong>Re-sincronizar:</strong> úsalo después de cambiar la configuración aquí, o si tienes vendors antiguos cuyas capacidades nunca se aplicaron.
    </p>
</form>
