<?php

class XfAddOns_Blogs_Importer_LNBlogs extends XenForo_Importer_Abstract
{

	/**
	 * The name of the importer is Social Groups for vBulletin
	 * @return string
	 */
	public static function getName()
	{
		return 'Better Blogs / from LN Blogs';
	}

	/**
	 * Constructor. Initializes some variables
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_charset = 'utf-8';
	}	
	
	public function retainKeysReset()
	{
		// do not call parent, it deletes forums
	}	
	
	/**
	 * Extends the base vBulletin configure method. We have our own config_set key to identify
	 * when we are ready to begin importing
	 *
	 * @param XenForo_ControllerAdmin_Abstract $controller
	 * @param array $config
	 * @return unknown
	 */
	public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
	{
		$xfConfig = XenForo_Application::getConfig();
		$config = array( 
			'db' => array(
				'host' => $xfConfig->db->host,
				'port' =>  $xfConfig->db->port,
				'username' => $xfConfig->db->username,
				'password' => $xfConfig->db->password,
				'dbname' =>  $xfConfig->db->dbname,
				'charset' => ''
			),
			'retain_keys' => 0
		);
		return true;		// since it's the same database, we don't need to ask for credentials
	}

	/**
	 * Returns the steps that this importer contain
	 * @return array
	 */
	public function getSteps()
	{
		return array(
			'blogs' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_blogs')
			),
			'entries' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_entries'),
				'depends' => array( 'blogs' )
			),
			'comments' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_comments'),
				'depends' => array( 'entries' )
			),
			'attachments' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_attachments'),
				'depends' => array ('entries' )
			),
			'totals' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_update_blog_counters'),
				'depends' => array( 'entries', 'comments' )
			),
			'positionEntries' => array(
				'title' => new XenForo_Phrase('xfa_blogs_rebuild_entries_indexes'),
				'depends' => array( 'entries', 'totals' )
			),
			'positionComments' => array(
				'title' => new XenForo_Phrase('xfa_blogs_rebuild_comments_indexes'),
				'depends' => array( 'comments', 'totals' )
			)								
		);
	}
	
	/**
	 * Import the blogs information
	 *
	 * @param int $start		Index at which to start the import
	 * @param array $options	Any options that were set for the step
	 * @return array
	 */
	public function stepBlogs($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 500,
			'max' => false
		), $options);

		$sDb = $this->_db;
		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(user_id) FROM xf_user');
		}

		// pull categories
		$rows = $sDb->fetchAll($sDb->limit("
			SELECT
				xf_user.*, lnblog_setting.about_author
			FROM xf_user
			LEFT JOIN lnblog_setting ON xf_user.user_id = lnblog_setting.user_id 
			WHERE xf_user.user_id > " . $sDb->quote($start) . "
			ORDER BY
				xf_user.user_id	
			", $options['limit']
		));
		if (!$rows)
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;
		foreach ($rows AS $blog)
		{
			$next = $blog['user_id'];
			
			$info = array(
					'user_id' => $blog['user_id'],
					'blog_title' => $blog['username'],
					'description' => $this->_convertToUtf8($blog['about_author'])
					);
			
			$newId = false;
			try
			{
				$newId = $this->_importModel->_importData($blog['user_id'], 'XfAddOns_Blogs_DataWriter_Blog', 'ln_blog', 'user_id', $info);
				if ($newId)
				{
					$total++;
				}
			}
			catch (Exception $ex)
			{
				XenForo_Db::rollback();		// needed, because the importData() method does not rollback on exception
				XenForo_Error::logException($ex, false);
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);
		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}
	
	/**
	 * Import all the entries information
	 *
	 * @param int $start		Index at which to start the import
	 * @param array $options	Any options that were set for the step
	 * @return array
	 */
	public function stepEntries($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 1000,
			'max' => false
		), $options);

		$sDb = $this->_db;
		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(entry_id) FROM lnblog_entry');
		}

		// pull entries
		$rows = $sDb->fetchAll($sDb->limit("
			SELECT
			    entry_id, user_id, title, entry_date, message, message_state, likes, like_users
			FROM lnblog_entry entry
				WHERE
				entry.entry_id > " . $sDb->quote($start) . "
			ORDER BY
				entry.entry_id 			
			", $options['limit']
		));
		if (!$rows)
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;
		foreach ($rows AS $entry)
		{
			$next = $entry['entry_id'];
			$info = array(
					'user_id' => $entry['user_id'],
					'title' => $this->_convertToUtf8($entry['title']),
					'post_date' => $entry['entry_date'],
					'message' => $this->_convertToUtf8($entry['message']),
					'message_state' => ($entry['message_state'] == 'visible' ? "visible" : "deleted"),
					'likes' => $entry['likes'],
					'like_users' => $entry['like_users']
					);
			
			$newId = $this->_importModel->_importData($entry['entry_id'], 'XfAddOns_Blogs_DataWriter_Entry', 'ln_entry', 'entry_id', $info);
			if ($newId)
			{
				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);
		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}
	
	/**
	 * Import all the comments
	 *
	 * @param int $start		Index at which to start the import
	 * @param array $options	Any options that were set for the step
	 * @return array
	 */
	public function stepComments($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 2000,
			'max' => false
		), $options);

		$sDb = $this->_db;
		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(comment_id) FROM lnblog_comment');
		}
		
		// pull categories
		$rows = $sDb->fetchAll($sDb->limit("
			SELECT
			    comment_id, entry_id old_entry_id, user_id, comment_date, message, message_state, likes, like_users
			FROM lnblog_comment comment
			WHERE
				comment_id > " . $sDb->quote($start) . "	
			ORDER BY
				comment_id		
			", $options['limit']
		));
		if (!$rows)
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;
		$entryMap = $this->_importModel->getImportContentMap('ln_entry');

		foreach ($rows AS $comment)
		{
			$next = $comment['comment_id'];
			$entryId = $this->_mapLookUp($entryMap, $comment['old_entry_id']);
			if (!$entryId)
			{
				continue;
			}			
			
			$info = array(
					'entry_id' => $entryId,
					'user_id' => $comment['user_id'],
					'post_date' => $comment['comment_date'],
					'message' => $this->_convertToUtf8($comment['message']),
					'message_state' => ($comment['message_state'] == 'visible' ? "visible" : "deleted"),
					'likes' => $comment['likes'],
					'like_users' => $comment['like_users']
					);
			
			$newId = $this->_importModel->_importData($comment['comment_id'], 'XfAddOns_Blogs_DataWriter_Comment', 'ln_comment', 'comment_id', $info);
			if ($newId)
			{
				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);
		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}	
	
	
	/**
	 * Import all the blog attachments
	 * @see XenForo_Importer_vBulletin::stepAttachments($start, $options)
	 */
	public function stepAttachments($start, array $options)
	{
		$options = array_merge(array(
			'path' => './internal_data/attachments',
			'limit' => 50,
			'max' => false
		), $options);

		$sDb = $this->_db;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;
		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne("SELECT max(attachment_id) FROM xf_attachment WHERE content_type='blog_entry'");
		}

		$attachments = $sDb->fetchAll($sDb->limit("
				SELECT
					attachment.*, entry.user_id, attachment_data.*
				FROM xf_attachment attachment
				INNER JOIN lnblog_entry entry ON attachment.content_id = entry.entry_id AND attachment.content_type = 'blog_entry'
				INNER JOIN xf_attachment_data attachment_data ON attachment.data_id = attachment_data.data_id
				WHERE attachment_id > " . $sDb->quote($start) . "
					AND entry.message_state = 'visible'
				ORDER BY attachment_id
			", $options['limit']
		));
		
		if (!$attachments)
		{
			return true;
		}

		$next = 0;
		$total = 0;
		$entryMap = $this->_importModel->getImportContentMap('ln_entry');

		
		/* @var $attachmentModel XenForo_Model_Attachment */
		$attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');
		
		foreach ($attachments AS $attachment)
		{
			$next = $attachment['attachment_id'];
			$entryId = $this->_mapLookUp($entryMap, $attachment['content_id']);
			if (!$entryId)
			{
				continue;
			}
			
			$attachFileOrig = $attachmentModel->getAttachmentDataFilePath($attachment);
			if (!file_exists($attachFileOrig))
			{
				// print "File does not exist: " . $attachFileOrig . "<br/>";
				continue;
			}

			$attachFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			copy($attachFileOrig, $attachFile);

			$success = $this->_importModel->importBlogAttachment(
				$attachment['attachment_id'],
				$this->_convertToUtf8($attachment['filename']),
				$attachFile,
				$attachment['user_id'],
				$entryId,
				$attachment['attach_date'],
				array('view_count' => $attachment['view_count'])
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
	
	
	/**
	 * Calls the rebuild action to update the counters for the blogs
	 */
	public function stepTotals($start, array $options)
	{
		return XfAddOns_Blogs_Importer_Helper::stepTotals($start, $options, $this);
	}
	
	/**
	 * Rebuild the position index for all the entries
	 */
	public function stepPositionEntries($start, array $options)
	{
		return XfAddOns_Blogs_Importer_Helper::stepPositionEntries($start, $options, $this);
	}	
	
	/**
	 * Rebuild the position index for all the comments
	 */
	public function stepPositionComments($start, array $options)
	{
		return XfAddOns_Blogs_Importer_Helper::stepPositionComments($start, $options, $this);
	}
	
	/**
	 * Returns a reference to the session that is being used for the import
	 */
	public function getSession()
	{
		return $this->_session;
	}
	
	/**
	 * Returns a reference to the XenForo Database in use by this importer
	 * @return Zend_Db_Adapter_Abstract
	 */
	public function getMainDb()
	{
		return $this->_db;
	}
	
	/**
	 * Made the function public to be able to call it from other objects
	 */
	public function _getProgressOutput($lastId, $maxId)
	{
		parent::_getProgressOutput($lastId, $maxId);
	}
	
	
	
	

}