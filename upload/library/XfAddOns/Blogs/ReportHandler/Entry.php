<?php

/**
 * Handler for reported posts.
 *
 * @package XenForo_Report
 */
class XfAddOns_Blogs_ReportHandler_Entry extends XenForo_ReportHandler_Abstract
{
	/**
	 * Gets report details from raw array of content (a blog entry).
	 * @see XenForo_ReportHandler_Abstract::getReportDetailsFromContent()
	 */
	public function getReportDetailsFromContent(array $content)
	{
		/* @var $entryModel XfAddOns_Blogs_Model_Entry */
		$entryModel = XenForo_Model::create('XfAddOns_Blogs_Model_Entry');
		
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Entry::JOIN_USER;
		$entry = $entryModel->getEntryById($content['entry_id'], $fetchOptions);
		
		if (!$entry)
		{
			return array(false, false, false);
		}

		return array(
			$content['entry_id'],
			$content['user_id'],
			array(
				'username' => $entry['username'],
				'message' => $entry['message'],
				'title' => $entry['title']
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
		return new XenForo_Phrase('xfa_blogs_entry_x', array('title' => $contentInfo['title']));
	}

	/**
	 * Gets the link to the specified content.
	 * @see XenForo_ReportHandler_Abstract::getContentLink()
	 */
	public function getContentLink(array $report, array $contentInfo)
	{
		return XenForo_Link::buildPublicLink('xfa-blog-entry', array('entry_id' => $report['content_id']));
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
		return $view->createTemplateObject('xfa_blogs_report_entry_content', array(
			'report' => $report,
			'content' => $contentInfo,
			'bbCodeParser' => $parser
		));
	}

}