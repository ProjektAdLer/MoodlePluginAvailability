<?php

namespace availability_adler\lib;

global $CFG;
require_once($CFG->dirroot . '/availability/condition/adler/vendor/autoload.php');

use advanced_testcase;
use externallib_advanced_testcase;
use Mockery;

trait general_testcase_adjustments{
    public function setUp(): void {
        parent::setUp();

        // set default value: reset DB after each test case
        $this->resetAfterTest();

        // if creating multiple mocks of the same class (in my example context_module) in different tests or
        // same test with different parameters Mockery always reused the first mock created for that class.
        // This is not desired, because test cases should be independent of each other. Therefore, the
        // Mockery container is reset after each test case.
        Mockery::resetContainer();
    }

    public function tearDown(): void {
        parent::tearDown();

        Mockery::close();
    }
}

abstract class adler_testcase extends advanced_testcase {
    use general_testcase_adjustments;
}
