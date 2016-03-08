<?php

@set_time_limit(0);
@ignore_user_abort(true);
$startTime = microtime(true);

// go to the XenForo root directory, in the event we are in a subdirectory
$fileDir = getcwd();
while (!is_file($fileDir . '/library/config.php'))
{
	if ($fileDir == '/')
	{
		break;
	}
	$fileDir = dirname($fileDir);
}

// if the file was not found anyway, let's see if we can find it from the document root
if (!is_file($fileDir . '/library/config.php'))
{
	$fileDir = $_SERVER["DOCUMENT_ROOT"];
}

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

// setup the session
$request = new Zend_Controller_Request_Http();
XenForo_Session::startPublicSession($request);

$visitor = XenForo_Visitor::getInstance();

if (!$visitor['is_admin'])
{
	print "You are not an admin. Please login first";
	exit;
}

if (!isset($_REQUEST['confirm']))
{
	print "This will delete ALL data for blogs. This operation is not recoverable. Do a backup first.<br/>";
	print "<b>This cannot be undone</b><br/>";
	print "<a href=\"uninstall_blog.php?confirm=1\">Click here to uninstall blogs</a><br/>";
	exit;
}

// little whitespace
print "<br />";

// add-on id
$addOnId = 'xfa_blogs';

// let's see if the datawriter will behave
try
{
	print "Attempting to uninstall using the regular datawriter ... "; flush();
	$addOnModel = new XenForo_Model_AddOn();
	$addOnData = $addOnModel->getAddOnById($addOnId);
	if (!empty($addOnData))
	{
		$dw = new XenForo_DataWriter_AddOn();
		$dw->setExistingData($addOnData);
		$dw->delete();
	}	
	print "ok<br/>"; flush();
}
catch (Exception $ex)
{
	XenForo_Error::logException($ex, false);
	print "failed<br/>"; flush();
}

// If the DW didn't get it, let's go ahead manually uninstall

$installer = new XfAddOns_Blogs_Install_Install();
print "Removing all blog tables and data .."; flush();
$installer->doUninstall();
print "ok<br/>"; flush();

print "Forcing removal of options, phrases, and other data .."; flush();
$installer->uninstallForce();
print "ok<br/>"; flush();

// let's get the model class and rebuild the data. Most likely this was already run by the datawriter, but run again
print "Deleting master data .."; flush();
$addOnModel->deleteAddOnMasterData($addOnId);
print "ok<br/>"; flush();

print "Rebuilding caches .."; flush();
$addOnModel->rebuildAddOnCaches();
print "ok<br/>"; flush();

print "Rebuilding add-on cache .."; flush();
$addOnModel->rebuildActiveAddOnCache();
print "ok<br/>"; flush();


print "<br />";
print "Blog has been uninstalled";
flush();