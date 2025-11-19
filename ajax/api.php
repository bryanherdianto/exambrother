<?php
define('AJAX_SCRIPT', true);

require('../../../config.php');
require_once($CFG->libdir . '/externallib.php');

require_login();
$context = context_system::instance();

$action = optional_param('action', '', PARAM_ALPHANUMEXT);

$result = [
    'success' => false,
    'message' => 'Invalid action'
];

// Debug logging to PHP error log
error_log("AJAX API called. Action: " . ($action ?? 'null') . " User: " . ($USER->id ?? 'none'));

try {
    switch ($action) {
        case 'ping':
            $result = ['success' => true, 'message' => 'Pong'];
            break;

        case 'log_alert':
            // Get parameters
            $sessionid = required_param('sessionid', PARAM_INT);
            $userid = required_param('userid', PARAM_INT);
            $alerttype = required_param('alerttype', PARAM_TEXT);
            $description = required_param('description', PARAM_TEXT);
            $screenshot = optional_param('screenshot', '', PARAM_RAW); // Base64 data
            $severity = optional_param('severity', 1, PARAM_INT);
            
            // Check sesskey if provided, or enforce it
            $sesskey = optional_param('sesskey', '', PARAM_RAW);
            if (!empty($sesskey) && !confirm_sesskey($sesskey)) {
                 throw new moodle_exception('invalidsesskey');
            }

            error_log("Processing log_alert for session $sessionid");

            // Call external function
            // We need to include the class file manually if autoloading isn't picking it up yet, 
            // but Moodle's autoloader should handle classes/external/log_alert.php -> \local_myplugin\external\log_alert
            
            $response = \local_myplugin\external\log_alert::execute(
                $sessionid, 
                $userid, 
                $alerttype, 
                $description, 
                $screenshot, 
                $severity
            );
            
            $result = $response;
            break;

        case 'end_session':
            require_capability('local/myplugin:monitor', $context);
            $sessionid = required_param('sessionid', PARAM_INT);
            
            $response = \local_myplugin\external\end_session::execute($sessionid);
            $result = $response;
            break;

        default:
            throw new moodle_exception('invalidaction');
    }
} catch (Exception $e) {
    $result = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($result);
die();
