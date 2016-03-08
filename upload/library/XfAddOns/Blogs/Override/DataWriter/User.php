<?php

class XfAddOns_Blogs_Override_DataWriter_User extends XFCP_XfAddOns_Blogs_Override_DataWriter_User
{

	/**
	 * We have an extra option for the default visibility of the blog
	 * @return array
	 */
	protected function _getFields()
	{
		$fields = parent::_getFields();
		$fields['xf_user_privacy']['allow_view_blog'] = array(
			'type' => self::TYPE_STRING,
			'default' => 'everyone',
			'verification' => array('$this', '_verifyPrivacyChoice')
		);
		return $fields;
	}
	
	
	/**
	 * Add some options on insert
	 */
	protected function _preSave()
	{
		parent::_preSave();
		
		if ($this->isInsert())
		{
			$options = XenForo_Application::getOptions();
			if (!empty($options->xfa_blogs_registrationDefaults))
			{
				$this->set('allow_view_blog', $options->xfa_blogs_registrationDefaults['allow_view_blog']);
			}
		}
	}
	
	
}