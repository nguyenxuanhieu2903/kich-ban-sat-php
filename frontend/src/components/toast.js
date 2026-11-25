let timer = 0;

function root() {
  let node = document.getElementById('kbs-toast-root');
  if (!node) {
    node = document.createElement('div');
    node.id = 'kbs-toast-root';
    node.className = 'kbs-toast-root';
    node.setAttribute('aria-live', 'polite');
    document.body.appendChild(node);
  }
  return node;
}

export function showToast(message, type = 'ok') {
  const host = root();
  host.hidden = false;
  host.textContent = message;
  host.className = `kbs-toast-root kbs-toast kbs-toast--${type === 'error' ? 'err' : type === 'success' ? 'ok' : type}`;
  window.clearTimeout(timer);
  timer = window.setTimeout(() => {
    host.hidden = true;
  }, 4200);
}
