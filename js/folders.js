/**
 * Upload2 - by Michael Milawski
 * Folders Module.
 * Last Update 26.07.2016
 */



$(function () {

    $modal_file = $("#modal_file");
    $file_form = $modal_file.find("form");


    table = $("#datatable").DataTable({
        conditionalPaging: {
            style: "fade",
            speed: 500
        },
        "order": [[2, "asc"]],
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "admin/get_galleries",
            "type": "POST",
            "data": function (d) {
                d.id_parent = $("#id_parent").val()
            }
        },
        "columnDefs": [

            {
                "orderable": false,
                "className": "centered",
                "render": function (data, type, row) {
                    var buttons = [
                        "<div data-id='" + row.x.id + "'>",
                        "<a class='btn btn-default edit'><i class='glyphicon glyphicon-edit'></i></a>",
                        "<a href='" + row.x.file + "' title='Download' class='btn btn-default download'><i class='fa fa-download'></i></a>",
                        "<a class='btn btn-danger del_gallery'><i class='glyphicon glyphicon-trash'></i></a>"
                    ];

                    if (row.x.type == 2) {
                        //Show additional buttons only for folders:
                        buttons.push("<a class='btn btn-default plugin email' title='Send Gallery to E-Mail contacts'><i class='fa fa-envelope'></i></a>");
                    }

                    buttons.push("</div>");

                    return buttons.join(" ");
                },
                "targets": 5
            },

            {
                "orderable": false,
                "className": "col_icon",
                "render": function (data, type, row) {
                    var icon = "<i class='fa fa-folder'></i>";

                    if (row.x.type != 2) {
                        //not a directory. Show a thumbnail or a default icon
                        //thumbnails are only available for images right now.
                        if (row.x.ftype == 'image') {
                            icon = "<img onload='UPL.loadedIcon(this)' class='file_icon loading' src='" + row.x.icon + "'>";
                        } else {
                            var css_filetype = row.x.ftype == 'file' ? row.x.ftype : 'file-' + row.x.ftype;
                            icon = "<i class='fa fa-" + css_filetype + "-o'></i>";
                        }

                    }

                    return icon;
                },
                "targets": 1
            }
            ,
            {
                "orderable": true,
                "className": "col_icon",
                "render": function (data, type, row) {
                    return "<div class='r_date'>" + row.x.date + "</div>" + "<div class='r_time'>" + row.x.time + "</div>";

                    return icon;
                },
                "targets": 3
            }

            ,
            {
                "orderable": true,
                "className": "col_size",
                "render": function (data, type, row) {
                    if (row.x.type == 2) {
                        //do not show file size for directories
                        return "";
                    } else {
                        return "<div class='r_date'>" + row.x.file_size + "</div>";
                    }

                },
                "targets": 4
            }

            ,
            {
                "orderable": false,
                "className": "col_chk"

                ,
                "render": function (data, type, row) {
                    return "<input data-id='" + row.x.id + "' class='row_chk' type='checkbox'>";
                },
                "targets": 0
            }

        ],
        "fnDrawCallback": function (oSettings) {
            UPL.DataTables.loadSelections();
        }
    });

    UPL.DataTables.init();

    /**
     * Delete Gallery
     */

    $datatable = $("#datatable");

    $datatable.on("click", ".btn.del_gallery", function (e) {
        e.preventDefault();
        var id = $(this).closest("div").data("id");

        if (confirm("Do you really want to delete this item?")) {

            $.post("admin/delete_selected", {ids: [id]}, function () {
                table.draw(false);
            });
        }
    });

    $datatable.on("load", ".file_icon", function (e) {
        console.log("Loaded!");
    });

    /**
     * User clicked on the row. Open the file or directory. Ignore the button area.
     */
    $datatable.on("click", "tr td:not(:first-child,:last-child)", function (e) {
        e.preventDefault();
        var data = table.row(this).data();

        if (data.x.type == 2) {
            window.location = "admin/galleries/" + data.x.id;
        } else {

            $.post("admin/get_file/" + data.x.id, function (data, status) {
                //console.log(data);
                $modal_file.find(".title").text(data.virtual_filename);
                $file_form.find("#f_filename,#f_old_filename").val(data.virtual_filename);
                $file_form.find("#val_id").val(data.id);
                $file_form.find("#f_filename").attr("placeholder", data.filename);

                //$("#preview").css("background-image", "url(pics/500/500/" + data.content_file) + ")";
                $("#preview").css("background-image", "url(" + data.preview) + ")";
                UPL.parseExifData(data.exif);
                hideModalLoader();

            }, "json");
            $("#modal_file").modal("show");
        }
    });


    /**
     * Open Gallery Modal
     */
    $(".btn.addgallery").click(function (e) {
        e.preventDefault();
        $("#val_id").val("");
        hideModalLoader(true);
        $("#modal_gallery").modal("show");

    });


    /**
     * Open Uploader Modal
     */
    $(".btn.openupload").click(function (e) {
        e.preventDefault();
        $("#modal_upload").modal("show");
    });

    $("#modal_upload").on('hidden.bs.modal', function () {
        table.draw();
    });


    //save folder modal:
    $(".frm.gallery").submit(function () {
        showModalLoader();
        var params = {
            id: $("#val_id").val(),
            id_parent: $("#id_parent").val(),
            name: $("#val_name").val()
        };

        $.post("admin/add_gallery", params, function (re) {
            table.draw();
            $("#modal_gallery").modal("hide");
        });
    });

    $("#datatable").on("click", ".btn.edit", function (e) {
        e.preventDefault();
        showModalLoader();
        $("#modal_gallery").modal("show");

        var id = $(this).closest("div").data("id");
        $.getJSON("admin/get_gallery/" + id, function (data) {
            $("#val_id").val(data.id);
            $("#val_name").val(data.filename);
            hideModalLoader();
        });
    });


    //we are in the root directory, display only sub galleries and no files
    if ($("#id_parent").val() == 0) {
    }

    /**
     * Set Focus when modal opened
     */
    $("#modal_gallery").on("shown.bs.modal", function (e) {
        $("#val_name").focus();
    });

    //additional action buttons, will be shown if a file was selected:
    $selection = $(".actionbar .selection");

    $("#datatable").on("click", ".row_chk", function (e) {
        e.stopPropagation();

        var checked = $(this).prop("checked");
        var id = $(this).data("id");

        //unchecked, remove from object
        if (!checked) {
            if (typeof selected_rows['x' + id] !== "undefined") {
                delete selected_rows['x' + id];
            }
        } else {
            //user checked a row. add it to object:
            selected_rows['x' + id] = id;
        }

        var selected_rows_len = Object.keys(selected_rows).length;

        //show the action bar for selected files:
        if (selected_rows_len > 0 && $selection.hasClass("hidden")) {
            $selection.removeClass("hidden");
        }

        //hide the action bar because no file was selected:
        if (selected_rows_len == 0 & !$selection.hasClass("hidden")) {
            $selection.addClass("hidden");
        }


        //.removeClass("hidden");

    });

    //Delete selected files:
    $(".selection .delete").click(function (e) {
        e.preventDefault();
        var yes = confirm("Do you rally want to delete selected files?");

        if (yes) {
            var items = {ids: _.values(selected_rows)};
            $.post("admin/delete_selected", items, function (re) {
                table.draw(false);
            });
        }

    });


    //Save file modal:
    $file_form.submit(UPL.File._onSaveFileForm);


    //Dropzone Init:
    Dropzone.options.frm_upload = {
        parallelUploads: 10

    };

});


