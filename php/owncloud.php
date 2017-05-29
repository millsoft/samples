<?php

/*
A library for a frontend owncloud extension.
by Michael Milawski
*/

class Owncloud extends \Prefab {

    //thumbnail settings:
    public $thumb_settings = array(
        'i' => array(100,100),    //icon
        't' => array(200,200),  //tiny
    );

    public $date_formats = array(
        'de' => 'd.m.Y',
        'en' => 'Y/m/d',
    );

    public $meta_files = array(
        "_header.jpg",
        "_header_en.jpg",
        "_header_de.jpg",
        "_header.txt",
        "_header_en.txt",
        "_header_de.txt",
        "_teaser.txt",
        "_teaser_de.txt",
        "_teaser_en.txt",
        "_header_offline.jpg",
        "_source.txt",          //Source file - if put in a directory, will display content as html instead of files
        "_source_en.txt",
        "_source_de.txt",
        "_button.jpg",           //image of the subevent
        "_button_en.jpg",           //image of the (english)
        "_button_de.jpg"           //image of the (german)
    );

    public $previewable_extensions = array(
        "jpg", "gif", "png", "jpeg", "mp4", "webm", "pdf", "mov"
    );


    //Public Filter array for search queries:
    public $filter = array();

    //thumbnail generation will be always issued on load of folder, this can be disabled by setting this var to true:
    public $disableThumbGeneration = false;

    public $items_per_page = 5;

    //! Constructor
    function __construct() {
        $f3=\Base::instance();
        session_start();
        //$config=$f3->get('db');


        $this->f3 = $f3;

        //get current language
        $this->lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'en';


    }

    /**
     * Get File ID from /events/{clean_name}
     * @param $cleanName
     * @return mixed
     */
    public function getIdFromCleanName($cleanName){
        $db = $this->f3->get("db");
        $sql = "SELECT fileid FROM erm_filedata WHERE clean_name = :clean_name";

        $data = $db->exec($sql, array(
            ':clean_name' => $cleanName
        ), 1800);

        if(empty($data)){
            $this->f3->error(404);
        }else{
            return $data[0]['fileid'];
        }
    }

    /**
     * Get the entire content for a directory
     * @param $etag
     *
     * @return array
     */
    public function getContent($fileid){

        //check if the directory exists:

        if(!is_numeric($fileid)){
            //request via event subdir, get the id of that name:
            $fileid = $this->getIdFromCleanName($fileid);
        }

        if(is_numeric($fileid) && !$this->doesExist($fileid)){
            $this->f3->error(404);
        }

        //get current folder:
        $folder = $this->getFolder($fileid);


        //set name of current folder in title tag:
        $this->f3->set("EVENTNAME", $folder['filename']);

        //get meta files for the current folder: (header images, additional content, etc..)
        $meta = $this->getMetaFiles($fileid);

        //get parent folder:
        $parentFolder = $this->getParentFolder($fileid);

        $ignoreReleaseTime = !empty($this->f3->get("SESSION.user")) ? true : false;

        $folders = $this->getFolders($fileid, true, true, true, $ignoreReleaseTime);
        $folders = $this->sortFolders($folders);
        $folders = $this->reassignSubevents($folders);

        $return = array(
            'folder' => $folder,
            'parentfolder' => $parentFolder,
            'folders' => $folders,
            //'dir_files' => $dir_files,
            'meta' => $meta
        );

        return $return;
    }


    //add subevents as subfiles to the parent
    public function reassignSubevents($folders){
        $new = array();

        $mainfiles = array();

        //first, add all folders excluding the subevents:
        foreach($folders as $k => $folder){
            if(!$folder['is_subevent']){
                $mainfiles[$folder['fileid']] = $k;
                $new[$k] = $folder;
            }
        }

        //now add subevents to parent:

        foreach($folders as $k => $folder){
            if($folder['is_subevent']){

                //get the key of parentid:
                $ky = $mainfiles[$folder['parent']];
                $mainfiles[$folder['fileid']] = $k;
                $new[$ky]['files'][$k] = $folder;

                //get meta files:
                $meta = $this->getMetaFiles($folder['fileid']);
                $new[$ky]['files'][$k]['meta'] = $meta;

            }
        }

        return $new;


    }

//sort folders by db col "posy"
    public function sortFolders($folders){
        uasort($folders, function($a,$b){
           if($a['posy'] > $b['posy']){return 1;}
           if($a['posy'] < $b['posy']){return -1;}
            return 0;
        });

        //remove folders if there are no files:
        $tmp = array();

        foreach($folders as $k => $folder){
            if(!empty($folder['files'])  || $folder['is_subevent']  || $folder['count_subfolders'] ){
                $tmp[$k] = $folder;
            }
        }

        return $tmp;
    }

    /**
     * Get parent folder details from db
     * @param $etag
     */
    public function getParentFolder($fileid){
        $db = $this->f3->get("db");

        $sql = "SELECT fc.*, fd.id_events, fd.filename_en, fd.filename_de, fd.description_en, fd.description_de FROM oc_filecache fc
                LEFT JOIN erm_filedata fd ON fd.fileid = fc.fileid
                WHERE fc.fileid =  (SELECT parent FROM oc_filecache WHERE fileid = :fileid)
                AND fc.mimetype = 2";

        $folder = $db->exec($sql, array(
            ':fileid' => $fileid
        ),1800);

        if(!empty($folder)){
            $folder = $this->translate($folder);
            return reset($folder);
        }else{
            return array();
        }

    }

