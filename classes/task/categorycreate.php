<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A scheduled task for scripted database integrations - category creation.
 *
 * @package    local_categorycreate - template
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_categorycreate\task;
use stdClass;
use coursecat;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot.'/local/extdb/classes/task/extdb.php');

/**
 * A scheduled task for scripted external database integrations.
 *
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class categorycreate extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'local_categorycreate');
    }

    /**
     * Run sync.
     */
    public function execute() {

        global $CFG, $DB;
        require_once($CFG->libdir . "/coursecatlib.php");

        $externaldb = new \local_extdb\extdb();
        $name = $externaldb->get_name();

        $externaldbtype = $externaldb->get_config('dbtype');
        $externaldbhost = $externaldb->get_config('dbhost');
        $externaldbname = $externaldb->get_config('dbname');
        $externaldbencoding = $externaldb->get_config('dbencoding');
        $externaldbsetupsql = $externaldb->get_config('dbsetupsql');
        $externaldbsybasequoting = $externaldb->get_config('dbsybasequoting');
        $externaldbdebugdb = $externaldb->get_config('dbdebugdb');
        $externaldbuser = $externaldb->get_config('dbuser');
        $externaldbpassword = $externaldb->get_config('dbpass');
        $table1 = get_string('remotetablecatlev', 'local_categorycreate');
        $table2 = get_string('remotetablecats', 'local_categorycreate');

        // Database connection and setup checks.
        // Check connection and label Db/Table in cron output for debugging if required.
        if (!$externaldbtype) {
            echo 'Database not defined.<br>';
            return 0;
        } else {
            echo 'Database: ' . $externaldbtype . '<br>';
        }
        // Check remote assessments table - usr_data_assessments.
        if (!$table1) {
            echo 'Levels Table not defined.<br>';
            return 0;
        } else {
            echo 'Levels Table: ' . $table1 . '<br>';
        }
        // Check remote student grades table - usr_data_student_assessments.
        if (!$table2) {
            echo 'Categories Table not defined.<br>';
            return 0;
        } else {
            echo 'Categories Table: ' . $table2 . '<br>';
        }
        echo 'Starting connection...<br>';

        // Report connection error if occurs.
        if (!$extdb = $externaldb->db_init(
            $externaldbtype,
            $externaldbhost,
            $externaldbuser,
            $externaldbpassword,
            $externaldbname)) {
            echo 'Error while communicating with external database <br>';
            return 1;
        }

        // EXTERNAL DB - TABLE1: Category levels.
        $levels = array();

        // Read data from table1.
        $sql = $externaldb->db_get_sql($table1, array(), array(), true, "rank");
        if ($rs = $extdb->Execute($sql)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {
                    $fields = array_change_key_case($fields, CASE_LOWER);
                    $fields = $externaldb->db_decode($fields);
                    $levels[] = $fields;
                }
            }
            $rs->Close();
        } else {
            // Report error if required.
            $extdb->Close();
            echo 'Error reading data from the external catlevel table, ' . $table1 . '<br>';
            return 4;
        }

        $cats = array();
        foreach ($levels as $l) { // Loop through each level in turn to create a tree.
            if ($l['inuse'] == 1) {

                $level = $l['categorylevel'];

                // EXTERNAL DB - TABLE2: Categories list.

                // Read data from table2.
                $sql2 = $externaldb->db_get_sql_like($table2, array("category_idnumber" => $level), array(), true);
                if ($rs2 = $extdb->Execute($sql2)) {
                    if (!$rs2->EOF) {
                        while ($category = $rs2->FetchRow()) {
                            $category = array_change_key_case($category, CASE_LOWER);
                            $category = $externaldb->db_decode($category);
                            $cats[] = $category;

                            // Create data to write category.
                            $data = array();

                            // ID number - UoG essential data!
                            if (isset($category['category_idnumber'])) {
                                $data['idnumber'] = $category['category_idnumber'];
                            } else {
                                echo 'Category IdNumber required';
                                break;
                            }

                            // Name - If no name is set, make name = idnumber.
                            if (isset($category['category_name']) && $category['category_name'] !== 'Undefined') {
                                $data['name'] = $category['category_name'];
                            } else {
                                $data['name'] = $category['category_idnumber'];
                            }

                            // Default $parent values as Misc category, to give base values and get initial record.
                            $parent = $DB->get_record('course_categories', array('name' => 'Miscellaneous'));
                            $parent->id = 0;
                            $parent->visible = 1;
                            $parent->depth = 0;
                            $parent->path = '';
                            // If exists overide default $parent by fetching parent->id based on unique parent category idnumber.
                            if (!$category['parent_cat_idnumber'] == '') {
                                // Check if the parent category already exists - based on unique idnumber.
                                if ($DB->record_exists('course_categories',
                                    array('idnumber' => $category['parent_cat_idnumber']))) {
                                    // Fetch that parent category details.
                                    $parent = $DB->get_record('course_categories',
                                    array('idnumber' => $category['parent_cat_idnumber']));
                                }
                            }
                            // Set $data['parent'] as the id of the parent category and depth as parent +1.
                            $data['parent'] = $parent->id;
                            $data['depth'] = $parent->depth + 1;

                            // Create a category that inherits visibility from parent.
                            $data['visible'] = $parent->visible;
                            // If a category is marked as 'deleted' then ensure it is hidden - don't actually delete it.
                            if ($category['deleted']) {
                                $data['visible'] = 0;
                            }

                            if (!$DB->record_exists('course_categories',
                                array('idnumber' => $category['category_idnumber']))) {
                                // Set new category id by inserting the data created above.
                                coursecat::create($data);
                                echo 'Category ' . $data['idnumber'] . ' added<br>';
                            } else {
                                // IF category already exists, fetch the existing id.
                                $data['id'] = $DB->get_field('course_categories', 'id',
                                    array('idnumber' => $category['category_idnumber']));
                                // Set the path as necessary.
                                $data['path'] = $parent->path . '/' . $data['id'];
                                // As category already exists, update it with any changes.
                                $DB->update_record('course_categories', $data);
                                echo 'Category ' . $data['idnumber'] . ' updated<br>';
                            }
                        }
                    }
                    $rs2->Close();
                } else {
                    // Report error if required.
                    $extdb->Close();
                    echo 'Error reading data from the external categories table, ' . $table2 .'<br>';
                    return 4;
                }
            } else {
                echo 'Category Level ' . $l['categorylevel'] . ' is not in use.<br>';
            }
        }
        // Free memory.
        $extdb->Close();
    }

}
