// tryon-face-glasses.js — Modo lentes
// Ubica el modelo de lentes sobre el puente nasal del usuario.
// El modelo debe tener su puente centrado en el origen local (0,0,0).
//
// Landmark MediaPipe Face Mesh utilizado:
//   6  → dorso nasal (puente de la nariz) — punto donde descansan las almohadillas
//
// Ejes del espacio local de la cara (compartidos con los otros modos):
//   X: positivo → derecha del usuario
//   Y: positivo → arriba
//   Z: positivo → hacia adelante / fuera de la malla facial

// ── Calibración del modo lentes ─────────────────────────────────────────────
// Todos los valores son ajustables sin tocar la lógica de posicionamiento.
export const GLASSES_CALIB = {
  // Índice del landmark MediaPipe para el puente nasal
  anchorIdx: 6,

  // Escala del modelo 3D en el espacio de MindAR.
  // Punto de partida: aumentar si los lentes se ven muy pequeños, reducir si muy grandes.
  scale: 0.1,

  // Offsets en unidades del espacio 3D de MindAR, aplicados en el espacio local de la cara
  // (se rotan junto con la cabeza). Ajustar si el modelo no queda centrado.
  offsetX:  0.0,   // lateral — debería ser 0 si el puente ya está en X=0
  offsetY:  0.7,   // vertical — subir (+) o bajar (-) los lentes
  offsetZ: 0.0,   // profundidad — valor negativo empuja los lentes hacia adelante
                   // (fuera de la superficie facial) para evitar z-fighting

  // Corrección de rotación en grados (se aplica encima de la rotación de la cara).
  // Útil si el modelo llega orientado de forma distinta a como se espera.
  rotX: 0,
  rotY: 0,
  rotZ: 0,
};

let _state = null;

// ── Modo lentes ──────────────────────────────────────────────────────────────
export const glassesMode = {

  init(ctx) {
    _state = {
      THREE:        ctx.THREE,
      bridgeAnchor: ctx.bridgeAnchor,
      glasses:      ctx.glasses,
    };

    ctx.scene.add(ctx.glasses);
    ctx.glasses.visible = false;

    console.log('[Aureo AR] modo glasses activado · anchor landmark:', GLASSES_CALIB.anchorIdx);
  },

  update() {
    if (!_state) return;
    const { THREE, bridgeAnchor, glasses } = _state;
    _syncToAnchor(THREE, bridgeAnchor.group, glasses);
  },

  reset() {
    _state = null;
  },
};

// ── Sincronización frame a frame ─────────────────────────────────────────────
function _syncToAnchor(THREE, anchorGroup, glasses) {
  glasses.visible = anchorGroup.visible;
  if (!anchorGroup.visible) return;

  const pos  = new THREE.Vector3();
  const quat = new THREE.Quaternion();
  anchorGroup.getWorldPosition(pos);
  anchorGroup.getWorldQuaternion(quat);

  // Offset posicional en espacio local de la cara → rotado al espacio mundial
  const offset = new THREE.Vector3(
    GLASSES_CALIB.offsetX,
    GLASSES_CALIB.offsetY,
    GLASSES_CALIB.offsetZ
  ).applyQuaternion(quat);

  glasses.position.copy(pos).add(offset);

  // Rotación: orientación de la cara + corrección base del modelo
  if (
    GLASSES_CALIB.rotX !== 0 ||
    GLASSES_CALIB.rotY !== 0 ||
    GLASSES_CALIB.rotZ !== 0
  ) {
    const correction = new THREE.Quaternion().setFromEuler(
      new THREE.Euler(
        THREE.MathUtils.degToRad(GLASSES_CALIB.rotX),
        THREE.MathUtils.degToRad(GLASSES_CALIB.rotY),
        THREE.MathUtils.degToRad(GLASSES_CALIB.rotZ)
      )
    );
    glasses.quaternion.copy(quat).multiply(correction);
  } else {
    glasses.quaternion.copy(quat);
  }
}
