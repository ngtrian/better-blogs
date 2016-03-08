<?php

class XfAddOns_Blogs_NewsFeedHandler_Entry extends XenForo_NewsFeedHandler_Abstract
{
	
	/**
	 * Fetches related content (comments) by IDs
	 *
	 * @param array $contentIds					Identifiers (post_ids) that we would need to fetch
	 * @param array $viewingUser 				Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, array $viewingUser)
	{
		$fetchOptions = array(
			'join' => XfAddOns_Blogs_Model_Entry::JOIN_USER + XfAddOns_Blogs_Model_Entry::JOIN_DELETION_LOG +
				XfAddOns_Blogs_Model_Entry::JOIN_BLOG_PRIVACY + XfAddOns_Blogs_Model_Entry::JOIN_BLOG + XfAddOns_Blogs_Model_Entry::JOIN_VISITOR_FOLLOW 
			);
		
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		$entries = $entryModel->getEntriesByIds($contentIds, $fetchOptions);
		$entryModel->prepareEntries($entries);
		return $entries;
	}
	
	/**
	 * Determines if the given news feed item is viewable.
	 *
	 * @param array $item
	 * @param mixed $content
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewNewsFeedItem(array $item, $content, array $viewingUser)
	{
		if (!isset($content['perms']) || !$content['perms']['canView'])
		{
			return false;
		}
		
		// blog fields are joined
		$blog = $content;
		
		/* @var $blogModel XfAddOns_Blogs_Model_Blog  */
		$blogModel = XenForo_Model::create('XfAddOns_Blogs_Model_Blog');
		$blogModel->prepareBlog($blog);
		
		// check blog permission
		if (!$blog['perms']['canView'])
		{
			return false;
		}
		return true;
	}
	
	/**
	 * Prepares the news feed item for display
	 *
	 * @param array $item News feed item
	 * @param array $content News feed item content
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	protected function _prepareNewsFeedItemAfterAction(array $item, $content, array $viewingUser)
	{
		$item['content'] = array();
		$item['content']['entry_id'] = $content['entry_id'];
		$item['content']['user_id'] = $content['user_id'];
		$item['content']['username'] = $content['username'];
		$item['content']['title'] = $content['title'];
		$item['content']['message'] = $content['message'];
		return $item;
	}	
	
}