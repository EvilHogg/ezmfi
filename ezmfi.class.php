<?php

/*************************
*** mFI Pro Class v1.0 ***
****** By EvilHogg *******
**************************

-No external libraries required.
-Please ensure fopen is enabled in php.ini.




**************************
***** Usage Examples *****
**************************


********************
** Init the class **
********************

//Without logging
$mfi = new ezmfi("192.168.1.1","myusername","mypassword");

//With logging
$mfi = new ezmfi("192.168.1.1","myusername","mypassword",true);


*******************
** Outlet Status **
*******************

Upon init you will login and get the power status of all outlets as the following array. The amount of outlets is auto-detected.

$mfi->mFIOutletStatus
    Array
    (
        [1] => 0
        [2] => 0
        [3] => 0
        [4] => 0
        [5] => 0
        [6] => 0
        [7] => 0
        [8] => 1
    )

Additionally, you can get the raw/detailed outlet status.
$mfi->mFIOutletStatusDetail
    Array
    (
    [1] => Array (                   <--Port number as array index
        [port] => 1                  <--Port number (again)
        [output] => 1                <--Turned on (1) or off (0)
        [power] => 24.476951122      <--Watts
        [energy] => 5.625            <--kW per hour
        [enabled] => 0               <--Not sure
        [current] => 0.368921756     <--Amps
        [voltage] => 118.110996246   <--Voltage
        [powerfactor] => 0.561736598 <--Not sure
        [relay] => 1                 <--Status of the relay itself. 1 is on 0 is off
        [lock] => 0                  <--If a port is locked it will be greyed out in the gui
        )
    )
    

********************
** On Demand Read **
********************

True if ON
False if OFF 
Reading an outlet also updates the mFIOutletStatus variable for all outlets

$mfi->readOutlet("1");
    -or-
$mfi->readOutlet(1);

***********************
** On Demand Refresh **
***********************

True if success
False if fail
Refreshes the mFIOutletStatus and mFIOutletStatusDetail arrays
$mfi->refresh();

************************************
** Turn an outlet on,off,or cycle **
************************************

$mfi->setOutlet("1","ccl");
    -or-
$mfi->setOutlet("1","on");
    -or-
$mfi->setOutlet(1,"on");
    -or-
$mfi->setOutlet("1",1);
    -or-
$mfi->setOutlet("1,2,3,5","on");

****************************************
** Turn ALL outlets on, off, or cycle **
****************************************

$mfi->setAllOutlets("off");
    -or-
$mfi->setAllOutlets(0);
    -or-
$mfi->setAllOutlets("ccl");

*/

class ezmfi {
    
   public $mFIAuth = false; // <-- Keeps track of if the user is logged in.
   public $mFICookie = "";  // <-- Cookie
   public $mFINumPorts = 1; // <-- Number of ports on outlet. Autodetected but defaults to 1
   public $mFIOutletStatus = array(); // <-- on/off outlet status. Updated on demand or at login
   public $mFIOutletStatusDetail = array(); // <-- All/raw outlet information
   
   public $mFIUsername;
   public $mFIPassword;
   public $mFIIP;
   
   public $verbose; // <-- Prints debug information if set to true, defaults to false
   public $mFILog = ""; // <-- Alternatively you can echo the log out when you are done
    
   /**************
   ** CONSTRUCT **
   **************/
   /*
        Logs in and reads outlets. Passed IP, username, password.
   */
   
