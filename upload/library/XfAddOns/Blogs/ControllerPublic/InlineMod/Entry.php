<?php

/**
 * Inline moderation actions for entries
 * @package XenForo_Thread
 */
class XfAddOns_Blogs_ControllerPublic_InlineMod_Entry extends XenForo_ControllerPublic_InlineMod_Abstract
{
	
	public $inlineModKey = 'entries';
	
	/**
	 * @return XfAddOns_Blogs_InlineMod_Entry
	 */
	public function getInlineModTypeModel()
	{
		return $this->getModelFromCache('XfAddOns_Blogs_InlineMod_Entry');
	}	
	
	/**
	 * Entry deletion handler.
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$entryIds = $this->getInlineModIds(false);
			$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::STRING);
			$options = array(
				'deleteType' => ($hardDelete ? 'hard' : 'soft'),
				'reason' => $this->_input->filterSingle('reason', XenForo_Input::STRING)
			);
			
			// transform to full objects
			$entries = $this->getEntries($entryIds);
			$this->checkForDeletePermissions($entries);

			// proceed with deleting the entries
			$model = $this->getInlineModTypeModel();
			$errorKey = '';
			$deleted = $model->deleteEntries($entries, $options, $errorKey);
			
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
			$entryIds = $this->getInlineModIds();
			$entries = $this->getEntries($entryIds);
			
			// check that we can delete all entries that we are requesting to delete
			$this->checkForDeletePermissions($entries);
			
			if (empty($entryIds))
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}
			
			$viewParams = array(
				'entryIds' => $entryIds,
				'entryCount' => count($entryIds),
				'canHardDelete' => $this->canHardDelete(),
				'redirect' => $this->getDynamicRedirect()
			);
	
			return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blogs_inline_mod_entry_delete', $viewParams);
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
		$entryIds = $this->getInlineModIds(false);
		$entries = $this->getEntries($entryIds);
		$this->checkForDeletePermissions($entries);
		
		// delegate for inlineMod
		$options = array(
			'entries' => $entries
		);
		return $this->executeInlineModAction('undeleteEntries', $options);
	}	

	/**
	 * Retrieve the complete entry objects. This will get a list of ids, retrieve, and prepare the entries
	 */
	protected function getEntries($entryIds)
	{
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Entry::JOIN_VISITOR_FOLLOW;
		
		$entries = $entryModel->getEntriesByIds($entryIds, $fetchOptions);
		$entryModel->prepareEntries($entries);
		return $entries;
	}
	
	/**
	 * Checks that the user has permission to delete all the entries mentioned in the list
	 * @param array $entries		Array of entries
	 */
	protected function checkForDeletePermissions($entries)
	{
		if (empty($entries))
		{
			return;
		}
		foreach ($entries as $entry)
		{
			if (!$entry['perms']['canDelete'])
			{
				$error = new XenForo_Phrase('xfa_blogs_delete_no_permission_entry_x', array('title' => $entry['title']));
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