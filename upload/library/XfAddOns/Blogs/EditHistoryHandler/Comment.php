<?php

class XfAddOns_Blogs_EditHistoryHandler_Comment extends XenForo_EditHistoryHandler_Abstract
{
	
	protected function _getContent($contentId, array $viewingUser)
	{
		/* @var $commentModel XfAddOns_Blogs_Model_Comment */
		$commentModel = XenForo_Model::create('XfAddOns_Blogs_Model_Comment');
		
		$comment = $commentModel->getCommentById($contentId, array());
		if (empty($comment))
		{
			return null;
		}
		$commentModel->prepareComment($comment);
		
		// get the entries and blog information (will be needed for privacy)
		$comments = array( $comment );
		$commentModel->wireEntriesAndBlogs($comments);
		return $comments[0];
	}
	
	protected function _canViewHistoryAndContent(array $content, array $viewingUser)
	{
		$entry = $content['entry'];
		$blog = $entry['blog'];
		if (!$entry['perms']['canView'] || !$blog['perms']['canView'])
		{
			return false;
		}
		return $content['perms']['canViewHistory'];		
	}
	
	protected function _canRevertContent(array $content, array $viewingUser)
	{
		$entry = $content['entry'];
		$blog = $entry['blog'];
		if (!$entry['perms']['canView'] || !$blog['perms']['canView'])
		{
			return false;
		}
		return $content['perms']['canRevert'];
	}
	
	public function getTitle(array $content)
	{
		return new XenForo_Phrase('comment_in_entry_x', array('entry' => $content['entry']['title']));
	}
	
	public function getText(array $content)
	{
		return $content['message'];
	}
	
	public function getBreadcrumbs(array $content)
	{
		$ret = array();
		$ret[] = array(
			'href' =>XenForo_Link::buildPublicLink('xfa-blogs', $content['entry']['blog']),
			'value' => $content['entry']['blog']['blog_title']
		);
		$ret[] = array(
			'href' =>XenForo_Link::buildPublicLink('xfa-blog-entry', $content['entry']),
			'value' => $content['entry']['title']
		);
		return $ret;		
	}
	
	public function getNavigationTab()
	{
		return 'xfa-blogs';
	}
	
	public function buildContentLink(array $content)
	{
		return XenForo_Link::buildPublicLink('xfa-blog-entry', $content['entry']);
	}	
	
	public function formatHistory($string, XenForo_View $view)
	{
		$parser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $view)));
		return new XenForo_BbCode_TextWrapper($string, $parser);
	}
	
	public function revertToVersion(array $content, $revertCount, array $history, array $previous = null)
	{
		$dw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Comment', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($content['comment_id']);
		$dw->set('message', $history['old_text']);
		return $dw->save();
	}

	/**
	 * Overloading this, until it is fixed in XenForo itself
	 */
	public function canRevertContent(array $content, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return $this->_canRevertContent($content, $viewingUser);
	}	
	
	
}