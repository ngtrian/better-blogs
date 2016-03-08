<?php

class XfAddOns_Blogs_ViewPublic_NewReplies extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => false
			)
		);

		$comment = &$this->_params['comment'];
		$comment['messageHtml'] = XenForo_ViewPublic_Helper_Message::getBbCodeWrapper($comment, $bbCodeParser);
	}
}