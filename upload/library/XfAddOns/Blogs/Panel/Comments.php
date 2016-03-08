<?php

/**
 * Recent Comments
 */
class XfAddOns_Blogs_Panel_Comments extends XfAddOns_Blogs_Panel_Abstract
{
	
	/**
	 * Fetches and return the panel content. This panel shows the most recent comments
	 */
	public function getPanelContent()
	{
		$cacheKey = 'xfab_wf_local_comments';
		$comments = $this->getCommentsFiltered($cacheKey, 'post_date', 'DESC', 10);
		
		$template = new XenForo_Template_Public('xfa_blog_wf_comments', array(
			'comments' => $comments,
			'visitor' => XenForo_Visitor::getInstance(),
			'title' => new XenForo_Phrase('xfa_blogs_recent_comments'),
			'includeSnippet' => true
			));		
		return $template;		
	}
	
	/**
	 * This will get the comments from the cache, and on top of that apply the filters for privacy
	 * We need to filter this each time since the privacy permissions are user-specific
	 */
	public function getCommentsFiltered($cacheKey, $orderBy, $direction, $limit)
	{
		// retrieve the comments from the cache
		$comments = $this->getFromCache('Comments', $cacheKey, $orderBy, $direction, $limit);
		
		// reprepare the comments and entry, since the active visitor is a different one
		$this->commentsModel->prepareComments($comments);
		foreach ($comments as &$comment)
		{
			$this->entryModel->prepareEntry($comment['entry']);
			$this->blogModel->prepareBlog($comment['entry']['blog']);
		}
		$this->commentsModel->removePrivateCommentsForVisitor($comments);
		
		// return the comments after they were filtered
		if (count($comments) > $limit)
		{
			return array_slice($comments, 0, $limit);	
		}
		return $comments;
	}
	
	/**
	 * We will get the latest comments, and store in a cache if needed
	 * @return array
	 */
	public function getComments($orderBy, $direction, $limit, $whereHint = '')
	{
		// fetch options
		$fetchOptions = array(
			'join' => XfAddOns_Blogs_Model_Comment::JOIN_USER + XfAddOns_Blogs_Model_Comment::JOIN_ENTRY,
			'limit' => $limit,
			'where' => $whereHint,
			'orderBy' => $orderBy . ' ' . $direction 
			);
		$comments = $this->commentsModel->getComments($fetchOptions);
		$this->commentsModel->prepareComments($comments);
		$this->commentsModel->wireEntriesAndBlogs($comments);
		$this->prepareFragments($comments);
		return $comments;
	}
	
	/**
	 * Prepare the fragments for the comments, trimming the content
	 * @param array $comments
	 */
	private function prepareFragments(&$comments)
	{
		$options = XenForo_Application::getOptions();
		$snippetOptions = array(
			'stripQuote' => true
		);
		foreach ($comments as &$comment)
		{
			$comment['fragment'] = XenForo_Template_Helper_Core::helperSnippet($comment['message'], $options->xfa_blogs_commentTrimLength, $snippetOptions);
		}
	}
	
}
