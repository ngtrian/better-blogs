<?php

/**
 * Controller that manages any actions on the scheduled page 
 */
class XfAddOns_Blogs_ControllerPublic_Scheduled extends XfAddOns_Blogs_ControllerPublic_Abstract
{
	
	/**
	 * @var XfAddOns_Blogs_Model_EntryScheduled
	 */
	protected $scheduledModel;
	
	/**
	 * Constructor. Initializes objects on this thread
	 */
	public function __construct($request, $response, $routeMatch)
	{
		parent::__construct($request, $response, $routeMatch);
		$this->scheduledModel = $this->getModelFromCache('XfAddOns_Blogs_Model_EntryScheduled');
	}			
	
	/**
	 * Action called to edit a scheduled entry. This will bring up the edit panel to change
	 * the message of the scheduled entry
	 */
	public function actionEdit()
	{
		$options = XenForo_Application::getOptions();
		$fetchOptions['prepareOptions'] = XfAddOns_Blogs_Model_Entry::PREPARE_ALLOW_MEMBERS;
		list($scheduledEntry, $blog) = $this->getScheduledEntryAndBlog(0, $fetchOptions);
		if (!$scheduledEntry['perms']['canEdit'])
		{
			return $this->responseNoPermission();
		}
	
		// parameters for attachments
		/* @var $attachmentModel XenForo_Model_Attachment */
		$attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment'); 
		$attachments = $attachmentModel->getAttachmentsByContentId('xfa_blog_entry_scheduled', $scheduledEntry['scheduled_entry_id']);
		$attachmentParams = array(
			'hash' => md5(uniqid('', true)),
			'content_type' => 'xfa_blog_entry_scheduled',
			'content_data' => array( 'entry_id' => $scheduledEntry['scheduled_entry_id'] )
		);
		$attachmentConstraints = $attachmentModel->getAttachmentConstraints();

		// iterate over the categories and mark them as selected
		$categories = $this->categoryModel->getCategoriesForBlog($blog['user_id']);
		$selectedIds = unserialize($scheduledEntry['categories']);
		$selectedIds = is_array($selectedIds) ? $selectedIds : array();
		foreach ($categories as &$cat)
		{
			$cat['checked'] = in_array($cat['category_id'], $selectedIds) ? true : false;
		}
		
		// return the response
		$containerParams = array(
			'isBlogContainer' => true,
			'containerTemplate' => ($options->xfa_blogs_blogMode != 'classic') ? 'xfa_blog_PAGE_CONTAINER_full_screen' : 'PAGE_CONTAINER',
			'noVisitorPanel' => true,
			'blog' => $blog
		);			
		$params = array(
			'blog' => $blog,
			'entry' => $scheduledEntry,
			'message' => $scheduledEntry['message'],
			'categories' => $categories,
			'attachmentParams' => $attachmentParams,
			'attachmentConstraints' => $attachmentConstraints,				
			'attachments' => $attachmentModel->prepareAttachments($attachments) 
		);
		XfAddOns_Blogs_Helper_TimeParams::addTimeParams($params, $scheduledEntry['post_date']);
		
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Entry_Edit', 'xfa_blog_scheduled_entry_edit', $params, $containerParams);
	}
	
