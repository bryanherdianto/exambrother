<?php

defined('MOODLE_INTERNAL') || die();

function local_myplugin_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('local/myplugin:takeexam', $context)) {

        $url = new moodle_url('/local/myplugin/index.php', ['id' => $course->id]);

        $node = navigation_node::create(
            get_string('pluginname', 'local_myplugin'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'local_myplugin',
            new pix_icon('i/customfield', '')
        );

        $navigation->add_node($node);
    }
}

function local_myplugin_before_footer() {
    global $PAGE, $USER, $CFG, $DB;

    // 1. ONLY RUN ON QUIZ ATTEMPT PAGES
    if ($PAGE->pagetype !== 'mod-quiz-attempt') {
        return;
    }

    // 2. Get Quiz/Attempt Details
    $attemptid = required_param('attempt', PARAM_INT);
    // (Optional) Verify DB logic here to start a session if one doesn't exist
    // For simplicity, we assume we just need the IDs for the JS
    
    // 3. START OUTPUT BUFFERING (To capture HTML/JS as a string)
    ob_start();
    ?>

    <style>
        #proctor-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 320px;
            background: white;
            border: 1px solid #ccc;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            border-radius: 8px;
            z-index: 99999; /* On top of everything */
            font-family: sans-serif;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        #proctor-header {
            background: #333;
            color: white;
            padding: 10px;
            font-size: 14px;
            cursor: move; /* Implies dragging if you add drag logic */
            display: flex;
            justify-content: space-between;
        }
        #proctor-body {
            padding: 10px;
            text-align: center;
        }
        .video-container {
            position: relative;
            width: 100%;
            height: 200px;
            background: #000;
            margin-bottom: 10px;
        }
        #video-feed, #canvas-output {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        /* Cheating Overlay (Full Screen Alert) */
        .cheating-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 0, 0, 0.2);
            z-index: 100000;
            pointer-events: none; /* Let them click through, or remove to block */
            border: 10px solid red;
        }
        .cheating-message {
            position: absolute;
            top: 10%; left: 50%;
            transform: translateX(-50%);
            background: #fff;
            padding: 20px;
            border: 2px solid red;
            font-weight: bold;
            font-size: 20px;
            color: red;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .status-dot { height: 10px; width: 10px; background-color: #bbb; border-radius: 50%; display: inline-block; }
        .status-dot.active { background-color: #28a745; }
    </style>

    <div id="cheating-overlay" class="cheating-overlay">
        <div class="cheating-message">⚠️ <span id="alert-text">WARNING</span></div>
    </div>

    <div id="proctor-widget">
        <div id="proctor-header">
            <span>Exam Brother Monitor</span>
            <span id="monitoring-status"><span class="status-dot"></span> Offline</span>
        </div>
        <div id="proctor-body">
            <div class="video-container">
                <video id="video-feed" autoplay playsinline style="display:none;"></video>
                <canvas id="canvas-output"></canvas>
            </div>
            <button id="start-camera-btn" class="btn btn-sm btn-success btn-block">Start Camera</button>
            <div id="camera-status" class="small text-muted mt-1">Waiting for permission...</div>
        </div>
    </div>

    <script>
        // Pass PHP variables to JS
        window.sesskey = '<?php echo sesskey(); ?>';
        window.wwwroot = '<?php echo $CFG->wwwroot; ?>';
        window.userId = <?php echo $USER->id; ?>;
        // In this simplified version, we use a dummy session ID or fetch it via AJAX later
        // For now, let's just use the Attempt ID as the session reference
        window.examSessionId = <?php echo $attemptid; ?>; 
    </script>

    <script type="module">
        import { FilesetResolver, FaceLandmarker } from "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.3";

        const video = document.getElementById("video-feed");
        const canvasElement = document.getElementById("canvas-output");
        const canvasCtx = canvasElement.getContext("2d");
        const startBtn = document.getElementById("start-camera-btn");
        const statusDiv = document.getElementById("camera-status");
        const overlay = document.getElementById("cheating-overlay");
        const alertText = document.getElementById("alert-text");
        const monitorStatus = document.getElementById("monitoring-status");

        let faceLandmarker = undefined;
        let lastVideoTime = -1;
        let results = undefined;
        let tabSwitchCount = 0;
        const MAX_SWITCHES = 3;

        // 1. Initialize AI
        async function createFaceLandmarker() {
            statusDiv.innerText = "Loading AI...";
            try {
                const vision = await FilesetResolver.forVisionTasks("https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.3/wasm");
                faceLandmarker = await FaceLandmarker.createFromOptions(vision, {
                    baseOptions: {
                        modelAssetPath: `https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task`,
                        delegate: "GPU"
                    },
                    outputFaceBlendshapes: true,
                    runningMode: "VIDEO",
                    numFaces: 1
                });
                statusDiv.innerText = "Ready to start.";
                startBtn.disabled = false;
            } catch (error) {
                statusDiv.innerText = "AI Error.";
                console.error(error);
            }
        }
        createFaceLandmarker();

        // 2. Start Camera
        startBtn.addEventListener("click", () => {
            if (!faceLandmarker) return;
            navigator.mediaDevices.getUserMedia({ video: true }).then((stream) => {
                video.srcObject = stream;
                video.addEventListener("loadeddata", predictWebcam);
                startBtn.style.display = "none";
                monitorStatus.innerHTML = '<span class="status-dot active"></span> Active';
                statusDiv.innerText = "Monitoring active.";
            });
        });

        // 3. AI Prediction Loop
        async function predictWebcam() {
            // Resize canvas to match video
            if (canvasElement.width !== video.videoWidth) {
                canvasElement.width = video.videoWidth;
                canvasElement.height = video.videoHeight;
            }

            let startTimeMs = performance.now();
            if (lastVideoTime !== video.currentTime) {
                lastVideoTime = video.currentTime;
                results = faceLandmarker.detectForVideo(video, startTimeMs);
            }

            canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);
            
            // Draw Mirrored Video
            canvasCtx.save();
            canvasCtx.scale(-1, 1);
            canvasCtx.translate(-canvasElement.width, 0);
            canvasCtx.drawImage(video, 0, 0, canvasElement.width, canvasElement.height);
            
            // Draw Dots (Visual Feedback)
            if (results && results.faceLandmarks && results.faceLandmarks.length > 0) {
                 const landmarks = results.faceLandmarks[0];
                 canvasCtx.fillStyle = "#00FF00";
                 // Draw nose tip
                 const pt = landmarks[1]; 
                 canvasCtx.beginPath();
                 canvasCtx.arc(pt.x * canvasElement.width, pt.y * canvasElement.height, 3, 0, 2 * Math.PI);
                 canvasCtx.fill();
            }
            canvasCtx.restore();

            // CHEATING LOGIC
            if (results && results.faceLandmarks && results.faceLandmarks.length > 0) {
                const landmarks = results.faceLandmarks[0];
                const nose = landmarks[1].x;
                const leftCheek = landmarks[454].x;
                const rightCheek = landmarks[234].x;

                const distToRight = Math.abs(nose - rightCheek);
                const distToLeft = Math.abs(nose - leftCheek);

                if (distToRight < distToLeft * 0.5) triggerAlert("LOOKING RIGHT");
                else if (distToLeft < distToRight * 0.5) triggerAlert("LOOKING LEFT");
            } else if (results && (!results.faceLandmarks || results.faceLandmarks.length === 0)) {
                triggerAlert("NO FACE DETECTED");
            }

            window.requestAnimationFrame(predictWebcam);
        }

        // 4. Alert System
        let alertTimer = null;
        function triggerAlert(msg) {
            alertText.innerText = msg;
            overlay.style.display = "block";
            
            if (alertTimer) clearTimeout(alertTimer);
            alertTimer = setTimeout(() => {
                overlay.style.display = "none";
            }, 1000);
            
            // Note: Add your logAlertToServer() function here if you want to save to DB
        }

        // 5. TAB SWITCH & AUTO SUBMIT (Crucial Update)
        document.addEventListener("visibilitychange", () => {
            if (document.hidden) {
                tabSwitchCount++;
                const remaining = MAX_SWITCHES - tabSwitchCount;
                triggerAlert(`TAB SWITCH DETECTED! (${tabSwitchCount}/${MAX_SWITCHES})`);

                if (tabSwitchCount >= MAX_SWITCHES) {
                    statusDiv.style.color = 'red';
                    statusDiv.innerText = "VIOLATION! SUBMITTING...";
                    
                    // Stop AI
                    video.srcObject.getTracks().forEach(track => track.stop());
                    
                    // === MOODLE SPECIFIC SUBMISSION LOGIC ===
                    // Moodle's quiz form usually has id="responseform"
                    const moodleForm = document.getElementById('responseform');
                    
                    if (moodleForm) {
                        alert("Max tab violations reached. Your exam is being auto-submitted.");
                        // We must create a hidden input to tell Moodle this is a finish attempt
                        // Usually Moodle handles this via buttons, but submitting the form works
                        moodleForm.submit(); 
                    } else {
                        console.error("Could not find Moodle quiz form!");
                        window.location.reload(); // Fallback
                    }
                }
            }
        });
    </script>

    <?php
    // 4. Output the buffer
    $output = ob_get_clean();
    echo $output;
}