<?php
require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// 1. AUTHENTICATION & SETUP
require_login();
$context = context_system::instance();

// Handle Exam Submission
$action = optional_param('action', '', PARAM_ALPHA);
if ($action == 'submit' && confirm_sesskey()) {
    $activesession = $DB->get_record('local_myplugin_sessions', [
        'userid' => $USER->id,
        'status' => 'active'
    ]);
    
    if ($activesession) {
        $activesession->status = 'completed';
        $activesession->endtime = time();
        $DB->update_record('local_myplugin_sessions', $activesession);
    }
    
    // Redirect to index or a success page
    redirect(new moodle_url('/local/myplugin/index.php'), 'Exam submitted successfully. You may now close this window.', 3);
}

// Note: For testing, you might want to comment out the capability check if you haven't defined it yet
// require_capability('local/myplugin:takeexam', $context);

$examid = optional_param('examid', 0, PARAM_INT);
$examname = optional_param('examname', 'Sample Exam', PARAM_TEXT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/myplugin/student_exam.php', ['examid' => $examid]));
$PAGE->set_title($examname);
$PAGE->set_heading($examname);
$PAGE->set_pagelayout('standard');

// 2. CSS STYLING
// We add some inline CSS here to ensure the alert is visible without needing an external file
echo '<style>
    .exam-monitor-container { position: relative; }
    .cheating-overlay {
        display: none; /* Hidden by default */
        position: fixed;
        top: 20%;
        left: 50%;
        transform: translateX(-50%);
        width: 80%;
        max-width: 600px;
        z-index: 9999;
        background-color: #fff3cd;
        border: 2px solid #ffeeba;
        box-shadow: 0 0 20px rgba(0,0,0,0.5);
        padding: 20px;
        text-align: center;
        border-radius: 8px;
    }
    .cheating-overlay.active-alert {
        display: block !important;
        animation: shake 0.5s;
    }
    @keyframes shake {
        0% { transform: translate(-50%, 1px) rotate(0deg); }
        10% { transform: translate(-50%, -1px) rotate(-1deg); }
        20% { transform: translate(-50%, -3px) rotate(1deg); }
        30% { transform: translate(-50%, 3px) rotate(0deg); }
        40% { transform: translate(-50%, 1px) rotate(1deg); }
        50% { transform: translate(-50%, -1px) rotate(-1deg); }
        60% { transform: translate(-50%, -3px) rotate(0deg); }
        100% { transform: translate(-50%, 0) rotate(0deg); }
    }
    .alert-content h3 { color: #856404; margin-top: 0; }
    .alert-content p { font-size: 1.5em; font-weight: bold; color: #721c24; }
    .status-dot { height: 10px; width: 10px; background-color: #bbb; border-radius: 50%; display: inline-block; margin-right: 5px; }
    .status-dot.active { background-color: #28a745; }
    .video-wrapper { position: relative; width: 100%; border: 2px solid #333; background: #000; min-height: 240px; }
</style>';

echo $OUTPUT->header();

// 3. SESSION MANAGEMENT
global $DB, $USER;
$activesession = $DB->get_record('local_myplugin_sessions', [
    'userid' => $USER->id,
    'status' => 'active'
]);

if (!$activesession) {
    // Create new exam session
    $session = new stdClass();
    $session->userid = $USER->id;
    $session->examname = $examname;
    $session->starttime = time();
    $session->status = 'active';
    $session->timecreated = time();
    $session->timemodified = time();
    $sessionid = $DB->insert_record('local_myplugin_sessions', $session);
} else {
    $sessionid = $activesession->id;
}
?>

<div class="exam-monitor-container">
    
    <!-- CHEATING ALERT POPUP -->
    <div id="cheating-alert" class="cheating-overlay">
        <div class="alert-content">
            <h3>⚠️ WARNING DETECTED</h3>
            <p id="alert-description">LOOKING AWAY</p>
            <small>Your session ID: <?php echo $sessionid; ?> is being logged.</small>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: EXAM CONTENT -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h3><?php echo htmlspecialchars($examname); ?></h3>
                    
                    <div id="exam-content-area" style="display:none;">
                        <form method="post" action="student_exam.php">
                            <input type="hidden" name="examid" value="<?php echo $examid; ?>">
                            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                            <input type="hidden" name="action" value="submit">

                            <p>Answer the following questions:</p>
                            <hr>
                            <div class="mb-3">
                                <label><strong>Q1: What is the capital of France?</strong></label>
                                <select name="q1" class="form-control"><option>Select...</option><option>Paris</option><option>London</option></select>
                            </div>
                            <div class="mb-3">
                                <label><strong>Q2: Calculate 5 * 5</strong></label>
                                <input name="q2" type="text" class="form-control" placeholder="Answer">
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Exam</button>
                        </form>
                    </div>

                    <div id="start-prompt" class="alert alert-info">
                        <strong>Camera Required:</strong> Please enable your camera on the right to reveal the exam questions.
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: MONITORING -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    Proctoring Monitor
                    <span class="float-right" id="monitoring-status"><span class="status-dot"></span> Offline</span>
                </div>
                <div class="card-body text-center">
                    
                    <div class="video-wrapper mb-2">
                        <!-- Video Feed (Hidden, processed in background) -->
                        <video id="video-feed" autoplay playsinline style="display:none;"></video>
                        <!-- Canvas (Visible, draws video + augmented reality) -->
                        <canvas id="canvas-output" style="width: 100%; height: auto;"></canvas>
                    </div>

                    <button id="start-camera-btn" class="btn btn-success btn-block">
                        Start Camera & Begin Exam
                    </button>
                    
                    <div id="camera-status" class="mt-2 text-muted small">Waiting for user...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT LOGIC -->
<script>
    // Pass data to JavaScript
    window.examSessionId = <?php echo $sessionid; ?>;
    window.userId = <?php echo $USER->id; ?>;
    window.wwwroot = '<?php echo $CFG->wwwroot; ?>';
    window.sesskey = '<?php echo sesskey(); ?>';
</script>
<script type="module">
    import {
        FilesetResolver,
        FaceLandmarker
    } from "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.3";

    const video = document.getElementById("video-feed");
    const canvasElement = document.getElementById("canvas-output");
    const canvasCtx = canvasElement.getContext("2d");
    const startBtn = document.getElementById("start-camera-btn");
    const statusDiv = document.getElementById("camera-status");
    const monitorStatus = document.getElementById("monitoring-status");
    const cheatingAlert = document.getElementById("cheating-alert");
    const alertDesc = document.getElementById("alert-description");
    const examContent = document.getElementById("exam-content-area");
    const startPrompt = document.getElementById("start-prompt");

    let faceLandmarker = undefined;
    let lastVideoTime = -1;
    let results = undefined;
    
    // Sticky Alert Logic Variables
    let alertTimeoutHandle = null; 
    const ALERT_DURATION_MS = 3000; // Alert stays for 3 seconds even if you look back
    
    // Alert Cooldown
    let lastAlertTime = 0;
    const ALERT_COOLDOWN_MS = 5000; // Only log one alert every 5 seconds

    // 1. Initialize MediaPipe
    async function createFaceLandmarker() {
        statusDiv.innerText = "Loading AI Models...";
        try {
            const vision = await FilesetResolver.forVisionTasks(
                "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.3/wasm"
            );
            faceLandmarker = await FaceLandmarker.createFromOptions(vision, {
                baseOptions: {
                    modelAssetPath: `https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task`,
                    delegate: "GPU"
                },
                outputFaceBlendshapes: true,
                runningMode: "VIDEO",
                numFaces: 1
            });
            statusDiv.innerText = "System Ready.";
            startBtn.disabled = false;
        } catch (error) {
            statusDiv.innerText = "Error: " + error;
        }
    }
    createFaceLandmarker();

    // Ping Test
    function pingServer() {
        console.log('Pinging server...');
        fetch(window.wwwroot + '/local/myplugin/ajax/api.php?action=ping')
        .then(response => {
            console.log('Ping response status:', response.status);
            return response.text();
        })
        .then(text => console.log('Ping response body:', text))
        .catch(err => console.error('Ping failed:', err));
    }
    pingServer();

    // 2. Start Camera Button
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        startBtn.addEventListener("click", () => {
            if (!faceLandmarker) return;
            
            navigator.mediaDevices.getUserMedia({ video: true }).then((stream) => {
                video.srcObject = stream;
                video.addEventListener("loadeddata", predictWebcam);
                
                // UI Updates
                startBtn.style.display = "none";
                monitorStatus.innerHTML = '<span class="status-dot active"></span> Active';
                statusDiv.innerText = "Monitoring user head pose...";
                
                // Reveal Exam
                examContent.style.display = "block";
                startPrompt.style.display = "none";
            });
        });
    }

    // 3. Detection Loop
    async function predictWebcam() {
        // Resize logic
        if (video.videoWidth && video.videoHeight) {
             if(canvasElement.width !== video.videoWidth) {
                 canvasElement.width = video.videoWidth;
                 canvasElement.height = video.videoHeight;
             }
        }

        let startTimeMs = performance.now();

        if (lastVideoTime !== video.currentTime) {
            lastVideoTime = video.currentTime;
            results = faceLandmarker.detectForVideo(video, startTimeMs);
        }

        // Clear canvas
        canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);

        // Draw Mirrored Video
        canvasCtx.save();
        canvasCtx.scale(-1, 1);
        canvasCtx.translate(-canvasElement.width, 0);
        canvasCtx.drawImage(video, 0, 0, canvasElement.width, canvasElement.height);

        // Draw Landmarks
        if (results && results.faceLandmarks && results.faceLandmarks.length > 0) {
            const landmarks = results.faceLandmarks[0];
            
            // Draw visual dots
            canvasCtx.fillStyle = "#00FF00";
            for (const idx of [1, 234, 454]) { // Nose, Right Cheek, Left Cheek
                const pt = landmarks[idx];
                canvasCtx.beginPath();
                canvasCtx.arc(pt.x * canvasElement.width, pt.y * canvasElement.height, 4, 0, 2 * Math.PI);
                canvasCtx.fill();
            }
        }
        canvasCtx.restore();

        // CHECK FOR CHEATING (Not Mirrored Logic)
        if (results && results.faceLandmarks && results.faceLandmarks.length > 0) {
            const landmarks = results.faceLandmarks[0];
            const nose = landmarks[1].x;
            const leftCheek = landmarks[454].x; // User's actual left
            const rightCheek = landmarks[234].x; // User's actual right

            const distToRight = Math.abs(nose - rightCheek);
            const distToLeft = Math.abs(nose - leftCheek);

            let warningMsg = "";

            // 0.5 is a good sensitivity threshold. 
            // If Right Distance is < 50% of Left Distance, nose is very close to right cheek.
            if (distToRight < distToLeft * 0.5) {
                warningMsg = "LOOKING RIGHT >>";
            } else if (distToLeft < distToRight * 0.5) {
                warningMsg = "<< LOOKING LEFT";
            }

            if (warningMsg) {
                triggerAlert(warningMsg);
            }
        }

        window.requestAnimationFrame(predictWebcam);
    }

    // 4. Sticky Alert System
    function triggerAlert(message) {
        // Update text
        alertDesc.innerText = message;
        
        // Show Alert
        cheatingAlert.classList.add("active-alert");

        // Clear any existing timer to hide it
        if (alertTimeoutHandle) {
            clearTimeout(alertTimeoutHandle);
        }

        // Set a new timer to hide it after 3 seconds
        alertTimeoutHandle = setTimeout(() => {
            cheatingAlert.classList.remove("active-alert");
        }, ALERT_DURATION_MS);
        
        // Send to Server (with cooldown)
        const now = Date.now();
        if (now - lastAlertTime > ALERT_COOLDOWN_MS) {
            lastAlertTime = now;
            logAlertToServer(message);
        }
    }
    
    function logAlertToServer(message) {
        if (!window.examSessionId) {
            console.error('No session ID available');
            return;
        }
        // Capture Screenshot
        const screenshot = canvasElement.toDataURL('image/jpeg', 0.7);
        
        const formData = new FormData();
        formData.append('action', 'log_alert');
        formData.append('sesskey', window.sesskey);
        formData.append('sessionid', window.examSessionId);
        formData.append('userid', window.userId);
        formData.append('alerttype', 'head_pose');
        formData.append('description', message);
        formData.append('screenshot', screenshot);
        formData.append('severity', 1);

        fetch(window.wwwroot + '/local/myplugin/ajax/api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .then(data => {
            if (data.success) {
                console.log('Alert logged:', data);
            } else {
                console.error('Server returned error:', data.message);
            }
        })
        .catch(err => console.error('Error logging alert:', err));
    }
</script>

<?php
echo $OUTPUT->footer();
?>