/**
 * Interactive Card System: Vanilla-Tilt 3D & Radial Mouse Spotlight
 * Adds tangible physical presence, 3D tilt, and dynamic cursor lighting.
 */

export function initCardInteractions(root = document) {
  if (typeof window === 'undefined') return;

  // Respect reduced motion
  const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // 1. Radial Mouse Spotlight Tracking (--mouse-x, --mouse-y)
  const cards = root.querySelectorAll('.card-dark, .spotlight-card, .case-dossier-item, .tactical-card, .metric-strip-item');
  cards.forEach((card) => {
    if (card._spotlightAttached) return;
    card._spotlightAttached = true;

    card.addEventListener('mousemove', (e) => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      card.style.setProperty('--mouse-x', `${x}px`);
      card.style.setProperty('--mouse-y', `${y}px`);
    }, { passive: true });
  });

  // 2. Vanilla-Tilt 3D Tilt Initialization
  if (!prefersReduced && window.VanillaTilt) {
    const tiltTargets = root.querySelectorAll('.spotlight-card, .case-dossier-item, .metric-strip-item');
    window.VanillaTilt.init(tiltTargets, {
      max: 6,
      speed: 400,
      glare: true,
      'max-glare': 0.12,
      scale: 1.015,
      perspective: 1200,
    });
  }
}
