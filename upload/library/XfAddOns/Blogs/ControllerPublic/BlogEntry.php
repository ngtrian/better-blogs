<?php

/**
 * Controller that handles the actions on the blog details page
 */
class XfAddOns_Blogs_ControllerPublic_BlogEntry extends XfAddOns_Blogs_ControllerPublic_Abstract
{
	
	/**
	 * Index page. Displays all the blog entries for a particular user
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionIndex()
	{
		list($entry, $blog) = $this->getEntryAndBlog(XfAddOns_Blogs_Model_Blog::JOIN_READ_DATE);
		
		// check if we have permission to view the blog
		if (!$blog['perms']['canView'])
		{
			// If there is a specific error with the detail we return that, else we will return a generic message
			if (isset($blog['perms']['canViewPermissionDetail']))
			{
				return $this->responseError($blog['perms']['canViewPermissionDetail']);
			}
			return $this->responseNoPermission();
		}
		
		if (!$entry['perms']['canView'])
		{
			// If there is a specific error with the detail we return that, else we will return a generic message
			if (isset($entry['perms']['canViewPermissionDetail']))
			{
				return $this->responseError($entry['perms']['canViewPermissionDetail']);
			}			
			return $this->responseNoPermission();
		}		
		
		// do canonicalization
		$this->canonicalizeRequestUrl(
				XenForo_Link::buildPublicLink('xfa-blog-entry', $entry)
		);
		$this->canonicalizeDomain($blog);
		
		// weave in the attachments and categories
		$this->entryModel->getAndMergeAttachmentsIntoEntry($entry);
		$entry['categories'] = $this->categoryModel->getSelectedCategoriesForEntry($entry['entry_id']);
		
		// comments on the thread
		$fetchOptions = array(
			'join' => XfAddOns_Blogs_Model_Comment::JOIN_USER + XfAddOns_Blogs_Model_Comment::JOIN_DELETION_LOG +
					  XfAddOns_Blogs_Model_Comment::JOIN_LIKE_INFORMATION + XfAddOns_Blogs_Model_Comment::JOIN_BLOG_INFO 
			);
		$comments = $this->commentsModel->getCommentsForEntry($entry, $fetchOptions);
		$this->commentsModel->prepareComments($comments, $entry);
		
		// mark the entry as read
		$options = XenForo_Application::getOptions();
		$maxCommentDate = $this->commentsModel->getMaxCommentDate($comments);
		if ($maxCommentDate == -1)
		{
			$maxCommentDate = $entry['post_date'];
		}
		
		$visitorUserId = XenForo_Visitor::getUserId();
		$this->entryModel->markEntryAsRead($visitorUserId, $entry['entry_id'], $entry['entry_read_date'], $maxCommentDate);
		$this->blogModel->markBlogAsRead($visitorUserId, $blog['user_id'], $blog['blog_read_date'], $maxCommentDate);
		
		// register that someone viewed this entry
		/* @var $entryView XfAddOns_Blogs_Model_BlogEntryView */
		$entryView = XenForo_Model::create('XfAddOns_Blogs_Model_BlogEntryView');
		$entryView->registerEntryView($entry);
		
		// check if we have a saved draft on the quick reply
		/* @var $draftModel XenForo_Model_Draft */
		$draftModel = XenForo_Model::create('XenForo_Model_Draft');
		$key = 'xfa-blog-comment-' . $entry['entry_id'];
		$draft = $draftModel->getDraftByUserKey($key, XenForo_Visitor::getUserId());		
		
