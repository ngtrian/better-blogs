<?php

/**
 * Prefix to build urls for the blog categories
 */
class XfAddOns_Blogs_Route_Prefix_BlogCategory implements XenForo_Route_Interface
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'category_id');
		$majorNavSection = 'xfa-blogs';
		return $router->getRouteMatch('XfAddOns_Blogs_ControllerPublic_BlogCategory', $action, $majorNavSection);
	}

	/**
	 * Method to build a link that includes the category_id
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'category_id', 'category_name');
	}

}