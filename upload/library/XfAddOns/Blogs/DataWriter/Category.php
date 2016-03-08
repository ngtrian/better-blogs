<?php

/**
 * Datawriter for the table
 */
class XfAddOns_Blogs_DataWriter_Category extends XenForo_DataWriter
{
	
	/**
	 * Return the list of fields in the table
	 * These are all the fields that the datawriter will attempt to update
	 * @return array
	 */
	protected function _getFields()
	{
		$fields = array();
		$fields['xfa_blog_category']['category_id'] = array(
			'type' => self::TYPE_UINT,
			'autoIncrement' => true
		);
		$fields['xfa_blog_category']['user_id'] = array(
			'type' => self::TYPE_UINT,
			'required' => true,
			'default' => 0
		);
		$fields['xfa_blog_category']['category_name'] = array(
			'type' => self::TYPE_STRING,
			'required' => true,
			'requiredError' => new XenForo_Phrase('xfa_blogs_please_write_category_name'),
			'maxLength' => 50
		);
		$fields['xfa_blog_category']['entry_count'] = array(
			'type' => self::TYPE_UINT,
			'default' => 0
		);
		$fields['xfa_blog_category']['parent_id'] = array(
			'type' => self::TYPE_UINT
		);
		$fields['xfa_blog_category']['display_order'] = array(
			'type' => self::TYPE_UINT,
			'default' => 0				
		);
		$fields['xfa_blog_category']['is_active'] = array(
			'type' => self::TYPE_UINT,
			'default' => 1
		);			
		
		return $fields;
	}
	
	/**
	 * Existing data is needed for the updates. This query will fetch whatever the category
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
			$data = $db->fetchRow("SELECT * FROM xfa_blog_category WHERE category_id = ?", array( $key ));
			return array( 'xfa_blog_category' => $data );
		}
		return null;
	}
	
	/**
	 * After deleting a category, update any children categories to reflect the correct parent
	 * @see XenForo_DataWriter::_postDelete()
	 */	
	protected function _postDelete()
	{
		// take care of orphan categories		
		$parentId = $this->get('parent_id');
		$where = 'parent_id=' . $this->get('category_id');
		$this->_db->update('xfa_blog_category', array( 'parent_id' => $parentId ), $where);
		
		// if there were any entries associated, delete them
		$this->_db->delete('xfa_blog_entry_category', 'category_id=' . $this->get('category_id'));
		
		if ($this->get('user_id') == 0)
		{
			// expire the caches
			/* @var $categoryModel XfAddOns_Blogs_Model_Category */
			$categoryModel = $this->getModelFromCache('XfAddOns_Blogs_Model_Category');
			$categoryModel->expireGlobalCategoriesInCache();
		}
	}
	
	/**
	 * If a category was inserted or deleted, we will wipe the cache
	 * @see XenForo_DataWriter::_postSave()
	 */
	protected function _postSave()
	{
		if ($this->get('user_id') == 0)
		{
			// expire the caches
			/* @var $categoryModel XfAddOns_Blogs_Model_Category */
			$categoryModel = $this->getModelFromCache('XfAddOns_Blogs_Model_Category');
			$categoryModel->expireGlobalCategoriesInCache();			
		}
	}
	
	/**
	 * Returns the query part used in the where condition for updating the table. This must match the primary key
	 *
	 * @param string $tableName		We ignore this, the data writer only supports one table
	 * @return						 the where part for updating the table
	 */
	protected function _getUpdateCondition($tableName)
	{
		return ' category_id = ' . $this->_db->quote($this->getExisting('category_id'));
	}
	
}