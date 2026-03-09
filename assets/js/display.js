/**
 * Display Page — JavaScript Logic
 * Global polling, handles 'select' + 'start' commands
 * Sound only plays when names are actually spinning (no pre-spin drum roll)
 * Supports configurable display_seconds from event settings
 */

const API = 'ajax/ajax_display.php';
let pollingInterval = null;
let participantNames = [];
let audioCtx = null;
let isAnimating = false;
let currentDisplaySeconds = 8; // default, overridden by event setting

// ─── INIT ───
document.addEventListener('DOMContentLoaded', () => {
    createParticles();
    startPolling();
});

// ═══════════════════════════════════════
//  PARTICLES BACKGROUND
// ═══════════════════════════════════════
function createParticles() {
    const container = document.getElementById('particles');
    const colors = ['#FFD54F', '#4DD0E1', '#FF5C8D', '#26C6DA', '#E5A800'];
    for (let i = 0; i < 30; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        particle.style.animationDuration = (8 + Math.random() * 12) + 's';
        particle.style.animationDelay = Math.random() * 10 + 's';
        particle.style.width = (2 + Math.random() * 3) + 'px';
        particle.style.height = particle.style.width;
        container.appendChild(particle);
    }
}

// ═══════════════════════════════════════
//  POLLING ENGINE
// ═══════════════════════════════════════
function startPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    pollingInterval = setInterval(pollForCommands, 500);
}

async function pollForCommands() {
    if (isAnimating) return;

    try {
        const res = await fetch(`${API}?action=poll`);
        const data = await res.json();

        if (data.success && data.has_command) {
            const cmdType = data.command.command;

            // Read display_seconds from event settings
            if (data.command.display_seconds) {
                currentDisplaySeconds = parseInt(data.command.display_seconds) || 8;
            }

            if (cmdType === 'select') {
                handleSelectCommand(data.command, data.remaining, data.total);
            } else if (cmdType === 'start') {
                isAnimating = true;
                if (data.participant_names && data.participant_names.length > 0) {
                    participantNames = data.participant_names;
                }
                await handleDrawCommand(data.command, data.winners);
            }
        }
    } catch(e) {}
}

// ═══════════════════════════════════════
//  HANDLE SELECT (prize preview on Display)
// ═══════════════════════════════════════
function handleSelectCommand(command, remaining, total) {
    const eventTitle = command.event_title || '';
    const prizeName = command.prize_name || '';

    document.getElementById('state-waiting').style.display = 'none';
    document.getElementById('state-spinning').style.display = 'none';

    const selectedEl = document.getElementById('state-selected');
    selectedEl.classList.remove('active');
    void selectedEl.offsetWidth; // force reflow for re-trigger animation
    selectedEl.classList.add('active');

    document.getElementById('selected-event-title').textContent = eventTitle;
    document.getElementById('selected-prize-name').textContent = prizeName;
    document.getElementById('selected-remaining-count').textContent = remaining ?? 0;
    document.getElementById('selected-total-count').textContent = total ?? 0;
    document.getElementById('event-title-display').textContent = eventTitle;

    // Small chime to signal update
    initAudio();
    playChime();
}