    /**
     * Get folder details from db
     * @param $etag
     */
    public function getFolder($fileid){
        $db = $this->f3->get("db");

        $sql = "SELECT fc.*, fd.id_events, fd.filename_en, fd.filename_de, fd.description_en, fd.description_de, fd.tags , fd.lang, fd.release_active, fd.release_date, fd.release_message_en, fd.release_message_de

                FROM oc_filecache fc
                LEFT JOIN erm_filedata fd ON fd.fileid = fc.fileid
                WHERE fc.fileid = :fileid
                AND fc.mimetype = 2";

        $folder = $db->exec($sql, array(
            ':fileid' => $fileid
        ), 1800);

        if(!empty($folder)){
            $folder = $this->translate($folder);
            return reset($folder);
        }else{
            return array();
        }

    }

    /**
     * Get all meta files in a folder (header images, additional content etc..)
     * @param $fileid
     *
     * @return array
     */
    public function getMetaFiles($fileid){
        $db = $this->f3->get("db");

        $sql = "SELECT fc.*, fd.id_events, fd.filename_en, fd.filename_de, fd.description_en, fd.description_de
                FROM oc_filecache fc
                LEFT JOIN erm_filedata fd ON fd.fileid = fc.fileid
                WHERE fc.parent = :fileid
                ";

        $files = $db->exec($sql, array(
            ':fileid' => $fileid
        ));

        $meta = array();

        //get meta files:
        foreach($files as $file){
            if( in_array($file['name'], $this->meta_files) ){
                //found a meta file! put it and the value in meta array
                $meta[$file['name']] = $file;
            }
        }

        return $meta;
    }

    /**
     * Get file details from db
     * @param $etag
     */
    public function getFile($fileid){
        $db = $this->f3->get("db");

        if(!is_numeric($fileid)){
            //get content by public link using the hash
            $sharing_salt = $this->f3->get("sharing.salt");

            $where = "SHA1(CONCAT('{$sharing_salt}', fc.fileid) ) = :fileid";

        }else{
            $where = "fc.fileid = :fileid";
        }

        $sql = "SELECT fc.*, fd.id_events, fd.filename_en, fd.filename_de, fd.description_en, fd.description_de, fd.lang,
                pf.fileid AS preview_fileid,
                pf.path AS preview_path

                FROM oc_filecache fc
                LEFT JOIN erm_filedata fd ON fd.fileid = fc.fileid
                LEFT JOIN oc_filecache pd ON pd.parent = fc.parent AND pd.mimetype = 2 AND pd.name='_preview'
                LEFT JOIN oc_filecache pf ON pf.parent = pd.fileid AND pf.name = fc.name

                WHERE {$where}
                AND fc.mimetype <> 2";

        $file = $db->exec($sql, array(
            ':fileid' => $fileid
        ));


        if(!empty($file)){

            $file = $this->translate($file);
            $file = reset($file);

            return $file;

        }else{
            return array();
        }

    }


    /**
     * Get file details from db
     * @param $etag
     */
    public function getFile2($fileid){
        $db = $this->f3->get("db");

        if(!is_numeric($fileid)){
            //get content by public link using the hash
            $sharing_salt = $this->f3->get("sharing.salt");

            $where = " LOWER(SHA1(CONCAT('$sharing_salt', fc.fileid) )) = LOWER('" . trim( strtolower($fileid)) . "')";

        }else{
            $where = "fc.fileid = " . $fileid;
        }

        $sql = "SELECT fc.*, fd.id_events, fd.filename_en, fd.filename_de, fd.description_en, fd.description_de, fd.lang
                FROM oc_filecache fc
                LEFT JOIN erm_filedata fd ON fd.fileid = fc.fileid
                WHERE {$where}
                AND fc.mimetype <> 2";



        $client = $this->getSqlTunnel();
        $file= $client->query($sql);

        if(!empty($file)){

            $file = $this->translate($file);
            $file = reset($file);

            return $file;

        }else{
            return array();
        }

    }

    /**
     * Get an instance of the sql tunnel
     */
    public function getSqlTunnel(){
		
		/* TOP SECRET */
		

        return $this->sqltunnel;
    }

    /**
     * Get file details from db
     * @param $fileids (array)
     */
    public function getMultipleFiles($fileids, $fields = ''){
        $db = $this->f3->get("db");

        if(empty($fields)){
            $fields = "fc.*, fd.id_events, fd.filename_en, fd.filename_de, fd.description_en, fd.description_de, fd.lang";
        }

        $fileids_str = join(',', $fileids);

        $sql = "SELECT {$fields}
                FROM oc_filecache fc
                LEFT JOIN erm_filedata fd ON fd.fileid = fc.fileid
                WHERE fc.fileid IN (" . $fileids_str . ")
                AND fc.mimetype <> 2";

        $files = $db->exec($sql);


        if(!empty($files)){

            $files = $this->assignThumbs($files);
            $files = $this->translate($files);
            return $files;

        }else{
            return array();
        }
    }


