<?php
/*
 * A controller for a gallery app.
 * Developed by Michael Milawski
 * Based on F3 framework.
 */

//language selector link:

$langs = array('en', 'de');
$lan = array();
foreach($langs as $l){
    $is_active = $lang == $l ? 'active' : '';
    $lan[] =  "<span data-lang='" . $l . "' class='clickable btn_set_language " . $is_active . "'>" . strtoupper($l) . "</span>";
}
$f3->set("lng_link", join("/", $lan));


//show cookie popup only once per session:
$pop = $f3->get("SESSION.cookie");
if(empty($pop)){
    $f3->set("show_cookie_popup", "1");

}else{
    $f3->set("show_cookie_popup", "0");
}



//serve robots txt:
$f3->route('GET /robots.txt',
    function($f3) {
        header('Content-Type:text/plain; charset=ISO-8859-15');
        include("robots-file-private.txt");
        die();
    }
);


//Define Error Page:
$f3->set('ONERROR',function($f3){
    //write error to log:
    $db = $f3->get("db");
    $ERROR = $f3->get("ERROR");


    //insert the error into database: (but only if debug mode is turned off)
    if($f3->get("DEBUG") == 0) {
        $db->exec("INSERT INTO erm_logs SET code = :code, status = :status, text = :text, trace = :trace", array(
            'code' => $ERROR['code'],
            'status' => $ERROR['status'],
            'text' => $ERROR['text'],
            'trace' => $ERROR['trace'],
        ));
        $id =  $db->lastInsertId();
        $f3->set("ERROR.log_id", $id);
    }

    $f3->set('content','error.php');
    echo View::instance()->render('layout_public.php');
    die();
});


//---------------------------------------------------- APP START
$f3->route('GET /',
    function($f3) {

        //get the default event:
        $db = $f3->get("db");
        $data = $db->exec("SELECT clean_name FROM erm_filedata WHERE is_default = 1");



        if(empty($data)){
            //no default event specified, display the start page

            global $owncloud;


            $usr= $f3->get("SESSION.user");

            if(!empty($usr)){
                //show all events when logged in as admin
                $onlyVisible = false;
            }else{
                $onlyVisible = true;
            }

            $events = $owncloud->getAllEvents($onlyVisible);
            $f3->set("events", $events);

            $f3->set('content','start.htm');
            echo View::instance()->render('layout.htm');
        }else{
            //default event was specified, redirect to the event:
            $event = $data[0];
            $f3->reroute( "/event/" . $event['clean_name']);
        }
    }
);


/**
 * Root Directory Listing
 */
$f3->route('GET /phpinfo',
    function($f3) {
        phpinfo();
    }
);


/**
 * Root Directory Listing
 */
$f3->route('GET /dir',
    function($f3) {
        global $owncloud;
        $content = $owncloud->getContent();

        $f3->set("folder", $content['folder']);
        $f3->set("parentfolder", $content['parentfolder']);
        $f3->set("folders", $content['folders']);

        $f3->set("files", $content['files']);

        $f3->set("hires", $f3->get("SESSION.hires"));


        $f3->set('content','dirs.php');
        echo View::instance()->render('layout.htm');

    }
);


/**
 * Directory Listing
 */
$f3->route('GET /dir/@dir',
    function($f3) {
        global $owncloud;
        $page_id = $f3->get("PARAMS.dir");
        $content = $owncloud->getContent($page_id);

        $f3->set("folder", $content['folder']);
        $f3->set("parentfolder", $content['parentfolder']);

        $content['folders'] = flattenSubdirectories($content['folders']);

        $f3->set("folders", $content['folders']);
        //$f3->set("files", $content['files']);

        //$f3->set("dir_files", $content['dir_files']);
        $f3->set("meta", $content['meta']);

        $f3->set('content','dirs.php');
        echo View::instance()->render('layout.htm');

    }
);


/**
 * Open File
 */
$f3->route('GET /file/@file_id',
    function($f3) {
        global $owncloud;
        $file_id = $f3->get("PARAMS.file_id");

        $parentfolder = $owncloud->getParentFolder($file_id);
        $file = $owncloud->getFile($file_id);

        $f3->set("file", $file);
        $f3->set("parentfolder", $parentfolder);

        $f3->set('content','file.htm');
        echo View::instance()->render('layout.htm');

    }
);





/**
 * Change language via ajax
 */
$f3->route('POST /setlang',
    function($f3) {
        $_P = $f3->get("POST");
        $f3->set("SESSION.lang", $_P['lang']);
    }
);


