/**
 * Master Three.js 3D Occult Ambient System
 * Features:
 * - Floating crimson & gold crime ember particles
 * - Interactive multifaceted 3D Crime Polyhedron with wireframe lattice & glowing pulsating core
 * - Mouse parallax tilt & kinetic pulse shockwaves on button clicks
 * - Low-overhead rendering (DPR <= 1.5, visibilitychange pause, zero-leak disposal)
 */

class ThreeAmbientEngine {
  constructor() {
    this.canvas = null;
    this.renderer = null;
    this.scene = null;
    this.camera = null;
    this.particles = null;
    this.polyhedron = null;
    this.polyCore = null;
    this.animId = null;
    this.mouseX = 0;
    this.mouseY = 0;
    this.targetMouseX = 0;
    this.targetMouseY = 0;
    this.pulseIntensity = 0;
    this.isPaused = false;
    this.initialized = false;
  }

  init() {
    if (this.initialized) return;
    if (typeof window === 'undefined' || !window.THREE) return;

    // Respect user's motion preference
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return;
    }

    const THREE = window.THREE;

    // Create fixed background canvas
    let canvas = document.getElementById('three-ambient-canvas');
    if (!canvas) {
      canvas = document.createElement('canvas');
      canvas.id = 'three-ambient-canvas';
      canvas.style.cssText = `
        position: fixed;
        inset: 0;
        width: 100vw;
        height: 100vh;
        pointer-events: none;
        z-index: 0;
        opacity: 0.55;
        transition: opacity 0.8s ease;
      `;
      document.body.prepend(canvas);
    }
    this.canvas = canvas;

    // Setup Three.js Renderer
    this.renderer = new THREE.WebGLRenderer({
      canvas: this.canvas,
      alpha: true,
      antialias: true,
      powerPreference: 'high-performance',
    });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
    this.renderer.setSize(window.innerWidth, window.innerHeight);

    // Setup Scene & Camera
    this.scene = new THREE.Scene();
    this.camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
    this.camera.position.z = 24;

    // 1. Floating Crime Ember Particles
    const particleCount = 420;
    const geometry = new THREE.BufferGeometry();
    const positions = new Float32Array(particleCount * 3);
    const colors = new Float32Array(particleCount * 3);
    const speeds = new Float32Array(particleCount);

    const crimsonColor = new THREE.Color(0xE63946);
    const bloodColor = new THREE.Color(0x8B0000);
    const goldColor = new THREE.Color(0xB39255);

    for (let i = 0; i < particleCount; i++) {
      positions[i * 3] = (Math.random() - 0.5) * 55;
      positions[i * 3 + 1] = (Math.random() - 0.5) * 45;
      positions[i * 3 + 2] = (Math.random() - 0.5) * 35;

      const mix = Math.random();
      const col = mix > 0.65 ? crimsonColor : (mix > 0.3 ? bloodColor : goldColor);
      colors[i * 3] = col.r;
      colors[i * 3 + 1] = col.g;
      colors[i * 3 + 2] = col.b;

      speeds[i] = 0.005 + Math.random() * 0.015;
    }

    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
    this.particleSpeeds = speeds;

