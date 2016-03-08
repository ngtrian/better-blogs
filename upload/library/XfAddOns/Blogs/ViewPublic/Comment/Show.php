<?php

class XfAddOns_Blogs_ViewPublic_Comment_Show extends XenForo_ViewPublic_Base
{

	/**
	 * Render the html for the comment
	 */
	public function renderHtml()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => false
			)
		);

		$comment = &$this->_params['comment'];
		$comment['messageHtml'] = XenForo_ViewPublic_Helper_Message::getBbCodeWrapper($comment, $bbCodeParser, $bbCodeOptions);
	}

}