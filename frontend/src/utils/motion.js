/**
 * Motion & Animation Controller
 * Implements GSAP Core, ScrollTrigger, and Scroll-Craft dimensional mechanics.
 * Respects WCAG prefers-reduced-motion standards.
 */

export function initMotion() {
  if (typeof window === 'undefined' || !window.gsap) return;

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) {
    // Respect accessibility: don't run motion
    return;
  }

  const { gsap, ScrollTrigger } = window;
  if (ScrollTrigger) {
    gsap.registerPlugin(ScrollTrigger);
  }

  // 1. Interactive Dimensional Hero Parallax & Cursor Spotlight (Scroll-Craft)
  const heroEl = document.querySelector('.home-hero');
  const glowGold = document.querySelector('.hero-ambient-glow');
  const glowCrimson = document.querySelector('.hero-ambient-crimson');

  if (heroEl && glowGold && glowCrimson) {
    // Hero Entrance Timeline
    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

    tl.from('.hero-eyebrow', { opacity: 0, y: -16, duration: 0.7 })
      .from('.hero-title', { opacity: 0, y: 32, duration: 0.9 }, '-=0.4')
      .from('.hero-subtitle', { opacity: 0, y: 20, duration: 0.7 }, '-=0.5')
      .from('.hero-actions .btn', { opacity: 0, y: 16, stagger: 0.15, duration: 0.6 }, '-=0.4')
      .from('.hero-noir-ticker', { opacity: 0, y: 12, duration: 0.5 }, '-=0.3');

    // Dimensional Mouse Tracking Spotlight
    heroEl.addEventListener('mousemove', (e) => {
      const rect = heroEl.getBoundingClientRect();
      const mouseX = e.clientX - rect.left;
      const mouseY = e.clientY - rect.top;

      gsap.to(glowGold, {
        x: mouseX - 325,
        y: mouseY - 225,
        duration: 1.2,
        ease: 'power2.out',
      });

      gsap.to(glowCrimson, {
        x: (mouseX - 300) * 0.7,
        y: (mouseY - 200) * 0.7,
        duration: 1.6,
        ease: 'power2.out',
      });
    });
  }

  if (!ScrollTrigger) return;

  // 2. Editorial Manifesto ScrollTrigger
  const manifestoQuote = document.querySelector('.manifesto-quote-col');
  const manifestoText = document.querySelector('.manifesto-text-col');
  if (manifestoQuote && manifestoText) {
    gsap.from(manifestoQuote, {
      scrollTrigger: {
        trigger: '.editorial-manifesto-section',
        start: 'top 80%',
      },
      opacity: 0,
      x: -40,
      duration: 1.0,
      ease: 'power3.out',
    });

    gsap.from(manifestoText, {
      scrollTrigger: {
        trigger: '.editorial-manifesto-section',
        start: 'top 80%',
      },
      opacity: 0,
      y: 30,
      duration: 1.0,
      delay: 0.2,
      ease: 'power3.out',
    });
  }

  // 3. Sealed Honor Charter ScrollTrigger
  const charterItems = document.querySelectorAll('.charter-item');
  if (charterItems.length > 0) {
    gsap.from(charterItems, {
      scrollTrigger: {
        trigger: '.sealed-charter',
        start: 'top 85%',
      },
      opacity: 0,
      y: 25,
      stagger: 0.12,
      duration: 0.8,
      ease: 'power2.out',
    });
  }

  // 4. Six-Stage Experience Chapter Index ScrollTrigger
  const chapterRows = document.querySelectorAll('.chapter-row');
  if (chapterRows.length > 0) {
    gsap.from(chapterRows, {
      scrollTrigger: {
        trigger: '.chapter-index-section',
        start: 'top 80%',
      },
      opacity: 0,
      x: -20,
      stagger: 0.1,
      duration: 0.7,
      ease: 'power2.out',
    });
  }

  // 5. Spotlight Premier Case ScrollTrigger
  const spotlightCard = document.querySelector('.spotlight-editorial-card');
  if (spotlightCard) {
    gsap.from(spotlightCard, {
      scrollTrigger: {
        trigger: '.premier-spotlight-section',
        start: 'top 80%',
      },
      opacity: 0,
      y: 35,
      duration: 0.9,
      ease: 'power3.out',
    });
  }

  // 6. Live Case Departure Board Table Rows
  const tableRows = document.querySelectorAll('.case-board-table tbody tr');
  if (tableRows.length > 0) {
    gsap.from(tableRows, {
      scrollTrigger: {
        trigger: '.departure-board-section',
        start: 'top 85%',
      },
      opacity: 0,
      y: 15,
      stagger: 0.08,
      duration: 0.6,
      ease: 'power2.out',
    });
  }
}
