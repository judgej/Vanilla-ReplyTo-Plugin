Notes written for Vanilla 2.0.11 (and most versions before and after 2.0.11)
================================

+----------------------------------------------------------------------------------------------------+
+ NOTE: this has changed on version 0.1.4, The old technique will work, but this is simpler and will +
+ be better prepared for changes made to Vanilla to allow the class to be modified by a plugin.      +
+                                                                                                    + 
+ Most of this plugin is self-contained. There is just one thing that                                +
+ needs to be added to the theme: the indentation classes of the comments                            +
+ in the comments lists.                                                                             +
+                                                                                                    + 
+ This is done in applications/vanilla/views/discussion/helper_functions.php                         +
+ Look around like 26 for the 'BeforeCommentDisplay' event call, that should look like this:         +
+                                                                                                    + 
+ $Sender->FireEvent('BeforeCommentDisplay');                                                        +
+                                                                                                    + 
+ Add the following line just before it, allowing the event handler to change the CSS class of       +
+ the coment:                                                                                        +
+                                                                                                    + 
+ $Sender->CssClassComment =& $CssClass;                                                             +
+                                                                                                    +
+ Note that you may already have helper_functions.php over-ridden in your theme. If not              +
+ I would recommend copying it to your theme anyway to allow product upgrades without                +
+ losing this customisation. The theme path to copy the templat to is:                               +
+ themes/{theme-name}/vanilla/views/discussion/helper_functions.php                                  +
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
to avoid the redirect. If we are to use AJAX only, then the front end would
need to tell the new comment what its depth is as it is submitted, (and the
back end can supply the relevant classes).

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
classes when the updated comment is reloaded. Another approach to the problem
could be to add a "depth" field to the edit comment form (using AJAX) and 
then capture that on the server and serve back the depth css classes as
appropriate.
Update: after experimenting a little more, I realise that the list item
that a comment is in, *is* replaced when that comment is submitted. At least,
when the CSS classes are applied to the list item, they are lost on display
of the edited comment.

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

It has been pointed out that this module uses a custom extension to the SQL
driver to support the SetCase() method. This needs to be somehow brought
into the module, perhaps just by creating ad-hoc SQL and injecting it into
the query to be run.
[FIXED 0.1.3]

It is unlikely comments will be indented correctly if pages of comments
are delivered by AJAX. Each new page fetched will be treated indepentantly
and will not continue any depth indenting that had been fetched prior to it.
Personally I avoid AJAX page exending anyway, since it removes the ability
for a user to bookmark the page or come back to the same page to view
the comments (the AJAX fetches would be lost on returning). I prefer stateless
pages in general ease of navigation.
[Possibly fixed 0.1.5 - nested levels now carry across pages]

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
left to right. Any comments without a parent are put at the top level (a zero 
depth) and displayed amoungst the comment trees, in posted date order.

The TreeLeft and TreeRight columns model the tree using the Nested Set Model,
aka the Celko tree. This model provides an incrementing counter up the left
sides of each tree branch and down the right sides, counting left to right.
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

Request for Changes
===================

A request has been made for adminisration functions to move comments around
on the hierachy. I probably won't implement this myself, but welcome any
code to include if anyone else would like to tacklle it.

The back-end functionality would be straight-forward: set the new ParentCommentID
on a comment (with zero to put a comment at the top level, straigh off the
discussion), then call up ReplyTo->RebuildLeftRight($DiscussionID) to rebuild the
nested link tree model on that discussion. That should be all that is needed,
with your imagination on what can be provided at the front end.

The front end and back end ought to prevent a comment being added to another that
was created later than it. Out-of-order comments may look a bit strange if
they are allowed, though technically shouldnot cause any issues with the tree
functionality.

Other potential improvements could be the ability to limit the maximum depth
of comments in any discussion.

An addition I would like to see are links where a tree is split acorss several
pages. Links at the break points could take the user to the next and previous
comments in the tree that are displayed on other pages.
[Done 0.1.4]

At the moment this plugin passes lists of CSS classes to the comment display
to be used to format any indenting. It would be useful also to pass in pure
data (indent level etc) so that the theme can use that data as it sees fit
to modify the markup.
Update: data available in the comments are ParentCommentID, the TreeLeft and
TreeRight (you can use this to tell if there are any child comments, and how
many there are), and ReplyToDepth (which is the depth calculated for the current
page).

Being able to turn this plugin on and off for specific categories would be good,
as well as a master switch to enable and disable that feature too.
