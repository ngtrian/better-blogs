<?php

class XfAddOns_Blogs_Importer_BlogsVbulletin extends XenForo_Importer_vBulletin
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
		return 'Better Blogs / from vBulletin 3.8+';
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
	 * Extends the base vBulletin configure method. We have our own config_set key to identify
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
			$this->validateAttachmentsDir($controller, $config);
			$errors = $this->validateConfiguration($config);		// default database validation
			if ($errors)
			{
				return $controller->responseError($errors);
			}
			
			if (isset($config['blogsAttachmentPath']))	// if this is configured, and we passed validations, we are done
			{
				if (empty($config['blogsAttachmentPath']))
				{
					$msg = new XenForo_Phrase('xfa_blogs_attachment_path_not_found');
					return $controller->responseError($msg);
				}
				return true;
			}
			
			$this->_bootstrap($config);
			
			return $controller->responseView('XenForo_ViewAdmin_Import_vBulletin_Config', 'xfa_blogs_import_vbulletin_config', array(
				'config' => $config,
				'retainKeys' => $config['retain_keys'],
			));
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
	 * Validate that the path for the attachment exists and is valid
	 */
	private function validateAttachmentsDir(XenForo_ControllerAdmin_Abstract $controller, $config)
	{
		if (!empty($config['blogsAttachmentPath']))
		{
			if (!file_exists($config['blogsAttachmentPath']) || !is_dir($config['blogsAttachmentPath']))
			{
				$msg = new XenForo_Phrase('xfa_blogs_attachment_path_not_found');
				throw new XenForo_ControllerResponse_Exception($controller->responseError($msg));
			}
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
			XenForo_Model::create('XenForo_Model_DataRegistry')->set('importSession', $sessionData);
		}
		
		return array(
			'categories' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_categories')
			),
			'categoriesHierarchy' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_categories_hierarchy'),
				'depends' => array( 'categories' )
			),				
			'blogs' => array(
				'title' => new XenForo_Phrase('xfa_blogs_import_blogs')
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
			$options['max'] = $sDb->fetchOne('SELECT MAX(blogcategoryid) FROM ' . $prefix . 'blog_category');
		}

		// pull categories
		$categories = $sDb->fetchAll($sDb->limit("
			SELECT
				*
			FROM " . $prefix . "blog_category
			WHERE blogcategoryid > " . $sDb->quote($start) . "
			ORDER BY
				blogcategoryid 			
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
		$categoryMap = $this->_importModel->getCategoriesMap();

		foreach ($categories AS $category)
		{
			$next = $category['blogcategoryid'];
			
			$userId = $this->_mapLookUp($userIdMap, $category['userid']);
			if ($category['userid'] > 0 && !$userId)
			{
				// if category had a user but not found, continue
				continue;
			}
			
			$categoryTitle = $category['title'];
			$categoryTitle = htmlspecialchars_decode($categoryTitle);
			$categoryTitle = preg_replace('/&amp;/isU', '&', $categoryTitle);
			
			$parentId = $this->_mapLookUp($categoryMap, $category['parentid']);
			
			$info = array(
				'user_id' => $userId,
				'category_name' => $this->_convertToUtf8($categoryTitle),
				'parent_id' => $parentId,
				'display_order' => $category['displayorder']
			);
			
			$newId = $this->_importModel->_importData($category['blogcategoryid'], 'XfAddOns_Blogs_DataWriter_Category', 'blog_category', 'category_id', $info);
			
			if ($newId)
			{
				$categoryMap[$category['blogcategoryid']] = $newId;
				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);
		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}
	
	/**
	 * Normalize the hierarchy of the categories after the import
	 * @param unknown $start
	 * @param array $options
	 */
	public function stepCategoriesHierarchy($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 500,
			'max' => false
		), $options);
		
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		
		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(blogcategoryid) FROM ' . $prefix . 'blog_category');
		}
		
		// pull categories
		$categories = $sDb->fetchAll($sDb->limit("
			SELECT
				*
			FROM " . $prefix . "blog_category
			WHERE
				blogcategoryid > " . $sDb->quote($start) . " AND
				parentid > 0
			ORDER BY
				blogcategoryid
			", $options['limit']
		));
		if (!$categories)
		{
			return true;
		}
		
		XenForo_Db::beginTransaction();
		
		$next = 0;
		$total = 0;
		$categoryMap = $this->_importModel->getCategoriesMap();
		
		foreach ($categories AS $category)
		{
			$next = $category['blogcategoryid'];
			
			$categoryId = $this->_mapLookUp($categoryMap, $category['blogcategoryid']);
			if (!$categoryId)
			{
				continue;
			}
			$parentId = $this->_mapLookUp($categoryMap, $category['parentid']);
			
			$this->_db->update('xfa_blog_category', array('parent_id' => $parentId), ('category_id = ' .$categoryId));
			$total++;
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

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(bloguserid) FROM ' . $prefix . 'blog_user');
		}

		// pull categories
		$rows = $sDb->fetchAll($sDb->limit("
			SELECT
				*
			FROM " . $prefix . "blog_user
			WHERE bloguserid > " . $sDb->quote($start) . "
			ORDER BY
				bloguserid 			
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

		foreach ($rows AS $blog)
		{
// 			print "<br/><br/>";
			
			$next = $blog['bloguserid'];
			$userId = $this->_mapLookUp($userIdMap, $blog['bloguserid']);
			if (!$userId)
			{
				continue;
			}
			
			$info = array(
					'user_id' => $userId,
					'blog_title' => $this->_convertToUtf8($blog['title']),
					'description' => $this->_convertToUtf8($blog['description'])
					);
			
			$newId = false;
			try
			{
// 				print "About to insert old user id: " . $blog['bloguserid'] . "<br/>";
				$newId = $this->_importModel->_importData($blog['bloguserid'], 'XfAddOns_Blogs_DataWriter_Blog', 'blog', 'user_id', $info);
				if ($newId)
				{
					$total++;
				}
// 				print "Inserted user_id: " . $newId . "<br/>";

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

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(blogid) FROM ' . $prefix . 'blog');
		}

		// pull entries
		$rows = $sDb->fetchAll($sDb->limit("
			SELECT
			    blog.blogid, blog.userid, blog.title, blog.dateline, blog_text.pagetext, blog.state, blog.categories
			FROM " . $prefix . "blog blog
			INNER JOIN " . $prefix . "blog_text blog_text ON blog.firstblogtextid = blog_text.blogtextid				
			WHERE
				blog.blogid > " . $sDb->quote($start) . "
			ORDER BY
				blog.blogid 			
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
		$categoryMap = $this->_importModel->getCategoriesMap();

		foreach ($rows AS $entry)
		{
			$next = $entry['blogid'];
			$userId = $this->_mapLookUp($userIdMap, $entry['userid']);
			if (!$userId)
			{
				continue;
			}
			
			$info = array(
					'user_id' => $userId,
					'title' => $this->_convertToUtf8($entry['title']),
					'post_date' => $entry['dateline'],
					'message' => $this->_convertToUtf8($entry['pagetext']),
					'message_state' => ($entry['state'] == 'visible' ? "visible" : "deleted")
					);
			
			$newId = $this->_importModel->_importData($entry['blogid'], 'XfAddOns_Blogs_DataWriter_Entry', 'blog_entry', 'entry_id', $info);
			if ($newId)
			{
				$total++;
				
				// and associate the categories
				$categories = $this->getCategoriesForBlogEntry($entry['blogid']);
				foreach ($categories as $oldCategoryId)
				{
					$newCategoryId = $this->_mapLookUp($categoryMap, $oldCategoryId);
					if (!$newCategoryId)
					{
						continue;
					}
					
					try {
						$this->_db->insert('xfa_blog_entry_category', array(
								'entry_id' => $newId,
								'category_id' => $newCategoryId
						));
					}
					catch (Exception $ex) {
						// not logging this
					}
				}
			}
		}
		XenForo_Db::commit();
		
		$this->_session->incrementStepImportTotal($total);
		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}
	
	private function getCategoriesForBlogEntry($entryBlogId)
	{
		$categories = $this->_sourceDb->fetchAll("SELECT * FROM " . $this->_prefix . "blog_categoryuser WHERE blogid = ?", $entryBlogId);
		$ret = array();
		foreach ($categories as $cat)
		{
			$ret = array_merge($ret, $this->getCategoriesRecursive($cat['blogcategoryid']));
		}
		return $ret;
	}
	
	private function getCategoriesRecursive($categoryId)
	{
		$category = $this->_sourceDb->fetchRow("SELECT * FROM " . $this->_prefix . "blog_category WHERE blogcategoryid = ?", $categoryId);
		$ret = array();
		$ret[] = $category['blogcategoryid'];
		if ($category['parentid'] > 0)
		{
			$ret = array_merge($ret, $this->getCategoriesRecursive($category['parentid']));
		}		
		return $ret;
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
			$options['max'] = $sDb->fetchOne('SELECT MAX(blogtextid) FROM ' . $prefix . 'blog_text');
		}

		
		// pull categories
		$rows = $sDb->fetchAll($sDb->limit("
			SELECT
			    blog_text.blogtextid, blog.blogid, blog_text.dateline, blog_text.pagetext, blog.state, blog_text.userid
			FROM " . $prefix . "blog_text blog_text
			INNER JOIN " . $prefix . "blog blog ON blog_text.blogid = blog.blogid
			WHERE
				blog_text.blogtextid <> blog.firstblogtextid AND
				blog_text.blogtextid > " . $sDb->quote($start) . "	
			ORDER BY
				blog_text.blogtextid 			
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
			$next = $comment['blogtextid'];
			$userId = $this->_mapLookUp($userIdMap, $comment['userid']);
			if (!$userId)
			{
				continue;
			}
			$entryId = $this->_mapLookUp($entryMap, $comment['blogid']);
			if (!$entryId)
			{
				continue;
			}			
			
			$info = array(
					'entry_id' => $entryId,
					'user_id' => $userId,
					'post_date' => $comment['dateline'],
					'message' => $this->_convertToUtf8($comment['pagetext']),
					'message_state' => ($comment['state'] == 'visible' ? "visible" : "deleted")
					);
			
			$newId = $this->_importModel->_importData($comment['blogtextid'], 'XfAddOns_Blogs_DataWriter_Comment', 'blog_comment', 'comment_id', $info);
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
	 * Import all the blog attachments
	 * @see XenForo_Importer_vBulletin::stepAttachments($start, $options)
	 */
	public function stepAttachments($start, array $options)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		
		$options = array_merge(array(
			'path' => isset($this->_config['blogsAttachmentPath']) ? $this->_config['blogsAttachmentPath'] : '',
			'limit' => 50,
			'max' => false
		), $options);

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(attachmentid) FROM ' . $prefix . 'blog_attachment');
		}

		$attachments = $sDb->fetchAll($sDb->limit("
				SELECT attachmentid, userid, dateline, filename, counter, blogid
				FROM " . $prefix . "blog_attachment
				WHERE attachmentid > " . $sDb->quote($start) . "
					AND visible = 'visible'
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
			$entryId = $this->_mapLookUp($entryMap, $attachment['blogid']);
			if (!$entryId)
			{
				continue;
			}
			$newUserId = $this->_mapLookUp($userIdMap, $attachment['userid'], 0);
			if (!$newUserId)
			{
				continue;
			}

			$attachFileOrig = "$options[path]/" . implode('/', str_split($attachment['userid'])) . "/$attachment[attachmentid].attach";
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
	
	
	

}