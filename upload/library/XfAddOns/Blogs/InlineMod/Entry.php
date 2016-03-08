<?php

/**
 * Some useful methods for inline moderation of entries
 */
class XfAddOns_Blogs_InlineMod_Entry
{

	/**
	 * Deletes all the entries for a particular user
	 * Returns true if everything went fine, false on exception
	 */
	public function deleteEntries(array $entries, array $options, &$errorKey)
	{
		if (count($entries) <= 0)
		{
			return false;
		}
		
		try
		{
			if ($options['deleteType'] == 'hard')
			{
				$this->deleteHard($entries);
			}
			else
			{
				$this->deleteSoft($entries, $options);
			}
			
			$this->updateLastBlogData($entries);
			return true;
		}
		catch (Exception $ex)
		{
			$errorKey = $ex->getMessage();
			return false;
		}
	}
	
	/**
	 * Undeletes all the entries for a particular user
	 * Returns true if everything went fine, false if we could not restore a particular entry
	 */
	public function undeleteEntries(array $ids, $options = array(), &$errorKey)
	{
		if (count($ids) <= 0)
		{
			return true;
		}		
		
		try
		{
			foreach ($ids as $entryId)
			{
				/* @var $dwEntry XfAddOns_Blogs_DataWriter_Entry */
				$dwEntry = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Entry');
				$dwEntry->setExistingData($entryId);
				$dwEntry->set('message_state', 'visible');
				$dwEntry->save();
				
				// log delete action
				XenForo_Model_Log::logModeratorAction('xfa_blog_entry', $dwEntry->getMergedData(), 'restore');
			}
				
			$this->updateLastBlogData($options['entries']);	// small optimization, since entries was already retrieved
			return true;
		}
		catch (Exception $ex)
		{
			$errorKey = $ex->getMessage();
			return false;
		}	
	}
	
	/**
	 * Update the information for last blog
	 * @param array $entries	The array of entries with different blogs to update
	 */
	private function updateLastBlogData(array $entries) 
	{
		/* @var $blogModel XfAddOns_Blogs_Model_Blog */
		$blogModel = XenForo_Model::create('XfAddOns_Blogs_Model_Blog');
		
		$blogIds = $this->getBlogIds($entries);
		foreach ($blogIds as $id)
		{
			$blog = array('user_id' => $id);
			$blogModel->updateLastEntry($blog);
		}
	}
	
	/**
	 * Return the ids for the blogs referenced in a set of entries
	 * @param array $entries	An array of entries
	 */
	private function getBlogIds(array $entries)
	{
		$ids = array();
		foreach ($entries as $entry)
		{
			$ids[] = $entry['user_id'];
		}
		return array_unique($ids);
	}

	/**
	 * Change the message state to "delete", for soft deleting all the messages for a user
	 * @param array $user
	 */
	private function deleteSoft(array $entries, $options)
	{
		foreach ($entries as $entry)
		{
			$reason = ($options && isset($options['reason'])) ? $options['reason'] :  'MultipleDelete';
			
			/* @var $dwEntry XfAddOns_Blogs_DataWriter_Entry */
			$dwEntry = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Entry');
			$dwEntry->setExistingData($entry['entry_id']);
			$dwEntry->setExtraData(XfAddOns_Blogs_DataWriter_Entry::EXTRA_DELETE_REASON, $reason);
			$dwEntry->set('message_state', 'deleted');
			$dwEntry->save();
			
			// log delete action
			XenForo_Model_Log::logModeratorAction('xfa_blog_entry', $dwEntry->getMergedData(), 'delete_soft', array('reason' => $reason));
		}
	}
	
	/**
	 * Hard remove of all the messages on the database for a particular user
	 * @param array $user
	 */
	private function deleteHard(array $entries)
	{
		foreach ($entries as $entry)
		{
			/* @var $dwEntry XfAddOns_Blogs_DataWriter_Entry */
			$dwEntry = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Entry');
			$dwEntry->setExistingData($entry['entry_id']);
			$dwEntry->delete();
			
			// log delete action
			XenForo_Model_Log::logModeratorAction('xfa_blog_entry', $dwEntry->getMergedData(), 'delete_hard');
		}		
	}
	
	
}