    /**
     * Get folders inside a folder
     * @param $etag
     * @param $recursive
     */
    public function getFolders($fileid, $recursive = false, $getFiles = false, $onlyShared = true, $ignoreReleaseTime = false){
        global $f3;
        $db = $this->f3->get("db");

        $joinType = $onlyShared ? "RIGHT" : "LEFT";
        $is_admin = $f3->get("ISADMIN");

        //$ignoreReleaseTime = !empty($f3->get("SESSION.ignoretime")) ? true : false;
        $ignoreReleaseTime = !empty($this->f3->get("SESSION.user")) ? true : false;


        //old preview method
        if(isset($_GET['ignoretime'])){
            $ignoreReleaseTime = isset($_REQUEST['ignoretime']) ? true : false;
        }

        if($ignoreReleaseTime){
            $release_time_sql = '1=1';
        }else{

        //simulate a time:
            if(isset($_REQUEST['time'])){
                $ct = str_replace('T', ' ', $_REQUEST['time']);
                $current_time = "'" . $ct . "'";
            }else{
                $current_time = 'NOW()';
            }

            $release_time_sql =  "fd.release_date < " . $current_time;
        }


        $sql = "SELECT
            fc.*,
            fd.pos,
            fd.id_events, 
            fd.filename_en, 
            fd.filename_de, 
            fd.description_en, 
            fd.description_de,
            fd.action,
            fd.tags ,
            IF(sh.share_type IS NOT NULL, 1, 0) AS shared, 
            fd.lang, fd.is_subevent , 
            fc_hires.fileid AS hires_fileid, 
            fd.release_active,
            fd.release_date,
            fd.show_header,
            fd.clean_name,
            (SELECT COUNT(*) FROM oc_filecache fc WHERE fc.parent = :fileid) AS count_subfolders

            FROM oc_filecache fc
            $joinType JOIN oc_share sh ON sh.item_source = fc.fileid
            LEFT JOIN erm_filedata fd ON fd.fileid = fc.fileid
            LEFT JOIN oc_filecache fc_hires ON fc_hires.parent = fc.fileid AND fc_hires.name=\"_hires\"
            WHERE fc.parent = (SELECT fileid FROM oc_filecache WHERE fileid = :fileid)
            AND fc.mimetype = 2
            /* If release date is enabled, compare the release date with current datetime  */            
            AND (
                CASE WHEN fd.release_active = 1
                THEN
                    $release_time_sql
                ELSE
                    1=1
                END
            )
            
            ORDER BY fd.pos ASC
            ";

        $folders = $db->exec($sql, array(
            ':fileid' => $fileid
        ));

        $folders = $this->translate($folders);
        $fol = array();
        if($recursive) {
            if (!empty($folders)) {
                foreach ($folders as &$folder) {

                    //add hires folder and files if available:
                    if($folder['hires_fileid'] != null){
                        $files_hires = $this->getFiles($folder['hires_fileid']);
                        $folder['files_hires'] = $files_hires;
                    }

                    //get all files:
                    $files = $this->getFiles($folder['fileid'], $ignoreReleaseTime);
                    $folder['files'] = $files;

                    //get sub dirs but only if the current is not marked as sub event
                    if(!$folder['is_subevent']){
                        $sub_folders = $this->getFolders($folder['fileid'], true, true, true, $ignoreReleaseTime);
                    }else{

                        //change folder type to a subevent folder type:
                        $folder['mimetype'] = 1000;
                    }

                    if(!empty($sub_folders)){
                        //we have some subfolders, add to parent:
                        $fol[] = $sub_folders;
                    }
                }
            }
        }


        if(!empty($fol)){
            foreach($fol as $fo){
                $folders = array_merge($folders, $fo);
            }
        }
        return $folders;
    }


    /**
     * Check if an actions should be performed for a file.
     * Actions are specified in the table erm_filedata, column "action"
     * @param $files
     */
    public function checkActions($files){
        //TODO: not implemented, yet. ;)

        
    }


    /**
     * Get folders inside a folder
     * @param $dirid
     * @return array() files.
     */
    public function getFiles($dirid, $ignoreReleaseTime = false){
        $db = $this->f3->get("db");

        if($ignoreReleaseTime){
            $release_time_sql = '1=1';
        }else{

        //simulate a time:
            if(isset($_REQUEST['time'])){
                $ct = str_replace('T', ' ', $_REQUEST['time']);
                $current_time = "'" . $ct . "'";
            }else{
                $current_time = 'NOW()';
            }

            $release_time_sql =  "fd.release_date < " . $current_time;
        }




        $sql = "SELECT fc.*,fd.id_events, fd.filename_en, fd.filename_de, fd.description_en, fd.description_de, fd.tags, fd.lang, fd.weight, 
                pf.fileid AS preview_fileid,
                pf.path AS preview_path,
                fd.release_active, fd.release_date, fd.release_message_en, fd.release_message_de

                FROM oc_filecache fc
                LEFT JOIN erm_filedata fd ON fd.fileid = fc.fileid

					/* pd: is there a preview directory */
                LEFT JOIN oc_filecache pd ON pd.parent = fc.parent AND pd.mimetype = 2 AND pd.name='_preview'

					/* pf: is there a preview file in the preview directory */
                LEFT JOIN oc_filecache pf ON pf.parent = pd.fileid AND pf.name = fc.name

                WHERE fc.parent = :fileid
                
                AND (
					CASE WHEN fd.release_active = 1
					THEN
						$release_time_sql
					ELSE
						1=1
					END
				)
                AND fc.mimetype <> 2";

        $files = $db->exec($sql, array(
            ':fileid' => $dirid
        ));


        //assign thumbnails for the file list:
        if($this->disableThumbGeneration == false){
            $files = $this->assignThumbs($files);
        }

        //check if actions should be performed:
        $this->checkActions($files);

        //translate files by selected language
        if(isset($this->disableTranslation) && $this->disableTranslation){
            //do not translate files
        }else{
            //translate files
            $files = $this->translate($files);
        }

        ksort($files);

        return $files;
    }

    /**
     * Prepare and return the search
     */
    private function getSearchQuery(){

        $qry = '';

        $search_columns = array("name", "filename_en", "filename_de", "description_en", "description_de", "tags");

        if(!empty($this->filter)){
            if(isset($this->filter['search'])){
                $s =  strtolower( $this->filter['search'] );

                $tmp = array();
                foreach($search_columns as $co){
                    $tmp[] = "LOWER(" . $co . ") LIKE :search ";
                }

                $qry .=  "AND (" .  join(" OR ", $tmp) . ")";

            }
        }

        //exclude system files from search:
        $qry .= " AND LEFT (name,1) != '_'";
        $qry .= " AND path NOT LIKE '%thumbnails/%'";


        return $qry;
    }

