/**
 * app.js – AI Fitness Trainer Frontend Logic
 * Handles: Auth, Navigation, Webcam, Pose API, Rep Counting,
 *          Voice Feedback, Charts, Workout History
 */

// ── Config ────────────────────────────────────────────────────
const PHP_BASE   = 'php/';          // relative path to PHP files
const FLASK_URL  = 'http://127.0.0.1:5000';
const ANALYSE_FPS = 6;              // frames per second sent to Flask

// ── State ─────────────────────────────────────────────────────
const state = {
  user:        null,
  page:        'auth',
  exercise:    'squat',
  isRunning:   false,
  reps:        0,
  stage:       'up',         // 'up' | 'down' for rep counting
  accuracy:    100,
  sessionStart:null,
  voiceEnabled:true,
  darkMode:    true,
  lastSpoken:  '',
  speakTimer:  null,
  frameLoop:   null,
  sessionReps: [],           // accuracy per rep for averaging
  chart:       null,         // Chart.js instance
};

// ── DOM refs ──────────────────────────────────────────────────
const $  = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

// ── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadTheme();
  checkSession();
  bindAuthForm();
  bindNav();
  bindWorkoutControls();
  bindThemeToggle();
  bindVoiceToggle();
});

// ═══════════════════════════════════════════════════
//  AUTH
// ═══════════════════════════════════════════════════

async function checkSession() {
  try {
    const res  = await api('auth.php', { action: 'check' });
    if (res.loggedIn) {
      state.user = res.user;
      showPage('dashboard');
      loadDashboard();
    } else {
      showPage('auth');
    }
  } catch {
    showPage('auth');
  }
}

function bindAuthForm() {
  // Tab toggle
  $$('.auth-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      $$('.auth-tab').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const mode = btn.dataset.tab;
      $('#signup-fields').classList.toggle('hidden', mode !== 'signup');
    });
  });

  // Submit
  $('#auth-submit').addEventListener('click', handleAuthSubmit);
  $$('.auth-input').forEach(inp => {
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') handleAuthSubmit(); });
  });
}

async function handleAuthSubmit() {
  const activeTab = $('.auth-tab.active')?.dataset.tab || 'login';
  const email     = $('#auth-email').value.trim();
  const password  = $('#auth-password').value;
  const body      = { action: activeTab, email, password };

  if (activeTab === 'signup') {
    body.name = $('#auth-name').value.trim();
  }

  clearAuthError();
  $('#auth-submit').textContent = 'LOADING…';
  $('#auth-submit').disabled    = true;

  try {
    const res = await api('auth.php', body);
    state.user = { id: res.id, name: res.name, avatar: res.avatar || '💪' };
    showPage('dashboard');
    loadDashboard();
    toast('Welcome back, ' + res.name + '! 💪', 'success');
  } catch (err) {
    showAuthError(err.message || 'Authentication failed');
  } finally {
    $('#auth-submit').textContent = activeTab === 'login' ? 'LOGIN' : 'SIGN UP';
    $('#auth-submit').disabled    = false;
  }
}

async function logout() {
  await api('auth.php', { action: 'logout' }).catch(() => {});
  state.user = null;
  stopWorkout();
  showPage('auth');
  // Clear user display
  $('#user-name-nav').textContent = '';
}

// ═══════════════════════════════════════════════════
//  NAVIGATION
// ═══════════════════════════════════════════════════

function showPage(name) {
  $$('.page').forEach(p => p.classList.remove('active'));
  $(`#${name}-page`)?.classList.add('active');
  state.page = name;

  $$('.nav-link[data-page]').forEach(l => {
    l.classList.toggle('active', l.dataset.page === name);
  });

  if (name !== 'workout') stopWorkout();
  if (name === 'history') loadHistory();
}

function bindNav() {
  $$('.nav-link[data-page]').forEach(link => {
    link.addEventListener('click', () => {
      if (!state.user) return;
      showPage(link.dataset.page);
    });
  });
  document.addEventListener('click', e => {
    if (e.target.matches('[data-goto]')) {
      const target = e.target.dataset.goto;
      if (target === 'logout') { logout(); return; }
      showPage(target);
      if (target === 'workout') {
        const ex = e.target.dataset.exercise;
        if (ex) setExercise(ex);
      }
    }
  });
}

// ═══════════════════════════════════════════════════
//  DASHBOARD
// ═══════════════════════════════════════════════════

