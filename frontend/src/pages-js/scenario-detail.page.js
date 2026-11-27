// src/pages-js/scenario-detail.page.js
import * as ScriptData from '../data/scripts.data.js';
import { store, formatVND } from '../logic/store.js';
import { initChrome } from '../logic/layout.js';

const SCENARIOS = ScriptData.SCENARIOS
  || ScriptData.ALL_SCRIPTS
  || ScriptData.SCRIPTS
  || ScriptData.default
  || Object.values(ScriptData).find((v) => Array.isArray(v))
  || [];
const FORMAT_VND = ScriptData.FORMAT_VND || formatVND;

initChrome('scenarios');

const params = new URLSearchParams(window.location.search);
const scriptId = params.get('id');
const container = document.getElementById('scenario-detail-container');

function normalizeText(str) {
  return (str || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/\s+/g, ' ')
    .trim();
}

function renderDetail(script, isHidden = false) {
  if (!script) {
    container.innerHTML = `<p class="text-muted">Không tìm thấy thông tin kịch bản.</p>`;
    return;
  }

  const title = script.title || script.name || 'Kịch bản bí ẩn';
  document.title = `${title} | Kịch Bản Sát`;

  // Xử lý đường dẫn ảnh tương đối từ thư mục pages/
  const posterFile = (script.poster || script.image || '').split('/').pop();
  const posterUrl = posterFile
    ? `../src/assets/images/${posterFile}`
    : `https://placehold.co/400x600/181512/d4af37?text=${encodeURIComponent(title)}`;

  // Định dạng giá vé 2 khung giờ
  const rawDay = script.priceDay ?? script.price ?? 159000;
  const rawNight = script.priceNight ?? 199000;
  const dayPrice = typeof rawDay === 'number' ? FORMAT_VND(rawDay) : rawDay;
  const nightPrice = typeof rawNight === 'number' ? FORMAT_VND(rawNight) : rawNight;

  // Định dạng số lượng người chơi (hỗ trợ cả dạng chuỗi lẫn object { total, male, female })
  const playersDisplay = script.playersText
    || (typeof script.players === 'object' && script.players !== null
      ? `${script.players.total || 6} người`
      : (script.players || '6 người'));

  // Tô vàng riêng các ký tự ngôi sao ★
  const rawDifficulty = script.difficulty || '★★★ (Trung bình)';
  const formattedDifficulty = rawDifficulty.replace(
    /(★+)/g,
    '<span class="difficulty-stars">$1</span>'
  );

  // Lấy nội dung cốt truyện
  const storyText = script.synopsis || script.description || script.summary || 'Nội dung vụ án đang được cập nhật...';

  container.innerHTML = `
    ${isHidden ? `
      <div id="hidden-script-notice" style="margin-bottom:20px;padding:16px 20px;border-radius:10px;background:rgba(139,30,30,0.16);border:1px solid #8B1E1E;color:#F5F2EB;">
        <strong style="color:#E8B955;display:block;margin-bottom:4px;">THÔNG BÁO: KỊCH BẢN ĐANG TẠM ẨN</strong>
        <span>Kịch bản <strong>${title}</strong> hiện đã được Quản trị viên tạm ẩn khỏi danh mục công khai và tạm ngừng nhận đặt phòng mới. Vui lòng chọn kịch bản khác trong kho kịch bản.</span>
      </div>
    ` : ''}

    <!-- PHẦN TRÊN: POSTER TRÁI + THÔNG SỐ PHẢI -->
    <div class="detail-hero-grid" ${isHidden ? 'style="opacity:0.78;"' : ''}>
      <div class="detail-poster-box">
        <img
          src="${posterUrl}"
          alt="${title}"
          onerror="this.src='https://placehold.co/400x600/181512/d4af37?text=${encodeURIComponent(title)}'"
        />
        ${script.isBestSeller ? `<span class="poster-badge">BÁN CHẠY NHẤT</span>` : ''}
        ${script.is18Plus ? `<span class="poster-tag">18+</span>` : ''}
      </div>

      <div class="detail-info-box">
        <div class="section-kicker">KỊCH BẢN SUY LUẬN PHÁ ÁN</div>
        <h1 class="detail-title">
          ${title}
          ${script.isBestSeller ? `<span class="detail-subtitle">(Bán chạy nhất)</span>` : ''}
        </h1>

        <ul class="detail-meta-list">
          <li><strong>Thể loại:</strong> ${script.genre || 'Trinh thám, Tâm lý'}</li>
          <li><strong>Độ khó:</strong> <span>${formattedDifficulty}</span></li>
          <li><strong>Số lượng:</strong> ${playersDisplay}</li>
          <li><strong>Thời gian:</strong> ${script.duration || '~ 3 tiếng'}</li>
        </ul>

        <div class="detail-price-box">
          <div class="detail-price-title">Giá vé tham gia:</div>
          <ul>
            <li>Khung giờ 9h00 – 19h59: <strong>${dayPrice} / 1 người</strong></li>
            <li>Khung giờ từ 20h00: <strong>${nightPrice} / 1 người</strong></li>
          </ul>
        </div>

        <div class="detail-actions">
          ${!isHidden && store.state.currentRole !== 'dm' && store.state.currentRole !== 'admin'
            ? `<a href="./create-room.html?scriptId=${encodeURIComponent(script.dbId || script.id)}&title=${encodeURIComponent(script.title || script.name || '')}" class="btn btn-gold">Tạo phòng chơi kịch bản này</a>`
            : ''}
          <a href="./scenarios.html" class="btn btn-ghost">Quay lại Kho kịch bản</a>
          <a href="../index.html#phong-dang-tuyen" class="btn btn-ghost">Xem phòng đang tuyển</a>
        </div>
      </div>
    </div>

    <!-- PHẦN DƯỚI: NỘI DUNG CỐT TRUYỆN -->
    <div class="detail-synopsis-box">
      <h2 class="detail-synopsis-heading">NỘI DUNG:</h2>
      <div class="detail-synopsis-text">
        ${storyText
          .split('\n')
          .filter((line) => line.trim())
          .map((p) => `<p>${p}</p>`)
          .join('')}
      </div>
    </div>
  `;
}

