<?php

class XfAddOns_Blogs_Importer_IPS26x extends XenForo_Importer_IPBoard34x
{

	const ARCHIVE_TABLE = 'archived_import_log';

	/**
	 * Whether we should retain the keys on the import
	 */
	private $retainKeys = false;
	
	/**
	 * Constructor. Overrides the importModel used to the custom one
	 */
	public function __construct()
	{
		parent::__construct();
		define('IMPORT_LOG_TABLE', self::ARCHIVE_TABLE); // kind need to define the archive table
	}
	
	public function retainKeysReset()
	{
		// do not call parent, it deletes forums
	}	

	/**
	 * The name of the importer is Social Groups for vBulletin
	 * @return string
	 */
	public static function getName()
	{
		return 'Better Blogs / from IP.Blogs 2.6.x';
	}

	protected final function _bootstrap(array $config)
	{
		$ret = parent::_bootstrap($config);
		$this->retainKeys = isset($config['retain_keys']) && $config['retain_keys'] == 1;
		
		// Add to the list of the retainable keys the ones used by the blog
		$this->_importModel->retainableKeys[] = 'category_id';
		$this->_importModel->retainableKeys[] = 'blog_id';
		$this->_importModel->retainableKeys[] = 'entry_id';
		$this->_importModel->retainableKeys[] = 'comment_id';
		return $ret;
	}	
	
	/**
	 * Extends the base IPS configure method. We have our own config_set key to identify
	 * when we are ready to begin importing
	 *
	 * @param XenForo_ControllerAdmin_Abstract $controller
	 * @param array $config
	 * @return unknown
	 */
	public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
	{
		if ($config)
		{
			$this->validateArchiveTable($controller, $config);		// we need an archive table to exist, for the users
			$errors = $this->validateConfiguration($config);		// default database validation
			if ($errors)
			{
				return $controller->responseError($errors);
			}
			
			$this->_bootstrap($config);
			return true;
		}		
		return parent::configure($controller, $config);
	}
	
	/**
	 * We need an archive table. If the table is not present, or is empty, we should not continue
	 */
	private function validateArchiveTable(XenForo_ControllerAdmin_Abstract $controller, $config)
	{
		$db = XenForo_Application::getDb();
		try
		{
			$totalRows = $db->fetchOne("SELECT count(*) FROM " . self::ARCHIVE_TABLE);
			if ($totalRows == 0)
			{
				throw new Exception("Zero result");
			}
		}
		catch (Exception $ex)
		{
			$msg = new XenForo_Phrase('xfa_blogs_archive_table_does_not_exist');
			throw new XenForo_ControllerResponse_Exception($controller->responseError($msg));
		}
	}

