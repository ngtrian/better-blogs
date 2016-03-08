<?php

class XfAddOns_Blogs_SpamHandler_Comment extends XenForo_SpamHandler_Abstract
{

	/**
	 * Checks that the options array contains a non-empty 'delete_xfa_blog_comment' key
	 */
	public function cleanUpConditionCheck(array $user, array $options)
	{
		return !empty($options['delete_xfa_blog_comment']) || (isset($_REQUEST['delete_xfa_blog_comment']) && $_REQUEST['delete_xfa_blog_comment']);
	}
	
	/**
	 * @see XenForo_SpamHandler_Abstract::cleanUp()
	 */
	public function cleanUp(array $user, array &$log, &$errorKey)
	{
		/* @var $commentModel XfAddOns_Blogs_Model_Comment */
		$commentModel = XenForo_Model::create('XfAddOns_Blogs_Model_Comment');
		$comments = $commentModel->getCommentsForUser($user);
		if (empty($comments))
		{
			return true;
		}
		
		$deleteType = (XenForo_Application::get('options')->spamMessageAction == 'delete' ? 'hard' : 'soft');
		$commentIds = array_keys($comments);
		
		$log['xfa_blog_comment'] = array(
			'deleteType' => $deleteType,
			'commentIds' => $commentIds
		);
		
		/* @var $inlineModModel XfAddOns_Blogs_InlineMod_Comment */
		$inlineModModel = $this->getModelFromCache('XfAddOns_Blogs_InlineMod_Comment');
		return $inlineModModel->deleteComments($comments, array('deleteType' => $deleteType), $errorKey);
		
	}
	
	/**
	 * @see XenForo_SpamHandler_Abstract::restore()
	 */
	public function restore(array $log, &$errorKey = '')
	{
	}
	
	
}