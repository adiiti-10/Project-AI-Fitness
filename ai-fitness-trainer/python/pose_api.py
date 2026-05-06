"""
pose_api.py – Flask REST API for AI Pose Detection
====================================================
Receives a base64-encoded webcam frame, runs MediaPipe Pose,
calculates joint angles, classifies posture, and returns JSON.

Install dependencies:
    pip install flask flask-cors mediapipe opencv-python numpy

Run:
    python pose_api.py
    # Server starts on http://127.0.0.1:5000
"""

import base64
import math
import time
from io import BytesIO

import cv2
import mediapipe as mp
import numpy as np
from flask import Flask, jsonify, request
from flask_cors import CORS

app = Flask(__name__)
CORS(app)  # Allow cross-origin requests from the browser

# ── MediaPipe setup ────────────────────────────────────────────────────────
mp_pose    = mp.solutions.pose
mp_drawing = mp.solutions.drawing_utils
mp_styles  = mp.solutions.drawing_styles

pose_detector = mp_pose.Pose(
    static_image_mode=False,
    model_complexity=1,
    smooth_landmarks=True,
    min_detection_confidence=0.6,
    min_tracking_confidence=0.6,
)

# ── Geometry helpers ───────────────────────────────────────────────────────

def _lm(landmarks, idx):
    """Return (x, y, z, visibility) for a landmark index."""
    lm = landmarks[idx]
    return lm.x, lm.y, lm.z, lm.visibility


def calc_angle(a, b, c) -> float:
    """
    Calculate the angle (degrees) at point B formed by vectors BA and BC.
    Each point is a (x, y) tuple (normalised or pixel coords).
    """
    ba = (a[0] - b[0], a[1] - b[1])
    bc = (c[0] - b[0], c[1] - b[1])
    dot     = ba[0]*bc[0] + ba[1]*bc[1]
    mag_ba  = math.hypot(*ba)
    mag_bc  = math.hypot(*bc)
    if mag_ba == 0 or mag_bc == 0:
        return 0.0
    cos_ang = max(-1.0, min(1.0, dot / (mag_ba * mag_bc)))
    return math.degrees(math.acos(cos_ang))


# ── Posture analysers ──────────────────────────────────────────────────────

def analyse_squat(lm_list, h, w):
    """
    Returns dict with angles, feedback, stage ('up'|'down'), is_correct (bool).
    Key joints: hip, knee, ankle (both sides averaged).
    """
    L = mp_pose.PoseLandmark

    def xy(idx):
        x, y, _, v = lm_list[idx]
        return (x * w, y * h), v

    (l_hip, lhv), (l_knee, lkv), (l_ankle, lav) = xy(L.LEFT_HIP), xy(L.LEFT_KNEE), xy(L.LEFT_ANKLE)
    (r_hip, rhv), (r_knee, rkv), (r_ankle, rav) = xy(L.RIGHT_HIP), xy(L.RIGHT_KNEE), xy(L.RIGHT_ANKLE)
    (l_shoulder, _) = xy(L.LEFT_SHOULDER)
    (r_shoulder, _) = xy(L.RIGHT_SHOULDER)

    # Average knee angle
    l_knee_ang = calc_angle(l_hip, l_knee, l_ankle) if min(lhv,lkv,lav) > 0.5 else None
    r_knee_ang = calc_angle(r_hip, r_knee, r_ankle) if min(rhv,rkv,rav) > 0.5 else None

    valid_angles = [a for a in [l_knee_ang, r_knee_ang] if a is not None]
    knee_angle   = sum(valid_angles) / len(valid_angles) if valid_angles else 180

    # Hip angle (torso lean)
    mid_shoulder = ((l_shoulder[0]+r_shoulder[0])/2, (l_shoulder[1]+r_shoulder[1])/2)
    mid_hip      = ((l_hip[0]+r_hip[0])/2,           (l_hip[1]+r_hip[1])/2)
    mid_knee     = ((l_knee[0]+r_knee[0])/2,          (l_knee[1]+r_knee[1])/2)
    hip_angle    = calc_angle(mid_shoulder, mid_hip, mid_knee)

    # Stage detection
    stage = 'up'
    if knee_angle < 100:
        stage = 'down'

    # Feedback logic
    feedback   = []
    is_correct = True

    if knee_angle < 60:
        feedback.append("⚠️ Too deep — risk of knee injury")
        is_correct = False
    elif knee_angle > 160:
        feedback.append("⬇️ Go lower — bend your knees more")
        is_correct = False

    if hip_angle < 70:
        feedback.append("📐 Straighten your back — too much forward lean")
        is_correct = False

    if is_correct and stage == 'down':
        feedback.append("✅ Correct Posture — great squat depth!")
    elif is_correct:
        feedback.append("✅ Good position — descend into squat")

    return {
        "exercise":    "squat",
        "stage":       stage,
        "knee_angle":  round(knee_angle, 1),
        "hip_angle":   round(hip_angle, 1),
        "is_correct":  is_correct,
        "feedback":    feedback,
        "accuracy":    _accuracy_score(knee_angle, 85, 110) if stage == 'down' else 100,
    }