/**
 * Generate and return a thumbnail
 */
$f3->route('GET|POST /thumb/@size/@id',
    function($f3) {
        $size = $f3->get("PARAMS.size");
        $id = $f3->get("PARAMS.id");

        global $owncloud;
        $owncloud->getThumbnail($id, $size);

    }
);


/**
 * Get meta file information for modal viewer (ajax)
 */
$f3->route('GET /getfile/@fileid',
    function($f3) {
        $fileid = $f3->get("PARAMS.fileid");

        global $owncloud;
        $file = $owncloud->getFile($fileid);
        $file = $owncloud->checkThumbnail($file);

        if(!empty($file)){
            $r = json_encode($file);
        }else{
            $r = json_encode(array());
        }

        echo $r;
    }
);


/**
 * Download a file by coode
 */
$f3->route('GET /download/@code',
    function($f3) {
        $code = $f3->get("PARAMS.code");

        //convert code into int:
        $fileid = Hashid::alphaID($code,true);

        global $owncloud;
        $file = $owncloud->getFile($fileid);

        //does the file exist?
        if(empty($file)){
            $f3->error(404);
        }

        $web = \Web::instance();
        $mime = $web->mime($file['path']);

        $file_path = getDataPath($file['path']);
        $filename = $file['name'];

        //printr("TEST!" . $file_path);
        header('Content-Type: '.$mime);
        header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode( $filename ));
        
        readfile_chunked($file_path);
        //echo $f3->read($file_path);
    }
);



/**
 * Add Download to List
 */
$f3->route('GET /add_download/@fileid',
    function($f3) {
        $fileid = $f3->get("PARAMS.fileid");

        //array with current downloads:

        if(empty($f3->get("SESSION.downloadlist"))) {
            $my_downloads = array();
        }else{
            $my_downloads = $f3->get("SESSION.downloadlist");
        }

        $my_downloads[$fileid] = $fileid;

        $f3->set("SESSION.downloadlist", $my_downloads);
        die("OK");
    }
);


/**
 * Add Directory to Download List
 */
$f3->route('GET /add_download_dir/@dirid',
    function($f3) {
        $dirid = $f3->get("PARAMS.dirid");

        //array with current downloads:
        if(empty($f3->get("SESSION.downloadlist"))) {
            $my_downloads = array();
        }else{
            $my_downloads = $f3->get("SESSION.downloadlist");
        }

        //get all files of a directory (not recursive!):
        global $owncloud;
        $files = $owncloud->getFiles($dirid) ;

        foreach($files as $file){
            $my_downloads[$file['fileid']] = $file['fileid'];
        }

        $f3->set("SESSION.downloadlist", $my_downloads);
        die("OK");
    }
);





/**
 * Get all user downloads
 */
$f3->route('GET /get_downloads',
    function($f3) {
        //array with current downloads:
        $ret = array();

        if(!empty($f3->get("SESSION.downloadlist"))) {
            $download_list =  $f3->get("SESSION.downloadlist");

            global $owncloud;
            $files = $owncloud->getMultipleFiles($download_list);

            //build json for ajax
            foreach($files as $file){
                $ret[] = array(
                    'fileid' => $file['fileid'],
                    'filename' => $file['filename'],
                    'size' => $file['human_readable_filesize'],
                    'hashid' => $file['hashid'],
                    'thumbnail' => $file['thumbnail_big'],
                    'extension' => strtoupper($file['extension']),
                );
            }
        }

        die(json_encode($ret));
    }
);


/**
 * Get all available events
 */
$f3->route('GET /get_events',
    function($f3) {
        //array with current downloads:
        global $owncloud;
        $ret = $owncloud->getAllEvents();
        die(json_encode($ret));
    }
);


/**
 * Delete a download
 */
$f3->route('GET /del_download/@which',
    function($f3) {
        $which = $f3->get("PARAMS.which");
        $download_list =  $f3->get("SESSION.downloadlist");

        if(is_numeric($which)){
            //delete one item:
            unset($download_list[$which]);
            
            if(!empty($download_list)){
                $f3->set("SESSION.downloadlist", $download_list);
            }else{
                $f3->clear("SESSION.downloadlist");
            }

        }else{
            //remove all
            $f3->clear("SESSION.downloadlist");
        }

        die("OK");

    }
);


/**
 * generaze a ZIP of all selected downloads and send it to user
 */
