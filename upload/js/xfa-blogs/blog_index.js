
/**
 * ########################################################################################
 * Used to update the title of the blog
 * ########################################################################################
 */
!function($, window, document, _undefined) {
	
	/**
	 * Bind to any button or area that has an edit action
	 * This is used to "Edit the Title" of the blogs, and to edit the description inline
	 */
	XenForo.ShowEditableArea = function(ele) {
		
		// get variables related to the element
		var areaName = ele.data('area'),
			area = $('#' + areaName),
			editable = $('#' + areaName + "-edit"),
			field = editable.children('.input'),
			originalValue;
		
		/**
		 * Called when we return from the ajax call
		 */
		var fieldChangeCallBack = function(ajaxData, textStatus) {
			if (ajaxData.error) {
				XenForo.hasResponseError(ajaxData, textStatus);
			} else {
				area.text(ajaxData.newValue);
			}
		};		
		
		/**
		 * Applies the title change to the blog
		 */
		var applyFieldChange = function() {
			// we'll apply the change immediately
			var newValue = field.val();
			area.text(newValue);
			area.show();
			ele.show();
			
			editable.hide();
			
			// do the ajax call
			if (newValue != originalValue) {
				var formValues = {};
				formValues[field.attr('id')] = newValue;
				XenForo.ajax(ele.attr('href'), formValues, fieldChangeCallBack);
			}
		};		
		
		// bind the blur to the text field
		field.blur(applyFieldChange);
		if (ele.data('newline') == true) {
			field.keyup(function(e) {
			    if(e.which == 13) {
			        applyFieldChange();
			    }
			});			
		}
		
		// bind the click event to the button
		ele.click(function(e) {
			e.preventDefault();
			
			// hide old elements
			ele.hide();
			area.hide();
			
			// show the new elements
			originalValue = area.text();
			field.val(originalValue);
			editable.show();
			field.focus();
		});
	};	
	
	XenForo.register('.ShowEditableArea', 'XenForo.ShowEditableArea');
}(jQuery, this, document);


/**
 * ########################################################################################
 * For the Edit Description functionality, we need to update the description afterwards
 * ########################################################################################
 */
!function($, window, document, _undefined)
{

	/**
	 * Binding for Edit, on form validation we will update the value that we get back from the response
	 */
	XenForo.EditDescriptionForm = function($form) {
		$form.bind('AutoValidationComplete', function(e) {
			var ajaxData = e.ajaxData;
			if (ajaxData.error) {
				XenForo.hasResponseError(ajaxData, textStatus);
			} else if (typeof(ajaxData.description) !== 'undefined') {
				$('#blogDescription').html(ajaxData.description);
			}
		});		
	};
	
	
	XenForo.register('.EditDescriptionForm', 'XenForo.EditDescriptionForm');
	
}(jQuery, this, document);


/**
 * ########################################################################################
 * Bind on delete entry, to substitute for the deleted entry bit
 * ########################################################################################
 */
!function($, window, document, _undefined)
{
	/**
	 * Form used to delete an entry. On complete, we need to replace with the new message
	 */
	XenForo.DeleteEntryForm = function($form) {
		$form.bind('AutoValidationComplete', function(e) {
			var ajaxData = e.ajaxData;
			if (ajaxData.error) {
				XenForo.hasResponseError(ajaxData, textStatus);
			} else if (e.ajaxData.entry) {
				$('#entry-' + e.ajaxData.entry_id).replaceWith(ajaxData.entry);
				$('#entry-' + ajaxData.entry_id).xfActivate();
			} else {
				XenForo.alert(ajaxData._redirectMessage, '', 1000, $.context(function() {
					window.location.href = ajaxData._redirectTarget;
				}, this));				
			}
		});
	};	
	
	XenForo.register('#DeleteEntryForm', 'XenForo.DeleteEntryForm');
}(jQuery, this, document);


/**
 * ########################################################################################
 * Bind on form submit, to update the categories panel
 * ########################################################################################
 */
