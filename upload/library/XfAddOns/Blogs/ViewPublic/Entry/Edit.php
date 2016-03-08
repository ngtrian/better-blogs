<?php

class XfAddOns_Blogs_ViewPublic_Entry_Edit extends XenForo_ViewPublic_Base
{

	/**
	 * Render the html for the page. Adds an editor to the page
	 */
	public function renderHtml()
	{
		$message = isset($this->_params['message']) ? $this->_params['message'] : null;
		$this->_params['title'] = $this->_params['entry']['title'];
		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate($this, 'message', $message);
	}

}