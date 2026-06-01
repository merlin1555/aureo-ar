// tryon-face-dangle.js — Modo aro colgante (dangle) con física pendular
// Dos modelos por oreja: gancho (hook-default.glb) + aro del producto.
//   · El gancho tiene su punto más bajo en (0,0,0) local → cuelga hacia arriba desde ese punto.
//   · El aro del producto tiene su punto más alto en (0,0,0) local → cuelga hacia abajo desde ese punto.
//   · Ambos comparten la misma posición y rotación mundo ("punto de unión"),
//     logrando la ilusión de ser una pieza continua con movimiento pendular único.

import { CALIB, CM_TO_WORLD_SCALE } from './tryon-face.js';

// Corrector de posición exclusivo del modo dangle (unidades 3D, espacio local de la cara).
//   x: positivo → exterior de la oreja  (se espeja automáticamente en el lado derecho)
//   y: positivo → arriba / negativo → abajo (desplaza el pivote del péndulo)
//   z: positivo → hacia adelante / fuera de la malla facial
export const DANGLE_CORRECT = {
  x: -0.1,
  y: -4,
  z: -2.00,
};

// ── Constantes de la simulación ──────────────────────────────────
const PHYSICS = {
  chainLength: 2.5,   // longitud del péndulo en cm (distancia anchor → punto de unión)
  damping:     0.92,  // fricción (1 = sin fricción, 0 = se frena al instante)
  gravity:     9.8,   // m/s²
};

// ── Estado interno del módulo ────────────────────────────────────
let pendulumState = null;
let THREE_REF = null;

export const dangleMode = {
  /**
   * @param {Object} ctx
   * @param {Object} ctx.leftAnchor
   * @param {Object} ctx.rightAnchor
   * @param {THREE.Object3D} ctx.leftEarring   – modelo del aro (punto más alto en 0,0,0)
   * @param {THREE.Object3D} ctx.rightEarring
   * @param {THREE.Object3D} ctx.leftHook      – modelo del gancho (punto más bajo en 0,0,0)
   * @param {THREE.Object3D} ctx.rightHook
   * @param {THREE.Scene} ctx.scene
   * @param {Object} ctx.THREE
   */
  init(ctx) {
    THREE_REF = ctx.THREE;

    // Ambos modelos viven en la escena (no parentados al anchor);
    // update() los reposiciona cada frame en el punto de unión del péndulo.
    ctx.scene.add(ctx.leftEarring);
    ctx.scene.add(ctx.rightEarring);

    if (ctx.leftHook)  ctx.scene.add(ctx.leftHook);
    if (ctx.rightHook) ctx.scene.add(ctx.rightHook);

    const chainLength = (PHYSICS.chainLength / 100) * CM_TO_WORLD_SCALE;

    pendulumState = {
      left: {
        angle:    0,
        velocity: 0,
        anchor:   ctx.leftAnchor.group,
        earring:  ctx.leftEarring,
        hook:     ctx.leftHook  ?? null,
        sideSign: 1,
      },
      right: {
        angle:    0,
        velocity: 0,
        anchor:   ctx.rightAnchor.group,
        earring:  ctx.rightEarring,
        hook:     ctx.rightHook ?? null,
        sideSign: -1,
      },
      chainLength,
      lastTime: performance.now(),
    };

    console.log('[Aureo AR] modo dangle · longitud cadena:', chainLength.toFixed(3));
  },

  /**
   * Se llama cada frame desde el loop animate() del core.
   */
  update() {
    if (!pendulumState) return;

    const now = performance.now();
    const dt  = Math.min(0.05, (now - pendulumState.lastTime) / 1000);
    pendulumState.lastTime = now;

    updatePendulum(pendulumState.left,  dt);
    updatePendulum(pendulumState.right, dt);
  },

  reset() {
    pendulumState = null;
    THREE_REF     = null;
  },
};

// ── Helpers internos ─────────────────────────────────────────────

function getAnchorPosition(anchor, sideSign) {
  const landmarkPos = new THREE_REF.Vector3();
  anchor.getWorldPosition(landmarkPos);

  const anchorQuat = new THREE_REF.Quaternion();
  anchor.getWorldQuaternion(anchorQuat);

  const offset = new THREE_REF.Vector3(
    sideSign * (CALIB.offsetX + DANGLE_CORRECT.x),
    CALIB.offsetY + DANGLE_CORRECT.y,
    CALIB.offsetZ + DANGLE_CORRECT.z
  ).applyQuaternion(anchorQuat);

  return {
    position:   landmarkPos.add(offset),
    quaternion: anchorQuat,
  };
}

function updatePendulum(pend, dt) {
  const { anchor, earring, hook, sideSign } = pend;
  const L = pendulumState.chainLength;

  const { position: anchorPos, quaternion: anchorQuat } = getAnchorPosition(anchor, sideSign);

  // Ecuación del péndulo simple: α = -(g/L) · sin(θ)
  const g = PHYSICS.gravity * CM_TO_WORLD_SCALE;
  pend.velocity += -(g / L) * Math.sin(pend.angle) * dt;
  pend.velocity *= PHYSICS.damping;
  pend.angle    += pend.velocity * dt;

  // Punto de unión: extremo inferior del gancho / extremo superior del aro.
  // Ambos tienen su 0,0,0 local aquí, así que basta con posicionarlos juntos.
  const pendulumOffset = new THREE_REF.Vector3(
    L * Math.sin(pend.angle),
    -L * Math.cos(pend.angle),
    0
  ).applyQuaternion(anchorQuat);

  const joiningPoint = anchorPos.clone().add(pendulumOffset);

  // Rotación compartida: calibración base + inclinación del péndulo
  const rx = CALIB.rotX;
  const ry = CALIB.rotY + pend.angle * sideSign;
  const rz = CALIB.rotZ;

  earring.position.copy(joiningPoint);
  earring.rotation.set(rx, ry, rz);

  if (hook) {
    hook.position.copy(joiningPoint);
    hook.rotation.set(rx, ry, rz);
  }
}
