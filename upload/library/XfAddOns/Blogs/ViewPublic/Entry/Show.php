<?php

class XfAddOns_Blogs_ViewPublic_Entry_Show extends XenForo_ViewPublic_Base
{

	/**
	 * Render the html for the entry
	 */
	public function renderHtml()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => false
			)
		);

		$entry = &$this->_params['entry'];
		$entry['messageHtml'] = XenForo_ViewPublic_Helper_Message::getBbCodeWrapper($entry, $bbCodeParser, $bbCodeOptions);
	}

}