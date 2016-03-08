<?php

class XfAddOns_Blogs_DataWriter_Comment extends XenForo_DataWriter
{
	
	/**
	 * Option that controls whether data in the blog entry should be updated,
	 * including reply counts and last comment info. Defaults to true.
	 * @var boolean
	 */
	const OPTION_UPDATE_BLOG_ENTRY = 'updateBlogEntry';
	
	/**
	 * Option that controls if we should store the ip address
	 * @var boolean
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
	 * Processes users that are tagged in a post
	 * @var array
	 */
	protected $_taggedUsers = array();	
	
	/**
	 * @var XfAddOns_Blogs_DataWriter_Entry
	 */
	private $entryDw;
	
	/**
	 * Return the list of fields in the xfa_blog_comment
	 * These are all the fields that the datawriter will attempt to update
	 * @return array
	 */
	protected function _getFields()
	{
		$fields = array();
		$fields['xfa_blog_comment']['comment_id'] = array(
			'type' => self::TYPE_UINT,
			'autoIncrement' => true
		);
		$fields['xfa_blog_comment']['entry_id'] = array(
			'type' => self::TYPE_UINT,
			'required' => true,
			'min' => 1
		);
		$fields['xfa_blog_comment']['user_id'] = array(
			'type' => self::TYPE_UINT,
			'required' => true
		);
		$fields['xfa_blog_comment']['post_date'] = array(
			'type' => self::TYPE_UINT,
			'required' => true,
			'default' => XenForo_Application::$time
		);
		$fields['xfa_blog_comment']['message'] = array(
			'type' => self::TYPE_STRING,
			'required' => true,
			'requiredError' => 'xfa_blogs_comment_message_required'
		);
		$fields['xfa_blog_comment']['message_state'] = array(
			'type' => self::TYPE_STRING,
			'required' => true,
			'allowedValues' => array( 'visible', 'deleted' ),
			'default' => 'visible'
		);
		$fields['xfa_blog_comment']['ip_id'] = array(
			'type' => self::TYPE_UINT,
			'required' => true,
			'default' => 0
		);	
		$fields['xfa_blog_comment']['position'] = array(
			'type' => self::TYPE_INT,		// this one we allow to go to -1 since we can delete the first post
			'required' => true,
			'default' => 0
		);
		$fields['xfa_blog_comment']['likes'] = array(
			'type' => self::TYPE_UINT_FORCED,
			'required' => true,
			'default' => 0
		);
		$fields['xfa_blog_comment']['like_users'] = array(
			'type' => self::TYPE_SERIALIZED,
			'required' => true,
			'default' => 'a:0:{}'
		);
		$fields['xfa_blog_comment']['last_edit_date'] = array(
			'type' => self::TYPE_UINT,
			'default' => 0
		);
		$fields['xfa_blog_comment']['last_edit_user_id'] = array(
			'type' => self::TYPE_UINT,
			'default' => 0
		);
		$fields['xfa_blog_comment']['edit_count'] = array(
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
			self::OPTION_UPDATE_BLOG_ENTRY => true,
			self::OPTION_UPDATE_IP_ADDRESS => true,
			self::OPTION_UPDATE_POSITION => true,
			self::OPTION_ADD_ALERTS => true
		);
	}
	
	/**
	 * Existing data is needed for the updates. This query will fetch whatever the comment
	 * currently has in the database
	 *
	 * @param array $data	An array of the data currently configured in the data writer
	 * @return an array with the existing data, all tables included
	 */
	protected function _getExistingData($key)
	{
		if ($key)
		{
			$db = XenForo_Application::getDb();
			$data = $db->fetchRow("SELECT * FROM xfa_blog_comment WHERE comment_id = ?", array( $key ));
			return array( 'xfa_blog_comment' => $data );
		}
		return null;
	}
	
