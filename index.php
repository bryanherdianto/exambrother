<?php
require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// 1. Initialize Globals immediately so we can use database
global $DB, $USER, $PAGE, $OUTPUT;

// 2. Get the Course ID parameter
$courseid = required_param('id', PARAM_INT);

// 3. Get the Course Record (Throw error if not found)
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// 4. Set the Context to COURSE (This fixes your permission issues!)
$context = context_course::instance($courseid);

// 5. Require Login for this specific course
require_login($course);

// 6. Setup the Page
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/myplugin/index.php', ['id' => $courseid]));
$PAGE->set_title(get_string('exambrother', 'local_myplugin'));
$PAGE->set_heading($course->fullname); // Good practice to show Course Name as heading
$PAGE->set_pagelayout('course'); // Use 'course' layout to show course navigation blocks

// Add CSS
$PAGE->requires->css('/local/myplugin/styles.css');

echo $OUTPUT->header();

// Check user capabilities (Now checks against the specific COURSE permissions)
$canTakeExam = has_capability('local/myplugin:takeexam', $context);
$canMonitor = has_capability('local/myplugin:monitor', $context);
$canViewReports = has_capability('local/myplugin:viewreports', $context);

?>

<div class="exam-monitor-container">
    <div class="welcome-section">
        <h2><?php echo get_string('welcome', 'local_myplugin'); ?></h2>
        <p>Welcome to the Exam Brother system for course: <strong><?php echo s($course->fullname); ?></strong></p>
    </div>

    <div class="features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 30px;">
        
        <?php if ($canTakeExam): ?>
        <div class="feature-card" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3>Take Exam</h3>
            <p>Start a monitored exam session with camera tracking and cheating detection.</p>
            <a href="<?php echo new moodle_url('/local/myplugin/student_exam.php', ['examname' => 'Sample Exam', 'courseid' => $courseid]); ?>" 
               class="btn btn-primary">
                Start Exam
            </a>
        </div>
        <?php endif; ?>

        <?php if ($canMonitor): ?>
        <div class="feature-card" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3>Live Monitoring</h3>
            <p>Monitor active exam sessions in real-time and receive instant alerts.</p>
            <?php
            // Optional: Filter active sessions by this course only?
            // $activecount = $DB->count_records('local_myplugin_sessions', ['status' => 'active', 'courseid' => $courseid]);
            $activecount = $DB->count_records('local_myplugin_sessions', ['status' => 'active']);
            ?>
            <p><strong><?php echo $activecount; ?></strong> active session(s)</p>
            <a href="<?php echo new moodle_url('/local/myplugin/proctor_live.php', ['courseid' => $courseid]); ?>" 
               class="btn btn-success">
                View Live Monitor
            </a>
        </div>
        <?php endif; ?>

        <?php if ($canViewReports): ?>
        <div class="feature-card" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3>Exam Reports</h3>
            <p>View detailed reports and screenshots from completed exam sessions.</p>
            <?php
            $totalexams = $DB->count_records('local_myplugin_sessions');
            $totalalerts = $DB->count_records('local_myplugin_alerts');
            ?>
            <p><strong><?php echo $totalexams; ?></strong> total exams<br>
               <strong><?php echo $totalalerts; ?></strong> total alerts</p>
            <a href="<?php echo new moodle_url('/local/myplugin/proctor_dashboard.php', ['courseid' => $courseid]); ?>" 
               class="btn btn-info">
                View Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($canViewReports): 
        // You might want to filter this by courseid too: ['courseid' => $courseid]
        $recentsessions = $DB->get_records('local_myplugin_sessions', null, 'timecreated DESC', '*', 0, 5);
        if (!empty($recentsessions)):
    ?>
    <div style="margin-top: 30px;">
        <h3>Recent Exam Sessions</h3>
        <table class="table" style="background: #fff; margin-top: 15px;">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Exam</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Alerts</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentsessions as $session): 
                    $user = $DB->get_record('user', ['id' => $session->userid]);
                    $alertcount = $DB->count_records('local_myplugin_alerts', ['sessionid' => $session->id]);
                ?>
                <tr>
                    <td><?php echo fullname($user); ?></td>
                    <td><?php echo $session->examname; ?></td>
                    <td><?php echo userdate($session->starttime, '%d %b %Y, %H:%M'); ?></td>
                    <td>
                        <span class="status-badge <?php echo $session->status; ?>">
                            <?php echo ucfirst($session->status); ?>
                        </span>
                    </td>
                    <td><?php echo $alertcount; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; endif; ?>
</div>

<?php
echo $OUTPUT->footer();