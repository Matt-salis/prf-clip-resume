<?php
require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/filelib.php'); // Required for file storage.
require_once($CFG->dirroot.'/vendor/autoload.php'); // Adjust path to your autoload.php

use Google\Client;
use Google\Service\Drive;

function initialize_google_client() {
    // Create a new Google client
    $client = new Google\Client();
    
    // Set your application name
    $client->setApplicationName('Moodle Video Manager');

    // Set the scope to access Google Drive (full access)
    $client->setScopes(Google_Service_Drive::DRIVE);

    // Path to your service account JSON key file (replace with your actual path)
    $client->setAuthConfig(__DIR__ . '/apikey.json'); 

    // Access type 'offline' ensures that your app can access Google services even when the user is not present
    $client->setAccessType('offline');

    // Initialize the Drive service using the authenticated client
    $driveService = new Google_Service_Drive($client);

    return $driveService;
}

function download_videos_from_drive($folderId) {
    $client = initialize_google_client();
    $driveService = new Drive($client);

    // Fetch video files in the specified folder
    $query = "'$folderId' in parents and mimeType contains 'video/'";
    $response = $driveService->files->listFiles(array(
        'q' => $query,
        'fields' => 'files(id, name, mimeType, webContentLink)',
        'pageSize' => 100,
    ));

    foreach ($response->files as $file) {
        // Download the video file
        $fileId = $file->id;
        $fileName = $file->name;

        // Ensure the path to Moodle's file storage is correct and absolute
        $filePath = $CFG->dataroot . '/filedir/' . $fileName; // Adjust path as necessary

        // Download the file content from Google Drive
        $response = $driveService->files->get($fileId, array('alt' => 'media'));
        file_put_contents($filePath, $response->getBody()->getContents());

        // Store the file in Moodle's file storage
        $fs = get_file_storage();
        $context = context_system::instance();
        $fileRecord = array(
            'contextid' => $context->id,
            'component' => 'local_yourplugin', // Adjust component name
            'filearea' => 'videos',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $fileName,
            'timecreated' => time(),
            'timemodified' => time()
        );
        $fs->create_file_from_pathname($fileRecord, $filePath);

        // Delete the video from Google Drive
        $driveService->files->delete($fileId);
    }
}

// Call this function periodically via cron
$folderId = '1akgGea9yg4lRVndGZRFrFo6WOXTC34np'; // Replace with your Google Drive folder ID
download_videos_from_drive($folderId);