<?php

class XfAddOns_Blogs_Sitemap_MultiSiteSiteMap extends XfAddOns_Sitemap_Sitemap_Base
{
	
	/**
	 * Multi-site sitemap. One different xml file will be generated for each person.
	 * And the xml file will not be added to the index file
	 */
	public function generate()
	{
		XfAddOns_Sitemap_Logger::debug('Generating multisite for blogs sitemap...');
		$fetchOptions = array(
			'limit' => 100000,
			'where' => "allow_view_blog = 'everyone'",
			'join' => XfAddOns_Blogs_Model_Blog::JOIN_PRIVACY
		);
		
		/* @var $blogModel XfAddOns_Blogs_Model_Blog' */
		$blogModel = XenForo_Model::create('XfAddOns_Blogs_Model_Blog');
		$blogs = $blogModel->getBlogList($fetchOptions);
		foreach ($blogs as $blog)
		{
			$this->generateBlogSitemap($blog);
		}
	}
	
	/**
	 * Generates a sitemap for one paritcular blog instance
	 */
	private function generateBlogSitemap(array $blog)
	{
		if (empty($blog['blog_key']))
		{
			return;
		}
			
		XfAddOns_Sitemap_Logger::debug('Generating blog for domain ' . $blog['blog_key'] . '...');
		
		// check if we even neeed to re-generate it (depending ont he map having new entries)
		$indexName = 'blog.' . $blog['blog_key'] . '.xml';
		$path = $this->sitemapDir . '/' . $indexName . '.gz';
		if (is_file($path))
		{
			$lastUpdate = filemtime($path);
			if ($blog['last_entry'] <= $lastUpdate)
			{
				return;		// do not regenerate the map, sitemap is current
			}
		}
			
		$entries = new XfAddOns_Blogs_Sitemap_Entry($blog);
		$allSitemaps = $entries->generate();
		
		// generate the index file
		/* @var $multiModel XfAddOns_Blogs_Model_MultiBlog */
		$multiModel = XenForo_Model::create('XfAddOns_Blogs_Model_MultiBlog');
		$index = new XfAddOns_Sitemap_Sitemap_Index($allSitemaps, $indexName, $multiModel->getDomainHost($blog));
		$index->generate();		
	}
	
	
	
	
}