	/**
	 * Returns the query part used in the where condition for updating the table. This must match the primary key
	 *
	 * @param string $tableName		We ignore this, the data writer only supports one table
	 * @return						 the where part for updating the table
	 */
	protected function _getUpdateCondition($tableName)
	{
		return ' comment_id = ' . $this->_db->quote($this->getExisting('comment_id'));
	}
	
	/**
	 * Content type is blog_comment
	 * @return string
	 */
	protected function getContentType()
	{
		return 'xfa_blog_comment';
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
	 * Set the position of the comment within the entry
	 */
	protected function _setPosition()
	{
		if (!$this->isInsert() && !$this->isChanged('message_state'))
		{
			return;
		}
	
		$entryDw = $this->getEntryDw();
		if (!$entryDw)
		{
			return;
		}
		
		if ($this->isInsert())
		{
			$entry = $entryDw->getEntryForUpdate($this->get('entry_id'));
				
			$commentsCount = $entry && isset($entry['reply_count']) ?
				$entry['reply_count'] : $entryDw->get('reply_count');
	
			$position = $this->get('message_state') == 'visible' ?  $commentsCount + 1 : $commentsCount;
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
		
		// update meta-information into the entry table
		if ($this->getOption(self::OPTION_UPDATE_BLOG_ENTRY))
		{
 			$entryDw = $this->getEntryDw();
 			if ($entryDw && $entryDw->isUpdate())
 			{
 				$entryDw->updateCountersAfterCommentSave($this);
 				
 				/* @var $blogDw XfAddOns_Blogs_DataWriter_Blog */
 				$blogDw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Blog');
 				$blogDw->setExistingData($entryDw->get('user_id'));
 				$blogDw->updateCountersAfterCommentSave($this);
 			}
		}
		
		// send alerts and publish the news feed info
		if ($this->getOption(self::OPTION_ADD_ALERTS) && $this->isInsert())
		{
 			$this->_publishToNewsFeed();
 			
 			// author is always notified of comments in his entries
 			$this->_publishAlertsToEntryAuthor();
 			
			/* @var $watchModel XfAddOns_Blogs_Model_BlogEntryWatch */
			$watchModel = XenForo_Model::create('XfAddOns_Blogs_Model_BlogEntryWatch');
			$watchModel->notifySubscribedUsers($this->getMergedData('xfa_blog_comment'), $this->getExtraData('entry')); 			
		}
		
		// update the position counters if needed
		if ($this->isUpdate() && $this->isChanged('message_state'))
		{
			$this->_updateMessagePositionList();
		}
		
		// add to the edit history log
		if ($this->isUpdate() && $this->isChanged('edit_count'))
		{
			$this->_insertEditHistory();
		}		
	}
	
	/**
	 * Inserts a record for the edit history.
	 */
	protected function _insertEditHistory()
	{
		$historyDw = XenForo_DataWriter::create('XenForo_DataWriter_EditHistory', XenForo_DataWriter::ERROR_SILENT);
		$historyDw->bulkSet(array(
			'content_type' => $this->getContentType(),
			'content_id' => $this->get('comment_id'),
			'edit_user_id' => XenForo_Visitor::getUserId(),
			'old_text' => $this->getExisting('message')
		));
		$historyDw->save();
	}	
	
	/**
	 * Check if the user was tagged, and alert the user
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
			/* @var $entryModel XfAddOns_Blogs_Model_Comment */
			$commentModel = XenForo_Model::create('XfAddOns_Blogs_Model_Comment');
			$comment = $this->getMergedData();
			$commentModel->alertTaggedMembers($comment, $this->_taggedUsers);
		}
	}	
	
