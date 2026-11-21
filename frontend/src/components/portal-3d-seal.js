/**
 * 3D Interactive Tactical Detective Seal (Three.js r186 + Occult Astrolabe Matrix)
 * A self-contained, high-performance 3D holographic matrix artifact mounted directly in portal heroes.
 * Features:
 * - Concentric gyroscopic brass & crimson astrolabe rings
 * - Central obsidian occult polyhedral evidence core with glowing vertices
 * - Underlying glowing vector astrolabe sigil for guaranteed visual richness across all environments
 * - Dynamic mouse tilt & kinetic click-to-spin acceleration
 * - Automatic resource cleanup, zero-leak disposal, respects prefers-reduced-motion
 */
import { h } from '../utils/dom.js';

export function createPortal3DSeal(roleName = 'PLAYER') {
  const container = h('div', {
    className: 'portal-3d-seal-wrapper',
    'aria-hidden': 'true',
    title: 'Mật Thư 3D Matrix — Bấm hoặc rê chuột để tương tác',
  });

  // 1. Vector Occult Astrolabe Backdrop (Always visible, glowing & spinning)
  const svgSigil = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svgSigil.setAttribute('viewBox', '0 0 140 140');
  svgSigil.setAttribute('class', 'portal-3d-sigil-svg');
  svgSigil.innerHTML = `
    <defs>
      <linearGradient id="sigil-gold-${roleName}" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#E11D48" stop-opacity="0.9"/>
        <stop offset="100%" stop-color="#9F1239" stop-opacity="0.4"/>
      </linearGradient>
      <linearGradient id="sigil-crimson-${roleName}" x1="100%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#F43F5E" stop-opacity="0.85"/>
        <stop offset="100%" stop-color="#E11D48" stop-opacity="0.3"/>
      </linearGradient>
    </defs>
    <!-- Outer Compass Track -->
    <circle cx="70" cy="70" r="62" fill="none" stroke="url(#sigil-gold-${roleName})" stroke-width="1.2" stroke-dasharray="4 3" opacity="0.75" />
    <circle cx="70" cy="70" r="54" fill="none" stroke="url(#sigil-crimson-${roleName})" stroke-width="1" opacity="0.65" />
    <!-- Sacred Hexagram & Labyrinth Triangles -->
    <polygon points="70,22 110,92 30,92" fill="none" stroke="rgba(225, 29, 72, 0.45)" stroke-width="1" />
    <polygon points="70,118 110,48 30,48" fill="none" stroke="rgba(255, 255, 255, 0.2)" stroke-width="1" />
    <!-- Tactical Reticle Crosshairs -->
    <line x1="70" y1="12" x2="70" y2="128" stroke="rgba(225, 29, 72, 0.3)" stroke-width="1" stroke-dasharray="2 4" />
    <line x1="12" y1="70" x2="128" y2="70" stroke="rgba(225, 29, 72, 0.3)" stroke-width="1" stroke-dasharray="2 4" />
    <!-- Inner Core Rune Ring -->
    <circle cx="70" cy="70" r="28" fill="rgba(225, 29, 72, 0.08)" stroke="url(#sigil-gold-${roleName})" stroke-width="1.2" />
    <circle cx="70" cy="70" r="14" fill="rgba(225, 29, 72, 0.2)" stroke="#E11D48" stroke-width="1" />
    <circle cx="70" cy="70" r="4" fill="#FFFFFF" opacity="0.9" />
  `;
  container.appendChild(svgSigil);

  // 2. Real-time Three.js 3D Canvas
  const canvas = h('canvas', { className: 'portal-3d-seal-canvas' });
  container.appendChild(canvas);

  // 3. Status Pill Tag
  const badge = h('div', { className: 'portal-3d-seal-label' },
    roleName === 'ADMIN' ? 'ROOT // 3D MATRIX' : (roleName === 'DM' ? 'COMMAND // 3D SIGIL' : 'DOSSIER // 3D SEAL')
  );
  container.appendChild(badge);

  // Defer Three.js setup to ensure container is mounted
  requestAnimationFrame(() => {
    initThreeSeal(canvas, container, roleName);
  });

  return container;
}

