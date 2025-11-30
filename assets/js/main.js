document.addEventListener('DOMContentLoaded', function(){
  // Simplified, robust main script: contact reveal, search suggestions, image-reorder init
  try {
    const reveal = document.getElementById('reveal');
    const contact = document.getElementById('contact');
    const overlay = document.getElementById('contactOverlay');
    if (reveal && contact && overlay) {
      let revealed = false;
      const radius = 80;
      function setSpot(x,y){ overlay.style.background = `radial-gradient(circle ${radius}px at ${x}px ${y}px, rgba(255,255,255,0) 0px, rgba(255,255,255,0) ${radius}px, rgba(255,255,255,0.6) ${radius+1}px)`; }
      contact.setAttribute('tabindex','0');
      contact.addEventListener('mousemove', function(e){ if (revealed) return; const r = contact.getBoundingClientRect(); setSpot(e.clientX - r.left, e.clientY - r.top); overlay.style.opacity = '1'; });
      contact.addEventListener('mouseleave', function(){ if (revealed) return; overlay.style.background = 'rgba(255,255,255,0.6)'; });
      contact.addEventListener('focus', function(){ if (revealed) return; contact.classList.add('revealed-temp'); });
      contact.addEventListener('blur', function(){ if (revealed) return; contact.classList.remove('revealed-temp'); overlay.style.background = 'rgba(255,255,255,0.6)'; });
      reveal.addEventListener('click', function(){ revealed = !revealed; if (revealed){ contact.classList.add('revealed'); reveal.setAttribute('aria-pressed','true'); reveal.textContent = 'Hide contact'; } else { contact.classList.remove('revealed'); reveal.setAttribute('aria-pressed','false'); reveal.textContent = 'Reveal contact (hover reveals too)'; } });
    }
  } catch (e) { console.warn('contact reveal init failed', e); }

  try {
    const searchInput = document.getElementById('search-input');
    const suggestions = document.getElementById('search-suggestions');
    let timer = null;
    if (searchInput && suggestions) {
      function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]; }); }
      searchInput.addEventListener('keydown', function(e){ if (e.key === 'Enter'){ const q=this.value.trim(); window.location.href = q ? ('/products?q='+encodeURIComponent(q)) : '/products'; e.preventDefault(); } });
      searchInput.addEventListener('input', function(){ clearTimeout(timer); const q=this.value.trim(); if (!q){ suggestions.innerHTML = ''; return; } timer = setTimeout(function(){ fetch('/api/search.php?q='+encodeURIComponent(q)).then(r=>r.json()).then(data=>{
        const maxShow = 5; const shown = (Array.isArray(data)?data:[]).slice(0, maxShow);
        let html = shown.map(d=>{
          const img = d.image ? ('<img src="'+escapeHtml(d.image)+'" style="width:100%;height:100%;object-fit:cover;border-radius:4px" alt="'+escapeHtml(d.name)+'">') : '';
          return '<div class="sugg" data-id="'+escapeHtml(d.id)+'" style="display:flex;gap:12px;align-items:center;padding:8px;border-bottom:1px solid #eee;cursor:pointer"><div style="width:84px;height:84px;flex:0 0 84px;background:#f6f6f6;border:1px solid #ddd;border-radius:6px;overflow:hidden">'+img+'</div><div style="flex:1;min-width:0"><div style="font-weight:600;margin-bottom:4px">'+escapeHtml(d.name)+'</div><div style="color:#666;font-size:0.95em">'+escapeHtml(d.series||'')+'</div><div style="color:#222;margin-top:6px;font-weight:600">'+escapeHtml(d.minPrice||'')+'</div></div></div>';
        }).join('');
        if (Array.isArray(data) && data.length>maxShow) html += '<div class="sugg sugg-find-more"><a href="/products?q='+encodeURIComponent(q)+'">Find more results</a></div>';
        suggestions.innerHTML = html;
      }).catch(()=>{ suggestions.innerHTML = ''; }); }, 200); });
      suggestions.addEventListener('click', function(e){ const t = e.target.closest('.sugg'); if (!t) return; const id = t.getAttribute('data-id'); if (id) location.href = '/item/' + id; });
    }
  } catch (e) { console.warn('search init failed', e); }

  // Safe init for the image reorder helper (non-fatal)
  try { if (typeof initImageReorder === 'function') initImageReorder('#imagePreview', '#imagesInput'); } catch(e){ /* ignore */ }

  // Thumb-strip navigation and thumbnail click handling
  try {
    const thumbsContainer = document.getElementById('thumbsContainer');
    const prevBtn = document.getElementById('thumbPrev');
    const nextBtn = document.getElementById('thumbNext');
    const mainImg = document.getElementById('mainItemImg');
    function scrollThumbs(amount){ if (!thumbsContainer) return; thumbsContainer.scrollBy({ left: amount, behavior: 'smooth' }); }
    function getActiveIndexFromThumbs(){
      const all = Array.from(document.querySelectorAll('.thumb-strip .thumb'));
      return all.findIndex(img => img.classList.contains('active'));
    }

    function scrollActiveIntoView(index){
      if (!thumbsContainer) return;
      const thumbNodes = Array.from(thumbsContainer.querySelectorAll('.thumb-item'));
      if (!thumbNodes.length) return;
      const target = thumbNodes[index];
      if (!target) return;
      const thumbW = target.offsetWidth;
      const containerWidth = thumbsContainer.clientWidth;

      // Preferred center: midpoint between prevBtn.right and nextBtn.left, but constrained to the visible scroller rect.
      let centerOffset = containerWidth / 2; // default: center of container
      try {
        const cRect = thumbsContainer.getBoundingClientRect();
        // compute how much of the scroller is covered by the arrow buttons
        let leftOverlap = 0, rightOverlap = 0;
        if (prevBtn) {
          const pRect = prevBtn.getBoundingClientRect();
          leftOverlap = Math.max(0, Math.min(pRect.right, cRect.right) - Math.max(pRect.left, cRect.left));
        }
        if (nextBtn) {
          const nRect = nextBtn.getBoundingClientRect();
          rightOverlap = Math.max(0, Math.min(nRect.right, cRect.right) - Math.max(nRect.left, cRect.left));
        }

        const effectiveVisible = Math.max(0, containerWidth - leftOverlap - rightOverlap);
        // center offset is left overlap + half of effective visible area (so it's visually between arrows)
        centerOffset = leftOverlap + (effectiveVisible / 2);
        // safety margin so the thumb center doesn't get too close to arrow edges
        const safety = Math.min(12, Math.floor(thumbW / 3));
        centerOffset = Math.max(centerOffset, leftOverlap + safety);
        centerOffset = Math.min(centerOffset, containerWidth - rightOverlap - safety);
      } catch (ex) {
        // measurement failed -> keep default centerOffset
      }

      // compute scrollLeft so target's center aligns with centerOffset
      // Use bounding rects (more stable across transforms) and include rounding so result is pixel-perfect.
      try {
        const max = Math.max(0, thumbsContainer.scrollWidth - containerWidth);
        const cRect = thumbsContainer.getBoundingClientRect();
        const tRect = target.getBoundingClientRect();
        // center of target relative to container left (pixels)
        const targetCenterInContainer = (tRect.left - cRect.left) + (thumbW / 2);
        const currentScroll = thumbsContainer.scrollLeft || 0;
        const desired = currentScroll + targetCenterInContainer - centerOffset;
        const left = Math.round(Math.max(0, Math.min(max, desired)));
        try { thumbsContainer.scrollTo({ left: left, behavior: 'smooth' }); } catch(e){ thumbsContainer.scrollLeft = left; }
      } catch (ex) {
        // fallback to previous offsetLeft method if getBoundingClientRect fails
        const targetCenter = target.offsetLeft + (thumbW / 2);
        const desired = targetCenter - centerOffset;
        const max = Math.max(0, thumbsContainer.scrollWidth - containerWidth);
        const left = Math.round(Math.max(0, Math.min(max, desired)));
        try { thumbsContainer.scrollTo({ left: left, behavior: 'smooth' }); } catch(e){ thumbsContainer.scrollLeft = left; }
      }
    }

    if (prevBtn && thumbsContainer) prevBtn.addEventListener('click', function(){
      const productCard = document.querySelector('.product-card');
      const anchorRect = productCard ? productCard.getBoundingClientRect() : null;
      const allThumbs = Array.from(document.querySelectorAll('.thumb-strip .thumb'));
      if (!allThumbs.length) return;
      let cur = getActiveIndexFromThumbs();
      if (cur < 0) cur = 0;
      // circular previous
      const newIdx = (cur - 1 + allThumbs.length) % allThumbs.length;
      setActiveThumb(newIdx);
      scrollActiveIntoView(newIdx);
      // restore vertical anchor if layout shifted
      requestAnimationFrame(function(){ if (!anchorRect) return; const nr = productCard.getBoundingClientRect(); const dy = nr.top - anchorRect.top; if (Math.abs(dy) > 1) window.scrollBy({ top: dy, left: 0, behavior: 'auto' }); });
    });

    if (nextBtn && thumbsContainer) nextBtn.addEventListener('click', function(){
      const productCard = document.querySelector('.product-card');
      const anchorRect = productCard ? productCard.getBoundingClientRect() : null;
      const allThumbs = Array.from(document.querySelectorAll('.thumb-strip .thumb'));
      if (!allThumbs.length) return;
      let cur = getActiveIndexFromThumbs();
      if (cur < 0) cur = 0;
      // circular next
      const newIdx = (cur + 1) % allThumbs.length;
      setActiveThumb(newIdx);
      scrollActiveIntoView(newIdx);
      requestAnimationFrame(function(){ if (!anchorRect) return; const nr = productCard.getBoundingClientRect(); const dy = nr.top - anchorRect.top; if (Math.abs(dy) > 1) window.scrollBy({ top: dy, left: 0, behavior: 'auto' }); });
    });

    // Clicking a thumbnail updates the main image and active state
    function setActiveThumb(index){
      const all = Array.from(document.querySelectorAll('.thumb-strip .thumb'));
      const t = all.find(img => String(img.dataset.index) === String(index));
      if (t && mainImg) mainImg.src = t.src;
      all.forEach(img => img.classList.toggle('active', String(img.dataset.index) === String(index)));
      // viewer thumbs too
      const vAll = Array.from(document.querySelectorAll('.viewer-thumbs img'));
      vAll.forEach(img => img.classList.toggle('active', String(img.dataset.index) === String(index)));
    }
    document.querySelectorAll('.thumb-strip .thumb').forEach(img => img.addEventListener('click', function(){ setActiveThumb(this.dataset.index); }));

    // Viewer slider nav (modal viewer)
    const viewerPrev = document.getElementById('viewerPrev');
    const viewerNext = document.getElementById('viewerNext');
    const viewerMain = document.getElementById('viewerMainImg');
    const viewerThumbs = document.getElementById('viewerThumbsContainer');
    function viewerSetIndex(idx){
      const imgs = Array.from(document.querySelectorAll('.viewer-thumbs img'));
      if (!imgs.length) return;
      const clamped = Math.max(0, Math.min(imgs.length - 1, idx));
      const node = imgs[clamped];
      if (node && viewerMain) viewerMain.src = node.src;
      imgs.forEach(img => img.classList.toggle('active', img === node));
    }
    if (viewerPrev && viewerThumbs) viewerPrev.addEventListener('click', function(){ const imgs = Array.from(document.querySelectorAll('.viewer-thumbs img')); const cur = imgs.findIndex(i=>i.classList.contains('active')); viewerSetIndex(cur > 0 ? cur - 1 : 0); viewerThumbs.scrollBy({ left: -160, behavior: 'smooth' }); });
    if (viewerNext && viewerThumbs) viewerNext.addEventListener('click', function(){ const imgs = Array.from(document.querySelectorAll('.viewer-thumbs img')); const cur = imgs.findIndex(i=>i.classList.contains('active')); viewerSetIndex(cur < imgs.length-1 ? cur + 1 : imgs.length-1); viewerThumbs.scrollBy({ left: 160, behavior: 'smooth' }); });
    document.querySelectorAll('.viewer-thumbs img').forEach(img => img.addEventListener('click', function(){ viewerSetIndex(this.dataset.index); }));
  } catch(e) { /* non-fatal */ }

});

