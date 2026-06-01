// tryon-face-stud.js — Modo aro pegado (stud)
// Los aros viven en la escena (no parenteados al anchor) y se sincronizan
// con la posición/rotación del anchor cada frame, igual que dangle pero sin física.

import { CALIB } from './tryon-face.js';

// Corrector de posición exclusivo del modo stud (unidades 3D, espacio local de la cara).
// Los ejes son relativos a la orientación de la cara, no a la cámara:
//   x: positivo → exterior de la oreja  (se espeja automáticamente en el lado derecho)
//   y: positivo → arriba / negativo → abajo (hacia el lóbulo)
//   z: positivo → hacia adelante / fuera de la malla facial
export const STUD_CORRECT = {
  x: -0.4,
  y: -2.9,
  z: -2.00,
};

let _state = null;

export const studMode = {
  init(ctx) {
    _state = {
      THREE:        ctx.THREE,
      leftAnchor:   ctx.leftAnchor,
      rightAnchor:  ctx.rightAnchor,
      leftEarring:  ctx.leftEarring,
      rightEarring: ctx.rightEarring,
    };

    ctx.scene.add(ctx.leftEarring);
    ctx.scene.add(ctx.rightEarring);
    ctx.leftEarring.visible  = false;
    ctx.rightEarring.visible = false;

    console.log('[Aureo AR] modo stud activado');
  },

  update() {
    if (!_state) return;
    const { THREE, leftAnchor, rightAnchor, leftEarring, rightEarring } = _state;
    syncToAnchor(THREE, leftAnchor.group,  leftEarring,   1);
    syncToAnchor(THREE, rightAnchor.group, rightEarring, -1);
  },

  reset() {
    _state = null;
  },
};

function syncToAnchor(THREE, anchorGroup, earring, sideSign) {
  earring.visible = anchorGroup.visible;
  if (!anchorGroup.visible) return;

  const pos  = new THREE.Vector3();
  const quat = new THREE.Quaternion();
  anchorGroup.getWorldPosition(pos);
  anchorGroup.getWorldQuaternion(quat);

  const offset = new THREE.Vector3(
    sideSign * (CALIB.offsetX + STUD_CORRECT.x),
    CALIB.offsetY + STUD_CORRECT.y,
    CALIB.offsetZ + STUD_CORRECT.z
  ).applyQuaternion(quat);

  earring.position.copy(pos).add(offset);
  earring.quaternion.copy(quat);
}
