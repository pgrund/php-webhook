<?php

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

    // handle security
    $config->verify();

    // get post data and process json
    $input = file_get_contents('php://input');
    $config->json = json_decode($input, true);
    $config->checkJson();

    // copy remote zip file locally
    $file = $config->remoteUrl();   
    $opts = array(
        'http' => array(
            'method' => "GET",
            'user_agent' => PROJECT_NAME." release update poller",
            'max_redirects' => 20,
            'request_fulluri' => true
    ));
    if (!copy($file, ZIP_FILENAME, stream_context_create($opts))) {
        $config->errorResponse(500, "failed to copy stream to file ...");
        
    } else {
        // extract zip file
        $zip = new ZipArchive;
        $res = $zip->open(ZIP_FILENAME, ZIPARCHIVE::CREATE);
        if ($res === TRUE) {
            $path = trim($zip->getNameIndex(0), '/');
            $zip->extractTo(".");
            $zip->close();

            // rename folder
            if (file_exists("./".EXTRACT_FOLDER)) {
                deleteDir("./".EXTRACT_FOLDER);
            }
            rename("./" . $path, "./".EXTRACT_FOLDER);
        } else {
            $config->errorResponse(500, "failed to open zip file (".ZIP_FILENAME.") ...");
        }
    }
}


echo "<html>
    <head>
    <title>Latest release for ".PROJECT_NAME."</title>
    </head>
     <body>
      <h1>Latest</h1>";
if (isset($config->json)) {
    echo "
        <p class='info'>successfully updated latest release !!!</p>
        <p>ZIP taken from " . $config->remoteUrl() . "</p>";
}
echo "
      <p>    
      <a href='./".ZIP_FILENAME."' target='_blank'>Latest Release of ".PROJECT_NAME."(Zip)</a> for download
      </p>
      <p>Please see <a href='./".EXTRACT_FOLDER."'>".EXTRACT_FOLDER."</a> for files ...</p>
     </body>
    </html>";
?>
