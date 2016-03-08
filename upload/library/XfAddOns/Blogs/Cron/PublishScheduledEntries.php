<?php

class XfAddOns_Blogs_Cron_PublishScheduledEntries
{
	
	/**
	 * @var XfAddOns_Blogs_Model_EntryScheduled
	 */
	private $scheduledModel;
	
	/**
	 * @var XfAddOns_Blogs_Model_Rebuild
	 */
	private $rebuildModel;

	/**
	 * @var XenForo_Model_Attachment
	 */
	private $attachmentModel;
	
	/**
	 * Constructor. Initializes the models and classes used.
	 */
	public function __construct()
	{		
		$options = XenForo_Application::getOptions();
		if (!$options->blogAdvancedFeatures)
		{
			return;
		}
		$this->scheduledModel = XenForo_Model::create('XfAddOns_Blogs_Model_EntryScheduled');
		$this->rebuildModel = XenForo_Model::create('XfAddOns_Blogs_Model_Rebuild');
		$this->attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');
	}
	
	/**
	 * Method that is called to publish the entries. This method creates an instance of this class
	 * to start the publishing process
	 */
	public static function run()
	{
		$options = XenForo_Application::getOptions();
		if (!$options->blogAdvancedFeatures)
		{
			return;
		}
		$publisher = new XfAddOns_Blogs_Cron_PublishScheduledEntries();
		$publisher->publishScheduled();
	}
	
	/**
	 * This method will publish any entries that are in the queue and that are set to be published now
	 * (because we reached the given time for publishing)
	 */
	public function publishScheduled()
	{
		$entries = $this->scheduledModel->getScheduledEntriesForPublish();
		if (empty($entries))
		{
			return;
		}
		
		// add additional information
		$this->scheduledModel->getAndMergeAttachmentsIntoEntries($entries);
		
		// iterate and publish the scheduled entries
		$rebuildBlogs = array();
		foreach ($entries as $entry)
		{
			XenForo_Db::beginTransaction();
			try
			{
				$this->publishEntry($entry);
				$this->removeScheduled($entry);
			}
			catch (Exception $ex)
			{
				XenForo_Error::logException($ex, false);
				XenForo_Db::rollback();
				continue;		
			}
			$rebuildBlogs[$entry['user_id']] = true;
			XenForo_Db::commit();
		}
		
		if (!empty($rebuildBlogs))
		{
			$this->rebuildBlogs(array_keys($rebuildBlogs));	
		}
	}
	
	/**
	 * Publish an individual entry that was scheduled for the future
	 * @param array $scheduledEntry	The information of the scheduled entry to publish
	 */
	private function publishEntry(array $scheduledEntry)
	{
		$categories = @unserialize($scheduledEntry['categories']);
		$categories = is_array($categories) ? $categories : array(); 
		
		$dwEntry = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Entry');
		$dwEntry->set('user_id', $scheduledEntry['user_id']);
		$dwEntry->set('title', $scheduledEntry['title']);
		$dwEntry->set('post_date', $scheduledEntry['post_date']);
		$dwEntry->set('message', $scheduledEntry['message']);
		$dwEntry->set('ip_id', $scheduledEntry['ip_id']);
		$dwEntry->set('allow_comments', $scheduledEntry['allow_comments']);
		$dwEntry->set('allow_view_entry', $scheduledEntry['allow_view_entry']);
		if (!empty($scheduledEntry['allow_members_ids']) && is_string($scheduledEntry['allow_members_ids']))
		{
			$dwEntry->set('allow_members_ids', $scheduledEntry['allow_members_ids']);
		}
		$dwEntry->setExtraData(XfAddOns_Blogs_DataWriter_Entry::EXTRA_DATA_NEW_CATEGORIES, $categories);
		$dwEntry->save();
		$entry = $dwEntry->getMergedData();
		
		if (!empty($scheduledEntry['attachments']))
		{
			foreach ($scheduledEntry['attachments'] as $attachment)
			{
				$this->publishAttachment($attachment, $entry);
			}
		}
		
	}

	/**
	 * Publishes an attachment. Method used to move the attachment from the scheduled entry to the real entry
	 * @return Imported attachment ID
	 */
	private function publishAttachment(array $attachment, array $entry)
	{
		$attachFileOrig = $this->attachmentModel->getAttachmentDataFilePath($attachment);
		if (!file_exists($attachFileOrig))
		{
			return;
		}

		// create a new temporary file
		$attachFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
		copy($attachFileOrig, $attachFile);

		// create the upload process
		$upload = new XenForo_Upload($attachment['filename'], $attachFile);

		try
		{
			$dataExtra = array('upload_date' => $attachment['attach_date'], 'attach_count' => 1);
			$dataId = $this->attachmentModel->insertUploadedAttachmentData($upload, $entry['user_id'], $dataExtra);
		}
		catch (XenForo_Exception $e)
		{
			return;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
		$dw->bulkSet(array(
			'data_id' => $dataId,
			'content_type' => 'xfa_blog_entry',
			'content_id' => $entry['entry_id'],
			'attach_date' => $attachment['attach_date'],
			'unassociated' => 0
		));
		$dw->save();
	}
	
	/**
	 * This will remove the scheduled entry. This is called after we finished successfully publishing the new entry
	 * and will remove the record for the scheduled one
	 * 
	 * @param array $scheduledEntry		The information for the scheduled entry
	 */
	private function removeScheduled(array $scheduledEntry)
	{
		/* @var $dwScheduled XfAddOns_Blogs_DataWriter_EntryScheduled */
		$dwScheduled = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_EntryScheduled');
		$dwScheduled->setExistingData($scheduledEntry['scheduled_entry_id']);
		$dwScheduled->delete();
	}
	
	/**
	 * This method will rebuild all the blogs that had an entry added. This is needed because we need to recalculate the "position"
	 * of the entries
	 * @param array $blogIds		An array of the ids that we will rebuild
	 */
	private function rebuildBlogs($blogIds)
	{
		foreach ($blogIds as $id)
		{
			$this->rebuildModel->rebuildEntryPositionIndex($id);
		}
	}
	
	
}
