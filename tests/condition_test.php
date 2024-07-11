<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace availability_adler;


use availability_adler\lib\adler_testcase;
use base_logger;
use core_availability\info;
use core_plugin_manager;
use local_adler\plugin_interface;
use local_logging\logger;
use Mockery;
use moodle_exception;
use ReflectionClass;
use ReflectionMethod;
use restore_dbops;

global $CFG;
require_once($CFG->dirroot . '/availability/condition/adler/tests/lib/adler_testcase.php');


class condition_test extends adler_testcase {
    public function provide_test_construct_data() {
        return [
            'valid' => [
                'structure' => (object)[
                    'type' => 'adler',
                    'condition' => '(1)^(2)'
                ],
                'expected_exception' => null,
                'expected_condition' => '(1)^(2)'
            ],
            'invalid condition' => [
                'structure' => (object)[
                    'type' => 'adler',
                    'condition' => '(1)^(2'
                ],
                'expected_exception' => 'invalid_parameter_exception',
                'expected_condition' => null
            ],
            'missing condition' => [
                'structure' => (object)[
                    'type' => 'adler',
                ],
                'expected_exception' => 'coding_exception',
                'expected_condition' => null
            ],
        ];
    }

    /**
     * @dataProvider provide_test_construct_data
     *
     * # ANF-ID: [MVP12]
     */
    public function test_construct($structure, $expected_exception, $expected_condition) {
        if ($expected_exception) {
            $this->expectException($expected_exception);
        }

        $condition = new condition($structure);

        // get condition
        $condition_reflection = new ReflectionClass($condition);
        $condition_property = $condition_reflection->getProperty('condition');
        $condition_property->setAccessible(true);
        $condition = $condition_property->getValue($condition);

        $this->assertEquals($expected_condition, $condition);
    }

    public function provide_test_evaluate_section_data() {
        return [
            [
                'exception' => null,
            ], [
                'exception' => 'user_not_enrolled',
            ], [
                'exception' => 'blub',
            ]
        ];
    }

    /**
     * @dataProvider provide_test_evaluate_section_data
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     *  # ANF-ID: [MVP12]
     */
    public function test_evaluate_section($exception) {
        $plugin_interface_mock = Mockery::mock('overload:'. plugin_interface::class);
        if ($exception == null) {
            $plugin_interface_mock->shouldReceive('is_section_completed')
                ->once()
                ->with(1,1)
                ->andReturn(true);
        } else {
            $plugin_interface_mock->shouldReceive('is_section_completed')
                ->once()
                ->with(1,1)
                ->andThrow(new moodle_exception($exception));
        }
        if ($exception != null && $exception != 'user_not_enrolled') {
            $this->expectException(moodle_exception::class);
            $this->expectExceptionMessage($exception);
        }

        $reflected_class = new ReflectionClass(condition::class);
        $method = $reflected_class->getMethod('evaluate_section');
        $method->setAccessible(true);


        $condition = Mockery::mock(condition::class);
        $result = $method->invoke($condition,1,1);


        if ($exception == null) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }


    public function provide_test_evaluate_section_requirements_data() {
        return [
            '1' => [
                'statement' => "(5)v((7)^(4))",
                'expected' => true,
                'exception' => null,
                'section_states' => [
                    5 => true,
                    7 => true,
                    4 => false
                ]
            ],
            '2' => [
                'statement' => "(5)v((7)^(4))",
                'expected' => false,
                'exception' => null,
                'section_states' => [
                    5 => false,
                    7 => true,
                    4 => false
                ]
            ],
            '3' => [
                'statement' => "(1)",
                'expected' => false,
                'exception' => null,
                'section_states' => [
                    1 => false,
                ]
            ],
            '4' => [
                'statement' => "(1)",
                'expected' => true,
                'exception' => null,
                'section_states' => [
                    1 => true,
                ]
            ],
            '5' => [
                'statement' => "(1)^(2)",
                'expected' => true,
                'exception' => null,
                'section_states' => [
                    1 => true,
                    2 => true,
                ]
            ],
            '6' => [
                'statement' => "(1)^(2)",
                'expected' => false,
                'exception' => null,
                'section_states' => [
                    1 => true,
                    2 => false,
                ]
            ],
            '7' => [
                'statement' => "(1)v(2)",
                'expected' => true,
                'exception' => null,
                'section_states' => [
                    1 => false,
                    2 => true,
                ]
            ],
            '8' => [
                'statement' => "1v(2)",
                'expected' => true,
                'exception' => null,
                'section_states' => [
                    1 => true,
                    2 => false,
                ]
            ],
            '9' => [
                'statement' => "((1)^(2))v((3)^(4))",
                'expected' => true,
                'exception' => null,
                'section_states' => [
                    1 => true,
                    2 => true,
                    3 => true,
                    4 => true,
                ]
            ],
            '10' => [
                'statement' => "!((1)^(2))",
                'expected' => false,
                'exception' => null,
                'section_states' => [
                    1 => true,
                    2 => true,
                ]
            ],
            '11' => [
                'statement' => "(w)^(2)",
                'expected' => true,
                'exception' => 'invalid_parameter_exception',
                'section_states' => [
                    1 => true,
                    2 => false,
                ]
            ],
        ];
    }


