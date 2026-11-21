/**
 * Accessible Data Table Component with Client-side Search, Filtering, Sorting, and Pagination
 */
import { h, renderIcon, escapeHtml } from '../utils/dom.js';

export class DataTable {
  constructor({
    columns = [], // [{ key, label, sortable, render }]
    data = [],
    pageSize = 10,
    searchPlaceholder = 'Tìm kiếm dữ liệu...',
    emptyMessage = 'Không tìm thấy dữ liệu phù hợp.',
    onRowClick = null,
  }) {
    this.columns = columns;
    this.rawData = [...data];
    this.filteredData = [...data];
    this.pageSize = pageSize;
    this.currentPage = 1;
    this.searchQuery = '';
    this.sortKey = null;
    this.sortAsc = true;
    this.searchPlaceholder = searchPlaceholder;
    this.emptyMessage = emptyMessage;
    this.onRowClick = onRowClick;
    this.container = null;
  }

  updateData(newData) {
    this.rawData = [...newData];
    this.applyFilters();
    this.renderBody();
  }

  applyFilters() {
    let result = [...this.rawData];

    // Search query filter across all fields
    if (this.searchQuery.trim()) {
      const q = this.searchQuery.toLowerCase().trim();
      result = result.filter((item) =>
        Object.values(item).some((val) => String(val || '').toLowerCase().includes(q))
      );
    }

    // Sorting
    if (this.sortKey) {
      result.sort((a, b) => {
        const valA = a[this.sortKey];
        const valB = b[this.sortKey];
        if (valA < valB) return this.sortAsc ? -1 : 1;
        if (valA > valB) return this.sortAsc ? 1 : -1;
        return 0;
      });
    }

    this.filteredData = result;
    this.currentPage = 1;
  }

  render() {
    this.container = h('div', { className: 'data-table-container' });

    // Top Controls (Search & Page Size)
    const searchInput = h('input', {
      type: 'text',
      className: 'table-search-input form-control',
      placeholder: this.searchPlaceholder,
      oninput: (e) => {
        this.searchQuery = e.target.value;
        this.applyFilters();
        this.renderBody();
      },
    });

    const topBar = h('div', { className: 'data-table-topbar' },
      h('div', { className: 'search-box' },
        renderIcon('search', 'search-icon'),
        searchInput
      ),
      h('div', { className: 'table-meta text-muted' },
        h('span', { id: 'table-count-label' }, `Tổng: ${this.filteredData.length} mục`)
      )
    );

    this.container.appendChild(topBar);

    // Table Element
    this.tableEl = h('div', { className: 'table-scroll-wrapper' });
    this.renderBody();
    this.container.appendChild(this.tableEl);

    return this.container;
  }

  renderBody() {
    if (!this.tableEl) return;
    this.tableEl.innerHTML = '';

    const countLabel = this.container?.querySelector('#table-count-label');
    if (countLabel) {
      countLabel.textContent = `Tổng: ${this.filteredData.length} mục`;
    }

    if (this.filteredData.length === 0) {
      this.tableEl.appendChild(
        h('div', { className: 'table-empty-state' },
          renderIcon('info'),
          h('p', {}, this.emptyMessage)
        )
      );
      return;
    }

    // Pagination slice
    const totalPages = Math.ceil(this.filteredData.length / this.pageSize) || 1;
    if (this.currentPage > totalPages) this.currentPage = totalPages;

    const startIdx = (this.currentPage - 1) * this.pageSize;
    const pageItems = this.filteredData.slice(startIdx, startIdx + this.pageSize);

    // Build Table
    const table = h('table', { className: 'ui-data-table' });
    const thead = h('thead', {});
    const headerRow = h('tr', {});

    this.columns.forEach((col) => {
      const th = h('th', { scope: 'col', className: col.sortable ? 'col-sortable' : '' },
        h('div', { className: 'th-content' },
          col.label,
          col.sortable ? renderIcon('refresh', 'sort-indicator') : null
        )
      );

      if (col.sortable) {
        th.onclick = () => {
          if (this.sortKey === col.key) {
            this.sortAsc = !this.sortAsc;
          } else {
            this.sortKey = col.key;
            this.sortAsc = true;
          }
          this.applyFilters();
          this.renderBody();
        };
      }

      headerRow.appendChild(th);
    });
    thead.appendChild(headerRow);
    table.appendChild(thead);

    const tbody = h('tbody', {});
    pageItems.forEach((rowItem) => {
      const tr = h('tr', {
        className: this.onRowClick ? 'row-clickable' : '',
        onclick: () => {
          if (this.onRowClick) this.onRowClick(rowItem);
        },
      });

      this.columns.forEach((col) => {
        const td = h('td', {});
        if (typeof col.render === 'function') {
          const rendered = col.render(rowItem[col.key], rowItem);
          if (rendered instanceof Node) {
            td.appendChild(rendered);
          } else {
            td.innerHTML = String(rendered ?? '—');
          }
        } else {
          td.textContent = String(rowItem[col.key] ?? '—');
        }
        tr.appendChild(td);
      });

      tbody.appendChild(tr);
    });
    table.appendChild(tbody);

    this.tableEl.appendChild(table);

    // Pagination Footer
    if (totalPages > 1) {
      const paginationEl = h('div', { className: 'table-pagination' },
        h('button', {
          className: 'btn btn-sm btn-secondary',
          disabled: this.currentPage === 1,
          onclick: () => {
            this.currentPage--;
            this.renderBody();
          },
        }, 'Trang trước'),
        h('span', { className: 'pagination-page-indicator' }, `Trang ${this.currentPage} / ${totalPages}`),
        h('button', {
          className: 'btn btn-sm btn-secondary',
          disabled: this.currentPage === totalPages,
          onclick: () => {
            this.currentPage++;
            this.renderBody();
          },
        }, 'Trang sau')
      );
      this.tableEl.appendChild(paginationEl);
    }
  }
}