$f3->route('GET /download_myfiles',
    function($f3) {
        if(!empty($f3->get("SESSION.downloadlist"))) {
            $download_list =  $f3->get("SESSION.downloadlist");

            global $owncloud;
            $fileName = $f3->get("DEFAULT_ZIP_FILENAME");
            $owncloud->downloadFiles($download_list, $fileName);
     }
    }
);

/**
 * download complete directory
 */
$f3->route('GET /download_dir/@dirid',
    function($f3) {
        $dirid = $f3->get("PARAMS.dirid");
            global $owncloud;
            $fileName = $f3->get("DEFAULT_ZIP_FILENAME");
            $owncloud->downloadDirectory($dirid, $fileName);
    }
);





/**
 * Set hires or lowres for a folder
 */
$f3->route('GET /set_hires/@action/@dirid',
    function($f3) {
        $action = $f3->get("PARAMS.action");
        $dirid = $f3->get("PARAMS.dirid");

        //load current res settings
        $hires = $f3->get("SESSION.hires");

        if(!is_array($hires)){
            //we have an empty setting here..
            $hires= array();
        }

        if($action == "hi"){
            //set hires
            $hires[$dirid] = $dirid;
        }else{
            //remove hires
            if(isset($hires[$dirid])){
                unset($hires[$dirid]);
            }
        }

        //save hires settings in session:
        $f3->set("SESSION.hires", $hires);
        echo("OK");
    }
);


/**
 * Disable cookie message (save in session)
 */
$f3->route('GET /disable_cookie_message',
    function($f3) {
        $f3->set("SESSION.cookie", "nomNomNom");
    }
);

/**
 * redirect to root instead of showing 404
 */
$f3->route('GET /event',
    function($f3) {

        global $owncloud;
        $events = $owncloud->getAllEvents();
        $f3->set("events", $events);

        $f3->set('content','events.php');
        echo View::instance()->render('layout.htm');

    });

/**
 * Event Subdir (unique event urls)
 */
$f3->route('GET /event/*',
    function($f3) {

        //printr($f3);

        //$params = $f3->get("PARAMS.1");
        $event_name = $f3->get("PARAMS.1");
        //$event_name = $f3->get("PARAMS.name");

        global $owncloud;
        $content = $owncloud->getContent($event_name);

        //Should we display the event or a "comimg soon" message?
        if($content['folder']['release_active'] == "1" &&  empty($f3->get("SESSION.user")) ){
            $release_date = strtotime($content['folder']['release_date']);

            if(time() < $release_date){
				$f3->set('meta', $content['meta']);
                $f3->set('filename', $content['folder']['filename']);
                $f3->set('release_message_en', $content['folder']['release_message_en']);
                $f3->set('release_message_de', $content['folder']['release_message_de']);
                $f3->set('content','commingsoon.php');
                echo View::instance()->render('layout_standby.php');
                exit();
            }
        }


        $f3->set("folder", $content['folder']);
        $f3->set("parentfolder", $content['parentfolder']);

        //$content['folders'] = flattenSubdirectories($content['folders']);
        $f3->set("folders", $content['folders']);

        $f3->set("meta", $content['meta']);


        if($f3->get("ismobile")){
            $f3->set('content','dirs_mobile.php');
        }else{
            $f3->set('content','dirs.php');
        }

        echo View::instance()->render('layout.htm');
    }
);



/**
 * Do not show navigation help in the current session
 */
$f3->route('GET /hide_navigation_help',
    function($f3) {
        //get all folders:
        $f3->set("SESSION.hide_navigation_help", 1);
    }
);

/**
 * Show
 */
$f3->route('GET /show_navigation_help',
    function($f3) {
        //get all folders:
        $f3->clear("SESSION.hide_navigation_help");
    }
);




/**
 * Mobile Swipe Test
 */
$f3->route('GET /test/swipe',
    function($f3) {
        $f3->set('content','swipe.php');
        echo View::instance()->render('layout.htm');
    }
);


/**
 * Mobile Test - Date
 */
$f3->route('GET /test/date',
    function($f3) {

        setlocale(LC_TIME, "de_DE");


        $info = cal_info(0);
        print_r($info);

        echo strftime(" in German %b.\n");


        die();
    }
);



/**
 * Suche
 */
$f3->route('GET /search',
    function($f3) {
        $f3->set('content','search.php');
        echo View::instance()->render('layout.htm');
    }
);

/**
 * AJAX Dateisuche
 */
$f3->route('GET|POST /searchfiles',
    function($f3) {
        global $owncloud;

        $params = $f3->get("POST");

        $files = $owncloud->search($params);
        
        die(json_encode($files));
    }
);


