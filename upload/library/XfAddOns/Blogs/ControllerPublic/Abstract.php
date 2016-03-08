<?php

/**
 * An abstract class to initialize the singleton objects and provide methods for readign from the request
 */
class XfAddOns_Blogs_ControllerPublic_Abstract extends XenForo_ControllerPublic_Abstract
{
	
	/**
	 * A model for retrieving all the entries information
	 * @var XfAddOns_Blogs_Model_Entry
	 */
	protected $entryModel;
	
	/**
	 * A model for fetching basic blog information (user information + blog summary)
	 * @var XfAddOns_Blogs_Model_Blog
	 */
	protected $blogModel;
	
	/**
	 * A model for retrieving all the comments information
	 * @var XfAddOns_Blogs_Model_Comment
	 */
	protected $commentsModel;
	
	/**
	 * A model for retrieving categories information
	 * @var XfAddOns_Blogs_Model_Category
	 */
	protected $categoryModel;
	
	/**
	 * Constructor. Initializes objects on this thread
	 */
	public function __construct($request, $response, $routeMatch)
	{
		parent::__construct($request, $response, $routeMatch);
		$this->blogModel = $this->getModelFromCache('XfAddOns_Blogs_Model_Blog');
		$this->entryModel = $this->getModelFromCache('XfAddOns_Blogs_Model_Entry');
		$this->commentsModel = $this->getModelFromCache('XfAddOns_Blogs_Model_Comment');
		$this->categoryModel = $this->getModelFromCache('XfAddOns_Blogs_Model_Category');
	}	
	
	/**
	 * Blogs classes use their own style, this method will override the visitor style with the one that has been configured
	 * in the control panel
	 */
	public function _preDispatch($action)
	{
		parent::_preDispatch($action);
		$options = XenForo_Application::getOptions();
		
		if ($options->xfa_option_use_style && $options->xfa_blog_style)
		{
			$this->setViewStateChange('styleId', $options->xfa_blog_style);
		}
		if (!empty($options->xfa_blogs_googleAnalyticsWebPropertyId))
		{
			$options->googleAnalyticsWebPropertyId = $options->xfa_blogs_googleAnalyticsWebPropertyId;
		}		
	}
	
	/**
	 * Retrieve the user data from the request
	 */
	protected function getBlog()
	{
		$fetchOptions = array(
				'join' => XfAddOns_Blogs_Model_Blog::JOIN_PRIVACY + XfAddOns_Blogs_Model_Blog::JOIN_VISITOR_FOLLOW + 
				XfAddOns_Blogs_Model_Blog::JOIN_VISITOR_WATCH + XfAddOns_Blogs_Model_Blog::JOIN_READ_DATE
			);
		
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::INT);
		$blog = $this->blogModel->getBlogForUser($userId, $fetchOptions);
		if (!$blog)
		{
			$phrase = new XenForo_Phrase('xfa_blogs_user_required');
			throw new XenForo_ControllerResponse_Exception($this->responseError($phrase));
		}
		
