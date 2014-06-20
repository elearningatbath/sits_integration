<?php
//Page to update SAMIS - Moodle Category mappings
require('../../../../config.php');
require_login();
if(isset($_POST['savecategories'])){
	
	//Get the values
	foreach($_POST as $category => $samis_dept){
		$categoryid = trim(preg_replace('/^sits_category_id_/','',$category));
		if(!empty($categoryid) && !empty($samis_dept)){
			//Get the field ID 
			$id = $DB->get_field('sits_categories', 'id', array('id' => $categoryid));
			$DB->set_field('sits_categories', 'sits_dep_code', $samis_dept, array('id'=>$categoryid));
			//$DB->update_record('sits_categories',$objData );
		}
	}
}
$PAGE->set_pagelayout('popup');
$PAGE->set_title('SAMIS Category Mapping');
$PAGE->requires->css('/blocks/sits/gui/css/samis_user_interface.css');
$PAGE->set_url('/blocks/sits/gui/views/categories.php',array());
$PAGE->set_context(context_system::instance()); 
echo $OUTPUT->header();

//Get details from mdl5_sits_categories table
global $DB,$CFG;
$categories_data = $DB->get_records('sits_categories');
//Display data in a form and table
?>
<form action="<?php echo $CFG->wwwroot.'/blocks/sits/gui/views/categories.php'; ?>" method="post">

<?php 

echo "<table>";
echo "<thead><tr><th>Category</th><th>SAMIS Department Code</th></tr></thead>";
echo "<tbody>";
foreach($categories_data as $obj){
	//Get category name from id
	$categoryname = $DB->get_field('course_categories', 'name', array('id'=>$obj->category_id));
	echo "<tr>";
		echo "<td>".($categoryname == "" ? '(Missing Category)' : $categoryname)."</td>";
		echo "<td><input type=\"text\" name=\"sits_category_id_$obj->id\" id=\"$obj->id\" value=\"$obj->sits_dep_code\"></input></td>";
		//echo "<td><input type=\"button\" name=\"save_$obj->category_id\" value=\"Save\" onClick=\"save_category($obj->category_id)\"></input></td>";
	echo "</tr>";
}
echo "</tbody></table>";
echo "<td><input type=\"submit\" name=\"savecategories\" value=\"Save\" ></input></td>";
?>
 </form>
<?php 
echo $OUTPUT->footer();




