<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function aureo_ar_add_admin_menu() {
    add_menu_page('Aureo AR', 'Aureo AR', 'manage_options', 'aureo-ar-settings', 'aureo_ar_display_page', 'dashicons-visibility', 6);
}
add_action( 'admin_menu', 'aureo_ar_add_admin_menu' );

function aureo_ar_display_page() {
    $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'debug';
    ?>
    <div class="wrap aureo-ar-admin-wrap">
        <h1>Aureo AR <span class="plugin-version">v3.2.0</span></h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="?page=aureo-ar-settings&tab=debug"   class="nav-tab <?php echo $active_tab == 'debug'   ? 'nav-tab-active' : ''; ?>">Debug</a>
            <a href="?page=aureo-ar-settings&tab=visor"   class="nav-tab <?php echo $active_tab == 'visor'   ? 'nav-tab-active' : ''; ?>">Visor AR</a>
            <a href="?page=aureo-ar-settings&tab=plans"   class="nav-tab <?php echo $active_tab == 'plans'   ? 'nav-tab-active' : ''; ?>">Planes</a>
            <a href="?page=aureo-ar-settings&tab=vendors" class="nav-tab <?php echo $active_tab == 'vendors' ? 'nav-tab-active' : ''; ?>">Vendors</a>
            <a href="?page=aureo-ar-settings&tab=config"  class="nav-tab <?php echo $active_tab == 'config'  ? 'nav-tab-active' : ''; ?>">Configuración</a>
        </h2>

        <div class="aureo-ar-content card">
            <?php
            // Cargamos el archivo de la pestaña dinámicamente
            $tab_file = AUREO_AR_PATH . "includes/admin/tabs/tab-{$active_tab}.php";
            if ( file_exists( $tab_file ) ) {
                include $tab_file;
            } else {
                echo "<h3>Próximamente</h3><p>Esta pestaña aún está en desarrollo.</p>";
            }
            ?>
        </div>
    </div>
    <?php
}