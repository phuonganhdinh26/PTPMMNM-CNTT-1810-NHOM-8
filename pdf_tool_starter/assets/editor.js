'use strict';

// Cấu hình worker cho thư viện PDF.js
if (window.pdfjsLib) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
}

const { PDFDocument, StandardFonts, rgb, degrees } = PDFLib;
const $ = (id) => document.getElementById(id);

// Thông tin tài khoản & quyền hạn
const appUser = window.APP_USER || { isLoggedIn: false, role: 'guest', limits: {} };
const isGuest = !appUser.isLoggedIn;
const userLimits = appUser.limits || {};
const canUseProOptions = Boolean(userLimits.allow_watermark);

// Trạng thái ứng dụng
let documents = [];
let pages = [];
let selected = new Set();
let undoStack = [];
let planId = null;
let dragIndex = null;
let pdfBytesCache = new Map();

// Dành riêng cho chế độ Khách (Guest) không lưu cơ sở dữ liệu
let guestFilesMap = new Map();
let guestDocCounter = 1;

// Đọc tham số kế hoạch từ URL
const params = new URLSearchParams(location.search);
if (params.has('plan')) {
    planId = Number(params.get('plan')) || null;
}

/**
 * Tránh lỗi XSS khi nhúng dữ liệu vào HTML
 */
function esc(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[c]));
}

/**
 * Lưu trạng thái để hoàn tác (Undo)
 */
function snapshot() {
    undoStack.push(JSON.stringify({ pages, selected: [...selected] }));
    if (undoStack.length > 30) {
        undoStack.shift();
    }
    $('undoBtn').disabled = false;
}

/**
 * Hoàn tác về trạng thái trước đó
 */
function restore() {
    if (!undoStack.length) return;
    const previous = JSON.parse(undoStack.pop());
    pages = previous.pages;
    selected = new Set(previous.selected);
    renderPages();
    $('undoBtn').disabled = !undoStack.length;
}

/**
 * Cập nhật số đếm trang hiển thị trên thanh công cụ
 */
function updateCount() {
    $('pageCount').textContent = `${pages.length} trang · ${selected.size} đã chọn`;
}

/**
 * Hiển thị danh sách tài liệu nguồn ở bảng bên phải
 */
function renderSources() {
    if (!documents.length) {
        $('sourceList').innerHTML = '<p class="muted">Chưa có tệp.</p>';
        return;
    }

    $('sourceList').innerHTML = documents.map((d) => `
        <div class="source-item">
            <span class="file-mark">PDF</span>
            <div class="list-main">
                <b title="${esc(d.original_name)}">${esc(d.original_name)}</b>
                <small>${d.page_count} trang · ${(d.file_size / 1048576).toFixed(2)} MB</small>
            </div>
        </div>
    `).join('');
}

/**
 * Hiển thị lưới danh sách trang và render xem trước từng trang bằng PDF.js
 */
