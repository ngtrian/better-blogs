<?php

/**
 * Generic model class for operations over a blog. Most blog content is actually just the user information
 */
class XfAddOns_Blogs_Model_Blog extends XenForo_Model
{

	/**
	 * Join the privacy information for the blog
	 * @var int
	 */
	const JOIN_PRIVACY = 0X2;	
	
	/**
	 * Join whether the current visitor is being followed by the blog owner
	 * @var int
	 */
	const JOIN_VISITOR_FOLLOW = 0X4;
	
	/**
	 * Join whether the current visitor is watching the blog
	 * @var int
	 */
	const JOIN_VISITOR_WATCH = 0X8;
	
	/**
	 * Join the information about the last time that the visitor read the blog
	 * @var int
	 */
	const JOIN_READ_DATE = 0X10;
	
	/**
	 * Join the last entry that was posted in the blog, if any
	 * @var int
	 */
	const JOIN_LAST_ENTRY = 0X20;
	
	/**
	 * When the where part of the query is computed, if this flag is provided it would filter only blogs that the visitor can see
	 * @var int
	 */
	const WHERE_PRIVACY = 0X2;
	
	
	/**
	 * Prepare model adds some information for before we pass the blog object to the view
	 */
	public function prepareBlog(array &$blog)
	{
		// create a blog title if one does not exist
		if (empty($blog['blog_title']))
		{
			$params = array('username' => $blog['username']);
			$blog['blog_title'] = new XenForo_Phrase('xfa_blogs_username', $params);
			$blog['blog_title'] = $blog['blog_title']->__toString(); 
		}
		
		// combine all lat_entry		
		if (!empty($blog['prepare_last_entry']))
		{
			$blog['lastEntry'] = array();
			unset($blog['prepare_last_entry']);
			foreach ($blog as $key => $val)
			{
				if (substr($key, 0, 3) == 'le_')
				{
					$blog['lastEntry'][substr($key, 3)] = $val;
					unset($blog[$key]);
				}
			}
		}
		
		// setup the permissions applicable to the blog
		$blog['perms'] = $this->getPerms($blog);
	}
	
	/**
	 * Prepare with permission information a list of blogs
	 * @param array $entries	An array with blogs
	 */
	public function prepareBlogs(&$blogs)
	{
		foreach ($blogs as &$blog)
		{
			$this->prepareBlog($blog);
		}
	}	
	
	/**
	 * Return the list of permissions that are applicable to this blog instance, depending
	 * on the visitor
	 */
	protected function getPerms(array $blog)
	{
		// get the references for visitor information
		$visitor = XenForo_Visitor::getInstance();
		$visitorUserId = XenForo_Visitor::getUserId();
		
		// add permissions information to the blog
		$allPermissions = $visitor->getPermissions();
		$blogPermissions = $allPermissions['xfa_blogs'];
		
		$isRegisteredUser = $visitorUserId > 0;
		$isBlogOwner = $blog['user_id'] == $visitorUserId;
		$isBlogAdmin = $blogPermissions['xfa_blogs_admin'];
		
		$perms = array(
			'isBlogOwner' => $isBlogOwner,
			'canCreateEntry' => $isBlogOwner && $blogPermissions['xfa_blogs_create'],
			'canCustomize' => $isBlogOwner && $blogPermissions['xfa_blogs_customize'],
			'canDownload' => $isBlogOwner && $blogPermissions['xfa_blogs_download'],
			'canCreateCategory' => $isBlogOwner && $blogPermissions['xfa_blogs_categories'],
			'canDeleteEntries' => ($isBlogOwner && $blogPermissions['xfa_blogs_delete']) || $isBlogAdmin,
			'canRestoreEntries' => ($isBlogOwner && $blogPermissions['xfa_blogs_restore']) || $isBlogAdmin,
			'canDeleteComments' => ($isBlogOwner && $blogPermissions['xfa_blogs_delete_comment']) || $isBlogAdmin,
			'canRestoreComments' => ($isBlogOwner && $blogPermissions['xfa_blogs_restore_comment']) || $isBlogAdmin,
			'canWatchBlog' => true,
			'canUsePrivacyOptions' => true,
			'canPostScheduledEntries' => true
			);
		
		$perms['canInlineMod'] = $perms['canDeleteEntries'] || $perms['canRestoreEntries'];
		$perms['canInlineModComments'] = $perms['canDeleteComments'] || $perms['canRestoreComments'];
		
		$options = XenForo_Application::getOptions();
		if (!$options->blogAdvancedFeatures)
		{
			$perms['canCustomize'] = false;
			$perms['canDownload'] = false;
			$perms['canWatchBlog'] = false;
			$perms['canUsePrivacyOptions'] = false;
			$perms['canPostScheduledEntries'] = false;
		}
		
		// setup privacy options. This will setup the 'canView' permissions
		$this->processPrivacyOptions($blog, $blogPermissions, $perms);
		
		return $perms;
	}
	
