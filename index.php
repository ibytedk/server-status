<!doctype html>
<html lang="da">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Apache Real-Time Dashboard</title>
<style>
  :root {
    --bg: #f4f7fb;
    --panel: rgba(255, 255, 255, 0.9);
    --text: #102033;
    --muted: #607086;
    --line: #d7e0ea;
    --accent: #0f766e;
    --accent-2: #1d4ed8;
    --warn: #b45309;
    --danger: #b91c1c;
    --shadow: 0 20px 60px rgba(14, 30, 62, 0.12);
  }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    font-family: Aptos, "Segoe UI Variable", "Segoe UI", sans-serif;
    color: var(--text);
    background:
      radial-gradient(circle at top right, rgba(29, 78, 216, 0.12), transparent 24rem),
      radial-gradient(circle at top left, rgba(15, 118, 110, 0.10), transparent 24rem),
      var(--bg);
  }

  .wrap {
    max-width: 1440px;
    margin: 0 auto;
    padding: 28px;
  }

  .hero,
  .card,
  .panel {
    border: 1px solid rgba(255, 255, 255, 0.8);
    box-shadow: var(--shadow);
    backdrop-filter: blur(12px);
  }

  .hero {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 18px;
    padding: 24px;
    border-radius: 24px;
    background: linear-gradient(135deg, rgba(16, 32, 51, 0.97), rgba(29, 78, 216, 0.86));
    color: #fff;
    margin-bottom: 18px;
  }

  .hero h1 {
    margin: 0 0 8px;
    font-size: 34px;
  }

  .hero p {
    margin: 0;
    max-width: 760px;
    color: rgba(255, 255, 255, 0.78);
  }

  .meta {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
    align-content: start;
  }

  .pill {
    padding: 10px 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.18);
    font-size: 13px;
    white-space: nowrap;
  }

  .pill a {
    color: #fff;
    text-decoration: none;
  }

  .grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 16px;
  }

  .card,
  .panel {
    background: var(--panel);
    border-radius: 20px;
    padding: 18px;
  }

  .label {
    color: var(--muted);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }

  .value {
    font-size: 34px;
    font-weight: 700;
    margin: 8px 0 4px;
  }

  .sub {
    color: var(--muted);
    font-size: 14px;
  }

  .layout {
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    gap: 16px;
    margin-top: 16px;
  }

  .history-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-top: 14px;
  }

  .charts {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .stack {
    display: grid;
    gap: 16px;
  }

  .panel h2 {
    margin: 0 0 14px;
    font-size: 18px;
  }

  .row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
  }

  .spark {
    display: block;
    width: 100%;
    height: 82px;
    margin-top: 10px;
  }

  .scoreboard,
  .vhost-list {
    display: grid;
    gap: 10px;
  }

  .list-item {
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid var(--line);
    background: rgba(255, 255, 255, 0.72);
  }

  .list-item strong {
    font-size: 14px;
  }

  .muted {
    color: var(--muted);
    font-size: 13px;
  }

  .sample {
    margin-top: 6px;
    color: var(--text);
    font-size: 13px;
    overflow-wrap: anywhere;
  }

  .bar {
    height: 10px;
    border-radius: 999px;
    overflow: hidden;
    background: #e6edf5;
    margin-top: 8px;
  }

  .fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
  }

  .requests {
    margin-top: 16px;
  }

  .table-wrap {
    overflow: auto;
    border: 1px solid var(--line);
    border-radius: 16px;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
  }

  th,
  td {
    text-align: left;
    padding: 12px 14px;
    border-bottom: 1px solid var(--line);
    vertical-align: top;
  }

  th {
    position: sticky;
    top: 0;
    background: #eef4fb;
    color: var(--muted);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }

  tr:last-child td {
    border-bottom: 0;
  }

  .mono {
    font-family: Consolas, "Courier New", monospace;
    font-size: 12px;
  }

  .facts {
    display: grid;
    gap: 10px;
  }

  .fact {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--line);
  }

  .fact:last-child {
    border-bottom: 0;
    padding-bottom: 0;
  }

  .fact span:first-child {
    color: var(--muted);
  }

  .empty {
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px dashed var(--line);
    color: var(--muted);
    background: rgba(255, 255, 255, 0.65);
  }

  .admin-panel {
    margin: 16px 0;
  }

  .admin-copy {
    margin: 0 0 14px;
    color: var(--muted);
    font-size: 14px;
  }

  .toolbar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
  }

  .input {
    min-width: 240px;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid var(--line);
    background: rgba(255, 255, 255, 0.9);
    color: var(--text);
  }

  .button {
    border: 0;
    border-radius: 12px;
    padding: 12px 14px;
    font-weight: 600;
    cursor: pointer;
    color: #fff;
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
  }

  .button.secondary {
    background: #dde7f3;
    color: var(--text);
  }

  .button.warn {
    background: linear-gradient(135deg, #b45309, #d97706);
  }

  .button.danger {
    background: linear-gradient(135deg, #b91c1c, #dc2626);
  }

  .button:disabled {
    cursor: not-allowed;
    opacity: 0.55;
  }

  .status-note {
    min-height: 20px;
    color: var(--muted);
    font-size: 13px;
  }

  .status-note.error {
    color: var(--danger);
  }

  .status-note.ok {
    color: var(--accent);
  }

  .mono-wrap {
    font-family: Consolas, "Courier New", monospace;
    font-size: 12px;
    overflow-wrap: anywhere;
  }

  .client-chips,
  .badges,
  .table-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .chip,
  .badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 9px;
    border-radius: 999px;
    font-size: 12px;
    border: 1px solid var(--line);
    background: rgba(255, 255, 255, 0.85);
  }

  .badge.warn {
    border-color: rgba(180, 83, 9, 0.25);
    color: var(--warn);
    background: rgba(245, 158, 11, 0.12);
  }

  .badge.danger {
    border-color: rgba(185, 28, 28, 0.25);
    color: var(--danger);
    background: rgba(220, 38, 38, 0.1);
  }

  .badge.ok {
    border-color: rgba(15, 118, 110, 0.25);
    color: var(--accent);
    background: rgba(15, 118, 110, 0.08);
  }

  .hint {
    margin-top: 8px;
    color: var(--muted);
    font-size: 12px;
  }

  .ok { color: var(--accent); }
  .warn { color: var(--warn); }
  .error { color: var(--danger); }

  @media (max-width: 1200px) {
    .grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .layout,
    .hero,
    .history-grid { grid-template-columns: 1fr; }
  }

  @media (max-width: 820px) {
    .wrap { padding: 16px; }
    .grid,
    .charts { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<div class="wrap">
  <section class="hero">
    <div>
      <h1>Apache Real-Time Dashboard</h1>
      <p>Primairt fokus: find hvilken trafik der faktisk belaster serveren, og hvilket website den rammer. Sekundaert fokus: vurdere hvor effektivt serveren arbejder ud fra workers, cache og throughput.</p>
    </div>
    <div class="meta">
      <div class="pill" id="live-state">Forbinder...</div>
      <div class="pill" id="last-update">Ingen data endnu</div>
      <div class="pill"><a href="/apache-status" target="_blank" rel="noopener">Aabn raa status</a></div>
    </div>
  </section>

  <section class="panel admin-panel">
    <div class="row">
      <h2>IP Blocking</h2>
      <strong id="blocklist-state">Read only</strong>
    </div>
    <p class="admin-copy" id="blocklist-help">Dashboardet kan vise aktive klient-IP'er med det samme. For at blokere IP'er skal serveren have en admin-noegle og en Apache include-path konfigureret.</p>
    <div class="toolbar">
      <input class="input" type="password" id="admin-key" placeholder="Admin key for write actions">
      <button class="button" type="button" id="save-admin-key">Use key in this browser tab</button>
      <button class="button secondary" type="button" id="clear-admin-key">Clear key</button>
    </div>
    <div class="status-note" id="mutation-status">No blocklist changes yet.</div>
    <div class="facts">
      <div class="fact"><span>Blocked Entries</span><strong id="blocked-count">-</strong></div>
      <div class="fact"><span>Apache Include File</span><strong id="apache-include-path" class="mono-wrap">-</strong></div>
      <div class="fact"><span>Apache Include Hint</span><strong id="apache-include-hint" class="mono-wrap">-</strong></div>
      <div class="fact"><span>Reload Mode</span><strong id="apache-reload-status">-</strong></div>
    </div>
  </section>

  <section class="grid">
    <div class="card">
      <div class="label">Busy Workers</div>
      <div class="value" id="busy">-</div>
      <div class="sub" id="busy-sub">-</div>
    </div>
    <div class="card">
      <div class="label">Idle Workers</div>
      <div class="value" id="idle">-</div>
      <div class="sub" id="idle-sub">-</div>
    </div>
    <div class="card">
      <div class="label">Requests / Sec</div>
      <div class="value" id="rps">-</div>
      <div class="sub" id="bytes-per-request">-</div>
    </div>
    <div class="card">
      <div class="label">Throughput</div>
      <div class="value" id="bps">-</div>
      <div class="sub" id="uptime">-</div>
    </div>
    <div class="card">
      <div class="label">Websites Under Load</div>
      <div class="value" id="active-sites">-</div>
      <div class="sub" id="active-sites-sub">-</div>
    </div>
    <div class="card">
      <div class="label">Load-Bearing Requests</div>
      <div class="value" id="active-requests">-</div>
      <div class="sub" id="active-requests-sub">-</div>
    </div>
  </section>

  <section class="layout">
    <div class="charts">
      <div class="panel">
        <div class="row"><h2>Req/Sec</h2><strong id="rps-now">-</strong></div>
        <svg class="spark" id="spark-rps" viewBox="0 0 300 82" preserveAspectRatio="none"></svg>
      </div>
      <div class="panel">
        <div class="row"><h2>Apache CPU</h2><strong id="cpu-now">-</strong></div>
        <svg class="spark" id="spark-cpu" viewBox="0 0 300 82" preserveAspectRatio="none"></svg>
      </div>
      <div class="panel">
        <div class="row"><h2>Requests / Poll</h2><strong id="requests-chart-now">-</strong></div>
        <svg class="spark" id="spark-requests" viewBox="0 0 300 82" preserveAspectRatio="none"></svg>
      </div>
      <div class="panel">
        <div class="row"><h2>Bytes/Sec</h2><strong id="bps-now">-</strong></div>
        <svg class="spark" id="spark-bps" viewBox="0 0 300 82" preserveAspectRatio="none"></svg>
      </div>
      <div class="panel">
        <div class="row"><h2>Output Cache Hit %</h2><strong id="cache-hit-now">-</strong></div>
        <svg class="spark" id="spark-cache" viewBox="0 0 300 82" preserveAspectRatio="none"></svg>
      </div>
    </div>

    <div class="stack">
      <div class="panel">
        <div class="row"><h2>Websites Under Load</h2><strong id="active-sites-now">-</strong></div>
        <div class="vhost-list" id="vhosts"></div>
      </div>

      <div class="panel">
        <h2>Worker States</h2>
        <div class="scoreboard" id="scoreboard"></div>
      </div>
    </div>
  </section>

  <section class="panel requests">
    <div class="row">
      <h2>Requests Under Load</h2>
      <strong id="requests-now">-</strong>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Website</th>
            <th>Request</th>
            <th>Client</th>
            <th>Proto</th>
            <th>Mode</th>
            <th>Dur ms</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="requests-body">
          <tr><td colspan="7" class="muted">Ingen data endnu</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="layout">
    <div class="panel">
      <div class="row">
        <h2>Client IPs Under Load</h2>
        <strong id="clients-now">-</strong>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Website</th>
              <th>Client IP</th>
              <th>Reqs</th>
              <th>Sample</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="clients-body">
            <tr><td colspan="6" class="muted">Ingen data endnu</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="row">
        <h2>Blocked IPs</h2>
        <strong id="blocked-now">-</strong>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Scope</th>
              <th>Client IP</th>
              <th>Created</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="blocked-body">
            <tr><td colspan="4" class="muted">Ingen blocks endnu</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="row">
      <h2>Historical Load</h2>
      <strong id="history-overview">-</strong>
    </div>
    <div class="admin-copy" id="history-note">Sampling not loaded yet.</div>
    <div class="status-note" id="history-error"></div>
    <div class="history-grid" id="history-windows">
      <div class="empty">Historical load will appear here when samples have been collected.</div>
    </div>
  </section>

  <section class="layout">
    <div class="panel">
      <h2>Key Facts</h2>
      <div class="facts">
        <div class="fact"><span>Total Accesses</span><strong id="total-accesses">-</strong></div>
        <div class="fact"><span>Total kBytes</span><strong id="total-kbytes">-</strong></div>
        <div class="fact"><span>Apache CPU</span><strong id="cpu-load">-</strong></div>
        <div class="fact"><span>CPU Source</span><strong id="cpu-source">-</strong></div>
        <div class="fact"><span>Worker Capacity</span><strong id="worker-capacity">-</strong></div>
        <div class="fact"><span>Server Uptime</span><strong id="server-uptime">-</strong></div>
      </div>
    </div>

    <div class="panel">
      <h2>Status</h2>
      <div class="facts">
        <div class="fact"><span>Summary Source</span><strong class="ok">/apache-status?auto</strong></div>
        <div class="fact"><span>Detail Source</span><strong class="ok">/apache-status</strong></div>
        <div class="fact"><span>Output Cache Hit %</span><strong id="cache-hit-summary">-</strong></div>
        <div class="fact"><span>Cache Source</span><strong id="cache-source">-</strong></div>
        <div class="fact"><span>Cache Decisions</span><strong id="cache-decisions">-</strong></div>
        <div class="fact"><span>Observed Requests</span><strong id="observed-requests">-</strong></div>
        <div class="fact"><span>Keepalive Requests</span><strong id="keepalive-requests">-</strong></div>
        <div class="fact"><span>Polling</span><strong>2 seconds</strong></div>
        <div class="fact"><span>Detail Parser</span><strong id="detail-state">Waiting</strong></div>
        <div class="fact"><span>Error</span><strong id="error" class="error">None</strong></div>
      </div>
    </div>
  </section>
</div>

<script>
const historySize = 40;
const history = { rps: [], cpu: [], requests: [], bps: [], cache: [] };
const previousCounters = { totalAccesses: null };
const uiState = {
  adminKey: sessionStorage.getItem('serverStatusAdminKey') || '',
  latestData: null,
  mutationMessage: 'No blocklist changes yet.',
  mutationError: false
};

function byId(id) {
  return document.getElementById(id);
}

function setText(id, value) {
  byId(id).textContent = value;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, function (char) {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    }[char];
  });
}

function pushMetric(name, value) {
  const safe = Number.isFinite(value) ? value : 0;
  history[name].push(safe);
  if (history[name].length > historySize) {
    history[name].shift();
  }
}

function formatFixed(value, digits = 2) {
  return Number(value || 0).toFixed(digits);
}

function formatBytesPerSecond(value) {
  const units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
  let size = Number(value || 0);
  let unitIndex = 0;
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex++;
  }
  const digits = size >= 100 ? 0 : size >= 10 ? 1 : 2;
  return `${size.toFixed(digits)} ${units[unitIndex]}`;
}

