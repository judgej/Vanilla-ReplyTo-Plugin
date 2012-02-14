Notes written for Vanilla 2.0.11 (and most versions before and after 2.0.11)
================================

Most of this plugin is self-contained. There is just one thing that 
needs to be added to the theme: the indentation classes of the comments
in the comments lists.

This is done in applications/vanilla/views/discussion/helper_functions.php
Look around like 29, that should look like this:

<div class="Comment">

Add the class display to this line like this:

<div class="Comment<?php if (!empty($Object->ReplyToClass)) echo ' '.$Object->ReplyToClass; ?>">

Note that you may already have helper_functions.php over-ridden in your theme.
I would recommend moving it to your theme anyway to allow product upgrades without
losing this customisation.

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

The hope, of course, is that vanilla will one day support structured comments
right out the box. It's not hard, and it is very, very useful. Take a look at
a site like reddit.com, and imagine that without comment structure. It simply
would not work.

Report bugs to me, and I'll fix what I can. And have fun.
