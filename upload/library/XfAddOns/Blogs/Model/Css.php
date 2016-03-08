<?php

/**
 * Model for manipulating the css information
 */
class XfAddOns_Blogs_Model_Css
{

	/**
	 * Return all the customized information (if any) for a particular user
	 * @param array $blog		A reference to the blog information
	 */
	public function getAllCss(array $blog)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchAll("
			SELECT * FROM xfa_blog_css WHERE user_id = ?
		", $blog['user_id']);
	}	
	
	/**
	 * Return all the customized information (if any) for a particular user
	 * @param array $blog		A reference to the blog information
	 */
	public function getCss(array $blog)
	{
		$db = XenForo_Application::getDb();
		$css = $db->fetchAll("
			SELECT * FROM xfa_blog_css WHERE user_id = ?
		", $blog['user_id']);
		
		return $this->groupByClassName($css);
	}

	/**
	 * Take all the variables and group them by selector, and by css variable name and property
	 * @param array $css		The css as returned from the database
	 * @return array		an array of cssName => variables
	 */
	protected function groupByClassName(array $css)
	{
		$ret = array();
		foreach ($css as $row)
		{
			if (!$row['value'])
			{
				continue;
			}
			if ($row['varname'] == 'background-image' && $row['value'] == 'http://')
			{
				continue;
			}				
			$ret[$row['className']][$row['varname']] = $row['value'];
		}
		return $ret;
	}
	
	/**
	 * Return all the css variables for a particular class name
	 */
	public function getCssForClassName(array $blog, $className)
	{
		$db = XenForo_Application::getDb();
		$css = $db->fetchAll("
			SELECT varname, value FROM xfa_blog_css WHERE user_id = ? AND className = ?
		", array($blog['user_id'], $className));
		
		return $this->groupByVarname($css);
	}
	
	/**
	 * Take all the variables and group them by varname. This method is useful when the results
	 * only contains one class
	 * @param array $css	The css as returned from the database
	 * @return array		an array of varname => variable
	 */
	protected function groupByVarname(array $css)
	{
		$ret = array();
		foreach ($css as $row)
		{
			if (!$row['value'])
			{
				continue;
			}
			if ($row['varname'] == 'background-image' && $row['value'] == 'http://')
			{
				continue;
			}			
			$ret[$row['varname']] = $row['value'];	
		}
		return $ret;
	}	
	
	/**
	 * This method does a REPLACE on the table, we use this instead of the datawriter to work around checking
	 * if the value already exists
	 */
	public function insertOrReplaceCss($userId, $className, $varname, $value)
	{
		$db = XenForo_Application::getDb();
		$db->query("
			INSERT INTO xfa_blog_css
				(user_id, className, varname, value)
			VALUES
				(?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE value = VALUES(value)				
		", array($userId, $className, $varname, $value));		
	}
	
	/**
	 * This method will reset every customization setting that the user has configured for their blog
	 * @param array $blog	The blog information
	 */
	public function resetCustomization(array $blog)
	{
		$db = XenForo_Application::getDb();
		$db->delete('xfa_blog_css', 'user_id=' . $db->quote($blog['user_id']));
	}
	
}