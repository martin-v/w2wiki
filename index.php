<?php
define('W2APP', true);
/*
 * W2
 *
 * Copyright (C) 2007-2011 Steven Frank <http://stevenf.com/>
 *
 * Code may be re-used as long as the above copyright notice is retained.
 * See README.txt for full details.
 *
 * Written with Coda: <http://panic.com/coda/>
 *
 */

// Install PSR-4-compatible class autoloader
spl_autoload_register(function($class){
	require str_replace('\\', DIRECTORY_SEPARATOR, ltrim($class, '\\')).'.php';
});

// Get Markdown class
use Michelf\MarkdownExtra;


// User configurable options:
require_once "config.php";

// Load configured localization:
require_once 'locales/' . W2_LOCALE . '.php';

/**
 * Get translated word
 *
 * String	$label		Key for locale word
 * String	$alt_word	Alternative word
 * return	String
 */
function __( $label, $alt_word = null )
{
	global $w2_word_set;
	if( empty($w2_word_set[$label]) )
	{
		return is_null($alt_word) ? $label : $alt_word;
	}
	return htmlspecialchars($w2_word_set[$label], ENT_QUOTES);
}

if ( REQUIRE_PASSWORD )
{
	ini_set('session.gc_maxlifetime', W2_SESSION_LIFETIME);
	session_set_cookie_params(W2_SESSION_LIFETIME);
	session_name(W2_SESSION_NAME);
	session_start();
}

if ( count($allowedIPs) > 0 )
{
	$ip = $_SERVER['REMOTE_ADDR'];
	$accepted = false;

	foreach ( $allowedIPs as $allowed )
	{
		if ( strncmp($allowed, $ip, strlen($allowed)) == 0 )
		{
			$accepted = true;
			break;
		}
	}

	if ( !$accepted )
	{
		print "<html><body>Access from IP address $ip is not allowed</body></html>";
		exit;
	}
}

function printHeader($title, $bodyclass="")
{
	print "<!doctype html>\n";
	print "<html lang=\"" . W2_LOCALE . "\">\n";
	print "  <head>\n";
	print "    <meta charset=\"" . W2_CHARSET . "\">\n";
	print "    <link rel=\"apple-touch-icon\" href=\"w2-icon.png\"/>\n";
	print "    <link rel=\"icon\" href=\"w2-icon.png\"/>\n";
	//print "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, minimum-scale=1.0, user-scalable=false\" />\n";
	print "    <link type=\"text/css\" rel=\"stylesheet\" href=\"" . BASE_URI . "/" . CSS_FILE ."\" />\n";
	print "    <title>$title</title>\n";
	print "  </head>\n";
	print "  <body".($bodyclass != "" ? " class=\"$bodyclass\"":"").">\n";
}


function printFooter()
{
	print "  </body>\n";
	print "</html>";
}

if ( REQUIRE_PASSWORD && !isset($_SESSION['password']) )
{
	if ( !defined('W2_PASSWORD_HASH') || W2_PASSWORD_HASH == '' )
		define('W2_PASSWORD_HASH', sha1(W2_PASSWORD));

	if ( (isset($_POST['p'])) && (sha1($_POST['p']) == W2_PASSWORD_HASH) )
		$_SESSION['password'] = W2_PASSWORD_HASH;
	else
	{
		printHeader( __('Log In'), "login");
		print "    <h1>" . __('Log In') . "</h1>\n";
		print "    <form method=\"post\">\n";
		print "      ".__('Password') . ": <input type=\"password\" name=\"p\">\n";
		print "      <input type=\"submit\" value=\"" . __('Log In') . "\">\n";
		print "    </form>\n";
		printFooter();
		exit;
	}
}

// Support functions

function descLengthSort($val_1, $val_2)
{
	$firstVal = strlen($val_1);
	$secondVal = strlen($val_2);
	return ( $firstVal > $secondVal ) ?
		-1 : ( ( $firstVal < $secondVal ) ? 1 : 0);
}

function getAllPageNames()
{
	$filenames = array();
	$dir = opendir(PAGES_PATH);
	while ( $filename = readdir($dir) )
	{
		if ( $filename[0] == "." || preg_match("/".PAGES_EXT."$/", $filename) != 1)
		{
			continue;
		}
		$filename = substr($filename, 0, -(strlen(PAGES_EXT)+1) );
		$filenames[] = $filename;
	}
	closedir($dir);
	return $filenames;
}

