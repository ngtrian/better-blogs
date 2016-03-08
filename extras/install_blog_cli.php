<?php

#
# This installer is meant to be used from the command line
# Copy this to your XenForo ROOT
# Run as php ./install_blog_cli.php
#

@set_time_limit(0);
@ignore_user_abort(true);
$startTime = microtime(true);
$fileDir = dirname(__FILE__);

// check if even after this we could not locate the xenforo installation directory
if (!is_file($fileDir . '/library/config.php'))
{
	print "Could not find library/config.php, make sure this script is run from the XenForo root directory<br/>";
	exit;
}

require($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');
XenForo_Application::initialize($fileDir . '/library', $fileDir);
XenForo_Application::set('page_start_time', $startTime);
XenForo_Application::setDebugMode(true);

// installation
print "Beginning installation ...\r\n";

// run the installer for the add-on
$fileName = './library/XfAddOns/Blogs/' . XfAddOns_Blogs_Install_Version::$xmlFile;
$addOnModel = new XenForo_Model_AddOn();
$addOnModel->installAddOnXmlFromFile($fileName);

print "AddOn has been installed, please wait 5 minutes until templates finish rebuilding, that runs in a deferred task ...\r\n";

