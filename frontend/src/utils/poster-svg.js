/**
 * Shared Editorial Noir SVG Poster & Folio Card Engine (V16 Official 42 Scenarios)
 * Zero external photographic images (no .jpg/.png/Unsplash) — 100% unified procedural Geometric Noir SVG artwork.
 */
import { escapeHtml } from './sanitize.js';

export const POSTER_METADATA = {
  1: { subtitle: 'BÍ MẬT BÊN Ô KÍNH MƯA ĐÊM', tagline: 'Khi tiếng mưa đập vào ô kính, bóng hình ai đang đứng đợi ngoài màn đêm?' },
  2: { subtitle: 'LỜI NGUYỀN CHIẾC GƯƠNG MA ÁM', tagline: 'Ngươi nhìn vào gương thấy chính mình, hay kẻ thủ ác đang đứng sau lưng?' },
  3: { subtitle: 'TẬP HỒ SƠ ÁN MẠNG NGUYỆT LẠC', tagline: 'Trang sách cổ giấu kín lời khai giả và những cái chết không lời giải.' },
  4: { subtitle: 'BÀN CỜ SINH TỬ GIỮA SƯƠNG ĐÊM', tagline: 'Một bước đi sai, vận mệnh thuộc về bóng tối tòa tháp đá.' },
  5: { subtitle: 'MẬT VỤ SUY LUẬN PHẢN NGHỊCH', tagline: 'Khi logic bị bóp méo, câu chữ trên máy đánh chữ là lời thú tội.' },
  6: { subtitle: 'CƠN ÁC MỘNG TRONG DINH THỰ CỔ', tagline: 'Tòa nhà ngọt ngào chỉ dành cho những bí mật chưa từng hé mở.' },
  7: { subtitle: 'ÁI TÌNH ĐIÊN CUỒNG VÀ BÓNG TỐI', tagline: 'Nếu không thể giữ trọn lời thề, bí mật đêm nay sẽ khóa chặt tất cả.' },
  8: { subtitle: 'LÁ THƯ TÌNH CUỐI CÙNG', tagline: 'Mật thư niêm phong bằng sáp đỏ chứng thực tội ác không thể tha thứ.' },
  9: { subtitle: 'HUYẾT ÁN TUYẾT TRẮNG TỬ CẤM THÀNH', tagline: 'Mùi hương thoang thoảng trong bão tuyết là điềm báo của án mạng liên hoàn.' },
  10: { subtitle: 'CHUYẾN TÀU HOÀI NIỆM CUỐI CÙNG', tagline: 'Còi tàu xé toạc màn sương, mang theo bí mật của những kẻ ly hương.' },
  11: { subtitle: 'KHOẢNH KHẮC THỜI GIAN NGỪNG TRÔI', tagline: 'Kim đồng hồ dừng lại đúng 1 giờ 30 phút, đánh dấu khoảnh khắc định mệnh.' },
  12: { subtitle: 'OAN HỒN DƯỚI ĐÈN LỒNG ĐỎ', tagline: 'Đôi giày thêu hoa cất bước lúc nửa đêm mang theo lời nguyền truyền kiếp.' },
  13: { subtitle: 'MẬT MÃ KÉT SẮT TỬ THẦN', tagline: 'Hai phần mười hai sự thật đang bị phong tỏa sau lớp thép lạnh.' },
  14: { subtitle: 'DANH SÁCH HỌC SINH BIẾN MẤT', tagline: 'Khi tiếng chuông reo lên, cái tên thứ mười ba trên bục giảng là ai?' },
  15: { subtitle: 'TÍN HIỆU SỐ BỊ NHIỄU LOẠN', tagline: 'Tần số cuối cùng truyền về từ đỉnh núi tuyết chứa đựng bí mật kinh hoàng.' },
  16: { subtitle: 'VÒNG LẶP TỬ ĐỊA ĐẢO HOANG', tagline: 'Mỗi lần mặt trời lặn trên vách đá biển, vụ án lại bắt đầu từ đầu.' },
  17: { subtitle: 'BÍ MẬT HÀNH LANG PHÒNG SINH CŨ', tagline: 'Tiếng bước chân vang vọng trong khu bệnh viện bỏ hoang giữa đêm mưa.' },
  18: { subtitle: 'BÓNG HÌNH TRONG GÁC XÉP CỔ', tagline: 'Ánh nhìn u tối từ bức chân dung sơn dầu giấu kín bi kịch gia tộc.' },
  19: { subtitle: 'KỲ ÁN QUAN HUYỆN XỬ ÁN', tagline: 'Bức tranh hoa mỹ giấu kín những mưu mẹo dở khóc dở cười chốn công đường.' },
  20: { subtitle: 'VỞ DIỄN CUỐI CỦA SÁT NHÂN', tagline: 'Dưới ánh đèn rọi sân khấu, kẻ đang diễn xuất là thám tử hay hung thủ?' },
  21: { subtitle: 'VÁN CƯỢC ĐẤU TRÍ THƯƠNG TRƯỜNG', tagline: 'Trên bàn thương thuyết, tiền bạc là mồi nhử còn chiến thuật quyết định người thắng.' },
  22: { subtitle: 'TIẾNG CHUÔNG LÚC NỬA ĐÊM', tagline: 'Đừng nhấc máy nếu bạn chưa sẵn sàng đối diện với bí mật bị chôn giấu.' },
  23: { subtitle: 'VÒ RƯỢU ĐẪM HẬN GIANG HỒ', tagline: 'Chén rượu tương phùng tại tửu điếm hóa thành cuộc phân định ân oán võ lâm.' },
  24: { subtitle: 'CUỘC CHIẾN TÀI SẢN VÀ HÔN NHÂN', tagline: 'Trước khi ký đơn phân chia tài sản, hãy xem ai đang giấu quỹ đen giỏi nhất.' },
  25: { subtitle: 'ĐÊM TIỆC TÁI NGỘ DỞ KHÓC DỞ CƯỜI', tagline: 'Một trăm kịch bản trả đũa người yêu cũ hóa thành vụ án bất ngờ giữa bữa tiệc.' },
  26: { subtitle: 'DẤU VẾT TRONG RỪNG ĐEN', tagline: 'Tờ thông báo tìm người dán trên thân cây thông đẫm sương đêm u tịch.' },
  27: { subtitle: 'ĐÓA HOA ĐEN CHÂU ÂU CỔ ĐIỂN', tagline: 'Vụ án mạng bí ẩn bậc nhất thành London đằng sau đóa hoa thược dược đen.' },
  28: { subtitle: 'VÒNG XOAY THỜI GIAN MÊ CUNG', tagline: 'Vân gỗ niên luân ghi dấu ký ức của những người đã từng bước vào vòng lặp.' },
  29: { subtitle: 'BÍ MẬT QUÁN TRỌ TRONG RỪNG THÔNG', tagline: 'Màn sương mù giăng kín lối thoát, giam giữ bảy vị khách trong nguy hiểm.' },
  30: { subtitle: 'TẤM KÍNH VỠ VỤN VÀ KÝ ỨC', tagline: 'Những mảnh thủy tinh phản chiếu góc khuất tâm lý không thể lường trước.' },
  31: { subtitle: 'HỒNG TRẦN KỲ ÁN CHỐN LẦU HOA', tagline: 'Rèm lụa lay động, khúc tỳ bà vừa dứt đã mở màn kỳ án kinh thành.' },
  32: { subtitle: 'QUYỀN LỰC THÂM CUNG VÀ MƯU LƯỢC', tagline: 'Dưới mái ngói lưu ly hoàng thành, mỗi bước đi đều là cuộc đấu trí sinh tồn.' },
  33: { subtitle: 'KHÚC CA CỔ PHONG TRẦM MẶC', tagline: 'Tiếng đàn tranh giữa làn sương ngọc ngân nga nỗi niềm vương vấn ngàn thu.' },
  34: { subtitle: 'QUÁN TRỌ HỒ LY TRÊN ĐỈNH NÚI', tagline: 'Ánh đèn lồng đỏ mờ ảo dẫn lối lữ khách vào kỳ cục huyền huyễn.' },
  35: { subtitle: 'HẺM ĐÊM MƯA KỲ BÍ NHẬT BẢN', tagline: 'Dưới ánh đèn vàng phố cổ Lý Xuyên, cuốn sổ quái đàm mở ra từng vụ án lạ.' },
  36: { subtitle: 'LỜI NGUYỀN CỔNG TORII LINH THIÊNG', tagline: 'Giữa làn sương nước đền cổ, nghi lễ mùa thu che giấu chân tướng vụ án.' },
  37: { subtitle: 'LỜI TẠM BIỆT TRÊN CON ĐƯỜNG THU', tagline: 'Lá vàng rơi rụng mang theo lời nhắn nhủ muộn màng của những người thương yêu.' },
  38: { subtitle: 'KHÚC VĨ CẦM BI KỊCH TÌNH TAN', tagline: 'Bản nhạc viết dở trên giấy ố vàng cất giấu câu chuyện bi thương chưa kể.' },
  39: { subtitle: 'MÙI HƯƠNG CUỐI CỦA KÝ ỨC', tagline: 'Lọ pha lê cổ lưu giữ tầng hương duy nhất vạch trần sự thật năm xưa.' },
  40: { subtitle: 'TIẾNG THÌ THẦM RỪNG NGUYÊN SINH', tagline: 'Cổ thụ ngàn năm chứng kiến bí mật bị chôn vùi dưới tầng rễ cây rêu phong.' },
  41: { subtitle: 'ÁN MẠNG PHÒNG KÍN GIỮA NÚI TUYẾT', tagline: 'Quán trọ biệt lập trên đỉnh núi tuyết phong tỏa mọi lối thoát của hung thủ.' },
  42: { subtitle: 'MẬT THẤT KHỐI LẬP PHƯƠNG TỬ ĐỊA', tagline: 'Mê cung hình học xoay chuyển theo cơ chế ngầm, đòi hỏi tư duy không gian đỉnh cao.' }
};

