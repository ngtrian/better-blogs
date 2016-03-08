<?php

class XfAddOns_Blogs_ControllerPublic_Watched extends XfAddOns_Blogs_ControllerPublic_Abstract
{
	
	
	/**
	 * List of all new watched content.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionBlogs()
	{
		$visitor = XenForo_Visitor::getInstance();
		/* @var $blogWatchModel XfAddOns_Blogs_Model_BlogWatch */
		$blogWatchModel = $this->getModelFromCache('XfAddOns_Blogs_Model_BlogWatch');
		$blogIds = $blogWatchModel->getUnreadBlogs($visitor['user_id']);
		
		/* @var $blogModel XfAddOns_Blogs_Model_Blog */
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Blog::JOIN_LAST_ENTRY;
		$blogModel = $this->getModelFromCache('XfAddOns_Blogs_Model_Blog');
		$blogs = $blogModel->getBlogsByIds($blogIds, $fetchOptions);
		$blogModel->prepareBlogs($blogs);
		
		$viewParams = array(
			'blogs' => $blogs
		);
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_watch_blogs', $viewParams);
	}
	
	/**
	 * List of all new watched content.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionBlogsAll()
	{
		$visitor = XenForo_Visitor::getInstance();
		/* @var $blogWatchModel XfAddOns_Blogs_Model_BlogWatch */
		$blogWatchModel = $this->getModelFromCache('XfAddOns_Blogs_Model_BlogWatch');
		$blogIds = $blogWatchModel->getWatchedBlogs($visitor['user_id']);

		// page parameters
		$options = XenForo_Application::getOptions();
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$blogsPerPage = $options->xfa_blogs_entriesPerPage;

		// retrieve fetch options
		$fetchOptions = array(
			'join' => XfAddOns_Blogs_Model_Blog::JOIN_LAST_ENTRY,
			'limit' => (($page - 1) * $blogsPerPage) . ',' . $blogsPerPage,
			'orderBy' => 'last_entry DESC'
		);
		/* @var $blogModel XfAddOns_Blogs_Model_Blog */
		$blogModel = $this->getModelFromCache('XfAddOns_Blogs_Model_Blog');
		$blogs = $blogModel->getBlogsByIds($blogIds, $fetchOptions);
		$blogModel->prepareBlogs($blogs);
		
		$viewParams = array(
			'blogs' => $blogs,
			'page' => $page,
			'blogsPerPage' => $blogsPerPage,
			'totalBlogs' => $blogWatchModel->getTotalWatchForUser($visitor['user_id'])
		);
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_watch_blogs_all', $viewParams);
	}	
	
	
	/**
	 * Update the subscription data
	 */
	public function actionBlogsUpdate()
	{
		$do = $this->_input->filterSingle('do', XenForo_Input::STRING);
		$blogs = $this->_input->filterSingle('blogs', XenForo_Input::ARRAY_SIMPLE);
		$blogs = array_map('intval', $blogs);
		
		/* @var $blogWatchModel XfAddOns_Blogs_Model_BlogWatch */
		$blogWatchModel = $this->getModelFromCache('XfAddOns_Blogs_Model_BlogWatch');
		
		$visitorUserId = XenForo_Visitor::getUserId();
		if ($do == 'stop')
		{
			foreach ($blogs as $blogId)
			{
				$watch = $blogWatchModel->getWatch($visitorUserId, $blogId);
				if (empty($watch))
				{
					continue;
				}
				
				$dw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_BlogWatch');
				$dw->setExistingData($watch, true);
				$dw->delete();
			}
		}
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blog-watched/blogs/all')
		);
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
			$output[$key] = new XenForo_Phrase('xfa_blogs_viewing_watched_blogs');
		}
		return $output;
	}	
	
	
	
}