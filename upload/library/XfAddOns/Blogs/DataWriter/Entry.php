<?php

class XfAddOns_Blogs_DataWriter_Entry extends XenForo_DataWriter
{
	
	/**
	 * Option that controls whether data in the blog should be updated,
	 * including entry counts. Defaults to true.
	 * @var boolean
	 */
	const OPTION_UPDATE_BLOG = 'updateBlog';	
	
	/**
	 * Option that controls if we should store the ip address
	 * @var string
	 */
	const OPTION_UPDATE_IP_ADDRESS = 'updateIpAddress';	
	
	/**
	 * Although position should always be updated, this might be disabled for batch imports
	 * @var string
	 */
	const OPTION_UPDATE_POSITION = 'updatePosition';
	
	/**
	 * Flag to send alerts on a new response
	 * @var boolean
	 */
	const OPTION_ADD_ALERTS = 'addAlerts';	
	
	/**
	 * Extra data for the Delete Reason
	 */
	const EXTRA_DELETE_REASON = 'reason';
	
	/**
	 * Extra data for the hash used to upload the attachments
	 */
	const DATA_ATTACHMENT_HASH = 'attachment_hash';
	
	/**
	 * Key for existing categories (used by this datawriter itself)
	 */
	const EXTRA_DATA_EXISTING_CATEGORIES = 'existing_categories';
	
	/**
	 * Key for new categories that are going to be set
	 */
	const EXTRA_DATA_NEW_CATEGORIES = 'new_categories';
	
	/**
	 * @var XfAddOns_Blogs_DataWriter_Blog
	 */
	private $blogDw;
	
	/**
	 * Processes users that are tagged in a post
	 * @var array
	 */
	protected $_taggedUsers = array();	
	
	/**
	 * Return the list of fields in the xfa_blog_entry table
	 * These are all the fields that the datawriter will attempt to update or insert
	 * @return array
	 */
	protected function _getFields()
	{
		$fields = array();
		$fields['xfa_blog_entry']['entry_id'] = array(
			'type' => self::TYPE_UINT_FORCED,
			'autoIncrement' => true
			);
		$fields['xfa_blog_entry']['user_id'] = array(
			'type' => self::TYPE_UINT,
			'required' => true
			);
		$fields['xfa_blog_entry']['title'] = array(
			'type' => self::TYPE_STRING,
			'maxLength' => 250,				
			'required' => true,
			'requiredError' => 'xfa_blogs_entry_title_required'
			);
		$fields['xfa_blog_entry']['post_date'] = array(
			'type' => self::TYPE_INT,
			'required' => true,
			'default' => XenForo_Application::$time
			);
		$fields['xfa_blog_entry']['reply_count'] = array(
			'type' => self::TYPE_UINT,
			'required' => true,
			'default' => 0
			);
		$fields['xfa_blog_entry']['view_count'] = array(
			'type' => self::TYPE_UINT,
			'default' => 0
		);		
		$fields['xfa_blog_entry']['message'] = array(
			'type' => self::TYPE_STRING,
			'required' => true,
			'requiredError' => 'xfa_blogs_entry_message_required'
			);
		$fields['xfa_blog_entry']['message_state'] = array(
			'type' => self::TYPE_STRING,
			'required' => true,
			'allowedValues' => array( 'visible', 'deleted' ),
			'default' => 'visible'
		);		
		$fields['xfa_blog_entry']['ip_id'] = array(
			'type' => self::TYPE_UINT,
			'required' => true,
			'default' => 0
		);
		$fields['xfa_blog_entry']['position'] = array(
			'type' => self::TYPE_INT,
			'required' => true,
			'default' => 0
		);
		$fields['xfa_blog_entry']['likes'] = array(
			'type' => self::TYPE_UINT_FORCED,
			'required' => true,
			'default' => 0
		);
		$fields['xfa_blog_entry']['like_users'] = array(
			'type' => self::TYPE_SERIALIZED,
			'required' => true,
			'default' => 'a:0:{}'
		);		
		$fields['xfa_blog_entry']['allow_comments'] = array(
			'type' => self::TYPE_UINT,
			'required' => true,
			'default' => 1
		);
		$fields['xfa_blog_entry']['allow_view_entry'] = array(
			'type' => self::TYPE_STRING,
			'required' => true,
			'default' => 'everyone',
			'allowedValues' => array('everyone','members','followed','none', 'list')
		);		
		$fields['xfa_blog_entry']['allow_members_ids'] = array(
			'type' => self::TYPE_STRING,
			'required' => false,
		);
		$fields['xfa_blog_entry']['last_edit_date'] = array(
			'type' => self::TYPE_UINT,
			'default' => 0
		);
		$fields['xfa_blog_entry']['last_edit_user_id'] = array(
			'type' => self::TYPE_UINT,
			'default' => 0
		);
		$fields['xfa_blog_entry']['edit_count'] = array(
			'type' => self::TYPE_UINT_FORCED,
			'default' => 0
		);
		
		return $fields;
	}

