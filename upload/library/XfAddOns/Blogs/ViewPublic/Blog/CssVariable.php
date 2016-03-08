<?php

class XfAddOns_Blogs_ViewPublic_Blog_CssVariable extends XenForo_ViewPublic_Base
{

	/**
	 * Render anything that we send in the params as json
	 * @return string		A json encoded string
	 */
	public function renderJson()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($this->_params);
	}

}