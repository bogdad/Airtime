<?php

class Application_Model_Preference
{

    public static function SetValue($key, $value, $isUserValue = false){       
        try {
            $con = Propel::getConnection();

            //called from a daemon process
            if(!class_exists("Zend_Auth", false) || !Zend_Auth::getInstance()->hasIdentity()) {
                $id = NULL;
            }
            else {
                $auth = Zend_Auth::getInstance();
                $id = $auth->getIdentity()->id;
            }

            $key = pg_escape_string($key);
            $value = pg_escape_string($value);

            //Check if key already exists
            $sql = "SELECT COUNT(*) FROM cc_pref"
            ." WHERE keystr = '$key'";

            //For user specific preference, check if id matches as well
            if($isUserValue) {
                $sql .= " AND subjid = '$id'";
            }
            
            $result = $con->query($sql)->fetchColumn(0);

            if($value == "") {
                $value = "NULL";
            }else {
                $value = "'$value'";
            }
            
            if($result == 1) {
                // result found
                if(is_null($id) || !$isUserValue) {
                    // system pref
                    $sql = "UPDATE cc_pref"
                    ." SET subjid = NULL, valstr = $value"
                    ." WHERE keystr = '$key'";
                } else {
                    // user pref
                    $sql = "UPDATE cc_pref"
                    . " SET valstr = $value"
                    . " WHERE keystr = '$key' AND subjid = $id";
                }
            } else {
                // result not found
                if(is_null($id) || !$isUserValue) {
                    // system pref
                    $sql = "INSERT INTO cc_pref (keystr, valstr)"
                    ." VALUES ('$key', $value)";
                } else {
                    // user pref
                    $sql = "INSERT INTO cc_pref (subjid, keystr, valstr)"
                    ." VALUES ($id, '$key', $value)";
                }
            }

            $con->exec($sql);
        
        } catch (Exception $e){
            header('HTTP/1.0 503 Service Unavailable');
            Logging::log("Could not connect to database: ".$e->getMessage());
            exit;
        }

    }

    public static function GetValue($key, $isUserValue = false){       
        try {
            $con = Propel::getConnection();

            //Check if key already exists
            $sql = "SELECT COUNT(*) FROM cc_pref"
            ." WHERE keystr = '$key'";
            //For user specific preference, check if id matches as well
            if ($isUserValue) {
                $auth = Zend_Auth::getInstance();
                if($auth->hasIdentity()) {
                    $id = $auth->getIdentity()->id;
                    $sql .= " AND subjid = '$id'";
                }
            }
            $result = $con->query($sql)->fetchColumn(0);
            if ($result == 0)
                return "";
            else {
                $sql = "SELECT valstr FROM cc_pref"
                ." WHERE keystr = '$key'";

                //For user specific preference, check if id matches as well
                if($isUserValue && $auth->hasIdentity()) {
                    $sql .= " AND subjid = '$id'";
                }

                $result = $con->query($sql)->fetchColumn(0);
                return ($result !== false) ? $result : "";
            }
        } catch (Exception $e) {
            header('HTTP/1.0 503 Service Unavailable');
            Logging::log("Could not connect to database: ".$e->getMessage());
            exit;            
        }
    }

    public static function GetHeadTitle(){
        $title = self::GetValue("station_name");
        $defaultNamespace->title = $title;
        if (strlen($title) > 0)
            $title .= " - ";

        return $title."Airtime";
    }

    public static function SetHeadTitle($title, $view=null){
        self::SetValue("station_name", $title);

        // in case this is called from airtime-saas script
        if($view !== null){
            //set session variable to new station name so that html title is updated.
            //should probably do this in a view helper to keep this controller as minimal as possible.
            $view->headTitle()->exchangeArray(array()); //clear headTitle ArrayObject
            $view->headTitle(self::GetHeadTitle());
        }

        $eventType = "update_station_name";
        $md = array("station_name"=>$title);

        Application_Model_RabbitMq::SendMessageToPypo($eventType, $md);
    }

