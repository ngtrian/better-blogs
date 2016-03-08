<?php

class XfAddOns_Blogs_Override_Model_User extends XFCP_XfAddOns_Blogs_Override_Model_User
{
	
	/**
	 * We store the last visitor that we resolved with this model. A hacky workaround to avoid doing two queries
	 * while setting up the visitor information
	 * @var int
	 */
	static $lastVisitingUser = 0;
	
	/**
	 * Extends the default function to store the visiting user, so we can later reference it in the prepareUserFetchOptions
	 * method
	 * @param int $userId	The identifier for the user
	 */
	public function getVisitingUserById($userId)
	{
		self::$lastVisitingUser = $userId;
		return parent::getVisitingUserById($userId);
	}
	
	/**
	 * Do a cross-join to retrieve the blog information when retrieving the user information
	 * why?
	 * 		Required for the link on the member card
	 */
	public function prepareUserFetchOptions(array $fetchOptions)
	{
		$joinOptions = parent::prepareUserFetchOptions($fetchOptions);
		
		// join the data for the blog
		$joinOptions['selectFields'] .= "
				,xfa_blog.last_entry
				,xfa_blog.blog_key 
				,xfa_blog.entry_count
				";
		$joinOptions['joinTables'] .= "
				LEFT JOIN xfa_blog ON xfa_blog.user_id = user.user_id
				";
		
		// we only join reads for registered
		if (self::$lastVisitingUser > 0)
		{
			$joinOptions['selectFields'] .= "
					,xfa_blog_read.blog_read_date
					";
			$joinOptions['joinTables'] .= "
					LEFT JOIN xfa_blog_read ON xfa_blog_read.blog_user_id = user.user_id AND xfa_blog_read.user_id = " . self::$lastVisitingUser . "
					";
		}		
		return $joinOptions;
	}	
	
	
}