let svgUidCounter = 0;

function hashString(str) {
  let hash = 0;
  const s = String(str || 'game');
  for (let i = 0; i < s.length; i++) {
    hash = (hash << 5) - hash + s.charCodeAt(i);
    hash |= 0;
  }
  return Math.abs(hash);
}

export function formatVnd(amount) {
  return Number(amount || 180000).toLocaleString('vi-VN') + 'đ';
}

export function getGameVisualTheme(id, title, isAgeRestricted = false) {
  const hash = hashString(`${id || 0}_${title || 'game'}`);
  const motifIndex = ((Number(id) || hash) - 1 + 800) % 8;
  const hues = [352, 215, 36, 268, 185, 14, 332, 198];
  const primaryHue = isAgeRestricted ? 350 : hues[(hash + Number(id || 0)) % hues.length];

  return {
    motifIndex,
    primaryHue,
    bgColor1: `hsl(${primaryHue}, 36%, 11%)`,
    bgColor2: `hsl(${(primaryHue + 34) % 360}, 42%, 6%)`,
    accentColor: isAgeRestricted ? '#E11D48' : `hsl(${primaryHue}, 62%, 56%)`,
    isAgeRestricted
  };
}

function splitPosterTitle(title, maxCharsPerLine = 20) {
  const clean = String(title || 'Kịch Bản Sát').trim();
  if (clean.length <= maxCharsPerLine) return [clean];
  const words = clean.split(/\s+/);
  const line1 = [];
  const line2 = [];
  let len = 0;
  for (const w of words) {
    if (line2.length === 0 && (len + w.length + (line1.length ? 1 : 0) <= maxCharsPerLine || line1.length === 0)) {
      line1.push(w);
      len += w.length + 1;
    } else {
      line2.push(w);
    }
  }
  if (line2.length === 0) return [line1.join(' ')];
  return [line1.join(' '), line2.join(' ')];
}

