<?php
/*
Extension Name: ReplyTo
Extension Url: http://lussumo.com/addons/index.php
Description: Allows users to reply to specific comments.
Version: 0.1.8
Author: Jason Judge
Author Url: http://www.consil.co.uk/
*/

/**
 * @package Vanilla extension
 * @copyright (C) 2010, 2011 Jason Judge
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @link http://www.consil.co.uk/
 * @subpackage ReplyTo
 * @author Jason Judge <jason.judge@consil.co.uk>
 * @date $Date: $
 * @revision $Revision: $
 */

// Define the plugin:
$PluginInfo['ReplyTo'] = array(
   'Name' => 'ReplyTo',
   'Description' => 'Allows a reply to be made to a specific comment, supporting nested comments.',
   'Version' => '0.1.8',
   'RequiredApplications' => array('Vanilla' => '2.0.9'),
   'RequiredTheme' => FALSE,
   'RequiredPlugins' => FALSE,
   'HasLocale' => FALSE,
   'RegisterPermissions' => array(),
   'SettingsUrl' => '/dashboard/plugin/replyto',
   'SettingsPermission' => 'Garden.AdminUser.Only',
   'Author' => 'Jason Judge',
   'AuthorEmail' => 'jason.judge@consil.co.uk',
   'AuthorUrl' => 'http://www.consil.co.uk',
);

class ReplyTo extends Gdn_Plugin {

   // Set up the plugin.

   public function Setup() {
      $Structure = Gdn::Structure();

      // Add parent pointer and left/right tree structure to the comments.
      $Structure
         ->Table('Comment')
         ->Column('ParentCommentID', 'int', 0)
         ->Column('TreeLeft', 'int', 0)
         ->Column('TreeRight', 'int', 0)
         ->Set(FALSE, FALSE);

      SaveToConfig('Plugins.ReplyTo.Enabled', TRUE);
   }

   // Disable the plugin.

   public function OnDisable() {
      SaveToConfig('Plugins.ReplyTo.Enabled', FALSE);
   }

   // Set JS and CSS for this plugin.

   protected function PrepareController($Controller) {
      // CHECKME: where does IsEnabled come from?
      if (!$this->IsEnabled()) return;
      
      $Controller->AddCssFile($this->GetResource('design/replyto.css', FALSE, FALSE));
      $Controller->AddJsFile($this->GetResource('js/replyto.js', FALSE, FALSE));
   }

   // Add the resources used by the front end on the discussion and post (AJAX) controller pages.

   public function DiscussionController_Render_Before($Sender) {
      $this->PrepareController($Sender);
   }
   public function PostController_Render_Before($Sender) {
      $this->PrepareController($Sender);
   }


   // Return the number of comments on a discussion.
   // Used to check whether left/right numbers need refreshing.
   // TODO: belongs in a model.

   public function CommentCount($DiscussionID) {
      $m = new CommentModel();
      return $m->GetCount($DiscussionID);
   }

   // Get the highest 'right' value in a set of discussion comments.
   // TODO: belongs in a model.

   public function MaxRight($DiscussionID) {
      $SQL = Gdn::SQL();

      // CHECKME: what happens when there are no comments on the discussion?
      $MaxRight = $SQL->Select('TreeRight', 'max', 'MaxRight')
         ->From('Comment')
         ->Where('DiscussionID', $DiscussionID)
         ->Get()
         ->FirstRow()
         ->MaxRight;

      return (!empty($MaxRight) ? $MaxRight : 0);
   }

   // Rebuild the left/right values for a discussion.
   // We are effectively creating a nested sets model from a simple adjacency model.
   // There are ways of doing this using temporary tables, but we will use PHP arrays
   // To build the tree before using it to update the discussion comments.

   public function TreeWalk($ParentID, $reset_parents = NULL, $reset_index = NULL) {
      static $index = 1;
      static $parents = array();

      $return = array();

      if (isset($reset_index)) $index = $reset_index;
      if (isset($reset_parents)) $parents = $reset_parents;

      foreach($parents[$ParentID] as $parent) {
         $return[$parent] = array();

         $index += 1;
         $left = $index;

         // Sub-comments
         if (isset($parents[$parent])) $sub = $this->TreeWalk($parent); else $sub = array();

         $index += 1;
         $right = $index;

         $return[$parent] = array('left' => $left, 'right' => $right);

         if (!empty($sub)) $return = $return + $sub;
      }

      // Consume the parent list now we have added it to the result.
      unset($parents[$ParentID]);

      // If this is the outer loop and we still have unconsumed parent lists, then tag them onto the end.
      // We want to make sure every comment in the discussion gets added to the tree somewhere.
      if (isset($reset_parents) && !empty($parents)) {
         while(!empty($parents)) {
            $next_parent_id = reset(array_keys($parents));
            $return = $return + $this->TreeWalk($next_parent_id);
         }
      }

      return $return;
   }