// DevTools helper: track scroll distance of the thumbnail strip.
// Usage (in browser console):
//   const tracker = startThumbScrollTracker();
//   // ... interact with the page, arrows, programmatic scrolls ...
//   tracker.stop(); // or call stopThumbScrollTracker();
// Dev helper: debug and visually outline thumb-strip and related elements.
// Usage in console: `const dbg = debugThumbStrip();` then `dbg.clear();`
window.debugThumbStrip = function() {
  const thumbStrip = document.querySelector('.thumb-strip');
  const thumbsContainer = document.getElementById('thumbsContainer');
  const prevBtn = document.getElementById('thumbPrev');
  const nextBtn = document.getElementById('thumbNext');
  const mainContainer = document.querySelector('.main-img') || document.querySelector('.main-img img') || document.getElementById('mainItemImg');
  const result = {};
  try {
    result.mainRect = mainContainer ? mainContainer.getBoundingClientRect() : null;
    result.stripRect = thumbStrip ? thumbStrip.getBoundingClientRect() : null;
    result.thumbsRect = thumbsContainer ? thumbsContainer.getBoundingClientRect() : null;
    result.prevRect = prevBtn ? prevBtn.getBoundingClientRect() : null;
    result.nextRect = nextBtn ? nextBtn.getBoundingClientRect() : null;
    result.stripInline = thumbStrip ? thumbStrip.style.cssText : '';
    result.stripComputed = thumbStrip ? getComputedStyle(thumbStrip) : null;
    result.thumbVars = thumbsContainer ? getComputedStyle(thumbsContainer).getPropertyValue('--thumb-size') + ' x ' + getComputedStyle(thumbsContainer).getPropertyValue('--thumb-count') : null;
  } catch (e) {
    console.warn('debugThumbStrip: measurement failed', e);
  }

  // apply visual outlines to help debugging
  const applied = [];
  function applyOutline(el, css) { if (!el) return; el.__oldOutline = el.style.outline || ''; el.style.outline = css; applied.push(el); }
  applyOutline(thumbStrip, '2px dashed red');
  applyOutline(thumbsContainer, '2px dashed orange');
  applyOutline(prevBtn, '2px dashed blue');
  applyOutline(nextBtn, '2px dashed blue');
  applyOutline(mainContainer, '2px dashed green');

  console.log('debugThumbStrip', result);

  return {
    info: result,
    clear() {
      applied.forEach(el => { if (!el) return; el.style.outline = el.__oldOutline || ''; delete el.__oldOutline; });
      console.log('debugThumbStrip: outlines cleared');
    }
  };
};

