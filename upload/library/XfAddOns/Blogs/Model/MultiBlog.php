<?php

/**
 * Model to fetch information about the multi-blog
 * This is the one stop for calculating subdomains, and reviewing information for the blog
 */
class XfAddOns_Blogs_Model_MultiBlog
{
	
	/**
	 * We store the subdomain so we only compute it once
	 * @var string
	 */
	private $subdomain;	
	
	/**
	 * Check if the user is currently in the domain that is configured for the blogs
	 */
	public function isInBlogsDomain()
	{
		$options = XenForo_Application::getOptions();
		return preg_match('@' . preg_quote($options->xfa_blogs_domain, '@') . '$@', $_SERVER["HTTP_HOST"]);
	}
	
	/**
	 * Returns the current subdomain that the user is browsing. We get this from the HTTP_HOST
	 * This is used to modify the routing information to dispatch the user to a custom blog
	 */
	public function getCurrentSubdomain()
	{
		$options = XenForo_Application::getOptions();
		if (empty($options->xfa_blogs_domain))
		{
			$this->subdomain = '';
			return $this->subdomain;
		}

		// process the subdomain information
		$result = preg_match('@([^.]+)\.' . preg_quote($options->xfa_blogs_domain, '@') . '@', $_SERVER["HTTP_HOST"], $matches);
		if (!$result)
		{
			$this->subdomain = '';
			return $this->subdomain;
		}
		
		$subdomain = $matches[1];
		$this->subdomain = $subdomain;
		return $this->subdomain;
	}
	
	/**
	 * Calculate the subdomain that the blog is supposed to have (if any), this is a wrapper for getting the blog_key, that will
	 * return empty if the option is disabled
	 * If the option is disabled this method just returns an empty string
	 */
	public function getSubdomainForBlog(array $blog)
	{
		$options = XenForo_Application::getOptions();
		if (empty($options->xfa_blogs_domain))
		{
			return '';
		}
		return $blog['blog_key'];
	}
	
	/**
	 * This will return the full host (including the http part) that holds the domain for the URL
	 * This is used for rewriting the URL for basic search & replace functionality, it is used by the canonicalization
	 * 
	 * @param array $blog
	 */
	public function getDomainHost($blog)
	{
		$subdomain = $this->getSubdomainForBlog($blog);
		if (empty($subdomain))
		{
			return '';
		}
		$options = XenForo_Application::getOptions();
		
		$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
		return $protocol . '://' . $subdomain . '.' . $options->xfa_blogs_domain;
	}
	
	/**
	 * Returns the full URL for the blogs, this is the prefered method for getting a blog url
	 * This is used by the link generation
	 * 
	 * @param array $blog			The blog information
	 * @param array $extraParams	Any additional parameters
	 * @return string				The url for the link
	 */
	public function getBlogUrl(array $blog, $extraParams = array())
	{
		$options = XenForo_Application::getOptions();
		if (empty($options->xfa_blogs_domain) || empty($blog['blog_key']))
		{
			return XenForo_Link::buildPublicLink('xfa-blogs', $blog, $extraParams);
		}
		return $this->getDomainHost($blog);
	}
	
	/**
	 * This call will return true if multiblogs is enabled. It will do basic checks over the configuration
	 * to validate if the feature is turned on and is configured in a valid way
	 */
	public function isMultiBlogEnabled()
	{
		$config = XenForo_Application::getConfig();
		$options = XenForo_Application::getOptions();
		
		// there is both a flag in config.php and the settings in the admincp
		return $config->blog && $config->blog->multisite && 
			!empty($options->xfa_blogs_domain);
	}
	
	/**
	 * This is a wrapper for returning the main domain for blogs. This is invoked from the BlogsHome page whenever
	 * we need to redirect to the "main" blogs page, and only if the blogs are using their own domain
	 */
	public function getBlogsDomain()
	{
		$options = XenForo_Application::getOptions();
		if (!$options->xfa_blogs_domain)
		{
			return '';
		}
		
		if (substr($options->xfa_blogs_domain, 0, 4) == 'http')
		{
			return $options->xfa_blogs_domain;
		}
		
		$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
		return $protocol . '://' . $options->xfa_blogs_domain; 
	}
	
	/**
	 * Return the home page for blogs.
	 * Depending on whether we are using a new domain this might return the domain, or the xfa-blog-home page
	 */
	public function getBlogsHomePage()
	{
		// if Multisite is disabled, we just generate a link to the blogs home page
		if (!$this->isMultiBlogEnabled())
		{
			return XenForo_Link::buildPublicLink('xfa-blog-home');
		}
		
		// if we are using the same domain for both, we cannot link to the root of the domain
		$options = XenForo_Application::getOptions();
		if ($options->xfa_blogs_domain == $options->xfa_blogs_forum_domain)
		{
			return XenForo_Link::buildPublicLink('xfa-blog-home');
		}
		return $this->getBlogsDomain();
	}
	
	
}