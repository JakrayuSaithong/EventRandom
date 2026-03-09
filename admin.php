<?php
session_start();
require_once __DIR__ . '/config/functions.php';
if (!checkSession()) {
    header('Location: index.php');
    exit;
}
$empCode = getEmpCode();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการรายการ — Random Picker</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="container">
        <a href="admin.php" class="navbar-brand">
            <div class="brand-icon"><i class="fa-solid fa-dice"></i></div>
            <span>Random Picker</span>
        </a>
        <ul class="navbar-nav">
            <li><a href="admin.php" class="active">Admin</a></li>
            <li><a href="remote.php">Remote</a></li>
            <li><a href="display.php" target="_blank"><i class="fa-solid fa-display"></i> Display</a></li>
        </ul>
    </div>
</nav>

<div class="container">
    <!-- Page Header -->
    <div class="page-header flex items-center justify-between">
        <div>
            <h1><i class="fa-solid fa-list-check"></i> จัดการรายการ</h1>
            <p class="text-muted text-thai">สร้างและจัดการกิจกรรมจับรางวัล</p>
        </div>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="fa-solid fa-plus"></i>
            สร้างรายการใหม่
        </button>
    </div>

    <!-- Event Cards Grid -->
    <div id="events-grid" class="grid grid-2">
        <!-- Loaded via JS -->
    </div>
    <div id="events-empty" class="empty-state" style="display:none;">
        <div class="empty-icon"><i class="fa-solid fa-bullseye"></i></div>
        <p>ยังไม่มีรายการ — สร้างรายการแรกของคุณ!</p>
        <button class="btn btn-gold" onclick="openCreateModal()">สร้างรายการ</button>
    </div>
</div>

<!-- ═══ CREATE/EDIT EVENT MODAL ═══ -->
<div id="modal-event" class="modal-overlay" onclick="if(event.target===this)closeModal('modal-event')">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-event-title">สร้างรายการใหม่</h3>
            <button class="modal-close" onclick="closeModal('modal-event')">✕</button>
        </div>
        <form id="form-event" onsubmit="saveEvent(event)">
            <input type="hidden" id="event-id" value="">
            <div class="form-group">
                <label class="form-label">ชื่อรายการ (Title)</label>
                <input type="text" id="event-title" class="form-control" placeholder="เช่น จับรางวัลงาน ASEFA Innovation Day 2026" required>
            </div>
            <div class="form-group">
                <label class="toggle-switch">
                    <input type="checkbox" id="event-allow-dup">
                    <span class="toggle-slider"></span>
                    <span class="text-thai">อนุญาตสุ่มซ้ำ (คนเดียวกันได้หลายรางวัล)</span>
                </label>
            </div>
            <div class="form-group">
                <label class="form-label">ระยะเวลาแสดงผู้โชคดี (วินาที)</label>
                <input type="number" id="event-display-seconds" class="form-control" value="8" min="3" max="60">
                <small style="color:var(--text-muted)">กำหนดความยาวของ Winner Card + Confetti Animation</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-event')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ EVENT DETAIL MODAL ═══ -->
