<?php

class XfAddOns_Blogs_StatsHandler_Comment extends XenForo_StatsHandler_Abstract
{

	public function getStatsTypes()
	{
		return array(
			'comment' => new XenForo_Phrase('xfa_blogs_blog_comments')
		);
	}

	public function getData($startDate, $endDate)
	{
		$comments = $this->_getDb()->fetchPairs(
			$this->_getBasicDataQuery('xfa_blog_comment', 'post_date', 'message_state = ?'),
			array($startDate, $endDate, 'visible')
		);

		return array(
			'comment' => $comments
		);
	}	
	
	
}