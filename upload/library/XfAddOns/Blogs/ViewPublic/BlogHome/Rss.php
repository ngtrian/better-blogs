<?php

class XfAddOns_Blogs_ViewPublic_BlogHome_Rss extends XenForo_ViewPublic_Base
{
	
	/**
	 * Iterates over the entry to search for the most recent one
	 * @return int
	 */
	private function getLastUpdate()
	{
		$lastUpdate = -1;
		foreach ($this->_params['entries'] as $entry)
		{
			$lastUpdate = max($lastUpdate, $entry['post_date']);
		}	
		return $lastUpdate;	
	}
	
	/**
	 * Renderer for the rss content
	 */
	public function renderRss()
	{
		$options = XenForo_Application::get('options');
		$buggyXmlNamespace = (defined('LIBXML_DOTTED_VERSION') && LIBXML_DOTTED_VERSION == '2.6.24');

		$entries = $this->_params['entries'];
		$lastUpdate = $this->getLastUpdate();
		
		$title = new XenForo_Phrase('xfa_blogs_blogs_at_x', array('title' => $options->boardTitle));
		$title = $title->__toString();
		
		$feed = new Zend_Feed_Writer_Feed();
		$feed->setEncoding('utf-8');
		$feed->setTitle($title);
		$feed->setDescription(!empty($options->boardDescription) ? $options->boardDescription : $options->boardTitle);
		$feed->setLink(XenForo_Link::buildPublicLink('canonical:xfa-blog-home/entriesRss.rss'), 'rss');
		if (!$buggyXmlNamespace)
		{
			$feed->setFeedLink(XenForo_Link::buildPublicLink('canonical:xfa-blog-home/entriesRss.rss'), 'rss');
		}
		if ($lastUpdate > 0)
		{
			$feed->setDateModified($lastUpdate);
			$feed->setLastBuildDate($lastUpdate);	
		}
		$feed->setGenerator('xfa_blogs');

		// parse the bbcode on the entries
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array( 'states' => array( 'viewAttachments' => false ) );
		
		// safely wrap the messages
		try
		{
			XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['entries'], $bbCodeParser, $bbCodeOptions);	
		}
		catch (Exception $ex)
		{
			XenForo_Error::logException($ex, false);
		}
		
		foreach ($this->_params['entries'] AS $blogEntry)
		{
			$entry = $feed->createEntry();
			$entry->setTitle($blogEntry['title']);
			
			$messageContent = '';
			try {
				$messageContent = '' . $blogEntry['messageHtml'] . '';	
			}
			catch (Exception $ex)
			{
				XenForo_Error::logException($ex, false);
				$messageContent = $blogEntry['message'];
			}
			
			if (!empty($messageContent))
			{
				$entry->setContent($messageContent);	
			}
			
			$entry->setLink(XenForo_Link::buildPublicLink('canonical:xfa-blog-entry', $blogEntry));
			$entry->setDateCreated(new Zend_Date($blogEntry['post_date'], Zend_Date::TIMESTAMP));
			$entry->setDateModified(new Zend_Date($blogEntry['post_date'], Zend_Date::TIMESTAMP));
			if (!$buggyXmlNamespace)
			{
				$entry->addAuthor(array(
					'name' => $blogEntry['username'],
					'uri' => XenForo_Link::buildPublicLink('canonical:members', $blogEntry)
				));
				if ($blogEntry['reply_count'])
				{
					$entry->setCommentCount($blogEntry['reply_count']);
				}
			}

			$feed->addEntry($entry);
		}

		return $feed->export('rss');
	}
	
	
	
}