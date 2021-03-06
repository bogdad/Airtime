<?php

class Application_Form_LiveStreamingPreferences extends Zend_Form_SubForm
{
    
    public function init()
    {
        $isSaas = Application_Model_Preference::GetPlanLevel() == 'disabled'?false:true;
        $defaultFade = Application_Model_Preference::GetDefaultTransitionFade();
        if($defaultFade == ""){
            $defaultFade = '00.000000';
        }
        
        //Default transition fade
        $transition_fade = new Zend_Form_Element_Text("transition_fade");
        $transition_fade->setLabel("Switch Transition Fade (s)")
                        ->setFilters(array('StringTrim'))
                        ->addValidator('regex', false, array('/^[0-9]{1,2}(\.\d{1,6})?$/',
                        'messages' => 'enter a time in seconds 00{.000000}'))
                        ->setValue($defaultFade)
                        ->setDecorators(array('ViewHelper'));
        $this->addElement($transition_fade);
        
        //Master username
        $master_username = new Zend_Form_Element_Text('master_username');
        $master_username->setAttrib('autocomplete', 'off')
                        ->setAllowEmpty(true)
                        ->setLabel('Master Username')
                        ->setFilters(array('StringTrim'))
                        ->setValue(Application_Model_Preference::GetLiveSteamMasterUsername())
                        ->setDecorators(array('ViewHelper'));
        $this->addElement($master_username);
        
        //Master password
        $master_password = new Zend_Form_Element_Password('master_password');
        $master_password->setAttrib('autocomplete', 'off')
                        ->setAttrib('renderPassword','true')
                        ->setAllowEmpty(true)
                        ->setValue(Application_Model_Preference::GetLiveSteamMasterPassword())
                        ->setLabel('Master Password')
                        ->setFilters(array('StringTrim'))
                        ->setDecorators(array('ViewHelper'));
        $this->addElement($master_password);
        
        //liquidsoap harbor.input port
        if (!$isSaas) {
            $m_port = Application_Model_StreamSetting::GetMasterLiveSteamPort();
            $master_dj_port = new Zend_Form_Element_Text('master_harbor_input_port');
            $master_dj_port->setLabel("Master Source Port")
                    ->setValue($m_port)
                    ->setValidators(array(new Zend_Validate_Between(array('min'=>1024, 'max'=>49151))))
                    ->addValidator('regex', false, array('pattern'=>'/^[0-9]+$/', 'messages'=>array('regexNotMatch'=>'Only numbers are allowed.')))
                    ->setDecorators(array('ViewHelper'));
            $this->addElement($master_dj_port);
            
            $m_mount = Application_Model_StreamSetting::GetMasterLiveSteamMountPoint();
            $master_dj_mount = new Zend_Form_Element_Text('master_harbor_input_mount_point');
            $master_dj_mount->setLabel("Master Source Mount Point")
                    ->setValue($m_mount)
                    ->setValidators(array(
                            array('regex', false, array('/^[^ &<>]+$/', 'messages' => 'Invalid character entered'))))
                    ->setDecorators(array('ViewHelper'));
            $this->addElement($master_dj_mount);
            
            //liquidsoap harbor.input port
            $l_port = Application_Model_StreamSetting::GetDJLiveSteamPort();
            $live_dj_port = new Zend_Form_Element_Text('dj_harbor_input_port');
            $live_dj_port->setLabel("Show Source Port")
                    ->setValue($l_port)
                    ->setValidators(array(new Zend_Validate_Between(array('min'=>1024, 'max'=>49151))))
                    ->addValidator('regex', false, array('pattern'=>'/^[0-9]+$/', 'messages'=>array('regexNotMatch'=>'Only numbers are allowed.')))
                    ->setDecorators(array('ViewHelper'));
            $this->addElement($live_dj_port);
            
            $l_mount = Application_Model_StreamSetting::GetDJLiveSteamMountPoint();
            $live_dj_mount = new Zend_Form_Element_Text('dj_harbor_input_mount_point');
            $live_dj_mount->setLabel("Show Source Mount Point")
                    ->setValue($l_mount)
                    ->setValidators(array(
                            array('regex', false, array('/^[^ &<>]+$/', 'messages' => 'Invalid character entered'))))
                    ->setDecorators(array('ViewHelper'));
            $this->addElement($live_dj_mount);
        }
    }
    
    public function updateVariables(){
        $m_port = Application_Model_StreamSetting::GetMasterLiveSteamPort();
        $m_mount = Application_Model_StreamSetting::GetMasterLiveSteamMountPoint();
        $l_port = Application_Model_StreamSetting::GetDJLiveSteamPort();
        $l_mount = Application_Model_StreamSetting::GetDJLiveSteamMountPoint();
        $isSaas = Application_Model_Preference::GetPlanLevel() == 'disabled'?false:true;
        
        $master_dj_connection_url = Application_Model_Preference::GetMasterDJSourceConnectionURL();
        $live_dj_connection_url = Application_Model_Preference::GetLiveDJSourceConnectionURL();
        
        $master_dj_connection_url = ($master_dj_connection_url == "")?("http://".$_SERVER['SERVER_NAME'].":".$m_port."/".$m_mount):$master_dj_connection_url;
        $live_dj_connection_url = ($live_dj_connection_url == "")?"http://".$_SERVER['SERVER_NAME'].":".$l_port."/".$l_mount:$live_dj_connection_url;
        
        if($m_port=="" || $m_mount==""){
            $master_dj_connection_url = "N/A";
        }
        if($l_port=="" || $l_mount==""){
            $live_dj_connection_url = "N/A";
        }
        
        $this->setDecorators(array(
        array('ViewScript', array('viewScript' => 'form/preferences_livestream.phtml', 'master_dj_connection_url'=>$master_dj_connection_url, 'live_dj_connection_url'=>$live_dj_connection_url, 'isSaas' => $isSaas))
        ));
    }
    
    public function isValid($data){
        $isSaas = Application_Model_Preference::GetPlanLevel() == 'disabled'?false:true;
        $isValid = parent::isValid($data);
        if (!$isSaas) {
            $master_harbor_input_port = $data['master_harbor_input_port'];
            $dj_harbor_input_port = $data['dj_harbor_input_port'];
            
            if($master_harbor_input_port == $dj_harbor_input_port && $master_harbor_input_port != ""){
                $element = $this->getElement("dj_harbor_input_port");
                $element->addError("You cannot use same port as Master DJ port.");
            }
            if($master_harbor_input_port != ""){
                if(is_numeric($master_harbor_input_port)){
                    if($master_harbor_input_port != Application_Model_StreamSetting::GetMasterLiveSteamPort()){
                        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                        $res = socket_bind($sock, 0, $master_harbor_input_port);
                        if(!$res){
                            $element = $this->getElement("master_harbor_input_port");
                            $element->addError("Port '$master_harbor_input_port' is not available.");
                            $isValid = false;
                        }
                        socket_close($sock);
                    }
                }else{
                    $isValid = false;
                }
            }
            if($dj_harbor_input_port != ""){
                if(is_numeric($dj_harbor_input_port)){
                    if($dj_harbor_input_port != Application_Model_StreamSetting::GetDJLiveSteamPort()){
                        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                        $res = socket_bind($sock, 0, $dj_harbor_input_port);
                        if(!$res){
                            $element = $this->getElement("dj_harbor_input_port");
                            $element->addError("Port '$dj_harbor_input_port' is not available.");
                            $isValid = false;
                        }
                        socket_close($sock);
                    }
                }else{
                    $isValid = false;
                }
            }
        }
        return $isValid;
    }
    
}