    /**
     * Will add special wildcard characters for mysql fulltext search
     * @param $text
     */
    private function prepareStringForFulltextsearch($text){
        $words = explode(' ', $text);
        $tmp = array();
        foreach($words as $word){

        }

    }

    /**
     * Get folders inside a folder
     * @param $etag
     */
    public function getFiles2($fileid){
        $db = $this->f3->get("db");

        $sql = "SELECT fc.*,fd.id_events, fd.filename_en, fd.filename_de, fd.description_en, fd.description_de,  fd.lang
                FROM oc_filecache fc
                LEFT JOIN erm_filedata fd ON fd.fileid = fc.fileid
                WHERE fc.parent = :fileid
                AND fc.mimetype <> 2";



        $files = $db->exec($sql, array(
            ':fileid' => $fileid
        ));

        //assign thumbnails for the file list:
        $files = $this->assignThumbs($files);

        //translate files by selected language
        $files = $this->translate2($files);

        ksort($files);

        return $files;
    }


    /**
     * Check if the file or directory is existing (and activated for sharing)
     * @param $etag
     *
     * @return bool
     */
     public function doesExist($fileid){
        $db = $this->f3->get("db");

        $sql = "SELECT fc.fileid as num, IF(sh.id IS NULL, 0, 1) as shared FROM oc_filecache fc
                LEFT JOIN oc_share sh ON sh.item_source = fc.fileid
                WHERE fc.fileid = :fileid";

        $data = $db->exec($sql, array(
            ':fileid' => $fileid
        ));

         $isadmin = $this->f3->get("ISADMIN");


         if(empty($data)){
             return false;
         }else{
             if($data[0]['shared'] || $isadmin){
                 return true;
             }else{
                 return false;
             }
         }

        return empty($data) ? false : true;
    }

    public function getContentType($filename){
        $types = array(
            'video' => array('mp4', 'webm', 'mov'),
            'image' => array('jpg', 'gif', 'png', 'jpeg'),
        );

        $file_info = pathinfo($filename);
        $file_ext = strtolower($file_info['extension']);

        foreach($types as $t => $extensions){
            if(in_array($file_ext, $extensions)){
                return $t;
            }
        }

        return 'file';
    }


    /**
     * Translate file names to set in the owncloud details modal, removes also meta files
     * This function adds also additional elements like human readable file date, sharing URL etc..
     * @param $data
     */
    private function translate($data = array()){
        $r = array();
        if(empty($data)){return $data;}
        $lang = $this->lang;

        foreach($data as $d){
            //remove meta files from directory listing:
            if(in_array($d['name'], $this->meta_files)){
                continue;
            }

            //set the file creation date based on selected language
            $d['filedate'] = date( $this->date_formats[$this->lang] , $d['mtime']);

            //insert additional information:
            $info = pathinfo($d['path']);
            $d['extension'] = strtolower( $info['extension'] );

            //get displayable/playable/downloadable content type:
            $d['filetype'] = $this->getContentType($d['path']);

            //convert int id to string hash
            $d['hashid'] = Hashid::alphaID($d['fileid']);

            //add public sharing link:
            $d['share_link'] = $this->getShareLink($d['fileid']);
            $d['share_hash'] = $this->getShareHash($d['fileid']);

            $fileName = !empty($d['filename_' . $lang]) ? $d['filename_' . $lang] : $info['filename'];
            $d['description'] = !empty($d['description_' . $lang]) ? $d['description_' . $lang] : '';

            //add human readable file size:
            $d['human_readable_filesize'] = human_filesize($d['size']);

            //is this file previewable? (should it be opened in a modal like a picture or direct download like doc file?)
            $d['previewable'] = in_array($d['extension'], $this->previewable_extensions) ? 1 : 0;

            $d['filename'] = $fileName;

            //visibility options activated?
            if(!empty($d['lang']) && $d['lang'] != $lang ){
                continue;
            }

            $r[ strtolower($d['weight'] . $fileName . $d['name']  .  $d['fileid'] ) ] = $d;

        }

        return $r;
    }




    /**
     * Get an encrypted sharing link for a file
     * @param $fileid
     */
    public function getShareLink($fileid=0){
        $sharing = $this->f3->get("sharing");   //get sharing options from config.ini

        if($fileid==0){
            //return just the whole url:
            return $sharing['public_url'];
        }

        //encrypt fileid with the salt and id by sha1
        $hash = $this->getShareHash($fileid);
        $link = $sharing['public_url'] . '/c/' . $hash;

        return $link;
    }

    /**
     * Get just the sharing hash
     * @param int $fileid
     */
    private function getShareHash($fileid=0){
        $sharing = $this->f3->get("sharing");   //get sharing options from config.ini
        //encrypt fileid with the salt and id by sha1
        $hash = sha1($sharing['salt'] . $fileid);
        return $hash;
    }