async function renderPages() {
    updateCount();
    const area = $('pages');

    if (!pages.length) {
        area.innerHTML = '<div class="empty large-empty">Không còn trang nào. Tải PDF hoặc hoàn tác thao tác vừa rồi.</div>';
        return;
    }

    area.innerHTML = '';

    for (let i = 0; i < pages.length; i++) {
        const p = pages[i];
        const el = document.createElement('article');
        el.className = 'page-card' + (selected.has(p.key) ? ' is-selected' : '');
        el.draggable = true;
        el.dataset.index = i;

        el.innerHTML = `
            <div class="page-card-top">
                <label class="page-check">
                    <input type="checkbox" ${selected.has(p.key) ? 'checked' : ''} aria-label="Chọn trang ${i + 1}">
                    <span>${i + 1}</span>
                </label>
                <button class="icon-btn" title="Xoay 90°" data-rotate="1">↻</button>
            </div>
            <div class="preview-wrap">
                <canvas></canvas>
                <span class="rotation-badge">${p.rotation}°</span>
            </div>
            <div class="page-meta">
                <b>${esc(p.name)}</b>
                <small>Nguồn: trang ${p.sourcePage}</small>
            </div>
        `;

        // Chọn / bỏ chọn thẻ (chỉ cập nhật class và checkbox, không re-render canvas)
        el.addEventListener('click', (e) => {
            if (e.target.closest('button')) return;
            if (e.target.tagName === 'INPUT') return;
            toggleSelect(p.key);
        });

        el.querySelector('input').addEventListener('change', (e) => {
            if (e.target.checked) selected.add(p.key);
            else selected.delete(p.key);
            updateSelectionUI();
        });

        // Xoay 90 độ (chỉ re-render đúng thẻ hiện tại)
        el.querySelector('[data-rotate]').addEventListener('click', async (e) => {
            e.stopPropagation();
            snapshot();
            p.rotation = (p.rotation + 90) % 360;
            await rerenderCardPreview(el, p);
        });

        // Kéo thả thay đổi thứ tự trang
        el.addEventListener('dragstart', (e) => {
            dragIndex = i;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(i));
        });

        el.addEventListener('dragover', (e) => {
            e.preventDefault();
            el.classList.add('drag-over');
        });

        el.addEventListener('dragleave', () => el.classList.remove('drag-over'));

        el.addEventListener('drop', (e) => {
            e.preventDefault();
            el.classList.remove('drag-over');
            const from = Number(e.dataTransfer.getData('text/plain'));
            const to = Number(el.dataset.index);

            if (Number.isInteger(from) && from !== to && from >= 0 && from < pages.length) {
                snapshot();
                const [moved] = pages.splice(from, 1);
                pages.splice(to, 0, moved);
                renderPages();
            }
        });

        area.appendChild(el);

        // Render preview lên thẻ canvas
        try {
            const pdf = await getPdfJs(p.documentId);
            const pg = await pdf.getPage(p.sourcePage);
            const viewport = pg.getViewport({ scale: 0.52, rotation: p.rotation });
            const canvas = el.querySelector('canvas');
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);

            await pg.render({
                canvasContext: canvas.getContext('2d'),
                viewport,
            }).promise;
        } catch (e) {
            el.querySelector('.preview-wrap').innerHTML = '<div class="preview-error">Không xem trước được trang</div>';
        }
    }
}

/**
 * Cập nhật giao diện chọn trang (chỉ đổi class & checkbox, cực nhanh và không re-render canvas)
 */
function updateSelectionUI() {
    updateCount();
    const cards = $('pages').querySelectorAll('.page-card');
    cards.forEach((card) => {
        const idx = Number(card.dataset.index);
        const p = pages[idx];
        if (!p) return;
        const isSel = selected.has(p.key);
        card.classList.toggle('is-selected', isSel);
        const chk = card.querySelector('input[type="checkbox"]');
        if (chk && chk.checked !== isSel) {
            chk.checked = isSel;
        }
    });
}

/**
 * Đổi trạng thái chọn của một trang mà không gây giật lag
 */
function toggleSelect(key) {
    if (selected.has(key)) selected.delete(key);
    else selected.add(key);
    updateSelectionUI();
}

/**
 * Chỉ render lại xem trước của 1 thẻ cụ thể (khi xoay trang)
 */
async function rerenderCardPreview(el, p) {
    const badge = el.querySelector('.rotation-badge');
    if (badge) badge.textContent = `${p.rotation}°`;
    try {
        const pdf = await getPdfJs(p.documentId);
        const pg = await pdf.getPage(p.sourcePage);
        const viewport = pg.getViewport({ scale: 0.52, rotation: p.rotation });
        const canvas = el.querySelector('canvas');
        if (canvas) {
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            await pg.render({
                canvasContext: canvas.getContext('2d'),
                viewport,
            }).promise;
        }
    } catch (e) {
        console.error('Không thể cập nhật xem trước thẻ:', e);
    }
}

