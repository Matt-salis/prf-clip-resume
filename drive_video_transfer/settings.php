<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) { // Only allow admins to configure this.
    $settings = new admin_settingpage('local_drive_video_transfer', get_string('pluginname', 'local_drive_video_transfer'));

    // Google Drive Folder ID Setting.
    $settings->add(new admin_setting_configtext(
        'local_drive_video_transfer/drive_folder_id', // Unique identifier.
        get_string('drivefolderid', 'local_drive_video_transfer'), // Label.
        get_string('drivefolderid_desc', 'local_drive_video_transfer'), // Description.
        '', // Default value.
        PARAM_TEXT // Data type.
    ));

    // Add the settings page to the 'Local Plugins' category.
    $ADMIN->add('localplugins', $settings);
}
