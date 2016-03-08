<?php

/**
 * Overrides the model to add the "B" icons
 */
class XfAddOns_Blogs_Override_Model_ProfilePost extends XFCP_XfAddOns_Blogs_Override_Model_ProfilePost
{

	public function prepareProfilePostFetchOptions(array $fetchOptions)
	{
		$joinOptions = parent::prepareProfilePostFetchOptions($fetchOptions);

		// we only join for visitors, not guests
		$visitorUserId = XenForo_Visitor::getUserId();
		if (!$visitorUserId)
		{
			return $joinOptions;
		}
		
		$joinOptions['selectFields'] .= "
				,xfa_blog_read.blog_read_date
				,xfa_blog.last_entry
				,xfa_blog.blog_key blog_key
				";
		$joinOptions['joinTables'] .= "
				LEFT JOIN xfa_blog_read ON xfa_blog_read.blog_user_id = profile_post.user_id AND xfa_blog_read.user_id = " . $visitorUserId . "
				LEFT JOIN xfa_blog ON xfa_blog.user_id = profile_post.user_id
				";
		return $joinOptions;		
	}
	
}