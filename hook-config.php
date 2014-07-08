<?php

define("SECRET_TOKEN", "BetterChangeThisOneHere");

// see https://help.github.com/articles/what-ip-addresses-does-github-use-that-i-should-whitelist
define("ALLOWED_IPS", "192.30.252.30/5"); 


define("ZIP_FILENAME", "latest.zip");
define("EXTRACT_FOLDER", "latest");

define("PROJECT_NAME", "My Project");

/**
 * wrapping all configuration and access stuff in an own class.
 */
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

   /**
    * do security checks.
    *
    * checking IP and signature header
    */
    private function verify($payload) {
        // check IP
        if (!(in_array($_SERVER["REMOTE_ADDR"], $this->allowed_ips))) {
            $this->httpResponse(403);
        }

        // check prerequisities
        $signature = $_SERVER["HTTP_X_HUB_SIGNATURE"];
        if(!(isset($payload))||!(isset($signature))) {
            $this->httpResponse(403);
        }
        //check signature
        $secret = "sha1=".hash_hmac('sha1', $payload, SECRET_TOKEN);
        if ( strcmp($signature, $secret ) != 0 ) {
            $this->httpResponse(403);
        }
    }

   /**
    * process payload.
    *
    * special handling for ping, parse json
    */
    function setPayload($input) {
        $this->verify($input);

         // handle ping
        if($_SERVER["HTTP_X_GITHUB_EVENT"] === "ping") {
            $this->httpResponse(200, "ping successfull (checks passed)");
        }

        $this->json = json_decode($input, true);
        if (!isset($this->json, $this->json["release"], $this->json["release"]["zipball_url"])) {
            $this->httpResponse(400, "wrong payload - github release event (application/json) expected");
        }        

        // write github url to file
        $github = "github.url";
        file_put_contents($github, $this->json["repository"]["html_url"]);
    }    

   /**
    * return http statuscode.
    *
    * stops further processing, send stauscode directly
    * default statuscode 400, optional message
    */
    function httpResponse($code = 400, $msg = null) {
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
   
   /**
    * get remote url for file download.
    */
    function remoteUrl() {
        if(isset($this->json)) {
            return $this->json["release"]["zipball_url"];
        } else {
            return null;
        }
    }

}

?>
