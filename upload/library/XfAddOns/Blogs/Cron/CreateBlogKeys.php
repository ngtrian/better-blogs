<?php

/**
 * A special "key" is assigned to each blog, that can be used to setup subdomains for the blog
 * The key is usually the username, though it can be anything valid on a domain name. Since the username is open (spaces, etc), we will do
 * some processing to make sure we don't end up with unparsable URLS
 */
class XfAddOns_Blogs_Cron_CreateBlogKeys
{
	
	/**
	 * Called from the cron controller to reprocess the keys once a day
	 */
	public static function runBlogKeys()
	{
		$options = XenForo_Application::getOptions();
		if (!$options->blogAdvancedFeatures)
		{
			return;
		}
		
		$cronJob = new XfAddOns_Blogs_Cron_CreateBlogKeys();
		$cronJob->processKeys();
	}
	
	/**
	 * Iterates over all the blogs. If the username changed we might ressign the key to the new username
	 */
	public function processKeys()
	{
		$db = XenForo_Application::getDb();
		
		$sql = "
			SELECT
				xf_user.user_id, xf_user.username, xfa_blog.blog_key,
				xfa_blog.user_id blogExists
			FROM xf_user
			LEFT JOIN xfa_blog ON xfa_blog.user_id = xf_user.user_id
			ORDER BY xf_user.user_id DESC
		"; 
		$stmt = new Zend_Db_Statement_Mysqli($db, $sql);
		$stmt->execute(); 
		
		/* @var $keyModel XfAddOns_Blogs_Model_BlogKey */
		$keyModel = XenForo_Model::create('XfAddOns_Blogs_Model_BlogKey');
		
		while ($user = $stmt->fetch())
		{
			$newKey = $keyModel->getBlogKey($user);
			if ($newKey == $user['blog_key'])
			{
				continue;
			}
			
			if (empty($user['blogExists']))
			{
				/* @var $dw XfAddOns_Blogs_DataWriter_Blog */
				$dw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Blog');
				$dw->set('user_id', $user['user_id']);
				$dw->set('blog_key', $newKey);
				$dw->save();
			}
			else
			{
				$db->update('xfa_blog', array( 'blog_key' => $newKey ), 'user_id=' . $db->quote($user['user_id']));
			}
		}
		$stmt->closeCursor();
	}
	
}
