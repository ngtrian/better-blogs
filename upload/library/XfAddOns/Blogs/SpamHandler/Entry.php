<?php

class XfAddOns_Blogs_SpamHandler_Entry extends XenForo_SpamHandler_Abstract
{

	/**
	 * Checks that the options array contains a non-empty 'delete_xfa_blog_entry' key
	 */
	public function cleanUpConditionCheck(array $user, array $options)
	{
		return !empty($options['delete_xfa_blog_entry']) || (isset($_REQUEST['delete_xfa_blog_entry']) && $_REQUEST['delete_xfa_blog_entry']);
	}
	
	/**
	 * @see XenForo_SpamHandler_Abstract::cleanUp()
	 */
	public function cleanUp(array $user, array &$log, &$errorKey)
	{
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		$entries = $entryModel->getBlogEntriesForUser($user, array());
		if (empty($entries))
		{
			return true;
		}
		
		$deleteType = (XenForo_Application::get('options')->spamMessageAction == 'delete' ? 'hard' : 'soft');
		$entryIds = array_keys($entries);
	
		$log['xfa_blog_entry'] = array(
			'deleteType' => $deleteType,
			'entryIds' => $entryIds
		);
		
		/* @var $inlineModModel XfAddOns_Blogs_InlineMod_Entry */
		$inlineModModel = $this->getModelFromCache('XfAddOns_Blogs_InlineMod_Entry');
		return $inlineModModel->deleteEntries($entries, array('deleteType' => $deleteType), $errorKey);
	}
	
	/**
	 * @see XenForo_SpamHandler_Abstract::restore()
	 */
	public function restore(array $log, &$errorKey = '')
	{
		// Sorry, no restore functionality. Once gone it's gone
	}
	
	
}