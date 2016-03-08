<?php

class XfAddOns_Blogs_DataWriter_EntryScheduled extends XenForo_DataWriter
{
	
	/**
	 * Key for new categories that are going to be set
	 */
	const EXTRA_DATA_NEW_CATEGORIES = 'new_categories';	
	
	/**
	 * Extra data for the hash used to upload the attachments
	 */
	const DATA_ATTACHMENT_HASH = 'attachment_hash';
	
	/**
	 * Return the list of fields in the xfa_blog_entry table
	 * These are all the fields that the datawriter will attempt to update or insert
	 * @return array
	 */
	protected function _getFields()
	{
		$fields = array();
		$fields['xfa_blog_entry_scheduled']['scheduled_entry_id'] = array(
			'type' => self::TYPE_UINT_FORCED,
			'autoIncrement' => true
			);
		$fields['xfa_blog_entry_scheduled']['user_id'] = array(
			'type' => self::TYPE_UINT,
			'required' => true
			);
		$fields['xfa_blog_entry_scheduled']['title'] = array(
			'type' => self::TYPE_STRING,
			'maxLength' => 250,				
			'required' => true,
			'requiredError' => 'xfa_blogs_entry_title_required'
			);
		$fields['xfa_blog_entry_scheduled']['post_date'] = array(
			'type' => self::TYPE_INT,
			'required' => true,
			'default' => XenForo_Application::$time
			);
		$fields['xfa_blog_entry_scheduled']['message'] = array(
			'type' => self::TYPE_STRING,
			'required' => true,
			'requiredError' => 'xfa_blogs_entry_message_required'
			);
		$fields['xfa_blog_entry_scheduled']['ip_id'] = array(
			'type' => self::TYPE_UINT,
			'required' => true,
			'default' => 0
		);
		$fields['xfa_blog_entry_scheduled']['categories'] = array(
			'type' => self::TYPE_SERIALIZED,
			'required' => false
		);
		$fields['xfa_blog_entry_scheduled']['allow_comments'] = array(
			'type' => self::TYPE_UINT,
			'required' => true,				
			'default' => 1
		);
		$fields['xfa_blog_entry_scheduled']['allow_view_entry'] = array(
			'type' => self::TYPE_STRING,
			'required' => true,
			'default' => 'everyone',
			'allowedValues' => array('everyone','members','followed','none','list')
		);		
		$fields['xfa_blog_entry_scheduled']['allow_members_ids'] = array(
				'type' => self::TYPE_STRING,
				'required' => false,
		);		
		
		return $fields;
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
			$data = $db->fetchRow("SELECT * FROM xfa_blog_entry_scheduled WHERE scheduled_entry_id = ?", array( $key ));
			return array( 'xfa_blog_entry_scheduled' => $data );
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
		return ' scheduled_entry_id = ' . $this->_db->quote($this->getExisting('scheduled_entry_id'));
	}
	
	/**
	 * Content type is xfa_blog_entry
	 * @return string
	 */
	protected function getContentType()
	{
		return 'xfa_blog_entry_scheduled';
	}	
	
	/**
	 * On presave we move some of the extra information to the fields
	 * @see XenForo_DataWriter::_preSave()
	 */
	protected function _preSave()
	{
		parent::_preSave();
		
		// categories are saved as a serialized array within the scheduled entries
		$categories = $this->getExtraData(self::EXTRA_DATA_NEW_CATEGORIES);
		if (is_array($categories))
		{
			$this->set('categories', $categories);
		}
	}
	
	/**
	 * Override the post-save handler to cascade updates
	 */
	protected function _postSave()
	{
		parent::_postSave();
		
		if ($this->isInsert() && !$this->get('ip_id'))
		{
			$this->_updateIpData();
		}
		
		// associate attachments as necessary
		$this->_associateAttachments();

		$postDate = $this->get('post_date');
		if ($this->isInsert() || $this->isChanged('post_date'))
		{
			/* @var $deferred XenForo_Model_Deferred */
			$deferred = XenForo_Model::create('XenForo_Model_Deferred');
			$options = array();
			$taskKey = 'scheduled_blog_' . $this->get('scheduled_entry_id');
			$deferred->defer('XfAddOns_Blogs_Deferred_PublishScheduledEntries', $options, $taskKey, false, $postDate);
		}
		
		// update the meta-information in the blog table (counts, etc)
		$this->_db->query("UPDATE xfa_blog SET scheduled_entries = scheduled_entries + 1 WHERE user_id = ?", $this->get('user_id'));
	}
	
	/**
 	 * When the scheduled entry is published, we remove it. At this time, we can also remove any associated content
	 */
	protected function _postDelete()
	{
		// remove the scheduled entry record
		$this->_db->query("UPDATE xfa_blog SET scheduled_entries = scheduled_entries - 1 WHERE user_id = ?", $this->get('user_id'));
		
		// remove the attachments
		$this->getModelFromCache('XenForo_Model_Attachment')->deleteAttachmentsFromContentIds(
			$this->getContentType(),
			array($this->get('entry_id'))
		);
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
			'content_id' => $this->get('scheduled_entry_id'),
			'temp_hash' => '',
			'unassociated' => 0
		), 'temp_hash = ' . $this->_db->quote($attachmentHash));
	}	
	
	/**
	 * Upates the IP data.
	 */
	protected function _updateIpData()
	{
		$ipAddress = !empty($this->_extraData['ipAddress']) ? $this->_extraData['ipAddress'] : null;
		$ipId = XenForo_Model_Ip::log(
				$this->get('user_id'), $this->getContentType(), $this->get('scheduled_entry_id'), 'insert', $ipAddress, $this->get('post_date')
		);
		$this->set('ip_id', $ipId, '', array('setAfterPreSave' => true));
	
		$this->_db->update('xfa_blog_entry_scheduled', array(
				'ip_id' => $ipId
		), 'scheduled_entry_id = ' .  $this->_db->quote($this->get('scheduled_entry_id')));
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
	
	

}