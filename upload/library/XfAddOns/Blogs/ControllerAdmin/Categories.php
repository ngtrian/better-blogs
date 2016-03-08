<?php

class XfAddOns_Blogs_ControllerAdmin_Categories extends XenForo_ControllerAdmin_Abstract
{

	/**
	 * Main page that list all the available categories
	 * @return XenForo_ViewAdmin_Base
	 */
	public function actionIndex()
	{
		$options = XenForo_Application::getOptions();
		if (!$options->blogAdvancedFeatures)
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_sorry_global_categories_available'));
		}		
		
		/* @var $model XfAddOns_Blogs_Model_Category */
		$model = $this->getModelFromCache('XfAddOns_Blogs_Model_Category');
		
		// fetch options
		$fetchOptions['orderBy'] = 'display_order';
		$allCategories = $model->getGlobalCategoriesForList($fetchOptions);
		
		$viewParams = array(
			'categories' => $allCategories
		);
		return $this->responseView('XenForo_ViewAdmin_Base', 'xfa_blog_category_list', $viewParams);
	}

	/**
	 * Called to add a new category
	 * @return XenForo_ViewAdmin_Base
	 */
	public function actionAdd()
	{
		$options = XenForo_Application::getOptions();
		if (!$options->blogAdvancedFeatures)
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_sorry_global_categories_available'));
		}
		
		/* @var $model XfAddOns_Blogs_Model_Category */
		$model = $this->getModelFromCache('XfAddOns_Blogs_Model_Category');
		$allCategories = $model->getGlobalCategoriesForSelectBox();
		$viewParams = array(
			'allCategories' => $allCategories
		);
		
		return $this->responseView('XenForo_ViewAdmin_Base', 'xfa_blog_category_edit', $viewParams);
	}

	public function actionEdit()
	{
		$options = XenForo_Application::getOptions();
		if (!$options->blogAdvancedFeatures)
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_sorry_global_categories_available'));
		}
		
		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);

		/* @var $model XfAddOns_Blogs_Model_Category */
		$model = $this->getModelFromCache('XfAddOns_Blogs_Model_Category');
		
		$allCategories = $model->getGlobalCategoriesForSelectBox();
		unset($allCategories[$categoryId]);
		
		$viewParams = array(
			'allCategories' => $allCategories,
			'category' => $model->getCategoryById($categoryId)
		);		
		return $this->responseView('XenForo_ViewAdmin_Base', 'xfa_blog_category_edit', $viewParams);
	}

	/**
	 * This is called whenever we want to insert or update a smilie
	 * This will take the existing data, and invoke the data writer for updating the database with
	 * the new information
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		$dwInput = $this->_input->filter(array(
			'category_name' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'parent_id' => XenForo_Input::UINT,
			'is_active' => XenForo_Input::UINT,
		));

		$dw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Category');
		if ($categoryId)
		{
			$dw->setExistingData($categoryId);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('xfa-blog-categories') . $this->getLastHash($dw->get('category_id'))
		);
	}

	/**
	 * Delete a category from the system.
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionDelete()
	{
		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);

		/* @var $dw XfAddOns_Blogs_DataWriter_Category */		
		$dw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Category');
		$dw->setExistingData($categoryId);
		$dw->delete();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('xfa-blog-categories')
		);
	}
	
	/**
	 * Update the order of the categories.
	 * This method is called from the main page whenever we want to re-arrange the categories 
	 */
	public function actionOrder()
	{
		$this->_assertPostOnly();
		
		$order = $this->_input->filterSingle('display_order', XenForo_Input::ARRAY_SIMPLE);
		foreach ($order as $categoryId => $order)
		{
			/* @var $dw XfAddOns_Blogs_DataWriter_Category */
			$dw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Category');
			$dw->setExistingData($categoryId);
			$dw->set('display_order', $order);
			$dw->save();
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('xfa-blog-categories')
		);
	}

}