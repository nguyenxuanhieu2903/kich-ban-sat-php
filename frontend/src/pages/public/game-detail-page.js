/**
 * Game Detail Page (#/games/:id)
 * Film Noir Case Dossier: Asymmetric Layout, Atmospheric Synopsis, 18+ Warning, 2-Tier Pricing Ledger, and Create Room CTA.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { generateGamePosterSvg, renderGameCoverElement } from '../../content/assets.js';
import { formatCurrency, formatDateTime } from '../../utils/format.js';
import { renderStatusBadge } from '../../components/status-badge.js';

export class GameDetailPage {
  constructor(mountPoint, { params, router }) {
    this.mountPoint = mountPoint;
    this.gameId = params.id;
    this.router = router;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-game-detail container' });

    try {
      const res = await apiService.getGameById(this.gameId);
      const game = res.data;

      // 1. Breadcrumb Navigation
      container.appendChild(
        h('div', { className: 'dossier-back-container' },
          h('a', { className: 'dossier-back-link', href: '#/games' },
            renderIcon('chevronLeft'), ' Quay lại Danh mục 42 Hồ Sơ'
          )
        )
      );

      // 2. 18+ Explicit Warning (if applicable, e.g. #16, #17, #18)
      if (game.is_age_restricted) {
        container.appendChild(
          h('div', { className: 'age-warning-banner' },
            renderIcon('shield'),
            h('span', {},
              h('strong', {}, 'CẢNH BÁO GIỚI HẠN ĐỘ TUỔI 18+:'),
              ' Hồ sơ vụ án này chứa các tình tiết kinh dị tâm lý nặng, yếu tố giật gân hoặc máu me. Người chơi bắt buộc xuất trình CCCD xác nhận đủ 18 tuổi trước khi nhận kịch bản tại quán.'
            )
          )
        );
      }

      // 3. Main Dossier Layout (Asymmetric 1.4fr : 1fr)
      const dossierGrid = h('div', { className: 'dossier-grid' },
        // Left Column: Story, Atmosphere, Specs, and Related Rooms
        h('div', { className: 'dossier-main' },
          h('div', { className: 'game-tags-row', style: 'margin-bottom: 0.75rem;' },
            h('span', { className: 'game-genre-tag' }, game.genre || 'Trinh thám'),
            game.is_age_restricted ? h('span', { className: 'game-18-badge' }, '18+ Phân loại gắt gao') : null,
            !game.verified ? h('span', { className: 'badge-unverified' }, 'Thông số minh họa (Chờ đối chiếu menu)') : null
          ),
          h('h1', { className: 'dossier-title' }, game.title),
          h('p', { className: 'text-muted font-mono', style: 'font-size: 0.85rem; margin-bottom: 2rem;' },
            `MÃ HỒ SƠ: JBS-V16-${String(game.id).padStart(3, '0')} • THỂ LOẠI: ${(game.genre || 'Trinh thám').toUpperCase()}`
          ),

          // Story Synopsis Section
          h('div', { className: 'dossier-section' },
            h('h3', { className: 'dossier-section-title' }, 'Bối Cảnh Hiện Trường & Giới Thiệu Vụ Án'),
            h('p', { className: 'dossier-synopsis' },
              game.description ||
              'Một biến cố đẫm máu bất ngờ xảy ra tại địa điểm biệt lập. Những manh mối để lại đều bị xáo trộn bởi thủ phạm giấu mặt. Mỗi nhân vật tham gia đều mang trong mình những góc khuất tăm tối, động cơ bất minh và lời khai mâu thuẫn. Nhiệm vụ của bàn chơi là kết nối các chứng cứ vật lý, đối chất trực diện và lột trần chân tướng sự thật trước khi thời gian khép lại.'
            )
          ),

          // Core Specifications Grid
          h('div', { className: 'dossier-section' },
            h('h3', { className: 'dossier-section-title' }, 'Quy Cách Tổ Chức & Điều Kiện Tham Gia'),
            h('div', { className: 'spotlight-specs', style: 'border: 1px solid var(--color-noir-divider); padding: 1.5rem; background: var(--color-bg-surface-1);' },
              h('div', { className: 'spec-item' },
                h('span', { className: 'spec-label' }, 'Số lượng thám tử'),
                h('span', { className: 'spec-val text-gold' }, `${game.min_players} – ${game.max_players} người`)
              ),
              h('div', { className: 'spec-item' },
                h('span', { className: 'spec-label' }, 'Thời lượng phiên'),
                h('span', { className: 'spec-val' }, `${game.duration_minutes || 240} phút (~${((game.duration_minutes || 240) / 60).toFixed(1)} tiếng)`)
              ),
              h('div', { className: 'spec-item' },
                h('span', { className: 'spec-label' }, 'Độ khó suy luận'),
                h('span', { className: 'spec-val text-amber' }, '★'.repeat(game.difficulty || 4) + '☆'.repeat(5 - (game.difficulty || 4)))
              ),
              h('div', { className: 'spec-item' },
                h('span', { className: 'spec-label' }, 'Quản trò (DM)'),
                h('span', { className: 'spec-val' }, 'Phân công riêng')
              )
            )
          ),

          // Salon Immersion Experience Standards
          h('div', { className: 'dossier-section' },
            h('h3', { className: 'dossier-section-title' }, 'Tiêu Chuẩn Không Gian Mật Thất Tại Quán'),
            h('ul', { style: 'color: var(--color-text-secondary); line-height: 1.8; padding-left: 1.25rem;' },
              h('li', {}, h('strong', {}, 'Phòng chơi vật lý biệt lập:'), ' Cách âm hoàn toàn, trang bị hệ thống âm thanh, ánh sáng theo mood kịch bản.'),
              h('li', {}, h('strong', {}, 'Trang phục & Đạo cụ:'), ' Cung cấp y phục nhân vật độc quyền cùng bộ hồ sơ chứng cứ hiện trường in màu sắc nét.'),
              h('li', {}, h('strong', {}, 'Quản trò (DM) chuyên trách:'), ' Được phân công trước tối thiểu 12 giờ để nghiên cứu kịch bản và dẫn dắt mạch lạc.')
            )
          ),

          // Related Active Public Rooms for this Game
          h('div', { className: 'dossier-section', id: 'related-rooms-container' },
            h('h3', { className: 'dossier-section-title' }, 'Phòng Đang Chiêu Mộ Cho Kịch Bản Này'),
            h('div', { className: 'loading-pulse' }, 'Đang tìm kiếm phòng đang mở...')
          )
        ),

        // Right Column: Sticky Noir Dossier Sidebar (Pricing, Promos & CTA)
        h('div', { className: 'dossier-sidebar' },
          h('div', { className: 'dossier-sidebar-card' },
            h('div', {
              style: { textAlign: 'center', marginBottom: '1.5rem', overflow: 'hidden', clipPath: 'polygon(0 0, calc(100% - 12px) 0, 100% 12px, 100% 100%, 12px 100%, 0 calc(100% - 12px))' },
            }, renderGameCoverElement(game, { width: 400, height: 280 })),
            h('h3', { style: 'font-size: 1.3rem; margin-bottom: 0.5rem; text-align: center;' }, 'Bảng Giá & Ký Thác'),
            h('p', { className: 'text-muted text-center', style: 'font-size: 0.85rem; margin-bottom: 1.25rem;' },
              'Giá vé trọn gói đã gồm phòng vật lý, kịch bản độc quyền, trang phục và Quản trò (DM).'
            ),

            // 2-Tier Pricing Table
            h('table', { className: 'price-ledger-table' },
              h('tbody', {},
                h('tr', {},
                  h('td', {}, 'Khung ngày (09:00–19:59):'),
                  h('td', {}, formatCurrency(game.price_day_vnd))
                ),
                h('tr', {},
                  h('td', {}, 'Khung tối (từ 20:00):'),
                  h('td', { className: 'highlight-gold' }, formatCurrency(game.price_night_vnd))
                ),
                h('tr', {},
                  h('td', {}, 'Tiền cọc giữ chỗ (Cố định):'),
                  h('td', { className: 'text-emerald' }, '50.000 VNĐ / người')
                )
              )
            ),

            // Promotion Box
            h('div', { className: 'promo-rules-box' },
              h('strong', { className: 'text-gold' }, 'ƯU ĐÃI ÁP DỤNG (KHÔNG CỘNG DỒN):'),
              h('br'),
              '• ', h('strong', {}, 'Thứ 3 Vui Vẻ:'), ' Giảm 30% khi đặt trước ít nhất 1 ngày (09:00–19:00).',
              h('br'),
              '• ', h('strong', {}, 'Học Sinh - Sinh Viên:'), ' Giảm 20.000đ/người từ Thứ 2 đến Thứ 5 (09:00–19:00).'
            ),

            // CTA Button
            h('div', { style: 'margin-top: 1.5rem;' },
              h('a', { className: 'btn btn-primary btn-block btn-lg', href: `#/player/create-room?game_id=${game.id}` },
                renderIcon('plus'), ' Mở Phòng Kịch Bản Này Ngay'
              )
            ),
            h('p', { className: 'text-muted text-center', style: 'font-size: 0.75rem; margin-top: 0.75rem;' },
              'Hạn thành lập phòng 4 giờ. Cọc 50.000đ sau khi DM nhận ca & chốt phòng.'
            )
          )
        )
      );

      container.appendChild(dossierGrid);
      this.mountPoint.appendChild(container);

      // Load related active rooms
      this.loadRelatedRooms(game);
    } catch (err) {
      container.appendChild(
        h('div', { className: 'error-state text-center' },
          h('p', { className: 'text-danger' }, err.message),
          h('a', { className: 'btn btn-secondary', href: '#/games' }, 'Quay lại danh mục kịch bản')
        )
      );
      this.mountPoint.appendChild(container);
    }
  }

  async loadRelatedRooms(game) {
    const box = this.mountPoint.querySelector('#related-rooms-container');
    if (!box) return;

    try {
      const res = await apiService.getPublicRooms({ gameId: game.id });
      const rooms = res.data;
      box.innerHTML = '';
      box.appendChild(h('h3', { className: 'dossier-section-title' }, `Phòng Đang Chiêu Mộ Cho Kịch Bản Này (${rooms.length})`));

      if (rooms.length === 0) {
        box.appendChild(
          h('p', { className: 'text-muted', style: 'padding: 1rem 0;' },
            'Hiện chưa có phòng nào mở cho kịch bản này. Hãy là người đầu tiên khai mở phòng!'
          )
        );
        return;
      }

      const list = h('div', { style: 'display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem;' });
      rooms.forEach((r) => {
        const item = h('div', {
          style: 'display: flex; justify-content: space-between; align-items: center; background: var(--color-bg-surface-1); border: 1px solid var(--color-border-subtle); padding: 1rem 1.25rem; border-radius: var(--radius-xs);'
        },
          h('div', {},
            h('div', { style: 'display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.25rem;' },
              h('span', { className: 'font-mono text-gold' }, r.code),
              renderStatusBadge(r.status)
            ),
            h('small', { className: 'text-muted' },
              `Bắt đầu: ${formatDateTime(r.session_start_time)} • Đã có ${r.participants.length}/${game.max_players} thám tử`
            )
          ),
          h('a', { className: 'btn btn-sm btn-secondary', href: `#/rooms/${r.id}` }, 'Vào Ghế')
        );
        list.appendChild(item);
      });
      box.appendChild(list);
    } catch {
      box.innerHTML = '<p class="text-muted">Không thể tải danh sách phòng liên quan.</p>';
    }
  }
}
