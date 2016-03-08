<?php

/**
 * Fetch all the information from the entries 
 */
class XfAddOns_Blogs_Model_Comment extends XenForo_Model
{
	
	/**
	 * Provide this constant in fetchOptions to join the user information
	 * @var int
	 */
	const JOIN_USER = 0x1;
	
	/**
	 * Join deletion information
	 * @var int
	 */
	const JOIN_DELETION_LOG = 0X2;	
	
	/**
	 * Join like information
	 * @var int
	 */
	const JOIN_LIKE_INFORMATION = 0X4;
	
	/**
	 * Joins the entry information along with the comment
	 * @var int
	 */
	const JOIN_ENTRY = 0X8;
	
	/**
	 * Join the blog information, this is necessary to provide the blog indicator
	 * @var int
	 */
	const JOIN_BLOG_INFO = 0X10;
	
	/**
	 * Add permission and any other required information to the comment
	 * @param array $comment	An array with the comment information. Array will be modified
	 */
	public function prepareComment(&$comment, $entry = null)
	{
		// add the deletion information
		if (!empty($comment['delete_date']))
		{
			$comment['deleteInfo'] = array(
					'user_id' => $comment['delete_user_id'],
					'username' => $comment['delete_username'],
					'date' => $comment['delete_date'],
					'reason' => $comment['delete_reason'],
			);
		}
		if (!empty($comment['blog_last_entry']))
		{
			$comment['userblog'] = array(
					'user_id' => $comment['user_id'],
					'username' => isset($comment['username']) ? $comment['username'] : '',
					'last_entry' => $comment['blog_last_entry'],
					'blog_read_date' => $comment['blog_read_date'],
					'blog_key' => $comment['blog_key']
				);
		}
		if (isset($comment['prepare_entry']) && $comment['prepare_entry'] == 1)	// if we got this joining the entry, we will initialize it
		{
			$comment['entry'] = array(
				'user_id' => $comment['entry_user_id'],
				'title' => $comment['entry_title'],
				'post_date' => $comment['entry_post_date'],
				'reply_count' => $comment['entry_reply_count'],
				'message' => $comment['entry_message'],
				'message_state' => $comment['entry_message_state'],
				'ip_id' => $comment['entry_ip_id'],
				'position' => $comment['entry_position'],
				'likes' => $comment['entry_likes'],
				'like_users' => $comment['entry_like_users'],
				'allow_comments' => $comment['entry_allow_comments'],
				'allow_view_entry' => $comment['entry_allow_view_entry'],
				'username' => $comment['entry_username']
			);
			unset($comment['entry_user_id']);
			unset($comment['entry_title']);
			unset($comment['entry_post_date']);
			unset($comment['entry_reply_count']);
			unset($comment['entry_message']);
			unset($comment['entry_message_state']);
			unset($comment['entry_ip_id']);
			unset($comment['entry_position']);
			unset($comment['entry_likes']);
			unset($comment['entry_like_users']);
			unset($comment['entry_username']);
			unset($comment['prepare_entry']);
		}
		
		// unserialize like information
		$comment['likeUsers'] = !empty($comment['like_users']) ? @unserialize($comment['like_users']) : array();
		
		// setup the permissions applicable to the comment
		$comment['perms'] = $this->getPerms($comment, $entry);
	}
	
