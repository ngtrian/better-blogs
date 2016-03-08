<?php

/**
 * Renderer for the view.
 * Formats the content as necessary
 */
class XfAddOns_Blogs_ViewPublic_Blog_EditDescription extends XenForo_ViewPublic_Base
{
	
	/**
	 * Function called when the page is being rendered as HTML
	 */
	public function renderHtml()
	{
		// add the editor for when the person wants to edit the description
		$blog = &$this->_params['blog'];
		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'description', $blog['description'],
			array('editorId' =>
					'description_' . substr(md5(microtime(true)), -8))
		);
	}
	
	
}