    /**
     * Set the furthest date that a never-ending show
     * should be populated until.
     *
     * @param DateTime $dateTime
     *        A row from cc_show_days table
     */
    public static function SetShowsPopulatedUntil($dateTime) {
        self::SetValue("shows_populated_until", $dateTime->format("Y-m-d H:i:s"));
    }

    /**
     * Get the furthest date that a never-ending show
     * should be populated until.
     *
     * Returns null if the value hasn't been set, otherwise returns
     * a DateTime object representing the date.
     *
     * @return DateTime (in UTC Timezone)
     */
    public static function GetShowsPopulatedUntil() {
        $date = self::GetValue("shows_populated_until");

        if ($date == ""){
            return null;
        } else {
            return new DateTime($date, new DateTimeZone("UTC"));
        }
    }

    public static function SetDefaultFade($fade) {
        self::SetValue("default_fade", $fade);
    }

    public static function GetDefaultFade() {
        $fade = self::GetValue("default_fade");
        
        if ($fade === "") {
            // the default value of the fade is 00.500000
            return "00.500000";
        }
        
        // we need this function to work with 2.0 version on default_fade value in cc_pref
        // it has 00:00:00.000000 format where in 2.1 we have 00.000000 format
        if(preg_match("/([0-9]{2}):([0-9]{2}):([0-9]{2}).([0-9]{6})/", $fade, $matches) == 1 && count($matches) == 5){
            $out = 0;
            $out += intval($matches[1] * 3600);
            $out += intval($matches[2] * 60);
            $out += intval($matches[3]);
            $out .= ".$matches[4]";
            $fade = $out;
        }
        
        $fade = number_format($fade, 6);
        //fades need 2 leading zeros for DateTime conversion
        $fade = str_pad($fade, 9, "0", STR_PAD_LEFT);
        return $fade;
    }

    public static function SetDefaultTransitionFade($fade) {
        self::SetValue("default_transition_fade", $fade);

        $eventType = "update_transition_fade";
        $md = array("transition_fade"=>$fade);
        Application_Model_RabbitMq::SendMessageToPypo($eventType, $md);
    }

    public static function GetDefaultTransitionFade() {
        $transition_fade = self::GetValue("default_transition_fade");
        if($transition_fade == ""){
            $transition_fade = "00.000000";
        }
        return $transition_fade;
    }

    public static function SetStreamLabelFormat($type){
        self::SetValue("stream_label_format", $type);

        $eventType = "update_stream_format";
        $md = array("stream_format"=>$type);

        Application_Model_RabbitMq::SendMessageToPypo($eventType, $md);
    }

    public static function GetStreamLabelFormat(){
        return self::getValue("stream_label_format");
    }

    public static function GetStationName(){
        return self::getValue("station_name");
    }

    public static function SetAutoUploadRecordedShowToSoundcloud($upload) {
        self::SetValue("soundcloud_auto_upload_recorded_show", $upload);
    }

    public static function GetAutoUploadRecordedShowToSoundcloud() {
        return self::GetValue("soundcloud_auto_upload_recorded_show");
    }

    public static function SetSoundCloudUser($user) {
        self::SetValue("soundcloud_user", $user);
    }

    public static function GetSoundCloudUser() {
        return self::GetValue("soundcloud_user");
    }

    public static function SetSoundCloudPassword($password) {
        if (strlen($password) > 0)
            self::SetValue("soundcloud_password", $password);
    }

    public static function GetSoundCloudPassword() {
        return self::GetValue("soundcloud_password");
    }

    public static function SetSoundCloudTags($tags) {
        self::SetValue("soundcloud_tags", $tags);
    }

    public static function GetSoundCloudTags() {
        return self::GetValue("soundcloud_tags");
    }

    public static function SetSoundCloudGenre($genre) {
        self::SetValue("soundcloud_genre", $genre);
    }

    public static function GetSoundCloudGenre() {
        return self::GetValue("soundcloud_genre");
    }

    public static function SetSoundCloudTrackType($track_type) {
        self::SetValue("soundcloud_tracktype", $track_type);
    }

    public static function GetSoundCloudTrackType() {
        return self::GetValue("soundcloud_tracktype");
    }

    public static function SetSoundCloudLicense($license) {
        self::SetValue("soundcloud_license", $license);
    }

