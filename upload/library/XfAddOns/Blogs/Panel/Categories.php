<?php

/**
 * This panel shows a list of all the categories that the user has configured
 */
class XfAddOns_Blogs_Panel_Categories
{

	/**
	 * @var XfAddOns_Blogs_Model_Category
	 */
	protected $categoryModel;
	
	/**
	 * Constructor. Initialize the model category.
	 */
	public function __construct()
	{
		$this->categoryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Category');
	}
	
	/**
	 * Fetches and return the panel content. This will fetch the list of people that the blog author follows
	 * and display that as a list
	 */
	public function getPanelContent(array $blogIds, $showCustomizeSidebar = false)
	{
		// check if we can use the global categories cached call
		if (count($blogIds) == 1 && $blogIds[0] == 0)
		{
			$categories = $this->getGlobalCategories();
		}
		else
		{
			$categories = $this->getCategories($blogIds);
		}

		// if empty, we will short circuit the panel
		if (empty($categories))
		{
			return '';
		}
		
		$template = new XenForo_Template_Public('xfa_blog_panel_categories', array(
			'categories' => $categories,
			'visitor' => XenForo_Visitor::getInstance(),
			'showCustomizeSidebar' => $showCustomizeSidebar,
			'title' => new XenForo_Phrase('xfa_blogs_categories')
			));		
		return $template;
	}
	
	/**
	 * Return the list of categories
	 */
	public function getCategories(array $blogIds)
	{
		// we can either use an array, or just an array
		if (!empty($blogIds) && !is_array($blogIds))
		{
			$blogIds = array( $blogIds );
		}

		$fetchOptions['orderBy'] = 'user_id ASC, display_order ASC';
		$allCategories = $this->categoryModel->getCategoriesForBlogs($blogIds, $fetchOptions);
		$allCategories = $this->categoryModel->convertToTree($allCategories);
		$allCategories = $this->categoryModel->flattenTree($allCategories);
		return $allCategories;
	}

	/**
	 * Return the list of global categories. This method will also store the information in the cache
	 */
	public function getGlobalCategories()
	{
		$fetchOptions['orderBy'] = 'user_id ASC, display_order ASC';
		$categories =  $this->categoryModel->getGlobalCategories($fetchOptions, true);
		return $categories;
		
	}
	
}