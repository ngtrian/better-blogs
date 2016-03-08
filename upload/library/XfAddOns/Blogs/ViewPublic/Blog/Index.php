<?php

/**
 * Renderer for the view. Formats the content as necessary
 */
class XfAddOns_Blogs_ViewPublic_Blog_Index extends XenForo_ViewPublic_Base
{
	
	/**
	 * Function called when the page is being rendered as HTML
	 */
	public function renderHtml()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => true
			),
			'showSignature' => false,
			'noFollow' => true
		);
		
		// parse the description for the blog
		$blog = &$this->_params['blog'];
		$blog['descriptionHtml'] = new XenForo_BbCode_TextWrapper($blog['description'], $bbCodeParser);
		
		// parse the entries
		XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['entries'], $bbCodeParser, $bbCodeOptions);
	}
	
	
}