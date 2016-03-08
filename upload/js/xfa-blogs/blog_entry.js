
!function($, window, document, _undefined)
{
	/**
	 * Form used to edit a comment. On complete, we need to replace with the new message
	 */
	XenForo.EditCommentForm = function($form) {
		$form.bind('AutoValidationComplete', function(e) {
			var newMessage = $form.find('#ctrl_message').val();
			$('#comment-' + e.ajaxData.comment_id + ' blockquote.messageText').text(newMessage);
		});
	};
	
	/**
	 * Form used to delete a comment. Replace with the delete bit
	 */
	XenForo.DeleteCommentForm = function($form) {
		$form.bind('AutoValidationComplete', function(e) {
			var ajaxData = e.ajaxData;
			if (ajaxData.error) {
				XenForo.hasResponseError(ajaxData, textStatus);
			} else if (ajaxData.comment) {
				$('#comment-' + ajaxData.comment_id).replaceWith(ajaxData.comment);
				$('#comment-' + ajaxData.comment_id).xfActivate();
			} else {
				XenForo.alert(ajaxData._redirectMessage, '', 1000, $.context(function() {
					window.location.href = ajaxData._redirectTarget;
				}, this));				
			}
		});
	};	
	
	XenForo.register('#EditCommentForm', 'XenForo.EditCommentForm');
	XenForo.register('#DeleteCommentForm', 'XenForo.DeleteCommentForm');	
	
}(jQuery, this, document);
