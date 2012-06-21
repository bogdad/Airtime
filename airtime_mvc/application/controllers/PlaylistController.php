<?php

class PlaylistController extends Zend_Controller_Action
{
    protected $pl_sess = null;

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('add-items', 'json')
                    ->addActionContext('move-items', 'json')
                    ->addActionContext('delete-items', 'json')
                    ->addActionContext('set-fade', 'json')
                    ->addActionContext('set-cue', 'json')
                    ->addActionContext('new', 'json')
                    ->addActionContext('edit', 'json')
                    ->addActionContext('delete', 'json')
                    ->addActionContext('play', 'json')
                    ->addActionContext('set-playlist-fades', 'json')
                    ->addActionContext('get-playlist-fades', 'json')
                    ->addActionContext('set-playlist-name', 'json')
                    ->addActionContext('set-playlist-description', 'json')
                    ->addActionContext('playlist-preview', 'json')
                    ->addActionContext('get-playlist', 'json')
                    ->initContext();

        $this->pl_sess = new Zend_Session_Namespace(UI_PLAYLIST_SESSNAME);
    }

    private function getPlaylist()
    {
        $pl = null;

        if (isset($this->pl_sess->id)) {
            $pl = new Application_Model_Playlist($this->pl_sess->id);

            $modified = $this->_getParam('modified', null);
            if ($pl->getLastModified("U") !== $modified) {
                $this->createFullResponse($pl);
                throw new PlaylistOutDatedException("You are viewing an older version of {$pl->getName()}");
            }
        }
        return $pl;
    }

    private function changePlaylist($pl_id)
    {
        if (is_null($pl_id)) {
            unset($this->pl_sess->id);
        }
        else {
            $this->pl_sess->id = intval($pl_id);
        }
    }

    private function createUpdateResponse($pl)
    {
        $formatter = new LengthFormatter($pl->getLength());
        $this->view->length = $formatter->format();

        $this->view->pl = $pl;
        $this->view->html = $this->view->render('playlist/update.phtml');
        $this->view->name = $pl->getName();
        $this->view->description = $pl->getDescription();
        $this->view->modified = $pl->getLastModified("U");

        unset($this->view->pl);
    }

    private function createFullResponse($pl = null)
    {
        if (isset($pl)) {
            $formatter = new LengthFormatter($pl->getLength());
            $this->view->length = $formatter->format();

            $this->view->pl = $pl;
            $this->view->id = $pl->getId();
            $this->view->html = $this->view->render('playlist/playlist.phtml');
            unset($this->view->pl);
        }
        else {
            $this->view->html = $this->view->render('playlist/playlist.phtml');
        }
    }

    private function playlistOutdated($pl, $e)
    {
        $this->view->error = $e->getMessage();
    }

    private function playlistNotFound()
    {
        $this->view->error = "Playlist not found";

        Logging::log("Playlist not found");
        $this->changePlaylist(null);
        $this->createFullResponse(null);
    }

    private function playlistUnknownError($e)
    {
        $this->view->error = "Something went wrong.";

        Logging::log("{$e->getFile()}");
        Logging::log("{$e->getLine()}");
        Logging::log("{$e->getMessage()}");
    }

    public function indexAction()
    {
        global $CC_CONFIG;

        $request = $this->getRequest();
        //$baseUrl = $request->getBaseUrl();
        $baseUrl = dirname($_SERVER['SCRIPT_NAME']);
        if (strcmp($baseUrl, '/') ==0) $baseUrl = "";

        $this->view->headScript()->appendFile($baseUrl.'/js/blockui/jquery.blockUI.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/contextmenu/jquery.contextMenu.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/js/jquery.dataTables.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/plugin/dataTables.pluginAPI.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/plugin/dataTables.fnSetFilteringDelay.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/plugin/dataTables.ColVis.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/plugin/dataTables.ColReorder.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/plugin/dataTables.FixedColumns.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/buttons/buttons.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/utilities/utilities.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/library/library.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($this->view->baseUrl('/js/airtime/library/events/library_playlistbuilder.js'),'text/javascript');
        
        $this->view->headLink()->appendStylesheet($baseUrl.'/css/media_library.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'/css/jquery.contextMenu.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'/css/datatables/css/ColVis.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'/css/datatables/css/ColReorder.css?'.$CC_CONFIG['airtime_version']);
        
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/library/spl.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headLink()->appendStylesheet($baseUrl.'/css/playlist_builder.css?'.$CC_CONFIG['airtime_version']);

        try {
            if (isset($this->pl_sess->id)) {
                $pl = new Application_Model_Playlist($this->pl_sess->id);
                $this->view->pl = $pl;

                $formatter = new LengthFormatter($pl->getLength());
                $this->view->length = $formatter->format();
            }
        }
        catch (PlaylistNotFoundException $e) {
            $this->playlistNotFound();
        }
        catch (Exception $e) {
            $this->playlistUnknownError($e);
        }
    }

    public function newAction()
    {
        $pl_sess = $this->pl_sess;
		$userInfo = Zend_Auth::getInstance()->getStorage()->read();

        $pl = new Application_Model_Playlist();
        $pl->setName("Untitled Playlist");
		$pl->setPLMetaData('dc:creator', $userInfo->id);

		$this->changePlaylist($pl->getId());
		$this->createFullResponse($pl);
    }

    public function editAction()
    {
        $id = $this->_getParam('id', null);
        Logging::log("editing playlist {$id}");

		if (!is_null($id)) {
			$this->changePlaylist($id);
		}

		try {
            $pl = new Application_Model_Playlist($id);
            $this->createFullResponse($pl);
		}
		catch (PlaylistNotFoundException $e) {
		    $this->playlistNotFound();
		}
		catch (Exception $e) {
		    $this->playlistUnknownError($e);
		}
    }

    public function deleteAction()
    {
        $ids = $this->_getParam('ids');
        $ids = (!is_array($ids)) ? array($ids) : $ids;
        $pl = null;

        try {

            Logging::log("Currently active playlist {$this->pl_sess->id}");
            if (in_array($this->pl_sess->id, $ids)) {
                Logging::log("Deleting currently active playlist");
                $this->changePlaylist(null);
            }
            else {
                Logging::log("Not deleting currently active playlist");
                $pl = new Application_Model_Playlist($this->pl_sess->id);
            }

            Application_Model_Playlist::DeletePlaylists($ids);
            $this->createFullResponse($pl);
        }
        catch (PlaylistNotFoundException $e) {
            $this->playlistNotFound();
        }
        catch (Exception $e) {
            $this->playlistUnknownError($e);
        }
    }
    
    public function addItemsAction()
    {
        $ids = $this->_getParam('ids', array());
        $ids = (!is_array($ids)) ? array($ids) : $ids;
    	$afterItem = $this->_getParam('afterItem', null);
    	$addType = $this->_getParam('type', 'after');

        try {
            $pl = $this->getPlaylist();
            $pl->addAudioClips($ids, $afterItem, $addType);
            $this->createUpdateResponse($pl);
        }
        catch (PlaylistOutDatedException $e) {
            $this->playlistOutdated($pl, $e);
        }
        catch (PlaylistNotFoundException $e) {
            $this->playlistNotFound();
        }
        catch (Exception $e) {
            $this->playlistUnknownError($e);
        }
    }

    public function moveItemsAction()
    {
        $ids = $this->_getParam('ids');
        $ids = (!is_array($ids)) ? array($ids) : $ids;
        $afterItem = $this->_getParam('afterItem', null);
        $modified = $this->_getParam('modified');

        try {
            $pl = $this->getPlaylist();
            $pl->moveAudioClips($ids, $afterItem);
            $this->createUpdateResponse($pl);
        }
        catch (PlaylistOutDatedException $e) {
            $this->playlistOutdated($pl, $e);
        }
        catch (PlaylistNotFoundException $e) {
            $this->playlistNotFound();
        }
        catch (Exception $e) {
            $this->playlistUnknownError($e);
        }
    }

    public function deleteItemsAction()
    {
        $ids = $this->_getParam('ids');
        $ids = (!is_array($ids)) ? array($ids) : $ids;
        $modified = $this->_getParam('modified');

        try {
            $pl = $this->getPlaylist();
            $pl->delAudioClips($ids);
            $this->createUpdateResponse($pl);
        }
        catch (PlaylistOutDatedException $e) {
            $this->playlistOutdated($pl, $e);
        }
        catch (PlaylistNotFoundException $e) {
            $this->playlistNotFound();
        }
        catch (Exception $e) {
            $this->playlistUnknownError($e);
        }
    }

    public function setCueAction()
    {
		$id = $this->_getParam('id');
		$cueIn = $this->_getParam('cueIn', null);
		$cueOut = $this->_getParam('cueOut', null);

        try {
            $pl = $this->getPlaylist();
            $response = $pl->changeClipLength($id, $cueIn, $cueOut);

            if (!isset($response["error"])) {
                $this->view->response = $response;
                $this->createUpdateResponse($pl);
            }
            else {
                $this->view->cue_error = $response["error"];
            }
        }
        catch (PlaylistOutDatedException $e) {
            $this->playlistOutdated($pl, $e);
        }
        catch (PlaylistNotFoundException $e) {
            $this->playlistNotFound();
        }
        catch (Exception $e) {
            $this->playlistUnknownError($e);
        }
    }

    public function setFadeAction()
    {
		$id = $this->_getParam('id');
		$fadeIn = $this->_getParam('fadeIn', null);
		$fadeOut = $this->_getParam('fadeOut', null);

        try {
            $pl = $this->getPlaylist();
            $response = $pl->changeFadeInfo($id, $fadeIn, $fadeOut);

            if (!isset($response["error"])) {
                $this->createUpdateResponse($pl);
                $this->view->response = $response;
            }
            else {
                $this->view->fade_error = $response["error"];
            }
        }
        catch (PlaylistOutDatedException $e) {
            $this->playlistOutdated($pl, $e);
        }
        catch (PlaylistNotFoundException $e) {
            $this->playlistNotFound();
        }
        catch (Exception $e) {
            $this->playlistUnknownError($e);
        }
    }

    public function getPlaylistFadesAction()
    {
        try {
            $pl = $this->getPlaylist();
            $fades = $pl->getFadeInfo(0);
            $this->view->fadeIn = $fades[0];

            $fades = $pl->getFadeInfo($pl->getSize()-1);
            $this->view->fadeOut = $fades[1];
        }
        catch (PlaylistOutDatedException $e) {
            $this->playlistOutdated($pl, $e);
        }
        catch (PlaylistNotFoundException $e) {
            $this->playlistNotFound();
        }
        catch (Exception $e) {
            $this->playlistUnknownError($e);
        }
    }

    /**
     * The playlist fades are stored in the elements themselves.
     * The fade in is set to the first elements fade in and
     * the fade out is set to the last elements fade out.
     **/
    public function setPlaylistFadesAction()
    {
		$fadeIn = $this->_getParam('fadeIn', null);
		$fadeOut = $this->_getParam('fadeOut', null);

        try {
            $pl = $this->getPlaylist();
            $pl->setPlaylistfades($fadeIn, $fadeOut);
            $this->view->modified = $pl->getLastModified("U");
        }
        catch (PlaylistOutDatedException $e) {
            $this->playlistOutdated($pl, $e);
        }
        catch (PlaylistNotFoundException $e) {
            $this->playlistNotFound();
        }
        catch (Exception $e) {
            $this->playlistUnknownError($e);
        }
    }

    public function setPlaylistNameAction()
    {
        $name = $this->_getParam('name', 'Unknown Playlist');

        try {
            $pl = $this->getPlaylist();
            $pl->setName($name);
            $this->view->playlistName = $name;
            $this->view->modified = $pl->getLastModified("U");
        }
        catch (PlaylistOutDatedException $e) {
            $this->playlistOutdated($pl, $e);
        }
        catch (PlaylistNotFoundException $e) {
            $this->playlistNotFound();
        }
        catch (Exception $e) {
            $this->playlistUnknownError($e);
        }
    }

    public function setPlaylistDescriptionAction()
    {
        $description = $this->_getParam('description', "");

        try {
            $pl = $this->getPlaylist();
            $pl->setDescription($description);
            $this->view->description = $pl->getDescription();
            $this->view->modified = $pl->getLastModified("U");
        }
        catch (PlaylistOutDatedException $e) {
            $this->playlistOutdated($pl, $e);
        }
        catch (PlaylistNotFoundException $e) {
            $this->playlistNotFound();
        }
        catch (Exception $e) {
            $this->playlistUnknownError($e);
        }
    }
}