	/**
	 * The comments can be hard deleted. When that happens, we need to update the internal position counter
	 */
	protected function _postDelete()
	{
		// the deletion log is no longer relevant at this point
		$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog($this->getContentType(), $this->get('comment_id'));
		
		// remove alerts and news feed
		$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts($this->getContentType(), $this->get('comment_id'));
		$this->_getNewsFeedModel()->delete($this->getContentType(), $this->get('comment_id'));
		
		// adjust the position of messages around
		$this->_adjustPositionListForRemoval();
		
		// update the totals for the entry
		if ($this->getOption(self::OPTION_UPDATE_BLOG_ENTRY))
		{
			$dwEntry = $this->getEntryDw();
			if ($dwEntry)
			{
				$dwEntry->updateCountersAfterCommentSave($this);
				
				/* @var $blogDw XfAddOns_Blogs_DataWriter_Blog */
				$blogDw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Blog');
				$blogDw->setExistingData($dwEntry->get('user_id'));
				$blogDw->updateCountersAfterCommentSave($this);				
			}			
		}		
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
					$this->getContentType(), $this->get('comment_id'), $reason
			);
		}
		else if ($this->getExisting('message_state') == 'deleted')
		{
			$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog(
					$this->getContentType(), $this->get('comment_id')
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
				$this->get('user_id'), $this->getContentType(), $this->get('comment_id'), 'insert', $ipAddress, $this->get('post_date')
		);
		$this->set('ip_id', $ipId, '', array('setAfterPreSave' => true));
	
		$this->_db->update('xfa_blog_comment', array(
				'ip_id' => $ipId
		), 'comment_id = ' .  $this->_db->quote($this->get('comment_id')));
	}
	
	/**
	 * If a comment was deleted, we need to update the rest of the entries to change the "position"
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
			UPDATE xfa_blog_comment
			SET position = position + 1
			WHERE entry_id = ?
				AND (position > $positionQuoted
					OR (position = $positionQuoted AND post_date > $postDateQuoted)
				)
				AND comment_id <> ?
		", array ( $this->get('entry_id'), $this->get('comment_id') ));
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
			UPDATE xfa_blog_comment
			SET position = IF(position > 0, position - 1, 0)
			WHERE entry_id = ?
				AND position >= $positionQuoted
				AND comment_id <> ?
		", array ( $this->get('entry_id'), $this->get('comment_id') ));
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
				$this->get('comment_id'),
				($this->isUpdate() ? 'update' : 'insert')
		);
	}
	
	/**
	 * We always alert the author when somebody comments on his entries. We assume that the blog author
	 * wants to know when somebody made a comment
	 */
	protected function _publishAlertsToEntryAuthor()
	{
		$entry = $this->getExtraData('entry');
		if (!$entry || $entry['user_id'] == $this->get('user_id'))
		{
			return;
		}
		// we check for the "own" permission, but actually insert the alert as an "insert", since that makes more sense
		if (!XenForo_Model_Alert::userReceivesAlert(array('user_id' => $entry['user_id']), 'xfa_blog_comment', 'own'))
		{
			return;
		}
		
		// check if the user has an unread comment
		$comments = $this->_db->fetchOne("
			SELECT count(*) unreadComments
			FROM xf_user_alert
			INNER JOIN xfa_blog_comment ON xf_user_alert.content_id = xfa_blog_comment.comment_id AND content_type = '" . $this->getContentType() . "'
			WHERE
				xf_user_alert.alerted_user_id = ? AND
				xfa_blog_comment.entry_id = ? AND view_date = 0
			",
			array( $entry['user_id'], $this->get('entry_id') ));
		if ($comments > 0)
		{
			return;
		}
		
		XenForo_Model_Alert::alert(
			$entry['user_id'],
			$this->get('user_id'),
			$this->getExtraData('username'),
			$this->getContentType(),
			$this->get('comment_id'),
			'insert'
		);
	}

	/**
	 * This is first called during preSave and can be called in any of the other methods. It initializes the entry data writer.
	 * We need to at least have the entry_id at this point
	 * @return XfAddOns_Blogs_DataWriter_Entry
	 */
	protected function getEntryDw()
	{
		if ($this->entryDw)
		{
			return $this->entryDw;
		}

		$entryId = $this->get('entry_id');
		if (!$entryId)
		{
			return null;
		}

		$this->entryDw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Entry');
		$this->entryDw->setExistingData($entryId);
		return $this->entryDw;
	}
	
	
}