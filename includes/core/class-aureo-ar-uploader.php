<?php
/**
 * Aureo AR — Uploader
 * Registra los tipos MIME personalizados necesarios para subir
 * modelos 3D a la biblioteca de medios de WordPress.
 *
 * Tipos soportados:
 *  .glb   → model/gltf-binary
 *  .gltf  → model/gltf+json
 *  .usdz  → model/vnd.usdz+zip  (para iOS AR Quick Look)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Aureo_AR_Uploader {

    public function __construct() {
        add_filter( 'upload_mimes',             array( $this, 'add_3d_mimes' ) );
        add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_mime_check' ), 10, 5 );
    }

    /**
     * Añade los MIME types a la lista permitida de WordPress.
     */
    public function add_3d_mimes( $mimes ) {
        $mimes['glb']  = 'model/gltf-binary';
        $mimes['gltf'] = 'model/gltf+json';
        $mimes['usdz'] = 'model/vnd.usdz+zip';
        return $mimes;
    }

    /**
     * WordPress valida el MIME real del archivo con finfo/getimagesize,
     * lo que puede hacer fallar archivos 3D aunque estén en la lista.
     * Este filtro fuerza los tipos correctos para las extensiones 3D.
     */
    public function fix_mime_check( $data, $file, $filename, $mimes, $real_mime ) {
        if ( ! $data['type'] || ! $data['ext'] ) {
            $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
            $map = array(
                'glb'  => 'model/gltf-binary',
                'gltf' => 'model/gltf+json',
                'usdz' => 'model/vnd.usdz+zip',
            );
            if ( isset( $map[ $ext ] ) ) {
                $data['ext']  = $ext;
                $data['type'] = $map[ $ext ];
            }
        }
        return $data;
    }
}

new Aureo_AR_Uploader();
