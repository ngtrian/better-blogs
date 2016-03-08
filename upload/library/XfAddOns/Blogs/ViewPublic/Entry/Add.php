<?php

class XfAddOns_Blogs_ViewPublic_Entry_Add extends XenForo_ViewPublic_Base
{

	/**
	 * Render the html for the page. Adds an editor to the page
	 */
	public function renderHtml()
	{
		$blog = $this->_params['blog'];
		$draft = $this->_params['draft'];
		
		if (!empty($draft))
		{
			$extra = @unserialize($draft['extra_data']);
			if (!empty($extra))
			{
				$this->_params['title'] = $extra['title'];
			}
		}
		
		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
				$this, 'message', !empty($draft) ? $draft['message'] : '',
				array('autoSaveUrl' => XenForo_Link::buildPublicLink('xfa-blogs/save-draft', $blog))
		);		
	}

}