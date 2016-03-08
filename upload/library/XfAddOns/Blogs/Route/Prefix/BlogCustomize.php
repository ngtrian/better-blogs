<?php

/**
 * Builds url to the customization actions of the blog
 */
class XfAddOns_Blogs_Route_Prefix_BlogCustomize implements XenForo_Route_Interface
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'user_id');
		$majorNavSection = 'xfa-blogs';
		
		$route = $router->getRouteMatch('XfAddOns_Blogs_ControllerPublic_BlogCustomize', $action, $majorNavSection);
		if ($action == 'download')
		{
			$route->setResponseType('raw');
		}
		return $route;
	}

	/**
	 * Method to build a link that includes the user_id
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'user_id');
	}

}