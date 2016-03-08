<?php

/**
 * Renderer for the view. Formats the content as necessary
 */
class XfAddOns_Blogs_ViewPublic_Blog_Categories extends XenForo_ViewPublic_Base
{
	
	/**
	 * Function called when the page is being rendered as HTML
	 */
	public function renderHtml()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array(
			'states' => array(
					'viewAttachments' => false
			)
		);
		
		$options = XenForo_Application::getOptions();
		$snippetOptions = array(
			'stripQuote' => true
		);
		
		$entries = &$this->_params['entries'];
		foreach ($entries as &$entry)
		{
			$entry['fragment'] = XenForo_Template_Helper_Core::helperSnippet($entry['message'], $options->xfa_blogs_trimLength, $snippetOptions);
			if ($entry['fragment'] != $entry['message'])
			{
				$entry['showContinueReading'] = true;
			}
		}
		
		// parse the entries
		XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['entries'], $bbCodeParser, $bbCodeOptions);
	}
	
	
}