// ═══════════════════════════════════════
//  HANDLE DRAW (slot animation)
// ═══════════════════════════════════════
async function handleDrawCommand(command, winners) {
    const mode = command.draw_mode;
    const count = command.draw_count;
    const prizeName = command.prize_name || 'รางวัล';
    const eventTitle = command.event_title || '';

    // Hide all, show spinning
    document.getElementById('state-waiting').style.display = 'none';
    document.getElementById('state-selected').classList.remove('active');
    const spinningEl = document.getElementById('state-spinning');
    spinningEl.style.display = 'block';
    document.getElementById('spinning-event-title').textContent = eventTitle;
    document.getElementById('spinning-prize-name').innerHTML = '<i class="fa-solid fa-trophy"></i> ' + prizeName;

    // Init audio — sound will play only during name spinning
    initAudio();

    // NO drum roll — go straight to spinning
    if (mode === 'all_at_once') {
        await animateAllAtOnce(winners, count);
    } else {
        await animateOneByOne(winners);
    }

    // Play reveal fanfare
    playFanfare();

    // Show winner overlay
    showWinnerOverlay(winners, prizeName);

    // Mark command as complete
    try {
        const formData = new FormData();
        formData.append('action', 'complete');
        formData.append('command_id', command.id);
        await fetch(API, { method: 'POST', body: formData });
    } catch(e) {}

    // Wait configurable duration then reset
    setTimeout(() => {
        hideWinnerOverlay();
        document.getElementById('state-spinning').style.display = 'none';
        document.getElementById('state-selected').classList.remove('active');
        document.getElementById('state-waiting').style.display = 'flex';
        document.getElementById('waiting-text').textContent = 'กำลังรอคำสั่งจากรีโมท...';
        isAnimating = false;
    }, currentDisplaySeconds * 1000);
}

// ═══════════════════════════════════════
//  SLOT MACHINE ANIMATION
// ═══════════════════════════════════════
function createSlotBoxes(count) {
    const container = document.getElementById('slot-container');
    container.innerHTML = '';
    const boxes = [];
    for (let i = 0; i < count; i++) {
        const box = document.createElement('div');
        box.className = 'slot-box';
        box.innerHTML = '<div class="slot-reel">?</div>';
        container.appendChild(box);
        boxes.push(box);
    }
    return boxes;
}

async function animateAllAtOnce(winners, count) {
    const boxes = createSlotBoxes(count);
    const spinPromises = boxes.map((box, i) => {
        return spinSlot(box, winners[i]?.participant_name || '???', 3500 + Math.random() * 1000);
    });
    await Promise.all(spinPromises);
}

async function animateOneByOne(winners) {
    const boxes = createSlotBoxes(winners.length);
    for (let i = 0; i < winners.length; i++) {
        await spinSlot(boxes[i], winners[i]?.participant_name || '???', 3500);
        if (i < winners.length - 1) {
            playRevealHit();
            await delay(800);
        }
    }
}

function spinSlot(box, winnerName, duration) {
    return new Promise((resolve) => {
        const reel = box.querySelector('.slot-reel');
        box.classList.add('spinning');
        
        const names = participantNames.length > 0 ? [...participantNames] : ['???'];
        const startTime = Date.now();

        function tick() {
            const elapsed = Date.now() - startTime;
            const progress = elapsed / duration;
            reel.textContent = names[Math.floor(Math.random() * names.length)];

            // Sound plays WITH each name change
            playRouletteTick(progress);

            if (elapsed < duration) {
                const interval = 40 + (progress * progress * progress * 250);
                setTimeout(tick, interval);
            } else {
                reel.textContent = winnerName;
                reel.classList.add('stopped');
                box.classList.remove('spinning');
                box.classList.add('revealed');
                playRevealHit();
                resolve();
            }
        }
        tick();
    });
}

function delay(ms) {
    return new Promise(r => setTimeout(r, ms));
}

// ═══════════════════════════════════════
//  SOUND DESIGN
// ═══════════════════════════════════════

function initAudio() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (audioCtx.state === 'suspended') audioCtx.resume();
}

// ── Small chime when prize is selected ──
function playChime() {
    if (!audioCtx) return;
    try {
        const t = audioCtx.currentTime;
        [523, 659, 784].forEach((freq, i) => {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0, t + i * 0.12);
            gain.gain.linearRampToValueAtTime(0.1, t + i * 0.12 + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.001, t + i * 0.12 + 0.3);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(t + i * 0.12);
            osc.stop(t + i * 0.12 + 0.3);
        });
    } catch(e) {}
}