// Dev helper: log internal centering calculation for a thumb index
window.logThumbCenterCalc = function(index){
  const container = document.getElementById('thumbsContainer');
  if (!container) return console.warn('logThumbCenterCalc: no #thumbsContainer');
  const thumbNodes = Array.from(container.querySelectorAll('.thumb-item'));
  const target = thumbNodes[index];
  if (!target) return console.warn('logThumbCenterCalc: invalid index', index);
  const thumbW = target.offsetWidth;
  const containerWidth = container.clientWidth;
  const cRect = container.getBoundingClientRect();
  const tRect = target.getBoundingClientRect();
  const pRect = document.getElementById('thumbPrev')?.getBoundingClientRect();
  const nRect = document.getElementById('thumbNext')?.getBoundingClientRect();
  const leftOverlap = pRect ? Math.max(0, Math.min(pRect.right, cRect.right) - Math.max(pRect.left, cRect.left)) : 0;
  const rightOverlap = nRect ? Math.max(0, Math.min(nRect.right, cRect.right) - Math.max(nRect.left, cRect.left)) : 0;
  const effectiveVisible = Math.max(0, containerWidth - leftOverlap - rightOverlap);
  const centerOffset = leftOverlap + (effectiveVisible / 2);
  const currentScroll = container.scrollLeft || 0;
  const targetCenterInContainer = (tRect.left - cRect.left) + (thumbW / 2);
  const desired = currentScroll + targetCenterInContainer - centerOffset;
  const max = Math.max(0, container.scrollWidth - containerWidth);
  const left = Math.round(Math.max(0, Math.min(max, desired)));
  console.log({ index, thumbW, containerWidth, leftOverlap, rightOverlap, effectiveVisible, centerOffset, targetCenterInContainer, currentScroll, desired, left });
  return { index, thumbW, containerWidth, leftOverlap, rightOverlap, effectiveVisible, centerOffset, targetCenterInContainer, currentScroll, desired, left };
};

