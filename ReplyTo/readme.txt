Notes written for Vanilla 2.0.11 (and most versions before and after 2.0.11)
================================

+----------------------------------------------------------------------------------------------------+
+ Most of this plugin is self-contained. There is just one thing that                                +
+ needs to be added to the theme: the indentation classes of the comments                            +
+ in the comments lists.                                                                             +
+                                                                                                    + 
+ This is done in applications/vanilla/views/discussion/helper_functions.php                         +
+ Look around like 29, that should look like this:                                                   +
+                                                                                                    + 
+ <div class="Comment">                                                                              +
+                                                                                                    + 
+ Add the class display to this line like this:                                                      +
+                                                                                                    + 
+ <div class="Comment<?php if (!empty($Object->ReplyToClass)) echo ' '.$Object->ReplyToClass; ?>">   +
+                                                                                                    +
+ Note that you may already have helper_functions.php over-ridden in your theme.                     +
+ I would recommend moving it to your theme anyway to allow product upgrades without                 +
+ losing this customisation.                                                                         +
+                                                                                                    +
+----------------------------------------------------------------------------------------------------+

Brief Description
=================

This plugin introduces attributes to comments allowing the comments to be
organised into an hierarchy, i.e. a tree.

Buttons have been introduced on the comments screens allowing a user to reply
to a specific comment. This then adds the new comment as a child of the
existing comment.

Comments are displayed in tree order. Comments are indented according to
the depth they are in the tree. The depth is only calculated for the comments
displayed on a single screen, and not for the whole discussion, so pages with
a larger number of comments displayed will show the structure to better effect.

Known Problems
==============

This plugin has not been tested with many other plugins, so compatibility is
not well known. Some assumptions have been made throughout the code on various
ways the core vanilla product works. If these assumptions are wrong, then the
plugin may break in future vanilla versions.

After posting a reply, a redirect is performed to take the user to the new
comment. This may be an issue with embedded versions of vanilla. Once the
methods of handling AJAX forms are more fully understood, we can try coding
to avoid the redirect.

The "Reply" button can be clicked multiple times, bringing up a new comment
form each time. The button needs to be disabled, or at least to not attempt
to pull up a new form if one is already present (should be easy enough in jQuery).
Clicking the edit button more than once toggles between a form and the original
post. It would make sense to take the same approach for usability.
[FIXED 0.1.1]

When editing a comment that is nested to any depth, upon saving the comment,
the indent style disappears completely. Since the comment is updated in
isolation, we do not know what its depth of indent should be, so we will
need to save it somehow (probably at the browser) and restore the indent
classes when the updated comment is reloaed.

Occasionally when a comment is made to the end-of-discussion comment box, the
coment appears at the start of the thread instead of the end. For some reason
the tree left/right values are not rebuilt. This appears to be random and no
errors are raised in the error_log. Note: it is possibly caused by deleted
comments in the discussion leaving a "hole" that fails to register as a rebuild
being needed.
[FIXED 0.1.2]

The module delares a settings page that does not exist. There are no settings
to be configured for this plugin.
[FIXED 0.1.2]

No tidy-up of the tree structure is done when comments are deleted.
[FIXED 0.1.2]

The hope, of course, is that vanilla will one day support structured comments
right out the box. It's not hard, and it is very, very useful. Take a look at
a site like reddit.com, and imagine that without comment structure. It simply
would not work.

Report bugs to me, and I'll fix what I can. And have fun.

How it Works
============

Additional columns added to the Comments table are ParentCommentID, TreeLeft
and TreeRight. The parent comment ID is the simple one, and points to either
a parent comment (the comment it is replying to) or zero.

The sort order of the comments in a discussion follow the tree structure from
left to right. Any comments without a parent are put at the level (a zero depth)
and displayed after any comments in the tree.

The TreeLeft and TreeRight columns model the tree using the Nested Set Model,
aka the Celko tree. This model provides an incrementing counter up the left
sides of each tree node and right the right sides, counting left to right.
Although this takes some effort to rebuid when nodes are added and deleted, it
does make selection of sets of the tree and ordering extremely efficient. The 
assumption is that discussuins will not get so big that rebuilding becomes a
problem.

When comments are displayed, comparing left and right values of each comment
allows the depth to be determined. This depth gets applied to categories on
the comments, which provides the indentation to show the structure. The
style sheet can be changed to show the indentation in other ways. The structure
of the markup is not changed in any way by default, though that could be an
option, e.g. to nest lower-level comments within their parent comments. The
comments could then be formatted to appear similar to threads in this subreddit,
which clearly shows the nesting: http://www.reddit.com/r/php

The parent comment ID is not used in the tree structure formatting. It is only
required when the left/right nest set values need to be rebuilt.