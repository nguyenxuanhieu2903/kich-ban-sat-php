/**
 * Interactive Button Click & Kinetic Occult Shockwave System
 * Spawns dynamic scarlet shockwaves, wax seal splatter, button depression,
 * and procedural subtle mechanical typewriter/stamp click sound via Web Audio API (0KB assets).
 */
import { threeAmbient } from './three-ambient.js';

class ClickEffectsEngine {
  constructor() {
    this.container = null;
    this.audioCtx = null;
    this.initialized = false;
  }

  init() {
    if (this.initialized) return;
    if (typeof document === 'undefined') return;

    // Create top-layer click particle container
    let container = document.getElementById('click-effects-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'click-effects-container';
      container.style.cssText = `
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 99999;
        overflow: hidden;
      `;
      document.body.appendChild(container);
    }
    this.container = container;

    // Global click handler
    document.addEventListener('click', (e) => this.handleClick(e), { capture: true, passive: true });

    this.initialized = true;
  }

  getAudioContext() {
    if (!this.audioCtx && typeof window !== 'undefined') {
      const AudioContext = window.AudioContext || window.webkitAudioContext;
      if (AudioContext) {
        this.audioCtx = new AudioContext();
      }
    }
    if (this.audioCtx && this.audioCtx.state === 'suspended') {
      this.audioCtx.resume();
    }
    return this.audioCtx;
  }

  playTactileClickSound() {
    try {
      const ctx = this.getAudioContext();
      if (!ctx) return;

      const osc = ctx.createOscillator();
      const gain = ctx.createGain();

      // Muted mechanical thud / typewriter click
      osc.type = 'triangle';
      osc.frequency.setValueAtTime(140, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(32, ctx.currentTime + 0.045);

      gain.gain.setValueAtTime(0.04, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.045);

      osc.connect(gain);
      gain.connect(ctx.destination);

      osc.start(ctx.currentTime);
      osc.stop(ctx.currentTime + 0.05);
    } catch {
      // Audio context policy or disabled — silent fallback
    }
  }

  handleClick(e) {
    const x = e.clientX;
    const y = e.clientY;

    const target = e.target.closest('button, .btn, a, .tactical-filter-btn, .pill-noir, .demo-role-btn, .card-dark, .status-badge, .game-card-dossier');
    const isInteractive = Boolean(target);

    // 1. Play subtle tactile procedural sound
    if (isInteractive) {
      this.playTactileClickSound();
    }

    // 2. Three.js background pulse
    threeAmbient.triggerPulse(isInteractive ? 1.5 : 0.4);

    // 3. Button tactile depression
    if (target) {
      target.style.transition = 'transform 0.08s ease';
      target.style.transform = 'scale(0.96)';
      setTimeout(() => {
        target.style.transform = '';
      }, 120);

      if (window.gsap && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        window.gsap.fromTo(target, 
          { filter: 'brightness(1.35) drop-shadow(0 0 8px rgba(230, 57, 70, 0.6))' }, 
          { filter: 'none', duration: 0.35, ease: 'power2.out' }
        );
      }
    }

    // 4. Expanding scarlet shockwave
    this.spawnShockwave(x, y, isInteractive);

    // 5. Sparks and blood ember splatter if interactive
    if (isInteractive) {
      this.spawnSparks(x, y);
    }
  }

  spawnShockwave(x, y, isInteractive) {
    if (!this.container) return;

    const ring = document.createElement('div');
    ring.style.cssText = `
      position: absolute;
      left: ${x}px;
      top: ${y}px;
      width: 14px;
      height: 14px;
      margin-left: -7px;
      margin-top: -7px;
      border-radius: 50%;
      border: 2px solid ${isInteractive ? '#E11D48' : 'rgba(225, 29, 72, 0.5)'};
      box-shadow: 0 0 14px ${isInteractive ? 'rgba(225, 29, 72, 0.7)' : 'rgba(225, 29, 72, 0.25)'};
      pointer-events: none;
      transform: scale(0.2);
      opacity: 1;
      transition: transform 0.42s cubic-bezier(0.1, 0.8, 0.3, 1), opacity 0.42s ease-out;
    `;

    this.container.appendChild(ring);

    requestAnimationFrame(() => {
      ring.style.transform = isInteractive ? 'scale(6.5)' : 'scale(3.2)';
      ring.style.opacity = '0';
    });

    setTimeout(() => ring.remove(), 450);
  }

  spawnSparks(x, y) {
    if (!this.container) return;

    const sparkCount = 10;
    const colors = ['#E11D48', '#F43F5E', '#FFFFFF', '#CBD5E1', '#9F1239'];

    for (let i = 0; i < sparkCount; i++) {
      const spark = document.createElement('div');
      const angle = (Math.PI * 2 / sparkCount) * i + (Math.random() - 0.5) * 0.4;
      const distance = 20 + Math.random() * 50;
      const tx = Math.cos(angle) * distance;
      const ty = Math.sin(angle) * distance;
      const color = colors[Math.floor(Math.random() * colors.length)];
      const size = 3 + Math.random() * 3;

      spark.style.cssText = `
        position: absolute;
        left: ${x}px;
        top: ${y}px;
        width: ${size}px;
        height: ${size}px;
        background: ${color};
        box-shadow: 0 0 8px ${color};
        pointer-events: none;
        transform: translate(0, 0) scale(1);
        opacity: 1;
        transition: transform 0.38s cubic-bezier(0.1, 0.8, 0.3, 1), opacity 0.38s ease-out;
      `;

      this.container.appendChild(spark);

      requestAnimationFrame(() => {
        spark.style.transform = `translate(${tx}px, ${ty}px) scale(0)`;
        spark.style.opacity = '0';
      });

      setTimeout(() => spark.remove(), 400);
    }
  }
}

export const clickEffects = new ClickEffectsEngine();
