<?php

class XfAddOns_Blogs_ControllerPublic_Blog extends XfAddOns_Blogs_ControllerPublic_Abstract
{
	
	/**
	 * Index page. Displays all the blog entries for a particular user 
	 */
	public function actionIndex()
	{
		// get the blog information
		$blog = $this->getBlog();
		$this->validateNewBlog($blog);
		
		// permissions
		if (!$blog['perms']['canView'])
		{
			$visitorUserId = XenForo_Visitor::getUserId();
			if ($visitorUserId)
			{
				// If there is a specific error with the detail we return that, else we will return a generic message
				if (isset($blog['perms']['canViewPermissionDetail']))
				{
					return $this->responseError($blog['perms']['canViewPermissionDetail']);
				}
				$params = array( 'username' => $blog['username'] );
				return $this->responseError(new XenForo_Phrase('xfa_blog_you_dont_have_permissions_user_blog', $params));
			}
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xfa-blog-home/checkForSession', null, array('b' => $blog['user_id']))
			);
		}
		
		// support for rss
		if ($this->_routeMatch->getResponseType() == 'rss')
		{
			return $this->actionRss();
		}		
		
		// entries per page
		$options = XenForo_Application::getOptions();
		
		// page parameters
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		
		// do canonicalization
		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('xfa-blogs', $blog, array('page' => $page))
		);
		$this->canonicalizePageNumber($page, $options->xfa_blogs_entriesPerPage, $blog['entry_count'] + 1, 'xfa-blogs', $blog);
		$this->canonicalizeDomain($blog);
		
		// fetch options for the entries
		$fetchOptions = array();
		$fetchOptions['page'] = $page;
		$fetchOptions['reverse'] = $blog['entry_count'];		
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Entry::JOIN_USER + XfAddOns_Blogs_Model_Entry::JOIN_LIKE_INFORMATION +
								XfAddOns_Blogs_Model_Entry::JOIN_READ_DATE + XfAddOns_Blogs_Model_Entry::JOIN_VISITOR_FOLLOW +
								XfAddOns_Blogs_Model_Entry::JOIN_DELETION_LOG;
		$fetchOptions['whereOptions'] = XfAddOns_Blogs_Model_Entry::WHERE_PRIVACY;
		
		// entries
		$entries = $this->entryModel->getBlogEntriesForUser($blog, $fetchOptions);
		
		$this->entryModel->prepareEntries($entries);
		$this->entryModel->getAndMergeAttachmentsIntoEntries($entries);
		$this->entryModel->removePrivateEntriesForVisitor($entries);
		
		// weave in the categories
		$this->categoryModel->getAndMergeSelectedCategories($entries);
		
		// mark the blog as read
		$maxEntryDate = $this->entryModel->getMaxEntryDate($entries);
		$this->blogModel->markBlogAsRead(XenForo_Visitor::getUserId(), $blog['user_id'], $blog['blog_read_date'], $maxEntryDate);

		// register that someone viewed this blog
		/* @var $blogView XfAddOns_Blogs_Model_BlogView */
		$blogView = XenForo_Model::create('XfAddOns_Blogs_Model_BlogView');
		$blogView->registerView($blog);
		
		// do we have scheduled entries?
		if ($blog['scheduled_entries'] > 0)
		{
			$scheduledFetchOptions = array(
				'limit' => 10
			);
			/* @var $scheduledModel XfAddOns_Blogs_Model_EntryScheduled */
			$scheduledModel = XenForo_Model::create('XfAddOns_Blogs_Model_EntryScheduled');
			$scheduledEntries = $scheduledModel->getScheduledEntriesForUser($blog, $scheduledFetchOptions);
		}
		// calculate any show vars and dispatch
		
		$containerParams = array(
			'isBlogContainer' => true,
			'containerTemplate' => ($options->xfa_blogs_blogMode != 'classic') ? 'xfa_blog_PAGE_CONTAINER_full_screen' : 'PAGE_CONTAINER',
			'blog' => $blog,
			'noVisitorPanel' => true,
			'showCustomization' => true
		);
		$params = array(
			'blog' => $blog,
			'page' => $page,
			'entriesPerPage' => $options->xfa_blogs_entriesPerPage,
			'entries' => $entries,
			'showCustomizeSidebar' => $blog['entry_count'] > 0,	// an additional check will be made for permissions, this is to skip in entry page
			'showWatchBlogSidebar' => true,
			'showDownloadLink' => $blog['perms']['canDownload'] && class_exists('ZipArchive'),
			'panels' => $this->blogModel->getPanels($blog),
			'scheduledEntries' => isset($scheduledEntries) ? $scheduledEntries : null, 
			'showCustomization' => true
		);
		
		if ($options->blogAdvancedFeatures)
		{
			$params['fonts'] = XfAddOns_Blogs_Helper_Fonts::getFonts(); 
		}
		
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Blog_Index', 'xfa_blog_index', $params, $containerParams);
	}
	
	/**
	 * Returns a list of all the blog entries for the user in Atom
	 */
	public function actionRss()
	{
		$blog = $this->getBlog();
		if (!$blog['perms']['canView'])
		{
			$params = array( 'username' => $blog['username'] );
			return $this->responseError(new XenForo_Phrase('xfa_blog_you_dont_have_permissions_user_blog', $params));
		}
		
		$options = XenForo_Application::getOptions();
		if (!$options->blogAdvancedFeatures)
		{
			return $this->responseError(new XenForo_Phrase('xfa_blog_rss_is_disabled'), 404);
		}

		$fetchOptions = array();
		$fetchOptions['page'] = 1;
		$fetchOptions['reverse'] = $blog['entry_count'];
		$fetchOptions['where'] = "message_state='visible'";
		
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Entry::JOIN_USER + XfAddOns_Blogs_Model_Entry::JOIN_VISITOR_FOLLOW;
		$fetchOptions['whereOptions'] = XfAddOns_Blogs_Model_Entry::WHERE_PRIVACY;
		
		$entries = $this->entryModel->getBlogEntriesForUser($blog, $fetchOptions);
		$this->entryModel->prepareEntries($entries);
		$this->entryModel->wireBlogs($entries);
		$this->entryModel->removePrivateEntriesForVisitor($entries);
		
		$viewParams = array(
			'blog' => $blog,
			'entries' => $entries,
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Blog_Rss', '', $viewParams);
	}
	
	/**
	 * Displays the page for creating a new entry, this page will ask for the entry title and the content
	 */
	public function actionNewEntry()
	{
		$options = XenForo_Application::getOptions();
		$blog = $this->getBlog();
		$this->validateNewBlog($blog);

		if (!$blog['perms']['canCreateEntry'])
		{
			return $this->responseNoPermission();
		}
		
		// parameters for attachments
		$attachmentParams = array(
			'hash' => md5(uniqid('', true)),
			'content_type' => 'xfa_blog_entry',
			'content_data' => array( 'blog_user_id' => $blog['user_id'] )
		);
		$attachmentConstraints = $this->getModelFromCache('XenForo_Model_Attachment')->getAttachmentConstraints();
		
		// check if we have a saved draft
		/* @var $draftModel XenForo_Model_Draft */
		$draftModel = XenForo_Model::create('XenForo_Model_Draft');
		$key = 'xfa-blog-entry-' . $blog['user_id'];
		$draft = $draftModel->getDraftByUserKey($key, XenForo_Visitor::getUserId());
		
		// return the response
		$containerParams = array(
			'isBlogContainer' => true,
			'containerTemplate' => ($options->xfa_blogs_blogMode != 'classic') ? 'xfa_blog_PAGE_CONTAINER_full_screen' : 'PAGE_CONTAINER',
			'noVisitorPanel' => true,
			'blog' => $blog
		);
		
		$params = array(
			'blog' => $blog,
			'attachmentParams' => $attachmentParams,
			'attachmentConstraints' => $attachmentConstraints,
			'categories' => $this->getCategories($blog),
			'draft' => $draft
			);
		XfAddOns_Blogs_Helper_TimeParams::addTimeParams($params, XenForo_Application::$time);	
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Entry_Add', 'xfa_blog_entry_edit', $params, $containerParams);
	}
	
	/**
	 * Return the list of categories
	 */
	protected function getCategories($blog)
	{
		$allCategories = array();
		$fetchOptions['orderBy'] = 'user_id ASC, display_order ASC';
		$allCategories = $this->categoryModel->getCategoriesForBlogs(array($blog['user_id'], 0), $fetchOptions);
		$allCategories = $this->categoryModel->convertToTree($allCategories);
		$allCategories = $this->categoryModel->flattenTree($allCategories);
		return $allCategories;		
	}
	
	/**
	 * Edits an existing entry on the database
	 */
	public function actionEditEntry()
	{
		$options = XenForo_Application::getOptions();
		$fetchOptions['prepareOptions'] = XfAddOns_Blogs_Model_Entry::PREPARE_ALLOW_MEMBERS;
		list($entry, $blog) = $this->getEntryAndBlog(0, $fetchOptions);
		if (!$entry['perms']['canEdit'])
		{
			return $this->responseNoPermission();
		}
	
		// parameters for attachments
		/* @var $attachmentModel XenForo_Model_Attachment */
		$attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment'); 
		$attachments = $attachmentModel->getAttachmentsByContentId('xfa_blog_entry', $entry['entry_id']);
		$attachmentParams = array(
			'hash' => md5(uniqid('', true)),
			'content_type' => 'xfa_blog_entry',
			'content_data' => array( 'entry_id' => $entry['entry_id'] )
		);
		$attachmentConstraints = $attachmentModel->getAttachmentConstraints();

		// iterate over the categories and mark them as selected
		$categories = $this->getCategories($blog);
		$selectedCategories = $this->categoryModel->getSelectedCategoriesForEntry($entry['entry_id']);
		
		$selectedIds = array_keys($selectedCategories);
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
			'entry' => $entry,
			'message' => $entry['message'],
			'categories' => $categories,
			'attachmentParams' => $attachmentParams,
			'attachmentConstraints' => $attachmentConstraints,				
			'attachments' => $attachmentModel->prepareAttachments($attachments) 
		);
		XfAddOns_Blogs_Helper_TimeParams::addTimeParams($params, $entry['post_date']);
		
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Entry_Edit', 'xfa_blog_entry_edit', $params, $containerParams);
	}	
	
	/**
	 * Save the information for the entry
	 * This method is called after the edit or create action, with the entry information
	 */
	public function actionSaveEntry()
	{
		$this->_assertPostOnly();
		
		// fetch the information for the blog
		$blog = $this->getBlog();
		$this->validateNewBlog($blog);

		// permission check depends on whether this is a new entry or we are editing one
		$entryId = $this->_input->filterSingle('entry_id', XenForo_Input::UINT);
		if ($entryId) 
		{
			list ( $entry, $blog ) = $this->getEntryAndBlog();
			if (!$entry['perms']['canEdit'])
			{
				return $this->responseNoPermission();
			}	
		}
		else
		{
			if (!$blog['perms']['canCreateEntry'])
			{
				return $this->responseNoPermission();
			}			
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
		
		// get attachment and other data		
		$attachmentHash = $this->_input->filterSingle('attachment_hash', XenForo_Input::STRING);
		$categories = $this->_input->filterSingle('category', array(XenForo_Input::UINT, 'array' => true));
		
		$helper = new XenForo_ControllerHelper_Editor($this);
		$dwInput['message'] = $helper->getMessageText('message', $this->_input);
		$dwInput['message'] = XenForo_Helper_String::autoLinkBbCode($dwInput['message']);
		
		$dwInput['user_id'] = $blog['user_id'];
		$dwInput['post_date'] = XfAddOns_Blogs_Helper_TimeParams::getPostDateFromRequest($this->_input);
		if (empty($dwInput['post_date']))
		{
			$dwInput['post_date'] = XenForo_Application::$time;
		}
		
		// visitor reference
		$visitor = XenForo_Visitor::getInstance();
		
		/* @var $dwEntry XfAddOns_Blogs_DataWriter_Entry */
		$dwEntry = null;
		if ($entryId)
		{
			$dwEntry = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Entry');
			$dwEntry->setExistingData($entryId);
		}
		else
		{
			// for insertions, we have two options of data writers, either normal, or scheduled
			if ($dwInput['post_date'] <= (XenForo_Application::$time))
			{
				$dwEntry = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Entry');
			}
			else
			{
				$dwEntry = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_EntryScheduled');	
			}
		}
		
		$dwEntry->bulkSet($dwInput);
		$dwEntry->setExtraData('username', $visitor->get('username'));		// only really used on insert
		$dwEntry->setExtraData(XfAddOns_Blogs_DataWriter_Entry::DATA_ATTACHMENT_HASH, $attachmentHash);
		$dwEntry->setExtraData(XfAddOns_Blogs_DataWriter_Entry::EXTRA_DATA_NEW_CATEGORIES, $categories);
		$dwEntry->setAllowMembers($allowMembers);
		$dwEntry->save();
		
		// on update, rebuild the position if the date changed
		// on insert, rebuild if we are inserting in the past
		if (($entryId && $dwEntry->isChanged('post_date')) || $dwInput['post_date'] < $blog['last_entry'])
		{
			/* @var $rebuildModel XfAddOns_Blogs_Model_Rebuild */
			$rebuildModel = XenForo_Model::create('XfAddOns_Blogs_Model_Rebuild');
			$rebuildModel->rebuildEntryPositionIndex($blog['user_id']);
		}

		// Delete any drafts that we may have on insert
		if ($dwEntry->isInsert())
		{
			$key = 'xfa-blog-entry-' . $blog['user_id'];
			/* @var $draftModel XenForo_Model_Draft */
			$draftModel = XenForo_Model::create('XenForo_Model_Draft');
			$draftModel->deleteDraft($key);
		}
		
		// log on edit
		if ($dwEntry->isUpdate())
		{
			XenForo_Model_Log::logModeratorAction('xfa_blog_entry', $entry, 'edit', array());
		}
		
		// get the entry information and return to the newly created entry
		$entry = $dwEntry->getMergedData();
		$redirectUrl = $entryId ? 
			XenForo_Link::buildPublicLink('xfa-blog-entry', $entry) :
			XenForo_Link::buildPublicLink('xfa-blogs', $blog);
		
		return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirectUrl
		);
	}
	
	/**
	 * If the blog was not found, validate whether the blog can be created.
	 * This can be done if the user exists
	 */
	private function validateNewBlog($blog)
	{
		if (!$blog['blog_exists'])
		{
			/* @var $dw XfAddOns_Blogs_DataWriter_Blog */
			$dw = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_Blog');
			$dw->set('user_id', $blog['user_id']);
			$dw->save();
		}
	}

	/**
	 * This will add an entry for the current user to watch the blog
	 */
	public function actionWatch()
	{
		$blog = $this->getBlog();
		if (!$blog['perms']['canView'])
		{
			return $this->responseNoPermission();
		}
		
		// well, anonymous users can't really subscribe
		$userId = XenForo_Visitor::getUserId();
		if (!$userId)
		{
			return $this->responseNoPermission();
		}
		
		/* @var $watchModel XfAddOns_Blogs_Model_BlogWatch */
		$watchModel = XenForo_Model::create('XfAddOns_Blogs_Model_BlogWatch');
		$watch = $watchModel->getWatch($userId, $blog['user_id']);
		
		if (!$watch)
		{
			/* @var $dwWatch XfAddOns_Blogs_DataWriter_BlogWatch */
			$dwWatch = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_BlogWatch');
			$dwWatch->set('user_id', $userId);
			$dwWatch->set('blog_user_id', $blog['user_id']);
			$dwWatch->save();			
		}
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blogs', $blog),
			new XenForo_Phrase('xfa_blogs_you_are_now_watching_this_blog')
		);		
	}
	
	/**
	 * This will remove the entry for a watched blog
	 */
	public function actionUnwatch()
	{
		$blog = $this->getBlog();
		if (!$blog['perms']['canView'])
		{
			return $this->responseNoPermission();
		}
		
		// well, anonymous users can't really unsubscribe
		$userId = XenForo_Visitor::getUserId();
		if (!$userId)
		{
			return $this->responseNoPermission();
		}	
		
		// gets the data for the watch
		/* @var $watchModel XfAddOns_Blogs_Model_BlogWatch */
		$watchModel = XenForo_Model::create('XfAddOns_Blogs_Model_BlogWatch');
		$watch = $watchModel->getWatch($userId, $blog['user_id']);
		if (!$watch)
		{
			return $this->responseError(new XenForo_Phrase('xfa_blogs_you_are_not_watching_this_blog'));
		}

		/* @var $dwWatch XfAddOns_Blogs_DataWriter_BlogWatch */
		$dwWatch = XenForo_DataWriter::create('XfAddOns_Blogs_DataWriter_BlogWatch');
		$dwWatch->setExistingData($watch, true);
		$dwWatch->delete();
		
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blogs', $blog),
			new XenForo_Phrase('xfa_blogs_you_are_no_longer_watching_this_blog')
		);
	}
	
	/**
	 * This is a redirect that comes from the main site whenever we want to migrate the session that we have started in the forum
	 * It will come with a sid, that we will honor if currently the user in the forum is just a visitor. After we set the cookie we will
	 * redirect to the login page, which will do a last check to see if the login was successful. If it wasn't, the user will be presented
	 * with a login screen instead
	 */
	public function actionStartSession()
	{
		// get the blog identifier
		$blog = $this->getBlog();
		
		// check if we are already logged in. If that is the case, we should not override the login
		$visitorUserId = XenForo_Visitor::getUserId();
		if ($visitorUserId > 0)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xfa-blogs', $blog)
			);			
		}
		
		// we are not logged in, so let's check for the sid, and, override it if necessary
		$sid = $this->_input->filterSingle('sid', XenForo_Input::STRING);
		if (!empty($sid))
		{
			XenForo_Helper_Cookie::setCookie('session', $sid, 0, true);
		}
		
		$u = $this->_input->filterSingle('u', XenForo_Input::STRING);
		if (!empty($u))
		{
			XenForo_Helper_Cookie::setCookie('user', $u, 30 * 86400, true);
		}

		// cookie set, one last thing, let's redirect to the login page
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('xfa-blogs/login', $blog)
		);
	}	
	
	/**
	 * This method is called through an AJAX request to show in the member_view all the blog entries for the user
	 */
	public function actionMemberProfile()
	{
		$blog = $this->getBlog();
		
		// fetch options
		$fetchOptions['reverse'] = $blog['entry_count'];
		$fetchOptions['where'] = "message_state='visible'";
		$fetchOptions['join'] = XfAddOns_Blogs_Model_Entry::JOIN_USER + XfAddOns_Blogs_Model_Entry::JOIN_DELETION_LOG + XfAddOns_Blogs_Model_Entry::JOIN_VISITOR_FOLLOW;
		$fetchOptions['whereOptions'] = XfAddOns_Blogs_Model_Entry::WHERE_PRIVACY;
		
		$entries = $this->entryModel->getBlogEntriesForUser($blog, $fetchOptions);
		
		$viewParams = array(
			'user' => $blog,
			'entries' => $entries,
			'snippetLength' => XenForo_Application::getOptions()->xfa_blogs_trimLength
		);
		
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blogs_member_content', $viewParams);
	}	

	/**
	 * This method will display a login form if the user is not logged in
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionLogin()
	{
		$options = XenForo_Application::getOptions();
		$blog = $this->getBlog();
		
		// check if we are already logged in. If that is the case, we should just redirect
		$visitorUserId = XenForo_Visitor::getUserId();
		if ($visitorUserId > 0)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('xfa-blogs', $blog)
			);
		}
		
		$containerParams = array(
			'isBlogContainer' => true,
			'containerTemplate' => ($options->xfa_blogs_blogMode != 'classic') ? 'xfa_blog_PAGE_CONTAINER_full_screen' : 'PAGE_CONTAINER',
			'noVisitorPanel' => true,
			'blog' => $blog
		);		
		return $this->responseView('XenForo_ViewPublic_Base', 'xfa_blog_login', array(), $containerParams);
	}	

	/**
	 * This action will start a download for all the entries on the blog of the person.
	 */	
	public function actionDownload() 
	{
		// permissions
		$blog = $this->getBlog();
		if (!$blog['perms']['canDownload'])
		{
			return $this->responseNoPermission();
		}
		
		// check for the class
		if (!class_exists('ZipArchive'))
		{
			$msg = new XenForo_Phrase('xfa_blog_ziparchive_required');
			throw new XenForo_ControllerResponse_Exception($this->responseError($msg));
		}
		
		if (XenForo_Visitor::getUserId() != $blog['user_id'])
		{
			return $this->responseNoPermission();
		}
		
		// create the download
		try
		{
			$helper = new XfAddOns_Blogs_Helper_Download();
			$filename = $helper->createDownload($blog);
		}
		catch (XfAddOns_Blogs_Helper_ZipException $ex)
		{
			throw new XenForo_ControllerResponse_Exception($this->responseError($ex->getMessage()));
		}
		
		// and deliver the download
		header('Content-type: application/zip');
		header('Content-Disposition: attachment; filename="blog.zip"');
		header('Content-Transfer-Encoding: binary');
		header("Content-Length: ". filesize($filename));
		
		readfile($filename);
		exit;
	}
	
	/**
	 * Action called to save the draft of the entry being written
	 */
	public function actionSaveDraft()
	{
		$blog = $this->getBlog();
		$key = 'xfa-blog-entry-' . $blog['user_id'];
	
		$message = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$title = $this->_input->filterSingle('title', XenForo_Input::STRING);
		$forceDelete = $this->_input->filterSingle('delete_draft', XenForo_Input::BOOLEAN);
	
		/* @var $draftModel XenForo_Model_Draft */
		$draftModel = XenForo_Model::create('XenForo_Model_Draft');
		
		if (!strlen($message) || $forceDelete)
		{
			$draftSaved = false;
			$draftDeleted = $draftModel->deleteDraft($key) || $forceDelete;
		}
		else
		{
			$extra = array(
				'title' => $title
			);
			$draftModel->saveDraft($key, $message, $extra);
			$draftSaved = true;
			$draftDeleted = false;
		}
	
		$viewParams = array(
			'draftSaved' => $draftSaved,
			'draftDeleted' => $draftDeleted
		);
		return $this->responseView('XfAddOns_Blogs_ViewPublic_Entry_SaveDraft', '', $viewParams);
	}	
	
	
	/**
	 * Gets session activity details of activity records that are pointing to this controller.
	 * This must check the visiting user's permissions before returning item info.
	 * Return value may be:
	 * 		* false - means page is unknown
	 * 		* string/XenForo_Phrase - gives description for all, but no item details
	 * 		* array (keyed by activity keys) of strings/XenForo_Phrase objects - individual description, no item details
	 * 		* array (keyed by activity keys) of arrays. Sub-arrays keys: 0 = description, 1 = specific item title, 2 = specific item url.
	 *
	 * @param array $activities List of activity records
	 *
	 * @return mixed See above.
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		$userIds = array();
		foreach ($activities AS $key => $activity)
		{
			if (!empty($activity['params']['user_id']))
			{
				$userIds[] = $activity['params']['user_id'];
			}
		}
	
		$blogMap = array();
		if (!empty($userIds))
		{
			/* @var $blogModel XfAddOns_Blogs_Model_Blog */
			$blogModel = XenForo_Model::create('XfAddOns_Blogs_Model_Blog');
			$fetchOptions = array('join' => XfAddOns_Blogs_Model_Blog::JOIN_PRIVACY + XfAddOns_Blogs_Model_Blog::JOIN_VISITOR_FOLLOW);
			$blogMap = $blogModel->getBlogsByIds($userIds, $fetchOptions);
			$blogModel->prepareBlogs($blogMap);
		}
		
		// generate the output
		$output = array();
		foreach ($activities AS $key => $activity)
		{
			if (!empty($activity['params']['user_id']) && isset($blogMap[$activity['params']['user_id']]))
			{
				$blog = $blogMap[$activity['params']['user_id']];
				if (!$blog['perms']['canView'])
				{
					$output[$key] = new XenForo_Phrase('xfa_blogs_viewing_private_blog');
					continue;
				}
				$output[$key] = array(
					new XenForo_Phrase('xfa_blogs_viewing_blog'),
					$blog['username'],
					XenForo_Link::buildPublicLink('xfa-blogs', $blog),
					false
				);
			}
			else
			{
				$output[$key] = new XenForo_Phrase('xfa_blogs_viewing_blogs');
			}
		}
		return $output;
	}
	
	
}