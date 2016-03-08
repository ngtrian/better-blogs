<?php

class XfAddOns_Blogs_ViewPublic_BlogHome_Version extends XenForo_ViewPublic_Base
{
	
	public function renderHtml()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($this->_params);
	}

	public function renderJson()
	{
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($this->_params);
	}
	
	
	
}