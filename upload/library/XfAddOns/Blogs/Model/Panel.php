<?php

/**
 * Model class for panel operations
 */
class XfAddOns_Blogs_Model_Panel
{

	public function getPanels()
	{
		$panels = array();
		$options = XenForo_Application::getOptions();
		if ($options->xfa_blogs_showPanels)
		{
			$statsPanel = new XfAddOns_Blogs_Panel_Stats();
			$panels[] = $statsPanel->getPanelContent();
			$blogsPanel = new XfAddOns_Blogs_Panel_Blogs();
			$panels[] = $blogsPanel->getPanelContent();
			$commentsPanel = new XfAddOns_Blogs_Panel_Comments();
			$panels[] = $commentsPanel->getPanelContent();
			$categories = new XfAddOns_Blogs_Panel_Categories();
			$panels[] = $categories->getPanelContent(array( 0 ));
		}
		return $panels;
	}
	
	
}