function fileNameForPage($page)
{
	return PAGES_PATH . "/$page." . PAGES_EXT;
}

function checkedExecute(&$html, $cmd)
{
	$returnValue = 0;
	$output = '';
	exec($cmd, $output, $returnValue);
	if ($returnValue != 0)
	{
		$html .= "<br/>Error executing command ".$cmd." (return value: ".$returnValue."): ".implode(" ", $output);
	}
	return ($returnValue == 0);
}

function gitChangeHandler($commitmsg, $html)
{
	if (!GIT_COMMIT_ENABLED)
	{
		return;
	}
	if (checkedExecute($html, "cd ".PAGES_PATH." && git add -A && git commit -m ".$commitmsg))
	{
		if (!GIT_PUSH_ENABLED)
		{
			return;
		}
		checkedExecute($html, "cd ".PAGES_PATH." && git push");
	}
}

function toHTML($inText)
{
	if ( AUTOLINK_PAGE_TITLES )
	{
		$filenames = getAllPageNames();
		uasort($filenames, "descLengthSort");
		foreach ( $filenames as $filename )
		{
			$inText = preg_replace("/(?<![\>\[\/])($filename)(?!\]\>)/im", "<a href=\"" . SELF . VIEW . "/$filename\">\\1</a>", $inText);
		}
	}

	preg_match_all(
		"/\[\[(.*?)\]\]/",
		$inText,
		$matches,
		PREG_PATTERN_ORDER
	);
	for ($i = 0; $i < count($matches[0]); $i++)
	{
		$linkedpage = $matches[1][$i];
		$linkedfilename = fileNameForPage($linkedpage);
		$exists = file_exists($linkedfilename);
		$inText = preg_replace("|\[\[".preg_quote($linkedpage)."\]\]|", "<a ".
			($exists? "" : "class=\"noexist\"")
			." href=\"" . SELF . VIEW . "/".urlencode($linkedpage)."\">$linkedpage</a>", $inText);
	}
	$inText = preg_replace("/\{\{(.*?)\}\}/", "<img src=\"" . BASE_URI . "/images/\\1\" alt=\"\\1\" />", $inText);
	$inText = preg_replace("/message:(.*?)\s/", "[<a href=\"message:\\1\">email</a>]", $inText);

	$html = MarkdownExtra::defaultTransform($inText);
	$inText = htmlentities($inText);

	return $html;
}

function sanitizeFilename($inFileName)
{
	return str_replace(array('..', '~', '/', '\\', ':'), '-', $inFileName);
}

function destroy_session()
{
	if ( isset($_COOKIE[session_name()]) )
	{
		setcookie(session_name(), '', time() - 42000, '/');
	}
	session_destroy();
	unset($_SESSION["password"]);
	unset($_SESSION);
}


// Main code

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';
$newPage = "";
$text = "";
$html = "";
if ($action === "view" || $action === "edit" || $action === "save" || $action === "rename" || $action === "delete")
{
	// Look for page name following the script name in the URL, like this:
	// http://stevenf.com/w2demo/index.php/Markdown%20Syntax
	//
	// Otherwise, get page name from 'page' request variable.

	$page = preg_match('@^/@', @$_SERVER["PATH_INFO"]) ?
		urldecode(substr($_SERVER["PATH_INFO"], 1)) : urldecode(@$_REQUEST['page']);
	$page = sanitizeFilename($page);
	if ( $page == "" )
	{
		$page = DEFAULT_PAGE;
	}
	$filename = fileNameForPage($page);
}
if ($action === "view" || $action === "edit")
{
	if ( file_exists($filename) )
	{
		$text = file_get_contents($filename);
	}
	else
	{
		$newPage = $page;
		$action = "new";
	}
}
$oldgitmsg = "";
$triedSave = false;
if ( $action == "save" )
{
	$newText = $_REQUEST['newText'];
	$isNew = $_REQUEST['isNew'];
	if ($isNew && file_exists($filename))
	{
		$html .= "<div class=\"note\">Error creating page '$page' - it already exists! Please choose a different name, or <a href=\"?action=edit&amp;page=".urlencode($page)."\">edit</a> the existing page (this discards current text!)!</div>\n";
		$action = "new";
		$text = $newText;
		$newPage = $page;
		if (GIT_COMMIT_ENABLED)
		{
			$oldgitmsg = $_REQUEST['gitmsg'];
		}
		$triedSave = true;
	}
	else
	{
		$errLevel = error_reporting(0);
		$success = file_put_contents($filename, $newText);
		error_reporting($errLevel);
		if ( $success === FALSE)
		{
			$html .= "<div class=\"note\">Error saving changes! Make sure your web server has write access to " . PAGES_PATH . "</div>\n";
			$action = ($isNew ? "new" : "edit");
			$text = $newText;
			$newPage = $page;
			if (GIT_COMMIT_ENABLED)
			{
				$oldgitmsg = $_REQUEST['gitmsg'];
			}
			$triedSave = true;
		}
		else
		{
			$html .= "<div class=\"note\">" . ($isNew ? __('Created'): __('Saved'));
			$usermsg = $_REQUEST['gitmsg'];
			$commitmsg = escapeshellarg($page . ($usermsg !== '' ?  (": ".$usermsg) : ($isNew ? " created" : " changed")));
			gitChangeHandler($commitmsg, $html);
			$html .=  "</div>\n";
			$html .= toHTML($newText);
		}
	}
}

