<?php
/*
Plugin Name: WP-ShoutBox
Plugin URI: http://bluegreenblog.com/
Description: A limited from of commenting; Not specific to any particular post.
Version: 0.7 (alpha)
Author: Ryan Mack
Author URI: http://bluegreenblog.com/
*/ 

/*
	Copyright (c) 2005 Ryan Mack.  All rights reserved.
  This software is licensed under the GPL (http://www.gnu.org/copyleft/gpl.html).
*/


/*
To do:

1)  Add a moderation page to the Wordpress menu.
	a) Users should be able to edit shouts (both name and message). -- Done --
	b) Users should be able to delete shouts. --  Done --
	c) Users should be able to approve/disapprove shouts.  -- Done --
	
2)  Add logic to allow approval/disapproval of shouts. -- Done --

3)  Add some means of filtering out spam. -- Some work done.  See shout_spamkarma_blacklist().

4)  Figure out why ">.<" invalidates xhtml :P.
	
*/




// Settings

$shout_tablename = $table_prefix.'shoutbox';
$shout_allowedtags = '';  //  <- Any html tags you wish to allow



//  Constants
define("SHOUT_ALL_SHOUTS", -1);
define("SHOUT_FILTER", true);
define("SHOUT_NO_FILTER", false);



//  Functions
function shout_tableexists($tablename)
{
	global $wpdb;
	
	// Get a list of tables contained within the database.
	$result = mysql_list_tables(DB_NAME);
	if(!$result)
		return false;
		
	$rcount = mysql_num_rows($result);
	
	// Check each in list for a match.
	for ($i=0;$i<$rcount;$i++)
	    if (mysql_tablename($result, $i)==$tablename) return true;
			
	return false;
}


function shout_createtable()
{
	global $wpdb, $shout_tablename;	
	
	/* Create the CREATE query string */
	$install_query = "CREATE TABLE ".$shout_tablename." (
									`id` int(10) unsigned NOT NULL auto_increment,
									`shout_author` tinytext NOT NULL,
									`shout_date` datetime NOT NULL default '0000-00-00 00:00:00',
									`shout_author_IP` varchar(100) NOT NULL default '',
									`shout_content` text NOT NULL,
									`shout_approved` varchar(10) NOT NULL default '',
									KEY `id` (`id`))TYPE=MyISAM;";													
									
									
	/* Create the table */
	$result = $wpdb->query($install_query);
	if(mysql_error())
	{
		die("<p>Error: The shoutbox table ".$shout_tablename." could not be created.  Make sure 
		the mySQL user you entered in wp_config.php has the 'Create' privilege.</p>");
	}
}

	
function shout_getresults($count, $filter = true)
{
	global $wpdb, $shout_tablename;
	
	if($count != -1)
		$querystring = "SELECT shout_author, shout_content, shout_date FROM ".$shout_tablename." WHERE shout_approved = 'yes' ORDER BY shout_date DESC LIMIT ".$count;
	else
		$querystring = "SELECT shout_author, shout_content, shout_date FROM ".$shout_tablename." WHERE shout_approved = 'yes' ORDER BY shout_date DESC";
	
	//  Get the shouts and hide any errors
	$showerrors = $wpdb->show_errors;
	$wpdb->hide_errors();
	$results = $wpdb->get_results($querystring, ARRAY_A);
	if($showerrors) $wpdb->show_errors();
	
	
	//  Create the shout table if it didn't exist and try again
	if(mysql_error())
		if(!shout_tableexists($shout_tablename))
		{
			// Create the table
			shout_createtable();
			if(mysql_error())
			{
				$wpdb->print_error();
				return null;
			}
				
			//  Try again
			$results = $wpdb->get_results($querystring, ARRAY_A);
			if(mysql_error())
			{
				$wpdb->print_error();
				return null;
			}
		}
		else
		{
			$wpdb->print_error();
			return null;
		}
	
	
	//  Filter each of the shouts
	if($filter)
		for($i = 0; $i < count($results); $i++)
		{
			$results[$i]['shout_author'] = apply_filters('shout_author', stripslashes($results[$i]['shout_author']));
			$results[$i]['shout_content'] = apply_filters('shout_content', stripslashes($results[$i]['shout_content']));
		}
	return $results;
}


function shout_addshout()
{
	global $wpdb, $shout_tablename, $shout_author, $shout_content;
		
	//  Skip if no shouts are being added, or if the shout data is incomplete
	if(empty($shout_content) || empty($shout_author))
		return;
		
	//  Skip if the author string length > 128 characters or the message is > 1024 characters
	if((strlen($shout_author) > 128) || (strlen($shout_content) > 1024))
		return;
		
	$shoutdata = array('shout_author' => $shout_author, 'shout_content' => $shout_content, 'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'], 'shout_approved' => 'yes');
	$shoutdata = apply_filters('shout_approve', $shoutdata);

			
	// Insert
	return $wpdb->query("INSERT INTO ".$shout_tablename." (shout_author, shout_date, shout_author_IP, shout_content, shout_approved) VALUES('".$shoutdata['shout_author']."', NOW(), '".$shoutdata['REMOTE_ADDR']."', '".$shoutdata['shout_content']."', '".$shoutdata['shout_approved']."')");
}



