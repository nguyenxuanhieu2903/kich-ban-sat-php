import { spawn } from 'node:child_process';
import fs from 'node:fs';

const CDP_PORT = 9222;
const APP_URL = 'http://127.0.0.1:8000';

function findChromePath() {
  const candidates = [
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
  ];
  for (const c of candidates) {
    if (fs.existsSync(c)) return c;
  }
  return null;
}

async function run() {
  let spawnedChrome = null;
  let isListening = false;
  try {
    const res = await fetch(`http://127.0.0.1:${CDP_PORT}/json/version`);
    isListening = res.ok;
  } catch {}

  if (!isListening) {
    const chromeBin = findChromePath();
    const userDataDir = process.env.TEMP + '\\chrome-debug-' + Date.now();
    spawnedChrome = spawn(chromeBin, [
      '--headless=new',
      `--remote-debugging-port=${CDP_PORT}`,
      `--user-data-dir=${userDataDir}`,
      '--disable-gpu',
      '--no-first-run',
      '--no-default-browser-check',
      'about:blank'
    ], { stdio: 'ignore' });
    await new Promise(r => setTimeout(r, 1500));
  }

  const list = await (await fetch(`http://127.0.0.1:${CDP_PORT}/json/list`)).json();
  const pageTarget = list.find(t => t.type === 'page') || list[0];
  const ws = new WebSocket(pageTarget.webSocketDebuggerUrl);

  let id = 1;
  const callbacks = new Map();
  ws.onmessage = (e) => {
    const data = JSON.parse(e.data);
    if (data.id && callbacks.has(data.id)) {
      const { resolve, reject } = callbacks.get(data.id);
      callbacks.delete(data.id);
      if (data.error) reject(new Error(data.error.message));
      else resolve(data.result);
    }
  };

  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const curId = id++;
    callbacks.set(curId, { resolve, reject });
    ws.send(JSON.stringify({ id: curId, method, params }));
  });

  await new Promise((resolve) => { ws.onopen = resolve; });
  await send('Page.enable');
  await send('Runtime.enable');
  await send('Emulation.setDeviceMetricsOverride', {
    width: 1440,
    height: 900,
    deviceScaleFactor: 1,
    mobile: false
  });

  await send('Page.navigate', { url: APP_URL });
  await new Promise(r => setTimeout(r, 2000));

  const screenshot = await send('Page.captureScreenshot', { format: 'png' });
  if (screenshot && screenshot.data) {
    fs.writeFileSync('C:\\Users\\DELL\\.gemini\\antigravity\\brain\\4bc7babd-01ac-498a-8487-7db59b52f797\\live_preview.png', Buffer.from(screenshot.data, 'base64'));
    console.log('Screenshot saved to live_preview.png');
  }

  ws.close();
  if (spawnedChrome) spawnedChrome.kill();
}

run().catch(console.error);