function renderMotifGeometry(motifIndex, width, height, accentColor) {
  const cx = width / 2;
  const cy = height * 0.39;
  const rOuter = Math.min(width, height) * 0.27;
  const rInner = rOuter * 0.68;

  switch (motifIndex) {
    case 0: // Window & Rain Frame
      return `
        <rect x="${cx - rInner}" y="${cy - rOuter * 0.85}" width="${rInner * 2}" height="${rOuter * 1.65}" rx="3" fill="${accentColor}" fill-opacity="0.12" stroke="#C4A36B" stroke-opacity="0.4" stroke-width="1.2"/>
        <line x1="${cx}" y1="${cy - rOuter * 0.85}" x2="${cx}" y2="${cy + rOuter * 0.8}" stroke="#C4A36B" stroke-opacity="0.35" stroke-width="1"/>
        <line x1="${cx - rInner}" y1="${cy}" x2="${cx + rInner}" y2="${cy}" stroke="#C4A36B" stroke-opacity="0.35" stroke-width="1"/>
        <circle cx="${cx}" cy="${cy}" r="${rOuter * 1.05}" fill="none" stroke="${accentColor}" stroke-opacity="0.24" stroke-width="1.2" stroke-dasharray="5,4"/>
      `;
    case 1: // Fractured Mirror Diamond
      return `
        <polygon points="${cx},${cy - rOuter} ${cx + rInner * 1.1},${cy} ${cx},${cy + rOuter} ${cx - rInner * 1.1},${cy}" fill="${accentColor}" fill-opacity="0.14" stroke="#C4A36B" stroke-opacity="0.45" stroke-width="1.2"/>
        <line x1="${cx - rInner * 0.8}" y1="${cy - rInner * 0.5}" x2="${cx + rInner * 0.85}" y2="${cy + rInner * 0.55}" stroke="${accentColor}" stroke-opacity="0.45" stroke-width="1.2"/>
        <circle cx="${cx}" cy="${cy}" r="${rOuter * 1.05}" fill="none" stroke="#C4A36B" stroke-opacity="0.22" stroke-width="1"/>
      `;
    case 2: // Crescent Moon & Case Seal
      return `
        <circle cx="${cx}" cy="${cy}" r="${rOuter}" fill="none" stroke="${accentColor}" stroke-opacity="0.3" stroke-width="1.4" stroke-dasharray="6,4"/>
        <circle cx="${cx}" cy="${cy}" r="${rInner}" fill="${accentColor}" fill-opacity="0.15" stroke="#C4A36B" stroke-opacity="0.4" stroke-width="1.1"/>
        <circle cx="${cx + rInner * 0.34}" cy="${cy - rInner * 0.16}" r="${rInner * 0.78}" fill="#0A0C10" fill-opacity="0.82"/>
      `;
    case 3: // Clockwork Dial
      return `
        <circle cx="${cx}" cy="${cy}" r="${rOuter}" fill="${accentColor}" fill-opacity="0.1" stroke="#C4A36B" stroke-opacity="0.42" stroke-width="1.3"/>
        <circle cx="${cx}" cy="${cy}" r="${rInner * 0.85}" fill="none" stroke="${accentColor}" stroke-opacity="0.3" stroke-width="1" stroke-dasharray="3,3"/>
        <line x1="${cx}" y1="${cy}" x2="${cx}" y2="${cy - rInner * 0.78}" stroke="#F3F0E9" stroke-opacity="0.65" stroke-width="1.6"/>
        <line x1="${cx}" y1="${cy}" x2="${cx + rInner * 0.55}" y2="${cy + rInner * 0.25}" stroke="#C4A36B" stroke-opacity="0.7" stroke-width="1.4"/>
      `;
    case 4: // Oriental Roof / Torii Arch
      return `
        <path d="M ${cx - rOuter * 1.15} ${cy - rInner * 0.45} Q ${cx} ${cy - rOuter * 0.95} ${cx + rOuter * 1.15} ${cy - rInner * 0.45}" fill="none" stroke="#C4A36B" stroke-opacity="0.55" stroke-width="2"/>
        <line x1="${cx - rInner * 1.1}" y1="${cy - rInner * 0.2}" x2="${cx + rInner * 1.1}" y2="${cy - rInner * 0.2}" stroke="${accentColor}" stroke-opacity="0.45" stroke-width="1.2"/>
        <line x1="${cx - rInner * 0.72}" y1="${cy - rInner * 0.5}" x2="${cx - rInner * 0.72}" y2="${cy + rOuter * 0.82}" stroke="#C4A36B" stroke-opacity="0.42" stroke-width="1.5"/>
        <line x1="${cx + rInner * 0.72}" y1="${cy - rInner * 0.5}" x2="${cx + rInner * 0.72}" y2="${cy + rOuter * 0.82}" stroke="#C4A36B" stroke-opacity="0.42" stroke-width="1.5"/>
      `;
    case 5: // Perspective Corridor / Train Tracks
      return `
        <rect x="${cx - rInner * 0.52}" y="${cy - rInner * 0.65}" width="${rInner * 1.04}" height="${rInner * 1.3}" fill="${accentColor}" fill-opacity="0.16" stroke="#C4A36B" stroke-opacity="0.45" stroke-width="1.2"/>
        <line x1="${cx - rOuter * 1.25}" y1="${cy + rOuter * 0.95}" x2="${cx - rInner * 0.52}" y2="${cy + rInner * 0.65}" stroke="#C4A36B" stroke-opacity="0.35" stroke-width="1.1"/>
        <line x1="${cx + rOuter * 1.25}" y1="${cy + rOuter * 0.95}" x2="${cx + rInner * 0.52}" y2="${cy + rInner * 0.65}" stroke="#C4A36B" stroke-opacity="0.35" stroke-width="1.1"/>
        <circle cx="${cx}" cy="${cy}" r="${rOuter}" fill="none" stroke="${accentColor}" stroke-opacity="0.22" stroke-width="1" stroke-dasharray="5,5"/>
      `;
    case 6: // Hanging Lantern Octagon
      return `
        <line x1="${cx}" y1="12" x2="${cx}" y2="${cy - rInner * 0.85}" stroke="#C4A36B" stroke-opacity="0.5" stroke-width="1.2"/>
        <polygon points="${cx - rInner * 0.55},${cy - rInner * 0.85} ${cx + rInner * 0.55},${cy - rInner * 0.85} ${cx + rInner * 0.85},${cy} ${cx + rInner * 0.55},${cy + rInner * 0.85} ${cx - rInner * 0.55},${cy + rInner * 0.85} ${cx - rInner * 0.85},${cy}" fill="${accentColor}" fill-opacity="0.18" stroke="#C4A36B" stroke-opacity="0.48" stroke-width="1.3"/>
        <circle cx="${cx}" cy="${cy}" r="${rInner * 0.28}" fill="${accentColor}" fill-opacity="0.38"/>
      `;
    default: // Isometric Cube / Labyrinth
      return `
        <polygon points="${cx},${cy - rOuter * 0.92} ${cx + rOuter * 0.86},${cy - rOuter * 0.42} ${cx + rOuter * 0.86},${cy + rOuter * 0.52} ${cx},${cy + rOuter * 0.98} ${cx - rOuter * 0.86},${cy + rOuter * 0.52} ${cx - rOuter * 0.86},${cy - rOuter * 0.42}" fill="${accentColor}" fill-opacity="0.14" stroke="#C4A36B" stroke-opacity="0.48" stroke-width="1.3"/>
        <line x1="${cx}" y1="${cy - rOuter * 0.92}" x2="${cx}" y2="${cy + rOuter * 0.98}" stroke="#C4A36B" stroke-opacity="0.3" stroke-width="1"/>
        <line x1="${cx - rOuter * 0.86}" y1="${cy - rOuter * 0.42}" x2="${cx + rOuter * 0.86}" y2="${cy + rOuter * 0.52}" stroke="${accentColor}" stroke-opacity="0.32" stroke-width="1"/>
        <line x1="${cx + rOuter * 0.86}" y1="${cy - rOuter * 0.42}" x2="${cx - rOuter * 0.86}" y2="${cy + rOuter * 0.52}" stroke="${accentColor}" stroke-opacity="0.32" stroke-width="1"/>
      `;
  }
}