if ( $action == "edit" || $action == "new" )
{
	$formAction = SELF . (($action == 'edit') ? "/$page" : "");
	$html .= "<form id=\"edit\" method=\"post\" action=\"$formAction\">\n";

	if ( $action == "edit" )
	{
		$html .= "<input type=\"hidden\" name=\"page\" value=\"$page\" />\n";
	}
	else
	{
		if ($newPage != "" && !$triedSave)
		{
			$html .= "<div class=\"note\">". __('Creating new page since no page with given title exists!') ;
			// check if similar page exists...
			$pageNames = getAllPageNames();
			foreach($pageNames as $page)
			{
				if (levenshtein($newPage, $page) < 3)
				{
//	print "<a href=\"" . SELF . "\">". __(DEFAULT_PAGE) . "</a>";

					$html .= "<br/><strong>Note:</strong> Found similar page <a href=\"".SELF."/".urlencode($page)."\">$page</a>. Maybe you meant to edit this instead?";
				}
			}
			$html .= "</div>\n";
		}
		$html .= "<p>" . __('Title') . ": <input id=\"title\" type=\"text\" name=\"page\" value=\"$newPage\" class=\"pagename\" /></p>\n";
	}

	$html .= "<p><textarea id=\"text\" name=\"newText\" rows=\"" . EDIT_ROWS . "\">$text</textarea></p>\n";
	if (GIT_COMMIT_ENABLED)
	{
		$html .= "<p>Message: <input type=\"text\" id=\"gitmsg\" name=\"gitmsg\" value=\"$oldgitmsg\" /></p>\n";
	}

	$html .= "<p><input type=\"hidden\" name=\"action\" value=\"save\" />\n";
	$html .= "<input type=\"hidden\" name=\"isNew\" value=\"".(($action==="new")?"true":"")."\" />\n";
	$html .= '<input id="save" type="submit" value="'. __('Save') .'" />'."\n";
	$html .= '<input id="cancel" type="button" onclick="history.go(-1);" value="'. __('Cancel') .'" />'."\n";
	$html .= "</p></form>\n";
}
else if ( $action == "logout" )
{
	destroy_session();
	header("Location: " . SELF);
	exit;
}
else if ( $action == "upload" )
{
	if ( DISABLE_UPLOADS )
	{
		$html .= '<p>' . __('Image uploading has been disabled on this installation.') . '</p>';
	}
	else
	{
		$html .= "<form id=\"upload\" method=\"post\" action=\"" . SELF . "\" enctype=\"multipart/form-data\"><p>\n";
		$html .= "<input type=\"hidden\" name=\"action\" value=\"uploaded\" />";
		$html .= "<input id=\"file\" type=\"file\" name=\"userfile\" />\n";
		$html .= '<input id="upload" type="submit" value="' . __('Upload') . '" />'."\n";
		$html .= '<input id="cancel" type="button" onclick="history.go(-1);" value="'. __('Cancel') .'" />'."\n";
		$html .= "</p></form>\n";
	}
}
else if ( $action == "uploaded" )
{
	if ( DISABLE_UPLOADS )
	{
		die('Invalid access. Uploads are disabled in the configuration.');
	}
	$dstName = sanitizeFilename($_FILES['userfile']['name']);
	$fileType = $_FILES['userfile']['type'];
	preg_match('/\.([^.]+)$/', $dstName, $matches);
	$fileExt = isset($matches[1]) ? $matches[1] : null;

	$html .= "<div class=\"note\">";
	if (in_array($fileType, explode(',', VALID_UPLOAD_TYPES)) &&
		in_array($fileExt, explode(',', VALID_UPLOAD_EXTS)))
	{
		$errLevel = error_reporting(0);
		$path = BASE_PATH . "/images/$dstName";
		if ( move_uploaded_file($_FILES['userfile']['tmp_name'], $path) === true )
		{
			$html .= "File '$dstName' uploaded";
		}
		else
		{
			$error_code = $_FILES['userfile']['error'];
			if ( $error_code === 0 ) {
				// Likely a permissions issue
				$html .= __('Upload error') .": can't write to ".$path."<br/><br/>\n".
					"Check that your permissions are set correctly.";
			} else {
				// Give generic error message
				$html .= "Upload error, error #".$error_code."<br/><br/>\n".
					"Please see <a href=\"https://www.php.net/manual/en/features.file-upload.errors.php\">here</a> for more information.<br/><br/>\n".
					"If you see this message, please <a href=\"https://github.com/codeling/w2wiki/issues\">file a bug to improve w2wiki</a>";
			}
		}

		error_reporting($errLevel);
	} else {
		$html .= __('Upload error: invalid file type');
	}
	$html .= "</div>\n";
}
else if ( $action === "rename" || $action === "delete")
{
	$actionName = ($action === "delete")?__('Delete'):__('Rename');
	$html .= "<form id=\"$action\" method=\"post\" action=\"" . SELF . "\">";
	$html .= "<p>".$actionName." $page ".
		(($action==="rename")? (__('to')." <input id=\"newPageName\" type=\"text\" name=\"newPageName\" value=\"" . htmlspecialchars($page) . "\" class=\"pagename\" />") : "?") . "</p>";
	$html .= "<p><input id=\"$action\" type=\"submit\" value=\"$actionName\">";
	$html .= "<input id=\"cancel\" type=\"button\" onclick=\"history.go(-1);\" value=\"Cancel\" />\n";
	$html .= "<input type=\"hidden\" name=\"action\" value=\"${action}d\" />";
	$html .= "<input type=\"hidden\" name=\"oldPageName\" value=\"" . htmlspecialchars($page) . "\" />";
	$html .= "</p></form>";
}
else if ( $action === "renamed" || $action === "deleted")
{
	$oldPageName = sanitizeFilename($_REQUEST['oldPageName']);
	$newPageName = ($action === "deleted") ? "": sanitizeFilename($_REQUEST['newPageName']);
	$html .= "<div class=\"note\">";
	if ($action === "deleted")
	{
		$success = unlink(fileNameForPage($oldPageName));
	}
	else
	{
		$success = rename(fileNameForPage($oldPageName), fileNameForPage($newPageName));
	}
	if ($success)
	{
		$message = ($action === "deleted") ? "Removed $oldPageName." :
			"Renamed $oldPageName to $newPageName.";
		$html .= $message;
		// Change links in all pages to point to new page
		$pagenames = getAllPageNames();
		$changedPages = array();
		foreach ($pagenames as $replacePage)
		{
			$content = file_get_contents(fileNameForPage($replacePage));
			$count = 0;
			$newContent = str_replace("[[$oldPageName]]", "[[$newPageName]]", $content, $count);
			if ($count > 0) // if something changed
			{
				$changedPages[] = $replacePage." ($count ".__('matches').")";
				file_put_contents(fileNameForPage($replacePage), $newContent);
			}
		}
		if (count($changedPages) > 0)
		{
			$html .= "<br/>\n".__('Updated links in the following pages:')."\n<ul><li>";
			$html .= implode("</li><li>", $changedPages);
			$html .= "</li></ul>";
		}
		$commitmsg = escapeshellarg($message);
		gitChangeHandler($commitmsg, $html);
		$page = $newPageName;
	}
	else
	{
		$html .= ($action === "deleted") ? __('Error deleting file'): __('Error renaming file');
		$page = $oldPageName;
	}
	$html .= "</div>\n";
}
else if ( $action == "all_name" )
{
	$pageNames = getAllPageNames();
	natcasesort($pageNames);
	$html .= "<p>".__('Total').": ".count($pageNames)." pages</p>";
	$html .= "<table>";
	foreach ($pageNames as $page)
	{
		$html .= "<tr>".
				"<td><a href=\"" . SELF . VIEW . "/".urlencode($page)."\">$page</a></td>".
				"<td width=\"20\"></td>".
				"<td><a href=\"?action=edit&amp;page=".urlencode($page)."\">". __('Edit') ."</a></td>".
			"</tr>\n";
	}
	$html .= "</table>\n";
}
else if ( $action == "all_date" )
{
	$dir = opendir(PAGES_PATH);
	$filelist = array();
	while ( $file = readdir($dir) )
	{
		if ( $file[0] == "." || preg_match("/".PAGES_EXT."$/", $file) != 1)
		{
			continue;
		}
		$filelist[preg_replace("/(.*?)\.".PAGES_EXT."/", "<a href=\"" . SELF . VIEW . "/\\1\">\\1</a>", $file)] = filemtime(PAGES_PATH . "/$file");
	}
	closedir($dir);
	arsort($filelist, SORT_NUMERIC);
	$html .= "<table>\n";
	foreach ($filelist as $key => $value)
	{
		$date_format = __('date_format', TITLE_DATE);
		$html .= "<tr><td valign=\"top\">$key</td><td width=\"20\"></td><td valign=\"top\"><nobr>"
			. date( $date_format, $value)
			. "</nobr></td></tr>\n";
	}
	$html .= "</table>\n";
}
else if ( $action == "search" )
{
	$matches = 0;
	$q = $_REQUEST['q'];
	$html .= "    <h1>Search: $q</h1>\n".
		"    <ul>\n";

	if ( trim($q) != "" )
	{
		$pagenames = getAllPageNames();
		foreach($pagenames as $searchPage)
		{
			$text = file_get_contents(fileNameForPage($searchPage));
			if ( preg_match("/{$q}/i", $text) || preg_match("/{$q}/i", $searchPage) )
			{
				++$matches;
				$html .= "<li><a href=\"".SELF.VIEW."/$searchPage\">$searchPage</a></li>\n";
			}
		}

		closedir($dir);
	}

	$html .= "    </ul>\n";
	$html .= "    <p>$matches matched</p>\n";
}
else
{
	$html .= empty($text) ? '' : toHTML($text);
}