!function($, window, document, _undefined)
{

	/**
	 * Binding for Edit, on form validation we will update the value that we get back from the response
	 */
	XenForo.CategoryEditForm = function($form) {
		
		$form.bind('AutoValidationComplete', function(e) {
			var ajaxData = e.ajaxData;
			
			if (ajaxData.error) {
				XenForo.hasResponseError(ajaxData, textStatus);
			} else if (typeof(ajaxData.categoryPanelTemplate) !== 'undefined') {
				$('.categoriesContainer').replaceWith(ajaxData.categoryPanelTemplate);
				$('.categoriesContainer').xfActivate();
				$('.categoriesContainer .bCustom').toggle();
			}
		});		
	};	
	
	XenForo.register('#CategoryEditForm', 'XenForo.CategoryEditForm');
}(jQuery, this, document);

/**
 * ########################################################################################
 * Link used to turn on the customization options on the blog
 * ########################################################################################
 */
!function($, window, document, _undefined)
{
	/**
	 * Form used to delete an entry. On complete, we need to replace with the new message
	 */
	XenForo.CustomizeBlog = function(link) {
		link.click(function(e) {
			e.preventDefault();
			$('.bCustom').toggle();
		})
	};
	
	XenForo.register('a.CustomizeBlog', 'XenForo.CustomizeBlog');
}(jQuery, this, document);


/**
 * ########################################################################################
 * Upload form will redirect
 * ########################################################################################
 */
!function($, window, document, _undefined)
{
	/**
	 * Form used to delete an entry. On complete, we need to replace with the new message
	 */
	XenForo.UploadCustomizationForm = function(form) {
		form.bind('AutoInlineUploadComplete', function(e) {
			
			console.log('AutoInlineUploadComplete was fired for the upload');
			
			var ajaxData = e.ajaxData;
			if (ajaxData.error) {
				XenForo.hasResponseError(ajaxData, textStatus);
			} else if (typeof(ajaxData._redirectTarget) !== 'undefined') {
				console.log('Form back, redirecting');
				document.location.href = ajaxData._redirectTarget;
			}
		});			
	};
	
	XenForo.register('#UploadCustomizationForm', 'XenForo.UploadCustomizationForm');
}(jQuery, this, document);


/**
 * ########################################################################################
 * Palette for editting the blog style
 * ########################################################################################
 */

