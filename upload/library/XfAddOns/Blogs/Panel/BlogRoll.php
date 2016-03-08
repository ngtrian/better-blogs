<?php

/**
 * This panel shows a list of all the friends for the user
 */
class XfAddOns_Blogs_Panel_BlogRoll
{

	/**
	 * Fetches and return the panel content. This will fetch the list of people that the blog author follows
	 * and display that as a list
	 */
	public function getPanelContent(array $blog)
	{
		$options = XenForo_Application::getOptions();
		
		/* @var $blogModel XfAddOns_Blogs_Model_Blog */
		$blogModel = XenForo_Model::create('XfAddOns_Blogs_Model_Blog');
		$friends = $blogModel->getWatchedBlogs($blog['user_id']);
		// $friends = $blogModel->getFriends($blog['user_id']);
		
		if (empty($friends))
		{
			return '';
		}
		if (count($friends) > $options->xfa_blogs_panel_blogRollLimit)
		{
			$friends = array_slice($friends, 0, $options->xfa_blogs_panel_blogRollLimit);
		}
		
		$template = new XenForo_Template_Public('xfa_blog_panel_blogroll', array(
			'unreadThreshold' => XenForo_Application::$time - 86400 * $options->xfa_blogs_unreadThreshold,
			'blog' => $blog,
			'friends' => $friends,
			'visitor' => XenForo_Visitor::getInstance()
			));		
		
		return $template;
	}
	
}
