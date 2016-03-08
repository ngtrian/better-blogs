<?php

class XfAddOns_Blogs_Install_Install extends XfAddOns_Blogs_Install_Abstract
{
	
	/**
	 * Create the tables as needed by this hack
	 */
	public static function install($installedAddon)
	{
		@set_time_limit(0);
		@ignore_user_abort(true);
		
 		if (XenForo_Application::$versionId < 1040031)
 		{
 			throw new XenForo_Exception('This add-on requires XenForo 1.4.0 or higher.', true);
 		}	
		
		$installer = new XfAddOns_Blogs_Install_Install();
		$version = is_array($installedAddon) ? $installedAddon['version_id'] : 0;
		if ($version < 100)
		{
			$installer->step1();
		}
		if ($version < 102)
		{
			$installer->step2();
		}
		if ($version < 103)
		{
			$installer->step3();
		}
		if ($version < 106)
		{
			$installer->step6();
		}
		if ($version < 107)
		{
			$installer->step7();
		}
		if ($version < 112)
		{
			$installer->step8();
		}
		if ($version < 117)
		{
			$installer->step10();
		}
		if ($version < 118)
		{
			$installer->step11();
		}
		if ($version < 120)
		{
			$installer->step12();
		}
		if ($version < 121)
		{
			$installer->step13();
		}
		if ($version < 124)
		{
			$installer->step14();
		}
		if ($version < 128)
		{
			$installer->step16();
		}
		if ($version < 132)
		{
			$installer->step17();
		}
		if ($version < 133)
		{
			$installer->step18();
		}
		if ($version < 144)
		{
			$installer->step19();
		}		
		
		// rebuild the content types
		$installer->checkTable();
		$installer->checkContentTypes();
	}
	
	/**
	 * So sad that this needs to be uninstalled, but .. there it goes. Delete all the data used by this hack
	 */
	public static function uninstall()
	{
		@set_time_limit(0);
		@ignore_user_abort(true);
		
		
		$config = XenForo_Application::getConfig();
		if (!$config->allow_blog_uninstall)
		{
			$msg = "
				Since uninstall is a destructive operation and will remove ALL blogs and ALL entries, to uninstall the blog you need to add the following to your library/config.php:
					<br/><br/>
					<pre>
					\$config['allow_blog_uninstall'] = true;
					</pre>					
			";
			
			
			throw new Exception($msg);
		}
		
		$installer = new XfAddOns_Blogs_Install_Install();
		$installer->doUninstall();
	}
	
