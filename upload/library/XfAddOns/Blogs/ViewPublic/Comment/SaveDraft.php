<?php

class XfAddOns_Blogs_ViewPublic_Comment_SaveDraft extends XenForo_ViewPublic_Base
{
	
	public function renderJson()
	{
		return array(
				'draftSaved' => $this->_params['draftSaved'],
				'draftDeleted' => $this->_params['draftDeleted']
		);
	}	
	
}