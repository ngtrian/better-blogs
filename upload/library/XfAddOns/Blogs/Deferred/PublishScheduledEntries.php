<?php 

class XfAddOns_Blogs_Deferred_PublishScheduledEntries extends XfAddOns_Blogs_Deferred_PanelInfo_Abstract
{
	
	/**
	 * Executed as a deferred task, this will publish the scheduled entries
	 */
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		XfAddOns_Blogs_Cron_PublishScheduledEntries::run();
	}
	
	
}