   public function __construct ($ip = false, $username = false, $password = false, $verbose = false) {
        
        // ** Set verbose ** //
        $this->verbose = $verbose;
        if ($verbose == true) {
            $verbose = "True";
        } else {
            $verbose = "False";
        }
        
        // ** Set default username and password below ** //
        if (!$ip || !$username || !$password) {
            $this->mFIIP = "192.168.1.100"; // YOUR IP OR URL HERE
            $this->mFIUsername = "ubnt"; // Use strong usernames and passwords, this is just an example
            $this->mFIPassword = "ubnt"; // Use strong usernames and passwords, this is just an example
        } else {
            $this->mFIIP = trim($ip);
            $this->mFIUsername = $username;
            $this->mFIPassword = $password;
        }
        
        // ** Just incase the user put http:// in **//
        $this->mFIIP = preg_replace('/http:\/\//', "", $this->mFIIP);
        
        self::verbose("Started mFI with:\nIP      : ".$this->mFIIP."</br>\n  -Username: ".$this->mFIUsername."</br>\n  -Password: ".$this->mFIPassword."</br>\n  -Verbose : ".$verbose);
        
        // ** Login ** //
        self::verbose("Attempting login to ".$this->mFIIP."");
        if (self::login()) {
            if ($this->mFIAuth = true) {
                self::verbose("Login successful");
                return true;
            }
        }
        self::verbose("Login failed");
        return false;
   }
    
    /*************
    ** DESTRUCT **
    *************/
    /*
        Logs out.....
    */
    
    public function __destruct() {
        if ($this->mFIAuth == true) {
            self::logout();
        } else {
            self::verbose("Already logged out");
        }
    }
    
    /**********
    ** LOGIN **
    **********/
    /**
        Logs into mFI strip. true is success false is fail 
        Sets mFIAuth, mFICookie, and mFINumPorts variables
    **/

    public function login () {
        
        // ** Cookie Check ** //
        if ($this->mFICookie == "") {
            self::verbose("Cookie not set, getting");
            $result = self::get("/"); // GET to / for cookie
            if (!preg_match('/Set\-Cookie\:.*?\;\s/',$result,$matches)) {
                self::verbose("Unable to get cookie");
                return false;
            }

            // ** Parse/Set Cookie ** //
            $cookie = preg_split('/Set\-Cookie\:\s/', trim($matches[0]));
            self::verbose("Got cookie " . trim($cookie[1]) . " ui_language=en_US");
            $this->mFICookie = trim($cookie[1]) . " ui_language=en_US";
        } else {
            // ** Cookie already set ** //
            self::verbose("Cookie already set");
        }

        // ** POST LOGIN ** //
        $result = self::post("/login.cgi");
        if (preg_match('/403 Forbidden/', $result) || preg_match('/error/i', $result) || strlen($result) == 0) {
            self::verbose("Unable to login. Forbidden or auth error");
            self::verbose($result);
            return false;
        }
        
        // Read Outlets to set number of ports variable //
        if (!self::readAllOutlets()) {
                self::verbose("Unable to read all outlets");
                return false;
        }
        
        //print_r($this->mFIOutletStatus);
        $this->mFIAuth = true;
        return true;
    } 

    /***********
    ** LOGOUT **
    ***********/
    /**
        Logs out....
    **/
    
    public function logout () {
        if ($this->mFIAuth == true) {
            self::verbose("[mfopro] Logging out");
            self::get("/logout.cgi");
            $this->mFICookie = "";
            $this->mFIAuth = "";
        }
    }
    
    /***************
    ** SET OUTLET **
    ***************/
    /**
	Pass an outlet number and state. state can be "on|off|ccl|1|0|3" or comma separated string "1,2,3","on"
    **/

