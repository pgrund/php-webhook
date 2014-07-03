<?php

require('hook-config.php');

class Config {

    public $json;
    private $errors = array(
        400 => "Bad Request",
        403 => "Not Authorized",
        500 => "Internal Server Error"
    );
    private $allowed_ips;
    private $secret;

    function __construct() {
        $this->secret = sha1(SECRET_TOKEN);
        $this->allowed_ips = explode(",", ALLOWED_IPS);
    }

    function verify() {
        // check IP
        if (!(in_array($_SERVER["REMOTE_ADDR"], $this->allowed_ips))) {
            $this->errorResponse(403);
        }
        // check signature
        $signature = $_SERVER["HTTP_X_HUB_SIGNATURE"];
        if (!(isset($signature) && $signature === $this->secret)) {
            $this->errorResponse(403);
        }
    }

    /* helper function processing http error status message */

    function errorResponse($code = 400, $msg = null) {
        // statuscode handling
        $header = $this->errors[$code];
        if (!(isset($header))) {
            $header = "";
        }
        header("HTTP/1.1 " . $code . " " . $header);

        // if message is present, append as text/plain
        if (isset($msg)) {
            header("Content-Type: text/plain");
            echo $msg;
        }
        die();
    }

    function checkJson() {
        if (!isset($this->json, $this->json["release"], $this->json["release"]["zipball_url"])) {
            $this->errorResponse(400, "wrong payload - github release event (application/json) expected");
        }
    }
    
    function remoteUrl() {
        if(isset($this->json)) {
            return $this->json["release"]["zipball_url"];
        } else {
            return null;
        }
    }

}

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

    // get post data
    $input = file_get_contents('php://input');
    $config->json = json_decode($input, true);

    $config->checkJson();

    // copy remote zip file locally
    $file = $config->remoteUrl();   
    $opts = array(
        'http' => array(
            'method' => "GET",
            'user_agent' => "NWComamnds release update poller",
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
    <title>Latest release</title>
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
      <a href='./".ZIP_FILENAME."' target='_blank'>Latest Release (Zip)</a> for download
      </p>
      <p>Please see <a href='./".EXTRACT_FOLDER."'>".EXTRACT_FOLDER."</a> for files ...</p>
     </body>
    </html>";
?>
