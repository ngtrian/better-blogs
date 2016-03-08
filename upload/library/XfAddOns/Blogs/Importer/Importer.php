<?php

class XfAddOns_Blogs_Importer_Importer extends XenForo_Model
{

	
	public function importCategories()
	{
		$db = XenForo_Application::getDb();
		
		$categoryMap = $this->fetchAllKeyed("SELECT old_id, new_id FROM xfa_blog_import_log WHERE content_type='category'", 'old_id');
		$entries = $db->fetchAll("
			SELECT
				xfa_blog_entry.entry_id, cemzoo_blog.categories	
			FROM cemzoo.cemzoo_blog
			INNER JOIN fanficslandia.xfa_blog_entry ON xfa_blog_entry.old_id = cemzoo_blog.blogid
			WHERE
				categories <> ''
			");
		
		$this->_db->delete('xfa_blog_entry_category', '1=1');
		
		foreach ($entries as $entry)
		{
			$categories = explode(',', $entry['categories']);
			foreach ($categories as $oldCategoryId)
			{
				$oldCategoryId = intval($oldCategoryId);
				$newCategoryId = isset($categoryMap[$oldCategoryId]) ? $categoryMap[$oldCategoryId]['new_id'] : null;
				
				if ($newCategoryId)
				{
					$this->_db->insert('xfa_blog_entry_category', array(
							'entry_id' => $entry['entry_id'],
							'category_id' => $newCategoryId
							));					
				}
			}
		}
		
		print "Done";
	}
	
}


