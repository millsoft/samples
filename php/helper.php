<?php
/**
 * Helper functions for Project
 */


/**
 * Get the Menu for a logged in user based on the user role
 * @return mixed
 */
function getMenu()
{
    global $f3;
    $db = $f3->get("db");
    $lang = strtolower($f3->get("lang"));

    $sql = "SELECT * FROM up_areas WHERE FIND_IN_SET(:usergroup,id_usergroups)";


    $menus = $db->exec($sql, array(
        "usergroup" => (int)$f3->GET("SESSION.user.group")
    ));

    return $menus;
}

/**
 * Include additional JS files set in the router: - will be included in the footer
 */
function includeAdditionalJS()
{
    global $f3;
    $require_js = $f3->get("require_js");

    $include_js = [];

    if (isset($require_js) && !empty($require_js)) {
        foreach ($require_js as $js) {
            $js_path = strpos($js, "/") === false ? "ui/js/" : '';
            $js_file = $js_path . $js;
            $include_js[] = "<script src='" . $js_file . "'></script>";
        }
        echo implode("\n", $include_js);
    }
}

/**
 * Get the directory for a file. Will create one if it does not exist
 * @param $fileid
 * @param bool $createIfNotExists
 * @return string
 */
function getContentDir($fileid, $createIfNotExists = true)
{
    global $f3;

    $seperate_by = 1000;
    $content_dir = $f3->get("content_dir");

    $maindir = $content_dir . round($fileid / $seperate_by);
    $filedir = $maindir . '/' . $fileid;

    //Create content directory:
    if (!file_exists($content_dir)) {
        mkdir($content_dir);
    }

    try {
        //create main directory:
        if ($createIfNotExists) {
            if (!file_exists($maindir)) {
                mkdir($maindir);
            }

            //create sub directory:
            if (!file_exists($filedir)) {
                mkdir($filedir);
            }
        }

        return $filedir;

    } catch (Exception $ex) {
        return '';
    }

}

/**
 * Get Human Readable File Size
 * @param $bytes
 * @param int $dec
 * @return string
 */
function human_filesize($bytes, $dec = 2)
{
    if ($bytes == null) {
        $bytes = 0;
    }
    $size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $factor = floor((strlen($bytes) - 1) / 3);

    return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
}

/**
 * Get file type of a file. Is it a picture, video or any other file type?
 */
function getFileType($filename)
{
    $pi = pathinfo($filename);

    $extensions = array(
        'image' => array('jpg', 'jpeg', 'png', 'gif'),
        'movie' => array('avi', 'mp4', 'webm', 'mov'),
        'archive' => array('zip', 'gz', 'tar', 'rar'),
        'audio' => array('mp3', 'mp2', 'wav', 'aiff'),
        'code' => array('css', 'js', 'php', 'sql', 'html', 'htm'),
        'excel' => array('xls', 'xlsx'),
        'pdf' => array('pdf'),
        'powerpoint' => array('ppt', 'pptx'),
        'word' => array('doc', 'docx'),
        'text' => array('txt', 'md'),
    );

    $file_extension = strtolower($pi['extension']);

    foreach ($extensions as $type => $exts) {
        if (in_array($file_extension, $exts)) {
            return array('type' => $type, 'extension' => $file_extension);

        }
    }

    return array('type' => 'file', 'extension' => $file_extension);
}

/**
 * Print a string or array nicely with the line number.
 * @param $data - string or array
 * @param $die - bool - should the script be stopped?
 */

function printr($data, $die = true)
{
    $bt = debug_backtrace();
    $caller = array_shift($bt);

    echo "<pre>";
    echo "<b>{$caller['file']} <span style='color:red'>(Line: {$caller['line']})</span></b><br/>";
    print_r($data);
    echo "</pre>";

    if ($die) {
        die();
    }
}


/**
 * Print a list of <option>
 * @param $data
 */
function printSelectOptions($data, $val_key = 'id', $label_key = 'name')
{
    foreach ($data as $row) {
        echo "<option value='" . $row[$val_key] . "'>" . $row[$label_key] . "</option>";
    }
}

/**
 * Returns hashed version of clean password
 * @param $clean_password
 */
function getPasswordHash($clean_password)
{
    //TODO: Add Salts
    return sha1($clean_password);
}

/**
 * Remove a directory recursively
 * @param $dir
 */
function rmdirRecursive($dir)
{
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }

    rmdir($dir);
}


/**
 * Load a plugin
 * @param $plugin
 * @return bool
 */
function loadPlugins()
{

    global $f3;
    //get all enabled plugins:
    $plugins = $f3->get("plugins");

    if (empty($plugins)) {
        //no plugins found:
        return false;
    }

    if (!is_array($plugins)) {
        //we have only one plugin, convert it to array:
        $plugins = array($plugins);
    }

    //store all plugins here:
    $allPlugins = array();

    foreach ($plugins as $plugin) {

        //does the plugin exist?
        $plugin_path = __DIR__ . '/../plugins/' . $plugin . '/plugin.php';
        if (!file_exists($plugin_path)) {
            continue;
        }

        require_once($plugin_path);

        $pl = $plugin . 'Plugin';
        $P = new $pl;
        //$config = $P->init();

        $allPlugins[$plugin] = $P;

    }

    //Get all injects:
    $injects = array();
    foreach ($allPlugins as $plugin_name => $plugin) {
        if (method_exists($plugin, "getinjects")) {
            $injects[$plugin_name] = $plugin->getinjects();
        }
    }
    $f3->set("injects", $injects);

    $f3->set("plugins", $allPlugins);


}

function loadInject($area)
{
    global $f3;
    $path = $f3->get("PATH");

    $injects = $f3->get("injects");

    if (empty($injects)) {
        return false;
    }

    foreach ($injects as $plugin_name => $ipa) {
        $foundPath = '';
        if (isset($ipa[$path])) {
            $foundPath = $path;
        }

        if (isset($ipa['*'])) {
            $foundPath = '*';
        }

        $i = $ipa[$foundPath];
            if (isset($i[$area])) {
                if (is_array($i[$area])) {
                    echo implode("\n", $i[$area]);
                } else {
                    echo $i[$area];
                }
            }

    }
}

/**
 * Load all Plugin Menus:
 */
function loadPluginMenus()
{
    global $f3;
    $plugins = $f3->get("plugins");

    $menus = array();
    if (empty($plugins)) {
        return $menus;
    }
    foreach ($plugins as $name => $plugin) {
        //get menu:
        $pluginMenus = array();

        if (method_exists($plugin, "getMenu")) {
            $pluginMenus = $plugin->getMenu();
        }

        if (!empty($pluginMenus))
            foreach ($pluginMenus as $mnu) {
                $menus[] = $mnu;
            }
    }

    return $menus;

}

/**
 * Get full paths of file and index
 * @param $id - file id
 * @param $sourcefile - filename
 * @return array
 */
function _getFullPaths($id, $sourcefile){
    $content_dir = getContentDir($id);
    $sourceimage = $content_dir . '/' . $sourcefile;
    $thumb_dir = $content_dir . '/t';

    return array(
        'content_dir' => $content_dir,
        'sourceimage' => $sourceimage,
        'thumb_dir' => $thumb_dir,
    );

}