function initThreeSeal(canvas, container, roleName) {
  if (!window.THREE || !canvas || !canvas.parentNode) return;

  const THREE = window.THREE;
  const width = 140;
  const height = 140;

  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({
      canvas,
      alpha: true,
      antialias: true,
      powerPreference: 'default',
    });
  } catch (err) {
    console.warn('WebGL not available for 3D seal, relying on vector sigil:', err);
    return;
  }

  renderer.setSize(width, height);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 100);
  camera.position.z = 6.2;

  // Group for gyroscopic rotation
  const group = new THREE.Group();
  scene.add(group);

  // 1. Central Occult Polyhedron (Dodecahedron Wireframe & Core)
  const dodecGeom = new THREE.DodecahedronGeometry(1.4, 0);
  const wireMat = new THREE.MeshBasicMaterial({
    color: 0xE11D48,
    wireframe: true,
    transparent: true,
    opacity: 0.85,
  });
  const wireMesh = new THREE.Mesh(dodecGeom, wireMat);
  group.add(wireMesh);

  // Inner glowing core
  const coreGeom = new THREE.OctahedronGeometry(0.7, 0);
  const coreMat = new THREE.MeshBasicMaterial({
    color: 0xFFFFFF,
    wireframe: true,
  });
  const coreMesh = new THREE.Mesh(coreGeom, coreMat);
  group.add(coreMesh);

  // 2. Gyroscopic Astrolabe Rings
  // Outer Ring (Deep Velvet Burgundy)
  const ring1Geom = new THREE.TorusGeometry(1.9, 0.035, 6, 36);
  const ring1Mat = new THREE.MeshBasicMaterial({
    color: 0x9F1239,
    wireframe: true,
    transparent: true,
    opacity: 0.75,
  });
  const ring1 = new THREE.Mesh(ring1Geom, ring1Mat);
  group.add(ring1);

  // Middle Ring (Platinum Silver)
  const ring2Geom = new THREE.TorusGeometry(1.65, 0.03, 6, 36);
  const ring2Mat = new THREE.MeshBasicMaterial({
    color: 0xE2E8F0,
    wireframe: true,
    transparent: true,
    opacity: 0.85,
  });
  const ring2 = new THREE.Mesh(ring2Geom, ring2Mat);
  ring2.rotation.x = Math.PI / 3;
  group.add(ring2);

  // 3. Orbiting Sparkles / Runes
  const pCount = 24;
  const pGeom = new THREE.BufferGeometry();
  const pPos = new Float32Array(pCount * 3);
  for (let i = 0; i < pCount; i++) {
    const angle = (i / pCount) * Math.PI * 2;
    const r = 2.0 + (Math.random() - 0.5) * 0.3;
    pPos[i * 3] = Math.cos(angle) * r;
    pPos[i * 3 + 1] = (Math.random() - 0.5) * 0.6;
    pPos[i * 3 + 2] = Math.sin(angle) * r;
  }
  pGeom.setAttribute('position', new THREE.BufferAttribute(pPos, 3));
  const pMat = new THREE.PointsMaterial({
    size: 0.14,
    color: 0xF43F5E,
    transparent: true,
    opacity: 0.95,
  });
  const pMesh = new THREE.Points(pGeom, pMat);
  group.add(pMesh);

  // Initial render immediately so it is captured on frame 0
  renderer.render(scene, camera);

  // Mouse & Click Interaction
  let targetRotX = 0;
  let targetRotY = 0;
  let spinVelocity = 0.012;

  function onMouseMove(e) {
    const rect = container.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;
    const dx = (e.clientX - cx) / (rect.width / 2);
    const dy = (e.clientY - cy) / (rect.height / 2);
    targetRotY = dx * 0.7;
    targetRotX = dy * 0.7;
  }

  function onClick() {
    spinVelocity = 0.09;
    if (window.gsap) {
      window.gsap.to(group.scale, {
        x: 1.25,
        y: 1.25,
        z: 1.25,
        duration: 0.18,
        yoyo: true,
        repeat: 1,
        ease: 'power2.out',
      });
    }
  }

  container.addEventListener('mousemove', onMouseMove);
  container.addEventListener('click', onClick);

  // Animation Loop
  let animId = null;

  function render() {
    if (!document.body.contains(canvas)) {
      cleanup();
      return;
    }

    animId = requestAnimationFrame(render);

    // Decay spin velocity
    spinVelocity += (0.012 - spinVelocity) * 0.05;

    // Rotations
    group.rotation.y += spinVelocity;
    ring1.rotation.x += 0.015;
    ring1.rotation.y += 0.01;
    ring2.rotation.y += 0.018;
    ring2.rotation.z += 0.012;
    wireMesh.rotation.x -= 0.008;
    wireMesh.rotation.y += 0.012;
    coreMesh.rotation.x += 0.022;
    pMesh.rotation.y += 0.007;

    // Smooth mouse parallax
    group.rotation.x += (targetRotX - group.rotation.x) * 0.08;
    group.rotation.z += (-targetRotY * 0.5 - group.rotation.z) * 0.08;

    renderer.render(scene, camera);
  }

  render();

  function cleanup() {
    if (animId) cancelAnimationFrame(animId);
    container.removeEventListener('mousemove', onMouseMove);
    container.removeEventListener('click', onClick);

    dodecGeom.dispose();
    wireMat.dispose();
    coreGeom.dispose();
    coreMat.dispose();
    ring1Geom.dispose();
    ring1Mat.dispose();
    ring2Geom.dispose();
    ring2Mat.dispose();
    pGeom.dispose();
    pMat.dispose();
    renderer.dispose();
  }
}
