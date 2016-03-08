<?php

/**
 * Panel that displays the counters for the blog totals
 */
class XfAddOns_Blogs_Panel_Stats
{
	
	/**
	 * Fetches and return the panel content. This panel shows the most recent blogs
	 */
	public function getPanelContent()
	{
		$template = new XenForo_Template_Public('xfa_blog_wf_stats', array(
			'blogTotals' => $this->getStats(),
			'visitor' => XenForo_Visitor::getInstance(),
			'title' => new XenForo_Phrase('xfa_blogs_blog_statistics')
			));
		return $template;
	}
	
	/**
	 * Return the counters from the DataRegistry
	 */
	public function getStats()
	{
		/* @var $registryModel XenForo_Model_DataRegistry */
		$registryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
		return $registryModel->get(XfAddOns_Blogs_Cron_Stats::TOTALS);
	}	
	
	
}
