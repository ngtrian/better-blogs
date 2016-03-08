<?php

class EWRporta_Block_HomeRecentComments extends XenForo_Model
{
	public function getModule()
	{
		$panel = new XfAddOns_Blogs_Panel_Comments();
		return $panel->getPanelContent();
	}
}