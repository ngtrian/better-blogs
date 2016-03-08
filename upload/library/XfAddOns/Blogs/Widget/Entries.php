<?php

/**
 * Widget panel for Entries
 */
class XfAddOns_Blogs_Widget_Entries extends WidgetFramework_WidgetRenderer
{

	protected function _getConfiguration() {
		return array(
			'name' => '[Better Blogs] Entries',
			'options' => array(
				'limit' => XenForo_Input::UINT,
				'order' => XenForo_Input::STRING,
				'direction' => XenForo_Input::STRING,
				'includeSnippet' => XenForo_Input::UINT,
			),
			'useCache' => false,
			'cacheSeconds' => XenForo_Application::getOptions()->xfa_blogs_cacheTime * 60 // cache for an hour
		);
	}
	
	protected function _getOptionsTemplate() {
		return 'xfa_blogs_wf_widget_options_entries';
	}
	
	protected function _getRenderTemplate(array $widget, $positionCode, array $params) {
		return 'xfa_blog_wf_entries';
	}
	
	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject) {
		$cacheKey = 'xfab_wf_' . $widget['widget_id'];
		$options = $widget['options'];
		$options['limit'] = $options['limit'] > 0 ? $options['limit'] : 10; 
		
		$panel = new XfAddOns_Blogs_Panel_Entries();
		$renderTemplateObject->setParam('entries', $panel->getEntriesFiltered($cacheKey, $options['order'], $options['direction'], $options['limit']));
		$renderTemplateObject->setParam('visitor', XenForo_Visitor::getInstance());
		$renderTemplateObject->setParam('includeSnippet', $options['includeSnippet']);
		$renderTemplateObject->setParam('order', $options['order']);
		return $renderTemplateObject->render();
	}
	
}