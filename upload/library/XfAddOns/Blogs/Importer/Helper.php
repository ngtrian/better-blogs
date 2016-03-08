<?php

class XfAddOns_Blogs_Importer_Helper
{
	
	
	/**
	 * Delegate for recalculating totals. Used by both importers
	 */
	public static function stepTotals(&$start, array &$options, $importerRef)
	{
		$start = intval($start);
		$options = array_merge(array(
			'limit' => 500,
			'max' => 7	// total steps
		), $options);

		if ($start >= $options['max'])
		{
			return true;
		}
		
		// counters and action
		/* @var $rebuildModel XfAddOns_Blogs_Model_Rebuild */
		$rebuildModel = XenForo_Model::create('XfAddOns_Blogs_Model_Rebuild');
		if ($start == 0)
		{
			$next = 1;		// only done so the UI does not hang
		}
		else if ($start == 1)
		{
			$next = 2;
			$rebuildModel->recountBlogTotals();
		}		
		else if ($start == 2)
		{
			$next = 3;
			$rebuildModel->rebuildLastEntry();
		}
		else if ($start == 3)
		{
			$next = 4;
			$rebuildModel->recountEntriesTotals();
		}
		else if ($start == 4)
		{
			$next = 5;
			$rebuildModel->recountCategoryTotals();
		}
		else if ($start == 5)
		{
			$next = 6;
			$rebuildModel->rebuildFirstEntry();
		}
		else if ($start == 6)
		{
			$next = 7;
			$rebuildModel->rebuildLastEntryId();
		}		
		
		$importerRef->getSession()->incrementStepImportTotal(1);
		return array($next, $options, $importerRef->_getProgressOutput($next, $options['max']));
	}
	
	/**
	 * Rebuild the position index for all the entries
	 */
	public static function stepPositionEntries(&$start, array &$options, $importerRef)
	{
		$start = intval($start);
		$db = $importerRef->getMainDb();
		
		$options = array_merge(array(
			'limit' => 100,
			'max' => false	// total steps
		), $options);
		
		if ($options['max'] === false)
		{
			$options['max'] = $db->fetchOne('SELECT MAX(user_id) FROM xfa_blog');
		}
		
		// pull entries
		$rows = $db->fetchAll($db->limit("
			SELECT xfa_blog.user_id FROM xfa_blog				
			WHERE
				xfa_blog.user_id > " . $db->quote($start) . "
			ORDER BY
				xfa_blog.user_id 			
			", $options['limit']
		));
		
		/* @var $model XfAddOns_Blogs_Model_Rebuild */
		$model = XenForo_Model::create('XfAddOns_Blogs_Model_Rebuild');
		
		if (!$rows)
		{
			$model->rebuildPositionForDeletedEntries();		// at the end rebuild the deleted entries
			return true;
		}
		
		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;
		
		foreach ($rows AS $blog)
		{
			$next = $blog['user_id'];
			$model->rebuildEntryPositionIndex($blog['user_id']);
			$total++;
		}

		XenForo_Db::commit();

		$importerRef->getSession()->incrementStepImportTotal($total);
		return array($next, $options, $importerRef->_getProgressOutput($next, $options['max']));		
	}
	
	/**
	 * Rebuild the position index for all the comments
	 */
	public static function stepPositionComments(&$start, array &$options, $importerRef)
	{
		$start = intval($start);
		$db = $importerRef->getMainDb();
		
		$options = array_merge(array(
			'limit' => 250,
			'max' => false	// total steps
		), $options);
		
		if ($options['max'] === false)
		{
			$options['max'] = $db->fetchOne('SELECT MAX(entry_id) FROM xfa_blog_entry');
		}
		
		// pull entries
		$rows = $db->fetchAll($db->limit("
			SELECT entry_id FROM xfa_blog_entry				
			WHERE
				entry_id > " . $db->quote($start) . "
			ORDER BY
				entry_id 			
			", $options['limit']
		));
		
		/* @var $rebuildModel XfAddOns_Blogs_Model_Rebuild */
		$rebuildModel = XenForo_Model::create('XfAddOns_Blogs_Model_Rebuild');
		
		if (!$rows)
		{
			$rebuildModel->rebuildPositionForDeletedComments();
			return true;
		}

		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;
		
		foreach ($rows AS $entry)
		{
			$next = $entry['entry_id'];
			$rebuildModel->rebuildCommentPositionIndex($entry['entry_id']);
			$total++;
		}

		XenForo_Db::commit();

		$importerRef->getSession()->incrementStepImportTotal($total);
		return array($next, $options, $importerRef->_getProgressOutput($next, $options['max']));		
	}
	
	
	
}