async function loadDashboard() {
  // Update nav username
  if (state.user) {
    $('#user-name-nav').textContent = state.user.name?.split(' ')[0] || '';
    $('#user-avatar-nav').textContent = state.user.avatar || '💪';
  }

  try {
    const res = await api('workouts.php', { action: 'stats' });
    const t   = res.totals;
    $('#stat-sessions').textContent = t.total_sessions || 0;
    $('#stat-reps').textContent     = t.total_reps     || 0;
    $('#stat-accuracy').textContent = (t.avg_accuracy  || 0) + '%';
    $('#stat-calories').textContent = Math.round(t.total_calories || 0);
    drawWeekChart(res.chart || []);
  } catch { /* no data yet */ }
}

function drawWeekChart(data) {
  const ctx = $('#week-chart');
  if (!ctx) return;

  // Build last-7-days labels
  const labels = [];
  const values = [];
  for (let i = 6; i >= 0; i--) {
    const d   = new Date();
    d.setDate(d.getDate() - i);
    const key = d.toISOString().slice(0, 10);
    labels.push(d.toLocaleDateString('en', { weekday: 'short' }));
    const found = data.find(r => r.day === key);
    values.push(found ? parseInt(found.reps) : 0);
  }

  if (state.chart) state.chart.destroy();
  state.chart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Reps',
        data:  values,
        backgroundColor: 'rgba(0,245,160,.25)',
        borderColor:     '#00f5a0',
        borderWidth:     2,
        borderRadius:    6,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#6b7280' } },
        y: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#6b7280' }, beginAtZero: true },
      }
    }
  });
}

// ═══════════════════════════════════════════════════
//  WORKOUT PAGE
// ═══════════════════════════════════════════════════

function bindWorkoutControls() {
  $('#btn-start').addEventListener('click', startWorkout);
  $('#btn-stop').addEventListener('click',  stopWorkout);
  $('#btn-save').addEventListener('click',  saveWorkout);
  $$('.ex-btn').forEach(btn => {
    btn.addEventListener('click', () => setExercise(btn.dataset.ex));
  });
}

function setExercise(ex) {
  state.exercise = ex;
  $$('.ex-btn').forEach(b => b.classList.toggle('active', b.dataset.ex === ex));
  $('#exercise-label').textContent = ex === 'squat' ? '🏋️ Squat' : '💪 Push-up';
  resetCounter();
}

async function startWorkout() {
  if (state.isRunning) return;

  // Request webcam
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 } });
    const video  = $('#webcam-video');
    video.srcObject = stream;
    await video.play();
    $('#camera-placeholder').classList.add('hidden');
    video.style.display = 'block';
    $('#pose-canvas').style.display = 'block';
  } catch (err) {
    toast('❌ Camera access denied: ' + err.message, 'error');
    return;
  }

  state.isRunning   = true;
  state.sessionStart = Date.now();
  state.reps        = 0;
  state.sessionReps = [];
  updateRepDisplay();

  $('#btn-start').classList.add('hidden');
  $('#btn-stop').classList.remove('hidden');
  $('#btn-save').classList.add('hidden');
  $('#recording-badge').classList.remove('hidden');

  startTimer();
  startFrameLoop();
}

function stopWorkout() {
  if (!state.isRunning) return;
  state.isRunning = false;

  clearInterval(state.timerInterval);
  clearTimeout(state.frameLoop);

  // Stop camera
  const video = $('#webcam-video');
  if (video?.srcObject) {
    video.srcObject.getTracks().forEach(t => t.stop());
    video.srcObject = null;
  }
  video.style.display = 'none';
  $('#pose-canvas').style.display = 'none';
  $('#camera-placeholder').classList.remove('hidden');
  $('#recording-badge').classList.add('hidden');

  $('#btn-start').classList.remove('hidden');
  $('#btn-stop').classList.add('hidden');
  if (state.reps > 0) $('#btn-save').classList.remove('hidden');

  clearFeedback();
}

async function saveWorkout() {
  if (!state.user) return;
  const avgAcc = state.sessionReps.length
    ? state.sessionReps.reduce((a,b) => a+b, 0) / state.sessionReps.length
    : state.accuracy;

  const elapsed = state.sessionStart ? Math.round((Date.now() - state.sessionStart) / 1000) : 0;

  try {
    const res = await api('workouts.php', {
      action:        'save',
      exercise_type: state.exercise,
      reps:          state.reps,
      accuracy:      Math.round(avgAcc * 10) / 10,
      duration_sec:  elapsed,
    });
    toast(`✅ Workout saved! ~${res.calories} kcal burned`, 'success');
    $('#btn-save').classList.add('hidden');
    loadDashboard();
    speak('Great workout! Session saved.');
  } catch (err) {
    toast('❌ Save failed: ' + err.message, 'error');
  }
}

