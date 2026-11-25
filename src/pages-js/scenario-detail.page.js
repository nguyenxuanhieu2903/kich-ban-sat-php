// src/pages-js/scenario-detail.page.js
import * as ScriptData from '../data/scripts.data.js';
import { formatVND } from '../logic/store.js';
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

// Tìm kịch bản theo id (nếu không truyền id thì lấy kịch bản đầu tiên làm mặc định)
const script = SCENARIOS.find((s) => String(s.id) === String(scriptId)) || SCENARIOS[0];

if (!script) {
  container.innerHTML = `<p class="text-muted">Không tìm thấy thông tin kịch bản.</p>`;
} else {
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
    <!-- PHẦN TRÊN: POSTER TRÁI + THÔNG SỐ PHẢI -->
    <div class="detail-hero-grid">
      <div class="detail-poster-box">
        <img
          src="${posterUrl}"
          alt="${title}"
          onerror="this.src='https://placehold.co/400x600/181512/d4af37?text=${encodeURIComponent(title)}'"
        />
        ${script.isBestSeller ? `<span class="poster-badge">BEST SELLER</span>` : ''}
        ${script.is18Plus ? `<span class="poster-tag">18+</span>` : ''}
      </div>

      <div class="detail-info-box">
        <div class="section-kicker">KỊCH BẢN SUY LUẬN PHÁ ÁN</div>
        <h1 class="detail-title">
          ${title}
          ${script.isBestSeller ? `<span class="detail-subtitle">(Best Seller)</span>` : ''}
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
          <a href="./create-room.html?scriptId=${script.id}" class="btn btn-gold">Tạo phòng chơi kịch bản này</a>
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