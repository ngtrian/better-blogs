<?php

/**
 * This class will register the views on an entry
 */
class XfAddOns_Blogs_Model_BlogEntryView
{
	
	/**
	 * The blog registers every time someone views a full entry. Either we will register the ip address of the
	 * person that viewed the entry or the userid
	 * @param array $blog
	 */
	public function registerEntryView(array $entry)
	{
		$visitorUserId = XenForo_Visitor::getUserId();
		$params[] = $entry['entry_id'];
		$params[] = $visitorUserId > 0 ? 'registered' : 'guest';
		$params[] = $visitorUserId > 0 ? $visitorUserId : XfAddOns_Blogs_Model_BlogView::getIpAddress();
		$params[] = 1;
		$params[] = XenForo_Application::$time;
		
		$db = XenForo_Application::getDb();
		$db->query("
			INSERT INTO xfa_blog_entry_view
				(entry_id, type, ipOrUser, views, last_visit)
			VALUES
				(?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				views = views + 1,
				last_visit = VALUES(last_visit)
		", $params);
		
		// and also add into the deferred view that is parsed by a cron
		$db->insert('xfa_blog_deferred_view', array('type' => 'entry', 'id' => $entry['entry_id']));
	}
	
}