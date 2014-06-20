<?php

require('../../../../config.php');
require_once("$CFG->dirroot/enrol/locallib.php");
require_once("$CFG->dirroot/enrol/sits/users_forms.php");
//require_once("$CFG->dirroot/enrol/renderer.php");
require_once("$CFG->dirroot/enrol/sits/renderer.php");
require_once("$CFG->dirroot/group/lib.php");

$id      = required_param('id', PARAM_INT); // course id
$action  = optional_param('action', '', PARAM_ACTION);
$filter  = optional_param('ifilter', 0, PARAM_INT);
$search  = optional_param('search', '', PARAM_RAW);
$role    = optional_param('role', 0, PARAM_INT);
$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);

// When users reset the form, redirect back to first page without other params.
if (optional_param('resetbutton', '', PARAM_RAW) !== '') {
    redirect('enrols.php?id=' . $id);
}

if ($course->id == SITEID) {
    redirect(new moodle_url('/'));
}

require_login($course);
require_capability('moodle/course:enrolreview', $context);
//$PAGE->set_pagelayout('admin');
$PAGE->set_pagetype('popup');
$PAGE->requires->css('/blocks/sits/gui/css/samis_user_interface.css');


/**
 * This class provides a targeted tied together means of interfacing the enrolment
 * tasks together with a course.
 *
 * It is provided as a convenience more than anything else.
 */
class sits_course_enrolment_manager extends course_enrolment_manager {

