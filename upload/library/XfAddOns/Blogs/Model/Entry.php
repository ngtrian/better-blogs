<?php

/**
 * Fetch all the information from the entries 
 */
class XfAddOns_Blogs_Model_Entry extends XenForo_Model
{

	/**
	 * Join user information
	 * @var int
	 */
	const JOIN_USER = 0X1;
	
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
	 * Join whether the current visitor is watching the entry
	 * @var int
	 */
	const JOIN_VISITOR_WATCH = 0X8;	
	
	/**
	 * Join the information about the last time that the visitor read the entry
	 * @var int
	 */
	const JOIN_READ_DATE = 0X10; 

	/**
	 * Join the details about the blog
	 * @var int
	 */
	const JOIN_BLOG = 0X20;
	
	/**
	 * Join the privacy information for the blog
	 * @var int
	 */
	const JOIN_BLOG_PRIVACY = 0X40;

	/**
	 * Join whether the current visitor is following the author of the entry
	 * @var int
	 */
	const JOIN_VISITOR_FOLLOW = 0X80;
	
	/**
	 * When the where part of the query is computed, if this flag is provided it would filter only blogs that the visitor can see
	 * @var int
	 */
	const WHERE_PRIVACY = 0X2;
	
	/**
	 * Used with $fetchOptions['prepareOptions'] to influence the prepareEntry method
	 * @var int
	 */
	const PREPARE_ALLOW_MEMBERS = 0X2;
	
	/**
	 * Add permission and any other required information to the entry
	 * @param array $entry	An array with the entry information. Array will be modified
	 */
	public function prepareEntry(&$entry)
	{
		// add the deletion information
		if (!empty($entry['delete_date']))
		{
			$entry['deleteInfo'] = array(
				'user_id' => $entry['delete_user_id'],
				'username' => $entry['delete_username'],
				'date' => $entry['delete_date'],
				'reason' => $entry['delete_reason'],
			);
		}

		// unserialize like information
		$entry['likeUsers'] = !empty($entry['like_users']) ? @unserialize($entry['like_users']) : array();
		
		// check if entry is unread
		$visitor = XenForo_Visitor::getInstance();
		$entry['isNew'] = !isset($entry['entry_read_date']) || !$entry['entry_read_date'];
		if ($entry['user_id'] == $visitor['user_id'])
		{
			$entry['isNew'] = false;
		}
		
		// prepare the privacy list if existing
		self::preparePrivacy($entry);
		// setup the permissions applicable to the entry
		$entry['perms'] = $this->getPerms($entry);
	}

	/**
	 * Prepares the privacy options for the entries. When allow_view_entry is set to list, then we have a list
	 * in a separated by comma format, and we need to convert that to an array and to usernames
	 */ 
	public static function preparePrivacy(&$entry)
	{
		if (empty($entry['allow_members_ids']) || !is_string($entry['allow_members_ids']))
		{
			return;
		}
		
		$entry['allow_members_ary'] = preg_split('/\s*[,]\s*/', $entry['allow_members_ids']);
		if (isset($entry['initializeAllowMembers']) && $entry['initializeAllowMembers'] == 1)
		{
			/* @var $privacyModel XfAddOns_Blogs_Model_Privacy */
			$privacyModel = XenForo_Model::create('XfAddOns_Blogs_Model_Privacy');
			$entry['allow_members_names'] = $privacyModel->getNamesForIds($entry['allow_members_ary']);
		}
	}
	
