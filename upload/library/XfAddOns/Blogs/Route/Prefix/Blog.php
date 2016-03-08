<?php

/**
 * Prefix to build urls for the user blog
 */
class XfAddOns_Blogs_Route_Prefix_Blog implements XenForo_Route_Interface
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'user_id');
		$majorNavSection = 'xfa-blogs';
		return $router->getRouteMatch('XfAddOns_Blogs_ControllerPublic_Blog', $action, $majorNavSection);
	}

	/**
	 * Method to build a link that includes the user_id
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		// though it might be tempting to use the blog title instead, the route is better served with just the username
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'user_id', 'username');
	}

}