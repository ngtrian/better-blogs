<?php

/**
 * Update the global counters of the applications 
 */
class XfAddOns_Blogs_Cron_Stats
{

	/**
	 * Constant used in the data registry
	 * @var string
	 */
	const TOTALS = 'xfab_totals';
	
	/**
	 * Method called to recount the views
	 */
	public static function run()
	{
		$cron = new XfAddOns_Blogs_Cron_Stats();
		$cron->updateCounters();
	}	
	
	/**
	 * Update the counters about the totals in the database
	 */
	public function updateCounters()
	{
		/* @var $registryModel XenForo_Model_DataRegistry */
		$registryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
		
		$db = XenForo_Application::getDb();
		$totals = $db->fetchRow("
			SELECT
				count(*) blogs,
				sum(entry_count) entries,
				sum(comment_count) comments
			FROM xfa_blog
		");
		
		$registryModel->set(self::TOTALS, $totals);
	}
	
	
}