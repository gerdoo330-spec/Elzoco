/* پنل کارمند */
(function () {
  const stageLabel = { direct: 'نزد مدیر مستقیم', general: 'نزد مدیر ارشد', admin: 'نزد منابع انسانی', final: 'تأیید نهایی', rejected: 'رد شده' };
  const advLabel = { 'در انتظار بررسی': 'b-warn', 'تایید شده': 'b-ok', 'رد شده': 'b-bad' };
  const promoLabel = { direct: 'نزد مدیر مستقیم', general: 'نزد مدیر ارشد', admin: 'نزد منابع انسانی', evaluated: 'در حال تصمیم‌گیری مدیر', final: 'تأیید نهایی مدیر ارشد', rejected: 'رد شده' };
  const promoCls = { direct: 'b-warn', general: 'b-info', admin: 'b-gray', evaluated: 'b-info', final: 'b-ok', rejected: 'b-bad' };
  const PAGES = ['payslip', 'advance', 'loan', 'leave', 'promotion', 'penalties'];
  const REQ_PAGES = ['advance', 'loan', 'leave', 'promotion'];
  /* مساعده و وام: یک قالب، دو مقصد */
  const FIN = {
    advance: { box: 'advBody', p: 'adv', action: 'advance_submit', title: 'مساعده' },
    loan: { box: 'loanBody', p: 'loan', action: 'loan_submit', title: 'وام' },
  };
  let state = null;

  /* ---------- ناوبری بین صفحات ---------- */
  function pageFromHash() {
    const h = location.hash.replace('#', '');
    return PAGES.includes(h) ? h : 'payslip';
  }

  window.switchTab = function (name) {
    const inReq = REQ_PAGES.includes(name);
    document.querySelectorAll('.tabs button[data-tab]').forEach(b => {
      const on = b.dataset.tab === name || (b.dataset.tab === 'requests' && inReq);
      b.classList.toggle('on', on);
    });
    document.querySelectorAll('.dd-item').forEach(a => a.classList.toggle('current', a.dataset.go === name));
    document.querySelectorAll('.pane').forEach(p => p.classList.toggle('on', p.id === 'pane-' + name));
    closeReqMenu();
    if (location.hash !== '#' + name) history.replaceState(null, '', '#' + name);
  };

  /* ---------- منوی «درخواست‌های من» ---------- */
  function reqDropdown() { return document.getElementById('ddReq'); }
  function closeReqMenu() {
    const dd = reqDropdown();
    if (!dd) return;
    dd.classList.remove('open');
    dd.querySelector('.dd-btn').setAttribute('aria-expanded', 'false');
  }
  window.toggleReqMenu = function (ev) {
    ev.stopPropagation();
    const dd = reqDropdown();
    const open = !dd.classList.contains('open');
    dd.classList.toggle('open', open);
    ev.currentTarget.setAttribute('aria-expanded', String(open));
  };
  document.addEventListener('click', (e) => { if (!e.target.closest('#ddReq') || e.target.closest('.dd-item')) closeReqMenu(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeReqMenu(); });
  window.addEventListener('hashchange', () => switchTab(pageFromHash()));

  /* با رسیدن اعلان تازه، وضعیت درخواست‌ها تازه می‌شود (مگر وقتی کارمند در حال پر کردن فرم است) */
  window.onNotified = function () {
    const typing = document.activeElement && /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName);
    if (!typing) load();
  };

  /* ---------- بارگذاری ---------- */
  async function load() {
    const r = await api('employee', 'me');
    if (!r.ok) { if (!r.logout) toast(r.message, 'bad'); return; }
    state = r;
    document.getElementById('loading').style.display = 'none';
    document.getElementById('app').style.display = '';
    const steps = [
      () => renderInfo(r.employee),
      () => renderPeriods(r.periods),
      () => renderFin(r.advance, 'advance'),
      () => renderFin(r.loan, 'loan'),
      () => renderPromotion(r.promotion),
      () => renderLeave(r.leave),
      () => renderPenalties(r.penalties),
    ];
    steps.forEach(fn => { try { fn(); } catch (err) { console.error(err); } });
    switchTab(pageFromHash());
  }

  function renderInfo(e) {
    document.getElementById('who').textContent = e.name;
    document.getElementById('info').innerHTML = `
      <div class="grid g4">
        <div class="stat"><span class="l">نام و نام خانوادگی</span><span class="v" style="font-size:1.05rem">${esc(e.name)}</span></div>
        <div class="stat"><span class="l">کد پرسنلی</span><span class="v ltr" style="font-size:1.05rem">${esc(e.personnel)}</span></div>
        <div class="stat"><span class="l">عنوان پست</span><span class="v" style="font-size:1.05rem">${esc(e.post || '-')}</span></div>
        <div class="stat"><span class="l">تاریخ شروع به کار</span><span class="v ltr" style="font-size:1.05rem">${esc(e.hire_date || '-')}</span></div>
      </div>`;
  }

  /* ---------- فیش ---------- */
  function renderPeriods(periods) {
    const sel = document.getElementById('period');
    if (!periods.length) {
      sel.innerHTML = '<option>فیشی ثبت نشده است</option>';
      document.getElementById('slip').innerHTML = '<div class="empty">هنوز فیش حقوقی برای شما ثبت نشده است.</div>';
      return;
    }
    sel.innerHTML = periods.map(p => `<option value="${p.id}">${esc(p.label)}${p.has_work ? '' : ' (کارکرد ثبت نشده)'}</option>`).join('');
    loadSlip(periods[0].id);
  }

  window.loadSlip = async function (id) {
    const box = document.getElementById('slip');
    box.innerHTML = '<div class="empty">در حال دریافت فیش…</div>';
    const r = await api('employee', 'payslip', { period_id: Number(id) });
    box.innerHTML = r.ok ? renderSlip(r.slip) : `<div class="alert">${esc(r.message)}</div>`;
  };

  /* ---------- مساعده و وام ---------- */
  function renderFin(a, kind) {
    const c = FIN[kind];
    const box = document.getElementById(c.box);
    const w = a.window;
    const winTxt = w.open ? `بازه‌ی ثبت: ${esc(w.start)} تا ${esc(w.end)}` : esc(a.reason || w.message);
    let form = '';
    if (a.can_request) {
      form = `
      <form class="stack" id="${c.p}Form" onsubmit="submitFin(event, '${kind}')">
        <div class="alert ok" style="margin:0">سقف مجاز ${c.title}: <b class="ltr">${money(a.max)}</b> ریال${kind === 'advance' ? ' ·' : ` · ${esc(a.cap_note)}`}</div>
        <label>مبلغ درخواستی (ریال)<input name="amount" class="ltr" inputmode="numeric" placeholder="مثلاً 50000000" required></label>
        ${a.registered_sheba ? `
          <label class="row" style="flex-direction:row;font-weight:500"><input type="radio" name="use_registered" value="1" checked style="width:auto"> استفاده از شبای ثبت‌شده در سیستم ${window.shebaBankHint(a.registered_sheba)}</label>
          <label class="row" style="flex-direction:row;font-weight:500"><input type="radio" name="use_registered" value="0" style="width:auto"> وارد کردن شماره شبای جدید</label>`
          : '<input type="hidden" name="use_registered" value="0">'}
        <label id="${c.p}ShebaLabel" class="sheba-wrap" ${a.registered_sheba ? 'style="display:none"' : ''}>شماره شبا (۲۴ رقم بدون IR)
          <div class="sheba-field"><span class="sheba-pre">IR</span><input name="sheba" class="ltr" inputmode="numeric" maxlength="24" autocomplete="off" placeholder="۲۴ رقم شبا"></div>
          <div class="sheba-info"><span data-bank class="sheba-bank">بعد از ۵ رقم، نام بانک نمایش داده می‌شود</span><span data-count class="sheba-count">۰ از ۲۴</span></div>
        </label>
        <button class="btn" type="submit">ثبت درخواست ${c.title}</button>
      </form>`;
    } else {
      form = `<div class="alert warn">${esc(a.reason)}</div>`;
    }
    const rows = a.requests.map(r => `<tr>
        <td class="ltr">${esc(r.tracking)}</td><td>${esc(r.jdate || '')}</td>
        <td class="ltr">${money(r.amount)}</td><td class="ltr">${r.approved_amount ? money(r.approved_amount) : '-'}</td>
        <td><span class="badge ${r.stage === 'final' ? 'b-ok' : (advLabel[r.status] || 'b-gray')}">${r.stage === 'final' ? 'تأیید نهایی' : esc(r.status)}</span></td>
        <td>${esc(r.note || '')}</td></tr>`).join('');
    box.innerHTML = `
      <div class="grid g2" style="align-items:start">
        <div class="card"><div class="section-title"><h2>درخواست ${c.title}</h2><span class="badge b-info">${esc(winTxt)}</span></div>${form}</div>
        <div class="card flush"><div style="padding:18px 20px 0"><h2>درخواست‌های ${c.title} من</h2></div>
          ${a.requests.length ? `<div style="overflow:auto"><table class="t"><tr><th>کد پیگیری</th><th>تاریخ</th><th>درخواستی</th><th>تأییدی</th><th>وضعیت</th><th>ملاحظات</th></tr>${rows}</table></div>` : `<div class="empty">هنوز درخواستی ثبت نکرده‌اید.</div>`}
        </div>
      </div>`;
    const shebaEl = document.getElementById(c.p + 'ShebaLabel');
    if (shebaEl) window.bindSheba(shebaEl);
    box.querySelectorAll('input[name=use_registered]').forEach(r => r.addEventListener('change', () => {
      const show = box.querySelector('input[name=use_registered]:checked')?.value === '0';
      shebaEl.style.display = show ? '' : 'none';
    }));
  }

  window.submitFin = async function (ev, kind) {
    ev.preventDefault();
    const c = FIN[kind];
    const f = ev.target, btn = f.querySelector('button[type=submit]');
    const data = Object.fromEntries(new FormData(f).entries());
    data.amount = data.amount.replace(/[,\s]/g, '');
    data.use_registered = data.use_registered === '1';
    setBusy(btn, true);
    const r = await api('employee', c.action, data);
    setBusy(btn, false, 'ثبت درخواست ' + c.title);
    toast(r.message || (r.ok ? 'ثبت شد' : 'خطا'), r.ok ? 'ok' : 'bad');
    if (r[kind]) renderFin(r[kind], kind);
  };

  /* ---------- ارتقا گروه شغلی ---------- */
  /* جمله‌ها با نقطه، علامت سؤال/تعجب یا خط جدید جدا می‌شوند (همان قاعده‌ی سرور) */
  const sentenceCount = (t) => String(t || '').split(/[.!?؟۔\n]+/).map(s => s.trim()).filter(Boolean).length;

  function renderPromotion(p) {
    const box = document.getElementById('promoBody');
    const shiftVisuals = [
      { match: /صبح/, cls: 'shift-morning', title: 'صبح', desc: '۸ ساعت کاری', icon: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>' },
      { match: /شب/, cls: 'shift-night', title: 'شب', desc: '۸ ساعت کاری', icon: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 15.5A8.5 8.5 0 0 1 8.5 3.5 8.7 8.7 0 1 0 20.5 15.5Z"/><path d="m16.5 3 .5 1.5 1.5.5-1.5.5-.5 1.5-.5-1.5-1.5-.5 1.5-.5.5-1.5Z"/></svg>' },
      { match: /فول/, cls: 'shift-full', title: 'فول تایم', desc: '۱۲ ساعت کاری', icon: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>' },
      { match: /گردشی/, cls: 'shift-rotating', title: 'گردشی', desc: 'برنامه چرخشی شیفت', icon: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7h-5l2-2"/><path d="M4 17h5l-2 2"/><path d="M6.2 9A7 7 0 0 1 18 6l2 1M4 17l2-1a7 7 0 0 0 11.8-3"/></svg>' }
    ];
    const shiftCard = (kind, s, i) => {
      const v = shiftVisuals.find(x => x.match.test(s)) || shiftVisuals[i] || shiftVisuals[3];
      return `<button type="button" class="sh-opt ${v.cls}" data-kind="${kind}" data-val="${esc(s)}" aria-pressed="false" title="${esc(s)}">
        <span class="sh-ic">${v.icon}</span>
        <span class="sh-t">${esc(s)}</span>
        <span class="sh-ck" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span>
      </button>`;
    };
    const currentCards = p.shifts.map((s, i) => shiftCard('current_shift', s, i)).join('');
    const requestedCards = p.shifts.map((s, i) => shiftCard('shift', s, i)).join('');
    const form = p.can_request ? `
      <form class="stack" id="promoForm" onsubmit="submitPromo(event)">
        <div class="alert ok" style="margin:0">پست فعلی شما: <b>${esc(p.post || '-')}</b></div>
        <label>شغل مورد درخواست<input name="job" maxlength="190" required placeholder="مثلاً: سرپرست شیفت"></label>
        <div class="sh-block">
          <div class="sh-head"><b>شیفت فعلی شما</b><small>شیفتی که الان در آن کار می‌کنید</small></div>
          <div class="sh-grid" data-group="current_shift">${currentCards}</div>
          <input type="hidden" name="current_shift" value="">
        </div>
        <div class="sh-block">
          <div class="sh-head"><b>شیفت درخواستی</b><small>شیفتی که برای ارتقا می‌خواهید</small></div>
          <div class="sh-grid" data-group="shift">${requestedCards}</div>
          <input type="hidden" name="shift" value="">
        </div>
        <label>دلایل ارتقا یا افزایش حقوق
          <textarea name="reason" rows="6" required placeholder="حدود ۵ جمله بنویسید و هر جمله را با نقطه جدا کنید."></textarea>
          <span class="hint" id="promoCount">۰ جمله · بین ${p.min_sentences} تا ${p.max_sentences} جمله</span>
        </label>
        <div class="hint">درخواست شما به سرپرست یا مدیر مستقیمتان ارسال می‌شود.</div>
        <button class="btn" type="submit">ارسال درخواست ارتقا</button>
      </form>` : `<div class="alert warn">${esc(p.reason)}</div>`;
    const rows = p.requests.map(r => `<tr>
        <td class="ltr">${esc(r.tracking)}</td><td>${esc(r.created_jdate)}</td>
        <td>${esc(r.job_requested)}</td><td class="sh-cell">${esc(r.current_shift || '—')} <span class="sh-arrow">←</span> ${esc(r.shift)}</td>
        <td><span class="badge ${promoCls[r.stage] || 'b-gray'}">${promoLabel[r.stage] || esc(r.stage)}</span>
          ${r.decision_note ? `<div class="hint">${esc(r.decision_note)}</div>` : ''}</td></tr>`).join('');
    box.innerHTML = `
      <div class="grid g2" style="align-items:start">
        <div class="card"><h2>درخواست ارتقای گروه شغلی</h2>${form}</div>
        <div class="card flush"><div style="padding:18px 20px 0"><h2>درخواست‌های ارتقای من</h2></div>
          ${p.requests.length ? `<div style="overflow:auto"><table class="t"><tr><th>کد</th><th>تاریخ</th><th>شغل درخواستی</th><th>شیفت (فعلی ← درخواستی)</th><th>وضعیت</th></tr>${rows}</table></div>` : '<div class="empty">هنوز درخواست ارتقایی ثبت نکرده‌اید.</div>'}
        </div>
      </div>`;
    /* انتخاب شیفت با دکمه‌ها: مقدار در input مخفی ذخیره می‌شود و کارت انتخاب‌شده علامت می‌خورد */
    box.querySelectorAll('.sh-grid').forEach(group => {
      const kind = group.dataset.group;
      const hidden = box.querySelector(`input[type=hidden][name="${kind}"]`);
      group.querySelectorAll('.sh-opt').forEach(btn => {
        btn.addEventListener('click', () => {
          group.querySelectorAll('.sh-opt').forEach(b => { b.classList.remove('is-on'); b.setAttribute('aria-pressed', 'false'); });
          btn.classList.add('is-on');
          btn.setAttribute('aria-pressed', 'true');
          if (hidden) hidden.value = btn.dataset.val;
        });
      });
    });
    const ta = box.querySelector('textarea[name=reason]');
    if (ta) {
      const cnt = document.getElementById('promoCount');
      const upd = () => {
        const n = sentenceCount(ta.value);
        cnt.textContent = `${fnum(n)} جمله · بین ${p.min_sentences} تا ${p.max_sentences} جمله`;
        cnt.style.color = (n >= p.min_sentences && n <= p.max_sentences) ? 'var(--ok)' : '';
      };
      ta.addEventListener('input', upd);
    }
  }

  window.submitPromo = async function (ev) {
    ev.preventDefault();
    const f = ev.target, btn = f.querySelector('button[type=submit]');
    const data = Object.fromEntries(new FormData(f).entries());
    if (!data.current_shift) { toast('شیفت فعلی خود را انتخاب کنید.', 'bad'); return; }
    if (!data.shift) { toast('شیفت درخواستی را انتخاب کنید.', 'bad'); return; }
    setBusy(btn, true);
    const r = await api('employee', 'promo_submit', data);
    setBusy(btn, false, 'ارسال درخواست ارتقا');
    toast(r.message || (r.ok ? 'ثبت شد' : 'خطا'), r.ok ? 'ok' : 'bad');
    if (r.promotion) renderPromotion(r.promotion);
  };

  /* ---------- مرخصی ---------- */
  function renderLeave(l) {
    const types = l.types;
    document.getElementById('lvType').innerHTML = types.map(t => `<option value="${t.key}">${esc(t.label)}</option>`).join('');
    document.getElementById('lvType').addEventListener('change', toggleLeaveFields);
    toggleLeaveFields();
    renderLeaveBalance(l.balance || {});
    renderLeaveList(l.requests);
  }

  function renderLeaveBalance(b) {
    const box = document.getElementById('leaveBalanceSummary');
    if (!box) return;
    if (!b.has_hire_date) {
      box.innerHTML = '<div class="leave-balance-panel is-missing"><b>مانده مرخصی هنوز قابل محاسبه نیست</b><span>تاریخ شروع به کار شما در اطلاعات پرسنلی ثبت نشده است. لطفاً با منابع انسانی پیگیری کنید.</span></div>';
      return;
    }
    const n = value => fnum(Math.round((Number(value) || 0) * 100) / 100);
    box.innerHTML = `<section class="leave-balance-panel">
      <div class="leave-balance-head"><div><b>مانده مرخصی استحقاقی تا پایان سال ${esc(b.year_label)}</b><small>سهم سالانه ۲۶ روز؛ متناسب با تاریخ شروع همکاری محاسبه شده است.</small></div><span>شروع همکاری: <b class="ltr">${esc(b.hire_date)}</b></span></div>
      <div class="leave-balance-grid">
        <div><small>سهم امسال</small><b>${n(b.entitlement)} روز</b></div>
        <div><small>مصرف قطعی</small><b>${n(b.used)} روز</b></div>
        <div><small>در انتظار تأیید</small><b>${n(b.pending)} روز</b></div>
        <div class="is-primary"><small>مانده قطعی</small><b>${n(b.remaining)} روز</b><em>پس از درخواست‌های در انتظار: ${n(b.projected_remaining)} روز</em></div>
      </div>
      <div class="leave-balance-foot">فقط مرخصی استحقاقیِ تأیید نهایی‌شده از سهمیه کسر می‌شود؛ مرخصی پزشکی و بدون حقوق جداگانه محاسبه می‌شوند.</div>
    </section>`;
  }

  function toggleLeaveFields() {
    const hourly = document.getElementById('lvType').value === 'hourly';
    document.getElementById('lvHourly').style.display = hourly ? '' : 'none';
    document.getElementById('lvRange').style.display = hourly ? 'none' : '';
    document.getElementById('lvFromLabel').firstChild.textContent = hourly ? 'تاریخ' : 'از تاریخ';
  }

  function renderLeaveList(rows) {
    document.getElementById('lvBody').innerHTML = rows.length ? `<div style="overflow:auto"><table class="t"><tr><th>کد پیگیری</th><th>نوع</th><th>از</th><th>تا</th><th>مدت</th><th>مرحله / وضعیت</th></tr>
      ${rows.map(r => `<tr><td class="ltr">${esc(r.tracking)}</td><td>${esc(r.type_label)}</td>
      <td class="ltr">${esc(r.from_date)} ${esc(r.start_time)}</td><td class="ltr">${esc(r.to_date)} ${esc(r.end_time)}</td>
      <td>${r.start_time ? esc(r.amount) + ' ساعت' : esc(r.amount) + ' روز'}</td>
      <td><span class="badge ${r.stage === 'final' ? 'b-ok' : r.stage === 'rejected' ? 'b-bad' : 'b-warn'}">${stageLabel[r.stage] || esc(r.stage)}</span>
        ${r.reject_reason ? `<div class="hint">دلیل رد: ${esc(r.reject_reason)}</div>` : ''}</td></tr>`).join('')}
      </table></div>` : '<div class="empty">درخواست مرخصی ثبت نشده است.</div>';
  }

  window.submitLeave = async function (ev) {
    ev.preventDefault();
    const f = ev.target, btn = f.querySelector('button[type=submit]');
    const data = Object.fromEntries(new FormData(f).entries());
    setBusy(btn, true);
    const r = await api('employee', 'leave_submit', data);
    setBusy(btn, false, 'ثبت درخواست مرخصی');
    toast(r.message || (r.ok ? 'ثبت شد' : 'خطا'), r.ok ? 'ok' : 'bad');
    if (r.ok) { f.reset(); toggleLeaveFields(); }
    if (r.requests) renderLeaveList(r.requests);
    if (r.balance) renderLeaveBalance(r.balance);
  };

  /* ---------- جریمه‌های من (فقط جریمه‌های تأییدنهایی‌شده) ---------- */
  function renderPenalties(rows) {
    document.getElementById('penBody').innerHTML = rows && rows.length
      ? `<div class="card" style="overflow:auto"><table class="t"><tr><th>کد پیگیری</th><th>نوع جریمه</th><th>تاریخ</th><th>مبلغ (ریال)</th><th>وضعیت کسر</th><th>توضیح</th></tr>
        ${rows.map(r => `<tr><td class="ltr">${esc(r.tracking)}</td><td>${esc(r.type_title)}</td><td class="ltr">${esc(r.jdate)}</td>
        <td class="ltr">${money(r.amount)}</td>
        <td>${r.deducted ? '<span class="badge b-ok">کسر شده</span>' : `<span class="badge b-warn">کسر از فیش ${esc(r.deduct_label)}</span>`}</td>
        <td>${esc(r.note || '—')}</td></tr>`).join('')}</table></div>`
      : '<div class="empty">تاکنون جریمه‌ای برای شما ثبت نشده است.</div>';
  }

  window.logout = async function () {
    await api('employee', 'logout');
    location.href = 'index.php';
  };

  document.addEventListener('DOMContentLoaded', () => {
    switchTab(pageFromHash());
    load();
  });
})();