    /**
     * Assign thumbnails to a filelist, (generate new thumbs or get saved thumbs)
     * @param $files
     */
    public function assignThumbs($data)
    {
        if(!empty($data)) {
            $dir = dirname($data[0]['path']) . '/_thumbs';
            $alt_thumbs = $this->loadAlternativeThumbnails($dir);


            //apply alternative thumbs to current files:
            foreach ($data as &$d) {
                $fn = pathinfo($d['path'], PATHINFO_FILENAME);

                if(isset($alt_thumbs[$fn])){
                    $d['alt_thumb_id'] = $alt_thumbs[$fn]['fileid'];
                    $d['alt_thumb_path'] = $alt_thumbs[$fn]['path'];
                }else {
                    //try to get the default extension image, eg. _thumbs/pdf.jpg:
                    //$fn = pathinfo($d['path'], PATHINFO_FILENAME);
                    $file_info = pathinfo($d['path']);
                    $ext = strtolower($file_info['extension']);

                    if (isset($alt_thumbs[$ext])) {
                        $d['alt_thumb_id'] = $alt_thumbs[$ext]['fileid'];
                        $d['alt_thumb_path'] = $alt_thumbs[$ext]['path'];
                    }

                }
            }
        }

        foreach ($data as &$d) {
            $d = $this->checkThumbnail($d);
        }

        return $data;
    }

    //Assign thumbnail picture for the sub directory:
    public function assignFolderThumb($data){

    }

    /**
     * Assign thumbnails to a filelist, (generate new thumbs or get saved thumbs) - using the sql tunnel because of damn domain factory restrictions
     * @param $files
     */
    public function assignThumbsTunnel($data)
    {
        if(!empty($data)) {
            $dir = dirname($data[0]['path']) . '/_thumbs';
            $alt_thumbs = $this->loadAlternativeThumbnailsTunnel($dir);


            //apply alternative thumbs to current files:
            foreach ($data as &$d) {
                $fn = pathinfo($d['path'], PATHINFO_FILENAME);
                if(isset($alt_thumbs[$fn])){
                    $d['alt_thumb_id'] = $alt_thumbs[$fn]['fileid'];
                }
            }
        }


        foreach ($data as &$d) {
            $d = $this->checkThumbnail($d);
        }

        return $data;
    }


    /**
     * Check if the _thumbnails Directory exists and get the available thumbs
     * @param $currentPath
     *
     * @return alternative array with thumbs
     */
    private function loadAlternativeThumbnails($currentPath){
        $db = $this->f3->get("db");

        $sql = "SELECT * FROM oc_filecache WHERE parent = (SELECT fileid FROM oc_filecache WHERE path = :path)";

        $thumb_files = $db->exec($sql, array(
            ':path' => $currentPath
        ));

        $thumbs = array();

        foreach($thumb_files as $thumb){
            //get key (filename without extension)
            $fn = pathinfo($thumb['path'], PATHINFO_FILENAME);
            $thumbs[$fn] = $thumb;
        }

        return $thumbs;
    }


    /**
     * Check if the _thumbnails Directory exists and get the available thumbs
     * @param $currentPath
     *
     * @return alternative array with thumbs
     */
    private function loadAlternativeThumbnailsTunnel($currentPath){

        $sql = "SELECT * FROM oc_filecache WHERE parent = (SELECT fileid FROM oc_filecache WHERE path = '" . $currentPath . "')";

        $client = $this->getSqlTunnel();
        $thumb_files= $client->query($sql);

        $thumbs = array();

        foreach($thumb_files as $thumb){
            //get key (filename without extension)
            $fn = pathinfo($thumb['path'], PATHINFO_FILENAME);
            $thumbs[$fn] = $thumb;
        }

        return $thumbs;
    }



    /**
     * Get the data thumb directory
     * @param $id
     *
     * @return mixed
     */
    private function getThumbDataDir($file_id){

        $new_dir_every = 100;      //create a new thumb dir every x IDs
        $ex = explode(',', ($file_id/$new_dir_every) );

        printr($this->f3->get("thumbdir") );
        $thumbdir = $this->f3->get("thumbdir") . '/' . $ex[0];

        if(!file_exists($thumbdir)){
            mkdir($thumbdir);
        }

        return $thumbdir;
    }



    /**
     * Check if Thumbnail based on a data entry from owncloud exists, if not it will be generated
     * The method returns the owncloud data row with paths to the thumbnails
     * @param $d
     */
    public function checkThumbnail($d){
        
        $file_info = pathinfo($d['path']);
        $pic_extensions = array("jpg","png", "gif");
        $file_info['extension'] = strtolower($file_info['extension']);
        if( in_array($file_info['extension'], $pic_extensions) ){
            //this is a picture! try to generate a thumbnail

            genThumbs($d['fileid'], getDataPath($d['path']) );
        }else{
            //genThumbs($d['fileid'], getDataPath($d['path']) );

        }


        if(isset($d['alt_thumb_id'])){
            //generate thumb:
            $thumbdir = "./data/admin/thumbnails/" . $d['alt_thumb_id'];

        }else{
            $thumbdir = "./data/admin/thumbnails/" . $d['fileid'];
        }


        $thumbail_file = $thumbdir . '/32-32.jpg';

        if(file_exists($thumbail_file)){
            $d['thumbnail'] = $thumbail_file;
        }else{
            //generate thumbs:
            genThumbs($d['alt_thumb_id'], getDataPath(   $d['alt_thumb_path'] ) );
        }

        $big_thumb = $thumbdir . '/382x255.jpg';
        $d['thumbnail_big'] = $big_thumb;


        //find or create big picture:
        $max_thumb = glob($thumbdir . '/*max.jpg');


        $prop_thumbnail = $thumbdir . '/preview_prop.jpg';
        if(file_exists($prop_thumbnail)) {
            $d['prop_thumbnail'] =   $prop_thumbnail;
        }

            if(!empty($max_thumb)){
            $d['thumbnail_max'] = $max_thumb[0];
        }


        //set default thumbnail:
        if(!file_exists($thumbail_file)){
            //no thumbnail found. Assign a default thumbnail
            $d = $this->setDefaultThumbnail($d);
        }

        return $d;
    }



