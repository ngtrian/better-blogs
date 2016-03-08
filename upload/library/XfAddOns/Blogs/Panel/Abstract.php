<?php

/**
 * Some common functionality for dealing with the cache logic
 */
abstract class XfAddOns_Blogs_Panel_Abstract
{
	
	/**
	 * @var XfAddOns_Blogs_Model_Blog
	 */
	protected $blogModel;
	
	/**
	 * @var XfAddOns_Blogs_Model_Entry
	 */
	protected $entryModel;
	
	/**
	 * @var XfAddOns_Blogs_Model_Comment
	 */
	protected $commentsModel;
	
	/**
	 * @var XenForo_Model_DataRegistry
	 */
	protected $registryModel;
	
	/**
	 * Constructor for this one is made public
	 */
	public function __construct()
	{
		$this->registryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
		$this->blogModel = XenForo_Model::create('XfAddOns_Blogs_Model_Blog');
		$this->entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		$this->commentsModel = XenForo_Model::create('XfAddOns_Blogs_Model_Comment');
	}	
	
	/**
	 * Tries to retrieve the data from the cache, where the cacheKey is known. If the data does not exist,
	 * it will return an empty array and schedule a deferred task to retrieve and fetch the data
	 */
	public function getFromCache($deferredTask, $cacheKey, $orderBy, $direction, $limit)
	{
		// look it up from the cache
		$cache = $this->registryModel->get($cacheKey);
		
		// the panel may already have a task for recalculating the information. Let's wait for it to finish and just return the dummy cache info
		if ($cache && isset($cache['in_progress']) && $cache['in_progress'] > XenForo_Application::$time && !isset($_REQUEST['forceDeferred']))
		{
			return $cache['data'];
		}
		// If the information already exists, return from the cache
		if ($cache && isset($cache['expires']) && $cache['expires'] > XenForo_Application::$time && !isset($_REQUEST['skipCache']))
		{
			return $cache['data'];
		}

		/* @var $deferred XenForo_Model_Deferred */
		$deferred = XenForo_Model::create('XenForo_Model_Deferred');
		$exists = $deferred->getDeferredByKey($cacheKey);
		if (!empty($exists))
		{
			return $cache ? $cache['data'] : array();
		}
		
		// if the task does not exist, schedule it
		$options = array(
			'cacheKey' => $cacheKey,
			'orderBy' => $orderBy,
			'direction' => $direction,
			'limit' => $limit,
			'sqlWhereHint' => ($cache && isset($cache['sqlWhereHint'])) ? $cache['sqlWhereHint'] : null
		);
		$deferred->defer('XfAddOns_Blogs_Deferred_PanelInfo_' . $deferredTask, $options, $cacheKey);
	
		// and prevent duplicates
		if (is_array($cache))
		{
			$cache['in_progress'] = XenForo_Application::$time + 300;	// give 5 minutes for the process to complete
		}
		else
		{
			$cache = array(
				'data' => array(),
				'in_progress' => 1
			);
		}
		$this->registryModel->set($cacheKey, $cache);
		return $cache['data'];
	}	
	
	
} 