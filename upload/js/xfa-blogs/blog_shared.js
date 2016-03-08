
/**
 * ########################################################################################
 * Logic for subscribe and unsubscribe, both blogs and entries
 * ########################################################################################
 */

!function($, window, document, _undefined)
{
	
	/**
	 * Whenever the user clicks on "watch"
	 */
	XenForo.WatchBlog = function($link) {
		var callback = function(ajaxData, textStatus) {
			if (ajaxData.error) {
				XenForo.hasResponseError(ajaxData, textStatus);
			} else {
				$('#watchContainer').hide();
				$('#unwatchContainer').show();
				XenForo.alert(ajaxData._redirectMessage, '', 1000);
			}
		};
		
		$link.click(function(e) {
			e.preventDefault();
			XenForo.ajax($link.attr('href'), {}, callback);
		});			
	};
	
	/**
	 * Whenever the user clicks on "unwatch"
	 */
	XenForo.UnwatchBlog = function($link) {
		var callback = function(ajaxData, textStatus) {
			if (ajaxData.error) {
				XenForo.hasResponseError(ajaxData, textStatus);
			} else {
				$('#unwatchContainer').hide();
				$('#watchContainer').show();
				XenForo.alert(ajaxData._redirectMessage, '', 1000);
			}
		};
		
		$link.click(function(e) {
			e.preventDefault();
			XenForo.ajax($link.attr('href'), {}, callback);
		});			
	};		
	
	XenForo.register('a.WatchBlog', 'XenForo.WatchBlog');
	XenForo.register('a.UnwatchBlog', 'XenForo.UnwatchBlog');
	
}(jQuery, this, document);



