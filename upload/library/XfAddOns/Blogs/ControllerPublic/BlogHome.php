<?php

/**
 * Actions for the blog home page, and any other generic action that does not require a user, an entry
 * or a comment
 */
class XfAddOns_Blogs_ControllerPublic_BlogHome extends XfAddOns_Blogs_ControllerPublic_Abstract
{

	/**
	 * XenForo registry, we use this as a cache. Usually backed up by memcache
	 * @var XenForo_Model_DataRegistry
	 */
	protected $registryModel;
	
	/**
	 * Constructor. Initializes objects on this thread
	 */
	public function __construct(Zend_Controller_Request_Http $request, Zend_Controller_Response_Http $response, XenForo_RouteMatch $routeMatch)
	{
		parent::__construct($request, $response, $routeMatch);
		$this->registryModel = $this->getModelFromCache('XenForo_Model_DataRegistry');
	}		
	
	/**
	 * Home page for the blogs, show all the latest entries for the blogs, and the latest comments
	 */
	public function actionIndex()
	{
		$options = XenForo_Application::getOptions();
		
		// canonical
		$this->canonicalizeHome();
		
		$visitor = XenForo_Visitor::getInstance();
		$allPermissions = $visitor->getPermissions();
		$blogPermissions = $allPermissions['xfa_blogs'];
			
		if (!$blogPermissions['xfa_blogs_view'])
		{
			return $this->responseNoPermission();
		}
		
		// fetch the panels
		/* @var $panelModel XfAddOns_Blogs_Model_Panel */
		$panelModel = XenForo_Model::create('XfAddOns_Blogs_Model_Panel');
		$panels = $panelModel->getPanels();
		
		// parameters for infinite scrolling
		$fetchOptions = array(
			'join' => XfAddOns_Blogs_Model_Entry::JOIN_USER + XfAddOns_Blogs_Model_Entry::JOIN_VISITOR_FOLLOW +
						XfAddOns_Blogs_Model_Entry::JOIN_BLOG + + XfAddOns_Blogs_Model_Entry::JOIN_BLOG_PRIVACY,
			'whereOptions' => XfAddOns_Blogs_Model_Blog::WHERE_PRIVACY
		);		
		
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$entriesPerPage = $options->xfa_blogs_entriesPerPage;
		$totalEntries = $this->entryModel->getTotalEntries($fetchOptions);
		
		// dispatch to the view
		$viewParams = array(
			'entries' => $this->getEntries($page),
			'panels' => $panels,
			'showRefreshPage' => $blogPermissions['xfa_blogs_admin'],
			'totalEntries' => $totalEntries,
			'entriesPerPage' => $entriesPerPage,
			'page' => $page
		);
		$containerParams = array(
			'isBlogContainer' => true,
			'noVisitorPanel' => true,
			'containerTemplate' => 'PAGE_CONTAINER'
		);		
		
		$templateName = $this->getResponseType() != 'json' ? 'xfa_blog_home' : 'xfa_blog_home_data';
		return $this->responseView('XfAddOns_Blogs_ViewPublic_BlogHome_Index', $templateName, $viewParams, $containerParams);
	}
	