!function($, window, document, _undefined) {
	
	/**
	 * Initialize the buttons in the panel and the cancel button
	 */
	XenForo.CssPanel = function(div) {
		// bound the cancel button
		// div.find('input#btnCancel').click(function(e) {
		//	div.hide();
		// });
		
		// initialize the panels that select a color
		$('#cssColor').miniColors({});
		$('#cssBgColor').miniColors({});
		$('#cssBorderColor').miniColors({});
		
		// hide the div when we click out of it
		$(document).mouseup(function (e) {
			var miniColorsDiv = $('.miniColors-selector');
		    if (div.has(e.target).length === 0 && miniColorsDiv.has(e.target).length === 0) {
		        div.hide();
		    }
		});
		
		// reposition the div behind body, else all position calculations will fail
		$(document).ready(function() {
			$('body').append(div);	
		});
	};	
	
	/**
	 * Everytime a field changes
	 */
	XenForo.CssUpdate = function(input) {
		
		var inputFieldChanged = function(e) {
			var ajaxHref = $('.CssPanel').data('href');
			var className = $('.CssPanel').data('class-name');
			
			var valueToApply = '';
			if (input.data('varname') == 'background-image') {
				valueToApply = 'url(' + input.val() + ')';
			} else {
				valueToApply = input.val();
			}

			// make the changes to the elements
			var elements = $(className);
			elements.css(input.data('varname'), valueToApply);

			// if we are changing color, that applies to the enclosed links as well
			if (input.data('varname') == 'color') {
				var elementsAnchor = $(className).children('a');
				elementsAnchor.css(input.data('varname'), valueToApply);
			}
			
			// ajax callback
			var ajaxCallback = function(ajaxData, textStatus) {
				if (ajaxData.error) {
					XenForo.hasResponseError(ajaxData, textStatus);
				} else {
					// XenForo.alert(ajaxData._redirectMessage, '', 1000);
				}
			};
			
			var params = {
				className: className,
				varname: input.data('varname'),
				value: input.val()
			};			
			XenForo.ajax(ajaxHref, params, ajaxCallback);
		};

		if (input[0].tagName == 'SELECT')
		{
			input.change(inputFieldChanged);
		}
		else
		{
			input.blur(inputFieldChanged);
			input.bind('colorChanged', inputFieldChanged);	
		}
	};
	
	/**
	 * When we click on a "C"
	 */
	XenForo.ShowColorSelector = function(link) {
		// bind the click event to the button
		link.click(function(e) {
			e.preventDefault();

			var cssPanel = $('.CssPanel'); 
			var isPanelVisible = cssPanel.css('display') != 'none';
			var ajaxCallback = function(ajaxData, textStatus) {
				if (!ajaxData.error) {
					if (ajaxData['color']) {
						cssPanel.find('#cssColor').val(ajaxData['color']);
						$('#cssColor').miniColors('value', ajaxData['color']);
					}
					if (ajaxData['background-color']) {
						cssPanel.find('#cssBgColor').val(ajaxData['background-color']);
						$('#cssBgColor').miniColors('value', ajaxData['background-color']);
					}
					if (ajaxData['background-image']) {
						cssPanel.find('#cssBgImage').val(ajaxData['background-image']);
					}
					if (ajaxData['font-family']) {
						cssPanel.find('#cssFontFamily').val(ajaxData['font-family']);						
					}
					if (ajaxData['font-size']) {
						cssPanel.find('#cssFontSize').val(ajaxData['font-size']);						
					}
					if (ajaxData['border-color']) {
						cssPanel.find('#cssBorderColor').val(ajaxData['border-color']);
						$('#cssBorderColor').miniColors('value', ajaxData['border-color']);
					}					
				}
			};
			
			if (isPanelVisible)
			{
				cssPanel.hide();
			}
			else
			{
				// clear the panel (between calls we should not retain values, ajax will load it later)
				cssPanel.find('#cssColor').val('');
				cssPanel.find('#cssBgColor').val('');
				cssPanel.find('#cssBgImage').val('');
				cssPanel.find('#cssFontFamily').val('');
				cssPanel.find('#cssFontSize').val('');
				cssPanel.find('#cssBorderColor').val('');
				
				// trigger an ajax request to load the values
				var params = { className: link.data('class-name') };
				XenForo.ajax(cssPanel.data('css-source'), params, ajaxCallback);
				
				var offset = link.offset();
				var width = link.children('img').width();
				
				cssPanel.data('class-name', link.data('class-name'));		// set the className on the container, will be used later on change
				cssPanel.css('top', offset.top + "px");
				cssPanel.css('left', (offset.left + width) + "px");
				cssPanel.fadeIn(600);
			}
		});
	};	
	
	XenForo.register('.CssPanel', 'XenForo.CssPanel');
	XenForo.register('.CssUpdate', 'XenForo.CssUpdate');
	XenForo.register('.ShowColorSelector', 'XenForo.ShowColorSelector');
	
}(jQuery, this, document);





/**
 * ########################################################################################
 * Check if we have a session started in sister sites
 * ########################################################################################
 */
!function($, window, document, _undefined)
{

//	$(document).ready(function() {
//		
//		console.log(federatedBlogs);
//		
//		if (!federatedBlogs || !federatedBlogs.sid) {
//			console.log('Federated info not found');
//			return;
//		}
//		
//		var callback = function(ajaxData, textStatus) {
//		};		
//		
//		var currentSession = $.cookie('ffl_session');
//		console.log('Current session is: ' + currentSession);
//		
//		if (currentSession != federatedBlogs.sid || true) {
//			
//			console.log('Calling ajax to set to: ' + federatedBlogs.sid);
//			XenForo.ajax('index.php?xfa-blogs/1/start-session', { sid: federatedBlogs.sid }, callback);
//			
//			
//			console.log('Setting session to ' + federatedBlogs.sid);
//			$.cookie('ffl_session', federatedBlogs.sid, { path: '/' });
//			$.cookie('ffl_session2', federatedBlogs.sid, { path: '/' });
//		}
//		
//		console.log('Session is: ' + currentSession);
//	});
	
	
//	
//	var completeCallback = function(jqXHR, textStatus) {
//		
//		console.log(jqXHR.statusCode());
//		console.log(jqXHR.getAllResponseHeaders());
//		console.log(jqXHR.getResponseHeader('Set-Cookie'));
//		
//		console.log('Complete: ' + textStatus);
//	};
//	
//	$.ajax({
//		url: 'http://localhost/xenforo/federatedBlogs.php',
//		complete: completeCallback,
//		type: 'GET',
//		dataType: 'json',
//		data: {
//		}
//	});
	
	//ffl_session
	// Value	e948ea231f68d9e62474aa3e66626242
	
	
}(jQuery, this, document);






