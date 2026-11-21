import assert from 'node:assert/strict';
import test from 'node:test';

const CDP_PORT = 9222;
const APP_URL = 'http://127.0.0.1:8000';

class SimpleCDP {
  constructor(wsUrl) {
    this.wsUrl = wsUrl;
    this.ws = null;
    this.id = 1;
    this.callbacks = new Map();
  }

  async connect() {
    return new Promise((resolve, reject) => {
      this.ws = new WebSocket(this.wsUrl);
      this.ws.onopen = () => resolve();
      this.ws.onerror = (err) => reject(err);
      this.ws.onmessage = (msg) => {
        const data = JSON.parse(msg.data);
        if (data.id && this.callbacks.has(data.id)) {
          const { resolve, reject } = this.callbacks.get(data.id);
          this.callbacks.delete(data.id);
          if (data.error) reject(new Error(data.error.message));
          else resolve(data.result);
        }
      };
    });
  }

  async send(method, params = {}) {
    const id = this.id++;
    return new Promise((resolve, reject) => {
      this.callbacks.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params }));
    });
  }

  async eval(expression) {
    const res = await this.send('Runtime.evaluate', {
      expression,
      awaitPromise: true,
      returnByValue: true
    });
    if (res.exceptionDetails) {
      throw new Error(`Eval exception: ${res.exceptionDetails.exception?.description || res.exceptionDetails.text}`);
    }
    return res.result?.value;
  }

  close() {
    if (this.ws) this.ws.close();
  }
}

