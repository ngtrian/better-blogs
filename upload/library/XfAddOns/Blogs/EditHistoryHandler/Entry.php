<?php

class XfAddOns_Blogs_EditHistoryHandler_Entry extends XenForo_EditHistoryHandler_Abstract
{
		
	protected function _getContent($contentId, array $viewingUser)
	{
		/* @var $blogModel XfAddOns_Blogs_Model_Blog */
		$blogModel = XenForo_Model::create('XfAddOns_Blogs_Model_Blog');
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		
		$entry = $entryModel->getEntryById($contentId, array(
				'join' => XfAddOns_Blogs_Model_Entry::JOIN_USER + XfAddOns_Blogs_Model_Entry::JOIN_BLOG +
				XfAddOns_Blogs_Model_Entry::JOIN_BLOG_PRIVACY
		));
		
		// we actually have the blog data in the same array
		$blog = $entry;
		$blogModel->prepareBlog($blog);
		
		$entry['blog'] = $blog;
		$entryModel->prepareEntry($entry);
		return $entry;
	}
	
	protected function _canViewHistoryAndContent(array $content, array $viewingUser)
	{
		if (!$content['blog']['perms']['canView'] || !$content['perms']['canView'])
		{
			return false;
		}
		return $content['perms']['canViewHistory'];
	}
	
	protected function _canRevertContent(array $content, array $viewingUser)
	{
		if (!$content['blog']['perms']['canView'] || !$content['perms']['canView'])
		{
			return false;
		}		
		return $content['perms']['canRevert'];
	}	
	
	public function getTitle(array $content)
	{
		return new XenForo_Phrase('entry_in_blog_x', array('title' => $content['blog']['blog_title']));
	}
	
	public function getText(array $content)
	{
		return $content['message'];
	}
	
	public function getBreadcrumbs(array $content)
	{
		$ret = array();
		$ret[] = array(
			'href' =>XenForo_Link::buildPublicLink('xfa-blogs', $content['blog']),
			'value' => $content['blog']['blog_title']
		);
		$ret[] = array(
			'href' => XenForo_Link::buildPublicLink('xfa-blog-entry', $content),
			'value' => $content['title']
		);
		return $ret;		
	}
	
	public function getNavigationTab()
	{
		return 'xfa-blogs';
	}
	
	public function buildContentLink(array $content)
	{
		return XenForo_Link::buildPublicLink('xfa-blog-entry', $content);
	}
	
	public function formatHistory($string, XenForo_View $view)
	{
		$parser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $view)));
		return new XenForo_BbCode_TextWrapper($string, $parser);
	}
	
	public function revertToVersion(array $content, $revertCount, array $history, array $previous = null)
	{
		$dw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Entry', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($content['entry_id']);
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