	/**
	 * Gets the default set of options for this data writer.
	 * @return array
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_UPDATE_BLOG => true,
			self::OPTION_UPDATE_IP_ADDRESS => true,
			self::OPTION_UPDATE_POSITION => true,
			self::OPTION_ADD_ALERTS => true
		);
	}
	
	/**
	 * Existing data is needed for the updates. This query will fetch whatever the entry
	 * currently has in the database
	 *
	 * @param array $data		An array of the data currently configured in the data writer
	 * @return 					an array with the existing data, all tables included
	 */
	protected function _getExistingData($key)
	{
		if ($key)
		{
			$db = XenForo_Application::getDb();
			$data = $db->fetchRow("SELECT * FROM xfa_blog_entry WHERE entry_id = ?", array( $key ));
			return array( 'xfa_blog_entry' => $data );
		}
		return null;
	}
	
	/**
	 * Returns the query part used in the where condition for updating the table. This must match the primary key
	 *
	 * @param string $tableName	We ignore this, the data writer only supports the entry table
	 * @return the where part for updating the table
	 */
	protected function _getUpdateCondition($tableName)
	{
		return ' entry_id = ' . $this->_db->quote($this->getExisting('entry_id'));
	}
	
	/**
	 * Content type is xfa_blog_entry
	 * @return string
	 */
	protected function getContentType()
	{
		return 'xfa_blog_entry';
	}	
	
	/**
	 * Override the pre-save handler to update calculated fields
	 */
	protected function _preSave()
	{
		parent::_preSave();
		
		if ($this->getOption(self::OPTION_UPDATE_POSITION))
		{
			if (!$this->isChanged('position'))
			{
				$this->_setPosition();
			}
		}
		
		if ($this->isUpdate() && $this->isChanged('message'))
		{
			$this->set('last_edit_date', XenForo_Application::$time);
			$this->set('last_edit_user_id', XenForo_Visitor::getUserId());
			$this->set('edit_count', $this->get('edit_count') + 1);
		}
		
		$this->processTaggedUsers();
	}
	
	/**
	 * Process and save the users that are tagged in the message
	 */
	protected function processTaggedUsers()
	{
		/* @var $taggingModel XenForo_Model_UserTagging */
		$taggingModel = $this->getModelFromCache('XenForo_Model_UserTagging');
	
		$this->_taggedUsers = $taggingModel->getTaggedUsersInMessage(
				$this->get('message'), $newMessage, 'bb'
		);
		$this->set('message', $newMessage);
	}	
	
