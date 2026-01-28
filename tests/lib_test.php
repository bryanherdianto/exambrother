<?php

namespace local_myplugin;

defined('MOODLE_INTERNAL') || die();

class lib_test extends \advanced_testcase
{

    /**
     * Test that a session can be created
     */
    public function test_create_session()
    {
        global $DB;

        $this->resetAfterTest(true);

        // Create a test user
        $user = $this->getDataGenerator()->create_user();

        // Create a session record
        $session = new \stdClass();
        $session->userid = $user->id;
        $session->examname = 'Test Quiz';
        $session->starttime = time();
        $session->status = 'active';
        $session->timecreated = time();
        $session->timemodified = time();

        $sessionid = $DB->insert_record('local_myplugin_sessions', $session);

        // Assert the session was created
        $this->assertNotEmpty($sessionid);

        // Verify we can retrieve it
        $retrieved = $DB->get_record('local_myplugin_sessions', ['id' => $sessionid]);
        $this->assertEquals('Test Quiz', $retrieved->examname);
        $this->assertEquals('active', $retrieved->status);
    }

    /**
     * Test that an alert can be logged
     */
    public function test_log_alert()
    {
        global $DB;

        $this->resetAfterTest(true);

        // Create a test user and session
        $user = $this->getDataGenerator()->create_user();

        $session = new \stdClass();
        $session->userid = $user->id;
        $session->examname = 'Test Quiz';
        $session->starttime = time();
        $session->status = 'active';
        $session->timecreated = time();
        $session->timemodified = time();
        $sessionid = $DB->insert_record('local_myplugin_sessions', $session);

        // Create an alert
        $alert = new \stdClass();
        $alert->sessionid = $sessionid;
        $alert->userid = $user->id;
        $alert->alerttype = 'head_pose';
        $alert->description = 'LOOKING LEFT';
        $alert->severity = 1;
        $alert->timecreated = time();

        $alertid = $DB->insert_record('local_myplugin_alerts', $alert);

        // Assert the alert was created
        $this->assertNotEmpty($alertid);

        // Verify the alert count
        $alertcount = $DB->count_records('local_myplugin_alerts', ['sessionid' => $sessionid]);
        $this->assertEquals(1, $alertcount);
    }

    /**
     * Test session completion
     */
    public function test_complete_session()
    {
        global $DB;

        $this->resetAfterTest(true);

        // Create a test user and session
        $user = $this->getDataGenerator()->create_user();

        $session = new \stdClass();
        $session->userid = $user->id;
        $session->examname = 'Test Quiz';
        $session->starttime = time();
        $session->status = 'active';
        $session->timecreated = time();
        $session->timemodified = time();
        $sessionid = $DB->insert_record('local_myplugin_sessions', $session);

        // Complete the session
        $session->id = $sessionid;
        $session->status = 'completed';
        $session->endtime = time();
        $session->timemodified = time();
        $DB->update_record('local_myplugin_sessions', $session);

        // Verify the session is completed
        $retrieved = $DB->get_record('local_myplugin_sessions', ['id' => $sessionid]);
        $this->assertEquals('completed', $retrieved->status);
        $this->assertNotEmpty($retrieved->endtime);
    }

    /**
     * Test tab switch alert type
     */
    public function test_tab_switch_alert()
    {
        global $DB;

        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();

        $session = new \stdClass();
        $session->userid = $user->id;
        $session->examname = 'Test Quiz';
        $session->starttime = time();
        $session->status = 'active';
        $session->timecreated = time();
        $session->timemodified = time();
        $sessionid = $DB->insert_record('local_myplugin_sessions', $session);

        // Create a tab_switch alert
        $alert = new \stdClass();
        $alert->sessionid = $sessionid;
        $alert->userid = $user->id;
        $alert->alerttype = 'tab_switch';
        $alert->description = 'TAB SWITCH DETECTED (1/3)';
        $alert->severity = 1;
        $alert->timecreated = time();

        $alertid = $DB->insert_record('local_myplugin_alerts', $alert);

        // Verify alert type
        $retrieved = $DB->get_record('local_myplugin_alerts', ['id' => $alertid]);
        $this->assertEquals('tab_switch', $retrieved->alerttype);
    }
}