    /**
     * @dataProvider provide_test_evaluate_section_requirements_data
     *
     *  # ANF-ID: [MVP12]
     */
    public function test_evaluate_section_requirements($statement, $expected, $exception, $section_states) {
        // map $section_states to the format of $section_states_map_format
        $section_states = array_map(function ($key, $value) {
            return [$key, 0, $value];
        }, array_keys($section_states), $section_states);

        // create mock for condition evaluate_section
        $mock = $this->getMockBuilder(condition::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['evaluate_section'])
            ->getMock();
        // set return values for evaluate_section
        $mock->method('evaluate_section')
            ->will($this->returnValueMap($section_states));

        // make evaluate_section_requirements accessible
        $method = new ReflectionMethod(condition::class, 'evaluate_section_requirements');
        $method->setAccessible(true);

        // if $exception is not null, expect an exception
        if ($exception !== null) {
            $this->expectException($exception);
        }

        $this->assertEquals($expected, $method->invoke($mock, $statement, 0));
    }

    public function provide_test_make_condition_user_readable_data() {
        return [
            [
                'condition' => '1',
                'section_states' => [
                    1 => true,
                ],
                'section_names' => [
                    1 => 'Section 1'
                ],
                'expected' => '"<span style="color: green;">Section 1</span>"',
            ],
            [
                'condition' => '1v2',
                'section_states' => [
                    1 => true,
                    2 => false,
                ],
                'section_names' => [
                    1 => 'Section 1',
                    2 => 'Section 2'
                ],
                'expected' => '"<span style="color: green;">Section 1</span> or <span style="color: red;">Section 2</span>"',
            ],
            [
                'condition' => '1^!(2v3)',
                'section_states' => [
                    1 => true,
                    2 => false,
                    3 => true,
                ],
                'section_names' => [
                    1 => 'Section 1',
                    2 => 'Section 2',
                    3 => 'Section 3'
                ],
                'expected' => '"<span style="color: green;">Section 1</span> and not (<span style="color: red;">Section 2</span> or <span style="color: green;">Section 3</span>)"',
            ],
        ];
    }

    /**
     * @dataProvider provide_test_make_condition_user_readable_data
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     *  # ANF-ID: [MVP13]
     */
    public function test_make_condition_user_readable($condition, $section_states, $section_names, $expected) {
        $condition_mock = Mockery::mock(condition::class)->makePartial();
        $condition_mock->shouldAllowMockingProtectedMethods();

        foreach ($section_states as $section_id => $section_state) {
            $condition_mock->shouldReceive('evaluate_section')->with($section_id, 0)->andReturn($section_state);
        }

        $plugin_interface_mock = Mockery::mock('overload:' . plugin_interface::class);
        $plugin_interface_mock->shouldReceive('get_section_name')->andReturnUsing(function ($section_id) use ($section_names) {
            return $section_names[$section_id];
        });

        $this->assertEquals($expected, $condition_mock->make_condition_user_readable($condition, 0));
    }

    /**
     *  # ANF-ID: [MVP13]
     */
    public function test_get_description() {
        $condition_mock = Mockery::mock(condition::class)->makePartial();
        $condition_mock->shouldAllowMockingProtectedMethods();
        $condition_mock->shouldReceive('make_condition_user_readable')
            ->with("condition")
            ->andReturn('test');

        $reflected_class = new ReflectionClass(condition::class);
        $reflected_property = $reflected_class->getProperty('condition');
        $reflected_property->setAccessible(true);
        $reflected_property->setValue($condition_mock, "condition");

        $info_mock = $this->getMockBuilder(info::class)
            ->disableOriginalConstructor()
            ->getMock();

        $result = $condition_mock->get_description(true, false, $info_mock);

        $this->assertIsString($result);
        $this->assertStringContainsString('test', $result);
    }

