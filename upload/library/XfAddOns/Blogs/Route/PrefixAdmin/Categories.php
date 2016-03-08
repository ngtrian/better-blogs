<?php

class XfAddOns_Blogs_Route_PrefixAdmin_Categories implements XenForo_Route_Interface
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'category_id');
		return $router->getRouteMatch('XfAddOns_Blogs_ControllerAdmin_Categories', $action, 'applications');
	}

	/**
	 * Method to build a link that includes the category_id
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'category_id');
	}

}