	/**
	 * Returns the steps that this importer contain
	 * @return array
	 */
	public function getSteps()
	{
		if (isset($_REQUEST['resetSteps']))
		{
			$sessionData = XenForo_Model::create('XenForo_Model_DataRegistry')->get('importSession');
			unset($sessionData['runSteps']['categories']);
			unset($sessionData['runSteps']['categoriesHierarchy']);
			unset($sessionData['runSteps']['blogs']);
			unset($sessionData['runSteps']['entries']);
			unset($sessionData['runSteps']['comments']);
			XenForo_Model::create('XenForo_Model_DataRegistry')->set('importSession', $sessionData);
			
			print "Reset";
			exit;
		}
		
		return array(
			'blogs' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_blogs')
			),				
			'categories' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_categories'),
				'depends' => array( 'blogs' )
			),
			'entries' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_entries'),
				'depends' => array( 'blogs', 'categories' )
			),
			'comments' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_comments'),
				'depends' => array( 'entries' )
			),
			'attachments' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_attachments'),
				'depends' => array ('entries')
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
			),								
				
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
	
		$xenforoDb = XenForo_Application::getDb();
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
	
		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(blog_id) FROM ' . $prefix . 'blog_blogs');
			XfAddOns_Logger_Log::info("Starting import for blogs, total blogs: " . $options['max']);
		}
	
		// pull categories
		$rows = $sDb->fetchAll($sDb->limit("
			SELECT
				*
			FROM " . $prefix . "blog_blogs
			WHERE 
				blog_id > " . $sDb->quote($start) . " AND
				blog_type = 'local'
			ORDER BY
				blog_id
			", $options['limit']
		));
		if (!$rows)
		{
			return true;
		}
	
		XenForo_Db::beginTransaction();
	
		$next = 0;
		$total = 0;
		$userIdMap = $this->_importModel->getImportContentMap('user');
		
		/* @var $blogModel XfAddOns_Blogs_Model_Blog */
		$blogModel = XenForo_Model::create('XfAddOns_Blogs_Model_Blog');
	
		foreach ($rows AS $blog)
		{
			$next = $blog['blog_id'];
			XfAddOns_Logger_Log::info("About to import the blog with id: " . $blog['blog_id']);
			
			$userId = $this->_mapLookUp($userIdMap, $blog['member_id']);
			if (!$userId)
			{
				XfAddOns_Logger_Log::info("-- The blog belongs to the user with id " . $blog['member_id'] . " but that user was not found from a previous IPS import");
				continue;
			}
			
			// check if the user already has a blog
			$existingBlog = $blogModel->getBlogForUser($userId);
			if ($existingBlog['blog_exists'])
			{
				XfAddOns_Logger_Log::info("---- The XenForo user {$userId} already has a blog, the second blog will not be created");
				$this->_importModel->logImportData('blog', $next, $existingBlog['user_id']);
				continue;
			}
				
			$info = array(
				'user_id' => $userId,
				'blog_title' => $this->_convertToUtf8($blog['blog_name']),
				'description' => $this->_convertToUtf8($blog['blog_desc']),
				'view_count' => $blog['blog_num_views']
			);
				
			$newId = false;
			try
			{
				XfAddOns_Logger_Log::info("-- Adding the blog with id " . $blog['blog_id'] . "  belonging to the XenForo user " . $userId);
				$newId = $this->_importModel->_importData($blog['blog_id'], 'XfAddOns_Blogs_DataWriter_Blog', 'blog', 'user_id', $info);
				if ($blog['blog_private'] == 1)
				{
					XfAddOns_Logger_Log::info("---- Setting the blog with id {$newId} to private");
					$xenforoDb->query("UPDATE xf_user_privacy SET allow_view_blog='none' WHERE user_id = ?", $userId);
				}
				else
				{
					$xenforoDb->query("UPDATE xf_user_privacy SET allow_view_blog='everyone' WHERE user_id = ?", $userId);
				}
				
				if ($newId)
				{
					XfAddOns_Logger_Log::info("---- The blog was added with id {$newId}");
					$total++;
				}
				else
				{
					XfAddOns_Logger_Log::warn("---- There was an error and the blog was not inserted");
				}
			}
			catch (Exception $ex)
			{
				XfAddOns_Logger_Log::warn("---- There was an error and the blog was not inserted: " . $ex->getMessage());
				XenForo_Db::rollback();		// needed, because the importData() method does not rollback on exception
				XenForo_Error::logException($ex, false);
			}
		}
	
		XenForo_Db::commit();
	
		$this->_session->incrementStepImportTotal($total);
		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
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

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		
		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(category_id) FROM ' . $prefix . 'blog_categories');
			XfAddOns_Logger_Log::info("Starting import for categories, total categories: " . $options['max']);
		}

		// pull categories
		$categories = $sDb->fetchAll($sDb->limit("
			SELECT
				*
			FROM " . $prefix . "blog_categories
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
		$userIdMap = $this->_importModel->getImportContentMap('user');
		$blogMap = $this->_importModel->getBlogsMap();

		foreach ($categories AS $category)
		{
			$next = $category['category_id'];
			XfAddOns_Logger_Log::info("About to import the category with id: " . $category['category_id']);
			
			$newBlogId = $this->_mapLookUp($blogMap, $category['category_blog_id']);
			if (!$newBlogId)
			{
				XfAddOns_Logger_Log::info("-- No blog is associated for the category with id: " . $category['category_id']);
				continue;				
			}
			
			$info = array(
				'user_id' => $newBlogId,
				'category_name' => $category['category_title'],
				'parent_id' => 0,
				'display_order' => $category['category_position']
			);
			
			$newId = false;
			try
			{
				XfAddOns_Logger_Log::info("-- Adding the category with id " . $category['category_id'] . "  belonging to the XenForo blog " . $newBlogId);
				$newId = $this->_importModel->_importData($category['category_id'], 'XfAddOns_Blogs_DataWriter_Category', 'blog_category', 'category_id', $info);
				if ($newId)
				{
					XfAddOns_Logger_Log::info("---- The category was added with id {$newId}");
					$total++;
				}
				else
				{
					XfAddOns_Logger_Log::warn("---- There was an error and the category was not inserted");
				}
			}
			catch (Exception $ex)
			{
				XfAddOns_Logger_Log::warn("---- There was an error and the category was not inserted: " . $ex->getMessage());
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

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(entry_id) FROM ' . $prefix . 'blog_entries');
			XfAddOns_Logger_Log::info("Starting import for entries, total entries: " . $options['max']);
		}

		// pull entries
		$rows = $sDb->fetchAll($sDb->limit("
			SELECT
			    *
			FROM " . $prefix . "blog_entries
			WHERE
				entry_id > " . $sDb->quote($start) . "
			ORDER BY
				entry_id 			
			", $options['limit']
		));
		if (!$rows)
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;
		$blogMap = $this->_importModel->getBlogsMap();
		$categoryMap = $this->_importModel->getCategoriesMap();

		foreach ($rows AS $entry)
		{
			$next = $entry['entry_id'];
			XfAddOns_Logger_Log::info("About to import the entry with id: " . $entry['entry_id']);
			
			$newBlogId = $this->_mapLookUp($blogMap, $entry['blog_id']);
			if (!$newBlogId)
			{
				XfAddOns_Logger_Log::info("-- No blog is associated with the entry with id: " . $entry['entry_id']);
				continue;
			}
			
			$info = array(
				'user_id' => $newBlogId,
				'title' => $this->_convertToUtf8($entry['entry_name']),
				'post_date' => $entry['entry_date'],
				'message' => $this->br2nl($this->_convertToUtf8($entry['entry'])),
				'message_state' => ($entry['entry_status'] == 'published' ? "visible" : "deleted"),
				'reply_count' => $entry['entry_num_comments']
				);
			
			$newId = false;
			try
			{
				XfAddOns_Logger_Log::info("-- Adding the entry with id " . $entry['entry_id'] . "  belonging to the XenForo blog " . $newBlogId);
				$newId = $this->_importModel->_importData($entry['entry_id'], 'XfAddOns_Blogs_DataWriter_Entry', 'blog_entry', 'entry_id', $info);
				
				if ($newId)
				{
					XfAddOns_Logger_Log::info("---- The entry was added with id {$newId}");
					$total++;
					
					// and associate the categories
					$categories = explode(',', $entry['entry_category']);
					foreach ($categories as $oldCategoryId)
					{
						$oldCategoryId = trim($oldCategoryId);
						if (empty($oldCategoryId))
						{
							continue;
						}
						
						$newCategoryId = $this->_mapLookUp($categoryMap, $oldCategoryId);
						if (!$newCategoryId)
						{
							XfAddOns_Logger_Log::warn("---- The category with id {$oldCategoryId} could not be associated to entry {$newId}");
							continue;
						}
						
						XfAddOns_Logger_Log::info("---- The entry {$newId} was associated to the XenForo Blog category {$newCategoryId}");
						$this->_db->insert('xfa_blog_entry_category', array(
							'entry_id' => $newId,
							'category_id' => $newCategoryId
						));
					}					
				}
				else
				{
					XfAddOns_Logger_Log::warn("---- There was an error and the entry was not inserted");
				}
			}
			catch (Exception $ex)
			{
				XfAddOns_Logger_Log::warn("---- There was an error and the entry was not inserted: " . $ex->getMessage());
				XenForo_Db::rollback();		// needed, because the importData() method does not rollback on exception
				XenForo_Error::logException($ex, false);
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

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(comment_id) FROM ' . $prefix . 'blog_comments');
			XfAddOns_Logger_Log::info("Starting import for comments, total comments: " . $options['max']);
		}

		$rows = $sDb->fetchAll($sDb->limit("
			SELECT
			    *
			FROM " . $prefix . "blog_comments
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
		$userIdMap = $this->_importModel->getImportContentMap('user');
		$entryMap = $this->_importModel->getEntriesMap();

		foreach ($rows AS $comment)
		{
			$next = $comment['comment_id'];
			XfAddOns_Logger_Log::info("About to import the comment with id: " . $comment['comment_id']);
			
			$userId = $this->_mapLookUp($userIdMap, $comment['member_id']);
			if (!$userId)
			{
				XfAddOns_Logger_Log::warn("-- The comment with id " . $comment['comment_id'] . " belongs to the member_id " . $comment['member_id'] . " but the user was not found");
				continue;
			}
			$entryId = $this->_mapLookUp($entryMap, $comment['entry_id']);
			if (!$entryId)
			{
				XfAddOns_Logger_Log::warn("-- The comment with id " . $comment['comment_id'] . " belongs to the entry_id " . $comment['entry_id'] . " but the entry was not found");
				continue;
			}			
			
			$info = array(
					'entry_id' => $entryId,
					'user_id' => $userId,
					'post_date' => $comment['comment_date'],
					'message' => $this->br2nl($this->_convertToUtf8($comment['comment_text'])),
					'message_state' => ($comment['comment_approved'] == 1 ? "visible" : "deleted")
					);
			
			try
			{
				XfAddOns_Logger_Log::info("-- Adding the comment with id " . $comment['comment_id'] . "  belonging to the XenForo entry " . $entryId);
				$newId = $this->_importModel->_importData($comment['comment_id'], 'XfAddOns_Blogs_DataWriter_Comment', 'blog_comment', 'comment_id', $info);
				if ($newId)
				{
					XfAddOns_Logger_Log::info("---- The comment was added with id {$newId}");
					$total++;
				}
				else
				{
					XfAddOns_Logger_Log::warn("---- There was an error and the comment was not inserted");
				}
			}
			catch (Exception $ex)
			{
				XfAddOns_Logger_Log::warn("---- There was an error and the comment was not inserted: " . $ex->getMessage());
				XenForo_Db::rollback();		// needed, because the importData() method does not rollback on exception
				XenForo_Error::logException($ex, false);
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
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		
		$options = array_merge(array(
			'path' => isset($this->_config['ipboard_path']) ? $this->_config['ipboard_path'] : '',
			'limit' => 50,
			'max' => false
		), $options);

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(attach_id) FROM ' . $prefix . 'attachments');
			XfAddOns_Logger_Log::info("Starting import for attachments, total attachments: " . $options['max']);
		}

		$attachments = $sDb->fetchAll($sDb->limit("
				SELECT *
				FROM " . $prefix . "attachments
				WHERE attach_id > " . $sDb->quote($start) . "
					AND attach_is_archived = 0
				ORDER BY attach_id
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
			$next = $attachment['attach_id'];
			XfAddOns_Logger_Log::info("About to import the attachment with id: " . $next);
			
			$entryId = $this->_mapLookUp($entryMap, $attachment['attach_rel_id']);
			if (!$entryId)
			{
				XfAddOns_Logger_Log::warn("-- The attachment with id " . $attachment['attach_id'] . " belongs to the entry_id " . $attachment['attach_rel_id'] . " but the entry was not found");
				continue;
			}
			$newUserId = $this->_mapLookUp($userIdMap, $attachment['attach_member_id'], 0);
			if (!$newUserId)
			{
				XfAddOns_Logger_Log::warn("-- The attachment with id " . $attachment['attach_id'] . " belongs to the member_id " . $attachment['attach_member_id'] . " but the user was not found");
				continue;
			}

			$attachFileOrig = "$options[path]/uploads/" . $attachment['attach_location'];
			if (!file_exists($attachFileOrig))
			{
				XfAddOns_Logger_Log::warn("-- The attachment with id " . $attachment['attach_id'] . " was not found in the path " . $attachFileOrig);
				continue;
			}

			$attachFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			copy($attachFileOrig, $attachFile);

			try
			{
				XfAddOns_Logger_Log::info("-- Adding the attachment with id " . $attachment['attach_id'] . "  belonging to the XenForo entry " . $entryId);
				$newId = $this->_importModel->importBlogAttachment(
						$attachment['attach_id'],
						$this->_convertToUtf8($attachment['attach_file']),
						$attachFile,
						$newUserId,
						$entryId,
						$attachment['attach_date'],
						array()
				);
				
				if ($newId)
				{
					XfAddOns_Logger_Log::info("---- The attachment was added with id {$newId}");
					$total++;
				}
				else
				{
					XfAddOns_Logger_Log::warn("---- There was an error and the attachment was not inserted");
				}
				
				@unlink($attachFile);
			}
			catch (Exception $ex)
			{
				XfAddOns_Logger_Log::warn("---- There was an error and the attachment was not inserted: " . $ex->getMessage());
				XenForo_Db::rollback();		// needed, because the importData() method does not rollback on exception
				XenForo_Error::logException($ex, false);
			}			
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
		return parent::_getProgressOutput($lastId, $maxId);
	}	
	
	/**
	 * Check if a table currently exists
	 * @param string $tableName		The name of the table
	 * @return boolean
	 */
	protected function checkTable($tableName)
	{
		$sDb = $this->_sourceDb;
		$table = $sDb->fetchRow("SHOW TABLES LIKE " . $sDb->quote($tableName) . "");
		return !empty($table);
	}

	/**
	 * Changes any <br /> into a new line
	 */
	protected function br2nl ($string)
	{
		return preg_replace('/\<br(\s*)?\/?\>/i', PHP_EOL, $string);
	}	
	
	

}