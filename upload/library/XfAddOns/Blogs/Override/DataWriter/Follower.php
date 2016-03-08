<?php

class XfAddOns_Blogs_Override_DataWriter_Follower extends XFCP_XfAddOns_Blogs_Override_DataWriter_Follower
{

	/**
	 * When the follower is saved, we will also subscribe the user to the blog (sneaky!)
	 */
	protected function _postSave()
	{
		parent::_postSave();

		/* @var $watchModel XfAddOns_Blogs_Model_BlogWatch */
		$watchModel = XenForo_Model::create('XfAddOns_Blogs_Model_BlogWatch');
		$watchModel->subscribeIfNotSubscribed($this->get('user_id'), $this->get('follow_user_id'));
	}
	
	
}