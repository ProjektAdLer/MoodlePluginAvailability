<?php


use availability_adler\lib\adler_testcase;
use availability_adler\frontend;

global $CFG;
require_once($CFG->dirroot . '/availability/condition/adler/tests/lib/adler_testcase.php');


class frontend_test extends adler_testcase {
    /**
     * # ANF-ID: [MVP13]
     */
    public function test_get_javascript_strings() {
        // make get_javascript_strings public
        $method = new ReflectionMethod(frontend::class, 'get_javascript_strings');
        $method->setAccessible(true);
        $frontend = new frontend();

        $this->assertIsArray($method->invoke($frontend));
    }

    public function test_allow_add() {
        // make allow_add public
        $method = new ReflectionMethod(frontend::class, 'allow_add');
        $method->setAccessible(true);
        $frontend = new frontend();

        $this->assertFalse($method->invoke($frontend, null, null, null));
    }
}