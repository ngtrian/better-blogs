<?php

class EWRporta_Block_HomeRecentlyCreatedBlogs extends XenForo_Model
{
	public function getModule()
	{
		$panel = new XfAddOns_Blogs_Panel_Blogs();
		return $panel->getPanelContent('create_date');
	}
}