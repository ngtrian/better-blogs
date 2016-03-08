<?php

/**
 * This class is overriden for the privacy options for the blog
 */
class XfAddOns_Blogs_Override_ControllerPublic_Account extends XFCP_XfAddOns_Blogs_Override_ControllerPublic_Account
{

	/**
	 * This action is called to save the privacy settings. We override it to
	 * also save the settings that we need for profile visitors privacy
	 */
	public function actionPrivacySave()
	{
		// we'll play nice, if anything happens inside this method we will log and continue normal updates
		try
		{
			$userId = XenForo_Visitor::getUserId();
			$allowViewBlog = $this->_input->filterSingle('allow_view_blog', XenForo_Input::STRING);
			if (empty($allowViewBlog))
			{
				$allowViewBlog = 'none';
			}

			// save the privacy settings
			if ($userId)
			{
				$db = XenForo_Application::getDb();
				$db->query("UPDATE xf_user_privacy SET allow_view_blog = ? WHERE user_id = ?",
					array( $allowViewBlog, $userId ));
			}
		}
		catch (Exception $ex)
		{
			XenForo_Error::logException($ex, false);
		}

		// and cascade to the main action
		return parent::actionPrivacySave();
	}




}