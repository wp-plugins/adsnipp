/*
Track clicks from special elements
*/
 
(function($) {
	$(document).ready(function() {
		$(document).on('click', 'div.gofollow', function(){
			var tracker = $(this).attr("data-track");
	
			$.post(click_object.ajax_url, {'action':'adsnipp_click','track':tracker});	
		});
	});
}(jQuery));