<?php

/**
 * Handler for reported posts.
 *
 * @package XenForo_Report
 */
class XfAddOns_Blogs_ReportHandler_Comment extends XenForo_ReportHandler_Abstract
{
	/**
	 * Gets report details from raw array of content (a blog entry).
	 * @see XenForo_ReportHandler_Abstract::getReportDetailsFromContent()
	 */
	public function getReportDetailsFromContent(array $content)
	{
		/* @var $commentModel XfAddOns_Blogs_Model_Comment */
		$commentModel = XenForo_Model::create('XfAddOns_Blogs_Model_Comment');
		
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Comment::JOIN_USER + XfAddOns_Blogs_Model_Comment::JOIN_ENTRY;
		$comment = $commentModel->getCommentById($content['comment_id'], $fetchOptions);
		$commentModel->prepareComment($comment);
		
		if (!$comment)
		{
			return array(false, false, false);
		}

		return array(
			$content['comment_id'],
			$content['user_id'],
			array(
				'username' => $comment['username'],
				'message' => $comment['message'],
				'entry_id' => $comment['entry_id'],
				'entry_title' => $comment['entry']['title']
			)
		);
	}

	/**
	 * Gets the visible reports of this content type for the viewing user.
	 *
	 * @see XenForo_ReportHandler_Abstract:getVisibleReportsForUser()
	 */
	public function getVisibleReportsForUser(array $reports, array $viewingUser)
	{
		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'xfa_blogs', 'xfa_blog_report_manage'))
		{
			return array();
		}
		return $reports;
	}

	/**
	 * Gets the title of the specified content.
	 * @see XenForo_ReportHandler_Abstract:getContentTitle()
	 */
	public function getContentTitle(array $report, array $contentInfo)
	{
		return new XenForo_Phrase('xfa_blogs_comment_in_entry_x', array('title' => $contentInfo['entry_title']));
	}

	/**
	 * Gets the link to the specified content.
	 * @see XenForo_ReportHandler_Abstract::getContentLink()
	 */
	public function getContentLink(array $report, array $contentInfo)
	{
		return XenForo_Link::buildPublicLink('xfa-blog-entry', array('entry_id' => $contentInfo['entry_id'])) . '#comment-' . $report['content_id'];
	}

	/**
	 * A callback that is called when viewing the full report.
	 *
	 * @see XenForo_ReportHandler_Abstract::viewCallback()
	 */
	public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
	{
		$parser = new XenForo_BbCode_Parser(
			XenForo_BbCode_Formatter_Base::create('Base', array('view' => $view))
		);
		return $view->createTemplateObject('xfa_blogs_report_comment_content', array(
			'comment' => array(
				'comment_id' => $report['content_id'],
				'message' => $contentInfo['message'],
				'username' => $contentInfo['username']
				),
			'entry' => array(
				'entry_id' => $contentInfo['entry_id'],
				'title' => $contentInfo['entry_title'],
				),
			'bbCodeParser' => $parser
		));
	}

}