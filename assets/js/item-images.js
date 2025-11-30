(function(global){
  function escapeHtml(s){ return (s===null||s===undefined)?'':String(s).replace(/[&<>\\"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }

  function initImageReorder(previewSelOrEl, imagesInputSelOrEl) {
    const preview = (typeof previewSelOrEl === 'string') ? document.querySelector(previewSelOrEl) : previewSelOrEl;
    const imagesInput = (typeof imagesInputSelOrEl === 'string') ? document.querySelector(imagesInputSelOrEl) : imagesInputSelOrEl;
    if (!imagesInput || !preview) return;

    let unified = false;
    if (preview.dataset && preview.dataset.unified) {
      unified = true;
      try {
        const inputWrapper = imagesInput.parentNode;
        if (inputWrapper && inputWrapper.parentNode) {
          inputWrapper.parentNode.insertBefore(preview, inputWrapper);
        }
      } catch (e) { /* non-fatal */ }
    }

    let filesList = [];
    function rebuildFileInput(){
      try{
        const dt = new DataTransfer();
        filesList.forEach(f => dt.items.add(f));
        imagesInput.files = dt.files;
      }catch(e){ }
    }

    function renderPreviews(){
      const oldUploads = preview.querySelectorAll('.upload-thumb');
      oldUploads.forEach(n => n.parentNode && n.parentNode.removeChild(n));

      const existing = Array.from(preview.querySelectorAll('.existing-thumb'));
      const insertBeforeNode = existing.length ? existing[existing.length-1].nextSibling : null;

      filesList.forEach((file, idx) => {
        const url = URL.createObjectURL(file);
        const item = document.createElement('div');
        item.className = 'upload-thumb';
        item.draggable = true;
        item.dataset.index = idx;

        item.innerHTML = `<img src="${url}" alt="${escapeHtml(file.name)}"><button class="thumb-remove" type="button" title="Remove image">Remove</button><div style="font-size:0.9em;word-break:break-word;text-align:center;max-width:100%">${escapeHtml(file.name)}</div>`;

        item.addEventListener('dragstart', (e) => { e.dataTransfer.setData('text/plain', 'n'+idx); item.classList.add('dragging'); });
        item.addEventListener('dragend', () => { item.classList.remove('dragging'); });
        item.addEventListener('dragover', (e) => { e.preventDefault(); item.classList.add('drag-over'); });
        item.addEventListener('dragleave', () => { item.classList.remove('drag-over'); });
        item.addEventListener('drop', (e) => {
          e.preventDefault();
          const src = e.dataTransfer.getData('text/plain');
          if (!src) return;
          // dropping an existing-thumb (e{id}) onto an upload-thumb
          if (src.indexOf('e') === 0) {
            const srcId = src.substring(1);
            const srcNode = preview.querySelector('.existing-thumb[data-image-id="'+srcId+'"]');
            if (!srcNode) return;
            // if somehow same node, no-op
            if (srcNode === item) return;
            // compute positions on current node list and no-op if nothing would change
            const nodesNow = Array.from(preview.querySelectorAll('.existing-thumb, .upload-thumb'));
            const srcPos = nodesNow.indexOf(srcNode);
            const targetPos = nodesNow.indexOf(item);
            if (srcPos === -1 || srcPos === targetPos) return;
            // perform move
            srcNode.parentNode.removeChild(srcNode);
            const afterNodes = Array.from(preview.querySelectorAll('.existing-thumb, .upload-thumb'));
            const insertBefore = (targetPos >= afterNodes.length) ? null : afterNodes[targetPos];
            if (insertBefore) preview.insertBefore(srcNode, insertBefore); else preview.appendChild(srcNode);
            try { syncCombinedOrder(); } catch (err) {}
            return;
          }
          // dropping an upload-thumb (n{index}) onto another upload-thumb
          if (src.indexOf('n') === 0) {
            const from = parseInt(src.substring(1),10);
            const to = parseInt(item.dataset.index,10);
            if (isNaN(from) || isNaN(to) || from === to) return;
            const moved = filesList.splice(from,1)[0];
            filesList.splice(to,0,moved);
            rebuildFileInput(); renderPreviews();
          }
        });

        const imgEl = item.querySelector('img'); if (imgEl) imgEl.setAttribute('draggable','false');
        item.querySelector('.thumb-remove').addEventListener('click', () => {
          filesList.splice(idx,1); rebuildFileInput(); renderPreviews();
        });

        if (insertBeforeNode) preview.insertBefore(item, insertBeforeNode);
        else preview.appendChild(item);
      });

      try { syncCombinedOrder(); } catch (e) { /* non-fatal */ }
    }

    function attachExistingHandlers(node) {
      if (!node) return;
      // Prevent attaching handlers multiple times to the same node
      if (node.dataset && node.dataset.reorderBound) return;
      try { if (node.dataset) node.dataset.reorderBound = '1'; } catch (e) {}
      node.draggable = true;
      const img = node.querySelector('img'); if (img) img.setAttribute('draggable','false');
      node.addEventListener('dragstart', function(e){
        const id = node.dataset.imageId || node.getAttribute('data-image-id');
        e.dataTransfer.setData('text/plain', id ? ('e'+id) : '');
        node.classList.add('dragging');
      });
      node.addEventListener('dragend', function(){ node.classList.remove('dragging'); });
      node.addEventListener('dragover', function(e){ e.preventDefault(); node.classList.add('drag-over'); });
      node.addEventListener('dragleave', function(){ node.classList.remove('drag-over'); });
      node.addEventListener('drop', function(e){
        e.preventDefault(); node.classList.remove('drag-over');
        const src = e.dataTransfer.getData('text/plain');
        if (!src) return;
        const nodesNow = Array.from(preview.querySelectorAll('.existing-thumb, .upload-thumb'));
        const targetIdx = nodesNow.indexOf(node);
        if (src.indexOf('e') === 0) {
          const srcId = src.substring(1);
          const srcNode = preview.querySelector('.existing-thumb[data-image-id="'+srcId+'"]');
          if (!srcNode) return;
          // no-op when dropped on itself or position unchanged
          const srcPos = nodesNow.indexOf(srcNode);
          if (srcNode === node || srcPos === -1 || srcPos === targetIdx) return;
          // remove then re-insert before the target position (recompute nodes after removal)
          srcNode.parentNode.removeChild(srcNode);
          const afterNodes = Array.from(preview.querySelectorAll('.existing-thumb, .upload-thumb'));
          const insertBefore = (targetIdx >= afterNodes.length) ? null : afterNodes[targetIdx];
          if (insertBefore) preview.insertBefore(srcNode, insertBefore); else preview.appendChild(srcNode);
          try { syncCombinedOrder(); } catch (err) {}
          return;
        } else if (src.indexOf('n') === 0) {
          const srcIdx = parseInt(src.substring(1),10);
          if (isNaN(srcIdx)) return;
          // find upload node by its data-index
          const uploadNodes = Array.from(preview.querySelectorAll('.upload-thumb'));
          const srcNode = uploadNodes.find(u => parseInt(u.dataset.index,10) === srcIdx);
          if (!srcNode) return;
          const srcPos = nodesNow.indexOf(srcNode);
          if (srcNode === node || srcPos === -1 || srcPos === targetIdx) return;
          srcNode.parentNode.removeChild(srcNode);
          const afterNodes = Array.from(preview.querySelectorAll('.existing-thumb, .upload-thumb'));
          const insertBefore = (targetIdx >= afterNodes.length) ? null : afterNodes[targetIdx];
          if (insertBefore) preview.insertBefore(srcNode, insertBefore); else preview.appendChild(srcNode);
          // rebuild filesList order based on new DOM order of upload-thumb elements
          const oldFiles = filesList.slice();
          const newUploadNodes = Array.from(preview.querySelectorAll('.upload-thumb'));
          filesList = newUploadNodes.map(n => {
            const idx = parseInt(n.dataset.index,10);
            return oldFiles[idx];
          }).filter(Boolean);
          rebuildFileInput(); renderPreviews();
          return;
        }
      });
    }

    Array.from(preview.querySelectorAll('.existing-thumb')).forEach(n => attachExistingHandlers(n));

    function syncCombinedOrder(){
      const formEl = imagesInput.closest('form');
      if (!formEl) return;
      Array.from(formEl.querySelectorAll('input[name="combinedOrder[]"]')).forEach(n => n.parentNode && n.parentNode.removeChild(n));
      const rawNodes = Array.from(preview.querySelectorAll('.existing-thumb, .upload-thumb'));
      const nodes = rawNodes.slice().sort((a, b) => {
        const la = a.offsetLeft || 0; const lb = b.offsetLeft || 0;
        if (la === lb) return rawNodes.indexOf(a) - rawNodes.indexOf(b);
        return la - lb;
      });
      let uploadCounter = 0;
      nodes.forEach((node) => {
        let val = null;
        if (node.classList.contains('existing-thumb')) {
          const id = node.dataset.imageId || node.getAttribute('data-image-id');
          if (id) val = 'e' + id;
        } else if (node.classList.contains('upload-thumb')) {
          val = 'n' + uploadCounter;
          uploadCounter++;
        }
        if (val) {
          const hid = document.createElement('input'); hid.type = 'hidden'; hid.name = 'combinedOrder[]'; hid.value = val;
          formEl.appendChild(hid);
        }
      });
    }

    imagesInput.addEventListener('change', async (e) => {
      const newFiles = Array.from(e.target.files || []);
      const existingCount = preview.querySelectorAll('.existing-thumb').length;
      const remainingSlots = Math.max(0, 10 - existingCount - filesList.length);
      if (remainingSlots <= 0) {
        alert('You already have 10 images; remove some existing images before adding more.');
        imagesInput.value = '';
        return;
      }

      const itemId = preview.dataset && preview.dataset.itemId ? preview.dataset.itemId : null;
      if (itemId) {
        const toUpload = newFiles.slice(0, remainingSlots);
        for (const file of toUpload) {
          try {
            const fd = new FormData();
            fd.append('itemId', itemId);
            fd.append('file', file);
            const resp = await fetch('/api/owner/upload_item_image.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            if (!resp.ok) {
              console.warn('Upload failed', await resp.text());
              filesList.push(file);
              continue;
            }
            const data = await resp.json();
            if (data && data.success) {
              const item = document.createElement('div');
              item.className = 'existing-thumb';
              item.dataset.imageId = data.id;
              item.innerHTML = `<img src="${data.url}" alt=""><div style="display:flex;gap:6px;margin-top:6px"><button type="button" class="btn small thumb-remove" style="background:#c00;border-color:#a00">Remove</button></div><input type="hidden" name="existingOrder[]" value="${data.id}">`;
              preview.appendChild(item);
              try { attachExistingHandlers(item); } catch (e) {}
              try { syncCombinedOrder(); } catch (e) {}
            } else {
              console.warn('Upload response error', data);
              filesList.push(file);
            }
          } catch (err) {
            console.warn('Upload exception', err);
            filesList.push(file);
          }
        }
        rebuildFileInput(); renderPreviews();
        imagesInput.value = '';
        return;
      }

      filesList = filesList.concat(newFiles.slice(0, remainingSlots));
      rebuildFileInput(); renderPreviews();
      imagesInput.value = '';
    });

    preview.addEventListener('click', function(e){
      const btn = e.target.closest && e.target.closest('.existing-thumb .thumb-remove');
      if (!btn) return;
      const thumb = btn.closest('.existing-thumb');
      if (!thumb) return;
      thumb.parentNode && thumb.parentNode.removeChild(thumb);
      try { syncCombinedOrder(); } catch (err) {}
    });

    const parentForm = imagesInput.closest('form');
    if (parentForm) {
      parentForm.addEventListener('submit', function(){ rebuildFileInput(); try { syncCombinedOrder(); } catch (e) {} });
    }

    const style = document.createElement('style');
    style.textContent = '\n' +
      '.upload-thumb.drag-over{outline:2px dashed #0077cc} \n' +
      '.upload-thumb img{display:block} \n' +
      '.upload-thumb, .existing-thumb { width: var(--thumb-size, 80px); padding:6px; border:1px solid #ddd; border-radius:6px; background:#fff; display:flex; flex-direction:column; align-items:center; gap:6px; position:relative; box-sizing:border-box; } \n' +
      '.upload-thumb img, .existing-thumb img { width:100%; height:var(--thumb-size, 80px); object-fit:cover; border-radius:4px; display:block; } \n' +
      '.upload-thumb .thumb-remove, .existing-thumb .thumb-remove { margin-top:6px; }';
    document.head.appendChild(style);
  }

  global.initImageReorder = initImageReorder;
})(window);

