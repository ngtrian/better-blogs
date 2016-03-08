<?php

/**
 * Register the widgets provided by the blogs
 */
class XfAddOns_Blogs_Widget_Listener
{
	
	/**
	 * Register the widgets provided by the blogs
	 */
	public static function registerWidgets(array &$renderers)
	{
		if (XfAddOns_Blogs_Listener::isBlogAdvancedFeatures())
		{
			$renderers[] = 'XfAddOns_Blogs_Widget_Blogs';
			$renderers[] = 'XfAddOns_Blogs_Widget_Comments';
			$renderers[] = 'XfAddOns_Blogs_Widget_Entries';
			$renderers[] = 'XfAddOns_Blogs_Widget_Categories';
			$renderers[] = 'XfAddOns_Blogs_Widget_Stats';
		}
	}
	
}
