$(document).ready(function() {
	//
});

function elitasoft_ajax_action(url,hook,action,params,form) {
	var ajaxdata = {
		hook: hook,
		action: action,
		params: params
	};
	if(form!=undefined) {
		ajaxdata.formData=form.serialize();
	}
	$.ajax({
		url: url,
		data:ajaxdata,
		dataType: "json",
		type: "POST",
		success:function(data) {
			if(data.type=="log") {
				console.log(data.msg);
			} else if (data.type=="js") {
				eval(data.code);
			}
		}
	});  
}

function attach_inplace_edit(url_root) {
	$(function(){
		$('.elitasoft-inplace-edit').click(function() {
			var elem = $(this);
			var table = elem.attr('data-t');
			var column = elem.attr('data-c');
			var rowid = elem.attr('data-r');
			var reload = elem.attr('data-reload');
			
			var input_size = 5;
						
			var replaceWith = $('<input name="temp" size="'+input_size+'" type="text" />');
					
			replaceWith.val(elem.html());
			
	 		elem.hide();
	 		elem.after(replaceWith);
					
	 		replaceWith.focus();
	 		replaceWith.select();
		
			replaceWith.blur(function() {
		
				if ($(this).val() != elem.html()) {
					elem.text($(this).val());
					var url=url_root + '/elitasoft/ajax/ajax.php';
					var hook='common';
					var action='elitasoft_inplace_submit';
					var params={
						t:table,
						c:column,
						r:rowid,
						v:$(this).val(),
						reload:reload
					};
					elitasoft_ajax_action(url,hook,action,params,null);
				}
		
				$(this).remove();
				elem.show();
			});
		});
	});
}