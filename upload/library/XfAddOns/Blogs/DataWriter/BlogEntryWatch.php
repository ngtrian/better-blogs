<?php

/**
 * Datawriter for the table
 */
class XfAddOns_Blogs_DataWriter_BlogEntryWatch extends XenForo_DataWriter
{
	
	/**
	 * Return the list of fields in the table
	 * These are all the fields that the datawriter will attempt to update
	 * @return array
	 */
	protected function _getFields()
	{
		$fields = array();
		$fields['xfa_blog_entry_watch']['watch_id'] = array(
			'type' => self::TYPE_UINT,
			'autoIncrement' => true
		);
		$fields['xfa_blog_entry_watch']['user_id'] = array(
			'type' => self::TYPE_UINT,
			'required' => true
		);
		$fields['xfa_blog_entry_watch']['entry_id'] = array(
			'type' => self::TYPE_UINT,
			'required' => true
		);
		return $fields;
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
			$data = $db->fetchRow("SELECT * FROM xfa_blog_entry_watch WHERE watch_id = ?", array( $key ));
			return array( 'xfa_blog_entry_watch' => $data );
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
		return ' watch_id = ' . $this->_db->quote($this->getExisting('watch_id'));
	}
	
}