export function generateGamePosterSvg(gameOrTitle, options = {}) {
  const isObj = typeof gameOrTitle === 'object' && gameOrTitle !== null;
  const id = isObj ? Number(gameOrTitle.id || 1) : Number(options.id || 1);
  const title = isObj ? (gameOrTitle.title || gameOrTitle.name || 'Kịch Bản Sát') : String(gameOrTitle || 'Kịch Bản Sát');
  const isAgeRestricted = isObj
    ? Boolean(gameOrTitle.is18Plus || gameOrTitle.is_age_restricted || Number(gameOrTitle.is_18_plus) === 1)
    : Boolean(options.isAgeRestricted);
  const meta = POSTER_METADATA[id] || {};
  const subtitle = (isObj && gameOrTitle.subtitle) || meta.subtitle || 'HỒ SƠ ÁN MẠNG LƯU TRỮ';
  const codeLabel = `KBS-${String(id).padStart(2, '0')}`;

  const width = Number(options.width || 360);
  const height = Number(options.height || 240);
  const uid = `kbs_${++svgUidCounter}_${id}`;

  const theme = getGameVisualTheme(id, title, isAgeRestricted);
  const lines = splitPosterTitle(title, width >= 380 ? 22 : 20);
  const titleFontSize = lines.length > 1 ? 17 : 20;
  const safeSub = escapeHtml(subtitle.slice(0, 38));
  const safeCode = escapeHtml(codeLabel);

  const titleSvg = lines.length === 1
    ? `<text x="${width / 2}" y="${Math.round(height * 0.82)}" font-family="'Playfair Display', Georgia, serif" font-size="${titleFontSize}" font-weight="700" fill="#F3F0E9" text-anchor="middle" letter-spacing="0.8">${escapeHtml(lines[0])}</text>`
    : `<text x="${width / 2}" y="${Math.round(height * 0.76)}" font-family="'Playfair Display', Georgia, serif" font-size="${titleFontSize}" font-weight="700" fill="#F3F0E9" text-anchor="middle" letter-spacing="0.6">
        <tspan x="${width / 2}" dy="0">${escapeHtml(lines[0])}</tspan>
        <tspan x="${width / 2}" dy="20">${escapeHtml(lines[1])}</tspan>
      </text>`;

  return `<svg class="noir-poster-svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="${escapeHtml(title)}">
    <defs>
      <linearGradient id="bgGrad_${uid}" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="${theme.bgColor1}"/>
        <stop offset="100%" stop-color="${theme.bgColor2}"/>
      </linearGradient>
      <radialGradient id="vignette_${uid}" cx="50%" cy="45%" r="68%">
        <stop offset="55%" stop-color="#000000" stop-opacity="0"/>
        <stop offset="100%" stop-color="#000000" stop-opacity="0.86"/>
      </radialGradient>
      <pattern id="grid_${uid}" width="36" height="36" patternUnits="userSpaceOnUse">
        <path d="M 36 0 L 0 0 0 36" fill="none" stroke="rgba(196,163,107,0.06)" stroke-width="1"/>
      </pattern>
      <linearGradient id="shadow_${uid}" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#08090C" stop-opacity="0"/>
        <stop offset="100%" stop-color="#08090C" stop-opacity="0.96"/>
      </linearGradient>
    </defs>
    <rect width="${width}" height="${height}" fill="url(#bgGrad_${uid})"/>
    <rect width="${width}" height="${height}" fill="url(#grid_${uid})"/>
    ${renderMotifGeometry(theme.motifIndex, width, height, theme.accentColor)}
    <rect width="${width}" height="${height}" fill="url(#vignette_${uid})"/>
    <rect x="10" y="10" width="${width - 20}" height="${height - 20}" rx="4" fill="none" stroke="#C4A36B" stroke-opacity="0.32" stroke-width="1"/>
    <g transform="translate(20, 20)">
      <rect width="58" height="20" rx="2" fill="rgba(10,12,17,0.78)" stroke="rgba(196,163,107,0.45)" stroke-width="1"/>
      <text x="29" y="14" font-family="'JetBrains Mono', monospace" font-size="10" font-weight="700" fill="#C4A36B" text-anchor="middle">${safeCode}</text>
    </g>
    ${isAgeRestricted ? `
    <g transform="translate(${width - 62}, 20)">
      <rect width="42" height="20" rx="2" fill="rgba(225,29,72,0.28)" stroke="#E11D48" stroke-width="1.2"/>
      <text x="21" y="14" font-family="'JetBrains Mono', monospace" font-size="10" font-weight="700" fill="#FFE4E6" text-anchor="middle">18+</text>
    </g>` : ''}
    <rect x="0" y="${Math.round(height * 0.56)}" width="${width}" height="${Math.round(height * 0.44)}" fill="url(#shadow_${uid})"/>
    <text x="${width / 2}" y="${Math.round(height * 0.66)}" font-family="'JetBrains Mono', monospace" font-size="8.5" fill="#C4A36B" text-anchor="middle" letter-spacing="1.2">${safeSub}</text>
    ${titleSvg}
    <text x="${width / 2}" y="${height - 15}" font-family="'Inter', sans-serif" font-size="8.5" fill="#92949B" text-anchor="middle" letter-spacing="1.8">JUBENSHA - KỊCH BẢN SÁT</text>
  </svg>`;
}

