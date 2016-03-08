<?php

/**
 * Class used to generate the sitemap contents for threads
 */
class XfAddOns_Blogs_Sitemap_Blog extends XfAddOns_Sitemap_Sitemap_BasePagination
{

	/**
	 * Constructor.
	 * Initializes the map with the root set as urlset
	 */
	public function __construct()
	{
		parent::__construct('urlset');
	}

	/**
	 * Generate the blogs part of the sitemap
	 */
	public function generate()
	{
		$sitemaps = array();
		XfAddOns_Sitemap_Logger::debug('Generating blogs...');
		while (!$this->isFinished)
		{
			XfAddOns_Sitemap_Logger::debug('-- Starting at ' . $this->lastId . ' and generating ' . $this->maxUrls .' urls...');
			$this->generateStep($this->maxUrls);
			if (!$this->isEmpty)
			{
				$sitemaps[] = $this->save($this->getSitemapName('blogs'));
			}
		}
		return $sitemaps;
	}	
	
	/**
	 * Append the information about the blogs to the sitemap
	 */
	public function generateStep($totalBlogs)
	{
		$this->initialize();

		$db = XenForo_Application::getDb();
		$sql = "
			SELECT
				xfa_blog.*, xf_user.*
			FROM xfa_blog
			INNER JOIN xf_user ON xfa_blog.user_id = xf_user.user_id
			INNER JOIN xf_user_privacy ON xfa_blog.user_id = xf_user_privacy.user_id 
			WHERE 
				allow_view_blog = 'everyone' AND
				xfa_blog.user_id > ?
			ORDER BY
				xfa_blog.user_id
			";
		$st = new Zend_Db_Statement_Mysqli($db, $sql);
		$st->execute( array( $this->lastId ) );

		while ($data = $st->fetch())
		{
			$this->lastId = $data['user_id'];
			$url = XenForo_Link::buildPublicLink('canonical:xfa-blogs', $data);
			$this->addUrl($url, $data['last_entry']);

			// We may have to break if we reached the limit of blogs to include in a single file
			$totalBlogs--;
			if ($totalBlogs <= 0)
			{
				break;
			}
		}

		// if we still have data, that means that we did not finish fetching the information
		$this->isFinished = !$st->fetch();
		$st->closeCursor();
	}

}

