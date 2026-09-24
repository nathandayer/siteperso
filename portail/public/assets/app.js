(function () {
  'use strict';

  var slug = document.body.dataset.slug || '';
  var base = slug ? '/' + slug : '';

  /* ---------- toast ---------- */
  var toastEl = document.getElementById('toast');
  var toastTimer;
  function toast(msg) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 2000);
  }

  /* ---------- journal côté navigateur ---------- */
  function log(event, detail) {
    if (!slug) return;
    try {
      var fd = new FormData();
      fd.append('event', event);
      fd.append('detail', detail || '');
      if (navigator.sendBeacon) navigator.sendBeacon(base + '/log', fd);
      else fetch(base + '/log', { method: 'POST', body: fd, keepalive: true }).catch(function () {});
    } catch (e) {}
  }

  /* ---------- copier ---------- */
  function copyText(txt, msg) {
    var done = function () { toast(msg || 'Copié'); };
    var fallback = function () {
      var ta = document.createElement('textarea');
      ta.value = txt;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); done(); } catch (e) { toast('Sélectionnez le texte pour le copier'); }
      document.body.removeChild(ta);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(txt).then(done).catch(fallback);
    } else {
      fallback();
    }
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy], [data-copy-text]');
    if (!b) return;
    var txt = b.dataset.copyText !== undefined ? b.dataset.copyText : (document.getElementById(b.dataset.copy) || {}).textContent || '';
    copyText(txt, b.dataset.toast || 'Description copiée');
    if (b.dataset.file) log('copy', b.dataset.file);
    if (b.dataset.copy !== undefined) {
      var old = b.textContent;
      b.textContent = 'Copié';
      setTimeout(function () { b.textContent = old; }, 1500);
    }
  });

  /* ---------- partage vers Photos (mobile) ---------- */
  var canShareFiles = false;
  try {
    if (navigator.share && navigator.canShare) {
      canShareFiles = navigator.canShare({ files: [new File([''], 'test.mp4', { type: 'video/mp4' })] });
    }
  } catch (e) { canShareFiles = false; }

  if (canShareFiles) {
    document.querySelectorAll('.share-btn').forEach(function (b) { b.hidden = false; });
    document.querySelectorAll('.dl-btn').forEach(function (b) { b.hidden = true; });
  }

  // Deux temps, exprès : 1) préparer (téléchargement avec progression), 2) enregistrer.
  // Le menu de partage exige un geste immédiat, impossible après un long téléchargement.
  function prepare(btn) {
    var label = btn.querySelector('span') || btn;
    var original = label.textContent;
    btn.disabled = true;
    label.innerHTML = 'Préparation <span class="pct">0 %</span>';
    var pct = label.querySelector('.pct');

    return fetch(btn.dataset.url).then(function (res) {
      if (!res.ok) throw new Error('http ' + res.status);
      var total = parseInt(res.headers.get('Content-Length') || '0', 10);
      if (!res.body || !res.body.getReader) return res.blob();
      var reader = res.body.getReader();
      var chunks = [];
      var received = 0;
      return new Promise(function (resolve, reject) {
        function pump() {
          reader.read().then(function (r) {
            if (r.done) { resolve(new Blob(chunks, { type: btn.dataset.type })); return; }
            chunks.push(r.value);
            received += r.value.length;
            if (total && pct) pct.textContent = Math.min(99, Math.round(100 * received / total)) + ' %';
            pump();
          }).catch(reject);
        }
        pump();
      });
    }).then(function (blob) {
      btn._file = new File([blob], btn.dataset.name, { type: btn.dataset.type });
      btn.disabled = false;
      btn.classList.add('is-ready');
      label.textContent = original.indexOf('Photos') !== -1 ? 'Enregistrer dans Photos' : original;
      toast('Prête. Appuyez pour enregistrer.');
    }).catch(function () {
      btn.disabled = false;
      label.textContent = original;
      toast('Impossible pour le moment. Réessayez ou utilisez Télécharger.');
    });
  }

  function share(btn) {
    var file = btn._file;
    if (!file) { prepare(btn); return; }
    var data = { files: [file], title: btn.dataset.title || file.name };
    if (!navigator.canShare(data)) {
      window.location.href = btn.dataset.dl;
      return;
    }
    navigator.share(data).then(function () {
      btn.classList.remove('is-ready');
      log('share', btn.dataset.file || file.name);
    }).catch(function (err) {
      if (err && err.name === 'AbortError') return;      // l'utilisateur a fermé le menu
      window.location.href = btn.dataset.dl;
    });
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest('.share-btn');
    if (!b || b.disabled) return;
    e.preventDefault();
    share(b);
  });

  /* ---------- vignette plein écran ---------- */
  var lb = document.getElementById('lb');
  if (lb) {
    var lbImg = document.getElementById('lb-img');
    var lbTitle = document.getElementById('lb-title');
    var lbDl = document.getElementById('lb-dl');
    var lbShare = document.getElementById('lb-share');

    function openLb(b) {
      lbImg.src = b.dataset.thumb;
      lbTitle.innerHTML = 'Vignette' + (b.dataset.num ? ' ' + b.dataset.num : '') + '<small></small>';
      lbTitle.querySelector('small').textContent = b.dataset.title || '';
      lbDl.href = b.dataset.dl;
      lbDl.setAttribute('download', b.dataset.name);
      lbShare.dataset.url = b.dataset.thumb;
      lbShare.dataset.dl = b.dataset.dl;
      lbShare.dataset.name = b.dataset.name;
      lbShare.dataset.title = b.dataset.title || '';
      lbShare.dataset.file = b.dataset.file || '';
      lbShare.dataset.type = /\.png$/i.test(b.dataset.name) ? 'image/png' : (/\.webp$/i.test(b.dataset.name) ? 'image/webp' : 'image/jpeg');
      lbShare._file = null;
      lbShare.classList.remove('is-ready');
      (lbShare.querySelector('span') || lbShare).textContent = 'Enregistrer dans Photos';
      lb.hidden = false;
      document.body.style.overflow = 'hidden';
    }
    function closeLb() {
      lb.hidden = true;
      document.body.style.overflow = '';
    }
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-thumb]');
      if (b) { openLb(b); return; }
      if (e.target === lb || e.target.closest('#lb-close, #lb-close-2')) closeLb();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !lb.hidden) closeLb(); });
    lbDl.addEventListener('click', function () { log('thumb', lbShare.dataset.file); });
  }

  /* ---------- liens journalisés (calendrier, ics) ---------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-log]');
    if (a) log(a.dataset.log, a.getAttribute('href').split('/').pop());
  });

  /* ---------- abonnement calendrier selon l'appareil ---------- */
  var cal = document.getElementById('cal-link');
  if (cal) {
    var ua = navigator.userAgent;
    var apple = /iPhone|iPad|iPod|Macintosh/.test(ua);
    if (!apple) {
      // Android, Windows : Google Agenda accepte un abonnement par lien
      cal.href = 'https://calendar.google.com/calendar/r?cid=' + encodeURIComponent(cal.dataset.https);
      cal.target = '_blank';
      cal.rel = 'noopener';
    }
  }

  /* ---------- vidéo : une seule à la fois ---------- */
  document.addEventListener('play', function (e) {
    if (e.target.tagName !== 'VIDEO') return;
    document.querySelectorAll('video').forEach(function (v) { if (v !== e.target) v.pause(); });
  }, true);
})();
