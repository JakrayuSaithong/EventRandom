<?php
session_start();
require_once __DIR__ . '/config/functions.php';
if (!checkSession()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รีโมทสุ่ม — Random Picker</title>
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
            <li><a href="admin.php">Admin</a></li>
            <li><a href="remote.php" class="active">Remote</a></li>
            <li><a href="display.php" target="_blank"><i class="fa-solid fa-display"></i> Display</a></li>
        </ul>
    </div>
</nav>

<div class="container">
    <div class="page-header" style="text-align:center;">
        <h1><i class="fa-solid fa-gamepad"></i> รีโมทสุ่ม</h1>
        <p class="text-muted text-thai">ควบคุมการสุ่มรายชื่อจากที่นี่</p>
    </div>

    <div class="remote-control-panel">
        <!-- Step 1: Select Event -->
        <div class="card mb-6" id="step-event">
            <div class="card-header">
                <span class="card-title">① เลือกรายการ</span>
            </div>
            <select id="event-select" class="form-control" onchange="onEventChange()">
                <option value="">— เลือกรายการ —</option>
            </select>
        </div>

        <!-- Step 2: Select Prize -->
        <div class="card mb-6" id="step-prize" style="opacity:0.4; pointer-events:none;">
            <div class="card-header">
                <span class="card-title">② เลือกรางวัล</span>
            </div>
            <div id="prize-list" class="prize-selector">
                <!-- Loaded via JS -->
            </div>
        </div>

        <!-- Step 3: Draw Settings -->
        <div class="card mb-6" id="step-settings" style="opacity:0.4; pointer-events:none;">
            <div class="card-header">
                <span class="card-title">③ ตั้งค่าการสุ่ม</span>
            </div>

            <div class="form-group">
                <label class="form-label">จำนวนที่สุ่ม</label>
                <div class="number-stepper">
                    <button type="button" onclick="adjustDrawCount(-1)">−</button>
                    <span class="stepper-value" id="draw-count-value">1</span>
                    <button type="button" onclick="adjustDrawCount(1)">+</button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">โหมดสุ่ม</label>
                <div class="radio-group">
                    <button type="button" class="radio-btn active" 
                            onclick="setDrawMode('one_by_one', this)" id="mode-one">
                        ค่อยๆ สุ่ม
                    </button>
                    <button type="button" class="radio-btn" 
                            onclick="setDrawMode('all_at_once', this)" id="mode-all">
                        สุ่มพร้อมกัน
                    </button>
                </div>
            </div>
        </div>

        <!-- Draw Button -->
        <div style="text-align:center;">
            <button class="draw-button" id="btn-draw" onclick="executeDraw()" disabled>
                สุ่ม!
            </button>
            <p class="text-muted text-thai mt-4" id="draw-status-text">เลือกรายการและรางวัลเพื่อเริ่มสุ่ม</p>
        </div>

        <!-- Result Card -->
        <div id="result-card" class="card mt-6" style="display:none;">
            <div class="card-header">
                <span class="card-title text-gold"><i class="fa-solid fa-star"></i> ผลการสุ่ม</span>
            </div>
            <div id="result-content"></div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="toast-container"></div>

<script src="assets/js/remote.js"></script>
</body>
</html>
