<?php
require('../../config.php');

require_login();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/myplugin/index.php'));
$PAGE->set_title('My Plugin');
$PAGE->set_heading('My Plugin');

echo $OUTPUT->header();
echo html_writer::tag('h2', get_string('welcome', 'local_myplugin'));
echo html_writer::div('This is a simple Moodle local plugin.');
echo $OUTPUT->footer();
