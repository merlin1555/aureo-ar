// tryon-face-dangle.js — Modo aro colgante (dangle) con física pendular
// El aro NO se parenta al anchor: el core calcula su posición cada frame
// simulando un péndulo que cuelga del anchor de la oreja.

import { CALIB, CM_TO_WORLD_SCALE } from './tryon-face.js';

// ── Constantes de la simulación ──────────────────────────────────
const PHYSICS = {
  chainLength: 2.5,   // longitud del péndulo en cm
  damping:     0.92,  // fricción (1 = sin fricción, 0 = se frena al instante)
  gravity:     9.8,   // m/s² (gravedad real)
};

// ── Estado interno del módulo ────────────────────────────────────
let pendulumState = null;
let THREE_REF = null;

export const dangleMode = {
  /**
   * Permite que el core inyecte overrides desde window.AUREO_AR_CFG.physics
   * antes de llamar a init().
   */
  configurePhysics(cfgPhysics) {
    if (!cfgPhysics) return;
    if (typeof cfgPhysics.chainLength === 'number') PHYSICS.chainLength = cfgPhysics.chainLength;
    if (typeof cfgPhysics.damping     === 'number') PHYSICS.damping     = cfgPhysics.damping;
  },

  /**
   * @param {Object} ctx
   * @param {Object} ctx.leftAnchor
   * @param {Object} ctx.rightAnchor
   * @param {THREE.Object3D} ctx.leftEarring
   * @param {THREE.Object3D} ctx.rightEarring
   * @param {THREE.Scene} ctx.scene
   * @param {Object} ctx.THREE - referencia al módulo three importado por el core
   */
  init(ctx) {
    THREE_REF = ctx.THREE;

    // En modo dangle los aros NO se parentan al anchor; viven en la escena
    // y el update() los reposiciona cada frame.
    ctx.scene.add(ctx.leftEarring);
    ctx.scene.add(ctx.rightEarring);

    const chainLength = (PHYSICS.chainLength / 100) * CM_TO_WORLD_SCALE;

    pendulumState = {
      left: {
        angle: 0,
        velocity: 0,
        anchor:  ctx.leftAnchor.group,
        earring: ctx.leftEarring,
        sideSign: 1,
      },
      right: {
        angle: 0,
        velocity: 0,
        anchor:  ctx.rightAnchor.group,
        earring: ctx.rightEarring,
        sideSign: -1,
      },
      chainLength,
      lastTime: performance.now(),
    };

    console.log('[Aureo AR] modo dangle · longitud:', chainLength.toFixed(3));
  },

  /**
   * Se llama cada frame desde el loop animate() del core.
   * Dangle calcula su propio dt desde performance.now() para no acoplarse al loop.
   */
  update() {
    if (!pendulumState) return;

    const now = performance.now();
    const dt = Math.min(0.05, (now - pendulumState.lastTime) / 1000);
    pendulumState.lastTime = now;

    updatePendulum(pendulumState.left, dt);
    updatePendulum(pendulumState.right, dt);
  },

  reset() {
    pendulumState = null;
    THREE_REF = null;
  },
};

// ── Helpers internos ─────────────────────────────────────────────

function getAnchorPosition(anchor, sideSign) {
  const landmarkPos = new THREE_REF.Vector3();
  anchor.getWorldPosition(landmarkPos);

  const anchorQuat = new THREE_REF.Quaternion();
  anchor.getWorldQuaternion(anchorQuat);

  const offset = new THREE_REF.Vector3(
    sideSign * CALIB.offsetX,
    CALIB.offsetY,
    CALIB.offsetZ
  ).applyQuaternion(anchorQuat);

  return {
    position: landmarkPos.add(offset),
    quaternion: anchorQuat,
  };
}

function updatePendulum(pend, dt) {
  const { anchor, earring, sideSign } = pend;
  const L = pendulumState.chainLength;

  const { position: anchorPos, quaternion: anchorQuat } = getAnchorPosition(anchor, sideSign);

  // Ecuación del péndulo simple: a = -(g/L) · sin(θ)
  const g = PHYSICS.gravity * CM_TO_WORLD_SCALE;
  const acceleration = -(g / L) * Math.sin(pend.angle);

  pend.velocity += acceleration * dt;
  pend.velocity *= PHYSICS.damping;
  pend.angle += pend.velocity * dt;

  // Posición del aro: pivote (anchor + offset) + vector pendular rotado por la cabeza
  const pendulumOffset = new THREE_REF.Vector3(
    L * Math.sin(pend.angle),
    -L * Math.cos(pend.angle),
    0
  ).applyQuaternion(anchorQuat);

  earring.position.copy(anchorPos).add(pendulumOffset);

  earring.rotation.set(
    CALIB.rotX,
    CALIB.rotY + pend.angle * sideSign,
    CALIB.rotZ
  );
}