// Keep the visible thumb-strip width in sync with the main image width (including arrows)
function syncThumbStripWidth() {
  try {
    const thumbStrip = document.querySelector('.thumb-strip');
    // Prefer the main-img container so we include any container padding/borders
    const mainContainer = document.querySelector('.main-img') || document.querySelector('.main-img img') || document.getElementById('mainItemImg');
    if (!thumbStrip || !mainContainer) return;
    const rect = mainContainer.getBoundingClientRect();
    // Round to avoid subpixel half-pixel issues
    const width = Math.round(rect.width);
    // Set exact width so the distance between the outer edges of the
    // left nav and right nav matches the main image box.
    thumbStrip.style.boxSizing = 'border-box';
    thumbStrip.style.width = width + 'px';
    thumbStrip.style.maxWidth = 'none';

    // Calculate available width for the scrolling area (.thumbs) by
    // subtracting the nav buttons (and any horizontal padding/border) from
    // the total width. This avoids relying on transforms which can lead to
    // misalignment on some layouts.
    try {
      const thumbsContainer = document.getElementById('thumbsContainer');
      const prevBtn = document.getElementById('thumbPrev');
      const nextBtn = document.getElementById('thumbNext');
      const prevW = prevBtn ? prevBtn.getBoundingClientRect().width : 0;
      const nextW = nextBtn ? nextBtn.getBoundingClientRect().width : 0;
      // compute horizontal paddings/borders on thumbStrip
      const cs = getComputedStyle(thumbStrip);
      const padLeft = parseFloat(cs.paddingLeft || 0) || 0;
      const padRight = parseFloat(cs.paddingRight || 0) || 0;
      const borderLeft = parseFloat(cs.borderLeftWidth || 0) || 0;
      const borderRight = parseFloat(cs.borderRightWidth || 0) || 0;
      const navTotal = prevW + nextW + padLeft + padRight + borderLeft + borderRight;
      const thumbsWidth = Math.max(0, width - navTotal);
      if (thumbsContainer) {
        thumbsContainer.style.width = Math.round(thumbsWidth) + 'px';
        thumbsContainer.style.boxSizing = 'border-box';
      }
      // Ensure no inline transform is left from earlier debugging runs
      thumbStrip.style.transform = 'none';
    } catch (e) {
      // if anything fails, leave the thumbStrip width as-is
    }
  } catch (e) {
    // non-fatal
  }
}

// Wire up resize & image load events so the thumb strip follows the main image responsively
window.addEventListener('resize', function(){ syncThumbStripWidth(); });
document.addEventListener('DOMContentLoaded', function(){
  // initial sync (in case main image already loaded)
  syncThumbStripWidth();
  // watch for main image load (if it loads after DOMContentLoaded)
  const mainImgEl = document.querySelector('.main-img img') || document.getElementById('mainItemImg');
  if (mainImgEl) {
    mainImgEl.addEventListener('load', function(){ setTimeout(syncThumbStripWidth, 20); });
  }
  // Also observe layout changes to main image size (in case of responsive CSS changes)
  try {
    const observed = document.querySelector('.main-img');
    if (observed && window.ResizeObserver) {
      const ro = new ResizeObserver(syncThumbStripWidth);
      ro.observe(observed);
    }
  } catch (e) {}
});
