<?php

/**
 * Fetch any scheduled entry information that we might have 
 */
class XfAddOns_Blogs_Model_EntryScheduled extends XenForo_Model
{

	/**
	 * Used with $fetchOptions['prepareOptions'] to influence the prepareEntry method
	 * @var int
	 */
	const PREPARE_ALLOW_MEMBERS = 0X2;	
	
	/**
	 * Add permission and any other required information to the entry
	 * @param array $entry	An array with the entry information. Array will be modified
	 */
	public function prepareScheduledEntry(&$entry)
	{
		$entry['perms'] = $this->getPerms($entry);
		
		// prepare privacy options
		XfAddOns_Blogs_Model_Entry::preparePrivacy($entry);
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
		
		// admins get almost any permission, except hard delete
		return array(
			'canEdit' => ($isEntryAuthor && $blogPermissions['xfa_blogs_edit']) || $isBlogAdmin,
			'canDelete' => ($isEntryAuthor && $blogPermissions['xfa_blogs_delete']) || $isBlogAdmin
		);
	}
	
	/**
	 * Prepare with permission information a list of entries
	 * @param array $entries	An array with entries
	 */
	public function prepareScheduledEntries(&$entries)
	{
		foreach ($entries as &$entry)
		{
			$this->prepareScheduledEntry($entry);
		}
	}	
	
	/**
	 * Returns the data for the scheduled entry. Lookup by primary key
	 * @param int $entryId		Identifier for the entry
	 */
	public function getScheduledEntryById($entryId, $fetchOptions = array())
	{
		$db = XenForo_Application::getDb();
		return $db->fetchRow("
			SELECT xfa_blog_entry_scheduled.*
				" . $this->getSelectOptions($fetchOptions) . "				
			FROM xfa_blog_entry_scheduled
				" . $this->getJoinOptions($fetchOptions) . "				
			WHERE
				xfa_blog_entry_scheduled.scheduled_entry_id = ?
			", $entryId);
	}	
	
	/**
	 * Returns a certain number of entries that a user has done
	 * @param array $blog	A reference to the user information
	 */
	public function getScheduledEntriesForUser(array $blog, $fetchOptions = array())
	{
		$db = XenForo_Application::getDb();
		
		return $this->fetchAllKeyed("
			SELECT xfa_blog_entry_scheduled.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_entry_scheduled
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				xfa_blog_entry_scheduled.user_id = ?
			ORDER BY
				post_date
			" . $this->getLimitOptions($fetchOptions) . "
			", 'scheduled_entry_id', $blog['user_id'] );
	}	
	
	/**
	 * Returns a list of all the scheduled entries that have their publish date passed, and that we should now
	 * publish.
	 */
	public function getScheduledEntriesForPublish($fetchOptions = array())
	{
		$db = XenForo_Application::getDb();
		return $this->fetchAllKeyed("
			SELECT xfa_blog_entry_scheduled.*
				" . $this->getSelectOptions($fetchOptions) . "
			FROM xfa_blog_entry_scheduled
				" . $this->getJoinOptions($fetchOptions) . "
			WHERE
				post_date < ?
			ORDER BY
				post_date
			" . $this->getLimitOptions($fetchOptions) . "
			", 'scheduled_entry_id', XenForo_Application::$time );
	}
	
	/**
	 * For a list of entries, merge all the attachment information that we have for them.
	 * This will create a key named attachments in each of the entries
	 */
	public function getAndMergeAttachmentsIntoEntries(&$entries)
	{
		// there is an optimization with attach_count
		$ids = array();
		foreach  ($entries as $entry)
		{
			$ids[] = $entry['scheduled_entry_id'];
		}

		if (!empty($ids))
		{
			/* @var $attachmentModel XenForo_Model_Attachment */
			$attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
			$attachments = $attachmentModel->getAttachmentsByContentIds('xfa_blog_entry_scheduled', $ids);
			foreach ($attachments AS $attachment)
			{
				$id = $attachment['content_id'];
				$entries[$id]['attachments'][$attachment['attachment_id']] = $attachmentModel->prepareAttachment($attachment);
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

		$select = '';
		
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
		return '';
	}
	
	/**
	 * If fetchoptions includes a limit clause, we will generate a LIMIT x
	 * @param array $fetchOptions	An array where the options reside
	 */
	protected function getLimitOptions($fetchOptions)
	{
		if (isset($fetchOptions['limit']))
		{
			return ' LIMIT ' . $fetchOptions['limit'];
		}
		return '';
	}	
	
	
	
}