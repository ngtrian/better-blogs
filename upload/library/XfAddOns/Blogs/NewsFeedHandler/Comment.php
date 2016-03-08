<?php

class XfAddOns_Blogs_NewsFeedHandler_Comment extends XenForo_NewsFeedHandler_Abstract
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
		/* @var $commentModel XfAddOns_Blogs_Model_Comment */
		$commentModel = XenForo_Model::create('XfAddOns_Blogs_Model_Comment');
		
		$fetchOptions = array();
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Comment::JOIN_USER;
		$comments = $commentModel->getCommentsById($contentIds, $fetchOptions);
		$commentModel->prepareComments($comments);
		$commentModel->wireEntriesAndBlogs($comments);
		
		return $comments;
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
		$comment = $content;
		$entry = isset($comment['entry']) ? $comment['entry'] : NULL;
		$blog = isset($comment['entry']['blog']) ? $comment['entry']['blog'] : NULL;
		
		if (!$entry || !isset($entry['perms']) || !$entry['perms']['canView'])
		{
			return false;
		}
		if (!$blog || !isset($blog['perms']) || !$blog['perms']['canView'])
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
		$item['content']['comment_id'] = $content['comment_id'];
		$item['content']['user_id'] = $content['user_id'];
		$item['content']['username'] = $content['username'];
		$item['content']['entry_id'] = $content['entry_id'];
		$item['content']['entry']['title'] = $content['entry']['title'];
		$item['content']['entryUser']['user_id'] = $content['entry']['user_id'];
		$item['content']['entryUser']['username'] = $content['entry']['username'];
		$item['content']['message'] = $content['message'];
		return $item;
	}	
	
}