/**
 * Fill the Exif Table with Content
 * @param exifData
 */
UPL.parseExifData = function (exifData) {

    if (exifData.length == 0) {
        //No Exif information was provided, show a message and hide the exif table
        $(".info_status.not_found").show();
        $("#exif_table").hide();
        return;
    }

    $("#exif_table").show();
    $(".info_status.not_found").hide();


    var rows = "";
    $("#exif_table tbody").html("");

    $.each(exifData, function (label, value) {
        rows += "<tr>";
        rows += "<td>" + label + "</td>";
        rows += "<td>" + value + "</td>";
        rows += "</tr>";
    });

    $("#exif_table tbody").html(rows);
}

/**
 * hide loader animation after the icon in datatables has been loaded:
 * @param t
 */
UPL.loadedIcon = function (t) {
    $(t).removeClass("loading");
}

/**
 * Save form data when pressed on save in the content modal
 * @param e
 * @private
 */
UPL.File._onSaveFileForm = function (e) {
    //will be called when user saves a file form:
    e.preventDefault();
    var form_data = $(this).serialize();

    $.post("admin/save_file", form_data, function (response, status) {
        if (response != '') {
            var processed = UPL.Ajax.checkAjaxReponse(response);
            if (processed === true) {
                //everything is OK, close the modal, reload the datatable
            }
        } else {
            table.draw(false);
            $("#modal_file").modal("hide");
        }


    });

}
