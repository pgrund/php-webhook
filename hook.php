<?php

/* get configuration */
require('hook-config.php');
$config = new Config();

/* helper function for deleting a nonempty directory */
function deleteDir($dir) {
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->getFilename() === '.' || $file->getFilename() === '..') {
            continue;
        }
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($dir);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // get post data
    $input = file_get_contents('php://input');

    // process payload (security check, json processing)
    $config->setPayload($input);

    // copy remote zip file locally
    $opts = array(
        'http' => array(
            'method' => "GET",
            'user_agent' => PROJECT_NAME." release update poller",
            'max_redirects' => 20,
            'request_fulluri' => true
    ));
    if (!copy($config->remoteUrl(), ZIP_FILENAME, stream_context_create($opts))) {
        $config->httpResponse(500, "failed to copy stream to file");
        
    } else {
        // extract zip file
        $zip = new ZipArchive;
        $res = $zip->open(ZIP_FILENAME, ZIPARCHIVE::CREATE);
        if ($res === TRUE) {
            $path = trim($zip->getNameIndex(0), '/');
            $zip->extractTo(".");
            $zip->close();

            // delete old version of folder and rename extracted folder
            if (file_exists("./".EXTRACT_FOLDER)) {
                deleteDir("./".EXTRACT_FOLDER);
            }
            rename("./" . $path, "./".EXTRACT_FOLDER);
        } else {
            $config->httpResponse(500, "failed to open zip file (".ZIP_FILENAME.")");
        }
    }
}

require('hook-template.php');
?>