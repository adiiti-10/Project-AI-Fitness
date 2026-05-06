# 🏋️ AI Fitness Trainer – Pose Detection
## Full-Stack AI/ML Web Application

---

## 📁 Project Structure

```
ai-fitness-trainer/
├── index.php                  ← Main HTML/PHP entry point
├── css/
│   └── style.css              ← All styles (dark/light theme)
├── js/
│   └── app.js                 ← Frontend logic (auth, webcam, API calls, charts)
├── php/
│   ├── config.php             ← DB config + helper functions
│   ├── auth.php               ← Signup / Login / Logout API
│   └── workouts.php           ← Save / List / Stats API
├── python/
│   ├── pose_api.py            ← Flask REST API (MediaPipe pose detection)
│   └── requirements.txt       ← Python dependencies
└── sql/
    └── fitness_db.sql         ← MySQL schema + seed data
```

---

## ⚙️ Prerequisites

| Tool | Version | Download |
|------|---------|----------|
| XAMPP | 8.x | https://www.apachefriends.org |
| Python | 3.9–3.11 | https://www.python.org/downloads |
| Browser | Chrome / Edge | – |

> ⚠️ **Webcam** required for pose detection.

---

## 🚀 Setup Guide (Step by Step)

### Step 1 – Copy Project to XAMPP

```bash
# Windows
Copy the entire ai-fitness-trainer/ folder to:
C:\xampp\htdocs\ai-fitness-trainer\

# macOS
/Applications/XAMPP/htdocs/ai-fitness-trainer/

# Linux
/opt/lampp/htdocs/ai-fitness-trainer/
```

### Step 2 – Start XAMPP Services

1. Open **XAMPP Control Panel**
2. Click **Start** next to **Apache**
3. Click **Start** next to **MySQL**

### Step 3 – Create Database

1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click **New** in the left sidebar
3. Name it `ai_fitness_trainer` → click **Create**
4. Click the **SQL** tab
5. Open `sql/fitness_db.sql` with any text editor
6. Paste the entire contents into the SQL tab
7. Click **Go**

   ✅ Tables `users` and `workouts` will be created automatically.

### Step 4 – Install Python Dependencies

Open a **terminal** (Command Prompt / PowerShell / Terminal):

```bash
cd ai-fitness-trainer/python

# Create virtual environment (recommended)
python -m venv venv

# Activate (Windows)
venv\Scripts\activate

# Activate (macOS/Linux)
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt
```

> **Note:** Installing MediaPipe + OpenCV may take 2–5 minutes.

### Step 5 – Start the Python Flask API

```bash
# Make sure you're in the python/ directory with venv active
python pose_api.py
```

You should see:
```
🏋️  AI Fitness Trainer – Pose API starting on http://127.0.0.1:5000
```

> Keep this terminal window open while using the app.

### Step 6 – Open the Application

Visit: **http://localhost/ai-fitness-trainer/**

---

## 🔐 Default Login

| Field | Value |
|-------|-------|
| Email | demo@fitness.ai |
| Password | password |

Or create a new account via the Sign Up tab.

---

## 🎯 How to Use

1. **Login** with your credentials
2. **Dashboard** – View your stats and weekly progress chart
3. **Workout** page:
   - Select exercise: **Squat** or **Push-up**
   - Click **▶ START SESSION**
   - Allow camera access when prompted
   - Perform exercises in front of the camera
   - Watch **real-time skeleton overlay** and **posture feedback**
   - Click **⏹ STOP** when done
   - Click **💾 SAVE WORKOUT** to record the session
4. **History** – View all past workouts with reps, accuracy, duration, calories

---

## 🤖 How Pose Detection Works

```
Webcam Frame
     │
     ▼ (base64 JPEG, 6 fps)
JavaScript fetch() → Flask API (port 5000)
     │
     ▼
MediaPipe Pose Detection
  - 33 body landmarks extracted
  - Joint angles calculated (knee, elbow, hip)
     │
     ▼
Exercise Analysis
  - Squat: knee angle (85°–110° ideal), hip lean
  - Push-up: elbow angle (70°–100° ideal), body straightness
     │
     ▼
JSON response: { stage, angles, feedback, accuracy, annotated_frame }
     │
     ▼
JavaScript draws annotated frame on <canvas>
Displays feedback badges + angle chips
Counts reps via stage transitions (UP→DOWN→UP = 1 rep)
```

---

## 🛠 Configuration

### Change Database Credentials (`php/config.php`)
```php
define('DB_USER', 'root');   // your MySQL username
define('DB_PASS', '');       // your MySQL password
```

### Change Flask Port (`php/config.php` + `js/app.js`)
```php
// config.php
define('FLASK_URL', 'http://127.0.0.1:5001');
```
```javascript
// app.js line ~7
const FLASK_URL = 'http://127.0.0.1:5001';
```
```python
# pose_api.py last line
app.run(host='127.0.0.1', port=5001, ...)
```

### Adjust Detection FPS (`js/app.js`)
```javascript
const ANALYSE_FPS = 6;   // increase for smoother, decrease to reduce CPU load
```

---

## 🐞 Troubleshooting

| Problem | Solution |
|---------|----------|
| "Database connection failed" | Check XAMPP MySQL is running; verify credentials in `config.php` |
| "Camera access denied" | Allow camera in browser settings; use HTTPS or localhost |
| "Python API not connected" | Ensure `python pose_api.py` is running; check port 5000 is free |
| "No pose detected" | Ensure full body is visible; improve lighting |
| Slow performance | Lower `ANALYSE_FPS` in `app.js`; close other applications |
| MediaPipe install fails | Try `pip install mediapipe --extra-index-url https://pypi.org/simple/` |
| Python 3.12 issues | Use Python 3.10 or 3.11 (MediaPipe has limited 3.12 support) |

---

## 🌟 Features Summary

- ✅ Live webcam pose detection with skeleton overlay
- ✅ Squat & Push-up analysis with joint angle calculation
- ✅ Real-time correct/incorrect posture feedback
- ✅ Automatic rep counting via stage transitions
- ✅ Voice feedback (Web Speech API)
- ✅ User authentication (bcrypt passwords)
- ✅ Workout history with calories estimate
- ✅ Weekly progress chart (Chart.js)
- ✅ Dark / Light mode toggle
- ✅ Mobile responsive layout
- ✅ Accuracy scoring per exercise

---

## 📦 Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Backend | PHP 8.x (PDO) |
| Database | MySQL (via XAMPP) |
| AI/ML | Python, MediaPipe Pose, OpenCV |
| API | Flask + flask-cors |
| Charts | Chart.js 4 |
| Voice | Web Speech API |

---

*Built with ❤️ for learning AI/ML + Web Development integration*
