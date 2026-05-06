<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AI Fitness Trainer – Pose Detection</title>
  <meta name="description" content="Real-time AI-powered fitness trainer using pose detection" />

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

  <!-- Stylesheet -->
  <link rel="stylesheet" href="css/style.css" />

  <!-- Chart.js (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

<!-- ═══════════════════════════════════════════
     AUTH PAGE
════════════════════════════════════════════ -->
<section id="auth-page" class="page">
  <div class="auth-card">

    <div class="auth-logo">
      <span class="logo-icon">🏋️</span>
      <h1>AI FITNESS</h1>
      <p>Real-time pose detection trainer</p>
    </div>

    <!-- Tabs -->
    <div class="auth-tabs">
      <button class="auth-tab active" data-tab="login">LOGIN</button>
      <button class="auth-tab"        data-tab="signup">SIGN UP</button>
    </div>

    <!-- Name (signup only) -->
    <div class="form-group hidden" id="signup-fields">
      <label>Full Name</label>
      <input class="auth-input" id="auth-name" type="text"
             placeholder="Your name" autocomplete="name" />
    </div>

    <div class="form-group">
      <label>Email Address</label>
      <input class="auth-input" id="auth-email" type="email"
             placeholder="you@example.com" autocomplete="email" />
    </div>

    <div class="form-group">
      <label>Password</label>
      <input class="auth-input" id="auth-password" type="password"
             placeholder="Min. 6 characters" autocomplete="current-password" />
    </div>

    <button class="btn-primary" id="auth-submit">LOGIN</button>

    <!-- Demo hint -->
    <p style="text-align:center;margin-top:1rem;font-size:.8rem;color:var(--muted)">
      Demo: demo@fitness.ai / password
    </p>
  </div>
</section>


<!-- ═══════════════════════════════════════════
     NAVBAR (shared)
════════════════════════════════════════════ -->
<nav class="navbar hidden" id="main-nav">
  <a class="nav-brand">🏋️ FITAI</a>
  <div class="nav-links">
    <button class="nav-link active" data-page="dashboard">Dashboard</button>
    <button class="nav-link"        data-page="workout">Workout</button>
    <button class="nav-link"        data-page="history">History</button>
  </div>
  <div class="nav-actions">
    <button class="theme-toggle" id="theme-btn" title="Toggle theme">🌙</button>
    <div class="user-avatar" id="user-avatar-nav">💪</div>
    <span style="font-size:.9rem;color:var(--muted)" id="user-name-nav"></span>
    <button class="btn-logout" data-goto="logout">Logout</button>
  </div>
</nav>


<!-- ═══════════════════════════════════════════
     DASHBOARD PAGE
════════════════════════════════════════════ -->
<section id="dashboard-page" class="page" style="flex-direction:column">
  <div class="dashboard-content">

    <div class="dashboard-header">
      <h2 class="neon-text">YOUR PERFORMANCE</h2>
      <p>Track progress, crush goals, repeat.</p>
    </div>

    <!-- Stats cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Sessions</div>
        <div class="stat-value neon" id="stat-sessions">0</div>
        <div class="stat-icon">📅</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Reps</div>
        <div class="stat-value neon" id="stat-reps">0</div>
        <div class="stat-icon">🔄</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Avg Accuracy</div>
        <div class="stat-value neon" id="stat-accuracy">0%</div>
        <div class="stat-icon">🎯</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Calories Burned</div>
        <div class="stat-value neon" id="stat-calories">0</div>
        <div class="stat-icon">🔥</div>
      </div>
    </div>

    <!-- Exercise selection -->
    <div class="section-title">⚡ Start a Workout</div>
    <div class="exercise-grid">
      <div class="exercise-card" data-goto="workout" data-exercise="squat">
        <span class="ex-icon">🏋️</span>
        <h3>SQUAT</h3>
        <p>Quadriceps, glutes, hamstrings</p>
        <span class="ex-badge">Knee & Hip Tracking</span>
      </div>
      <div class="exercise-card" data-goto="workout" data-exercise="pushup">
        <span class="ex-icon">💪</span>
        <h3>PUSH-UP</h3>
        <p>Chest, shoulders, triceps</p>
        <span class="ex-badge">Elbow & Body Alignment</span>
      </div>
    </div>

    <!-- Weekly chart -->
    <div class="chart-section">
      <div class="section-title">📊 Reps This Week</div>
      <canvas id="week-chart" height="120"></canvas>
    </div>

  </div>
</section>


<!-- ═══════════════════════════════════════════
     WORKOUT PAGE
════════════════════════════════════════════ -->
<section id="workout-page" class="page" style="flex-direction:column">
  <div class="workout-layout">

    <!-- Camera panel -->
    <div class="camera-panel">
      <div class="camera-header">
        <h3 id="exercise-label">🏋️ Squat</h3>
        <div class="recording-badge hidden" id="recording-badge">
          <span class="rec-dot"></span> LIVE
        </div>
      </div>

      <div class="video-wrapper">
        <!-- Raw webcam feed (hidden behind canvas) -->
        <video id="webcam-video" autoplay muted playsinline
               style="display:none" aria-label="Webcam feed"></video>

        <!-- Annotated pose overlay canvas -->
        <canvas id="pose-canvas" style="display:none" aria-label="Pose overlay"></canvas>

        <!-- Placeholder before camera starts -->
        <div class="camera-placeholder" id="camera-placeholder">
          <span class="placeholder-icon">📷</span>
          <p>Camera is off. Press Start to begin.</p>
          <button class="btn-start-cam" onclick="document.getElementById('btn-start').click()">
            START CAMERA
          </button>
        </div>

        <!-- Feedback messages overlay -->
        <div class="posture-overlay" id="feedback-messages"></div>

        <!-- Angle chips overlay -->
        <div class="angle-display" id="angle-chips"></div>
      </div>
    </div><!-- /camera-panel -->

    <!-- Side panel -->
    <div class="side-panel">

      <!-- Exercise selector -->
      <div class="panel-card">
        <h4>EXERCISE</h4>
        <div class="ex-select-btns">
          <button class="ex-btn active" data-ex="squat">🏋️ Squat</button>
          <button class="ex-btn"        data-ex="pushup">💪 Push-up</button>
        </div>
      </div>

      <!-- Rep counter -->
      <div class="panel-card">
        <h4>REPETITIONS</h4>
        <div class="rep-display">
          <div class="rep-number" id="rep-number">0</div>
          <div class="rep-stage"  id="rep-stage">▲ UP</div>
        </div>
      </div>

      <!-- Accuracy -->
      <div class="panel-card">
        <h4>ACCURACY</h4>
        <div class="accuracy-value" id="accuracy-value">—</div>
        <div class="accuracy-bar-bg" style="margin-top:.6rem">
          <div class="accuracy-bar-fill" id="accuracy-bar" style="width:0%"></div>
        </div>
      </div>

      <!-- Timer -->
      <div class="panel-card">
        <h4>SESSION TIME</h4>
        <div class="timer-display" id="timer-display">00:00</div>
      </div>

      <!-- Voice toggle -->
      <div class="panel-card">
        <h4>VOICE FEEDBACK</h4>
        <div class="voice-row">
          <span style="font-size:.9rem">Audio cues</span>
          <label class="toggle-switch">
            <input type="checkbox" id="voice-toggle" checked />
            <span class="toggle-slider"></span>
          </label>
        </div>
      </div>

      <!-- Controls -->
      <div class="panel-card">
        <h4>CONTROLS</h4>
        <div class="workout-controls">
          <button class="btn-control btn-go"   id="btn-start">▶ START SESSION</button>
          <button class="btn-control btn-stop  hidden" id="btn-stop">⏹ STOP</button>
          <button class="btn-control btn-save  hidden" id="btn-save">💾 SAVE WORKOUT</button>
        </div>
      </div>

    </div><!-- /side-panel -->
  </div>
</section>


<!-- ═══════════════════════════════════════════
     HISTORY PAGE
════════════════════════════════════════════ -->
<section id="history-page" class="page" style="flex-direction:column">
  <div class="history-content">

    <div class="dashboard-header">
      <h2 class="neon-text">WORKOUT HISTORY</h2>
      <p>Your past sessions and performance data.</p>
    </div>

    <div class="history-table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Exercise</th>
            <th>Reps</th>
            <th>Accuracy</th>
            <th>Duration</th>
            <th>Calories</th>
          </tr>
        </thead>
        <tbody id="history-tbody">
          <tr>
            <td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">
              Loading history…
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</section>

<!-- Toast notification (created dynamically) -->

<!-- Main script -->
<script src="js/app.js"></script>

<script>
  // Show/hide nav after auth check
  // Nav is shown after successful login in app.js:showPage()
  const _origShowPage = window.showPage;

  // Patch showPage to show nav once logged in
  const _appShowPage = showPage;
  window.showPage = function(name) {
    _appShowPage(name);
    const nav = document.getElementById('main-nav');
    if (nav) {
      nav.classList.toggle('hidden', name === 'auth');
    }
    // Update active nav link
    document.querySelectorAll('.nav-link[data-page]').forEach(l => {
      l.classList.toggle('active', l.dataset.page === name);
    });
  };
</script>

</body>
</html>
