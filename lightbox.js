/* PintaReformas · visor de fotos a pantalla grande. Sin dependencias. */
(function () {
  var SEL = 'figure img, .port-item img';
  var css = '' +
    'figure img,.port-item img{cursor:zoom-in}' +
    '.lb{position:fixed;inset:0;z-index:100000;background:rgba(8,14,22,.95);display:none;align-items:center;justify-content:center;flex-direction:column;padding:46px 12px 56px;touch-action:pan-y}' +
    '.lb.on{display:flex}' +
    '.lb-img{max-width:min(96vw,1100px);max-height:calc(100vh - 104px);max-height:calc(100dvh - 104px);object-fit:contain;border-radius:8px;box-shadow:0 12px 50px rgba(0,0,0,.6);user-select:none;-webkit-user-drag:none}' +
    '.lb-cap{position:absolute;left:0;right:0;bottom:16px;text-align:center;color:#e6ebf0;font:500 .95rem/1.4 "Segoe UI",Helvetica,Arial,sans-serif;padding:0 70px}' +
    '.lb-n{position:absolute;top:18px;left:18px;color:#c7d0da;font:600 .9rem "Segoe UI",Helvetica,Arial,sans-serif}' +
    '.lb-b{position:absolute;border:0;background:rgba(255,255,255,.12);color:#fff;width:46px;height:46px;border-radius:50%;font-size:26px;line-height:46px;cursor:pointer;padding:0}' +
    '.lb-b:hover,.lb-b:focus-visible{background:#FF9800;color:#0D1B2A}' +
    '.lb-x{top:10px;right:12px}.lb-p{left:10px;top:50%;transform:translateY(-50%)}.lb-nx{right:10px;top:50%;transform:translateY(-50%)}' +
    '@media(max-width:600px){.lb-p,.lb-nx{top:auto;bottom:62px;transform:none}.lb-p{left:calc(50% - 56px)}.lb-nx{right:calc(50% - 56px)}.lb-cap{bottom:12px;padding:0 12px;font-size:.85rem}.lb{padding-bottom:128px}.lb-img{max-height:calc(100dvh - 210px)}}';
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  var groups = [], cur = null, idx = 0, lastFocus = null, startX = null;

  function build() {
    var all = [].slice.call(document.querySelectorAll(SEL)).filter(function (i) {
      return !i.closest('nav,footer,header') && (i.getAttribute('width') ? +i.getAttribute('width') > 150 : true);
    });
    var seen = [], rest = [];
    all.forEach(function (img) {
      var g = img.closest('.gal');
      if (g) { var k = seen.indexOf(g); if (k < 0) { seen.push(g); groups.push([]); k = seen.length - 1; } groups[k].push(img); }
      else rest.push(img);
    });
    if (rest.length) groups.push(rest);
    groups.forEach(function (list) {
      list.forEach(function (img, n) {
        img.setAttribute('tabindex', '0'); img.setAttribute('role', 'button');
        img.setAttribute('aria-label', 'Ampliar foto: ' + (img.alt || ''));
        img.addEventListener('click', function (e) { e.preventDefault(); open(list, n, img); });
        img.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(list, n, img); } });
      });
    });
  }

  var box = document.createElement('div');
  box.className = 'lb'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-label', 'Foto ampliada');
  box.innerHTML = '<div class="lb-n"></div><button type="button" class="lb-b lb-x" aria-label="Cerrar">&times;</button>' +
    '<button type="button" class="lb-b lb-p" aria-label="Foto anterior">&#8249;</button><img class="lb-img" alt="">' +
    '<button type="button" class="lb-b lb-nx" aria-label="Foto siguiente">&#8250;</button><div class="lb-cap"></div>';
  var im = box.querySelector('.lb-img'), cap = box.querySelector('.lb-cap'), num = box.querySelector('.lb-n');

  function show(n) {
    idx = (n + cur.length) % cur.length;
    var src = cur[idx], fig = src.closest('figure'), fc = fig && fig.querySelector('figcaption');
    im.src = src.currentSrc || src.src; im.alt = src.alt || '';
    cap.textContent = (fc && fc.textContent.trim()) || src.alt || '';
    num.textContent = cur.length > 1 ? (idx + 1) + ' / ' + cur.length : '';
    box.querySelector('.lb-p').style.display = box.querySelector('.lb-nx').style.display = cur.length > 1 ? '' : 'none';
    [idx + 1, idx - 1].forEach(function (k) { var o = cur[(k + cur.length) % cur.length]; if (o) { var p = new Image(); p.src = o.currentSrc || o.src; } });
  }
  function open(list, n, from) {
    cur = list; lastFocus = from; show(n);
    box.classList.add('on'); document.documentElement.style.overflow = 'hidden';
    box.querySelector('.lb-x').focus();
  }
  function close() {
    box.classList.remove('on'); document.documentElement.style.overflow = '';
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  box.addEventListener('click', function (e) {
    if (e.target === box || e.target.classList.contains('lb-cap')) close();
    else if (e.target.closest('.lb-x')) close();
    else if (e.target.closest('.lb-p')) show(idx - 1);
    else if (e.target.closest('.lb-nx')) show(idx + 1);
  });
  document.addEventListener('keydown', function (e) {
    if (!box.classList.contains('on')) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowLeft') show(idx - 1);
    else if (e.key === 'ArrowRight') show(idx + 1);
    else if (e.key === 'Tab') { var b = [].slice.call(box.querySelectorAll('button')).filter(function (x) { return x.style.display !== 'none'; }); var f = b.indexOf(document.activeElement); if (e.shiftKey && f <= 0) { e.preventDefault(); b[b.length - 1].focus(); } else if (!e.shiftKey && f === b.length - 1) { e.preventDefault(); b[0].focus(); } }
  });
  box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
  box.addEventListener('touchend', function (e) {
    if (startX === null) return; var dx = e.changedTouches[0].clientX - startX; startX = null;
    if (Math.abs(dx) > 50) show(idx + (dx < 0 ? 1 : -1));
  }, { passive: true });

  function init() { document.body.appendChild(box); build(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