/**
 * Bộ đệm đối tượng PDF.js của tài liệu nguồn (hỗ trợ cả Guest và User đăng nhập)
 */
async function getPdfJs(id) {
    if (!window.pdfjsLib) throw new Error('Không tải được PDF.js');

    if (isGuest) {
        if (!guestFilesMap.has(id)) throw new Error('Không tìm thấy tệp khách trong bộ nhớ tạm');
        if (!pdfBytesCache.has('guest_js_' + id)) {
            const buffer = guestFilesMap.get(id);
            pdfBytesCache.set('guest_js_' + id, pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise);
        }
        return pdfBytesCache.get('guest_js_' + id);
    }

    if (!pdfBytesCache.has('js' + id)) {
        const r = await fetch(`api.php?action=file&id=${id}`);
        if (!r.ok) throw new Error('Không tải được tệp nguồn');
        const b = await r.arrayBuffer();
        pdfBytesCache.set('js' + id, pdfjsLib.getDocument({ data: new Uint8Array(b) }).promise);
    }

    return pdfBytesCache.get('js' + id);
}

/**
 * Bộ đệm ArrayBuffer thô của tài liệu nguồn
 */
async function getBytes(id) {
    if (isGuest) {
        if (!guestFilesMap.has(id)) throw new Error('Không tìm thấy tệp khách trong bộ nhớ tạm');
        return guestFilesMap.get(id);
    }

    if (!pdfBytesCache.has('bytes' + id)) {
        const r = await fetch(`api.php?action=file&id=${id}`);
        if (!r.ok) throw new Error('Không tải được tệp nguồn');
        pdfBytesCache.set('bytes' + id, await r.arrayBuffer());
    }

    return pdfBytesCache.get('bytes' + id);
}

/**
 * Tải danh sách tệp lên máy chủ hoặc xử lý trực tiếp trên trình duyệt (Khách)
 */
