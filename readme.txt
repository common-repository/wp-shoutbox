=== WP-ShoutBox ===
Tags: Alternative Commenting
Contributors: Ryan Mack


!!  This is an early (alpha) version of this software and may contain bugs/defects.  Use at your own risk.  !!



== WHAT IS WP-SHOUTBOX? ==

wp-shoutbox is a Wordpress plug-in that allows visitors to leave messages to the site owner or one another.  It differs from the Wordpress commenting system in that the messages are not associated with a particular post or page, and is much simpler, allowing users to leave only a name and a message.  This plug-in should work with Wordpress version 1.5 and later (probably).  There is a tag for Wordpress version 1.2.2, but development for that version of Wordpress has stopped. 



== INSTALLATION ==

To install wp-shoutbox follow the steps below:

1. Upload wp-shoutbox.php to your wp-content/plugins folder.
2. Activate the plug-in using the ‘plugins’ section of your Wordpress installation.
3. Add:

<?php
if(function_exists(shout_box))
	echo shout_box(5); 
else
	echo '<p>The Shout Box is currently disabled</p>';
?> 

where you'd like the shout box to appear in your html source, where '5' is the maximum number of shouts to display.  There are other display options.  See the source for details.



== NEW FOR VERSION 0.2 ==

* A new column has been added to the shout box table.  You'll need to either drop the table or add the column manually if you've run the install script from version 0.1.  The following should do the trick if you have access to phpMyAdmin or similar tool:

ALTER TABLE `wp_shoutbox` ADD `shout_approved` varchar(10) NOT NULL default 'yes' AFTER `shout_content`

where wp_shoutbox is the name of the shout box table (wp_shoutbox is the default).


* The install script has been removed.  The code for creating the table has been incorporated into the plug-in itself.  If the shout box table is not detected, it is created.


* A limit on the number of occurances 'http://' can occur has been added.  Zero are
allowed in the name field and only one is allowed in the message.  These numbers can be changed in the shout_limitlinks function.



== To Do List ==

1. Add a moderation page to the Wordpress menu.
	a. Users should be able to edit shouts (both name and message).
	b. Users should be able to delete shouts.
	c. Users should be able to approve/disapprove shouts.
	
2. Add logic to allow approval/disapproval of shouts.

3. Add some means of filtering out spam.



== SAMPLE OUTPUT ==

Below is an example of the output produced by the shout_box() function:

<ul id="shouts">
	<li><span class="shout-author-name">Ryan</span>: Test shout 1</li>
	<li><span class="shout-author-name">Ryan</span>: Test shout 2</li>
	<li><span class="shout-author-name">Ryan</span>: Test shout 3</li>
	<li><span class="shout-author-name">Ryan</span>: Test shout 4</li>
	<li><span class="shout-author-name">Ryan</span>: Test shout 5</li>
</ul>
<form id="shout-form" method="post" action="">
	<label for="shout_content">Your Message</label><br />
	<textarea name="shout_content" id="shout_content"></textarea><br />
	<label for="shout_author">Your Name</label><br />
	<input type="text" name="shout_author" id="shout_author" title="Shout Author" />
	<input type="submit" name="shoutit" id="shoutit" value="Say it" />
</form>
