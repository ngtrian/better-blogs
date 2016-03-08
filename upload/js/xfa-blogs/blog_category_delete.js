
/**
 * ########################################################################################
 * Used after we delete a category, both in the create entry and the index page
 * ########################################################################################
 */
!function($, window, document, _undefined)
{
	
	/**
	 * Binding for Edit, on form validation we will update the value that we get back from the response
	 */
	XenForo.DeleteCategoryForm = function($form) {
		
		console.log('Doing the binding for category');
		
		$form.bind('AutoValidationComplete', function(e) {
			
			console.log('Bound Validation Complete');
			
			var ajaxData = e.ajaxData;
			if (ajaxData.error) {
				XenForo.hasResponseError(ajaxData, textStatus);
			} else if (typeof(ajaxData.categoryId) !== 'undefined') {
				console.log('Removing row');
				$('#category-' + ajaxData.categoryId).remove();
			}
		});		
	};
	
	XenForo.register('#DeleteCategoryForm', 'XenForo.DeleteCategoryForm');
	
}(jQuery, this, document);

