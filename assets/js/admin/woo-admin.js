/**
 * aureo-ar-woo-admin.js
 * Script para el tab "Aureo AR" en el editor de productos del admin de WP.
 * Maneja:
 *  - Mostrar/ocultar filas según el tipo de AR seleccionado
 *  - Uploader de medios para GLB y USDZ
 *  - Botón de limpiar campo
 *  - Actualización del <model-viewer> preview en tiempo real
 */
(function ($) {
    'use strict';

    // ── Selectores ──────────────────────────────────────────────────
    var SEL = {
        typeSelect:     '#aureo_ar_type',
        rowAccessory:   '.aureo-ar-row--accessory',
        rowObject:      '.aureo-ar-row--object',
        rowHasModel:    '.aureo-ar-row--has-model',
        uploadBtn:      '.aureo-ar-upload-btn',
        clearBtn:       '.aureo-ar-clear-btn',
        previewWrapper: '#aureo-model-preview-wrapper',
        miniViewer:     '#aureo-mini-viewer',
        glbInput:       '#aureo_ar_model_url',
    };

    // ── Visibilidad condicional ──────────────────────────────────────
    function applyVisibility(type) {
        if (type === 'accessory') {
            $(SEL.rowAccessory).show();
            $(SEL.rowObject).hide();
            $(SEL.rowHasModel).show();
        } else if (type === 'object') {
            $(SEL.rowAccessory).hide();
            $(SEL.rowObject).show();
            $(SEL.rowHasModel).show();
        } else {
            // none
            $(SEL.rowAccessory).hide();
            $(SEL.rowObject).hide();
            $(SEL.rowHasModel).hide();
        }
    }

    $(SEL.typeSelect).on('change', function () {
        applyVisibility($(this).val());
    });

    // Aplicar al cargar (por si ya hay valor guardado)
    applyVisibility($(SEL.typeSelect).val());

    // ── Uploader media library ───────────────────────────────────────
    $(document).on('click', SEL.uploadBtn, function (e) {
        e.preventDefault();
        var $btn       = $(this);
        var targetId   = $btn.data('target');
        var $input     = $('#' + targetId);
        var isGlb      = targetId === 'aureo_ar_model_url';

        var frame = wp.media({
            title:    isGlb ? 'Seleccionar modelo GLB / GLTF' : 'Seleccionar modelo USDZ',
            button:   { text: 'Usar este archivo' },
            multiple: false,
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var url        = attachment.url;

            $input.val(url);

            if (isGlb) {
                updatePreview(url);
                // Mostrar botón limpiar si no existe
                if (!$btn.siblings('.aureo-ar-clear-btn').length) {
                    $btn.after(' <button type="button" class="button aureo-ar-clear-btn" data-target="' + targetId + '">✕</button>');
                }
            }
        });

        frame.open();
    });

    // ── Limpiar campo ────────────────────────────────────────────────
    $(document).on('click', SEL.clearBtn, function (e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        $('#' + targetId).val('');
        if (targetId === 'aureo_ar_model_url') {
            $(SEL.previewWrapper).hide();
            $(SEL.miniViewer).attr('src', '');
        }
        $(this).remove();
    });

    // ── Actualizar preview <model-viewer> ────────────────────────────
    function updatePreview(url) {
        var $wrapper = $(SEL.previewWrapper);
        var viewer   = document.getElementById('aureo-mini-viewer');
        if (!viewer) { return; }

        viewer.setAttribute('src', url);
        $wrapper.show();
    }

    // Si ya hay un GLB al cargar la página, aseguramos que el preview sea visible
    var existingUrl = $(SEL.glbInput).val();
    if (existingUrl) {
        $(SEL.previewWrapper).show();
    }

}(jQuery));