	/**
	 * Set the position of the entry within the blog
	 */
	protected function _setPosition()
	{
		if (!$this->isInsert() && !$this->isChanged('message_state'))
		{
			return;
		}
		
		$blogDw = $this->getBlogDw();
		if (!$blogDw)
		{
			return;
		}
	
		if ($this->isInsert())
		{
			$blog = $blogDw->getBlogForUpdate($this->get('user_id'));
			
			$entriesCount = $blog && isset($blog['entry_count']) ?
				$blog['entry_count'] : $blogDw->get('entry_count');

			$position = $this->get('message_state') == 'visible' ?  $entriesCount + 1 : $entriesCount;
			$this->set('position', $position);
			return;
		}
	
		// updated the state on an existing message -- need to slot in
		if ($this->get('message_state') == 'visible' && $this->getExisting('message_state') != 'visible')
		{
			$this->set('position', $this->get('position') + 1);
		}
		else if ($this->get('message_state') != 'visible' && $this->getExisting('message_state') == 'visible')
		{
			$this->set('position', $this->get('position') - 1);
		}
	}	

	/**
	 * Override the post-save handler to cascade updates
	 */
	protected function _postSave()
	{
		parent::_postSave();
		
		if ($this->getOption(self::OPTION_UPDATE_IP_ADDRESS))
		{
			if ($this->isInsert() && !$this->get('ip_id'))
			{
				$this->_updateIpData();
			}
		}		
		
		// update the deletion log
		$this->_updateDeletionLog();
		
		// associate attachments as necessary
		$this->_associateAttachments();
		
		// update the meta-information in the blog table (counts, etc)
		if ($this->getOption(self::OPTION_UPDATE_BLOG))
		{
			$blogDw = $this->getBlogDw();
			if ($blogDw)
			{
				$blogDw->updateCountersAfterEntrySave($this);
			}			
		}
		
		// send alerts and publish the news feed info
		if ($this->getOption(self::OPTION_ADD_ALERTS) && $this->isInsert())
		{
			$this->_publishToNewsFeed();
			$this->_publishWatch();
		}		
		
		// update the position counters if needed
		if ($this->isUpdate() && $this->isChanged('message_state'))
		{
			$this->_updateMessagePositionList();
		}
		
		// index
		$this->_indexForSearch();
		
		// update the categories
		$this->updateCategories();
		
		// add to the edit history log
		if ($this->isUpdate() && $this->isChanged('edit_count'))
		{
			$this->_insertEditHistory();
		}
	}

	/**
	 * Notify users that are watching the blog
	 */
	protected function _publishWatch()
	{
		$options = XenForo_Application::getOptions();
		if (!$options->blogAdvancedFeatures)
		{
			return;
		}
		
		/* @var $watchModel XfAddOns_Blogs_Model_BlogWatch */
		$watchModel = XenForo_Model::create('XfAddOns_Blogs_Model_BlogWatch');
		$watchModel->notifySubscribedUsers($this->getMergedData('xfa_blog_entry'));
	}
	
	/**
	 * Inserts a record for the edit history.
	 */
	protected function _insertEditHistory()
	{
		$historyDw = XenForo_DataWriter::create('XenForo_DataWriter_EditHistory', XenForo_DataWriter::ERROR_SILENT);
		$historyDw->bulkSet(array(
			'content_type' => $this->getContentType(),
			'content_id' => $this->get('entry_id'),
			'edit_user_id' => XenForo_Visitor::getUserId(),
			'old_text' => $this->getExisting('message')
		));
		$historyDw->save();
	}
	
	/**
	 * After transaction, we will do some integration with the TagMe add-on
	 * @see XenForo_DataWriter::_postSaveAfterTransaction()
	 */
	protected function _postSaveAfterTransaction()
	{
		parent::_postSaveAfterTransaction();
		$this->notifyTaggedUsers();
	}
	
	/**
	 * This will notify all the users that are tagged with @User
	 */
	protected function notifyTaggedUsers()
	{
		if ($this->get('message_state') != 'visible')
		{
			return;
		}
		if (!$this->isInsert())
		{
			return;
		}		
		
		if ($this->_taggedUsers)
		{
			/* @var $entryModel XfAddOns_Blogs_Model_Entry */
			$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
			$entry = $this->getMergedData();
			$entryModel->alertTaggedMembers($entry, $this->_taggedUsers);
		}
	}
	
