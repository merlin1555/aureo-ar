// tryon-object.js — Motor AR para OBJETOS (cámara trasera).
// Usa <model-viewer> con ar-modes "webxr scene-viewer quick-look":
//   - Android Chrome moderno → WebXR (sesión inmersiva en la misma pestaña)
//   - Android fallback       → Scene Viewer (app nativa de Google)
//   - iOS Safari             → Quick Look (requiere USDZ o GLB con fallback)
// Desktop: muestra el modelo 3D interactivo (sin AR).
//
// A diferencia de tryon-face.js, aquí NO necesitamos three.js manual ni MindAR.
// <model-viewer> encapsula todo. Este archivo solo maneja eventos, errores y UX.

(function () {
  'use strict';

  const CFG = window.AUREO_AR_CFG || {};
  let initialized = false;

  function showError(msg) {
    console.error('[Aureo AR · object]', msg);
    if (window.AureoAR && window.AureoAR.showError) {
      window.AureoAR.showError(msg);
    }
  }

  function hideLoader() {
    const loader = document.getElementById('aureo-ar-loader');
    if (loader) loader.style.display = 'none';
  }

  /**
   * Aplica un color hexadecimal a todos los materiales del model-viewer.
   * Convierte hex → [r, g, b, 1] normalizados que acepta la Material API.
   */
  function applyColor(hex) {
    const mv = document.getElementById('aureo-ar-mv');
    if (!mv || !mv.model) return;
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;
    mv.model.materials.forEach(mat => {
      mat.pbrMetallicRoughness.baseColorTexture.setTexture(null); // quita albedo
      mat.pbrMetallicRoughness.setBaseColorFactor([r, g, b, 1]);
    });
  }

  /**
   * Inicializa listeners sobre el <model-viewer>.
   * Es idempotente: si se llama dos veces, solo engancha una vez.
   */
  function initModelViewer() {
    if (initialized) return;
    const mv = document.getElementById('aureo-ar-mv');
    if (!mv) {
      console.warn('[Aureo AR · object] <model-viewer> no encontrado todavía');
      return;
    }
    initialized = true;

    // Log de capacidad AR del dispositivo
    // canActivateAR es reactivo: puede actualizarse tras cargar el modelo.
    const logSupport = () => {
      console.log('[Aureo AR · object] canActivateAR =', mv.canActivateAR);
    };

    mv.addEventListener('load', () => {
      hideLoader();
      logSupport();
      console.log('[Aureo AR · object] modelo cargado:', CFG.glbUrl);
      if ( CFG.colorMode === 'multi_color' && Array.isArray(CFG.colors) && CFG.colors.length > 0 ) {
        applyColor(CFG.colors[0]);
      } else if ( CFG.colorMode === 'per_material' && CFG.materialSlots ) {
        applyMaterialSlots(CFG.materialSlots);
      }
    });

    mv.addEventListener('error', (ev) => {
      const detail = ev.detail || {};
      showError('No se pudo cargar el modelo 3D. Verifica la URL del GLB.');
      console.error('[Aureo AR · object] error detalle:', detail);
    });

    mv.addEventListener('ar-status', (ev) => {
      // status: 'not-presenting' | 'session-started' | 'object-placed' | 'failed'
      console.log('[Aureo AR · object] ar-status:', ev.detail.status);
      if (ev.detail.status === 'failed') {
        showError('No se pudo iniciar la sesión AR. Tu dispositivo podría no soportarlo.');
      }
    });

    // Si canActivateAR vuelve falso tras cargar, informamos al usuario con un
    // mensaje suave (sin bloquear: aún puede ver el 3D interactivo).
    mv.addEventListener('ar-tracking', (ev) => {
      console.log('[Aureo AR · object] ar-tracking:', ev.detail.status);
    });

    // Fallback defensivo: si en 15s no cargó, mostramos error
    setTimeout(() => {
      if (mv.loaded) return;
      hideLoader();
      console.warn('[Aureo AR · object] timeout: el modelo tardó demasiado');
    }, 15000);
  }

  /**
   * Handler cuando se abre la modal.
   * El <model-viewer> ya está en el DOM (renderizado por PHP), solo
   * necesitamos asegurarnos de que sus listeners estén activos.
   */
  document.addEventListener('aureo-ar:open', () => {
    if (CFG.arType !== 'object') return; // este motor solo responde a objetos
    initModelViewer();

    // Si el dispositivo NO soporta AR, model-viewer oculta el slot="ar-button"
    // automáticamente. Chequeamos después de un tick para mostrar feedback.
    setTimeout(() => {
      const mv = document.getElementById('aureo-ar-mv');
      if (!mv) return;
      if (!mv.canActivateAR) {
        console.log('[Aureo AR · object] dispositivo sin AR, mostrando solo 3D interactivo');
      }
    }, 500);
  });

  document.addEventListener('aureo-ar:close', () => {
    if (CFG.arType !== 'object') return;
    // model-viewer se limpia solo al cerrar la modal. No necesitamos stop().
    // Solo reseteamos UI por si el usuario reabre.
    const loader = document.getElementById('aureo-ar-loader');
    if (loader) loader.style.display = '';
  });

  // Si la página carga con el modelo ya en DOM y sin evento open aún,
  // al menos inicializamos para que el evento "load" capture hideLoader().
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModelViewer);
  } else {
    initModelViewer();
  }

  /**
   * Aplica slots { materialN: { type, value } } usando la Material API de model-viewer.
   * Para "texture": asigna una textura KHR_texture_basisu/PNG al slot base color.
   */
  function applyMaterialSlots(slots) {
    const mv = document.getElementById('aureo-ar-mv');
    if (!mv || !mv.model) return;
    mv.model.materials.forEach(mat => {
      const key = (mat.name || '').toLowerCase();
      if (!slots[key]) return;
      const slot = slots[key];
      if (slot.type === 'color') {
        // Soporte formato nuevo (colors[]) y legado (value)
        const hex = Array.isArray(slot.colors) && slot.colors.length ? slot.colors[0] : (slot.value || '#c0c0c0');
        const r = parseInt(hex.slice(1, 3), 16) / 255;
        const g = parseInt(hex.slice(3, 5), 16) / 255;
        const b = parseInt(hex.slice(5, 7), 16) / 255;
        mat.pbrMetallicRoughness.baseColorTexture.setTexture(null);
        mat.pbrMetallicRoughness.setBaseColorFactor([r, g, b, 1]);
      } else if (slot.type === 'texture' && slot.value) {
        mv.createTexture(slot.value).then(tex => {
          mat.pbrMetallicRoughness.baseColorTexture.setTexture(tex);
          mat.pbrMetallicRoughness.setBaseColorFactor([1, 1, 1, 1]);
        }).catch(() => {
          console.warn('[Aureo AR · object] no se pudo cargar textura:', slot.value);
        });
      }
    });
  }

  function applyMaterialColor(materialName, hex) {
    const mv = document.getElementById('aureo-ar-mv');
    if (!mv || !mv.model || !hex || !materialName) return;
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;
    mv.model.materials.forEach(mat => {
      if ((mat.name || '').toLowerCase() !== materialName) return;
      mat.pbrMetallicRoughness.baseColorTexture.setTexture(null);
      mat.pbrMetallicRoughness.setBaseColorFactor([r, g, b, 1]);
    });
  }

  document.addEventListener('aureo-ar:set-color', e => {
    if (CFG.arType !== 'object') return;
    applyColor(e.detail);
  });

  document.addEventListener('aureo-ar:set-material-color', e => {
    if (CFG.arType !== 'object') return;
    applyMaterialColor(e.detail.material, e.detail.color);
  });

  console.log('[Aureo AR · object] motor objeto listo. CFG:', CFG);
})();
