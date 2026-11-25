import { healthApi } from '../../api/health-api.js';
import { playerApi, getCachedUser, logout } from '../../api/player-api.js';
import { h } from '../../components/dom.js';
import { renderState, toast } from '../../components/player-ui.js';
import { formatVnd, initials, safeUrl } from '../../logic/format.js';
import { extractList, normalizeGame, normalizeProfile, pick } from '../../logic/normalize.js';

const catalogHost = document.getElementById('landing-catalog');
const authHost = document.getElementById('landing-auth');
const healthHost = document.getElementById('landing-health');
const header = document.getElementById('landing-header');
const nav = document.getElementById('landing-nav');
const menuBtn = document.getElementById('landing-menu-btn');
const reviewsSection = document.getElementById('testimonials');

function coverOf(raw) {
  const value = pick(raw, ['cover_url', 'cover', 'image_url', 'image', 'poster_url', 'poster', 'thumbnail_url', 'thumbnail', 'banner_url']);
  return safeUrl(String(value || ''));
}

function playersLabel(game) {
  if (game.minPlayers && game.maxPlayers) return `${game.minPlayers}–${game.maxPlayers} người`;
  if (game.playerTotal) return `${game.playerTotal} người`;
  if (game.maxPlayers) return `Tối đa ${game.maxPlayers} người`;
  return '';
}

function durationLabel(game) {
  if (game.duration && !/^\d+$/.test(game.duration)) return game.duration;
  if (game.durationMinutes) return `${game.durationMinutes} phút`;
  if (game.duration) return `${game.duration} phút`;
  return '';
}

function toneClass(title) {
  const sum = [...String(title)].reduce((acc, ch) => acc + ch.charCodeAt(0), 0);
  return `landing-cover-fallback landing-cover-${sum % 4}`;
}

function gameCard(game) {
  const cover = coverOf(game.raw);
  const media = cover
    ? h('img', { src: cover, alt: `Ảnh bìa ${game.title}` })
    : h('div', { class: toneClass(game.title), 'aria-hidden': 'true' }, game.title.slice(0, 1));
  const meta = [game.genre, game.difficulty, playersLabel(game), durationLabel(game)].filter(Boolean).join(' · ');
  const detailHref = game.title
    ? `./lobby.html?q=${encodeURIComponent(game.title)}`
    : './lobby.html';
  const bookHref = game.id
    ? `./create-room.html?game=${encodeURIComponent(game.id)}`
    : './create-room.html';
  const image = media.tagName === 'IMG' ? media : media;
  if (image.tagName === 'IMG') {
    image.addEventListener('error', () => {
      image.replaceWith(h('div', { class: toneClass(game.title) }, game.title.slice(0, 1)));
    });
  }
  return h('article', { class: 'landing-game' }, [
    h('div', { class: 'landing-game-cover' }, [image]),
    h('div', { class: 'landing-game-body' }, [
      game.code ? h('p', { class: 'landing-game-code' }, game.code) : null,
      h('h3', {}, game.title),
      meta ? h('p', { class: 'landing-game-meta' }, meta) : null,
      game.price != null ? h('p', { class: 'landing-game-meta' }, `Giá tham khảo từ ${formatVnd(game.price)}`) : null,
      game.is18Plus ? h('p', { class: 'landing-game-code' }, '18+') : null,
      h('div', { class: 'landing-game-actions' }, [
        h('a', { class: 'btn btn-outline', href: detailHref }, 'Xem chi tiết'),
        h('a', { class: 'btn btn-gold', href: bookHref }, 'Đặt phòng'),
      ]),
    ]),
  ]);
}

async function loadCatalog() {
  if (!catalogHost) return;
  renderState(catalogHost, {
    kind: 'loading',
    title: 'Đang mở hồ sơ vụ án',
    message: 'Đang lấy danh sách kịch bản từ hệ thống.',
  });
  catalogHost.querySelector('.player-state')?.classList.add('landing-state');
  try {
    const payload = await playerApi.listGames();
    const { items } = extractList(payload, ['items', 'games', 'data']);
    const games = items.map((raw) => {
      const game = normalizeGame(raw);
      if (!game) return null;
      return { ...game, code: String(raw.code || raw.game_code || ''), raw };
    }).filter(Boolean);
    if (!games.length) {
      renderState(catalogHost, {
        kind: 'empty',
        title: 'Chưa có kịch bản',
        message: 'Hệ thống chưa trả về hồ sơ vụ án nào. Hãy thử lại sau.',
        actionLabel: 'Thử lại',
        onAction: () => loadCatalog(),
      });
      catalogHost.querySelector('.player-state')?.classList.add('landing-state');
      return;
    }
    catalogHost.replaceChildren(...games.map(gameCard));
  } catch {
    renderState(catalogHost, {
      kind: 'error',
      title: 'Chưa kết nối được danh sách kịch bản',
      message: 'Không lấy được dữ liệu trò chơi lúc này. Các phần giới thiệu khác vẫn dùng được.',
      actionLabel: 'Thử lại',
      onAction: () => loadCatalog(),
    });
    catalogHost.querySelector('.player-state')?.classList.add('landing-state');
  }
}

