<?php

class XfAddOns_Blogs_ControllerPublic_BlogList extends XfAddOns_Blogs_ControllerPublic_Abstract
{
	
	/**
	 * Shows a list of all the blogs available in the system
	 */
	public function actionIndex()
	{
		$options = XenForo_Application::getOptions();
		
		// parse the input
		$order = $this->getOrder();
		
		// page parameters
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$blogsPerPage = $options->xfa_blogs_blist_total;

		// filters for privacy and fetch options
		$fetchOptions = array(
			'join' => XfAddOns_Blogs_Model_Blog::JOIN_VISITOR_FOLLOW + XfAddOns_Blogs_Model_Blog::JOIN_PRIVACY + XfAddOns_Blogs_Model_Blog::JOIN_LAST_ENTRY,
			'limit' => (($page - 1) * $blogsPerPage) . ',' . $blogsPerPage,
			'orderBy' => $this->getColumn($order),
			'whereOptions' => XfAddOns_Blogs_Model_Blog::WHERE_PRIVACY,
			'where' => "entry_count > 0 AND is_banned = 0 AND user_state = 'valid'"
		);
		
		// get total blogs in the DB
		/* @var $blogModel XfAddOns_Blogs_Model_Blog */
		$blogModel = XenForo_Model::create('XfAddOns_Blogs_Model_Blog');
		$totalBlogs = $blogModel->getTotalBlogs($fetchOptions);
		
		// do canonicalization
		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('xfa-blog-list', null, array('page' => $page))
		);
		$this->canonicalizePageNumber($page, $blogsPerPage, $totalBlogs + 1, 'xfa-blog-list', null);
		
		$blogs = $blogModel->getBlogList($fetchOptions);
		$blogModel->prepareBlogs($blogs);
		$blogModel->removePrivateBlogsForVisitor($blogs);
		
		// we also need to prepare the last entry of the blog for permissions
		foreach ($blogs as &$blog)
		{
			if (!isset($blog['lastEntry']))
			{
				continue;
			}
			
			$lastEntry = &$blog['lastEntry'];
			$this->entryModel->prepareEntry($lastEntry);
			if (!$lastEntry['perms']['canView'])
			{
				unset($blog['lastEntry']);
			}
		}
		
		// fetch the panels
		/* @var $panelModel XfAddOns_Blogs_Model_Panel */
		$panelModel = XenForo_Model::create('XfAddOns_Blogs_Model_Panel');
		$panels = $panelModel->getPanels();		
		
		// dispatch to the view
		$viewParams = array(
			'blogs' => $blogs,
			'page' => $page,
			'blogsPerPage' => $blogsPerPage,
			'totalBlogs' => $totalBlogs,
			'panels' => $panels,
			'order' => $order,
			'recentlyUpdatedSelected' => ($order == 'recently_updated'),
			'alphabeticalSelected' => ($order == 'alphabetical'),
			'mostEntriesSelected' => ($order == 'most_entries'),
		);
		
		$containerParams = array(
			'isBlogContainer' => true,
			'noVisitorPanel' => true,
			'containerTemplate' => 'PAGE_CONTAINER'
		);
		
		$templateName = $this->getResponseType() != 'json' ? 'xfa_blog_list' : 'xfa_blog_list_data';
		return $this->responseView('XenForo_ViewPublic_Base', $templateName, $viewParams, $containerParams);		
	}
	
	/**
	 * Retrieve and validate the order from the input
	 */
	private function getOrder()
	{
		$order = $this->_input->filterSingle('order', XenForo_Input::STRING);
		if ($order != 'recently_updated' && $order != 'alphabetical' && $order != 'most_entries')
		{
			$order = 'recently_updated';
		}
		return $order;
	}
	
	private function getColumn($orderFromRequest)
	{
		switch ($orderFromRequest)
		{
			case 'alphabetical':
				return 'username';
			case 'most_entries':
				return 'entry_count DESC';
			default:
				return 'last_entry DESC';
		}
	}
	
	/**
	 * Gets session activity details of activity records that are pointing to this controller.
	 * This must check the visiting user's permissions before returning item info.
	 * Return value may be:
	 * 		* false - means page is unknown
	 * 		* string/XenForo_Phrase - gives description for all, but no item details
	 * 		* array (keyed by activity keys) of strings/XenForo_Phrase objects - individual description, no item details
	 * 		* array (keyed by activity keys) of arrays. Sub-arrays keys: 0 = description, 1 = specific item title, 2 = specific item url.
	 *
	 * @param array $activities List of activity records
	 *
	 * @return mixed See above.
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		// generate the output
		$output = array();
		foreach ($activities AS $key => $activity)
		{
			$output[$key] = new XenForo_Phrase('xfa_blogs_viewing_all_blogs');
		}
		return $output;
	}	
	
	
}