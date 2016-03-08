<?php

/**
 * Prefix to build urls for the user blog
 */
class XfAddOns_Blogs_Route_Prefix_BlogHome implements XenForo_Route_Interface
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$majorNavSection = 'xfa-blogs';
		return $router->getRouteMatch('XfAddOns_Blogs_ControllerPublic_BlogHome', $routePath, $majorNavSection);
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