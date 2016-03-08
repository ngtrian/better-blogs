<?php

class XfAddOns_Blogs_SitemapHandler_Blog extends XenForo_SitemapHandler_Abstract
{

	/**
	 * @var XfAddOns_Blogs_Model_Blog
	 */
	protected $_blogModel;	
	
	public function getRecords($previousLast, $limit, array $viewingUser)
	{
		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'xfa_blogs', 'xfa_blogs_view'))
		{
			return array();
		}
	
		$blogModel = $this->_getBlogModel();
		return $blogModel->getBlogIdsInRange($previousLast, $limit);
	}
	
	public function isIncluded(array $entry, array $viewingUser)
	{
		return true;
	}
	
	public function getData(array $entry)
	{
		$entry['blog_title'] = XenForo_Helper_String::censorString($entry['blog_title']);
		return array(
				'loc' => XenForo_Link::buildPublicLink('canonical:xfa-blogs', $entry),
				'lastmod' => $entry['last_entry']
		);
	}
	
	public function isInterruptable()
	{
		return true;
	}	
	
	/**
	 * @return XfAddOns_Blogs_Model_Blog
	 */
	protected function _getBlogModel()
	{
		if (!$this->_blogModel)
		{
			$this->_blogModel = XenForo_Model::create('XfAddOns_Blogs_Model_Blog');
		}
	
		return $this->_blogModel;
	}	
	
}