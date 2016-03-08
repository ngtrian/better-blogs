<?php

class XfAddOns_Blogs_StatsHandler_Entry extends XenForo_StatsHandler_Abstract
{

	public function getStatsTypes()
	{
		return array(
			'entry' => new XenForo_Phrase('xfa_blogs_entries')
		);
	}

	public function getData($startDate, $endDate)
	{
		$entries = $this->_getDb()->fetchPairs(
			$this->_getBasicDataQuery('xfa_blog_entry', 'post_date', 'message_state = ?'),
			array($startDate, $endDate, 'visible')
		);

		return array(
			'entry' => $entries
		);
	}	
	
	
}