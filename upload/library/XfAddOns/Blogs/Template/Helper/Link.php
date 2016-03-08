<?php

class XfAddOns_Blogs_Template_Helper_Link
{

	/**
	 * This will check if we are using custom domains, and will generate blog links like that
	 * @return string		The path that should be used for the group icon
	 */
	public static function helperBlogLink(array $blog, array $extraParams = array())
	{
		/* @var $multiModel XfAddOns_Blogs_Model_MultiBlog */
		static $multiModel;
		if ($multiModel == null)
		{
			$multiModel = XenForo_Model::create('XfAddOns_Blogs_Model_MultiBlog');
		}
		return $multiModel->getBlogUrl($blog, $extraParams);
	}

}