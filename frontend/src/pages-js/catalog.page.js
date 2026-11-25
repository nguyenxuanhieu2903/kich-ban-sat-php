// src/pages-js/catalog.page.js
import * as ScriptData from '../data/scripts.data.js';
import { initChrome } from '../logic/layout.js';
import { generateGamePosterSvg } from '../utils/poster-svg.js';

const EXISTING_POSTER_FILES = new Set([
  'co-gai-ben-cua-so.png',
  'guong.png',
  'ly-luan-hu-cau.png',
  'ngoi-nha-ngot-ngao.png',
  'nguyet-lac-oa.png',
  'sinh-tu.png',
]);

const SCENARIOS = ScriptData.SCENARIOS
  || ScriptData.ALL_SCRIPTS
  || ScriptData.SCRIPTS
  || ScriptData.default
  || Object.values(ScriptData).find((v) => Array.isArray(v))
  || [];

initChrome('scenarios');

let activeGenre = 'Tất cả';
let searchKeyword = '';
let pendingUrl = '';

function normalizeText(str) {
  return (str || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd');
}

function renderFilters() {
  const wrap = document.getElementById('catalog-genre-filters');
  if (!wrap) return;

  const genres = ['Tất cả', ...new Set(SCENARIOS.map((s) => s.genre).filter(Boolean))];
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
  const list = SCENARIOS.filter((s) => {
    const matchGenre = activeGenre === 'Tất cả' || s.genre === activeGenre;
    const title = s.title || s.name || '';
    const matchKeyword = !kw || normalizeText(title).includes(kw);
    return matchGenre && matchKeyword;
  });

  if (!list.length) {
    grid.innerHTML = `<p class="text-muted" style="grid-column: 1 / -1; text-align: center; padding: 40px 0;">Không tìm thấy kịch bản phù hợp.</p>`;
    return;
  }

  grid.innerHTML = list.map((sc, idx) => {
    const title = sc.title || sc.name;
    const posterFile = (sc.poster || '').split('/').pop();
    const hasLocalImg = posterFile && EXISTING_POSTER_FILES.has(posterFile);
    const thumbHtml = hasLocalImg
      ? `<img src="../src/assets/images/${posterFile}" alt="${title}" loading="lazy" />`
      : generateGamePosterSvg({ id: Number(sc.id) || (idx + 1), title, is18Plus: Boolean(sc.is18Plus) }, { width: 320, height: 420 });

    return `
      <a href="./scenario-detail.html?id=${sc.id}"
         class="poster-card"
         data-open-script="${sc.id}"
         data-title="${title}"
         data-18="${Boolean(sc.is18Plus)}">
        <div class="poster-thumb">
          ${thumbHtml}
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

renderFilters();
renderCatalog();