test('Browser Real Interaction: Auth UI & Multi-Role Security', async () => {
  // 1. Create a new target page in Headless Chrome
  const targetRes = await fetch(`http://127.0.0.1:${CDP_PORT}/json/new?${encodeURIComponent(APP_URL + '/')}`, { method: 'PUT' });
  const target = await targetRes.json();
  const cdp = new SimpleCDP(target.webSocketDebuggerUrl);
  await cdp.connect();

  try {
    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');

    // Wait for DOMContentLoaded and app initialization
    await new Promise(r => setTimeout(r, 1000));

    // 1. Initial State: Check Public Header - DM and Admin tabs MUST BE HIDDEN
    const initialNav = await cdp.eval(`(() => {
      const dm = document.getElementById('nav-btn-dm');
      const admin = document.getElementById('nav-btn-admin');
      return {
        dmDisplay: dm ? window.getComputedStyle(dm).display : null,
        adminDisplay: admin ? window.getComputedStyle(admin).display : null,
        loginBtnText: document.getElementById('btn-open-login')?.textContent?.trim()
      };
    })()`);

    assert.equal(initialNav.dmDisplay, 'none', 'nav-btn-dm must be display: none for guest');
    assert.equal(initialNav.adminDisplay, 'none', 'nav-btn-admin must be display: none for guest');
    assert.ok(initialNav.loginBtnText.includes('Đăng nhập'), 'Login CTA is visible');

    // 2. Open Login Modal
    await cdp.eval(`document.getElementById('btn-open-login').click()`);
    await new Promise(r => setTimeout(r, 300));

    const modalState = await cdp.eval(`(() => {
      const modal = document.getElementById('auth-modal');
      return {
        isOpen: modal ? modal.open : false,
        roleButtonsCount: modal ? modal.querySelectorAll('.role-tab-btn').length : 0,
        selectedRole: modal ? modal.querySelector('.role-tab-btn.active')?.dataset?.role : null
      };
    })()`);

    assert.equal(modalState.isOpen, true, 'AuthModal opens upon clicking #btn-open-login');
    assert.equal(modalState.roleButtonsCount, 3, 'AuthModal contains 3 role buttons');
    assert.equal(modalState.selectedRole, 'PLAYER', 'Default selected role is PLAYER');

    // 3. Switch between roles: Select DM
    await cdp.eval(`document.querySelector('.role-tab-btn[data-role="DM"]').click()`);
    await new Promise(r => setTimeout(r, 200));

    const dmRoleState = await cdp.eval(`(() => {
      const modal = document.getElementById('auth-modal');
      const activeBtn = modal.querySelector('.role-tab-btn.active');
      const submitBtn = modal.querySelector('#auth-submit-btn');
      return {
        role: activeBtn?.dataset?.role,
        submitText: submitBtn?.textContent?.trim()
      };
    })()`);
    assert.equal(dmRoleState.role, 'DM', 'Active role tab switched to DM');
    assert.ok(dmRoleState.submitText.includes('Quản Trò'), 'Submit button reflects DM role');

    // 4. Switch to Admin
    await cdp.eval(`document.querySelector('.role-tab-btn[data-role="ADMIN"]').click()`);
    await new Promise(r => setTimeout(r, 200));

    const adminRoleState = await cdp.eval(`(() => {
      const modal = document.getElementById('auth-modal');
      const activeBtn = modal.querySelector('.role-tab-btn.active');
      const submitBtn = modal.querySelector('#auth-submit-btn');
      return {
        role: activeBtn?.dataset?.role,
        submitText: submitBtn?.textContent?.trim()
      };
    })()`);
    assert.equal(adminRoleState.role, 'ADMIN', 'Active role tab switched to ADMIN');
    assert.ok(adminRoleState.submitText.includes('Admin'), 'Submit button reflects ADMIN role');

    // 5. Test Quick Demo Fill (Dev)
    await cdp.eval(`document.getElementById('btn-fill-demo').click()`);
    const filledValues = await cdp.eval(`(() => {
      return {
        email: document.getElementById('auth-email-input').value,
        pass: document.getElementById('auth-password-input').value
      };
    })()`);
    assert.equal(filledValues.email, 'admin@kichbansat.vn');
    assert.equal(filledValues.pass, 'password123');

    // 6. Test Error Handling: Type invalid password and submit
    await cdp.eval(`(() => {
      document.getElementById('auth-password-input').value = 'wrongpassword';
      document.getElementById('auth-form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    })()`);
    await new Promise(r => setTimeout(r, 500));

    const errorState = await cdp.eval(`(() => {
      const err = document.getElementById('auth-error-alert');
      const btn = document.getElementById('auth-submit-btn');
      return {
        isDisplayed: err ? window.getComputedStyle(err).display !== 'none' : false,
        errorText: err?.textContent?.trim(),
        btnDisabled: btn?.disabled
      };
    })()`);
    assert.equal(errorState.isDisplayed, true, 'Error alert is displayed on failed login');
    assert.ok(errorState.errorText.includes('không chính xác'), 'Error alert shows correct error message');
    assert.equal(errorState.btnDisabled, false, 'Submit button is re-enabled');

    // 7. Role Mismatch Test: Keep ADMIN role tab selected, but input PLAYER credentials
    await cdp.eval(`(() => {
      document.getElementById('auth-email-input').value = 'player.hieu@kichbansat.vn';
      document.getElementById('auth-password-input').value = 'password123';
      document.getElementById('auth-form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    })()`);
    await new Promise(r => setTimeout(r, 800));

    const postPlayerLoginState = await cdp.eval(`(() => {
      const modal = document.getElementById('auth-modal');
      const dm = document.getElementById('nav-btn-dm');
      const admin = document.getElementById('nav-btn-admin');
      const userPill = document.querySelector('.user-pill');
      return {
        modalOpen: modal ? modal.open : false,
        dmDisplay: dm ? window.getComputedStyle(dm).display : null,
        adminDisplay: admin ? window.getComputedStyle(admin).display : null,
        userPillText: userPill ? userPill.textContent : ''
      };
    })()`);

    assert.equal(postPlayerLoginState.modalOpen, false, 'Modal closed after successful login');
    assert.ok(postPlayerLoginState.userPillText.includes('PLAYER'), 'User pill shows actual role PLAYER from server');
    assert.equal(postPlayerLoginState.adminDisplay, 'none', 'Role mismatch protection: Admin tab is NOT shown for player');
    assert.equal(postPlayerLoginState.dmDisplay, 'none', 'Role mismatch protection: DM tab is NOT shown for player');

    // 8. Logout
    await cdp.eval(`document.getElementById('btn-logout').click()`);
    await new Promise(r => setTimeout(r, 500));

    const postLogoutState = await cdp.eval(`(() => {
      const dm = document.getElementById('nav-btn-dm');
      const admin = document.getElementById('nav-btn-admin');
      return {
        dmDisplay: dm ? window.getComputedStyle(dm).display : null,
        adminDisplay: admin ? window.getComputedStyle(admin).display : null,
        loginBtnVisible: Boolean(document.getElementById('btn-open-login'))
      };
    })()`);
    assert.equal(postLogoutState.loginBtnVisible, true, 'Login button visible after logout');
    assert.equal(postLogoutState.dmDisplay, 'none', 'DM nav hidden after logout');
    assert.equal(postLogoutState.adminDisplay, 'none', 'Admin nav hidden after logout');

    // 9. Login as ADMIN and verify Admin privileges
    await cdp.eval(`document.getElementById('btn-open-login').click()`);
    await new Promise(r => setTimeout(r, 300));
    await cdp.eval(`document.querySelector('.role-tab-btn[data-role="ADMIN"]').click()`);
    await cdp.eval(`document.getElementById('btn-fill-demo').click()`);
    await cdp.eval(`document.getElementById('auth-form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));`);
    await new Promise(r => setTimeout(r, 800));

    const postAdminLoginState = await cdp.eval(`(() => {
      const dm = document.getElementById('nav-btn-dm');
      const admin = document.getElementById('nav-btn-admin');
      const userPill = document.querySelector('.user-pill');
      return {
        dmDisplay: dm ? window.getComputedStyle(dm).display : null,
        adminDisplay: admin ? window.getComputedStyle(admin).display : null,
        userPillText: userPill ? userPill.textContent : ''
      };
    })()`);
    assert.ok(postAdminLoginState.userPillText.includes('ADMIN'), 'User pill shows ADMIN');
    assert.equal(postAdminLoginState.adminDisplay, 'flex', 'Admin tab IS shown for ADMIN user');
    assert.equal(postAdminLoginState.dmDisplay, 'flex', 'DM tab IS shown for ADMIN user');

    // Cleanup: Logout
    await cdp.eval(`document.getElementById('btn-logout').click()`);
    await new Promise(r => setTimeout(r, 500));

  } finally {
    cdp.close();
    // Close target page
    await fetch(`http://127.0.0.1:${CDP_PORT}/json/close/${target.id}`);
  }
});
