<?php

/**
 * Processes the watch information for entries
 */
class XfAddOns_Blogs_Model_BlogEntryWatch
{
	
	/**
	 * Return the information about an entry being watched by a particular user
	 * @param int $userId		The user or visitor browsing the forum
	 * @param int $entryId		The identifier for the entry
	 */
	public function getWatch($userId, $entryId)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchRow("
			SELECT * FROM xfa_blog_entry_watch WHERE user_id = ? AND entry_id = ?",
			array($userId, $entryId)
			);
	}
	
	/**
	 * Returns a list of all the users that have subscribed to a blog entry
	 * @param int $entryId	The identifier for the entry
	 * @return array	An array of all the users
	 */
	protected function getUsersForNotification($entryId)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchCol("
			SELECT user_id FROM xfa_blog_entry_watch WHERE entry_id = ? AND NOT EXISTS (
				SELECT 1 FROM xf_user_alert
				INNER JOIN xfa_blog_comment ON xf_user_alert.content_id = xfa_blog_comment.comment_id AND content_type = 'xfa_blog_comment'
				WHERE 
					xf_user_alert.alerted_user_id = xfa_blog_entry_watch.user_id AND
					xfa_blog_comment.entry_id = xfa_blog_entry_watch.entry_id AND
					view_date = 0
			)
			",  $entryId);
	}
	
	/**
	 * Method used to send an alert notification to all the users that are subscribed to this blog that there is
	 * a new entry
	 */
	public function notifySubscribedUsers(array $comment, array $entry = null)
	{
		$subscribedUsers = $this->getUsersForNotification($comment['entry_id']);
		
		foreach ($subscribedUsers as $userId)
		{
			if (!XenForo_Model_Alert::userReceivesAlert(array('user_id' => $userId), 'xfa_blog_comment', 'insert'))
			{
				continue;
			}
			if ($userId == $comment['user_id'])		// do not notifiy the comment author about the comment he just made
			{
				continue;
			}
			if ($entry && $userId == $entry['user_id'])		// the author of the entry already gets alerts, do not double-alert
			{
				continue;
			}
			
			XenForo_Model_Alert::alert(
				$userId,
				$comment['user_id'],
				'',
				'xfa_blog_comment',
				$comment['comment_id'],
				'insert'
			);
		}
	}
	
	/**
	 * After a person posts a comment in the entry, they are auto-subscribed to that entry, so they would
	 * receive alerts for any further comments that are made on it
	 */
	public function subscribeIfNotSubscribed($userId, $entryId)
	{
		$db = XenForo_Application::getDb();
		$db->query("
			INSERT IGNORE INTO xfa_blog_entry_watch (user_id, entry_id) VALUES (?, ?)
			", array($userId, $entryId));
	}
	
	
	
}