    public function setOutlet ($number,$state) {
            // ** Parse Input ** //
            if (strtolower($state) == "on" || $state == "1" || $state == 1) {
                $state = "1";
            } else if (strtolower($state) == "ccl" || strtolower($state) == "cycle" || $state == 3) {
                $state = "3";
            }else {
                $state = "0";
            }
            
            // ** Split at "," ** //
            $numbers = preg_split('/\,/', $number);
            foreach($numbers as $number) {
                if (!is_numeric($number)) {
                    continue;
                }
                
                // ** Set extra array entries if 3 (ccl) ** //
                if ($state == "3") {
                    $setOutlets["ccloff:".$number] = "0";
                    $setOutlets["cclon:".$number] = "1";
                } else {
                    $setOutlets[$number] = $state;
                }
            }
            
            // ** Prepare some variables ** //
            $host = gethostbyname($this->mFIIP);
            $setResult = true;
            $ccl = false;
            
            foreach ($setOutlets as $outletNumber=>$outletState) {
                
                // ** Build Headers ** //
                $outletNumber = preg_replace('/[^\d]/', "", $outletNumber);
                $opts = array(
                    'http' => array(
                        'method' => 'PUT',
                        'max_redirects' => '0',
                        'follow_location' => false,
                        'header' => "Cookie: " . $this->mFICookie . "\r\nContent-Type: application/x-www-form-urlencoded; charset=UTF-8\r\nConnection: keep-alive\r\nHost: ".$host."\r\nX-Requested-With: XMLHttpRequest\r\nReferer: http://".$host."/index.cgi\r\nAccept:*/*",
                        'content' => "output=".$outletState."")
                );
                
                // ** Send Request ** //
                $url = "http://".$this->mFIIP."/sensors/".$outletNumber."";
                $context = stream_context_create($opts);
                self::verbose("Performing HTTP PUT to ".$url.", output=".$outletState."");
                $stream = fopen($url, 'rb', false, $context);
                $headersTmp = stream_get_meta_data($stream);
                foreach ($headersTmp['wrapper_data'] as $header) {
                    @$headers.= $header . "\r\n";
                }
                $result = stream_get_contents($stream);
                // ** Set result always contains "success" or "fail" ** //
                if (!preg_match('/success/i', $result)) {
                    $setResult = false;
                }
                
                fclose($stream);
                sleep(.5);
            }
            
            // Read outlets again //
            self::readAllOutlets();
            return $setResult;
    }
        
    /********************
    ** SET ALL OUTLETS **
    *********************
    /**
	Turns all outlets "on|off|1|0".
    **/

    public function setAllOutlets ($state) {
            
        // ** NEEDS WORK **//
        $failed = false;
        if (strtolower(trim($state)) == "on" || $state == "1" || $state == 1) {
            $state = "1";
        } else if ($state == "3" || $state == 3 || strtolower($state) == "ccl") {
            $state = "3";
        } else {
            $state = "0";
        }
            
	    // ** MAX OUTLETS IS 8 ** //
        $x = 1;
        while ($x <= $this->mFINumPorts) {
            if (self::setOutlet($x,$state) == false) {
                self::verbose("Failed to set outlet " . $x . " to " . $state . "");
                $failed = true;
            }
            sleep(1);
            $x++;
        }
        
        // ** Update Outlet Status ** //
        self::readAllOutlets();
        if ($failed == true) {
            return false;
        }
    }
        
    /*********************
    ** READ ALL OUTLETS **
    *********************/
    /**
	Reads all outlets. Returns false if fail and array of states if true
    **/

    public function readAllOutlets () {
        
            self::verbose("Reading all outlets");
            $result = self::get("/sensors");
            $resultArray = json_decode($result,true);
           // print_r($resultArray);
            
            $x = 0;
            $sensors[0] = "test"; //placeholder. The outlets are offset by 1. Fill position 0 so subsequent pushes go to position 1.
            $power[0] = "test"; //placeholder The outlets are offset by 1. Fill position 0 so subsequent pushes go to position 1.
            while($x <= 25) { //set ports to artificially high
                if (!isset($resultArray['sensors'][$x]['output'])) {
                    break;
                }
                array_push($sensors,strtolower($resultArray['sensors'][$x]['output']));
                array_push($power,$resultArray['sensors'][$x]);
                $x++;
            }
            unset($sensors[0]);
            unset($power[0]);
            $this->mFINumPorts = count($sensors);
            $this->mFIOutletStatus = $sensors;
            $this->mFIOutletStatusDetail = $power;
            return $sensors;
        }

    /****************
    ** READ OUTLET **
    ****************/
    /**
	Reads a single outlet. Calls readall and grabs the desired outlet
    **/

    public function readOutlet ($number) {
            $sensors = self::readAllOutlets();
            if (strtolower($number) == "all") {
                return $sensors;
            }
            
            foreach ($sensors as $sensorNumber => $sensorValue) {
                if ($number == $sensorNumber) {
                    return $sensorValue;
                }
            }
            return false;
    }
    
