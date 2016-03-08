<?php

/**
 * Class used to parse the comment alerts
 */
class XfAddOns_Blogs_AlertHandler_Comment extends XenForo_AlertHandler_Abstract
{

	/**
	 * Gets the entry content.
	 * @param array $contentIds
	 * @param XenForo_Model_Alert $model 	Alert model invoking this
	 * @param integer $userId 				User ID the alerts are for
	 * @param array $viewingUser 			Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
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
	 * Determines if the entry is viewable.
	 * @see XenForo_AlertHandler_Abstract::canViewAlert()
	 */
	public function canViewAlert(array $alert, $content, array $viewingUser)
	{
		return true;
	}

}