// ── Roulette tick — layered sound that builds tension ──
function playRouletteTick(progress) {
    if (!audioCtx) return;
    try {
        const t = audioCtx.currentTime;

        // Layer 1: Main tick — pitch rises with progress
        const osc1 = audioCtx.createOscillator();
        const gain1 = audioCtx.createGain();
        const baseFreq = 400 + progress * 1200;
        osc1.type = 'triangle';
        osc1.frequency.setValueAtTime(baseFreq, t);
        osc1.frequency.exponentialRampToValueAtTime(baseFreq * 0.6, t + 0.04);
        gain1.gain.setValueAtTime(0.06 + progress * 0.06, t);
        gain1.gain.exponentialRampToValueAtTime(0.001, t + 0.04);
        osc1.connect(gain1);
        gain1.connect(audioCtx.destination);
        osc1.start(t);
        osc1.stop(t + 0.04);

        // Layer 2: High harmonic shimmer (kicks in at 30%)
        if (progress > 0.3) {
            const osc2 = audioCtx.createOscillator();
            const gain2 = audioCtx.createGain();
            osc2.type = 'sine';
            osc2.frequency.value = baseFreq * 2;
            gain2.gain.setValueAtTime(0.02 + (progress - 0.3) * 0.06, t);
            gain2.gain.exponentialRampToValueAtTime(0.001, t + 0.03);
            osc2.connect(gain2);
            gain2.connect(audioCtx.destination);
            osc2.start(t);
            osc2.stop(t + 0.03);
        }

        // Layer 3: Sub-bass thump (kicks in at 60%)
        if (progress > 0.6) {
            const osc3 = audioCtx.createOscillator();
            const gain3 = audioCtx.createGain();
            osc3.type = 'sine';
            osc3.frequency.setValueAtTime(80, t);
            osc3.frequency.exponentialRampToValueAtTime(40, t + 0.05);
            gain3.gain.setValueAtTime(0.08 * (progress - 0.6) / 0.4, t);
            gain3.gain.exponentialRampToValueAtTime(0.001, t + 0.05);
            osc3.connect(gain3);
            gain3.connect(audioCtx.destination);
            osc3.start(t);
            osc3.stop(t + 0.05);
        }
    } catch(e) {}
}

// ── Reveal hit — dramatic impact when name locks in ──
function playRevealHit() {
    if (!audioCtx) return;
    try {
        const t = audioCtx.currentTime;

        // Impact noise burst
        const bufferSize = audioCtx.sampleRate * 0.08;
        const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
        const data = buffer.getChannelData(0);
        for (let s = 0; s < bufferSize; s++) {
            data[s] = (Math.random() * 2 - 1) * Math.pow(1 - s / bufferSize, 3);
        }
        const noise = audioCtx.createBufferSource();
        noise.buffer = buffer;
        const noiseGain = audioCtx.createGain();
        noiseGain.gain.setValueAtTime(0.12, t);
        noiseGain.gain.exponentialRampToValueAtTime(0.001, t + 0.08);
        noise.connect(noiseGain);
        noiseGain.connect(audioCtx.destination);
        noise.start(t);
        noise.stop(t + 0.08);

        // Low thump
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(200, t);
        osc.frequency.exponentialRampToValueAtTime(60, t + 0.1);
        gain.gain.setValueAtTime(0.15, t);
        gain.gain.exponentialRampToValueAtTime(0.001, t + 0.15);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start(t);
        osc.stop(t + 0.15);

        // Bright ping
        const ping = audioCtx.createOscillator();
        const pingGain = audioCtx.createGain();
        ping.type = 'sine';
        ping.frequency.value = 1200;
        pingGain.gain.setValueAtTime(0.08, t);
        pingGain.gain.exponentialRampToValueAtTime(0.001, t + 0.2);
        ping.connect(pingGain);
        pingGain.connect(audioCtx.destination);
        ping.start(t);
        ping.stop(t + 0.2);
    } catch(e) {}
}