    /************
    ** REFRESH **
    ************/
    /**
        Just a wrapper to refresh mFIOutletStatus and mFIOutletStatusDetail
    **/
    public function refresh () {
        self::verbose("Refreshing outlets");
        if (!self::readAllOutlets()) {
            self::verbose("Unable to refresh outlets");
            return false;
        }
    }
    
    /************
    ** Verbose **
    ************/
    /** 
        For verbose logging
    **/
    
    public function verbose ($sentence) {
        if ($this->verbose == true) {
            echo "[ezmfi] " . $sentence . "</br>\n";
        }
        
        $this->mFILog.= "[ezmfi] " . $sentence . "</br>\n";
    }
    
    /*************
    ** HTTP GET **
    **************/
    
    public function get ($url = "/") {

        // ** is cookie set ** //
        if ($this->mFICookie == "") {
            $cookie = "";
            $debug = "without cookie";
        } else {
            $cookie = "Cookie: " . $this->mFICookie . "\r\n";
            $debug = "with cookie";
        }
        
        // ** get http host ** //
        $host = gethostbyname($this->mFIIP);
        $url = "http://".$this->mFIIP . $url;
        $opts = array(
            'http' => array(
                'timeout' => 3,
                'method' => 'GET',
                'max_redirects' => '0',
                'follow_location' => false,
                'header' => $cookie . "Connection: Close\r\nHost: ".$host."\r\nAccept:*/*" )
        );
        
        // ** make request ** //
        $context = stream_context_create($opts);
        
        self::verbose("Performing HTTP GET to $url $debug.");
        
        @$stream = fopen($url, 'r', false, $context);
        if (!$stream) {
            @fclose($stream);
            self::verbose("Timeout while connecting to " .$url. "");
            return false;
        }
        $headersTmp = stream_get_meta_data($stream);
        foreach ($headersTmp['wrapper_data'] as $header) {
            @$headers.= $header . "\r\n";
        }
        $data = stream_get_contents($stream);
        fclose($stream);
        if (preg_match('/sensor/', $url)) {
            return($data);
        }
        return ($headers . "\r\n\r\n" . $data);
    }
    
    /**************
    ** HTTP POST **
    ***************/
    
    public function post ($url = "/") {
        
        // ** is cookie set ** //
        if ($this->mFICookie == "") {
            $cookie = "";
            $debug = "without cookie";
        } else {
            $cookie = "Cookie: " . $this->mFICookie . "\r\n";
            $debug = "with cookie";
        }
        
        // ** POST to Login Page ** //
        $retval = '';
        $boundary = '----WebKitFormBoundaryJBCkYPR5rLs3LFYK';
        $postData = array("uri" => "/", "username" => $this->mFIUsername, "password" => $this->mFIPassword, "Submit" => "Login");
        foreach($postData as $key => $value){
            $retval .= "--$boundary\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";
        }
        $retval .= "--$boundary--\r\n";
 
        // ** get http host ** //
        $host = gethostbyname($this->mFIIP);
        $url = "http://".$this->mFIIP . $url;
        $opts = array(
            'http' => array(
                'timeout' => 3,
                'method' => 'POST',
                'max_redirects' => '0',
                'follow_location' => false,
                'header' => $cookie . "Connection: keep-alive\r\nHost: ".$host."\r\nAccept:*/*\r\nContent-Type: multipart/form-data; boundary=".$boundary,
                'content' => $retval)
        );
        
        // ** make request ** //
        $context = stream_context_create($opts);
        
        self::verbose("Performing HTTP POST to $url $debug.");
        @$stream = fopen($url, 'rb', false, $context);
        if (!$stream) {
            @fclose($stream); //just incase
            self::verbose("Timeout while connecting");
            return false;
        }
        $headersTmp = stream_get_meta_data($stream);
        foreach ($headersTmp['wrapper_data'] as $header) {
            @$headers.= $header . "\r\n";
        }
        $data = stream_get_contents($stream);

        fclose($stream);
        return ($headers . "\r\n\r\n" . $data);
    }
}
?>