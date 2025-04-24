<?php
/**
 * @package Akismet
 */
/*
Plugin Name:Jubayer's Test Plugin
Plugin URI: http://13.61.145.200:8000/
Description: This is a test Phase plugin for Jubayer
Version: 0.0.1
Requires at least: 5.8
Requires PHP: 5.6.20
Author: Jubayer Ahamed
Author URI: https://automattic.com/wordpress-plugins/
License: GPLv2 or later
Text Domain: Jubayer
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

Copyright 2005-2023 Automattic, Inc.
*/

// Make sure we don't expose any info if called directly
if ( ! function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

add_action('admin_menu', 'add_plugin_page');
add_action('wp_dashboard_setup', 'register_custom_dashboard_widget');


function register_custom_dashboard_widget()
{
    wp_add_dashboard_widget(
        'custom_dashboard_widget',
        'website_stastictics',
        'render_custom_dashboard_widget'
    );
}

function render_custom_dashboard_widget()
{
    echo '<h2>Website Statistics</h2>';
    echo '<p>Here you can display your website statistics.</p>';
    // You can add more HTML or PHP code to display actual statistics here.s
}

function add_plugin_page() {
    add_menu_page(
        'My Plugin Settings',
        'My Plugin',
        'manage_options',
        'my-plugin-settings',
        'render_settings_page'
    );
}

function render_settings_page() {
    ?>
    <div class="wrap">
        <h1>My Plugin Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('jubayers_group');
            do_settings_sections('jubayers-plugin-settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Setting 1</th>
                    <td><input type="text" name="setting_1" value="<?php echo esc_attr(get_option('setting_1')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Setting 2</th>
                    <td><input type="text" name="setting_2" value="<?php echo esc_attr(get_option('setting_2')); ?>" /></td>
                </tr>
            </table>
            
        <form method="post" action="options.php">
            <?php
            settings_fields('my_plugin_options_group');
            do_settings_sections('my-plugin-settings');
            submit_button();
            ?>
        </form>
    <?php
}