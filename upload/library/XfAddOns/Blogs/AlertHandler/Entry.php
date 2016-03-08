<?php

/**
 * Class used to parse the entry alerts
 */
class XfAddOns_Blogs_AlertHandler_Entry extends XenForo_AlertHandler_Abstract
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
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Entry::JOIN_DELETION_LOG;
		$entries = $entryModel->getEntriesByIds($contentIds, $fetchOptions);
		$entryModel->prepareEntries($entries);
		return $entries;
	}

	/**
	 * Determines if the entry is viewable.
	 * @see XenForo_AlertHandler_Abstract::canViewAlert()
	 */
	public function canViewAlert(array $alert, $content, array $viewingUser)
	{
		return isset($content['perms']) ? $content['perms']['canView'] : true;
	}

}