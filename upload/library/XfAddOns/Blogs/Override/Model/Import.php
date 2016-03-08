<?php

/**
 * Extends the default importer to add the blogs importer
 */
class XfAddOns_Blogs_Override_Model_Import extends XFCP_XfAddOns_Blogs_Override_Model_Import
{

	/**
	 * Adds the blog importer to the list
	 * @return array
	 */	
	public function __construct()
	{
		self::$extraImporters['XfAddOns_Blogs_Importer_BlogsVbulletin'] = 'XfAddOns_Blogs_Importer_BlogsVbulletin';
		self::$extraImporters['XfAddOns_Blogs_Importer_BlogsVbulletin4'] = 'XfAddOns_Blogs_Importer_BlogsVbulletin4';
		self::$extraImporters['XfAddOns_Blogs_Importer_LNBlogs'] = 'XfAddOns_Blogs_Importer_LNBlogs';
		self::$extraImporters['XfAddOns_Blogs_Importer_XIBlog'] = 'XfAddOns_Blogs_Importer_XIBlog';
		self::$extraImporters['XfAddOns_Blogs_Importer_IPS26x'] = 'XfAddOns_Blogs_Importer_IPS26x';
	}
	
	/**
	 * A simple override to make this function public. We will be calling this from the importer.
	 * It does not seem like a good idea to just create 4 delegates
	 */
	public function _importData($oldId, $dwName, $contentKey, $idKey, array $info, $errorHandler = false, $update = false)
	{
		return parent::_importData($oldId, $dwName, $contentKey, $idKey, $info, $errorHandler, $update);
	}
	
	/**
	 * Return a map of blogId => entryId
	 */
	public function getEntriesMap()
	{
		return $this->_getDb()->fetchPairs("
			SELECT old_id, new_id
			FROM xf_import_log
			WHERE content_type = 'blog_entry'
		");
	}

	/**
	 * Return a map of oldBlogId => newBlogId
	 */
	public function getBlogsMap()
	{
		return $this->_getDb()->fetchPairs("
			SELECT old_id, new_id
			FROM xf_import_log
			WHERE content_type = 'blog'
		");
	}	
	
	/**
	 * Return a map of oldCategoryId => newCategoryId
	 */
	public function getCategoriesMap()
	{
		return $this->_getDb()->fetchPairs("
			SELECT old_id, new_id
			FROM xf_import_log
			WHERE content_type = 'blog_category'
		");
	}

	/**
	 * Return a map of oldCategoryId => newCategoryId
	 */
	public function getGlobalCategoriesMap()
	{
		return $this->_getDb()->fetchPairs("
			SELECT old_id, new_id
			FROM xf_import_log
			WHERE content_type = 'blog_category_global'
		");		
	}
	
	/**
	 * Returns the new entry Id
	 */
	public function mapEntryId($oldEntryId)
	{
		$db = XenForo_Application::getDb();
		return $db->fetchOne("SELECT new_id FROM xf_import_log_blogs WHERE old_id = ? AND content_type='blog_entry'",
			array( $oldEntryId ));
	}	
	
	/**
	 * Imports an attachment from a blog
	 * @return Imported attachment ID
	 */
	public function importBlogAttachment($oldAttachmentId, $fileName, $tempFile, $userId, $entryId, $date, array $attach = array())
	{
		$upload = new XenForo_Upload($fileName, $tempFile);

		try
		{
			$dataExtra = array('upload_date' => $date, 'attach_count' => 1);
			$dataId = $this->getModelFromCache('XenForo_Model_Attachment')->insertUploadedAttachmentData($upload, $userId, $dataExtra);
		}
		catch (XenForo_Exception $e)
		{
			return false;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
		$dw->setImportMode(true);

		$dw->bulkSet(array(
			'data_id' => $dataId,
			'content_type' => 'xfa_blog_entry',
			'content_id' => $entryId,
			'attach_date' => $date,
			'unassociated' => 0
		));
		$dw->bulkSet($attach);	// view_count
		$dw->save();

		$newAttachmentId = $dw->get('attachment_id');
		$this->logImportData('xfa_blog_attachment', $oldAttachmentId, $newAttachmentId);

		return $newAttachmentId;
	}	
	
	
	
	
}