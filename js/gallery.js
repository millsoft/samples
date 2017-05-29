var GAL = GAL || {};
GAL.gallery = {
    current_path: '',
    init: function (path) {
        this.init_events();
        this.tpl = $(".template-files");
        this.tpl_folders = $(".template-folders");
    },
    init_events: function () {
        console.log("init_events. Path: " + APP_PATH);

    },

    /* Get a directory listing */
    dir: function (path) {
        this.current_path = path;

        params = {
            path: path
        };

        var _this = this;
        $.post("a/dir", params, function (data, status) {
            _this.render(data);
        }, "json");
    },

    /* Display the file listing */
    render: function (data) {
        //clear directory listing:
        $D = $("#dirlist");
        $F = $("#dir_folders");

        //Clear the view in case this was called multiple times:
        $D.find(".fileitem").remove();
        $F.find(".folderitem").remove();
        var $T = this.tpl;

        var tpl_folders = $(".template-folders");

        //load sub directories:
        $.each(data.folders, function (i, d) {
            $_FO = $(tpl_folders).clone();
            $_FO.removeClass("template template-folders");
            $_FO.removeAttr("id");
            $_FO.addClass("grid-item");



            var $folder_name = $_FO.find(".folder_name");
            var $link = $_FO.find(".dir_icon");
            $folder_name.text(d.display_name);
            $link.attr("href", "dir/" + d.hashid);
            $F.append($_FO);

        });


                //load files thumbnails:
        $.each(data.files, function (i, d) {
            var $_F = $T.clone();
            var $metainfo = $_F.find(".metainfo");
            $metainfo.find(".filename").text(d.display_name);
            $_F.removeClass("template template-files");
            $_F.addClass("fileitem grid-item");

            //set meta info for the image:
            $_F.data("hashid", d.hashid);
            //$_F.addClass("loading_thumb");

            //Get an object with all availabl thumbnail sizes:
            var src = getThumbBySize(d.hashid, d.file_name);

            var $a = $_F.find("a");
            $a.attr("href", "t/preview/" + d.hashid + "/" + d.file_name);

            var $img = $a.find("img");
            $img.data("thumb", src.gal);
            $img.attr("src", src.gal).load(function () {

            });



            //append the file item to the container:
            $D.append($_F);

            //console.log(d);
        });

        $('a.gallery').colorbox({rel:'gal', transition:"fade", height:"90%"});


        //Render Masonry:
        savvior.init("#dirlist", {
            "screen and (max-width: 320px)": {columns: 1},
            "screen and (min-width: 320px) and (max-width: 640px)": {columns: 2},
            "screen and (min-width: 640px) and (max-width: 1280px)": {columns: 3},
            "screen and (min-width: 1280px) and (max-width: 1600px)": {columns: 4},
            "screen and (min-width: 1600px)": {columns: 5},
        });



    },

    //Event watcher for Clicks, changes, etc..
    eventwatcher: function(){
        $("#dirlist").on("click", ".fileitem.grid-item", function(){
            console.log($(this).data("hashid"));
        });
    },

    masonry: function () {

        //Render Masonry:
        savvior.init("#dirlist", {
            "screen and (max-width: 400px)": {columns: 2},
            "screen and (min-width: 400em) and (max-width: 600px)": {columns: 3},
            "screen and (min-width: 600px)": {columns: 4},
        });

    }

}

/**
 * Update small pre Thumbs with bigger one:
 */
function loadBigThumbs(){
    var toProcess = $("img.loading");
    $.each(toProcess, function(i, d){
        //console.log($(this).data("thumb"));
        $(this).attr("src", $(this).data("thumb"));
    });
}

/**
 * Get an object of various thumb sizes based on hash id
 * @param hashid - a unique hashid
 * @param filename
 * @returns {{}}
 */
function getThumbBySize(hashid, filename){
    var sizes = ['pre','gal', 'preview'];
    var re = {};
    for(var a = 0; a < sizes.length ; a++){
        var current_size = sizes[a];
        re[current_size] = "t/" + current_size + "/" + hashid + "/" + filename
    }

    return re;
}

$(function () {
    _G = GAL.gallery;
    _G.init();
    _G.dir(INITIAL_PATH);
    _G.eventwatcher();

    //$('a.gallery').colorbox({rel:'gal', transition:"fade", width:"75%", height:"75%"});

    //GAL.gallery.init();


});

