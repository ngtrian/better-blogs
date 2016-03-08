<?php

/**
 * Some useful methods for inline moderation of comments
 */
class XfAddOns_Blogs_InlineMod_Comment
{

	/**
	 * Deletes all the comments for a particular user
	 * Returns true if everything went fine, false on exception
	 */
	public function deleteComments(array $comments, array $options, &$errorKey)
	{
		if (count($comments) <= 0)
		{
			return false;
		}
		
		try
		{
			if ($options['deleteType'] == 'hard')
			{
				$this->deleteHard($comments);
			}
			else
			{
				$this->deleteSoft($comments, $options);
			}
			return true;
		}
		catch (Exception $ex)
		{
			$errorKey = $ex->getMessage();
			return false;
		}
	}
	
	/**
	 * Undeletes all the comments for a particular user
	 * Returns true if everything went fine, false if we could not restore a particular comment
	 */
	public function undeleteComments(array $ids, $options = array(), &$errorKey)
	{
		if (count($ids) <= 0)
		{
			return true;
		}
	
		try
		{
			foreach ($ids as $commentId)
			{
				/* @var $dwComment XfAddOns_Blogs_DataWriter_Comment */
				$dwComment = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Comment');
				$dwComment->setExistingData($commentId);
				$dwComment->set('message_state', 'visible');
				$dwComment->save();
				
				// log delete action
				XenForo_Model_Log::logModeratorAction('xfa_blog_comment', $dwComment->getMergedData(), 'restore');
			}
			return true;
		}
		catch (Exception $ex)
		{
			$errorKey = $ex->getMessage();
			return false;
		}
	}	

	/**
	 * Change the message state to "delete", for soft deleting all the messages for a user
	 * @param array $user
	 */
	private function deleteSoft(array $comments, $options)
	{
		foreach ($comments as $comment)
		{
			$reason = ($options && isset($options['reason'])) ? $options['reason'] :  'MultipleDelete';
			
			/* @var $dwComment XfAddOns_Blogs_DataWriter_Comment */
			$dwComment = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Comment');
			$dwComment->setExistingData($comment['comment_id']);
			$dwComment->setExtraData(XfAddOns_Blogs_DataWriter_Comment::EXTRA_DELETE_REASON, $reason);
			$dwComment->set('message_state', 'deleted');
			$dwComment->save();
			
			// log delete action
			XenForo_Model_Log::logModeratorAction('xfa_blog_comment', $dwComment->getMergedData(), 'delete_soft', array('reason' => $reason));
		}
	}
	
	/**
	 * Hard remove of all the messages on the database for a particular user
	 * @param array $user
	 */
	private function deleteHard(array $comments)
	{
		foreach ($comments as $comment)
		{
			/* @var $dwComment XfAddOns_Blogs_DataWriter_Comment */
			$dwComment = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Comment');
			$dwComment->setExistingData($comment['comment_id']);
			$dwComment->delete();
			
			// log delete action
			XenForo_Model_Log::logModeratorAction('xfa_blog_comment', $dwComment->getMergedData(), 'delete_hard');
		}	
	}
	
	
}