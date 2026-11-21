/**
 * Error Pages: 403 Forbidden & 404 Not Found
 */
import { h, renderIcon } from '../../utils/dom.js';

export class ForbiddenPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-error container text-center card-dark p-5 mt-5' },
      h('div', { className: 'error-icon text-amber mb-3' }, renderIcon('shield', 'large-icon')),
      h('h1', { className: 'error-title' }, '403 — Không Đủ Quyền Truy Cập'),
      h('p', { className: 'text-muted mb-4' },
        'Tài khoản của bạn không có quyền truy cập vào phân vùng chức năng này. Vui lòng chuyển đổi vai trò phù hợp ở thanh Demo Banner phía trên.'
      ),
      h('a', { className: 'btn btn-primary', href: '#/' }, 'Trở Về Trang Chủ')
    );
    this.mountPoint.appendChild(container);
  }
}

export class NotFoundPage {
  constructor(mountPoint, { router }) {
    this.mountPoint = mountPoint;
    this.router = router;
  }

  render() {
    this.mountPoint.innerHTML = '';
    const container = h('div', { className: 'page-error container text-center card-dark p-5 mt-5' },
      h('div', { className: 'error-icon text-danger mb-3' }, renderIcon('alertTriangle', 'large-icon')),
      h('h1', { className: 'error-title' }, '404 — Không Tìm Thấy Trang'),
      h('p', { className: 'text-muted mb-4' },
        'Đường dẫn bạn yêu cầu không tồn tại hoặc đã được dời sang vị trí khác.'
      ),
      h('a', { className: 'btn btn-primary', href: '#/' }, 'Trở Về Trang Chủ')
    );
    this.mountPoint.appendChild(container);
  }
}