// ── Victory fanfare — triumphant ascending chord ──
function playFanfare() {
    if (!audioCtx) return;
    try {
        const t = audioCtx.currentTime;
        const notes = [523, 659, 784, 1047];
        const spacing = 0.1;

        notes.forEach((freq, i) => {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0, t + i * spacing);
            gain.gain.linearRampToValueAtTime(0.12, t + i * spacing + 0.02);
            gain.gain.setValueAtTime(0.12, t + i * spacing + 0.15);
            gain.gain.exponentialRampToValueAtTime(0.001, t + i * spacing + 0.6);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(t + i * spacing);
            osc.stop(t + i * spacing + 0.6);

            // Shimmer octave
            const osc2 = audioCtx.createOscillator();
            const gain2 = audioCtx.createGain();
            osc2.type = 'sine';
            osc2.frequency.value = freq * 2;
            gain2.gain.setValueAtTime(0, t + i * spacing);
            gain2.gain.linearRampToValueAtTime(0.04, t + i * spacing + 0.02);
            gain2.gain.exponentialRampToValueAtTime(0.001, t + i * spacing + 0.5);
            osc2.connect(gain2);
            gain2.connect(audioCtx.destination);
            osc2.start(t + i * spacing);
            osc2.stop(t + i * spacing + 0.5);
        });

        // Final sustain chord
        const chordTime = t + notes.length * spacing + 0.1;
        notes.forEach(freq => {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.08, chordTime);
            gain.gain.setValueAtTime(0.08, chordTime + 0.3);
            gain.gain.exponentialRampToValueAtTime(0.001, chordTime + 1.2);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(chordTime);
            osc.stop(chordTime + 1.2);
        });
    } catch(e) {}
}

// ═══════════════════════════════════════
//  WINNER OVERLAY
// ═══════════════════════════════════════
function showWinnerOverlay(winners, prizeName) {
    const overlay = document.getElementById('winner-overlay');
    const namesList = document.getElementById('winner-names-list');
    const prizeLabel = document.getElementById('winner-prize-label');

    namesList.innerHTML = winners.map(w => 
        `<div class="winner-name">${w.participant_name}</div>`
    ).join('');
    
    prizeLabel.innerHTML = '<i class="fa-solid fa-trophy"></i> ' + prizeName;
    overlay.classList.add('active');
    launchConfetti();
}

function hideWinnerOverlay() {
    document.getElementById('winner-overlay').classList.remove('active');
}

// ═══════════════════════════════════════
//  CONFETTI EFFECT
// ═══════════════════════════════════════
function launchConfetti() {
    const canvas = document.getElementById('confetti-canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const colors = ['#FFD54F', '#E91E63', '#4DD0E1', '#FF5C8D', '#E5A800', '#43A047', '#fff'];
    const pieces = [];
    for (let i = 0; i < 150; i++) {
        pieces.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height - canvas.height,
            w: 8 + Math.random() * 6,
            h: 4 + Math.random() * 4,
            color: colors[Math.floor(Math.random() * colors.length)],
            rotation: Math.random() * 360,
            rotSpeed: (Math.random() - 0.5) * 10,
            vx: (Math.random() - 0.5) * 4,
            vy: 2 + Math.random() * 4,
            opacity: 1
        });
    }

    // Confetti duration = display seconds * 60 fps
    const maxFrames = currentDisplaySeconds * 60;
    let frame = 0;

    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        pieces.forEach(p => {
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.05;
            p.rotation += p.rotSpeed;
            // Fade out in last 60 frames
            if (frame > maxFrames - 60) p.opacity = Math.max(0, (maxFrames - frame) / 60);

            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rotation * Math.PI / 180);
            ctx.globalAlpha = p.opacity;
            ctx.fillStyle = p.color;
            ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
            ctx.restore();
        });
        frame++;
        if (frame < maxFrames) requestAnimationFrame(animate);
        else ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
    animate();
}

window.addEventListener('resize', () => {
    const c = document.getElementById('confetti-canvas');
    c.width = window.innerWidth;
    c.height = window.innerHeight;
});
