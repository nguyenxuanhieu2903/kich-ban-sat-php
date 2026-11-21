/**
 * DM Availability Calendar Page (#/dm/availability)
 * Availability Matrix — Dark Cinematic Luxury
 * Interactive 7-day grid, quick presets, dirty-state indicator, and genuine persistence.
 */
import { h, renderIcon } from '../../utils/dom.js';
import { apiService } from '../../api/api-service.js';
import { auth, ROLES } from '../../utils/auth.js';
import { showToast } from '../../components/toast.js';

export class DmAvailabilityPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
    this.schedule = {};
    this.savedSnapshot = '';
    this.isDirty = false;
    this.isSaving = false;
  }

  async render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-dm-availability container' });

    const user = auth.getUser();
    if (!user || user.role !== ROLES.DM) {
      this.router.navigate('/403');
      return;
    }

    // 1. Hero Header
    const header = h('div', { className: 'dm-command-hero flex-between', style: 'flex-wrap: wrap; gap: 1.5rem;' },
      h('div', {},
        h('span', { className: 'dossier-badge-pill' },
          renderIcon('calendar'), ' MA TRẬN KHẢ DỤNG • LỊCH TRỰC TUẦN'
        ),
        h('h1', { className: 'dm-command-title' }, 'Đăng Ký Khung Giờ Rảnh'),
        h('p', { className: 'text-muted' },
          'Ban Quản Trị sẽ dựa trên ma trận lịch rảnh thực tế của bạn để điều phối các phòng chơi Jubensha phù hợp.'
        )
      ),
      h('div', { style: 'display: flex; gap: 0.75rem; align-items: center;' },
        h('a', { className: 'btn btn-secondary', href: '#/dm/dashboard' },
          renderIcon('chevronLeft'), ' Về Bàn Làm Việc'
        )
      )
    );
    container.appendChild(header);

    // 2. Loading / Matrix Mount Point
    this.matrixWrapper = h('div', { className: 'matrix-board-container' },
      h('div', { className: 'loading-pulse', style: 'padding: 2rem;' }, 'Đang tải ma trận lịch rảnh từ hệ thống...')
    );
    container.appendChild(this.matrixWrapper);

    this.mountPoint.appendChild(container);

    await this.loadAvailability();
  }

  async loadAvailability() {
    const user = auth.getUser();
    try {
      const res = await apiService.getDmAvailability(user.id);
      this.schedule = res.data || {};
      this.savedSnapshot = JSON.stringify(this.schedule);
      this.isDirty = false;
      this.renderMatrixContent();
    } catch (err) {
      this.matrixWrapper.innerHTML = '';
      this.matrixWrapper.appendChild(
        h('div', { className: 'error-state text-danger p-4' }, err.message)
      );
    }
  }

  checkDirty() {
    this.isDirty = JSON.stringify(this.schedule) !== this.savedSnapshot;
  }

  renderMatrixContent() {
    this.matrixWrapper.innerHTML = '';

    const days = [
      { key: 1, name: 'Thứ Hai', short: 'T2' },
      { key: 2, name: 'Thứ Ba', short: 'T3' },
      { key: 3, name: 'Thứ Tư', short: 'T4' },
      { key: 4, name: 'Thứ Năm', short: 'T5' },
      { key: 5, name: 'Thứ Sáu', short: 'T6' },
      { key: 6, name: 'Thứ Bảy', short: 'T7' },
      { key: 0, name: 'Chủ Nhật', short: 'CN' },
    ];

    const shifts = [
      { key: 'morning', name: 'Ca Sáng', hours: '09:00 – 13:00' },
      { key: 'afternoon', name: 'Ca Chiều', hours: '14:00 – 18:00' },
      { key: 'night', name: 'Ca Tối', hours: '19:00 – 23:00' },
    ];

    // Presets Row & Dirty Indicator
    const topBar = h('div', { className: 'matrix-presets-row flex-between', style: 'align-items: center;' },
      h('div', { style: 'display: flex; gap: 0.5rem; flex-wrap: wrap;' },
        h('span', { className: 'text-muted mr-2', style: 'font-size: 0.85rem; display: flex; align-items: center;' },
          renderIcon('sparkles'), ' Mẫu ca nhanh:'
        ),
        h('button', {
          className: 'btn btn-xs btn-secondary',
          onclick: () => this.applyPreset('ALL_NIGHT'),
        }, 'Tất cả ca tối (19h–23h)'),
        h('button', {
          className: 'btn btn-xs btn-secondary',
          onclick: () => this.applyPreset('WEEKEND_ALL'),
        }, 'Trọn ngày cuối tuần (T7–CN)'),
        h('button', {
          className: 'btn btn-xs btn-secondary',
          onclick: () => this.applyPreset('ALL_SLOTS'),
        }, 'Sẵn sàng toàn bộ tuần'),
        h('button', {
          className: 'btn btn-xs btn-secondary text-danger',
          onclick: () => this.applyPreset('CLEAR_ALL'),
        }, 'Xóa trắng lịch')
      ),
      h('div', {},
        this.isDirty
          ? h('span', { className: 'dirty-indicator-pill is-dirty' },
              renderIcon('alertTriangle'), ' Có thay đổi chưa lưu'
            )
          : h('span', { className: 'dirty-indicator-pill is-clean' },
              renderIcon('check'), ' Đã đồng bộ với hệ thống'
            )
      )
    );
    this.matrixWrapper.appendChild(topBar);

    // 7-Column Matrix Board
    const grid7 = h('div', { className: 'matrix-grid-7days' });

    days.forEach((day) => {
      const dayData = this.schedule[day.key] || { morning: false, afternoon: false, night: false };

      const col = h('div', { className: 'matrix-day-col' },
        h('div', { className: 'matrix-day-header' },
          `${day.short} • ${day.name}`
        )
      );

      shifts.forEach((shift) => {
        const isAvailable = Boolean(dayData[shift.key]);

        const cell = h('div', {
          className: `shift-cell-toggle ${isAvailable ? 'is-available' : ''}`,
          onclick: () => {
            dayData[shift.key] = !dayData[shift.key];
            this.checkDirty();
            this.renderMatrixContent();
          },
        },
          h('span', { className: 'shift-cell-name' },
            isAvailable ? `✓ ${shift.name}` : shift.name
          ),
          h('span', { className: 'shift-cell-hours' }, shift.hours)
        );
        col.appendChild(cell);
      });

      grid7.appendChild(col);
    });

    this.matrixWrapper.appendChild(grid7);

    // Bottom Action Bar
    const actionBar = h('div', { className: 'flex-between mt-4', style: 'padding-top: 1.5rem; border-top: 1px solid var(--color-noir-divider); align-items: center; flex-wrap: wrap; gap: 1rem;' },
      h('div', { className: 'text-muted', style: 'font-size: 0.85rem;' },
        renderIcon('shield'), ' Lịch rảnh được lưu trữ vào hệ thống và có ghi nhận Audit Log theo từng lần cập nhật.'
      ),
      h('div', { style: 'display: flex; gap: 0.75rem;' },
        this.isDirty
          ? h('button', {
              className: 'btn btn-secondary',
              onclick: () => this.loadAvailability(),
            }, 'Hủy thay đổi')
          : null,
        h('button', {
          className: `btn btn-primary ${this.isSaving ? 'btn-loading' : ''}`,
          disabled: !this.isDirty || this.isSaving,
          onclick: () => this.handleSave(),
        }, renderIcon('check'), this.isSaving ? ' Đang lưu...' : ' Lưu Cấu Hình Lịch Rảnh')
      )
    );
    this.matrixWrapper.appendChild(actionBar);
  }

  applyPreset(presetType) {
    for (let day = 0; day <= 6; day++) {
      this.schedule[day] = this.schedule[day] || { morning: false, afternoon: false, night: false };
      if (presetType === 'ALL_NIGHT') {
        this.schedule[day].night = true;
      } else if (presetType === 'WEEKEND_ALL') {
        if (day === 0 || day === 6) {
          this.schedule[day].morning = true;
          this.schedule[day].afternoon = true;
          this.schedule[day].night = true;
        }
      } else if (presetType === 'ALL_SLOTS') {
        this.schedule[day].morning = true;
        this.schedule[day].afternoon = true;
        this.schedule[day].night = true;
      } else if (presetType === 'CLEAR_ALL') {
        this.schedule[day].morning = false;
        this.schedule[day].afternoon = false;
        this.schedule[day].night = false;
      }
    }
    this.checkDirty();
    this.renderMatrixContent();
  }

  async handleSave() {
    const user = auth.getUser();
    this.isSaving = true;
    this.renderMatrixContent();
    try {
      await apiService.saveDmAvailability(this.schedule, user.id);
      this.savedSnapshot = JSON.stringify(this.schedule);
      this.isDirty = false;
      showToast('Đã lưu cấu hình ma trận lịch rảnh thành công!', 'success');
    } catch (err) {
      showToast(err.message, 'error');
    } finally {
      this.isSaving = false;
      this.renderMatrixContent();
    }
  }
}