def analyse_pushup(lm_list, h, w):
    """
    Returns dict with angles, feedback, stage ('up'|'down'), is_correct (bool).
    Key joints: shoulder, elbow, wrist (both sides averaged).
    Also checks body straightness via hip angle.
    """
    L = mp_pose.PoseLandmark

    def xy(idx):
        x, y, _, v = lm_list[idx]
        return (x * w, y * h), v

    (l_sh, lsv), (l_el, lev), (l_wr, lwv) = xy(L.LEFT_SHOULDER),  xy(L.LEFT_ELBOW),  xy(L.LEFT_WRIST)
    (r_sh, rsv), (r_el, rev), (r_wr, rwv) = xy(L.RIGHT_SHOULDER), xy(L.RIGHT_ELBOW), xy(L.RIGHT_WRIST)
    (l_hip, _) = xy(L.LEFT_HIP)
    (r_hip, _) = xy(L.RIGHT_HIP)
    (l_ank, _) = xy(L.LEFT_ANKLE)
    (r_ank, _) = xy(L.RIGHT_ANKLE)

    l_el_ang = calc_angle(l_sh, l_el, l_wr) if min(lsv,lev,lwv) > 0.5 else None
    r_el_ang = calc_angle(r_sh, r_el, r_wr) if min(rsv,rev,rwv) > 0.5 else None

    valid = [a for a in [l_el_ang, r_el_ang] if a is not None]
    elbow_angle = sum(valid) / len(valid) if valid else 180

    # Body alignment (shoulder → hip → ankle should be ~180°)
    mid_sh  = ((l_sh[0]+r_sh[0])/2,   (l_sh[1]+r_sh[1])/2)
    mid_hip = ((l_hip[0]+r_hip[0])/2, (l_hip[1]+r_hip[1])/2)
    mid_ank = ((l_ank[0]+r_ank[0])/2, (l_ank[1]+r_ank[1])/2)
    body_angle = calc_angle(mid_sh, mid_hip, mid_ank)

    stage = 'up'
    if elbow_angle < 90:
        stage = 'down'

    feedback   = []
    is_correct = True

    if elbow_angle < 50:
        feedback.append("⚠️ Elbow angle too acute — risk of shoulder strain")
        is_correct = False
    elif elbow_angle > 160 and stage == 'up':
        feedback.append("⬇️ Lower your chest to the ground")
        is_correct = False

    if body_angle < 150:
        feedback.append("📐 Hips too high/low — keep body straight")
        is_correct = False

    if is_correct and stage == 'down':
        feedback.append("✅ Correct Posture — great push-up form!")
    elif is_correct:
        feedback.append("✅ Good position — push all the way down")

    return {
        "exercise":    "pushup",
        "stage":       stage,
        "elbow_angle": round(elbow_angle, 1),
        "body_angle":  round(body_angle, 1),
        "is_correct":  is_correct,
        "feedback":    feedback,
        "accuracy":    _accuracy_score(elbow_angle, 70, 100) if stage == 'down' else 100,
    }


