<?php

class XfAddOns_Blogs_SearchHandler_Entry extends XenForo_Search_DataHandler_Abstract
{

	/**
	 * Inserts into (or replaces a record) in the index.
	 * @see XenForo_Search_DataHandler_Abstract::_insertIntoIndex()
	 */
	protected function _insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null)
	{
		$metadata = array();
		if (!empty($parentData))
		{
			$metadata = array(
				'blog_id' => $parentData['user_id']
			);
		}
		
		$title = '';
		$indexer->insertIntoIndex(
			'xfa_blog_entry', $data['entry_id'],
			$data['title'], $data['message'],
			$data['post_date'], $data['user_id'], $data['user_id'], $metadata
		);
	}

	/**
	 * Updates a record in the index.
	 * @see XenForo_Search_DataHandler_Abstract::_updateIndex()
	 */
	protected function _updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates)
	{
		$indexer->updateIndex('xfa_blog_entry', $data['entry_id'], $fieldUpdates);
	}

	/**
	 * Deletes one or more records from the index.
	 * @see XenForo_Search_DataHandler_Abstract::_deleteFromIndex()
	 */
	protected function _deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList)
	{
		$ids = array();
		foreach ($dataList AS $data)
		{
			$ids[] = $data['entry_id'];
		}

		$indexer->deleteFromIndex('xfa_blog_entry', $ids);
	}

	/**
	 * Rebuilds the index for a batch.
	 * @see XenForo_Search_DataHandler_Abstract::rebuildIndex()
	 */
	public function rebuildIndex(XenForo_Search_Indexer $indexer, $lastId, $batchSize)
	{
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		
		$entries = $entryModel->getEntryIdsInRange($lastId, $batchSize);
		$entryIds = array_keys($entries);
		if (empty($entryIds))
		{
			return false;
		}

		$this->quickIndex($indexer, $entryIds);
		return max($entryIds);
	}

	/**
	 * Rebuilds the index for the specified content.

	 * @see XenForo_Search_DataHandler_Abstract::quickIndex()
	 */
	public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds)
	{
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		
		$entries = $entryModel->getEntriesByIds($contentIds);
		foreach ($entries AS $entry)
		{
			$this->insertIntoIndex($indexer, $entry);
		}
		return true;
	}

	/**
	 * Gets the type-specific data for a collection of results of this content type.
	 * @see XenForo_Search_DataHandler_Abstract::getDataForResults()
	 */
	public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
	{
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		$fetchOptions = array(
			'join' => XfAddOns_Blogs_Model_Entry::JOIN_USER + XfAddOns_Blogs_Model_Entry::JOIN_BLOG 
		);
		return $entryModel->getEntriesByIds($ids, $fetchOptions);
	}

	/**
	 * Determines if this result is viewable.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::canViewResult()
	 */
	public function canViewResult(array $result, array $viewingUser)
	{
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		$entry = $result;
		$entryModel->prepareEntry($entry);

		/* @var $blogModel XfAddOns_Blogs_Model_Blog */
		$blogModel = XenForo_Model::create('XfAddOns_Blogs_Model_Blog');
		$blog = $result;
		$blogModel->prepareBlog($blog);		
		
		if (!isset($entry['perms']) || !$entry['perms']['canView'])
		{
			return false;
		}
		if (!isset($blog['perms']) || !$blog['perms']['canView'])
		{
			return false;
		}
		return true;		
	}

	/**
	 * Prepares a result for display.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::prepareResult()
	 */
	public function prepareResult(array $result, array $viewingUser)
	{
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		$entryModel->prepareEntry($result);
		return $result;
	}

	/**
	 * Gets the date of the result (from the result's content).
	 * @see XenForo_Search_DataHandler_Abstract::getResultDate()
	 */
	public function getResultDate(array $result)
	{
		return $result['post_date'];
	}

	/**
	 * Renders a result to HTML.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::renderResult()
	 */
	public function renderResult(XenForo_View $view, array $result, array $search)
	{
		return $view->createTemplateObject('xfa_blog_search_result_entry', array(
			'entry' => $result,
			'blog' => $result,
			'search' => $search
		));
	}

	/**
	 * Get the controller response for the form to search this type of content specifically.
	 *
	 * @param XenForo_ControllerPublic_Abstract $controller Invoking controller
	 * @param XenForo_Input $input Input object from controller
	 * @param array $viewParams View params prepared for general search
	 *
	 * @return XenForo_ControllerResponse_Abstract|false
	 */
	public function getSearchFormControllerResponse(XenForo_ControllerPublic_Abstract $controller, XenForo_Input $input, array $viewParams)
	{
		return $controller->responseView('XenForo_ViewPublic_Base', 'search_form_xfa_blog_entry', $viewParams);
	}	
	
	/**
	 * Gets the content types searched in a type-specific search.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getSearchContentTypes()
	 */
	public function getSearchContentTypes()
	{
		return array( 'xfa_blog_entry' );
	}

	/**
	 * Gets the content type that will be used when grouping for this type.
	 * @see XenForo_Search_DataHandler_Abstract::getGroupByType()
	 */
	public function getGroupByType()
	{
		return 'xfa_blog_entry';
	}
	
}