   public function RebuildLeftRight($DiscussionID) {
      // Get all the comments for the discussion.
      // Order by parent and then creation date.
      $SQL = Gdn::SQL();

      $Data = $SQL->Select('CommentID')->Select('ParentCommentID')
         ->From('Comment')
         ->Where('DiscussionID', $DiscussionID)
         ->OrderBy('ParentCommentID', 'asc')
         ->OrderBy('DateInserted', 'asc')
         ->Get();

      $parents = array();

      while ($Row = $Data->NextRow()) {
         if (empty($parents[$Row->ParentCommentID])) $parents[$Row->ParentCommentID] = array();
         $parents[$Row->ParentCommentID][] = $Row->CommentID;
      }

      // Now we have the comments, grouped into parents.
      // Turn it into a tree.
      // Keys are the comment IDs, and values are the comment IDs or an array of sub-comments.

      $tree = $this->TreeWalk(0, $parents, 0);

      // Now use this tree to update the left/right values of the comments.
      // TODO: get TreeWalk to return data in this format so we don't need to
      // manipulate it any further.
      $LeftData = array();
      $RightData = array();
      foreach($tree as $key => $value) {
          $LeftData[$key] = $value['left'];
          $RightData[$key] = $value['right'];
      }
      $LeftData[''] = 'TreeLeft';
      $RightData[''] = 'TreeRight';

      $Update = $SQL->Update('Comment')
         //->SetCase('TreeLeft', 'CommentID', $LeftData)
         //->SetCase('TreeRight', 'CommentID', $RightData)
         ->Set('TreeLeft', $this->SetCase('TreeLeft', 'CommentID', $LeftData), FALSE)
         ->Set('TreeRight', $this->SetCase('TreeRight', 'CommentID', $RightData), FALSE)
         ->Where('DiscussionID', $DiscussionID)
         ->Put();

   }

   // Create a "case A when B then C [when D then E ...] [else F];" sql fragment
   // to be used with "SET" statement. "A" is $Field and $Options define the
   // remainder in the same format as the $GDN::SQL()->SelectCase() method.
   // Returns a string.
   // Note no escaping or quoting is done here, so only use with numeric values for now.

   public function SetCase($SetField, $Field, $Options) {
      $CaseOptions = 'case ' . $Field;

      if (empty($Options)) {
         // For some reason there are no options, so just return the field we are updating.
         return $SetField;
      } else {
         foreach ($Options as $Key => $Val) {
            if ($Key == '') {
               $Default = $Val;
            } else {
               $CaseOptions .= ' when ' . $Key . ' then ' . $Val;
            }
         }
      }

      if (isset($Default)) $CaseOptions .= ' else ' . $Default;

      $CaseOptions .= ' end';

      return $CaseOptions;
   }

   // Get the tree-related attributes of a comment.

   public function GetComment($CommentID) {
      $SQL = Gdn::SQL();

      return $SQL->Select(array('DiscussionID', 'CommentID', 'ParentCommentID', 'TreeLeft', 'TreeRight'))
         ->From('Comment')
         ->Where('CommentID', $CommentID)
         ->Get()
         ->FirstRow();
   }

   // Replies will always be added to the same part of the tree, i.e. as a last sibling
   // of an existing comment. For example, if replying to comment X, which already has
   // three replies to it, this reply will become the forth child comment of comment X.
   // The ordering replies on the date posted, so if anything starts messing around with
   // those dates, then the ordering of siblings could change.
   // This function opens a gap in the left/right values in the comment tree, then returns
   // the new left, right, and parent ID values as an array.
   // If the left/right values are not contiguous before it starts, then it will rebuild
   // the left/right values for the complete discussion.
   // Note also that the base left/right is in the discussion, so a reply direct to the discussion
   // will open the gap there.
   // The CommentID passed in is the comment we wish to reply to.
   // If $InsertCommentID is set, then that is updated as the comment that is being inserted
   // into the tree..

