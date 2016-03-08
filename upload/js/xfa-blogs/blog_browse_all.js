
!function($, window, document, _undefined)
{
	/**
	 * Form used to edit a comment. On complete, we need to replace with the new message
	 */
	XenForo.YearContainer = function(div) {
		
		children = div.children('.monthContainer');
		for (var i = 0; i < children.length; i += 3)
		{
			var first = $(children[i]);
			var second = ((i + 1) < children.length) ? $(children[i + 1]) : null;
			var third = ((i + 2) < children.length) ? $(children[i + 2]) : null;

			// calculate the max height of all the layers
			var maxHeight = first.height();
			if (second) {
				maxHeight = Math.max(maxHeight, second.height());
			}
			if (third) {
				maxHeight = Math.max(maxHeight, third.height());
			}
			
			// set the max height of al the layers
			first.height(maxHeight);
			if (second) {
				second.height(maxHeight);
			}
			if (third) {
				third.height(maxHeight);
			}
		}
	};

	XenForo.register('.YearContainer', 'XenForo.YearContainer');
	
}(jQuery, this, document);
