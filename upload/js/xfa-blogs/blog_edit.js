
/**
 * ########################################################################################
 * Used when we create a category
 * ########################################################################################
 */
!function($, window, document, _undefined)
{

	/**
	 * Binding for Edit, on form validation we will update the value that we get back from the response
	 */
	XenForo.CreateCategoryForm = function($form) {
		$form.bind('AutoValidationComplete', function(e) {
			var ajaxData = e.ajaxData;
			if (ajaxData.error) {
				XenForo.hasResponseError(ajaxData, textStatus);
			} else if (typeof(ajaxData.categoryTemplate) !== 'undefined') {
				$('#categoryList').append(ajaxData.categoryTemplate);

				var categoryBox = $('#category-' + ajaxData.category_id);
				if (categoryBox)
				{
					categoryBox.parent().xfActivate();	// overlays and anything inside
					categoryBox.find('input').attr('checked', 'checked');
				}
			}
		});		
	};
	
	/**
	 * The row has hidden controls for delete
	 */
	XenForo.Category = function(ele) {
		ele.mouseenter(function(e) {
			ele.find('.categoryControls').show();
		});
		ele.mouseleave(function(e) {
			ele.find('.categoryControls').hide();
		});
	};
	
	XenForo.register('#CreateCategoryForm', 'XenForo.CreateCategoryForm');
	XenForo.register('.Category', 'XenForo.Category');
	
}(jQuery, this, document);

/**
 * ########################################################################################
 * Functionality for scheduled entries
 * ########################################################################################
 */

!function($, window, document, _undefined)
{

	var dateField = null,
		hourField = null,
		minuteField = null,
		secondField = null;
	
	/**
	 * This method will check if the date that is selected is in the future. If that is the case
	 * then we will show a notice to the user saying that the entry won't be published immediately but will
	 * rather be published in the future 
	 */
	var checkDate = function() {
		try {
			var val = dateField.val();
			var bits = val.split('-');
			
			// set the individual fields of the date object
			var selectedDate = new Date();
			selectedDate.setFullYear(bits[0]);
			selectedDate.setMonth(bits[1] - 1);
			selectedDate.setDate(bits[2]);
			selectedDate.setHours(hourField.val());
			selectedDate.setMinutes(minuteField.val());
			selectedDate.setSeconds(secondField.val());
			
			var selectedMillis = selectedDate.getTime();
			var timeNow = new Date().getTime();
			if (selectedMillis > timeNow) {
				$('#futureEntryMessage').show();
			} else {
				$('#futureEntryMessage').hide();
			}			
		} catch (ex) {
			console.log(ex);
		}
	};
	
	/**
	 * DateChange is a container that has several children, one of which is the date, the others is the time.
	 * When any of these change, we will need to calculate if the date is in the future, in which case
	 * we will display to the user an alert
	 */
	XenForo.DateChange = function($span) {
		dateField = $span.find('#ctrl_post_date');
		hourField = $span.find('#ctrl_hour');
		minuteField = $span.find('#ctrl_minute');
		secondField = $span.find('#ctrl_second');
		
		dateField.bind('change', checkDate);
		hourField.bind('change', checkDate);
		minuteField.bind('change', checkDate);
		secondField.bind('change', checkDate);
	};
	
	XenForo.register('.DateChange', 'XenForo.DateChange');
	
}(jQuery, this, document);

/**
 * ########################################################################################
 * The privacy feature enables a list of users
 * ########################################################################################
 */

!function($, window, document, _undefined)
{
	
	XenForo.BlogsPrivacySelector = function(select) {
		select.on('change', function(e) {
			var value = select.val();
			var membersField = $('#ctrl_allow_members_names');
			if (value == 'list') {
				membersField.show();
			} else {
				membersField.hide();
			}
		})
	};
	
	XenForo.register('.PrivacySelector', 'XenForo.BlogsPrivacySelector');
	
	
}(jQuery, this, document);



