<?php

/**
 * @package     availability_adler
 * @copyright   2023 Markus Heck <markus.heck@hs-kempten.de>
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2024102600;
$plugin->requires = 2024042200;  // Moodle version
$plugin->component = 'availability_adler';
$plugin->release = '4.0.0-rc.1';
$plugin->maturity = MATURITY_RC;
$plugin->dependencies = array(
    'local_logging' => ANY_VERSION,
);