async function uploadFiles(fileList) {
    const files = [...fileList];
    if (!files.length) return;

    const maxFiles = userLimits.max_files || 10;
    const maxBytes = userLimits.max_file_bytes || (20 * 1024 * 1024);
    const maxPages = userLimits.max_pages || 200;
    const maxMb = userLimits.max_file_mb || 20;

    if (files.length > maxFiles) {
        alert(`Gói ${userLimits.label || 'hiện tại'} chỉ cho phép tải tối đa ${maxFiles} tệp mỗi lượt.`);
        return;
    }

    $('uploadStatus').textContent = 'Đang kiểm tra và xử lý tệp…';

    try {
        // =====================================================================
        // XỬ LÝ DÀNH CHO KHÁCH (GUEST): Hoàn toàn chạy trên trình duyệt Client
        // =====================================================================
        if (isGuest) {
            for (const f of files) {
                if (!/\.pdf$/i.test(f.name) || (f.type && f.type !== 'application/pdf')) {
                    throw new Error(`${f.name}: chỉ chấp nhận định dạng tệp PDF.`);
                }
                if (f.size > maxBytes || f.size < 8) {
                    throw new Error(`${f.name}: tệp rỗng hoặc vượt giới hạn ${maxMb} cho Khách vãng lai.`);
                }

                const buffer = await f.arrayBuffer();
                if (new TextDecoder().decode(new Uint8Array(buffer.slice(0, 5))) !== '%PDF-') {
                    throw new Error(`${f.name}: chữ ký PDF không hợp lệ.`);
                }

                const pdf = await pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
                if (pdf.numPages > maxPages) {
                    throw new Error(`${f.name}: vượt giới hạn ${maxPages} trang cho Khách vãng lai.`);
                }

                const docId = guestDocCounter++;
                guestFilesMap.set(docId, buffer);

                documents.push({
                    id: docId,
                    original_name: f.name,
                    page_count: pdf.numPages,
                    file_size: f.size
                });

                for (let n = 1; n <= pdf.numPages; n++) {
                    pages.push({
                        key: `guest-${docId}-${n}-${crypto.randomUUID()}`,
                        documentId: docId,
                        sourcePage: n,
                        name: f.name,
                        rotation: 0,
                    });
                }
            }

            renderSources();
            renderPages();
            $('uploadStatus').textContent = `Đã nạp ${files.length} tệp (Chế độ Khách: chạy trực tiếp trên máy).`;
            return;
        }

        // =====================================================================
        // XỬ LÝ DÀNH CHO USER / PRO / ADMIN: Tải lên Cloud Server
        // =====================================================================
        const counts = [];
        for (const f of files) {
            if (!/\.pdf$/i.test(f.name) || (f.type && f.type !== 'application/pdf')) {
                throw new Error(`${f.name}: chỉ chấp nhận tệp PDF.`);
            }
            if (f.size > maxBytes || f.size < 8) {
                throw new Error(`${f.name}: tệp rỗng hoặc vượt giới hạn ${maxMb}.`);
            }

            const data = await f.arrayBuffer();
            if (new TextDecoder().decode(new Uint8Array(data.slice(0, 5))) !== '%PDF-') {
                throw new Error(`${f.name}: chữ ký PDF không hợp lệ.`);
            }

            const pdf = await pdfjsLib.getDocument({ data: new Uint8Array(data) }).promise;
            if (pdf.numPages > maxPages) {
                throw new Error(`${f.name}: vượt giới hạn ${maxPages} trang.`);
            }
            counts.push(pdf.numPages);
        }

        $('uploadStatus').textContent = 'Đang tải tệp lên máy chủ…';
        const fd = new FormData();
        files.forEach((f) => fd.append('files[]', f));
        counts.forEach((n, i) => fd.append(`page_counts[${i}]`, n));

        const res = await fetch('api.php?action=upload', { method: 'POST', body: fd });
        const dataRes = await res.json();
        if (!dataRes.ok) throw new Error(dataRes.error);

        const list = await (await fetch('api.php?action=list')).json();
        documents = list.documents;

        for (const saved of dataRes.saved) {
            for (let n = 1; n <= saved.pages; n++) {
                pages.push({
                    key: `${saved.id}-${n}-${crypto.randomUUID()}`,
                    documentId: saved.id,
                    sourcePage: n,
                    name: saved.name,
                    rotation: 0,
                });
            }
        }

        renderSources();
        renderPages();
        $('uploadStatus').textContent = `Đã lưu thành công ${dataRes.saved.length} tệp lên kho cá nhân.`;
    } catch (e) {
        $('uploadStatus').textContent = '';
        alert(e.message || 'Không thể tải PDF.');
    }
}

/**
 * Tạo ảnh PNG watermark độ nét cao hỗ trợ đầy đủ tiếng Việt có dấu
 * Xoay chữ chéo 45 độ, màu đỏ nhạt với độ mờ alpha 0.25
 */
async function createWatermarkPng(text, width = 800, height = 800) {
    return new Promise((resolve) => {
        try {
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                resolve(null);
                return;
            }

            ctx.clearRect(0, 0, width, height);
            ctx.save();
            ctx.translate(width / 2, height / 2);
            // Xoay chéo 45 độ ngược chiều kim đồng hồ trong hệ tọa độ canvas
            ctx.rotate(-45 * Math.PI / 180);

            // Cỡ chữ lớn 50 - 60pt, in đậm
            ctx.font = 'bold 56px "Segoe UI", Arial, Helvetica, sans-serif';
            // Màu chữ đỏ nhạt hoặc xám mờ với alpha 0.25
            ctx.fillStyle = 'rgba(220, 38, 38, 0.25)';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(text, 0, 0);
            ctx.restore();

            canvas.toBlob((blob) => {
                if (!blob) {
                    resolve(null);
                    return;
                }
                const reader = new FileReader();
                reader.onload = () => resolve(new Uint8Array(reader.result));
                reader.onerror = () => resolve(null);
                reader.readAsArrayBuffer(blob);
            }, 'image/png');
        } catch (e) {
            console.warn('Lỗi khi vẽ canvas watermark:', e);
            resolve(null);
        }
    });
}