	/**
	 * Return the list of permissions that are applicable to this entry, depending
	 * on the visitor
	 */
	protected function getPerms(array $entry)
	{
		// get the references for visitor information
		$visitor = XenForo_Visitor::getInstance();
		$visitorUserId = XenForo_Visitor::getUserId();
		
		// add permissions information to the blog
		$allPermissions = $visitor->getPermissions();
		$blogPermissions = $allPermissions['xfa_blogs'];
		
		$isRegisteredUser = $visitorUserId > 0;
		$isEntryAuthor = $entry['user_id'] == $visitorUserId;
		$isBlogAdmin = $blogPermissions['xfa_blogs_admin'];
		
		// spam cleaner
		/* @var $userModel XenForo_Model_User  */
		$userModel = $this->getModelFromCache('XenForo_Model_User');
		$canCleanSpam = isset($entry['is_moderator']) && isset($entry['message_count']) &&
			$visitor->hasPermission('general', 'cleanSpam') && $userModel->couldBeSpammer($entry);
		
		// admins get almost any permission, except hard delete
		$perms = array(
			'canView' => (!isset($entry['deleteInfo']) || $blogPermissions['xfa_blogs_restore']),		// either not deleted, or can restore 
			'canEdit' => ($isEntryAuthor && $blogPermissions['xfa_blogs_edit']) || $isBlogAdmin,
			'canDelete' => ($isEntryAuthor && $blogPermissions['xfa_blogs_delete']) || $isBlogAdmin,
			'canRestore' => ($isEntryAuthor && $blogPermissions['xfa_blogs_restore']) || $isBlogAdmin,
			'canViewDeleted' => ($isEntryAuthor && $blogPermissions['xfa_blogs_restore']) || $isBlogAdmin,
			'canHardDelete' => ($isEntryAuthor && $blogPermissions['xfa_blogs_hard_delete']) || ($isBlogAdmin && $blogPermissions['xfa_blogs_hard_delete']),
			'canComment' => ($blogPermissions['xfa_blogs_comment']) || $isBlogAdmin,
			'canLike' => !$isEntryAuthor && $blogPermissions['xfa_blogs_like_entry'],
			'canViewIps' => $visitor->hasPermission('general', 'viewIps'),
			'canReport' => !$isEntryAuthor && $blogPermissions['xfa_blogs_report'],
			'canCleanSpam' => $canCleanSpam,
			'canViewHistory' => $isEntryAuthor || $blogPermissions['xfa_blogs_entry_history'] || $isBlogAdmin,
			'canRevert' => $isEntryAuthor || $isBlogAdmin
		);
		
		$perms['canInlineMod'] = $perms['canDelete'] || $perms['canRestore'];
		
		$options = XenForo_Application::getOptions();
		if (!$options->blogAdvancedFeatures)
		{
			$perms['canReport'] = false;
			$perms['canCleanSpam'] = false;
			$perms['canViewHistory'] = false;
			$perms['canInlineMod'] = false;
		}
		
		// Setup the canView permissions depending on the privacy options
		$this->processPrivacyOptions($entry, $blogPermissions, $perms);
		
		// Return the permissions
		return $perms;
	}
	
	/**
	 * Adds the relevant flags for viewing the blog depending on permissions
	 */
	protected function processPrivacyOptions(array $entry, array $blogPermissions, array &$perms)
	{
		$this->processCanViewPermission($entry, $blogPermissions, $perms);
	
		// there is a final overrides if we have the permission to bypass privacy
		if  (!$perms['canView'] && $blogPermissions['xfa_blogs_bypass_privacy'])
		{
			$perms['canView'] = true;
			$perms['showEntryPrivacyWarning'] = true;
		}
	}	
	
