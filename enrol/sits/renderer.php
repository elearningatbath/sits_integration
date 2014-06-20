<?php
class enrol_sits_renderer extends plugin_renderer_base {
	 public function render_sits_course_enrolment_users_table(sits_course_enrolment_users_table $table,moodleform $mform) {

        $table->initialise_javascript();

        $buttons = $table->get_manual_enrol_buttons();
        $buttonhtml = '';
        if (count($buttons) > 0) {
            $buttonhtml .= html_writer::start_tag('div', array('class' => 'enrol_user_buttons'));
            foreach ($buttons as $button) {
                $buttonhtml .= $this->render($button);
            }
            $buttonhtml .= html_writer::end_tag('div');
        }

        $content = '';
        if (!empty($buttonhtml)) {
            $content .= $buttonhtml;
        }
		$content .= $mform->render();
        //$content .= $this->output->render($table->get_enrolment_type_filter());
        $content .= $this->output->render($table->get_paging_bar());

        // Check if the table has any bulk operations. If it does we want to wrap the table in a
        // form so that we can capture and perform any required bulk operations.
        if ($table->has_bulk_user_enrolment_operations()) {
            $content .= html_writer::start_tag('form', array('action' => new moodle_url('/enrol/bulkchange.php'), 'method' => 'post'));
            foreach ($table->get_combined_url_params() as $key => $value) {
                if ($key == 'action') {
                    continue;
                }
                $content .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $key, 'value' => $value));
            }
            $content .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'bulkchange'));
            $content .= html_writer::table($table);
            $content .= html_writer::start_tag('div', array('class' => 'singleselect bulkuserop'));
            $content .= html_writer::start_tag('select', array('name' => 'bulkuserop'));
            $content .= html_writer::tag('option', get_string('withselectedusers', 'enrol'), array('value' => ''));
            $options = array('' => get_string('withselectedusers', 'enrol'));
            foreach ($table->get_bulk_user_enrolment_operations() as $operation) {
                $content .= html_writer::tag('option', $operation->get_title(), array('value' => $operation->get_identifier()));
            }
            $content .= html_writer::end_tag('select');
            $content .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('go')));
            $content .= html_writer::end_tag('div');

            $content .= html_writer::end_tag('form');
        } else {
            $content .= html_writer::table($table);
        }
        $content .= $this->output->render($table->get_paging_bar());
        if (!empty($buttonhtml)) {
            $content .= $buttonhtml;
        }
        return $content;
    }
}
class sits_course_enrolment_table extends html_table implements renderable {

    /**
     * The get/post variable that is used to identify the page.
     * Default: page
     */
    const PAGEVAR = 'page';

    /**
     * The get/post variable to is used to identify the number of items to display
     * per page.
     * Default: perpage
     */
    const PERPAGEVAR = 'perpage';

    /**
     * The get/post variable that is used to identify the sort field for the table.
     * Default: sort
     */
    const SORTVAR = 'sort';

    /**
     * The get/post variable that is used to identify the sort direction for the table.
     * Default: dir
     */
    const SORTDIRECTIONVAR = 'dir';

    /**
     * The default number of items per page.
     * Default: 100
     */
    const DEFAULTPERPAGE = 100;

    /**
     * The default sort, options are course_enrolment_table::$sortablefields
     * Default: lastname
     */
    const DEFAULTSORT = 'cohort';

    /**
     * The default direction
     * Default: ASC
     */
    const DEFAULTSORTDIRECTION = 'ASC';

    /**
     * The current page, starting from 0
     * @var int
     */
    public $page = 0;

    /**
     * The total number of pages
     * @var int
     */
    public $pages = 0;

    /**
     * The number of items to display per page
     * @var int
     */
    public $perpage = 0;

    /**
     * The sort field for this table, should be one of course_enrolment_table::$sortablefields
     * @var string
     */
    public $sort;

    /**
     * The sort direction, either ASC or DESC
     * @var string
     */
    public $sortdirection;

    /**
     * The course manager this table is displaying for
     * @var course_enrolment_manager
     */
    protected $manager;

    /**
     * The paging bar that controls the paging for this table
     * @var paging_bar
     */
    protected $pagingbar = null;

    /**
     * The total number of users enrolled in the course
     * @var int
     */
    protected $totalusers = null;

    /**
     * The users enrolled in this course
     * @var array
     */
    protected $users = null;

    /**
     * The fields for this table
     * @var array
     */
    protected $fields = array();

    /**
     * An array of bulk user enrolment operations
     * @var array
     */
    protected $bulkoperations = array();

    /**
     * An array of sortable fields
     * @static
     * @var array
     */
    protected  $sortablefields = array('firstname', 'lastname', 'idnumber', 'email',
            'phone1', 'phone2', 'institution', 'department');

