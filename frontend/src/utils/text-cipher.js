/**
 * Detective Text Cipher & Scrambler (Pure Vanilla JS)
 * Hollywood Crime Cryptogram & Baffle Scramble Effect
 * Decodes characters with mysterious occult runes and forensic glyphs.
 */

const GLYPHS = '0123456789ABCDEF!@#$%&*?§¶ΔΩΨØ░▒▓█';

export function scrambleText(element, finalString = null, durationMs = 650) {
  if (!element) return;
  const target = finalString !== null ? finalString : element.textContent.trim();
  if (!target) return;

  // Respect prefers-reduced-motion
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    element.textContent = target;
    return;
  }

  const length = target.length;
  const startTime = performance.now();

  function update(now) {
    const elapsed = now - startTime;
    const progress = Math.min(elapsed / durationMs, 1);
    const revealedCount = Math.floor(progress * length);

    let output = '';
    for (let i = 0; i < length; i++) {
      if (target[i] === ' ' || target[i] === '\n' || target[i] === ':') {
        output += target[i];
      } else if (i < revealedCount) {
        output += target[i];
      } else {
        output += GLYPHS[Math.floor(Math.random() * GLYPHS.length)];
      }
    }

    element.textContent = output;

    if (progress < 1) {
      requestAnimationFrame(update);
    } else {
      element.textContent = target;
    }
  }

  requestAnimationFrame(update);
}

/**
 * Automatically decodes elements with .cipher-reveal on initial mount
 */
export function initAutoCiphers(root = document) {
  const elements = root.querySelectorAll('.cipher-reveal, .player-desk-title, .dm-command-title, .page-title');
  elements.forEach((el, idx) => {
    setTimeout(() => {
      scrambleText(el, null, 700);
    }, idx * 120);
  });
}
