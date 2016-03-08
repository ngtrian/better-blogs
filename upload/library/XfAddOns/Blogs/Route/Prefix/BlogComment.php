<?php

/**
 * Prefix to build urls for the blog comments
 */
class XfAddOns_Blogs_Route_Prefix_BlogComment implements XenForo_Route_Interface
{

	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'comment_id');
		$majorNavSection = 'xfa-blogs';
		return $router->getRouteMatch('XfAddOns_Blogs_ControllerPublic_BlogComment', $action, $majorNavSection);
	}

	/**
	 * Method to build a link that includes the comment_id
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'comment_id');
	}

}