/**
 * Universal Quick Guide Component (Cẩm Nang Dẫn Dắt 3 Bước)
 * Progressive disclosure component to guide users through the workflow, reducing cognitive load.
 */
import { h, renderIcon } from '../utils/dom.js';

export function renderQuickGuide({ role, title = 'Cẩm Nang Bắt Đầu Nhanh 3 Bước', steps = [] }) {
  const storageKey = `ks_guide_collapsed_${role}`;
  let isCollapsed = localStorage.getItem(storageKey) === 'true';

  const container = h('div', { className: `quick-guide-container ${isCollapsed ? 'is-collapsed' : ''}` });

  const renderContent = () => {
    container.innerHTML = '';

    const header = h('div', { className: 'quick-guide-header' },
      h('div', { className: 'quick-guide-title-wrap' },
        h('span', { className: 'quick-guide-bulb' }, renderIcon('sparkles')),
        h('h3', { className: 'quick-guide-title' }, title),
        h('span', { className: 'quick-guide-badge' }, 'Dành cho bạn')
      ),
      h('button', {
        className: 'quick-guide-toggle-btn',
        title: isCollapsed ? 'Mở rộng hướng dẫn' : 'Thu gọn hướng dẫn',
        onclick: () => {
          isCollapsed = !isCollapsed;
          localStorage.setItem(storageKey, String(isCollapsed));
          container.classList.toggle('is-collapsed', isCollapsed);
          renderContent();
        }
      },
        isCollapsed ? renderIcon('chevronDown') : renderIcon('chevronUp'),
        h('span', {}, isCollapsed ? 'Hiện hướng dẫn' : 'Thu gọn')
      )
    );
    container.appendChild(header);

    if (!isCollapsed) {
      const stepGrid = h('div', { className: 'quick-steps-grid' },
        ...steps.map((s, idx) => {
          return h('div', { className: `quick-step-card ${s.isHighlight ? 'step-highlight' : ''}` },
            h('div', { className: 'step-card-top' },
              h('span', { className: 'step-badge-num' }, `0${idx + 1}`),
              s.icon ? h('span', { className: 'step-icon' }, renderIcon(s.icon)) : null
            ),
            h('h4', { className: 'step-title' }, s.title),
            h('p', { className: 'step-desc text-muted' }, s.desc),
            s.action ? h('a', {
              className: `btn ${s.action.primary ? 'btn-primary' : 'btn-secondary'} btn-sm step-action-btn`,
              href: s.action.href
            }, s.action.label) : null
          );
        })
      );
      container.appendChild(stepGrid);
    }
  };

  renderContent();
  return container;
}
