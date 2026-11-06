/* نسخه‌ی مستقل نمایش کارت‌های ارتقا گروه شغلی.
   این فایل بعد از admin.js بارگذاری می‌شود و تابع loadPromotions را بازنویسی می‌کند.
   اگر admin.js روی سرور یا در کش قدیمی باشد، این فایل همچنان نمایش درست را تضمین می‌کند. */
(function () {
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const fnum = (v) => Number(v || 0).toLocaleString('fa-IR');
  const note = (msg, type) => (window.toast ? window.toast(msg, type) : alert(msg));

  const ST = { direct: 'نزد مدیر مستقیم', general: 'نزد مدیر ارشد', admin: 'نزد منابع انسانی', evaluated: 'ارزیابی شد ← نزد مدیر', final: 'تأیید نهایی و افزایش حقوق', rejected: 'رد شده' };
  const SC = { direct: 'b-warn', general: 'b-info', admin: 'b-gray', evaluated: 'b-info', final: 'b-ok', rejected: 'b-bad' };
  const DEC = { approve: 'تأیید', reject: 'رد', reevaluate: 'درخواست ارزیابی مجدد' };
  const STEP = { direct: 0, evaluated: 0, general: 1, admin: 2, final: 3, rejected: -1 };
  const STEP_LABELS = ['مدیر مستقیم', 'مدیر ارشد', 'منابع انسانی'];

  const tile = (label, body, tone = '') => `<div class="rq-tile ${tone}"><small>${label}</small><div>${body}</div></div>`;

  function actionsFor(x) {
    const map = {
      approve: x.stage === 'admin'
        ? `<button class="btn sm" onclick="promoFinal(${x.id})">ثبت نهایی و افزایش حقوق</button>`
        : `<button class="btn sm" onclick="promoApprove(${x.id})">تأیید</button>`,
      reject: `<button class="btn sm danger" onclick="promoReject(${x.id})">${x.stage === 'admin' ? 'رد نهایی' : 'رد'}</button>`,
      reevaluate: `<button class="btn sm ghost" onclick="promoReeval(${x.id})">ارزیابی مجدد</button>`,
      evaluate: `<button class="btn sm" onclick="promoEvaluate(${x.id})">ثبت نمره‌ی ارزیابی</button>`
    };
    return (x.actions || []).map(a => map[a] || '').join('');
  }

  function card(x) {
    const step = STEP[x.stage] ?? -1;
    const steps = STEP_LABELS.map((lab, i) => {
      let st = 'todo';
      if (x.stage === 'rejected') st = 'off';
      else if (step > i) st = 'done';
      else if (step === i) st = 'now';
      return `<div class="rq-step ${st}"><span class="rq-dot">${st === 'done' ? '✓' : i + 1}</span><span>${lab}</span></div>`;
    }).join('<div class="rq-line"></div>');

    const tiles = [];
    if (x.direct_decision) tiles.push(tile('تصمیم مدیر مستقیم',
      `<b>${esc(DEC[x.direct_decision] || x.direct_decision)}</b> <span class="muted">— ${esc(x.direct_by || '')}</span>${x.direct_note ? `<p class="rq-note">${esc(x.direct_note)}</p>` : ''}`));
    if (x.raise_amount) tiles.push(tile('افزایش حقوق پیشنهادی', `<span class="rq-money">${fnum(x.raise_amount)} ریال</span>`, 'money'));
    if (x.eval_score !== null && x.eval_score !== undefined && x.eval_score !== '') tiles.push(tile('نمره‌ی ارزیابی',
      `<span class="rq-score">${esc(String(parseFloat(x.eval_score)))}<small>/100</small></span><div class="muted">${esc(x.eval_by || '')}</div>${x.eval_note ? `<p class="rq-note">${esc(x.eval_note)}</p>` : ''}`, 'score'));
    if (x.decided_by) tiles.push(tile('نتیجه‌ی نهایی',
      `<b>${esc(x.decided_by)}</b>${x.decision_note ? `<p class="rq-note">${esc(x.decision_note)}</p>` : ''}`,
      x.stage === 'final' ? 'ok' : (x.stage === 'rejected' ? 'bad' : '')));

    const acts = actionsFor(x);
    return `<article class="rq st-${esc(x.stage)}">
      <header class="rq-h">
        <div class="rq-av">${esc(String(x.name || '؟').trim().charAt(0))}</div>
        <div class="rq-who"><b>${esc(x.name)}</b><span class="ltr muted">${esc(x.personnel_code || '')}</span></div>
        <div class="rq-tr"><span class="rq-code ltr">${esc(x.tracking)}</span><span class="muted">${esc(x.created_jdate || '')}</span></div>
        <span class="badge ${SC[x.stage] || 'b-gray'} rq-badge">${ST[x.stage] || esc(x.stage)}</span>
      </header>
      <div class="rq-steps">${steps}</div>
      <div class="rq-change">
        <div class="rq-from"><small>شغل فعلی</small><b>${esc(x.post || '—')}</b></div>
        <div class="rq-arrow" aria-hidden="true">←</div>
        <div class="rq-to"><small>شغل درخواستی</small><b>${esc(x.job_requested || '—')}</b></div>
        <div class="rq-shifts"><span class="rq-chip cur"><small>شیفت فعلی</small>${esc(x.current_shift || '—')}</span><span class="rq-chip req"><small>شیفت درخواستی</small>${esc(x.shift || '—')}</span></div>
      </div>
      <div class="rq-reason"><small>دلایل درخواست</small><p>${esc(x.reason || '—')}</p></div>
      ${tiles.length ? `<div class="rq-tiles">${tiles.join('')}</div>` : ''}
      <footer class="rq-a">${acts}<a class="btn sm ghost" target="_blank" href="request_report.php?type=promotion&mode=form&id=${x.id}">فرم چاپی درخواست</a></footer>
    </article>`;
  }

  window.renderPromoCards = function (list) {
    return list.length ? list.map(card).join('') : '<div class="rq-empty">درخواستی برای شما نیست.</div>';
  };

  window.loadPromotions = async function () {
    const box = document.getElementById('ptable');
    if (!box) return;
    const filter = {
      q: document.getElementById('prQ')?.value || '',
      stage: document.getElementById('prStage')?.value || '',
      from: document.getElementById('prFrom')?.value || '',
      to: document.getElementById('prTo')?.value || ''
    };
    const r = await window.api('admin', 'promotions', { filter });
    if (!r.ok) return note(r.message, 'bad');
    window._pro = r.requests;
    box.innerHTML = window.renderPromoCards(r.requests);
    const q = new URLSearchParams();
    Object.entries(filter).forEach(([k, v]) => { if (String(v || '').trim()) q.set(k, String(v).trim()); });
    const tail = q.toString() ? '&' + q.toString() : '';
    const print = document.getElementById('prPrintLink');
    const excel = document.getElementById('prExcelLink');
    if (print) print.href = `request_report.php?type=promotion&mode=print${tail}`;
    if (excel) excel.href = `request_report.php?type=promotion&mode=excel${tail}`;
  };
})();