    public static function GetSoundCloudLicense() {
        return self::GetValue("soundcloud_license");
    }

    public static function SetAllow3rdPartyApi($bool) {
        self::SetValue("third_party_api", $bool);
    }

    public static function GetAllow3rdPartyApi() {
        $val = self::GetValue("third_party_api");
        if (strlen($val) == 0){
            return "0";
        } else {
            return $val;
        }
    }

    public static function SetPhone($phone){
    	self::SetValue("phone", $phone);
    }

    public static function GetPhone(){
    	return self::GetValue("phone");
    }

	public static function SetEmail($email){
    	self::SetValue("email", $email);
    }

    public static function GetEmail(){
    	return self::GetValue("email");
    }

	public static function SetStationWebSite($site){
    	self::SetValue("station_website", $site);
    }

    public static function GetStationWebSite(){
    	return self::GetValue("station_website");
    }

	public static function SetSupportFeedback($feedback){
    	self::SetValue("support_feedback", $feedback);
    }

    public static function GetSupportFeedback(){
    	return self::GetValue("support_feedback");
    }

	public static function SetPublicise($publicise){
    	self::SetValue("publicise", $publicise);
    }

    public static function GetPublicise(){
    	return self::GetValue("publicise");
    }

	public static function SetRegistered($registered){
    	self::SetValue("registered", $registered);
    }

    public static function GetRegistered(){
    	return self::GetValue("registered");
    }

	public static function SetStationCountry($country){
    	self::SetValue("country", $country);
    }

    public static function GetStationCountry(){
    	return self::GetValue("country");
    }

	public static function SetStationCity($city){
    	self::SetValue("city", $city);
    }

	public static function GetStationCity(){
    	return self::GetValue("city");
    }

	public static function SetStationDescription($description){
    	self::SetValue("description", $description);
    }

	public static function GetStationDescription(){
    	return self::GetValue("description");
    }

    public static function SetTimezone($timezone){
        self::SetValue("timezone", $timezone);
        date_default_timezone_set($timezone);
        $md = array("timezone" => $timezone);
    }

    public static function GetTimezone(){
        return self::GetValue("timezone");
    }

    public static function SetStationLogo($imagePath){
    	if(!empty($imagePath)){
	    	$image = @file_get_contents($imagePath);
	    	$image = base64_encode($image);
	    	self::SetValue("logoImage", $image);
    	}
    }

	public static function GetStationLogo(){
    	return self::GetValue("logoImage");
    }

    public static function GetUniqueId(){
    	return self::GetValue("uniqueId");
    }

    public static function GetCountryList()
    {
    	$con = Propel::getConnection();
    	$sql = "SELECT * FROM cc_country";
    	$res =  $con->query($sql)->fetchAll();
    	$out = array();
    	$out[""] = "Select Country";
    	foreach($res as $r){
    		$out[$r["isocode"]] = $r["name"];
    	}
    	return $out;
    }

