<?php

/**
 * Recent Blogs
 */
class XfAddOns_Blogs_Panel_Blogs extends XfAddOns_Blogs_Panel_Abstract
{
	
	/**
	 * Fetches and return the panel content. This panel shows the most recent blogs
	 */
	public function getPanelContent($orderBy = 'create_date')
	{
		$cacheKey = 'xfab_wf_local_blogs';
		$blogs = $this->getBlogsFiltered($cacheKey, $orderBy, 'DESC', 10);
		
		$template = new XenForo_Template_Public('xfa_blog_wf_blogs', array(
			'blogs' => $blogs,
			'visitor' => XenForo_Visitor::getInstance(),
			'title' => new XenForo_Phrase('xfa_blogs_new_blogs')
			));
		return $template;
	}
	
	/**
	 * This will get the blogs from the cache, and on top of that apply the filters for privacy
	 * We need to filter this each time since the privacy permissions are user-specific
	 */
	public function getBlogsFiltered($cacheKey, $orderBy, $direction, $limit)
	{
		// retrieve the blogs from the cache
		$blogs = $this->getFromCache('Blogs', $cacheKey, $orderBy, $direction, $limit);
		$this->blogModel->prepareBlogs($blogs);
		$this->blogModel->removePrivateBlogsForVisitor($blogs);
		
		// return the blogs after they were filtered
		if (count($blogs) > $limit)
		{
			return array_slice($blogs, 0, $limit);	
		}
		return $blogs;
	}
	
	/**
	 * We will get the latest blogs, and store in a cache if needed
	 * @return array
	 */
	public function getBlogs($orderBy, $direction, $limit, $whereHint = '')
	{
		// fetch options
		$fetchOptions = array(
			'join' => XfAddOns_Blogs_Model_Blog::JOIN_PRIVACY,
			'limit' => $limit,
			'where' => (!empty($whereHint) ? ($whereHint . ' AND ') : '') . " entry_count > 0 AND is_banned = 0 AND user_state = 'valid'",
			'orderBy' => $orderBy . ' ' . $direction 
			);
		$blogs = $this->blogModel->getBlogList($fetchOptions);
		$this->blogModel->prepareBlogs($blogs);
		return $blogs;
	}
	
}
