<?php

/**
 * Extends the Sitemap add-on to also add a sitemap for blogs and a sitemap for entries
 */
class XfAddOns_Blogs_Override_Model_Sitemap extends XFCP_XfAddOns_Blogs_Override_Model_Sitemap
{

	/**
	 * We will add additional sitemaps. There are two options for this, either we have multi-domain enabled, in which case
	 * we will generate a sitemap per blog, or multidomain is not enabled in which case we will just add the sitemaps to
	 * the main index file
	 * 
	 * @return array
	 */
	protected function getAdditionalSitemaps()
	{
		$sitemaps = array();
		/* @var $multiBlog XfAddOns_Blogs_Model_MultiBlog */
		$multiBlog = XenForo_Model::create('XfAddOns_Blogs_Model_MultiBlog');
		$options = XenForo_Application::getOptions();
		
		if (!$multiBlog->isMultiBlogEnabled())
		{
			if ($options->xfa_blogs_sitemap['blogs'])
			{
				$sitemaps[] = 'XfAddOns_Blogs_Sitemap_Blog';
			}
			if ($options->xfa_blogs_sitemap['entries'])
			{
				$sitemaps[] = 'XfAddOns_Blogs_Sitemap_Entry';
			}
		}
		else 
		{
			if ($options->xfa_blogs_sitemap['blogs'] && $options->xfa_blogs_sitemap['entries'])
			{
				$sitemaps[] = 'XfAddOns_Blogs_Sitemap_MultiSiteSiteMap';
			}
		}
		return array_merge(parent::getAdditionalSitemaps(), $sitemaps);
	}
	
	
}