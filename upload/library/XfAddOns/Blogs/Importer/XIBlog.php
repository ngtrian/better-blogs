<?php

class XfAddOns_Blogs_Importer_XIBlog extends XenForo_Importer_Abstract
{

	/**
	 * The name of the importer is Social Groups for vBulletin
	 * @return string
	 */
	public static function getName()
	{
		return 'Better Blogs / from XI Blog 1.1 Beta 4';
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
			'categories' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_categories')
			),				
			'blogs' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_blogs'),
				'depends' => array( 'categories' )
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
	 * Import the categories information
	 *
	 * @param int $start		Index at which to start the import
	 * @param array $options	Any options that were set for the step
	 * @return array
	 */
	public function stepCategories($start, array $options)
	{
		$options = array_merge(array(
				'limit' => 500,
				'max' => false
		), $options);
	
		$sDb = $this->_db;
	
		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(category_id) FROM xf_xi_blog_category');
		}
	
		// pull categories
		$categories = $sDb->fetchAll($sDb->limit("
			SELECT *
			FROM xf_xi_blog_category
			WHERE category_id > " . $sDb->quote($start) . "
			ORDER BY
				category_id
			", $options['limit']
		));
		if (!$categories)
		{
			return true;
		}
	
		XenForo_Db::beginTransaction();
	
		$next = 0;
		$total = 0;
		foreach ($categories AS $category)
		{
			$next = $category['category_id'];
			$info = array(
				'category_name' => $this->_convertToUtf8($category['title'])
			);
			$newId = $this->_importModel->_importData($category['category_id'], 'XfAddOns_Blogs_DataWriter_Category', 'xi_blog_category_global', 'category_id', $info);
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
			$options['max'] = $sDb->fetchOne('SELECT MAX(member_blog_id) FROM xf_xi_blog_member_blog');
		}

		// pull categories
		$rows = $sDb->fetchAll($sDb->limit("
			SELECT *
			FROM xf_xi_blog_member_blog blog
			WHERE member_blog_id > " . $sDb->quote($start) . "
			ORDER BY
				blog.member_blog_id	
			", $options['limit']
		));
		
		if (!$rows)
		{
			return true;
		}

		$blogMap = $this->_importModel->getImportContentMap('xi_blog');		
		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;
		foreach ($rows AS $blog)
		{
			$next = $blog['member_blog_id'];
			$info = array(
				'user_id' => $blog['user_id'],
				'blog_title' => $this->_convertToUtf8($blog['title']),
				'description' => $this->_convertToUtf8($blog['description'])
				);
			
			$newId = false;
			try
			{
				// this would mean we have already created a blog for the user
				if (in_array($blog['user_id'], $blogMap))	
				{
					$this->_importModel->logImportData('xi_blog', $blog['member_blog_id'], $blog['user_id']);
				}
				else
				{
					$newId = $this->_importModel->_importData($blog['member_blog_id'], 'XfAddOns_Blogs_DataWriter_Blog', 'xi_blog', 'user_id', $info);
					if ($newId)
					{
						$total++;
					}
					$blogMap[$blog['member_blog_id']] = $newId;
					
					// and update the preference
					$db = XenForo_Application::getDb();
					$privacyLevel = $blog['blog_privacy_level'];
					if ($privacyLevel != 'everyone' && $privacyLevel != 'members' && $privacyLevel != 'followed' && $privacyLevel != 'none')
					{
						$privacyLevel = 'followed';
					}
					$db->query("UPDATE xf_user_privacy SET allow_view_blog = ? WHERE user_id = ?", array($privacyLevel, $blog['user_id']));
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
			$options['max'] = $sDb->fetchOne('SELECT MAX(entry_id) FROM xf_xi_blog_entry');
		}

		// pull entries
		$rows = $sDb->fetchAll($sDb->limit("
			SELECT *
			FROM xf_xi_blog_entry entry
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

		$blogMap = $this->_importModel->getImportContentMap('xi_blog');
		$categoryMap = $this->_importModel->getImportContentMap('xi_blog_category_global');
		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;
		foreach ($rows AS $entry)
		{
			$next = $entry['entry_id'];
			
			$privacyLevel = $entry['entry_privacy_level'];
			if ($privacyLevel != 'everyone' && $privacyLevel != 'members' && $privacyLevel != 'followed' && $privacyLevel != 'none')
			{
				$privacyLevel = 'followed';
			}			
			
			$info = array(
				'user_id' => $entry['user_id'],
				'title' => $this->_convertToUtf8($entry['title']),
				'post_date' => $entry['entry_date'],
				'message' => $this->_convertToUtf8($entry['message']),
				'message_state' => ($entry['discussion_state'] == 'visible' ? "visible" : "deleted"),
				'likes' => $entry['likes'],
				'view_count' => $entry['view_count'],
				'like_users' => $entry['like_users'],
				'allow_view_entry' => $privacyLevel,
				'allow_comments' => $entry['discussion_open'] ? 1 : 0
				);
			
			$newId = $this->_importModel->_importData($entry['entry_id'], 'XfAddOns_Blogs_DataWriter_Entry', 'xi_blog_entry', 'entry_id', $info);
			if ($newId)
			{
				$total++;
				
				// and associate the categories
				$oldCategoryId = $entry['category_id'];
				$globalCategoryId = $this->_mapLookUp($categoryMap, $oldCategoryId);
				if (!$globalCategoryId)
				{
					continue;
				}
				
				$this->_db->insert('xfa_blog_entry_category', array(
					'entry_id' => $newId,
					'category_id' => $globalCategoryId
				));
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
			$options['max'] = $sDb->fetchOne('SELECT MAX(comment_id) FROM xf_xi_blog_comment');
		}
		
		// pull categories
		$rows = $sDb->fetchAll($sDb->limit("
			SELECT *
			FROM xf_xi_blog_comment comment
			WHERE
				comment.comment_id > " . $sDb->quote($start) . "	
			ORDER BY
				comment.comment_id
			", $options['limit']
		));
		if (!$rows)
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;
		$entryMap = $this->_importModel->getImportContentMap('xi_blog_entry');

		foreach ($rows AS $comment)
		{
			$next = $comment['comment_id'];
			$entryId = $this->_mapLookUp($entryMap, $comment['entry_id']);
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
			
			$newId = $this->_importModel->_importData($comment['comment_id'], 'XfAddOns_Blogs_DataWriter_Comment', 'xi_blog_comment', 'comment_id', $info);
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
				INNER JOIN xf_xi_blog_entry entry ON attachment.content_id = entry.entry_id AND attachment.content_type = 'blog_entry'
				INNER JOIN xf_attachment_data attachment_data ON attachment.data_id = attachment_data.data_id
				WHERE attachment_id > " . $sDb->quote($start) . "
					AND entry.discussion_state = 'visible'
				ORDER BY attachment_id
			", $options['limit']
		));
		
		if (!$attachments)
		{
			return true;
		}

		$next = 0;
		$total = 0;
		$entryMap = $this->_importModel->getImportContentMap('xi_blog_entry');
		
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