<?php
namespace local_drive_video_transfer\task;

class transfer_videos extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('taskname', 'local_drive_video_transfer');
    }

    public function execute() {
        \local_drive_video_transfer\video_manager::process_videos();
    }
}