	/**
	 * Check if the user is authorized to view the entry, per the privacy permissions
	 * @param array $blog		The blog contents
	 */
	protected function processCanViewPermission(array $entry, array $blogPermissions, array &$perms)
	{
		// If this is already false, it means the entry was deleted, we won't process the permissions because this is already false
		if (!$perms['canView'])
		{
			return;
		}
		
		// the user can always view their own entries
		$visitorUserId = XenForo_Visitor::getUserId();
		if ($entry['user_id'] == $visitorUserId)
		{
			$perms['canView'] = true;
			return;
		}
		
		// check the access control
		if ($entry['allow_view_entry'] == 'everyone')
		{
			$perms['canView'] = true;
			return;
		}
		if ($entry['allow_view_entry'] == 'none')
		{
			$perms['canView'] = false;
			$perms['canViewPermissionDetail'] = new XenForo_Phrase('xfa_blogs_user_set_entry_to_personal');
			return;
		}
		if ($entry['allow_view_entry'] == 'members')
		{
			$perms['canView'] = $visitorUserId > 0;
			if (!$perms['canView'])
			{
				$perms['canViewPermissionDetail'] = new XenForo_Phrase('xfa_blogs_user_set_entry_to_members');
			}
			return;
		}
		if ($entry['allow_view_entry'] == 'followed')
		{
			$perms['canView'] = isset($entry['blog_owner_follows_visitor']) && $entry['blog_owner_follows_visitor'] > 0;
			if (!$perms['canView'])
			{
				$perms['canViewPermissionDetail'] = new XenForo_Phrase('xfa_blogs_user_set_entry_to_followed');
			}
			return;
		}
		if ($entry['allow_view_entry'] == 'list')
		{
			if (empty($entry['allow_members_ary']))
			{
				$perms['canView'] = false;
				$perms['canViewPermissionDetail'] = new XenForo_Phrase('xfa_blogs_user_set_entry_to_empty_list');
				return;
			}
			
			$perms['canView'] = in_array($visitorUserId, $entry['allow_members_ary']);
			if (!$perms['canView'])
			{
				$perms['canViewPermissionDetail'] = new XenForo_Phrase('xfa_blogs_user_set_entry_to_list');
			}
			return;
		}
		
		// usually we would never come here. This would hint at bad setup in the DB
		$perms['canView'] = false;
	}	
	
	/**
	 * Prepare with permission information a list of entries
	 * @param array $entries	An array with entries
	 */
	public function prepareEntries(&$entries)
	{
		foreach ($entries as &$entry)
		{
			$this->prepareEntry($entry);
		}
	}
	
	/**
	 * Return the max date for an entry, this is used by the controller for the mark as read functionality
	 * @param array $entries		An array of entry data
	 * @return int
	 */
	public function getMaxEntryDate($entries)
	{
		$maxEntryDate = -1;
		foreach ($entries as &$entry)
		{
			$maxEntryDate = max($maxEntryDate, $entry['post_date']);
		}
		return $maxEntryDate;
	}
	
