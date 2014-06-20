<?php
/**
 * Script to migrate mappings from the SITS/Moodle 1.9 block to this
 * @package    enrol
 * @subpackage sits
 * @copyright  2011 University of Bath
 * @author     Alex Lydiate {@link http://alexlydiate.co.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
echo "\n\n";
$root = dirname(__FILE__);

//define('CLI_SCRIPT', true);
require_once($root . '/../../config.php');
require_once($CFG->dirroot . '/local/sits/lib/mapping.class.php');
require_once($CFG->dirroot . '/local/sits/lib/cohort.class.php');



$SITS_sync = enrol_get_plugin('sits');
//GLOBAL $DB;

$mf_dbtype = 'mysql';
$mf_dbhost = 'hostname';
$mf_dbname = 'dbname';
$mf_user = 'dbuser';
$mf_password = 'dbpass';

$dbh_mf = pdo_connect($mf_dbtype,
$mf_dbhost,
$mf_dbname,
$mf_user,
$mf_password);
 
if(!$dbh_mf){
    exit('Could not establish connection to the migrate-from database');
}

echo "Connected to the migrate-from database, rinsing it for mappings right about now...\n";

$created = 0;
$failed = 0;
$no_course = array();

$sql = 'select * from mdl5_sits_mappings';

$old_map_stm = $dbh_mf->prepare($sql);
$old_map_stm->execute();

while($row = $old_map_stm->fetchObject()){
        
	if($row->type == 'module'){
	try{
            $cohort = new module_cohort($row->sits_code, $row->period_code, $row->acyear);
        }catch(InvalidArgumentException $e){
            echo 'mod cohort exception: ' . $e->getMessage() . "\n";
        }
    }else{
        try{
            $cohort = new program_cohort($row->sits_code, $row->year_group, $row->acyear);
        }catch(InvalidArgumentException $e){
            echo 'prog cohort exception: ' . $e->getMessage() . "\n";
        }
    }

    
    //comment out check for course with idnumber/sits_code match as this 
    //prevents essential manual mappings from migrating - just use the internal moodle course 
    //ID from the mapping as this will be consistent across 1.9 and 2.2

    // $course = $DB->get_record('course', array('idnumber' => $row->sits_code));

    // if(is_object($course)){
		try
		{
	    	$mapping = new mapping( $row->courseid, 
	    							$cohort, 
	    							new DateTime($row->start_date), 
	    							new DateTime($row->end_date), 
	    							$row->manual, 
	    							$row->default_map, 
	    							$id = null, 
	    							$row->specified, 
	    							$row->active = null);
	    }
	    catch(Exception $e)
	    {
	        echo 'Failed to create automatic mapping for ' . $row->courseid . ' to cohort :';
	        echo_r($cohort);
	        echo $e->message;
	    }

	    if($SITS_sync->create_mapping($mapping))
	    {
	        $created++;
	    }
	    else
	    {
	        $failed++;
	    }
    // }else{
    //     $no_course[] = $row->sits_code;
    // }
}

/*
if(count($no_course > 0)){
	echo "No course found for SITS codes :\n";
	foreach ($no_course as $sits_code) {
		echo $sits_code . "\n";
	}
}
*/

echo "\nCreated " . $created . ' mappings, did not create ' . $failed . " probably because they are there already - done\n\n";

function pdo_connect($dbtype, $dbhost, $dbname, $dbuser, $dbpass){
    $connect_string = '%s:host=%s;dbname=%s';
    return new PDO(sprintf($connect_string, $dbtype, $dbhost, $dbname), $dbuser, $dbpass);
}
?>
