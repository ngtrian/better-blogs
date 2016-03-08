<?php

/**
 * Attachment handler for blog entries
 * @package XenForo_Attachment
 */
class XfAddOns_Blogs_AttachmentHandler_Entry extends XenForo_AttachmentHandler_Abstract
{

	/**
	 * Key of primary content in content data array.
	 * @var string
	 */
	protected $_contentIdKey = 'entry_id';

	/**
	 * Route to get to an entry
	 * @var string
	 */
	protected $_contentRoute = 'xfa-blog-entry';

	/**
	 * Name of the phrase that describes the content type
	 * @var string
	 */
	protected $_contentTypePhraseKey = 'xfa_blogs_entry';

	/**
	 * Determines if attachments and be uploaded and managed in this context.
	 *
	 * @see XenForo_AttachmentHandler_Abstract::_canUploadAndManageAttachments()
	 */
	protected function _canUploadAndManageAttachments(array $contentData, array $viewingUser)
	{
		if (!empty($contentData['entry_id']))
		{
			/* @var $entryModel XfAddOns_Blogs_Model_Entry */
			$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
			$entry = $entryModel->getEntryById($contentData['entry_id']);
			$entryModel->prepareEntry($entry);
			return $entry['perms']['canEdit'];
		}
		if (!empty($contentData['blog_user_id']))
		{
			/* @var $blogModel XfAddOns_Blogs_Model_Blog */
			$blogModel = XenForo_Model::create('XfAddOns_Blogs_Model_Blog');
			$blog = $blogModel->getBlogForUser($contentData['blog_user_id']);
			return $blog['user_id'] == XenForo_Visitor::getUserId(); 
		}
		return false;
	}

	/**
	 * Determines if the specified attachment can be viewed.
	 *
	 * @see XenForo_AttachmentHandler_Abstract::_canViewAttachment()
	 */
	protected function _canViewAttachment(array $attachment, array $viewingUser)
	{
		return true;
	}

	/**
	 * Code to run after deleting an associated attachment.
	 *
	 * @see XenForo_AttachmentHandler_Abstract::attachmentPostDelete()
	 */
	public function attachmentPostDelete(array $attachment, Zend_Db_Adapter_Abstract $db)
	{
		// this would decrement the attachment count if existing
	}

	/**
	 * @see XenForo_AttachmentHandler_Abstract::_getContentRoute()
	 */
	protected function _getContentRoute()
	{
		return $this->_contentRoute;
	}
}