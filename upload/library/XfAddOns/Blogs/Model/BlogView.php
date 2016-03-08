<?php

/**
 * This class will register the views on a blog
 */
class XfAddOns_Blogs_Model_BlogView
{
	
	/**
	 * The blog registers every time someone views a full entry. The entry will be marked as "viewed"
	 * @param array $blog
	 */
	public function registerView(array $blog)
	{
		$visitorUserId = XenForo_Visitor::getUserId();
		$params[] = $blog['user_id'];
		$params[] = $visitorUserId > 0 ? 'registered' : 'guest';
		$params[] = $visitorUserId > 0 ? $visitorUserId : self::getIpAddress();
		$params[] = 1;
		$params[] = XenForo_Application::$time;
		
		// add into the summary table
		$db = XenForo_Application::getDb();
		$db->query("
			INSERT INTO xfa_blog_view
				(user_id, type, ipOrUser, views, last_visit)
			VALUES
				(?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				views = views + 1,
				last_visit = VALUES(last_visit)
		", $params);
		
		// and also add into the deferred view that is parsed by a cron
		$db->insert('xfa_blog_deferred_view', array('type' => 'blog', 'id' => $blog['user_id']));
	}

	/**
	 * Figure out the ip address for the user, and return the long equivalent of it. This will provide future compatibility
	 * with IPV6 addreses
	 * @return int, or 0
	 */
	public static function getIpAddress()
	{
		$ipAddress = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false);
		$ipAddress = (is_string($ipAddress) && strpos($ipAddress, '.')) ? ip2long($ipAddress) : false;
		$ipAddress = sprintf('%u', $ipAddress);	// in 32-bit architecture this would be negative
		return $ipAddress > 0 ? $ipAddress : 0;
	}
	
	
}