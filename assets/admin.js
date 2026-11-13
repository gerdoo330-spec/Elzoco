/* پنل مدیریت */
(function () {
  const csrf = () => (document.querySelector('meta[name="csrf"]') || {}).content || '';
  const adv = { 'در انتظار بررسی': 'b-warn', 'تایید شده': 'b-ok', 'رد شده': 'b-bad' };
  const stage = { direct: 'مدیر مستقیم', general: 'مدیر ارشد', admin: 'منابع انسانی', final: 'تأیید نهایی', rejected: 'رد شده' };
  const stageCls = { direct: 'b-warn', general: 'b-info', admin: 'b-gray', final: 'b-ok', rejected: 'b-bad' };

  window.openModal = (html) => { const mb = document.getElementById('modalBody'); mb.style.maxWidth = ''; mb.style.maxHeight = ''; mb.style.overflow = ''; mb.innerHTML = html; document.getElementById('modal').classList.add('on'); };
  window.closeModal = () => document.getElementById('modal').classList.remove('on');
  document.getElementById('modal').addEventListener('click', (e) => { if (e.target.id === 'modal') closeModal(); });

  window.logout = async () => { await api('admin', 'logout'); location.href = 'index.php'; };

  document.getElementById('menu').addEventListener('click', (e) => {
    const toggle = e.target.closest('button[data-submenu]');
    if (toggle) {
      const group = toggle.closest('.side-group');
      const opening = !group.classList.contains('expanded');
      group.classList.toggle('expanded', opening);
      toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
      const submenu = document.getElementById(toggle.dataset.submenu);
      if (submenu) submenu.setAttribute('aria-hidden', opening ? 'false' : 'true');
      return;
    }
    const b = e.target.closest('button[data-s]');
    if (!b) return;
    showSection(b.dataset.s);
  });

  window.showSection = function (name) {
    document.querySelectorAll('#menu button[data-s]').forEach(b => b.classList.toggle('on', b.dataset.s === name));
    const personnelGroup = document.getElementById('personnelGroup');
    if (personnelGroup) {
      const childActive = ['advances', 'promotions', 'leaves', 'penalties'].includes(name);
      personnelGroup.classList.toggle('expanded', childActive);
      const groupToggle = personnelGroup.querySelector('[data-submenu]');
      const submenu = personnelGroup.querySelector('.side-submenu');
      if (groupToggle) {
        groupToggle.classList.toggle('has-active-child', childActive);
        groupToggle.setAttribute('aria-expanded', childActive ? 'true' : 'false');
      }
      if (submenu) submenu.setAttribute('aria-hidden', childActive ? 'false' : 'true');
    }
    /* با باز شدن بخش مربوط، تأکید اعلان همان بخش پاک می‌شود؛ شمارنده‌ی درخواست‌های در انتظار باقی می‌ماند. */
    if (typeof window.clearSidebarRequestAlert === 'function') window.clearSidebarRequestAlert(name);
    document.querySelectorAll('.sec').forEach(s => s.classList.toggle('on', s.id === 's-' + name));
    if (name === 'dashboard') loadDashboard();
    if (name === 'employees') loadEmployees();
    if (name === 'advances') loadAdvances();
    if (name === 'promotions') loadPromotions();
    if (name === 'leaves') loadLeaves();
    if (name === 'penalties') loadPenalties();
    if (name === 'admins') { loadAdmins(); loadWindows(); }
  };

  /* ---------- داشبورد ---------- */
  /* ---------- داشبورد: «میز امروز» ----------
   * دفتر کل (صف تصمیم‌ها) به‌صورت کارت، فیش آخرین بارگذاری، کارت‌های وضعیت و نوار بازه‌های ماه جاری.
   * حرکت فقط در ورود اولیه‌ی داشبورد و در تغییر واقعی داده‌ها اجرا می‌شود؛ تازه‌سازی‌های بعدی بی‌سروصدا عدد را به‌روز می‌کنند. */
  const dzKey = (j) => j[0] * 1000 + j[1] * 40 + j[2];
  const dzDayLabel = () => {
    try { return new Intl.DateTimeFormat('fa-IR-u-ca-persian', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date()); }
    catch (e) { return ''; }
  };
  const dzPct = (n, total) => (total ? Math.min(100, (n / total) * 100) : 0).toFixed(2) + '%';
  const dzNum = (key, n) => `<b data-key="${key}" data-count="${Number(n) || 0}">${fnum(Number(n) || 0)}</b>`;
  const dzReduce = () => !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);

  /* عدد از from به to می‌شمارد؛ با حرکت کم یا بدون تغییر، فوراً مقدار نهایی نوشته می‌شود */
  function dzTween(el, from, to, dur) {
    if (from === to || dzReduce()) { el.textContent = fnum(to); return; }
    const t0 = performance.now();
    const step = (now) => {
      const k = Math.min(1, (now - t0) / dur);
      const e = 1 - Math.pow(1 - k, 3);
      el.textContent = fnum(Math.round(from + (to - from) * e));
      if (k < 1) requestAnimationFrame(step);
    };
    el.textContent = fnum(from);
    requestAnimationFrame(step);
  }

  function dzCountUp(root, first) {
    const prev = window._dzPrev || {};
    const next = {};
    root.querySelectorAll('[data-key]').forEach((el) => {
      const key = el.dataset.key, to = Number(el.dataset.count) || 0;
      next[key] = to;
      const from = first ? 0 : (key in prev ? prev[key] : to);
      dzTween(el, from, to, first ? 900 : 600);
    });
    window._dzPrev = next;
  }

  function dzLedger(p) {
    const rows = [
      { key: 'adv', label: 'مساعده و وام', n: p.adv, go: 'advances' },
      { key: 'lv', label: 'مرخصی', n: p.lv, go: 'leaves' },
      { key: 'pr', label: 'ارتقای شغلی', n: p.pr, go: 'promotions' },
      { key: 'pen', label: 'جریمه', n: p.pen, go: 'penalties' },
    ];
    const total = rows.reduce((s, x) => s + (Number(x.n) || 0), 0);
    const html = rows.map((x, i) => {
      const n = Number(x.n) || 0;
      return `<button type="button" class="dz-row${n ? ' has-items' : ' is-zero'}" style="--w:${dzPct(n, total)};--i:${i}" onclick="showSection('${x.go}')">
        <span class="dz-row-main">
          <span class="dz-row-label">${n ? '<span class="dz-live" aria-hidden="true"></span>' : ''}${esc(x.label)}</span>
          <span class="dz-leader" aria-hidden="true"></span>
          ${dzNum(x.key, n).replace('<b ', '<b class="dz-row-count" ')}
        </span>
        <span class="dz-bar" aria-hidden="true"><i></i></span>
        <span class="dz-go">باز کردن این صف</span>
      </button>`;
    }).join('');
    return { total, html };
  }

  function dzReceipt(period) {
    if (!period) return '<p class="dz-rcpt-empty">هنوز فایلی بارگذاری نشده است.</p>';
    const rep = period.report;
    if (!rep) return '<p class="dz-rcpt-empty">گزارش سراسری فقط برای مدیران با دسترسی کامل نمایش داده می‌شود.</p>';
    const issues = [
      ['کد ملی نامعتبر', (rep.national_invalid || []).length],
      ['بدون کارکرد این ماه', (rep.missing_work || []).length],
      ['اطلاعات پرسنلی ناقص', (rep.personnel_incomplete || []).length],
      ['حساب تکراری', (rep.duplicates || []).length],
      ['نام نامطابق', (rep.name_mismatch || []).length],
      ['کد ملی ۰۰۰۰ بدون موبایل', (rep.zero_national_no_phone || []).length],
    ];
    const issueSum = issues.reduce((s, x) => s + x[1], 0);
    const lines = [
      ['کارکنان جدید', rep.new || 0, false, 'rc_new'],
      ['به‌روزرسانی‌شده', rep.updated || 0, false, 'rc_upd'],
      ['حذف‌شده از فایل', rep.missing_from_file || 0, false, 'rc_gone'],
      ...issues.map(([l, n], i) => [l, n, n > 0, 'rc_i' + i]),
    ].map(([l, n, bad, key], i) => `<div class="dz-rcpt-line${bad ? ' is-issue' : ''}" style="--i:${i}"><span>${esc(l)}</span><span class="dz-leader" aria-hidden="true"></span>${dzNum(key, n)}</div>`).join('');
    return `${lines}
      <div class="dz-rcpt-sum${issueSum ? '' : ' is-clean'}"><span>موارد نیازمند بررسی</span>${dzNum('rc_sum', issueSum)}</div>
      <p class="dz-rcpt-foot">${esc(period.file || '')} · ${esc(period.uploaded_at || '')}</p>`;
  }

  /** یک ردیف نوار ماه: بازه‌ی ثبت یا مشاهده‌ی فیش روی روزهای ماه جاری */
  function dzWindowRow(label, w, today, days, i) {
    const head = `<span class="dz-wlabel">${esc(label)}</span>`;
    if (!w || !w.start || !w.end) {
      return `<div class="dz-gantt-row" style="--i:${i}">${head}<span class="dz-gantt-note">تعیین نشده؛ همیشه باز</span><span></span></div>`;
    }
    const s = window.JDate.parse(w.start), e = window.JDate.parse(w.end);
    const monthFirst = dzKey([today[0], today[1], 1]);
    const monthLast = dzKey([today[0], today[1], days]);
    const state = w.open ? '<span class="dz-wstate is-open">باز</span>' : '<span class="dz-wstate is-closed">بسته</span>';
    const info = `<small>${esc(w.start)} تا ${esc(w.end)}</small>`;
    if (!s || !e || dzKey(e) < monthFirst || dzKey(s) > monthLast) {
      return `<div class="dz-gantt-row" style="--i:${i}"><span class="dz-wlabel">${esc(label)}${info}</span><span class="dz-gantt-note">خارج از این ماه</span>${state}</div>`;
    }
    const sDay = dzKey(s) < monthFirst ? 1 : s[2];
    const eDay = dzKey(e) > monthLast ? days : e[2];
    const a = ((sDay - 1) / days) * 100;
    const b = ((days - eDay) / days) * 100;
    const t = ((today[2] - 0.5) / days) * 100;
    return `<div class="dz-gantt-row" style="--i:${i}">
      <span class="dz-wlabel">${esc(label)}${info}</span>
      <div class="dz-track" style="--days:${days}">
        <span class="dz-span${w.open ? '' : ' is-closed'}" style="--a:${a.toFixed(2)}%;--b:${b.toFixed(2)}%"></span>
        <span class="dz-today" style="--t:${t.toFixed(2)}%" data-day="${fnum(today[2])}"></span>
      </div>${state}</div>`;
  }

  function dzWindows(w) {
    if (!window.JDate) return '';
    const today = window.JDate.toJ(new Date());
    const days = window.JDate.daysInMonth(today[0], today[1]);
    return dzWindowRow('ثبت مساعده', w.advance, today, days, 0)
      + dzWindowRow('ثبت وام', w.loan, today, days, 1)
      + dzWindowRow('مشاهده‌ی فیش', w.payslip, today, days, 2);
  }

  function dzDetails(rep) {
    if (!rep) return '';
    const list = (arr, fmt) => (arr && arr.length) ? `<ul>${arr.map(fmt).join('')}</ul>` : '<p>موردی برای گزارش نیست.</p>';
    const sections = [
      ['کارکنان بدون کارکرد ماه', rep.missing_work, n => `<li>${esc(n)}</li>`],
      ['نام‌های نامطابق یا دارای فاصله', rep.name_mismatch, x => `<li>${esc(x.name)} — ${esc(x.reason)}</li>`],
      ['حساب‌های تکراری', rep.duplicates, d => `<li>${esc(d.name)} — ردیف‌ها: ${esc(d.rows.join('، '))} — انتخاب‌شده: ردیف ${esc(d.chosen_row)} (${esc(d.status)})</li>`],
      ['اطلاعات پرسنلی ناقص', rep.personnel_incomplete, x => `<li>${esc(x.name)} — ردیف ${esc(x.row)}</li>`],
      ['کد ملی نامعتبر', rep.national_invalid, x => `<li>${esc(x.name)} — مقدار: ${esc(x.raw || '—')}</li>`],
      ['کد ملی ۰۰۰۰ بدون موبایل', rep.zero_national_no_phone, n => `<li>${esc(n)}</li>`],
    ];
    return `<div class="dz-report-grid">${sections.map(([title, arr, fmt], i) => `
      <details class="dz-details" style="--i:${i}"${(arr || []).length && title.includes('ناقص') ? ' open' : ''}>
        <summary><span>${esc(title)}</span>${dzNum('d' + i, (arr || []).length)}</summary>
        ${list(arr, fmt)}
      </details>`).join('')}</div>`;
  }

  window.loadDashboard = async function () {
    const r = await api('admin', 'dashboard');
    if (!r.ok) return toast(r.message, 'bad');
    const c = r.counts, p = r.pending, w = r.windows;
    const badge = (id, n) => { const el = document.getElementById(id); el.style.display = n ? '' : 'none'; el.textContent = n; };
    badge('cAdv', p.adv); badge('cLv', p.lv); badge('cPr', p.pr); badge('cPen', p.pen);

    const ledger = dzLedger(p);
    const rep = r.latest_period && r.latest_period.report;
    const period = r.latest_period;
    const active = Number(c.active) || 0, total = Number(c.total) || 0;
    const headline = ledger.total ? `${fnum(ledger.total)} درخواست منتظر تصمیم شماست` : 'صف تصمیم شما خالی است';
    const first = !window._dzRendered;
    window._dzRendered = true;

    const root = document.getElementById('dash');
    root.innerHTML = `
      <div class="dz${first ? '' : ' is-steady'}">
        <header class="dz-head">
          <div>
            <p class="dz-date">${esc(dzDayLabel())}</p>
            <h2 class="dz-title">${headline}</h2>
            <p class="dz-sub">فقط درخواست‌هایی شمرده می‌شوند که در مرحله‌ی تصمیم شما هستند.</p>
          </div>
        </header>

        <div class="dz-main">
          <section aria-label="صف تصمیم‌ها">
            <div class="dz-ledger-head"><span>نوع درخواست</span><span>در انتظار</span></div>
            <div class="dz-ledger">${ledger.html}</div>
            <div class="dz-total"><span>جمع</span><span class="dz-leader" aria-hidden="true"></span>${dzNum('total', ledger.total)}</div>
          </section>

          <aside aria-label="آخرین بارگذاری اکسل">
            <div class="dz-receipt">
              <div class="dz-rcpt-head"><b>آخرین بارگذاری اکسل</b><span>${period ? esc(period.label) : '—'}</span></div>
              <div style="margin-top:6px">${dzReceipt(period)}</div>
            </div>
          </aside>
        </div>

        <div class="dz-strip" role="list">
          <div class="dz-stat" role="listitem" style="--i:0">${dzNum('c_total', total)}<span>کل کارکنان ثبت‌شده</span></div>
          <div class="dz-stat" role="listitem" style="--i:1">${dzNum('c_active', active)}<span>فعال</span>
            <div class="dz-share" aria-hidden="true"><i style="--share:${dzPct(active, total)}"></i></div></div>
          <div class="dz-stat" role="listitem" style="--i:2">${dzNum('c_inactive', c.inactive)}<span>غیرفعال یا خارج از فایل</span></div>
          <div class="dz-stat${Number(c.incomplete) ? ' is-warn' : ''}" role="listitem" style="--i:3">${dzNum('c_incomplete', c.incomplete)}<span>اطلاعات پرسنلی ناقص</span></div>
        </div>

        <h2 class="dz-section-title">بازه‌های ماه جاری</h2>
        <p class="dz-section-sub">نوار هر بازه روی روزهای این ماه نشان داده می‌شود. خط قرمز امروز است.</p>
        <div class="dz-gantt">${dzWindows(w)}</div>

        ${rep ? `<h2 class="dz-section-title">جزئیات گزارش بارگذاری</h2>${dzDetails(rep)}` : ''}
      </div>`;
    dzCountUp(root, first);
  };

  /* داشبورد زنده: هر ۴۵ ثانیه اگر داشبورد باز است، داده‌ها تازه می‌شوند */
  setInterval(() => {
    const on = document.querySelector('#menu button.on');
    if (on && on.dataset.s === 'dashboard' && !document.hidden) loadDashboard();
  }, 45000);

  /* ---------- آپلود (فقط مدیران با دسترسی کامل) ---------- */
  const dz = document.getElementById('dz');
  const uploadForm = document.getElementById('upForm');
  if (dz && uploadForm) {
    const fileInput = dz.querySelector('input[type=file]');
    fileInput.addEventListener('change', () => { document.getElementById('fname').textContent = fileInput.files[0] ? fileInput.files[0].name : ''; });
    ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('over'); }));
    dz.addEventListener('drop', (e) => { fileInput.files = e.dataTransfer.files; fileInput.dispatchEvent(new Event('change')); });

    uploadForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const btn = document.getElementById('upBtn');
      const fd = new FormData(ev.target);
      fd.append('action', 'upload');
      setBusy(btn, true);
      let r;
      try {
        const res = await fetch('../api/admin.php', { method: 'POST', headers: { 'X-CSRF-Token': csrf() }, body: fd, credentials: 'same-origin' });
        r = await res.json();
      } catch (e) { r = { ok: false, message: 'ارسال فایل ناموفق بود.' }; }
      setBusy(btn, false, 'بارگذاری و به‌روزرسانی');
      toast(r.message, r.ok ? 'ok' : 'bad');
      if (r.ok) renderUploadReport(r.report);
    });
  }

  function renderUploadReport(rep) {
    const li = (arr, fmt) => arr && arr.length ? `<ul class="list-report">${arr.map(fmt).join('')}</ul>` : '<div class="hint">✓ مورد نیست</div>';
    document.getElementById('upReport').innerHTML = `
      <div class="grid g3" style="gap:10px;margin-bottom:14px">
        <div class="stat"><span class="v">${fnum(rep.employees)}</span><span class="l">کارمند یکتا</span></div>
        <div class="stat"><span class="v" style="color:#15803d">${fnum(rep.active)}</span><span class="l">فعال</span></div>
        <div class="stat"><span class="v" style="color:#b91c1c">${fnum(rep.inactive)}</span><span class="l">غیرفعال</span></div>
        <div class="stat"><span class="v">${fnum(rep.new)}</span><span class="l">جدید</span></div>
        <div class="stat"><span class="v">${fnum(rep.updated)}</span><span class="l">به‌روزشده</span></div>
        <div class="stat"><span class="v">${fnum(rep.missing_from_file)}</span><span class="l">خارج از فایل اخیر (سوابق حفظ شده)</span></div>
        <div class="stat"><span class="v">${fnum(rep.profile_data_updated || 0)}</span><span class="l">پرونده دارای فیلدهای تکمیلی</span></div>
        <div class="stat"><span class="v">${fnum(rep.preserved_existing_fields || 0)}</span><span class="l">مقدار قبلی حفظ‌شده</span></div>
      </div>
      ${(rep.profile_fields_detected || []).length ? `<div class="alert ok">فیلدهای تکمیلی شناسایی‌شده از اکسل: ${rep.profile_fields_detected.map(k => esc(({father_name:'نام پدر',birth_date:'تاریخ تولد',birth_place:'محل تولد',gender:'جنسیت',marital_status:'وضعیت تأهل',children_count:'تعداد فرزند',education:'مدرک تحصیلی',field_of_study:'رشته تحصیلی',address:'نشانی',postal_code:'کد پستی',emergency_phone:'تماس اضطراری',bank_name:'نام بانک',bank_account:'شماره حساب',bank_card:'شماره کارت',insurance_number:'شماره بیمه',employment_type:'نوع استخدام',branch:'شعبه',section:'سکشن / واحد کاری'})[k] || k)).join('، ')}</div>` : '<div class="hint">در این فایل، فقط ستون‌های اصلی شناخته‌شده شیت اطلاعات پایه خوانده شدند. برای اتصال فیلدهای هویتی بیشتر، نمونه اکسل را با عنوان ستون‌ها بررسی می‌کنیم.</div>'}
      <h3>کارکرد ماه ندارند</h3>${li(rep.missing_work, n => `<li>${esc(n)}</li>`)}
      <h3>نام‌های نامطابق</h3>${li(rep.name_mismatch, x => `<li>${esc(x.name)} — ${esc(x.reason)}</li>`)}
      <h3>تکراری‌ها</h3>${li(rep.duplicates, d => `<li>${esc(d.name)} ← ردیف ${esc(d.chosen_row)} (${esc(d.status)})</li>`)}
      <h3>اطلاعات پرسنلی ناقص</h3>${li(rep.personnel_incomplete, x => `<li>${esc(x.name)} — ردیف ${esc(x.row)}</li>`)}
      <h3>کد ملی نامعتبر</h3>${li(rep.national_invalid, x => `<li>${esc(x.name)} — ${esc(x.raw || '—')}</li>`)}
      <h3>خطای سلول در فایل</h3>${li(rep.error_cells, c => `<li class="ltr">${esc(c)}</li>`)}`;
    showSection('dashboard');
  }

  /* ---------- کارمندان ---------- */
  window.loadEmployees = async function () {
    const sectionSelect = document.getElementById('esSection');
    const selectedSection = sectionSelect ? sectionSelect.value : '';
    const r = await api('admin', 'employees', {
      q: document.getElementById('eq').value,
      filter: document.getElementById('ef').value,
      section: selectedSection
    });
    if (!r.ok) return toast(r.message, 'bad');

    // گزینه‌های سکشن فقط از مجموعه‌ی مجاز همین حساب ساخته می‌شوند؛ فهرست کل سازمان به مدیر محدود نمایش داده نمی‌شود.
    if (sectionSelect) {
      const allowedSections = Array.isArray(r.sections) ? r.sections : [];
      const keep = selectedSection === '__none__' || allowedSections.includes(selectedSection) ? selectedSection : '';
      sectionSelect.innerHTML = '<option value="">همه سکشن‌ها</option><option value="__none__">بدون سکشن ثبت‌شده</option>' + allowedSections.map(v => `<option value="${esc(v)}">${esc(v)}</option>`).join('');
      sectionSelect.value = keep;
      const sectionHint = document.getElementById('sectionHint');
      if (sectionHint) sectionHint.textContent = allowedSections.length
        ? `سکشن‌های قابل مشاهده برای این حساب: ${allowedSections.join('، ')}`
        : 'هنوز سکشنی در پرونده کارکنان ثبت نشده است؛ عنوان ستون اکسل باید «سکشن»، «بخش» یا «واحد کاری» باشد.';
    }

    window._emps = r.employees;
    const status = (e) => {
      if (!e.in_latest) return '<span class="badge b-gray">خارج از فایل</span>';
      if (e.inactive) return '<span class="badge b-bad">غیرفعال</span>';
      return '<span class="badge b-ok">فعال</span>';
    };
    const rows = r.employees.map(e => `<tr>
        <td data-label="">${IS_ADMIN && e.personnel_code ? `<input type="checkbox" class="esel" value="${e.id}" ${e.personnel_ok ? '' : 'disabled'} onchange="onEmpSelChange()">` : ''}</td>
        <td data-label="نام"><b>${esc(e.name)}</b> ${e.personnel_ok ? '' : '<span class="badge b-warn">ناقص</span>'}</td>
        <td data-label="کد پرسنلی" class="ltr">${esc(e.personnel_code || '—')}</td>
        <td data-label="کد ملی" class="ltr">${esc(e.national_code === '0000000000' ? '0000000000' : e.national_code || '—')}</td>
        <td data-label="موبایل" class="ltr">${esc(e.phone || '—')}</td><td data-label="پست">${esc(e.post || '')}</td><td data-label="سکشن">${esc(e.section_name || '—')}</td><td data-label="وضعیت">${status(e)}</td>
        <td data-label="مدیر مستقیم"><div class="manager-cell">${e.manager_name ? `<span class="badge b-info"> ${esc(e.manager_name)} </span>` : '<span class="badge b-gray">تعیین نشده</span>'}<small class="ltr hint">${esc(e.manager_pc || '')}</small>${IS_ADMIN && e.personnel_ok ? `<button class="btn sm ghost" onclick="editManager(${e.id})">تغییر مدیر</button>` : ''}</div></td>
        <td data-label="پرونده / فیش"><div class="row">${IS_ADMIN ? `<button class="btn sm" onclick="viewEmployeeHistory(${e.id})">پرونده پرسنلی</button>` : ''}<button class="btn sm ghost" onclick="viewPayslips(${e.id})">مشاهده فیش</button></div></td></tr>`).join('');
    document.getElementById('etable').innerHTML = `<thead><tr class="hd">${IS_ADMIN ? '<th><input type="checkbox" title="انتخاب همه" onchange="toggleAllEmp(this.checked)"></th>' : '<th></th>'}<th>نام</th><th>کد پرسنلی</th><th>کد ملی</th><th>موبایل</th><th>پست</th><th>سکشن</th><th>وضعیت</th><th>مدیر مستقیم</th><th>پرونده / فیش</th></tr></thead><tbody>` +
      (rows || '<tr><td colspan="10" class="empty">موردی پیدا نشد.</td></tr>') + '</tbody>';
    onEmpSelChange();
  };

  /* ---------- انتخاب کارمند یا مدیر (جستجو به‌جای تایپ کد پرسنلی) ---------- */
  window._spCb = null;
  window.pickEmployee = function (cb, opts = {}) {
    window._spCb = cb;
    window._spFilter = opts.filter || 'active';
    openModal(`<h2>${esc(opts.title || 'انتخاب کارمند')}</h2>
      <input id="spq" placeholder="جستجوی نام یا کد پرسنلی" autocomplete="off">
      <div id="spRes" style="margin-top:12px;max-height:50vh;overflow:auto"></div>
      <div class="row" style="margin-top:12px">${opts.allowNone ? `<button class="btn ghost" type="button" onclick="spPick(null)">${esc(opts.noneLabel || 'بدون انتخاب')}</button>` : ''}<button class="btn ghost" type="button" onclick="closeModal()">انصراف</button></div>`);
    const q = document.getElementById('spq');
    let t;
    q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => spSearch(q.value), 250); });
    q.focus();
    spSearch('');
  };
  window.spSearch = async function (q) {
    const r = await api('admin', 'employees', { q, filter: window._spFilter || 'active' });
    const box = document.getElementById('spRes');
    if (!box) return;
    if (!r.ok) { box.innerHTML = `<div class="alert">${esc(r.message)}</div>`; return; }
    const list = r.employees.filter(e => e.personnel_code);
    window._spList = list;
    box.innerHTML = list.length ? list.map(e => `<div class="row" style="justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--line)">
        <div><b>${esc(e.name)}</b> <span class="ltr hint">${esc(e.personnel_code)}</span> ${e.has_account ? '<span class="badge b-ok">حساب مدیریت دارد</span>' : '<span class="badge b-warn">بدون حساب مدیریت</span>'}</div>
        <button class="btn sm" type="button" onclick="spPick(${e.id})">انتخاب</button></div>`).join('') : '<div class="empty">موردی پیدا نشد.</div>';
  };
  window.spPick = function (id) {
    const cb = window._spCb;
    window._spCb = null;
    const e = id === null ? null : ((window._spList || []).find(x => x.id === id) || null);
    closeModal();
    if (cb) cb(e);
  };

  /* ---------- مدیر مستقیم: تکی و گروهی ---------- */
  window.selectedEmpIds = () => [...document.querySelectorAll('.esel:checked')].map(x => Number(x.value));
  window.onEmpSelChange = function () {
    const n = selectedEmpIds().length;
    const bar = document.getElementById('bulkBar');
    if (!bar) return;
    bar.style.display = n ? 'flex' : 'none';
    document.getElementById('bulkCount').textContent = n;
  };
  window.toggleAllEmp = function (on) {
    document.querySelectorAll('.esel:not(:disabled)').forEach(x => { x.checked = on; });
    onEmpSelChange();
  };
  window.bulkSetManager = function () {
    const ids = selectedEmpIds();
    if (!ids.length) return toast('ابتدا کارمندان را انتخاب کنید.', 'bad');
    pickEmployee(sup => applyManager(ids, sup), { title: `مدیر مستقیم برای ${ids.length} کارمند`, allowNone: true, noneLabel: 'بدون مدیر مستقیم' });
  };
  window.editManager = function (id, refreshDossier = false) {
    const e = (window._emps || []).find(x => Number(x.id) === Number(id)) || (window._currentEmployeeDossier?.employee || { name: '' });
    pickEmployee(sup => applyManager([id], sup, refreshDossier), { title: `تغییر مدیر مستقیم «${e.name}»`, allowNone: true, noneLabel: 'بدون مدیر مستقیم' });
  };
  window.applyManager = async function (ids, sup, refreshDossier = false) {
    const r = await api('admin', 'set_manager_bulk', { ids, supervisor_id: sup ? sup.id : 0 });
    toast(r.message, r.ok ? 'ok' : 'bad');
    if (r.ok) {
      await loadEmployees();
      if (refreshDossier && ids.length === 1) await viewEmployeeHistory(Number(ids[0]));
    }
  };

  /* ---------- پرونده جامع پرسنلی کارمند ---------- */
  window.viewEmployeeHistory = async function (id) {
    openModal('<div class="employee-dossier-loading"><div class="employee-dossier-spinner"></div><b>در حال دریافت پرونده پرسنلی…</b><small>اطلاعات هویتی، حقوقی و سوابق درخواست‌ها در حال بارگذاری است.</small></div>');
    const mb = document.getElementById('modalBody');
    mb.style.maxWidth = '1180px'; mb.style.maxHeight = '92vh'; mb.style.overflow = 'auto';
    const r = await api('admin', 'employee_dossier', { employee_id: Number(id) });
    if (!r.ok) { closeModal(); return toast(r.message || 'دریافت پرونده پرسنلی ناموفق بود.', 'bad'); }
    const d = r.dossier || {};
    window._currentEmployeeDossier = d;
    const emp = d.employee || {};
    const escv = (v, fallback = 'ثبت نشده') => esc(v === null || v === undefined || String(v).trim() === '' ? fallback : String(v));
    const cash = (v) => `${fnum(Math.round(Number(v) || 0))} ریال`;
    const kv = (label, value, extra = '') => `<div class="employee-dossier-kv ${extra}"><small>${label}</small><b>${value}</b></div>`;
    const badge = (label, cls = 'b-gray') => `<span class="badge ${cls}">${label}</span>`;
    const statusLabel = (stage, status = '') => {
      if (stage === 'final') return badge('تأیید نهایی', 'b-ok');
      if (stage === 'rejected') return badge('رد نهایی', 'b-bad');
      if (stage === 'admin') return badge('در انتظار منابع انسانی', 'b-warn');
      if (stage === 'general') return badge('نزد مدیر ارشد', 'b-info');
      if (stage === 'direct') return badge('نزد مدیر مستقیم', 'b-warn');
      if (stage === 'evaluated') return badge('ارزیابی شده؛ منتظر تصمیم', 'b-info');
      return badge(esc(status || 'وضعیت نامشخص'));
    };
    const tableEmpty = (cols, text) => `<tr><td colspan="${cols}" class="empty">${text}</td></tr>`;
    const listTable = (headers, rows, emptyText) => `<div class="employee-dossier-table-wrap"><table class="employee-dossier-table"><thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead><tbody>${rows || tableEmpty(headers.length, emptyText)}</tbody></table></div>`;

    const isActive = !!emp.in_latest && !emp.inactive;
    const activeBadge = isActive ? badge('همکاری فعال', 'b-ok') : badge(emp.inactive ? 'غیرفعال' : 'خارج از آخرین فایل', 'b-gray');
    const periods = Array.isArray(d.payslips) ? d.payslips : [];
    const advances = Array.isArray(d.advances) ? d.advances : [];
    const leaves = Array.isArray(d.leaves) ? d.leaves : [];
    const promotions = Array.isArray(d.promotions) ? d.promotions : [];
    const history = Array.isArray(d.history) ? d.history : [];
    const bankName = String(d.bank?.sheba || '').trim() ? (window.shebaBankName ? (window.shebaBankName(d.bank.sheba) || 'قابل تشخیص نیست') : '—') : 'ثبت نشده';

    const extraIdentity = (d.profile_fields || []).filter(x => !['bank_name','bank_account','bank_card'].includes(x.key));
    const identityPane = `<div class="employee-dossier-grid">
      ${kv('نام و نام خانوادگی', escv(emp.name))}
      ${kv('کد پرسنلی', `<span class="ltr">${escv(emp.personnel_code)}</span>`)}
      ${kv('کد ملی', `<span class="ltr">${escv(emp.national_code)}</span>`)}
      ${kv('شماره همراه', `<span class="ltr">${escv(emp.phone)}</span>`)}
      ${kv('عنوان شغلی فعلی', escv(emp.post))}
      ${kv('تاریخ شروع همکاری', `<span class="ltr">${escv(emp.hire_date)}</span>`)}
      ${kv('کد تاریخ شروع ثبت‌شده', `<span class="ltr">${escv(emp.start_code)}</span>`)}
      ${kv('مدیر مستقیم', emp.manager_name ? `<span class="badge b-info">${esc(emp.manager_name)}</span>` : escv('', emp.manager_pc ? 'نام مدیر در اطلاعات کارکنان پیدا نشد' : 'تعیین نشده'))}
      ${kv('کد پرسنلی مدیر مستقیم', `<span class="ltr">${escv(emp.manager_pc)}</span>`)}
      ${IS_ADMIN ? `<div class="employee-dossier-manager-action"><button class="btn sm" type="button" onclick="editManager(${Number(emp.id)},true)">تغییر مدیر مستقیم</button><small>این تغییر در رابطه مدیریتی و مسیر ارجاع درخواست‌های جدید اعمال می‌شود.</small></div>` : ''}
      ${extraIdentity.map(x => kv(esc(x.label), escv(x.value))).join('')}
      ${kv('وضعیت اطلاعات پرسنلی', emp.personnel_ok ? badge('اطلاعات پایه کامل', 'b-ok') : badge('نیازمند تکمیل اطلاعات پایه', 'b-warn'))}
      ${kv('وضعیت همکاری', activeBadge)}
    </div>
    <div class="employee-dossier-note"><b>منبع اطلاعات:</b> اطلاعات موجود از شیت «اطلاعات پایه» خوانده شده است. فیلدهایی که در فایل نباشند یا خالی باشند، اطلاعات قبلی پرونده را پاک نمی‌کنند.</div>`;

    const bankExtras = [
      d.bank?.bank_account ? kv('شماره حساب', `<span class="ltr">${esc(d.bank.bank_account)}</span>`) : '',
      d.bank?.bank_card ? kv('شماره کارت', `<span class="ltr">${esc(d.bank.bank_card)}</span>`) : '',
    ].join('');
    const bankPane = `<div class="employee-dossier-bank-grid">
      <div class="employee-dossier-bank-card"><span class="employee-dossier-bank-icon">بانک</span><small>نام بانک</small><strong>${escv(d.bank?.bank_name || bankName, 'ثبت نشده')}</strong><span class="employee-dossier-muted">در صورت نبود نام بانک در اکسل، از روی شماره شبا تشخیص داده می‌شود.</span></div>
      <div class="employee-dossier-bank-card"><span class="employee-dossier-bank-icon">IR</span><small>شماره شبای ثبت‌شده</small><strong class="ltr employee-dossier-sheba">${escv(d.bank?.sheba)}</strong><span class="employee-dossier-muted">شماره شبا از آخرین اطلاعات کامل ثبت‌شده خوانده می‌شود.</span></div>
    </div>
    ${bankExtras ? `<div class="employee-dossier-grid compact">${bankExtras}</div>` : ''}
    <div class="employee-dossier-note">فیلدهای بانکی تکمیلی در صورتی نمایش داده می‌شوند که در سرفصل‌های فایل اکسل شناسایی شوند.</div>`;

    const payroll = d.payroll || {};
    const latest = payroll.latest_breakdown;
    const breakdownHtml = latest ? `<div class="employee-dossier-split">
      <div class="employee-dossier-subcard"><h4>اجزای پرداخت · ${esc(latest.period)}</h4>${latest.pay.map(x => `<div class="employee-dossier-line"><span>${esc(x.label)}</span><b>${cash(x.amount)}</b></div>`).join('')}</div>
      <div class="employee-dossier-subcard"><h4>اجزای کسورات · ${esc(latest.period)}</h4>${latest.deductions.map(x => `<div class="employee-dossier-line"><span>${esc(x.label)}</span><b>${cash(x.amount)}</b></div>`).join('')}</div>
    </div>` : '<div class="empty">برای این کارمند هنوز فیش دارای کارکرد ثبت نشده است.</div>';
    const payrollPane = `<div class="employee-dossier-kpis">
      <div><small>حقوق ثبت‌شده فعلی</small><b>${cash(payroll.current_salary)}</b></div>
      <div><small>جمع افزایش حقوق‌های ارتقا</small><b>${cash(payroll.promotion_increase_total)}</b></div>
      <div><small>عنوان شغلی فعلی</small><b>${escv(payroll.current_post)}</b></div>
      <div><small>آخرین عنوان شغلی مصوب ارتقا</small><b>${escv(payroll.promotion_post)}</b></div>
    </div>${breakdownHtml}<div class="employee-dossier-note">مبلغ حقوق فعلی از رکورد جاری کارمند خوانده می‌شود. تغییرات ناشی از ارتقا بسته به سیاست «ثبت در سامانه / فایل اکسل» ممکن است در بارگذاری دوره‌ای اعمال شوند.</div>`;

    const periodRows = periods.map(p => `<tr>
      <td><b>${esc(p.label)}</b></td><td>${p.has_work ? badge('کارکرد ثبت شده', 'b-ok') : badge('بدون کارکرد', 'b-gray')}</td>
      <td>${p.has_work ? cash(p.gross) : '—'}</td><td>${p.has_work ? cash(p.deductions) : '—'}</td><td><b>${p.has_work ? cash(p.net) : '—'}</b></td>
      <td><button class="btn sm ghost" type="button" ${p.has_work ? `onclick="loadDossierSlip(${Number(emp.id)},${Number(p.id)})"` : 'disabled'}>مشاهده فیش</button></td>
    </tr>`).join('');
    const payslipsPane = `${listTable(['دوره فیش', 'وضعیت کارکرد', 'جمع پرداخت', 'جمع کسورات', 'خالص پرداختی', 'جزئیات'], periodRows, 'برای این کارمند دوره فیش ثبت نشده است.')}
      <div class="employee-dossier-slip-preview" id="dossierSlipPreview"><div class="empty">برای مشاهده جزئیات، دوره موردنظر را انتخاب کنید.</div></div>`;

    const financeRows = (kind) => advances.filter(x => x.kind === kind).map(x => `<tr>
      <td><b>${esc(x.tracking)}</b><small>${kind === 'loan' ? 'وام' : 'مساعده'}</small></td>
      <td>${escv(x.jdate)}</td><td>${cash(x.amount)}</td><td>${x.approved_amount === null ? '—' : cash(x.approved_amount)}</td>
      <td>${statusLabel(x.stage, x.status)}</td><td>${escv(x.decided_by)}</td>
    </tr>`).join('');
    const financePane = `<div class="employee-dossier-section-block"><div class="employee-dossier-section-head"><h3>سوابق مساعده</h3><span>${fnum(advances.filter(x => x.kind === 'advance').length)} درخواست</span></div>
      ${listTable(['کد پیگیری', 'تاریخ ثبت', 'مبلغ درخواستی', 'مبلغ تأییدشده', 'وضعیت', 'تصمیم‌گیرنده'], financeRows('advance'), 'درخواستی برای مساعده ثبت نشده است.')}</div>
      <div class="employee-dossier-section-block"><div class="employee-dossier-section-head"><h3>سوابق وام</h3><span>${fnum(advances.filter(x => x.kind === 'loan').length)} درخواست</span></div>
      ${listTable(['کد پیگیری', 'تاریخ ثبت', 'مبلغ درخواستی', 'مبلغ تأییدشده', 'وضعیت', 'تصمیم‌گیرنده'], financeRows('loan'), 'درخواستی برای وام ثبت نشده است.')}</div>`;

    const leaveRows = leaves.map(x => `<tr>
      <td><b>${esc(x.tracking)}</b><small>${escv(x.type_label)}</small></td>
      <td><span class="ltr">${escv(x.from_date)}</span>${x.to_date && x.to_date !== x.from_date ? ` تا <span class="ltr">${esc(x.to_date)}</span>` : ''}${x.start_time ? `<small class="ltr">${esc(x.start_time)} تا ${esc(x.end_time)}</small>` : ''}</td>
      <td>${fnum(x.amount)} ${x.type_key === 'hourly' ? 'ساعت' : 'روز'}</td><td>${statusLabel(x.stage)}</td>
      <td class="employee-dossier-reason">${escv(x.reason)}</td>
    </tr>`).join('');
    const leaveBalance = d.leave_balance || {};
    const leaveBalancePane = leaveBalance.has_hire_date ? `<div class="employee-dossier-leave-balance">
      <div class="employee-dossier-leave-balance-head"><div><b>مانده مرخصی استحقاقی تا پایان سال ${esc(leaveBalance.year_label)}</b><small>سهم سالانه ۲۶ روز؛ برای سال شروع همکاری به نسبت روزهای اشتغال محاسبه شده است.</small></div><span class="employee-dossier-leave-balance-date">شروع: ${esc(leaveBalance.hire_date)}</span></div>
      <div class="employee-dossier-leave-balance-grid">
        <div><small>سهم استحقاقی تا پایان سال</small><b>${fnum(leaveBalance.entitlement)} روز</b></div>
        <div><small>مصرف قطعی (تأیید نهایی)</small><b>${fnum(leaveBalance.used)} روز</b></div>
        <div><small>در انتظار تأیید</small><b>${fnum(leaveBalance.pending)} روز</b></div>
        <div class="is-primary"><small>مانده قطعی</small><b>${fnum(leaveBalance.remaining)} روز</b><em>با احتساب درخواست‌های در انتظار: ${fnum(leaveBalance.projected_remaining)} روز</em></div>
      </div>
      <small class="employee-dossier-leave-balance-foot">فقط مرخصی «استحقاقی» نهایی‌شده از سهمیه کسر می‌شود؛ مرخصی پزشکی و بدون حقوق جداگانه محاسبه می‌شوند.</small>
    </div>` : `<div class="employee-dossier-note">تاریخ شروع همکاری این کارمند در فایل اطلاعات پایه قابل تشخیص نیست؛ برای محاسبه مانده مرخصی، تاریخ شروع به کار را در فایل اکسل تکمیل کنید.</div>`;
    const leavesPane = leaveBalancePane + listTable(['کد پیگیری / نوع', 'بازه مرخصی', 'مدت', 'وضعیت', 'دلیل ثبت‌شده'], leaveRows, 'درخواستی برای مرخصی ثبت نشده است.');

    const promotionCards = promotions.map(x => `<article class="employee-dossier-request-card">
      <div class="employee-dossier-request-head"><div><b>ارتقا به ${escv(x.job_requested)}</b><small>کد پیگیری: <span class="ltr">${escv(x.tracking)}</span> · ثبت: <span class="ltr">${escv(x.created_jdate)}</span></small></div>${statusLabel(x.stage)}</div>
      <div class="employee-dossier-grid compact">${kv('عنوان شغلی زمان درخواست', escv(x.post))}${kv('شیفت فعلی', escv(x.current_shift))}${kv('شیفت درخواستی', escv(x.shift))}${kv('افزایش حقوق مصوب', x.raise_amount ? cash(x.raise_amount) : '—')}${kv('نمره ارزیابی', x.eval_score === null ? '—' : `${fnum(x.eval_score)} از ۱۰۰`)}${kv('ثبت تصمیم نهایی توسط', escv(x.decided_by || x.senior_by))}</div>
      <div class="employee-dossier-reason"><b>دلایل کارمند:</b><p>${escv(x.reason)}</p></div>
      ${x.direct_note ? `<div class="employee-dossier-note"><b>یادداشت مدیر مستقیم:</b> ${esc(x.direct_note)}</div>` : ''}
      ${x.senior_note ? `<div class="employee-dossier-note"><b>یادداشت مدیر ارشد:</b> ${esc(x.senior_note)}</div>` : ''}
      ${x.eval_note ? `<div class="employee-dossier-note"><b>یادداشت ارزیابی:</b> ${esc(x.eval_note)}</div>` : ''}
      ${x.decision_note ? `<div class="employee-dossier-note"><b>یادداشت نهایی:</b> ${esc(x.decision_note)}</div>` : ''}
    </article>`).join('');
    const promotionsPane = promotionCards || '<div class="empty">درخواستی برای ارتقای شغلی ثبت نشده است.</div>';

    const sourceLabel = { leave: 'مرخصی', advance: 'مساعده', loan: 'وام', promotion: 'ارتقای شغلی' };
    const historyCards = history.map(x => `<article class="employee-history-record"><div class="employee-history-record-head"><div><b>${esc(x.title || 'رویداد پرسنلی')}</b><small>${esc(sourceLabel[x.source_type] || x.source_type || 'سایر')} · کد مرجع: <span class="ltr">${esc(String(x.source_id || '—'))}</span></small></div>${badge('ثبت‌شده در پرونده', 'b-ok')}</div><p>${esc(x.description || '—')}</p><footer><span>ثبت‌کننده: <b>${esc(x.actor_name || '—')}</b></span><span>تاریخ: <b>${esc(x.event_jdate || '—')}</b></span></footer></article>`).join('');
    const importCards = (d.import_snapshots || []).map(x => {
      const snap = x.snapshot || {}, empSnap = snap.employee || {}, slipSnap = snap.payslip || {};
      const slipValues = slipSnap.data?.values || {};
      const payLabels = { G10:'مزد ثابت ماهانه', G11:'مزایا و حق مسکن', G12:'حق تأهل و اولاد', G13:'سنوات و پاداش', G15:'اضافه‌کار و شب‌کاری', G16:'معوقات', G17:'پاداش عملکرد', G18:'بازخرید مرخصی' };
      const payRows = Object.entries(payLabels).filter(([key]) => Number(slipValues[key] || 0) !== 0)
        .map(([key,label]) => `<div class="employee-dossier-line"><span>${label}</span><b>${cash(slipValues[key])}</b></div>`).join('');
      const extraLabels = { father_name:'نام پدر', birth_date:'تاریخ تولد', birth_place:'محل تولد', gender:'جنسیت', marital_status:'وضعیت تأهل', children_count:'تعداد فرزند', education:'مدرک تحصیلی', field_of_study:'رشته تحصیلی', address:'نشانی', postal_code:'کد پستی', emergency_phone:'تماس اضطراری', bank_name:'نام بانک', bank_account:'شماره حساب', bank_card:'شماره کارت', insurance_number:'شماره بیمه', employment_type:'نوع استخدام', branch:'شعبه' };
      const extraRows = Object.entries(snap.extra || {}).filter(([key,value]) => extraLabels[key] && value !== null && String(value).trim() !== '')
        .map(([key,value]) => `<div class="employee-dossier-kv"><small>${extraLabels[key]}</small><b>${esc(String(value))}</b></div>`).join('');
      const details = `<details class="employee-import-snapshot-details"><summary>مشاهده جزئیات نسخه ذخیره‌شده</summary>
        <div class="employee-import-snapshot-grid">
          <div class="employee-dossier-kv"><small>حقوق منبع</small><b>${cash(empSnap.source_salary ?? empSnap.salary ?? 0)}</b></div>
          <div class="employee-dossier-kv"><small>خالص فیش این نسخه</small><b>${slipSnap.has_work ? cash(slipSnap.net) : 'بدون کارکرد'}</b></div>
          <div class="employee-dossier-kv"><small>عنوان شغلی</small><b>${esc(empSnap.post || '—')}</b></div>
          <div class="employee-dossier-kv"><small>تاریخ شروع</small><b>${esc(empSnap.hire_date || '—')}</b></div>
        </div>
        ${extraRows ? `<h4>اطلاعات هویتی و بانکی این نسخه</h4><div class="employee-import-snapshot-grid">${extraRows}</div>` : ''}
        ${payRows ? `<h4>اجزای پرداخت این نسخه</h4><div class="employee-dossier-subcard">${payRows}</div>` : ''}
      </details>`;
      return `<article class="employee-history-record"><div class="employee-history-record-head"><div><b>ثبت اطلاعات از اکسل · ${esc(x.period_label || 'دوره نامشخص')}</b><small>فایل: ${esc(x.file_name || '—')} · بارگذاری‌کننده: ${esc(x.uploaded_by || '—')}</small></div>${badge('نسخه محفوظ', 'b-info')}</div><p>حقوق منبع: ${cash(empSnap.source_salary ?? empSnap.salary ?? 0)} · خالص فیش: ${slipSnap.has_work ? cash(slipSnap.net) : 'بدون کارکرد'} · عنوان شغلی: ${esc(empSnap.post || '—')}</p>${details}<footer><span>کد پرسنلی: <b class="ltr">${esc(empSnap.personnel_code || '—')}</b></span><span>تاریخ ثبت: <b>${esc(x.created_at_jalali || '—')}</b></span></footer></article>`;
    }).join('');
    const editCards = (d.profile_edit_history || []).map(x => `<article class="employee-history-record"><div class="employee-history-record-head"><div><b>ویرایش دستی اطلاعات پرونده</b><small>ثبت‌کننده: ${esc(x.changed_by || '—')}</small></div>${badge('ذخیره‌شده در هاست', 'b-info')}</div><footer><span>شماره ثبت: <b class="ltr">${fnum(x.id)}</b></span><span>تاریخ: <b>${esc(x.changed_at || '—')}</b></span></footer></article>`).join('');
    const historyPane = (historyCards || importCards || editCards) ? `<div class="employee-dossier-history-list">${editCards}${importCards}${historyCards}</div>` : '<div class="empty">هنوز رویداد یا نسخه‌ای از اطلاعات اکسل در پرونده این کارمند ثبت نشده است.</div>';

    const tabs = [
      ['identity','اطلاعات هویتی','user-round'], ['bank','اطلاعات بانکی','landmark'], ['payroll','حقوق و دستمزد','wallet'],
      ['payslips','دوره‌های فیش حقوقی','file-text'], ['finance','وام و مساعده','hand-coins'], ['leaves','مرخصی‌ها','calendar-days'],
      ['promotions','ارتقای شغلی','trending-up'], ['history','سوابق پرونده','folder-check']
    ];
    mb.innerHTML = `<div class="employee-dossier">
      <header class="employee-dossier-header"><div class="employee-dossier-avatar">${esc((emp.name || 'ک').trim().slice(0,1))}</div><div class="employee-dossier-heading"><div class="employee-dossier-title-row"><h2>پرونده جامع پرسنلی</h2>${activeBadge}</div><p>${escv(emp.name)} <span>کد پرسنلی: <b class="ltr">${escv(emp.personnel_code)}</b></span></p></div><div class="row employee-dossier-header-actions"><button class="btn sm" type="button" onclick="editEmployeeProfile(${Number(emp.id)})">ویرایش اطلاعات</button><button class="btn ghost sm" type="button" onclick="closeModal()">بستن پرونده</button></div></header>
      <div class="employee-dossier-overview"><div><small>حقوق ثبت‌شده فعلی</small><b>${cash(payroll.current_salary)}</b></div><div><small>دوره‌های فیش</small><b>${fnum(periods.length)}</b></div><div><small>درخواست‌های مالی</small><b>${fnum(advances.length)}</b></div><div><small>مرخصی‌ها / ارتقا</small><b>${fnum(leaves.length)} / ${fnum(promotions.length)}</b></div></div>
      <nav class="employee-dossier-tabs" aria-label="بخش‌های پرونده">${tabs.map((t,i) => `<button type="button" class="${i===0?'on':''}" data-dossier-tab="${t[0]}" onclick="selectEmployeeDossierTab('${t[0]}')">${esc(t[1])}</button>`).join('')}</nav>
      <div class="employee-dossier-panes">
        <section class="employee-dossier-pane on" data-dossier-pane="identity"><h3>مشخصات هویتی و شغلی</h3>${identityPane}</section>
        <section class="employee-dossier-pane" data-dossier-pane="bank"><h3>اطلاعات بانکی</h3>${bankPane}</section>
        <section class="employee-dossier-pane" data-dossier-pane="payroll"><h3>اطلاعات حقوق و دستمزد</h3>${payrollPane}</section>
        <section class="employee-dossier-pane" data-dossier-pane="payslips"><h3>دوره‌های فیش حقوقی</h3>${payslipsPane}</section>
        <section class="employee-dossier-pane" data-dossier-pane="finance"><h3>سوابق وام و مساعده</h3>${financePane}</section>
        <section class="employee-dossier-pane" data-dossier-pane="leaves"><h3>سوابق مرخصی</h3>${leavesPane}</section>
        <section class="employee-dossier-pane" data-dossier-pane="promotions"><h3>سوابق ارتقای شغلی</h3>${promotionsPane}</section>
        <section class="employee-dossier-pane" data-dossier-pane="history"><h3>رویدادهای ثبت‌شده در پرونده</h3>${historyPane}</section>
      </div>
    </div>`;
  };

  /* ---------- ویرایش اطلاعات واردشده از اکسل در پرونده پرسنلی ---------- */
  window.editEmployeeProfile = function (id) {
    const d = window._currentEmployeeDossier || {};
    const emp = d.employee || {};
    if (Number(emp.id) !== Number(id)) return toast('اطلاعات پرونده برای ویرایش در دسترس نیست؛ پرونده را دوباره باز کنید.', 'bad');
    const extra = d.profile_extra || {};
    const base = d.payroll_base || {};
    const manual = d.manual_overrides || {};
    const owns = (obj, key) => Object.prototype.hasOwnProperty.call(obj || {}, key);
    const isManual = (key) => {
      if (key.startsWith('extra_')) return owns(manual.extra, key.slice(6));
      if (key.startsWith('base_')) return owns(manual.payroll_base, key.slice(5));
      return owns(manual.core, key);
    };
    const field = (key, label, value, type = 'text', options = {}) => {
      const manualNow = isManual(key);
      const inputMode = options.inputmode ? `inputmode="${options.inputmode}"` : '';
      const direction = options.ltr ? 'ltr' : '';
      const dateAttr = options.date ? 'data-jdate' : '';
      const help = options.help ? `<small class="dossier-edit-help">${esc(options.help)}</small>` : '';
      return `<div class="dossier-edit-field">
        <label for="dpe_${key}">${esc(label)}</label>
        <input id="dpe_${key}" data-profile-edit="${esc(key)}" type="${type}" value="${esc(value ?? '')}" ${inputMode} ${dateAttr} class="${direction}" ${options.step ? `step="${options.step}"` : ''} ${options.min !== undefined ? `min="${options.min}"` : ''}>
        <label class="dossier-edit-source"><input type="checkbox" data-profile-use-excel="${esc(key)}" ${manualNow ? '' : 'checked'}> ${manualNow ? 'ویرایش دستی فعال است؛ اکسل مقدار را تغییر نمی‌دهد' : 'در بارگذاری بعدی از اکسل به‌روزرسانی شود'}</label>${help}
      </div>`;
    };
    const extraFields = [
      ['father_name','نام پدر'],['birth_date','تاریخ تولد'],['birth_place','محل تولد'],['gender','جنسیت'],
      ['marital_status','وضعیت تأهل'],['children_count','تعداد فرزند'],['education','مدرک تحصیلی'],
      ['field_of_study','رشته تحصیلی'],['address','نشانی'],['postal_code','کد پستی'],
      ['emergency_phone','شماره تماس اضطراری'],['insurance_number','شماره بیمه'],
      ['employment_type','نوع استخدام'],['branch','شعبه']
    ];
    const extraFieldHtml = extraFields.map(([key,label]) => field(`extra_${key}`, label, extra[key] ?? '', 'text', {
      ltr: ['postal_code','emergency_phone','insurance_number'].includes(key),
      date: key === 'birth_date', inputmode: key === 'children_count' ? 'numeric' : undefined
    })).join('');
    const salaryBase = base.salary !== undefined ? base.salary : Math.max(0, Number(emp.salary || 0) - Number(d.payroll?.promotion_increase_total || 0));
    const baseFields = [
      ['S','مزد ثابت ماهانه'],['T','حق سنوات و پاداش پایان سال'],['U','حق مسکن و بن کارگری'],
      ['V','حق تأهل و اولاد'],['W','جزء متغیر حقوق (W)'],['AD','جزء متغیر حقوق (AD)']
    ].map(([key,label]) => field(`base_${key}`, label, base[key] ?? 0, 'text', {ltr:true,inputmode:'numeric'})).join('');
    const coreHtml = [
      field('name','نام و نام خانوادگی',emp.name || ''),
      field('national_code','کد ملی',emp.national_code || '', 'text', {ltr:true,inputmode:'numeric'}),
      field('phone','شماره همراه',emp.phone || '', 'text', {ltr:true,inputmode:'numeric'}),
      field('post','عنوان شغلی',emp.post || ''),
      field('hire_date','تاریخ شروع همکاری',emp.hire_date || '', 'text', {ltr:true,date:true}),
    ].join('');
    const bankHtml = [
      field('sheba','شماره شبا',d.bank?.sheba || '', 'text', {ltr:true,inputmode:'numeric',help:'شماره شبا را با IR و ۲۴ رقم یا فقط ۲۴ رقم وارد کنید.'}),
      field('extra_bank_name','نام بانک',extra.bank_name || ''),
      field('extra_bank_account','شماره حساب',extra.bank_account || '', 'text', {ltr:true,inputmode:'numeric'}),
      field('extra_bank_card','شماره کارت',extra.bank_card || '', 'text', {ltr:true,inputmode:'numeric'}),
    ].join('');
    const payrollHtml = field('salary','حقوق پایه منبع (ریال)',salaryBase,'text',{ltr:true,inputmode:'numeric'}) + baseFields;
    openModal(`<form id="employeeProfileEditForm" class="employee-profile-edit" onsubmit="saveEmployeeProfile(event,${Number(id)})">
      <header class="dossier-edit-header"><div><h2>ویرایش اطلاعات پرونده</h2><p>${esc(emp.name || '')} · کد پرسنلی <b class="ltr">${esc(emp.personnel_code || '')}</b></p></div><span class="badge b-info">ذخیره در هاست</span></header>
      <div class="dossier-edit-notice"><b>نحوه اولویت اطلاعات</b><span>تغییراتی که دستی ذخیره می‌کنید در بارگذاری‌های بعدی اکسل حفظ می‌شوند. برای هر فیلد می‌توانید دریافت از اکسل را دوباره فعال کنید.</span><small>کد پرسنلی N+F فقط از ستون‌های N و F اکسل ساخته می‌شود و در این فرم قابل تغییر نیست.</small></div>
      <section class="dossier-edit-section"><h3>اطلاعات هویتی و شغلی</h3><div class="dossier-edit-grid">${coreHtml}</div></section>
      <section class="dossier-edit-section"><h3>اطلاعات بانکی</h3><div class="dossier-edit-grid">${bankHtml}</div></section>
      <section class="dossier-edit-section"><h3>حقوق و دستمزد</h3><div class="dossier-edit-grid">${payrollHtml}</div></section>
      <section class="dossier-edit-section"><h3>اطلاعات تکمیلی</h3><div class="dossier-edit-grid">${extraFieldHtml}</div></section>
      <footer class="dossier-edit-actions"><span class="hint">آخرین ویرایش دستی: ${esc(d.manual_updated_by || 'هنوز ثبت نشده')}</span><div class="row"><button class="btn ghost" type="button" onclick="viewEmployeeHistory(${Number(id)})">انصراف</button><button class="btn" type="submit">ذخیره تغییرات</button></div></footer>
    </form>`);
    const mb = document.getElementById('modalBody');
    mb.style.maxWidth = '1120px'; mb.style.maxHeight = '92vh'; mb.style.overflow = 'auto';
    document.querySelectorAll('[data-profile-edit]').forEach(input => {
      input.addEventListener('input', () => {
        const checkbox = document.querySelector(`[data-profile-use-excel="${CSS.escape(input.dataset.profileEdit)}"]`);
        if (checkbox) checkbox.checked = false;
      });
    });
  };

  window.saveEmployeeProfile = async function (event, id) {
    event.preventDefault();
    const form = document.getElementById('employeeProfileEditForm');
    if (!form) return;
    const fields = {}, useExcel = {};
    form.querySelectorAll('[data-profile-edit]').forEach(input => { fields[input.dataset.profileEdit] = input.value; });
    form.querySelectorAll('[data-profile-use-excel]').forEach(input => { useExcel[input.dataset.profileUseExcel] = input.checked; });
    const button = form.querySelector('button[type="submit"]');
    setBusy(button, true, 'در حال ذخیره…');
    const r = await api('admin', 'employee_profile_save', { employee_id: Number(id), fields, use_excel: useExcel });
    if (!r.ok) { setBusy(button, false); return toast(r.message || 'ذخیره اطلاعات ناموفق بود.', 'bad'); }
    toast(r.message || 'اطلاعات پرونده ذخیره شد.', 'ok');
    await viewEmployeeHistory(Number(id));
  };

  window.selectEmployeeDossierTab = function (key) {
    document.querySelectorAll('[data-dossier-tab]').forEach(b => b.classList.toggle('on', b.dataset.dossierTab === key));
    document.querySelectorAll('[data-dossier-pane]').forEach(p => p.classList.toggle('on', p.dataset.dossierPane === key));
  };

  window.loadDossierSlip = async function (employeeId, periodId) {
    const box = document.getElementById('dossierSlipPreview');
    if (!box) return;
    box.innerHTML = '<div class="empty">در حال دریافت فیش…</div>';
    const r = await api('admin', 'payslip', { employee_id: Number(employeeId), period_id: Number(periodId) });
    if (!box.isConnected) return;
    box.innerHTML = r.ok ? renderSlip(r.slip) : `<div class="alert">${esc(r.message || 'دریافت فیش ناموفق بود.')}</div>`;
  };

  /* ---------- فیش حقوقی کارمند (ادمین و مدیر) ---------- */
  window.viewPayslips = async function (id) {
    const r = await api('admin', 'employee_periods', { employee_id: id });
    if (!r.ok) return toast(r.message, 'bad');
    openModal(`<div class="section-title no-print"><h2>فیش حقوقی — ${esc(r.employee.name)}</h2>
        <button class="btn ghost sm" type="button" onclick="closeModal()">بستن</button></div>
      ${r.periods.length ? `<label class="no-print" style="flex-direction:row;align-items:center;gap:8px;font-weight:600;margin-bottom:12px">دوره:
        <select id="admPeriod" onchange="loadAdminSlip(${id}, this.value)" style="min-width:200px">
          ${r.periods.map(p => `<option value="${p.id}">${esc(p.label)}${p.has_work ? '' : ' (کارکرد ثبت نشده)'}</option>`).join('')}
        </select></label><div id="slip"></div>` : '<div class="empty">برای این کارمند فیشی ثبت نشده است.</div>'}`);
    const mb = document.getElementById('modalBody');
    mb.style.maxWidth = '980px'; mb.style.maxHeight = '90vh'; mb.style.overflow = 'auto';
    if (r.periods.length) loadAdminSlip(id, r.periods[0].id);
  };
  window.loadAdminSlip = async function (id, periodId) {
    const box = document.getElementById('slip');
    box.innerHTML = '<div class="empty">در حال دریافت فیش…</div>';
    const r = await api('admin', 'payslip', { employee_id: Number(id), period_id: Number(periodId) });
    box.innerHTML = r.ok ? renderSlip(r.slip) : `<div class="alert">${esc(r.message)}</div>`;
  };

  window.loadWindows = async function () {
    if (!IS_ADMIN) return;
    const r = await api('admin', 'dashboard');
    if (!r.ok) return;
    const fmt = (w) => w.start ? `تنظیم‌شده: <b class="ltr">${esc(w.start)} تا ${esc(w.end)}</b>` : 'تنظیم نشده';
    document.getElementById('winPay').innerHTML = fmt(r.windows.payslip);
    document.getElementById('winAdv').innerHTML = fmt(r.windows.advance);
    document.getElementById('winLoan').innerHTML = fmt(r.windows.loan);
    if (r.approval_flows && IS_ADMIN) {
      window.renderApprovalFlows(r.approval_flows);
    }
    if (r.penalty_settings && IS_ADMIN) {
      window.renderPenaltySettings(r.penalty_settings);
    }
    if (r.promo_mode && IS_ADMIN) {
      document.querySelectorAll('#promoModeForm input[name=mode]').forEach(i => { i.checked = i.value === r.promo_mode; });
    }
    if (r.request_sources && IS_ADMIN) {
      ['advance', 'loan'].forEach(k => {
        document.querySelectorAll(`#requestSourceForm input[name=${k}_src]`).forEach(i => { i.checked = i.value === r.request_sources[k]; });
      });
    }
    if (r.rules && IS_ADMIN) {
      const f = document.getElementById('rulesForm');
      f.adv_wait_months.value = r.rules.adv_wait_months;
      f.adv_ratio_pct.value = r.rules.adv_ratio_pct;
      f.adv_round.value = r.rules.adv_round;
      f.loan_wait_months.value = r.rules.loan_wait_months;
      f.loan_ratio_pct.value = r.rules.loan_ratio_pct;
      f.loan_round.value = r.rules.loan_round;
    }
  };
  window.saveRules = async function (ev) {
    ev.preventDefault();
    const f = ev.target;
    const data = {
      adv_wait_months: f.adv_wait_months.value,
      adv_ratio_pct: f.adv_ratio_pct.value,
      adv_round: f.adv_round.value.replace(/[ ,\s]/g, ''),
      loan_wait_months: f.loan_wait_months.value,
      loan_ratio_pct: f.loan_ratio_pct.value,
      loan_round: f.loan_round.value.replace(/[ ,\s]/g, ''),
    };
    const r = await api('admin', 'rules_save', data);
    toast(r.message, r.ok ? 'ok' : 'bad');
    document.getElementById('rulesHint').textContent = r.ok ? 'ذخیره شد؛ از این لحظه برای همه‌ی کارکنان اعمال می‌شود.' : '';
    if (r.ok) loadWindows();
  };
  window.saveRequestSources = async function (ev) {
    ev.preventDefault();
    const a = ev.target.querySelector('input[name=advance_src]:checked');
    const l = ev.target.querySelector('input[name=loan_src]:checked');
    const r = await api('admin', 'request_source_save', { advance: a ? a.value : '', loan: l ? l.value : '' });
    toast(r.message, r.ok ? 'ok' : 'bad');
  };
  window.savePromoMode = async function (ev) {
    ev.preventDefault();
    const sel = ev.target.querySelector('input[name=mode]:checked');
    const r = await api('admin', 'promo_mode_save', { mode: sel ? sel.value : '' });
    toast(r.message, r.ok ? 'ok' : 'bad');
  };
  const FLOW_KIND_META = {
    leave: { label: 'مرخصی', icon: 'م' },
    advance: { label: 'مساعده', icon: 'هـ' },
    loan: { label: 'وام', icon: 'و' },
    promotion: { label: 'ارتقای شغلی', icon: 'ا' },
    penalty: { label: 'جریمه', icon: 'ج' },
  };
  const FLOW_LEVELS = [
    { key: 'direct', label: 'سوپروایزر / سرپرست مستقیم', short: 'سرپرست مستقیم' },
    { key: 'upper', label: 'مدیران بالادستی در دایره‌ی مدیریت', short: 'مدیران بالادستی' },
    { key: 'general', label: 'مدیر ارشد', short: 'مدیر ارشد' },
  ];
  window.approvalFlowState = {};

  function drawApprovalFlows() {
    const box = document.getElementById('approvalFlows');
    if (!box) return;
    box.innerHTML = Object.entries(FLOW_KIND_META).map(([k, meta]) => {
      const on = approvalFlowState[k] || {};
      const activeCount = FLOW_LEVELS.filter(level => on[level.key]).length;
      const steps = [
        { label: 'کارمند', active: true, title: 'ثبت درخواست توسط کارمند' },
        ...FLOW_LEVELS.map(level => ({ label: level.short, active: !!on[level.key], title: level.label })),
        { label: 'منابع انسانی', active: true, title: 'ثبت نهایی توسط منابع انسانی' },
      ];
      const path = steps.map((step, index) =>
        `<span class="approval-step ${step.active ? '' : 'is-off'}" title="${esc(step.title)}">${esc(step.label)}</span>${index < steps.length - 1 ? '<span class="approval-path-arrow" aria-hidden="true">←</span>' : ''}`
      ).join('');
      const toggles = FLOW_LEVELS.map(level => `
        <label class="approval-toggle">
          <input type="checkbox" ${on[level.key] ? 'checked' : ''} onchange="flowToggle('${k}','${level.key}')">
          <span>${esc(level.short)}</span>
        </label>`).join('');
      return `
        <article class="approval-kind-card">
          <header class="approval-kind-head">
            <span class="approval-kind-icon">${esc(meta.icon)}</span>
            <div class="approval-kind-title"><b>${esc(meta.label)}</b><small>سطح‌های فعال: ${activeCount} از ۳</small></div>
            <span class="approval-kind-status ${activeCount ? 'has-steps' : 'hr-only'}">${activeCount ? 'گردش چندمرحله‌ای' : 'مستقیم به منابع انسانی'}</span>
          </header>
          <div class="approval-path" aria-label="مسیر تأیید ${esc(meta.label)}">${path}</div>
          <div class="approval-levels">${toggles}</div>
        </article>`;
    }).join('');
  }

  window.renderApprovalFlows = function (flows) {
    Object.keys(FLOW_KIND_META).forEach(k => {
      const list = flows[k] || [];
      approvalFlowState[k] = Object.fromEntries(FLOW_LEVELS.map(level => [level.key, list.includes(level.key)]));
    });
    drawApprovalFlows();
  };
  window.flowToggle = function (k, key) {
    if (!approvalFlowState[k]) return;
    approvalFlowState[k][key] = !approvalFlowState[k][key];
    drawApprovalFlows();
  };
  window.saveApprovalFlows = async function (ev) {
    ev.preventDefault();
    const data = {};
    Object.keys(FLOW_KIND_META).forEach(k => {
      const list = FLOW_LEVELS.filter(l => approvalFlowState[k][l.key]).map(l => l.key);
      data['flow_' + k] = list.length ? list.join(',') : 'none';
    });
    const r = await api('admin', 'approval_flows_save', data);
    toast(r.message, r.ok ? 'ok' : 'bad');
    if (r.ok) loadWindows();
  };
  window.saveWindow = async function (ev, which) {
    ev.preventDefault();
    const f = ev.target;
    const r = await api('admin', 'windows', { which, start: f.start.value, end: f.end.value });
    toast(r.message, r.ok ? 'ok' : 'bad');
    if (r.ok) loadWindows();
  };
  window.clearWindow = async function (which) {
    const r = await api('admin', 'windows', { which, clear: true });
    toast(r.message, r.ok ? 'ok' : 'bad');
    if (r.ok) loadWindows();
  };

  /* ---------- مساعده ---------- */
  /* دکمه‌های هر درخواست: تصمیم (در انتظار)، فرم چاپی، دانلود و حذف (فقط ادمین) */
  const advActions = (x) => {
    const pending = !!x.can_decide;
    const finalRej = x.stage === 'admin' && x.to_admin_decision === 'reject';
    const approvedByGeneral = x.stage === 'admin' && x.to_admin_decision === 'approve';
    const relink = (window.IS_ADMIN && x.orphan) ? `<button class="btn sm" onclick="relinkAdvance(${x.id})">اتصال به کارمند</button>` : '';
    const btns = approvedByGeneral
      ? `<button class="btn sm" onclick="decideAdvance(${x.id})">ثبت نهایی</button>`
      : finalRej
      ? `<button class="btn sm danger" onclick="rejectAdvance(${x.id})">ثبت رد نهایی</button>`
      : `<button class="btn sm" onclick="decideAdvance(${x.id})">${x.stage === 'admin' ? 'ثبت نهایی' : 'تأیید'}</button><button class="btn sm danger" onclick="rejectAdvance(${x.id})">رد</button>`;
    return `<div class="row">${relink}${pending ? btns : ''}` +
      `<a class="btn sm ghost" target="_blank" href="request_report.php?type=advance&mode=form&id=${x.id}">فرم چاپی درخواست</a>` +
      (window.IS_ADMIN && x.stage === 'final' && x.status === 'تایید شده' ? `<button class="btn sm ghost" onclick="editAdvance(${x.id})">ویرایش</button>` : '') +
      (window.IS_ADMIN ? `<button class="btn sm danger" onclick="deleteAdvance(${x.id})">حذف</button>` : '') + `</div>`;
  };
  window.relinkAdvance = function (id) {
    const x = (window._adv || []).find(v => v.id === id) || {};
    pickEmployee(async (e) => {
      if (!e) return;
      const r = await api('admin', 'advance_relink', { id, employee_id: e.id });
      toast(r.message || (r.ok ? 'متصل شد' : 'خطا'), r.ok ? 'ok' : 'bad');
      if (r.ok) loadAdvances();
    }, { title: `اتصال «${x.name || 'درخواست'}» به کارمند`, filter: 'all' });
  };
  window.deleteRequest = async function (type, id) {
    const list = type === 'advance' ? (window._adv || []) : type === 'leave' ? (window._leaves || []) : (window._pro || []);
    const x = list.find(v => Number(v.id) === Number(id)) || {};
    if (!confirm(`درخواست ${x.tracking || ''} ${x.name ? '(' + x.name + ')' : ''} برای همیشه حذف شود؟\nاین درخواست از پرونده‌ی پرسنلی کارمند هم پاک می‌شود.`)) return;
    const r = await api('admin', 'request_delete', { type, id });
    toast(r.message || (r.ok ? 'حذف شد' : 'خطا'), r.ok ? 'ok' : 'bad');
    if (!r.ok) return;
    if (type === 'advance') loadAdvances();
    else if (type === 'leave') loadLeaves();
    else loadPromotions();
  };
  window.deleteAdvance = function (id) { return deleteRequest('advance', id); };
  window.editAdvance = function (id) {
    const x = (window._adv || []).find(v => Number(v.id) === Number(id)) || {};
    const isLoan = x.kind === 'loan';
    openModal(`<h2>ویرایش ${isLoan ? 'وام' : 'مساعده'}</h2>
      <p class="muted">${esc(x.name || '')} — <span class="ltr">${esc(x.tracking || '')}</span></p>
      <p class="hint">تا وقتی قسطی از فیش کسر نشده، مبلغ تأییدی و تعداد اقساط قابل تغییرند.</p>
      <form class="stack" onsubmit="submitEditAdvance(event, ${id})">
        <label>مبلغ تأییدی (ریال، حداکثر ${groupDigits(String(x.amount || 0))})<input name="approved" class="ltr" inputmode="numeric" autocomplete="off" oninput="this.value=groupDigits(this.value)" value="${groupDigits(String(x.approved_amount || x.amount || 0))}"></label>
        ${isLoan ? `<label>تعداد اقساط (۱ تا ۶۰)<input name="installments" class="ltr" inputmode="numeric" value="${x.installments || 1}"></label>` : ''}
        <label>یادداشت (اختیاری)<input name="note"></label>
        <div class="row"><button class="btn" type="submit">ذخیره‌ی تغییرات</button><button class="btn ghost" type="button" onclick="closeModal()">انصراف</button></div>
      </form>`);
  };
  window.submitEditAdvance = async function (ev, id) {
    ev.preventDefault();
    const f = ev.target;
    const data = { id, approved: toEnDigits(f.approved.value).replace(/\D/g, ''), note: f.note.value };
    if (f.installments) data.installments = toEnDigits(f.installments.value).replace(/\D/g, '');
    const r = await api('admin', 'advance_edit', data);
    toast(r.message || (r.ok ? 'ذخیره شد' : 'خطا'), r.ok ? 'ok' : 'bad');
    if (r.ok) { closeModal(); loadAdvances(); }
  };
  const requestReportQuery = (values, reportType) => {
    const p = new URLSearchParams();
    Object.entries(values || {}).forEach(([k, v]) => {
      if (v === undefined || v === null || String(v).trim() === '') return;
      // `type` در URL برای نوع گزارش رزرو شده است؛ نوع مرخصی پارامتر جدا دارد.
      const key = reportType === 'leave' && k === 'type' ? 'leave_type' : k;
      p.set(key, String(v).trim());
    });
    return p.toString();
  };
  const updateRequestReportLinks = (prefix, type, values) => {
    const q = requestReportQuery(values, type);
    const tail = q ? '&' + q : '';
    const print = document.getElementById(prefix + 'PrintLink');
    const excel = document.getElementById(prefix + 'ExcelLink');
    if (print) print.href = `request_report.php?type=${type}&mode=print${tail}`;
    if (excel) excel.href = `request_report.php?type=${type}&mode=excel${tail}`;
  };

  window.loadAdvances = async function () {
    const filter = {
      status: document.getElementById('advStatus').value,
      kind: document.getElementById('advKind').value,
      q: document.getElementById('advQ')?.value || '',
      orphan: document.getElementById('advOrphan')?.checked ? '1' : '',
      from: document.getElementById('advFrom')?.value || '',
      to: document.getElementById('advTo')?.value || ''
    };
    const r = await api('admin', 'advances', { filter });
    if (!r.ok) return toast(r.message, 'bad');
    window._adv = r.requests;
    updateRequestReportLinks('adv', 'advance', filter);
    document.getElementById('atable').innerHTML = `<tr><th>کد</th><th>نوع</th><th>تاریخ</th><th>نام</th><th>کد پرسنلی</th><th>شبا</th><th>درخواستی</th><th>تأییدی</th><th>وضعیت</th><th>عملیات</th></tr>` +
      (r.requests.length ? r.requests.map(x => `<tr>
        <td class="ltr">${esc(x.tracking)}</td><td><span class="badge ${x.kind === 'loan' ? 'b-info' : 'b-gray'}">${x.kind === 'loan' ? 'وام' : 'مساعده'}</span></td><td>${esc(x.jdate)}</td><td>${esc(x.name)}${x.post ? `<div class="hint">${esc(x.post)}</div>` : ''}</td>
        <td class="ltr">${esc(x.personnel_code)}${x.orphan ? '<div class="hint"><span class="badge b-bad">بدون کارمند متصل</span></div>' : ''}</td><td style="font-size:.8rem"><div class="ltr">${esc(x.sheba)}</div>${x.sheba_owner ? `<div class="hint">${esc(x.sheba_owner)}</div>` : ''}</td>
        <td class="ltr">${money(x.amount)}</td><td class="ltr">${x.approved_amount ? money(x.approved_amount) : '—'}</td>
        <td><span class="badge ${x.stage === 'final' ? 'b-ok' : (adv[x.status] || 'b-gray')}">${x.stage === 'final' ? 'تأیید نهایی' : esc(x.status)}</span>${x.stage === 'final' && x.to_admin_decision === 'notified' ? '<div class="hint">منابع انسانی مطلع شد · در پرونده پرسنلی ثبت شد</div>' : ''}${x.status === 'در انتظار بررسی' ? `<div class="hint">مرحله: ${stage[x.stage] || ''}</div>` : ''}${x.note ? `<div class="hint">${esc(x.note)}</div>` : ''}</td>
        <td>${advActions(x)}</td></tr>`).join('')
        : '<tr><td colspan="10" class="empty">درخواستی با این فیلترها پیدا نشد.</td></tr>');
  };
  /* قالب‌بندی مبلغ: ارقام فارسی/عربی به لاتین تبدیل و هر سه رقم با ویرگول جدا می‌شود */
  window.toEnDigits = function (s) {
    return String(s ?? '')
      .replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
      .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
  };
  window.groupDigits = function (s) {
    const d = toEnDigits(s).replace(/\D/g, '');
    return d.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  };
  window.decideAdvance = function (id) {
    const x = (window._adv || []).find(v => v.id === id) || { amount: 0, name: '', stage: '' };
    const amount = x.amount, name = x.name;
    const hasAmount = x.stage === 'general' || (x.stage === 'admin' && x.to_admin_decision === 'approve');
    const shownAmount = x.stage === 'admin' && x.approved_amount ? x.approved_amount : amount;
    const amountField = hasAmount
      ? `<label>مبلغ تأییدی (ریال، حداکثر ${groupDigits(String(amount))})<input name="approved" class="ltr" inputmode="numeric" autocomplete="off" oninput="this.value=groupDigits(this.value)" value="${groupDigits(String(shownAmount))}"></label>`
        + (x.kind === 'loan' ? `<label>تعداد اقساط (کسر ماهانه از فیش، ۱ تا ۶۰)<input name="installments" class="ltr" inputmode="numeric" value="${x.installments && x.installments > 1 ? x.installments : 12}"></label>` : '')
      : '';
    const hint = x.stage === 'admin' ? 'ثبت نهایی منابع انسانی؛ پس از تأیید، به کارمند اعلام می‌شود.'
      : x.stage === 'general' ? 'با تأیید شما درخواست نهایی می‌شود؛ منابع انسانی مطلع و نتیجه در پرونده پرسنلی ثبت خواهد شد.'
      : 'پس از تأیید شما، درخواست برای مدیر ارشد ارسال می‌شود.';
    openModal(`<h2>تأیید ${x.kind === 'loan' ? 'وام' : 'مساعده'}</h2><p class="muted">${esc(name)} — درخواستی: <b class="ltr">${money(amount)}</b> ریال</p>
      <p class="hint">${hint}</p>
      <form class="stack" onsubmit="submitAdvance(event, ${id})">
        ${amountField}
        <label>ملاحظات (اختیاری)<input name="note"></label>
        <div class="row"><button class="btn" type="submit">${x.stage === 'general' ? 'تأیید نهایی' : 'تأیید'}</button><button class="btn ghost" type="button" onclick="closeModal()">انصراف</button></div>
      </form>`);
  };
  window.submitAdvance = async function (ev, id) {
    ev.preventDefault();
    const f = ev.target;
    const data = { id, decision: 'approve', note: f.note.value };
    if (f.approved) {
      const v = toEnDigits(f.approved.value).replace(/\D/g, '');
      if (!v || Number(v) <= 0) return toast('مبلغ تأییدی را وارد کنید.', 'bad');
      data.approved = v;
    }
    if (f.installments) {
      const n = toEnDigits(f.installments.value).replace(/\D/g, '');
      if (!n || Number(n) < 1 || Number(n) > 60) return toast('تعداد اقساط باید بین ۱ و ۶۰ باشد.', 'bad');
      data.installments = n;
    }
    const r = await api('admin', 'advance_decide', data);
    toast(r.ok ? 'تأیید شد' : r.message, r.ok ? 'ok' : 'bad');
    if (r.ok) { closeModal(); loadAdvances(); }
  };
  window.rejectAdvance = function (id) {
    const name = ((window._adv || []).find(v => v.id === id) || {}).name || '';
    openModal(`<h2>رد درخواست</h2><p class="muted">${esc(name)}</p>
      <form class="stack" onsubmit="submitReject(event, ${id})">
        <label>دلیل رد (الزامی)<textarea name="note" required></textarea></label>
        <div class="row"><button class="btn danger" type="submit">رد درخواست</button><button class="btn ghost" type="button" onclick="closeModal()">انصراف</button></div>
      </form>`);
  };
  window.submitReject = async function (ev, id) {
    ev.preventDefault();
    const r = await api('admin', 'advance_decide', { id, decision: 'reject', note: ev.target.note.value });
    toast(r.ok ? 'رد شد' : r.message, r.ok ? 'ok' : 'bad');
    if (r.ok) { closeModal(); loadAdvances(); }
  };

  /* ---------- مرخصی ---------- */
  const leaveStageLabel = { direct: 'نزد مدیر مستقیم', general: 'نزد مدیر ارشد', admin: 'نزد منابع انسانی', final: 'تأیید نهایی', rejected: 'رد نهایی' };
  const leaveStageClass = { direct: 'b-warn', general: 'b-info', admin: 'b-gray', final: 'b-ok', rejected: 'b-bad' };
  const leaveHistoryItem = (label, value, state = '') => `<div class="leave-history-item ${state}"><small>${label}</small><div>${value ? esc(value) : 'هنوز تصمیمی ثبت نشده است.'}</div></div>`;
  const renderLeaveCard = (x) => {
    const amount = String(x.amount ?? '0');
    const unit = x.start_time ? 'ساعت' : 'روز';
    const range = x.start_time
      ? `${esc(x.from_date)} · ${esc(x.start_time)} تا ${esc(x.end_time)}`
      : `${esc(x.from_date)} تا ${esc(x.to_date)}`;
    const duration = `${esc(amount)} ${unit}`;
    const isFinalRejected = x.stage === 'admin' && x.to_admin_decision === 'reject';
    const canAct = !!x.can_decide;
    const actions = canAct
      ? (isFinalRejected
        ? `<button class="btn sm danger" onclick="leaveReject(${x.id})">ثبت رد نهایی</button>`
        : `<button class="btn sm" onclick="leaveApprove(${x.id})">${x.stage === 'admin' ? 'بررسی و ثبت نهایی' : 'بررسی و تأیید'}</button>${x.stage === 'admin' ? '' : `<button class="btn sm danger" onclick="leaveReject(${x.id})">رد درخواست</button>`}`)
      : (x.stage === 'final'
        ? `<span class="badge b-ok">تأیید نهایی${x.to_admin_decision === 'notified' ? ' مدیر ارشد' : ''}</span>`
        : (x.stage === 'rejected' ? '<span class="badge b-bad">رد نهایی</span>' : '<span class="leave-alert">در انتظار اقدام مرحله‌ی مربوط</span>'));
    return `<article class="leave-card stage-${esc(x.stage)}">
      <header class="leave-card-head">
        <div class="leave-avatar">${esc(String(x.name || '؟').trim().charAt(0))}</div>
        <div class="leave-person"><b>${esc(x.name || '—')}</b><small>سمت: ${esc(x.employee_post || '—')} · کد پرسنلی: <span class="ltr">${esc(x.personnel_code || '—')}</span> · کد ملی: <span class="ltr">${esc(x.national_code || '—')}</span></small></div>
        <div class="leave-track"><span class="leave-track-code">${esc(x.tracking || '')}</span><span class="leave-track-date">ثبت: ${esc(x.created_jdate || '—')}</span></div>
        <span class="leave-type">${esc(x.type_label || 'مرخصی')}</span>
      </header>
      <div class="leave-facts">
        <div class="leave-fact"><small>بازه درخواست</small><b>${range}</b></div>
        <div class="leave-fact"><small>مدت مرخصی</small><b>${duration}</b></div>
        <div class="leave-fact"><small>وضعیت گردش</small><b><span class="badge ${leaveStageClass[x.stage] || 'b-gray'}">${leaveStageLabel[x.stage] || esc(x.stage)}</span></b></div>
        <div class="leave-fact"><small>زمان درخواست</small><b>${esc(x.created_jdate || '—')}</b></div>
      </div>
      <div class="leave-reason"><small>دلیل ثبت‌شده توسط کارمند</small><p>${esc(x.reason || '—')}</p></div>
      <div class="leave-history">
        ${leaveHistoryItem('تصمیم مدیر مستقیم', x.direct_decision, x.direct_decision ? '' : 'pending')}
        ${leaveHistoryItem('تصمیم مدیر ارشد', x.general_decision, x.general_decision ? '' : 'pending')}
        ${leaveHistoryItem('وضعیت منابع انسانی', x.to_admin_decision === 'approve' ? 'تأیید نهایی ثبت شد' : (x.to_admin_decision === 'reject' ? 'رد برای ثبت نهایی' : (x.to_admin_decision === 'notified' ? 'از تأیید نهایی مطلع شد' : '')), x.to_admin_decision ? '' : 'pending')}
      </div>
      ${x.reject_reason ? `<div class="leave-reason" style="background:#fff5f5;border-color:#fecaca"><small style="color:#b91c1c">دلیل رد ثبت‌شده</small><p>${esc(x.reject_reason)}</p></div>` : ''}
      <footer class="leave-actions"><div class="row">${actions}${window.IS_ADMIN ? `<button class="btn sm danger" onclick="deleteRequest('leave', ${x.id})">حذف درخواست</button>` : ''}</div><div class="row"><a class="btn sm ghost" target="_blank" href="request_report.php?type=leave&mode=form&id=${x.id}">فرم چاپی درخواست</a></div></footer>
    </article>`;
  };
  window.loadLeaves = async function () {
    const filter = {
      q: document.getElementById('lvQ')?.value || '',
      type: document.getElementById('lvType')?.value || '',
      stage: document.getElementById('lvStage')?.value || '',
      from: document.getElementById('lvFrom')?.value || '',
      to: document.getElementById('lvTo')?.value || ''
    };
    const r = await api('admin', 'leaves', { filter });
    if (!r.ok) return toast(r.message, 'bad');
    window._leaves = r.requests || [];
    updateRequestReportLinks('lv', 'leave', filter);
    const list = window._leaves;
    const pending = list.filter(x => !['final', 'rejected'].includes(x.stage)).length;
    const hourly = list.filter(x => !!x.start_time).length;
    const summary = document.getElementById('lvSummary');
    if (summary) summary.innerHTML = `<div class="request-summary-item"><span>کل درخواست‌های قابل مشاهده</span><b>${fnum(list.length)}</b></div><div class="request-summary-item"><span>در انتظار بررسی</span><b>${fnum(pending)}</b></div><div class="request-summary-item"><span>مرخصی ساعتی</span><b>${fnum(hourly)}</b></div><div class="request-summary-item"><span>مرخصی روزانه</span><b>${fnum(list.length - hourly)}</b></div>`;
    document.getElementById('ltable').innerHTML = list.length ? list.map(renderLeaveCard).join('') : '<div class="leave-empty">درخواستی با فیلترهای انتخاب‌شده برای شما پیدا نشد.</div>';
  };
  window.leaveApprove = function (id) {
    const x = (window._leaves || []).find(v => Number(v.id) === Number(id));
    if (!x) return toast('اطلاعات درخواست پیدا نشد؛ فهرست را تازه کنید.', 'bad');
    const isFinal = x.stage === 'admin';
    const isSeniorFinal = x.stage === 'general';
    const range = x.start_time
      ? `${esc(x.from_date)} از ساعت ${esc(x.start_time)} تا ${esc(x.end_time)}`
      : `${esc(x.from_date)} تا ${esc(x.to_date)}`;
    openModal(`<h2>${isFinal ? 'ثبت نهایی مرخصی' : (isSeniorFinal ? 'تأیید نهایی مدیر ارشد' : 'بازبینی و تأیید مرخصی')}</h2>
      <p class="muted">لطفاً پیش از ثبت تصمیم، اطلاعات درخواست را بررسی کنید.</p>
      <div class="leave-review-modal">
        <div><small>کارمند</small><b>${esc(x.name || '—')}</b></div>
        <div><small>نوع مرخصی</small><b>${esc(x.type_label || '—')}</b></div>
        <div><small>بازه</small><b>${range}</b></div>
        <div><small>مدت</small><b>${esc(String(x.amount ?? '0'))} ${x.start_time ? 'ساعت' : 'روز'}</b></div>
        <div class="wide"><small>دلیل درخواست</small><p>${esc(x.reason || '—')}</p></div>
      </div>
      <form class="stack" onsubmit="submitLeaveApprove(event, ${id})">
        <label class="leave-final-confirm"><input type="checkbox" name="confirm" required> اطلاعات درخواست را بررسی کردم و با ${isFinal ? 'ثبت نهایی' : (isSeniorFinal ? 'تأیید نهایی و ثبت در پرونده پرسنلی' : 'ارسال به مرحله بعد')} موافقم.</label>
        <div class="row"><button class="btn" type="submit">${isFinal ? 'ثبت نهایی مرخصی' : (isSeniorFinal ? 'تأیید نهایی مرخصی' : 'تأیید و ارسال به مرحله بعد')}</button><button class="btn ghost" type="button" onclick="closeModal()">بازگشت</button></div>
      </form>`);
  };
  window.submitLeaveApprove = async function (ev, id) {
    ev.preventDefault();
    const checked = ev.target.querySelector('input[name="confirm"]')?.checked;
    if (!checked) return;
    closeModal();
    await leaveDecide(id, 'approve');
  };
  window.leaveDecide = async function (id, decision, note = '') {
    const r = await api('admin', 'leave_decide', { id, decision, note });
    toast(r.ok ? 'ثبت شد' : r.message, r.ok ? 'ok' : 'bad');
    if (r.ok) { loadLeaves(); if (window.pollNotifications) window.pollNotifications(); }
  };
  window.leaveReject = function (id) {
    openModal(`<h2>رد مرخصی</h2>
      <form class="stack" onsubmit="submitLeaveReject(event, ${id})">
        <label>دلیل رد (الزامی)<textarea name="note" required></textarea></label>
        <div class="row"><button class="btn danger" type="submit">رد درخواست</button><button class="btn ghost" type="button" onclick="closeModal()">انصراف</button></div>
      </form>`);
  };
  window.submitLeaveReject = async function (ev, id) {
    ev.preventDefault();
    closeModal();
    await leaveDecide(id, 'reject', ev.target.note.value);
  };

  /* ---------- ارتقا گروه شغلی ---------- */
  const PST = { direct: 'نزد مدیر مستقیم', general: 'نزد مدیر ارشد', admin: 'نزد منابع انسانی', evaluated: 'ارزیابی شد ← نزد مدیر', final: 'تأیید نهایی مدیر ارشد', rejected: 'رد شده' };
  const PSC = { direct: 'b-warn', general: 'b-info', admin: 'b-gray', evaluated: 'b-info', final: 'b-ok', rejected: 'b-bad' };
  const PDEC = { approve: 'تأیید', reject: 'رد', reevaluate: 'درخواست ارزیابی مجدد' };

  const PSTEP = { direct: 0, evaluated: 0, general: 1, admin: 2, final: 3, rejected: -1 };
  const PSTEP_LABELS = ['مدیر مستقیم', 'مدیر ارشد', 'اطلاع منابع انسانی'];
  const rqInitial = (n) => esc(String(n || '؟').trim().charAt(0));
  const rqTile = (label, body, tone = '') => `<div class="rq-tile ${tone}"><small>${label}</small><div>${body}</div></div>`;

  window.renderPromoCards = function (list) {
    const btnsFor = (x) => (x.actions || []).map(a => ({
      approve: x.stage === 'admin'
        ? `<button class="btn sm" onclick="promoFinal(${x.id})">ثبت نهایی و افزایش حقوق</button>`
        : `<button class="btn sm" onclick="promoApprove(${x.id})">تأیید</button>`,
      reject: `<button class="btn sm danger" onclick="promoReject(${x.id})">${x.stage === 'admin' ? 'رد نهایی' : 'رد'}</button>`,
      reevaluate: `<button class="btn sm ghost" onclick="promoReeval(${x.id})">ارزیابی مجدد</button>`,
      evaluate: `<button class="btn sm" onclick="promoEvaluate(${x.id})">ثبت نمره‌ی ارزیابی</button>`
    }[a])).join('');

    const card = (x) => {
      const step = PSTEP[x.stage] ?? -1;
      const stepLabels = x.stage === 'admin' ? ['مدیر مستقیم', 'مدیر ارشد', 'اقدام منابع انسانی'] : PSTEP_LABELS;
      const steps = stepLabels.map((lab, i) => {
        let st = 'todo';
        if (x.stage === 'rejected') st = 'off';
        else if (step > i) st = 'done';
        else if (step === i) st = 'now';
        return `<div class="rq-step ${st}"><span class="rq-dot">${st === 'done' ? '✓' : i + 1}</span><span>${lab}</span></div>`;
      }).join('<div class="rq-line"></div>');

      const tiles = [];
      if (x.direct_decision) tiles.push(rqTile('تصمیم مدیر مستقیم',
        `<b>${esc(PDEC[x.direct_decision] || x.direct_decision)}</b> <span class="muted">— ${esc(x.direct_by || '')}</span>${x.direct_note ? `<p class="rq-note">${esc(x.direct_note)}</p>` : ''}`));
      if (x.raise_amount) tiles.push(rqTile('افزایش حقوق پیشنهادی', `<span class="rq-money">${fnum(x.raise_amount)} ریال</span>`, 'money'));
      if (x.eval_score !== null && x.eval_score !== undefined) tiles.push(rqTile('نمره‌ی ارزیابی',
        `<span class="rq-score">${esc(String(parseFloat(x.eval_score)))}<small>/100</small></span><div class="muted">${esc(x.eval_by || '')}</div>${x.eval_note ? `<p class="rq-note">${esc(x.eval_note)}</p>` : ''}`, 'score'));
      if (x.decided_by) tiles.push(rqTile('نتیجه‌ی نهایی',
        `<b>${esc(x.decided_by)}</b>${x.decision_note ? `<p class="rq-note">${esc(x.decision_note)}</p>` : ''}`, x.stage === 'final' ? 'ok' : (x.stage === 'rejected' ? 'bad' : '')));
      if (x.stage === 'final' && x.to_admin_decision === 'notified') tiles.push(rqTile('وضعیت منابع انسانی', '<b>مطلع شد · ثبت در پرونده پرسنلی</b>', 'ok'));

      const acts = btnsFor(x);
      return `<article class="rq st-${esc(x.stage)}">
        <header class="rq-h">
          <div class="rq-av">${rqInitial(x.name)}</div>
          <div class="rq-who"><b>${esc(x.name)}</b><span class="ltr muted">${esc(x.personnel_code || '')}</span></div>
          <div class="rq-tr"><span class="rq-code ltr">${esc(x.tracking)}</span><span class="muted">${esc(x.created_jdate || '')}</span></div>
          <span class="badge ${PSC[x.stage] || 'b-gray'} rq-badge">${PST[x.stage] || esc(x.stage)}</span>
        </header>
        <div class="rq-steps">${steps}</div>
        <div class="rq-change">
          <div class="rq-from"><small>شغل فعلی</small><b>${esc(x.post || '—')}</b></div>
          <div class="rq-arrow" aria-hidden="true">←</div>
          <div class="rq-to"><small>شغل درخواستی</small><b>${esc(x.job_requested || '—')}</b></div>
          <span class="rq-chip">شیفت: ${esc(x.shift || '—')}</span>
        </div>
        <div class="rq-reason"><small>دلایل درخواست</small><p>${esc(x.reason || '—')}</p></div>
        ${tiles.length ? `<div class="rq-tiles">${tiles.join('')}</div>` : ''}
        ${acts ? `<footer class="rq-a">${acts}</footer>` : ''}
      </article>`;
    };
    return list.length ? list.map(card).join('') : '<div class="rq-empty">درخواستی برای شما نیست.</div>';
  };

  window.loadPromotions = async function () {
    const r = await api('admin', 'promotions');
    if (!r.ok) return toast(r.message, 'bad');
    window._pro = r.requests;
    document.getElementById('ptable').innerHTML = window.renderPromoCards(r.requests);
  };
  window.promoDecide = async function (id, decision, note = '', score = '', amount = '') {
    const r = await api('admin', 'promo_decide', { id, decision, note, score, amount });
    toast(r.ok ? 'ثبت شد' : r.message, r.ok ? 'ok' : 'bad');
    if (r.ok) { closeModal(); loadPromotions(); if (window.pollNotifications) window.pollNotifications(); }
    return r;
  };
  window.promoReject = function (id) {
    const fin = (window._pro || []).find(p => p.id == id && p.stage === 'admin');
    openModal(`<h2>${fin ? 'رد نهایی درخواست ارتقا' : 'رد درخواست ارتقا'}</h2>
      <p class="muted">${fin ? 'درخواست رد می‌شود و نتیجه به کارمند و مدیر اعلام می‌شود.' : 'پس از ثبت، درخواست برای ثبت نهایی نزد منابع انسانی می‌رود.'}</p>
      <form class="stack" onsubmit="submitPromoNote(event, ${id}, 'reject')">
        <label>دلیل رد (الزامی)<textarea name="note" required maxlength="500"></textarea></label>
        <div class="row"><button class="btn danger" type="submit">ثبت رد</button><button class="btn ghost" type="button" onclick="closeModal()">انصراف</button></div>
      </form>`);
  };
  window.promoApprove = function (id) {
    const x = (window._pro || []).find(p => p.id == id) || {};
    const lvl = x.stage === 'evaluated' ? x.review_level : x.stage;
    const senior = lvl === 'general';
    openModal(`<h2>${senior ? 'تأیید مدیر ارشد' : 'تأیید مدیر مستقیم'}</h2>
      <p class="muted">${esc(x.name || '')} — ${esc(x.job_requested || '')}${x.eval_score !== null && x.eval_score !== undefined ? `<br><span class="score-pill">★ نمره‌ی ارزیابی: ${esc(String(parseFloat(x.eval_score)))} از ۱۰۰</span>` : ''}</p>
      <form class="stack" onsubmit="submitPromoApprove(event, ${id}, ${senior})">
        ${senior ? '<label>مبلغ افزایش حقوق پایه (ریال)<input name="amount" class="ltr" inputmode="numeric" required></label>' : ''}
        <label>${senior ? 'دلیل تأیید و توجیه این مبلغ (الزامی)' : 'دلیل تأیید (الزامی)'}<textarea name="note" required maxlength="500"></textarea></label>
        <div class="row"><button class="btn" type="submit">${senior ? 'تأیید نهایی و اطلاع منابع انسانی' : 'تأیید و ارسال به مدیر ارشد'}</button><button class="btn ghost" type="button" onclick="closeModal()">انصراف</button></div>
      </form>`);
  };
  window.submitPromoApprove = async function (ev, id, senior) {
    ev.preventDefault();
    await promoDecide(id, 'approve', ev.target.note.value, '', senior ? ev.target.amount.value : '');
  };
  window.promoFinal = function (id) {
    const x = (window._pro || []).find(p => p.id == id) || {};
    const has = !!x.raise_amount;
    openModal(`<h2>ثبت نهایی منابع انسانی</h2>
      <p class="muted">${esc(x.name || '')} — ${esc(x.job_requested || '')}</p>
      ${has ? `<p>افزایش حقوق پایه‌ی تأییدشده توسط مدیر ارشد: <span class="score-pill">${fnum(x.raise_amount)} ریال</span></p>` : ''}
      <p class="muted">این مسیر برای درخواست‌های قدیمی است که هنوز در صف منابع انسانی هستند.</p>
      <form class="stack" onsubmit="submitPromoFinal(event, ${id}, ${has})">
        ${has ? '' : '<label>مبلغ افزایش حقوق پایه (ریال)<input name="amount" class="ltr" inputmode="numeric" required></label>'}
        <label>توضیح (اختیاری)<textarea name="note" maxlength="500"></textarea></label>
        <div class="row"><button class="btn" type="submit">ثبت نهایی و افزایش حقوق</button><button class="btn ghost" type="button" onclick="closeModal()">انصراف</button></div>
      </form>`);
  };
  window.submitPromoFinal = async function (ev, id, has) {
    ev.preventDefault();
    await promoDecide(id, 'approve', ev.target.note.value, '', has ? '' : ev.target.amount.value);
  };
  window.promoReeval = function (id) {
    openModal(`<h2>درخواست ارزیابی مجدد</h2>
      <p class="muted">درخواست برای منابع انسانی ارسال می‌شود تا نمره‌ی ارزیابی را ثبت کند؛ سپس همین درخواست با نمره در پنل شما دیده می‌شود.</p>
      <form class="stack" onsubmit="submitPromoNote(event, ${id}, 'reevaluate')">
        <label>توضیح برای ادمین (اختیاری)<textarea name="note" maxlength="500"></textarea></label>
        <div class="row"><button class="btn" type="submit">ارسال برای ارزیابی</button><button class="btn ghost" type="button" onclick="closeModal()">انصراف</button></div>
      </form>`);
  };
  window.promoEvaluate = function (id) {
    const x = (window._pro || []).find(p => p.id == id) || {};
    openModal(`<h2>ثبت نمره‌ی ارزیابی</h2>
      <p class="muted">${esc(x.name || '')} — ${esc(x.job_requested || '')}${x.direct_note ? '<br>توضیح مدیر: ' + esc(x.direct_note) : ''}</p>
      <form class="stack" onsubmit="submitPromoScore(event, ${id})">
        <label>نمره‌ی ارزیابی (۰ تا ۱۰۰)<input name="score" class="ltr" inputmode="decimal" required autofocus></label>
        <label>توضیح (اختیاری)<textarea name="note" maxlength="500"></textarea></label>
        <div class="row"><button class="btn" type="submit">ثبت و ارسال به مدیر مستقیم</button><button class="btn ghost" type="button" onclick="closeModal()">انصراف</button></div>
      </form>`);
  };
  window.submitPromoNote = async function (ev, id, decision) {
    ev.preventDefault();
    await promoDecide(id, decision, ev.target.note.value);
  };
  window.submitPromoScore = async function (ev, id) {
    ev.preventDefault();
    await promoDecide(id, 'evaluate', ev.target.note.value, ev.target.score.value);
  };

  /* وقتی اعلان تازه می‌رسد، فهرست بازِ فعلی تازه می‌شود */
  window.onNotified = function () {
    const on = document.querySelector('#menu button.on');
    const name = on && on.dataset.s;
    if (name === 'dashboard') { loadDashboard(); return; }
    if (name === 'promotions') loadPromotions();
    else if (name === 'advances') loadAdvances();
    else if (name === 'leaves') loadLeaves();
    else if (name === 'penalties') loadPenalties();
  };

  /* ---------- مدیران و تنظیمات ---------- */
  window.loadAdmins = async function () {
    if (!IS_ADMIN) return;
    const r = await api('admin', 'admins');
    if (!r.ok) return toast(r.message, 'bad');
    window._admins = r.admins;
    document.getElementById('adminList').innerHTML = `<table class="t"><tr><th>نام</th><th>کاربری</th><th>نقش</th><th>کد پرسنلی</th><th></th></tr>` +
      r.admins.map(a => `<tr><td>${esc(a.name)}</td><td class="ltr">${esc(a.username)}</td>
        <td><span class="badge b-info">${({ admin: 'ادمین', senior: 'مدیر ارشد', manager: 'مدیر مستقیم' })[a.role] || esc(a.role)}</span></td>
        <td class="ltr">${esc(a.personnel_code || '—')}</td>
        <td><div class="row"><button class="btn sm ghost" onclick="editAdmin(${a.id})">ویرایش</button>
        <button class="btn sm ${a.active == 1 ? 'danger' : ''}" onclick="toggleAdmin(${a.id})">${a.active == 1 ? 'غیرفعال' : 'فعال'}</button></div></td></tr>`).join('') + '</table>';
  };
  window.editAdmin = function (id) {
    const a = (window._admins || []).find(x => x.id === id);
    if (!a) return;
    const f = document.getElementById('adForm');
    f.id.value = a.id; f.username.value = a.username; f.name.value = a.name; f.role.value = a.role;
    setAdminEmp(a.personnel_code || ''); f.password.value = '';
    f.scrollIntoView({ behavior: 'smooth' });
  };
  window.resetAdminForm = function () { document.getElementById('adForm').reset(); document.getElementById('adForm').id.value = 0; setAdminEmp(''); };
  window.setAdminEmp = async function (pc) {
    const f = document.getElementById('adForm');
    f.personnel.value = pc;
    const lbl = document.getElementById('adEmpName');
    if (!pc) { lbl.textContent = 'هیچ کارمندی انتخاب نشده'; return; }
    lbl.textContent = pc;
    const r = await api('admin', 'employees', { q: pc });
    const e = r.ok && (r.employees || []).find(x => x.personnel_code === pc);
    if (e) lbl.textContent = `${e.name} (${pc})`;
  };
  window.pickAdminEmployee = function () {
    pickEmployee(e => { if (e) setAdminEmp(e.personnel_code); }, { title: 'انتخاب کارمند برای این حساب' });
  };
  window.saveAdmin = async function (ev) {
    ev.preventDefault();
    const f = ev.target;
    const data = { id: Number(f.id.value), username: f.username.value, name: f.name.value, role: f.role.value, personnel: f.personnel.value, password: f.password.value };
    const r = await api('admin', 'admin_save', data);
    toast(r.message, r.ok ? 'ok' : 'bad');
    if (r.ok) { resetAdminForm(); loadAdmins(); }
  };
  window.toggleAdmin = async function (id) {
    const r = await api('admin', 'admin_toggle', { id });
    toast(r.message, r.ok ? 'ok' : 'bad');
    if (r.ok) loadAdmins();
  };
  document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
    document.getElementById('eq').addEventListener('keydown', (e) => { if (e.key === 'Enter') loadEmployees(); });
    document.getElementById('ef').addEventListener('change', loadEmployees);
    const sectionFilter = document.getElementById('esSection');
    if (sectionFilter) sectionFilter.addEventListener('change', loadEmployees);
    [['advQ', loadAdvances], ['lvQ', loadLeaves], ['prQ', loadPromotions]].forEach(([id, fn]) => {
      const field = document.getElementById(id);
      if (field) field.addEventListener('keydown', (e) => { if (e.key === 'Enter') fn(); });
    });
  });
})();