/**
 * Xuất nhóm trang thành một tệp PDF hoàn chỉnh
 * Áp dụng Đánh số trang, Watermark và Mật khẩu (nếu là Pro/Admin)
 */
async function exportGroup(group, filename, groupName) {
    if (!group.length) throw new Error('Nhóm xuất không có trang.');

    // 1. Tạo tài liệu PDF mới
    const out = await PDFDocument.create();
    const loaded = new Map();

    for (const p of group) {
        let source = loaded.get(p.documentId);
        if (!source) {
            source = await PDFDocument.load(await getBytes(p.documentId), { ignoreEncryption: false });
            loaded.set(p.documentId, source);
        }

        if (p.sourcePage < 1 || p.sourcePage > source.getPageCount()) {
            throw new Error(`Trang ${p.sourcePage} không tồn tại trong ${p.name}.`);
        }

        const [copied] = await out.copyPages(source, [p.sourcePage - 1]);
        const original = source.getPage(p.sourcePage - 1).getRotation().angle || 0;
        copied.setRotation(degrees(((original + p.rotation) % 360 + 360) % 360));
        out.addPage(copied);
    }

    const totalPages = out.getPageCount();

    // 2. Tùy chọn Pro: Tự động đánh số trang
    if (canUseProOptions && $('optNumbering') && $('optNumbering').checked) {
        try {
            const numFont = await out.embedFont(StandardFonts.Helvetica);
            for (let i = 0; i < totalPages; i++) {
                const page = out.getPage(i);
                const { width } = page.getSize();
                const numText = `Trang ${i + 1} / ${totalPages}`;
                const textWidth = numFont.widthOfTextAtSize(numText, 9);
                page.drawText(numText, {
                    x: (width - textWidth) / 2,
                    y: 16,
                    size: 9,
                    font: numFont,
                    color: rgb(0.35, 0.35, 0.4),
                });
            }
        } catch (e) {
            console.warn('Không thể thêm số trang:', e);
        }
    }

    // 3. Tùy chọn: Chèn Watermark chéo (Hỗ trợ tiếng Việt "BẢN SAO", vẽ đè lên từng trang)
    const isWatermarkChecked = Boolean($('optWatermark') && $('optWatermark').checked);
    const rawWatermarkText = $('watermarkText') ? $('watermarkText').value.trim() : '';
    const watermarkText = isWatermarkChecked ? (rawWatermarkText || 'BẢN SAO') : '';

    if (isWatermarkChecked) {
        try {
            const pngBytes = await createWatermarkPng(watermarkText);
            if (pngBytes) {
                const wmImage = await out.embedPng(pngBytes);
                for (let i = 0; i < totalPages; i++) {
                    const page = out.getPage(i);
                    const { width, height } = page.getSize();
                    const size = Math.max(width, height) * 0.9;
                    page.drawImage(wmImage, {
                        x: (width - size) / 2,
                        y: (height - size) / 2,
                        width: size,
                        height: size,
                    });
                }
            } else {
                // Fallback nếu không tạo được canvas: chuẩn hóa loại bỏ dấu tiếng Việt để dùng WinAnsi
                const safeAscii = watermarkText.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D');
                const wmFont = await out.embedFont(StandardFonts.HelveticaBold);
                const fontSize = 52;
                const textWidth = wmFont.widthOfTextAtSize(safeAscii, fontSize);
                const rad = 45 * Math.PI / 180;
                for (let i = 0; i < totalPages; i++) {
                    const page = out.getPage(i);
                    const { width, height } = page.getSize();
                    const cx = width / 2;
                    const cy = height / 2;
                    page.drawText(safeAscii, {
                        x: cx - (textWidth / 2) * Math.cos(rad),
                        y: cy - (textWidth / 2) * Math.sin(rad),
                        size: fontSize,
                        font: wmFont,
                        color: rgb(0.85, 0.25, 0.25),
                        opacity: 0.25,
                        rotate: degrees(45),
                    });
                }
            }
        } catch (e) {
            console.error('Không thể chèn Watermark:', e);
        }
    }

    // 4. Tùy chọn Pro: Đặt mật khẩu bảo vệ file
    if (canUseProOptions && $('optPassword') && $('optPassword').checked) {
        const pass = $('pdfPassword').value.trim();
        if (pass && typeof out.encrypt === 'function') {
            out.encrypt({
                userPassword: pass,
                ownerPassword: pass,
            });
        }
    }

    // 5. Lưu bytes
    const bytes = await out.save();

    // =========================================================================
    // NẾU LÀ KHÁCH (GUEST): Tự động tải ngay về máy, không lưu máy chủ
    // =========================================================================
    if (isGuest) {
        const blob = new Blob([bytes], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);

        return {
            id: 0,
            name: filename,
            pages: group.length,
            isGuestDirect: true,
            downloadUrl: url,
        };
    }

    // =========================================================================
    // NẾU ĐÃ ĐĂNG NHẬP: Lưu vào Cơ sở dữ liệu và Storage qua API
    // Thu thập đầy đủ watermark_enabled và watermark_text gửi lên Backend
    // =========================================================================
    const payload = {
        name: $('planName').value.trim() || 'Kế hoạch PDF',
        pages: group.map((p) => ({
            documentId: p.documentId,
            sourcePage: p.sourcePage,
            rotation: p.rotation,
            name: p.name,
        })),
        group: groupName,
        watermark_enabled: isWatermarkChecked,
        watermark_text: isWatermarkChecked ? watermarkText : 'BẢN SAO',
    };

    const begin = await fetch('api.php?action=begin_export', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            ...payload,
            planId: planId || null,
            watermark_enabled: isWatermarkChecked,
            watermark_text: isWatermarkChecked ? watermarkText : 'BẢN SAO',
        }),
    });
    const bj = await begin.json();
    if (!bj.ok) throw new Error(bj.error);
    const jobId = bj.jobId;

    try {
        const blob = new Blob([bytes], { type: 'application/pdf' });
        const blobUrl = URL.createObjectURL(blob);
        const fd = new FormData();
        fd.append('job_id', jobId);
        fd.append('filename', filename);
        fd.append('watermark_enabled', isWatermarkChecked ? '1' : '0');
        fd.append('watermark_text', isWatermarkChecked ? watermarkText : 'BẢN SAO');
        fd.append('page_map', JSON.stringify(payload.pages.map((p, i) => ({
            outputPage: i + 1,
            sourceFile: p.name,
            sourcePage: p.sourcePage,
            rotation: p.rotation,
        }))));
        fd.append('pdf', blob, filename);

        const rr = await fetch('api.php?action=finish_export', { method: 'POST', body: fd });
        const jr = await rr.json();
        if (!jr.ok) throw new Error(jr.error);

        return { id: jr.outputId, name: filename, pages: group.length, isGuestDirect: false, blobUrl: blobUrl };
    } catch (e) {
        const fd = new FormData();
        fd.append('job_id', jobId);
        fd.append('status', 'failed');
        fd.append('error_message', e.message || 'Lỗi không xác định');
        await fetch('api.php?action=finish_export', { method: 'POST', body: fd });
        throw e;
    }
}