function formatCpuLoad(value) {
  if (value === null || value === undefined || value === '') {
    return 'N/A';
  }

  const number = Number(value || 0);
  if (number === 0) {
    return '0.0000%';
  }
  if (number < 0.01) {
    return `${number.toFixed(4)}%`;
  }
  if (number < 1) {
    return `${number.toFixed(3)}%`;
  }
  return `${number.toFixed(2)}%`;
}

function formatPercent(value, digits = 2) {
  if (value === null || value === undefined || value === '') {
    return 'N/A';
  }

  return `${Number(value).toFixed(digits)}%`;
}

function formatUptime(seconds) {
  let rest = Number(seconds || 0);
  const days = Math.floor(rest / 86400);
  rest %= 86400;
  const hours = Math.floor(rest / 3600);
  rest %= 3600;
  const minutes = Math.floor(rest / 60);
  return `${days}d ${hours}h ${minutes}m`;
}

function formatScope(scope) {
  return scope === 'global' ? 'Global' : scope;
}

function renderSparkline(id, values, stroke) {
  const svg = byId(id);
  if (!values.length) {
    svg.innerHTML = '';
    return;
  }

  const width = 300;
  const height = 82;
  const pad = 6;
  const min = Math.min(...values);
  const max = Math.max(...values);
  const range = max - min || 1;
  const points = values.map(function (value, index) {
    const x = pad + (index * (width - pad * 2)) / Math.max(values.length - 1, 1);
    const y = height - pad - ((value - min) / range) * (height - pad * 2);
    return `${x},${y}`;
  }).join(' ');

  svg.innerHTML = `
    <polyline fill="none" stroke="#d7e0ea" stroke-width="1.2" points="6,76 294,76"></polyline>
    <polyline fill="none" stroke="${stroke}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="${points}"></polyline>
  `;
}

