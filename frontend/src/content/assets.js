/**
 * Dynamic Visual Theme & SVG Poster Generator for Games
 * Generates bespoke cinematic artwork motifs deterministically based on game title.
 */

const MOTIFS = [
  'dha_manor',       // Biệt thự cổ u tối
  'train_snow',      // Chuyến tàu tuyết đêm
  'palace_forbidden',// Cung đình thâm cung
  'mirror_shadow',   // Gương phản chiếu
  'crimson_ribbon',  // Dải lụa đỏ
  'ancient_lantern', // Đèn lồng cổ
  'foggy_corridor',  // Hành lang sương mù
  'clock_tower',     // Tháp đồng hồ bí ẩn
];

function hashString(str) {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = (hash << 5) - hash + str.charCodeAt(i);
    hash |= 0;
  }
  return Math.abs(hash);
}

export function getGameVisualTheme(title, isAgeRestricted = false) {
  const hash = hashString(title || 'game');
  const motifIndex = hash % MOTIFS.length;
  const motif = MOTIFS[motifIndex];

  // Colors based on genre/age
  let primaryHue = (hash * 47) % 360;
  if (isAgeRestricted) {
    primaryHue = 350; // Crimson dark
  }

  const bgColor1 = `hsl(${primaryHue}, 35%, 10%)`;
  const bgColor2 = `hsl(${(primaryHue + 40) % 360}, 40%, 6%)`;
  const accentColor = `hsl(${primaryHue}, 65%, 55%)`;

  return {
    motif,
    primaryHue,
    bgColor1,
    bgColor2,
    accentColor,
    isAgeRestricted,
  };
}

export function renderGameCoverElement(game, options = {}) {
  const { width = 400, height = 560, className = 'game-cover-photo' } = options;
  const coverUrl = game.cover_url || `./assets/images/covers/${game.id}.jpg`;

  const wrapper = document.createElement('div');
  wrapper.className = 'game-cover-wrapper';
  wrapper.style.cssText = 'position: relative; width: 100%; height: 100%; overflow: hidden; background: #0A0507;';

  const img = document.createElement('img');
  img.src = coverUrl;
  img.alt = `Hồ sơ án: ${game.title}`;
  img.className = className;
  img.loading = 'lazy';
  img.style.cssText = 'width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), filter 0.5s ease;';

  const overlay = document.createElement('div');
  overlay.className = 'game-cover-gradient-overlay';
  overlay.style.cssText = 'position: absolute; inset: 0; background: linear-gradient(180deg, rgba(5,6,8,0.15) 0%, rgba(5,6,8,0.75) 100%); pointer-events: none;';

  img.onerror = () => {
    img.style.display = 'none';
    overlay.style.display = 'none';
    wrapper.innerHTML = generateGamePosterSvg(game.title, game.is_age_restricted, width, height);
  };

  wrapper.appendChild(img);
  wrapper.appendChild(overlay);
  return wrapper;
}

export function generateGamePosterSvg(title, isAgeRestricted = false, width = 400, height = 560) {
  const theme = getGameVisualTheme(title, isAgeRestricted);
  const cleanTitle = (title || 'Kịch Bản Sát').replace(/&/g, '&amp;').replace(/</g, '&lt;');

  return `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" xmlns="http://www.w3.org/2000/svg" style="border-radius: 12px; display: block; width: 100%; height: auto;">
    <defs>
      <linearGradient id="bgGrad_${theme.primaryHue}" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="${theme.bgColor1}"/>
        <stop offset="100%" stop-color="${theme.bgColor2}"/>
      </linearGradient>
      <radialGradient id="vignette" cx="50%" cy="50%" r="65%">
        <stop offset="60%" stop-color="#000" stop-opacity="0"/>
        <stop offset="100%" stop-color="#000" stop-opacity="0.85"/>
      </radialGradient>
      <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(196,163,107,0.06)" stroke-width="1"/>
      </pattern>
      <linearGradient id="bottomShadow_${theme.primaryHue}" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#0B0C0F" stop-opacity="0"/>
        <stop offset="100%" stop-color="#0B0C0F" stop-opacity="0.95"/>
      </linearGradient>
    </defs>
    <rect width="${width}" height="${height}" fill="url(#bgGrad_${theme.primaryHue})"/>
    <rect width="${width}" height="${height}" fill="url(#grid)"/>

    <!-- Geometric Noir Elements -->
    <circle cx="${width / 2}" cy="${height * 0.42}" r="${width * 0.32}" fill="none" stroke="${theme.accentColor}" stroke-opacity="0.25" stroke-width="1.5" stroke-dasharray="6,4"/>
    <circle cx="${width / 2}" cy="${height * 0.42}" r="${width * 0.22}" fill="none" stroke="#C4A36B" stroke-opacity="0.3" stroke-width="1"/>
    
    <!-- Silhouette Motif -->
    <path d="M ${width * 0.3} ${height * 0.65} Q ${width * 0.5} ${height * 0.3} ${width * 0.7} ${height * 0.65} Z" fill="${theme.accentColor}" fill-opacity="0.18"/>
    <line x1="${width * 0.2}" y1="${height * 0.42}" x2="${width * 0.8}" y2="${height * 0.42}" stroke="#C4A36B" stroke-opacity="0.4" stroke-width="1"/>

    <!-- Vignette Shadow -->
    <rect width="${width}" height="${height}" fill="url(#vignette)"/>

    <!-- Hairline Border -->
    <rect x="12" y="12" width="${width - 24}" height="${height - 24}" rx="8" fill="none" stroke="#C4A36B" stroke-opacity="0.3" stroke-width="1"/>

    <!-- 18+ Stamp if applicable -->
    ${
      isAgeRestricted
        ? `<g transform="translate(${width - 65}, 24)">
            <rect width="42" height="24" rx="4" fill="rgba(181,46,54,0.3)" stroke="#B52E36" stroke-width="1.5"/>
            <text x="21" y="16" font-family="'JetBrains Mono', monospace" font-size="11" font-weight="bold" fill="#F3F0E9" text-anchor="middle">18+</text>
          </g>`
        : ''
    }

    <!-- Bottom Typography Block -->
    <rect x="0" y="${height * 0.70}" width="${width}" height="${height * 0.30}" fill="url(#bottomShadow_${theme.primaryHue})"/>
    <text x="${width / 2}" y="${height * 0.86}" font-family="'Cormorant Garamond', serif" font-size="20" font-weight="bold" fill="#F3F0E9" text-anchor="middle" letter-spacing="1.5">
      ${cleanTitle}
    </text>
    <text x="${width / 2}" y="${height * 0.93}" font-family="'Be Vietnam Pro', sans-serif" font-size="10" fill="#92949B" text-anchor="middle" letter-spacing="2">
      JUBENSHA • KỊCH BẢN SÁT
    </text>
  </svg>`;
}
