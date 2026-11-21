/**
 * Public Home Page (#/)
 * Editorial Noir Salon: Asymmetric Grid, Sealed Honor Charter, Chapter Index, Premier Spotlight & Live Case Board.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { BRAND } from '../../content/brand.js';
import { apiService } from '../../api/api-service.js';
import { generateGamePosterSvg } from '../../content/assets.js';
import { renderStatusBadge } from '../../components/status-badge.js';
import { renderCountdown } from '../../components/countdown.js';
import { formatCurrency, formatDateTime } from '../../utils/format.js';
import { initMotion } from '../../utils/motion.js';

export class HomePage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-home' });

    // 1. Cinematic Hero Section (Atmospheric Noir)
    const hero = h('section', { className: 'home-hero' },
      h('div', { className: 'hero-ambient-glow' }),
      h('div', { className: 'hero-ambient-crimson' }),
      h('div', { className: 'hero-content container' },
        h('div', { className: 'hero-editorial-wrap' },
          h('span', { className: 'hero-eyebrow' },
            renderIcon('sparkles'), ' MẬT VỤ SALON • TRẢI NGHIỆM JUBENSHA CAO CẤP'
          ),
          h('h1', { className: 'hero-title' },
            'Vén Màn Bí Ẩn.', h('br'),
            h('span', { className: 'hero-title-accent' }, 'Tìm Ra Kẻ Thủ Ác.')
          ),
          h('p', { className: 'hero-subtitle' },
            'Khi cánh cửa mật thất khép lại, bạn không còn là chính mình. Bước vào không gian Kịch Bản Sát — nơi sự thật bị chôn vùi dưới những mảnh ký ức vụn vỡ, và mỗi người ngồi cùng bàn đều có thể là kẻ sát nhân.'
          ),
          h('div', { className: 'hero-actions' },
            h('a', { className: 'btn btn-primary btn-lg', href: '#/games' },
              'Khám phá 42 Hồ Sơ Án', renderIcon('chevronRight')
            ),
            h('a', { className: 'btn btn-secondary btn-lg hero-btn-secondary', href: '#/rooms' },
              h('span', { className: 'beacon-dot' }),
              'Bảng Lịch Trình Chiêu Mộ'
            )
          ),
          h('div', { className: 'hero-noir-ticker' },
            h('span', {}, renderIcon('sparkles'), ' 42 Kịch Bản Bản Quyền'),
            h('span', { className: 'ticker-sep' }, '•'),
            h('span', { className: 'ticker-badge-gold' }, 'Mật Thất Chuẩn Điện Ảnh'),
            h('span', { className: 'ticker-sep' }, '•'),
            h('span', {}, renderIcon('users'), ' Quản Trò (DM) Dẫn Dắt Riêng'),
            h('span', { className: 'ticker-sep' }, '•'),
            h('span', {}, renderIcon('shield'), ' Hoàn Cọc Minh Bạch 24H')
          )
        )
      )
    );
    container.appendChild(hero);

    // 2. Editorial Brand Manifesto (2-Column Asymmetric Spread)
    const manifesto = h('section', { className: 'editorial-manifesto-section' },
      h('div', { className: 'container' },
        h('div', { className: 'manifesto-grid' },
          h('div', { className: 'manifesto-quote-col' },
            h('p', { className: 'manifesto-quote-text' },
              '“Chữ ‘Sát’ trong Kịch Bản Sát không đơn thuần là án mạng. Đó là thời khắc sự thật trần trụi được phơi bày dưới ánh đèn mờ của mật thất.”'
            ),
            h('span', { className: 'manifesto-quote-author' },
              '— HỘI ĐỒNG QUẢN TRÒ (DM GUILD) • KỊCH BẢN SÁT'
            )
          ),
          h('div', { className: 'manifesto-text-col' },
            h('p', {},
              'Khác biệt hoàn toàn với các tựa boardgame thông thường, ',
              h('strong', { className: 'highlight-gold' }, 'Kịch Bản Sát (Jubensha)'),
              ' đưa bạn vào một vở diễn trinh thám đích thực. Từng tập hồ sơ dày cộm, từng chứng cứ vật lý tinh vi, và sự dẫn dắt tài tình của các Quản trò (DM) chuyên nghiệp sẽ tái hiện lại hiện trường vụ án sống động hơn bao giờ hết.'
            ),
            h('p', {},
              'Mỗi phiên chơi là một hành trình đấu trí cân não kéo dài từ 3 đến 5 giờ. Sự hoài nghi, những lời khai man, và những bí mật thầm kín sẽ dần được bóc tách cho tới khi sự thật cuối cùng phát lộ.'
            ),
            h('div', { className: 'manifesto-pillars' },
              h('div', { className: 'pillar-item' },
                h('h4', {}, 'Mật Thất Độc Lập'),
                h('p', {}, 'Phòng chơi vật lý biệt lập, cách âm tuyệt đối với ánh sáng nghệ thuật điện ảnh.')
              ),
              h('div', { className: 'pillar-item' },
                h('h4', {}, 'Quản Trò Chuyên Biệt'),
                h('p', {}, 'DM có tối thiểu 12 giờ chuẩn bị kịch bản chuyên sâu trước mỗi ca dẫn.')
              )
            )
          )
        )
      )
    );
    container.appendChild(manifesto);

    // 3. Sealed Honor Charter (Quy Ước Danh Dự V16 — Thay thế hoàn toàn card box cũ)
    const charter = h('section', { className: 'home-charter-section' },
      h('div', { className: 'container' },
        h('div', { className: 'sealed-charter' },
          h('div', { className: 'charter-header' },
            h('span', { className: 'hero-eyebrow' }, renderIcon('shield'), ' NGUYÊN TẮC BẢO CHỨNG'),
            h('h2', { className: 'charter-title' }, 'Quy Ước Danh Dự & Bảo Vệ Quyền Lợi'),
            h('p', { className: 'charter-subtitle' }, 'HAI NGUYÊN TẮC VẬN HÀNH TỐI THƯỢNG ĐÃ ĐÓNG BĂNG TRONG V16')
          ),
          h('div', { className: 'charter-grid' },
            ...BRAND.COMMITMENTS.map((c) =>
              h('div', { className: 'charter-item' },
                h('div', { className: 'charter-seal-icon' }, renderIcon('shield')),
                h('div', { className: 'charter-item-body' },
                  h('h3', {}, c.title),
                  h('p', {}, c.desc)
                )
              )
            )
          )
        )
      )
    );
    container.appendChild(charter);

    // 4. Chapter Index (Hành Trình Khai Màn 6 Chương — Thay thế grid card cũ)
    const chapters = h('section', { className: 'home-chapters-section' },
      h('div', { className: 'container' },
        h('div', { className: 'section-header flex-between' },
          h('div', {},
            h('span', { className: 'hero-eyebrow' }, renderIcon('sparkles'), ' QUY TRÌNH THAM GIA'),
            h('h2', { className: 'charter-title' }, 'Hành Trình Khai Màn Vụ Án'),
            h('p', { className: 'text-muted' }, 'Sáu giai đoạn nghiêm ngặt từ lúc đề xuất phòng chơi đến khi giải mã kết án.')
          )
        ),
        h('div', { className: 'chapters-index-list' },
          ...BRAND.HOW_TO_PLAY_STEPS.map((step) =>
            h('div', { className: 'chapter-row' },
              h('div', { className: 'chapter-num' }, `0${step.step}`),
              h('div', { className: 'chapter-title-col' },
                h('h4', {}, step.title),
                h('span', { className: 'chapter-rule-badge' },
                  step.step === 1 ? 'Mốc :00 / :30' :
                  step.step === 2 ? 'Tối đa 3 phòng' :
                  step.step === 3 ? 'Hạn thành lập 4h' :
                  step.step === 4 ? 'DM có ≥12h chuẩn bị' :
                  step.step === 5 ? 'Hạn cọc 2h riêng' : 'Mật thất tại quán'
                )
              ),
              h('div', { className: 'chapter-desc-col' },
                h('p', {}, step.desc)
              ),
              h('div', { className: 'chapter-status-col' },
                h('span', {}, 'GIAI ĐOẠN 0', `${step.step}`)
              )
            )
          )
        )
      )
    );
    container.appendChild(chapters);

    // 5. Premier Scenario Spotlight
    const spotlight = h('section', { className: 'home-spotlight-section' },
      h('div', { className: 'container' },
        h('div', { className: 'section-header' },
          h('span', { className: 'hero-eyebrow' }, renderIcon('sparkles'), ' TÁC PHẨM TÂM ĐIỂM'),
          h('h2', { className: 'charter-title' }, 'Martinez Chết Tại Kinh Kỳ Quán'),
          h('p', { className: 'text-muted' }, 'Kịch bản trinh thám suy lý đỉnh cao được giới phê bình đánh giá 9.8/10.')
        ),
        h('div', { className: 'spotlight-card' },
          h('div', { className: 'spotlight-poster-wrapper' },
            h('img', {
              src: './assets/images/martinez_mountain_inn.jpg',
              alt: 'Bìa hồ sơ án: Martinez Chết Tại Kinh Kỳ Quán',
              className: 'spotlight-poster-img',
              loading: 'lazy',
              onerror: (e) => {
                e.target.style.display = 'none';
                e.target.parentElement.innerHTML = generateGamePosterSvg('Martinez Chết Tại Kinh Kỳ Quán', false, 400, 560);
              }
            }),
            h('div', { className: 'spotlight-poster-overlay' }),
            h('div', { className: 'spotlight-dossier-badge' }, 'HỒ SƠ ÁN #41 • SUY LÝ CỔ ĐIỂN')
          ),
          h('div', { className: 'spotlight-info' },
            h('span', { className: 'spotlight-tag' }, renderIcon('shield'), ' KIỆT TÁC SUY LÝ CỔ ĐIỂN'),
            h('h3', { className: 'spotlight-title' }, 'Martinez Chết Tại Kinh Kỳ Quán'),
            h('p', { className: 'spotlight-synopsis' },
              'Một đêm bão tuyết cô lập toàn bộ quán trọ cổ kính trên đỉnh núi. Khi tiếng chuông nửa đêm vang lên, thi thể của nhà quý tộc Martinez được phát hiện trong mật thất kín mít không một dấu vết đột nhập. Tám vị khách có mặt đều mang theo những động cơ giết người không thể chối bỏ...'
            ),
            h('div', { className: 'spotlight-specs' },
              h('div', { className: 'spec-item' },
                h('span', { className: 'spec-label' }, 'Số thám tử'),
                h('span', { className: 'spec-val' }, '6 - 8 người')
              ),
              h('div', { className: 'spec-item' },
                h('span', { className: 'spec-label' }, 'Thời lượng'),
                h('span', { className: 'spec-val' }, '4.5 giờ')
              ),
              h('div', { className: 'spec-item' },
                h('span', { className: 'spec-label' }, 'Độ khó'),
                h('span', { className: 'spec-val text-amber' }, '★★★★★ (Khó)')
              ),
              h('div', { className: 'spec-item' },
                h('span', { className: 'spec-label' }, 'Giá cơ sở'),
                h('span', { className: 'spec-val text-gold font-mono' }, '189.000 VNĐ')
              )
            ),
            h('div', { className: 'spotlight-actions' },
              h('a', { className: 'btn btn-primary btn-lg', href: '#/player/create-room?game_id=game_041' },
                'Tạo Phòng Chơi Ngay', renderIcon('chevronRight')
              ),
              h('a', { className: 'btn btn-secondary btn-lg hero-btn-secondary', href: '#/games/game_041' },
                'Xem Hồ Sơ Chi Tiết'
              )
            )
          )
        )
      )
    );
    container.appendChild(spotlight);

    // 6. Live Case Departure Board (Bảng Điều Phối Lịch Trình Mở)
    const liveSection = h('section', { className: 'home-live-section' },
      h('div', { className: 'container' },
        h('div', { className: 'section-header flex-between' },
          h('div', {},
            h('span', { className: 'hero-eyebrow' }, renderIcon('clock'), ' BẢNG CHIÊU MỘ TRỰC TUYẾN'),
            h('h2', { className: 'charter-title' }, 'Phòng Chơi Đang Tuyển Thám Tử'),
            h('p', { className: 'text-muted' }, 'Các phòng đang trong thời hạn 4 giờ thành lập, cần ghép thêm ghế trống.')
          ),
          h('a', { className: 'btn btn-sm btn-secondary', href: '#/rooms' }, 'Xem Tất Cả Phòng →')
        ),
        h('div', { id: 'home-live-rooms-feed' },
          h('div', { className: 'loading-pulse' }, 'Đang nạp dữ liệu điều phối phòng chơi...')
        )
      )
    );
    container.appendChild(liveSection);

    // 7. Editorial Colophon Footer
    const footer = h('footer', { className: 'app-footer' },
      h('div', { className: 'container footer-inner' },
        h('div', { className: 'footer-main-grid' },
          h('div', { className: 'footer-brand-col' },
            h('div', { className: 'footer-brand-header' },
              h('span', { className: 'footer-brand-seal' }, 'KS'),
              h('div', {},
                h('h3', { className: 'brand-name' }, BRAND.name),
                h('span', { className: 'footer-brand-sub' }, 'JUBENSHA SALON PRIVÉ')
              )
            ),
            h('p', { className: 'footer-desc' },
              'Nền tảng đặt lịch chơi kịch bản sát nhập vai giải đố cao cấp đầu tiên tại Việt Nam với hệ thống mật thất độc lập và quy trình quản trò V16 chuẩn mực.'
            ),
            h('div', { className: 'footer-specs-badge' },
              renderIcon('shield'), ' PHIÊN BẢN V16 • TẤT CẢ QUY TRÌNH ĐÃ ĐÓNG BĂNG'
            )
          ),
          h('div', { className: 'footer-nav-col' },
            h('h4', { className: 'footer-col-title' }, 'Khám Phá'),
            h('ul', { className: 'footer-links' },
              h('li', {}, h('a', { href: '#/games' }, '42 Hồ Sơ Kịch Bản')),
              h('li', {}, h('a', { href: '#/rooms' }, 'Phòng Chơi Đang Tuyển')),
              h('li', {}, h('a', { href: '#/games/game_041' }, 'Tác Phẩm Tâm Điểm')),
              h('li', {}, h('a', { href: '#/player/create-room' }, 'Khởi Tạo Vụ Án Mới'))
            )
          ),
          h('div', { className: 'footer-nav-col' },
            h('h4', { className: 'footer-col-title' }, 'Phân Hệ Portal'),
            h('ul', { className: 'footer-links' },
              h('li', {}, h('a', { href: '#/player/dashboard' }, 'Player Portal (Thám tử)')),
              h('li', {}, h('a', { href: '#/dm/dashboard' }, 'DM Portal (Quản trò)')),
              h('li', {}, h('a', { href: '#/admin/dashboard' }, 'Admin Ops Center (Điều hành)')),
              h('li', {}, h('a', { href: '#/player/history' }, 'Lịch Sử & Hoàn Cọc'))
            )
          ),
          h('div', { className: 'footer-nav-col' },
            h('h4', { className: 'footer-col-title' }, 'Cam Kết V16'),
            h('ul', { className: 'footer-links' },
              h('li', {}, h('span', { className: 'footer-link-text' }, 'Cọc 50.000đ/người cố định')),
              h('li', {}, h('span', { className: 'footer-link-text' }, 'Hoàn tiền khi hủy ≥24h')),
              h('li', {}, h('span', { className: 'footer-link-text' }, 'DM chuẩn bị tối thiểu 12h')),
              h('li', {}, h('span', { className: 'footer-link-text' }, 'Hạn thành lập phòng 4h'))
            )
          )
        ),
        h('div', { className: 'footer-bottom-bar' },
          h('span', { className: 'footer-copyright' },
            '© 2026 Kịch Bản Sát • Dark Cinematic Luxury Jubensha Platform. Toàn quyền bảo lưu.'
          ),
          h('div', { className: 'footer-bottom-meta' },
            h('span', {}, 'MẬT THẤT BIỆT LẬP'),
            h('span', { className: 'meta-sep' }, '•'),
            h('span', {}, 'HÀ NỘI — SÀI GÒN'),
            h('span', { className: 'meta-sep' }, '•'),
            h('span', {}, 'STANDALONE ES MODULES')
          )
        )
      )
    );
    container.appendChild(footer);

    this.mountPoint.appendChild(container);

    // Populate live forming rooms as Departure Board Table
    await this.loadFeaturedRooms();

    // Initialize GSAP & Scroll-Craft animations (Respects prefers-reduced-motion)
    requestAnimationFrame(() => initMotion());
  }

  async loadFeaturedRooms() {
    const feed = this.mountPoint.querySelector('#home-live-rooms-feed');
    if (!feed) return;

    try {
      const res = await apiService.getPublicRooms({ status: 'FORMING' });
      feed.innerHTML = '';

      const rooms = res.data.slice(0, 4);
      if (rooms.length === 0) {
        feed.appendChild(
          h('p', { className: 'text-muted text-center', style: 'padding: 2rem;' },
            'Hiện chưa có phòng nào đang mở tuyển. Hãy mở phòng đầu tiên để khai màn vụ án!'
          )
        );
        return;
      }

      const tableWrapper = h('div', { className: 'table-scroll-wrapper' });
      const table = h('table', { className: 'case-board-table' },
        h('thead', {},
          h('tr', {},
            h('th', {}, 'Mã Vụ Án'),
            h('th', {}, 'Kịch Bản Nhập Vai'),
            h('th', {}, 'Giờ Bắt Đầu'),
            h('th', {}, 'Thám Tử Đã Nhận'),
            h('th', {}, 'Hạn Thành Lập (4H)'),
            h('th', {}, 'Tình Trạng'),
            h('th', { style: 'text-align: right;' }, 'Thao Tác')
          )
        )
      );

      const tbody = h('tbody', {});
      for (const room of rooms) {
        const game = await apiService.getGameById(room.game_id);
        const row = h('tr', {},
          h('td', { className: 'font-mono text-gold' }, room.code),
          h('td', {},
            h('div', { className: 'case-game-title' }, game.data.title),
            h('small', { className: 'text-muted' }, `${game.data.genre || 'Trinh thám'} • ${game.data.duration_minutes || 240} phút`)
          ),
          h('td', { className: 'case-time-pill' }, formatDateTime(room.session_start_time)),
          h('td', {},
            h('span', { className: 'case-seats-pill' },
              h('strong', {}, `${room.participants.length}`),
              h('span', { className: 'text-muted' }, ` / ${game.data.max_players} thám tử`)
            )
          ),
          h('td', {},
            room.formation_deadline
              ? renderCountdown(room.formation_deadline, { label: 'Còn' })
              : h('span', { className: 'text-muted' }, 'Đã đủ')
          ),
          h('td', {}, renderStatusBadge(room.status)),
          h('td', { style: 'text-align: right;' },
            h('a', { className: 'btn btn-sm btn-primary', href: `#/rooms/${room.id}` }, 'Vào Ghế')
          )
        );
        tbody.appendChild(row);
      }

      table.appendChild(tbody);
      tableWrapper.appendChild(table);
      feed.appendChild(tableWrapper);
    } catch (err) {
      feed.innerHTML = `<p class="text-danger">Không thể tải danh sách điều phối: ${err.message}</p>`;
    }
  }
}
