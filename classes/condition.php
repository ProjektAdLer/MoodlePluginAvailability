<?php

namespace availability_adler;


use base_logger;
use coding_exception;
use core\di;
use core_availability\condition as availability_condition;
use core_availability\info;
use core_plugin_manager;
use dml_exception;
use invalid_parameter_exception;
use lang_string;
use local_adler\plugin_interface;
use local_logging\logger;
use moodle_exception;
use restore_dbops;


class condition extends availability_condition {
    protected logger $logger;

    protected string $condition;
    protected $core_plugin_manager_instance;

    /**
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function __construct($structure) {
        $this->logger = new logger('availability_adler', 'condition');
        if (isset($structure->condition)) {
            try {
                $this->evaluate_section_requirements($structure->condition, 0, true);
            } catch (invalid_parameter_exception $e) {
                throw new invalid_parameter_exception('Invalid condition: ' . $e->getMessage());
            }
            $this->condition = $structure->condition;
        } else {
            throw new coding_exception('adler condition not set');
        }

        $this->core_plugin_manager_instance = core_plugin_manager::instance();
    }

    /**
     * @param $statement
     * @param $userid
     * @param $validation_mode bool If set to true, this method is used to validate the condition. In this case,
     * the method will not call external methods. All calls to evaluate_section will be replaced with a "true" value.
     * @return bool
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    protected function evaluate_section_requirements($statement, $userid, bool $validation_mode = false): bool {
        // search for brackets
        for ($i = 0; $i < strlen($statement); $i++) {
            if ($statement[$i] == '(') {
                $start = $i;
                $end = $i;
                $depth = 1;
                for ($j = $i + 1; $j < strlen($statement); $j++) {
                    if ($statement[$j] == '(') {
                        $depth++;
                    } else if ($statement[$j] == ')') {
                        $depth--;
                    }
                    if ($depth == 0) {
                        $end = $j;
                        break;
                    }
                }
                $substatement = substr($statement, $start + 1, $end - $start - 1);
                $result = $this->evaluate_section_requirements($substatement, $userid, $validation_mode) ? 't' : 'f';
                $statement = substr($statement, 0, $start) . $result . substr($statement, $end + 1);
                $i = $start;
            }
        }

        // Search for AND and OR following the rule "AND before OR"
        // search for AND (^)
        for ($i = 0; $i < strlen($statement); $i++) {
            if ($statement[$i] == '^') {
                $left = substr($statement, 0, $i);
                $right = substr($statement, $i + 1);
                $statement = ($this->evaluate_section_requirements($left, $userid, $validation_mode) == 't' && $this->evaluate_section_requirements($right, $userid) == 't') ? 't' : 'f';
                break;
            }
        }
        // search for OR (v)
        for ($i = 0; $i < strlen($statement); $i++) {
            if ($statement[$i] == 'v') {
                $left = substr($statement, 0, $i);
                $right = substr($statement, $i + 1);
                $statement = ($this->evaluate_section_requirements($left, $userid, $validation_mode) == 't' || $this->evaluate_section_requirements($right, $userid) == 't') ? 't' : 'f';
                break;
            }
        }

        // search for NOT (!)
        for ($i = 0; $i < strlen($statement); $i++) {
            if ($statement[$i] == '!') {
                $right = substr($statement, $i + 1);
                $statement = (!$this->evaluate_section_requirements($right, $userid, $validation_mode) == 't') ? 't' : 'f';
                break;
            }
        }

        // If this place is reached the statement should be only a number (section id)
        if (is_numeric($statement)) {
            $statement = $validation_mode || $this->evaluate_section((int)$statement, $userid);
        } else if ($statement == 't' || $statement == 'f') {
            $statement = $statement == 't';
        } else {
            throw new invalid_parameter_exception('Invalid statement: ' . $statement);
        }

        return $statement;
    }

    /**
     * @throws moodle_exception
     */
    protected function evaluate_section($section_id, $userid): bool {
        try {
            return di::get(plugin_interface::class)::is_section_completed($section_id, $userid);
        } catch (moodle_exception $e) {
            if ($e->errorcode == 'user_not_enrolled') {
                return false;
            } else {
                throw $e;
            }
        }
    }