// ── Frame loop ─────────────────────────────────────────────────
function startFrameLoop() {
  const interval = Math.round(1000 / ANALYSE_FPS);
  async function loop() {
    if (!state.isRunning) return;
    await sendFrame();
    state.frameLoop = setTimeout(loop, interval);
  }
  loop();
}

async function sendFrame() {
  const video  = $('#webcam-video');
  const canvas = $('#pose-canvas');
  if (!video || !canvas) return;

  // Draw webcam onto canvas for capture
  const offscreen = document.createElement('canvas');
  offscreen.width  = video.videoWidth  || 640;
  offscreen.height = video.videoHeight || 480;
  const ctx = offscreen.getContext('2d');
  ctx.drawImage(video, 0, 0);
  const b64 = offscreen.toDataURL('image/jpeg', 0.7);

  try {
    const res = await fetch(FLASK_URL + '/analyse', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ frame: b64, exercise: state.exercise }),
    });

    if (!res.ok) return;
    const data = await res.json();

    // Draw annotated frame
    if (data.frame) {
      const img = new Image();
      img.onload = () => {
        canvas.width  = video.videoWidth  || 640;
        canvas.height = video.videoHeight || 480;
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
      };
      img.src = data.frame;
    }

    if (data.result) processResult(data.result);

  } catch {
    // Flask not running — show warning once
    showFeedback(['⚠️ Python API not connected — run pose_api.py'], false);
  }
}

function processResult(result) {
  const { is_correct, feedback, stage, accuracy } = result;

  // Rep counting via stage transitions
  if (state.stage === 'up' && stage === 'down') {
    state.stage = 'down';
  } else if (state.stage === 'down' && stage === 'up') {
    state.stage = 'up';
    state.reps++;
    state.sessionReps.push(accuracy ?? 100);
    updateRepDisplay(true);
    if (state.voiceEnabled) speak(`${state.reps}`);
  } else {
    state.stage = stage || state.stage;
  }

  // Accuracy
  if (accuracy != null) {
    state.accuracy = accuracy;
    updateAccuracy(accuracy);
  }

  // Feedback messages
  showFeedback(feedback || [], is_correct);

  // Angle chips
  updateAngles(result);

  // Stage label
  $('#rep-stage').textContent = stage === 'down' ? '▼ DOWN' : '▲ UP';

  // Voice feedback (throttled)
  if (state.voiceEnabled && feedback?.length && !is_correct) {
    const msg = feedback[0].replace(/[^\w\s–—]/g, '');
    if (msg !== state.lastSpoken) {
      clearTimeout(state.speakTimer);
      state.speakTimer = setTimeout(() => {
        speak(msg);
        state.lastSpoken = msg;
      }, 2500);
    }
  }
}

function updateRepDisplay(bump = false) {
  const el = $('#rep-number');
  el.textContent = state.reps;
  if (bump) {
    el.classList.add('bump');
    setTimeout(() => el.classList.remove('bump'), 200);
  }
}

function updateAccuracy(val) {
  const pct = Math.round(val);
  $('#accuracy-value').textContent = pct + '%';
  $('#accuracy-bar').style.width   = pct + '%';
  $('#accuracy-bar').style.background = pct >= 80
    ? 'linear-gradient(90deg,#00f5a0,#00d4ff)'
    : pct >= 50 ? '#ffa94d' : '#ff4757';
}

function showFeedback(messages, correct) {
  const el = $('#feedback-messages');
  el.innerHTML = messages.map(m => `
    <div class="feedback-badge ${correct ? 'correct' : 'incorrect'}">${m}</div>
  `).join('');
}

function clearFeedback() {
  $('#feedback-messages').innerHTML = '';
  $('#angle-chips').innerHTML = '';
}

function updateAngles(result) {
  const chips = [];
  if (result.knee_angle  != null) chips.push(`Knee: ${result.knee_angle}°`);
  if (result.elbow_angle != null) chips.push(`Elbow: ${result.elbow_angle}°`);
  if (result.hip_angle   != null) chips.push(`Hip: ${result.hip_angle}°`);
  if (result.body_angle  != null) chips.push(`Body: ${result.body_angle}°`);
  $('#angle-chips').innerHTML = chips.map(c => `<span class="angle-chip">${c}</span>`).join('');
}

