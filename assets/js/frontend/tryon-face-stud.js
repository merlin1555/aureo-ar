// tryon-face-stud.js — Modo aro pegado (stud)
// El aro se parenta al anchor de la oreja y se mantiene fijo respecto a la cara.
// Sin física: posición y rotación se aplican una sola vez en init().

import { CALIB } from './tryon-face.js';

export const studMode = {
  /**
   * @param {Object} ctx
   * @param {Object} ctx.leftAnchor   - anchor de MindAR para la oreja izquierda
   * @param {Object} ctx.rightAnchor  - anchor de MindAR para la oreja derecha
   * @param {THREE.Object3D} ctx.leftEarring
   * @param {THREE.Object3D} ctx.rightEarring
   */
  init(ctx) {
    // Aplicar posiciones y rotaciones relativas al anchor
    applyStudPositions(ctx.leftEarring, ctx.rightEarring);
    
    // Los anchors de MindAR tienen .group que es el THREE.Group donde agregar objetos
    ctx.leftAnchor.group.add(ctx.leftEarring);
    ctx.rightAnchor.group.add(ctx.rightEarring);
    
    console.log('[Aureo AR] modo stud activado');
  },

  // Sin update por frame: el anchor de MindAR mueve el aro automáticamente.
  // Sin reset: el cleanup del core (escena vaciada) es suficiente.
};

/**
 * Aplica las transformaciones de posición y rotación a los aros
 * según la calibración definida en CALIB.
 * El aro derecho espeja X, rotY y rotZ del izquierdo.
 */
function applyStudPositions(left, right) {
  // Aro izquierdo
  left.position.set(CALIB.offsetX, CALIB.offsetY, CALIB.offsetZ);
  left.rotation.set(CALIB.rotX, CALIB.rotY, CALIB.rotZ);

  // Aro derecho (espejado en X, rotY, rotZ)
  right.position.set(-CALIB.offsetX, CALIB.offsetY, CALIB.offsetZ);
  right.rotation.set(CALIB.rotX, -CALIB.rotY, -CALIB.rotZ);
}