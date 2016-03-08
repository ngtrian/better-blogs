<?php

/**
 * The Robots.php controller is the one responsible for generating the robots.txt file. We override this class because, when running
 * multisite, we want to return the sitemap for the domain, not the generic sitemap (which we have no use for) 
 */
class XfAddOns_Blogs_Override_ControllerPublic_Robots extends XFCP_XfAddOns_Blogs_Override_ControllerPublic_Robots
{
	
	public function getSitemapLocation()
	{
		/* @var $multiModel XfAddOns_Blogs_Model_MultiBlog */
		$multiModel = XenForo_Model::create('XfAddOns_Blogs_Model_MultiBlog');
		$subdomain = $multiModel->getCurrentSubdomain();
		if (!$multiModel->isMultiBlogEnabled())
		{
			return parent::getSitemapLocation();
		}
		if (empty($subdomain) || $subdomain == 'www' || $subdomain == 'forums' || $subdomain == 'forum')
		{
			return parent::getSitemapLocation();
		}
		
		$fileExtension = function_exists('gzopen') ? '.xml.gz' : '.xml';
		$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
		return $protocol . '://' . $_SERVER['HTTP_HOST'] . '/sitemap/blog.' . $subdomain . $fileExtension;
	}
	

	
}