<?php

class XfAddOns_Blogs_ControllerPublic_Browse extends XfAddOns_Blogs_ControllerPublic_Abstract
{
	
	/**
	 * Browse a blog, by month
	 */
	public function actionMonth()
	{
		$blog = $this->getBlog();
		if (!$blog['perms']['canView'])
		{
			return $this->responseNoPermission();
		}

		// get the categories for the user
		$categoryOptions['orderBy'] = 'category_name';
		$categories = $this->categoryModel->getCategoriesForBlog($blog['user_id'], $categoryOptions);
		
		// get the categories
		$filter = $this->_input->filterSingle('filter', XenForo_Input::UINT);
		$selectedCategory = null;
		if (isset($categories[$filter]))
		{
			$selectedCategory = $categories[$filter];
		}

		// retrieve all entries, or entries inside the category
		if ($selectedCategory)
		{
			$fetchOptions['orderBy'] = 'post_date ASC';
			$fetchOptions['where'] = 'xfa_blog_entry.user_id = ' . $blog['user_id'] . " AND message_state='visible'";
			$entries = $this->entryModel->getBlogEntriesForCategory($selectedCategory, $fetchOptions);
		}
		else
		{
			$fetchOptions['orderBy'] = 'post_date ASC';
			$fetchOptions['where'] = 'xfa_blog_entry.user_id = ' . $blog['user_id'] . " AND message_state='visible'";
			$entries = $this->entryModel->getEntriesSimple($fetchOptions);
		}
		
		// process the entries
		$this->entryModel->prepareEntries($entries);
		$this->entryModel->removePrivateEntriesForVisitor($entries);
				
		$options = XenForo_Application::getOptions();
		$entriesByYear = $this->groupByYearAndMonth($entries);
		$containerParams = array(
			'isBlogContainer' => true,
			'containerTemplate' => ($options->xfa_blogs_blogMode != 'classic') ? 'xfa_blog_PAGE_CONTAINER_full_screen' : 'PAGE_CONTAINER',
			'blog' => $blog,
			'noVisitorPanel' => true,
			'showCustomization' => false
		);
		$params = array(
			'blog' => $blog,
			'categories' => $categories,
			'entriesByYear' => $entriesByYear,
			'selectedCategory' => $selectedCategory
		);
		
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_browse_year', $params, $containerParams);
	}
	
	
	/**
	 * From a list of entries, group the information by year and month
	 * @param array $entries
	 */
	public function groupByYearAndMonth($entries)
	{
		$data = array();
		foreach ($entries as $entry)
		{
			$year = date('Y', $entry['post_date']);
			$month = date('n', $entry['post_date']);
			if (!isset($data[$year][$month]))
			{
				$data[$year][$month] = array(
					'label' => new XenForo_Phrase('month_' . $month),
					'entries' => array()
				);
			}
			$data[$year][$month]['entries'][] = $entry;
		}
		return $data;
	}
	
	
	
}