function paintGuestAuth() {
  if (!authHost) return;
  authHost.replaceChildren(
    h('a', { class: 'btn btn-outline', href: './profile.html' }, 'Đăng nhập'),
    h('a', { class: 'btn btn-outline', href: './profile.html' }, 'Đăng ký'),
    h('a', { class: 'btn btn-primary', href: './create-room.html' }, 'Đặt phòng ngay'),
  );
}

function paintMemberAuth(profile) {
  if (!authHost) return;
  const name = profile.fullName || profile.full_name || profile.email || 'Tài khoản';
  const menu = h('div', { class: 'landing-account-menu', role: 'menu' }, [
    h('a', { href: './profile.html', role: 'menuitem' }, 'Hồ sơ cá nhân'),
    h('a', { href: './profile.html?tab=upcoming', role: 'menuitem' }, 'Lịch sử đặt phòng'),
    h('button', {
      type: 'button',
      role: 'menuitem',
      onClick: async () => {
        try {
          await logout();
        } catch {
          /* phiên có thể đã hết */
        }
        toast('Đã đăng xuất.');
        paintGuestAuth();
      },
    }, 'Đăng xuất'),
  ]);
  const wrap = h('div', { class: 'landing-account' }, [
    h('button', {
      class: 'btn btn-outline landing-account-btn',
      type: 'button',
      'aria-haspopup': 'true',
      'aria-expanded': 'false',
      onClick: (event) => {
        const open = wrap.classList.toggle('is-open');
        event.currentTarget.setAttribute('aria-expanded', open ? 'true' : 'false');
      },
    }, [
      h('span', { class: 'landing-avatar' }, initials(name)),
      h('span', {}, name),
    ]),
    menu,
    h('a', { class: 'btn btn-primary', href: './create-room.html' }, 'Đặt phòng ngay'),
  ]);
  document.addEventListener('click', (event) => {
    if (!wrap.contains(event.target)) wrap.classList.remove('is-open');
  });
  authHost.replaceChildren(wrap);
}

async function loadAuth() {
  paintGuestAuth();
  const cached = getCachedUser();
  if (cached && (cached.full_name || cached.email || cached.fullName)) {
    paintMemberAuth(normalizeProfile(cached));
  }
  try {
    const profile = normalizeProfile(await playerApi.getMe());
    if (profile.fullName || profile.email) paintMemberAuth(profile);
  } catch {
    if (!cached) paintGuestAuth();
  }
}

function loadReviews() {
  if (!reviewsSection) return;
  reviewsSection.hidden = true;
}

async function loadHealth() {
  if (!healthHost) return;
  const chip = h('span', { class: 'landing-health-chip is-wait' }, 'Đang kiểm tra hệ thống');
  const retry = h('button', { class: 'btn btn-outline', type: 'button' }, 'Thử lại');
  retry.addEventListener('click', () => loadHealth());
  healthHost.replaceChildren(chip, retry);
  retry.disabled = true;
  try {
    await healthApi.checkHealth();
    chip.className = 'landing-health-chip is-ok';
    chip.textContent = 'Hệ thống đặt phòng sẵn sàng';
  } catch {
    chip.className = 'landing-health-chip is-down';
    chip.textContent = 'Hệ thống đặt phòng tạm thời gián đoạn';
  } finally {
    retry.disabled = false;
  }
}

function bindHeader() {
  const onScroll = () => header?.classList.toggle('is-scrolled', window.scrollY > 12);
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  const links = [...(nav?.querySelectorAll('a[href^="#"]') || [])];
  const sections = links
    .map((link) => document.querySelector(link.getAttribute('href')))
    .filter(Boolean);
  const markActive = () => {
    const y = window.scrollY + 110;
    let current = sections[0];
    sections.forEach((section) => {
      if (section.offsetTop <= y) current = section;
    });
    links.forEach((link) => {
      link.classList.toggle('is-active', current && link.getAttribute('href') === `#${current.id}`);
    });
  };
  window.addEventListener('scroll', markActive, { passive: true });
  markActive();

  menuBtn?.addEventListener('click', () => {
    const open = nav.classList.toggle('is-open');
    authHost?.classList.toggle('is-open', open);
    menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  nav?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      nav.classList.remove('is-open');
      authHost?.classList.remove('is-open');
      menuBtn?.setAttribute('aria-expanded', 'false');
    });
  });
}

function bindFaq() {
  document.querySelectorAll('#landing-faq details').forEach((item) => {
    item.addEventListener('toggle', () => {
      if (!item.open) return;
      document.querySelectorAll('#landing-faq details').forEach((other) => {
        if (other !== item) other.open = false;
      });
    });
  });
}

function observeReveal() {
  const nodes = document.querySelectorAll('.landing-reveal');
  if (!('IntersectionObserver' in window)) {
    nodes.forEach((node) => { node.style.opacity = '1'; node.style.transform = 'none'; });
    return;
  }
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.style.animationPlayState = 'running';
      observer.unobserve(entry.target);
    });
  }, { threshold: 0.12 });
  nodes.forEach((node) => {
    node.style.animationPlayState = 'paused';
    observer.observe(node);
  });
}

bindHeader();
bindFaq();
observeReveal();
loadAuth();
loadCatalog();
loadReviews();
loadHealth();
