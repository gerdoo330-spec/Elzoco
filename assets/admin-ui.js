/* رفتارهای مشترک پنل مدیریت: آیکون دکمه‌ها، لیست کشویی متحرک، افکت کلیک */
(function () {
  const ICONS = {
    check: 'M20 6L9 17l-5-5',
    x: 'M18 6L6 18M6 6l12 12',
    trash: 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6',
    download: 'M12 3v12M7 10l5 5 5-5M5 21h14',
    print: 'M6 9V2h12v7M6 18H4v-7h16v7h-2M6 14h12v8H6z',
    upload: 'M12 15V3M7 8l5-5 5 5M4 17v3h16v-3',
    logout: 'M9 21H5V3h4M16 17l5-5-5-5M21 12H9',
    user: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M19 8v6M22 11h-6',
    star: 'M12 2l3.1 6.3 6.9 1L17 14l1.2 6.9L12 17.8 5.8 20.9 7 14 2 9.3l6.9-1z',
    plus: 'M12 5v14M5 12h14',
    edit: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
    eraser: 'M20 20H9L3 14l9-9 8 8-4 4M6.5 9.5l8 8'
  };
  const svg = (d) => `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="${d}"/></svg>`;

  function iconFor(text) {
    const t = text.replace(/\s+/g, ' ').trim();
    if (!t) return null;
    if (/ارزیابی|نمره/.test(t)) return 'star';
    if (/^رد(\s|$)/.test(t) || /انصراف|بستن/.test(t)) return 'x';
    if (/حذف/.test(t)) return 'trash';
    if (/دانلود/.test(t)) return 'download';
    if (/چاپ/.test(t)) return 'print';
    if (/بارگذاری/.test(t)) return 'upload';
    if (/خروج/.test(t)) return 'logout';
    if (/انتخاب کارمند/.test(t)) return 'user';
    if (/پاک/.test(t)) return 'eraser';
    if (/حساب جدید|جدید/.test(t)) return 'plus';
    if (/ویرایش/.test(t)) return 'edit';
    if (/ذخیره|ثبت|تأیید|تایید|فعال/.test(t)) return 'check';
    return null;
  }

  function decorateButtons(root) {
    root.querySelectorAll('button.btn:not([data-ico])').forEach(b => {
      b.setAttribute('data-ico', '1');
      if (b.querySelector('.bi')) return;
      const key = iconFor(b.textContent);
      if (!key) return;
      const label = b.innerHTML;
      b.innerHTML = `<span class="bi">${svg(ICONS[key])}</span><span class="bt">${label}</span>`;
    });
  }

  /* افکت کلیک (موج) */
  document.addEventListener('pointerdown', e => {
    const b = e.target.closest && e.target.closest('.btn');
    if (!b || b.disabled) return;
    const r = b.getBoundingClientRect();
    const dot = document.createElement('span');
    const size = Math.max(r.width, r.height) * 1.6;
    dot.style.cssText = `position:absolute;border-radius:50%;pointer-events:none;background:rgba(255,255,255,.45);width:${size}px;height:${size}px;left:${e.clientX - r.left - size / 2}px;top:${e.clientY - r.top - size / 2}px;transform:scale(0);animation:ui-ripple .6s var(--ui-ease) forwards;z-index:-1`;
    b.appendChild(dot);
    setTimeout(() => dot.remove(), 650);
  });
  const rip = document.createElement('style');
  rip.textContent = '@keyframes ui-ripple{to{transform:scale(1);opacity:0}}';
  document.head.appendChild(rip);

  /* لیست کشویی متحرک: منوی آن به body منتقل می‌شود تا هیچ کارت یا جدولی رویش را نگیرد */
  const enhanced = new WeakMap();
  const sig = (sel) => [...sel.options].map(o => o.value + '|' + o.text).join(';');
  let openState = null;

  function placeList(st) {
    const r = st.btn.getBoundingClientRect();
    const gap = 8, vh = window.innerHeight;
    const below = vh - r.bottom - gap - 12;
    const above = r.top - gap - 12;
    const natural = Math.min(st.list.scrollHeight || 300, 300);
    const openUp = below < natural && above > below;
    const space = openUp ? above : below;
    st.list.style.maxHeight = Math.min(300, Math.max(120, space)) + 'px';
    st.list.style.width = r.width + 'px';
    st.list.style.left = Math.max(8, Math.min(r.left, window.innerWidth - r.width - 8)) + 'px';
    st.list.style.top = openUp ? 'auto' : (r.bottom + gap) + 'px';
    st.list.style.bottom = openUp ? (vh - r.top + gap) + 'px' : 'auto';
  }

  function closeAll() {
    if (!openState) return;
    openState.wrap.classList.remove('open');
    openState.list.classList.remove('open');
    openState = null;
  }

  function enhanceSelect(sel) {
    if (enhanced.has(sel) || sel.multiple || sel.size > 1) return;
    const wrap = document.createElement('div');
    wrap.className = 'cs';
    sel.parentNode.insertBefore(wrap, sel);
    sel.classList.add('cs-native');
    sel.style.cssText = 'position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;overflow:hidden;clip-path:inset(50%)';
    sel.tabIndex = -1;
    sel.setAttribute('aria-hidden', 'true');
    wrap.appendChild(sel);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'cs-btn';
    btn.setAttribute('aria-haspopup', 'listbox');
    btn.innerHTML = `<span class="cs-val"></span><span class="cs-chev">${svg('M6 9l6 6 6-6')}</span>`;
    wrap.appendChild(btn);

    const list = document.createElement('ul');
    list.className = 'cs-list';
    list.setAttribute('role', 'listbox');
    document.body.appendChild(list);

    const st = { s: '', btn, list, sel, wrap };
    enhanced.set(sel, st);

    const buildList = () => {
      st.s = sig(sel);
      list.innerHTML = [...sel.options].map((o, i) =>
        `<li role="option" style="--i:${i}" data-v="${o.value.replace(/"/g, '&quot;')}" class="${o.selected ? 'on' : ''}">${o.text.replace(/</g, '&lt;')}</li>`).join('');
    };
    const syncLabel = () => {
      const o = sel.options[sel.selectedIndex];
      btn.querySelector('.cs-val').textContent = o ? o.text : '—';
      list.querySelectorAll('li').forEach(li => li.classList.toggle('on', li.dataset.v === sel.value));
      btn.disabled = sel.disabled;
    };
    st.sync = () => { if (sig(sel) !== st.s) buildList(); syncLabel(); };
    buildList(); syncLabel();

    const open = () => {
      if (openState && openState !== st) closeAll();
      st.sync();
      wrap.classList.add('open');
      list.classList.add('open');
      openState = st;
      placeList(st);
      const cur = list.querySelector('li.on');
      cur && cur.scrollIntoView({ block: 'nearest' });
    };
    const close = () => { if (openState === st) closeAll(); };

    btn.addEventListener('click', () => (openState === st ? close() : open()));
    btn.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); }
      if (e.key === 'Escape') close();
    });
    list.addEventListener('click', e => {
      const li = e.target.closest('li[data-v]');
      if (!li) return;
      sel.value = li.dataset.v;
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      syncLabel(); close(); btn.focus();
    });
  }

  document.addEventListener('click', e => {
    if (!openState) return;
    if (openState.wrap.contains(e.target) || openState.list.contains(e.target)) return;
    closeAll();
  });
  window.addEventListener('scroll', () => { if (openState) placeList(openState); }, true);
  window.addEventListener('resize', () => { if (openState) placeList(openState); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });

  function scan(root) {
    decorateButtons(root);
    root.querySelectorAll('select').forEach(enhanceSelect);
  }

  /* همگام‌سازی برچسب با مقدار واقعی select */
  setInterval(() => {
    document.querySelectorAll('select.cs-native').forEach(sel => {
      const st = enhanced.get(sel);
      if (st) st.sync();
    });
  }, 300);

  const mo = new MutationObserver(() => scan(document.body));
  mo.observe(document.body, { childList: true, subtree: true });
  scan(document.body);
})();
