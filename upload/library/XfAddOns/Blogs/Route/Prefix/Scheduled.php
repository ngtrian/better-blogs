<?php

/**
 * Prefix to build urls for the blog entry
 */
class XfAddOns_Blogs_Route_Prefix_Scheduled implements XenForo_Route_Interface
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'scheduled_entry_id');
		$majorNavSection = 'xfa-blogs';
		return $router->getRouteMatch('XfAddOns_Blogs_ControllerPublic_Scheduled', $action, $majorNavSection);
	}

	/**
	 * Method to build a link that includes the entry_id
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'scheduled_entry_id', 'title');
	}

}