    /**
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     * @throws dml_exception
     */
    public function is_available($not, info $info, $grabthelot, $userid): bool {
        // check if local_adler is available
        $plugins = $this->core_plugin_manager_instance->get_installed_plugins('local');
        if (!array_key_exists('adler', $plugins)) {
            $this->logger->warning('local_adler is not available');
            $allow = true;
        } else {
            $allow = $this->evaluate_section_requirements($this->condition, $userid);
        }

        if ($not) {
            $allow = !$allow;
        }

        return $allow;
    }


    /** This method is used to make the condition user readable.
     * It will replace all section ids with section names.
     * Section names are colored according to the section status.
     * It also replaced the ugly math symbols with the readable text.
     * @param $statement string The condition
     * @return string The user readable condition
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected function make_condition_user_readable(string $statement): string {
        $chars = ['(', ')', '^', 'v', '!'];
        $splitted_statement = [$statement];
        foreach ($chars as $char) {
            $new_statement = [];
            foreach ($splitted_statement as $part) {
                $part = explode($char, $part);
                for ($i = 0; $i < count($part); $i++) {
                    $new_statement[] = $part[$i];
                    if ($i < count($part) - 1) {
                        $new_statement[] = $char;
                    }
                }
            }
            $splitted_statement = $new_statement;
        }

        $updated_statement = "";
        foreach ($splitted_statement as $part) {
            if (is_numeric($part)) {
                if ($this->evaluate_section($part, $GLOBALS['USER']->id)) {
                    $updated_statement .= '<span style="color: green;">';
                } else {
                    $updated_statement .= '<span style="color: red;">';
                }
                $section_name = di::get(plugin_interface::class)::get_section_name($part);
                $updated_statement .= htmlspecialchars($section_name, ENT_QUOTES, 'UTF-8') . '</span>';
            } else {
                switch ($part) {
                    case '^':
                        $updated_statement .= ' ' . get_string("condition_operator_pretty_and", "availability_adler"). ' ';
                        break;
                    case 'v':
                        $updated_statement .= ' ' . get_string("condition_operator_pretty_or", "availability_adler") . ' ';
                        break;
                    case '!':
                        $updated_statement .= get_string("condition_operator_pretty_not", "availability_adler") . ' ';
                        break;
                    default:
                        $updated_statement .= $part;
                }
            }
        }

        return "\"" . trim($updated_statement) . "\"";
    }

    /**
     * @throws moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_description($full, $not, info $info): lang_string|string {
        $translation_key = $not ? 'description_previous_sections_required_not' : 'description_previous_sections_required';
        return get_string($translation_key, 'availability_adler', $this->make_condition_user_readable($this->condition));
    }

    protected function get_debug_string(): string {
        return 'Section condition: ' . $this->condition;
    }

    public function save(): object {
        return (object)[
            'type' => 'adler',
            'condition' => $this->condition,
        ];
    }

    /** Restore logic for completion condition
     * Translates the room/section ids in the condition from the backup file to the new ids in the restored course.
     *
     * @param string $restoreid
     * @param int $courseid
     * @param base_logger $logger
     * @param string $name
     * @return bool
     * @throws moodle_exception
     */
    public function update_after_restore($restoreid, $courseid, base_logger $logger, $name): bool {
        $updated_condition = "";
        for ($i = 0; $i < strlen($this->condition); $i++) {
            $char = $this->condition[$i];
            if (is_numeric($char)) {
                // find last digit of number
                $j = $i + 1;
                while ($j < strlen($this->condition) && is_numeric($this->condition[$j])) {
                    $j++;
                }
                $number = substr($this->condition, $i, $j - $i);
                $i = $j - 1;

                // add updated id to new string
                $updated_id = di::get(restore_dbops::class)::get_backup_ids_record($restoreid, 'course_section', $number);
                if ($updated_id == false) {
                    throw new moodle_exception('unknown_section', 'availability_adler', '', NULL, 'section: ' . $number);
                }
                $updated_condition .= $updated_id->newitemid;
            } else {
                $updated_condition .= $char;
            }
        }

        $condition_changed = $updated_condition != $this->condition;
        $this->condition = $updated_condition;
        return $condition_changed;
    }
}