<?php

class XfAddOns_Blogs_Deferred_CommentsBlog extends XenForo_Deferred_Abstract
{
	
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		/* @var $rebuild XfAddOns_Blogs_Model_Rebuild */
		$rebuild = XenForo_Model::create('XfAddOns_Blogs_Model_Rebuild');
		$rebuild->recountCommentTotalsOnBlog();
	}
	
}