	/**
	 * Return the list of permissions that are applicable to this comment, depending
	 * on the visitor
	 */
	protected function getPerms($comment, $entry = null)
	{
		// get the references for visitor information
		$visitor = XenForo_Visitor::getInstance();
		$visitorUserId = XenForo_Visitor::getUserId();
	
		// add permissions information to the blog
		$allPermissions = $visitor->getPermissions();
		$blogPermissions = $allPermissions['xfa_blogs'];
		
		$isRegisteredUser = $visitorUserId > 0;
		$isCommentAuthor = $comment['user_id'] == $visitorUserId;
		$isBlogAdmin = $blogPermissions['xfa_blogs_admin'];
	
		// spam cleaner
		/* @var $userModel XenForo_Model_User  */
		$userModel = $this->getModelFromCache('XenForo_Model_User');
		$canCleanSpam = isset($comment['is_moderator']) && isset($comment['message_count']) &&
			$visitor->hasPermission('general', 'cleanSpam') && $userModel->couldBeSpammer($comment);		
		
		$perms = array(
			'canEdit' => ($isCommentAuthor && $blogPermissions['xfa_blogs_edit_comment']) || $isBlogAdmin,
			'canDelete' => ($isCommentAuthor && $blogPermissions['xfa_blogs_delete_comment']) || $isBlogAdmin,
			'canRestore' => ($isCommentAuthor && $blogPermissions['xfa_blogs_restore_comment']) || $isBlogAdmin,
			'canViewDeleted' => ($isCommentAuthor && $blogPermissions['xfa_blogs_restore_comment']) || $isBlogAdmin,
			'canHardDelete' => ($isCommentAuthor && $blogPermissions['xfa_blogs_hard_delete']) || ($isBlogAdmin && $blogPermissions['xfa_blogs_hard_delete']),
			'canLike' => !$isCommentAuthor && $blogPermissions['xfa_blogs_like_comment'],
			'canViewIps' => $visitor->hasPermission('general', 'viewIps'),
			'canReport' => !$isCommentAuthor && $blogPermissions['xfa_blogs_report'],
			'canCleanSpam' => $canCleanSpam,
			'canViewHistory' => $isCommentAuthor || $blogPermissions['xfa_blogs_comment_history'] || $isBlogAdmin,
			'canRevert' => $isCommentAuthor || $isBlogAdmin
		);
		
		// the author can delete comments in their entry, the author can view history in their entry 
		if ($entry && $entry['user_id'] == $visitorUserId)
		{
			if (!$perms['canDelete'])
			{
				$perms['canDelete'] = $blogPermissions['xfa_blogs_delete_comment'] ? true : false;
			}
			if (!$perms['canViewHistory'])
			{
				$perms['canViewHistory'] = true;
			}
		}
		
		// inline mod permissions
		$perms['canInlineMod'] = $perms['canDelete'] || $perms['canRestore'];
		
		$options = XenForo_Application::getOptions();
		if (!$options->blogAdvancedFeatures)
		{
			$perms['canReport'] = false;
			$perms['canCleanSpam'] = false;
			$perms['canViewHistory'] = false;
			$perms['canInlineMod'] = false;
		}
		
		return $perms;
	}
	
	/**
	 * Prepare with permission information a list of comments
	 * @param array $comments	An array with comments
	 */
	public function prepareComments(&$comments, $entry = null)
	{
		foreach ($comments as &$comment)
		{
			$this->prepareComment($comment, $entry);
		}
	}	
	
	/**
	 * Return the max date for a list of comments, this is used by the controller for the mark as read functionality
	 * @param array $comments		An array of comments data
	 * @return int
	 */
	public function getMaxCommentDate($comments)
	{
		$maxCommentDate = -1;
		foreach ($comments as $comment)
		{
			$maxCommentDate = max($maxCommentDate, $comment['post_date']);
		}
		return $maxCommentDate;
	}	
	
	/**
	 * Return all the comments for a particular entry
	 * @param array $entry		The information for the entry
	 */
	public function getCommentsForEntry(array $entry, $fetchOptions)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchAll("
			SELECT
				xfa_blog_comment.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_comment
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				xfa_blog_comment.entry_id = ?
			ORDER BY
				xfa_blog_comment.post_date
			", $entry['entry_id'] );
	}
	
	/**
	 * Return the latest comments posted in the forum, and the blog they were posted to
	 * @param int $limit
	 */
	public function getLatestComments($fetchOptions)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchAll("
			SELECT
				xfa_blog_comment.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_comment
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				xfa_blog_comment.message_state = 'visible'
				" . $this->getWhereOptions($fetchOptions) . "
			ORDER BY
				xfa_blog_comment.post_date DESC
			LIMIT " . $fetchOptions['limit'] . "
			");		
	}
	
	/**
	 * Return a list of comments ordered depending on the information pased in fetchOptions
	 * @param int $limit
	 */
	public function getComments($fetchOptions)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchAll("
			SELECT
				xfa_blog_comment.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_comment
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				xfa_blog_comment.message_state = 'visible'
				" . $this->getWhereOptions($fetchOptions) . "
				" . $this->getOrderBy($fetchOptions) . "
				" . $this->getLimit($fetchOptions) . "
			");
	}	
	
	/**
	 * Returns the details about a comment, fetching it by the database primary key
	 * @param int $commentId		The identifier for the comment
	 */
	public function getCommentById($commentId, $fetchOptions)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchRow("
			SELECT
				xfa_blog_comment.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_comment
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				comment_id = ?
			", $commentId);		
	}
	