	/**
	 * This will delete all data in the tables, manually. Usually this is not necessary, it is done by the DataWriter. But
	 * if it messes up, we want to do this
	 */
	public function uninstallForce()
	{
		$uninstall = array();
		$uninstall[] = "DELETE FROM xf_content_type_field where content_type = 'xfa_blog_comment'";
		$uninstall[] = "DELETE FROM xf_content_type_field where content_type = 'xfa_blog_entry'";
		$uninstall[] = "DELETE FROM xf_content_type where content_type = 'xfa_blog_comment'";
		$uninstall[] = "DELETE FROM xf_content_type where content_type = 'xfa_blog_entry'";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_deferred_view";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_view";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_entry_view";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_entry_category";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_category";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_category_global";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_entry_read";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_read";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_css";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_comment";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_entry_watch";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_entry";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_entry_scheduled";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_watch";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog";
		$uninstall[] = "ALTER TABLE xf_user_privacy DROP allow_view_blog";
		$uninstall[] = "ALTER TABLE xf_user_profile DROP entry_count";
		$uninstall[] = "ALTER TABLE xf_user_profile DROP blog_key";
		$uninstall[] = "DELETE FROM xf_attachment where content_type = 'xfa_blog_entry'";
		$uninstall[] = "DELETE FROM xf_news_feed where content_type = 'xfa_blog_entry'";
		$uninstall[] = "DELETE FROM xf_user_alert where content_type = 'xfa_blog_entry'";
		$uninstall[] = "DELETE FROM xf_admin_navigation WHERE addon_id = 'xfa_blogs'";
		$uninstall[] = "DELETE FROM xf_admin_permission WHERE addon_id = 'xfa_blogs'";
		$uninstall[] = "DELETE FROM xf_code_event WHERE addon_id = 'xfa_blogs'";
		$uninstall[] = "DELETE FROM xf_code_event_listener WHERE addon_id = 'xfa_blogs'";
		$uninstall[] = "DELETE FROM xf_cron_entry WHERE addon_id = 'xfa_blogs'";
		$uninstall[] = "DELETE FROM xf_option WHERE addon_id = 'xfa_blogs'";
		$uninstall[] = "DELETE FROM xf_option_group WHERE addon_id = 'xfa_blogs'";
		$uninstall[] = "DELETE FROM xf_permission WHERE addon_id = 'xfa_blogs'";
		$uninstall[] = "DELETE FROM xf_permission_group WHERE addon_id = 'xfa_blogs'";
		$uninstall[] = "DELETE FROM xf_permission_interface_group WHERE addon_id = 'xfa_blogs'";
		$uninstall[] = "DELETE FROM xf_phrase WHERE addon_id = 'xfa_blogs'";
		$uninstall[] = "DELETE FROM xf_route_prefix WHERE addon_id = 'xfa_blogs'";
		$uninstall[] = "DELETE FROM xf_template WHERE addon_id = 'xfa_blogs'";
		$uninstall[] = "DELETE FROM xf_option_group_relation where option_id like 'xfa_blog%'";
		$uninstall[] = "DELETE FROM xf_option_group_relation WHERE option_id='xfa_option_use_style'";
		$uninstall[] = "DELETE FROM xf_addon WHERE addon_id = 'xfa_blogs'";		
		
		foreach ($uninstall as $sql)
		{
			$this->executeUpgradeQuery($sql, array(), true);
		}		
	}	
	
	/**
	 * So sad that this needs to be uninstalled, but .. there it goes. Delete all the data used by this hack
	 */
	public function doUninstall()
	{
		$uninstall = array();
		$uninstall[] = "DELETE FROM xf_content_type_field where content_type = 'xfa_blog_comment'";
		$uninstall[] = "DELETE FROM xf_content_type_field where content_type = 'xfa_blog_entry'";
		$uninstall[] = "DELETE FROM xf_content_type where content_type = 'xfa_blog_comment'";
		$uninstall[] = "DELETE FROM xf_content_type where content_type = 'xfa_blog_entry'";

		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_deferred_view";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_view";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_entry_view";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_entry_category";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_category";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_category_global";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_entry_read";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_read";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_css";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_comment";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_entry_watch";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_entry";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_entry_scheduled";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog_watch";
		$uninstall[] = "DROP TABLE IF EXISTS xfa_blog";
		
		$uninstall[] = "ALTER TABLE xf_user_privacy DROP allow_view_blog";
		$uninstall[] = "ALTER TABLE xf_user_profile DROP entry_count";		// these most likely do not exist on this version of the installer, but try to drop them regardless
		$uninstall[] = "ALTER TABLE xf_user_profile DROP blog_key";
		
		$uninstall[] = "DELETE FROM xf_attachment where content_type = 'xfa_blog_entry'";
		$uninstall[] = "DELETE FROM xf_news_feed where content_type = 'xfa_blog_entry'";
		$uninstall[] = "DELETE FROM xf_user_alert where content_type = 'xfa_blog_entry'";
		
		foreach ($uninstall as $sql)
		{
			$this->executeUpgradeQuery($sql, array(), true);
		}
		
		// Delete any caches used by the application
		/* @var $registryModel XenForo_Model_DataRegistry */
		$registryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
		$registryModel->delete('xfa_blog_mostcomments');
		$registryModel->delete('xfa_blog_mostentries');
		$registryModel->delete('xfa_blog_comments_home');
		$registryModel->delete('xfab_recently_created');
		$registryModel->delete('xfab_recently_updated');
		$registryModel->delete('xfa_blog_entries_home');		
		$registryModel->delete('xfab_totals');
		$registryModel->delete('xfab_wf_local_blogs');
		$registryModel->delete('xfab_wf_local_comments');
		$registryModel->delete('xfab_wf_local_entries');
		$registryModel->delete('__xfab_global_categ');
		
		/* @var $cacheModel XenForo_Model_ContentType */
		$cacheModel = XenForo_Model::create('XenForo_Model_ContentType');
		$cacheModel->rebuildContentTypeCache();	
	}
	