def _accuracy_score(angle, ideal_min, ideal_max):
    """Return 0–100 accuracy score based on how close angle is to ideal range."""
    if ideal_min <= angle <= ideal_max:
        return 100.0
    deviation = min(abs(angle - ideal_min), abs(angle - ideal_max))
    return max(0.0, round(100 - deviation * 2, 1))


# ── Drawing helper ─────────────────────────────────────────────────────────

def draw_skeleton(image, results, is_correct: bool):
    """Draw pose landmarks and connections on the image."""
    landmark_style = mp_drawing.DrawingSpec(
        color=(0, 255, 100) if is_correct else (0, 80, 255),
        thickness=3, circle_radius=5
    )
    connection_style = mp_drawing.DrawingSpec(
        color=(0, 220, 80) if is_correct else (30, 30, 255),
        thickness=3
    )
    mp_drawing.draw_landmarks(
        image, results.pose_landmarks,
        mp_pose.POSE_CONNECTIONS,
        landmark_drawing_spec=landmark_style,
        connection_drawing_spec=connection_style,
    )
    return image


# ── Flask routes ───────────────────────────────────────────────────────────

@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok', 'model': 'MediaPipe Pose'})


@app.route('/analyse', methods=['POST'])
def analyse():
    """
    POST /analyse
    Body (JSON):
      {
        "frame":    "<base64-encoded JPEG>",
        "exercise": "squat" | "pushup"
      }
    Returns:
      {
        "pose_detected": bool,
        "frame": "<base64 annotated JPEG>",
        "result": { ... posture analysis ... }
      }
    """
    data     = request.get_json(force=True)
    frame_b64 = data.get('frame', '')
    exercise  = data.get('exercise', 'squat').lower()

    if not frame_b64:
        return jsonify({'error': 'No frame provided'}), 400

    # Decode base64 → numpy image
    try:
        raw   = base64.b64decode(frame_b64.split(',')[-1])  # strip data-url prefix
        nparr = np.frombuffer(raw, np.uint8)
        img   = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        if img is None:
            raise ValueError("cv2.imdecode returned None")
    except Exception as e:
        return jsonify({'error': f'Image decode error: {e}'}), 400

    h, w = img.shape[:2]
    rgb   = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)

    # Run pose detection
    results = pose_detector.process(rgb)

    if not results.pose_landmarks:
        _, buf = cv2.imencode('.jpg', img, [cv2.IMWRITE_JPEG_QUALITY, 75])
        return jsonify({
            'pose_detected': False,
            'frame':  'data:image/jpeg;base64,' + base64.b64encode(buf).decode(),
            'result': {'feedback': ['🔍 No pose detected — step into frame'], 'is_correct': False},
        })

    # Analyse posture
    lm_list = results.pose_landmarks.landmark
    if exercise == 'pushup':
        analysis = analyse_pushup(lm_list, h, w)
    else:
        analysis = analyse_squat(lm_list, h, w)

    # Draw skeleton on BGR image
    annotated = draw_skeleton(img.copy(), results, analysis['is_correct'])

    # Encode annotated frame back to base64
    _, buf = cv2.imencode('.jpg', annotated, [cv2.IMWRITE_JPEG_QUALITY, 75])
    frame_out = 'data:image/jpeg;base64,' + base64.b64encode(buf).decode()

    return jsonify({
        'pose_detected': True,
        'frame':         frame_out,
        'result':        analysis,
        'timestamp':     time.time(),
    })


if __name__ == '__main__':
    print("🏋️  AI Fitness Trainer – Pose API starting on http://127.0.0.1:5000")
    app.run(host='127.0.0.1', port=5000, debug=False, threaded=True)
