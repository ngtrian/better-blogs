<?php

/**
 * This model deals with retrieving categories information
 */
class XfAddOns_Blogs_Model_Category extends XenForo_Model
{

	/**
	 * @var XenForo_Model_DataRegistry
	 */
	private $registryModel;
	
	/**
	 * Used to store the global categories in the cache
	 * @var string
	 */
	const GLOBAL_CATEGORIES_CACHE_KEY = '__xfab_global_categ';	
	
	/**
	 * Initialize the class variables
	 */
	public function __construct()
	{
		$this->registryModel = $this->getModelFromCache('XenForo_Model_DataRegistry');
	}
	
	/**
	 * Retrieve the information about a category by primary key
	 * @param int $categoryId	Identifier for the category
	 */
	public function getCategoryById($categoryId)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchRow("SELECT * FROM xfa_blog_category WHERE category_id = ?", $categoryId);
	}
	
	/**
	 * Returns a list with all the categories in the database
	 * @return array
	 */
	public function getGlobalCategories($fetchOptions = array(), $useCache = false)
	{
		static $categories = null;
		if ($categories)
		{
			return $categories;
		}
		
		if ($useCache)
		{
			$categories = $this->registryModel->get(self::GLOBAL_CATEGORIES_CACHE_KEY);
			if ($categories !== null)
			{
				return $categories;
			}			
		}
		
		$categories = $this->fetchAllKeyed("
			SELECT * 
			FROM xfa_blog_category
			WHERE
				user_id = 0
			" . $this->getOrderByOptions($fetchOptions) . " 
		", 'category_id');
		
		// and do some post-processing (always needed for Global Categories)
		$categories = $this->convertToTree($categories);
		$categories = $this->flattenTree($categories);
		
		if ($useCache)
		{
			$this->registryModel->set(self::GLOBAL_CATEGORIES_CACHE_KEY, $categories);
		}
		
		return $categories;
	}

	/**
	 * Expire caches as needed for the categories
	 */
	public function expireGlobalCategoriesInCache()
	{
		/* @var $registryModel XenForo_Model_DataRegistry */
		$registryModel = $this->getModelFromCache('XenForo_Model_DataRegistry');
		$registryModel->delete(self::GLOBAL_CATEGORIES_CACHE_KEY);
	}	
	
	/**
	 * Parse the list of categories and generate a reference to a parent and children
	 * @param array $categories	The array of categories
	 */
	public function convertToTree($originalData)
	{
		// create a copy of the array
		$categories = $originalData;
		
		// iterate and assign the children
		$root = array();
		foreach ($originalData as $id => $category)
		{
			$categoryData = &$categories[$id];
			
			// some quick aliases
			$parentId = $category['parent_id'];
			if (!$parentId)
			{
				$root[] = &$categoryData;
				continue;
			}
			$categories[$parentId]['children'][] = &$categoryData;
		}
		return $root;
	}
	
	/**
	 * Flatten back the tree, providing a prefix depending on the depth of the category
	 * @param array $tree		The tree of categories
	 * @param string $prefix	The prefix for the category name
	 * @return the categories, flat
	 */
	public function flattenTree($tree, $prefix = '')
	{
		static $separator = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		$ret = array();
		foreach ($tree as $category)
		{
			$category['whitespace'] = $prefix;
			$ret[] = $category;
			if (isset($category['children']))
			{
				$ret = array_merge($ret, $this->flattenTree($category['children'], $prefix . $separator));
				unset($category['children']);
			}
		}
		return $ret;
	}
	
	/**
	 * Returns a list with all the categories in the database
	 * @return array
	 */
	public function getGlobalCategoriesForSelectBox($fetchOptions = array())
	{
		if (!isset($fetchOptions['orderBy']))
		{
			$fetchOptions['orderBy'] = 'user_id ASC, display_order ASC';
		}		
		
		$allCategories = $this->getGlobalCategories($fetchOptions);
		$tree = $this->convertToTree($allCategories);
		$flatCategories = $this->flattenTree($tree);
		
		$ret = array();
		$noParent = new XenForo_Phrase('xfa_blogs_no_parent_category');
		$ret[0] = $noParent->__toString();
		foreach ($flatCategories as $category)
		{
			$ret[$category['category_id']] = str_replace('&nbsp;&nbsp;', '-', $category['whitespace']) . $category['category_name'];
		}
		return $ret;
	}
	
	/**
	 * Returns a list with all the categories in the database for a particular blog, with global categories added
	 * @return array
	 */
	public function getCategoriesForSelectBox($blogId, $fetchOptions = array())
	{
		if (!isset($fetchOptions['orderBy']))
		{
			$fetchOptions['orderBy'] = 'user_id ASC, display_order ASC';
		}
		
		$allCategories = $this->getCategoriesForBlogs(array($blogId), $fetchOptions);	// no global here
		$tree = $this->convertToTree($allCategories);
		$flatCategories = $this->flattenTree($tree);
	
		$ret = array();
		$ret[0] = array(
			'category_id' => 0,
			'category_name' => new XenForo_Phrase('xfa_blogs_no_parent_category')
		);
		foreach ($flatCategories as $category)
		{
			$category['whitespace'] = str_replace('&nbsp;&nbsp;', '-', $category['whitespace']);
			$ret[$category['category_id']] =  $category;
		}
		return $ret;
	}	
	
	/**
	 * Return a list with all the categories in the database. This is formatted to be displayed
	 * as a list, with the proper whitespace included
	 */
	public function getGlobalCategoriesForList($fetchOptions = array())
	{
		$allCategories = $this->getGlobalCategories($fetchOptions);
		$tree = $this->convertToTree($allCategories);
		$flatCategories = $this->flattenTree($tree);
		return $flatCategories;
	}
	
	/**
	 * Return the list of categories for a particular blog identifier
	 * @param The id of the blog we want the categories for $blogId
	 */
	public function getCategoriesForBlog($blogId, $fetchOptions = array())
	{
		return $this->fetchAllKeyed("
			SELECT *
			FROM xfa_blog_category
			WHERE
				user_id = ?
				" . $this->getOrderByOptions($fetchOptions) . "
			", 'category_id', $blogId);
	}
	
	/**
	 * Return the list of categories for a list of blogs
	 * @param The id of the blog we want the categories for $blogId
	 */
	public function getCategoriesForBlogs(array $blogIds, $fetchOptions = array())
	{
		if (empty($blogIds))
		{
			return array();
		}
		
		return $this->fetchAllKeyed("
			SELECT
				*
			FROM 
				xfa_blog_category
			WHERE
				user_id IN (" . implode(',', $blogIds) . ")
				" . $this->getOrderByOptions($fetchOptions) . "
			", 'category_id');
	}	
	
	/**
	 * Returns the category for a user that has a matching name
	 */
	public function getCategoryWithName($blogId, $categoryName)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchRow("
			SELECT *
			FROM
				xfa_blog_category
			WHERE
				user_id = ? AND
				category_name = ?
			", array($blogId, $categoryName));		
	}
	
	/**
	 * Return a list of the categories that the person has selected for a particular entry
	 * @param int $entryId	The identifier for the entry
	 */
	public function getSelectedCategoriesForEntry($entryId)
	{
		return $this->fetchAllKeyed("
			SELECT category.*
			FROM xfa_blog_entry_category entry_category
			INNER JOIN xfa_blog_category category ON entry_category.category_id = category.category_id
			WHERE
				entry_category.entry_id = ?
			", 'category_id', $entryId);		
	}
	
	/**
	 * Get all the categories that are applicable for a list of entries, then merges that list into the entries itself
	 * in a categories array.
	 * 
	 * @param array $entries		The list of entries
	 */
	public function getAndMergeSelectedCategories(&$entries)
	{
		if (empty($entries))
		{
			return;
		}
		
		// we are assumming that the array is keyed
		$entryIds = array_keys($entries);
		array_map('intval', $entryIds);
		
		$db = XenForo_Application::getDb();
		$categories = $db->fetchAll("
			SELECT category.*, entry_category.entry_id
			FROM xfa_blog_entry_category entry_category
			INNER JOIN xfa_blog_category category ON entry_category.category_id = category.category_id
			WHERE
				entry_category.entry_id IN (" . implode(',', $entryIds) . ")
			");
		
		foreach ($categories as $category)
		{
			$entryId = $category['entry_id'];
			$entry = &$entries[$entryId];
			if (!isset($entry['categories']) || !is_array($entry['categories']))
			{
				$entry['categories'] = array();
			}
			
			$categoryId = $category['category_id'];
			$entry['categories'][$categoryId] = $category;
			unset($entry['categories'][$categoryId]['entry_id']);		// additional information not needed
		}
	}
	
	
	/**
	 * Deletes a category (and all the associations with the entries) from the database
	 * @param array $category	Information about the category to delete
	 */
	public function deleteCategory(array $category)
	{
		$db = XenForo_Application::getDb();
		$db->delete('xfa_blog_entry_category', 'category_id=' . $db->quote($category['category_id']));
		$db->delete('xfa_blog_category', 'category_id=' . $db->quote($category['category_id']));
		$this->expireGlobalCategoriesInCache();
	}
	
	/**
	 * If fetch options provided any order by cause, this will return ORDER BY with the appended clause
	 * @param array $fetchOptions	The options passed to the fetch method
	 * @return string				An empty string, or the order by
	 */
	protected function getOrderByOptions($fetchOptions)
	{
		if (empty($fetchOptions) || !isset($fetchOptions['orderBy']) || empty($fetchOptions['orderBy']))
		{
			return '';
		}
		return ' ORDER BY ' . $fetchOptions['orderBy'];
	}	
	
	
}