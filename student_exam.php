<?php
require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
$context = context_system::instance();
require_capability('local/myplugin:takeexam', $context);

$examid = optional_param('examid', 0, PARAM_INT);
$examname = optional_param('examname', 'Sample Exam', PARAM_TEXT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/myplugin/student_exam.php', ['examid' => $examid]));
$PAGE->set_title(get_string('studentexam', 'local_myplugin'));
$PAGE->set_heading(get_string('studentexam', 'local_myplugin'));
$PAGE->set_pagelayout('standard');

// Add CSS
$PAGE->requires->css('/local/myplugin/styles.css');

// Add JavaScript for face detection
$PAGE->requires->js('/local/myplugin/amd/build/face_detection.min.js', true);

echo $OUTPUT->header();

// Check if session already exists
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
    <div class="exam-header">
        <h2><?php echo $examname; ?></h2>
        <div class="monitoring-status">
            <span class="status-label"><?php echo get_string('monitoringstatus', 'local_myplugin'); ?>:</span>
            <span class="status-indicator" id="monitoring-status">
                <span class="status-dot inactive"></span>
                <?php echo get_string('inactive', 'local_myplugin'); ?>
            </span>
        </div>
    </div>

    <div class="exam-content">
        <div class="main-section">
            <!-- Exam Content Area -->
            <div class="exam-questions">
                <div class="alert alert-info">
                    <strong><?php echo get_string('camerarequired', 'local_myplugin'); ?></strong>
                    <p>Please enable your camera to start the exam. Your actions will be monitored during the exam.</p>
                </div>
                
                <div id="exam-content-area" style="display: none;">
                    <h3>Exam Questions</h3>
                    <p><em>This is where the regular Moodle exam/quiz questions would be integrated.</em></p>
                    <p>The monitoring system will track your head movements during the exam.</p>
                    
                    <div class="sample-question">
                        <h4>Sample Question 1</h4>
                        <p>What is 2 + 2?</p>
                        <div class="question-options">
                            <label><input type="radio" name="q1" value="3"> 3</label><br>
                            <label><input type="radio" name="q1" value="4"> 4</label><br>
                            <label><input type="radio" name="q1" value="5"> 5</label><br>
                            <label><input type="radio" name="q1" value="6"> 6</label><br>
                            <label><input type="radio" name="q1" value="7"> 7</label><br>
                        </div>
                    </div>

                    <div class="sample-question">
                        <h4>Sample Question 2</h4>
                        <p>What is the capital of France?</p>
                        <div class="question-options">
                            <label><input type="radio" name="q2" value="London"> London</label><br>
                            <label><input type="radio" name="q2" value="Paris"> Paris</label><br>
                            <label><input type="radio" name="q2" value="Berlin"> Berlin</label><br>
                            <label><input type="radio" name="q2" value="Soto"> Soto</label><br>
                            <label><input type="radio" name="q2" value="Indomie"> Indomie</label><br>
                        </div>
                    </div>

                    <button class="btn btn-primary" id="end-exam-btn">End Exam</button>
                </div>
            </div>

            <!-- Cheating Alert Display -->
            <div class="cheating-alert" id="cheating-alert" style="display: none;">
                <div class="alert-message">
                    <strong><?php echo get_string('cheatingdetected', 'local_myplugin'); ?></strong>
                    <p id="alert-description">Please look at your screen.</p>
                </div>
            </div>
        </div>

        <div class="sidebar-section">
            <!-- Camera Feed -->
            <div class="camera-section">
                <h3>Camera Monitor</h3>
                <div class="camera-container">
                    <video id="video-feed" autoplay playsinline></video>
                    <canvas id="canvas-output" style="display: none;"></canvas>
                </div>
                <button class="btn btn-success" id="enable-camera-btn" style="display: none;">
                    <?php echo get_string('enablecamera', 'local_myplugin'); ?>
                </button>
                <button class="btn btn-success" id="start-camera-btn">
                    Start Camera Monitoring
                </button>
                <div class="camera-status alert alert-danger" id="camera-status">
                    <small>Camera: Not Active</small>
                </div>
            </div>

            <!-- Alert History -->
            <div class="alert-history">
                <h4>Alert Log <span class="badge badge-warning" id="alert-count">0</span></h4>
                <div id="alert-log">
                    <p class="no-alerts">No alerts yet</p>
                </div>
            </div>
            
            <!-- Alert Box for warnings -->
            <div id="alert-box" class="alert d-none" style="margin-top: 15px;"></div>
        </div>
    </div>
</div>

<!-- Hidden inputs for JavaScript -->
<input type="hidden" id="session-id" value="<?php echo $sessionid; ?>">
<input type="hidden" id="user-id" value="<?php echo $USER->id; ?>">

<script>
    // Pass data to JavaScript
    window.examSessionId = <?php echo $sessionid; ?>;
    window.userId = <?php echo $USER->id; ?>;
    window.wwwroot = '<?php echo $CFG->wwwroot; ?>';
    M.cfg.wwwroot = '<?php echo $CFG->wwwroot; ?>';
</script>

<?php
echo $OUTPUT->footer();
?>
