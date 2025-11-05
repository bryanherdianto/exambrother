<?php
require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/myplugin/index.php'));
$PAGE->set_title(get_string('exambrother', 'local_myplugin'));
$PAGE->set_heading(get_string('exambrother', 'local_myplugin'));
$PAGE->set_pagelayout('standard');

// Add CSS
$PAGE->requires->css('/local/myplugin/styles.css');

echo $OUTPUT->header();

global $DB, $USER;

// Check user capabilities
$canTakeExam = has_capability('local/myplugin:takeexam', $context);
$canMonitor = has_capability('local/myplugin:monitor', $context);
$canViewReports = has_capability('local/myplugin:viewreports', $context);

?>

<div class="exam-monitor-container">
    <div class="welcome-section">
        <h2><?php echo get_string('welcome', 'local_myplugin'); ?></h2>
        <p>Welcome to the Exam Brother system. This plugin provides comprehensive exam monitoring with real-time cheating detection.</p>
    </div>

    <div class="features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 30px;">
        
        <?php if ($canTakeExam): ?>
        <div class="feature-card" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3>Take Exam</h3>
            <p>Start a monitored exam session with camera tracking and cheating detection.</p>
            <a href="<?php echo new moodle_url('/local/myplugin/student_exam.php', ['examname' => 'Sample Exam']); ?>" 
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
            $activecount = $DB->count_records('local_myplugin_sessions', ['status' => 'active']);
            ?>
            <p><strong><?php echo $activecount; ?></strong> active session(s)</p>
            <a href="<?php echo new moodle_url('/local/myplugin/proctor_live.php'); ?>" 
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
            <a href="<?php echo new moodle_url('/local/myplugin/proctor_dashboard.php'); ?>" 
               class="btn btn-info">
                View Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <?php if ($canViewReports): 
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
