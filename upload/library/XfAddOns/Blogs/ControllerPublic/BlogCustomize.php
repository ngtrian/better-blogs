<?php

class XfAddOns_Blogs_ControllerPublic_BlogCustomize extends XfAddOns_Blogs_ControllerPublic_Abstract
{
	
	/**
	 * Update the title of the blog. This action is called from the UI interface as an AJAX request
	 * This method returns an error if the field is not correct
	 */	
	public function actionUpdateTitle()
	{
		$blog = $this->getBlog();
		if (!$blog['perms']['canCustomize'])
		{
			return $this->responseNoPermission();
		}
		
		$title = $this->_input->filterSingle('title', XenForo_Input::STRING);
		if (empty($title))
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_blog_title_cannot_be_empty'));
		}
		
		/* @var $dwBlog XfAddOns_Blogs_DataWriter_Blog */
		$dwBlog = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Blog');
		$dwBlog->setExistingData($blog['user_id']);
		$dwBlog->set('blog_title', $title);
		$dwBlog->save();
		
		$extraParams = array(
			'newValue' => $title
		);		

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blogs', $blog),
			new XenForo_Phrase('xfa_blogs_blog_title_has_been_updated'),
			$extraParams
		);
	}
	
	/**
	 * This method is called for inline editing the description. It will return the editor that we need to show
	 */
	public function actionEditDescription()
	{
		$blog = $this->getBlog();
		if (!$blog['perms']['canCustomize'])
		{
			return $this->responseNoPermission();
		}		
		
		$params = array(
			'blog' => $blog
			);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Blog_EditDescription', 'xfa_blog_description_edit', $params);
	}
	
	/**
	 * Update the title of the blog. This action is called from the UI interface as an AJAX request
	 * This method returns an error if the field is not correct
	 */
	public function actionUpdateDescription()
	{
		$blog = $this->getBlog();
		if (!$blog['perms']['canCustomize'])
		{
			return $this->responseNoPermission();
		}
		
		$helper = new XenForo_ControllerHelper_Editor($this);
		$description = $helper->getMessageText('description', $this->_input);
		
		/* @var $dwBlog XfAddOns_Blogs_DataWriter_Blog */
		$dwBlog = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Blog');
		$dwBlog->setExistingData($blog['user_id']);
		$dwBlog->set('description', $description);
		$dwBlog->save();
		
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base'));
		$extraParams = array(
			'description' => new XenForo_BbCode_TextWrapper($description, $bbCodeParser)
		);		

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blogs', $blog),
			'',		// we don't want to display a message
			$extraParams
		);
	}
	
	
	/**
	 * Update the css for the blog.
	 * This action is called with all the data needed to update the blog
	 */
	public function actionUpdateCss()
	{
		$blog = $this->getBlog();
		if (!$blog['perms']['canCustomize'])
		{
			return $this->responseNoPermission();
		}
		
		$css = $this->_input->filter(array(
			'className' => XenForo_Input::STRING,
			'varname' => XenForo_Input::STRING,
			'value' => XenForo_Input::STRING
		));

		/* @var $cssModel XfAddOns_Blogs_Model_Css */
		$cssModel = XenForo_Model::create('XfAddOns_Blogs_Model_Css');
		$cssModel->insertOrReplaceCss($blog['user_id'], $css['className'], $css['varname'], $css['value']);
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blogs', $blog),
			''		// we don't want to display a message
		);		
	}
	
	/**
	 * Shows a short overlay with a warning for the user that the customization will be reset
	 */
	public function actionResetCustomizationOverlay()
	{
		$blog = $this->getBlog();
		$params = array(
			'blog' => $blog
			);
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_reset_customization_overlay', $params);
	}
	
	/**
	 * Users that request for the customization to be reset will find that all their settings are deleted. Sometimes this is
	 * something the user might want, just a "delete-all" button, when they have messed up their style beyond repair
	 */
	public function actionResetCustomization()
	{
		$blog = $this->getBlog();
		if (!$blog['perms']['canCustomize'])
		{
			return $this->responseNoPermission();
		}	

		/* @var $cssModel XfAddOns_Blogs_Model_Css */
		$cssModel = XenForo_Model::create('XfAddOns_Blogs_Model_Css');
		$cssModel->resetCustomization($blog);
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blogs', $blog),
			new XenForo_Phrase('xfa_blogs_customization_has_been_reset')
		);		
	}	
	
	/**
	 * Returns all the custom css that has been configured for a particular blog
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionCss()
	{
		header('Content-Type: text/css');
		$blog = $this->getBlog();
		
		/* @var $cssModel XfAddOns_Blogs_Model_Css */
		$cssModel = XenForo_Model::create('XfAddOns_Blogs_Model_Css');
		$css = $cssModel->getCss($blog);
		
		foreach ($css as $className => $data)
		{
			// regular classname
			print $className . "\r\n";
			print "{" . "\r\n";
			foreach ($data as $varname => $value)
			{
				if ($varname == 'background-image')
				{
					$value = "url({$value})";
				}
				print "\t" . $varname . ": " . $value . ";\r\n";
			}
			print "}" . "\r\n\r\n";
			
			// also modify the anchor
			print $className . " a\r\n";
			print "{" . "\r\n";
			foreach ($data as $varname => $value)
			{
				if ($varname == 'color')
				{
					print "\t" . $varname . ": " . $value . ";\r\n";
				}
			}
			print "}" . "\r\n\r\n";			
		}
		
		// a little too much, but breaking the flow here
		exit;
		
		return $this->responseView('XenForo_ViewPublic_Base', '');
	}
	
	/**
	 * Return all the css variables that are used for a particular action, this method is used so we can 
	 * populate the popup with data
	 */
	public function actionCssVariable()
	{
		$blog = $this->getBlog();
		$className = $this->_input->filterSingle('className', XenForo_Input::STRING);
		
		/* @var $cssModel XfAddOns_Blogs_Model_Css */
		$cssModel = XenForo_Model::create('XfAddOns_Blogs_Model_Css');
		$css = $cssModel->getCssForClassName($blog, $className);
		
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Blog_CssVariable', '', $css);
	}

	/**
	 * Download a customization in json format
	 */
	public function actionDownload()
	{
		$blog = $this->getBlog();
		if (!$blog['perms']['canCustomize'])
		{
			return $this->responseNoPermission();
		}

		// user can only download their own customizations
		$visitorUserId = XenForo_Visitor::getUserId();
		if ($visitorUserId != $blog['user_id'])
		{
			return $this->responseNoPermission();
		}
		
		/* @var $cssModel XfAddOns_Blogs_Model_Css */
		$cssModel = XenForo_Model::create('XfAddOns_Blogs_Model_Css');
		$css = $cssModel->getAllCss($blog);
		
		$ret = array();
		foreach ($css as $data)
		{
			$ret[] = array(
				'className' => $data['className'],
				'varname' => $data['varname'],
				'value' => $data['value']
			);
		}

		$viewParams = array(
			'user_id' => $blog['user_id'],
			'download_date' => date('Y-m-d H:i:s'),
			'css' => $ret
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_BlogCustomize_Download', '', $viewParams);
	}
	
	/**
	 * Show an overlay with the input box of the new customization to be uploaded
	 */
	public function actionUploadOverlay()
	{
		$blog = $this->getBlog();
		$params = array(
			'blog' => $blog
		);
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_upload_customization_overlay', $params);		
	}
	
	/**
	 * Update the customization for the blog
	 */
	public function actionUpload()
	{
		$blog = $this->getBlog();
		if (!$blog['perms']['canCustomize'])
		{
			return $this->responseNoPermission();
		}
		
		// user can only download their own customizations
		$visitorUserId = XenForo_Visitor::getUserId();
		if ($visitorUserId != $blog['user_id'])
		{
			return $this->responseNoPermission();
		}		
		
		// check that we have an upload file
		if (!isset($_FILES['customizationFile']))
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_please_select_a_file'));
		}
		
		$file = $_FILES['customizationFile'];
		if (!$file['tmp_name'] || $file['error'])
		{
			if ($file['tmp_name'])
			{
				@unlink($file['tmp_name']);
			}
			throw new XenForo_Exception(array('uploadfile' => new XenForo_Phrase('xfa_blogs_error_uploading_file')), true);
		}		
		
		$data = file_get_contents($file['tmp_name']);
		@unlink($file['tmp_name']);
		
		if (empty($data))
		{
			throw new XenForo_Exception(array('uploadfile' => new XenForo_Phrase('xfa_blogs_the_file_was_empty')), true);
		}
		
		$jsonData = @json_decode($data, true);
		if ($jsonData === false || empty($jsonData['user_id']))
		{
			throw new XenForo_Exception(array('uploadfile' => new XenForo_Phrase('xfa_blogs_the_file_was_invalid')), true);
		}
		
		/* @var $cssModel XfAddOns_Blogs_Model_Css */
		$cssModel = XenForo_Model::create('XfAddOns_Blogs_Model_Css');
		$cssModel->resetCustomization($blog);
		foreach ($jsonData['css'] as $row)
		{
			$cssModel->insertOrReplaceCss($blog['user_id'], $row['className'], $row['varname'], $row['value']);
		}
		
		return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xfa-blogs', $blog)
		);		
	}

	/**
	 * The list of the allowed clases to be substituted when uploading a customization
	 * @return array
	 */
	protected function getAllowedClasses()
	{
		$classes = array();
		$classes[] = '#content .pageContent .customizeTitle';
		$classes[] = '#content .pageContent .customizeEntry';
		$classes[] = '.customizeBody';
		$classes[] = '#content .customizePageContent';
		$classes[] = '.customizeSecondaryContent';
		$classes[] = '.sidebar .section .secondaryContent h3.customizeSecondaryH3';
		return $classes;		
	}
	
	/**
	 * This controller does not track user activity
	 * @see XenForo_Controller::canUpdateSessionActivity()
	 */
	public function canUpdateSessionActivity($controllerName, $action, &$newState)
	{
		return false;
	}
	
	
	
}