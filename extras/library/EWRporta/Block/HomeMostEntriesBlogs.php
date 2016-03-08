<?php

class EWRporta_Block_HomeMostEntriesBlogs extends XenForo_Model
{
	public function getModule()
	{
		$panel = new XfAddOns_Blogs_Panel_Blogs();
		return $panel->getPanelContent('entry_count');
	}
}