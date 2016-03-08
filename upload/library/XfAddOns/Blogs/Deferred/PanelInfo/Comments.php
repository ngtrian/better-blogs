<?php

class XfAddOns_Blogs_Deferred_PanelInfo_Comments extends XfAddOns_Blogs_Deferred_PanelInfo_Abstract
{

	/**
	 * Executed as a deferred task, it will calculate the information
	 */
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		try
		{
			$panel = new XfAddOns_Blogs_Panel_Comments();
			$comments = $panel->getComments($data['orderBy'], $data['direction'], $data['limit'] + 32, $data['sqlWhereHint']);
			$storage = array(
				'expires' => XenForo_Application::$time + XenForo_Application::getOptions()->xfa_blogs_cacheTime * 60,
				'data' => $comments,
				'in_progress' => 0,
				'sqlWhereHint' => $this->getWhereHint($comments, $data['orderBy'], $data['direction'])
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
	 * Computes a "WHERE" hint that can be applied to further retrievals of the comments
	 *
	 * @param array $comments
	 * @param string $direction
	 */
	protected function getWhereHint($comments, $orderBy, $direction)
	{
		if (empty($comments))
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
			foreach ($comments as $comment)
			{
				$max = max($comment['comment_id'], $max);
			}
			return ' comment_id <= ' . $max;
		}
		if ($direction == 'DESC')
		{
			$min = PHP_INT_MAX;
			foreach ($comments as $comment)
			{
				$min = min($comment['comment_id'], $min);
			}
			return ' comment_id >= ' . $min;
		}
	}	
	
}