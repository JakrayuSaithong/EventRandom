/**
 * Admin Page — JavaScript Logic (SweetAlert2 edition)
 * CRUD operations for events, participants, prizes, winners
 */

const API = 'ajax/ajax_admin.php';
let currentEventId = null;

// ─── INIT ───
document.addEventListener('DOMContentLoaded', () => {
    loadEvents();
});

// ─── TOAST NOTIFICATION ───
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

// ─── MODAL HELPERS ───
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// ─── API HELPER ───
async function api(action, data = {}) {
    const formData = new FormData();
    formData.append('action', action);
    for (const [key, val] of Object.entries(data)) {
        formData.append(key, val);
    }
    try {
        const res = await fetch(API, { method: 'POST', body: formData });
        return await res.json();
    } catch (e) {
        showToast('Network error', 'error');
        return { success: false };
    }
}

// ═══════════════════════════════════════
//  EVENTS
// ═══════════════════════════════════════

async function loadEvents() {
    const result = await api('get_events');
    const grid = document.getElementById('events-grid');
    const empty = document.getElementById('events-empty');

    if (!result.success || !result.events.length) {
        grid.innerHTML = '';
        empty.style.display = 'block';
        return;
    }

    empty.style.display = 'none';
    grid.innerHTML = result.events.map(e => `
        <div class="event-card">
            <div class="flex items-center justify-between">
                <span class="event-title">${e.title}</span>
                ${e.allow_duplicate ? '<span class="chip chip-teal">สุ่มซ้ำได้</span>' : '<span class="chip chip-pink">ไม่ซ้ำ</span>'}
            </div>
            <div class="event-meta">
                <span class="stat-pill"><span class="stat-icon"><i class="fa-solid fa-users"></i></span> ${e.participant_count} คน</span>
                <span class="stat-pill"><span class="stat-icon"><i class="fa-solid fa-trophy"></i></span> ${e.prize_count} รางวัล</span>
                <span class="stat-pill"><span class="stat-icon"><i class="fa-solid fa-star"></i></span> ${e.winner_count} ผู้ชนะ</span>
                <span class="stat-pill"><span class="stat-icon"><i class="fa-solid fa-clock"></i></span> ${e.display_seconds || 8}s</span>
            </div>
            <div class="event-actions">
                <button class="btn btn-sm btn-primary" onclick="openDetail(${e.id}, '${escapeHtml(e.title)}')">
                    จัดการ
                </button>
                <button class="btn btn-sm btn-outline" onclick="editEvent(${e.id})">แก้ไข</button>
                <button class="btn btn-sm btn-danger" onclick="deleteEvent(${e.id}, '${escapeHtml(e.title)}')">ลบ</button>
            </div>
        </div>
    `).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/'/g, "\\'");
}

function openCreateModal() {
    document.getElementById('modal-event-title').textContent = 'สร้างรายการใหม่';
    document.getElementById('event-id').value = '';
    document.getElementById('event-title').value = '';
    document.getElementById('event-allow-dup').checked = false;
    document.getElementById('event-display-seconds').value = 8;
    openModal('modal-event');
}

async function editEvent(id) {
    const result = await api('get_event', { id });
    if (!result.success) return showToast(result.message, 'error');

    document.getElementById('modal-event-title').textContent = 'แก้ไขรายการ';
    document.getElementById('event-id').value = result.event.id;
    document.getElementById('event-title').value = result.event.title;
    document.getElementById('event-allow-dup').checked = !!result.event.allow_duplicate;
    document.getElementById('event-display-seconds').value = result.event.display_seconds || 8;
    openModal('modal-event');
}

async function saveEvent(e) {
    e.preventDefault();
    const id = document.getElementById('event-id').value;
    const title = document.getElementById('event-title').value.trim();
    const allowDup = document.getElementById('event-allow-dup').checked ? 1 : 0;
    const displaySeconds = document.getElementById('event-display-seconds').value || 8;

    const action = id ? 'update_event' : 'create_event';
    const data = { title, allow_duplicate: allowDup, display_seconds: displaySeconds };
    if (id) data.id = id;

    const result = await api(action, data);
    if (result.success) {
        showToast(result.message);
        closeModal('modal-event');
        loadEvents();
    } else {
        showToast(result.message, 'error');
    }
}

