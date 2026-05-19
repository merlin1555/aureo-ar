(function() {
    let scene, camera, renderer, currentModel, controls;

    function init() {
        const container = document.getElementById('aureo-canvas-container');
        if (!container || scene) return;

        scene = new THREE.Scene();
        scene.background = new THREE.Color(0xeeeeee);

        camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.1, 1000);
        camera.position.set(0, 1, 5);

        renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setSize(container.clientWidth, container.clientHeight);
        container.innerHTML = ''; 
        container.appendChild(renderer.domElement);

        // NUEVO: Inicializar OrbitControls para poder "arrastrar" la vista
        if (typeof THREE.OrbitControls !== 'undefined') {
            controls = new THREE.OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true; // Movimiento más suave
        }

        const ambientLight = new THREE.AmbientLight(0xffffff, 1.2);
        scene.add(ambientLight);

        const directionalLight = new THREE.DirectionalLight(0xffffff, 1);
        directionalLight.position.set(5, 5, 5);
        scene.add(directionalLight);

        setupControls();
        animate();
    }

    function setupControls() {
        const bgControl = document.getElementById('control-bg-color');
        if (bgControl) {
            bgControl.addEventListener('input', (e) => scene.background.set(e.target.value));
        }

        const scaleControl = document.getElementById('control-scale');
        if (scaleControl) {
            scaleControl.addEventListener('input', (e) => {
                if (currentModel) {
                    const s = parseFloat(e.target.value);
                    currentModel.scale.set(s, s, s);
                }
            });
        }

        const posYControl = document.getElementById('control-pos-y');
        if (posYControl) {
            posYControl.addEventListener('input', (e) => {
                if (currentModel) currentModel.position.y = parseFloat(e.target.value);
            });
        }

        const posXControl = document.getElementById('control-pos-x');
        if (posXControl) {
            posXControl.addEventListener('input', (e) => {
                if (currentModel) currentModel.position.x = parseFloat(e.target.value);
            });
        }
        // Rotación Horizontal (Eje Y)
        const rotYControl = document.getElementById('control-rot-y');
        if (rotYControl) {
            rotYControl.addEventListener('input', (e) => {
                if (currentModel) currentModel.rotation.y = parseFloat(e.target.value);
            });
        }

        // Rotación Vertical (Eje X)
        const rotXControl = document.getElementById('control-rot-x');
        if (rotXControl) {
            rotXControl.addEventListener('input', (e) => {
                if (currentModel) currentModel.rotation.x = parseFloat(e.target.value);
            });
        }
    }

    function loadModel(url) {
        const LoaderClass = THREE.GLTFLoader;
        if (!LoaderClass) return;

        const loader = new LoaderClass();
        document.getElementById('aureo-canvas-container').style.opacity = "0.5";

        loader.load(url, function(gltf) {
            if (currentModel) scene.remove(currentModel);
            currentModel = gltf.scene;
            
            const box = new THREE.Box3().setFromObject(currentModel);
            const size = box.getSize(new THREE.Vector3());
            const center = box.getCenter(new THREE.Vector3());

            // Normalizamos: la dimensión mayor será siempre 1 unidad de Three.js
            const maxDim = Math.max(size.x, size.y, size.z);
            const scaleFactor = 1 / maxDim;
            currentModel.scale.set(scaleFactor, scaleFactor, scaleFactor);

            // Lo centramos
            currentModel.position.x = -center.x * scaleFactor;
            currentModel.position.y = -center.y * scaleFactor;
            currentModel.position.z = -center.z * scaleFactor;

            scene.add(currentModel);
            document.getElementById('aureo-canvas-container').style.opacity = "1";
            document.getElementById('control-rot-y').value = 0;
            document.getElementById('control-rot-x').value = 0;
        });
    }

    function animate() {
        requestAnimationFrame(animate);
        
        if (controls) controls.update();

        const rotationCheck = document.getElementById('control-rotation');
        if (currentModel && rotationCheck && rotationCheck.checked) {
            currentModel.rotation.y += 0.01;
            // Opcional: Actualizar el valor del slider para que se vea el movimiento
            document.getElementById('control-rot-y').value = currentModel.rotation.y % 6.28;
        }

        renderer.render(scene, camera);
    }

    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('select-model-btn')) {
            const url = e.target.getAttribute('data-url');
            if (!scene) init();
            loadModel(url);
        }
    });
})();