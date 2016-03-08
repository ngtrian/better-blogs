<?php 

class XfAddOns_Blogs_MultiBlog_Listener
{
	
	/**
	 * @var XenForo_FrontController
	 */
	private $fc;
	
	/**
	 * @var XfAddOns_Blogs_Model_Blog
	 */
	private $blogModel;
	
	/**
	 * @var XfAddOns_Blogs_Model_MultiBlog
	 */
	private $multiModel;
	
	/**
	 * Constructor. Initializes all objects needed in this class
	 */
	public function __construct($fc)
	{
		$this->blogModel = new XfAddOns_Blogs_Model_Blog();	// really early, that is why no XenForo_Model::create
		$this->multiModel = new XfAddOns_Blogs_Model_MultiBlog();
		$this->fc = $fc;
	}

	/**
	 * Call this method to completely halt the execution of the program and return a 404. Rest of the controller won't be returned, and the program
	 * will immediately exit.
	 */
	private function haltNotFound()
	{
		header('HTTP/1.1 404 Not Found');
		print "404 Not Found";
		exit;		// yes, exit. Halt rest of the execution		
	}
	
	/**
	 * We consider ourselves to be at he root if there is no subdomain, if it is www
	 * @param unknown $subdomain
	 */
	protected function isAtRoot($subdomain)
	{
		return empty($subdomain) || $subdomain == 'www' || $subdomain == 'forums' || $subdomain == 'forum';		
	}
	
	/**
	 * This method will check the request, if we are in a blog or in a homepage with domains enabled, we need to rewrite
	 * to display the appropiate blog
	 */
	public function preRoute()
	{
		$options = XenForo_Application::getOptions();
		
		// if we are not in the blogs domain, we might as well just return. We don't really care. We are either on the main site, or on other alias that we don't care about
		if (!$this->multiModel->isInBlogsDomain())
		{
			return;
		}
		
		// we are going to give special license to the "www" and "forums" subdomain, since it is not really possible that they are blogs
		$subdomain = $this->multiModel->getCurrentSubdomain();
		if ($this->isAtRoot($subdomain))
		{
			// at this point, there are two possibilites, either the admin is using it's own domain for blog hosting, or a completely different domain
			// if it is the same domain, we need to let him pass (as we don't want to block the main forum!!), however, if it's a different domain, we'll return a 404
			if (empty($options->xfa_blogs_forum_domain))	// if this is empty, we assume it's the same one for forums and blogs
			{
				return;
			}
			
			// if the person is using the same domain for forums and blogs, we will return now (we don't want to display blog index!)
			if ($options->xfa_blogs_forum_domain == $options->xfa_blogs_domain)
			{
				return;
			}

			// if he is using a different domain, we will assume that the index is being used for the blogs home page
			$this->routeToPage('xfa-blog-home');
			return;
		}
		
		// so, we have a subdomain, let's see if that maps to an actual blog
		$blog = $this->blogModel->getBlogForKey($subdomain);
		if (!$blog)		// accesing through a subdomain, but no blog for that exists
		{
			$this->haltNotFound();
		}
		
		// at this point, we are on a subdomain, and we found the blog, route to the blog if necessary
		$this->routeToPage('xfa-blogs', $blog);
	}
	
	/**
	 * Routes to a particular page. This method is called when we passed checks and we need to know whether we need to redirect
	 * back to the main site, or display the requested page. Three things would happen
	 * 		1) The request is let through, XenForo will process the request (good for AJAX)
	 * 		2) The request is rewritten to the provided url (e.g. xfa-blog-home)
	 * 		3) The request is redirected to the main forum
	 * 
	 * @param string $route		The route (e.g. xfa-blog-home)
	 * @param array $params		Options to pass to the buildLink method
	 */
	protected function routeToPage($routeName, array $params = null)
	{
		$options = XenForo_Application::getOptions();
		// check if the request is valid for blogs
		$router = new XenForo_Router();
		$route = $router->getRoutePath($this->fc->getRequest());
		if ($this->isAllowedRequestForSubdomain($route))
		{
			return;
		}
		
		// if there is a subdomain, but no query string, that means we are in the main page of the blog. Rewrite and get the home
		if ($route == '')
		{
			/* this one should match the router XfAddOns_Blogs_Route_Prefix_Blog */
			$link = XenForo_Link::buildPublicLink($routeName, $params);
			if (substr($link, 0, 1) !== '/')
			{
				$link = '/' . $link;		// but we always need to make it with a trailing slash
			}
		
			// setup now the query string
			if (strpos($link, '?'))
			{
				$_SERVER['QUERY_STRING'] = substr($link, strpos($link, '?') + 1);
			}
			else
			{
				$_SERVER['QUERY_STRING'] = '';
			}
			$_SERVER['REQUEST_URI'] = $link;
				
			// we'll have to reset this, since we hijacked the paths, and the old Zend Request object is no longer valid
			$this->fc->setRequest(new Zend_Controller_Request_Http());
			$this->fc->setResponse(new Zend_Controller_Response_Http());
			$this->fc->setRequestPaths();
			return;
		}
		
		// at this step, we should redirect the user to the main forum, since we are browsing a subdomain but we are not in blogs (we may be in forums, etc), and we don't
		// want the user to browse the forums on a subdomain
		if ($options->useFriendlyUrls)
		{
			$url = $options->boardUrl . '/' . $route;
			$url .= !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
		}
		else
		{
			$url = $options->boardUrl;
			$url .= substr($url, -1, 1) == '/' ? '' : '/';
			$url .= 'index.php';
			$url .= !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
		}
		
		header('Xfa-Blog-Origin: ' . $_SERVER['REQUEST_URI']);
		header('Location: ' . $url);
		exit;		
	}
	
