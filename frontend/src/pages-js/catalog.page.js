// src/pages-js/catalog.page.js
import * as ScriptData from '../data/scripts.data.js';
import { initChrome } from '../logic/layout.js';

const SCENARIOS = ScriptData.SCENARIOS
  || ScriptData.ALL_SCRIPTS
  || ScriptData.SCRIPTS
  || ScriptData.default
  || Object.values(ScriptData).find((v) => Array.isArray(v))
  || [];

initChrome('scenarios');

let ACTIVE_SCENARIOS = [];
let catalogLoaded = false;
let activeGenre = 'Tất cả';
let searchKeyword = '';
let pendingUrl = '';

function normalizeText(str) {
  return (str || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/\s+/g, ' ')
    .trim();
}

async function syncActiveScenariosFromApi() {
  const grid = document.getElementById('catalog-scripts-grid');
  if (grid && !catalogLoaded) {
    grid.innerHTML = `<p class="text-muted" style="grid-column: 1 / -1; text-align: center; padding: 40px 0;">Đang tải kho kịch bản...</p>`;
  }
  try {
    const res = await fetch(`/api/v1/games?limit=100&_t=${Date.now()}`, {
      credentials: 'include',
      cache: 'no-store',
    });
    if (!res.ok) throw new Error('API_ERROR');
    const json = await res.json();
    const apiGames = json?.data?.games;
    if (!Array.isArray(apiGames)) throw new Error('INVALID_PAYLOAD');

    const staticByNormTitle = new Map();
    const staticByNumId = new Map();
    SCENARIOS.forEach((sc) => {
      const k = normalizeText(sc.title || sc.name || '');
      if (k) staticByNormTitle.set(k, sc);
      const numId = Number(String(sc.id || '').replace(/^SCR-0*/i, ''));
      if (Number.isFinite(numId) && numId > 0) staticByNumId.set(numId, sc);
    });

    // Nguồn sự thật duy nhất là danh sách ACTIVE từ MySQL -> kịch bản bị Admin ẩn biến mất hoàn toàn
    ACTIVE_SCENARIOS = apiGames.map((g) => {
      const norm = normalizeText(g.name || g.title || '');
      const sc = staticByNormTitle.get(norm) || staticByNumId.get(Number(g.id)) || null;
      if (sc) {
        return {
          ...sc,
          dbId: Number(g.id),
          title: g.name || g.title || sc.title,
          priceDay: Number(g.day_price ?? g.min_price ?? sc.priceDay ?? sc.price ?? 199000),
          priceNight: Number(g.night_price ?? g.max_price ?? sc.priceNight ?? 239000),
          is18Plus: Boolean(Number(g.is_18_plus) === 1 || sc.is18Plus),
        };
      }
      return {
        id: `DB-${g.id}`,
        dbId: Number(g.id),
        title: g.name || g.title || `Kịch bản #${g.id}`,
        genre: g.genre || g.theme_code || 'Trinh thám',
        playersText: `${g.min_players || 4}–${g.max_players || 8} người`,
        duration: `${Math.round((Number(g.duration_minutes || 210) / 60) * 10) / 10} tiếng`,
        priceDay: Number(g.day_price ?? g.min_price ?? 199000),
        priceNight: Number(g.night_price ?? g.max_price ?? 239000),
        is18Plus: Number(g.is_18_plus) === 1,
        poster: g.image_url || '',
        synopsis: g.description || '',
      };
    });

    catalogLoaded = true;
    renderFilters();
    renderCatalog();
  } catch {
    if (!catalogLoaded) {
      ACTIVE_SCENARIOS = [...SCENARIOS];
      catalogLoaded = true;
      renderFilters();
      renderCatalog();
    }
  }
}

function renderFilters() {
  const wrap = document.getElementById('catalog-genre-filters');
  if (!wrap) return;

  const genres = ['Tất cả', ...new Set(ACTIVE_SCENARIOS.map((s) => s.genre).filter(Boolean))];
  if (!genres.includes(activeGenre)) activeGenre = 'Tất cả';
  wrap.innerHTML = genres.map((g) => `
    <button type="button" class="btn btn-sm ${g === activeGenre ? 'btn-gold' : 'btn-ghost'}" data-genre="${g}">
      ${g}
    </button>
  `).join('');

  wrap.querySelectorAll('button').forEach((btn) => {
    btn.addEventListener('click', () => {
      activeGenre = btn.dataset.genre;
      renderFilters();
      renderCatalog();
    });
  });
}

function renderCatalog() {
  const grid = document.getElementById('catalog-scripts-grid');
  if (!grid) return;

  const kw = normalizeText(searchKeyword.trim());
  const list = ACTIVE_SCENARIOS.filter((s) => {
    const matchGenre = activeGenre === 'Tất cả' || s.genre === activeGenre;
    const title = s.title || s.name || '';
    const matchKeyword = !kw || normalizeText(title).includes(kw);
    return matchGenre && matchKeyword;
  });

  if (!list.length) {
    grid.innerHTML = `<p class="text-muted" style="grid-column: 1 / -1; text-align: center; padding: 40px 0;">Không tìm thấy kịch bản phù hợp.</p>`;
    return;
  }

  grid.innerHTML = list.map((sc) => {
    const title = sc.title || sc.name;
    const posterFile = (sc.poster || '').split('/').pop();
    const imgSrc = posterFile ? `../src/assets/images/${posterFile}` : '';
    const fallbackUrl = `https://placehold.co/400x600/181512/D64545?text=${encodeURIComponent(title)}`;

    return `
      <a href="./scenario-detail.html?id=${sc.id}"
         class="poster-card"
         data-open-script="${sc.id}"
         data-title="${title}"
         data-18="${Boolean(sc.is18Plus)}">
        <div class="poster-thumb">
          <img src="${imgSrc || fallbackUrl}" alt="${title}" loading="lazy" onerror="this.src='${fallbackUrl}'" />
          ${sc.is18Plus ? '<span class="poster-tag">18+</span>' : ''}
        </div>
        <h3 class="poster-title">${title}</h3>
      </a>
    `;
  }).join('');

  grid.querySelectorAll('[data-open-script]').forEach((card) => {
    card.addEventListener('click', (e) => {
      if (card.dataset['18'] === 'true') {
        e.preventDefault();
        pendingUrl = card.getAttribute('href');
        const nameEl = document.getElementById('age-script-name');
        if (nameEl) nameEl.textContent = card.dataset.title;
        document.getElementById('age-modal')?.classList.add('open');
      }
    });
  });
}

const searchInput = document.getElementById('catalog-search-input');
const clearBtn = document.getElementById('catalog-search-clear');

searchInput?.addEventListener('input', (e) => {
  searchKeyword = e.target.value;
  if (clearBtn) clearBtn.style.display = searchKeyword ? 'inline-block' : 'none';
  renderCatalog();
});

clearBtn?.addEventListener('click', () => {
  if (searchInput) searchInput.value = '';
  searchKeyword = '';
  clearBtn.style.display = 'none';
  renderCatalog();
  searchInput?.focus();
});

document.getElementById('age-cancel-btn')?.addEventListener('click', () => {
  document.getElementById('age-modal')?.classList.remove('open');
  pendingUrl = '';
});

document.getElementById('age-confirm-btn')?.addEventListener('click', () => {
  document.getElementById('age-modal')?.classList.remove('open');
  if (pendingUrl) window.location.href = pendingUrl;
});

syncActiveScenariosFromApi();