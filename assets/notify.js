/* اعلان‌های پاپ‌آپ؛ پنل مدیر (scope=admin) و پنل کارمند (scope=employee) */
(function () {
  const scope = window.NOTIFY_SCOPE;
  if (!scope) return;

  const SVG = (p) => `<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">${p}</svg>`;
  /* آیکون‌های خطی یکدست؛ هر نوع اعلان یک آیکون و رنگ مخصوص دارد */
  const KIND = {
    advance: { cls: 'k-advance', label: 'مساعده', icon: SVG('<rect x="2.5" y="6" width="19" height="12" rx="2.5"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9.5v.01M18 14.5v.01"/>') },
    loan: { cls: 'k-loan', label: 'وام', icon: SVG('<path d="M3 9.5 12 4l9 5.5"/><path d="M5 10v7M9.5 10v7M14.5 10v7M19 10v7"/><path d="M3 20h18"/>') },
    leave: { cls: 'k-leave', label: 'مرخصی', icon: SVG('<path d="M21 3 10.5 13.5"/><path d="M21 3l-6.8 18-3.7-7.5L3 9.8 21 3z"/>') },
    promotion: { cls: 'k-promotion', label: 'ارتقا گروه شغلی', icon: SVG('<path d="M3 17l6-6 4 4 7-8"/><path d="M15 7h6v6"/>') },
    default: { cls: 'k-default', label: '', icon: SVG('<path d="M6 8a6 6 0 1 1 12 0c0 6 2.5 7.5 2.5 9.5h-17C3.5 15.5 6 14 6 8z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>') }
  };
  const ICO_CLOSE = SVG('<path d="M6 6l12 12M18 6 6 18"/>');
  const ICO_ARROW = SVG('<path d="M5 12h14M13 6l6 6-6 6"/>');
  const ICO_LATER = SVG('<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>');
  const DUR = 9000;

  const stack = document.createElement('div');
  stack.className = 'np-stack';
  stack.setAttribute('role', 'status');
  stack.setAttribute('aria-live', 'polite');
  document.body.appendChild(stack);

  const esc2 = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function close(el) {
    if (!el || el.classList.contains('out')) return;
    el.classList.add('out');
    setTimeout(() => el.remove(), 420);
  }

  function open(n) {
    if (scope === 'admin') {
      if (typeof window.showSection === 'function' && n.tab) window.showSection(n.tab);
    } else if (n.tab) {
      location.hash = '#' + n.tab;
    }
  }

  function show(n) {
    const k = KIND[n.kind] || KIND.default;
    const el = document.createElement('div');
    el.className = 'np-card ' + (n.tone || 'info') + ' ' + k.cls;
    el.style.setProperty('--dur', DUR + 'ms');
    el.setAttribute('role', 'alert');
    el.innerHTML = `
      <div class="np-ic">${k.icon}</div>
      <div class="np-body">
        <div class="np-head">
          ${k.label ? `<span class="np-tag">${esc2(k.label)}</span>` : ''}
          <span class="np-time">همین حالا</span>
        </div>
        <div class="np-title">${esc2(n.title)}</div>
        ${n.body ? `<div class="np-text">${esc2(n.body)}</div>` : ''}
        <div class="np-act">
          <button class="np-btn" type="button" data-a="go"><span>مشاهده</span><span class="np-arr">${ICO_ARROW}</span></button>
          <button class="np-btn g" type="button" data-a="later"><span class="np-arr">${ICO_LATER}</span><span>بعداً</span></button>
        </div>
      </div>
      <button class="np-x" type="button" data-a="x" aria-label="بستن">${ICO_CLOSE}</button>
      <div class="np-bar"></div>`;
    el.addEventListener('click', (ev) => {
      const a = ev.target.closest('[data-a]');
      if (a && (a.dataset.a === 'x' || a.dataset.a === 'later')) { close(el); return; }
      open(n);
      close(el);
    });
    stack.prepend(el);
    while (stack.children.length > 4) stack.lastElementChild.remove();
    setTimeout(() => {
      if (!el.matches(':hover')) close(el);
      else el.addEventListener('mouseleave', () => close(el), { once: true });
    }, DUR);
  }

  function bump(id, n) {
    const b = document.getElementById(id);
    if (!b) return;
    const had = parseInt(b.textContent, 10) || 0;
    b.style.display = n ? '' : 'none';
    b.textContent = n;
    if (n > had) { b.classList.remove('bump'); void b.offsetWidth; b.classList.add('bump'); }
  }

  /* اعلان ماندگار در سایدبار مدیر: تا زمان باز کردن همان بخش باقی می‌ماند. */
  const ADMIN_TABS = new Set(['advances', 'promotions', 'leaves']);
  const KIND_TAB = { advance: 'advances', loan: 'advances', promotion: 'promotions', leave: 'leaves' };
  const alertStorageKey = `hrm:admin:${String(window.NOTIFY_ADMIN_ID || 'current')}:sidebar-alert-tabs`;
  let pulseTimers = new WeakMap();

  function readAlertTabs() {
    try {
      const raw = localStorage.getItem(alertStorageKey);
      const tabs = raw ? JSON.parse(raw) : [];
      return Array.isArray(tabs) ? tabs.filter(t => ADMIN_TABS.has(t)) : [];
    } catch (_) { return []; }
  }
  function writeAlertTabs(tabs) {
    try { localStorage.setItem(alertStorageKey, JSON.stringify([...new Set(tabs)].filter(t => ADMIN_TABS.has(t)))); }
    catch (_) { /* در صورت غیرفعال بودن storage، نشانگر همان نشست همچنان کار می‌کند. */ }
  }
  function sidebarButton(tab) {
    return ADMIN_TABS.has(tab) ? document.querySelector(`#menu button[data-s="${tab}"]`) : null;
  }
  function markSidebarRequest(tab, persist = true, animate = true) {
    if (scope !== 'admin' || !ADMIN_TABS.has(tab)) return;
    const button = sidebarButton(tab);
    if (!button) return;
    button.classList.add('has-new');
    button.setAttribute('data-request-alert', 'true');
    if (persist) writeAlertTabs([...readAlertTabs(), tab]);
    if (!animate) return;
    button.classList.remove('alert-pulse');
    void button.offsetWidth;
    button.classList.add('alert-pulse');
    const oldTimer = pulseTimers.get(button);
    if (oldTimer) clearTimeout(oldTimer);
    pulseTimers.set(button, setTimeout(() => button.classList.remove('alert-pulse'), 3900));
  }
  function clearSidebarRequestAlert(tab) {
    if (scope !== 'admin' || !ADMIN_TABS.has(tab)) return;
    const button = sidebarButton(tab);
    if (button) {
      button.classList.remove('has-new', 'alert-pulse');
      button.removeAttribute('data-request-alert');
      const timer = pulseTimers.get(button);
      if (timer) clearTimeout(timer);
    }
    writeAlertTabs(readAlertTabs().filter(t => t !== tab));
  }
  if (scope === 'admin') {
    window.clearSidebarRequestAlert = clearSidebarRequestAlert;
    readAlertTabs().forEach(tab => markSidebarRequest(tab, false, false));
  }

  let busy = false;
  async function poll() {
    if (busy || document.hidden) return;
    busy = true;
    try {
      const r = await api(scope, 'notif_poll');
      if (!r || !r.ok) return;
      if (r.pending) { bump('cAdv', r.pending.adv); bump('cLv', r.pending.lv); bump('cPr', r.pending.pr); }
      if (r.items && r.items.length) {
        if (scope === 'admin') {
          r.items.forEach(n => {
            const tab = ADMIN_TABS.has(n.tab) ? n.tab : KIND_TAB[n.kind];
            if (tab) markSidebarRequest(tab, true, true);
          });
        }
        r.items.forEach((n, i) => setTimeout(() => show(n), i * 450));
        await api(scope, 'notif_ack', { ids: r.items.map((x) => x.id) });
        if (typeof window.onNotified === 'function') window.onNotified(r.items);
      }
    } finally { busy = false; }
  }

  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  setInterval(poll, 15000);
  setTimeout(poll, 1200);
  window.pollNotifications = poll;
})();
