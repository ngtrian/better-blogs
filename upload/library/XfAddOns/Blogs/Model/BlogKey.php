<?php

/**
 * Helper class 
 */
class XfAddOns_Blogs_Model_BlogKey extends XenForo_Model
{
	
	/**
	 * Transform a username to a blog key. This will also validate that the blog key is not in use (in which case, it will
	 * generate the next availabel key)
	 * 
	 * @param array $user	The user information that we will use to compute the blog key
	 */
	public function getBlogKey(array $user)
	{
		static $blogKeyMap;
		if (!$blogKeyMap)
		{
			$blogKeyMap = self::getBlogKeyMap();
		}
		
		$searchArray = array('á', 'é', 'í', 'ó', 'ú'); 
		$replaceArray = array('a', 'e', 'i', 'o', 'u');
		
		$key = $user['username'];		// comes from the database as utf-8
		$key = strtolower($key);
		$key = str_replace($searchArray, $replaceArray, $key);
		$key = preg_replace("/[ _]/", "-", $key);		// spaces and dashes become dashes
		$key = preg_replace("/[^a-z0-9-]/isU", "", $key);	// remove strange and weird characters
		$key = preg_replace("/-[-]+/", "-", $key);		// two consecutive dashes replaced for only one of them
		$key = preg_replace("/^-/", "", $key);		// do not start with a dash
		$key = preg_replace("/-$/", "", $key);		// do not end with a dash either
		
		// there's a chance that we ended with a conflicting user, in which case ... the unlucky guy will get a horrible subdomain
		$i = 1;
		$originalKey = $key;
		while (true)
		{
			if (!in_array($key, $blogKeyMap))
			{
				break;		// awesome, we are out of here
			}
			if (isset($blogKeyMap[$user['user_id']]) && $blogKeyMap[$user['user_id']] == $key)
			{
				break;		// the key existed, but it was us, so it is ok to keep it
			}
			$key = $originalKey . '-' . $i++;
		}

		$blogKeyMap[$user['user_id']] = $key;
		return $key;
	}
	
	/**
	 * Returns an array with all the blog keys configured in the database
	 * @return array
	 */
	protected function getBlogKeyMap()
	{
		return $this->fetchAllKeyed("SELECT user_id, blog_key FROM xfa_blog", 'user_id');
	}
	
}