	/**
	 * Adds the relevant flags for viewing the blog depending on permissions
	 */
	protected function processPrivacyOptions(array $blog, array $blogPermissions, array &$perms)
	{
		$this->processCanViewPermission($blog, $blogPermissions, $perms);
		
		// there is a final overrides if we have the permission to bypass privacy
		if  (!$perms['canView'] && $blogPermissions['xfa_blogs_bypass_privacy'])
		{
			$perms['canView'] = true;
			$perms['showBlogPrivacyWarning'] = true;
		}
	}
	
	/**
	 * Check if the user is authorized to view the blog, per the privacy permissions
	 * @param array $blog		The blog contents
	 */
	protected function processCanViewPermission(array $blog, array $blogPermissions, array &$perms)
	{
		if (!$blogPermissions['xfa_blogs_view'])
		{
			$perms['canView'] = false;
			$perms['canViewPermissionDetail'] = new XenForo_Phrase('xfa_blogs_usergroup_missing_canview');
			return;
		}		
		
		// the user can always view their own blogs
		$visitorUserId = XenForo_Visitor::getUserId();
		if ($blog['user_id'] == $visitorUserId)
		{
			$perms['canView'] = true;
			return;
		}
		
		// if we did not do the table join, assume true, because we can't verify anything past this point
		if (empty($blog['allow_view_blog']))
		{
			$perms['canView'] = true;
			return;
		}	
		
		// check for view_blog permission
		if ($blog['allow_view_blog'] == 'everyone')
		{
			$perms['canView'] = true;
			return;
		}
		if ($blog['allow_view_blog'] == 'none')
		{
			$perms['canView'] = false;
			$perms['canViewPermissionDetail'] = new XenForo_Phrase('xfa_blogs_user_set_blog_to_personal');
			return;
		}
		if ($blog['allow_view_blog'] == 'members')
		{
			$perms['canView'] = $visitorUserId > 0;
			if (!$perms['canView'])
			{
				$perms['canViewPermissionDetail'] = new XenForo_Phrase('xfa_blogs_user_set_blog_to_members');
			}
			return;
		}
		if ($blog['allow_view_blog'] == 'followed')
		{
			$perms['canView'] = isset($blog['blog_owner_follows_visitor']) && $blog['blog_owner_follows_visitor'] > 0;
			if (!$perms['canView'])
			{
				$perms['canViewPermissionDetail'] = new XenForo_Phrase('xfa_blogs_user_set_blog_to_followed');
			}			
			return;
		}
		
		// usually we would never come here. This would hint at bad setup in the DB
		$perms['canView'] = false;
	}
	
