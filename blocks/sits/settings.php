<?php
/**
 * @package    blocks
 * @subpackage sits
 * @copyright  2011 University of Bath
 * @author     Alex Lydiate {@link http://alexlydiate.co.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot.'/mod/forum/lib.php');
$options = array('Off', 'On');

$settings->add(new admin_setting_configselect('sits_gui_enabled', get_string('sits_gui_label', 'block_sits'),
get_string('sits_gui_desc', 'block_sits'), 1, $options));

$settings->add(new admin_setting_configtext('sits_disable_message', get_string('sits_disable_label', 'block_sits'),
get_string('sits_disable_desc', 'block_sits'), get_string('sits_disable_text', 'block_sits'), $paramtype=PARAM_RAW, $size=70));
 
$options = array('Off', 'Daily', 'Continuous');
// Default whether user needs to mark a post as read
$settings->add(new admin_setting_configselect('sits_cron_select', get_string('sits_cron_label', 'block_sits'),
get_string('config_select_desc', 'block_sits'), 1, $options));

$options = array('Off', 'On');
$settings->add(new admin_setting_configselect('sits_remove_orphans', get_string('sits_orphan_label', 'block_sits'),
get_string('sits_orphan_desc', 'block_sits'), 1, $options));

$options = array();
for ($i=0; $i<24; $i++) {
    $options[$i] = $i;
}

$settings->add(new admin_setting_configselect('sits_hour_of_sync', get_string('config_runtime_label', 'block_sits'),
get_string('config_runtime_desc', 'block_sits'), 1, $options));

$days = array(1=>'Monday', 'Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday');
$settings->add(new admin_setting_configmulticheckbox('sits_cron_days','Days to run cron on','Select the days you want the cron to run',array(),$days));
//Only add the settings if the local plugin exist
if(file_exists($CFG->dirroot.'/local/moodle_staff_area_enrolment/lib.php')){
	$settings->add(new admin_setting_configtext('sits_moodle_staff_area', get_string('sits_moodle_staff_area_label', 'block_sits'),
get_string('sits_moodle_staff_area_desc', 'block_sits'), NULL,PARAM_INT));
}