export function normalizeScenario(raw) {
  const id = Number(raw?.id || 1);
  const meta = POSTER_METADATA[id] || {};
  const title = String(raw?.title || raw?.name || 'Kịch Bản Sát');
  const is18Plus = Boolean(raw?.is18Plus || raw?.is_age_restricted || Number(raw?.is_18_plus) === 1);
  const genre = String(raw?.genre || (is18Plus ? '18+ Kinh dị' : 'Trinh thám'));
  const difficulty = String(raw?.difficulty || (is18Plus ? 'Khó' : 'Trung bình'));
  const difficultyLevel = Number(raw?.difficulty_level || (difficulty.includes('Rất khó') ? 5 : difficulty.includes('Khó') ? 4 : 3));
  const minPlayers = Number(raw?.min_players || 6);
  const maxPlayers = Number(raw?.max_players || 8);
  const durationMinutes = Number(raw?.duration_minutes || 240);
  const priceDay = Number(raw?.min_price || raw?.price || raw?.price_day_vnd || 180000);
  const priceNight = Number(raw?.max_price || raw?.price_night_vnd || (priceDay + 60000));

  return {
    id,
    code: `KBS-${String(id).padStart(2, '0')}`,
    dossierCode: `JBS-V16-${String(id).padStart(3, '0')}`,
    title,
    subtitle: String(raw?.subtitle || meta.subtitle || 'HỒ SƠ ÁN MẠNG LƯU TRỮ'),
    tagline: String(raw?.tagline || meta.tagline || raw?.description || raw?.summary || ''),
    description: String(raw?.description || raw?.summary || meta.tagline || ''),
    genre,
    difficulty,
    difficultyLevel,
    themeCode: String(raw?.theme_code || 'DETECTIVE').toUpperCase(),
    is18Plus,
    minPlayers,
    maxPlayers,
    durationMinutes,
    priceDay,
    priceNight,
    props: Array.isArray(raw?.props) ? raw.props : []
  };
}

