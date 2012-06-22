<?php

require_once __DIR__."/logging/Logging.php";
Logging::setLogPath('/var/log/airtime/zendphp.log');
require_once __DIR__."/configs/conf.php";
require_once __DIR__."/configs/ACL.php";
require_once 'propel/runtime/lib/Propel.php';
Propel::init(__DIR__."/configs/airtime-conf-production.php");
require_once __DIR__."/configs/constants.php";
require_once 'Preference.php';
require_once "DateHelper.php";
require_once "OsPath.php";
require_once __DIR__.'/controllers/plugins/RabbitMqPlugin.php';


//DateTime in PHP 5.3.0+ need a default timezone set. Set to UTC initially
//in case Application_Model_Preference::GetTimezone fails and creates needs to create
//a log entry. This log entry requires a call to date(), which then complains that
//timezone isn't set. Setting a default timezone allows us to create a a graceful log
//that getting the real timezone failed, without PHP complaining that it cannot log because
//there is no timezone :|.
date_default_timezone_set('UTC');
date_default_timezone_set(Application_Model_Preference::GetTimezone());

global $CC_CONFIG;
$CC_CONFIG['airtime_version'] = Application_Model_Preference::GetAirtimeVersion();

require_once __DIR__."/configs/navigation.php";

Zend_Validate::setDefaultNamespaces("Zend");

$front = Zend_Controller_Front::getInstance();
$front->registerPlugin(new RabbitMqPlugin());

/* The bootstrap class should only be used to initialize actions that return a view.
   Actions that return JSON will not use the bootstrap class! */
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initDoctype()
    {
        $this->bootstrap('view');
        $view = $this->getResource('view');
        $view->doctype('XHTML1_STRICT');
    }
    
    protected function _initGlobals()
    {
        $view = $this->getResource('view');
        $baseUrl = dirname($_SERVER['SCRIPT_NAME']);
        if (strcmp($baseUrl, '/') ==0) $baseUrl = "";
        
	    $view->headScript()->appendScript("var baseUrl = '$baseUrl'");
                                               
        $user = Application_Model_User::GetCurrentUser();
        if (!is_null($user)){
            $userType = $user->getType();
        } else {
            $userType = "";
        }
        $view->headScript()->appendScript("var userType = '$userType';");
        	
	}

    protected function _initHeadLink()
    {
        global $CC_CONFIG;

        $view = $this->getResource('view');
        //$baseUrl = Zend_Controller_Front::getInstance()->getBaseUrl();
        $baseUrl = dirname($_SERVER['SCRIPT_NAME']);
        if (strcmp($baseUrl, '/') ==0) $baseUrl = "";
        $CC_CONFIG['base_dir'] = $baseUrl;
        
        $view->headLink()->appendStylesheet($baseUrl.'/css/redmond/jquery-ui-1.8.8.custom.css?'.$CC_CONFIG['airtime_version']);
        $view->headLink()->appendStylesheet($baseUrl.'/css/pro_dropdown_3.css?'.$CC_CONFIG['airtime_version']);
        $view->headLink()->appendStylesheet($baseUrl.'/css/qtip/jquery.qtip.min.css?'.$CC_CONFIG['airtime_version']);
        $view->headLink()->appendStylesheet($baseUrl.'/css/styles.css?'.$CC_CONFIG['airtime_version']);
        $view->headLink()->appendStylesheet($baseUrl.'/css/masterpanel.css?'.$CC_CONFIG['airtime_version']);
    }

    protected function _initHeadScript()
    {
        global $CC_CONFIG;

        $view = $this->getResource('view');
        //$baseUrl = Zend_Controller_Front::getInstance()->getBaseUrl();
        $baseUrl = dirname($_SERVER['SCRIPT_NAME']);
        if (strcmp($baseUrl, '/') ==0) $baseUrl = "";
        		
        $view->headScript()->appendFile($baseUrl.'/js/libs/jquery-1.7.2.min.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/libs/jquery-ui-1.8.18.custom.min.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/libs/jquery.stickyPanel.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/qtip/jquery.qtip.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/jplayer/jquery.jplayer.min.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/sprintf/sprintf-0.7-beta1.js?'.$CC_CONFIG['airtime_version'],'text/javascript');

        //scripts for now playing bar
        $view->headScript()->appendFile($baseUrl.'/js/airtime/dashboard/helperfunctions.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/airtime/dashboard/dashboard.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/airtime/dashboard/versiontooltip.js?'.$CC_CONFIG['airtime_version'],'text/javascript');


        $view->headScript()->appendFile($baseUrl.'/js/airtime/common/common.js?'.$CC_CONFIG['airtime_version'],'text/javascript');

        if (Application_Model_Preference::GetPlanLevel() != "disabled"
                && !($_SERVER['REQUEST_URI'] == $baseUrl.'/Dashboard/stream-player' || 
                     strncmp($_SERVER['REQUEST_URI'], $baseUrl.'/audiopreview/audio-preview', strlen($baseUrl.'/audiopreview/audio-preview'))==0)) {
			$client_id = Application_Model_Preference::GetClientId();
            $view->headScript()->appendScript("var livechat_client_id = '$client_id';");
            $view->headScript()->appendFile($baseUrl . '/js/airtime/common/livechat.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        }
        if(isset($CC_CONFIG['demo']) && $CC_CONFIG['demo'] == 1){
            $view->headScript()->appendFile($baseUrl.'/js/libs/google-analytics.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        }
    }

    protected function _initViewHelpers()
    {
        $view = $this->getResource('view');
        $view->addHelperPath('../application/views/helpers', 'Airtime_View_Helper');
    }

    protected function _initTitle()
    {
        $view = $this->getResource('view');
        $view->headTitle(Application_Model_Preference::GetHeadTitle());
    }

    protected function _initZFDebug()
    {
        if (APPLICATION_ENV == "development"){
            $autoloader = Zend_Loader_Autoloader::getInstance();
            $autoloader->registerNamespace('ZFDebug');

            $options = array(
                'plugins' => array('Variables',
                                   'Exception',
                                   'Memory',
                                   'Time')
            );
            $debug = new ZFDebug_Controller_Plugin_Debug($options);

            $this->bootstrap('frontController');
            $frontController = $this->getResource('frontController');
            $frontController->registerPlugin($debug);
        }
    }

    protected function _initRouter()
    {
    	$front = Zend_Controller_Front::getInstance();
        $router = $front->getRouter();

        $router->addRoute(
            'password-change',
            new Zend_Controller_Router_Route('password-change/:user_id/:token', array(
                'module' => 'default',
                'controller' => 'login',
                'action' => 'password-change',
            )));
    }
}

