<?php

class XfAddOns_Blogs_ModeratorLog_Entry extends XenForo_ModeratorLogHandler_Abstract
{

	protected $_skipLogSelfActions = array(
		'edit', 'delete_soft', 'delete_hard', 'restore'
	);
	
	protected function _log(array $logUser, array $content, $action, array $actionParams = array(), $parentContent = null)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ModeratorLog');
		$dw->bulkSet(array(
				'user_id' => $logUser['user_id'],
				'content_type' => 'xfa_blog_entry',
				'content_id' => $content['entry_id'],
				'content_user_id' => $content['user_id'],
				'content_username' => isset($content['username']) ? $content['username'] : 'Unknown',
				'content_title' => $content['title'],
				'content_url' => XenForo_Link::buildPublicLink('xfa-blog-entry', $content),
				'discussion_content_type' => 'xfa_blog',
				'discussion_content_id' => $content['user_id'],
				'action' => $action,
				'action_params' => $actionParams
		));
		$dw->save();
	
		return $dw->get('moderator_log_id');
	}
	
	protected function _prepareEntry(array $entry)
	{
		/* @var $userModel XenForo_Model_User */
		$userModel = XenForo_Model::create('XenForo_Model_User');
		$user = $userModel->getUserById($entry['user_id']);
		
		$entry['username'] = $user['username'];
		return $entry;
	}	
	
	
}