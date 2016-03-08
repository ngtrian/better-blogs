<?php

class XfAddOns_Blogs_ViewPublic_BlogCustomize_Download extends XenForo_ViewPublic_Base
{
	
	/**
	 * Render anything that we send in the params as json
	 * @return string		A json encoded string
	 */
	public function renderRaw() 
	{
		$visitor = XenForo_Visitor::getInstance();
		$username = $visitor['username'];	
		$date = date('Y_m_d');
		$filename = urlencode("{$username}_{$date}") . ".json";
		
		header('Content-type: text/plain');
		header("Content-Disposition: attachment; filename=\"{$filename}\"");
		header('Content-Transfer-Encoding: binary');
		
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($this->_params, false);
	}	
	
}