function renderHiddenUnavailable() {
  document.title = 'Kịch bản không khả dụng | Kịch Bản Sát';
  container.innerHTML = `
    <div id="hidden-script-notice" style="max-width:680px;margin:48px auto;padding:28px;border-radius:12px;background:rgba(139,30,30,0.14);border:1px solid #8B1E1E;color:#F5F2EB;text-align:center;">
      <div class="section-kicker" style="margin-bottom:8px;">THÔNG BÁO HỆ THỐNG</div>
      <h1 style="font-family:var(--font-display);font-size:1.8rem;margin:0 0 12px;color:#E8B955;">Kịch Bản Đang Tạm Ẩn</h1>
      <p style="font-size:0.96rem;line-height:1.6;margin:0 0 22px;">
        Kịch bản này hiện đã được Quản trị viên ẩn khỏi kho kịch bản công khai và không nhận đặt phòng mới. Vui lòng lựa chọn kịch bản khác đang mở.
      </p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
        <a href="./scenarios.html" class="btn btn-gold">Quay lại Kho kịch bản</a>
        <a href="../index.html" class="btn btn-ghost">Về Trang chủ</a>
      </div>
    </div>
  `;
}

// Tìm kịch bản theo id (hỗ trợ cả SCR-xx và DB-xx)
let script = SCENARIOS.find((s) => String(s.id) === String(scriptId)) || null;
const isDbNumericParam = /^DB-(\d+)$/i.test(String(scriptId || '')) || /^\d+$/.test(String(scriptId || ''));
const explicitDbId = /^DB-(\d+)$/i.test(String(scriptId || ''))
  ? Number(String(scriptId).replace(/^DB-/i, ''))
  : (/^\d+$/.test(String(scriptId || '')) ? Number(scriptId) : null);

if (!script && !isDbNumericParam) {
  script = SCENARIOS[0] || null;
}

container.innerHTML = `<p class="text-muted" style="text-align:center;padding:48px 0;">Đang tải hồ sơ kịch bản...</p>`;

(async () => {
  try {
    const res = await fetch(`/api/v1/games?limit=100&_t=${Date.now()}`, {
      credentials: 'include',
      cache: 'no-store',
    });
    if (!res.ok) {
      if (script) renderDetail(script, false);
      else renderDetail(null, false);
      return;
    }
    const json = await res.json();
    const apiGames = json?.data?.games || [];

    if (!script && explicitDbId) {
      const activeById = apiGames.find((g) => Number(g.id) === explicitDbId);
      if (!activeById) {
        renderHiddenUnavailable();
        return;
      }
      script = {
        id: `DB-${activeById.id}`,
        dbId: Number(activeById.id),
        title: activeById.name || activeById.title,
        genre: activeById.genre || activeById.theme_code || 'Trinh thám',
        playersText: `${activeById.min_players || 4}–${activeById.max_players || 8} người`,
        duration: `${Math.round((Number(activeById.duration_minutes || 210) / 60) * 10) / 10} tiếng`,
        priceDay: Number(activeById.day_price ?? activeById.min_price ?? 199000),
        priceNight: Number(activeById.night_price ?? activeById.max_price ?? 239000),
        is18Plus: Number(activeById.is_18_plus) === 1,
        poster: activeById.image_url || '',
        synopsis: activeById.description || '',
      };
      renderDetail(script, false);
      return;
    }

    if (!script) {
      renderDetail(null, false);
      return;
    }

    const norm = normalizeText(script.title || script.name || '');
    const numId = Number(String(script.id || '').replace(/^SCR-0*/i, ''));
    const activeMatch = apiGames.find(
      (g) => normalizeText(g.name || g.title || '') === norm || (Number.isFinite(numId) && Number(g.id) === numId)
    );

    if (!activeMatch) {
      // Kịch bản bị Admin ẩn -> KHÔNG hiển thị nội dung cho bất kỳ ai ngoài Admin Portal
      renderHiddenUnavailable();
    } else {
      const updatedScript = {
        ...script,
        dbId: Number(activeMatch.id),
        title: activeMatch.name || activeMatch.title || script.title,
        priceDay: Number(activeMatch.day_price ?? activeMatch.min_price ?? script.priceDay ?? script.price ?? 199000),
        priceNight: Number(activeMatch.night_price ?? activeMatch.max_price ?? script.priceNight ?? 239000),
        is18Plus: Boolean(Number(activeMatch.is_18_plus) === 1 || script.is18Plus),
      };
      renderDetail(updatedScript, false);
    }
  } catch {
    if (script) renderDetail(script, false);
  }
})();