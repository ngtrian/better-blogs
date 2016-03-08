<?php

/**
 * Prefix to build urls for the watched blogs section
 */
class XfAddOns_Blogs_Route_Prefix_Watched implements XenForo_Route_Interface
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$majorNavSection = 'xfa-blogs';
		return $router->getRouteMatch('XfAddOns_Blogs_ControllerPublic_Watched', $routePath, $majorNavSection);
	}

}