<?php
/**
 * Library function(s) for moodle staff area enrolment
 *
 * @author  Hittesh Ahuja  h.ahuja@bath.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package local
 * @subpackage moodle_staff_area_enrolment
 * @copyright 2013 University of Bath
 */

/// CONSTANTS ///////////////////////////////////////////////////////////
DEFINE('LAST_24_HOURS', 3600 * 24);
/**
 * Function to be run periodically according to the SITS cron
 * This should ideally run after SITS cron
 * Finds all manually enrolled teachers and enrol them to the staff area course
 *  
 */

function local_moodle_staff_area_enrolment_cron()
{
    global $DB, $CFG;
	require_once($CFG->dirroot . '/local/sits/lib/report.class.php');
	$report = new report();
    require_once($CFG->libdir . '/enrollib.php');
    $execute      = false;
    $users_added  = array();
    $resultset    = array();
    //Run only once a day after the sits cron
    $now          = new DateTime();
    $lastcrontime = get_config('local_moodle_staff_area_enrolment', 'lastcrontime');
    if(!$lastcrontime){
	//D.I.Y
	set_config('lastcrontime',time(),'local_moodle_staff_area_enrolment');
	}
    $last_staff_area_enrolment = new DateTime();
    $last_staff_area_enrolment->setTimestamp($lastcrontime);
    echo "LAST STAFF CRON TIME:" . date("Y-m-d H:i:s",$lastcrontime) . "\n";
    //Run only from Mon - Fri
    if ($now->format('Y-m-d') != $last_staff_area_enrolment->format('Y-m-d') && ($now->format('N') != 6 || $now->format('N') != 7 ) ) {
        $staff_course = $CFG->sits_moodle_staff_area;
        if (!empty($staff_course)) {
			mtrace(' Running Moodle Staff Area Enrolment function..... ');
            $timemodified   = time(); //Curent time
            set_config('lastcrontime',$timemodified,'local_moodle_staff_area_enrolment');
            $timeend        = 0;
            $context        = get_context_instance(CONTEXT_COURSE, $staff_course);
            $enrol_instance = $DB->get_record('enrol', array(
                'courseid' => $staff_course,
                'enrol' => 'manual'
            ), '*', MUST_EXIST);
            $enrol          = enrol_get_plugin('manual');
            //get all teachers enrolled manually in the last 24 hours
            $timestart      = time() - LAST_24_HOURS;
            $timestart      = 0;
            $where          = " roleid = '3' AND component = '' AND timemodified >= $timestart";
            $rs             = $DB->get_recordset_select('role_assignments', $where);
            if ($rs->valid()) {
                foreach ($rs as $record) {
                    $resultset[] = $record;
                }
            }
	$resultset = remove_duplicates($resultset); //Removing duplicate userids to save some time
            $rs->close();
            if (!empty($resultset)) {
                foreach ($resultset as $record) {
                    $userid = $record->userid;
                    //Check to see if this user is enrolled as teacher onto the staff area course
                    if (!is_enrolled($context, $userid, '', true)) {
                        //If not , add them as manual student enrolment to the course
                        try {
                            $enrol->enrol_user($enrol_instance, $userid, 5, $timestart, $timeend);
                            $users_added[] = $userid;
                        }
                        catch (exception $e) {
                            $message = "Could not add user : $userid to Moodle Staff Area Enrolment Course";
			    $report->log_report(1, $message);
                        }
                    }
                }
            }
        }
        else{
				mtrace("Staff Area id is set to 0. Plugin disabled.");
		}
    }
    else{
			mtrace("Set to run on Mon-Fri and it is not time yet.");
		}
    if (!empty($users_added)) {
        echo "Following users were added to the Moodle Staff Enrolment Area course: \n";
        foreach ($users_added as $id) {
            echo $id . " \n";
        }
    }
    return true;
}
function remove_duplicates($rs){
	$tmp = array();
	foreach($rs as $key => $object){
		$tmp[$key] = $object->userid;
	}
	$tmp = array_unique($tmp);
	foreach($rs as $key=> $object){
	if(!array_key_exists($key,$tmp)){
		unset($rs[$key]);
		}
	}
return $rs;
}

