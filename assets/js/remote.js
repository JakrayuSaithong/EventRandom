/**
 * Remote Page — JavaScript Logic
 * Event/prize selection, draw controls, command dispatch
 */

const API = 'ajax/ajax_remote.php';

let selectedEventId = null;
let selectedPrizeId = null;
let selectedPrizeName = '';
let drawCount = 1;
let drawMode = 'one_by_one';
let maxDrawCount = 1;

// ─── INIT ───
document.addEventListener('DOMContentLoaded', () => {
    loadEvents();
});

// ─── TOAST ───
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    const icon = type === 'success' ? '<i class="fa-solid fa-check"></i>' : 
                 type === 'error' ? '<i class="fa-solid fa-xmark"></i>' : 
                 '<i class="fa-solid fa-info"></i>';
    toast.innerHTML = `${icon} ${message}`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ─── API HELPER ───
async function api(action, data = {}, method = 'POST') {
    if (method === 'GET') {
        const params = new URLSearchParams({ action, ...data });
        const res = await fetch(`${API}?${params}`);
        return await res.json();
    }
    const formData = new FormData();
    formData.append('action', action);
    for (const [key, val] of Object.entries(data)) {
        formData.append(key, val);
    }
    const res = await fetch(API, { method: 'POST', body: formData });
    return await res.json();
}

// ═══ STEP 1: LOAD EVENTS ═══
async function loadEvents() {
    const result = await api('get_events', {}, 'GET');
    const select = document.getElementById('event-select');
    
    if (!result.success || !result.events.length) {
        select.innerHTML = '<option value="">— ไม่มีรายการ —</option>';
        return;
    }

    select.innerHTML = '<option value="">— เลือกรายการ —</option>' + 
        result.events.map(e => `<option value="${e.id}">${e.title} (${e.participant_count} คน, ${e.prize_count} รางวัล)</option>`).join('');
}

function onEventChange() {
    const select = document.getElementById('event-select');
    selectedEventId = select.value ? parseInt(select.value) : null;
    selectedPrizeId = null;
    drawCount = 1;

    const stepPrize = document.getElementById('step-prize');
    const stepSettings = document.getElementById('step-settings');

    if (selectedEventId) {
        stepPrize.style.opacity = '1';
        stepPrize.style.pointerEvents = 'auto';
        loadPrizes();
    } else {
        stepPrize.style.opacity = '0.4';
        stepPrize.style.pointerEvents = 'none';
        stepSettings.style.opacity = '0.4';
        stepSettings.style.pointerEvents = 'none';
        document.getElementById('btn-draw').disabled = true;
    }
}

// ═══ STEP 2: LOAD PRIZES ═══
async function loadPrizes() {
    const result = await api('get_prizes', { event_id: selectedEventId }, 'GET');
    const list = document.getElementById('prize-list');

    if (!result.success || !result.prizes.length) {
        list.innerHTML = '<p style="color:var(--text-muted); text-align:center; padding:var(--sp-4);">ยังไม่มีรางวัล</p>';
        return;
    }

    list.innerHTML = result.prizes.map(p => {
        const remaining = p.quantity - p.awarded_count;
        const isComplete = remaining <= 0;
        return `
            <div class="prize-option ${isComplete ? '' : ''}" 
                 data-id="${p.id}" data-remaining="${remaining}" data-name="${p.name}"
                 onclick="${isComplete ? '' : `selectPrize(${p.id}, ${remaining}, '${p.name.replace(/'/g, "\\'")}')`}"
                 style="${isComplete ? 'opacity:0.4; cursor:not-allowed;' : ''}">
                <div class="prize-info">
                    <span class="prize-name">${isComplete ? '<i class="fa-solid fa-circle-check" style="color:var(--color-green)"></i> ' : '<i class="fa-solid fa-trophy" style="color:var(--color-gold)"></i> '}${p.name}</span>
                    <span class="prize-qty">จำนวน: ${p.awarded_count} / ${p.quantity} ${isComplete ? '(ครบแล้ว)' : `(เหลือ ${remaining})`}</span>
                </div>
                ${!isComplete ? `<span class="chip chip-gold">${remaining}</span>` : '<span class="chip chip-green">ครบ</span>'}
            </div>
        `;
    }).join('');
}

async function selectPrize(prizeId, remaining, prizeName) {
    selectedPrizeId = prizeId;
    selectedPrizeName = prizeName;
    maxDrawCount = remaining;
    drawCount = 1;
    document.getElementById('draw-count-value').textContent = '1';

    // Highlight selected
    document.querySelectorAll('.prize-option').forEach(el => el.classList.remove('selected'));
    document.querySelector(`.prize-option[data-id="${prizeId}"]`).classList.add('selected');

    // Enable step 3
    const stepSettings = document.getElementById('step-settings');
    stepSettings.style.opacity = '1';
    stepSettings.style.pointerEvents = 'auto';

    // Enable draw button
    document.getElementById('btn-draw').disabled = false;
    document.getElementById('draw-status-text').textContent = 'พร้อมสุ่ม!';

    // ★ Notify Display page about prize selection
    try {
        await api('select_prize', {
            event_id: selectedEventId,
            prize_id: selectedPrizeId
        });
    } catch(e) {
        // Non-critical — display will still work without this
    }
}

// ═══ STEP 3: DRAW SETTINGS ═══
function adjustDrawCount(delta) {
    drawCount = Math.max(1, Math.min(maxDrawCount, drawCount + delta));
    document.getElementById('draw-count-value').textContent = drawCount;
}

function setDrawMode(mode, btn) {
    drawMode = mode;
    document.querySelectorAll('.radio-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

// ═══ EXECUTE DRAW ═══
async function executeDraw() {
    if (!selectedEventId || !selectedPrizeId) return;

    const btn = document.getElementById('btn-draw');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังสุ่ม...';
    document.getElementById('draw-status-text').textContent = 'กำลังส่งคำสั่งสุ่ม...';

    const result = await api('draw', {
        event_id: selectedEventId,
        prize_id: selectedPrizeId,
        draw_count: drawCount,
        draw_mode: drawMode
    });

    if (result.success) {
        showToast('<i class="fa-solid fa-party-horn"></i> ' + result.message);
        document.getElementById('draw-status-text').textContent = 'สุ่มสำเร็จ! กำลังแสดงผลบนหน้า Display...';

        // Show result
        const resultCard = document.getElementById('result-card');
        const resultContent = document.getElementById('result-content');
        resultCard.style.display = 'block';

        resultContent.innerHTML = `
            <p style="margin-bottom:var(--sp-3); color:var(--text-secondary);">รางวัล: <strong class="text-gold">${result.prize_name}</strong></p>
            <div class="flex flex-col gap-2">
                ${result.winners.map(w => `
                    <div class="chip chip-gold" style="padding:var(--sp-2) var(--sp-4); font-size:0.95rem;">
                        <i class="fa-solid fa-star"></i> ${w.name}
                    </div>
                `).join('')}
            </div>
        `;

        // Refresh prizes
        setTimeout(() => loadPrizes(), 1000);
    } else {
        showToast(result.message, 'error');
        document.getElementById('draw-status-text').textContent = result.message;
    }

    btn.disabled = false;
    btn.textContent = 'สุ่ม!';
}
