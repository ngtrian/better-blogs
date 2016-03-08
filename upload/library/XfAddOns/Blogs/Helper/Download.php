<?php

/**
 * A helper used to generate the download for the blog
 */
class XfAddOns_Blogs_Helper_Download
{

	/**
	 * A reference to the template used
	 * @var XenForo_Template_Public
	 */
	private static $template;
	
	/**
	 * @var XfAddOns_Blogs_Model_Comment
	 */
	private $commentModel;
	
	/**
	 * @var XenForo_Model_Attachment
	 */
	private $attachmentModel;

	/**
	 * @var XenForo_BbCode_Parser
	 */
	private $bbCodeParser;
	
	/**
	 * @var array
	 */
	private $bbCodeOptions;
	
	/**
	 * Constructor. Initializes references to wired models.
	 */
	public function __construct()
	{
		$this->commentModel = Xenforo_Model::create('XfAddOns_Blogs_Model_Comment');
		$this->attachmentModel = Xenforo_Model::create('XenForo_Model_Attachment');
		$this->bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base'));
		$this->bbCodeOptions = array('states' => array( 'viewAttachments' => true ), 'showSignature' => false, 'noFollow' => true );
	}
	
	/**
	 * Creates the zip file that the person can use to download all the blog content
	 */
	public function createDownload(array $blog) 
	{
		$db = XenForo_Application::getDb();
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');

		// TODO: Remove Entry Id
		$fetchOptions['where'] = "user_id='" . $db->quote($blog['user_id']) . "' AND message_state='visible'";
		$entries = $entryModel->getEntries($fetchOptions);
		foreach ($entries as $entry)
		{
			$this->generateEntry($blog, $entry);
		}

		$this->compressResourcesUsingZip($blog);
		$this->cleanUpTemporaryFiles($blog);
		
		return $this->getZipFile($blog);		
	}
	
	/**
	 * Generates a temporary file for the entry
	 * @param array $blog	The data for the blog
	 * @param array $entry	The data for the entry
	 */
	protected function generateEntry(array $blog, array $entry)
	{
		$filePath = $this->getFileDir($blog, $entry) . '/';
		$filePath .= $this->sanitizeTitle($entry);
		$filePath .= '.html';
		
		// get the comments
		$fetchOptions = array(
			'join' => XfAddOns_Blogs_Model_Comment::JOIN_USER
		);
		$comments = $this->commentModel->getCommentsForEntry($entry, $fetchOptions);
		XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($comments, $this->bbCodeParser, $this->bbCodeOptions);

		// get the attachments
		$attachments = $this->saveAttachments($blog, $entry);
		
		// build the html
		$entryHtml = new XenForo_BbCode_TextWrapper($entry['message'], $this->bbCodeParser);
		
		$template = $this->getTemplate();
		$template->setParam('title', $entry['title']);
		$template->setParam('content', $entryHtml);
		$template->setParam('dateHtml', date('d/F/Y h:i', $entry['post_date']));
		$template->setParam('comments', $comments);
		$template->setParam('attachments', $attachments);
		
		$content = $template->render();
		file_put_contents($filePath, $content);
		chmod($filePath, 0666);
	}
	
	/**
	 * Save all the attachments contained by a particular entry into the system. This will be used
	 * to later re-create the zip file
	 * @param array $blog	The data for the blog
	 * @param array $entry	The data for the entry
	 */
	private function saveAttachments(array $blog, array $entry)
	{
		$attachments = $this->attachmentModel->getAttachmentsByContentId('xfa_blog_entry', $entry['entry_id']);
		if (count($attachments) == 0)
		{
			return;
		}
		
		$path = $this->getFileDir($blog, $entry) . '/attachments';
		if (!is_dir($path))
		{
			mkdir($path, 0777, true);
		}
		
		$ret = array();
		foreach ($attachments as $attachment)
		{
			$filename = $attachment['filename'];
			$data = $this->attachmentModel->getAttachmentDataFilePath($attachment);
			if (is_file($data))
			{
				copy($data, $path . '/' . $filename);
				$ret[] = 'attachments/' . $filename;
			}
		}
		return $ret;
	}
	
	/**
	 * Sanitize the title so we can use it as a filename
	 * @param string $title	The title that we will sanitize
	 */
	protected function sanitizeTitle(array $entry)
	{
		$title = $entry['title'];
		$title = strtolower($title);
		$title = preg_replace('/\\s+/', '_', $title);
		$title = preg_replace('/[^a-z_]/', '', $title);
		return date('d', $entry['post_date']) . '_' . $title;
	}
	