/* ---------- جریمه‌های پرسنل ---------- */
(function () {
  const penStage = { direct: 'نزد مدیر مستقیم', general: 'نزد مدیر ارشد', admin: 'نزد منابع انسانی', final: 'تأیید نهایی', rejected: 'رد یا لغو شده' };
  const penCls = { direct: 'b-warn', general: 'b-info', admin: 'b-gray', final: 'b-ok', rejected: 'b-bad' };
  let penTypes = [];
  let penAmounts = [];

  const pad = (n) => String(n).padStart(2, '0');
  /* امروز به شمسی از همان تقویم پروژه (JDate) با نمایش نام روز و تاریخ کامل */
  function todayJalali() {
    const now = new Date();
    let y, m, d;
    if (window.JDate) { [y, m, d] = window.JDate.toJ(now); }
    const key = (y && m && d) ? `${y}/${pad(m)}/${pad(d)}` : '';
    let label = key;
    try {
      label = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }).format(now);
    } catch (e) { /* مرورگر بدون تقویم فارسی: همان کلید عددی نمایش داده می‌شود */ }
    return { key, label };
  }

  function penStatus(x) {
    if (x.stage === 'rejected' && x.to_admin_decision === 'cancelled') return 'لغو شده';
    if (x.stage === 'admin' && x.to_admin_decision === 'reject') return 'رد شده؛ منتظر ثبت نهایی';
    if (x.stage === 'admin' && x.to_admin_decision === 'approve') return 'تأیید شده؛ منتظر ثبت نهایی';
    return penStage[x.stage] || x.stage;
  }

  function penRow(x) {
    const deduct = x.stage !== 'final' ? ''
      : (x.deducted_period_id ? '<div class="hint">از فیش کسر شده</div>' : `<div class="hint">کسر از فیش ${esc(x.deduct_label)}</div>`);
    const btns = [
      x.can_decide ? `<button class="btn sm" onclick="penDecide(${x.id},'approve')">${x.stage === 'admin' ? 'ثبت نهایی' : 'تأیید'}</button><button class="btn sm danger" onclick="penDecide(${x.id},'reject')">رد</button>` : '',
      x.can_cancel ? `<button class="btn sm danger" onclick="penCancel(${x.id})">لغو</button>` : '',
    ].join('');
    return `<tr>
      <td class="ltr">${esc(x.tracking)}</td>
      <td>${esc(x.name)}<div class="hint ltr">${esc(x.personnel_code)}</div></td>
      <td>${esc(x.type_title)}<div class="hint">${esc(x.note)}</div></td>
      <td class="ltr">${money(x.amount)}</td>
      <td class="ltr">${esc(x.jdate)}</td>
      <td>${esc(x.created_by)}</td>
      <td><span class="badge ${penCls[x.stage] || 'b-gray'}">${esc(penStatus(x))}</span>${deduct}</td>
      <td><div class="row">${btns || '<span class="hint">—</span>'}</div></td>
    </tr>`;
  }

  window.loadPenalties = async function () {
    const filter = { stage: document.getElementById('penStage').value, q: document.getElementById('penQ').value || '' };
    const r = await api('admin', 'penalties', { filter });
    if (!r.ok) return toast(r.message, 'bad');
    penTypes = r.types || [];
    penAmounts = r.amounts || [];
    document.getElementById('pentable').innerHTML =
      `<tr><th>کد</th><th>کارمند</th><th>نوع و توضیح</th><th>مبلغ (ریال)</th><th>تاریخ جریمه</th><th>ثبت‌کننده</th><th>وضعیت</th><th>عملیات</th></tr>` +
      (r.requests.length ? r.requests.map(penRow).join('')
        : '<tr><td colspan="8" class="empty">جریمه‌ای با این فیلترها پیدا نشد.</td></tr>');
  };

  window.openPenaltyForm = async function () {
    if (!penTypes.length || !penAmounts.length) await loadPenalties();
    if (!penTypes.length) return toast('ابتدا از «مدیران و تنظیمات» یک نوع جریمه تعریف کنید.', 'bad');
    if (!penAmounts.length) return toast('ابتدا از «مدیران و تنظیمات» یک مبلغ برای جریمه تعریف کنید.', 'bad');
    pickEmployee(emp => { if (emp) showPenaltyForm(emp); }, { title: 'انتخاب کارمند برای ثبت جریمه', filter: 'all' });
  };

  function showPenaltyForm(emp) {
    const today = todayJalali();
    openModal(`<h2>ثبت جریمه</h2>
      <p class="muted">${esc(emp.name)} — <span class="ltr">${esc(emp.personnel_code || '')}</span></p>
      <form class="stack" onsubmit="submitPenalty(event, ${Number(emp.id)})">
        <label>نوع جریمه<select name="type_id" required>${penTypes.map(t => `<option value="${t.id}">${esc(t.title)}</option>`).join('')}</select></label>
        <label>مبلغ جریمه<select name="amount_id" required>${penAmounts.map(a => `<option value="${a.id}">${money(a.amount)} ریال</option>`).join('')}</select></label>
        <label>تاریخ جریمه<input name="jdate" class="ltr" data-jdate required readonly value="${esc(today.key)}" placeholder="1405/07/20"></label>
        <div class="hint">امروز: <b>${esc(today.label)}</b> · تاریخ را از تقویم انتخاب کنید؛ تاریخ بعد از امروز مجاز نیست.</div>
        <label>توضیح جریمه<textarea name="note" rows="3" maxlength="500" required placeholder="شرح دلیل جریمه"></textarea></label>
        <div class="row"><button class="btn" type="submit">ثبت جریمه</button><button class="btn ghost" type="button" onclick="closeModal()">انصراف</button></div>
      </form>`);
  }

  window.submitPenalty = async function (ev, empId) {
    ev.preventDefault();
    const f = ev.target;
    const note = f.note.value.trim();
    if (!note) return toast('توضیح جریمه را بنویسید.', 'bad');
    if (!f.jdate.value) return toast('تاریخ جریمه را از تقویم انتخاب کنید.', 'bad');
    const r = await api('admin', 'penalty_create', {
      employee_id: empId,
      type_id: Number(f.type_id.value),
      amount_id: Number(f.amount_id.value),
      jdate: f.jdate.value,
      note,
    });
    toast(r.message || (r.ok ? 'ثبت شد' : 'خطا'), r.ok ? 'ok' : 'bad');
    if (r.ok) { closeModal(); loadPenalties(); }
  };

  window.penDecide = async function (id, decision) {
    let note = '';
    if (decision === 'reject') {
      note = prompt('دلیل رد جریمه را بنویسید:');
      if (note === null) return;
      if (!note.trim()) return toast('برای رد جریمه، دلیل را بنویسید.', 'bad');
    } else if (!confirm('این جریمه تأیید شود؟')) {
      return;
    }
    const r = await api('admin', 'penalty_decide', { id, decision, note });
    toast(r.message || (r.ok ? 'ثبت شد' : 'خطا'), r.ok ? 'ok' : 'bad');
    if (r.ok) loadPenalties();
  };

  window.penCancel = async function (id) {
    const note = prompt('دلیل لغو جریمه را بنویسید (پس از لغو از فیش کسر نمی‌شود):');
    if (note === null) return;
    if (!note.trim()) return toast('دلیل لغو را بنویسید.', 'bad');
    const r = await api('admin', 'penalty_cancel', { id, note });
    toast(r.message || (r.ok ? 'لغو شد' : 'خطا'), r.ok ? 'ok' : 'bad');
    if (r.ok) loadPenalties();
  };

  /* ----- تنظیمات: نوع جریمه، مبلغ‌ها و روش کسر ----- */
  const toggleBtn = (fn, id, active) => `<button class="btn sm ghost" type="button" onclick="${fn}(${id})">${active ? 'غیرفعال کردن' : 'فعال کردن'}</button>`;

  window.renderPenaltySettings = function (s) {
    window._penTypesAdmin = s.types || [];
    window._penAmountsAdmin = s.amounts || [];
    document.getElementById('penTypes').innerHTML = (s.types && s.types.length)
      ? `<div style="overflow:auto"><table class="t"><tr><th>نوع جریمه</th><th>وضعیت</th><th>عملیات</th></tr>` +
        s.types.map(t => `<tr><td>${esc(t.title)}</td>
          <td><span class="badge ${t.active ? 'b-ok' : 'b-gray'}">${t.active ? 'فعال' : 'غیرفعال'}</span></td>
          <td><div class="row"><button class="btn sm ghost" type="button" onclick="editPenaltyType(${t.id})">ویرایش</button>${toggleBtn('togglePenaltyType', t.id, t.active)}</div></td></tr>`).join('') + '</table></div>'
      : '<div class="empty">هنوز نوع جریمه‌ای تعریف نشده است.</div>';
    document.getElementById('penAmounts').innerHTML = (s.amounts && s.amounts.length)
      ? `<div style="overflow:auto"><table class="t"><tr><th>مبلغ (ریال)</th><th>وضعیت</th><th>عملیات</th></tr>` +
        s.amounts.map(a => `<tr><td class="ltr">${money(a.amount)}</td>
          <td><span class="badge ${a.active ? 'b-ok' : 'b-gray'}">${a.active ? 'فعال' : 'غیرفعال'}</span></td>
          <td><div class="row"><button class="btn sm ghost" type="button" onclick="editPenaltyAmount(${a.id})">ویرایش</button>${toggleBtn('togglePenaltyAmount', a.id, a.active)}</div></td></tr>`).join('') + '</table></div>'
      : '<div class="empty">هنوز مبلغی برای جریمه تعریف نشده است.</div>';
    document.querySelectorAll('#penSourceForm input[name=penalty_src]').forEach(i => { i.checked = i.value === s.source; });
  };

  window.editPenaltyType = function (id) {
    const t = (window._penTypesAdmin || []).find(x => Number(x.id) === Number(id));
    if (!t) return;
    const f = document.getElementById('penTypeForm');
    f.pt_id.value = t.id;
    f.title.value = t.title;
    f.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };
  window.resetPenaltyTypeForm = function () {
    const f = document.getElementById('penTypeForm');
    f.reset();
    f.pt_id.value = '0';
  };
  window.savePenaltyType = async function (ev) {
    ev.preventDefault();
    const f = ev.target;
    const r = await api('admin', 'penalty_type_save', { id: f.pt_id.value, title: f.title.value });
    toast(r.message || (r.ok ? 'ذخیره شد' : 'خطا'), r.ok ? 'ok' : 'bad');
    if (r.ok) { resetPenaltyTypeForm(); loadWindows(); }
  };
  window.togglePenaltyType = async function (id) {
    const r = await api('admin', 'penalty_type_toggle', { id });
    toast(r.message || (r.ok ? 'انجام شد' : 'خطا'), r.ok ? 'ok' : 'bad');
    if (r.ok) loadWindows();
  };

  window.editPenaltyAmount = function (id) {
    const a = (window._penAmountsAdmin || []).find(x => Number(x.id) === Number(id));
    if (!a) return;
    const f = document.getElementById('penAmountForm');
    f.pa_id.value = a.id;
    f.amount.value = groupDigits(String(a.amount));
    f.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };
  window.resetPenaltyAmountForm = function () {
    const f = document.getElementById('penAmountForm');
    f.reset();
    f.pa_id.value = '0';
  };
  window.savePenaltyAmount = async function (ev) {
    ev.preventDefault();
    const f = ev.target;
    const r = await api('admin', 'penalty_amount_save', { id: f.pa_id.value, amount: toEnDigits(f.amount.value).replace(/\D/g, '') });
    toast(r.message || (r.ok ? 'ذخیره شد' : 'خطا'), r.ok ? 'ok' : 'bad');
    if (r.ok) { resetPenaltyAmountForm(); loadWindows(); }
  };
  window.togglePenaltyAmount = async function (id) {
    const r = await api('admin', 'penalty_amount_toggle', { id });
    toast(r.message || (r.ok ? 'انجام شد' : 'خطا'), r.ok ? 'ok' : 'bad');
    if (r.ok) loadWindows();
  };

  window.savePenaltySource = async function (ev) {
    ev.preventDefault();
    const sel = ev.target.querySelector('input[name=penalty_src]:checked');
    const r = await api('admin', 'penalty_source_save', { source: sel ? sel.value : '' });
    toast(r.message || (r.ok ? 'ذخیره شد' : 'خطا'), r.ok ? 'ok' : 'bad');
  };
})();