    public static function GetSystemInfo($returnArray=false, $p_testing=false)
    {
    	exec('/usr/bin/airtime-check-system --no-color', $output);
    	$output = preg_replace('/\s+/', ' ', $output);

    	$systemInfoArray = array();
    	foreach( $output as $key => &$out){
    		$info = explode('=', $out);
    		if(isset($info[1])){
    			$key = str_replace(' ', '_', trim($info[0]));
    			$key = strtoupper($key);
    			if ($key == 'WEB_SERVER' || $key == 'CPU' || $key == 'OS'  || $key == 'TOTAL_RAM' ||
    	                  $key == 'FREE_RAM' || $key == 'AIRTIME_VERSION' || $key == 'KERNAL_VERSION'  || 
    	                  $key == 'MACHINE_ARCHITECTURE' || $key == 'TOTAL_MEMORY_MBYTES' || $key == 'TOTAL_SWAP_MBYTES' ||
    	                  $key == 'PLAYOUT_ENGINE_CPU_PERC' ) {
    	            if($key == 'AIRTIME_VERSION'){
    	                // remove hash tag on the version string
    	                $version = explode('+', $info[1]);
    			        $systemInfoArray[$key] = $version[0];
    	            }else{
    	                $systemInfoArray[$key] = $info[1];
    	            }
    	        }
    		}
    	}
    	
    	$outputArray = array();

    	$outputArray['LIVE_DURATION'] = Application_Model_LiveLog::GetLiveShowDuration($p_testing);
    	$outputArray['SCHEDULED_DURATION'] = Application_Model_LiveLog::GetScheduledDuration($p_testing);
    	$outputArray['SOUNDCLOUD_ENABLED'] = self::GetUploadToSoundcloudOption();
        if ($outputArray['SOUNDCLOUD_ENABLED']) {
    	    $outputArray['NUM_SOUNDCLOUD_TRACKS_UPLOADED'] = Application_Model_StoredFile::getSoundCloudUploads();
        } else {
            $outputArray['NUM_SOUNDCLOUD_TRACKS_UPLOADED'] = NULL;
        }
    	$outputArray['STATION_NAME'] = self::GetStationName();
    	$outputArray['PHONE'] = self::GetPhone();
    	$outputArray['EMAIL'] = self::GetEmail();
    	$outputArray['STATION_WEB_SITE'] = self::GetStationWebSite();
    	$outputArray['STATION_COUNTRY'] = self::GetStationCountry();
    	$outputArray['STATION_CITY'] = self::GetStationCity();
    	$outputArray['STATION_DESCRIPTION'] = self::GetStationDescription();
    	
    	// get web server info
    	if(isset($systemInfoArray["AIRTIME_VERSION_URL"])){
    	   $url = $systemInfoArray["AIRTIME_VERSION_URL"];
           $index = strpos($url,'/api/');
           $url = substr($url, 0, $index);
           
           $headerInfo = get_headers(trim($url),1);
           $outputArray['WEB_SERVER'] = $headerInfo['Server'][0];
    	}

    	$outputArray['NUM_OF_USERS'] = Application_Model_User::getUserCount();
    	$outputArray['NUM_OF_SONGS'] = Application_Model_StoredFile::getFileCount();
    	$outputArray['NUM_OF_PLAYLISTS'] = Application_Model_Playlist::getPlaylistCount();
    	$outputArray['NUM_OF_SCHEDULED_PLAYLISTS'] = Application_Model_Schedule::getSchduledPlaylistCount();
    	$outputArray['NUM_OF_PAST_SHOWS'] = Application_Model_ShowInstance::GetShowInstanceCount(gmdate("Y-m-d H:i:s"));
    	$outputArray['UNIQUE_ID'] = self::GetUniqueId();
    	$outputArray['SAAS'] = self::GetPlanLevel();
    	if ($outputArray['SAAS'] != 'disabled') {
    	    $outputArray['TRIAL_END_DATE'] = self::GetTrialEndingDate();
    	} else {
            $outputArray['TRIAL_END_DATE'] = NULL;
        }
    	$outputArray['INSTALL_METHOD'] = self::GetInstallMethod();
    	$outputArray['NUM_OF_STREAMS'] = self::GetNumOfStreams();
    	$outputArray['STREAM_INFO'] = Application_Model_StreamSetting::getStreamInfoForDataCollection();

    	$outputArray = array_merge($systemInfoArray, $outputArray);

    	$outputString = "\n";
    	foreach($outputArray as $key => $out){
    	    if($key == 'TRIAL_END_DATE' && ($out != '' || $out != 'NULL')){
    	        continue;
    	    }
    	    if($key == "STREAM_INFO"){
    	        $outputString .= $key." :\n";
    	        foreach($out as $s_info){
    	            foreach($s_info as $k => $v){
    	                $outputString .= "\t".strtoupper($k)." : ".$v."\n";
    	            }
    	        }
    	    }else if ($key == "SOUNDCLOUD_ENABLED") {
    	        if ($out) {
    	            $outputString .= $key." : TRUE\n";
    	        } else if (!$out) {
    	            $outputString .= $key." : FALSE\n";
    	        }
    	    }else if ($key == "SAAS") {
                if (strcmp($out, 'disabled')!=0) {
                    $outputString .= $key.' : '.$out."\n";
                }
    	    }else{
    	        $outputString .= $key.' : '.$out."\n";
    	    }
    	}
    	if($returnArray){
    	    $outputArray['PROMOTE'] = self::GetPublicise();
    		$outputArray['LOGOIMG'] = self::GetStationLogo();
    	    return $outputArray;
    	}else{
    	    return $outputString;
    	}
    }