	/**
	 * Adds the entry into the search index
	 */
	protected function _indexForSearch()
	{
		$options = XenForo_Application::getOptions();
		if (!$options->blogAdvancedFeatures)
		{
			return;
		}
		
		/* @var $dataHandler XfAddOns_Blogs_SearchHandler_Entry */
		$dataHandler = XenForo_Search_DataHandler_Abstract::create('XfAddOns_Blogs_SearchHandler_Entry');
		$indexer = new XenForo_Search_Indexer();
		
		// check if we have a blog
		$blogDw = $this->getBlogDw();
		$blog = $blogDw->getMergedData();
		
		if ($this->get('message_state') == 'visible')
		{
			if ($this->getExisting('message_state') != 'visible' || $this->isChanged('message'))
			{
				$dataHandler->insertIntoIndex($indexer, $this->getMergedData(), $blog);
			}
		}
		else if ($this->isUpdate() && $this->get('message_state') != 'visible' && $this->getExisting('message_state') == 'visible')
		{
			$dataHandler->deleteFromIndex($indexer, $this->getMergedData());
		}
	}
	
	/**
	 * Update the categories data
	 */
	protected function updateCategories()
	{
		// let's store the existing categories
		if ($this->isInsert())
		{
			$this->setExtraData(self::EXTRA_DATA_EXISTING_CATEGORIES, array());
		}
		else
		{
			/* @var $categoryModel XfAddOns_Blogs_Model_Category */
			$categoryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Category');
			$existingCategories = $categoryModel->getSelectedCategoriesForEntry($this->get('entry_id'));
			$this->setExtraData(self::EXTRA_DATA_EXISTING_CATEGORIES, $existingCategories);
		}
		
		// when we are soft deleting a post, we don't send new categories (edit does), so we'll assume they didn't change
		$newCategories = $this->getExtraData(self::EXTRA_DATA_NEW_CATEGORIES);
		if ($newCategories === null)
		{
			$this->setExtraData(self::EXTRA_DATA_NEW_CATEGORIES, array_keys($existingCategories));
		}
		
		// proceed to delete and re-insert the categories
		$this->deleteExistingCategories();
		$this->addNewCategories();
	}
	
	/**
	 * Check the categories that the entry is registered to, and unregister the entry for all the categories
	 */
	protected function deleteExistingCategories()
	{
		if ($this->isInsert())
		{
			return;
		}
		
		$existingCategories = $this->getExtraData(self::EXTRA_DATA_EXISTING_CATEGORIES);
		if (empty($existingCategories))	// nothing to do
		{
			return;	
		}

		if ($this->getExisting('message_state') == 'visible')	// if the message was visible, decrement
		{
			$ids = array_keys($existingCategories);
			$this->_db->query("UPDATE xfa_blog_category SET entry_count=entry_count-1 WHERE category_id IN (" . implode(",", $ids) . ")");	
		}
		// delete all the entries (will be reinserted)
		$this->_db->delete('xfa_blog_entry_category', 'entry_id=' . $this->_db->quote($this->get('entry_id')));	
	}
	
	/**
	 * Register the entry to any category passed through extra_data
	 */
	protected function addNewCategories()
	{
		// if there is no data, there is not much to do
		$categories = $this->getExtraData(self::EXTRA_DATA_NEW_CATEGORIES);
		if (empty($categories))
		{
			return;
		}
		
		$entryId = $this->get('entry_id');
		foreach ($categories as $categoryId)
		{
			$this->_db->insert('xfa_blog_entry_category',
					array('entry_id' => $entryId, 'category_id' => $categoryId));
		}
		
		if ($this->get('message_state') == 'visible')
		{
			$this->_db->query("UPDATE xfa_blog_category SET entry_count=entry_count+1 WHERE category_id IN (" . implode(",", $categories) . ")");	
		}
	}
	
