<?php

/**
 * The listener enhances some classes with functionality.
 */
class XfAddOns_Blogs_Listener
{

	/**
	 * @param string	The name of the class to be created
	 * @param array		A modifiable list of classes that wish to extend the class.
	 */
	public static function listenController($class, array &$extend)
	{
		if ($class == 'XenForo_ControllerPublic_Account')
		{
			$extend[] = 'XfAddOns_Blogs_Override_ControllerPublic_Account';
		}
		if ($class == 'XenForo_ControllerAdmin_Import')
		{
			$extend[] = 'XfAddOns_Blogs_Override_ControllerAdmin_Import';
		}
		if ($class == 'XenForo_ControllerPublic_FindNew')
		{
			$extend[] = 'XfAddOns_Blogs_Override_ControllerPublic_FindNew';
		}		
		

		if (self::isBlogAdvancedFeatures())
		{
			if ($class == 'XfAddOns_Sitemap_ControllerPublic_Robots')
			{
				$extend[] = 'XfAddOns_Blogs_Override_ControllerPublic_Robots';
			}
		}
	}
	
	/**
	 * @param string	The name of the class to be created
	 * @param array		A modifiable list of classes that wish to extend the class.
	 */
	public static function listenModel($class, array &$extend)
	{
		if ($class == 'XenForo_Model_User')
		{
			$extend[] = 'XfAddOns_Blogs_Override_Model_User';
		}		
		if ($class == 'XenForo_Model_Post')
		{
			$extend[] = 'XfAddOns_Blogs_Override_Model_Post';
		}
		if ($class == 'XenForo_Model_Import')
		{
			$extend[] = 'XfAddOns_Blogs_Override_Model_Import';
		}
		if ($class == 'XenForo_Model_ProfilePost')
		{
			$extend[] = 'XfAddOns_Blogs_Override_Model_ProfilePost';
		}
		
		if (self::isBlogAdvancedFeatures())
		{
			if ($class == 'XfAddOns_Sitemap_Model_Sitemap')
			{
				$extend[] = 'XfAddOns_Blogs_Override_Model_Sitemap';
			}
		}		
	}	
	
	/**
	 * Extend stock XenForo datawriters
	 */
	public static function listenDatawriter($class, array &$extend)
	{
		if (self::isBlogAdvancedFeatures())
		{
			if ($class == 'XenForo_DataWriter_Follower')
			{
				$extend[] = 'XfAddOns_Blogs_Override_DataWriter_Follower';
			}
		}
		if ($class == 'XenForo_DataWriter_User')
		{
			$extend[] = 'XfAddOns_Blogs_Override_DataWriter_User';
		}		
	}
	
	/**
	 * Registers variables for the XenForo Blog add-on
	 * @param XenForo_FrontController $fc
	 */
	public static function listenPreRoute(XenForo_FrontController $fc)
	{
		$options = XenForo_Application::getOptions();
		$options->blogAdvancedFeatures = self::isBlogAdvancedFeatures();
	}
	
	/**
	 * Checks if the advanced features for blogs need to be enabled. For this to work, we need
	 * to have the correct files in place, too
	 * @return boolean
	 */
	public static function isBlogAdvancedFeatures()
	{
		// if not free, or uninitialized, activate advanced features
		return XfAddOns_Blogs_Install_Version::$isFreeVersion === '0' || XfAddOns_Blogs_Install_Version::$isFreeVersion === '__IS_FREE__';
	}
	
	/**
	 * XenForo_FrontController $fc - the front controller instance. From this, you can inspect the request, response, dependency loader, etc.
	 */
	public static function listenControllerPreRouteMultiBlog(XenForo_FrontController $fc)
	{
		$config = XenForo_Application::getConfig();
		if (!$config->blog || !$config->blog->multisite)		// this is a safe check to avoid people shooting themselves on the foot
		{
			return;
		}
		$options = XenForo_Application::getOptions();
		if (empty($options->xfa_blogs_domain))
		{
			return;
		}
	
		$listener = new XfAddOns_Blogs_MultiBlog_Listener($fc);
		$listener->preRoute();
		$listener->modifyCookies();
	}	
	
	/**
	 * Adds a helper for creating the blog link
	 * @param XenForo_Dependencies_Abstract $dependencies
	 * @param array $data
	 */
	public static function listenInitDependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
        //Get the static variable $helperCallbacks and add a new item in the array.
        XenForo_Template_Helper_Core::$helperCallbacks['bloglink'] = array('XfAddOns_Blogs_Template_Helper_Link', 'helperBlogLink');
	}
	
	/**
	 * Add to the list of public params a version for the blog javascript resources
	 */
	public static function listenContainerPostDispatch(XenForo_Controller $controller, $controllerResponse, $controllerName, $action)
	{
		$controllerResponse->params['blogVersion'] = md5(XfAddOns_Blogs_Install_Version::$version);
	}


}