    /**
     * DEPRECATED: this function used png file extension for jpg files.. (generated by owncloud)
     * Check if Thumbnail based on a data entry from owncloud exists, if not it will be generated
     * The method returns the owncloud data row with paths to the thumbnails
     * @param $d
     */
    public function checkThumbnail_OLD($d){

        if(isset($d['alt_thumb_id'])){
            $thumbdir = "./data/admin/thumbnails/" . $d['alt_thumb_id'];
        }else{
            $thumbdir = "./data/admin/thumbnails/" . $d['fileid'];
        }

            $thumbail_file = $thumbdir . '/32-32.png';

        if(file_exists($thumbail_file)){
            $d['thumbnail'] = $thumbail_file;
        }

        $big_thumb = $thumbdir . '/382x255.jpg';
        $d['thumbnail_big'] = $big_thumb;


        //find or create big picture:
        $max_thumb = glob($thumbdir . '/*max.png');

        if(!file_exists($big_thumb)){
            if(!empty($max_thumb)){
                $this->generateThumbnail( $max_thumb[0], $big_thumb, 382,255 );
            }
        }

        if(!empty($max_thumb)){
            $d['thumbnail_max'] = $max_thumb[0];
        }



        //set default thumbnail:
        if(!file_exists($thumbail_file)){

            //no thumbnail found. Assign a default thumbnail
            $d = $this->setDefaultThumbnail($d);
        }

        return $d;
    }




    /**
     * Get file(s) in a specific directory
     * @param        $dir_id
     * @param string $search
     */
    private function getFilesInDirectory($dir_id, $search = ''){

        $db = $this->f3->get("db");
        $sql = "SELECT fileid FROM erm_filedata WHERE clean_name = :clean_name";




    }


    public function rebuildEventThumbs($dirid){
        $meta = $this->getMetaFiles($dirid);
        if(isset($meta['_header.jpg'])){
            //header image exists, check if the event thumb exists:
            $header = $meta['_header.jpg'];
            $sourceFile = getDataPath($header['path']);
            $ret = genThumbs($header['fileid'], $sourceFile);

            return $ret;
        }
    }

    /**
     * Assign a default thumbnail of no thumb was specified
     * @param $d
     */
    private function setDefaultThumbnail($d){
        //get filename:
        $filename = $d['path'];
        $file_info = pathinfo($filename);

        $file_ext = strtolower($file_info['extension']);

        $default_icons_dir = "ui/images/icons/";
        $default_file_thumb = $default_icons_dir . "ico_default.png";

        $thumb_for_file = $default_icons_dir .  $file_ext . '.png';
        //do we have a thumbnai for that format?

        if(file_exists($thumb_for_file)){
            $d['thumbnail'] = $thumb_for_file;
        }else{
            $d['thumbnail'] = $default_file_thumb;
        }

        $d['thumbnail_big'] = $d['thumbnail'];

        return $d;
    }

    public function generateThumbnail($sourceFile, $saveAs, $width, $height){
        set_time_limit(0);

        $imagine = new Imagine\Gd\Imagine();
        $size    = new Imagine\Image\Box($width, $height);

        $mode    = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;


        if(file_exists($sourceFile)){
            //Can I make a thumbnail of that file?
            $file_info = pathinfo($sourceFile);


            $pic_extensions = array("jpg","png", "gif");
            //$video_extensions = array("mp4","mpg", "mpeg");

            $extension = strtolower($file_info['extension']);

            //generate thumbnail out of a picture
            if( in_array($extension, $pic_extensions) ){
                $img = $imagine->open($sourceFile);
                $size = $img->getSize();
                //$img->thumbnail($size, $mode)->save( $saveAs );

                $file_width = $size->getWidth();
                $file_height = $size->getHeight();


                //$s = "convert -define jpeg:size={$file_width}x{$file_height} '$sourceFile' -thumbnail '{$width}x{$height}>' $saveAs";

                if(stripos($saveAs,"_prop") !== false){
                    //generate a thumbnail and preserve proportions
                    $s = "convert '$sourceFile' -thumbnail {$width}x{$height} $saveAs";
                }else{
                    //generate a thumbnail with cropping
                    $s = "convert -define jpeg:size={$file_width}x{$file_height} '$sourceFile' -thumbnail {$width}x{$height}^ -gravity center -extent {$width}x{$height} $saveAs";

                }

                $ret = system($s);

                $img = null;
                return $saveAs;
            }
        }else{
            //die("File $sourceFile does not exist");
        }

        return '';
    }


    /**
     * Extract a frame from a video
     * @param $sourceFile
     *
     * @return bool|string (return the filename or false on errors)
     */
    public function extractFrameFromVideo($sourceFile, $frame=10){
        global $f3;

        $app_path = $f3->get("APP_PATH");
        $tempNam = $app_path . "tmp/" . substr( md5(rand()), 0, 7) . ".jpg";

        //get the ffmpeg command path from config.ini
        $ffmpeg = $f3->get("FFMPEG");

        $sourceFile = str_ireplace(' ', '\ ', $sourceFile);
        $command = $ffmpeg . ' -i ' . $sourceFile . ' -vf "select=gte(n\,' . $frame . ')" -vframes 1 ' . $tempNam;

        $ex = exec($command);

        if(file_exists($tempNam)){
            return $tempNam;
        }else{
            return false;
        }
    }

    /**
     * Read meta information from a video file using ffprobe
     * @param $path
     */
    public function getVideoMeta($path){
        global $f3;

        $ffprobe = $f3->get("FFPROBE");
        $app_path = $f3->get("APP_PATH");
        $video_file_path = $app_path . $path;
        $video_file_path = str_replace(("/./"), "/" , $video_file_path );


        if(!file_exists($video_file_path)){
            return array();
        }

        $command = $ffprobe . ' -v quiet -select_streams v:0 -print_format json -show_format -show_streams -i "' . $video_file_path . '"';
        exec($command, $out);
        $meta = implode("\n", $out);
        return json_decode($meta, true);
    }

