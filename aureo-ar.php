<?php
/**
 * Plugin Name:       Aureo AR
 * Description:       Realidad Aumentada para marketplace 3D. Soporta accesorios (cámara frontal) y objetos/muebles (cámara trasera). Integrado con WooCommerce y Dokan.
 * Version:           4.1.0
 * Author:            Merlin & 3D Commerce
 * Text Domain:       aureo-ar
 * Requires Plugins:  woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'AUREO_AR_PATH',    plugin_dir_path( __FILE__ ) );
define( 'AUREO_AR_URL',     plugin_dir_url( __FILE__ ) );
define( 'AUREO_AR_VERSION', '4.1.0' );

/* ── Clases del plugin ─────────────────────────────────────────── */
require_once AUREO_AR_PATH . 'includes/core/class-aureo-ar-uploader.php';
require_once AUREO_AR_PATH . 'includes/admin/class-aureo-ar-admin.php';
require_once AUREO_AR_PATH . 'includes/integrations/class-aureo-ar-woo.php';
add_action( 'plugins_loaded', function() {
    if ( ! defined( 'WCFM_VERSION' ) && ! function_exists( 'wcfm_is_vendor' ) ) { return; }
    require_once AUREO_AR_PATH . 'includes/integrations/class-aureo-ar-wcfm.php';
    require_once AUREO_AR_PATH . 'includes/integrations/class-aureo-ar-plans.php';
}, 20 );
require_once AUREO_AR_PATH . 'includes/core/class-aureo-ar-frontend.php';

/* ── Assets del admin (WP product editor) ──────────────────────── */
add_action( 'admin_enqueue_scripts', 'aureo_ar_admin_assets' );

function aureo_ar_admin_assets( $hook ) {
    $screen = get_current_screen();

    $is_aureo_panel  = ( 'toplevel_page_aureo-ar-settings' === $hook );
    $is_woo_product  = ( $screen && $screen->id === 'product' );

    if ( ! $is_aureo_panel && ! $is_woo_product ) {
        return;
    }

    wp_enqueue_media();

    /* ── Panel Aureo AR (pestaña Visor) ── */
    if ( $is_aureo_panel ) {
        wp_enqueue_style(
            'aureo-ar-style',
            AUREO_AR_URL . 'assets/css/admin/style.css',
            array(),
            AUREO_AR_VERSION
        );
        wp_enqueue_script( 'three-js', 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js', array(), 'r128', true );
        wp_enqueue_script( 'three-gltf-loader', AUREO_AR_URL . 'assets/js/vendor/GLTFLoader.js', array( 'three-js' ), 'r128', true );
        wp_enqueue_script( 'aureo-ar-general-js', AUREO_AR_URL . 'assets/js/admin/general.js', array(), AUREO_AR_VERSION, true );

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'debug';
        if ( $active_tab === 'visor' ) {
            wp_enqueue_script( 'three-orbit-controls', AUREO_AR_URL . 'assets/js/vendor/OrbitControls.js', array( 'three-js' ), 'r128', true );
            wp_enqueue_script( 'aureo-ar-visor-js', AUREO_AR_URL . 'assets/js/admin/visor.js', array( 'three-js', 'three-gltf-loader', 'three-orbit-controls' ), AUREO_AR_VERSION, true );
        }
    }

    /* ── Editor de producto WooCommerce ── */
    if ( $is_woo_product ) {
        // model-viewer para preview
        wp_enqueue_script(
            'model-viewer-js',
            'https://ajax.googleapis.com/ajax/libs/model-viewer/3.4.0/model-viewer.min.js',
            array(),
            '3.4.0',
            true
        );

        // CSS del tab AR
        wp_enqueue_style(
            'aureo-ar-woo-admin',
            AUREO_AR_URL . 'assets/css/admin/woo-admin.css',
            array(),
            AUREO_AR_VERSION
        );

        // JS del tab AR (visibilidad condicional + uploader)
        wp_enqueue_script(
            'aureo-ar-woo-admin',
            AUREO_AR_URL . 'assets/js/admin/woo-admin.js',
            array( 'jquery' ),
            AUREO_AR_VERSION,
            true
        );
    }
}

/* ── Forzar type="module" en scripts que lo necesitan ──────────── */
add_filter( 'script_loader_tag', 'aureo_ar_make_scripts_modules', 10, 3 );

function aureo_ar_make_scripts_modules( $tag, $handle, $src ) {
    // model-viewer y el motor de accesorios (three.js ESM + MindAR) son módulos.
    // tryon-object.js es un script clásico y NO va aquí.
    $module_handles = array( 'model-viewer-js', 'aureo-ar-tryon-face' );
    if ( in_array( $handle, $module_handles, true ) ) {
        return '<script type="module" src="' . esc_url( $src ) . '"></script>' . "\n";
    }
    return $tag;
}
