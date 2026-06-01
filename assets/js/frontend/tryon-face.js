// tryon-face.js — CORE / ENTRY POINT
// Maneja el ciclo de vida del AR facial (MindAR + Three.js) y delega
// la lógica específica del aro al modo correspondiente (stud o dangle).

import { studMode }                    from './tryon-face-stud.js';
import { dangleMode }                  from './tryon-face-dangle.js';
import { glassesMode, GLASSES_CALIB }  from './tryon-face-glasses.js';
import { maskMode,    MASK_CALIB }     from './tryon-face-mask.js';

const CFG = window.AUREO_AR_CFG || {};
let started = false;
let mindarThree = null;
let renderer, scene, camera, THREE_REF;
let activeMode = null;

let leftEarring  = null;
let rightEarring = null;
let leftHook     = null;
let rightHook    = null;
let glasses      = null;
let mask         = null;
let bridgeAnchor = null;

// ═══════════════════════════════════════════════════════════════════
// Calibración compartida (la usan ambos modos vía import)
// ═══════════════════════════════════════════════════════════════════
export const CALIB = {
  // Anchors de MindAR para las orejas (MediaPipe Face Mesh)
  // 234 = lóbulo izquierdo, 454 = lóbulo derecho
  leftAnchorIdx:  234,
  rightAnchorIdx: 454,

  // Tamaño del modelo 3D — ajustar según el GLB (empieza pequeño y sube)
  scale: 0.1,

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

function isGlassesMode() {
  return CFG.accessoryType === 'glasses';
}

function isMaskMode() {
  return CFG.accessoryType === 'mask';
}

// Retorna los modelos sobre los que aplican los cambios de color/material.
// En modo lentes o máscara: el modelo facial único.
// En modo aros: izquierdo y derecho (nunca los hooks).
function getColorTargets() {
  if (isGlassesMode()) return glasses ? [glasses] : [];
  if (isMaskMode())    return mask    ? [mask]    : [];
  return [leftEarring, rightEarring].filter(Boolean);
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
  // Solo luz ambiente: iluminación uniforme sin sombras direccionales.
  scene.add(new THREE.AmbientLight(0xffffff, 3.14));

  // ── Anchors — solo los que necesita el modo activo ───────────────
  let leftAnchor  = null;
  let rightAnchor = null;
  if (isGlassesMode()) {
    bridgeAnchor = mindarThree.addAnchor(GLASSES_CALIB.anchorIdx);
  } else if (isMaskMode()) {
    bridgeAnchor = mindarThree.addAnchor(MASK_CALIB.anchorIdx);
  } else {
    leftAnchor  = mindarThree.addAnchor(CALIB.leftAnchorIdx);
    rightAnchor = mindarThree.addAnchor(CALIB.rightAnchorIdx);
  }

  // ── Occluder facial (oculta lo que está detrás de la cara) ───────
  const occluder = mindarThree.addFaceMesh();
  occluder.material = new THREE.MeshBasicMaterial({
    colorWrite: false,
    depthWrite: true,
  });
  occluder.renderOrder = 0;
  scene.add(occluder);

  // 🔍 Malla facial de debug (wireframe verde) — quitar en producción
  // const faceMesh = mindarThree.addFaceMesh();
  // faceMesh.material = new THREE.MeshBasicMaterial({
  //   color: 0x00ff88, wireframe: true, transparent: true, opacity: 0.3, depthTest: false,
  // });
  // faceMesh.renderOrder = 999;
  // scene.add(faceMesh);

  // ── Carga del modelo GLB y clonado izq/der ───────────────────────
  const gltf = await new Promise((resolve, reject) => {
    new GLTFLoader().load(CFG.glbUrl, resolve, undefined, reject);
  });

  // Convertir TODOS los materiales a MeshBasicMaterial: muestra el color
  // puro sin ningún cálculo de iluminación, equivalente a luz ambiental
  // perfecta y uniforme desde todas las direcciones. Esto resuelve que
  // materiales metálicos (metalness alto) queden negros con solo ambient.
  const upgradeMat = m => {
    const basic = new THREE.MeshBasicMaterial({
      color:       m.color ? m.color.clone() : new THREE.Color(1, 1, 1),
      map:         m.map        ?? null,
      transparent: m.transparent ?? false,
      opacity:     m.opacity     ?? 1.0,
      side:        m.side        ?? THREE.FrontSide,
      alphaTest:   m.alphaTest   ?? 0,
      depthTest:   true,
      depthWrite:  true,
    });
    basic.name = m.name ?? '';
    return basic;
  };

  if (isGlassesMode()) {
    // ── Modo lentes: un único modelo centrado en el puente ────────────
    glasses = gltf.scene.clone(true);
    glasses.scale.setScalar(GLASSES_CALIB.scale);
    glasses.traverse(obj => {
      obj.renderOrder = 1;
      if (obj.isMesh && obj.material) {
        obj.material = Array.isArray(obj.material)
          ? obj.material.map(upgradeMat)
          : upgradeMat(obj.material);
      }
    });
  } else if (isMaskMode()) {
    // ── Modo máscara: un único modelo centrado en el eje nasal ────────
    mask = gltf.scene.clone(true);
    mask.scale.setScalar(MASK_CALIB.scale);
    mask.traverse(obj => {
      obj.renderOrder = 1;
      if (obj.isMesh && obj.material) {
        obj.material = Array.isArray(obj.material)
          ? obj.material.map(upgradeMat)
          : upgradeMat(obj.material);
      }
    });
  } else {
    // ── Modo aros: dos clones, derecho con espejo en X ────────────────
    leftEarring  = gltf.scene.clone(true);
    rightEarring = gltf.scene.clone(true);

    leftEarring.scale.setScalar(CALIB.scale);
    rightEarring.scale.setScalar(CALIB.scale);
    rightEarring.scale.x *= -1; // efecto espejo

    [leftEarring, rightEarring].forEach(group => {
      group.traverse(obj => {
        obj.renderOrder = 1;
        if (obj.isMesh && obj.material) {
          // Clonar materiales para que cada aro tenga instancias independientes
          // y el cambio de color no afecte al otro ni al asset original del GLTF.
          obj.material = Array.isArray(obj.material)
            ? obj.material.map(upgradeMat)
            : upgradeMat(obj.material);
        }
      });
    });
  }

  // ── Modelo de gancho por defecto (solo en modo earring_dangle) ──────
  if (!isGlassesMode() && !isMaskMode() && isDangleMode()) {
    const hookUrl = CFG.pluginUrl + 'assets/models/hook-default.glb';
    const hookGltf = await new Promise((resolve, reject) => {
      new GLTFLoader().load(hookUrl, resolve, undefined, reject);
    });

    leftHook  = hookGltf.scene.clone(true);
    rightHook = hookGltf.scene.clone(true);

    leftHook.scale.setScalar(CALIB.scale);
    rightHook.scale.setScalar(CALIB.scale);
    rightHook.scale.x *= -1;

    [leftHook, rightHook].forEach(group => {
      group.traverse(obj => {
        obj.renderOrder = 1;
        if (obj.isMesh && obj.material) {
          const materials = Array.isArray(obj.material) ? obj.material : [obj.material];
          materials.forEach(m => {
            m.depthTest = true;
            m.depthWrite = true;
          });
        }
      });
    });
  }

  // ── Aplicar color/materiales iniciales ───────────────────────────
  if ( CFG.colorMode === 'multi_color' && Array.isArray(CFG.colors) && CFG.colors.length > 0 ) {
    applyColor(CFG.colors[0]);
  } else if ( CFG.colorMode === 'per_material' && CFG.materialSlots ) {
    applyMaterialSlots(CFG.materialSlots, THREE);
  }

  // ── Delegar al modo correspondiente ──────────────────────────────
  activeMode = isGlassesMode() ? glassesMode
             : isMaskMode()    ? maskMode
             : isDangleMode()  ? dangleMode
             : studMode;

  const ctx = {
    THREE,
    scene,
    leftAnchor,
    rightAnchor,
    bridgeAnchor,
    leftEarring,
    rightEarring,
    glasses,
    mask,
    leftHook,
    rightHook,
  };

  activeMode.init(ctx);

  // ── Arrancar ─────────────────────────────────────────────────────
  await mindarThree.start();
  window.AureoAR?.hideLoader?.();
  started = true;

  renderer.setAnimationLoop(animate);

  if (isGlassesMode()) {
    console.log('[Aureo AR] iniciado · modo: GLASSES · anchor:', GLASSES_CALIB.anchorIdx);
  } else if (isMaskMode()) {
    console.log('[Aureo AR] iniciado · modo: MASK · anchor:', MASK_CALIB.anchorIdx);
  } else {
    console.log('[Aureo AR] iniciado · modo:', isDangleMode() ? 'DANGLE' : 'STUD');
    console.log('  Anchors:', CALIB.leftAnchorIdx, '/', CALIB.rightAnchorIdx);
  }
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
  activeMode   = null;
  mindarThree  = null;
  leftEarring  = null;
  rightEarring = null;
  leftHook     = null;
  rightHook    = null;
  glasses      = null;
  mask         = null;
  bridgeAnchor = null;
  THREE_REF    = null;
  started = false;
}

// ═══════════════════════════════════════════════════════════════════
// Listeners públicos
// ═══════════════════════════════════════════════════════════════════
// ═══════════════════════════════════════════════════════════════════
// Cambio de color dinámico
// Afecta al modelo activo (lentes o aros) — nunca a los hooks.
// ═══════════════════════════════════════════════════════════════════
function applyColor(hex) {
  if (!hex) return;
  getColorTargets().forEach(group => {
    group.traverse(obj => {
      if (!obj.isMesh || !obj.material) return;
      const mats = Array.isArray(obj.material) ? obj.material : [obj.material];
      mats.forEach(m => {
        m.map = null;       // quita la textura albedo para que el color sea sólido
        m.color.set(hex);
        m.needsUpdate = true;
      });
    });
  });
}

/**
 * Aplica slots { materialN: { type, value } } a los aros.
 * Solo toca meshes cuyo material.name coincide con "materialN".
 * Para "color": setea material.color y quita textura.
 * Para "texture": carga la URL y asigna material.map.
 */
function applyMaterialSlots(slots, THREE) {
  if (!slots) return;
  const loader = new THREE.TextureLoader();

  getColorTargets().forEach(group => {
    group.traverse(obj => {
      if (!obj.isMesh || !obj.material) return;
      const mats = Array.isArray(obj.material) ? obj.material : [obj.material];
      mats.forEach(m => {
        const key = (m.name || '').toLowerCase();
        if (!slots[key]) return;
        const slot = slots[key];
        if (slot.type === 'color') {
          // Soporte formato nuevo (colors[]) y legado (value)
          const hex = Array.isArray(slot.colors) && slot.colors.length ? slot.colors[0] : (slot.value || '#c0c0c0');
          m.color.set(hex);
          m.map = null;
          m.needsUpdate = true;
        } else if (slot.type === 'texture' && slot.value) {
          loader.load(slot.value, tex => {
            tex.flipY = false; // GLB convention
            m.map   = tex;
            m.color.set('#ffffff');
            m.needsUpdate = true;
          });
        }
      });
    });
  });
}

function applyMaterialColor(materialName, hex) {
  if (!hex || !materialName) return;
  getColorTargets().forEach(group => {
    group.traverse(obj => {
      if (!obj.isMesh || !obj.material) return;
      const mats = Array.isArray(obj.material) ? obj.material : [obj.material];
      mats.forEach(m => {
        if ((m.name || '').toLowerCase() !== materialName) return;
        m.map = null;
        m.color.set(hex);
        m.needsUpdate = true;
      });
    });
  });
}

document.addEventListener('aureo-ar:open', () => {
  startAR().catch(err => {
    console.error('[Aureo AR]', err);
    window.AureoAR?.showError?.(err.message || String(err));
  });
});

document.addEventListener('aureo-ar:close', () => { stopAR(); });

document.addEventListener('aureo-ar:set-color', e => {
  if (CFG.arType !== 'accessory') return;
  applyColor(e.detail);
});

document.addEventListener('aureo-ar:set-material-color', e => {
  if (CFG.arType !== 'accessory') return;
  applyMaterialColor(e.detail.material, e.detail.color);
});

console.log('[Aureo AR] tryon-face core cargado');