		$this->blogModel->prepareBlog($blog);
		return $blog;
	}
	
	/**
	 * Retrieve the entry information from the database
	 */
	private function getEntryFromRequest($entryFetchOptions = null)
	{
		$fetchOptions = array(
				'join' => XfAddOns_Blogs_Model_Entry::JOIN_LIKE_INFORMATION + XfAddOns_Blogs_Model_Entry::JOIN_USER + 
						  XfAddOns_Blogs_Model_Entry::JOIN_VISITOR_WATCH + XfAddOns_Blogs_Model_Entry::JOIN_READ_DATE +
						  XfAddOns_Blogs_Model_Entry::JOIN_DELETION_LOG + XfAddOns_Blogs_Model_Entry::JOIN_VISITOR_FOLLOW,
				'prepareOptions' => 0
			);
		
		if ($entryFetchOptions)
		{
			if (isset($entryFetchOptions['join']))
			{
				$fetchOptions['join'] |= $entryFetchOptions['join'];
			}
			if (isset($entryFetchOptions['prepareOptions']))
			{
				$fetchOptions['prepareOptions'] |= $entryFetchOptions['prepareOptions'];
			}
		}
		
		$entryId = $this->_input->filterSingle('entry_id', XenForo_Input::INT);
		$entry = $this->entryModel->getEntryById($entryId, $fetchOptions);
		if (!$entry)
		{
			$phrase = new XenForo_Phrase('xfa_blogs_entry_not_found');
			throw new XenForo_ControllerResponse_Exception($this->responseError($phrase));
		}
		return $entry;
	}
	
	/**
	 * Retrieve the comment information, taking the identifier from the request
	 * @return array	An array with the comment information, or null
	 */
	private function getCommentFromRequest()
	{
		$commentId = $this->_input->filterSingle('comment_id', XenForo_Input::INT);
		$fetchOptions = array(
				'join' => XfAddOns_Blogs_Model_Comment::JOIN_USER
		);
		$comment = $this->commentsModel->getCommentById($commentId, $fetchOptions);
		if (!$comment)
		{
			$phrase = new XenForo_Phrase('xfa_blogs_comment_not_found');
			throw new XenForo_ControllerResponse_Exception($this->responseError($phrase));			
		}
		return $comment;
	}
	
	/**
	 * Looks in the request for a category_id, if found, returns the category object 
	 * 
	 * @throws XenForo_ControllerResponse_Exception		If the category does not exist
	 * @return array	Category data
	 */
	protected function getCategoryFromRequest()
	{
		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::INT);
		$category = $this->categoryModel->getCategoryById($categoryId);
		if (!$category)
		{
			$phrase = new XenForo_Phrase('xfa_blogs_category_not_found');
			throw new XenForo_ControllerResponse_Exception($this->responseError($phrase));
		}
		return $category;		
	}
	
	/**
	 * Returns an array with the blog that is currently being edited and the entry that is being modified
	 */
	protected function getEntryAndBlog($extraBlogJoin = 0, $entryFetchOptions = null)
	{
		$entry = $this->getEntryFromRequest($entryFetchOptions);

		$blog = $this->blogModel->getBlogForUser($entry['user_id'],
			array( 'join' => XfAddOns_Blogs_Model_Blog::JOIN_PRIVACY + XfAddOns_Blogs_Model_Blog::JOIN_VISITOR_FOLLOW + $extraBlogJoin ));
		
		if (!$blog)
		{
			$phrase = new XenForo_Phrase('xfa_blogs_entry_does_not_have_valid_blog');
			throw new XenForo_ControllerResponse_Exception($this->responseError($phrase));
		}
		
		$this->entryModel->prepareEntry($entry);
		$this->blogModel->prepareBlog($blog);
		return array( $entry, $blog );
	}
	

	
	/**
	 * Get a reference for the comment that is being modified and the entry
	 */
	protected function getCommentAndEntry()
	{
		$comment = $this->getCommentFromRequest();
		$entry = $this->entryModel->getEntryById($comment['entry_id']);
		if (!$entry)
		{
			$phrase = new XenForo_Phrase('xfa_blogs_entry_not_found');
			throw new XenForo_ControllerResponse_Exception($this->responseError($phrase));			
		}
		
		$this->entryModel->prepareEntry($entry);
		$this->commentsModel->prepareComment($comment, $entry);		// we need to send the entry because the entry author has escalated permissions
		return array ( $comment, $entry );
	}
	
	/**
	 * Return information about the category and the blog in a single call
	 * @throws XenForo_ControllerResponse_Exception		If either the category or blog was not found
	 * @return array
	 */
	protected function getCategoryAndBlog()
	{
		$category = $this->getCategoryFromRequest();
		
		$blog = null;
		if ($category['user_id'] > 0)
		{
			$blog = $this->blogModel->getBlogForUser($category['user_id'], array( 'join' => XfAddOns_Blogs_Model_Blog::JOIN_PRIVACY + XfAddOns_Blogs_Model_Blog::JOIN_VISITOR_FOLLOW  ));
			if (!$blog)
			{
				$phrase = new XenForo_Phrase('xfa_blogs_user_required');
				throw new XenForo_ControllerResponse_Exception($this->responseError($phrase));
			}
			$this->blogModel->prepareBlog($blog);
		}
		
		return array( $category, $blog );		
	}
	
	/**
	 * Since we are pointing multiple installations to the same XenForo for the domains functionality, there is the possibility that one
	 * would access an entry or blog with a domain that doesn't really match that blog. This method will do some canonical transformations
	 * for that
	 */
	protected function canonicalizeDomain(array $blog = null)
	{
		if (empty($blog))
		{
			return;
		}
		
		/* @var $multiModel XfAddOns_Blogs_Model_MultiBlog */
		$multiModel = XenForo_Model::create('XfAddOns_Blogs_Model_MultiBlog');
		if (!$multiModel->isMultiBlogEnabled())
		{
			return;
		}
		
		$currentDomain = $multiModel->getCurrentSubdomain();
		$expectedDomain = $multiModel->getSubdomainForBlog($blog);
		if (empty($expectedDomain))	// what the hell happened here? :)
		{
			return;
		}
		
		if ($currentDomain != $expectedDomain)
		{
			$domainHost = $multiModel->getDomainHost($blog);
			$redirectUrl = $domainHost . $_SERVER["REQUEST_URI"];
			throw $this->responseException($this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				$redirectUrl
			));
		}
	}
	
	
}