    /**
     * Constructs the table
     *
     * @param course_enrolment_manager $manager
     */
    public function __construct(course_enrolment_manager $manager) {

        $this->manager        = $manager;

        $this->page           = optional_param(self::PAGEVAR, 0, PARAM_INT);
        $this->perpage        = optional_param(self::PERPAGEVAR, self::DEFAULTPERPAGE, PARAM_INT);
        $this->sort           = optional_param(self::SORTVAR, self::DEFAULTSORT, PARAM_ALPHANUM);
        $this->sortdirection  = optional_param(self::SORTDIRECTIONVAR, self::DEFAULTSORTDIRECTION, PARAM_ALPHA);

        $this->attributes = array('class'=>'userenrolment');
        if (!in_array($this->sort, $this->sortablefields)) {
            $this->sort = self::DEFAULTSORT;
        }
        if ($this->page < 0) {
            $this->page = 0;
        }
        if ($this->sortdirection !== 'ASC' && $this->sortdirection !== 'DESC') {
            $this->sortdirection = self::DEFAULTSORTDIRECTION;
        }

        $this->id = html_writer::random_id();

        // Collect the bulk operations for the currently filtered plugin if there is one.
        $plugin = $manager->get_filtered_enrolment_plugin();
        if ($plugin) {
            $this->bulkoperations = $plugin->get_bulk_operations($manager);
        }
    }

    /**
     * Returns an array of enrol_user_buttons that are created by the different
     * enrolment plugins available.
     *
     * @return array
     */
    public function get_manual_enrol_buttons() {
        return $this->manager->get_manual_enrol_buttons();
    }

    /**
     * Gets the sort direction for a given field
     *
     * @param string $field
     * @return string ASC or DESC
     */
    public function get_field_sort_direction($field) {
        if ($field == $this->sort) {
            return ($this->sortdirection == 'ASC')?'DESC':'ASC';
        }
        return self::DEFAULTSORTDIRECTION;
    }

    /**
     * Sets the fields for this table. These get added to the tables head as well.
     *
     * You can also use a multi dimensional array for this to have multiple fields
     * in a single column
     *
     * @param array $fields An array of fields to set
     * @param string $output
     */
    public function set_fields($fields, $output) {
        $this->fields = $fields;
        $this->head = array();
        $this->colclasses = array();
        $this->align = array();
        $url = $this->manager->get_moodlepage()->url;

        if (!empty($this->bulkoperations)) {
            // If there are bulk operations add a column for checkboxes.
            $this->head[] = '';
            $this->colclasses[] = 'field col_bulkops';
        }

        foreach ($fields as $name => $label) {
            $newlabel = '';
            if (is_array($label)) {
                $bits = array();
                foreach ($label as $n => $l) {
                    if ($l === false) {
                        continue;
                    }
                    if (!in_array($n, $this->sortablefields)) {
                        $bits[] = $l;
                    } else {
                        $link = html_writer::link(new moodle_url($url, array(self::SORTVAR=>$n)), $fields[$name][$n]);
                        if ($this->sort == $n) {
                            $link .= ' '.html_writer::link(new moodle_url($url, array(self::SORTVAR=>$n, self::SORTDIRECTIONVAR=>$this->get_field_sort_direction($n))), $this->get_direction_icon($output, $n));
                        }
                        $bits[] = html_writer::tag('span', $link, array('class'=>'subheading_'.$n));

                    }
                }
                $newlabel = join(' / ', $bits);
            } else {
                if (!in_array($name, $this->sortablefields)) {
                    $newlabel = $label;
                } else {
                    $newlabel  = html_writer::link(new moodle_url($url, array(self::SORTVAR=>$name)), $fields[$name]);
                    if ($this->sort == $name) {
                        $newlabel .= ' '.html_writer::link(new moodle_url($url, array(self::SORTVAR=>$name, self::SORTDIRECTIONVAR=>$this->get_field_sort_direction($name))), $this->get_direction_icon($output, $name));
                    }
                }
            }
            $this->head[] = $newlabel;
            $this->colclasses[] = 'field col_'.$name;
        }
    }
    /**
     * Sets the total number of users
     *
     * @param int $totalusers
     */
    public function set_total_users($totalusers) {
        $this->totalusers = $totalusers;
        $this->pages = ceil($this->totalusers / $this->perpage);
        if ($this->page > $this->pages) {
            $this->page = $this->pages;
        }
    }
    /**
     * Sets the users for this table
     *
     * @param array $users
     * @return void
     */
    public function set_users(array $users,$sort = '') {
 
        $this->users = $users;
        $hasbulkops = !empty($this->bulkoperations);
        foreach ($users as $userid=>$user) {
            $user = (array)$user;
            $row = new html_table_row();
            $row->attributes = array('class' => 'userinforow');
            $row->id = 'user_'.$userid;
            $row->cells = array();
            if ($hasbulkops) {
                // Add a checkbox into the first column.
                $input = html_writer::empty_tag('input', array('type' => 'checkbox', 'name' => 'bulkuser[]', 'value' => $userid));
                $row->cells[] = new html_table_cell($input);
            }
            foreach ($this->fields as $field => $label) {
                if (is_array($label)) {
                    $bits = array();
                    foreach (array_keys($label) as $subfield) {
                        if (array_key_exists($subfield, $user)) {
                            $bits[] = html_writer::tag('div', $user[$subfield], array('class'=>'subfield subfield_'.$subfield));
                        }
                    }
                    if (empty($bits)) {
                        $bits[] = '&nbsp;';
                    }
                    $row->cells[] = new html_table_cell(join(' ', $bits));
                } else {
                    if (!array_key_exists($field, $user)) {
                        $user[$field] = '&nbsp;';
                    }
                    $row->cells[] = new html_table_cell($user[$field]);
                }
            }
            $this->data[] = $row;
        }
    }

