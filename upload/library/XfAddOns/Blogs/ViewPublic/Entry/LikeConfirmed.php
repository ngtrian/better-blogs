<?php

class XfAddOns_Blogs_ViewPublic_Entry_LikeConfirmed extends XenForo_ViewPublic_Base
{

	public function renderJson()
	{
		$message = $this->_params['entry'];
		if (!empty($message['likeUsers']))
		{
			$params = array(
				'message' => $message,
				'likesUrl' => XenForo_Link::buildPublicLink('xfa-blog-entry/likes', $message)
			);
			$output = $this->_renderer->getDefaultOutputArray(get_class($this), $params, 'likes_summary');
		}
		else
		{
			$output = array('templateHtml' => '', 'js' => '', 'css' => '');
		}

		$output += XenForo_ViewPublic_Helper_Like::getLikeViewParams($this->_params['liked']);
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}

}