	/**
	 * Step 1 of installation
	 */
	public function step1()
	{
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog
			(
				user_id int not null primary key,
				entry_count int not null default 0,
				last_entry int null,
				title varchar(255) not null,
				description mediumtext not null				
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog_watch
			(
				watch_id int not null primary key auto_increment,
				user_id int not null,
				blog_user_id int not null,
				UNIQUE ux_watch (user_id, blog_user_id)
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog_entry
			(
				entry_id int not null primary key auto_increment,
				user_id int not null,
				title varchar(255) not null,
				post_date int not null,
				reply_count int not null,
				message mediumtext not null,
				message_state enum('visible','deleted') default 'visible',
				ip_id int not null,
				position int not null,
				likes int not null,
				like_users blob null
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog_entry_watch
			(
				watch_id int not null primary key auto_increment,
				user_id int not null,
				entry_id int not null,
				UNIQUE ux_watch (user_id, entry_id)
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog_comment
			(
				comment_id int not null primary key auto_increment,
				entry_id int not null,
				user_id int not null,
				post_date int not null,
				message mediumtext not null,
				message_state enum('visible','deleted') default 'visible',
				ip_id int not null,
				position int not null,
				likes int not null,
				like_users blob null	
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");	
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog_css
			(
				css_id int not null primary key auto_increment,
				user_id int not null,
				className varchar(50) not null,
				varname varchar(50) not null,
				value varchar(1000) not null
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog_read
			(
				read_id int not null primary key auto_increment,
				user_id int not null,
				blog_user_id int not null,
				blog_read_date int not null,
				unique (user_id, blog_user_id)
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog_entry_read
			(
				read_id int not null primary key auto_increment,
				user_id int not null,
				entry_id int not null,
				entry_read_date int not null,
				unique (user_id, entry_id)
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog_category
			(
				category_id int not null primary key auto_increment,
				user_id int not null,
				category_name varchar(255) not null,
				entry_count int default 0 not null
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog_entry_category
			(
				entry_id int not null,
				category_id int not null,
				primary key (entry_id, category_id)
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");

		$this->executeUpgradeQuery("ALTER TABLE xfa_blog_entry ADD INDEX idx_user (user_id)");
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog_entry ADD INDEX idx_position (user_id, position)");
		
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog_comment ADD INDEX idx_entry (entry_id)");
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog_comment ADD INDEX idx_user (user_id)");
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog_comment ADD INDEX idx_position (entry_id, position)");
		
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog_css ADD UNIQUE ux_css (user_id, className, varname)");
		
		// inserting constants for the handlers
		$this->executeUpgradeQuery("REPLACE INTO xf_content_type VALUES ('xfa_blog', 'xfa_blogs', '')");
		$this->executeUpgradeQuery("REPLACE INTO xf_content_type VALUES ('xfa_blog_comment', 'xfa_blogs', '')");
		$this->executeUpgradeQuery("REPLACE INTO xf_content_type VALUES ('xfa_blog_entry', 'xfa_blogs', '')");
		
		// privacy options
		$this->executeUpgradeQuery("ALTER TABLE xf_user_privacy ADD allow_view_blog enum('everyone','members','followed','none') NOT NULL DEFAULT 'everyone'");
		$this->executeUpgradeQuery("ALTER TABLE xf_user_profile ADD entry_count int unsigned default 0 not null");
	}
	
	/**
	 * Upgrade for reporting options
	 */
	public function step2()
	{
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog CHANGE title blog_title varchar(255) default '' not null");
	}
	
	/**
	 * Upgrade for custom domain blogs
	 */
	public function step3()
	{
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog ADD blog_key varchar(60) default '' NOT NULL after blog_title");
		$this->executeUpgradeQuery("ALTER TABLE xf_user_profile ADD blog_key varchar(60) default '' NOT NULL");
	}
	
	/**
	 * Upgrade for columns
	 */
	public function step6()
	{
		// register the creation date of the blog
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog ADD create_date int null after entry_count");
		$this->executeUpgradeQuery("UPDATE xfa_blog SET create_date = (SELECT min(post_date) FROM xfa_blog_entry WHERE xfa_blog.user_id = xfa_blog_entry.user_id AND message_state='visible')");
		
		// remove columns that will not be used anymore (we will just join the user information)
		$this->executeUpgradeQuery("ALTER TABLE xf_user_profile DROP entry_count");
		$this->executeUpgradeQuery("ALTER TABLE xf_user_profile DROP blog_key");
	}
	
	/**
	 * Upgrade for scheduled entries
	 */
	public function step7()
	{
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog ADD scheduled_entries int default 0 NOT NULL");
		
		// add support for scheduled entries
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog_entry_scheduled
			(
				scheduled_entry_id int not null primary key auto_increment,
				user_id int not null,
				title varchar(255) not null,
				post_date int not null,
				message mediumtext not null,
				ip_id int not null,
				categories mediumtext null
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
	}
	
	/**
	 * Upgrade for views
	 */
	public function step8()
	{
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog_view
			(
				user_id int not null,
				type enum ('guest', 'registered'),
				ipOrUser int unsigned not null,
				views int not null,
				last_visit int not null
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci			
		");
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog_entry_view
			(
				entry_id int not null,
				type enum ('guest', 'registered'),
				ipOrUser int unsigned not null,
				views int not null,
				last_visit int not null
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
		$this->executeUpgradeQuery("
			CREATE TABLE xfa_blog_deferred_view
			(
				type enum ('blog', 'entry'),
				id int not null
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		");
		
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog_view ADD UNIQUE visit (user_id, type, ipOrUser)");
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog_entry_view ADD UNIQUE visit (entry_id, type, ipOrUser)");
		
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog ADD view_count int not null DEFAULT 0 AFTER entry_count");
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog_entry ADD view_count int not null DEFAULT 0 AFTER reply_count");
	}	

	public function step10()
	{
		// privacy options
		$this->executeUpgradeQuery("
			ALTER TABLE xfa_blog_entry
				ADD allow_comments tinyint NOT NULL DEFAULT 1,
				ADD allow_view_entry enum('everyone','members','followed','none','list') NOT NULL DEFAULT 'everyone',
				ADD allow_members VARCHAR(500) NULL
		");
		
		// scheduled entries
		$this->executeUpgradeQuery("
			ALTER TABLE xfa_blog_entry_scheduled
				ADD allow_comments tinyint NOT NULL DEFAULT 1,
				ADD allow_view_entry enum('everyone','members','followed','none','list') NOT NULL DEFAULT 'everyone',
				ADD allow_members VARCHAR(500) NULL
		");
		
		// alter the tables to register last entry
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog ADD last_entry_id INT NULL AFTER last_entry");
		
		// update the last entry for all blogs
		$this->executeUpgradeQuery("UPDATE xfa_blog SET last_entry_id = (SELECT max(entry_id) FROM xfa_blog_entry where xfa_blog_entry.user_id = xfa_blog.user_id AND message_state='visible')");
	}
	
	public function step11()
	{
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog_category ADD INDEX id_user (user_id)");
	}
	
	
	public function step12()
	{
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog_entry ADD INDEX idx_post_date (post_date)");
		$this->executeUpgradeQuery("ALTER TABLE xf_user_privacy ADD INDEX idx_blog (allow_view_blog)");
	}
	
	public function step13()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xfa_blog
				ADD comment_count INT NOT NULL DEFAULT 0 AFTER entry_count,
				ADD last_comment INT NULL AFTER last_entry_id,
				ADD last_comment_id INT NULL AFTER last_comment
		");
		
		/* @var $deferred XenForo_Model_Deferred */
		$deferred = XenForo_Model::create('XenForo_Model_Deferred');
		$deferred->defer('XfAddOns_Blogs_Deferred_CommentsBlog', array());
		
		$this->executeUpgradeQuery("
			ALTER TABLE xfa_blog_category
				ADD parent_id INT NULL,
				ADD display_order INT DEFAULT 0 NOT NULL,
				ADD is_active tinyint default 1 NOT NULL
		");
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog_category ADD INDEX idx_parent (parent_id)");
		$this->executeUpgradeQuery("DROP TABLE IF EXISTS xfa_blog_category_global");
	}
	
	
	public function step14()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xfa_blog_entry
				ADD last_edit_date INT unsigned DEFAULT 0 NOT NULL,
				ADD last_edit_user_id INT unsigned DEFAULT 0 NOT NULL,
				ADD edit_count INT unsigned DEFAULT 0 NOT NULL
		");
		$this->executeUpgradeQuery("
			ALTER TABLE xfa_blog_comment
				ADD last_edit_date INT unsigned DEFAULT 0 NOT NULL,
				ADD last_edit_user_id INT unsigned DEFAULT 0 NOT NULL,
				ADD edit_count INT unsigned DEFAULT 0 NOT NULL
		");
	}
	
	public function step16()
	{
		$this->executeUpgradeQuery("ALTER TABLE xfa_blog_css CHANGE className className varchar(200) NOT NULL");
	}	

	/**
	 * Since this version, we will change from PHP serialization to a simple comma separated list of ids
	 */
	public function step17()
	{
		$db = XenForo_Application::getDb();
		try
		{
			$privateEntries = $db->fetchAll("SELECT entry_id, allow_members FROM xfa_blog_entry WHERE allow_view_entry='list'");
			foreach ($privateEntries as $entry)
			{
				if (strpos($entry['allow_members'], '{') === false)
				{
					continue;
				}
				$allowMembers = unserialize($entry['allow_members']);
				if (is_array($allowMembers))
				{
					$allowMembers = array_map('intval', $allowMembers);
					$allowMembers = array_unique($allowMembers);
					$newData = implode(',', $allowMembers);
			
					$db->query("UPDATE xfa_blog_entry SET allow_members = ? WHERE entry_id = ?",
							array($newData, $entry['entry_id']));
				}
			}
		}
		catch (Exception $ex)
		{
			XenForo_Error::logException($ex, false);
		}
		$this->executeUpgradeQuery('ALTER TABLE xfa_blog_entry CHANGE allow_members allow_members_ids varchar(500) NULL');
	}
	
	/**
	 * Since this version, we will change from PHP serialization to a simple comma separated list of ids
	 */
	public function step18()
	{	
		$this->executeUpgradeQuery('ALTER TABLE xfa_blog_entry_scheduled CHANGE allow_members allow_members_ids varchar(500) NULL');
	}
	
	/**
	 * Content type for blog for the sitemap
	 */
	public function step19()
	{
		$this->executeUpgradeQuery("REPLACE INTO xf_content_type VALUES ('xfa_blog', 'xfa_blogs', '')");
	}	
	
	/**
	 * Check the type of the entry_id column, for users upgrading from the free version
	 */
	public function checkTable()
	{
		$db = XenForo_Application::getDb();
		$structure = $db->fetchAll("DESC xfa_blog_entry");
		$isBlogAdvancedFeatures = XfAddOns_Blogs_Listener::isBlogAdvancedFeatures();
		
		foreach ($structure as $field)
		{
			if ($field['Field'] !== 'entry_id')
			{
				continue;
			}
			if ($isBlogAdvancedFeatures)
			{
				if (strtolower($field['Type']) != 'int(11)' && strtolower($field['Type']) != 'int(10) unsigned')
				{
					$this->executeUpgradeQuery("ALTER TABLE xfa_blog_entry CHANGE entry_id entry_id INT NOT NULL AUTO_INCREMENT");
				}
			}
			else
			{
				if (strtolower($field['Type']) != 'tinyint(4)' && strtolower($field['Type']) != 'tinyint(3) unsigned')
				{
					$this->executeUpgradeQuery("ALTER TABLE xfa_blog_entry CHANGE entry_id entry_id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT");
				}
			}
			break;
		}
	}

	/**
	 * Check the configuration for content types
	 */
	public function checkContentTypes()
	{
		$isBlogAdvancedFeatures = XfAddOns_Blogs_Listener::isBlogAdvancedFeatures();
		if ($isBlogAdvancedFeatures)
		{
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog', 'sitemap_handler_class', 'XfAddOns_Blogs_SitemapHandler_Blog' )");
			
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_entry', 'news_feed_handler_class', 'XfAddOns_Blogs_NewsFeedHandler_Entry')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_entry', 'like_handler_class', 'XfAddOns_Blogs_LikeHandler_Entry')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_entry', 'alert_handler_class', 'XfAddOns_Blogs_AlertHandler_Entry')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_entry',   'stats_handler_class', 'XfAddOns_Blogs_StatsHandler_Entry')");			
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_entry', 'spam_handler_class', 'XfAddOns_Blogs_SpamHandler_Entry')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_entry', 'edit_history_handler_class', 'XfAddOns_Blogs_EditHistoryHandler_Entry')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_entry',   'report_handler_class', 'XfAddOns_Blogs_ReportHandler_Entry')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_entry', 'moderator_log_handler_class', 'XfAddOns_Blogs_ModeratorLog_Entry')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_entry',   'search_handler_class', 'XfAddOns_Blogs_SearchHandler_Entry')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_entry', 'attachment_handler_class', 'XfAddOns_Blogs_AttachmentHandler_Entry')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_entry', 'sitemap_handler_class', 'XfAddOns_Blogs_SitemapHandler_Entry')");
			
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_comment', 'news_feed_handler_class', 'XfAddOns_Blogs_NewsFeedHandler_Comment')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_comment', 'like_handler_class', 'XfAddOns_Blogs_LikeHandler_Comment')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_comment', 'alert_handler_class', 'XfAddOns_Blogs_AlertHandler_Comment')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_comment', 'stats_handler_class', 'XfAddOns_Blogs_StatsHandler_Comment')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_comment', 'spam_handler_class', 'XfAddOns_Blogs_SpamHandler_Comment')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_comment', 'edit_history_handler_class', 'XfAddOns_Blogs_EditHistoryHandler_Comment')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_comment', 'report_handler_class', 'XfAddOns_Blogs_ReportHandler_Comment')");
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('xfa_blog_comment', 'moderator_log_handler_class', 'XfAddOns_Blogs_ModeratorLog_Comment')");
			
			// content handler for scheduled entries
			$this->executeUpgradeQuery("REPLACE INTO xf_content_type_field VALUES ('xfa_blog_entry_scheduled', 'attachment_handler_class', 'XfAddOns_Blogs_AttachmentHandler_Entry')");
		}
		else
		{
			$this->executeUpgradeQuery("DELETE FROM xf_content_type_field WHERE content_type='xfa_blog_entry' AND field_name='stats_handler_class'");
			$this->executeUpgradeQuery("DELETE FROM xf_content_type_field WHERE content_type='xfa_blog_entry' AND field_name='search_handler_class'");
			$this->executeUpgradeQuery("DELETE FROM xf_content_type_field WHERE content_type='xfa_blog_entry' AND field_name='spam_handler_class'");
			$this->executeUpgradeQuery("DELETE FROM xf_content_type_field WHERE content_type='xfa_blog_entry' AND field_name='edit_history_handler_class'");
			$this->executeUpgradeQuery("DELETE FROM xf_content_type_field WHERE content_type='xfa_blog_entry' AND field_name='report_handler_class'");
			$this->executeUpgradeQuery("DELETE FROM xf_content_type_field WHERE content_type='xfa_blog_comment' AND field_name='stats_handler_class'");
			$this->executeUpgradeQuery("DELETE FROM xf_content_type_field WHERE content_type='xfa_blog_comment' AND field_name='spam_handler_class'");
			$this->executeUpgradeQuery("DELETE FROM xf_content_type_field WHERE content_type='xfa_blog_comment' AND field_name='edit_history_handler_class'");
			$this->executeUpgradeQuery("DELETE FROM xf_content_type_field WHERE content_type='xfa_blog_comment' AND field_name='report_handler_class'");

			// there are no scheduled entries in the free version
			$this->executeUpgradeQuery("DELETE FROM xf_content_type_field WHERE content_type='xfa_blog_entry_scheduled'");
		}
		
		// rebuild the content type cache
		/* @var $cacheModel XenForo_Model_ContentType */
		$cacheModel = XenForo_Model::create('XenForo_Model_ContentType');
		$cacheModel->rebuildContentTypeCache();
	}
	
	
	
	
}