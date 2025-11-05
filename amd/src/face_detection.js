define(['jquery'], function($) {
    'use strict';

    let video, canvas, ctx;
    let detector = null;

    return {
        init: function() {
            console.log('Starting...');
            
            video = document.getElementById('video-feed');
            canvas = document.getElementById('canvas-output');
            ctx = canvas ? canvas.getContext('2d') : null;

            if (!video || !canvas) {
                console.log('Elements not found');
                return;
            }

            // Setup button
            $('#start-camera-btn').click(() => this.start());
            
            // Load MediaPipe
            this.loadLib();
        },

        loadLib: function() {
            console.log('Loading MediaPipe...');
            
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.8/vision_bundle.js';
            s.onload = () => {
                console.log('MediaPipe loaded!');
                this.initDetector();
            };
            s.onerror = () => console.log('MediaPipe failed');
            document.head.appendChild(s);
        },

        initDetector: async function() {
            try {
                console.log('Init detector...');
                
                if (!window.FaceLandmarker || !window.FilesetResolver) {
                    console.log('MediaPipe objects not found');
                    return;
                }

                const vision = await window.FilesetResolver.forVisionTasks(
                    'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.8/wasm'
                );
                
                detector = await window.FaceLandmarker.createFromOptions(vision, {
                    baseOptions: {
                        modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task'
                    },
                    runningMode: 'VIDEO',
                    numFaces: 1
                });
                
                console.log('Detector ready!');
                
            } catch (e) {
                console.log('Init error:', e.message);
            }
        },

        start: async function() {
            try {
                console.log('Starting camera...');
                
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: true
                });
                
                video.srcObject = stream;
                video.onloadedmetadata = () => {
                    video.play();
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    
                    $('#camera-status').text('Camera Active').removeClass('alert-danger').addClass('alert-success');
                    $('#start-camera-btn').prop('disabled', true);
                    
                    console.log('Camera started!');
                    this.loop();
                };
                
            } catch (e) {
                console.log('Camera error:', e.message);
                alert('Camera error: ' + e.message);
            }
        },

        loop: function() {
            const self = this;
            
            function tick() {
                // Draw video
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                // Detect face
                if (detector) {
                    try {
                        const results = detector.detectForVideo(video, performance.now());
                        
                        if (results.faceLandmarks && results.faceLandmarks[0]) {
                            // Draw green dots
                            ctx.fillStyle = 'lime';
                            results.faceLandmarks[0].forEach(p => {
                                ctx.fillRect(p.x * canvas.width - 1, p.y * canvas.height - 1, 2, 2);
                            });
                            
                            // Show text
                            ctx.fillStyle = 'lime';
                            ctx.font = '20px Arial';
                            ctx.fillText('✓ Face detected', 10, 30);
                        } else {
                            ctx.fillStyle = 'red';
                            ctx.font = '20px Arial';
                            ctx.fillText('No face', 10, 30);
                        }
                    } catch (e) {
                        // Ignore errors
                    }
                } else {
                    ctx.fillStyle = 'yellow';
                    ctx.font = '16px Arial';
                    ctx.fillText('Loading detector...', 10, 30);
                }
                
                requestAnimationFrame(tick);
            }
            
            tick();
        }
    };
});

// Start
require(['local_myplugin/face_detection'], function(fd) {
    if (document.getElementById('video-feed')) {
        fd.init();
    }
});