    public static function GetInstallMethod(){
        $easy_install = file_exists('/usr/bin/airtime-easy-setup');
        $debian_install = file_exists('/var/lib/dpkg/info/airtime.config');
        if($debian_install){
            if($easy_install){
                return "easy_install";
            }else{
                return "debian_install";
            }
        }else{
            return "manual_install";
        }
    }

    public static function SetRemindMeDate(){
    	$weekAfter = mktime(0, 0, 0, gmdate("m"), gmdate("d")+7, gmdate("Y"));
   		self::SetValue("remindme", $weekAfter);
    }

    public static function GetRemindMeDate(){
        return self::GetValue("remindme");
    }

    public static function SetImportTimestamp(){
        $now = time();
        if(self::GetImportTimestamp()+5 < $now){
            self::SetValue("import_timestamp", $now);
        }
    }

    public static function GetImportTimestamp(){
        return self::GetValue("import_timestamp");
    }

    public static function GetStreamType(){
        $st = self::GetValue("stream_type");
        return explode(',', $st);
    }

    public static function GetStreamBitrate(){
        $sb = self::GetValue("stream_bitrate");
        return explode(',', $sb);
    }

    public static function SetPrivacyPolicyCheck($flag){
        self::SetValue("privacy_policy", $flag);
    }

    public static function GetPrivacyPolicyCheck(){
        return self::GetValue("privacy_policy");
    }

    public static function SetNumOfStreams($num){
        self::SetValue("num_of_streams", intval($num));
    }

    public static function GetNumOfStreams(){
        return self::GetValue("num_of_streams");
    }

    public static function SetMaxBitrate($bitrate){
        self::SetValue("max_bitrate", intval($bitrate));
    }

    public static function GetMaxBitrate(){
        return self::GetValue("max_bitrate");
    }

    public static function SetPlanLevel($plan){
        self::SetValue("plan_level", $plan);
    }

    public static function GetPlanLevel(){
        $plan = self::GetValue("plan_level");
        if(trim($plan) == ''){
            $plan = 'disabled';
        }
        return $plan;
    }

    public static function SetTrialEndingDate($date){
        self::SetValue("trial_end_date", $date);
    }

    public static function GetTrialEndingDate(){
        return self::GetValue("trial_end_date");
    }

    public static function SetEnableStreamConf($bool){
        self::SetValue("enable_stream_conf", $bool);
    }

    public static function GetEnableStreamConf(){
        if(self::GetValue("enable_stream_conf") == Null){
            return "true";
        }
        return self::GetValue("enable_stream_conf");
    }

    public static function GetAirtimeVersion(){
        if (defined('APPLICATION_ENV') && APPLICATION_ENV == "development" && function_exists('exec')){
            $version = exec("git rev-parse --short HEAD 2>/dev/null", $out, $return_code);
            if ($return_code == 0){
                return self::GetValue("system_version")."+".$version;
            }
        }
        return self::GetValue("system_version");
    }

    public static function GetLatestVersion(){
        $latest = self::GetValue("latest_version");
        if($latest == null || strlen($latest) == 0) {
            return self::GetAirtimeVersion();
        } else {
            return $latest;
        }
    }

    public static function SetLatestVersion($version){
        $pattern = "/^[0-9]+\.[0-9]+\.[0-9]+/";
        if(preg_match($pattern, $version)) {
            self::SetValue("latest_version", $version);
        }
    }

    public static function GetLatestLink(){
        $link = self::GetValue("latest_link");
        if($link == null || strlen($link) == 0) {
            return 'http://airtime.sourcefabric.org';
        } else {
            return $link;
        }
    }

