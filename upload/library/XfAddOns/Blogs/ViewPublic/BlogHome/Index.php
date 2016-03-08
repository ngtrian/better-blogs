<?php

/**
 * Renderer for the view. Formats the content as necessary
 */
class XfAddOns_Blogs_ViewPublic_BlogHome_Index extends XenForo_ViewPublic_Base
{
	
	/**
	 * Adds some information to the view variables, parses, generates snippets, filters, etc
	 */
	public function renderHtml()
	{
		$this->renderFragments();
	}

	protected function renderFragments()
	{
		// fragment for entries
		$options = XenForo_Application::getOptions();
		$snippetOptions = array(
				'stripQuote' => true,
				'showSignature' => false,
				'noFollow' => true
		);
		
		$entries = &$this->_params['entries'];
		foreach ($entries as &$entry)
		{
			$entry['fragment'] = XenForo_Template_Helper_Core::helperSnippet($entry['message'], $options->xfa_blogs_trimLength, $snippetOptions);
		}		
	}
	
	/**
	 * Override the default behavior so we don't also get the sidebar
	 */
	public function renderJson()
	{
		$this->renderFragments();
		
		$template = $this->_renderer->createTemplateObject($this->getTemplateName(), $this->_params);
		$ret['templateHtml'] = $template;
		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($ret);
	}
	
	
}