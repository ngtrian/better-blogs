<?php

class XfAddOns_Blogs_DataWriter_Blog extends XenForo_DataWriter
{

	/**
	 * Return the list of fields in the xfa_blog table
	 * These are all the fields that the datawriter will attempt to update or insert
	 * @return array
	 */
	protected function _getFields()
	{
		$fields = array();
		
		// blog fields
		$fields['xfa_blog']['user_id'] = array(
			'type' => self::TYPE_UINT_FORCED,
			'required' => true
		);
		$fields['xfa_blog']['entry_count'] = array(
			'type' => self::TYPE_UINT,
			'required' => true,
			'default' => 0
		);
		$fields['xfa_blog']['comment_count'] = array(
			'type' => self::TYPE_UINT,
			'required' => true,
			'default' => 0
		);	
		$fields['xfa_blog']['view_count'] = array(
			'type' => self::TYPE_UINT,
			'required' => true,
			'default' => 0
		);			
		$fields['xfa_blog']['create_date'] = array(
			'type' => self::TYPE_UINT,
			'required' => true,
			'default' => XenForo_Application::$time
		);
		$fields['xfa_blog']['last_entry'] = array(
			'type' => self::TYPE_UINT,
			'default' => 0
		);
		$fields['xfa_blog']['last_entry_id'] = array(
			'type' => self::TYPE_UINT
		);
		$fields['xfa_blog']['last_comment'] = array(
			'type' => self::TYPE_UINT,
			'default' => 0
		);
		$fields['xfa_blog']['last_comment_id'] = array(
			'type' => self::TYPE_UINT
		);		
		$fields['xfa_blog']['blog_title'] = array(
			'type' => self::TYPE_STRING,
			'default' => '',
			'maxLength' => 100
		);
		$fields['xfa_blog']['blog_key'] = array(
			'type' => self::TYPE_STRING,
			'default' => ''
		);
		$fields['xfa_blog']['description'] = array(
			'type' => self::TYPE_STRING,
			'default' => '',
			'maxLength' => 800
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
			$data = $db->fetchRow("SELECT * FROM xfa_blog WHERE user_id = ?", array( $key ));
			return array( 'xfa_blog' => $data );
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
		return ' user_id = ' . $this->_db->quote($this->getExisting('user_id'));
	}	
	
	/**
	 * Returns the information for the blog, locking the row for an update
	 * @return array
	 */
	public function getBlogForUpdate($userId)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchRow("SELECT * FROM xfa_blog WHERE user_id = ? FOR UPDATE", $userId);
	}
	
	/**
	 * Called after blog creation, we will finalize any other information that we need, like the blog key
	 * @see XenForo_DataWriter::_postSave()
	 */
	protected function _postSave()
	{
		parent::_postSave();
		if (!$this->isInsert())
		{
			return;
		}

		/* @var $userModel XenForo_Model_User */
		$userModel = XenForo_Model::create('XenForo_Model_User');
		$user = $userModel->getUserById($this->get('user_id'));
		
		if ($user)
		{
			/* @var $keyModel XfAddOns_Blogs_Model_BlogKey */
			$keyModel = XenForo_Model::create('XfAddOns_Blogs_Model_BlogKey');
			$blogKey = $keyModel->getBlogKey($user);
			$this->_db->update('xfa_blog', array('blog_key' => $blogKey), 'user_id = ' . $this->get('user_id'));
		}
	}
	
	/**
	 * Update the message count. This method is called when we save an entry.
	 * @param XfAddOns_Blogs_DataWriter_Entry $dwEntry	Datawriter for the entry
	 */
	public function updateCountersAfterEntrySave(XfAddOns_Blogs_DataWriter_Entry $dwEntry)
	{
		// update the counter
		if ($dwEntry->get('message_state') == 'visible' && $dwEntry->getExisting('message_state') != 'visible')
		{
			$this->set('entry_count', $this->get('entry_count') + 1);
		}
		else if ($dwEntry->getExisting('message_state') == 'visible' && $dwEntry->get('message_state') != 'visible')
		{
			$this->set('entry_count', $this->get('entry_count') - 1);
		}
		
		// update all the summaries
		if ($dwEntry->get('message_state') == 'visible')
		{
			$this->set('last_entry', max($dwEntry->get('post_date'), $this->get('last_entry')));
			$this->set('last_entry_id', $dwEntry->get('entry_id'));
			if ($this->get('entry_count') <= 1)
			{
				$this->set('create_date', XenForo_Application::$time);
			}			
		}
		
		// do the update in the main table
		$this->_update();
	}
	
	/**
	 * Update the comments count. This method is called when we save a comment
	 * @param XfAddOns_Blogs_DataWriter_Comment $dwComment
	 */
	public function updateCountersAfterCommentSave(XfAddOns_Blogs_DataWriter_Comment $dwComment)
	{
		// update the counter
		if ($dwComment->get('message_state') == 'visible' && $dwComment->getExisting('message_state') != 'visible')
		{
			$this->set('comment_count', $this->get('comment_count') + 1);
		}
		else if ($dwComment->getExisting('message_state') == 'visible' && $dwComment->get('message_state') != 'visible')
		{
			$this->set('comment_count', $this->get('comment_count') - 1);
		}
		
		// update all the summaries
		if ($dwComment->get('message_state') == 'visible')
		{
			$this->set('last_comment', max($dwComment->get('post_date'), $this->get('last_comment')));
			$this->set('last_comment_id', $dwComment->get('comment_id'));
		}
		
		// do the update in the main table
		$this->_update();		
	}
	
}