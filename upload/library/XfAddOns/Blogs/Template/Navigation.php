<?php

class XfAddOns_Blogs_Template_Navigation
{
	public static function listenNavigationTabs(array &$extraTabs, $selectedTabId)
	{
		$options = XenForo_Application::getOptions();
		
		if ($options->xfa_blogs_blogMode != 'skip_homepage')
		{
			self::addLinkToBlogsHomePage($extraTabs, $selectedTabId, 'middle');
		}
		else 
		{
			self::addLinkToMyBlog($extraTabs, $selectedTabId, 'middle');
		}
	}
	
	/**
	 * This will add to the navigation bar a link to the blog's home page
	 */
	private static function addLinkToBlogsHomePage(array &$extraTabs, $selectedTabId, $position)
	{
		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor->hasPermission('xfa_blogs', 'xfa_blogs_view'))
		{
			return;
		}
		
		/* @var $multiBlogModel XfAddOns_Blogs_Model_MultiBlog */
		$multiBlogModel = XenForo_Model::create('XfAddOns_Blogs_Model_MultiBlog');
		$homePage = $multiBlogModel->getBlogsHomePage();
		
		$extraTabs['xfa-blogs'] = array(
			'position' => $position,
			'title' => new XenForo_Phrase('xfa_blogs_blogs'),
			'selected' => ($selectedTabId == 'xfa-blogs'),		// selectedTabId is set with major section in the router
			'href' => $homePage,
			'linksTemplate' => 'xfa_blog_navigation_links',
			'blogsHomePage' => $homePage
			);		
	}
	
	/**
	 * This will add to the navigation bar a link to the user's blog
	 */
	private static function addLinkToMyBlog(array &$extraTabs, $selectedTabId, $position)
	{
		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor->get('user_id') > 0 || !$visitor->hasPermission('xfa_blogs', 'xfa_blogs_view'))
		{
			return;
		}
		
		/* @var $multiModel XfAddOns_Blogs_Model_MultiBlog  */
		$multiModel = XenForo_Model::create('XfAddOns_Blogs_Model_MultiBlog');
		$link = $multiModel->getBlogUrl(XenForo_Visitor::getInstance()->toArray());
		$homePage = $multiModel->getBlogsHomePage();
		
		$extraTabs['xfa-blogs'] = array(
			'position' => $position,
			'title' => new XenForo_Phrase('xfa_blogs_my_blog'),
			'selected' => ($selectedTabId == 'xfa-my-blog'),		// selectedTabId is set with major section in the router
			'href' => $link,
			'linksTemplate' => 'xfa_blog_navigation_links',
			'blogsHomePage' => $homePage,
			'css' => 'my-blog'	
			);
	}
	
	
}

