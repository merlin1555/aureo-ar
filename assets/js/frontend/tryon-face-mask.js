// tryon-face-mask.js — Modo máscaras y antifaz
// Ubica el modelo sobre el rostro del usuario anclado al puente nasal.
// El modelo debe tener su centro (nariz del antifaz / eje de la máscara) en el origen (0,0,0).
//
// Landmark MediaPipe Face Mesh utilizado:
//   6  → dorso nasal (puente de la nariz) — punto de referencia central del rostro
//
// Ejes del espacio local de la cara:
//   X: positivo → derecha del usuario
//   Y: positivo → arriba
//   Z: positivo → hacia adelante / fuera de la malla facial

// ── Calibración del modo máscara ─────────────────────────────────────────────
export const MASK_CALIB = {
  // Mismo landmark que lentes — el puente nasal es el centro natural de la cara
  anchorIdx: 6,

  // Escala del modelo en el espacio de MindAR.
  // Las máscaras suelen necesitar un valor similar al de los lentes; ajustar según el GLB.
  scale: 0.1,

  // Offsets en unidades del espacio 3D (espacio local de la cara, se rotan con la cabeza).
  offsetX:  0.0,   // lateral  — 0 si el modelo ya está centrado horizontalmente
  offsetY:  0.7,   // vertical — sube el antifaz hacia los ojos (+) o baja la máscara (-)
  offsetZ:  1.5,   // profundidad — negativo empuja el modelo hacia adelante

  // Corrección de rotación en grados encima de la orientación de la cara.
  rotX: 0,
  rotY: 0,
  rotZ: 0,
};

let _state = null;

// ── Modo máscara ─────────────────────────────────────────────────────────────
export const maskMode = {

  init(ctx) {
    _state = {
      THREE:        ctx.THREE,
      bridgeAnchor: ctx.bridgeAnchor,
      mask:         ctx.mask,
    };

    ctx.scene.add(ctx.mask);
    ctx.mask.visible = false;

    console.log('[Aureo AR] modo mask activado · anchor landmark:', MASK_CALIB.anchorIdx);
  },

  update() {
    if (!_state) return;
    const { THREE, bridgeAnchor, mask } = _state;
    _syncToAnchor(THREE, bridgeAnchor.group, mask);
  },

  reset() {
    _state = null;
  },
};

// ── Sincronización frame a frame ─────────────────────────────────────────────
function _syncToAnchor(THREE, anchorGroup, mask) {
  mask.visible = anchorGroup.visible;
  if (!anchorGroup.visible) return;

  const pos  = new THREE.Vector3();
  const quat = new THREE.Quaternion();
  anchorGroup.getWorldPosition(pos);
  anchorGroup.getWorldQuaternion(quat);

  // Offset posicional en espacio local de la cara → rotado al espacio mundial
  const offset = new THREE.Vector3(
    MASK_CALIB.offsetX,
    MASK_CALIB.offsetY,
    MASK_CALIB.offsetZ
  ).applyQuaternion(quat);

  mask.position.copy(pos).add(offset);

  // Rotación: orientación de la cara + corrección base del modelo
  if (
    MASK_CALIB.rotX !== 0 ||
    MASK_CALIB.rotY !== 0 ||
    MASK_CALIB.rotZ !== 0
  ) {
    const correction = new THREE.Quaternion().setFromEuler(
      new THREE.Euler(
        THREE.MathUtils.degToRad(MASK_CALIB.rotX),
        THREE.MathUtils.degToRad(MASK_CALIB.rotY),
        THREE.MathUtils.degToRad(MASK_CALIB.rotZ)
      )
    );
    mask.quaternion.copy(quat).multiply(correction);
  } else {
    mask.quaternion.copy(quat);
  }
}
