// tryon-face.js — CORE / ENTRY POINT
// Maneja el ciclo de vida del AR facial (MindAR + Three.js) y delega
// la lógica específica del aro al modo correspondiente (stud o dangle).

import { studMode }   from './tryon-face-stud.js';
import { dangleMode } from './tryon-face-dangle.js';

const CFG = window.AUREO_AR_CFG || {};
let started = false;
let mindarThree = null;
let renderer, scene, camera, THREE_REF;
let activeMode = null;

let leftEarring  = null;
let rightEarring = null;

// ═══════════════════════════════════════════════════════════════════
// Calibración compartida (la usan ambos modos vía import)
// ═══════════════════════════════════════════════════════════════════
export const CALIB = {
  // Anchors de MindAR para las orejas
  leftAnchorIdx:  234,
  rightAnchorIdx: 356,

  // Tamaño del modelo 3D
  scale: 0.15,

  // Offsets respecto al anchor (X se espeja para el lado derecho)
  offsetX: 0.00,
  offsetY: 0.00,
  offsetZ: 0.00,

  // Rotación base (Y y Z se espejan para el lado derecho)
  rotX: 0,
  rotY: 0,
  rotZ: 0,
};

// Factor de conversión cm → unidades 3D, derivado de scale.
// Lo exportamos porque dangle lo necesita para los cálculos físicos.
export const CM_TO_WORLD_SCALE = CALIB.scale / 0.15;

function isDangleMode() {
  return CFG.accessoryType === 'earring_dangle';
}

// ═══════════════════════════════════════════════════════════════════
// Arranque del AR
// ═══════════════════════════════════════════════════════════════════
async function startAR() {
  if (started) return;
  if (!CFG.glbUrl) throw new Error('No hay modelo GLB configurado.');

  let THREE, GLTFLoader, MindARThree;
  try {
    THREE = await import('three');
    THREE_REF = THREE;
    ({ GLTFLoader } = await import('three/addons/loaders/GLTFLoader.js'));
    ({ MindARThree } = await import('mindar-face-three'));
  } catch (e) {
    console.error('[Aureo AR] fallo al importar dependencias', e);
    throw new Error('No se pudieron cargar las librerías AR. Revisa tu conexión.');
  }

  const container = document.getElementById('aureo-ar-stage');
  mindarThree = new MindARThree({ container });
  ({ renderer, scene, camera } = mindarThree);

  // ── Luces ─────────────────────────────────────────────────────────
  scene.add(new THREE.AmbientLight(0xffffff, 1.0));
  const dir = new THREE.DirectionalLight(0xffffff, 0.8);
  dir.position.set(1, 1, 2);
  scene.add(dir);

  // ── Anchors izq/der ──────────────────────────────────────────────
  const leftAnchor  = mindarThree.addAnchor(CALIB.leftAnchorIdx);
  const rightAnchor = mindarThree.addAnchor(CALIB.rightAnchorIdx);

  // ── Occluder facial (oculta lo que está detrás de la cara) ───────
  const occluder = mindarThree.addFaceMesh();
  occluder.material = new THREE.MeshBasicMaterial({
    colorWrite: false,
    depthWrite: true,
  });
  occluder.renderOrder = 0;
  scene.add(occluder);

  // 🔍 Malla facial de debug (wireframe verde) — quitar en producción
  const faceMesh = mindarThree.addFaceMesh();
  faceMesh.material = new THREE.MeshBasicMaterial({
    color: 0x00ff88, wireframe: true, transparent: true, opacity: 0.3, depthTest: false,
  });
  faceMesh.renderOrder = 999;
  scene.add(faceMesh);

  // ── Carga del modelo GLB y clonado izq/der ───────────────────────
  const gltf = await new Promise((resolve, reject) => {
    new GLTFLoader().load(CFG.glbUrl, resolve, undefined, reject);
  });

  leftEarring  = gltf.scene.clone(true);
  rightEarring = gltf.scene.clone(true);

  leftEarring.scale.setScalar(CALIB.scale);
  rightEarring.scale.setScalar(CALIB.scale);
  rightEarring.scale.x *= -1; // efecto espejo

  [leftEarring, rightEarring].forEach(group => {
    group.renderOrder = 1;
    group.traverse(obj => {
      if (obj.isMesh?.material) {
        const materials = Array.isArray(obj.material) ? obj.material : [obj.material];
        materials.forEach(m => {
          m.depthTest = true;
          m.depthWrite = true;
        });
      }
    });
  });

  // ── Configurar física si vamos a modo dangle ─────────────────────
  if (isDangleMode() && CFG.physics) {
    dangleMode.configurePhysics(CFG.physics);
  }

  // ── Delegar al modo correspondiente ──────────────────────────────
  activeMode = isDangleMode() ? dangleMode : studMode;

  const ctx = {
    THREE,
    scene,
    leftAnchor,
    rightAnchor,
    leftEarring,
    rightEarring,
  };

  activeMode.init(ctx);

  // ── Arrancar ─────────────────────────────────────────────────────
  await mindarThree.start();
  window.AureoAR?.hideLoader?.();
  started = true;

  renderer.setAnimationLoop(animate);

  console.log('[Aureo AR] iniciado · modo:', isDangleMode() ? 'DANGLE' : 'STUD');
  console.log('  Anchors:', CALIB.leftAnchorIdx, '/', CALIB.rightAnchorIdx);
}

// ═══════════════════════════════════════════════════════════════════
// Loop de render — delega update al modo si lo necesita
// ═══════════════════════════════════════════════════════════════════
function animate() {
  if (!started || !renderer) return;

  if (activeMode?.update) {
    activeMode.update();
  }

  renderer.render(scene, camera);
}

// ═══════════════════════════════════════════════════════════════════
// Detención y limpieza
// ═══════════════════════════════════════════════════════════════════
async function stopAR() {
  if (!started || !mindarThree) return;

  try {
    renderer.setAnimationLoop(null);
    await mindarThree.stop();
  } catch (e) {
    console.warn('[Aureo AR] error al detener', e);
  }

  const stage = document.getElementById('aureo-ar-stage');
  if (stage) stage.querySelectorAll('video, canvas').forEach(n => n.remove());
  window.AureoAR?.resetStage?.();

  activeMode?.reset?.();
  activeMode  = null;
  mindarThree = null;
  leftEarring  = null;
  rightEarring = null;
  THREE_REF    = null;
  started = false;
}

// ═══════════════════════════════════════════════════════════════════
// Listeners públicos
// ═══════════════════════════════════════════════════════════════════
document.addEventListener('aureo-ar:open', () => {
  startAR().catch(err => {
    console.error('[Aureo AR]', err);
    window.AureoAR?.showError?.(err.message || String(err));
  });
});

document.addEventListener('aureo-ar:close', () => { stopAR(); });

console.log('[Aureo AR] tryon-face core cargado');