	/**
	 * Gets an array of users for display, this includes minimal user information
	 * as well as minimal information on the users roles, groups, and enrolments.
	 *
	 * @param core_enrol_renderer $renderer
	 * @param moodle_url $pageurl
	 * @param int $sort
	 * @param string $direction ASC or DESC
	 * @param int $page
	 * @param int $perpage
	 * @return array
	 */
	function get_users_for_display(course_enrolment_manager $manager, $sort, $direction, $page, $perpage) {
		$pageurl = $manager->get_moodlepage()->url;
		$users = $this->get_users($sort, $direction, $page, $perpage);

		$now = time();
		$strnever = get_string('never');
		$straddgroup = get_string('addgroup', 'group');
		$strunenrol = get_string('unenrol', 'enrol');
		$stredit = get_string('edit');

		$allroles   = $this->get_all_roles();
		$assignable = $this->get_assignable_roles();
		$allgroups  = $this->get_all_groups();
		$courseid   = $this->get_course()->id;
		$context    = $this->get_context();
		$canmanagegroups = has_capability('moodle/course:managegroups', $context);

		$url = new moodle_url($pageurl, $this->get_url_params());
		$extrafields = get_extra_user_fields($context);

		$userdetails = array();
		foreach ($users as $user) {
			$details = array(
                'userid'     => $user->id,
                'username'   => $user->username,
                'courseid'   => $courseid,
                'picture'    => new user_picture($user),
                'firstname'  => fullname($user, true),
                'lastseen'   => $strnever,
                'roles'      => $user->role,
                'groups'     => array(),
                'enrolments' => array()
			);
			foreach ($extrafields as $field) {
				$details[$field] = $user->{$field};
			}

			$userdetails[$user->id] = $details;
		}
		return $userdetails;
	}
	function get_users($sort, $direction='ASC', $page=0, $perpage=25) {
		global $DB;
		if ($direction !== 'ASC') {
			$direction = 'DESC';
		}
		$key = md5("$sort-$direction-$page-$perpage");
		if (!array_key_exists($key, $this->users)) {
			list($instancessql, $params, $filter) = $this->get_instance_sql();
			list($filtersql, $moreparams) = $this->get_filter_sql();
			$params += $moreparams;
			$extrafields = get_extra_user_fields($this->get_context());
			$extrafields[] = 'username';
			$ufields = user_picture::fields('u', $extrafields);
			$sql = "SELECT DISTINCT $ufields, ul.timeaccess AS lastseen,r.name as role
	                      FROM {user} u
	                      JOIN {user_enrolments} ue ON (ue.userid = u.id  AND ue.enrolid $instancessql)
	                      JOIN {enrol} e ON (e.id = ue.enrolid)
	                      LEFT JOIN {sits_mappings_enrols} sme ON sme.u_enrol_id = ue.id 
	                      LEFT JOIN {role_assignments} ra ON ra.id = sme.ra_id
	                      LEFT JOIN {role} r ON (ra.roleid = r.id)
	                 LEFT JOIN {user_lastaccess} ul ON (ul.courseid = e.courseid AND ul.userid = u.id) WHERE $filtersql";
			if ($sort === 'firstname') {
				$sql .= " ORDER BY u.firstname $direction, u.lastname $direction";
			} else if ($sort === 'lastname') {
				$sql .= " ORDER BY u.lastname $direction, u.firstname $direction";
			} else if ($sort === 'email') {
				$sql .= " ORDER BY u.email $direction, u.lastname $direction, u.firstname $direction";
			} else if ($sort === 'lastseen') {
				$sql .= " ORDER BY ul.timeaccess $direction, u.lastname $direction, u.firstname $direction";
			}else if ($sort === 'username') {
				$sql .= " ORDER BY u.username $direction"; 
			}else if ($sort === 'roles') {
				$sql .= " ORDER BY r.name $direction";
			}
			$this->users[$key] = $DB->get_records_sql($sql, $params, $page*$perpage, $perpage);
		}
		return $this->users[$key];
	}
	/**
     * Obtains WHERE clause to filter results by defined search and role filter
     * (instance filter is handled separately in JOIN clause, see
     * get_instance_sql).
     *
     * @return array Two-element array with SQL and params for WHERE clause
     */
    protected function get_filter_sql() {
        global $DB;

        // Search condition.
        $extrafields = get_extra_user_fields($this->get_context());
        list($sql, $params) = users_search_sql($this->searchfilter, 'u', true, $extrafields);

        // Role condition.
        if ($this->rolefilter) {
            // Get context SQL.
            $contextids = $this->context->get_parent_context_ids();
            $contextids[] = $this->context->id;
            list($contextsql, $contextparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
            $params += $contextparams;

            // Role check condition.
            $sql .= " AND (SELECT COUNT(1) FROM {role_assignments} ra WHERE ra.userid = u.id " .
                    "AND ra.roleid = :roleid AND ra.contextid $contextsql) > 0";
            $params['roleid'] = $this->rolefilter;
        }

        return array($sql, $params);
    }
	public function get_manual_enrol_buttons() {
		//return no button.
	}

}
/*class sits_enrol_users_filter_form extends moodleform
{
	//TODO: maybe implement a user search filter form when/if time allows
	function definition() {}
}*/
/////////////////////////////////////////////////
$manager = new sits_course_enrolment_manager($PAGE, $course, $filter,$role,$search);
$table = new sits_course_enrolment_users_table($manager, $PAGE);

$PAGE->set_url('/blocks/sits/gui/views/enrols.php', $manager->get_url_params()+$table->get_url_params());
//navigation_node::override_active_url(new moodle_url('/blocks/sits/gui/views/enrols.php', array('id' => $id)));

//$renderer = $PAGE->get_renderer('enrol');
$renderer = $PAGE->get_renderer('enrol_sits');
$userdetails = array (
    //'picture' => false,
    'firstname' => get_string('firstname'),
    'lastname' => get_string('lastname')
);

$fields = array(
    'userdetails' => $userdetails,
    //'email' => get_string('email'),
    'username' => get_string('username'),
    //'cohort' => array('Mapped Cohort'),
    'cohort' => 'Mapped Cohort',
    'roles' => 'Role',
    'unenrol_method' => 'Unenrol Method',
    'isdefault' => 'Default',
    'unenroldate' => 'Unenrol Date'
);
$filterform = new sits_enrol_users_filter_form('enrols.php', array('manager' => $manager, 'id' => $id),
        'get', '', array('id' => 'sits_filterform'));
$filterform->set_data(array('search' => $search, 'ifilter' => $filter, 'role' => $role));
$table->set_fields($fields, $renderer);

$canassign = has_capability('moodle/role:assign', $manager->get_context());
$users = $manager->get_users_for_display($manager, $table->sort, $table->sortdirection, $table->page, $table->perpage);
$contextid = $manager->get_context()->id;

$sits_enrol = new enrol_sits_plugin();

foreach ($users as $userid=>&$user) {
	$mapping = $sits_enrol->mapping_for_user_in_context($contextid,$user['userid']);
	if (is_object($mapping)){
		$user['cohort'] = $mapping->cohort->sits_code.' '.$mapping->cohort->academic_year;
		if(isset($mapping->cohort->period_code))
		{
			$user['cohort'] .= ' '.$mapping->cohort->period_code;
		}
		$user['unenroldate'] = $mapping->end->format('d-m-Y');
		if($mapping->default == 1){
			$user['isdefault']='Yes';
		} else {
			$user['isdefault'] = 'No';
		}
		if($mapping->manual == 1){
			$user['unenrol_method'] = 'Manual';
			$user['unenroldate'] = '';
		} elseif($mapping->specified == 1){
			$user['unenrol_method'] = 'Specified';
		} else {
			$user['unenrol_method'] = 'Sync';
		}
		
	} else {
		$user['cohort'] = 'Manual Enrolment';
		$user['unenrol_method'] = 'N/A';
		$user['isdefault'] = $user['unenroldate'] = '';
	}
}
$table->set_total_users($manager->get_total_users());
$table->set_users($users);

$PAGE->set_title($PAGE->course->fullname.': '.get_string('totalenrolledusers', 'enrol', $manager->get_total_users()));
$PAGE->set_heading($PAGE->title);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('enrolledusers', 'enrol'));
echo $renderer->render_sits_course_enrolment_users_table($table, $filterform);
echo $OUTPUT->footer();
