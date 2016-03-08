<?php

/**
 * Rebuilding all the information for the entries. This will recount the position
*/
class XfAddOns_Blogs_Model_Rebuild extends XenForo_Model
{
	
	/**
	 * Recalculate the position of all the entries
	 */
	public function rebuildAllEntriesPositionIndex()
	{
		$db = XenForo_Application::getDb();
		$users = $db->fetchCol("SELECT DISTINCT user_id FROM xfa_blog_entry");
		foreach ($users as $userId)
		{
			$this->rebuildEntryPositionIndex($userId);
		}
		$this->rebuildPositionForDeletedEntries();
	}

	/**
	 * Recalculate the position of all the entries in a blog
	 */
	public function rebuildEntryPositionIndex($blogId)
	{
		$db = XenForo_Application::getDb();
		$db->query("SET @rownum:=-1");
		$db->query("UPDATE xfa_blog_entry SET position=( @rownum:=@rownum+1 ) WHERE user_id = ? AND message_state='visible' order by post_date", $blogId);
	}	
	
	/**
	 * Updates the position for the deleted and moderated entries (logic is a little more complicated than the position count)
	 */
	public function rebuildPositionForDeletedEntries()
	{
		$db = XenForo_Application::getDb();
		$db->query("UPDATE xfa_blog_entry SET position=-1 WHERE message_state<>'visible'");
		
		$temporaryTableName = 'xfa_blog_entry_temp_' . rand();
		$db->query("CREATE TEMPORARY TABLE " . $temporaryTableName . " AS SELECT * FROM xfa_blog_entry");
		$db->query("ALTER TABLE " . $temporaryTableName . " ADD INDEX (entry_id)");
		$db->query("ALTER TABLE " . $temporaryTableName . " ADD INDEX (user_id, position)");
		
		$db->query("
				UPDATE xfa_blog_entry entry
				SET
					position=
						(SELECT ifnull(max(position), 0) FROM " . $temporaryTableName . " temp WHERE
							entry.user_id = temp.user_id AND 
							temp.message_state = 'visible' AND
							temp.entry_id < entry.entry_id
						)
				WHERE
					message_state<>'visible'"
				);		
	}
	
	/**
	 * Recalculate the position of all the comments
	 */
	public function rebuildAllCommentsPositionIndex()
	{
		$db = XenForo_Application::getDb();
		$entries = $db->fetchCol("SELECT DISTINCT entry_id FROM xfa_blog_comment");
		foreach ($entries as $entryId)
		{
			$this->rebuildCommentPositionIndex($entryId);
		}
		
		$this->rebuildPositionForDeletedComments();
	}
	
	/**
	 * Deleted comments are a little more complicated due to the relative position. Rebuild.
	 */
	public function rebuildPositionForDeletedComments()
	{
		$db = XenForo_Application::getDb();
		$db->query("UPDATE xfa_blog_comment SET position=-1 WHERE message_state<>'visible'");
		
		$temporaryTableName = 'xfa_blog_entry_temp_' . rand();
		$db->query("CREATE TEMPORARY TABLE " . $temporaryTableName . " AS SELECT * FROM xfa_blog_comment");
		$db->query("ALTER TABLE " . $temporaryTableName . " ADD INDEX (comment_id)");
		$db->query("ALTER TABLE " . $temporaryTableName . " ADD INDEX (entry_id, position)");
		
		$db->query("
				UPDATE xfa_blog_comment comment
				SET
					position=
						(SELECT ifnull(max(position), 0) FROM " . $temporaryTableName . " temp WHERE
							comment.entry_id = temp.entry_id AND
							temp.message_state = 'visible' AND
							temp.comment_id < comment.comment_id
						)
				WHERE
					message_state<>'visible'"
		);		
	}
	
	/**
	 * Recalculate the position of all the comments in an entry
	 */
	public function rebuildCommentPositionIndex($entryId)
	{
		$db = XenForo_Application::getDb();
		$db->query("SET @rownum:=-1");
		$db->query("UPDATE xfa_blog_comment SET position=( @rownum:=@rownum+1 ) WHERE entry_id = ? AND message_state='visible' order by post_date", $entryId);
	}	
	
	/**
	 * Update the blog count on all the blogs. This can be used to reset the count in the event rows were manually deleted
	 * or something wrong happened
	 */
	public function recountBlogTotals()
	{
		$db = XenForo_Application::getDb();
		$db->query("
			UPDATE xfa_blog SET entry_count = (
				SELECT count(*) FROM xfa_blog_entry WHERE xfa_blog.user_id = xfa_blog_entry.user_id AND message_state='visible'
			)			
		");
	}
	
	/**
	 * Updates the information of when the last entry was for every blog
	 */
	public function rebuildLastEntry()
	{
		$db = XenForo_Application::getDb();
		$db->query("
			UPDATE xfa_blog SET last_entry = (
				SELECT ifnull(max(post_date), 0) FROM xfa_blog_entry WHERE xfa_blog_entry.user_id = xfa_blog.user_id AND message_state='visible'
			)		
		");
	}
	
	/**
	 * Updates the information of when the last entry was for every blog
	 */
	public function rebuildLastEntryId()
	{
		$db = XenForo_Application::getDb();
		$db->query("
			UPDATE xfa_blog SET last_entry_id = (
				SELECT max(entry_id) FROM xfa_blog_entry where xfa_blog_entry.user_id = xfa_blog.user_id AND message_state='visible'
			)
		");
	}
	
	/**
	 * Updates the information about the creation date of the blog (we will map to the first entry)
	 */
	public function rebuildFirstEntry()
	{
		$db = XenForo_Application::getDb();
		$db->query("
			UPDATE xfa_blog SET create_date = (
				SELECT min(post_date) FROM xfa_blog_entry WHERE xfa_blog_entry.user_id = xfa_blog.user_id AND message_state='visible'
			)
		");
	}
	
	/**
	 * Update the comments count on all the entries
	 */
	public function recountEntriesTotals()
	{
		$db = XenForo_Application::getDb();
		$db->query("
			UPDATE xfa_blog_entry SET reply_count = (
				SELECT count(*) FROM xfa_blog_comment WHERE xfa_blog_entry.entry_id = xfa_blog_comment.entry_id AND message_state='visible'
			)
		");
	}
	
	/**
	 * Update the totals on categories
	 */
	public function recountCategoryTotals()
	{
		$db = XenForo_Application::getDb();
		$db->query("
			UPDATE xfa_blog_category SET entry_count = (
			  SELECT count(*) FROM xfa_blog_entry_category where xfa_blog_entry_category.category_id=xfa_blog_category.category_id
			)				
		");
	}

	/**
	 * Update the comments count on all the blogs
	 */
	public function recountCommentTotalsOnBlog()
	{
		$db = XenForo_Application::getDb();
		$db->query("
			UPDATE xfa_blog blog SET comment_count = (
				SELECT IFNULL(SUM(reply_count), 0) FROM xfa_blog_entry entry WHERE blog.user_id = entry.user_id 
			)				
		");
	}	
	
	
}