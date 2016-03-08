<?php

class XfAddOns_Blogs_Model_BlogWatch
{
	
	/**
	 * Return the information about a blog being watched by a particular user
	 * @param unknown_type $userId		The user or visitor browsing the forum
	 * @param unknown_type $blogUserId	The identifier for the blog
	 */
	public function getWatch($userId, $blogUserId)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchRow("
			SELECT * FROM xfa_blog_watch WHERE user_id = ? AND blog_user_id = ?",
			array($userId, $blogUserId)
			);
	}
	
	/**
	 * Returns a list of all watched blogs
	 * @param int $userId
	 * @return array
	 */
	public function getWatchedBlogs($userId)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchCol("
			SELECT
				blog_user_id user_id
			FROM xfa_blog_watch blog_watch
			WHERE
				blog_watch.user_id = ?
		", array( $userId ));
	}
	
	/**
	 * Returns the total blogs that a user is watching
	 * @param int $userId		Person for which we want to count subscription
	 * @return int
	 */
	public function getTotalWatchForUser($userId)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchOne("SELECT count(*) total FROM xfa_blog_watch WHERE user_id = ?", array( $userId ));
	}

	/**
	 * Returns a list of all unread blogs
	 * @param int $userId
	 * @return array
	 */
	public function getUnreadBlogs($userId)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchCol("
			SELECT
				blog_watch.blog_user_id user_id
			FROM xfa_blog_watch blog_watch
			INNER JOIN xfa_blog blog ON blog_watch.blog_user_id = blog.user_id
			LEFT JOIN xfa_blog_read blog_read ON 
				(blog_watch.blog_user_id = blog_read.blog_user_id AND blog_watch.user_id = blog_read.user_id)
			WHERE
				blog_watch.user_id = ? AND
				(blog_read_date IS NULL OR blog_read_date < blog.last_entry)
		", array( $userId ));
	}	
	
	/**
	 * Returna  list of all the users that have subscribed to a blog
	 * @param int $blogUserId	The identifier for the blog
	 * @return array	An array of all the users
	 */
	public function getUsersSubscribedToBlog($blogUserId)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchCol("
			SELECT user_id FROM xfa_blog_watch WHERE blog_user_id = ?
			",  $blogUserId);
	}
	
	/**
	 * Method used to send an alert notification to all the users that are subscribed to this blog that there is
	 * a new entry
	 */
	public function notifySubscribedUsers(array $entry)
	{
		$subscribedUsers = $this->getUsersSubscribedToBlog($entry['user_id']);
		foreach ($subscribedUsers as $userId)
		{
			if (!XenForo_Model_Alert::userReceivesAlert(array('user_id' => $userId), 'xfa_blog_entry', 'insert'))
			{
				continue;
			}
			if ($userId == $entry['user_id'])		// do not notify the author about the entry just posted
			{
				continue;
			}
			
			XenForo_Model_Alert::alert(
				$userId,
				$entry['user_id'],
				'',
				'xfa_blog_entry',
				$entry['entry_id'],
				'insert'
			);
		}
	}
	
	/**
	 * Subscribe to a blog. This method ignores any errors, and is invoked from the follower datawriter
	 */
	public function subscribeIfNotSubscribed($userId, $blogUserId)
	{
		$db = XenForo_Application::getDb();
		$db->query("
			INSERT IGNORE INTO xfa_blog_watch (user_id, blog_user_id) VALUES (?, ?)
			", array($userId, $blogUserId));
	}	
	
	
}