	/**
	 * Return a list of the pages that are considered to be "safe" to be accesed through a subdomain
	 * This method also hooks with the Route Changer addon, if that one exists, it will also map the route changes and register them as safe
	 */
	private function getSafePages()
	{
		$safePages = array( 'xfa-blog', 'login', 'logout', 'editor/', 'attachments/', 'xfa-robots' );

		$rcRoutes = XenForo_Application::get('options')->rcRoutes;
		if (!empty($rcRoutes))
		{
			foreach ($rcRoutes as $routeMap)
			{
				if (preg_match("/xfa-blog/is", $routeMap['oldRoute']))
				{
					$safePages[] = $routeMap['newRoute'];
				}
			}
		}
		
		/* @var $dataRegistry XenForo_Model_DataRegistry */
		$dataRegistry = XenForo_Model::create('XenForo_Model_DataRegistry');
		$xfRoutes = $dataRegistry->get('routeFiltersIn');
		if (!empty($xfRoutes))
		{
			foreach ($xfRoutes as $routeMap)
			{
				if (preg_match("/xfa-blog/is", $routeMap['find_route']))
				{
					$safePages[] = $routeMap['replace_route'];
				}
			}
		}
		
		return $safePages;
	}
	
	/**
	 * Inspect the request, and check if this is available for a blog subdomain. Since we are using the same XenForo instance any URL pattern would come
	 * but we don't want to honor all of them, just the blog ones.
	 * For a matter of the interactions, we will allow the AJAX requests, so we can process the alerts and the member cards
	 */
	private function isAllowedRequestForSubdomain($route)
	{
		// blog home is not allowed in a subdomain
		if (preg_match('@xfa-blog-home@is', $route) && $this->multiModel->getCurrentSubdomain() != '')
		{
			return false;
		}
		
		// we have a whitelist of actions that we will process through the subdomain
		$safePages = $this->getSafePages();
		foreach ($safePages as $page)
		{
			if (preg_match('@' . preg_quote($page, '@') . '@is', $route))
			{
				return true;
			}
		}
		
		// we'll consider ajax actions as safe, like requesting the member profile, alerts, etc
		$requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : '';
		$ajaxReferer = isset($_SERVER['HTTP_X_AJAX_REFERER']) ? $_SERVER['HTTP_X_AJAX_REFERER'] : '';
		if ($requestedWith == 'XMLHttpRequest' || !empty($ajaxReferer))
		{
			return true;
		}		
		return false;
	}
	
	/**
	 * Check if we nee to modify the cookie domain. This is mostly used for the login, so we can scope the cookies to the domain
	 * your blogs are hosted at
	 */
	public function modifyCookies()
	{
		$options = XenForo_Application::getOptions();		
		if (!$options->xfa_blogs_cookie_prefix)
		{
			return;
		}

		// if we are not in the blogs domain, get out now
		if (!$this->multiModel->isInBlogsDomain())
		{
			return;
		}
		
		$subdomain = $this->multiModel->getCurrentSubdomain();
		if ($this->isAtRoot($subdomain))
		{
			// not configured ... get out
			if (empty($options->xfa_blogs_forum_domain))
			{
				return;
			}
			// same domain ... no need to rewrite cookies
			if ($options->xfa_blogs_forum_domain == $options->xfa_blogs_domain)
			{
				return;
			}
		}
		
		// note: we will not really do a lot of sanity check in here. Let's hope people do not do silly things like 
		// assigning to a random subdomain and expect that to work
		// this will reassign the cookie domain, and refresh the config
		$config = XenForo_Application::getConfig()->toArray();
		$config['cookie']['domain'] = $options->xfa_blogs_cookie_prefix; 
		$newConfig = new Zend_Config($config, true);
		XenForo_Application::set('config', $newConfig);
	}
	
}