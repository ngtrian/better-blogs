<?php

abstract class XfAddOns_Blogs_Deferred_PanelInfo_Abstract extends XenForo_Deferred_Abstract
{
	
	/**
	 * @var XenForo_Model_DataRegistry
	 */
	protected $registryModel;	
	
	/**
	 * Constructor for this one is made public
	 */
	public function __construct()
	{
		parent::__construct();
		$this->registryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
	}	
	
}