<div id="modal-detail" class="modal-overlay" onclick="if(event.target===this)closeModal('modal-detail')">
    <div class="modal" style="max-width:800px;">
        <div class="modal-header">
            <h3 id="detail-title">รายละเอียด</h3>
            <button class="modal-close" onclick="closeModal('modal-detail')">✕</button>
        </div>

        <!-- Tabs -->
        <div class="tab-bar mb-6">
            <button class="tab-btn active" onclick="switchDetailTab('participants', this)"><i class="fa-solid fa-users"></i> รายชื่อ</button>
            <button class="tab-btn" onclick="switchDetailTab('prizes', this)"><i class="fa-solid fa-trophy"></i> รางวัล</button>
            <button class="tab-btn" onclick="switchDetailTab('winners', this)"><i class="fa-solid fa-star"></i> ผู้ได้รางวัล</button>
        </div>

        <!-- Participants Tab -->
        <div id="tab-participants" class="tab-content">
            <div class="flex items-center justify-between mb-4">
                <h4>รายชื่อผู้มีสิทธิ์</h4>
                <div class="flex gap-2">
                    <button class="btn btn-sm btn-danger" onclick="openDisqualifyForm()">
                        <i class="fa-solid fa-user-slash"></i> ตัดสิทธิ์
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="openAddParticipants()">
                        <i class="fa-solid fa-user-plus"></i> เพิ่ม
                    </button>
                </div>
            </div>
            <!-- Add Participants Form -->
            <div id="add-participants-form" style="display:none;" class="card mb-4">
                <div class="form-group" style="margin-bottom:var(--sp-3)">
                    <label class="form-label">ใส่รายชื่อ (คั่นด้วยบรรทัดใหม่ หรือ คอมม่า)</label>
                    <textarea id="participants-text" class="form-control" rows="4" placeholder="สมชาย&#10;สมหญิง&#10;สมศักดิ์"></textarea>
                </div>
                <div class="flex gap-2">
                    <button class="btn btn-sm btn-primary" onclick="addParticipants()">เพิ่มรายชื่อ</button>
                    <button class="btn btn-sm btn-outline" onclick="document.getElementById('add-participants-form').style.display='none'">ยกเลิก</button>
                </div>
            </div>
            <!-- Disqualify Form -->
            <div id="disqualify-form" style="display:none;" class="card mb-4">
                <div class="form-group" style="margin-bottom:var(--sp-3)">
                    <label class="form-label"><i class="fa-solid fa-user-slash"></i> ใส่รายชื่อที่ต้องการตัดสิทธิ์ (คั่นด้วยบรรทัดใหม่ หรือ คอมม่า)</label>
                    <textarea id="disqualify-text" class="form-control" rows="4" placeholder="ชื่อที่ต้องการตัดสิทธิ์..."></textarea>
                </div>
                <div class="flex gap-2">
                    <button class="btn btn-sm btn-danger" onclick="batchDisqualify()"><i class="fa-solid fa-ban"></i> ตัดสิทธิ์</button>
                    <button class="btn btn-sm btn-outline" onclick="document.getElementById('disqualify-form').style.display='none'">ยกเลิก</button>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ชื่อ</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="participants-tbody"></tbody>
                </table>
            </div>
        </div>

        <!-- Prizes Tab -->
        <div id="tab-prizes" class="tab-content" style="display:none;">
            <div class="flex items-center justify-between mb-4">
                <h4>รางวัล</h4>
                <button class="btn btn-sm btn-gold" onclick="document.getElementById('add-prize-form').style.display='block'">
                    <i class="fa-solid fa-trophy"></i> เพิ่มรางวัล
                </button>
            </div>
            <div id="add-prize-form" style="display:none;" class="card mb-4">
                <div class="grid grid-2 gap-3">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">ชื่อรางวัล</label>
                        <input type="text" id="prize-name" class="form-control" placeholder="เช่น MacBook Pro">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">จำนวน</label>
                        <input type="number" id="prize-quantity" class="form-control" value="1" min="1">
                    </div>
                </div>
                <div class="flex gap-2 mt-4">
                    <button class="btn btn-sm btn-gold" onclick="addPrize()">เพิ่มรางวัล</button>
                    <button class="btn btn-sm btn-outline" onclick="document.getElementById('add-prize-form').style.display='none'">ยกเลิก</button>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ชื่อรางวัล</th>
                            <th>จำนวน</th>
                            <th>แจกแล้ว</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="prizes-tbody"></tbody>
                </table>
            </div>
        </div>

        <!-- Winners Tab -->
        <div id="tab-winners" class="tab-content" style="display:none;">
            <h4 class="mb-4">ผู้ได้รางวัล</h4>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ชื่อ</th>
                            <th>รางวัล</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="winners-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="toast-container"></div>

<script src="assets/js/admin.js"></script>
</body>
</html>
