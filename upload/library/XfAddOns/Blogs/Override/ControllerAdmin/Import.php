<?php

class XfAddOns_Blogs_Override_ControllerAdmin_Import extends XFCP_XfAddOns_Blogs_Override_ControllerAdmin_Import
{
	
	public function actionDoReset()
	{
		XenForo_Model::create('XenForo_Model_DataRegistry')->delete('importSession');
		return $this->responseReroute('XenForo_ControllerAdmin_Import', 'index');
	}
	
}