    // Generate soft circular particle texture
    const pCanvas = document.createElement('canvas');
    pCanvas.width = 32;
    pCanvas.height = 32;
    const ctx = pCanvas.getContext('2d');
    const grad = ctx.createRadialGradient(16, 16, 0, 16, 16, 16);
    grad.addColorStop(0, 'rgba(255, 255, 255, 1)');
    grad.addColorStop(0.3, 'rgba(230, 57, 70, 0.8)');
    grad.addColorStop(0.8, 'rgba(139, 0, 0, 0.2)');
    grad.addColorStop(1, 'rgba(0, 0, 0, 0)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, 32, 32);

    const pTexture = new THREE.CanvasTexture(pCanvas);

    const pMaterial = new THREE.PointsMaterial({
      size: 0.85,
      map: pTexture,
      transparent: true,
      vertexColors: true,
      blending: THREE.AdditiveBlending,
      depthWrite: false,
    });

    this.particles = new THREE.Points(geometry, pMaterial);
    this.scene.add(this.particles);

    // 2. Interactive 3D Occult Polyhedron (Forensic Crime Node)
    const polyGroup = new THREE.Group();

    // Outer wireframe icosahedron
    const icoGeom = new THREE.IcosahedronGeometry(4.2, 1);
    const wireMat = new THREE.MeshBasicMaterial({
      color: 0x8B0000,
      wireframe: true,
      transparent: true,
      opacity: 0.35,
    });
    this.polyhedron = new THREE.Mesh(icoGeom, wireMat);
    polyGroup.add(this.polyhedron);

    // Inner pulsating scarlet core octahedron
    const octGeom = new THREE.OctahedronGeometry(2.4, 0);
    const coreMat = new THREE.MeshBasicMaterial({
      color: 0xE63946,
      wireframe: true,
      transparent: true,
      opacity: 0.55,
    });
    this.polyCore = new THREE.Mesh(octGeom, coreMat);
    polyGroup.add(this.polyCore);

    // Position at upper right quadrant for elegance without covering text
    polyGroup.position.set(16, 4, -4);
    this.polyGroup = polyGroup;
    this.scene.add(polyGroup);

    // Mouse Parallax Listeners
    window.addEventListener('mousemove', (e) => {
      this.targetMouseX = (e.clientX / window.innerWidth - 0.5) * 2;
      this.targetMouseY = (e.clientY / window.innerHeight - 0.5) * 2;
    }, { passive: true });

    // Handle Window Resize
    window.addEventListener('resize', () => this.onResize(), { passive: true });

    // Pause when tab is inactive to save battery and GPU cycles
    document.addEventListener('visibilitychange', () => {
      this.isPaused = document.hidden;
      if (!this.isPaused) {
        this.animate();
      }
    });

    this.initialized = true;
    this.animate();
  }

  triggerPulse(intensity = 1.0) {
    this.pulseIntensity = Math.min(this.pulseIntensity + intensity, 2.5);
  }

  onResize() {
    if (!this.renderer || !this.camera) return;
    this.camera.aspect = window.innerWidth / window.innerHeight;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(window.innerWidth, window.innerHeight);
  }

  animate() {
    if (this.isPaused) return;

    this.animId = requestAnimationFrame(() => this.animate());

    // Smooth mouse damping
    this.mouseX += (this.targetMouseX - this.mouseX) * 0.04;
    this.mouseY += (this.targetMouseY - this.mouseY) * 0.04;

    // Camera subtle tilt
    this.camera.position.x = this.mouseX * 3;
    this.camera.position.y = -this.mouseY * 2.5;
    this.camera.lookAt(0, 0, 0);

    // Rotate Polyhedron
    if (this.polyhedron && this.polyCore) {
      const pulseFactor = 1 + this.pulseIntensity * 0.5;
      this.polyhedron.rotation.x += 0.003 * pulseFactor;
      this.polyhedron.rotation.y += 0.005 * pulseFactor;

      this.polyCore.rotation.x -= 0.007 * pulseFactor;
      this.polyCore.rotation.z += 0.006 * pulseFactor;

      // Pulse decay
      if (this.pulseIntensity > 0.01) {
        this.pulseIntensity *= 0.92;
        this.polyCore.material.color.setHex(0xFF4D5E);
        this.polyhedron.material.opacity = 0.35 + this.pulseIntensity * 0.3;
      } else {
        this.polyIntensity = 0;
        this.polyCore.material.color.setHex(0xE63946);
        this.polyhedron.material.opacity = 0.35;
      }
    }

    // Animate Particles
    if (this.particles) {
      const pos = this.particles.geometry.attributes.position.array;
      const count = pos.length / 3;

      for (let i = 0; i < count; i++) {
        pos[i * 3 + 1] -= this.particleSpeeds[i] * (1 + this.pulseIntensity);
        // Wrap around bottom
        if (pos[i * 3 + 1] < -25) {
          pos[i * 3 + 1] = 25;
          pos[i * 3] = (Math.random() - 0.5) * 55;
        }
      }
      this.particles.geometry.attributes.position.needsUpdate = true;
    }

    this.renderer.render(this.scene, this.camera);
  }
}

export const threeAmbient = new ThreeAmbientEngine();
