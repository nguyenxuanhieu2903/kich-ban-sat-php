import { api } from '../../api/api-client.js';
import { authState } from '../../api/auth-state.js';
import { toast } from '../../utils/toast.js';
import { escapeHtml, sanitizeUrl } from '../../utils/sanitize.js';
import { ALL_SCRIPTS } from '../../data/scripts.data.js';

export class HomeView {
  constructor(container, options = {}) {
    this.container = container;
    this.onNavigate = options.onNavigate || ((tab) => { window.location.hash = `#${tab}`; });
    this.onOpenAuth = options.onOpenAuth || (() => {});

    this.games = [];
    this.promotions = [];
    this.formingRooms = [];
    this.searchQuery = '';
    this.selectedPlayers = '';
    this.selectedGenre = 'Tất cả';

    this.isLoadingGames = false;
    this.isLoadingPromotions = false;
    this.gamesError = null;
    this.promotionsError = null;
    this.ticking = false;
  }

  async render() {
    const user = authState.getUser();
    let roleBannerHtml = '';

    if (user) {
      if (user.role === 'ADMIN') {
        roleBannerHtml = `
          <div style="background: rgba(196, 55, 55, 0.14); border: 1px solid var(--crimson); border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <span style="font-size: 1.8rem;">🛡️</span>
              <div>
                <strong style="color: #fca5a5; font-size: 1rem;">Bạn đang đăng nhập: Quản Trị Viên (Admin) — ${escapeHtml(user.name)}</strong>
                <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: var(--text-secondary);">Bạn có toàn quyền điều phối phòng chơi, phân công DM và xét duyệt hoàn tiền.</p>
              </div>
            </div>
            <a href="#admin" class="btn btn-crimson btn-sm btn-role-tab" data-tab="admin" style="font-size: 0.88rem;">
              Vào Trang ADMIN &rarr;
            </a>
          </div>
        `;
      } else if (user.role === 'DM') {
        roleBannerHtml = `
          <div style="background: rgba(212, 175, 55, 0.14); border: 1px solid var(--gold-border); border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <span style="font-size: 1.8rem;">📜</span>
              <div>
                <strong style="color: var(--gold); font-size: 1rem;">Bạn đang đăng nhập: Quản Trò (DM) — Host ${escapeHtml(user.name)}</strong>
                <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: var(--text-secondary);">Truy cập cổng Quản trò để xem lịch trực hôm nay và điểm danh người chơi.</p>
              </div>
            </div>
            <a href="#dm" class="btn btn-gold btn-sm btn-role-tab" data-tab="dm" style="font-size: 0.88rem; color: var(--ink-black);">
              Vào Trang DM &rarr;
            </a>
          </div>
        `;
      } else {
        roleBannerHtml = `
          <div style="background: rgba(46, 125, 79, 0.14); border: 1px solid rgba(46, 125, 79, 0.4); border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <span style="font-size: 1.8rem;">🎲</span>
              <div>
                <strong style="color: #5CD68D; font-size: 1rem;">Chào mừng Người Chơi: ${escapeHtml(user.name)}</strong>
                <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: var(--text-secondary);">Bạn có thể kiểm tra vé cọc 50k, phòng đã chốt hoặc ghép nhóm mới tại Trang Player.</p>
              </div>
            </div>
            <a href="#rooms" class="btn btn-gold btn-sm btn-role-tab" data-tab="rooms" style="font-size: 0.88rem; color: var(--ink-black);">
              Vào Trang PLAYER &rarr;
            </a>
          </div>
        `;
      }
    }

    this.container.innerHTML = `
      <!-- VÙNG SCROLLYTELLING PARALLAX 3 TẦNG (FE_KỊCH_BẢN_SÁT) -->
      <section class="scrolly-stage" id="scrolly-stage">
        <div class="scrolly-sticky">
          <div class="scrolly-progress-bar" id="scrolly-progress"></div>

          <!-- PHÂN CẢNH 1: CỔNG DINH THỰ & MÀN SƯƠNG -->
          <div class="scene-layer s1-sky" id="s1-sky">
            <div class="blood-moon"></div>
          </div>

          <div class="scene-layer s1-mansion" id="s1-mansion">
            <svg class="mansion-svg" viewBox="0 0 800 320" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M80 320V170L160 110L240 170V130L400 40L560 130V170L640 110L720 170V320H80Z" fill="#0B0909"/>
              <polygon points="400,15 385,45 415,45" fill="#0B0909"/>
              <rect x="372" y="95" width="20" height="32" rx="2" fill="#D4AF37" opacity="0.9"/>
              <rect x="408" y="95" width="20" height="32" rx="2" fill="#D4AF37" opacity="0.75"/>
              <rect x="280" y="165" width="22" height="36" rx="2" fill="#D4AF37" opacity="0.85"/>
              <rect x="330" y="165" width="22" height="36" rx="2" fill="#D4AF37" opacity="0.5"/>
              <rect x="448" y="165" width="22" height="36" rx="2" fill="#D4AF37" opacity="0.9"/>
              <rect x="498" y="165" width="22" height="36" rx="2" fill="#D4AF37" opacity="0.65"/>
              <rect x="148" y="195" width="24" height="38" rx="2" fill="#D4AF37" opacity="0.7"/>
              <rect x="628" y="195" width="24" height="38" rx="2" fill="#D4AF37" opacity="0.85"/>
            </svg>
          </div>

          <div class="scene-layer s1-gates" id="s1-gates">
            <div class="iron-gate left" id="gate-left"></div>
            <div class="iron-gate right" id="gate-right"></div>
          </div>

          <div class="s1-content" id="s1-content">
            <span class="hero-kicker">36/52 NGUYỄN GIA TRÍ • BÌNH THẠNH</span>
            <h1 class="hero-title">KỊCH BẢN <span class="text-crimson">SÁT</span></h1>
            <p class="hero-desc">
              Chọn kịch bản, ghép đủ 5–8 người, giữ chỗ trong 4 giờ. Hung thủ đang ngồi ngay trong phòng.
            </p>
            <div class="hero-cta-row">
              <a href="#section-featured-games" class="btn btn-gold">Khám phá kịch bản</a>
              <button id="hero-btn-create-room" class="btn btn-ghost" style="border-color: rgba(255,255,255,0.4); color: #fff;">Tạo phòng ngay</button>
            </div>
            <div class="scroll-cue" id="scroll-cue">↓ Cuộn để bước vào hiện trường</div>
          </div>

          <!-- PHÂN CẢNH 2: BÀN TRÒN NGHI PHẠM & ÁNH NẾN -->
          <div class="scene-layer s2-room" id="s2-room">
            <div class="s2-copy" id="s2-copy">
              <span class="hero-kicker">PHÒNG THẨM VẤN KÍN</span>
              <h2>5–8 NGHI PHẠM.<br /><span class="text-crimson">1 HUNG THỦ.</span></h2>
              <p class="text-muted" style="font-size: 1.05rem;">
                Mỗi người cầm một vai và giấu một bí mật. Quản trò (DM) giữ nhịp, cả nhóm lần ra sự thật.
              </p>
            </div>

            <div class="suspects-row">
              <div class="suspect-silhouette"></div>
              <div class="suspect-silhouette"></div>
              <div class="suspect-silhouette"></div>
              <div class="suspect-silhouette"></div>
              <div class="suspect-silhouette culprit" title="Nghi phạm số 5"></div>
              <div class="suspect-silhouette"></div>
              <div class="suspect-silhouette"></div>
            </div>
            <div class="interrogation-table">
              <div class="candle"></div>
              <div class="candle"></div>
              <div class="candle"></div>
            </div>
          </div>

          <!-- PHÂN CẢNH 3: RÚT HỒ SƠ NHÂN VẬT 3D CARD FAN -->
          <div class="scene-layer s3-cards-scene" id="s3-cards">
            <div class="card-fan">
              <div class="dossier-card dossier-paper" id="card-left">
                <span class="dossier-stamp">HỒ SƠ A</span>
                <div>
                  <h3 style="font-family: var(--font-display); color: var(--crimson); margin-bottom: 6px;">Người thừa kế</h3>
                  <p style="font-size: 0.8rem; color: var(--ink-muted);">Có mặt tại thư phòng lúc 22:15. Giữ chìa khóa két sắt.</p>
                </div>
                <span style="font-family: var(--font-mono); font-size: 0.72rem;">MẬT MÃ #01</span>
              </div>

              <div class="dossier-card dossier-culprit" id="card-center">
                <span class="badge badge-gold" style="width: fit-content;">#KBS</span>
                <div>
                  <div class="question-emblem">?</div>
                  <p style="text-align: center; color: var(--gold); font-weight: 700; font-size: 0.95rem;">Ai là hung thủ?</p>
                </div>
                <span style="font-family: var(--font-mono); font-size: 0.72rem; color: var(--text-dim); text-align: center;">VAI DIỄN BÍ MẬT</span>
              </div>

              <div class="dossier-card dossier-paper" id="card-right">
                <span class="dossier-stamp">HỒ SƠ B</span>
                <div>
                  <h3 style="font-family: var(--font-display); color: var(--ink-black); margin-bottom: 6px;">Quản gia</h3>
                  <p style="font-size: 0.8rem; color: var(--ink-muted);">Người cuối cùng dâng trà cho nạn nhân trước khi mất điện.</p>
                </div>
                <span style="font-family: var(--font-mono); font-size: 0.72rem;">MẬT MÃ #02</span>
              </div>
            </div>

            <div class="s3-copy">
              <h2>RÚT MỘT HỒ SƠ. <span class="text-gold">LẬT MỘT VAI.</span></h2>
              <p class="text-muted" style="margin-bottom: 20px;">Mỗi kịch bản là một vụ án hoàn toàn khác nhau.</p>
              <a href="#section-featured-games" class="btn btn-gold">Chọn kịch bản</a>
            </div>
          </div>
        </div>
      </section>

      <!-- KHỐI 1: PHÒNG ĐANG TUYỂN NGƯỜI CHƠI (GHÉP NHÓM NHANH) -->
      <section class="home-section" style="margin-top: 40px;">
        <div class="container">
          ${roleBannerHtml}
          <div class="section-head" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
            <div>
              <div class="section-kicker" style="color: var(--gold); font-size: 0.8rem; letter-spacing: 0.1em; font-family: var(--font-mono);">GHÉP NHÓM NHANH</div>
              <h2 class="section-title" style="font-family: var(--font-display); font-size: 1.8rem; margin: 4px 0 0;">Phòng Đang Ghép Người Chơi</h2>
            </div>
            <a href="#rooms" id="btn-view-all-rooms" class="btn btn-ghost btn-sm" data-tab="rooms">Xem tất cả phòng &rarr;</a>
          </div>
          <div class="rooms-grid" id="home-rooms-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
            <div style="text-align: center; padding: 40px; grid-column: 1/-1;" class="text-muted">Đang tải phòng chơi trực tiếp...</div>
          </div>
        </div>
      </section>

      <!-- KHỐI 2: KHO 42 VỤ ÁN & KỊCH BẢN NỔI BẬT -->
      <section class="home-section" id="section-featured-games" style="margin-top: 48px;">
        <div class="container">
          <div class="section-head" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
            <div>
              <div class="section-kicker" style="color: var(--gold); font-size: 0.8rem; letter-spacing: 0.1em; font-family: var(--font-mono);">KHO 42 VỤ ÁN ĐẶC SẮC</div>
              <h2 class="section-title" style="font-family: var(--font-display); font-size: 1.8rem; margin: 4px 0 0;">Kịch Bản Jubensha Nổi Bật</h2>
            </div>
            <div style="display: flex; gap: 8px;">
              <button id="btn-open-price-modal" class="btn btn-ghost btn-sm" style="color: var(--gold); border-color: var(--gold-border);">
                📊 Xem bảng giá chi tiết
              </button>
            </div>
          </div>

          <!-- Bộ Lọc Thể Loại -->
          <div class="filter-pills" id="home-genre-filters" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;"></div>

          <!-- Danh Sách Kịch Bản -->
          <div class="scripts-grid" id="home-scripts-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 22px;"></div>
        </div>
      </section>

      <!-- KHỐI 3: NIỀM TIN KHÁCH HÀNG (TRUST BANNER) -->
      <section class="home-section" style="margin-top: 48px;">
        <div class="container">
          <div class="trust-banner" style="background: var(--bg-surface); border: 1px solid var(--gold-border); border-radius: 12px; padding: 36px 32px; position: relative; overflow: hidden;">
            <div class="trust-watermark" style="position: absolute; right: 24px; top: 12px; font-size: 7rem; font-weight: 900; color: rgba(212,175,55,0.06); pointer-events: none;">59</div>
            <span class="badge badge-gold">XÁC THỰC TỪ CỘNG ĐỒNG FACEBOOK QUÁN</span>
            <h2 class="section-title" style="font-family: var(--font-display); font-size: 1.8rem; margin: 12px 0 8px;">
              KHÁCH ĐÁNH GIÁ: <span class="text-gold">100% ĐỀ XUẤT</span>
            </h2>
            <p class="text-muted" style="max-width: 640px; margin-bottom: 24px; font-size: 0.95rem; line-height: 1.6;">
              Trải nghiệm nhập vai phá án Jubensha có Quản trò (DM) dẫn dắt bài bản, hệ thống đạo cụ trau chuốt và phòng chơi riêng tư tại 36/52 Nguyễn Gia Trí, Bình Thạnh.
            </p>
            <div class="trust-metrics" style="display: flex; gap: 36px; flex-wrap: wrap;">
              <div>
                <div class="metric-value" style="font-family: var(--font-display); font-size: 2rem; color: var(--gold); font-weight: 800;">100%</div>
                <div class="text-muted" style="font-size: 0.85rem;">Đề xuất quán trên Facebook</div>
              </div>
              <div>
                <div class="metric-value" style="font-family: var(--font-display); font-size: 2rem; font-weight: 800; color: var(--text-primary);">59</div>
                <div class="text-muted" style="font-size: 0.85rem;">Lượt đánh giá thực tế</div>
              </div>
              <div>
                <div class="metric-value" style="font-family: var(--font-display); font-size: 2rem; color: var(--gold); font-weight: 800;">2,2K</div>
                <div class="text-muted" style="font-size: 0.85rem;">Người theo dõi cộng đồng</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- KHỐI 4: QUY TRÌNH 4 BƯỚC & CHÍNH SÁCH VÉ V16 -->
      <section class="home-section" style="margin-top: 48px; margin-bottom: 40px;">
        <div class="container">
          <div class="section-head" style="margin-bottom: 24px;">
            <div class="section-kicker" style="color: var(--gold); font-size: 0.8rem; letter-spacing: 0.1em; font-family: var(--font-mono);">MINH BẠCH & RÕ RÀNG</div>
            <h2 class="section-title" style="font-family: var(--font-display); font-size: 1.8rem; margin: 4px 0 0;">Quy Trình 4 Bước & Chính Sách Vé V16</h2>
          </div>

          <div class="steps-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 18px; margin-bottom: 28px;">
            <div class="step-card" style="background: var(--bg-surface); border: 1px solid rgba(255,255,255,0.1); padding: 22px; border-radius: 8px;">
              <div class="step-num" style="width: 36px; height: 36px; border-radius: 50%; background: var(--gold); color: var(--ink-black); font-weight: 800; display: grid; place-items: center; margin-bottom: 12px;">1</div>
              <h3 style="font-family: var(--font-display); margin-bottom: 6px;">Chọn kịch bản</h3>
              <p class="text-muted" style="font-size: 0.88rem; line-height: 1.5;">Lọc theo 5–8 người, độ khó và thể loại trinh thám, kinh dị hoặc cổ trang.</p>
            </div>
            <div class="step-card" style="background: var(--bg-surface); border: 1px solid rgba(255,255,255,0.1); padding: 22px; border-radius: 8px;">
              <div class="step-num" style="width: 36px; height: 36px; border-radius: 50%; background: var(--gold); color: var(--ink-black); font-weight: 800; display: grid; place-items: center; margin-bottom: 12px;">2</div>
              <h3 style="font-family: var(--font-display); margin-bottom: 6px;">Tạo hoặc ghép phòng</h3>
              <p class="text-muted" style="font-size: 0.88rem; line-height: 1.5;">Mở phòng khung giờ chẵn hoặc ghép slot cùng nhóm đang thiếu người chơi.</p>
            </div>
            <div class="step-card" style="background: var(--bg-surface); border: 1px solid rgba(255,255,255,0.1); padding: 22px; border-radius: 8px;">
              <div class="step-num" style="width: 36px; height: 36px; border-radius: 50%; background: var(--gold); color: var(--ink-black); font-weight: 800; display: grid; place-items: center; margin-bottom: 12px;">3</div>
              <h3 style="font-family: var(--font-display); margin-bottom: 6px;">Cọc 50.000đ trong 4 giờ</h3>
              <p class="text-muted" style="font-size: 0.88rem; line-height: 1.5;">Hệ thống giữ chỗ 4 giờ để bạn hoàn tất chuyển khoản cọc và nhận mã QR Check-in.</p>
            </div>
            <div class="step-card" style="background: var(--bg-surface); border: 1px solid rgba(255,255,255,0.1); padding: 22px; border-radius: 8px;">
              <div class="step-num" style="width: 36px; height: 36px; border-radius: 50%; background: var(--gold); color: var(--ink-black); font-weight: 800; display: grid; place-items: center; margin-bottom: 12px;">4</div>
              <h3 style="font-family: var(--font-display); margin-bottom: 6px;">Đến quán, DM dẫn ca</h3>
              <p class="text-muted" style="font-size: 0.88rem; line-height: 1.5;">Check-in tại 36/52 Nguyễn Gia Trí, nhận hồ sơ nhân vật và bắt đầu phá án.</p>
            </div>
          </div>

          <!-- 3 Vé Chính Sách Chuẩn V16 -->
          <div class="policy-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <div class="paper-ticket policy-ticket" style="padding: 22px;">
              <span class="badge badge-18">CỌC CỐ ĐỊNH</span>
              <h3 style="font-family: var(--font-display); font-size: 1.35rem; margin: 10px 0 6px;">Cọc 50.000đ / người</h3>
              <p style="font-size: 0.88rem; color: var(--ink-muted); line-height: 1.5;">
                Khấu trừ trực tiếp vào tiền vé khi thanh toán phần còn lại tại quán. Mã QR mở khóa ngay sau khi chuyển cọc.
              </p>
            </div>
            <div class="paper-ticket policy-ticket" style="padding: 22px;">
              <span class="badge badge-18">THỜI HẠN GIỮ GHẾ</span>
              <h3 style="font-family: var(--font-display); font-size: 1.35rem; margin: 10px 0 6px;">Giữ chỗ 4 giờ</h3>
              <p style="font-size: 0.88rem; color: var(--ink-muted); line-height: 1.5;">
                Đồng hồ đếm ngược 4 giờ kích hoạt ngay khi giữ ghế. Quá 4 giờ chưa cọc ghế sẽ tự động mở lại cho người khác.
              </p>
            </div>
            <div class="paper-ticket policy-ticket" style="padding: 22px;">
              <span class="badge badge-18">QUY TẮC VÀNG 24H</span>
              <h3 style="font-family: var(--font-display); font-size: 1.35rem; margin: 10px 0 6px;">Hoàn 100% trước 24h</h3>
              <p style="font-size: 0.88rem; color: var(--ink-muted); line-height: 1.5;">
                Hủy trước giờ chơi trên 24 tiếng được hoàn đủ 50.000đ/người. Hủy dưới 24 tiếng sẽ không được hoàn cọc.
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- POPUP XÁC NHẬN ĐỘ TUỔI 18+ -->
      <div class="modal-backdrop" id="age-modal">
        <div class="paper-ticket modal-box" style="padding: 28px; max-width: 480px; margin: auto;">
          <span class="badge badge-18">CẢNH BÁO NỘI DUNG 18+</span>
          <h3 style="font-family: var(--font-display); font-size: 1.45rem; margin: 12px 0 8px;">Xác nhận độ tuổi tham gia</h3>
          <p style="font-size: 0.92rem; color: var(--ink-muted); margin-bottom: 20px; line-height: 1.5;">
            Kịch bản <strong id="age-script-name" style="color: var(--crimson);"></strong> chứa yếu tố tâm lý nặng, kinh dị hoặc tình tiết án mạng nhạy cảm chỉ dành cho người chơi từ <strong>18 tuổi trở lên</strong>.
          </p>
          <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button class="btn btn-ghost" style="color: var(--ink-black); border-color: #999;" id="age-cancel-btn">Quay lại</button>
            <button class="btn btn-crimson" id="age-confirm-btn">Tôi đã đủ 18 tuổi &rarr;</button>
          </div>
        </div>
      </div>

      <!-- PRICE MATRIX MODAL -->
      <dialog id="home-price-dialog" style="padding: 24px; background: var(--bg-surface); border: 1px solid var(--gold-border); border-radius: 12px; color: var(--text-primary); max-width: 600px; width: 92%; margin: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">
          <h3 style="margin: 0; font-family: var(--font-display); font-size: 1.3rem; color: var(--gold);">Bảng Giá Vé Chuẩn V16 Theo Khung Giờ</h3>
          <button id="btn-close-price-dialog" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">&times;</button>
        </div>
        <div style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 16px;">
          Giá vé áp dụng theo người, đã bao gồm Quản trò DM độc quyền, đạo cụ nhân vật, trà bánh và phòng riêng:
        </div>
        <table class="data-table" style="width: 100%; margin-bottom: 16px;">
          <thead><tr><th>Khung giờ</th><th>Thứ 2 – Thứ 5</th><th>Thứ 6 – Chủ Nhật</th></tr></thead>
          <tbody>
            <tr><td><strong>09:00 – 12:30</strong></td><td class="text-gold">119.000đ / người</td><td>129.000đ / người</td></tr>
            <tr><td><strong>14:00 – 17:30</strong></td><td class="text-gold">129.000đ / người</td><td>139.000đ / người</td></tr>
            <tr><td><strong>19:00 – 22:30</strong> (Vàng)</td><td class="text-gold">139.000đ / người</td><td>149.000đ / người</td></tr>
          </tbody>
        </table>
        <div style="background: rgba(212,175,55,0.08); padding: 12px; border-radius: 6px; font-size: 0.82rem; color: var(--gold); border: 1px solid var(--gold-border);">
          🎉 <strong>Ưu đãi HSSV:</strong> Tự động giảm thêm 20.000đ/người cho ca ngày thường (09:00–19:59).<br />
          🎉 <strong>Thứ 3 Đen Tối:</strong> Giảm 30% giá vé kịch bản kinh dị.
        </div>
      </dialog>
    `;

    this.setupParallaxEngine();
    this.setupAgeModal();
    this.setupPriceModal();

    const heroCreateBtn = document.getElementById('hero-btn-create-room');
    if (heroCreateBtn) {
      heroCreateBtn.addEventListener('click', () => {
        if (!authState.isAuthenticated()) {
          toast.info('Vui lòng đăng nhập để bắt đầu tạo phòng chơi!');
          this.onOpenAuth();
        } else {
          this.onNavigate('rooms');
        }
      });
    }

    const viewAllRoomsBtn = document.getElementById('btn-view-all-rooms');
    if (viewAllRoomsBtn) {
      viewAllRoomsBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.onNavigate('rooms');
      });
    }

    document.querySelectorAll('.btn-role-tab').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const tab = btn.dataset.tab;
        if (tab) this.onNavigate(tab);
      });
    });

    await this.fetchRooms();
    await this.fetchGames();
  }

  setupParallaxEngine() {
    const stage = document.getElementById('scrolly-stage');
    const progressBar = document.getElementById('scrolly-progress');
    const s1Sky = document.getElementById('s1-sky');
    const s1Mansion = document.getElementById('s1-mansion');
    const gateLeft = document.getElementById('gate-left');
    const gateRight = document.getElementById('gate-right');
    const s1Content = document.getElementById('s1-content');
    const scrollCue = document.getElementById('scroll-cue');
    const s2Room = document.getElementById('s2-room');
    const s2Copy = document.getElementById('s2-copy');
    const s3Cards = document.getElementById('s3-cards');
    const cardLeft = document.getElementById('card-left');
    const cardCenter = document.getElementById('card-center');
    const cardRight = document.getElementById('card-right');

    const clamp = (val, min, max) => Math.min(max, Math.max(min, val));

    const updateParallax = () => {
      if (!stage) return;
      const rect = stage.getBoundingClientRect();
      const maxScroll = stage.offsetHeight - window.innerHeight;
      const scrolled = clamp(-rect.top, 0, maxScroll);
      const progress = maxScroll > 0 ? scrolled / maxScroll : 0;

      if (progressBar) progressBar.style.width = `${(progress * 100).toFixed(1)}%`;

      // Phân cảnh 1: 0.00 -> 0.35
      const p1 = clamp(progress / 0.35, 0, 1);
      const fadeOut1 = progress > 0.28 ? clamp(1 - (progress - 0.28) / 0.09, 0, 1) : 1;

      if (s1Sky) {
        s1Sky.style.transform = `translate3d(0, ${scrolled * 0.12}px, 0)`;
        s1Sky.style.opacity = fadeOut1;
      }
      if (s1Mansion) {
        s1Mansion.style.transform = `translate3d(0, ${p1 * -45}px, 0) scale(${1 + p1 * 0.18})`;
        s1Mansion.style.opacity = fadeOut1;
      }
      if (gateLeft && gateRight) {
        const gateScale = 1 + p1 * 0.6;
        const gateSpread = p1 * 48;
        gateLeft.style.transform = `translate3d(-${gateSpread}%, ${p1 * 30}px, 0) scale(${gateScale})`;
        gateRight.style.transform = `translate3d(${gateSpread}%, ${p1 * 30}px, 0) scale(${gateScale})`;
        gateLeft.style.opacity = fadeOut1;
        gateRight.style.opacity = fadeOut1;
      }
      if (s1Content) {
        s1Content.style.opacity = fadeOut1;
        s1Content.style.transform = `translate3d(0, ${p1 * -35}px, 0)`;
        s1Content.style.pointerEvents = fadeOut1 > 0.2 ? 'auto' : 'none';
      }
      if (scrollCue) {
        scrollCue.style.opacity = progress > 0.15 ? '0' : '1';
      }

      // Phân cảnh 2: 0.35 -> 0.70
      let op2 = 0;
      if (progress >= 0.30 && progress <= 0.74) {
        if (progress < 0.38) op2 = (progress - 0.30) / 0.08;
        else if (progress > 0.66) op2 = 1 - (progress - 0.66) / 0.08;
        else op2 = 1;
      }
      const p2 = clamp((progress - 0.35) / 0.35, 0, 1);
      if (s2Room) {
        s2Room.style.opacity = clamp(op2, 0, 1);
      }
      if (s2Copy) {
        const slideX = (1 - clamp(p2 * 2.2, 0, 1)) * -40;
        s2Copy.style.transform = `translate3d(${slideX}px, 0, 0)`;
      }

      // Phân cảnh 3: 0.70 -> 1.00
      const p3 = clamp((progress - 0.68) / 0.32, 0, 1);
      if (s3Cards) {
        s3Cards.style.opacity = p3;
        s3Cards.style.pointerEvents = p3 > 0.3 ? 'auto' : 'none';
      }
      if (cardLeft && cardCenter && cardRight) {
        const riseY = (1 - p3) * 85;
        cardLeft.style.transform = `translate3d(${(1 - p3) * 40}px, ${riseY}px, 0) rotate(${-6 * p3}deg)`;
        cardCenter.style.transform = `translate3d(0, ${riseY * 0.7}px, 0) scale(${0.95 + 0.13 * p3})`;
        cardRight.style.transform = `translate3d(${(1 - p3) * -40}px, ${riseY}px, 0) rotate(${6 * p3}deg)`;
      }

      this.ticking = false;
    };

    if (this.scrollHandler) {
      window.removeEventListener('scroll', this.scrollHandler);
    }
    this.scrollHandler = () => {
      if (!this.ticking) {
        requestAnimationFrame(updateParallax);
        this.ticking = true;
      }
    };
    window.addEventListener('scroll', this.scrollHandler, { passive: true });
    updateParallax();
  }

  setupAgeModal() {
    const ageModal = document.getElementById('age-modal');
    document.getElementById('age-cancel-btn')?.addEventListener('click', () => {
      ageModal.classList.remove('open');
      this.pendingAction = null;
    });
    document.getElementById('age-confirm-btn')?.addEventListener('click', () => {
      ageModal.classList.remove('open');
      if (typeof this.pendingAction === 'function') {
        const action = this.pendingAction;
        this.pendingAction = null;
        action();
      } else {
        this.onNavigate('rooms');
      }
    });
  }

  setupPriceModal() {
    const dialog = document.getElementById('home-price-dialog');
    document.getElementById('btn-open-price-modal')?.addEventListener('click', () => dialog.showModal());
    document.getElementById('btn-close-price-dialog')?.addEventListener('click', () => dialog.close());
  }

  async fetchRooms() {
    const grid = document.getElementById('home-rooms-grid');
    if (!grid) return;

    let rooms = [];
    try {
      const res = await api.get('/rooms?status=FORMING');
      rooms = res?.data?.rooms || [];
    } catch {
      rooms = [];
    }

    const curatedFallbacks = [
      { id: '104', title: 'Nhóm Thám Tử Đêm', game_name: 'Yandere: Lời Thề Máu', creator_name: 'Trần Gia Bảo', current_players: 6, max_players: 8 },
      { id: '107', title: 'Cần 2 bạn phá án nhanh', game_name: 'Căn Phòng Số 7', creator_name: 'Nguyễn Minh Khôi', current_players: 5, max_players: 7 },
      { id: '109', title: 'Hội trinh thám cuối tuần', game_name: 'Sinh Tử: Chuyến Tàu Cuối', creator_name: 'Lê Hoàng Phúc', current_players: 7, max_players: 8 },
      { id: '112', title: 'Nhóm cổ trang cung đình', game_name: 'Gương: Phản Chiếu Tội Ác', creator_name: 'Đào Mỹ Duyên', current_players: 4, max_players: 6 }
    ];

    // Combine or pad rooms to ensure 4 cards are displayed
    const finalRooms = [...rooms];
    for (const fb of curatedFallbacks) {
      if (finalRooms.length >= 4) break;
      if (!finalRooms.some(r => String(r.id) === String(fb.id))) {
        finalRooms.push(fb);
      }
    }

    grid.innerHTML = finalRooms.slice(0, 4).map(r => {
      const filled = r.current_players ?? 4;
      const max = r.max_players ?? 8;
      const pct = max > 0 ? Math.round((filled / max) * 100) : 50;
      return `
        <article class="room-card" style="background: var(--bg-surface); border: 1px solid var(--gold-border); border-radius: 8px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span class="badge badge-green">Đang ghép</span>
              <span style="font-family: var(--font-mono); color: var(--gold); font-size: 0.8rem;">#${r.id}</span>
            </div>
            <h3 style="font-family: var(--font-display); font-size: 1.25rem; margin: 4px 0 6px;">${escapeHtml(r.title)}</h3>
            <p class="text-gold" style="font-size: 0.88rem; margin: 0 0 4px 0;">🎭 ${escapeHtml(r.game_name || 'Kịch bản trinh thám')}</p>
            <p class="text-muted" style="font-size: 0.84rem; margin: 0 0 10px 0;">Host: <strong>${escapeHtml(r.creator_name || 'Người chơi')}</strong></p>
          </div>

          <div style="margin-top: 14px; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 12px;">
            <div style="display: flex; justify-content: space-between; font-size: 0.82rem; margin-bottom: 6px;">
              <span>Tiến độ ghế</span>
              <strong class="text-gold">${filled}/${max} chỗ đã cọc</strong>
            </div>
            <div class="progress-track" style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; margin-bottom: 12px;">
              <div class="progress-fill" style="width: ${pct}%; height: 100%; background: linear-gradient(90deg, var(--gold), var(--crimson));"></div>
            </div>
            <button class="btn btn-gold btn-sm btn-join-room" data-room-id="${r.id}" style="width: 100%; text-align: center; color: var(--ink-black);">
              Vào phòng & Giữ chỗ 50k
            </button>
          </div>
        </article>
      `;
    }).join('');

    grid.querySelectorAll('.btn-join-room').forEach(btn => {
      btn.addEventListener('click', () => {
        if (!authState.isAuthenticated()) {
          toast.info('Vui lòng đăng nhập để tham gia giữ chỗ phòng chơi!');
          this.onOpenAuth();
        } else {
          this.onNavigate('rooms');
        }
      });
    });
  }

  async fetchGames() {
    const filterWrap = document.getElementById('home-genre-filters');
    const grid = document.getElementById('home-scripts-grid');
    if (!grid) return;

    let apiGames = [];
    try {
      const res = await api.get('/games');
      apiGames = res?.data?.games || [];
    } catch {
      apiGames = [];
    }

    const scripts = ALL_SCRIPTS;
    const genres = ['Tất cả', 'Tâm lý / Kinh dị', 'Trinh thám / Siêu nhiên', 'Sinh tồn / Đấu trí', 'Cổ điển / Phá án', 'Gia tộc / Tâm lý'];

    if (filterWrap) {
      filterWrap.innerHTML = genres.map(g => `
        <button class="filter-pill ${g === this.selectedGenre ? 'active' : ''}" data-hgenre="${g}">${g}</button>
      `).join('');

      filterWrap.querySelectorAll('[data-hgenre]').forEach(btn => {
        btn.addEventListener('click', () => {
          this.selectedGenre = btn.dataset.hgenre;
          this.fetchGames();
        });
      });
    }

    const filtered = scripts.filter(s => this.selectedGenre === 'Tất cả' || s.genre.includes(this.selectedGenre));

    grid.innerHTML = filtered.slice(0, 6).map(s => `
      <article class="script-card" style="background: var(--bg-surface); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; padding: 22px; display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden;">
        <div class="script-bg-letter" style="position: absolute; right: 12px; top: -10px; font-size: 6rem; font-weight: 900; color: rgba(212,175,55,0.04); pointer-events: none;">
          ${s.title.charAt(0)}
        </div>
        <div>
          <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px;">
            <span class="badge badge-gold">${s.playersMin}–${s.playersMax} người</span>
            <span class="badge badge-dark">${s.difficulty}</span>
            ${s.is18Plus ? '<span class="badge badge-18">18+</span>' : '<span class="badge badge-green">Phổ thông</span>'}
          </div>
          <h3 style="font-family: var(--font-display); font-size: 1.35rem; margin-bottom: 6px;">${escapeHtml(s.title)}</h3>
          <div style="font-size: 0.82rem; color: var(--gold); margin-bottom: 10px;">
            ${escapeHtml(s.genre)} &bull; Thời lượng: ${s.durationHours} giờ
          </div>
          <p class="text-muted" style="font-size: 0.88rem; line-height: 1.5; margin-bottom: 14px;">
            ${escapeHtml(s.summary)}
          </p>
        </div>

        <div style="border-top: 1px solid rgba(255,255,255,0.08); padding-top: 14px; display: flex; align-items: center; justify-content: space-between;">
          <div>
            <span style="font-size: 0.75rem; color: var(--text-dim); display: block;">Giá vé</span>
            <strong style="font-size: 1.15rem; color: var(--gold);">${(s.price).toLocaleString('vi-VN')}đ</strong>
          </div>
          <button class="btn btn-crimson btn-sm" data-play-script="${s.id}" data-is18="${s.is18Plus}" data-title="${escapeHtml(s.title)}">
            Đặt phòng chơi &rarr;
          </button>
        </div>
      </article>
    `).join('');

    grid.querySelectorAll('[data-play-script]').forEach(b => {
      b.addEventListener('click', () => {
        const is18 = b.dataset.is18 === 'true';
        const scriptTitle = b.dataset.title;
        const onConfirm = () => {
          if (!authState.isAuthenticated()) {
            toast.info('Vui lòng đăng nhập để bắt đầu đặt phòng kịch bản này!');
            this.onOpenAuth();
          } else {
            this.onNavigate('rooms');
          }
        };

        if (is18) {
          document.getElementById('age-script-name').textContent = scriptTitle;
          this.pendingAction = onConfirm;
          document.getElementById('age-modal').classList.add('open');
        } else {
          onConfirm();
        }
      });
    });
  }
}
