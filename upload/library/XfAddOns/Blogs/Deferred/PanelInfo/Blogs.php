<?php

class XfAddOns_Blogs_Deferred_PanelInfo_Blogs extends XfAddOns_Blogs_Deferred_PanelInfo_Abstract
{
	
	/**
	 * Executed as a deferred task, it will calculate the information
	 */
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		try
		{
			$panel = new XfAddOns_Blogs_Panel_Blogs();
			$blogs = $panel->getBlogs($data['orderBy'], $data['direction'], $data['limit'] + 32, $data['sqlWhereHint']);
			$storage = array(
				'expires' => XenForo_Application::$time + XenForo_Application::getOptions()->xfa_blogs_cacheTime * 60,
				'data' => $blogs,
				'in_progress' => 0,
				'sqlWhereHint' => $this->getWhereHint($blogs, $data['orderBy'], $data['direction'])
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
	 * Computes a "WHERE" hint that can be applied to further retrievals of the blogs
	 *
	 * @param array $blogs
	 * @param string $direction
	 */
	protected function getWhereHint($blogs, $orderBy, $direction)
	{
		if (empty($blogs))
		{
			return '';
		}
		if ($orderBy != 'create_date')
		{
			return '';
		}
	
		if ($direction == 'ASC')
		{
			$max = -1;
			foreach ($blogs as $blog)
			{
				$max = max($blog['user_id'], $max);
			}
			return ' blog.user_id <= ' . $max;
		}
		if ($direction == 'DESC')
		{
			$min = PHP_INT_MAX;
			foreach ($blogs as $blog)
			{
				$min = min($blog['user_id'], $min);
			}
			return ' blog.user_id >= ' . $min;
		}
	}	
	
}