	/**
	 * Associates attachments with this message.
	 *
	 * @param string $attachmentHash
	 */
	protected function _associateAttachments()
	{
		$attachmentHash = $this->getExtraData(self::DATA_ATTACHMENT_HASH);
		if (!$attachmentHash)
		{
			return;
		}
		
		$rows = $this->_db->update('xf_attachment', array(
			'content_type' => $this->getContentType(),
			'content_id' => $this->get('entry_id'),
			'temp_hash' => '',
			'unassociated' => 0
		), 'temp_hash = ' . $this->_db->quote($attachmentHash));
	}	
	
	/**
	* The entries can be hard deleted. When that happens, we have some cleanup to do, we need to delete all the comments associated
	* with the entry, and we need to delete all references in the alerts and the news feed (as they will no longer be valid)
	*/
	protected function _postDelete()
	{
		// the deletion log is no longer relevant at this point
		$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog($this->getContentType(), $this->get('entry_id'));
		
		// remove alerts and news feed
		$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts($this->getContentType(), $this->get('entry_id'));
		$this->_getNewsFeedModel()->delete($this->getContentType(), $this->get('entry_id'));
		
		// adjust the position of messages around
		$this->_adjustPositionListForRemoval();
		
		// update the totals for the blog
		// update the meta-information in the blog table (counts, etc)
		if ($this->getOption(self::OPTION_UPDATE_BLOG))
		{
			$blogDw = $this->getBlogDw();
			if ($blogDw)
			{
				$blogDw->updateCountersAfterEntrySave($this);
			}			
		}

		// remove the attachments
		$this->getModelFromCache('XenForo_Model_Attachment')->deleteAttachmentsFromContentIds(
			$this->getContentType(),
			array($this->get('entry_id'))
		);
		
		// delete existing categories
		/* @var $categoryModel XfAddOns_Blogs_Model_Category */
		$categoryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Category');
		$existingCategories = $categoryModel->getSelectedCategoriesForEntry($this->get('entry_id'));
		$this->setExtraData(self::EXTRA_DATA_EXISTING_CATEGORIES, $existingCategories);
		$this->deleteExistingCategories();		
	}	
	
	/**
	 * Updates the deletion log if necessary.
	 */
	protected function _updateDeletionLog()
	{
		if (!$this->isChanged('message_state'))
		{
			return;
		}
	
		if ($this->get('message_state') == 'deleted')
		{
			$reason = $this->getExtraData(self::EXTRA_DELETE_REASON);
			$this->getModelFromCache('XenForo_Model_DeletionLog')->logDeletion(
					$this->getContentType(), $this->get('entry_id'), $reason
			);
		}
		else if ($this->getExisting('message_state') == 'deleted')
		{
			$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog(
					$this->getContentType(), $this->get('entry_id')
			);
		}
	}	
	
	/**
	 * Upates the IP data.
	 */
	protected function _updateIpData()
	{
		$ipAddress = !empty($this->_extraData['ipAddress']) ? $this->_extraData['ipAddress'] : null;
		$ipId = XenForo_Model_Ip::log(
				$this->get('user_id'), $this->getContentType(), $this->get('entry_id'), 'insert', $ipAddress, $this->get('post_date')
		);
		$this->set('ip_id', $ipId, '', array('setAfterPreSave' => true));
	
		$this->_db->update('xfa_blog_entry', array(
				'ip_id' => $ipId
		), 'entry_id = ' .  $this->_db->quote($this->get('entry_id')));
	}	
	
	/**
	 * If an entry was deleted, we need to update the rest of the entries to change the "position"
	 */
	protected function _updateMessagePositionList()
	{
		if ($this->get('message_state') == 'visible' && $this->getExisting('message_state') != 'visible')
		{
			$this->_adjustPositionListForInsert();
		}
		else if ($this->get('message_state') != 'visible' && $this->getExisting('message_state') == 'visible')
		{
			$this->_adjustPositionListForRemoval();
		}
	}
	