	/**
	 * Reset all the caches used by the panels and displays the index
	 * @return XenForo_ControllerResponse_Reroute
	 */
	public function actionResetCache()
	{
		$this->registryModel->delete('xfa_blog_mostcomments');
		$this->registryModel->delete('xfa_blog_mostentries');
		$this->registryModel->delete('xfa_blog_comments_home');
		$this->registryModel->delete('xfab_recently_created');
		$this->registryModel->delete('xfab_recently_updated');
		$this->registryModel->delete('xfa_blog_entries_home');
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blog-home')
			);
	}
	
	/**
	 * We will get the latest entries, and store in a cache if needed
	 * @return array
	 */
	private function getEntries($page = 1)
	{
		$options = XenForo_Application::getOptions();
		$fetchOptions = array(
			'join' => XfAddOns_Blogs_Model_Entry::JOIN_USER + XfAddOns_Blogs_Model_Entry::JOIN_VISITOR_FOLLOW + XfAddOns_Blogs_Model_Entry::JOIN_BLOG + XfAddOns_Blogs_Model_Entry::JOIN_BLOG_PRIVACY,
			'limit' => (($page - 1) * $options->xfa_blogs_entriesPerPage) . ',' . $options->xfa_blogs_entriesPerPage,
			'whereOptions' => XfAddOns_Blogs_Model_Blog::WHERE_PRIVACY
			);
		
		$entries = $this->entryModel->getLatestEntries($fetchOptions);
		
		$this->entryModel->prepareEntries($entries);
		$this->entryModel->getAndMergeAttachmentsIntoEntries($entries);
		$this->entryModel->wireBlogs($entries);
		$this->entryModel->removePrivateEntriesForVisitor($entries);
		$this->categoryModel->getAndMergeSelectedCategories($entries);

		// return the entries
		return $entries;
	}

	/**
	 * From a list of entries, returns the minimum entry id
	 * @param array $entries		An array of the entries
	 * @return int
	 */
	protected function getMinEntryId($entries)
	{
		if (empty($entries))
		{
			return 0;
		}
		
		$min = PHP_INT_MAX;
		foreach ($entries as $entry)
		{
			$min = min($min, $entry['entry_id']);
		}
		return $min;		
	}
	
	/**
	 * Just a shortcut to return the login template. This is called when the user does not have a sesion started and they
	 * click on the "login" button on the sidebar
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionLoginOverlay()
	{
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_login', array());
	}

	/**
	 * First stop when we are checking for a session. This method will redirect to forwardSession, but it has the side-effect
	 * that if there are any cookies or anything to be initialized, the session will be started
	 */
	public function actionCheckForSession()
	{
		// send the session back to the blog instance
		$blogId = $this->_input->filterSingle('b', XenForo_Input::INT);
		$blog = $this->blogModel->getBlogForUser($blogId);
		if (!$blog)
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_user_required'));
		}

		$params = array(
			'b' => $blogId
		);
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blog-home/forwardSession', null, $params)
		);		
	}
	
	/**
	 * Returns the information about the current session that the user has open. This will check the cookie for the session
	 * and will send it back to the blog instance, the blog instance, if it does not have a session started, will try to use
	 * that session instead
	 */
	public function actionForwardSession()
	{
		$cookiePrefix = XenForo_Application::get('config')->cookie->prefix;
		
		// send the session back to the blog instance
		$blogId = $this->_input->filterSingle('b', XenForo_Input::INT);
		$blog = $this->blogModel->getBlogForUser($blogId);
		if (!$blog)
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_user_required'));
		}

		// view parameters and redirect
		$params = array(
			'sid' => isset($_COOKIE[$cookiePrefix . 'session']) ? $_COOKIE[$cookiePrefix . 'session'] : '',
			'u' => isset($_COOKIE[$cookiePrefix . 'user']) ? $_COOKIE[$cookiePrefix . 'user'] : ''
			);
		
		/* @var $multiModel XfAddOns_Blogs_Model_MultiBlog */
		$multiModel = $this->getModelFromCache('XfAddOns_Blogs_Model_MultiBlog');
		$domainHost = $multiModel->getDomainHost($blog);
		
		$link = '';
		if ($domainHost)
		{
			$link .= $domainHost . '/';	
		}
		$link .= XenForo_Link::buildPublicLink('xfa-blogs/startSession', $blog, $params);

		// return
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$link
		);		
	}

	/**
	 * Returns a list of all the blog entries in Atom
	 */
	public function actionEntriesRss()
	{
		$options = XenForo_Application::getOptions();
		if (!$options->blogAdvancedFeatures)
		{
			return $this->responseError(new XenForo_Phrase('xfa_blog_rss_is_disabled'), 404);
		}
		
		$entries = $this->getEntries();
		$viewParams = array(
			'entries' => $entries,
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_BlogHome_Rss', '', $viewParams);
	}	
	
	
	/**
	 * Since we are pointing multiple installations to the same XenForo for the domains functionality, we need to detect
	 * whether we are in the correct home page
	 */
	protected function canonicalizeHome()
	{
		/* @var $multiModel XfAddOns_Blogs_Model_MultiBlog */
		$multiModel = XenForo_Model::create('XfAddOns_Blogs_Model_MultiBlog');
		if (!$multiModel->isMultiBlogEnabled())
		{
			return;
		}

		$options = XenForo_Application::getOptions();
		if (empty($options->xfa_blogs_domain) || empty($options->xfa_blogs_forum_domain))
		{
			return;
		}
		if ($options->xfa_blogs_domain == $options->xfa_blogs_forum_domain)
		{
			return;
		}
		if ($options->xfa_blogs_domain == $_SERVER['HTTP_HOST'])
		{
			return;
		}
		
		$redirectUrl = $multiModel->getBlogsDomain();
		throw $this->responseException($this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				$redirectUrl));
	}	

	/**
	 * A simple handler for returning the version numbers of this package
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionVersion()
	{
		/* @var $model XenForo_Model_AddOn */
		$model = XenForo_Model::create('XenForo_Model_AddOn');
		$add = $model->getAddOnById(XfAddOns_Blogs_Install_Version::$addonId);
		
		$data = array(
			'binary' => XfAddOns_Blogs_Install_Version::$version,
			'database' => $add['version_string']
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_BlogHome_Version', '', $data);
	}
	
	/**
	 * Gets session activity details of activity records that are pointing to this controller.
	 * This must check the visiting user's permissions before returning item info.
	 * Return value may be:
	 * 		* false - means page is unknown
	 * 		* string/XenForo_Phrase - gives description for all, but no item details
	 * 		* array (keyed by activity keys) of strings/XenForo_Phrase objects - individual description, no item details
	 * 		* array (keyed by activity keys) of arrays. Sub-arrays keys: 0 = description, 1 = specific item title, 2 = specific item url.
	 *
	 * @param array $activities List of activity records
	 *
	 * @return mixed See above.
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		// generate the output
		$output = array();
		foreach ($activities AS $key => $activity)
		{
			$output[$key] = new XenForo_Phrase('xfa_blogs_viewing_blog_home_page');
		}
		return $output;
	}	
	
	
	
	
	
}
	