function renderScoreboard(states) {
  const entries = Object.entries(states || {});
  if (!entries.length) {
    byId('scoreboard').innerHTML = '<div class="empty">No worker-state data available.</div>';
    return;
  }

  const total = entries.reduce(function (sum, entry) {
    return sum + Number(entry[1] || 0);
  }, 0) || 1;

  const rows = entries.map(function (entry) {
    const label = entry[0];
    const count = Number(entry[1] || 0);
    const percent = ((count / total) * 100).toFixed(1);
    return `
      <div class="list-item">
        <div class="row"><strong>${escapeHtml(label)}</strong><span class="muted">${count}</span></div>
        <div class="bar"><div class="fill" style="width:${percent}%"></div></div>
      </div>
    `;
  }).join('');

  byId('scoreboard').innerHTML = rows;
}

function renderClientBadges(clients) {
  if (!clients.length) {
    return '<div class="hint">No client IPs in the current snapshot.</div>';
  }

  const shown = clients.slice(0, 6).map(function (client) {
    return `<span class="chip mono-wrap">${escapeHtml(client.client)} <strong>${escapeHtml(client.requestCount)}</strong></span>`;
  }).join('');
  const more = clients.length > 6 ? `<span class="chip">+${clients.length - 6} more</span>` : '';
  return `<div class="client-chips">${shown}${more}</div>`;
}

