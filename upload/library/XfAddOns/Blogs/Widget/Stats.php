<?php

/**
 * Widget panel for Categories
 */
class XfAddOns_Blogs_Widget_Stats extends WidgetFramework_WidgetRenderer
{

	protected function _getConfiguration() {
		return array(
			'name' => '[Better Blogs] Stats',
			'useCache' => false,
			'cacheSeconds' => XenForo_Application::getOptions()->xfa_blogs_cacheTime * 60 // cache for an hour
		);
	}
	
	protected function _getOptionsTemplate() {
		return '';
	}
	
	protected function _getRenderTemplate(array $widget, $positionCode, array $params) {
		return 'xfa_blog_wf_stats';
	}
	
	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject) {
		$panel = new XfAddOns_Blogs_Panel_Stats();
		$renderTemplateObject->setParam('blogTotals', $panel->getStats());
		$renderTemplateObject->setParam('visitor', XenForo_Visitor::getInstance());
		return $renderTemplateObject->render();
	}	
	
}