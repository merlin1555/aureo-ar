/**
 * aureo-ar-woo-admin.js
 * Script para el tab "Aureo AR" en el editor de productos del admin de WP.
 */
(function ($) {
    'use strict';

    // ── Selectores ──────────────────────────────────────────────────
    var SEL = {
        typeSelect:       '#aureo_ar_type',
        colorModeSelect:  '#aureo_ar_color_mode',
        rowAccessory:     '.aureo-ar-row--accessory',
        rowObject:        '.aureo-ar-row--object',
        rowHasModel:      '.aureo-ar-row--has-model',
        colorsSection:    '.aureo-ar-colors-section',
        materialsSection: '.aureo-ar-materials-section',
        addColorBtn:      '#aureo-ar-add-color-btn',
        colorsData:       '#aureo_ar_colors_data',
        colorList:        '#aureo-ar-color-list',
        materialList:     '#aureo-ar-material-list',
        materialSlotsData:'#aureo_ar_material_slots_data',
        uploadBtn:        '.aureo-ar-upload-btn',
        clearBtn:         '.aureo-ar-clear-btn',
        previewWrapper:   '#aureo-model-preview-wrapper',
        miniViewer:       '#aureo-mini-viewer',
        glbInput:         '#aureo_ar_model_url',
    };

    // ── Visibilidad condicional (tipo AR) ────────────────────────────
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
            $(SEL.rowAccessory).hide();
            $(SEL.rowObject).hide();
            $(SEL.rowHasModel).hide();
        }
        applyColorVisibility($(SEL.colorModeSelect).val());
    }

    $(SEL.typeSelect).on('change', function () {
        applyVisibility($(this).val());
    });

    applyVisibility($(SEL.typeSelect).val());

    // ── Visibilidad condicional (modo color) ─────────────────────────
    function applyColorVisibility(mode) {
        var type = $(SEL.typeSelect).val();
        if (type === 'none' || !mode) {
            $(SEL.colorsSection).hide();
            $(SEL.materialsSection).hide();
            return;
        }
        if (mode === 'model_texture') {
            $(SEL.colorsSection).hide();
            $(SEL.materialsSection).hide();
        } else if (mode === 'per_material') {
            $(SEL.colorsSection).hide();
            $(SEL.materialsSection).show();
            detectMaterialsFromViewer();
        } else {
            // multi_color
            $(SEL.materialsSection).hide();
            $(SEL.colorsSection).show();
            $(SEL.addColorBtn).show();
            $(SEL.colorList + ' .aureo-color-entry .aureo-remove-color-btn').show();
            syncColorsData();
        }
    }

    $(SEL.colorModeSelect).on('change', function () {
        applyColorVisibility($(this).val());
    });

    applyColorVisibility($(SEL.colorModeSelect).val());

    // ── Gestión de la lista de colores (multi_color) ────────────────
    function syncColorsData() {
        var colors = [];
        $(SEL.colorList + ' .aureo-color-picker').each(function () {
            colors.push($(this).val());
        });
        $(SEL.colorsData).val(JSON.stringify(colors));
    }

    $(document).on('input change', SEL.colorList + ' .aureo-color-picker', function () {
        syncColorsData();
    });

    $(document).on('click', SEL.addColorBtn, function (e) {
        e.preventDefault();
        var entry = '<div class="aureo-color-entry">' +
            '<input type="color" class="aureo-color-picker" value="#c0c0c0">' +
            '<button type="button" class="button aureo-remove-color-btn">✕</button>' +
            '</div>';
        $(SEL.colorList).append(entry);
        syncColorsData();
    });

    $(document).on('click', '.aureo-remove-color-btn', function (e) {
        e.preventDefault();
        var $entries = $(SEL.colorList + ' .aureo-color-entry');
        if ($entries.length <= 1) return;
        $(this).closest('.aureo-color-entry').remove();
        syncColorsData();
    });

    syncColorsData();

    // ── Gestión de materiales por nombre (per_material) ──────────────

    /**
     * Lee los materiales del <model-viewer> filtrando por nombre "materialN".
     * Cruza con los datos ya guardados y renderiza las filas.
     */
    function detectMaterialsFromViewer() {
        var viewer = document.getElementById('aureo-mini-viewer');
        if (!viewer || !viewer.model) {
            $(SEL.materialList).html(
                '<p class="aureo-mat-notice">' +
                'Cargando modelo… Los materiales aparecerán una vez que el preview cargue.' +
                '</p>'
            );
            return;
        }

        var mats = (viewer.model.materials || []).filter(function (m) {
            return /^material\d+$/i.test(m.name);
        }).sort(function (a, b) {
            return a.name.localeCompare(b.name, undefined, { numeric: true });
        });

        if (!mats.length) {
            $(SEL.materialList).html(
                '<p class="aureo-mat-notice aureo-mat-notice--warn">' +
                '⚠ No se encontraron materiales con nombre <code>material0</code>, <code>material1</code>… en este modelo.' +
                '</p>'
            );
            syncMaterialSlotsData();
            return;
        }

        var savedSlots = {};
        try { savedSlots = JSON.parse($(SEL.materialSlotsData).val() || '{}'); } catch (e) {}

        var html = '';
        mats.forEach(function (mat) {
            var saved     = savedSlots[mat.name] || { type: 'color', colors: ['#c0c0c0'] };
            var isTexture = saved.type === 'texture';

            // Soporte formato nuevo (colors[]) y legado (value)
            var colors = ['#c0c0c0'];
            if (!isTexture) {
                if (Array.isArray(saved.colors) && saved.colors.length) {
                    colors = saved.colors;
                } else if (saved.value) {
                    colors = [saved.value];
                }
            }
            var texVal = isTexture ? (saved.value || '') : '';

            html += '<div class="aureo-material-row" data-material="' + mat.name + '">';
            html += '  <span class="aureo-material-name">' + mat.name + '</span>';
            html += '  <select class="aureo-material-type">';
            html += '    <option value="color"'   + (!isTexture ? ' selected' : '') + '>Color</option>';
            html += '    <option value="texture"' + ( isTexture ? ' selected' : '') + '>Textura</option>';
            html += '  </select>';
            html += '  <div class="aureo-mat-colors-wrap"' + (isTexture ? ' style="display:none"' : '') + '>';
            colors.forEach(function (c) {
                html += '    <div class="aureo-mat-color-entry">';
                html += '      <input type="color" class="aureo-material-color" value="' + c + '">';
                html += '      <button type="button" class="button aureo-remove-mat-color-btn">✕</button>';
                html += '    </div>';
            });
            html += '    <button type="button" class="button aureo-add-mat-color-btn">+ Color</button>';
            html += '  </div>';
            html += '  <div class="aureo-texture-wrap"' + (!isTexture ? ' style="display:none"' : '') + '>';
            html += '    <input type="text" class="aureo-material-texture regular-text" value="' + texVal + '" placeholder="https://… URL de textura">';
            html += '    <button type="button" class="button aureo-select-texture-btn" data-material="' + mat.name + '">Seleccionar</button>';
            html += '  </div>';
            html += '</div>';
        });

        $(SEL.materialList).html(html);
        syncMaterialSlotsData();
    }

    function syncMaterialSlotsData() {
        var slots = {};
        $(SEL.materialList + ' .aureo-material-row').each(function () {
            var mat  = $(this).data('material');
            var type = $(this).find('.aureo-material-type').val();
            if (type === 'color') {
                var colors = [];
                $(this).find('.aureo-material-color').each(function () {
                    colors.push($(this).val());
                });
                slots[mat] = { type: 'color', colors: colors };
            } else {
                slots[mat] = { type: 'texture', value: $(this).find('.aureo-material-texture').val() };
            }
        });
        $(SEL.materialSlotsData).val(JSON.stringify(slots));
    }

    // Tipo color ↔ textura dentro de una fila
    $(document).on('change', SEL.materialList + ' .aureo-material-type', function () {
        var $row      = $(this).closest('.aureo-material-row');
        var isTexture = $(this).val() === 'texture';
        $row.find('.aureo-mat-colors-wrap').toggle(!isTexture);
        $row.find('.aureo-texture-wrap').toggle(isTexture);
        syncMaterialSlotsData();
    });

    $(document).on('input change', SEL.materialList + ' .aureo-material-color, ' +
                                   SEL.materialList + ' .aureo-material-texture', function () {
        syncMaterialSlotsData();
    });

    // Agregar color a un material
    $(document).on('click', SEL.materialList + ' .aureo-add-mat-color-btn', function (e) {
        e.preventDefault();
        var $wrap = $(this).closest('.aureo-mat-colors-wrap');
        var entry = '<div class="aureo-mat-color-entry">' +
            '<input type="color" class="aureo-material-color" value="#c0c0c0">' +
            '<button type="button" class="button aureo-remove-mat-color-btn">✕</button>' +
            '</div>';
        $wrap.find('.aureo-add-mat-color-btn').before(entry);
        syncMaterialSlotsData();
    });

    // Eliminar color de un material (mínimo 1)
    $(document).on('click', SEL.materialList + ' .aureo-remove-mat-color-btn', function (e) {
        e.preventDefault();
        var $wrap = $(this).closest('.aureo-mat-colors-wrap');
        if ($wrap.find('.aureo-mat-color-entry').length <= 1) return;
        $(this).closest('.aureo-mat-color-entry').remove();
        syncMaterialSlotsData();
    });

    // Selector de textura desde la biblioteca de medios
    $(document).on('click', '.aureo-select-texture-btn', function (e) {
        e.preventDefault();
        var $btn   = $(this);
        var $input = $btn.closest('.aureo-texture-wrap').find('.aureo-material-texture');

        var frame = wp.media({
            title:    'Seleccionar textura',
            button:   { text: 'Usar esta imagen' },
            library:  { type: 'image' },
            multiple: false,
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            if (att && att.url) {
                $input.val(att.url).trigger('input');
            }
        });
        frame.open();
    });

    // Cuando el model-viewer cargue, re-detectar materiales si el modo es per_material
    $(document).ready(function () {
        var viewer = document.getElementById('aureo-mini-viewer');
        if (!viewer) return;
        viewer.addEventListener('load', function () {
            if ($(SEL.colorModeSelect).val() === 'per_material') {
                detectMaterialsFromViewer();
            }
        });
    });

    // ── Uploader media library (GLB / USDZ) ─────────────────────────
    $(document).on('click', SEL.uploadBtn, function (e) {
        e.preventDefault();
        var $btn     = $(this);
        var targetId = $btn.data('target');
        var $input   = $('#' + targetId);
        var isGlb    = targetId === 'aureo_ar_model_url';

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
        var viewer = document.getElementById('aureo-mini-viewer');
        if (!viewer) return;
        viewer.setAttribute('src', url);
        $(SEL.previewWrapper).show();
    }

    var existingUrl = $(SEL.glbInput).val();
    if (existingUrl) {
        $(SEL.previewWrapper).show();
    }

}(jQuery));