   public function InsertPrep($DiscussionID, $CommentID, $InsertCommentID = 0) {
      // Get the count of comments in the discussion.
      $CommentCount = $this->CommentCount($DiscussionID);

      // Get the current max right value.
      $MaxRight = $this->MaxRight($DiscussionID);

      // If the base comment left/right does not match the comment count (excluding the
      // new comment we have just inserted), then rebuild the left/right values for the 
      // entire discussion.
      if ($MaxRight != ((2 * $CommentCount) - 2)) {
         // Rebuild. The 'right' value of the right-most comment should be twice the total number
         // of comments, since with this Nested Sets tree model we go up the left
         // and back down the right.
         $this->RebuildLeftRight($DiscussionID);

         // Since we rebuilt the whole tree, there is no point doing the gap-opening stuff 
         // that follows.
         // Do not return the left/right values, since they have already been updated.
         return;
      }

      $SQL = Gdn::SQL();

      // Now the main task: opening up a gap in the tree numbering for the new comment.
      // We want to insert as the last child of comment $CommentID.
      // A gap opnly needs to be opened up if this is a reply to an existing comment.

      if ($CommentID > 0) {
         // Get the right value of the new comment parent.
         // This and everything above it will be moved up two places.
         // The left of the new comment will be given the same value as
         // the old right value of the parent comment.
         // We could just rebuild the tree model, but this reduces the number
         // of database rows that need to be updated.

         $InsertComment = $this->GetComment($CommentID);

         // If this comment is for a different discussion, then stop now.
         if (empty($InsertComment) || $DiscussionID != $InsertComment->DiscussionID) return;

         $TreeRight = (int)$InsertComment->TreeRight;

         $Update = $SQL->Update('Comment')
            ->Where('DiscussionID', $DiscussionID)
            ->Where('TreeRight >=', $TreeRight)
            ->Set('TreeRight', 'TreeRight + 2', FALSE)
            ->Put();

         $Update = $SQL->Update('Comment')
            ->Where('DiscussionID', $DiscussionID)
            ->Where('TreeLeft >=', $TreeRight)
            ->Set('TreeLeft', 'TreeLeft + 2', FALSE)
            ->Put();

         // Return the left/right/parent information necessary to add to the comment.
         // The new item 'left' replaces the parent 'right', and that shifts the parent 'right' up by two.
         $TreeLeft = $TreeRight;
      } else {
         // There is no parent, so tag the comment on to the end (far right) of the nested set
         // model (left is max right+1 and right is one more again).
         $TreeLeft = $MaxRight + 1;
      }

      if ($InsertCommentID > 0) {
         $Update = $SQL->Update('Comment')
            ->Where('CommentID', $InsertCommentID)
            ->Set('TreeLeft', $TreeLeft)
            ->Set('TreeRight', $TreeLeft + 1)
            ->Put();
      }

      return array(
         'ParentCommentID' => $CommentID, 
         'TreeLeft' => $TreeLeft, 
         'TreeRight' => $TreeLeft + 1);
   }

   // Set the tree order of all comments in the model as soon as it is instantiated.
   // By ensuring the ordering is set here, redirects after a comment has been added is
   // handled better.
   // It is not clear if there are other plugins that may also wish to change the ordering.

   public function CommentModel_AfterConstruct_Handler(&$Sender) {
      $Sender->OrderBy(array('TreeLeft asc', 'DateInserted asc'));
   }

   public function PostController_AfterCommentSave_Handler(&$Sender) {
      // Only if inserting a new comment, we want to insert it into the tree.
      // Two things seem to indicate we are inserting new: the CommentID is empty and
      // the "Editing" flag is empty. We will check both to make sure.

      if (empty($Sender->EventArguments['Editing']) || empty($Sender->EventArguments['CommentID'])) {
         // Open up a space in the tree for this comment..
         $Details = $this->InsertPrep(
            $Sender->EventArguments['Comment']->DiscussionID,
            $Sender->EventArguments['Comment']->ParentCommentID,
            $Sender->EventArguments['Comment']->CommentID
         );
      }
   }

   // On deleting a comment, close the left-right gap.
   // If the comment has any children, then they need moving so that they are
   // not orphaned.
   // The default display will still work with gaps not closed and orthaned child
   // comments, but a broken tree becomes less flexible in other things we may
   // wish to do with it. For example, the different between a TreeLeft and TreeRight
   // value for a comment, when divided by two, tells you how any descendants a
   // comment has. However, if those values cannot be trusted to be correct and
   // contigous across the tree, then you need to go count the actual comments.