function renderVHosts(items) {
  if (!items.length) {
    byId('vhosts').innerHTML = '<div class="empty">No websites are currently putting measurable load on the server.</div>';
    return;
  }

  const maxRequests = Math.max.apply(null, items.map(function (item) {
    return Number(item.activeRequests || 0);
  })) || 1;

  const rows = items.slice(0, 12).map(function (item) {
    const width = ((Number(item.activeRequests || 0) / maxRequests) * 100).toFixed(1);
    const blockedText = Number(item.blockedClients || 0) > 0
      ? `<span class="badge warn">${escapeHtml(item.blockedClients)} blocked</span>`
      : '<span class="badge ok">No blocks</span>';

    return `
      <div class="list-item">
        <div class="row">
          <strong>${escapeHtml(item.vhost)}</strong>
          <span class="muted">${escapeHtml(item.activeRequests)} active</span>
        </div>
        <div class="row">
          <div class="muted">${escapeHtml(item.uniqueClients)} clients now</div>
          <div class="badges">${blockedText}</div>
        </div>
        <div class="sample">${escapeHtml(item.sampleRequest)}</div>
        ${renderClientBadges(item.clients || [])}
        <div class="bar"><div class="fill" style="width:${width}%"></div></div>
      </div>
    `;
  }).join('');

  byId('vhosts').innerHTML = rows;
}

