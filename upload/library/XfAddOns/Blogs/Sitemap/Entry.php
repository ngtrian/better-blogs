<?php

/**
 * Class used to generate the sitemap contents for threads
 */
class XfAddOns_Blogs_Sitemap_Entry extends XfAddOns_Sitemap_Sitemap_BasePagination
{

	/**
	 * @var array
	 */
	private $blog;
	
	/**
	 * Constructor.
	 * Initializes the map with the root set as urlset
	 */
	public function __construct($blog = null)
	{
		parent::__construct('urlset');
		$this->blog = $blog;
	}

	/**
	 * Generate the entries part of the sitemap
	 */
	public function generate()
	{
		$sitemaps = array();
		XfAddOns_Sitemap_Logger::debug('Generating entries...');

		while (!$this->isFinished)
		{
			XfAddOns_Sitemap_Logger::debug('-- Starting at ' . $this->lastId . ' and generating ' . $this->maxUrls .' urls...');
			$this->generateStep($this->maxUrls);
			if (!$this->isEmpty)
			{
				$prefix = $this->blog ? ($this->blog['blog_key'] . '.entries') : 'entries';
				$sitemaps[] = $this->save($this->getSitemapName($prefix));
			}
		}
		return $sitemaps;
	}	
	
	/**
	 * Append the information about the threads to the sitemap
	 */
	public function generateStep($totalUrls)
	{
		$this->initialize();

		$db = XenForo_Application::getDb();
		$sql = "
			SELECT
				xfa_blog_entry.*
			FROM xfa_blog_entry
			INNER JOIN xfa_blog ON xfa_blog_entry.user_id = xfa_blog.user_id
			INNER JOIN xf_user_privacy ON xfa_blog.user_id = xf_user_privacy.user_id 
			WHERE
				entry_id > ? AND
				" . ($this->blog ? ('xfa_blog_entry.user_id = ' . $this->blog['user_id'] . ' AND') : '') . "
				message_state = 'visible' AND
				allow_view_blog = 'everyone'
			ORDER BY
				xfa_blog_entry.entry_id
			";
		
		$st = new Zend_Db_Statement_Mysqli($db, $sql);
		$st->execute( array( $this->lastId ) );

		/* @var $multiModel XfAddOns_Blogs_Model_MultiBlog  */
		$options = XenForo_Application::getOptions();
		$multiModel = XenForo_Model::create('XfAddOns_Blogs_Model_MultiBlog');
		$blogUrl = $this->blog ? $multiModel->getBlogUrl($this->blog) : null;
		
		while ($data = $st->fetch())
		{
			$this->lastId = $data['entry_id'];
			if (!empty($options->xfa_blogs_domain)) 
			{
				$url = $blogUrl . '/' . XenForo_Link::buildPublicLink('xfa-blog-entry', $data);
			}
			else
			{
				$url = XenForo_Link::buildPublicLink('canonical:xfa-blog-entry', $data);
			}
			$this->addUrl($url, $data['post_date']);

			// We may have to break if we reached the limit of threads to include in a single file
			$totalUrls--;
			if ($totalUrls <= 0)
			{
				break;
			}
		}

		// if we still have data, that means that we did not finish fetching the information
		$this->isFinished = !$st->fetch();
		$st->closeCursor();
	}

}

