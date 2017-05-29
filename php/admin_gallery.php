<?php

    /**
     * List all galleries (root)
     */
    class Admin
    {

        public function __construct($f3)
        {
            $this->f3 = $f3;
        }

        public function galleries()
        {
            $f3 = $this->f3;
            $f3->set("require_js", ["folders.js"]);
            $f3->set("id_parent", "0");
            $f3->set('content', 'l_galleries_only.php');
            echo View::instance()->render('layout.htm');
        }

        /**
         * Open the Gallery in admin
         * @param $f3
         * @param $params
         */
        public function open_gallery($f3, $params)
        {
            $f3->set("require_js", ["vendor/enyo/dropzone/dist/min/dropzone.min.js", "folders.js", "files.js"]);
            $id = $f3->get("PARAMS.id");
            $db = $f3->get("db");
            $data = $db->exec("SELECT * FROM up_files WHERE id = :id AND type=2", ["id" => $id]);

            if (empty($data)) {
                $f3->error(404);
            }

            $data = $data[0];
            $f3->set("gallery", $data);
            $f3->set("id_parent", $id);

            $bread = $this->getBreadthumb($id);
            $f3->set("breadcrumb", $bread);

            $f3->set('content', 'l_galleries_only.php');
            echo View::instance()->render('layout.htm');
        }

        /**
         * Load gallery options
         * @param $f3
         * @param $params
         */
        public function gallery_options($f3, $params)
        {
            //$f3->set("require_js", ["vendor/enyo/dropzone/dist/min/dropzone.min.js",  "folders.js", "files.js"]);
            $id = $f3->get("PARAMS.id");
            $db = $f3->get("db");

            $data = $db->exec("SELECT * FROM up_gallery_options WHERE gallery_id = :id", array(
                "id" => $id
            ));
            if(!empty($data)){
                $data = $data[0];
            }else{
                $data = array();
            }



            $method = $f3->get("VERB");
            if($method == 'POST'){
                //save form:
                $this->save_gallery_options($id);
            }

            $f3->set("gallery", $data);
            $f3->set("id_parent", $id);


            $bread = $this->getBreadthumb($id);
            $f3->set("breadcrumb", $bread);

            $f3->set('content', 'l_gallery_options.php');
            echo View::instance()->render('layout.htm');
        }

        /**
         * Save gallery options
         * @param $f3
         * @param $params
         */
        private function save_gallery_options($id){
            $f3 = $this->f3;
            $db = $f3->get("db");
            $G = $f3->get("POST.gallery");

            //do we have already some gallery options for that ID?
            $sql = "SELECT id FROM up_gallery_options WHERE gallery_id = :gallery_id";
            $found = $f3->db->exec($sql, array(
                "gallery_id" => $id
            ));

            if($found){
                //update entry
                $sql_action = "UPDATE";
                $sql_where = "WHERE gallery_id = " . (int)$id;
            }else{
                //new entry
                $sql_action = "INSERT INTO";
                $sql_where = '';
            }

            $sql = <<<SQL
$sql_action up_gallery_options SET 
  title = :title,
  gallery_id = :gallery_id,
  gallery_password = :gallery_password
  $sql_where
  
SQL;

            //Save:
            $db->exec($sql, array(
                "title" => $G['title'],
                "gallery_password" => $G['gallery_password'],
                "gallery_id" => $id,
            ));

            //self redirect to prevent POST on refresh
            $f3->reroute("/admin/galleries/{$id}/options");

        }

        /**
         * Get File Meta Information (AJAX)
         * @param $f3
         * @param $params
         */
        public function get_file($f3, $params)
        {

            $id = $f3->get("PARAMS.id");
            if (empty($id)) {
                $f3->error(404);
            }

            //get file:
            $db = $f3->get("db");
            $data = $db->exec("SELECT *, IF(display_name != '', display_name, filename) AS virtual_filename FROM up_files WHERE id = :id", array(
                'id' => (int)$id
            ));

            if (empty($data)) {
                $f3->error(404);
            }

            $data = $data[0];

            $source_file = $data['path'] . '/' . $data['filename'];
            $exif = Images::getExif($source_file);
            $exif_data = Images::parseExifData($exif);
            //printr($exif_data);
            $data['exif'] = $exif_data;

            //printr($exif);
            $data['preview'] = 't/preview/' . $id . '/' . $data['filename'];

            die(json_encode($data));
        }


        //Save file settings:
        public function save_file($f3)
        {
            $F = $f3->get("POST");
            $db = $f3->get("db");

            $D = $F['f'];


            $D['filename'] = trim($D['filename']);

            if ($D['old_filename'] != $D['filename']) {
                //rename file:
                //check if we don't have a file in the same directory with the same new filename:
                $found = $db->exec("SELECT id FROM up_files WHERE filename = :filename AND id_parent = (SELECT id_parent FROM up_files WHERE id = :id) AND type != 2", array(
                        'filename' => $D['filename'],
                        'id'       => $F['id'])
                );

                if (!empty($found)) {
                    die("SAME_FILE");
                }

            }

            $db->exec("UPDATE up_files SET display_name = :filename WHERE id = :id", array(
                'filename' => $D['filename'],
                'id'       => $F['id'],
            ));

            //check

            //print_r($F);

        }

        public function delete_selected($f3)
        {
            $ids = $f3->get("POST.ids");
            $db = $f3->get("db");
            $user_group = $f3->get("SESSION.user.group");
            $user_id = $f3->get("SESSION.user.id");

            $db->begin();

            //put here the files that were really deleted from the database. In the 2nd step the files will be removed from disk
            $deleteFiles = array();

            foreach ($ids as $id) {

                $sql_owner = $user_group > 1 ? "AND id_owner = $user_id" : '';
                //do we have sub dirs and files?
                $sql = "select  GROUP_CONCAT(id) AS ids
                    from    (select * from up_files
                             order by id_parent, id) files_sorted,
                            (select @pv := '$id') initialisation
                    where   find_in_set(id_parent, @pv) > 0
                    and     @pv := concat(@pv, ',', id)
                    $sql_owner";

                $subFiles = $db->exec($sql);
                if (!empty($subFiles)) {
                    //we found some sub files! add these ids to deleteFiles array:
                    $sub_ids = explode(",", $subFiles[0]['ids']);
                    foreach ($sub_ids as $sub_id) {
                        //delete the file from database:
                        $db->exec("DELETE FROM up_files WHERE id = :id", array(
                            "id" => $sub_id
                        ));
                        $deleteFiles[] = $sub_id;
                    }
                }


                if ($user_group == 1) {
                    //admin, can delete also files from other users:
                    $canBeDeleted = $db->exec("DELETE FROM up_files WHERE id = :id", array(
                        "id" => $id
                    ));

                } else {
                    //only users files can be deleted:
                    $canBeDeleted = $db->exec("DELETE FROM up_files WHERE id = :id AND id_owner = :id_owner", array(
                        "id"       => $id,
                        "id_owner" => $user_id,
                    ));

                }

                if ($canBeDeleted) {
                    $deleteFiles[] = $id;
                }
            }

            if ($db->commit()) {
                //files were deleted from database. Not remove the files from disk:

                foreach ($deleteFiles as $id) {
                    $this->deleteContent($id);
                }
            }

        }


        /**
         * remove a content directory and the contents:
         * @param $id
         */
        private function deleteContent($id)
        {
            if ($id == 0 || empty($id)) {
                return false;
            }
            $content_dir = getContentDir($id, false);

            if (!file_exists($content_dir)) {
                return false;
            }

            rmdirRecursive($content_dir);
        }

        /**
         * Generate a breadcrumb path from the current directory to root.
         * @param $currentDir
         */
        private function getBreadthumb($currentDir)
        {
            $db = $this->f3->db;
            $crumbs = array();
            if ($currentDir == 0) {
                //we are already on root dir, leave the method, return nothing.
                return false;
            }

            do {
                $sql = "SELECT id, id_parent, filename FROM up_files WHERE type=2 AND id = :id";
                $row = $db->exec($sql, array("id" => $currentDir));
                $r = $row[0];

                $onRoot = $r['id_parent'] == 0 ? true : false;
                $currentDir = $r['id_parent'];

                $crumbs[] = array(
                    'id'       => $r['id'],
                    'filename' => $r['filename'],
                );

            } while (!$onRoot);
            $crumbs = array_reverse($crumbs);

            //generate breadcrumb html:
            $str = array();

            $str[] = '<ol class="breadcrumb">';
            $str[] = "<li><a href='admin'><i class='fa fa-home'></i> Galleries</a></li>";
            $pos = 0;

            foreach ($crumbs as $crumb) {
                $pos++;
                $isActive = $pos == count($crumbs) ? 'active' : '';
                $str[] = "<li class='$isActive'>";

                if (!$isActive) {
                    $str[] = "<a href='admin/galleries/" . $crumb['id'] . "'>";
                    $str[] = $crumb['filename'];
                    $str[] = "</a></li>";
                } else {
                    $str[] = $crumb['filename'];
                }


            }

            $str[] = '</ol>';

            $str = join("", $str);

            return $str;

        }


    }


    /**
     * Add new gallery to database
     */
    $f3->route('POST /admin/add_gallery',
        function ($f3) {
            $db = $f3->get("db");

            //should we add or update?
            $id = $f3->get("POST.id");
            $user_id = $f3->get("SESSION.user.id");

            if (empty($id)) {
                $db->exec("INSERT INTO up_files SET filename = :name, id_parent = :id_parent, id_owner = :id_owner, type=2, ctime = now()", array(
                    "name"      => $f3->get("POST.name"),
                    "id_parent" => $f3->get("POST.id_parent"),
                    "id_owner"  => $user_id,
                ));
            } else {
                $db->exec("UPDATE up_files SET filename = :name, id_parent = :id_parent WHERE id = :id", array(
                    "name"      => $f3->get("POST.name"),
                    "id"        => $id,
                    "id_parent" => $f3->get("POST.id_parent"),
                ));
            }


        }
    );


    /**
     * Get all galleries for datatables in json format
     */
    $f3->route('POST /admin/get_galleries',
        function ($f3) {

            //print_r($_POST);
            $P = $f3->get("POST");

            $columns = [0, 1, 'virtual_filename', 'ctime', 'file_size'];
            $start = $P['start'];
            $length = $P['length'];
            $order_by = $columns[$P['order'][0]['column']];
            $order_dir = $P['order'][0]['dir'];
            $id_parent = !empty($P['id_parent']) ? $P['id_parent'] : 0;

            //Prepare Search:
            $search = '';
            if (!empty($P['search']['value'])) {
                $search = "AND filename LIKE '%" . $P['search']['value'] . "%'";
            }

            $db = $f3->get("db");
            $data = $db->exec("SELECT SQL_CALC_FOUND_ROWS  *, IF(display_name != '', display_name, filename ) AS virtual_filename FROM up_files
                            WHERE id_parent = :id_parent {$search}
                            ORDER BY type DESC, {$order_by} {$order_dir} LIMIT {$start},{$length}", [
                //Params:
                "id_parent" => $id_parent,
            ]);

            $rows_count = $db->exec("SELECT FOUND_ROWS() AS rows_count");
            $rows_count = $rows_count[0]['rows_count'];

            $galleries = [];
            $content_dir = $f3->get("content_dir");

            $icon_size = $f3->get("thumbs.icon");


            foreach ($data as $gal) {
                $thumb = str_replace($content_dir, "", $gal['path']);

                $file_type = getFileType($gal['filename']);

                $icon = "t/icon/" . $gal['id'] . "/" . $gal['filename'];

                $galleries[] = [
                    "",
                    "icon",
                    $gal['virtual_filename'],
                    "time",
                    "filesize",
                    "x" => [
                        "type"      => $gal['type'],
                        "id"        => $gal['id'],
                        "file_size" => human_filesize($gal['file_size'], 0),
                        "date"      => date("d.m.Y", strtotime($gal['ctime'])),
                        "time"      => date("H:i", strtotime($gal['ctime'])),
                        "icon"      => $icon,
                        "file"      => $gal['path'] . "/" . $gal['filename'],
                        "ftype"     => $file_type['type'],
                        "extension" => $file_type['extension']
                        ,
                    ]
                ];
            }

            $return = [
                "draw"            => $f3->get("POST.draw"),
                "recordsTotal"    => $rows_count,
                "recordsFiltered" => $rows_count,
                "data"            => $galleries
            ];


            die(json_encode($return));

        }
    );


    /**
     * Upload files
     */
    $f3->route('POST /admin/upload',
        function ($f3) {

            $db = $f3->get("db");

            $web = \Web::instance();
            $overwrite = false; // set to true, to overwrite an existing file; Default: false
            $slug = true; // rename file to filesystem-friendly version


            //at first we create a database entry as we need the ID for directory name:

            $db->begin();
            $db->exec("INSERT INTO up_files SET
                      status=0,
                      id_parent = :id_parent,
                      id_owner = :id_owner,
                      ctime = now()
                      "

                , [
                    "id_parent" => $f3->get("POST.id_parent"),
                    "id_owner"  => $f3->get("SESSION.user.id")
                ]
            );


            $fileid = $db->lastInsertId();
            $f3->set("fileid", $fileid);

            //set / create the upload directory:
            $upload_dir = getContentDir($fileid);

            $f3->set("UPLOADS", $upload_dir . "/");

            $files = $web->receive(function ($file, $formFieldName) {

                global $f3;
                $db = $f3->get("db");

                $db->exec("UPDATE up_files
                      SET status=1, filename=:filename, path = :path, file_size = :filesize

                      WHERE id=:id", [

                    'id'       => $f3->get("fileid"),
                    'filename' => basename($file['name']),
                    'path'     => dirname($file['name']),
                    'filesize' => $file['size'],

                ]);

                $db->commit();

                // everything went fine, hurray!
                return true; // allows the file to be moved from php tmp dir to your defined upload dir
            },
                $overwrite,
                $slug
            );

        });


    /**
     * Get Gallery content by json
     */
    $f3->route('POST|GET /get_gallery/@id',
        function ($f3) {

            $id = $f3->get("PARAMS.id");
            $db = $f3->get("db");

            $data = $db->exec("SELECT * FROM up_files WHERE gallery_id = :gallery_id", ["gallery_id" => $id]);

            $content_dir = $f3->get("content_dir");

            //prepare array
            $files = [];
            foreach ($data as $file) {

                $thumb = str_replace($content_dir, "", $file['path']);

                $files[] = [
                    "id"       => $file['id'],
                    "filename" => $file['filename'],
                    "path"     => $file['path'],
                    "thumb"    => "thumbs/200/200/" . $thumb . "/" . $file['filename'],
                    //"preview" => "pics/800/600/" . $thumb . "/" . $file['filename'] . "&ff=FFFFAA"
                    "preview"  => "pics/800/600/" . $thumb . "/" . $file['filename'] . "&nu"
                ];
            }
            die(json_encode($files));
        });


    /**
     * Delete files from gallery
     */
    $f3->route('POST /admin/del_files',
        function ($f3) {

            $P = $f3->get("POST");
            $ids = implode(",", $f3->get("POST.ids"));

            if (empty($ids)) {
                die("EMPTY");
            }

            $db = $f3->get("db");
            $sql = "DELETE FROM up_files WHERE id IN ($ids)";
            $db->exec($sql);

        });


    /**
     * Delete directory or file:
     */
    $f3->route('POST /admin/delete',
        function ($f3) {
            $db = $f3->get("db");
            $id = $f3->get("POST.id");

            //Get the information about the file:
            $data = $db->exec("SELECT * FROM up_files WHERE id = :id", array("id" => $id));
            if (empty($data)) {
                die("404");
            } else {
                $data = $data[0];
            }


            $db->begin();
            if ($data['type'] == 2) {
                //user tries to remove a directory:
                $db->exec("DELETE FROM up_files WHERE id = " . (int)$id);
                $db->exec("DELETE FROM up_files WHERE id_parent = " . (int)$id);
            } else {
                //user tries to remove a file:
                $db->exec("DELETE FROM up_files WHERE id = " . (int)$id);
            }

            //TODO: remove files and directories recursively! now it only deletes current dir/file and all 1st degree children:
            //begin a transaction which will remove the pictures and the gallery itself:
            $db->commit();
            echo "OK";
        });
