// tryon-ui.js — script clásico (no module). Maneja abrir/cerrar modal.
(function () {
  'use strict';

  function $(id) { return document.getElementById(id); }

  function openModal() {
    var m = $('aureo-ar-modal');
    if (!m) { console.warn('[Aureo AR] modal no encontrado en el DOM'); return; }
    m.classList.add('is-open');
    m.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    // Resetear swatches de color global (multi_color)
    var globalSwatches = document.querySelectorAll('#aureo-ar-colors .aureo-ar-color-swatch');
    globalSwatches.forEach(function(s, i) {
      s.classList.toggle('aureo-ar-color-swatch--active', i === 0);
    });
    // Resetear swatches por material: primero de cada grupo
    document.querySelectorAll('.aureo-ar-material-group').forEach(function(group) {
      group.querySelectorAll('.aureo-ar-color-swatch').forEach(function(s, i) {
        s.classList.toggle('aureo-ar-color-swatch--active', i === 0);
      });
    });
    // Avisamos al módulo AR para que arranque
    document.dispatchEvent(new CustomEvent('aureo-ar:open'));
  }

  function closeModal() {
    var m = $('aureo-ar-modal');
    if (!m) return;
    m.classList.remove('is-open');
    m.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    document.dispatchEvent(new CustomEvent('aureo-ar:close'));
  }

  // Exponer por si el módulo necesita pintar errores
  window.AureoAR = window.AureoAR || {};
  window.AureoAR.open  = openModal;
  window.AureoAR.close = closeModal;
  window.AureoAR.showError = function (msg) {
    var el = $('aureo-ar-error');
    var loader = $('aureo-ar-loader');
    if (loader) loader.style.display = 'none';
    if (el) { el.hidden = false; el.textContent = msg || 'Error desconocido.'; }
  };
  window.AureoAR.hideLoader = function () {
    var loader = $('aureo-ar-loader');
    if (loader) loader.style.display = 'none';
  };
  window.AureoAR.resetStage = function () {
    var loader = $('aureo-ar-loader');
    if (loader) loader.style.display = '';
    var err = $('aureo-ar-error');
    if (err) { err.hidden = true; err.textContent = ''; }
  };

  document.addEventListener('click', function (e) {
    var openBtn = e.target.closest && e.target.closest('#aureo-ar-open-btn');
    if (openBtn) {
      e.preventDefault();
      openModal();
      return;
    }
    if (e.target.matches && e.target.matches('[data-aureo-close="1"]')) {
      closeModal();
      return;
    }
    // Selector de color: click en un swatch
    var swatch = e.target.closest && e.target.closest('.aureo-ar-color-swatch');
    if (swatch) {
      var hex = swatch.dataset.color;
      if (!hex) return;
      var mat = swatch.dataset.material;
      if (mat) {
        // Swatch por material: activa solo dentro de su grupo
        var group = swatch.closest('.aureo-ar-material-colors');
        if (group) {
          group.querySelectorAll('.aureo-ar-color-swatch').forEach(function(s) {
            s.classList.remove('aureo-ar-color-swatch--active');
          });
        }
        swatch.classList.add('aureo-ar-color-swatch--active');
        document.dispatchEvent(new CustomEvent('aureo-ar:set-material-color', { detail: { material: mat, color: hex } }));
      } else {
        // Swatch de color global (multi_color)
        document.querySelectorAll('#aureo-ar-colors .aureo-ar-color-swatch').forEach(function(s) {
          s.classList.remove('aureo-ar-color-swatch--active');
        });
        swatch.classList.add('aureo-ar-color-swatch--active');
        document.dispatchEvent(new CustomEvent('aureo-ar:set-color', { detail: hex }));
      }
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
  });

  console.log('[Aureo AR] UI listo. CFG:', window.AUREO_AR_CFG);
})();