function shout_remove_shout($id)
{
	global $wpdb, $shout_tablename;

	$querystring = "DELETE FROM ".$shout_tablename." WHERE id = ".$id;
	$wpdb->get_results($querystring);
}



function shout_update_shout($id, $shout_author, $shout_date, $shout_author_IP, $shout_content, $shout_approved)
{
	global $wpdb, $shout_tablename;

	$querystring = "UPDATE ".$shout_tablename." SET shout_author = '".$shout_author.
		"', shout_date = '".$shout_date.
		"', shout_author_IP = '".$shout_author_IP.
		"', shout_content = '".$shout_content.
		"', shout_approved = '".$shout_approved. 
		"' WHERE id = ".$id;
		
	$wpdb->get_results($querystring);
}


//  Builds the xhtml needed to display the shoutbox
function shout_box($show_count = 5, $message_label="Your Message", $author_label="Your Name", $button_text="Say it")
{
	$output = '<ul id="shouts">';
		
	$tmpShouts = shout_getresults($show_count);
	
	if(($tmpShouts != null) && (count($tmpShouts) > 0))
	{
		$shouts = array_reverse($tmpShouts, true);
		foreach($shouts as $shout)
			$output = $output.'<li><span class="shout-author-name">'.$shout['shout_author'].'</span>: '.$shout['shout_content'].'</li>';
	}
	else
		$output = $output.'<li>No shouts yet.  Add yours using the form below.</li>';
		
	$output = $output.'
		</ul>
		<form id="shout-form" method="post" action="http://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"].'">
		<div>
			<label for="shout_content">'. $message_label .'</label><br />
			<textarea name="shout_content" id="shout_content" rows="3" cols="10"></textarea><br />
			<label for="shout_author">'. $author_label .'</label><br />
			<input type="text" name="shout_author" id="shout_author" title="Shout Author" />
			<div id="shoutit-wrap"><input type="submit" name="shoutit" id="shoutit" value="'. $button_text .'" /></div>
		</div>
		</form>';
		
	return $output;		
}	
	

function shout_get_approve_options($approved)
{
	$output = '<option value="'.$approved.'" label="'.$approved.'">'.$approved.'</option>';
	if($approved == "yes")
		$output .= '<option value="no" label="no">no</option>';
	else
		$output .= '<option value="yes" label="yes">yes</option>';
		
	return $output;
}

	
function shout_admin_getshouts()
{
	global $wpdb, $shout_tablename;
	
	$querystring = "SELECT * FROM ".$shout_tablename." ORDER BY shout_date DESC";
	
	//  Get the shouts and hide any errors
	$showerrors = $wpdb->show_errors;
	$wpdb->hide_errors();
	$results = $wpdb->get_results($querystring, ARRAY_A);
	if($showerrors) $wpdb->show_errors();
	
	
	//  Create the shout table if it didn't exist and try again
	if(mysql_error())
		if(!shout_tableexists($shout_tablename))
		{
			// Create the table
			shout_createtable();
			if(mysql_error())
			{
				$wpdb->print_error();
				return null;
			}
				
			//  Try again
			$results = $wpdb->get_results($querystring, ARRAY_A);
			if(mysql_error())
			{
				$wpdb->print_error();
				return null;
			}
		}
		else
		{
			$wpdb->print_error();
			return null;
		}

	return $results;
}