function renderStatusBadges(item) {
  const badges = [];

  if (item.blockedGlobal) {
    badges.push('<span class="badge danger">Global block</span>');
  }

  if (item.blockedOnVHost) {
    badges.push('<span class="badge warn">Site block</span>');
  }

  if (!badges.length) {
    badges.push('<span class="badge ok">Live</span>');
  }

  return `<div class="badges">${badges.join('')}</div>`;
}

function canMutate(blocklist) {
  return Boolean(blocklist && blocklist.adminKeyConfigured && uiState.adminKey);
}

function renderPairActions(item, blocklist) {
  if (!blocklist || !blocklist.adminKeyConfigured) {
    return '<span class="muted">Configure admin key on server</span>';
  }

  if (!uiState.adminKey) {
    return '<span class="muted">Enter admin key above</span>';
  }

  const actions = [];
  if (item.blockedOnVHost) {
    actions.push(`<button class="button secondary" type="button" data-action="unblock" data-scope="${escapeHtml(item.vhost)}" data-ip="${escapeHtml(item.client)}">Remove site block</button>`);
  } else {
    actions.push(`<button class="button warn" type="button" data-action="block" data-scope="${escapeHtml(item.vhost)}" data-ip="${escapeHtml(item.client)}">Block for site</button>`);
  }

  if (item.blockedGlobal) {
    actions.push(`<button class="button secondary" type="button" data-action="unblock" data-scope="global" data-ip="${escapeHtml(item.client)}">Remove global</button>`);
  } else {
    actions.push(`<button class="button danger" type="button" data-action="block" data-scope="global" data-ip="${escapeHtml(item.client)}">Block global</button>`);
  }

  return `<div class="table-actions">${actions.join('')}</div>`;
}

function renderRequests(items) {
  if (!items.length) {
    byId('requests-body').innerHTML = '<tr><td colspan="7" class="muted">No requests are currently contributing measurable load.</td></tr>';
    return;
  }

  const rows = items.map(function (item) {
    return `
      <tr>
        <td>${escapeHtml(item.vhost)}</td>
        <td class="mono">${escapeHtml(item.request)}</td>
        <td class="mono mono-wrap">${escapeHtml(item.client)}</td>
        <td>${escapeHtml(item.protocol)}</td>
        <td>${escapeHtml(item.mode)} ${escapeHtml(item.modeLabel || '')}</td>
        <td>${escapeHtml(item.durationMs)}</td>
        <td>${renderStatusBadges(item)}</td>
      </tr>
    `;
  }).join('');

  byId('requests-body').innerHTML = rows;
}