	/**
	 * Create a zip file containing all the html files that were previously generated. This will generate a zip file with all the blog
	 * entries available
	 */
	protected function compressResourcesUsingZip(array $blog)
	{
		$tmpDir = $this->getTmpDir($blog);
		$zipFile = $this->getZipFile($blog);
		
		$zipArchive = new ZipArchive();
		$result = $zipArchive->open($zipFile, ZIPARCHIVE::CREATE);
		if ($result !== true)
		{
			throw new XfAddOns_Blogs_Helper_ZipException('Error: ' . $result); 
		}
		
		$this->addResourcesInDirectory($zipArchive, $tmpDir, $tmpDir);
		$zipArchive->close();

		// if the file is there, make it world readable (easy to delete later)
		if (is_file($zipFile))
		{
			chmod($zipFile, 0666);
		}
		else
		{
			$msg = new XenForo_Phrase('xfa_blog_create_zip_error');
			throw new XfAddOns_Blogs_Helper_ZipException($msg);
		}
	}
	
	/**
	 * Add all the resources found in a particular directory. This method iterates over
	 * the directory, adds the files and goes recursive on the subdirectory
	 */
	protected function addResourcesInDirectory(ZipArchive $zipArchive, $dir, $startDirectory)
	{
		$handle = opendir($dir);
		while ($file = readdir($handle))
		{
			if ($file == '.' || $file == '..')
			{
				continue;
			}
			
			$path = $dir . '/' . $file;
			if (is_dir($path))
			{
				$this->addResourcesInDirectory($zipArchive, $path, $startDirectory);
				continue;
			}

			if (!is_file($path))
			{
				continue;
			}
			if (substr($path, -4) !== 'html' && strpos($path, 'attachments/') === false)
			{
				continue;
			}
			
			$localFile = str_replace($startDirectory, "", $path);
			$success = $zipArchive->addFile($path, 'blog/' . $localFile);
			if (!$success)
			{
				XenForo_Error::logException(new Exception("Failed to write file: " . $path), false);
			}
		}		
	}
	
	/**
	 * Returns the temporary directory where the backup for the user is going
	 * to be stored. This is the system directory and the user
	 */
	protected function getTmpDir($blog)
	{
		$tmpDir = sys_get_temp_dir() . '/user_' . $blog['user_id'];
		if (!is_dir($tmpDir))
		{
			mkdir($tmpDir, 0777, true);
		}
		return $tmpDir;
	}
	
	/**
	 * For a particular entry, we will store the data in a directory that has the year
	 * and month. We will generate that directory from the entry date
	 * 
	 * @param array $blog	Reference to the blog information
	 * @param array $entry	Reference to the entry information
	 * @return string	The directory in which the entry html should be stored
	 */
	protected function getFileDir($blog, $entry)
	{
		$tmpDir = $this->getTmpDir($blog);
		$tmpDir .= '/';
		$tmpDir .= date('Y', $entry['post_date']) . '/';
		$tmpDir .= date('m', $entry['post_date']);
		
		if (!is_dir($tmpDir))
		{
			mkdir($tmpDir, 0777, true);
		}
		return $tmpDir;
	}
	
	/**
	 * Returns the name of the zip file that will be written
	 * @param array $blog	A reference to the blog data
	 * @return string	The file path for the zip file
	 */
	protected function getZipFile($blog)
	{
		return sys_get_temp_dir() . '/' . 'archive_' . $blog['user_id'] . '.zip';
	}
	
	/**
	 * Returns the template that will be used to render the blog content for the download
	 * @return XenForo_Template_Public
	 */
	protected function getTemplate()
	{
		if (!self::$template)
		{
			self::$template = new XenForo_Template_Public('xfa_blog_download_html');
			$visitor = XenForo_Visitor::getInstance();
			$options = XenForo_Application::get('options');
		
			$styleId = $visitor->get('style_id');
			$styleId = $styleId ? $styleId : $options->defaultStyleId;
			self::$template->setStyleId($styleId);
		
			$languageId = $visitor->get('language_id');
			$languageId = $languageId ? $languageId : $options->defaultLanguageId;
			self::$template->setLanguageId($languageId);
		}
		return self::$template;
	}
	
	/**
	 * Clean up all the temporary files that we created while creating the zip file
	 * @param array $blog	A reference to the blog information
	 */
	protected function cleanUpTemporaryFiles(array $blog)
	{
		$tmpDir = $this->getTmpDir($blog);
		$this->cleanUpDirectory($tmpDir);
	}

	/**
	 * Clean up all the files in a directory
	 * @param string $dir	The directory that we will recursively delete
	 */
	protected function cleanUpDirectory($dir)
	{
		$handle = opendir($dir);
		while ($file = readdir($handle))
		{
			if ($file == '.' || $file == '..')
			{
				continue;
			}
			
			$path = $dir . '/' . $file;
			if (is_dir($path))
			{
				$this->cleanUpDirectory($path);
				continue;
			}
			@unlink($path);	
		}
		@rmdir($dir);
	} 
	
	
	
}