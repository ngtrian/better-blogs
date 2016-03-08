<?php

class XfAddOns_Blogs_Template_Hook
{

	/**
	 * Called whenever the template object constructor is called. You may use this event to modify the name of the template being called,
	 * to modify the params being passed to the template, or to pre-load additional templates as needed.
	 *
	 * @param	string &$templateName - the name of the template to be rendered
	 * @param	array &$params - key-value pairs of parameters that are available to the template
	 * @param	XenForo_Template_Abstract $template - the template object itself
	 *
	 */
	public static function templateCreate($templateName, array &$params, XenForo_Template_Abstract $template)
	{
		if ($templateName == 'PAGE_CONTAINER')
		{
			$template->addRequiredExternal('css', 'xfa_blogs_nav');
			$template->preloadTemplate('xfa_blog_navigation_tab_link');
		}
	}

}

