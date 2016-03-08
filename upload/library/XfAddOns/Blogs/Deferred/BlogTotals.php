<?php

/**
 * Rebuilds all the blog totals
 * This is invoked through the AdminCP on the Update Counters section
 */
class XfAddOns_Blogs_Deferred_BlogTotals extends XenForo_Deferred_Abstract
{
	
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		/* @var $rebuild XfAddOns_Blogs_Model_Rebuild */
		$rebuild = XenForo_Model::create('XfAddOns_Blogs_Model_Rebuild');
		$rebuild->recountBlogTotals(); 			// entries in a blog
		$rebuild->recountEntriesTotals(); 		// comments in an entry
		$rebuild->recountCommentTotalsOnBlog(); // coments in a blog
		
		// rebuild the blog totals
		$cron = new XfAddOns_Blogs_Cron_Stats();
		$cron->updateCounters();		
	}
	
}