import { healthApi } from '../../api/health-api.js';

export class HealthPage {
  constructor(mountElement) {
    this.container = mountElement;
    this.state = {
      status: 'idle', // 'idle' | 'loading' | 'success' | 'error'
      data: null,
      error: null,
      latency: null,
    };
  }

  async runCheck() {
    this.state.status = 'loading';
    this.state.error = null;
    this.render();

    const startTime = performance.now();
    try {
      const response = await healthApi.checkHealth();
      const endTime = performance.now();
      this.state.status = 'success';
      this.state.data = response;
      this.state.latency = Math.round(endTime - startTime);
    } catch (err) {
      this.state.status = 'error';
      this.state.error = err;
    } finally {
      this.render();
    }
  }

  render() {
    const { status, data, error, latency } = this.state;

    const section = document.createElement('section');
    section.className = 'card';

    // Card title
    const cardTitle = document.createElement('div');
    cardTitle.className = 'card-title';
    const titleSpan = document.createElement('span');
    titleSpan.textContent = 'Backend Health Verification';
    cardTitle.appendChild(titleSpan);

    // Status badge
    const badge = document.createElement('span');
    const dot = document.createElement('span');
    dot.className = 'status-indicator-dot';

    if (status === 'loading') {
      badge.className = 'status-badge loading';
      badge.appendChild(dot);
      badge.appendChild(document.createTextNode(' Checking API...'));
    } else if (status === 'success') {
      badge.className = 'status-badge success';
      badge.appendChild(dot);
      badge.appendChild(document.createTextNode(' Connected'));
    } else if (status === 'error') {
      badge.className = 'status-badge error';
      badge.appendChild(dot);
      badge.appendChild(document.createTextNode(' Unreachable / Error'));
    } else {
      badge.className = 'status-badge';
      badge.appendChild(dot);
      badge.appendChild(document.createTextNode(' Idle'));
    }
    cardTitle.appendChild(badge);
    section.appendChild(cardTitle);

    // Body content
    const bodyContainer = document.createElement('div');
    bodyContainer.className = 'card-body';

    if (status === 'loading') {
      const p = document.createElement('p');
      p.style.color = 'var(--color-text-muted)';
      p.textContent = 'Contacting backend at /api/health...';
      bodyContainer.appendChild(p);
    } else if (status === 'success') {
      const healthData = data?.data || {};

      const dataGrid = document.createElement('div');
      dataGrid.className = 'data-grid';

      const createCell = (label, value) => {
        const cell = document.createElement('div');
        cell.className = 'data-cell';
        const lbl = document.createElement('div');
        lbl.className = 'data-label';
        lbl.textContent = label;
        const val = document.createElement('div');
        val.className = 'data-value';
        val.textContent = String(value);
        cell.appendChild(lbl);
        cell.appendChild(val);
        return cell;
      };

      dataGrid.appendChild(createCell('API Status', healthData.status || 'OK'));
      dataGrid.appendChild(createCell('Application', healthData.application || 'Kich Ban Sat'));
      dataGrid.appendChild(createCell('Platform Version', `V${healthData.version || '16'}`));
      dataGrid.appendChild(createCell('Response Latency', `${latency} ms`));
      bodyContainer.appendChild(dataGrid);

      const rawLabel = document.createElement('div');
      rawLabel.className = 'data-label';
      rawLabel.textContent = 'Raw JSON Response:';
      bodyContainer.appendChild(rawLabel);

      const pre = document.createElement('pre');
      pre.className = 'code-block';
      pre.textContent = JSON.stringify(data, null, 2);
      bodyContainer.appendChild(pre);
    } else if (status === 'error') {
      const errorDiv = document.createElement('div');
      errorDiv.style.marginBottom = '1rem';
      errorDiv.style.color = 'var(--color-error)';

      const strong = document.createElement('strong');
      strong.textContent = `Error [${error?.code || 'UNKNOWN'}]: `;
      errorDiv.appendChild(strong);
      errorDiv.appendChild(document.createTextNode(error?.message || 'An unknown error occurred'));
      bodyContainer.appendChild(errorDiv);

      if (error?.details) {
        const pre = document.createElement('pre');
        pre.className = 'code-block';
        pre.textContent = JSON.stringify(error.details, null, 2);
        bodyContainer.appendChild(pre);
      }

      const hintP = document.createElement('p');
      hintP.style.color = 'var(--color-text-muted)';
      hintP.style.fontSize = '0.875rem';
      const code = document.createElement('code');
      code.textContent = '127.0.0.1:8080';
      hintP.appendChild(document.createTextNode('Make sure the PHP backend server is running on '));
      hintP.appendChild(code);
      hintP.appendChild(document.createTextNode('.'));
      bodyContainer.appendChild(hintP);
    }

    section.appendChild(bodyContainer);

    // Action button
    const actionsDiv = document.createElement('div');
    actionsDiv.style.marginTop = '1.5rem';

    const button = document.createElement('button');
    button.id = 'btn-recheck';
    button.className = 'btn';
    if (status === 'loading') {
      button.disabled = true;
      button.textContent = 'Checking...';
    } else {
      button.disabled = false;
      button.textContent = 'Check Connection';
    }
    button.addEventListener('click', () => this.runCheck());

    actionsDiv.appendChild(button);
    section.appendChild(actionsDiv);

    // Safe DOM replacement
    if (typeof this.container.replaceChildren === 'function') {
      this.container.replaceChildren(section);
    } else {
      this.container.innerHTML = '';
      this.container.appendChild(section);
    }
  }
}