function renderClientPairs(items, blocklist) {
  if (!items.length) {
    byId('clients-body').innerHTML = '<tr><td colspan="6" class="muted">No client IPs are currently associated with load-bearing requests.</td></tr>';
    return;
  }

  const rows = items.map(function (item) {
    return `
      <tr>
        <td>${escapeHtml(item.vhost)}</td>
        <td class="mono mono-wrap">${escapeHtml(item.client)}</td>
        <td>${escapeHtml(item.requestCount)}</td>
        <td class="mono mono-wrap">${escapeHtml(item.sampleRequest)}</td>
        <td>${renderStatusBadges(item)}</td>
        <td>${renderPairActions(item, blocklist)}</td>
      </tr>
    `;
  }).join('');

  byId('clients-body').innerHTML = rows;
}

function renderBlockedEntries(blocklist) {
  const entries = (blocklist && blocklist.entries) || [];
  if (!entries.length) {
    byId('blocked-body').innerHTML = '<tr><td colspan="4" class="muted">No blocked IP entries yet.</td></tr>';
    return;
  }

  const canWrite = canMutate(blocklist);
  const rows = entries.map(function (entry) {
    const action = canWrite
      ? `<button class="button secondary" type="button" data-action="unblock" data-scope="${escapeHtml(entry.scope)}" data-ip="${escapeHtml(entry.ip)}">Remove</button>`
      : '<span class="muted">Read only</span>';

    return `
      <tr>
        <td>${escapeHtml(formatScope(entry.scope))}</td>
        <td class="mono mono-wrap">${escapeHtml(entry.ip)}</td>
        <td>${escapeHtml(entry.createdAt || '')}</td>
        <td>${action}</td>
      </tr>
    `;
  }).join('');

  byId('blocked-body').innerHTML = rows;
}

function renderHistory(history, errorText) {
  if (!history || !history.windows) {
    setText('history-overview', 'No history');
    setText('history-note', 'Historical sampling is not configured yet.');
    byId('history-error').textContent = errorText || '';
    byId('history-windows').innerHTML = '<div class="empty">Historical load will appear here when samples have been collected.</div>';
    return;
  }

  setText('history-overview', `Sampling every ${history.sampleIntervalSeconds}s`);
  setText('history-note', `${history.note} Retention: ${history.retentionDays} days.`);

  const errorEl = byId('history-error');
  errorEl.textContent = errorText || '';
  errorEl.className = `status-note ${errorText ? 'error' : ''}`;

  const order = ['24h', '7d', '30d'];
  const html = order.map(function (key) {
    const window = history.windows[key];
    if (!window) {
      return '';
    }

    const coverage = `${Number(window.coveragePercent || 0).toFixed(1)}% coverage`;
    const rows = (window.sites || []).slice(0, 8).map(function (site, index) {
      return `
        <div class="list-item">
          <div class="row">
            <strong>${index + 1}. ${escapeHtml(site.vhost)}</strong>
            <span class="muted">${escapeHtml(site.requestMinutesEstimate)} req-min</span>
          </div>
          <div class="muted">Peak ${escapeHtml(site.peakRequests)} reqs | Avg ${escapeHtml(site.avgConcurrentRequests)} | Max ${escapeHtml(site.maxUniqueClients)} clients</div>
          <div class="sample">${escapeHtml(site.sampleRequest || '')}</div>
        </div>
      `;
    }).join('');

    return `
      <div class="card">
        <div class="row">
          <h2>${escapeHtml(window.label)}</h2>
          <strong>${coverage}</strong>
        </div>
        <div class="muted">${escapeHtml(window.capturedSamples)} / ${escapeHtml(window.expectedSamples)} samples</div>
        ${rows || '<div class="empty">No load samples in this window yet.</div>'}
      </div>
    `;
  }).join('');

  byId('history-windows').innerHTML = html || '<div class="empty">Historical load will appear here when samples have been collected.</div>';
}

function renderBlocklistPanel(blocklist) {
  const adminConfigured = Boolean(blocklist && blocklist.adminKeyConfigured);
  const writeReady = adminConfigured && Boolean(uiState.adminKey);

  setText('blocklist-state', adminConfigured ? (writeReady ? 'Write access ready' : 'Admin key required') : 'Read only');
  setText('blocked-count', blocklist ? blocklist.entryCount ?? 0 : 0);
  setText('blocked-now', `${blocklist ? blocklist.entryCount ?? 0 : 0} entries`);
  setText('apache-include-path', blocklist && blocklist.apacheIncludePath ? blocklist.apacheIncludePath : 'Not configured');
  setText('apache-include-hint', blocklist && blocklist.apacheIncludeHint ? blocklist.apacheIncludeHint : 'Add IncludeOptional for the generated blocklist file on the real server');
  setText('apache-reload-status', blocklist && blocklist.reloadCommandConfigured ? 'Automatic reload configured' : 'Manual Apache reload required');

  const help = !adminConfigured
    ? 'Set admin_key in config.local.php on the real server before write actions can be used.'
    : (writeReady
      ? 'This browser tab can now add and remove IP blocks.'
      : 'Enter the server admin key above to enable block and unblock actions.');
  setText('blocklist-help', help);

  const mutation = byId('mutation-status');
  mutation.textContent = uiState.mutationMessage;
  mutation.className = `status-note ${uiState.mutationError ? 'error' : 'ok'}`;
}

