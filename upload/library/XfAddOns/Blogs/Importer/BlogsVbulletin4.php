<?php

class XfAddOns_Blogs_Importer_BlogsVbulletin4 extends XfAddOns_Blogs_Importer_BlogsVbulletin
{

	/**
	 * Content Type for the blogs in the attachments table
	 * @var int
	 */
	const BLOG_CONTENT_TYPE_ID = 19;
	
	/**
	 * The name of the importer is Social Groups for vBulletin
	 * @return string
	 */
	public static function getName()
	{
		return 'Better Blogs / from vBulletin 4.2.2';
	}
	
	/**
	 * Import all the blog attachments
	 * @see XenForo_Importer_vBulletin::stepAttachments($start, $options)
	 */
	public function stepAttachments($start, array $options)
	{
		$options = array_merge(array(
				'path' => isset($this->_config['blogsAttachmentPath']) ? $this->_config['blogsAttachmentPath'] : '',
				'limit' => 50,
				'max' => false
		), $options);
	
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
	
		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;
	
		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(attachmentid) FROM ' . $prefix . 'attachment WHERE contenttypeid = ' . XfAddOns_Blogs_Importer_BlogsVbulletin4::BLOG_CONTENT_TYPE_ID);
		}
	
		$attachments = $sDb->fetchAll($sDb->limit("
				SELECT attachmentid, userid, dateline, filename, counter, contentid, filedataid
				FROM " . $prefix . "attachment
				WHERE attachmentid > " . $sDb->quote($start) . "
					AND state = 'visible'
					AND contenttypeid = " . XfAddOns_Blogs_Importer_BlogsVbulletin4::BLOG_CONTENT_TYPE_ID . "
				ORDER BY attachmentid
			", $options['limit']
		));
	
		if (!$attachments)
		{
			return true;
		}
	
		$next = 0;
		$total = 0;
		$userIdMap = $this->_importModel->getImportContentMap('user');
		$entryMap = $this->_importModel->getEntriesMap();
	
		foreach ($attachments AS $attachment)
		{
			$next = $attachment['attachmentid'];
			$entryId = $this->_mapLookUp($entryMap, $attachment['contentid']);
			if (!$entryId)
			{
				continue;
			}
			$newUserId = $this->_mapLookUp($userIdMap, $attachment['userid'], 0);
			if (!$newUserId)
			{
				continue;
			}
			
			$attachFileOrig = "$options[path]/" . implode('/', str_split($attachment['userid'])) . "/$attachment[filedataid].attach";
			if (!file_exists($attachFileOrig))
			{
				// print "File does not exist: " . $attachFileOrig . "<br/>";
				continue;
			}
	
			$attachFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			copy($attachFileOrig, $attachFile);
	
			$success = $this->_importModel->importBlogAttachment(
					$attachment['attachmentid'],
					$this->_convertToUtf8($attachment['filename']),
					$attachFile,
					$newUserId,
					$entryId,
					$attachment['dateline'],
					array('view_count' => $attachment['counter'])
			);
			if ($success)
			{
				$total++;
			}
	
			@unlink($attachFile);
		}
	
		$this->_session->incrementStepImportTotal($total);
		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}	
	

}