/**
 * Hiển thị liên kết tải về sau khi xuất PDF thành công
 */
function showResults(items, message) {
    $('resultPanel').hidden = false;
    $('resultMessage').textContent = message;

    $('resultLinks').innerHTML = items.map((x) => {
        if (x.isGuestDirect) {
            return `
                <div class="list-item">
                    <span class="file-mark green-mark">PDF</span>
                    <div class="list-main">
                        <b>${esc(x.name)}</b>
                        <small>${x.pages} trang · Đã tự động tải về máy</small>
                    </div>
                    <a class="btn primary small-btn" href="${x.downloadUrl}" download="${esc(x.name)}">Tải lại</a>
                </div>
            `;
        }

        const downloadHref = x.blobUrl || `api.php?action=download&id=${x.id}`;
        return `
            <div class="list-item">
                <span class="file-mark green-mark">PDF</span>
                <div class="list-main">
                    <b>${esc(x.name)}</b>
                    <small>${x.pages} trang · Xuất thành công</small>
                </div>
                <a class="btn primary small-btn" href="${downloadHref}" download="${esc(x.name)}">Tải PDF</a>
            </div>
        `;
    }).join('');

    $('resultPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ==========================================
// Custom Modal: Giới thiệu & Mua gói Pro
// ==========================================
function openProFeatureModal() {
    const m = document.getElementById('proFeatureModal');
    if (m) {
        m.style.display = 'flex';
        requestAnimationFrame(() => m.classList.add('open'));
    }
}

function closeProFeatureModal() {
    const m = document.getElementById('proFeatureModal');
    if (m) {
        m.classList.remove('open');
        setTimeout(() => {
            if (!m.classList.contains('open')) m.style.display = 'none';
        }, 200);
    }
}

function handleProModalBackdropClick(e) {
    if (e.target && e.target.id === 'proFeatureModal') {
        closeProFeatureModal();
    }
}

// Hỗ trợ đóng modal khi bấm phím ESC
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeProFeatureModal();
    }
});