   public function CommentModel_DeleteComment_Handler(&$Sender) {
      if (empty($Sender->EventArguments['CommentID'])) return;

      $CommentID = $Sender->EventArguments['CommentID'];

      $Comment = $this->GetComment($CommentID);

      if (empty($Comment)) return;

      $SQL = Gdn::SQL();

      // Left and right will be continuous if there are no children.
      if ($Comment->TreeRight != $Comment->TreeLeft + 1) {
         // Child comments involved - move them first.
         // Move them to the parent of the comment we are about to delete.
         $Update = $SQL->Update('Comment')
            ->Where('ParentCommentID', $CommentID)
            ->Set('ParentCommentID', $Comment->ParentCommentID)
            ->Put();

         // Rebuild the tree, since lots of left/rights could need changing.
         $this->RebuildLeftRight($Comment->DiscussionID);

         // Fetch the comment to be deleted again, just in case (in theory it will not
         // have changed, as the children will be inserted after it).
         $Comment = $this->GetComment($CommentID);

         // If left/right not continguous still, then bail out (something has gone wrong).
         if ($Comment->TreeRight != $Comment->TreeLeft + 1) return;
      }

      // Move all left and right values above the right value of the comment
      // to be deleted, down two places to close up the gap.
      $SQL->Update('Comment')
         ->Where('DiscussionID', $Comment->DiscussionID)
         ->Where('TreeLeft >', $Comment->TreeRight)
         ->Set('TreeLeft', 'TreeLeft - 2', FALSE)
         ->Put();

      $SQL->Update('Comment')
         ->Where('DiscussionID', $Comment->DiscussionID)
         ->Where('TreeRight >', $Comment->TreeRight)
         ->Set('TreeRight', 'TreeRight - 2', FALSE)
         ->Put();
   }

   // Before the comments are rendered, go through them and work out their (relative)
   // depth and give them classes.

   public function DiscussionController_BeforeDiscussionRender_Handler(&$Sender) {
      // Get a list of all comment IDs in this set, i.e. displayed on this page.
      $CommentIDs = array();
      $MaxTreeRight = 1;
      $DepthCounts = array();

      foreach($Sender->Data['Comments'] as $Comment) {
         $CommentIDs[$Comment->CommentID] = $Comment->CommentID;
         if ($Comment->TreeRight > $MaxTreeRight) $MaxTreeRight = $Comment->TreeRight + 1;
      }

      // Find all comments that have parents on a previous page.
      $NoParents = array();
      foreach($Sender->Data['Comments'] as $Comment) {
         if (!empty($Comment->ParentCommentID) && empty($CommentIDs[$Comment->ParentCommentID])) {
            $NoParents[] = $Comment->CommentID;
         }
      }
      if (!empty($NoParents)) $DepthCounts = $this->CountAncestors($NoParents);

      // Loop for each comment and build a depth and give them some categories.
      $depthstack = array();

      foreach($Sender->Data['Comments'] as $Comment) {
         // If we hit a comment without a parent, then treat it as level 0.
         if (empty($Comment->ParentCommentID)) $depthstack = array();

         // If this comment has a parent that is not on this page, then provide a link
         // back to the parent.
         if (!empty($Comment->ParentCommentID) && empty($CommentIDs[$Comment->ParentCommentID])) {
            $Comment->ReplyToParentURL = Gdn::Request()->Url(
               'discussion/comment/' . $Comment->ParentCommentID . '/#Comment_' . $Comment->ParentCommentID,
               TRUE
            );

            // Set the depth as one more than its number of ancestors.
            if (isset($DepthCounts[$Comment->CommentID])) {
               // Fill out the depth array just to fool the algorithm.
               // We probably could do it more efficiently with an offset, but then have two
               // variables to account for the depth.
               $depthstack = array_pad(array(), $DepthCounts[$Comment->CommentID], $MaxTreeRight);
            }
         }

         // Calculate the depth of the comment (within the context of the selected comments, i.e. not
         // in absolute terms.
         while (!empty($depthstack) && end($depthstack) < $Comment->TreeRight) {
            array_pop($depthstack);
         }

         $depth = count($depthstack);
         $depthstack[] = $Comment->TreeRight;

         $Comment->ReplyToDepth = $depth;

         // TODO: if a tree is cut short or starts half-way through, then links to the rest 
         // of the replies would be useful, e.g. "replies continue..." and "this is a reply to...".
         // Links to individual comments are possible, and would be ideal.

         // Set the class of the comment according to depth.
         $Comment->ReplyToClass = $this->DepthClasses($depth);
      }
   }

   // Return comment classes for a specified depth.

