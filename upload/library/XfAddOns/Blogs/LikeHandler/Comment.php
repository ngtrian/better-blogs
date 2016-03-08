<?php

/**
 * Like handler
 */
class XfAddOns_Blogs_LikeHandler_Comment extends XenForo_LikeHandler_Abstract
{

	/**
	 * Increments the like counter.
	 * @see XenForo_LikeHandler_Abstract::incrementLikeCounter()
	 */
	public function incrementLikeCounter($contentId, array $latestLikes, $adjustAmount = 1)
	{
		/* @var $dw XfAddOns_Blogs_DataWriter_Comment */
		$dw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Comment');
		$dw->setExistingData($contentId);
		$dw->set('likes', $dw->get('likes') + $adjustAmount);
		$dw->set('like_users', $latestLikes);
		$dw->save();
	}

	/**
	 * Gets content data (if viewable).
	 * @see XenForo_LikeHandler_Abstract::getContentData()
	 */
	public function getContentData(array $contentIds, array $viewingUser)
	{
		/* @var $commentModel XfAddOns_Blogs_Model_Comment */
		$commentModel = XenForo_Model::create('XfAddOns_Blogs_Model_Comment');
		
		$fetchOptions = array();
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Comment::JOIN_USER + XfAddOns_Blogs_Model_Comment::JOIN_ENTRY;
		$comments = $commentModel->getCommentsById($contentIds, $fetchOptions);
		$commentModel->prepareComments($comments);
		
		return $comments;		
	}

	/**
	 * Gets the name of the template that will be used when listing likes of this type.
	 *
	 * @return string news_feed_item_profile_post_like
	 */
	public function getListTemplateName()
	{
		return 'news_feed_item_xfa_blog_comment_like';
	}

}