async function deleteEvent(id, title) {
    const result2 = await Swal.fire({
        title: 'ลบรายการ?',
        html: `คุณต้องการลบรายการ <strong>"${title}"</strong> ?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#E53935'
    });
    if (!result2.isConfirmed) return;

    const result = await api('delete_event', { id });
    if (result.success) {
        showToast(result.message);
        loadEvents();
    } else {
        showToast(result.message, 'error');
    }
}

// ═══════════════════════════════════════
//  EVENT DETAIL
// ═══════════════════════════════════════

function openDetail(eventId, title) {
    currentEventId = eventId;
    document.getElementById('detail-title').textContent = title;
    
    switchDetailTab('participants', document.querySelector('.tab-btn'));
    
    openModal('modal-detail');
    loadParticipants();
}

function switchDetailTab(tab, btn) {
    document.querySelectorAll('#modal-detail .tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.getElementById(`tab-${tab}`).style.display = 'block';

    if (tab === 'participants') loadParticipants();
    if (tab === 'prizes') loadPrizes();
    if (tab === 'winners') loadWinners();
}

// ─── PARTICIPANTS ───
function openAddParticipants() {
    document.getElementById('add-participants-form').style.display = 'block';
    document.getElementById('disqualify-form').style.display = 'none';
    document.getElementById('participants-text').value = '';
    document.getElementById('participants-text').focus();
}

function openDisqualifyForm() {
    document.getElementById('disqualify-form').style.display = 'block';
    document.getElementById('add-participants-form').style.display = 'none';
    document.getElementById('disqualify-text').value = '';
    document.getElementById('disqualify-text').focus();
}

async function loadParticipants() {
    const result = await api('get_participants', { event_id: currentEventId });
    const tbody = document.getElementById('participants-tbody');
    
    if (!result.success || !result.participants.length) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:var(--sp-8);">ยังไม่มีรายชื่อ</td></tr>';
        return;
    }

    tbody.innerHTML = result.participants.map((p, i) => `
        <tr style="${!p.is_eligible ? 'opacity:0.4;' : ''}">
            <td>${i + 1}</td>
            <td>${p.name} ${p.win_count > 0 ? '<span class="chip chip-gold" style="margin-left:4px">ได้รางวัล ' + p.win_count + '</span>' : ''}</td>
            <td>
                <span class="chip ${p.is_eligible ? 'chip-green' : 'chip-red'}">
                    ${p.is_eligible ? 'มีสิทธิ์' : 'ถูกตัดสิทธิ์'}
                </span>
            </td>
            <td>
                <div class="flex gap-2">
                    <button class="btn btn-sm ${p.is_eligible ? 'btn-danger' : 'btn-primary'}" 
                            onclick="toggleParticipant(${p.id})">
                        ${p.is_eligible ? 'ตัดสิทธิ์' : 'คืนสิทธิ์'}
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="deleteParticipant(${p.id})">ลบ</button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function addParticipants() {
    const names = document.getElementById('participants-text').value.trim();
    if (!names) return showToast('กรุณาใส่รายชื่อ', 'error');
    
    const result = await api('add_participants', { event_id: currentEventId, names });
    if (result.success) {
        showToast(result.message);
        document.getElementById('add-participants-form').style.display = 'none';
        loadParticipants();
        loadEvents();
    } else {
        showToast(result.message, 'error');
    }
}

async function batchDisqualify() {
    const names = document.getElementById('disqualify-text').value.trim();
    if (!names) return showToast('กรุณาใส่รายชื่อ', 'error');

    const result2 = await Swal.fire({
        title: 'ตัดสิทธิ์?',
        html: 'ยืนยันตัดสิทธิ์รายชื่อที่ระบุ?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ตัดสิทธิ์',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#E53935'
    });
    if (!result2.isConfirmed) return;

    const result = await api('batch_disqualify', { event_id: currentEventId, names });
    if (result.success) {
        showToast(result.message);
        document.getElementById('disqualify-form').style.display = 'none';
        loadParticipants();
    } else {
        showToast(result.message, 'error');
    }
}

async function toggleParticipant(id) {
    const result = await api('toggle_participant', { id, event_id: currentEventId });
    if (result.success) {
        loadParticipants();
    } else {
        showToast(result.message, 'error');
    }
}

async function deleteParticipant(id) {
    const result2 = await Swal.fire({
        title: 'ลบรายชื่อ?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#E53935'
    });
    if (!result2.isConfirmed) return;

    const result = await api('delete_participant', { id, event_id: currentEventId });
    if (result.success) {
        showToast(result.message);
        loadParticipants();
        loadEvents();
    } else {
        showToast(result.message, 'error');
    }
}

// ─── PRIZES ───
async function loadPrizes() {
    const result = await api('get_prizes', { event_id: currentEventId });
    const tbody = document.getElementById('prizes-tbody');

    if (!result.success || !result.prizes.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:var(--sp-8);">ยังไม่มีรางวัล</td></tr>';
        return;
    }

    tbody.innerHTML = result.prizes.map((p, i) => `
        <tr>
            <td>${i + 1}</td>
            <td><span class="text-gold">${p.name}</span></td>
            <td>${p.quantity}</td>
            <td>
                <span class="chip ${p.awarded_count >= p.quantity ? 'chip-green' : 'chip-teal'}">
                    ${p.awarded_count} / ${p.quantity}
                </span>
            </td>
            <td>
                <div class="flex gap-2">
                    <button class="btn btn-sm btn-outline" onclick="editPrize(${p.id}, '${p.name.replace(/'/g, "\\'")}', ${p.quantity})">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deletePrize(${p.id})">ลบ</button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function addPrize() {
    const name = document.getElementById('prize-name').value.trim();
    const quantity = document.getElementById('prize-quantity').value;

    if (!name) return showToast('กรุณาใส่ชื่อรางวัล', 'error');

    const result = await api('add_prize', { event_id: currentEventId, name, quantity });
    if (result.success) {
        showToast(result.message);
        document.getElementById('prize-name').value = '';
        document.getElementById('prize-quantity').value = '1';
        document.getElementById('add-prize-form').style.display = 'none';
        loadPrizes();
        loadEvents();
    } else {
        showToast(result.message, 'error');
    }
}

async function editPrize(id, currentName, currentQty) {
    const { value: formValues } = await Swal.fire({
        title: 'แก้ไขรางวัล',
        html: `
            <div style="text-align:left;">
                <label style="font-weight:600; display:block; margin-bottom:4px;">ชื่อรางวัล</label>
                <input id="swal-prize-name" class="swal2-input" value="${currentName}" style="margin-bottom:12px;">
                <label style="font-weight:600; display:block; margin-bottom:4px;">จำนวน</label>
                <input id="swal-prize-qty" type="number" class="swal2-input" value="${currentQty}" min="1">
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'บันทึก',
        cancelButtonText: 'ยกเลิก',
        preConfirm: () => {
            return {
                name: document.getElementById('swal-prize-name').value.trim(),
                quantity: document.getElementById('swal-prize-qty').value
            };
        }
    });

    if (!formValues) return;
    if (!formValues.name) return showToast('กรุณาใส่ชื่อรางวัล', 'error');

    const result = await api('update_prize', { 
        id, 
        event_id: currentEventId, 
        name: formValues.name, 
        quantity: formValues.quantity 
    });
    if (result.success) {
        showToast(result.message);
        loadPrizes();
        loadEvents();
    } else {
        showToast(result.message, 'error');
    }
}

async function deletePrize(id) {
    const result2 = await Swal.fire({
        title: 'ลบรางวัล?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#E53935'
    });
    if (!result2.isConfirmed) return;

    const result = await api('delete_prize', { id, event_id: currentEventId });
    if (result.success) {
        showToast(result.message);
        loadPrizes();
        loadEvents();
    } else {
        showToast(result.message, 'error');
    }
}

// ─── WINNERS ───
async function loadWinners() {
    const result = await api('get_winners', { event_id: currentEventId });
    const tbody = document.getElementById('winners-tbody');

    if (!result.success || !result.winners.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:var(--sp-8);">ยังไม่มีผู้ได้รางวัล</td></tr>';
        return;
    }

    tbody.innerHTML = result.winners.map((w, i) => `
        <tr style="${w.is_revoked ? 'opacity:0.4;' : ''}">
            <td>${i + 1}</td>
            <td>${w.participant_name}</td>
            <td><span class="chip chip-gold">${w.prize_name}</span></td>
            <td>
                <span class="chip ${w.is_revoked ? 'chip-red' : 'chip-green'}">
                    ${w.is_revoked ? 'ถูกถอน' : 'ได้รางวัล'}
                </span>
            </td>
            <td>
                <button class="btn btn-sm ${w.is_revoked ? 'btn-primary' : 'btn-danger'}" 
                        onclick="revokeWinner(${w.id})">
                    ${w.is_revoked ? 'คืนรางวัล' : 'ถอนรางวัล'}
                </button>
            </td>
        </tr>
    `).join('');
}

async function revokeWinner(id) {
    const result = await api('revoke_winner', { id, event_id: currentEventId });
    if (result.success) {
        showToast(result.message);
        loadWinners();
        loadEvents();
    } else {
        showToast(result.message, 'error');
    }
}
