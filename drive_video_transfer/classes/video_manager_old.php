<?php
namespace local_drive_video_transfer;

class video_manager {

    public static function process_videos() {
        mtrace("Process video started");
        
        // Step 1: Get OAuth token for Google Drive access.
        $token = self::get_google_access_token();

        // Step 2: Get Google Drive Folder ID from settings.
        $folder_id = get_config('local_drive_video_transfer', 'drive_folder_id');
        if (empty($folder_id)) {
            throw new \moodle_exception('drivefolderidnotset', 'local_drive_video_transfer');
        }

        // Step 3: Fetch list of videos from Google Drive folder.
        $videos = self::list_google_drive_files($token, $folder_id);
        
        if (empty($videos['files'])) {
            mtrace("No videos found in the Google Drive folder.");
            return;
        }

        // Step 4: Process each video.
        foreach ($videos['files'] as $video) {
            mtrace("Processing video: " . $video['name'] . " (ID: " . $video['id'] . ")");
            
            // Step 4.1: Download video from Google Drive
            $video_content = self::download_google_drive_file($token, $video['id']);
            if ($video_content === false) {
                mtrace("Failed to download video: " . $video['name']);
                continue;
            }

            // Step 4.2: Save video to Moodle's file system
            self::save_video_to_moodle($video['name'], $video_content);
            mtrace("Video saved to Moodle: " . $video['name']);
            
            // Step 4.3 (Optional): Delete video from Google Drive after saving to Moodle
            $deleteResponse = self::delete_google_drive_file($token, $video['id']);
            if ($deleteResponse) {
                mtrace("Video deleted from Google Drive: " . $video['name']);
            } else {
                mtrace("Failed to delete video from Google Drive: " . $video['name']);
            }
        }
    }
    

    private static function get_google_access_token() {
        global $USER;
    
        // Fetch OAuth issuers
        $issuers = \core\oauth2\api::get_all_issuers();
        mtrace("Fetched " . count($issuers) . " OAuth issuers.");
    
        // Find Google issuer
        $googleIssuer = null;
        foreach ($issuers as $iss) {
            if ($iss->get('name') === 'Google') {
                $googleIssuer = $iss;
                break;
            }
        }
    
        if (!$googleIssuer) {
            throw new \moodle_exception('oauth2issuernotfound', 'local_drive_video_transfer', '', 'Google issuer not found');
        }
    
        // Prepare scopes and URLs
        $returnurl = new \moodle_url('/local/drive_video_transfer/return');
        $currenturl = new \moodle_url('/local/drive_video_transfer/current');
        $scopes = ['https://www.googleapis.com/auth/drive.file'];
        $scopeString = implode(',', $scopes);
    
        try {
            $client = \core\oauth2\api::get_user_oauth_client($googleIssuer, $currenturl, $scopeString);
        } catch (Exception $e) {
            mtrace("Failed to get user oauth client: " . $e->getMessage());
            throw new \moodle_exception('oauth2error', 'core_oauth2', '', $e->getMessage());
        }
    
        // Log the client details for debugging
        mtrace("OAuth Client: " . print_r($client, true));
    
        $accessToken = $client->get('access_token');
        mtrace("Access token: " . $accessToken);  // Log the token for debugging
    
        if (empty($accessToken)) {
            mtrace("Access token is empty or invalid.");
            throw new \moodle_exception('oauth2error', 'core_oauth2', '', 'Access token is empty or invalid.');
        }
    
        return $accessToken;
    }
    
    
    private static function list_google_drive_files($accessToken, $folderId) {
        $url = 'https://www.googleapis.com/drive/v3/files?q="' . $folderId . '"+in+parents';
        $headers = array('Authorization: Bearer ' . $accessToken, 'Content-Type: application/json');
    
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);  // Force HTTP/1.1
        curl_setopt($curl, CURLOPT_VERBOSE, true);  // Enable verbose output
    
        $response = curl_exec($curl);
        
        if (curl_errno($curl)) {
            mtrace("CURL error: " . curl_error($curl));
            return [];  // Return an empty array on error
        }
    
        curl_close($curl);
        return json_decode($response, true);
    }
    
    private static function download_google_drive_file($accessToken, $fileId) {
        $url = 'https://www.googleapis.com/drive/v3/files/' . $fileId . '?alt=media';
        $headers = array('Authorization: Bearer ' . $accessToken);
    
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);  // Force HTTP/1.1
        curl_setopt($curl, CURLOPT_VERBOSE, true);  // Enable verbose output
    
        $response = curl_exec($curl);
        
        if (curl_errno($curl)) {
            mtrace("CURL error during download: " . curl_error($curl));
            return false;  // Return false on error
        }
    
        curl_close($curl);
        return $response;
    }
    

    // Save Video to Moodle's File System
    private static function save_video_to_moodle($filename, $content) {
        global $USER;

        $context = \context_user::instance($USER->id);
        $fs = get_file_storage();

        $fileinfo = array(
            'contextid' => $context->id,
            'component' => 'user',
            'filearea' => 'private',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename
        );

        if ($oldfile = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'], $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename'])) {
            $oldfile->delete();
        }

        $fs->create_file_from_string($fileinfo, $content);
    }

    // Delete Video from Google Drive
    private static function delete_google_drive_file($accessToken, $fileId) {
        $url = 'https://www.googleapis.com/drive/v3/files/' . $fileId;
        $headers = array('Authorization: Bearer ' . $accessToken, 'Content-Type: application/json');

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        
        if (curl_errno($curl)) {
            mtrace("CURL error during delete: " . curl_error($curl));
            return false;  // Return false on error
        }

        curl_close($curl);
        return true;  // Return true on successful deletion
    }
}