export function renderGameFolioCardHtml(rawGame, options = {}) {
  const g = normalizeScenario(rawGame);
  const detailBase = options.detailBase || './scenario-detail.html';
  const createBase = options.createBase || './create-room.html';
  const detailHref = `${detailBase}?id=${encodeURIComponent(g.id)}`;
  const createHref = `${createBase}?game_id=${encodeURIComponent(g.id)}`;

  const safeTitle = escapeHtml(g.title);
  const safeGenre = escapeHtml(g.genre);
  const safeTagline = escapeHtml(g.tagline || g.description);
  const playerRange = g.minPlayers === g.maxPlayers ? `${g.minPlayers}` : `${g.minPlayers}-${g.maxPlayers}`;
  const posterSvg = generateGamePosterSvg(g, { width: 360, height: 240 });

  return `
    <article class="game-folio-item" data-game-id="${g.id}">
      <a href="${detailHref}" class="game-folio-poster scenario-nav-link" data-id="${g.id}" data-adult="${g.is18Plus ? 'true' : 'false'}" data-title="${safeTitle}" aria-label="Xem hồ sơ ${safeTitle}">
        ${posterSvg}
      </a>
      <div class="game-folio-body">
        <div class="game-tags-row">
          <span class="game-genre-tag">${safeGenre}</span>
          ${g.is18Plus
            ? '<span class="game-18-badge">18+</span>'
            : `<span class="game-diff-tag">Độ khó ${g.difficultyLevel}/5</span>`}
        </div>
        <h3 class="game-folio-title">
          <a href="${detailHref}" class="scenario-nav-link" data-id="${g.id}" data-adult="${g.is18Plus ? 'true' : 'false'}" data-title="${safeTitle}">${safeTitle}</a>
        </h3>
        <p class="game-folio-tagline">${safeTagline}</p>
        <div class="game-folio-meta">
          <span>${playerRange} thám tử</span>
          <span>${g.durationMinutes} phút</span>
        </div>
        <div class="game-folio-price">
          <span>Ngày: <strong>${formatVnd(g.priceDay)}</strong></span>
          <span>Tối: <strong class="highlight-gold">${formatVnd(g.priceNight)}</strong></span>
        </div>
        <div class="game-folio-actions">
          <a href="${detailHref}" class="btn btn-outline scenario-nav-link" data-id="${g.id}" data-adult="${g.is18Plus ? 'true' : 'false'}" data-title="${safeTitle}">Xem Hồ Sơ</a>
          <a href="${createHref}" class="btn btn-primary scenario-nav-link" data-id="${g.id}" data-adult="${g.is18Plus ? 'true' : 'false'}" data-title="${safeTitle}">Mở Phòng</a>
        </div>
      </div>
    </article>
  `;
}
