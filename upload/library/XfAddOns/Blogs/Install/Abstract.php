<?php

class XfAddOns_Blogs_Install_Abstract
{
	
	
	/**
	 * @return Zend_Db_Adapter_Abstract
	 */
	protected function _getDb()
	{
		return XenForo_Application::getDb();
	}

	/**
	 * Execute a query and ignore if it causes an exception
	 */
	public function executeUpgradeQuery($sql, array $bind = array(), $logExceptions = false)
	{
		try
		{
			return $this->_getDb()->query($sql, $bind);
		}
		catch (Zend_Db_Exception $ex)
		{
			if ($logExceptions)
			{
				XenForo_Error::logException($ex, false);
			}
			return false;
		}
	}	
	
}