function resetCounter() {
  state.reps  = 0;
  state.stage = 'up';
  updateRepDisplay();
  $('#rep-stage').textContent = '▲ UP';
  clearFeedback();
}

// ── Timer ──────────────────────────────────────────────────────
function startTimer() {
  const start = Date.now();
  state.timerInterval = setInterval(() => {
    const elapsed = Date.now() - start;
    const s = Math.floor(elapsed / 1000) % 60;
    const m = Math.floor(elapsed / 60000);
    $('#timer-display').textContent =
      String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
  }, 500);
}

// ═══════════════════════════════════════════════════
//  HISTORY PAGE
// ═══════════════════════════════════════════════════

async function loadHistory() {
  const tbody = $('#history-tbody');
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">Loading…</td></tr>';

  try {
    const res  = await api('workouts.php', { action: 'list', limit: 50 });
    const rows = res.workouts || [];

    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No workouts yet — start training! 💪</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map(w => `
      <tr>
        <td>${w.formatted_date}</td>
        <td><span class="pill pill-${w.exercise_type}">${w.exercise_type === 'squat' ? '🏋️ Squat' : '💪 Push-up'}</span></td>
        <td style="font-family:var(--font-mono)">${w.reps}</td>
        <td>
          <div style="display:flex;align-items:center;gap:.5rem">
            <div class="accuracy-bar-bg" style="width:80px">
              <div class="accuracy-bar-fill" style="width:${w.accuracy}%"></div>
            </div>
            <span style="font-family:var(--font-mono);font-size:.85rem">${w.accuracy}%</span>
          </div>
        </td>
        <td style="font-family:var(--font-mono)">${w.duration_sec}s</td>
        <td style="font-family:var(--font-mono);color:var(--neon)">${w.calories} kcal</td>
      </tr>
    `).join('');
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="6" style="color:var(--danger);padding:1rem">${err.message}</td></tr>`;
  }
}

// ═══════════════════════════════════════════════════
//  VOICE FEEDBACK
// ═══════════════════════════════════════════════════

function speak(text) {
  if (!state.voiceEnabled || !window.speechSynthesis) return;
  window.speechSynthesis.cancel();
  const utt = new SpeechSynthesisUtterance(text);
  utt.rate   = 1.1;
  utt.pitch  = 1;
  utt.volume = 0.9;
  window.speechSynthesis.speak(utt);
}

function bindVoiceToggle() {
  const toggle = $('#voice-toggle');
  if (!toggle) return;
  toggle.addEventListener('change', () => {
    state.voiceEnabled = toggle.checked;
    if (state.voiceEnabled) speak('Voice feedback on');
  });
}

// ═══════════════════════════════════════════════════
//  THEME
// ═══════════════════════════════════════════════════

function bindThemeToggle() {
  $('#theme-btn')?.addEventListener('click', () => {
    state.darkMode = !state.darkMode;
    document.body.classList.toggle('light', !state.darkMode);
    $('#theme-btn').textContent = state.darkMode ? '🌙' : '☀️';
    localStorage.setItem('theme', state.darkMode ? 'dark' : 'light');
  });
}

function loadTheme() {
  const saved = localStorage.getItem('theme');
  state.darkMode = saved !== 'light';
  document.body.classList.toggle('light', !state.darkMode);
  if ($('#theme-btn')) $('#theme-btn').textContent = state.darkMode ? '🌙' : '☀️';
}

// ═══════════════════════════════════════════════════
//  UTILITIES
// ═══════════════════════════════════════════════════

async function api(endpoint, body) {
  const res = await fetch(PHP_BASE + endpoint, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(body),
  });
  const data = await res.json();
  if (!res.ok || data.error) throw new Error(data.error || 'Request failed');
  return data;
}

function toast(msg, type = 'success') {
  let el = $('#toast');
  if (!el) {
    el = document.createElement('div');
    el.id = 'toast';
    document.body.appendChild(el);
  }
  el.className = type;
  el.textContent = msg;
  el.style.display = 'flex';
  clearTimeout(el._timer);
  el._timer = setTimeout(() => { el.style.display = 'none'; }, 3500);
}

function showAuthError(msg) {
  let el = $('#auth-error');
  if (!el) {
    el = document.createElement('div');
    el.id = 'auth-error';
    el.className = 'auth-error';
    $('#auth-submit').insertAdjacentElement('afterend', el);
  }
  el.textContent = msg;
  el.style.display = 'block';
}

function clearAuthError() {
  const el = $('#auth-error');
  if (el) el.style.display = 'none';
}
