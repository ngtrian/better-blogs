<?php

/**
 * Override the post model to load the blog read information
 */
class XfAddOns_Blogs_Override_Model_Post extends XFCP_XfAddOns_Blogs_Override_Model_Post
{

	/**
	 * When retrieving the posts, we will also cross join the blog table, since we need the information on whether the visitor
	 * have read a particular blog 
	 */
	public function preparePostJoinOptions(array $fetchOptions)
	{
		$joinOptions = parent::preparePostJoinOptions($fetchOptions);

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
				LEFT JOIN xfa_blog_read ON xfa_blog_read.blog_user_id = post.user_id AND xfa_blog_read.user_id = " . $visitorUserId . "
				LEFT JOIN xfa_blog ON xfa_blog.user_id = post.user_id
				";
		return $joinOptions;
	}
	
	protected function _copyPost(array $post, array $targetThread, array $forum)
	{
		unset($post['blog_read_date']);
		unset($post['last_entry']);
		unset($post['blog_key']);
		
		parent::_copyPost($post, $targetThread, $forum);
	}
	
	
	
}