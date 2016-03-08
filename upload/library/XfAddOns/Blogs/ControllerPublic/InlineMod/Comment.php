<?php

/**
 * Inline moderation actions for comments
 * @package XenForo_Thread
 */
class XfAddOns_Blogs_ControllerPublic_InlineMod_Comment extends XenForo_ControllerPublic_InlineMod_Abstract
{
	
	public $inlineModKey = 'comments';
	
	/**
	 * @return XfAddOns_Blogs_InlineMod_Comment
	 */
	public function getInlineModTypeModel()
	{
		return $this->getModelFromCache('XfAddOns_Blogs_InlineMod_Comment');
	}	
	
	/**
	 * Comments deletion handler.
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$commentIds = $this->getInlineModIds(false);
			$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::STRING);
			$options = array(
				'deleteType' => ($hardDelete ? 'hard' : 'soft'),
				'reason' => $this->_input->filterSingle('reason', XenForo_Input::STRING)
			);
			
			// transform to full objects
			$comments = $this->getComments($commentIds);
			$this->checkForDeletePermissions($comments);

			// proceed with deleting the comments
			$model = $this->getInlineModTypeModel();
			$errorKey = '';
			$deleted = $model->deleteComments($comments, $options, $errorKey);
			
			if (!$deleted)
			{
				throw $this->getErrorOrNoPermissionResponseException($errorKey);
			}
	
			$this->clearCookie();
	
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect(false, false)
			);
		}
		else // show confirmation dialog
		{
			$commentIds = $this->getInlineModIds();
			$comments = $this->getComments($commentIds);
			
			// check that we can delete all comments that we are requesting to delete
			$this->checkForDeletePermissions($comments);
			
			if (empty($commentIds))
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}
			
			$viewParams = array(
				'commentIds' => $commentIds,
				'commentCount' => count($commentIds),
				'canHardDelete' => $this->canHardDelete(),
				'redirect' => $this->getDynamicRedirect()
			);
	
			return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blogs_inline_mod_comment_delete', $viewParams);
		}
	}
	
	/**
	 * Undeletes the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUndelete()
	{
		// transform to full objects
		$commentIds = $this->getInlineModIds(false);
		$comments = $this->getComments($commentIds);
		$this->checkForDeletePermissions($comments);
		
		// delegate for inlineMod
		$options = array(
			'comments' => $comments
		);
		return $this->executeInlineModAction('undeleteComments', $options);
	}	

	/**
	 * Retrieve the complete comment objects. This will get a list of ids, retrieve, and prepare the comments
	 */
	protected function getComments($commentIds)
	{
		/* @var $commentModel XfAddOns_Blogs_Model_Comment */
		$commentModel = XenForo_Model::create('XfAddOns_Blogs_Model_Comment');
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Comment::JOIN_ENTRY;
		
		$comments = $commentModel->getCommentsById($commentIds, $fetchOptions);
		$commentModel->prepareComments($comments);
		return $comments;
	}
	
	/**
	 * Checks that the user has permission to delete all the comments mentioned in the list
	 * @param array $comments		Array of comments
	 */
	protected function checkForDeletePermissions($comments)
	{
		if (empty($comments))
		{
			return;
		}
		foreach ($comments as $comment)
		{
			if (!$comment['perms']['canDelete'])
			{
				$title = '#' . ($comment['position'] + 1);
				$error = new XenForo_Phrase('xfa_blogs_delete_no_permission_comment_x', array('title' => $title));
				throw $this->getErrorOrNoPermissionResponseException($error);
			}
		}
	}	
	
	/**
	 * Returns true if visitor has hard delete privileges
	 */
	protected function canHardDelete()
	{
		$visitor = XenForo_Visitor::getInstance();
		$allPermissions = $visitor->getPermissions();
		$blogPermissions = $allPermissions['xfa_blogs'];
		return $blogPermissions['xfa_blogs_hard_delete'];
	}	
	
	
	
}