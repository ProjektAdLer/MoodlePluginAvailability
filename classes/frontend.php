<?php

namespace availability_adler;

use cm_info;
use core_availability\frontend as availability_frontend;
use section_info;

class frontend extends availability_frontend {
    protected function get_javascript_strings(): array {
        // You can return a list of names within your language file and the
        // system will include them here.
        // Should you need strings from another language file, you can also
        // call $PAGE->requires->strings_for_js manually from here.)
        return ['node_adler_rule'];
    }

    /** Decide whether to allow adding this condition to the form.
     * Will always be false for the adler condition. Conditions can only be defined via AMG tool.
     * @param $course
     * @param cm_info|null $cm
     * @param section_info|null $section
     * @return bool
     */
    protected function allow_add($course, cm_info $cm = null, section_info $section = null): bool {
        return false;
    }
}