// Chặn nhấp tùy chọn Pro đối với User thường và Khách -> Mở Custom Modal Pro
['optNumberingLabel', 'optWatermarkLabel', 'optPasswordLabel'].forEach((id) => {
    const el = document.getElementById(id);
    if (el && !canUseProOptions) {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            openProFeatureModal();
        });
    }
});

// Thiết lập các sự kiện giao diện
if ($('watermarkText')) {
    $('watermarkText').addEventListener('input', () => {
        if ($('optWatermark') && !$('optWatermark').checked) {
            $('optWatermark').checked = true;
        }
    });
}

$('fileInput').addEventListener('change', (e) => {
    uploadFiles(e.target.files);
    e.target.value = '';
});

$('dropZone').addEventListener('dragover', (e) => {
    e.preventDefault();
    $('dropZone').classList.add('drag-active');
});

$('dropZone').addEventListener('dragleave', () => $('dropZone').classList.remove('drag-active'));

$('dropZone').addEventListener('drop', (e) => {
    e.preventDefault();
    $('dropZone').classList.remove('drag-active');
    uploadFiles(e.dataTransfer.files);
});

$('rotateBtn').addEventListener('click', async () => {
    if (!selected.size) return alert('Hãy chọn ít nhất một trang.');
    snapshot();
    const cards = $('pages').querySelectorAll('.page-card');
    for (const card of cards) {
        const idx = Number(card.dataset.index);
        const p = pages[idx];
        if (p && selected.has(p.key)) {
            p.rotation = (p.rotation + 90) % 360;
            rerenderCardPreview(card, p);
        }
    }
});

$('removeBtn').addEventListener('click', () => {
    if (!selected.size) return alert('Hãy chọn trang cần bỏ.');
    snapshot();
    pages = pages.filter((p) => !selected.has(p.key));
    selected.clear();
    renderPages();
});

$('undoBtn').addEventListener('click', restore);

