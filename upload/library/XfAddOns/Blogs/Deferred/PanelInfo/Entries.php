<?php

class XfAddOns_Blogs_Deferred_PanelInfo_Entries extends XfAddOns_Blogs_Deferred_PanelInfo_Abstract
{
	
	/**
	 * Executed as a deferred task, it will calculate the information
	 */
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		try
		{
			$panel = new XfAddOns_Blogs_Panel_Entries();
			$entries = $panel->getEntries($data['orderBy'], $data['direction'], $data['limit'] + 32, $data['sqlWhereHint']);
			$storage = array(
				'expires' => XenForo_Application::$time + XenForo_Application::getOptions()->xfa_blogs_cacheTime * 60,
				'data' => $entries,
				'in_progress' => 0,
				'sqlWhereHint' => $this->getWhereHint($entries, $data['orderBy'], $data['direction'])
			);
			$this->registryModel->set($data['cacheKey'], $storage);
		}
		catch (Exception $ex)
		{
			$this->registryModel->delete($data['cacheKey']);
			XenForo_Error::logException($ex, false);
		}
	}

	/**
	 * Computes a "WHERE" hint that can be applied to further retrievals of the entries
	 *
	 * @param array $entries
	 * @param string $direction
	 */
	protected function getWhereHint($entries, $orderBy, $direction)
	{
		if (empty($entries))
		{
			return '';
		}
		if ($orderBy != 'post_date')
		{
			return '';
		}
	
		if ($direction == 'ASC')
		{
			$max = -1;
			foreach ($entries as $entry)
			{
				$max = max($entry['entry_id'], $max);
			}
			return ' entry_id <= ' . $max;
		}
		if ($direction == 'DESC')
		{
			$min = PHP_INT_MAX;
			foreach ($entries as $entry)
			{
				$min = min($entry['entry_id'], $min);
			}
			return ' entry_id >= ' . $min;
		}
	}	
	
}