    /**
     * Download all files in a directory
     * @param $dirid
     */
    public function downloadDirectory($dirid, $fileName = 'download.zip'){
        //get all files in directory:
        $files = $this->getFiles($dirid);
        $download_list = array();

        foreach($files as $file){
            $download_list[$file['fileid']] = $file['fileid'];
        }

        $this->downloadFiles($download_list);
    }

    /**
     * Generate a ZIP for selected files and send it to browser
     * @param $download_list
     */
    public function downloadFiles($download_list, $fileName = 'download.zip'){
        $files = $this->getMultipleFiles($download_list);

        $arr_files = array();
        foreach($files as $file){
            $arr_files[] =   escapeshellarg( getDataPath($file['path']) );
        }
        $str_files = join(' ',  $arr_files );

        header('Content-Type: application/octet-stream');
        header('Content-disposition: attachment; filename="' . $fileName  . '"');
        //$fp = popen('zip -r - ' . $str_files . ' 2>&1', 'r');     //<-- this will show the output errors use this for debugging
        $fp = popen('zip -j -r - ' . $str_files , 'r');
        $bufsize = 8192;
        $buff = '';
        while( !feof($fp) ) {
            $buff = fread($fp, $bufsize);
            echo $buff;
        }
        pclose($fp);
    }


    //reposition folders:
    public function reposition($ids){
        $db = $this->f3->get("db");
        $sql = "UPDATE oc_filecache SET posy = :posy WHERE fileid = :fileid";

        foreach($ids as $pos=>$fileid  ){

            $db->exec($sql, array(
                ':posy' => $pos,
                ':fileid' => $fileid
            ));
        }

    }


    /**
     * Generate excel file for an event with thumbs, names and descriptions
     * The excel file can be later used to import the descriptions
     * @param $id
     */
    public function exportExcel($id){

        $event = getEvents($id);

        $this->disableTranslation = true;
        $folders = $this->getFolders($id, true, true);
        $folders = $this->sortFolders($folders);

        $ex = new PHPExcel();
        $ex->getProperties()->setCreator("Planet IT Services")
            ->setLastModifiedBy("Michael Milawski")
            ->setTitle("Event Export")
            ->setSubject("Event Export")
            ->setDescription("Owncloud Export file for updating file names and descriptions")
            ->setCategory("Export");

        $sheet = $ex->setActiveSheetIndex(0);
        $sheet->setTitle("Truck Bus Van");

        //prepare data for excel export
        $data = array();
        foreach($folders as $folder){
            //$data[] =  $this->getDataForExcel($folder);
            $data =  array_merge($data,  $this->getDataForExcel($folder) );
        }

        //generate the excel file:
        $row_id = 2;




        //set column widths:
        $column_widths = array(
            'A' => 5,
            'B' => 10,
            'C' => 30,
            'D' => 10,
            'E' => 30,
            'F' => 30,
            'G' => 30,
            'H' => 30,
            'I' => 30,
            'J' => 10,
            'K' => 15,
            'L' => 15,
        );

        foreach($column_widths as $column=>$width){
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        //Set excel header:
        $rowdata = array(
            'A' => "ID",
            'B' => "THUMB",
            'C' => "Filename",
            'D' => "Type",
            'E' => "Filename EN",
            'F' => "Filename DE",
            'G' => "Description EN",
            'H' => "Description DE",
            'I' => "Tags",
            'J' => "Language",
            'K' => "Weight",
            'L' => "Rethumb",
            );

            $this->setExcelRow($sheet, 1, $rowdata);


        $sheet->getStyle("A1:L1")->getFont()->setBold(true);

        foreach($data as $row){

            $this->addImageToExcel($sheet, "B" . $row_id, $row['thumbnail'] );

            $rowdata = array(
                'A' => $row['fileid'],
                'B' => "",
                'C' => $row['name'],
                'D' => $row['type'],
                'E' => $row['filename_en'],
                'F' => $row['filename_de'],
                'G' => $row['description_en'],
                'H' => $row['description_de'],
                'I' => $row['tags'],
                'J' => $row['lang'],
                'K' => $row['weight'],
                'L' => '',
            );

            $this->setExcelRow($sheet, $row_id, $rowdata);
            $row_id++;
        }


        //generate and output the file to browser:
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="owncloud_export.xls"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');

        header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
        header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header ('Pragma: public'); // HTTP/1.0

        $objWriter = PHPExcel_IOFactory::createWriter($ex, 'Excel5');
        $objWriter->save('php://output');
        exit();

    }


    private function addImageToExcel(&$sheet, $position, $imagePath){

        if(empty($imagePath) || !file_exists($imagePath)){
            return;
        }

        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        if($ext == 'jpg'){
            $gdImage = imagecreatefromjpeg($imagePath);
        }elseif($ext == 'png'){
            $gdImage = imagecreatefrompng($imagePath);
        }elseif($ext == 'gif'){
            $gdImage = imagecreatefromgif($imagePath);
        }else{
            //we only support jpg and png and gif!
            return;
        }

        // Add a drawing to the worksheetecho date('H:i:s') . " Add a drawing to the worksheet\n";
        $objDrawing = new PHPExcel_Worksheet_MemoryDrawing();
        $objDrawing->setName('Thumbnail');
        $objDrawing->setDescription('Thumbnail');
        $objDrawing->setImageResource($gdImage);
        $objDrawing->setRenderingFunction(PHPExcel_Worksheet_MemoryDrawing::RENDERING_JPEG);
        $objDrawing->setMimeType(PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_DEFAULT);
        $objDrawing->setHeight(32);
        $objDrawing->setOffsetX(20);
        $objDrawing->setOffsetY(5);
        $objDrawing->setWorksheet($sheet);
        $objDrawing->setCoordinates($position);
        //$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');$objWriter->save(str_replace('.php', '.xlsx', __FILE__));
    }

    private function setExcelRow(PHPExcel_Worksheet &$sheet, $row_id, $data){
        foreach($data as $column => $d){
            $sheet->setCellValue($column . $row_id, $d );
        }

        //set alignments
        //center the thumbnail:
        $sheet->getStyle("B" . $row_id)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        //set row height:
        $sheet->getRowDimension($row_id)->setRowHeight(32);

        //return $sheet;
    }

    private function getDataForExcel($folder){
        $data = array();

        //first we put the folder into the data array:
        $data[] = array(
            'fileid' => $folder['fileid'],
            'type' => 'folder',
            'name' => $folder['name'],
            'filename_en' => $folder['filename_en'],
            'filename_de' => $folder['filename_de'],
            'description_en' => $folder['description_en'],
            'description_de' => $folder['description_de'],
            'lang' => $folder['lang'],
            'weight' => $folder['weight']
        );

        $path = $folder['name'];

        //get the files in folder:
        foreach($folder['files'] as $file  ){
            $row = array(
                'fileid' => $file['fileid'],
                'path' => $path,
                'type' => $file['filetype'],
                'name' => $file['name'],
                'filename_en' => $file['filename_en'],
                'filename_de' => $file['filename_de'],
                'description_en' => $file['description_en'],
                'description_de' => $file['description_de'],
                'tags' => $file['tags'],
                'thumbnail' => $file['thumbnail'],
                'lang' => $file['lang'],
                'weight' => $file['weight']
            );
            $data[] = $row;
        }

        return $data;
    }


    /**
     * Search for files
     * @return array() files.
     */
    public function findFiles(){
        $db = $this->f3->get("db");

        $items_per_page = $this->filter['items_per_page'];
        $start_at = $this->filter['page'] * $items_per_page;

        $query_search = $this->getSearchQuery();

        
        $sql = "SELECT SQL_CALC_FOUND_ROWS fc.*,fd.id_events, fd.filename_en, fd.filename_de, fd.description_en, fd.description_de, fd.tags, fd.lang FROM (
                SELECT *, 
                (SELECT fileid FROM oc_filecache fc2
                    RIGHT JOIN oc_share sh ON sh.file_source = fc2.fileid
                    WHERE fc2.mimetype = 2 AND fc2.fileid = fc1.parent LIMIT 1) AS isShared
                    FROM oc_filecache fc1
                    
                ) AS fc
                
                LEFT JOIN erm_filedata fd ON fd.fileid = fc.fileid
                WHERE isShared IS NOT NULL $query_search AND fc.mimetype <> 2 AND storage=1
                ORDER BY name
                LIMIT $start_at,$items_per_page
";


        if(!empty($query_search)){
            $params = array("search" => "%" . strtolower($this->filter['search']) . "%");
        }


        $files = $db->exec($sql, $params);



        //get count of total found files:

        $total_count = $db->exec("SELECT FOUND_ROWS() AS total_count");
        $total_count = $total_count[0]['total_count'];


        //assign thumbnails for the file list:
        $files = $this->assignThumbs($files);

        //translate files by selected language
        $files = $this->translate($files);

        ksort($files);
        //printr($files);

        return array(
            "total_count" => $total_count,
            "files" => $files
        );
    }