async function mutateBlocklist(action, scope, ip) {
  const label = action === 'block' ? 'block' : 'remove the block for';
  if (!confirm(`Do you want to ${label} ${ip} (${scope})?`)) {
    return;
  }

  try {
    const response = await fetch('stats.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Admin-Key': uiState.adminKey
      },
      body: JSON.stringify({
        action: action === 'block' ? 'block_ip' : 'unblock_ip',
        scope: scope,
        ip: ip
      })
    });
    const payload = await response.json();

    if (!payload.ok) {
      throw new Error(payload.error || 'Mutation failed.');
    }

    const reloadSummary = payload.data && payload.data.apply && payload.data.apply.reload && payload.data.apply.reload.configured
      ? ` Reload: ${payload.data.apply.reload.summary}`
      : '';
    uiState.mutationMessage = (payload.data && payload.data.message ? payload.data.message : 'Blocklist updated.') + reloadSummary;
    uiState.mutationError = false;
    await refreshDashboard();
  } catch (error) {
    uiState.mutationMessage = error.message;
    uiState.mutationError = true;
    renderBlocklistPanel(uiState.latestData ? uiState.latestData.Blocklist : null);
  }
}

async function refreshDashboard() {
  try {
    const response = await fetch(`stats.php?_=${Date.now()}`, { cache: 'no-store' });
    const payload = await response.json();

    if (!payload.ok) {
      throw new Error(payload.error || 'Unknown error');
    }

    const data = payload.data;
    uiState.latestData = data;
    const updated = new Date(payload.timestamp);
    const activeSites = data.ActiveVHosts || [];
    const activeRequests = data.ActiveRequests || [];
    const activePairs = data.ActiveClientPairs || [];
    const blocklist = data.Blocklist || null;
    const apacheCpu = data.ApacheCpuPercent ?? data.CPULoad ?? null;
    const outputCacheHitPercent = data.OutputCacheHitPercent;
    const observedRequestCount = Number(data.ObservedRequestCount || 0);
    const keepaliveRequestCount = Number(data.KeepaliveRequestCount || 0);
    const pressureRequestCount = Number(data.PressureRequestCount || data.ActiveRequestCount || 0);
    const totalAccesses = Number(data.TotalAccesses || 0);
    const requestDelta = previousCounters.totalAccesses === null
      ? 0
      : Math.max(0, totalAccesses - previousCounters.totalAccesses);

    previousCounters.totalAccesses = totalAccesses;

    const cpuSourceMap = {
      mod_status: 'Apache mod_status',
      win_process: 'Windows httpd.exe counters',
      unavailable: 'Unavailable'
    };
    const cacheSourceMap = {
      x_cache_log: 'X-Cache log',
      unavailable: 'No cache log found',
      log_error: 'Cache log read error'
    };

    setText('live-state', 'Live');
    setText('last-update', `Updated ${updated.toLocaleTimeString('da-DK')}`);
    setText('busy', data.BusyWorkers ?? 0);
    setText('busy-sub', `${formatFixed(data.BusyPercent, 1)}% of worker capacity`);
    setText('idle', data.IdleWorkers ?? 0);
    setText('idle-sub', `${formatFixed(data.IdlePercent, 1)}% idle`);
    setText('rps', formatFixed(data.ReqPerSec, 2));
    setText('bytes-per-request', `${formatFixed(data.BytesPerReq, 1)} bytes/request`);
    setText('bps', formatBytesPerSecond(data.BytesPerSec));
    setText('uptime', `Uptime ${formatUptime(data.Uptime)}`);
    setText('active-sites', data.ActiveVHostCount ?? 0);
    setText('active-sites-sub', activeSites.length ? activeSites[0].vhost : 'No websites under load');
    setText('active-requests', pressureRequestCount);
    setText('active-requests-sub', `${keepaliveRequestCount} keepalive / ${Math.max(0, observedRequestCount - pressureRequestCount)} non-pressure`);
    setText('rps-now', formatFixed(data.ReqPerSec, 2));
    setText('cpu-now', formatCpuLoad(apacheCpu));
    setText('requests-chart-now', `${requestDelta} last poll`);
    setText('bps-now', formatBytesPerSecond(data.BytesPerSec));
    setText('cache-hit-now', formatPercent(outputCacheHitPercent, outputCacheHitPercent !== null && outputCacheHitPercent < 1 ? 3 : 2));
    setText('active-sites-now', `${data.ActiveVHostCount ?? 0} websites`);
    setText('requests-now', `${pressureRequestCount} under load`);
    setText('clients-now', `${activePairs.length} website/IP pairs`);
    setText('total-accesses', data.TotalAccesses ?? 0);
    setText('total-kbytes', data.TotalkBytes ?? 0);
    setText('cpu-load', formatCpuLoad(apacheCpu));
    setText('cpu-source', cpuSourceMap[data.ApacheCpuSource] || data.ApacheCpuSource || 'Unknown');
    setText('worker-capacity', data.WorkerCapacity ?? 0);
    setText('server-uptime', formatUptime(data.Uptime));
    setText('cache-hit-summary', formatPercent(outputCacheHitPercent, outputCacheHitPercent !== null && outputCacheHitPercent < 1 ? 3 : 2));
    setText('cache-source', cacheSourceMap[data.OutputCacheSource] || data.OutputCacheSource || 'Unknown');
    setText('cache-decisions', data.OutputCacheDecisions ?? 0);
    setText('observed-requests', observedRequestCount);
    setText('keepalive-requests', keepaliveRequestCount);
    setText('detail-state', data.DetailError ? 'Summary only' : `Summary + load filter (${pressureRequestCount}/${observedRequestCount})`);
    setText('error', data.OutputCacheError ? data.OutputCacheError : (data.DetailError ? data.DetailError : 'None'));

    pushMetric('rps', Number(data.ReqPerSec || 0));
    pushMetric('cpu', Number(apacheCpu || 0));
    pushMetric('requests', requestDelta);
    pushMetric('bps', Number(data.BytesPerSec || 0));
    pushMetric('cache', Number(outputCacheHitPercent || 0));

    renderSparkline('spark-rps', history.rps, '#1d4ed8');
    renderSparkline('spark-cpu', history.cpu, '#b45309');
    renderSparkline('spark-requests', history.requests, '#0f766e');
    renderSparkline('spark-bps', history.bps, '#7c3aed');
    renderSparkline('spark-cache', history.cache, '#c2410c');
    renderScoreboard(data.ScoreboardStates || {});
    renderVHosts(activeSites);
    renderRequests(activeRequests);
    renderClientPairs(activePairs, blocklist);
    renderBlockedEntries(blocklist);
    renderHistory(data.History || null, data.HistoryError || '');
    renderBlocklistPanel(blocklist);
  } catch (error) {
    setText('live-state', 'Error');
    setText('error', error.message);
    uiState.mutationMessage = error.message;
    uiState.mutationError = true;
    renderHistory(uiState.latestData ? uiState.latestData.History : null, error.message);
    renderBlocklistPanel(uiState.latestData ? uiState.latestData.Blocklist : null);
  }
}

byId('admin-key').value = uiState.adminKey;
byId('save-admin-key').addEventListener('click', function () {
  uiState.adminKey = byId('admin-key').value.trim();
  sessionStorage.setItem('serverStatusAdminKey', uiState.adminKey);
  uiState.mutationMessage = uiState.adminKey ? 'Admin key stored in this browser tab.' : 'Admin key cleared.';
  uiState.mutationError = false;
  renderBlocklistPanel(uiState.latestData ? uiState.latestData.Blocklist : null);
});

byId('clear-admin-key').addEventListener('click', function () {
  uiState.adminKey = '';
  byId('admin-key').value = '';
  sessionStorage.removeItem('serverStatusAdminKey');
  uiState.mutationMessage = 'Admin key cleared from this browser tab.';
  uiState.mutationError = false;
  renderBlocklistPanel(uiState.latestData ? uiState.latestData.Blocklist : null);
});

document.addEventListener('click', function (event) {
  const target = event.target.closest('button[data-action]');
  if (!target) {
    return;
  }

  mutateBlocklist(target.getAttribute('data-action'), target.getAttribute('data-scope'), target.getAttribute('data-ip'));
});

refreshDashboard();
setInterval(refreshDashboard, 2000);
</script>
</body>
</html>
