<?php

/**
 * Recent entries
 */
class XfAddOns_Blogs_Panel_Entries extends XfAddOns_Blogs_Panel_Abstract
{
	
	/**
	 * Fetches and return the panel content. This panel shows the most recent entries
	 */
	public function getPanelContent()
	{
		$cacheKey = 'xfab_wf_local_entries';
		$entries = $this->getEntriesFiltered($cacheKey, 'post_date', 'DESC', 10);
		
		$template = new XenForo_Template_Public('xfa_blog_wf_entries', array(
			'entries' => $entries,
			'visitor' => XenForo_Visitor::getInstance(),
			'title' => new XenForo_Phrase('xfa_blogs_recent_entries'),
			'includeSnippet' => true,
			'order' => 'post_date'
			));
		return $template;		
	}
	
	/**
	 * This will get the entries from the cache, and on top of that apply the filters for privacy
	 * We need to filter this each time since the privacy permissions are user-specific
	 */
	public function getEntriesFiltered($cacheKey, $orderBy, $direction, $limit)
	{
		// retrieve the entries from the cache
		$entries = $this->getFromCache('Entries', $cacheKey, $orderBy, $direction, $limit);
		
		// prepare the entries
		$this->entryModel->prepareEntries($entries);
		foreach ($entries as &$entry)
		{
			$this->blogModel->prepareBlog($entry['blog']);
		}
		$this->entryModel->removePrivateEntriesForVisitor($entries);
		
		// return the entries after they were filtered
		if (count($entries) > $limit)
		{
			return array_slice($entries, 0, $limit);	
		}
		return $entries;
	}
	
	/**
	 * We will get the latest entries, and store in a cache if needed
	 * @return array
	 */
	public function getEntries($orderBy, $direction, $limit, $whereHint = '')
	{
		// fetch options
		$fetchOptions = array(
			'join' => XfAddOns_Blogs_Model_Entry::JOIN_USER,	// can't join visitor, no visitor (deferred)
			'limit' => $limit,
			'where' => $whereHint,
			'orderBy' => $orderBy . ' ' . $direction 
			);
		$entries = $this->entryModel->getEntries($fetchOptions);
		$this->entryModel->prepareEntries($entries);
		$this->entryModel->wireBlogs($entries);
		return $entries;
	}
	
}
