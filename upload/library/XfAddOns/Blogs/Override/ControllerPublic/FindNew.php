<?php

class XfAddOns_Blogs_Override_ControllerPublic_FindNew extends XFCP_XfAddOns_Blogs_Override_ControllerPublic_FindNew
{
	
	protected function _getWrapperTabs()
	{
		$tabs = parent::_getWrapperTabs();
		$tabs['xfa_blog_entries_tab'] = array (
			'href' => XenForo_Link::buildPublicLink('find-new/blog-entries'),
			'title' => new XenForo_Phrase('xfa_blogs_entries')
		);
		return $tabs;
	}

	/**
	 * Fetches the latest blog entries that have been posted since the user last visited
	 */	
	public function actionBlogEntries()
	{
		$this->_routeMatch->setSections('xfa-blogs');
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		$visitor = XenForo_Visitor::getInstance();
		
		// variables from the request
		$recent = $this->_input->filterSingle('recent', XenForo_Input::UINT);
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$visitor = XenForo_Visitor::getInstance();
		
		$fetchOptions = array();
		$fetchOptions['page'] = $page;
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Entry::JOIN_USER + XfAddOns_Blogs_Model_Entry::JOIN_BLOG + 
			XfAddOns_Blogs_Model_Entry::JOIN_BLOG_PRIVACY + XfAddOns_Blogs_Model_Entry::JOIN_VISITOR_FOLLOW +
			XfAddOns_Blogs_Model_Entry::JOIN_READ_DATE;
		$fetchOptions['whereOptions'] = XfAddOns_Blogs_Model_Entry::WHERE_PRIVACY;
		
		// guests and people requesting all recent get the whole list, not filtered by their visit
		if ($visitor->get('user_id') > 0 && !$recent)
		{
			$fetchOptions['where'] = " xfa_blog_entry.post_date >= " . $visitor->get('last_activity');
		}
		
		$entries = $entryModel->getLatestEntries($fetchOptions);
		if (!$entries)
		{
			return $this->getNoEntriesResponse();
		}		
		$entryModel->prepareEntries($entries);

		// total entries
		$totalEntries = $entryModel->getTotalEntries($fetchOptions);
		$totalEntries = min($totalEntries, 200);
		
		$viewParams = array(
			'showingNewPosts' => !$recent,
			'entries' => $entries,
			'page' => $page,
			'perPage' => XenForo_Application::getOptions()->xfa_blogs_entriesPerPage,
			'totalEntries' => $totalEntries,
			'pageNav' => array(
				'recent' => $recent
			)
		);
		
		return $this->getFindNewWrapper(
				$this->responseView('XenForo_ViewPublic_Base', 'xfa_blogs_find_new', $viewParams),
				'xfa_blog_entries_tab'
		);		
	}
	
	/**
	 * Response returned when there are no entries from the search
	 */
	public function getNoEntriesResponse()
	{
		$days = $this->_input->filterSingle('days', XenForo_Input::UINT);
		$recent = $this->_input->filterSingle('recent', XenForo_Input::UINT);
	
		$this->_routeMatch->setSections('xfa-blogs');
	
		return $this->getFindNewWrapper($this->responseView('XenForo_ViewPublic_Base', 'xfa_blogs_find_new_none', array(
				'days' => $days,
				'recent' => $recent
		)), 'xfa_blog_entries_tab');
	}
	
	
	
	
} 