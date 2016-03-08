<?php

/**
 * Router class for building lists to the blog list
 */
class XfAddOns_Blogs_Route_Prefix_BlogList implements XenForo_Route_Interface
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$majorNavSection = 'xfa-blogs';
		return $router->getRouteMatch('XfAddOns_Blogs_ControllerPublic_BlogList', $routePath, $majorNavSection);
	}

	/**
	 * Method to build a link that includes the user_id
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLink($outputPrefix, $action, $extension);
	}

}