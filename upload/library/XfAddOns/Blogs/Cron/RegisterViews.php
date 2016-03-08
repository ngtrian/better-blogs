<?php

class XfAddOns_Blogs_Cron_RegisterViews
{
	
	/**
	 * Method called to recount the views
	 */
	public static function run()
	{
		$cron = new XfAddOns_Blogs_Cron_RegisterViews();
		$cron->processViews();	
	}

	/**
	 * This method is called to process all the pending views. The views are not registered instantly but rather
	 * are queued and updated every 15 minutes
	 */
	private function processViews()
	{
		$db = XenForo_Application::getDb();
		
		XenForo_Db::beginTransaction();
		$sql = "SELECT * FROM xfa_blog_deferred_view FOR UPDATE";
		$stmt = new Zend_Db_Statement_Mysqli($db, $sql);
		$stmt->execute();

		// first, let's get a nice summary
		$summary = array(
			'blog' => array(),
			'entry' => array()
		);
		while ($data = $stmt->fetch())
		{
			$id = $data['id'];
			$type = $data['type'];
			if (!isset($summary[$type][$id]))
			{
				$summary[$type][$id] = 0;
			}
			$summary[$type][$id]++;
		}
		
		// we have the summary, we can release the lock
		$db->query("DELETE FROM xfa_blog_deferred_view");
		XenForo_Db::commit();
		
		// second, let's do the updates for the blogs
		foreach ($summary['blog'] as $userId => $updateTotal)
		{
			$db->query("UPDATE xfa_blog SET view_count = view_count + ? WHERE user_id = ?",
				array($updateTotal, $userId));
		}
		
		// third, let's do the updates for the entries
		foreach ($summary['entry'] as $entryId => $updateTotal)
		{
			$db->query("UPDATE xfa_blog_entry SET view_count = view_count + ? WHERE entry_id = ?",
					array($updateTotal, $entryId));
		}		
	}
	
	
}