	/**
	 * Return the total blogs that exist in the database
	 */
	public function getTotalBlogs($fetchOptions = array())
	{
		$db = XenForo_Application::getDb();
		return $db->fetchOne("
			SELECT
				count(*) total
			FROM xf_user user
			INNER JOIN xfa_blog blog ON user.user_id = blog.user_id
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				1 = 1
				" . $this->getWhereOptions($fetchOptions) . "
			");		
	}
	
	/**
	 * Returns the blog information for a particular user. Since all of the users can have a blog, this method will fetch
	 * the user information first, and then join the optional blog information
	 */
	public function getBlogForUser($userId, $fetchOptions = array())
	{
		// user.user_id, user.username
		$db = XenForo_Application::getDb();
		return $db->fetchRow("
			SELECT
				blog.*, blog.user_id blog_exists,
				user.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xf_user user
			LEFT JOIN xfa_blog blog ON user.user_id = blog.user_id
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				user.user_id = ?
		", $userId);
	}
	
	/**
	 * Returns the blog information that matches a key (subdomain). Since all of the users can have a blog, this method will fetch
	 * the user information first, and then join the optional blog information
	 */
	public function getBlogForKey($blogKey, $fetchOptions = array())
	{
		// user.user_id, user.username
		$db = XenForo_Application::getDb();
		return $db->fetchRow("
			SELECT
				blog.*, user.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xf_user user
			LEFT JOIN xfa_blog blog ON user.user_id = blog.user_id
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				blog.blog_key = ?
		", $blogKey);
	}	
	
	
	/**
	 * Return all the user data and blog data of the blogs matching a list of users
	 * @param array $userIds
	 * @return array
	 */
	public function getBlogsByIds($userIds, $fetchOptions = array())
	{
		if (empty($userIds))
		{
			return array();
		}

		return $this->fetchAllKeyed("
			SELECT
				blog.*,
				blog.user_id blog_exists,
				user.user_id,
				user.username,
				user.avatar_date,
				user.avatar_width,
				user.avatar_height,
				user.gravatar
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xf_user user
			LEFT JOIN xfa_blog blog ON user.user_id = blog.user_id				
				" . $this->getJoinOptions($fetchOptions) . "				
			WHERE
				user.user_id IN (" . implode($userIds, ',') . ")
			" . $this->getOrderByOptions($fetchOptions) . "
			" . $this->getLimit($fetchOptions) . "
		", 'user_id');
	}
	
	/**
	 * Return a list of blogs. THis method is used heavily by the panels to retrieve blogs by a certain order (entries, how recent, etc)
	 * @param  $fetchOptions		array	An array with the options for joins, where, and order
	 * @return array
	 */
	public function getBlogList(array $fetchOptions)
	{
		$limit = isset($fetchOptions['limit']) ? $fetchOptions['limit'] : 10;
		return $this->fetchAllKeyed("
			SELECT
				blog.*,
				user.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xf_user user
			INNER JOIN xfa_blog blog ON user.user_id = blog.user_id				
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				1 = 1
				" . $this->getWhereOptions($fetchOptions) . "
				" . $this->getOrderByOptions($fetchOptions) . "
			LIMIT
				{$limit}
		", 'user_id');		
	}
	
	/**
	 * Update the user data, to register that the blog has been read
	 */
	public function markBlogAsRead($userId, $blogUserId, $currentDate, $newDate)
	{
		if (!$userId)
		{
			return;
		}
		if ($newDate < $currentDate)
		{
			return;
		}
		
		$this->_getDb()->query("
			INSERT INTO xfa_blog_read
				(user_id, blog_user_id, blog_read_date)
			VALUES
				(?, ?, ?)
			ON DUPLICATE KEY UPDATE blog_read_date = VALUES(blog_read_date)
		", array($userId, $blogUserId, $newDate));		
	}
	
	/**
	 * Return the list of all the friends for a given user. This will also join the visitor
	 * information to figure out the last time the users' blog was updated
	 */
	public function getFriends($userId)
	{
		$visitorUserId = XenForo_Visitor::getUserId();
		return $this->_getDb()->fetchAll("
			SELECT
				xf_user.*,
				xfa_blog_read.blog_read_date,
				xfa_blog.last_entry,
				xfa_blog.blog_key
			FROM xf_user_follow
			INNER JOIN xf_user ON xf_user_follow.follow_user_id = xf_user.user_id
			INNER JOIN xfa_blog ON xf_user.user_id = xfa_blog.user_id
			LEFT JOIN xfa_blog_read ON xfa_blog.user_id = xfa_blog_read.blog_user_id AND xfa_blog_read.user_id = " . $visitorUserId . "
			WHERE
				xf_user_follow.user_id = ?
			ORDER BY
				xfa_blog.last_entry DESC
			", $userId);
	}
	
	/**
	 * Return the list of all the blogs that are being watched by a particular user.
	 *  
	 * @param int $userId
	 */
	public function getWatchedBlogs($userId)
	{
		$visitorUserId = XenForo_Visitor::getUserId();
		return $this->_getDb()->fetchAll("
			SELECT
				xf_user.*,
				xfa_blog_read.blog_read_date,
				xfa_blog.last_entry,
				xfa_blog.blog_key
			FROM xfa_blog_watch
			INNER JOIN xf_user ON xfa_blog_watch.blog_user_id = xf_user.user_id
			INNER JOIN xfa_blog ON xf_user.user_id = xfa_blog.user_id
			LEFT JOIN xfa_blog_read ON xfa_blog.user_id = xfa_blog_read.blog_user_id AND xfa_blog_read.user_id = " . $visitorUserId . "
			WHERE
				xfa_blog_watch.user_id = ?
			ORDER BY
				xfa_blog.last_entry DESC
			", $userId);		
	}

	/**
	 * Return an array of all the panels configured for the blog
	 * @param array $blog		A reference to the blog which panels we want
	 * @return array		An array of templates
	 */
	public function getPanels(array $blog)
	{
		$panels = array();
		$blogRollPanel = new XfAddOns_Blogs_Panel_BlogRoll();
		$panels[] = $blogRollPanel->getPanelContent($blog);
		$categoriesPanel = new XfAddOns_Blogs_Panel_Categories();
		$panels[] = $categoriesPanel->getPanelContent(array ( $blog['user_id'], 0 ));
		return $panels;		
	}
	
	/**
	 * From a list of blogs, return the blogs that are private
	 * @param array $blogIds	The blogs that are private
	 */
	public function getPrivateBlogIds($ids)
	{
		$blogs = $this->getBlogsByIds($ids, array('join' => XfAddOns_Blogs_Model_Blog::JOIN_PRIVACY ));
		$ret = array();
		foreach ($blogs as $blog)
		{
			if ($blog['allow_view_blog'] != 'everyone')
			{
				$ret[] = $blog['user_id'];
			}
		}
		return $ret;
	}	
	
	/**
	 * Update the date for the last entry. This is usually called when the last entry was deleted and we need to figure out
	 * which is the newest one
	 * @param array $blog		A reference to the blog information
	 */
	public function updateLastEntry(array $blog)
	{
		$db = XenForo_Application::getDb();
		$db->query("
			UPDATE xfa_blog SET last_entry = (
				SELECT ifnull(max(post_date), 0) FROM xfa_blog_entry WHERE xfa_blog_entry.user_id = xfa_blog.user_id AND message_state='visible'
			) WHERE xfa_blog.user_id = ?
		", $blog['user_id']);
	}
	
	/**
	 * Remove all the blogs that are not viewable for the user. This depends on prepareBlog to have
	 * been called so we have a canView permission computed
	 * 
	 * @param array $blogs
	 */
	public function removePrivateBlogsForVisitor(array &$blogs)
	{
		foreach ($blogs as $key => $blog)
		{
			if (!$blog['perms']['canView'])
			{
				unset($blogs[$key]);
			}
		}
	}	
	
	/**
	 * Return a list of blog rows, fetching from a particular id, and ordering them by blog_id
	 * @param int $previousLast		The previous blog_id
	 * @param int $limit			The amount of blogs to retrieve
	 */
	public function getBlogIdsInRange($previousLast, $limit)
	{
		$sql = "
			SELECT
				xfa_blog.*, xf_user.*
			FROM xfa_blog
			INNER JOIN xf_user ON xfa_blog.user_id = xf_user.user_id
			INNER JOIN xf_user_privacy ON xfa_blog.user_id = xf_user_privacy.user_id
			WHERE
				allow_view_blog = 'everyone' AND
				xfa_blog.user_id > ?
			ORDER BY
				xfa_blog.user_id
			LIMIT
				{$limit}
			";
		return $this->fetchAllKeyed($sql, 'user_id', array ( $previousLast ));
	}	
	
	/**
	 * Return the selection depending on the joinOptions
	 * @param array $fetchOptions		We will extact the 'join' variable from this
	 * @return string		An empty string, or a sql statement of fields to select
	 */
	protected function getSelectOptions($fetchOptions)
	{
		if (!isset($fetchOptions['join']))
		{
			return '';
		}
		$select = '';
		if ($fetchOptions['join'] & self::JOIN_PRIVACY)
		{
			$select .= ',user_privacy.allow_view_blog';
		}
		if ($fetchOptions['join'] & self::JOIN_VISITOR_FOLLOW)
		{
			$select .= ',user_follow.follow_user_id blog_owner_follows_visitor';
		}
		if ($fetchOptions['join'] & self::JOIN_VISITOR_WATCH)
		{
			$select .= ',xfa_blog_watch.watch_id watch_id';
		}
		if ($fetchOptions['join'] & self::JOIN_READ_DATE)
		{
			$select .= ',xfa_blog_read.blog_read_date';
		}
		if ($fetchOptions['join'] & self::JOIN_LAST_ENTRY)
		{
			$select .= "
				, 1 prepare_last_entry
				, xfa_blog_entry.entry_id		le_entry_id
				, xfa_blog_entry.user_id 		le_user_id
				, xfa_blog_entry.title 			le_title
				, xfa_blog_entry.post_date 		le_post_date
				, xfa_blog_entry.reply_count 	le_reply_count
				, xfa_blog_entry.view_count 	le_view_count
				, xfa_blog_entry.message 		le_message
				, xfa_blog_entry.message_state	le_message_state
				, xfa_blog_entry.ip_id 			le_ip_id
				, xfa_blog_entry.position 		le_position
				, xfa_blog_entry.likes 			le_likes
				, xfa_blog_entry.like_users 	le_like_users
				, xfa_blog_entry.allow_comments le_allow_comments
				, xfa_blog_entry.allow_view_entry le_allow_view_entry
				, xfa_blog_entry.allow_members_ids 	le_allow_members_ids					
			";
		}
		return $select;
	}
	
	/**
	 * Return the tables depending on the joinOptions
	 * @param array $fetchOptions		We will extact the 'join' variable from this
	 * @return string		An empty string, or a sql statement of tables to join
	 */
	protected function getJoinOptions($fetchOptions)
	{
		if (!isset($fetchOptions['join']))
		{
			return '';
		}
		
		$visitorUserId = XenForo_Visitor::getUserId();
		$join = '';
		
		if ($fetchOptions['join'] & self::JOIN_PRIVACY)
		{
			$join .= " INNER JOIN xf_user_privacy user_privacy ON user.user_id = user_privacy.user_id";
		}
		if ($fetchOptions['join'] & self::JOIN_VISITOR_FOLLOW)
		{
			$join .= " LEFT JOIN xf_user_follow user_follow ON user.user_id = user_follow.user_id AND follow_user_id = " . $visitorUserId;
		}
		if ($fetchOptions['join'] & self::JOIN_VISITOR_WATCH)
		{
			$join .= " LEFT JOIN xfa_blog_watch ON user.user_id = xfa_blog_watch.blog_user_id AND xfa_blog_watch.user_id = " . $visitorUserId;
		}
		if ($fetchOptions['join'] & self::JOIN_READ_DATE)
		{
			$join .= " LEFT JOIN xfa_blog_read ON user.user_id = xfa_blog_read.blog_user_id AND xfa_blog_read.user_id = " . $visitorUserId;
		}
		if ($fetchOptions['join'] & self::JOIN_LAST_ENTRY)
		{
			$join .= " LEFT JOIN xfa_blog_entry ON blog.last_entry_id = xfa_blog_entry.entry_id";
		}		
		
		return $join;
	}	
	
	/**
	 * Check if we have an additional 'where' clause that we want to concatenate to the query
	 * @param array $fetchOptions
	 */
	protected function getWhereOptions($fetchOptions)
	{
		$where = '';
		if (isset($fetchOptions['where']) && !empty($fetchOptions['where']))
		{
			$where .= ' AND ' . $fetchOptions['where'];
		}
		if (isset($fetchOptions['whereOptions']) && ($fetchOptions['whereOptions'] & self::WHERE_PRIVACY))
		{
			$visitor = XenForo_Visitor::getInstance();			
			$visitorUserId = $visitor->get('user_id');
			$bypassPrivacy = $visitor->hasPermission('xfa_blogs', 'xfa_blogs_bypass_privacy');
			if (!$bypassPrivacy)
			{
				$where .= "
					AND (
						(blog.user_id = " . $visitorUserId . ") OR
						(user_privacy.allow_view_blog = 'followed' AND user_follow.follow_user_id > 0) OR
						" . ($visitorUserId > 0 ? "user_privacy.allow_view_blog = 'members' OR" : "") . "
						user_privacy.allow_view_blog = 'everyone'
					)
				";
			}
		}
		return $where;
	}	

	/**
	 * If fetch options provided any order by cause, this will return ORDER BY with the appended clause
	 * @param array $fetchOptions	The options passed to the fetch method
	 * @return string				An empty string, or the order by
	 */
	protected function getOrderByOptions($fetchOptions)
	{
		if (empty($fetchOptions) || !isset($fetchOptions['orderBy']) || empty($fetchOptions['orderBy']))
		{
			return '';
		}
		return ' ORDER BY ' . $fetchOptions['orderBy'];
	}
	
	/**
	 * If fetch options provided a limit cause, this will return LIMIT with the appended info
	 * @param array $fetchOptions	The options passed to the fetch method
	 * @return string				An empty string, or the order by
	 */
	protected function getLimit($fetchOptions)
	{
		if (empty($fetchOptions) || !isset($fetchOptions['limit']) || empty($fetchOptions['limit']))
		{
			return '';
		}
		return ' LIMIT ' . $fetchOptions['limit'];
	}	

	
	
	
}