    public function initialise_javascript() {
        if (has_capability('moodle/role:assign', $this->manager->get_context())) {
            $this->manager->get_moodlepage()->requires->strings_for_js(array(
                'assignroles',
                'confirmunassign',
                'confirmunassigntitle',
                'confirmunassignyes',
                'confirmunassignno'
            ), 'role');
            $modules = array('moodle-enrol-rolemanager', 'moodle-enrol-rolemanager-skin');
            $function = 'M.enrol.rolemanager.init';
            $arguments = array(
                'containerId'=>$this->id,
                'userIds'=>array_keys($this->users),
                'courseId'=>$this->manager->get_course()->id,
                'otherusers'=>isset($this->otherusers));
            $this->manager->get_moodlepage()->requires->yui_module($modules, $function, array($arguments));
        }
    }

    /**
     * Gets the paging bar instance for this table
     *
     * @return paging_bar
     */
    public function get_paging_bar() {
        if ($this->pagingbar == null) {
            $this->pagingbar = new paging_bar($this->totalusers, $this->page, $this->perpage, $this->manager->get_moodlepage()->url, self::PAGEVAR);
        }
        return $this->pagingbar;
    }

    /**
     * Gets the direction icon for the sortable field within this table
     *
     * @param core_renderer $output
     * @param string $field
     * @return string
     */
    protected function get_direction_icon($output, $field) {
        $direction = self::DEFAULTSORTDIRECTION;
        if ($this->sort == $field) {
            $direction = $this->sortdirection;
        }
        if ($direction === 'ASC') {
            return html_writer::empty_tag('img', array('alt'=>'', 'src'=>$output->pix_url('t/down')));
        } else {
            return html_writer::empty_tag('img', array('alt'=>'', 'src'=>$output->pix_url('t/up')));
        }
    }

    /**
     * Gets the params that will need to be added to the url in order to return to this page.
     *
     * @return array
     */
    public function get_url_params() {
        return array(
            self::PAGEVAR => $this->page,
            self::PERPAGEVAR => $this->perpage,
            self::SORTVAR => $this->sort,
            self::SORTDIRECTIONVAR => $this->sortdirection
        );
    }

    /**
     * Returns an array of URL params for both the table and the manager.
     *
     * @return array
     */
    public function get_combined_url_params() {
        return $this->get_url_params() + $this->manager->get_url_params();
    }

    /**
     * Sets the bulk operations for this table.
     *
     * @param array $bulkoperations
     */
    public function set_bulk_user_enrolment_operations(array $bulkoperations) {
        $this->bulkoperations = $bulkoperations;
    }

    /**
     * Returns an array of bulk operations.
     *
     * @return array
     */
    public function get_bulk_user_enrolment_operations() {
        return $this->bulkoperations;
    }

    /**
     * Returns true fi the table is aware of any bulk operations that can be performed on users
     * selected from the currently filtered enrolment plugins.
     *
     * @return bool
     */
    public function has_bulk_user_enrolment_operations() {
        return !empty($this->bulkoperations);
    }
}
class sits_course_enrolment_users_table extends sits_course_enrolment_table {

    /**
     * An array of sortable fields
     * @static
     * @var array
     */
     protected  $sortablefields = array('firstname', 'lastname','cohort','roles','isdefault','unenroldate','username' );

    /**
     * Gets the enrolment type filter control for this table
     *
     * @return single_select
     */
    public function get_enrolment_type_filter() {
        $selector = new single_select($this->manager->get_moodlepage()->url, 'ifilter', array(0=>get_string('all')) + (array)$this->manager->get_enrolment_instance_names(), $this->manager->get_enrolment_filter(), array());
        $selector->set_label( get_string('enrolmentinstances', 'enrol'));
        return $selector;
    }
}
