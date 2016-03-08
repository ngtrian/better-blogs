<?php

class XfAddOns_Blogs_SitemapHandler_Entry extends XenForo_SitemapHandler_Abstract
{
	
	/**
	 * @var XfAddOns_Blogs_Model_Entry
	 */
	protected $_entryModel;
	
	public function getRecords($previousLast, $limit, array $viewingUser)
	{
		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'xfa_blogs', 'xfa_blogs_view'))
		{
			return array();
		}
	
		$entryModel = $this->_getEntryModel();
		$entries = $entryModel->getEntryIdsFromRangeId($previousLast, $limit);
		$entryModel->prepareEntries($entries);
		
		return $entries;
	}
	
	public function isIncluded(array $entry, array $viewingUser)
	{
		return $entry['perms']['canView'];
	}
	
	public function getData(array $entry)
	{
		$entry['title'] = XenForo_Helper_String::censorString($entry['title']);
		return array(
				'loc' => XenForo_Link::buildPublicLink('canonical:xfa-blog-entry', $entry),
				'lastmod' => $entry['post_date']
		);
	}
	
	public function isInterruptable()
	{
		return true;
	}
	
	/**
	 * @return XfAddOns_Blogs_Model_Entry
	 */
	protected function _getEntryModel()
	{
		if (!$this->_entryModel)
		{
			$this->_entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		}
	
		return $this->_entryModel;
	}	
	
	
	
}