    public static function SetLatestLink($link){
        $pattern = "#^(http|https|ftp)://" .
                    "([a-zA-Z0-9]+\.)*[a-zA-Z0-9]+" .
                    "(/[a-zA-Z0-9\-\.\_\~\:\?\#\[\]\@\!\$\&\'\(\)\*\+\,\;\=]+)*/?$#";
        if(preg_match($pattern, $link)) {
            self::SetValue("latest_link", $link);
        }
    }

    public static function SetUploadToSoundcloudOption($upload) {
        self::SetValue("soundcloud_upload_option", $upload);
    }

    public static function GetUploadToSoundcloudOption() {
        return self::GetValue("soundcloud_upload_option");
    }

    public static function SetSoundCloudDownloadbleOption($upload) {
        self::SetValue("soundcloud_downloadable", $upload);
    }

    public static function GetSoundCloudDownloadbleOption() {
        return self::GetValue("soundcloud_downloadable");
    }

    public static function SetWeekStartDay($day) {
        self::SetValue("week_start_day", $day);
    }

    public static function GetWeekStartDay() {
    	$val = self::GetValue("week_start_day");
        if (strlen($val) == 0){
            return "0";
        } else {
            return $val;
        }
    }

    /**
    * Stores the last timestamp of user updating stream setting
    */
    public static function SetStreamUpdateTimestamp() {
        $now = time();
        self::SetValue("stream_update_timestamp", $now);
    }

    /**
     * Gets the last timestamp of user updating stream setting
     */
    public static function GetStreamUpdateTimestemp() {
        $update_time = self::GetValue("stream_update_timestamp");
        if($update_time == null){
            $update_time = 0;
        }
        return $update_time;
    }

    public static function GetClientId() {
        return self::GetValue("client_id");
    }

    public static function SetClientId($id) {
        if (is_numeric($id)) {
            self::SetValue("client_id", $id);
        }
    }

    /* User specific preferences start */

    /**
     * Sets the time scale preference (agendaDay/agendaWeek/month) in Calendar.
     *
     * @param $timeScale	new time scale
     */
    public static function SetCalendarTimeScale($timeScale) {
        self::SetValue("calendar_time_scale", $timeScale, true /* user specific */);
    }

    /**
     * Retrieves the time scale preference for the current user.
     * Defaults to month if no entry exists
     */
    public static function GetCalendarTimeScale() {
        $val = self::GetValue("calendar_time_scale", true /* user specific */);
        if(strlen($val) == 0) {
            $val = "month";
        }
    	return $val;
    }

    /**
     * Sets the number of entries to show preference in library under Playlist Builder.
     *
     * @param $numEntries	new number of entries to show
     */
    public static function SetLibraryNumEntries($numEntries) {
    	self::SetValue("library_num_entries", $numEntries, true /* user specific */);
    }

    /**
     * Retrieves the number of entries to show preference in library under Playlist Builder.
     * Defaults to 10 if no entry exists
     */
    public static function GetLibraryNumEntries() {
    	$val = self::GetValue("library_num_entries", true /* user specific */);
        if(strlen($val) == 0) {
            $val = "10";
        }
        return $val;
    }

    /**
     * Sets the time interval preference in Calendar.
     *
     * @param $timeInterval		new time interval
     */
    public static function SetCalendarTimeInterval($timeInterval) {
        self::SetValue("calendar_time_interval", $timeInterval, true /* user specific */);
    }

    /**
     * Retrieves the time interval preference for the current user.
     * Defaults to 30 min if no entry exists
     */
    public static function GetCalendarTimeInterval() {
    	$val = self::GetValue("calendar_time_interval", true /* user specific */);
        if(strlen($val) == 0) {
            $val = "30";
        }
        return $val;
    }

    public static function SetDiskQuota($value){
        self::SetValue("disk_quota", $value, false);
    }

    public static function GetDiskQuota(){
        $val = self::GetValue("disk_quota");
        if(strlen($val) == 0) {
            $val = "0";
        }
        return $val;
    }

    public static function SetLiveSteamMasterUsername($value){
        self::SetValue("live_stream_master_username", $value, false);
    }

    public static function GetLiveSteamMasterUsername(){
        return self::GetValue("live_stream_master_username");
    }

    public static function SetLiveSteamMasterPassword($value){
        self::SetValue("live_stream_master_password", $value, false);
    }