		$params = array(
			'entry' => $entry,
			'blog' => $blog,
			'comments' => $comments,
			'draft' => $draft,
			'panels' => $this->blogModel->getPanels($blog),
			'unreadThreshold' => XenForo_Application::$time - 86400 * $options->xfa_blogs_unreadThreshold,
			'showCustomization' => true
			);
		$containerParams = array(
			'isBlogContainer' => true,
			'containerTemplate' => ($options->xfa_blogs_blogMode != 'classic') ? 'xfa_blog_PAGE_CONTAINER_full_screen' : 'PAGE_CONTAINER',
			'blog' => $blog,
			'noVisitorPanel' => true,
			'showCustomization' => true
		);		
		
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Entry_Index', 'xfa_blog_entry', $params, $containerParams);
	}
	
	/**
	 * This method will redirect to the entry with an anchor for the latest comment that the person has read
	 */
	public function actionUnread()
	{
		list($entry, $blog) = $this->getEntryAndBlog();
		
		$comment = $this->commentsModel->getFirstCommentFromDate($entry, $entry['entry_read_date']);
		if (!$comment)
		{
			return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
					XenForo_Link::buildPublicLink('xfa-blog-entry', $entry)
			);
		}
		
		return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('xfa-blog-entry', $entry) . '#comment-' . $comment['comment_id']
			);
	}
	
	/**
	 * This is invoked using quick reply
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionAddReply()
	{
		list($entry, $blog) = $this->getEntryAndBlog();

		$visitor = XenForo_Visitor::getInstance();
		$userId = XenForo_Visitor::getUserId();
		
		// permissions check
		if (!$entry['perms']['canComment'])
		{
			return $this->responseNoPermission();
		}
		if (!$entry['allow_comments'])
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_comments_disabled'));
		}
		
		// get the message
		$helper = new XenForo_ControllerHelper_Editor($this);
		$message = $helper->getMessageText('message', $this->_input);
		$message = XenForo_Helper_String::autoLinkBbCode($message);
	
		if (empty($message))
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_please_type_message_reply_with'));
		}
	
		// create post info
		$comment = array(
			'entry_id' => $entry['entry_id'],
			'user_id' => $userId,
			'message' => $message,
			'post_date' => XenForo_Application::$time
		);
	
		// insert the new comment into the database
		$dwComment = null;
		try
		{
			XenForo_Db::beginTransaction();
	
			/* @var $dw XfAddOns_Blogs_DataWriter_Comment */
			$dwComment = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Comment');
			$dwComment->bulkSet($comment);
			$dwComment->setExtraData('entry', $entry);
			$dwComment->setExtraData('username', $visitor->get('username'));
			$dwComment->save();
			$comment['comment_id'] = $dwComment->get('comment_id');
	
			XenForo_Db::commit();
		}
		catch (Exception $ex)
		{
			XenForo_Db::rollback();
			throw $ex;	// throw back for error display
		}
		
		// Delete any drafts that we may have on insert
		if ($dwComment->isInsert())
		{
			$key = 'xfa-blog-comment-' . $entry['entry_id'];
			/* @var $draftModel XenForo_Model_Draft */
			$draftModel = XenForo_Model::create('XenForo_Model_Draft');
			$draftModel->deleteDraft($key);
		}
		
		// auto subscribe the user to the entry he just commented to
		/* @var $watchModel XfAddOns_Blogs_Model_BlogEntryWatch */
		$watchModel = XenForo_Model::create('XfAddOns_Blogs_Model_BlogEntryWatch');
		$watchModel->subscribeIfNotSubscribed($userId, $entry['entry_id']);
	
		// prepare the data
		$commentData = $dwComment->getMergedData('xfa_blog_comment');
		$this->commentsModel->prepareComment($commentData);
		
		// log the moderator action
		if ($dwComment->isUpdate())
		{
			XenForo_Model_Log::logModeratorAction('xfa_blog_comment', $commentData, 'edit', array());
		}
		
		return $this->responseView(
				'XfAddOns_Blogs_ViewPublic_NewReplies',
				'xfa_blog_comment',
				array( 'comment' => array_merge($commentData, $visitor->toArray()), 'showCustomization' => true )
		);
	}	

	/**
	 * Overlay for deleting an entry
	 */
	public function actionDeleteEntryOverlay()
	{
		list($entry, $blog) = $this->getEntryAndBlog();
	
		// check if the user has permission to delete the entry
		if (!$entry['perms']['canDelete'])
		{
			return $this->responseNoPermission();
		}
	
		$viewParams = array(
				'blog' => $blog,
				'entry' => $entry
		);
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_delete_entry_overlay', $viewParams);
	}

	/**
	 * This method is called when we need to delete an entry
	 */
	public function actionDeleteEntry()
	{
		list($entry, $blog) = $this->getEntryAndBlog();
	
		// check if the user has permission to delete the entry
		if (!$entry['perms']['canDelete'])
		{
			return $this->responseNoPermission();
		}
	
		// fetch the reason and proceed with the delete
		$reason = $this->_input->filterSingle('reason', XenForo_Input::STRING);
	
		/* @var $dwEntry XfAddOns_Blogs_DataWriter_Entry */
		$dwEntry = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Entry');
		$dwEntry->setExistingData($entry['entry_id']);
		$dwEntry->setExtraData(XfAddOns_Blogs_DataWriter_Entry::EXTRA_DELETE_REASON, $reason);
		$dwEntry->set('message_state', 'deleted');
		
		$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::INT);
		if ($hardDelete == 1)
		{
			$dwEntry->delete();
		}
		else
		{
			$dwEntry->save();
		}
		
		$deleteType = $hardDelete ? 'hard' : 'soft';
		XenForo_Model_Log::logModeratorAction('xfa_blog_entry', $entry, 'delete_' . $deleteType, array('reason' => $reason));
		
		// if it happens that the deleted entry was the last one, we need to update the last_entry count
		if ($dwEntry->get('post_date') == $blog['last_entry'])
		{
			$this->blogModel->updateLastEntry($blog);
		}
	
		// add the delete information to the entry
		$visitor = XenForo_Visitor::getInstance();
		$entry = array_merge($entry, array(
				'delete_user_id' => $visitor->get('user_id'),
				'delete_username' => $visitor->get('username'),
				'delete_date' => XenForo_Application::$time,
				'delete_reason' => $reason
		));
		// prepare the entry to parse the "deleteInfo" array
		$this->entryModel->prepareEntry($entry);
	
		// params will be the template that will be rendered
		$userData = XenForo_Visitor::getInstance()->toArray();
		$extraParams = array(
			'entry_id' => $entry['entry_id'],
			'entry' => new XenForo_Template_Public('xfa_blog_entry_deleted', array(
					'blog' => $blog,
					'entry' => $entry,
					'visitor' => $userData,
					'showCustomization' => true
			)));
		
		// doesn't make sense to display the deleted entry if it was permanently removed
		if ($hardDelete == 1)
		{
			$extraParams['entry'] = ' ';
		}
	
		// return to the thread
		return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xfa-blogs', $blog),
				new XenForo_Phrase('xfa_blogs_entry_has_been_deleted'),
				$extraParams
		);
	}	
	
	/**
	 * Action called when we want to see an entry that has been deleted
	 */
	public function actionShowEntry()
	{
		list($entry, $blog) = $this->getEntryAndBlog();
	
		// check if the user has permission to delete the entry
		if (!$entry['perms']['canViewDeleted'])
		{
			return $this->responseNoPermission();
		}
	
		$viewParams = array(
				'blog' => $blog,
				'entry' => $entry,
				'showCustomization' => true
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Entry_Show', 'xfa_blog_entry_bit', $viewParams);
	}

	/**
	 * This action will show the entry and restore it in the process
	 */
	public function actionRestoreEntry()
	{
		list($entry, $blog) = $this->getEntryAndBlog();
	
		// check if the user has permission to restore the entry
		if (!$entry['perms']['canRestore'])
		{
			return $this->responseNoPermission();
		}

		/* @var $dwEntry XfAddOns_Blogs_DataWriter_Entry */
		$dwEntry = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Entry');
		$dwEntry->setExistingData($entry['entry_id']);
		$dwEntry->set('message_state', 'visible');
		$dwEntry->save();
		
		// Log Moderator action
		XenForo_Model_Log::logModeratorAction('xfa_blog_entry', $entry, 'restore', array());
		
		// render the entry		
		$viewParams = array(
			'blog' => $blog,
			'entry' => $entry,
			'showCustomization' => true
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Entry_Show', 'xfa_blog_entry_bit', $viewParams);
	}	
	
	/**
	 * Called when we want to like an entry
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLike()
	{
		list($entry, $blog) = $this->getEntryAndBlog();
		if (!$entry['perms']['canLike'])
		{
			return $this->responseNoPermission();
		}
	
		/* @var $likeModel XenForo_Model_Like */
		$likeModel = $this->getModelFromCache('XenForo_Model_Like');
		$existingLike = $likeModel->getContentLikeByLikeUser('xfa_blog_entry', $entry['entry_id'], XenForo_Visitor::getUserId());
	
		// change the like status
		if ($existingLike)
		{
			$latestUsers = $likeModel->unlikeContent($existingLike);
		}
		else
		{
			$latestUsers = $likeModel->likeContent('xfa_blog_entry', $entry['entry_id'], $entry['user_id']);
		}
	
		$liked = ($existingLike ? false : true);
	
		$entry['likeUsers'] = $latestUsers;
		$entry['likes'] += ($liked ? 1 : -1);
		$entry['like_date'] = ($liked ? XenForo_Application::$time : 0);
	
		$viewParams = array(
			'entry' => $entry,
			'liked' => $liked,
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Entry_LikeConfirmed', '', $viewParams);
	}	
	
	/**
	 * This will add an entry for the current user to watch the entry
	 */
	public function actionWatch()
	{
		list($entry, $blog) = $this->getEntryAndBlog();
		if (!$blog['perms']['canView'])
		{
			return $this->responseNoPermission();
		}		
		
		// well, anonymous users can't really subscribe
		$userId = XenForo_Visitor::getUserId();
		if (!$userId)
		{
			return $this->responseNoPermission();
		}
		
		/* @var $watchModel XfAddOns_Blogs_Model_BlogEntryWatch */
		$watchModel = XenForo_Model::create('XfAddOns_Blogs_Model_BlogEntryWatch');
		$watch = $watchModel->getWatch($userId, $entry['entry_id']);
		if (!$watch)
		{
			/* @var $dwWatch XfAddOns_Blogs_DataWriter_BlogEntryWatch */
			$dwWatch = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_BlogEntryWatch');
			$dwWatch->set('user_id', $userId);
			$dwWatch->set('entry_id', $entry['entry_id']);
			$dwWatch->save();			
		}
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blogs', $blog),
			new XenForo_Phrase('xfa_blogs_you_are_now_watching_this_entry')
		);		
	}
	
	/**
	 * This will remove the entry for a watched entry
	 */
	public function actionUnwatch()
	{
		list($entry, $blog) = $this->getEntryAndBlog();
		if (!$blog['perms']['canView'])
		{
			return $this->responseNoPermission();
		}		
		
		// well, anonymous users can't really unsubscribe
		$userId = XenForo_Visitor::getUserId();
		if (!$userId)
		{
			return $this->responseNoPermission();
		}	
		
		// gets the data for the watch
		/* @var $watchModel XfAddOns_Blogs_Model_BlogEntryWatch */
		$watchModel = XenForo_Model::create('XfAddOns_Blogs_Model_BlogEntryWatch');
		$watch = $watchModel->getWatch($userId, $entry['entry_id']);
		if (!$watch)
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_you_are_not_watching_this_entry'));
		}

		/* @var $dwWatch XfAddOns_Blogs_DataWriter_BlogEntryWatch */
		$dwWatch = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_BlogEntryWatch');
		$dwWatch->setExistingData($watch, true);
		$dwWatch->delete();
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blogs', $blog),
			new XenForo_Phrase('xfa_blogs_you_are_no_longer_watching_this_entry')
		);
	}	
	
	/**
	 * List of everyone that liked this.
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLikes()
	{
		list($entry, $blog) = $this->getEntryAndBlog();
		if (!$blog['perms']['canView'])
		{
			return $this->responseNoPermission();
		}

		/* @var $likeModel XenForo_Model_Like */
		$likeModel = $this->getModelFromCache('XenForo_Model_Like');
		$likes = $likeModel->getContentLikes('xfa_blog_entry', $entry['entry_id']);
		if (!$likes)
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_nobody_has_liked_this_entry_yet'));
		}
	
		$viewParams = array(
				'post' => $entry,
				'likes' => $likes
		);
		return $this->responseView('XenForo_ViewPublic_Post_Likes', 'xfa_blog_entry_likes', $viewParams);
	}	

	/**
	 * Displays the IP associated with the entry
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIp()
	{
		list($entry, $blog) = $this->getEntryAndBlog();
		if (!XenForo_Visitor::getInstance()->hasPermission('general', 'viewIps'))
		{
			return $this->responseNoPermission();
		}
		if (!$entry['ip_id'])
		{
			return $this->responseError(new XenForo_Phrase('no_ip_information_available'));
		}

		$viewParams = array(
			'entry' => $entry,
			'ipInfo' => $this->getModelFromCache('XenForo_Model_Ip')->getContentIpInfo($entry)
		);

		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_entry_ip', $viewParams);
	}
	
	/**
	 * Sends a report to the blog moderators. This is invoked from the entry using the "Report" action
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionReport()
	{
		list($entry, $blog) = $this->getEntryAndBlog();
		if (!$entry['perms']['canReport'])
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
			$reportModel->reportContent('xfa_blog_entry', $entry, $message);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xfa-blog-entry', $entry),
				new XenForo_Phrase('xfa_blogs_thank_you_for_reporting_this_entry')
			);
		}
		else
		{
			$viewParams = array(
				'blog' => $blog,
				'entry' => $entry
			);
			return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_entry_report', $viewParams);
		}		
	}
	
	/**
	 * Action called to save the draft of the comment being written
	 */
	public function actionSaveDraft()
	{
		list($entry, $blog) = $this->getEntryAndBlog();
		$key = 'xfa-blog-comment-' . $entry['entry_id'];
	
		$message = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$forceDelete = $this->_input->filterSingle('delete_draft', XenForo_Input::BOOLEAN);
	
		/* @var $draftModel XenForo_Model_Draft */
		$draftModel = XenForo_Model::create('XenForo_Model_Draft');
	
		if (!strlen($message) || $forceDelete)
		{
			$draftSaved = false;
			$draftDeleted = $draftModel->deleteDraft($key) || $forceDelete;
		}
		else
		{
			$draftModel->saveDraft($key, $message);
			$draftSaved = true;
			$draftDeleted = false;
		}
	
		$viewParams = array(
			'draftSaved' => $draftSaved,
			'draftDeleted' => $draftDeleted
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Comment_SaveDraft', '', $viewParams);
	}	

	/**
	 * View the entry history
	 */
	public function actionHistory()
	{
		$this->_request->setParam('content_type', 'xfa_blog_entry');
		$this->_request->setParam('content_id', $this->_input->filterSingle('entry_id', XenForo_Input::UINT));
		return $this->responseReroute('XenForo_ControllerPublic_EditHistory', 'index');
	}	
	
	/**
	 * Gets session activity details of activity records that are pointing to this controller.
	 * This must check the visiting user's permissions before returning item info.
	 * Return value may be:
	 * 		* false - means page is unknown
	 * 		* string/XenForo_Phrase - gives description for all, but no item details
	 * 		* array (keyed by activity keys) of strings/XenForo_Phrase objects - individual description, no item details
	 * 		* array (keyed by activity keys) of arrays. Sub-arrays keys: 0 = description, 1 = specific item title, 2 = specific item url.
	 *
	 * @param array $activities List of activity records
	 *
	 * @return mixed See above.
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		$entryIds = array();
		foreach ($activities AS $key => $activity)
		{
			if (!empty($activity['params']['entry_id']))
			{
				$entryIds[] = $activity['params']['entry_id'];
			}
		}
	
		$entryMap = array();
		if (!empty($entryIds))
		{
			/* @var $entryModel XfAddOns_Blogs_Model_Entry */
			$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
			$entryMap = $entryModel->getEntriesByIds($entryIds);
			$entryModel->wireBlogs($entryMap);
			$entryModel->prepareEntries($entryMap);
		}
	
		// generate the output
		$output = array();
		foreach ($activities AS $key => $activity)
		{
			if (!empty($activity['params']['entry_id']) && isset($entryMap[$activity['params']['entry_id']]))
			{
				$entry = $entryMap[$activity['params']['entry_id']];
				if (!$entry['perms']['canView'])
				{
					$output[$key] = new XenForo_Phrase('xfa_blogs_viewing_private_blog_entry');
					continue;
				}
				if (isset($entry['blog']) && !$entry['blog']['perms']['canView'])
				{
					$output[$key] = new XenForo_Phrase('xfa_blogs_viewing_private_blog');
					continue;
				}
				$output[$key] = array(
					new XenForo_Phrase('xfa_blogs_viewing_blog_entry'),
					$entry['title'],
					XenForo_Link::buildPublicLink('xfa-blog-entry', $entry),
					false
				);
			}
			else
			{
				$output[$key] = new XenForo_Phrase('xfa_blogs_viewing_blog_entry');
			}
		}
		return $output;
	}	
	

	
	
}