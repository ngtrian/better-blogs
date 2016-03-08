<?php

/**
 * Controller that handles the actions for comments
 */
class XfAddOns_Blogs_ControllerPublic_BlogComment extends XfAddOns_Blogs_ControllerPublic_Abstract
{

	/**
	 * There is no index page for comments, instead, it redirects to the entry
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionIndex()
	{
		list($comment, $entry) = $this->getCommentAndEntry();
		return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('xfa-blog-entry', $entry) . '#comment-' . $comment['comment_id']
		);
	}	
	
	/**
	 * Shows an overlay for editing a comment
	 */
	public function actionEditCommentOverlay()
	{
		list($comment, $entry) = $this->getCommentAndEntry();
		
		// Permissions check
		if (!$comment['perms']['canEdit'])
		{
			return $this->responseNoPermission();
		}
		
		$viewParams = array(
			'comment' => $comment
		);
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_comment_edit', $viewParams);		
	}
	
	/**
	 * Persists the new comment information into the database
	 */
	public function actionSaveComment()
	{
		list($comment, $entry) = $this->getCommentAndEntry();
		
		// Permissions check
		if (!$comment['perms']['canEdit'])
		{
			return $this->responseNoPermission();
		}

		$message = $this->_input->filterSingle('message', XenForo_Input::STRING);
		
		// visitor
		$visitor = XenForo_Visitor::getInstance();
		
		/* @var $dw XfAddOns_Blogs_DataWriter_Comment */
		$dw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Comment');
		$dw->setExistingData($comment['comment_id']);
		$dw->set('message', $message);
		$dw->setExtraData('entry', $entry);
		$dw->setExtraData('username', $visitor->get('username'));
		$dw->save();
		
		$viewParams = array(
				'comment_id' => $comment['comment_id']
		);
		return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xfa-blogs-entry', $entry),
				new XenForo_Phrase('xfa_blogs_comment_has_been_edited'),
				$viewParams
		);		
	}
	
	/**
	 * Overlay for deleting a comment
	 * Triggered when the user clicks con "delete"
	 */
	public function actionDeleteCommentOverlay()
	{
		list($comment, $entry) = $this->getCommentAndEntry();
	
		// check if the user has permission to delete the entry
		if (!$comment['perms']['canDelete'])
		{
			return $this->responseNoPermission();
		}
	
		$viewParams = array(
				'comment' => $comment
		);
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_delete_comment_overlay', $viewParams);
	}
	
	/**
	 * This method is called when we need to delete a comment
	 */
	public function actionDeleteComment()
	{
		list($comment, $entry) = $this->getCommentAndEntry();
	
		// check if the user has permission to delete the comment
		if (!$comment['perms']['canDelete'])
		{
			return $this->responseNoPermission();
		}
	
		// fetch the reason and proceed with the delete
		$reason = $this->_input->filterSingle('reason', XenForo_Input::STRING);
	
		/* @var $dwComment XfAddOns_Blogs_DataWriter_Comment */
		$dwComment = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Comment');
		$dwComment->setExistingData($comment['comment_id']);
		$dwComment->setExtraData(XfAddOns_Blogs_DataWriter_Comment::EXTRA_DELETE_REASON, $reason);
		$dwComment->set('message_state', 'deleted');

		// check if comment is to be hard deleted
		$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::INT);
		if ($hardDelete == 1)
		{
			$dwComment->delete();
		}
		else
		{
			$dwComment->save();
		}
	
		// add the delete information to the comment
		$visitor = XenForo_Visitor::getInstance();
		$comment = array_merge($comment, array(
			'delete_user_id' => $visitor->get('user_id'),
			'delete_username' => $visitor->get('username'),
			'delete_date' => XenForo_Application::$time,
			'delete_reason' => $reason
		));
		// prepare the comment to parse the "deleteInfo" array
		$this->commentsModel->prepareComment($comment);
	
		// params will be the template that will be rendered
		$userData = XenForo_Visitor::getInstance()->toArray();
		$extraParams = array(
			'comment_id' => $comment['comment_id'],
			'comment' => new XenForo_Template_Public('xfa_blog_comment_deleted', array(
				'entry' => $entry,
				'comment' => $comment,						
				'visitor' => $userData,
				'showCustomization' => true
			)));
		
		// doesn't make sense to display the deleted comment if it was permanently removed
		if ($hardDelete == 1)
		{
			$extraParams['comment'] = ' ';
		}

		// log the moderator action
		$deleteType = $hardDelete ? 'hard' : 'soft';
		XenForo_Model_Log::logModeratorAction('xfa_blog_comment', $comment, 'delete_' . $deleteType, array('reason' => $reason));
	
		// return to the thread
		return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xfa-blog-entry', $entry),
				new XenForo_Phrase('xfa_blogs_comment_has_been_deleted'),
				$extraParams
		);
	}
	
	/**
	 * Action called when we want to see a comment that has been deleted
	 */
	public function actionShowComment()
	{
		list($comment, $entry) = $this->getCommentAndEntry();
	
		// check if the user has permission to delete the entry
		if (!$comment['perms']['canViewDeleted'])
		{
			return $this->responseNoPermission();
		}
	
		$viewParams = array(
			'comment' => $comment,
			'showCustomization' => true
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Comment_Show', 'xfa_blog_comment', $viewParams);
	}

	/**
	 * Action called when we want to restore a deleted comment
	 */
	public function actionRestoreComment()
	{
		list($comment, $entry) = $this->getCommentAndEntry();
	
		// check if the user has permission to delete the entry
		if (!$comment['perms']['canRestore'])
		{
			return $this->responseNoPermission();
		}
		
		/* @var $dwComment XfAddOns_Blogs_DataWriter_Comment */
		$dwComment = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Comment');
		$dwComment->setExistingData($comment['comment_id']);
		$dwComment->set('message_state', 'visible');
		$dwComment->save();		
	
		// log moderator action
		XenForo_Model_Log::logModeratorAction('xfa_blog_comment', $comment, 'restore', array());
		
		$viewParams = array(
			'comment' => $comment,
			'showCustomization' => true
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Comment_Show', 'xfa_blog_comment', $viewParams);
	}
	
	/**
	 * Called when we want to like a comment
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLike()
	{
		list($comment, $entry) = $this->getCommentAndEntry();
		if (!$comment['perms']['canLike'])
		{
			return $this->responseNoPermission();
		}
	
		/* @var $likeModel XenForo_Model_Like */
		$likeModel = $this->getModelFromCache('XenForo_Model_Like');
		$existingLike = $likeModel->getContentLikeByLikeUser('xfa_blog_comment', $comment['comment_id'], XenForo_Visitor::getUserId());

		// change the like status
		if ($existingLike)
		{
			$latestUsers = $likeModel->unlikeContent($existingLike);
		}
		else
		{
			$latestUsers = $likeModel->likeContent('xfa_blog_comment', $comment['comment_id'], $comment['user_id']);
		}
	
		$liked = ($existingLike ? false : true);
	
		$comment['likeUsers'] = $latestUsers;
		$comment['likes'] += ($liked ? 1 : -1);
		$comment['like_date'] = ($liked ? XenForo_Application::$time : 0);
	
		$viewParams = array(
				'comment' => $comment,
				'liked' => $liked,
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Comment_LikeConfirmed', '', $viewParams);
	}	
	
	/**
	 * List of everyone that liked this.
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLikes()
	{
		list($comment, $entry) = $this->getCommentAndEntry();

		/* @var $likeModel XenForo_Model_Like */
		$likeModel = $this->getModelFromCache('XenForo_Model_Like');
		$likes = $likeModel->getContentLikes('xfa_blog_comment', $comment['comment_id']);
		if (!$likes)
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_nobody_has_liked_this_comment_yet'));
		}
	
		$viewParams = array(
			'post' => $comment,
			'likes' => $likes
		);
		return $this->responseView('XenForo_ViewPublic_Post_Likes', 'xfa_blog_comment_likes', $viewParams);
	}	
	
	/**
	 * Displays the IP associated with the comment
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIp()
	{
		list($comment, $entry) = $this->getCommentAndEntry();
		if (!XenForo_Visitor::getInstance()->hasPermission('general', 'viewIps'))
		{
			return $this->responseNoPermission();
		}
		if (!$comment['ip_id'])
		{
			return $this->responseError(new XenForo_Phrase('no_ip_information_available'));
		}

		$viewParams = array(
			'comment' => $comment,
			'ipInfo' => $this->getModelFromCache('XenForo_Model_Ip')->getContentIpInfo($comment)
		);

		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_comment_ip', $viewParams);
	}
	
	/**
	 * View the entry history
	 */
	public function actionHistory()
	{
		$this->_request->setParam('content_type', 'xfa_blog_comment');
		$this->_request->setParam('content_id', $this->_input->filterSingle('comment_id', XenForo_Input::UINT));
		return $this->responseReroute('XenForo_ControllerPublic_EditHistory', 'index');
	}	
	
	/**
	 * Sends a report to the blog moderators. This is invoked from the entry using the "Report" action
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionReport()
	{
		list($comment, $entry) = $this->getCommentAndEntry();
		if (!$comment['perms']['canReport'])
		{
			return $this->responseNoPermission();
		}

		// means form submit
		if ($this->_request->isPost())
		{
			$message = $this->_input->filterSingle('message', XenForo_Input::STRING);
			if (!$message)
			{
				return $this->responseError(new XenForo_Phrase('xfa_blogs_please_enter_reason_for_reporting'));
			}

			/* @var $reportModel XenForo_Model_Report */
			$reportModel = XenForo_Model::create('XenForo_Model_Report');
			$reportModel->reportContent('xfa_blog_comment', $comment, $message);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xfa-blog-entry', $entry),
				new XenForo_Phrase('xfa_blogs_thank_you_for_reporting_this_comment')
			);
		}
		else
		{
			$viewParams = array(
				'comment' => $comment,
				'entry' => $entry
			);
			return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_comment_report', $viewParams);
		}		
	}	
	
	/**
	 * This controller does not track user activity
	 * @see XenForo_Controller::canUpdateSessionActivity()
	 */
	public function canUpdateSessionActivity($controllerName, $action, &$newState)
	{
		return false;
	}
	
	
	
	
}