    /**
     * return only various keys from an array list
     * @param $data (array)
     * @param $keys (array)
     */
    private function getOnly($data, $keys){
        $tmp = array();
        foreach($data as $d){
            $row = array();

            foreach($keys as $k){
                $row[$k] = isset($d[$k]) ? $d[$k] : '';
            }

            $tmp[] = $row;
        }

        return $tmp;
    }

    /**
     * get files by search params:
     * @param $params
     */
    public function search($params = array()){

        //set filters for search
        $this->filter = array(
            'search' => isset($params['search']) ? $params['search'] : '',
            'page' => isset($params['page']) ? $params['page'] : 0,
            'items_per_page' => isset($params['items_per_page']) ? $params['items_per_page'] : 10
        );

        $data = $this->findFiles();

        $files = $this->getOnly($data['files'],
            array(
                'fileid', 'path', 'parent', 'name', 'size', 'tags', 'thumbnail', 'thumbnail_big', 'thumbnail_max', 'extension', 'filetype', 'hashid', 'share_link', 'description', 'human_readable_filesize', 'filename', 'filedate'
            ));

        $files = !empty($files) ? $files : array();

        return array(
            "total_count" => $data['total_count'],
            "files" => $files
        );


    }
    
    
    public function getAllEvents($onlyVisible = true){
        global $f3;

        $ret = array();
        $db = $f3->get("db");
        $date_format = $f3->get("date_format");
        
        $events = getEvents(0,array('onlyvisible'=> $onlyVisible));
        $default_thumb = "ui/images/default/default_event.jpg";
        
        foreach ($events as $event) {
            $thumbs = $this->rebuildEventThumbs($event['fileid']);
            $event_date = getEventDate( $event['date_from'], $event['date_to'] );

            $date = date_parse($event['date_from']);

            $ret[] = array(
                'fileid' => $event['fileid'],
                'slug' => $event['clean_name'],
                'year' => $date['year'],
                'event_name' => $event['event_name'],
                'date' =>  $event_date,
                'location' =>  $event['location'],
                'thumb' => isset($thumbs['event']) ? $thumbs['event'] : $default_thumb,
            );
        }

        return $ret;
    }

}