<?php

define("SECRET_TOKEN", "BetterChangeThisOneHere");
define("ALLOWED_IPS", "94.45.140.46,207.97.227.253,50.57.128.197");//default github push ips

define("ZIP_FILENAME", "latest.zip");
define("EXTRACT_FOLDER", "latest");

define("PROJECT_NAME", "My Project");


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

?>