    public function test_get_debug_string() {
        $condition = new condition((object)['type' => 'adler', 'condition' => '1']);

        // make get_debug_string accessible
        $method = new ReflectionMethod(condition::class, 'get_debug_string');
        $method->setAccessible(true);

        // call get_debug_string
        $result = $method->invoke($condition);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     *  # ANF-ID: [MVP12]
     */
    public function test_save() {
        $adler_statement = (object)['type' => 'adler', 'condition' => '1'];

        $condition = new condition($adler_statement);
        $result = $condition->save();

        $this->assertEquals($adler_statement, $result);
    }

    public function provide_test_is_available_data() {
        return [
            '1' => [
                'installed_plugins' => ['adler' => '123'],
                'evaluate_section_requirements' => true,
                'not' => false,
                'expected' => true
            ],
            '2' => [
                'installed_plugins' => ['adler' => '123'],
                'evaluate_section_requirements' => true,
                'not' => true,
                'expected' => false
            ],
            '3' => [
                'installed_plugins' => ['adler' => '123'],
                'evaluate_section_requirements' => false,
                'not' => false,
                'expected' => false
            ],
            '4' => [
                'installed_plugins' => [],
                'evaluate_section_requirements' => true,
                'not' => true,
                'expected' => false
            ],
        ];
    }

    /**
     * @dataProvider provide_test_is_available_data
     *
     *  # ANF-ID: [MVP12]
     */
    public function test_is_available(array $installed_plugins, bool $evaluate_section_requirements, bool $not, bool $expected) {
        $info_mock = $this->getMockBuilder(info::class)
            ->disableOriginalConstructor()
            ->getMock();


        $core_plugin_manager_mock = $this->getMockBuilder(core_plugin_manager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $core_plugin_manager_mock->method('get_installed_plugins')
            ->willReturn($installed_plugins);


        $condition_mock = $this->getMockBuilder(condition::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['evaluate_section_requirements'])
            ->getMock();
        $condition_mock->method('evaluate_section_requirements')
            ->willReturn($evaluate_section_requirements);
        // set protected property core_plugin_manager_instance of condition_mock
        $reflection = new ReflectionClass($condition_mock);
        $property = $reflection->getProperty('core_plugin_manager_instance');
        $property->setAccessible(true);
        $property->setValue($condition_mock, $core_plugin_manager_mock);
        // set condition
        $property = $reflection->getProperty('condition');
        $property->setAccessible(true);
        $property->setValue($condition_mock, '1');


        // mock logger as it does not exist because constructor is not executed
        $logger_mock = Mockery::mock(Logger::class);
        // ignore all method calls on mock
        $logger_mock->shouldIgnoreMissing();
        // set logger mock to $logger variable in class under test
        $property = $reflection->getProperty('logger');
        $property->setAccessible(true);
        $property->setValue($condition_mock, $logger_mock);


        // invoke method is_available on $reflection
        $result = $condition_mock->is_available($not, $info_mock, true, 0);
        // alternative approach
//        $method = $reflection->getMethod('is_available');
//        $result = $method->invoke($condition_mock, $not, $info_mock, true, 0);


        $this->assertEquals($expected, $result);
    }

    public function provide_test_update_after_restore_data() {
        return [
            '1' => [
                'condition' => "(1)^(20)",
                'backup_id_mappings' => [
                    [1, (object)["newitemid" => "3"]],
                    [20, (object)["newitemid" => "4"]],
                ],
                'expected_updated_condition' => "(3)^(4)",
                'expect_exception' => false,
            ],
            '2' => [
                'condition' => "(1)^(2)",
                'backup_id_mappings' => [
                    [1, (object)["newitemid" => "3"]],
                    [2, false],
                ],
                'expected_updated_condition' => null,
                'expect_exception' => moodle_exception::class,
            ],
        ];
    }

    /**
     * @dataProvider provide_test_update_after_restore_data
     *
     *  # ANF-ID: [MVP2]
     */
    public function test_update_after_restore($condition, $backup_id_mappings, $expected_updated_condition, $expect_exception) {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        // unused but required variables
        $restoreid = 123;
        $courseid = 456;
        $base_logger_mock = $this->getMockBuilder(base_logger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $name = 'test';

        // create get_backup_ids_record return map
        $return_map = [];
        foreach ($backup_id_mappings as $mapping) {
            $return_map[] = [restore_dbops::class, 'get_backup_ids_record', $restoreid, 'course_section', (string)$mapping[0], $mapping[1]];
        }

        // mock condition
        $condition_mock = $this->getMockBuilder(condition::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['callStatic'])
            ->getMock();
        $condition_mock->method('callStatic')
            ->will($this->returnValueMap($return_map));

        // set condition
        $reflection = new ReflectionClass($condition_mock);
        $property = $reflection->getProperty('condition');
        $property->setAccessible(true);
        $property->setValue($condition_mock, $condition);

        // setup exception
        if ($expect_exception) {
            $this->expectException($expect_exception);
        }

        // call update_after_restore
        $result = $condition_mock->update_after_restore($restoreid, $courseid, $base_logger_mock, $name);

        // verify result
        $this->assertEquals(true, $result);
        $updated_condition = $property->getValue($condition_mock);
        $this->assertEquals($expected_updated_condition, $updated_condition);
    }
}
