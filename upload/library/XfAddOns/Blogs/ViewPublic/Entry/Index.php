<?php

/**
 * Renderer for the view. Formats the content as necessary
 */
class XfAddOns_Blogs_ViewPublic_Entry_Index extends XenForo_ViewPublic_Base
{
	
	/**
	 * Function called when the page is being rendered as HTML
	 */
	public function renderHtml()
	{
		// render the main entry
		$this->parseBBCodeInEntry();
		$this->parseBBCodeInComments();
		
		// quick reply
		$draft = $this->_params['draft'];
		$entry =$this->_params['entry']; 
		$this->_params['qrEditor'] = XenForo_ViewPublic_Helper_Editor::getQuickReplyEditor(
				$this, 'message', !empty($draft) ? $draft['message'] : '',
				array('autoSaveUrl' => XenForo_Link::buildPublicLink('xfa-blog-entry/save-draft', $entry))
		);
	}
	
	/**
	 * Parse any bbcode that we used in the entry
	 */
	private function parseBBCodeInEntry()
	{
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => true
			),
			'showSignature' => false,
			'noFollow' => true
		);		
		
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$this->_params['entry']['messageHtml'] = XenForo_ViewPublic_Helper_Message::getBbCodeWrapper($this->_params['entry'], $bbCodeParser, $bbCodeOptions);
	}
	
	/**
	 * Parse any bbcode that we used in the comments
	 */
	private function parseBBCodeInComments()
	{
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => false
			),
			'showSignature' => false,
			'noFollow' => true				
		);
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['comments'], $bbCodeParser, $bbCodeOptions);		
	}
	
	
	
}