	/**
	 * Called when we update the information on the entry. This is done through the left navigation
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();
		
		// fetch the information for the blog
		list($scheduledEntry, $blog) = $this->getScheduledEntryAndBlog();
		if (!$scheduledEntry['perms']['canEdit'])
		{
			return $this->responseNoPermission();
		}
		
		// get the input data
		$dwInput = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'allow_comments' => XenForo_Input::INT,
			'allow_view_entry' => XenForo_Input::STRING
		));
		if (empty($dwInput['allow_view_entry']))
		{
			$dwInput['allow_view_entry'] = 'none';
		}
		$allowMembers = $this->_input->filterSingle('allow_members_names', XenForo_Input::STRING);		
		
		$attachmentHash = $this->_input->filterSingle('attachment_hash', XenForo_Input::STRING);
		
		$helper = new XenForo_ControllerHelper_Editor($this);
		$dwInput['message'] = $helper->getMessageText('message', $this->_input);
		$dwInput['user_id'] = $blog['user_id'];
		$dwInput['categories'] = $this->_input->filterSingle('category', array(XenForo_Input::UINT, 'array' => true)); 
		$dwInput['post_date'] = XfAddOns_Blogs_Helper_TimeParams::getPostDateFromRequest($this->_input);
		
		// visitor reference
		$visitor = XenForo_Visitor::getInstance();
		
		/* @var $dwEntry XfAddOns_Blogs_DataWriter_Entry */
		$dwScheduledEntry = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_EntryScheduled');
		$dwScheduledEntry->setExistingData($scheduledEntry['scheduled_entry_id']);
		$dwScheduledEntry->bulkSet($dwInput);
		$dwScheduledEntry->setExtraData(XfAddOns_Blogs_DataWriter_Entry::DATA_ATTACHMENT_HASH, $attachmentHash);
		$dwScheduledEntry->setAllowMembers($allowMembers);
		$dwScheduledEntry->save();
		
		return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xfa-blogs', $blog)
		);
	}	
	
	/**
	 * Action called whenever we want to discard an entry, this will show an overlay which will double
	 * as a confirmation message of wheter we really want to discard the entry
	 */
	public function actionDiscardOverlay()
	{
		// fetch the information for the blog
		list($scheduledEntry, $blog) = $this->getScheduledEntryAndBlog();
		if (!$scheduledEntry['perms']['canEdit'])
		{
			return $this->responseNoPermission();
		}

		$params = array(
			'entry' => $scheduledEntry
		);
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_entry_discarded_overlay', $params); 
	}
	
	/**
	 * This action is called to discard the entry, the scheduled entry will be deleted
	 * and the user will be redirected to the main blog page
	 */
	public function actionDiscard()
	{
		// fetch the information for the blog
		list($scheduledEntry, $blog) = $this->getScheduledEntryAndBlog();
		if (!$scheduledEntry['perms']['canEdit'])
		{
			return $this->responseNoPermission();
		}

		/* @var $dwEntry XfAddOns_Blogs_DataWriter_Entry */
		$dwScheduledEntry = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_EntryScheduled');
		$dwScheduledEntry->setExistingData($scheduledEntry['scheduled_entry_id']);
		$dwScheduledEntry->delete();
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blogs', $blog),
			new XenForo_Phrase('xfa_blogs_entry_has_been_discarded')
		);
	}
	
	/**
	 * Retrieve the scheduled entry information from the database
	 */
	protected function getScheduledEntryFromRequest($entryFetchOptions = null)
	{
		$entryId = $this->_input->filterSingle('scheduled_entry_id', XenForo_Input::INT);
		$entry = $this->scheduledModel->getScheduledEntryById($entryId, $entryFetchOptions);
		if (!$entry)
		{
			$phrase = new XenForo_Phrase('xfa_blogs_schduled_entry_not_found');
			throw new XenForo_ControllerResponse_Exception($this->responseError($phrase));
		}
		return $entry;
	}	
	
	/**
	 * Returns an array with the scheduled entry, and the blog that it belongs to
	 */
	protected function getScheduledEntryAndBlog($extraBlogJoin = 0, $entryFetchOptions = array())
	{
		$entry = $this->getScheduledEntryFromRequest($entryFetchOptions);
		$blog = $this->blogModel->getBlogForUser($entry['user_id'],
			array( 'join' => XfAddOns_Blogs_Model_Blog::JOIN_PRIVACY + XfAddOns_Blogs_Model_Blog::JOIN_VISITOR_FOLLOW + $extraBlogJoin ));
		
		if (!$blog)
		{
			$phrase = new XenForo_Phrase('xfa_blogs_entry_does_not_have_valid_blog');
			throw new XenForo_ControllerResponse_Exception($this->responseError($phrase));
		}
		
		$this->scheduledModel->prepareScheduledEntry($entry);
		$this->blogModel->prepareBlog($blog);
		return array( $entry, $blog );		
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