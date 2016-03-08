<?php

class XfAddOns_Blogs_DataWriter_Css extends XenForo_DataWriter
{

	/**
	 * Return the list of fields in the table
	 * These are all the fields that the datawriter will attempt to update or insert
	 * @return array
	 */
	protected function _getFields()
	{
		$fields = array();
		$fields['xfa_blog_css']['css_id'] = array(
			'type' => self::TYPE_UINT_FORCED,
			'autoIncrement' => true
		);
		$fields['xfa_blog_css']['user_id'] = array(
			'type' => self::TYPE_UINT,
			'required' => true
		);
		$fields['xfa_blog_css']['className'] = array(
			'type' => self::TYPE_STRING,
			'required' => true,
			'maxLength' => 200
		);		
		$fields['xfa_blog_css']['varname'] = array(
			'type' => self::TYPE_STRING,
			'required' => true,
			'maxLength' => 50
		);
		$fields['xfa_blog_css']['value'] = array(
			'type' => self::TYPE_STRING,
			'default' => ''
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
			$data = $db->fetchRow("SELECT * FROM xfa_blog_css WHERE css_id = ?", array( $key ));
			return array( 'xfa_blog_css' => $data );
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
		return ' css_id = ' . $this->_db->quote($this->getExisting('css_id'));
	}	
	
}