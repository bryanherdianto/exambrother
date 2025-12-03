# Exam Brother - Moodle Online Exam Monitoring Plugin

![picture 0](https://i.imgur.com/niKrY2a.jpeg)  

<p align="center">
 <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" />
 <img src="https://img.shields.io/badge/Moodle-F98012?style=for-the-badge&logo=moodle&logoColor=white" />
 <img src="https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" />
 <img src="https://img.shields.io/badge/MediaPipe-0097A7?style=for-the-badge&logo=google&logoColor=white" />
 <img src="https://img.shields.io/badge/PostgreSQL-4479A1?style=for-the-badge&logo=postgresql&logoColor=white" />
 <img src="https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white" />
 <img src="https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white" />
 <img src="https://img.shields.io/badge/Bootstrap-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white" />
</p>

## Overview

A comprehensive Moodle plugin for online exam monitoring with real-time AI-powered cheating detection, seamlessly integrated into Moodle Quiz attempts.

## Features

### 1. Seamless Quiz Integration

- **Auto-Injection**: Monitoring widget automatically appears on all quiz attempt pages
- **Smart Startup Blocker**: Screen remains blurred until student enables camera - prevents premature access to questions
- **Anti-Tamper Protection**: Robust checks prevent students from removing the overlay via browser DevTools
- **Session Management**: Automatically creates and tracks sessions per quiz attempt
- **Auto-Completion**: Sessions automatically marked as "completed" when quiz is submitted

### 2. AI-Powered Monitoring

- **Face Detection**: Real-time face tracking using MediaPipe Face Landmarker
- **Head Pose Analysis**: Detects when students look left, right, or away from screen
- **Missing Face Detection**: Alerts when no face is visible (with 60-frame buffer to reduce false positives)
- **Adjustable Sensitivity**: Lax detection thresholds (0.1) to minimize false alerts during normal movement
- **Alert Cooldown**: Half second cooldown between alerts to prevent spam

### 3. Tab Switch Detection & Auto-Submit

- **Tab Monitoring**: Tracks when students switch tabs or windows during the exam
- **Violation Counter**: Persistent count across quiz pages (stored in sessionStorage per attempt)
- **Smart Detection**: Ignores legitimate page navigation (Next/Previous buttons) using `beforeunload` and form submit event listeners
- **Three-Strike Policy**: Automatically submits the exam after 3 tab switches
- **Multi-Step Submission**: Handles Moodle's multi-page submission process (Attempt → Summary → Review)
- **Resume Protection**: If student clicks "Back" during auto-submit, the process resumes automatically
- **No Screenshots for Tab Switches**: Tab switch alerts are logged without capturing images to save bandwidth

### 4. Draggable Monitoring Widget

- **User-Friendly Design**: Compact widget with live camera feed in top-right corner
- **Drag-and-Drop**: Widget can be repositioned by dragging the header
- **Text Selection Fix**: Widget uses `user-select: none` to prevent interfering with text selection on exam questions
- **Status Indicators**: Visual indicators for "Offline" (gray dot) and "Active" (green dot)

### 5. Live Proctor Dashboard

- **Real-time Monitoring**: View all active exam sessions with auto-refresh
- **Student Overview**: See each student's exam status, alert count, and duration
- **Course Navigation**: Back button preserves `courseid` parameter for easy navigation
- **Flexible Layout**: Stats displayed in responsive grid with proper vertical centering

### 6. Post-Exam Reports

- **Detailed Timeline**: Complete history of all alerts with timestamps and alert types
- **Screenshot Gallery**: View all captured screenshots (excluding tab switches)
- **Modal Image Viewer**: Click any screenshot to view full-size in a centered, responsive modal with close button positioned relative to the image
- **View Screenshot Buttons**: Buttons in the alert timeline open the corresponding image from the gallery
- **Session Statistics**: Duration, alert counts, student info, and status displayed in vertically-centered stat cards
- **Historical Data**: Browse all past exam sessions from the dashboard overview

## Installation

1. Copy the plugin folder to `moodle/local/myplugin`
2. Log in to Moodle as an administrator
3. Navigate to Site Administration > Notifications
4. Click "Upgrade Moodle database now"
5. The plugin will be installed and database tables will be created

## Database Structure

The plugin creates three main tables:

### local_myplugin_sessions

Stores exam session information:

- Session ID
- User ID
- Quiz/Course Module ID
- Attempt ID (unique per quiz attempt)
- Exam name
- Start/end times
- Status (active/completed)

### local_myplugin_alerts

Stores cheating detection alerts:

- Alert ID
- Session ID
- Alert type (head_pose, missing_face, tab_switch)
- Description
- Severity level
- Timestamp

### local_myplugin_screenshots

Stores screenshots of cheating incidents:

- Screenshot ID
- Alert ID
- Session ID
- Image data (base64 encoded JPEG)
- Timestamp

**Note**: Tab switch alerts do NOT generate screenshots to conserve storage and bandwidth.

## Capabilities

The plugin defines three capabilities:

1. **local/myplugin:takeexam**
   - Assigned to: Students
   - Allows taking monitored exams

2. **local/myplugin:monitor**
   - Assigned to: Teachers, Editing Teachers, Managers
   - Allows real-time monitoring of exams

3. **local/myplugin:viewreports**
   - Assigned to: Teachers, Editing Teachers, Managers
   - Allows viewing exam reports and history

## Technical Implementation

### Frontend

- **JavaScript (ES6 Modules)**: Uses native `import` statements for MediaPipe integration
- **MediaPipe Face Landmarker**: AI-powered face detection using MediaPipe Tasks Vision v0.10.3
- **Canvas API**: Live video feed rendering with mirrored display and landmark visualization
- **Drag-and-Drop API**: Native HTML5 drag events for repositionable widget
- **Fetch API**: Real-time alert logging via custom AJAX endpoint
- **sessionStorage**: Persistent tab switch counter and auto-submit flags scoped per attempt ID
- **Event Listeners**: `visibilitychange`, `beforeunload`, `submit`, and form events for comprehensive monitoring
- **Anti-Tamper**: `setInterval` loop continuously checks and re-applies security measures

### Backend

- **lib.php Hook**: `local_myplugin_before_footer()` injects monitoring widget into quiz pages
- **Custom AJAX Handler**: `ajax/api.php` processes alert logging requests with sesskey validation
- **Database API**: Standard Moodle `$DB` operations for sessions, alerts, and screenshots
- **Capability Checks**: Permission validation for monitoring and viewing reports
- **Security**: Input validation, PARAM_* constants, and sesskey verification
- **Base64 Image Storage**: Screenshots stored as JPEG data URLs in the database

## File Structure

```txt
local/myplugin/
├── ajax/
│   └── api.php                        # Custom AJAX handler for alert logging
├── db/
│   ├── access.php                     # Capability definitions
│   ├── install.sql                    # Database schema (SQL format)
│   └── services.php                   # Web services definition
├── lang/
│   └── en/
│       └── local_myplugin.php         # English language strings
├── lib.php                            # Core plugin logic (quiz integration, monitoring widget)
├── index.php                          # Course-level landing page
├── student_exam.php                   # [DEPRECATED] Legacy standalone exam page
├── proctor_live.php                   # Live monitoring dashboard
├── proctor_dashboard.php              # Post-exam reports and session details
├── styles.css                         # Plugin styles (widget, dashboard, modal)
└── version.php                        # Plugin version info
```
