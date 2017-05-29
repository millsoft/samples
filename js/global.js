/**
 * GLOBAL FUNCTIONS - UPLOAD2 - by Michael Milawski 2016
 */

//store selected rows in this object:
selected_rows = {};


//define global UPL object. will be extended in otehr foles and used in the whole app:
UPL = {
	version: "Upload2 - Version 2.0.1 Alpha by Michael Milawski - Last Update 31.07.2016",

	//main file object and cuntions
	File: {},

	//Ajax callbacks
	Ajax:{

		ajax_reponse_codes : {
				'SAME_FILE': 'A file with the same name is already in the folder.\nPlease choose a different filename',
				'EMPTY_FILENAME': "A filename can't be empty"
			},
		'checkAjaxReponse': function(response){

			if(typeof this.ajax_reponse_codes[response] !== 'undefined'){
				UPL.fn.alert(this.ajax_reponse_codes[response]);
			}
		}
	},

	//Global functions
	fn:{
		alert: function(txt){
			//show a customized alert box
			alert(txt);
		}
	},

	DataTables: {
		'init' : function(){
			this.datatable_info = $("#datatable_info");
			this.extra_info = $(".extra_info");

		},
		//Update the info footer after files has been selected
		'updateInfoFooter': function(){
			//TODO: WTF ist das?
			$(this.extra_info).text("SSSSSSS");

		},

		/**
		 * Load all stored selections (used each time after fnDraw od datatables has been called)
		 */
		'loadSelections' : function(){
				var $datatable = $("#datatable");
				_.each(selected_rows, function(a){
					$datatable.find(".row_chk[data-id=" + a + "]").prop("checked", true) ;
				});
			}
	}

};


function showModalLoader(){
    $(".modal_loader").show();
}

function hideModalLoader(quick){

    if(quick != undefined){
        $(".modal_loader").hide();
    }else{
        $(".modal_loader").fadeOut();
    }
}


//READY
$(function(){

	//on click on the sidebar menu, select the link and load the page.
	$(".treeview-menu li").on("click", function(e){
		//remove all actives:
		$(".treeview-menu li.active").removeClass("active");
		$(this).addClass("active loading");
	});
});
