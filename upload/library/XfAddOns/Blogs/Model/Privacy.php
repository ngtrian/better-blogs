<?php

/**
 * A quick way to localize all the privacy related functions 
 */
class XfAddOns_Blogs_Model_Privacy extends XenForo_Model
{
	
	/**
	 * This method will get a String that is a serialized list of ids from the database, and will apply the necessary logic
	 * to transform that list into a set of usernames. This method will do error handling and just return nul lif the string
	 * could not be unserialized
	 */
	public function getNamesForIds($ids)
	{
		if (empty($ids))
		{
			return null;
		}
		
		if (is_string($ids))
		{
			$ids = @unserialize($ids);
		}
		if (!is_array($ids) || empty($ids))
		{
			return null;
		}
		
		/* @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');
		$users = $userModel->getUsersByIds($ids);
		if (empty($users))
		{
			return null;
		}
		
		$usernames = array();
		foreach ($users as $user)
		{
			$usernames[] = $user['username'];
		}
		return implode(', ', $usernames);
	}
	
	
}