	/**
	 * Return the list of all the comments made by a user. This is used by the spam cleaner.
	 * @param array $user		The array of the user information
	 * @param array $fetchOptions	The fetch options for joins and selects
	 */
	public function getCommentsForUser($user, $fetchOptions = array())
	{
		return $this->fetchAllKeyed("
			SELECT
				xfa_blog_comment.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_comment
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				user_id = ?
			", 'comment_id', array($user['user_id']));		
	}
	
	/**
	 * Return comments when we are given a list of ids
	 *
	 * @param array $ids	An array of post ids to retrieve
	 * @return array		An array with the comments information
	 */
	public function getCommentsById($ids, $fetchOptions = array())
	{
		if (empty($ids))
		{
			return array();
		}

// 		SELECT comment.*, entry.title, entry.user_id entry_user_id, 
	// userComment.username username, 
	// user.username entry_username
// 		FROM xfa_blog_comment comment
// 		INNER JOIN xfa_blog_entry entry ON comment.entry_id = entry.entry_id
// 		INNER JOIN xf_user user ON entry.user_id = user.user_id
// 		INNER JOIN xf_user userComment ON comment.user_id = userComment.user_id
// 		WHERE
		
		return $this->fetchAllKeyed("
			SELECT 
				xfa_blog_comment.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_comment
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				comment_id IN (" . implode(',', $ids) . ")
			", 'comment_id');
	}
	
	/**
	 * Returns the first comment found on a particular entry, from a fixed date. This is done in such a way that we can jump
	 * directly to the comment
	 * 
	 * @param array $entry		An array with the information for the entry
	 * @param int $date			The date that we want to search the comment from
	 */
	public function getFirstCommentFromDate(array $entry, $postDate, $fetchOptions = array())
	{
		$db = XenForo_Application::getDb();
		return $db->fetchRow("
			SELECT
				xfa_blog_comment.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_comment
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				entry_id = ? AND post_date >= ?
			", array($entry['entry_id'], $postDate));			
	}
	
	/**
	 * Remove all the private comments. This method depends on prepareComment() to have been called beforehand, and wireEntries
	 * This method will also try to go down to blog permissions and remove if we don't have view privileges
	 */
	public function removePrivateCommentsForVisitor(&$comments)
	{
		foreach ($comments as $key => $comment)
		{
			$entry = isset($comment['entry']) ? $comment['entry'] : NULL;
			$blog = isset($comment['entry']['blog']) ? $comment['entry']['blog'] : NULL;
			if (!$entry || !isset($entry['perms']) || !$entry['perms']['canView'])
			{
				unset($comments[$key]);
			}
			if (!$blog || !isset($blog['perms']) || !$blog['perms']['canView'])
			{
				unset($comments[$key]);
			}			
		}
	}
	
	/**
	 * Alert members tagged in an comment
	 */
	public function alertTaggedMembers(array $comment, array $tagged)
	{
		$userIds = XenForo_Application::arrayColumn($tagged, 'user_id');
		if (empty($userIds))
		{
			return;
		}
	
		$alertedUserIds = array();
		/* @var $userModel XenForo_Model_User  */
		$userModel = XenForo_Model::create('XenForo_Model_User');
		$users = $userModel->getUsersByIds($userIds, array(
				'join' => XenForo_Model_User::FETCH_USER_OPTION | XenForo_Model_User::FETCH_USER_PROFILE
		));
	
		foreach ($users AS $user)
		{
			if (!isset($alertedUserIds[$user['user_id']]) && $user['user_id'] != $comment['user_id'])
			{
				if (!$userModel->isUserIgnored($user, $comment['user_id']) && XenForo_Model_Alert::userReceivesAlert($comment, 'xfa_blog_comment', 'tagged'))
				{
					$alertedUserIds[$user['user_id']] = true;
					XenForo_Model_Alert::alert($user['user_id'], $comment['user_id'], '', 'xfa_blog_comment', $comment['comment_id'], 'tagged');
				}
			}
		}
		return array_keys($alertedUserIds);
	}	
	
	/**
	 * To an array of comments, this will retrieve information for all the entries contained within, and for all the blogs
	 * contained within, and it will add that information
	 * 
	 * @param array $comments	A reference to the comments
	 */
	public function wireEntriesAndBlogs(array &$comments)
	{
		$ids = array();
		foreach ($comments as $comment)
		{
			$ids[$comment['entry_id']] = true;
		}
		$entryIds = array_keys($ids);
		
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		$fetchOptions = array(
			'join' => XfAddOns_Blogs_Model_Entry::JOIN_USER + XfAddOns_Blogs_Model_Entry::JOIN_DELETION_LOG + XfAddOns_Blogs_Model_Entry::JOIN_VISITOR_FOLLOW
			);
		$entries = $entryModel->getEntriesByIds($entryIds, $fetchOptions);
		$entryModel->prepareEntries($entries);
		$entryModel->wireBlogs($entries);
		
		foreach ($comments as &$comment)
		{
			$entryId = $comment['entry_id'];
			if (isset($entries[$entryId]))
			{
				$comment['entry'] = $entries[$entryId];
			}
		}
	}
	
	/**
	 * Return the tables that need to be joined
	 * @param array $joinOptions	An array with the fetchOptions
	 */
	protected function getSelectOptions(array $fetchOptions)
	{
		if (!isset($fetchOptions['join']))
		{
			return '';
		}
		
		$select = '';
		if ($fetchOptions['join'] & self::JOIN_USER)
		{
			$select .= ',xf_user.*';
		}
		if ($fetchOptions['join'] & self::JOIN_ENTRY)
		{
			$select .= "
				,1 prepare_entry
				,xfa_blog_entry.user_id entry_user_id
				,xfa_blog_entry.title entry_title
				,xfa_blog_entry.post_date entry_post_date
				,xfa_blog_entry.reply_count entry_reply_count
				,xfa_blog_entry.message entry_message
				,xfa_blog_entry.message_state entry_message_state
				,xfa_blog_entry.ip_id entry_ip_id
				,xfa_blog_entry.position entry_position
				,xfa_blog_entry.likes entry_likes
				,xfa_blog_entry.like_users entry_like_users
				,xfa_blog_entry.allow_comments entry_allow_comments
				,xfa_blog_entry.allow_view_entry entry_allow_view_entry
				,entry_user.username entry_username			
				";
		}
		if ($fetchOptions['join'] & self::JOIN_DELETION_LOG)
		{
			$select .= ',deletion_log.*';
		}		
		if ($fetchOptions['join'] & self::JOIN_LIKE_INFORMATION)
		{
			$select .= ',liked_content.like_date';
		}
		if ($fetchOptions['join'] & self::JOIN_BLOG_INFO)
		{
			$select .= ',xfa_blog.last_entry blog_last_entry
					,xfa_blog_read.blog_read_date blog_read_date
					,xfa_blog.blog_key blog_key';
		}		
		return $select;
	}	

	/**
	 * Return the tables that need to be joined
	 * @param array $joinOptions	An array with the fetchOptions
	 */
	protected function getJoinOptions(array $fetchOptions)
	{
		if (!isset($fetchOptions['join']))
		{
			return '';
		}
		
		$join = '';
		if ($fetchOptions['join'] & self::JOIN_USER)
		{
			$join .= ' INNER JOIN xf_user ON xfa_blog_comment.user_id = xf_user.user_id';
		}
		if ($fetchOptions['join'] & self::JOIN_ENTRY)
		{
			$join .= ' INNER JOIN xfa_blog_entry ON xfa_blog_comment.entry_id = xfa_blog_entry.entry_id';
			$join .= ' INNER JOIN xf_user entry_user ON xfa_blog_entry.user_id = entry_user.user_id';
		}
		if ($fetchOptions['join'] & self::JOIN_DELETION_LOG)
		{
			$join .= " LEFT JOIN xf_deletion_log deletion_log ON xfa_blog_comment.comment_id = deletion_log.content_id AND content_type = 'xfa_blog_comment'";
		}		
		if ($fetchOptions['join'] & self::JOIN_LIKE_INFORMATION)
		{
			$likeUserId = XenForo_Visitor::getUserId();
			$join .= "
				LEFT JOIN xf_liked_content AS liked_content
					ON (liked_content.content_type = 'xfa_blog_comment'
					AND liked_content.content_id = xfa_blog_comment.comment_id
					AND liked_content.like_user_id = " .$likeUserId . ")
				";
		}
		if ($fetchOptions['join'] & self::JOIN_BLOG_INFO)
		{
			$visitorUserId = XenForo_Visitor::getUserId();
			$join .= " 
				LEFT JOIN xfa_blog ON xfa_blog_comment.user_id = xfa_blog.user_id
				LEFT JOIN xfa_blog_read ON xfa_blog_comment.user_id = xfa_blog_read.blog_user_id AND xfa_blog_read.user_id = " . $visitorUserId . "
				";
		}
		return $join;
	}
	
	/**
	 * Check if we have an additional 'where' clause that we want to concatenate to the query
	 * @param array $fetchOptions
	 */
	protected function getWhereOptions($fetchOptions)
	{
		if (empty($fetchOptions) || !isset($fetchOptions['where']) || empty($fetchOptions['where']))
		{
			return '';
		}
		return ' AND ' . $fetchOptions['where'];
	}	

	/**
	 * If there is an orderBy clause, this will  apend the information to the query
	 * @return string	The OrderBy clause, or an empty string
	 */
	protected function getOrderBy($fetchOptions)
	{
		return !empty($fetchOptions['orderBy']) ? ('ORDER BY ' . $fetchOptions['orderBy']) : '';
	}
	
	/**
	 * If there is an limit clause, this will  apend the information to the query
	 * @return string	The LIMIT clause, or an empty string
	 */
	protected function getLimit($fetchOptions)
	{
		return !empty($fetchOptions['limit']) ? ('LIMIT ' . $fetchOptions['limit']) : '';
	}	
	
	
	
	
	
}