   public function DepthClasses($depth) {
      $Prefix = 'ReplyToDepth';

      $Class = $Prefix . '-' . $depth;

      // Add some further classes for blocks of each 5 depth levels, so limits can
      // be set on the way depth is formatted.
      for($i = 1; $i <= 100; $i += 5) {
         if ($depth >= $i) $Class .= ' ' . $Prefix . '-' . $i . 'plus';
         else break;
      }

      // This is the set of classes that is applied to the comment in the output view.
      return trim($Class);
   }

   // Count ancestors for a range of comments.
   // Will accept a single comment ID or an array of comment IDs.
   // Returns an array of comment IDs and counts for each.
   // No zero counts will be returned, so an empty array will be returned
   // if none of the supplied comment IDs have ancestor comments.

   public function CountAncestors($CommentIDs = array()) {
      // Make sure the input is an array.
      // TODO: validate them as numeric.
      if (!is_array($CommentIDs)) $CommentIDs = array($CommentIDs);

      $SQL = Gdn::SQL();

      $Data = $SQL->Select('Roots.CommentID')
         ->Select('Ancs.CommentID', 'count', 'AncestorCount')
         ->From('Comment Roots')
         ->Join('Comment Ancs', 'Ancs.DiscussionID = Roots.DiscussionID'
            . ' AND Ancs.TreeLeft < Roots.TreeLeft'
            . ' AND Ancs.TreeRight > Roots.TreeRight', 'inner');

      if (!empty($CommentIDs)) {
         $Data = $Data->WhereIn('Roots.CommentID', $CommentIDs);
      }

      $Data = $Data->GroupBy('Roots.CommentID')
         ->OrderBy('Roots.CommentID', 'asc')
         ->Get();

      $Counts = array();

      while ($Count = $Data->NextRow()) $Counts[$Count->CommentID] = $Count->AncestorCount;

      return $Counts;
   }

   // Return a list of ancestor comments IDs for a given comment.
   // The count of ancestors will give the absolue depth of the comment.
   // An empty list will be returned if the comment is hanging directly off the discussion.
   // Note: don't assume the ancestors link to each other contigously. They should do,
   // but if the Nested Tree gets messed up at all - e.g. comments are removed by other
   // plugins without firing events to rebuild the tree - then there may be gaps in the links.
   // The first comment in the list will be the top level, and the last will be the
   // ancestor of the specified comment.

   public function AncestorComments($CommentID) {
      $Comments = array();

      // Get details of the comment we are starting at.
      $Comment = $this->GetComment($CommentID);

      // Comment was not found.
      if (empty($Comment)) return $Comments;

      // Comment has no ancestors or the discussion has never been written to with this
      // plugin enabled.
      if (empty($Comment->ParentCommentID)
          || empty($Comment->TreeLeft) || empty($Comment->TreeRight)
      ) return $Comments;

      $SQL = Gdn::SQL();

      // All ancestors will have a TreeLeft and TreeRight that wraps around
      // the current comment's TreeLeft.
      // Select a range of useful columns.

      $Data = $SQL->Select('DiscussionID')
         ->Select('CommentID')->Select('ParentCommentID')
         ->Select('TreeLeft')->Select('TreeRight')
         ->Select('DateInserted')
         ->From('Comment')
         ->Where('DiscussionID', $Comment->DiscussionID)
         ->Where('TreeLeft <', $Comment->TreeLeft)
         ->Where('TreeRight >', $Comment->TreeRight)
         ->OrderBy('TreeLeft', 'asc')
         ->Get();

      while ($Comment = $Data->NextRow()) $Comments[] = $Comment;

      return $Comments;
   }

   // Pop-up form allowing a comment to be created underneath any existing comment.
   // Requires the ID of the comment that is being replied to.

