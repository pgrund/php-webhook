<?php

define("SECRET_TOKEN", "BetterChangeThisOneHere");
define("ALLOWED_IPS", "192.30.252.0/22"); // see https://help.github.com/articles/what-ip-addresses-does-github-use-that-i-should-whitelist
//"94.45.140.46,207.97.227.253,50.57.128.197"  //==> old github push ips

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

    function __construct() {
        // handle CIDR notation for ips (only last net segment)
        $this->allowed_ips = array();
        foreach (explode(",", ALLOWED_IPS) as $ip) {
            if(!(strpos($ip,"/"))) {
                array_push($this->allowed_ips, $ip);
            } else {
                $pos = strripos($ip, ".");
                $newIP = substr($ip, 0, $pos);
                $rest = substr($ip, $pos+1, strlen($ip));               
                $bounds = explode("/",$rest);
                for ($i=$bounds[0]; $i<=$bounds[1] ; $i++) { 
                   array_push($this->allowed_ips, $newIP.".".$i);
                }
            }
        }
    }

    function verify($payload) {
        // check IP
        if (!(in_array($_SERVER["REMOTE_ADDR"], $this->allowed_ips))) {
            $this->errorResponse(403);
        }
        // check signature
        $signature = $_SERVER["HTTP_X_HUB_SIGNATURE"];
        if(!(isset($payload))||!(isset($signature))) {
            $this->errorResponse(403, "missing payload");
        }

        $secret = "sha1=".hash_hmac('sha1', $input, SECRET_TOKEN);
        if ( $signature === $secret ) {
            $this->errorResponse(403);
        }

        // handle ping
        if($_SERVER["HTTP_X_GitHub_Event"] === "ping") {
            $this->errorResponse(200, "ping successfull (checks passed)");
        }
    }

    function setPayload($input) {
        $this->verify($input);
        $this->json = json_decode($input, true);
        if (!isset($this->json, $this->json["release"], $this->json["release"]["zipball_url"])) {
            $this->errorResponse(400, "wrong payload - github release event (application/json) expected");
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
   
    function remoteUrl() {
        if(isset($this->json)) {
            return $this->json["release"]["zipball_url"];
        } else {
            return null;
        }
    }

}

?>