    public static function GetLiveSteamMasterPassword(){
        return self::GetValue("live_stream_master_password");
    }

    public static function SetSourceStatus($sourcename, $status){
        self::SetValue($sourcename, $status, false);
    }

    public static function GetSourceStatus($sourcename){
        $value = self::GetValue($sourcename);
        if($value == null || $value == "false"){
            return false;
        }else{
            return true;
        }
    }

    public static function SetSourceSwitchStatus($sourcename, $status){
        self::SetValue($sourcename."_switch", $status, false);
    }

    public static function GetSourceSwitchStatus($sourcename){
        $value = self::GetValue($sourcename."_switch");
        if($value == null || $value == "off"){
            return "off";
        }else{
            return "on";
        }
    }

    public static function SetMasterDJSourceConnectionURL($value){
        self::SetValue("master_dj_source_connection_url", $value, false);
    }

    public static function GetMasterDJSourceConnectionURL(){
        return self::GetValue("master_dj_source_connection_url");
    }
    
    public static function SetLiveDJSourceConnectionURL($value){
        self::SetValue("live_dj_source_connection_url", $value, false);
    }

    public static function GetLiveDJSourceConnectionURL(){
        return self::GetValue("live_dj_source_connection_url");
    }
    
    /* Source Connection URL override status starts */
    public static function GetLiveDjConnectionUrlOverride(){
        return self::GetValue("live_dj_connection_url_override");
    }
    
    public static function SetLiveDjConnectionUrlOverride($value){
        self::SetValue("live_dj_connection_url_override", $value, false);
    }
    
    public static function GetMasterDjConnectionUrlOverride(){
        return self::GetValue("master_dj_connection_url_override");
    }
    
    public static function SetMasterDjConnectionUrlOverride($value){
        self::SetValue("master_dj_connection_url_override", $value, false);
    }
    /* Source Connection URL override status ends */
    
    public static function SetAutoTransition($value){
        self::SetValue("auto_transition", $value, false);
    }
    
    public static function GetAutoTransition(){
        return self::GetValue("auto_transition");
    }
    
    public static function SetAutoSwitch($value){
        self::SetValue("auto_switch", $value, false);
    }
    
    public static function GetAutoSwitch(){
        return self::GetValue("auto_switch");
    }
    
    public static function SetEnableSystemEmail($upload) {
        self::SetValue("enable_system_email", $upload);
    }
    
    public static function GetEnableSystemEmail() {
        $v =  self::GetValue("enable_system_email");
        
        if ($v === "") {
            return 0;
        }
        
        return $v;
    }
    
    public static function SetSystemEmail($value) {
        self::SetValue("system_email", $value, false);
    }
    
    public static function GetSystemEmail() {
        return self::GetValue("system_email");
    }
    
    public static function SetMailServerConfigured($value) {
	    self::SetValue("mail_server_configured", $value, false);
	}
	
	public static function GetMailServerConfigured() {
	    return self::GetValue("mail_server_configured");
	}
	
	public static function SetMailServer($value) {
	    self::SetValue("mail_server", $value, false);
	}
	
	public static function GetMailServer() {
	    return self::GetValue("mail_server");
	}
	
	public static function SetMailServerEmailAddress($value) {
	    self::SetValue("mail_server_email_address", $value, false);
	}
	
	public static function GetMailServerEmailAddress() {
	    return self::GetValue("mail_server_email_address");
	}
	
	public static function SetMailServerPassword($value) {
	    self::SetValue("mail_server_password", $value, false);
	}
	
	public static function GetMailServerPassword() {
	    return self::GetValue("mail_server_password");
	}
	
	public static function SetMailServerPort($value) {
	    self::SetValue("mail_server_port", $value, false);
	}
	
	public static function GetMailServerPort() {
	    return self::GetValue("mail_server_port");
	}
    /* User specific preferences end */

    public static function ShouldShowPopUp(){
        $today = mktime(0, 0, 0, gmdate("m"), gmdate("d"), gmdate("Y"));
        $remindDate = Application_Model_Preference::GetRemindMeDate();
        if($remindDate == NULL || $today >= $remindDate){
            return true;
        }
    }
}