   public function PostController_ReplyComment_Create(&$Sender) {
      // Comment to reply to, i.e. the parent comment ID.
      $CommentID = $Sender->RequestArgs[0];

      if (is_numeric($CommentID) && $CommentID > 0) {
         $Sender->Form->SetModel($Sender->CommentModel);

         // Fetch the parent comment to check.
         $ParentComment = $Sender->CommentModel->GetID($CommentID);

         if (!empty($ParentComment)) {
            $DiscussionID = $ParentComment->DiscussionID;

            // Check whether the user is permitted to comment on this discussion.
            $Discussion = $Sender->DiscussionModel->GetID($DiscussionID);
            if (isset($Discussion->PermissionCategoryID)) {
                $Sender->Permission('Vanilla.Comments.Add', TRUE, 'Category', $Discussion->PermissionCategoryID);
            } else {
                $Sender->Permission('Vanilla.Comments.Add', TRUE, 'Category', $Discussion->CategoryID);
            }

            // Make sure the form has the parent comment ID.
            $Sender->Form->AddHidden('ParentCommentID', $CommentID);

            // Get the username of the user you are replying to, and add their name
            // to the comment body as a "mention".
            // Translate spaces in a name to plus characters. This is the convention I am
            // using for consistency with the encoding of URLs for profile pages.
            // Only put the mention string into the body if the body is empty.
            $CurrentBody = $Sender->Form->GetFormValue('Body');
            $DoInsertmention = C('ReplyTo.Mention.Insert', 0);
            if (trim($CurrentBody) == '' && !empty($DoInsertmention)) {
                $Sender->Form->SetFormValue(
                    'Body', 
                    sprintf(T('Reply to @%s: '), str_replace(' ', '+', $ParentComment->InsertName))
                );
            }

            // Set up the form for rendering or processing.
            $Sender->View = 'Comment';

            // Run Comment() in the PostController to add a comment to this discussion.
            // We also need to ensure the parent comment ID gets into the process.
            $Sender->Comment($DiscussionID);
         }
      }
   }

   // Add the option to "reply to" the comment.

   public function DiscussionController_CommentOptions_Handler(&$Sender) {
      $this->AddReplyToButton($Sender);
   }

   protected function AddReplyToButton(&$Sender) {
      if (!Gdn::Session()->UserID) return;

      if (isset($Sender->Discussion->PermissionCategoryID)) {
         $CategoryID = $Sender->Discussion->PermissionCategoryID;
      } else {
         $CategoryID = $Sender->Discussion->CategoryID;
      }

      $Session = Gdn::Session();
      $CommentID = $Sender->CurrentComment->CommentID;

      if ($Sender->EventArguments['Type'] == 'Comment') {
          // Can the user comment on this category, and is the discussion open for comments?
          if (empty($Sender->Data['Discussion']->Closed)
             && $Session->CheckPermission('Vanilla.Comments.Add', TRUE, 'Category', $CategoryID)
          ) {
             // Add the "Reply To" link on the options for the comment.
             // It sucks that we must generate HTML here and not just data for the view.
             $Sender->Options .= '<span>'.Anchor(T('Reply'), '/vanilla/post/replycomment/'.$CommentID, 'ReplyComment').'</span>';
          }
      }

      // Add a "in reply to" link if the parent is not on the current page.
      if (!empty($Sender->CurrentComment->ReplyToParentURL)) {
          $Sender->Options .= '<span>'
             . Anchor(T('In Reply To'), $Sender->CurrentComment->ReplyToParentURL, 'ReplyToParentLink')
             . '</span>';
      }
   }

   // Insert the indentation classes into the comment.
   // An addition is made to the WriteComment() function to expose the comment CssClass as
   // $Sender->CssClassComment. See readme.txt for details on how to set this up.

   public function DiscussionController_BeforeCommentDisplay_handler(&$Sender) {
      if (!isset($Sender->CssClassComment)) return;

      $Sender->CssClassComment .= (
         !empty($Sender->EventArguments['Comment']->ReplyToClass)
         ? ' ' . $Sender->EventArguments['Comment']->ReplyToClass : ''
      );
   }

   // Options for this module.
   
   public function Base_GetAppSettingsMenuItems_Handler(&$Sender) {
      $Menu = $Sender->EventArguments['SideMenu'];
      $Menu->AddLink('Add-ons', 'Reply To', 'plugin/replyto', 'Garden.Themes.Manage');
   }   

   public function PluginController_ReplyTo_Create(&$Sender) {
      $Sender->AddSideMenu('plugin/replyto');
      $Sender->Form = new Gdn_Form();
      $Validation = new Gdn_Validation();
      $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
      $ConfigurationModel->SetField(array('ReplyTo.Mention.Insert'));
      $Sender->Form->SetModel($ConfigurationModel);
            
      if ($Sender->Form->AuthenticatedPostBack() === FALSE) {    
         $Sender->Form->SetData($ConfigurationModel->Data);
      } else {
         $Data = $Sender->Form->FormValues();
         if ($Sender->Form->Save() !== FALSE) {
            $Sender->StatusMessage = Gdn::Translate("Your settings have been saved.");
         }
      }

      $Sender->Render($this->GetView('replyto-settings.php'));
   }
}
