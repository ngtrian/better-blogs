<?php

/**
 * Controller that handles the actions for comments
 */
class XfAddOns_Blogs_ControllerPublic_BlogCategory extends XfAddOns_Blogs_ControllerPublic_Abstract
{
	
	/**
	 * Overlay displayed for creating a new category. This will display a form with the data needed
	 * to create a new category for the blog user
	 */
	public function actionCreateCategoryOverlay()
	{
		$visitorUserId = XenForo_Visitor::getUserId();
		if (!$visitorUserId)
		{
			return $this->responseNoPermission();
		}		
		
		// check if we have permissions to create categories
		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor->hasPermission('xfa_blogs', 'xfa_blogs_categories'))
		{
			return $this->responseNoPermission();
		}
		
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_category_create');
	}
	
	/**
	 * This method will add a new category to that user's blog
	 */
	public function actionCreateCategory()
	{
		$visitorUserId = XenForo_Visitor::getUserId();
		if (!$visitorUserId)
		{
			return $this->responseNoPermission();
		}

		// check if we have permissions to create categories
		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor->hasPermission('xfa_blogs', 'xfa_blogs_categories'))
		{
			return $this->responseNoPermission();
		}
		
		$categoryName = $this->_input->filterSingle('category_name', XenForo_Input::STRING);
		$dw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Category');
		$dw->set('user_id', $visitorUserId);
		$dw->set('category_name', $categoryName);
		$dw->save();
		
		$extraParams = array(
			'category_id' => $dw->get('category_id'),
			'categoryTemplate' => new XenForo_Template_Public('xfa_blog_category_bit', array(
				'category' => $dw->getMergedData('xfa_blog_category')
			)));
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blogs', array('user_id' => $visitorUserId)),
			'',
			$extraParams
		);
	}
	
	/**
	 * Shows the overlay with the confirmation message on whether we want to delete the categories
	 */
	public function actionDeleteCategoryOverlay()
	{
		list($category, $blog) = $this->getCategoryAndBlog();
		$viewParams = array(
				'blog' => $blog,
				'category' => $category
		);
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_delete_category_overlay', $viewParams);		
	}
	
	/**
	 * Called when we finally want to delete a category
	 */
	public function actionDeleteCategory()
	{
		list($category, $blog) = $this->getCategoryAndBlog();
		if ($category['user_id'] != XenForo_Visitor::getUserId())
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_category_no_permissions'));
		}

		// do delete the category
		$this->categoryModel->deleteCategory($category);
		
		// standard redirect will be captured by ajax
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blogs', $blog),
			'',
			array('categoryId' => $category['category_id'])
		);		
	}
	
	/**
	 * Called to list all the entries in a category
	 */
	public function actionViewEntries()
	{
		list($category, $blog) = $this->getCategoryAndBlog();
		if ($blog && !$blog['perms']['canView'])
		{
			return $this->responseNoPermission();
		}
		
		// page
		$options = XenForo_Application::getOptions();
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		
		// do canonicalization
		$this->canonicalizeRequestUrl(
				XenForo_Link::buildPublicLink('xfa-blog-category/view-entries', $category, array('page' => $page))
		);
		$this->canonicalizeDomain($blog);
		$this->canonicalizePageNumber($page, $options->xfa_blogs_entriesPerPage, $category['entry_count'] + 1, 'xfa-blog-category/view-entries', $category);
		
		// parameters
		$fetchOptions['page'] = $page;
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Entry::JOIN_USER + XfAddOns_Blogs_Model_Entry::JOIN_LIKE_INFORMATION +
								XfAddOns_Blogs_Model_Entry::JOIN_READ_DATE + XfAddOns_Blogs_Model_Entry::JOIN_VISITOR_FOLLOW +
								XfAddOns_Blogs_Model_Entry::JOIN_BLOG + XfAddOns_Blogs_Model_Entry::JOIN_BLOG_PRIVACY;
		$fetchOptions['orderBy'] = 'post_date DESC';
		$fetchOptions['whereOptions'] = XfAddOns_Blogs_Model_Entry::WHERE_PRIVACY;
		
		// entries
		$entries = $this->entryModel->getBlogEntriesForCategory($category, $fetchOptions);
		$this->entryModel->prepareEntries($entries);
		$this->entryModel->getAndMergeAttachmentsIntoEntries($entries);
		$this->entryModel->removePrivateEntriesForVisitor($entries);

		// weave in the categories
		$this->categoryModel->getAndMergeSelectedCategories($entries);
		
		$containerParams = array(
			'isBlogContainer' => true,
			'noVisitorPanel' => true,
			'blog' => $blog
		);
		$params = array(
			'blog' => $blog,
			'category' => $category,
			'page' => $fetchOptions['page'],
			'entriesPerPage' => $options->xfa_blogs_entriesPerPage,
			'entries' => $entries,
			'panels' => $blog ? $this->blogModel->getPanels($blog) : array()
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Blog_Categories', 'xfa_blog_category_browse', $params, $containerParams);		
	}
	
	/**
	 * This is called as an overlay to show a summary of the latest entries
	 * in a specific category
	 */
	public function actionViewEntriesOverlay()
	{
		list($category, $blog) = $this->getCategoryAndBlog();
		if ($blog && !$blog['perms']['canView'])
		{
			return $this->responseNoPermission();
		}
		
		// fetchOptions
		$fetchOptions['page'] = 1;
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Entry::JOIN_USER + XfAddOns_Blogs_Model_Entry::JOIN_LIKE_INFORMATION +
								XfAddOns_Blogs_Model_Entry::JOIN_READ_DATE + XfAddOns_Blogs_Model_Entry::JOIN_VISITOR_FOLLOW +
								XfAddOns_Blogs_Model_Entry::JOIN_BLOG + XfAddOns_Blogs_Model_Entry::JOIN_BLOG_PRIVACY +
								XfAddOns_Blogs_Model_Entry::JOIN_DELETION_LOG;
		$fetchOptions['orderBy'] = 'post_date DESC';
		$fetchOptions['whereOptions'] = XfAddOns_Blogs_Model_Entry::WHERE_PRIVACY;
		
		// entries
		$entries = $this->entryModel->getBlogEntriesForCategory($category, $fetchOptions);		
		$this->entryModel->prepareEntries($entries);
		$this->entryModel->removePrivateEntriesForVisitor($entries);

		$params = array(
			'blog' => $blog,
			'category' => $category,
			'entries' => $entries
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Blog_Categories', 'xfa_blog_categories_overlay', $params);		
	}	
	
	/**
	 * Shows the overlay with the confirmation message on whether we want to delete the categories
	 */
	public function actionEditOverlay()
	{
		$visitorUserId = XenForo_Visitor::getUserId();
		if (!$visitorUserId)
		{
			return $this->responseNoPermission();
		}
		
		$visitor = XenForo_Visitor::getInstance();
		list($category, $blog) = $this->getCategoryAndBlog();
		if ($category['user_id'] != $visitor['user_id'])
		{
			return $this->responseNoPermission();
		}
		
		$fetchOptions['join'] = 'display_order ASC';
		$allCategories = $this->categoryModel->getCategoriesForSelectBox($blog['user_id']);
		
		$viewParams = array(
			'category' => $category,
			'allCategories' => $allCategories
		);
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_edit_category_overlay', $viewParams);
	}
	
	/**
	 * Save the category back into the database
	 */
	public function actionSave()
	{
		$visitorUserId = XenForo_Visitor::getUserId();
		if (!$visitorUserId)
		{
			return $this->responseNoPermission();
		}		
		
		$visitor = XenForo_Visitor::getInstance();
		list($category, $blog) = $this->getCategoryAndBlog();
		if ($category['user_id'] != $visitor['user_id'])
		{
			return $this->responseNoPermission();
		}
		
		$dwInput = $this->_input->filter(array(
			'category_id' => XenForo_Input::INT,
			'category_name' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::INT,
			'parent_id' => XenForo_Input::INT,
			'is_active' => XenForo_Input::INT
		));
		
		$dw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Category');
		$dw->setExistingData($dwInput['category_id']);
		$dw->bulkSet($dwInput);
		$dw->save();
		
		$categoriesPanel = new XfAddOns_Blogs_Panel_Categories();
		$extraParams = array(
			'category_id' => $dw->get('category_id'),
			'categoryPanelTemplate' => $categoriesPanel->getPanelContent(array ( $blog['user_id'], 0 ))
		);
		
		return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xfa-blogs', array('user_id' => $visitor['user_id'])),
				'',
				$extraParams
		);		
	}
	
	
	/**
	 * This controller does not track user activity
	 * @see XenForo_Controller::canUpdateSessionActivity()
	 */
	public function canUpdateSessionActivity($controllerName, $action, &$newState)
	{
		return false;
	}
	
	
}