	/**
	 * Adjust the position list surrounding this message, when this message
	 * has been put from a position that "counts" (removed or hidden).
	 */
	protected function _adjustPositionListForInsert()
	{
		$positionQuoted = $this->_db->quote($this->getExisting('position'));
		$postDateQuoted = $this->_db->quote($this->get('post_date'));
		$this->_db->query("
			UPDATE xfa_blog_entry
			SET position = position + 1
			WHERE user_id = ?
				AND (position > $positionQuoted
					OR (position = $positionQuoted AND post_date > $postDateQuoted)
				)
				AND entry_id <> ?
		", array ( $this->get('user_id'), $this->get('entry_id') ));
	}
	
	/**
	 * Adjust the position list surrounding this message, when this message
	 * has been removed from a position that "counts" (removed or hidden).
	 */
	protected function _adjustPositionListForRemoval()
	{
		$positionQuoted = $this->_db->quote($this->getExisting('position'));
		$postDateQuoted = $this->_db->quote($this->get('post_date'));
		$this->_db->query("
			UPDATE xfa_blog_entry
			SET position = IF(position > 0, position - 1, 0)
			WHERE user_id = ?
				AND position >= $positionQuoted
				AND entry_id <> ?
		", array ( $this->get('user_id'), $this->get('entry_id') ));
	}
	
	/**
	 * Publish to the news feed
	 */
	protected function _publishToNewsFeed()
	{
		$this->_getNewsFeedModel()->publish(
				$this->get('user_id'),
				$this->getExtraData('username'),
				$this->getContentType(),
				$this->get('entry_id'),
				($this->isUpdate() ? 'update' : 'insert')
		);
	}
	
	/**
	 * This method will set and validate a list of members. If at least one does not exist, it will add that to the error array
	 * @param string $memberList
	 */
	public function setAllowMembers($memberList)
	{
		if (empty($memberList))
		{
			$this->set('allow_members_ids', null);
			return;
		}
		
		// memberList is comma-separated usernames
		$members = preg_split("/[ ]*[,][ ]*/s", $memberList);
		/* @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');
		
		$fetchOptions = array();
		$invalidNames = array();
		$users = $userModel->getUsersByNames($members, $fetchOptions, $invalidNames);
		
		$userIds = array();
		foreach ($users as $user)
		{
			$userIds[] = $user['user_id'];
		}
		
		if (!empty($invalidNames))
		{
			$msg = new XenForo_Phrase('xfa_blogs_following_users_not_found', array('users' => implode(',', $invalidNames)));
			$this->error($msg, 'allow_members_names');
		}
		else
		{
			$this->set('allow_members_ids', implode(',', $userIds));
		}
	}
	
	/**
	 * Update the comments count. This method is called when we save a comment.
	 * @param XfAddOns_Blogs_DataWriter_Comment $dwPost	Datawriter for the post
	 */
	public function updateCountersAfterCommentSave(XfAddOns_Blogs_DataWriter_Comment $dwComment)
	{
		// update the counter
		if ($dwComment->get('message_state') == 'visible' && $dwComment->getExisting('message_state') != 'visible')
		{
			$this->set('reply_count', $this->get('reply_count') + 1);
		}
		else if ($dwComment->getExisting('message_state') == 'visible' && $dwComment->get('message_state') != 'visible')
		{
			$this->set('reply_count', $this->get('reply_count') - 1);
		}
		$this->_update();
	}
	
	/**
	 * This is first called during preSave and can be called in any of the other methods. It initializes the thread data writer.
	 * We need to at least have the thread_id at this point
	 * @return XfAddOns_Blogs_DataWriter_Blog
	 */
	protected function getBlogDw()
	{
		if ($this->blogDw)
		{
			return $this->blogDw;
		}
	
		$userId = $this->get('user_id');
		if (!$userId)
		{
			return null;
		}
	
		$this->blogDw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Blog');
		$this->blogDw->setExistingData($userId);
		return $this->blogDw;
	}
	
	/**
	 * Returns the information for the entry, locking the row for an update
	 * @return array
	 */
	public function getEntryForUpdate($entryId)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchRow("SELECT * FROM xfa_blog_entry WHERE entry_id = ? FOR UPDATE", $entryId);
	}	
	
	
	

}