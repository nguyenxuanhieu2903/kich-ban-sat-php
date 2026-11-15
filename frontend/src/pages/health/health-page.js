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

    let statusBadge = '';
    let bodyContent = '';

    if (status === 'loading') {
      statusBadge = `
        <span class="status-badge loading">
          <span class="status-indicator-dot"></span> Checking API...
        </span>
      `;
      bodyContent = `
        <p style="color: var(--color-text-muted);">Contacting backend at <code>/api/health</code>...</p>
      `;
    } else if (status === 'success') {
      const healthData = data?.data || {};
      statusBadge = `
        <span class="status-badge success">
          <span class="status-indicator-dot"></span> Connected
        </span>
      `;
      bodyContent = `
        <div class="data-grid">
          <div class="data-cell">
            <div class="data-label">API Status</div>
            <div class="data-value">${healthData.status || 'OK'}</div>
          </div>
          <div class="data-cell">
            <div class="data-label">Application</div>
            <div class="data-value">${healthData.application || 'Kich Ban Sat'}</div>
          </div>
          <div class="data-cell">
            <div class="data-label">Platform Version</div>
            <div class="data-value">V${healthData.version || '16'}</div>
          </div>
          <div class="data-cell">
            <div class="data-label">Response Latency</div>
            <div class="data-value">${latency} ms</div>
          </div>
        </div>
        <div class="data-label">Raw JSON Response:</div>
        <pre class="code-block">${JSON.stringify(data, null, 2)}</pre>
      `;
    } else if (status === 'error') {
      statusBadge = `
        <span class="status-badge error">
          <span class="status-indicator-dot"></span> Unreachable / Error
        </span>
      `;
      bodyContent = `
        <div style="margin-bottom: 1rem; color: var(--color-error);">
          <strong>Error [${error.code || 'UNKNOWN'}]:</strong> ${error.message}
        </div>
        ${error.details ? `<pre class="code-block">${JSON.stringify(error.details, null, 2)}</pre>` : ''}
        <p style="color: var(--color-text-muted); font-size: 0.875rem;">
          Make sure the PHP backend server is running on <code>127.0.0.1:8000</code>.
        </p>
      `;
    }

    this.container.innerHTML = `
      <section class="card">
        <div class="card-title">
          <span>Backend Health Verification</span>
          ${statusBadge}
        </div>
        ${bodyContent}
        <div style="margin-top: 1.5rem;">
          <button id="btn-recheck" class="btn" ${status === 'loading' ? 'disabled' : ''}>
            ${status === 'loading' ? 'Checking...' : 'Check Connection'}
          </button>
        </div>
      </section>
    `;

    const button = this.container.querySelector('#btn-recheck');
    if (button) {
      button.addEventListener('click', () => this.runCheck());
    }
  }
}