$datetime = '';

if ( ($action == "all_name") || ($action == "all_date"))
{
	$title = __("All");
}
else if ( $action == "upload" )
{
	$title = __("Upload");
}
else if ( $action == "new" )
{
	$title = __("New");
}
else if ( $action == "search" )
{
	$title = __("Search");
}
else
{
	$title = $page;
	$date_format = __('date_format', TITLE_DATE);
	if ( $date_format )
	{
		$datetime = "<span class=\"titledate\">" . date($date_format, @filemtime($filename)) . "</span>";
	}
}

// Disable caching on the client (the iPhone is pretty agressive about this
// and it can cause problems with the editing function)

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
printHeader($title);
print "    <div class=\"titlebar\">$title <span style=\"font-weight: normal;\">$datetime</span>";
if ($action === 'view' || $action == 'rename' || $action == 'delete' || $action == 'save' || $action === 'edit')
{
	$pageActions = array('edit', 'delete', 'rename');
	$pageActionNames = array(__('Edit'), __('Delete'), __('Rename'));
	for ($i = 0; $i < count($pageActions); $i++ )
	{
		if ($action != $pageActions[$i])
		{
			print "      <a href=\"".SELF."?action=".$pageActions[$i].
				"&amp;page=".urlencode($page)."\">".$pageActionNames[$i]."</a>\n";
		}
	}
}
print "    </div>\n";
print "    <div class=\"toolbar\">\n";
print "      <a href=\"" . SELF . "\">". __(DEFAULT_PAGE) . "</a>\n";
print "      <a href=\"" . SELF . "?action=all_name\">". __('All') ."</a>\n";
print "      <a href=\"" . SELF . "?action=all_date\">". __('Recent') ."</a>\n";
print "      <a href=\"" . SELF . "?action=new\">". __('New') ."</a>\n";
if ( !DISABLE_UPLOADS )
{
	print "      <a href=\"" . SELF . VIEW . "?action=upload\">". __('Upload') ."</a>\n";
}
if ( REQUIRE_PASSWORD )
{
	print "      <a href=\"" . SELF . "?action=logout\">". __('Log out') . "</a>";
}
print "      <form method=\"post\" action=\"" . SELF . "?action=search\">\n";
print "        <input class=\"search\" placeholder=\"". __('Search') ."\" size=\"20\" id=\"search\" type=\"text\" name=\"q\" />\n      </form>\n";
print "    </div>\n";
print "    <div class=\"main\">\n\n";
print "$html\n";
print "    </div>\n";
printFooter();
