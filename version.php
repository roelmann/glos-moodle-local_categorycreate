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
 * Category Creation local plugin template specification.
 *
 * @package    local_categorycreate
 * @copyright  2017 RMOelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_categorycreate';  // Full name of the plugin (used for diagnostics).

$plugin->version   = 2019032700;
$plugin->release  = 'v3.7.0.0';
$plugin->maturity  = MATURITY_STABLE;

$plugin->requires  = 2018051705;
$plugin->dependencies = array(
    'local_extdb'  => 2019032000,
);
