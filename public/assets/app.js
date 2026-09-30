/* NIMCOS E-VOTING: progressive enhancement only.
 * Every voter page works without JavaScript; this file adds the step-by-step
 * ballot on phones, confirmation dialogs, live statistics and small charts.
 * Loaded as an external file so the Content-Security-Policy can forbid inline script.
 */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* ---------- Widths from data attributes (CSP forbids inline style) ---------- */
  function applyWidths(root) {
    $$('[data-width]', root).forEach(function (el) {
      var v = parseFloat(el.getAttribute('data-width')) || 0;
      el.style.width = Math.max(0, Math.min(100, v)) + '%';
    });
  }

  /* ---------- Dialogs ---------- */
  function initDialogs() {
    $$('[data-dialog-open]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var d = document.getElementById(btn.getAttribute('data-dialog-open'));
        if (d && typeof d.showModal === 'function') {
          d.showModal();
          var first = $('input:not([type=hidden]), select, textarea, button', d);
          if (first) first.focus();
        }
      });
    });
    $$('[data-dialog-close]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var d = btn.closest('dialog');
        if (d) d.close();
      });
    });
  }

  /* ---------- Forms: prevent double submission ---------- */
  function initSubmitOnce() {
    $$('form[data-submit-once]').forEach(function (form) {
      form.addEventListener('submit', function () {
        if (form.dataset.submitted === '1') { return; }
        form.dataset.submitted = '1';
        $$('button[type=submit], input[type=submit]', form).forEach(function (b) {
          b.setAttribute('aria-disabled', 'true');
          b.disabled = true;
          if (b.dataset.busyText) b.textContent = b.dataset.busyText;
        });
      });
    });
  }

  /* ---------- Final ballot submission: confirmation modal ---------- */
  function initFinalSubmit() {
    var form = $('form[data-final-ballot]');
    if (!form) return;
    var dialog = document.getElementById(form.getAttribute('data-final-ballot'));
    if (!dialog || typeof dialog.showModal !== 'function') return;
    var confirmed = false;
    form.addEventListener('submit', function (e) {
      if (confirmed) return;
      e.preventDefault();
      dialog.showModal();
      var cancel = $('[data-dialog-close]', dialog);
      if (cancel) cancel.focus();
    });
    var go = $('[data-confirm-submit]', dialog);
    if (go) {
      go.addEventListener('click', function () {
        confirmed = true;
        go.disabled = true;
        go.textContent = go.dataset.busyText || 'Submitting…';
        dialog.close();
        var primary = $('button[type=submit]', form);
        if (primary) { primary.disabled = true; primary.textContent = 'Submitting…'; }
        form.submit();
      });
    }
  }

  /* ---------- Ballot ---------- */
  function initBallot() {
    var form = $('form[data-ballot]');
    if (!form) return;
    var positions = $$('.position', form);
    var total = positions.length;
    var progressBar = $('[data-progress-bar]');
    var progressText = $$('[data-progress-text]');
    var stepText = $('[data-step-text]');
    var prev = $('[data-step-prev]');
    var next = $('[data-step-next]');
    var reviewBtn = $('[data-review]');
    var mq = window.matchMedia('(max-width: 899px)');
    var current = 0;

    // First position with an error, if any, is where the voter starts.
    positions.some(function (p, i) { if (p.classList.contains('has-error')) { current = i; return true; } return false; });

    function isDone(p) {
      return $$('input:checked', p).length > 0;
    }

    function enforceSeats(p) {
      var seats = parseInt(p.getAttribute('data-seats') || '1', 10);
      var boxes = $$('input[type=checkbox]', p);
      if (!boxes.length) return;
      var checked = boxes.filter(function (b) { return b.checked; }).length;
      boxes.forEach(function (b) { b.disabled = !b.checked && checked >= seats; });
    }

    function update() {
      var done = positions.filter(isDone).length;
      if (progressBar) progressBar.style.width = (total ? (done / total) * 100 : 0) + '%';
      progressText.forEach(function (el) { el.textContent = done + ' of ' + total + ' completed'; });
      positions.forEach(function (p) {
        var link = $('.ballot-index a[href="#' + p.id + '"]');
        if (link) link.parentElement.classList.toggle('done', isDone(p));
        enforceSeats(p);
      });
    }

    function stepperOn() { return mq.matches; }

    function show(i) {
      current = Math.max(0, Math.min(total - 1, i));
      positions.forEach(function (p, idx) { p.classList.toggle('is-current', idx === current); });
      if (stepText) stepText.textContent = 'Position ' + (current + 1) + ' of ' + total;
      if (prev) prev.disabled = current === 0;
      if (next) next.classList.toggle('hidden', current === total - 1);
      if (reviewBtn) reviewBtn.classList.toggle('hidden', current !== total - 1);
    }

    function applyMode() {
      if (stepperOn()) {
        form.classList.add('js-stepper');
        if (prev) prev.classList.remove('hidden');
        show(current);
      } else {
        form.classList.remove('js-stepper');
        positions.forEach(function (p) { p.classList.remove('is-current'); });
        if (prev) prev.classList.add('hidden');
        if (next) next.classList.add('hidden');
        if (reviewBtn) reviewBtn.classList.remove('hidden');
        if (stepText) stepText.textContent = total + ' positions';
      }
    }

    if (prev) prev.addEventListener('click', function () { show(current - 1); window.scrollTo({ top: 0 }); });
    if (next) next.addEventListener('click', function () { show(current + 1); window.scrollTo({ top: 0 }); });

    // Clear a selection (voters may change their mind before review).
    $$('[data-clear]', form).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var p = btn.closest('.position');
        $$('input', p).forEach(function (i) { i.checked = false; });
        update();
      });
    });

    form.addEventListener('change', update);
    if (mq.addEventListener) mq.addEventListener('change', applyMode); else if (mq.addListener) mq.addListener(applyMode);

    // Warn before accidentally leaving a ballot with unsaved selections.
    var leaving = false;
    form.addEventListener('submit', function () { leaving = true; });
    window.addEventListener('beforeunload', function (e) {
      if (!leaving && $$('input:checked', form).length) { e.preventDefault(); e.returnValue = ''; }
    });

    update();
    applyMode();
  }

  /* ---------- Session countdown ---------- */
  function initCountdown() {
    $$('[data-expires-at]').forEach(function (el) {
      var end = Date.parse(el.getAttribute('data-expires-at'));
      if (isNaN(end)) return;
      var serverNow = Date.parse(el.getAttribute('data-server-now') || '') || Date.now();
      var skew = Date.now() - serverNow;
      var out = $('[data-countdown]', el) || el;
      function tick() {
        var left = Math.floor((end - (Date.now() - skew)) / 1000);
        if (left <= 0) { out.textContent = '0:00'; el.classList.add('expired'); return; }
        var m = Math.floor(left / 60), s = left % 60;
        out.textContent = m + ':' + (s < 10 ? '0' : '') + s;
        el.classList.toggle('warning', left < 120);
        setTimeout(tick, 1000);
      }
      tick();
    });
  }

  /* ---------- OTP field: digits only ---------- */
  function initOtp() {
    var el = $('input[data-otp]');
    if (!el) return;
    el.addEventListener('input', function () {
      var v = el.value.replace(/\D+/g, '').slice(0, parseInt(el.getAttribute('maxlength') || '6', 10));
      if (v !== el.value) el.value = v;
    });
  }

  /* ---------- Bar chart (SVG) ---------- */
  function drawBarChart(el, series) {
    var svgNS = 'http://www.w3.org/2000/svg';
    el.innerHTML = '';
    if (!series || !series.length) {
      var empty = document.createElement('div');
      empty.className = 'chart-empty';
      empty.textContent = el.getAttribute('data-empty') || 'No activity yet.';
      el.appendChild(empty);
      return;
    }
    var W = 640, H = 220, padL = 36, padB = 26, padT = 12, padR = 8;
    var max = Math.max.apply(null, series.map(function (d) { return d.value; })) || 1;
    var niceMax = Math.ceil(max / 5) * 5 || 5;
    var svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', el.getAttribute('data-label') || 'Bar chart');
    var plotW = W - padL - padR, plotH = H - padT - padB;
    for (var g = 0; g <= 4; g++) {
      var y = padT + plotH - (plotH * g / 4);
      var line = document.createElementNS(svgNS, 'line');
      line.setAttribute('x1', padL); line.setAttribute('x2', W - padR);
      line.setAttribute('y1', y); line.setAttribute('y2', y);
      line.setAttribute('class', 'axis'); line.setAttribute('stroke-width', g === 0 ? 1 : 0.5);
      svg.appendChild(line);
      var t = document.createElementNS(svgNS, 'text');
      t.setAttribute('x', padL - 6); t.setAttribute('y', y + 4); t.setAttribute('text-anchor', 'end');
      t.textContent = Math.round(niceMax * g / 4);
      svg.appendChild(t);
    }
    var slot = plotW / series.length, bw = Math.min(42, slot * 0.7);
    series.forEach(function (d, i) {
      var h = plotH * (d.value / niceMax);
      var x = padL + slot * i + (slot - bw) / 2;
      var r = document.createElementNS(svgNS, 'rect');
      r.setAttribute('x', x); r.setAttribute('y', padT + plotH - h);
      r.setAttribute('width', bw); r.setAttribute('height', Math.max(h, 0));
      r.setAttribute('class', 'bar'); r.setAttribute('rx', 2);
      var title = document.createElementNS(svgNS, 'title');
      title.textContent = d.label + ': ' + d.value;
      r.appendChild(title);
      svg.appendChild(r);
      if (series.length <= 16 || i % 2 === 0) {
        var lbl = document.createElementNS(svgNS, 'text');
        lbl.setAttribute('x', x + bw / 2); lbl.setAttribute('y', H - 8); lbl.setAttribute('text-anchor', 'middle');
        lbl.textContent = d.label;
        svg.appendChild(lbl);
      }
    });
    el.appendChild(svg);
  }

  function initCharts() {
    $$('[data-bar-chart]').forEach(function (el) {
      try { drawBarChart(el, JSON.parse(el.getAttribute('data-bar-chart') || '[]')); } catch (e) { /* ignore */ }
    });
  }

  /* ---------- Live statistics polling ---------- */
  function get(obj, path) {
    return path.split('.').reduce(function (o, k) { return o == null ? undefined : o[k]; }, obj);
  }
  function fmt(v, kind) {
    if (v == null) return '-';
    if (kind === 'percent') return Number(v).toFixed(2) + '%';
    if (typeof v === 'number') return v.toLocaleString('en-NG');
    return String(v);
  }
  /* Live vote count on the dashboard: update figures, highlight leaders, keep rows ordered by votes. */
  function updateTally(tally) {
    if (!tally || !tally.candidates) return;
    Object.keys(tally.totals || {}).forEach(function (id) {
      var el = $('[data-tally-total="' + id + '"]');
      if (el) el.textContent = fmt(tally.totals[id]);
    });
    $$('[data-tally-row]').forEach(function (row) {
      var c = tally.candidates[row.getAttribute('data-tally-row')];
      if (!c) return;
      var votesEl = $('[data-tally-votes]', row);
      var changed = votesEl && votesEl.textContent !== fmt(c.votes);
      if (votesEl) votesEl.textContent = fmt(c.votes);
      var pct = $('[data-tally-pct]', row);
      if (pct) pct.textContent = Number(c.percentage).toFixed(1) + '%';
      var bar = $('[data-tally-bar]', row);
      if (bar) bar.setAttribute('data-width', c.percentage);
      var lead = $('[data-tally-lead]', row);
      if (lead) lead.hidden = !c.leading;
      row.classList.toggle('is-leading', !!c.leading);
      row.setAttribute('data-votes', c.votes);
      if (changed) {
        row.classList.add('bump');
        setTimeout(function () { row.classList.remove('bump'); }, 2500);
      }
    });
    $$('[data-tally-list]').forEach(function (list) {
      var rows = $$('[data-tally-row]', list);
      rows.sort(function (a, b) {
        return (parseInt(b.getAttribute('data-votes'), 10) || 0) - (parseInt(a.getAttribute('data-votes'), 10) || 0)
          || (parseInt(a.getAttribute('data-order'), 10) - parseInt(b.getAttribute('data-order'), 10));
      });
      rows.forEach(function (row) { list.appendChild(row); });
    });
  }

  function initPolling() {
    var root = $('[data-poll-url]');
    if (!root) return;
    var url = root.getAttribute('data-poll-url');
    var every = parseInt(root.getAttribute('data-poll-seconds') || '15', 10) * 1000;
    var stamp = $('[data-poll-stamp]');
    function run() {
      if (document.hidden) { setTimeout(run, every); return; }
      fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { if (r.status === 401) { window.location.reload(); } return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data) return;
          $$('[data-stat]').forEach(function (el) { el.textContent = fmt(get(data, el.getAttribute('data-stat')), el.getAttribute('data-format')); });
          $$('[data-stat-width]').forEach(function (el) { el.setAttribute('data-width', get(data, el.getAttribute('data-stat-width')) || 0); });
          updateTally(data.tally);
          applyWidths();
          $$('[data-bar-chart][data-chart-source]').forEach(function (el) { drawBarChart(el, get(data, el.getAttribute('data-chart-source')) || []); });
          if (stamp) stamp.textContent = new Date().toLocaleTimeString('en-GB');
        })
        .catch(function () { /* transient network issue: try again next cycle */ })
        .then(function () { setTimeout(run, every); });
    }
    setTimeout(run, every);
  }

  /* ---------- Home page menu (phones) ---------- */
  function initSiteNav() {
    var btn = $('[data-nav-toggle]');
    var panel = $('[data-nav-panel]');
    if (!btn || !panel) return;
    btn.addEventListener('click', function () {
      var open = panel.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    $$('a', panel).forEach(function (a) {
      a.addEventListener('click', function () { panel.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); });
    });
  }

  /* ---------- Announcement ticker: steady reading speed, with a pause button (WCAG 2.2.2) ---------- */
  function initTicker() {
    var ticker = $('[data-ticker]');
    if (!ticker) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var track = $('[data-ticker-track]', ticker);
    var toggle = $('[data-ticker-toggle]', ticker);
    ticker.classList.add('is-moving');
    // One copy of the text scrolls past at about 70 pixels per second.
    var distance = track.scrollWidth / 2;
    track.style.setProperty('--ticker-duration', Math.max(15, Math.round(distance / 70)) + 's');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var paused = ticker.classList.toggle('is-paused');
        toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
        toggle.textContent = paused ? 'Play' : 'Pause';
      });
    }
  }

  /* ---------- Announcement pop-up: shown once per announcement version per browser ---------- */
  function initAnnouncementPopup() {
    var dialog = $('[data-announcement-popup]');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    var keys = (dialog.getAttribute('data-announcement-popup') || '').split(',').filter(Boolean);
    var storeKey = 'nimcos.announcements.seen';
    var seen = [];
    try { seen = JSON.parse(window.localStorage.getItem(storeKey) || '[]'); } catch (e) { seen = []; }
    var unseen = keys.filter(function (k) { return seen.indexOf(k) === -1; });
    if (!unseen.length) return;
    dialog.addEventListener('close', function () {
      try { window.localStorage.setItem(storeKey, JSON.stringify(seen.concat(unseen).slice(-50))); } catch (e) { /* private mode: it will show again next time */ }
    });
    setTimeout(function () { dialog.showModal(); }, 400);
  }

  /* ---------- Admin sidebar ---------- */
  function initSidebar() {
    var toggle = $('[data-menu-toggle]');
    var sidebar = $('.sidebar');
    if (!toggle || !sidebar) return;
    var scrim = document.createElement('div');
    scrim.className = 'scrim hidden';
    document.body.appendChild(scrim);
    function set(open) {
      sidebar.classList.toggle('open', open);
      scrim.classList.toggle('hidden', !open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    toggle.addEventListener('click', function () { set(!sidebar.classList.contains('open')); });
    scrim.addEventListener('click', function () { set(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
  }

  /* ---------- Tie resolution: winner field only when a winner is declared ---------- */
  function initTieForms() {
    $$('form[data-tie-form]').forEach(function (form) {
      var method = $('select[name=method]', form);
      var winner = $('[data-winner-field]', form);
      if (!method || !winner) return;
      function sync() { winner.classList.toggle('hidden', method.value === 'RUNOFF_PENDING'); }
      method.addEventListener('change', sync);
      sync();
    });
  }

  /* ---------- Select all (bulk actions) ---------- */
  function initSelectAll() {
    $$('[data-select-all]').forEach(function (master) {
      master.addEventListener('change', function () {
        $$('input[name="' + master.getAttribute('data-select-all') + '"]').forEach(function (cb) { cb.checked = master.checked; });
      });
    });
  }

  /* ---------- Auto-submit filters ---------- */
  function initAutoSubmit() {
    $$('select[data-autosubmit]').forEach(function (s) {
      s.addEventListener('change', function () { if (s.form) s.form.submit(); });
    });
  }

  /* ---------- Print ---------- */
  function initPrint() {
    $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
  }

  /* ---------- Candidate form: auto-fill from voter register ---------- */
  function initCandidateVoterLookup() {
    var input = $('input[data-voter-lookup]');
    if (!input) return;

    var url = input.getAttribute('data-voter-lookup');
    var btn = $('[data-voter-lookup-btn]');
    var status = $('[data-voter-lookup-status]');
    var form = input.form;
    if (!url || !form) return;

    var surname = $('input[name=surname]', form);
    var firstName = $('input[name=first_name]', form);
    var otherNames = $('input[name=other_names]', form);
    var rank = $('select[name=rank]', form);
    var command = $('select[name=command]', form);

    var lastFetched = '';
    var debounceTimer = null;

    function setStatus(text, type) {
      if (!status) return;
      status.textContent = text;
      status.className = 'sn-lookup-msg ' + (type || 'info');
    }

    function doFetch() {
      var sn = (input.value || '').trim();
      if (!sn) {
        setStatus('Enter Service Number to auto-fill name, rank and command from the voter register.', 'info');
        return;
      }
      if (sn === lastFetched) return;
      if (sn.length < 3) {
        setStatus('Enter at least 3 digits to fetch details.', 'info');
        return;
      }

      setStatus('Fetching voter details…', 'info');
      if (btn) btn.disabled = true;

      fetch(url + '?service_number=' + encodeURIComponent(sn), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (btn) btn.disabled = false;
        if (!data || !data.found || !data.voter) {
          setStatus(data && data.message ? data.message : 'No voter found with this Service Number on the register.', 'error');
          return;
        }

        var v = data.voter;
        lastFetched = sn;

        if (surname && v.surname) surname.value = v.surname;
        if (firstName && v.first_name) firstName.value = v.first_name;
        if (otherNames) otherNames.value = v.other_names || '';

        if (rank && v.rank) {
          rank.value = v.rank;
        }
        if (command && v.command) {
          command.value = v.command;
        }

        var nameStr = (v.first_name || '') + ' ' + (v.surname || '');
        var rankStr = v.rank_label ? ' (' + v.rank_label + ')' : '';
        setStatus('✓ Loaded details for ' + nameStr.trim() + rankStr + ' from register.', 'success');
      })
      .catch(function () {
        if (btn) btn.disabled = false;
        setStatus('Unable to look up voter details. You can enter details manually.', 'error');
      });
    }

    input.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      var sn = (input.value || '').trim();
      if (sn.length >= 4) {
        debounceTimer = setTimeout(doFetch, 400);
      }
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(debounceTimer);
        doFetch();
      }
    });

    input.addEventListener('blur', function () {
      clearTimeout(debounceTimer);
      if (input.value.trim().length >= 3 && input.value.trim() !== lastFetched) {
        doFetch();
      }
    });

    if (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        clearTimeout(debounceTimer);
        doFetch();
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    applyWidths();
    initDialogs();
    initSubmitOnce();
    initFinalSubmit();
    initBallot();
    initCountdown();
    initOtp();
    initCharts();
    initPolling();
    initSidebar();
    initTieForms();
    initSelectAll();
    initAutoSubmit();
    initPrint();
    initSiteNav();
    initTicker();
    initAnnouncementPopup();
    initCandidateVoterLookup();
  });
})();
