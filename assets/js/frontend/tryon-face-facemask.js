// tryon-face-facemask.js — Modo mascarilla (cubre mandíbula / parte inferior de la cara)
// Ubica el modelo anclado al mentón (punto inferior del rostro).
// El modelo debe tener su centro en el origen (0,0,0).
//
// Landmarks MediaPipe Face Mesh relevantes para la zona bucal:
//    13 → centro de la boca (unión de labios)                    ← ANCHOR usado
//     0 → labio inferior exterior central
//    17 → labio inferior exterior
//    57 → comisura izquierda
//   287 → comisura derecha
//   152 → mentón (chin) — punto inferior de la cara
//
// Ejes del espacio local de la cara:
//   X: positivo → derecha del usuario
//   Y: positivo → arriba
//   Z: positivo → hacia adelante / fuera de la malla facial

// ── Calibración del modo mascarilla ─────────────────────────────────────────
export const FACEMASK_CALIB = {
  // Anchor en el centro de la boca (landmark 13).
  // El modelo debe tener su origen (0,0,0) justo en la posición de la boca.
  anchorIdx: 13,

  // Escala del modelo en el espacio de MindAR.
  scale: 0.099,

  // Offsets en el espacio local de la cara (se rotan con la cabeza).
  offsetX:  0.0,   // lateral  — 0 si el modelo está centrado en X
  offsetY:  -2.0,   // vertical — ajustar si el modelo no coincide exactamente con la boca
  offsetZ:  0.3,   // profundidad — negativo empuja el modelo hacia adelante

  // Corrección de rotación en grados encima de la orientación de la cara.
  rotX: 0,
  rotY: 180,
  rotZ: 0,
};

let _state = null;

// ── Modo mascarilla ──────────────────────────────────────────────────────────
export const facemaskMode = {

  init(ctx) {
    _state = {
      THREE:       ctx.THREE,
      chinAnchor:  ctx.chinAnchor,
      facemask:    ctx.facemask,
    };

    ctx.scene.add(ctx.facemask);
    ctx.facemask.visible = false;

    console.log('[Aureo AR] modo facemask activado · anchor landmark:', FACEMASK_CALIB.anchorIdx);
  },

  update() {
    if (!_state) return;
    const { THREE, chinAnchor, facemask } = _state;
    _syncToAnchor(THREE, chinAnchor.group, facemask);
  },

  reset() {
    _state = null;
  },
};

// ── Sincronización frame a frame ─────────────────────────────────────────────
function _syncToAnchor(THREE, anchorGroup, facemask) {
  facemask.visible = anchorGroup.visible;
  if (!anchorGroup.visible) return;

  const pos  = new THREE.Vector3();
  const quat = new THREE.Quaternion();
  anchorGroup.getWorldPosition(pos);
  anchorGroup.getWorldQuaternion(quat);

  // Offset posicional en espacio local de la cara → rotado al espacio mundial
  const offset = new THREE.Vector3(
    FACEMASK_CALIB.offsetX,
    FACEMASK_CALIB.offsetY,
    FACEMASK_CALIB.offsetZ
  ).applyQuaternion(quat);

  facemask.position.copy(pos).add(offset);

  // Rotación: orientación de la cara + corrección base del modelo
  if (
    FACEMASK_CALIB.rotX !== 0 ||
    FACEMASK_CALIB.rotY !== 0 ||
    FACEMASK_CALIB.rotZ !== 0
  ) {
    const correction = new THREE.Quaternion().setFromEuler(
      new THREE.Euler(
        THREE.MathUtils.degToRad(FACEMASK_CALIB.rotX),
        THREE.MathUtils.degToRad(FACEMASK_CALIB.rotY),
        THREE.MathUtils.degToRad(FACEMASK_CALIB.rotZ)
      )
    );
    facemask.quaternion.copy(quat).multiply(correction);
  } else {
    facemask.quaternion.copy(quat);
  }
}