$('selectAll').addEventListener('click', () => {
    selected = selected.size === pages.length ? new Set() : new Set(pages.map((p) => p.key));
    updateSelectionUI();
});

// Lưu kế hoạch
$('savePlan').addEventListener('click', async () => {
    if (isGuest) {
        if (confirm('Tính năng lưu kế hoạch đám mây yêu cầu đăng nhập tài khoản. Bạn có muốn chuyển sang trang Đăng nhập không?')) {
            window.location.href = 'index.php';
        }
        return;
    }

    try {
        const payload = {
            id: planId,
            name: $('planName').value.trim() || 'Kế hoạch PDF',
            pages: pages.map((p) => ({
                documentId: p.documentId,
                sourcePage: p.sourcePage,
                rotation: p.rotation,
                name: p.name,
            })),
        };

        const r = await fetch('api.php?action=save_plan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const d = await r.json();
        if (!d.ok) throw new Error(d.error);

        planId = d.id;
        history.replaceState(null, '', `editor.php?plan=${planId}`);
        alert(`Đã lưu kế hoạch (phiên bản ${d.version}).`);
    } catch (e) {
        alert(e.message);
    }
});

// Ghép & xuất PDF
$('exportBtn').addEventListener('click', async () => {
    if (!pages.length) return alert('Chưa có trang để xuất.');
    const btn = $('exportBtn');
    btn.disabled = true;
    btn.textContent = 'Đang xuất…';

    try {
        const x = await exportGroup(pages, `ho-so-${Date.now()}.pdf`, 'merge');
        const msg = isGuest
            ? 'Đã xuất và tự động tải trực tiếp xuống máy của bạn (Chế độ Khách vãng lai: tệp không lưu trên máy chủ).'
            : 'Tệp đã được tạo từ đúng thứ tự trang đang hiển thị.';
        showResults([x], msg);
    } catch (e) {
        alert('Xuất PDF thất bại: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Ghép & xuất PDF';
    }
});

// Tách trang đã chọn
$('splitBtn').addEventListener('click', async () => {
    if (!selected.size) return alert('Chọn các trang muốn tách trước.');
    const chosen = pages.filter((p) => selected.has(p.key));
    const rest = pages.filter((p) => !selected.has(p.key));

    if (!chosen.length) return;

    const btn = $('splitBtn');
    btn.disabled = true;
    btn.textContent = 'Đang tách…';
    const outputs = [];

    try {
        outputs.push(await exportGroup(chosen, `nhom-da-chon-${Date.now()}.pdf`, 'selected'));
        if (rest.length) {
            outputs.push(await exportGroup(rest, `nhom-con-lai-${Date.now()}.pdf`, 'remaining'));
        }
        showResults(outputs, 'Đã tách theo lựa chọn: một tệp chứa trang đã chọn, một tệp chứa các trang còn lại.');
    } catch (e) {
        alert('Tách PDF chưa hoàn tất: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Tách trang đã chọn';
    }
});

/**
 * Khởi tạo dữ liệu trang khi mở ứng dụng
 */
async function init() {
    try {
        if (!isGuest) {
            const r = await fetch('api.php?action=list');
            const d = await r.json();
            if (!d.ok) throw new Error(d.error);
            documents = d.documents;
            renderSources();

            if (planId) {
                const pr = await fetch(`api.php?action=load_plan&id=${planId}`);
                const pd = await pr.json();
                if (pd.ok) {
                    $('planName').value = pd.plan.name;
                    pages = pd.plan.data.pages.map((p, i) => ({
                        key: `loaded-${i}-${crypto.randomUUID()}`,
                        documentId: Number(p.documentId),
                        sourcePage: Number(p.sourcePage),
                        rotation: Number(p.rotation) || 0,
                        name: p.name || documents.find((doc) => doc.id == p.documentId)?.original_name || 'PDF',
                    }));
                    await renderPages();
                }
            }
        }
    } catch (e) {
        console.error(e);
    }
}

init();