function shout_moderate()
{
	if($_GET["action"] == "delete")
		shout_remove_shout($_GET["id"]);
		
	if(($_GET["action"] == "edit") && (isset($_GET["id"])))
		$editing = true;
	else
		$editing = false;
		
	if(($_GET["action"] == "save") && (isset($_POST["id"])))
	{
		shout_update_shout($_POST["id"], $_POST["shout_author"], $_POST["shout_date"], $_POST["shout_author_IP"], $_POST["shout_content"], $_POST["shout_approved"]);
	}
		
	$output .= '
<div class="wrap">
	<h2>Shoutbox Administration</h2>
	<form id="theform" name="theform" action="../../wp-admin/edit.php?page=wp-shoutbox.php&action=save" method="post">
		<div>
			<form method="get">
			<table style="width: 100%;">
				<tr><th>Name</th><th>Message</th><th>Timestamp</th><th>IP</th><th>Approved</th><th colspan="2">Edit/Delete</th></tr>';
	$results = shout_admin_getshouts();
	
	$class = "alternate";
	
	if(($results != null) && (count($results) > 0))
	{
		foreach($results as $result)
		{
			if(($editing) && ($result["id"] == $_GET["id"]))
			{
				$output .= '<tr id="'.$result["id"].'" class="'.$class.'">';
				$output .= '<td><input id="shout_author" name="shout_author" value="'.stripslashes($result["shout_author"]).'" /></td>';
				$output .= '<td><input id="shout_content" name="shout_content" value="'.stripslashes($result["shout_content"]).'" /></td>';
				$output .= '<td><input id="shout_date" name="shout_date" value="'.$result["shout_date"].'" /></td>';
				$output .= '<td><input id="shout_author_IP" name="shout_author_IP" value="'.$result["shout_author_IP"].'" /></td>';
				$output .= '<td><select id="shout_approved" name="shout_approved">'.shout_get_approve_options($result["shout_approved"]).'</select></td>';
				$output .= '<td>';
				$output .= '<input id="page" name="page" value="wp-shoutbox.php" type="hidden" />';
				$output .= '<input id="id" name="id" value="'. $result["id"] .'" type="hidden" />';
				$output .= '<a href="edit.php?page=wp-shoutbox.php" class="edit" onclick="document.theform.submit();return false;">Save</a>';
				$output .= '</td>';
				$output .= '<td><a href="edit.php?page=wp-shoutbox.php" class="delete">Cancel</a></td></tr>';
			}
			else
			{
				$output .= '<tr id="'.$result["id"].'" class="'.$class.'">';
				$output .= '<td>'.stripslashes($result["shout_author"]).'</td>';
				$output .= '<td>'.stripslashes($result["shout_content"]).'</td>';
				$output .= '<td>'.$result["shout_date"].'</td><td>'.$result["shout_author_IP"].'</td>';
				$output .= '<td>'.$result["shout_approved"].'</td>';
				$output .= '<td><a href="edit.php?page=wp-shoutbox.php&action=edit&id='.$result["id"].'" class="edit">Edit</a></td>';
				$output .= '<td><a href="edit.php?page=wp-shoutbox.php&action=delete&id='.$result["id"].'" class="delete">Delete</a></td></tr>';
			}
				
			if($class == "alternate")
				$class = "";
			else
				$class = "alternate";
		}
	}
	$output .= '
			</table>
			</form>
		</div>
	</form>
</div>';
	
	echo $output;
}

function shout_add_moderation_page()
{
	add_management_page('Shoutbox', 'Shoutbox', 5, __FILE__, 'shout_moderate');
}

	
// Get and clear vars
function shout_varstoreset($vars)
{
	$shoutvarstoreset = array('shout_author','shout_content');
	return array_merge($vars,$shoutvarstoreset);
}

	
//  Filters

function shout_strip_tags($string)
{
	global $shout_allowedtags;
	return strip_tags($string, $shout_allowedtags);
}


function shout_htmlspecialchars($string)
{
	return htmlspecialchars($string, ENT_QUOTES, strtoupper(get_settings('blog_charset')));
}
	

function shout_limitlinks($shoutdata)
{
	// No links in the author name
	if (substr_count(strtolower($shoutdata['shout_author']), "http://") > 0)
		$shoutdata['shout_approved'] = "no";
		
	// Only one link allowed in comment
	if (substr_count(strtolower($shoutdata['shout_content']), "http://") > 1)
		$shoutdata['shout_approved'] = "no";
	
	return $shoutdata;
}
	
	

//  This function uses SpamKarma's blacklist to help filter out spam.
//  Naturally, it requires that SpamKarma be installed.
function shout_spamkarma_blacklist($shoutdata)
{
	global $wpdb;
	
  //  URLs and Regexs
	$blacklist = $wpdb->get_results("SELECT regex FROM blacklist WHERE regex_type in ('url', 'auto-url', 'regex-url') AND ('".$shoutdata['shout_content']."' REGEXP regex OR '".$shoutdata['shout_author']."' REGEXP regex)");
	if ($blacklist != 0)
		$shoutdata['shout_approved'] = "no";
	
	//  IPs
	$blacklist = $wpdb->get_results("SELECT regex FROM blacklist WHERE regex_type in ('ip', 'auto-ip') and regex = '".$shoutdata['REMOTE_ADDR']."'");
	if ($blacklist != 0)
		$shoutdata['shout_approved'] = "no";
	
	
	return $shoutdata;
}

	
	
//  Actions
add_action('admin_menu', 'shout_add_moderation_page');
add_action('wp_head', 'shout_addshout', 8);
//add_action('shout_approve', 'shout_spamkarma_blacklist', 2);

//  Filters
add_filter('query_vars', 'shout_varstoreset');
add_filter('shout_author', 'shout_strip_tags', 1);
add_filter('shout_author', 'shout_htmlspecialchars', 2);
add_filter('shout_content', 'shout_strip_tags', 1);
add_filter('shout_content', 'convert_smilies', 20);
add_filter('shout_approve', 'shout_limitlinks', 1);

?>