	/**
	 * Update the user data, to register that the blog has been read
	 */
	public function markEntryAsRead($userId, $entryId, $currentDate, $newDate)
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
			INSERT INTO xfa_blog_entry_read
				(user_id, entry_id, entry_read_date)
			VALUES
				(?, ?, ?)
			ON DUPLICATE KEY UPDATE entry_read_date = VALUES(entry_read_date)
		", array($userId, $entryId, $newDate));		
	}	
	
	/**
	 * Returns the data for the entry. Lookup by primary key
	 * @param int $entryId		Identifier for the entry
	 */
	public function getEntryById($entryId, $fetchOptions = array())
	{
		$db = XenForo_Application::getDb();
		return $db->fetchRow("
			SELECT xfa_blog_entry.*
				" . $this->getSelectOptions($fetchOptions) . "				
			FROM xfa_blog_entry
				" . $this->getJoinOptions($fetchOptions) . "				
			WHERE
				xfa_blog_entry.entry_id = ?
			", $entryId);
	}
	
	/**
	 * Return a list of entries that matches a list of ids
	 * @param array $entryIds	List of ids to search for
	 * @return array
	 */
	public function getEntriesByIds($entryIds, $fetchOptions = array())
	{
		if (empty($entryIds))
		{
			return array();
		}
		
		return $this->fetchAllKeyed("
			SELECT xfa_blog_entry.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_entry
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				xfa_blog_entry.entry_id IN (" . implode($entryIds, ',') . ")
		", 'entry_id');		
	}
	
	/**
	 * Return a list of entries, starting from a given entry id and up to a certain limit
	 * @param int $start				The entry id to start at
	 * @param int $limit				The amount of entries to fetch
	 * @param array $fetchOptions		Use any of the constants of this class to pull additional fields
	 * @return array
	 */
	public function getEntryIdsInRange($start, $limit, $fetchOptions = array())
	{
		return $this->fetchAllKeyed("
			SELECT xfa_blog_entry.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_entry
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				1=1
				" . $this->getWhereOptions($fetchOptions) . "				
			ORDER BY
				entry_id
			LIMIT
				{$start}, {$limit} 
		", 'entry_id');
	}
	
	/**
	 * Returns a lsit of blogs with a starting id and up to a limit. This is specially used by the sitemap.
	 * @param int $previousLast	The last id we want
	 * @param int $limit		The total entries to retrieve
	 */
	public function getEntryIdsFromRangeId($previousLast, $limit)
	{
		$sql = "
			SELECT
				xfa_blog_entry.*
			FROM xfa_blog_entry
			INNER JOIN xfa_blog ON xfa_blog_entry.user_id = xfa_blog.user_id
			INNER JOIN xf_user_privacy ON xfa_blog.user_id = xf_user_privacy.user_id
			WHERE
				entry_id > ? AND
				message_state = 'visible' AND
				allow_view_blog = 'everyone'
			ORDER BY
				xfa_blog_entry.entry_id
			LIMIT
				{$limit}
			";

		return $this->fetchAllKeyed($sql, 'entry_id', array( $previousLast ));
	}	
	
	
	/**
	 * Return a list of entries. This method depends on what you pass on fetchOptions
	 * @param array $fetchOptions		Use any of the constants of this class to pull additional fields
	 * @return array		The list of entries
	 */
	public function getEntries($fetchOptions = array())
	{
		return $this->fetchAllKeyed("
			SELECT xfa_blog_entry.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_entry
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				1=1
				" . $this->getWhereOptions($fetchOptions) . "
				" . $this->getOrderByOptions($fetchOptions) . "
				" . $this->getLimitOptions($fetchOptions) . "",
			'entry_id');
	}

	/**
	 * Similar to getEntries(), but only retrieves the id and the title, and never the text
	 * @param array $fetchOptions		Use any of the constants of this class to pull additional fields
	 * @return array		The list of entries
	 */
	public function getEntriesSimple($fetchOptions = array())
	{
		$db = XenForo_Application::getDb();
		return $db->fetchAll("
			SELECT 
				xfa_blog_entry.entry_id,
				xfa_blog_entry.user_id,
				xfa_blog_entry.title,
				xfa_blog_entry.allow_view_entry,
				xfa_blog_entry.allow_members_ids,
				xfa_blog_entry.post_date
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_entry
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				1=1
				" . $this->getWhereOptions($fetchOptions) . "
				" . $this->getOrderByOptions($fetchOptions) . "
				" . $this->getLimitOptions($fetchOptions) . "
			");
	}
	
	/**
	 * Returns a certain number of entries that a user has done
	 * @param array $blog	A reference to the user information
	 */
	public function getBlogEntriesForUser(array $blog, $fetchOptions)
	{
		$db = XenForo_Application::getDb();
		
		return $this->fetchAllKeyed("
			SELECT xfa_blog_entry.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_entry
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				xfa_blog_entry.user_id = ?
				" . $this->getWhereOptions($fetchOptions) ."
				" . $this->getPosition($fetchOptions) ."
				" . (isset($fetchOptions['extraWhere']) ? $fetchOptions['extraWhere'] : '') . "				
			ORDER BY
				post_date DESC
			", 'entry_id', $blog['user_id'] );
	}
	
	/**
	 * Return the latest entries that have been posted in the forum
	 * @param int limit		The amount of entries to fetch
	 */
	public function getLatestEntries($fetchOptions)
	{
		$db = XenForo_Application::getDb();
		return $this->fetchAllKeyed("
			SELECT xfa_blog_entry.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_entry
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				xfa_blog_entry.message_state = 'visible'
				" . $this->getWhereOptions($fetchOptions) . "
			ORDER BY
				post_date DESC
			" . $this->getLimitOptions($fetchOptions) . "
			" . $this->getLimitWithPage($fetchOptions) . "
			", 'entry_id');		
	}
	
	/**
	 * Return the total entries in the database
	 */
	public function getTotalEntries($fetchOptions)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchOne("
			SELECT count(*) total
			FROM xfa_blog_entry
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				xfa_blog_entry.message_state = 'visible'
				" . $this->getWhereOptions($fetchOptions) . "
		");		
	}
	
	/**
	 * Returns the entries that were posted in a specific category
	 * @param array $category	A reference to the category information
	 */
	public function getBlogEntriesForCategory(array $category, $fetchOptions = array())
	{
		$db = XenForo_Application::getDb();
		return $this->fetchAllKeyed("
			SELECT xfa_blog_entry.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_entry
			INNER JOIN xfa_blog_entry_category ON xfa_blog_entry.entry_id = xfa_blog_entry_category.entry_id
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				xfa_blog_entry_category.category_id = ? AND
				xfa_blog_entry.message_state = 'visible'
				" . $this->getWhereOptions($fetchOptions) . "
				" . $this->getOrderByOptions($fetchOptions) . "
				" . $this->getLimitWithPage($fetchOptions) ."			
			", 'entry_id', $category['category_id'] );
	}	

	/**
	 * For a single entry, merge all the attachment information that we have for it.
	 * This will create a key named attachments
	 */
	public function getAndMergeAttachmentsIntoEntry(&$entry)
	{
		/* @var $attachmentModel XenForo_Model_Attachment */
		$attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
		$attachments = $attachmentModel->getAttachmentsByContentId('xfa_blog_entry', $entry['entry_id']);
		foreach ($attachments AS $attachment)
		{
			$entry['attachments'][$attachment['attachment_id']] = $attachmentModel->prepareAttachment($attachment);
		}
	}	
	
	/**
	 * For a list of entries, merge all the attachment information that we have for them.
	 * This will create a key named attachments in each of the entries
	 */
	public function getAndMergeAttachmentsIntoEntries(&$entries)
	{
		// there is an optimization with attach_count
		$entryIds = array();
		foreach ($entries as $entry)
		{
			$entryIds[] = $entry['entry_id'];
		}

		if ($entryIds)
		{
			/* @var $attachmentModel XenForo_Model_Attachment */
			$attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
			$attachments = $attachmentModel->getAttachmentsByContentIds('xfa_blog_entry', $entryIds);
			foreach ($attachments AS $attachment)
			{
				$entryId = $attachment['content_id'];
				$entries[$entryId]['attachments'][$attachment['attachment_id']] = $attachmentModel->prepareAttachment($attachment);
			}
		}
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
			$fetchOptions['join'] = 0;
		}
		if (!isset($fetchOptions['prepareOptions']))
		{
			$fetchOptions['prepareOptions'] = 0;
		}		
		
		// SELECT can be influenced by the join
		$select = '';
		if ($fetchOptions['join'] & self::JOIN_USER)
		{
			$select .= ',xf_user.*';
		}
		if ($fetchOptions['join'] & self::JOIN_DELETION_LOG)
		{
			$select .= ',deletion_log.*';
		}
		if ($fetchOptions['join'] & self::JOIN_LIKE_INFORMATION)
		{
			$select .= ',liked_content.like_date';
		}
		if ($fetchOptions['join'] & self::JOIN_BLOG)
		{
			$select .= ',xfa_blog.user_id, xfa_blog.blog_title, xfa_blog.blog_key';
		}
		if ($fetchOptions['join'] & self::JOIN_BLOG_PRIVACY)
		{
			$select .= ',user_privacy.allow_view_blog';
		}
		if ($fetchOptions['join'] & self::JOIN_VISITOR_WATCH)
		{
			$select .= ',xfa_blog_entry_watch.watch_id watch_id';
		}
		if ($fetchOptions['join'] & self::JOIN_READ_DATE)
		{
			$select .= ',xfa_blog_entry_read.entry_read_date';
		}	
		if ($fetchOptions['join'] & self::JOIN_VISITOR_FOLLOW)
		{
			$select .= ',user_follow.follow_user_id blog_owner_follows_visitor';
		}
		
		// SELECT can also be influenced with prepareOptions
		if ($fetchOptions['prepareOptions'] & self::PREPARE_ALLOW_MEMBERS)
		{
			$select .= ', 1 initializeAllowMembers';
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
		if ($fetchOptions['join'] & self::JOIN_USER)
		{
			$join .= " INNER JOIN xf_user ON xfa_blog_entry.user_id = xf_user.user_id";
		}
		if ($fetchOptions['join'] & self::JOIN_DELETION_LOG)
		{
			$join .= " LEFT JOIN xf_deletion_log deletion_log ON xfa_blog_entry.entry_id = deletion_log.content_id AND content_type = 'xfa_blog_entry'";
		}
		if ($fetchOptions['join'] & self::JOIN_LIKE_INFORMATION)
		{
			$join .= "
				LEFT JOIN xf_liked_content AS liked_content
					ON (liked_content.content_type = 'xfa_blog_entry'
					AND liked_content.content_id = xfa_blog_entry.entry_id
					AND liked_content.like_user_id = " .$visitorUserId . ")					
				";
		}
		if ($fetchOptions['join'] & self::JOIN_BLOG)
		{
			$join .= " INNER JOIN xfa_blog ON xfa_blog_entry.user_id = xfa_blog.user_id";			
		}
		if ($fetchOptions['join'] & self::JOIN_BLOG_PRIVACY)
		{
			$join .= " INNER JOIN xf_user_privacy user_privacy ON xfa_blog.user_id = user_privacy.user_id";
		}
		if ($fetchOptions['join'] & self::JOIN_VISITOR_WATCH)
		{
			$join .= " LEFT JOIN xfa_blog_entry_watch ON xfa_blog_entry.entry_id = xfa_blog_entry_watch.entry_id AND xfa_blog_entry_watch.user_id = " . $visitorUserId;
		}
		if ($fetchOptions['join'] & self::JOIN_READ_DATE)
		{
			$join .= " LEFT JOIN xfa_blog_entry_read ON xfa_blog_entry.entry_id = xfa_blog_entry_read.entry_id AND xfa_blog_entry_read.user_id = " . $visitorUserId;
		}
		if ($fetchOptions['join'] & self::JOIN_VISITOR_FOLLOW)
		{
			$join .= " LEFT JOIN xf_user_follow user_follow ON xfa_blog_entry.user_id = user_follow.user_id AND follow_user_id = " . $visitorUserId;
		}		
		
		return $join;		
	}
	
	/**
	 * Alert members tagged in an entry
	 */
	public function alertTaggedMembers(array $entry, array $tagged)
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
			if (!isset($alertedUserIds[$user['user_id']]) && $user['user_id'] != $entry['user_id'])
			{
				if (!$userModel->isUserIgnored($user, $entry['user_id']) && XenForo_Model_Alert::userReceivesAlert($user, 'xfa_blog_entry', 'tagged'))
				{
					$alertedUserIds[$user['user_id']] = true;
					XenForo_Model_Alert::alert($user['user_id'], $entry['user_id'], '', 'xfa_blog_entry', $entry['entry_id'], 'tagged');
				}
			}
		}
		return array_keys($alertedUserIds);
	}	
	
	/**
	 * To an array of entries, this will retrieve information for all the blogs referenced
	 *
	 * @param array $entries	A reference to the entries
	 */
	public function wireBlogs(array &$entries)
	{
		$ids = array();
		foreach ($entries as $entry)
		{
			$ids[$entry['user_id']] = true;
		}
		$blogIds = array_keys($ids);
	
		/* @var $blogModel XfAddOns_Blogs_Model_Blog */
		$blogModel = XenForo_Model::create('XfAddOns_Blogs_Model_Blog');
		$fetchOptions = array(
			'join' => XfAddOns_Blogs_Model_Blog::JOIN_PRIVACY + XfAddOns_Blogs_Model_Blog::JOIN_VISITOR_FOLLOW
		);
		$blogs = $blogModel->getBlogsByIds($blogIds, $fetchOptions);
		$blogModel->prepareBlogs($blogs);
		
		foreach ($entries as &$entry)
		{
			$blogId = $entry['user_id'];
			if (isset($blogs[$blogId]))
			{
				$entry['blog'] = $blogs[$blogId];
			}
		}
	}
	
	/**
	 * Remove all the private entries. This method depends on prepareEntry() to have been called beforehand, which will
	 * initialize a canView permission on each of the entries. We will remove anything that does not have a canView.
	 * THis method will also try to go down to blog permissions and remove if we don't have view privileges, although
	 * initializing the blog information is optional
	 */
	public function removePrivateEntriesForVisitor(&$entries)
	{
		foreach ($entries as $key => $entry)
		{
			if (!$entry['perms']['canView'])
			{
				unset($entries[$key]);
			}
			if (isset($entry['blog']) && isset($entry['blog']['perms']) && !$entry['blog']['perms']['canView'])
			{
				unset($entries[$key]);
			}
		}
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
				// validate that the visitor has permission to view the entry
				$where .= "
					AND (
						(xfa_blog_entry.user_id = " . $visitorUserId . ") OR
						(xfa_blog_entry.allow_view_entry = 'followed' AND user_follow.follow_user_id > 0) OR
						" . ($visitorUserId > 0 ? "xfa_blog_entry.allow_view_entry = 'members' OR" : "") . "
						(xfa_blog_entry.allow_view_entry = 'list' AND FIND_IN_SET({$visitorUserId}, allow_members_ids) > 0) OR
						xfa_blog_entry.allow_view_entry = 'everyone'
					)
				";
				// validate that the visitor has permission to view the blog
				if ($fetchOptions['join'] & self::JOIN_BLOG)
				{
					$where .= "
						AND (
							(xfa_blog.user_id = " . $visitorUserId . ") OR
							(user_privacy.allow_view_blog = 'followed' AND user_follow.follow_user_id > 0) OR
							" . ($visitorUserId > 0 ? "user_privacy.allow_view_blog = 'members' OR" : "") . "
							user_privacy.allow_view_blog = 'everyone'
						)
					";
				}
			}
		}		
		return $where;
	}
	
	/**
	 * Return the options to limit the size of the resultset
	 * @param array $fetchOptions		Fetch options passed to the query that should contain the limit
	 * @return string					Either the LIMIT clause for the SQL, or an empty string
	 */
	protected function getLimitOptions($fetchOptions)
	{
		if (empty($fetchOptions) || !isset($fetchOptions['limit']))
		{
			return '';
		}
		return ' LIMIT ' . $fetchOptions['limit'];
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
	 * Return how many entries to fetch. In the fetch options we should have a limit and a start
	 * @param array $fetchOptions	An array where the options reside
	 */
	protected function getPosition($fetchOptions)
	{
		// entries per page
		$options = XenForo_Application::getOptions();
		$page = isset($fetchOptions['page']) ? $fetchOptions['page'] : 1;
		
		// return the limit depending on the page
		if (!isset($fetchOptions['reverse']))
		{
			$start = ($page - 1) * $options->xfa_blogs_entriesPerPage;
			$end = $start + $options->xfa_blogs_entriesPerPage;
			return "AND position >= " . $start . " AND position <" . $end;
		}
		else
		{
			$skip = ($page - 1) * $options->xfa_blogs_entriesPerPage;
			$end = $fetchOptions['reverse'] - $skip;
			$start = $end - $options->xfa_blogs_entriesPerPage;
			return "AND position >= " . $start . " AND position <" . $end;
		}
	}
	
	/**
	 * While getPosition() is appropriate for the cases in which the entries are just being navigated
	 * from their position within the blog, if we want to randomly localize the entries (like when
	 * navigating by category), we actually need to do it including LIMIT
	 * 
	 * @param array $fetchOptions	An array where the options reside
	 */
	protected function getLimitWithPage($fetchOptions)
	{
		if (!isset($fetchOptions['page']))
		{
			return '';
		}
		
		// entries per page
		$options = XenForo_Application::getOptions();
		$page = isset($fetchOptions['page']) ? $fetchOptions['page'] : 1;
		$page = max($page, 1);		// if page would ever be 0 or negative, it would be 1
		
		$start = ($page - 1) * $options->xfa_blogs_entriesPerPage;
		return "LIMIT {$start}, {$options->xfa_blogs_entriesPerPage}";
	}
	
}