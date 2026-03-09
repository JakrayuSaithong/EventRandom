<?php
// Display page — receives commands from Remote automatically
// No login/event selection needed
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จับรางวัล — Random Picker</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Display-specific overrides */
        body { overflow: hidden; cursor: default; }
        body::before { display: none; }
        
        /* Particle background */
        .particles {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            pointer-events: none;
            z-index: 0;
        }
        .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            border-radius: 50%;
            opacity: 0.3;
            animation: particle-float linear infinite;
        }
        @keyframes particle-float {
            0% { transform: translateY(100vh) scale(0); opacity: 0; }
            10% { opacity: 0.3; }
            90% { opacity: 0.3; }
            100% { transform: translateY(-10vh) scale(1); opacity: 0; }
        }

        /* === DISPLAY FULLSCREEN — top-center layout === */
        .display-fullscreen {
            justify-content: flex-start !important;
            padding-top: 6vh !important;
        }

        /* --- Waiting State --- */
        .display-waiting {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            text-align: center;
            width: 100%;
            gap: var(--sp-6);
        }
        .display-waiting .waiting-icon {
            font-size: 4rem;
        }
        .display-waiting .display-title {
            font-size: 3.5rem;
        }

        /* --- Selected State --- */
        .display-selected-info {
            display: none;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: var(--sp-6);
            width: 100%;
            max-width: 700px;
            padding: 0 var(--sp-6);
        }
        .display-selected-info.active {
            display: flex;
        }
        .selected-event-title {
            font-size: 3.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #FFD54F, #FF5C8D);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .selected-prize-card {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-xl);
            padding: var(--sp-8) var(--sp-10);
            width: 100%;
        }
        .selected-prize-icon {
            font-size: 3.5rem;
            color: #FFD54F;
        }
        .selected-prize-name {
            font-size: 2.4rem;
            font-weight: 700;
            color: #FFD54F;
            margin-bottom: var(--sp-4);
        }
        .selected-remaining {
            font-size: 1.3rem;
            color: rgba(255,255,255,0.7);
        }
        .selected-remaining strong {
            color: #4DD0E1;
            font-size: 1.8rem;
        }
        .selected-waiting-text {
            color: rgba(255,255,255,0.5);
            font-size: 1.1rem;
            animation: pulse-text 2s ease-in-out infinite;
        }
        @keyframes pulse-text {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        /* --- Spinning State — bigger --- */
        #state-spinning .display-title {
            font-size: 3rem;
            margin-bottom: var(--sp-2);
        }
        #state-spinning .display-prize {
            font-size: 2rem;
            margin-bottom: var(--sp-6);
        }
    </style>
</head>
<body>

<!-- Particle Background -->
<div class="particles" id="particles"></div>

<!-- Confetti Canvas -->
<canvas id="confetti-canvas"></canvas>

<!-- Main Display -->
<div class="display-fullscreen" id="display-main">
    
    <!-- Waiting State (default) -->
    <div id="state-waiting" class="display-waiting">
        <div class="waiting-icon"><i class="fa-solid fa-dice fa-2x"></i></div>
        <h2 class="display-title" id="event-title-display">Random Picker</h2>
        <p id="waiting-text" style="color: rgba(255,255,255,0.6); font-size: 1.4rem;">กำลังรอคำสั่งจากรีโมท...</p>
    </div>

    <!-- Selected State (when Remote selects prize) -->
    <div id="state-selected" class="display-selected-info">
        <div class="selected-prize-icon"><i class="fa-solid fa-trophy"></i></div>
        <h2 class="selected-event-title" id="selected-event-title"></h2>
        <div class="selected-prize-card">
            <div class="selected-prize-name" id="selected-prize-name"></div>
            <div class="selected-remaining">
                เหลือ <strong id="selected-remaining-count">0</strong> / <span id="selected-total-count">0</span> รางวัล
            </div>
        </div>
        <div class="selected-waiting-text">
            <i class="fa-solid fa-satellite-dish"></i> รอกดสุ่มจากรีโมท...
        </div>
    </div>

    <!-- Spinning State -->
    <div id="state-spinning" style="display:none; text-align:center; width:100%; padding:0 var(--sp-8);">
        <h2 class="display-title" id="spinning-event-title"></h2>
        <div class="display-prize" id="spinning-prize-name"></div>
        <div class="slot-container" id="slot-container"></div>
    </div>

    <!-- Winner Reveal -->
    <div id="winner-overlay" class="winner-overlay">
        <div class="winner-card">
            <div class="winner-label"><i class="fa-solid fa-crown"></i> ผู้โชคดี</div>
            <div class="winner-names" id="winner-names-list"></div>
            <div class="winner-prize-name" id="winner-prize-label"></div>
        </div>
    </div>

</div>

<script src="assets/js/display.js"></script>
</body>
</html>
