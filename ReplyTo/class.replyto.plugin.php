<?php
/*
Extension Name: ReplyTo
Extension Url: http://lussumo.com/addons/index.php
Description: Allows users to reply to specific comments.
Version: 0.1.0
Author: Jason Judge
Author Url: http://www.consil.co.uk/
*/

/**
 * @package Vanilla extension
 * @copyright (C) 2010 Jason Judge
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @link http://www.consil.co.uk/
 * @subpackage UserFields
 * @author Jason Judge <jason@consil.co.uk>
 * @date $Date: 2009-03-01 15:21:58 +0000 (Sun, 01 Mar 2009) $
 * @revision $Revision: 76 $
 */

// Define the plugin:
$PluginInfo['ReplyTo'] = array(
   'Name' => 'ReplyTo',
   'Description' => 'Allows a reply to to be made to a specific comment.',
   'Version' => '0.1',
   'RequiredApplications' => array('Vanilla' => '2.0.9'),
   'RequiredTheme' => FALSE,
   'RequiredPlugins' => FALSE,
   'HasLocale' => FALSE,
   'RegisterPermissions' => array(),
   'SettingsUrl' => '/dashboard/settings/replyto',
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
      $Controller->AddJsFile($this->GetResource('js/discussion.js', FALSE, FALSE));
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
   // There are ways of doing this using temporary tables, but we will use PHP arrays.

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

   // TODO: belongs in a model.

   public function RebuildLeftRight($DiscussionID) {
      // Get the base comment. Actually it is the discussion in vanilla 2.
      // We only call it a comment for historical reasons (TODO: change this).
      // This gives us the left and right values, used to organise the tree.
      //$BaseComment = $this->BaseComment($DiscussionID);

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
         ->SetCase('TreeLeft', 'CommentID', $LeftData)
         ->SetCase('TreeRight', 'CommentID', $RightData)
         ->Where('DiscussionID', $DiscussionID)
         ->Put();
   }

   // Replies will always be added to the same part of the tree, i.e. as a last sibling
   // of an existing comment.
   // This function opens a gap in the left/right values in the comment tree, then returns
   // the new left, right, and parent ID values as an array.
   // If the left/right values are not contiguous before it starts, then it will rebuild
   // the left/right values for the complete discussion.
   // Note also that the base left/right is in the discussion, so a reply direct to the discussion
   // will open the gap there.

   public function InsertPrep($DiscussionID, $CommentID) {
      // Get the count of comments in the discussion.
      $CommentCount = $this->CommentCount($DiscussionID);

      // Get the current max right value.
      $MaxRight = $this->MaxRight($DiscussionID);

      // If the base comment left/right does not match the comment count, then
      // rebuild the left/right values for the entire discussion.
      if ($MaxRight != (2 * $CommentCount)) {
         // Rebuild. The 'right' value of the base comment should be twice the total number
         // of comments, since with this tree model (Celko Nested Sets) we could up the left
         // and back down the right.
         $this->RebuildLeftRight($DiscussionID);
      }

      // Now we get to the task in hand: opening up a gap in the tree numbering for the new comment.
      // We want to insert as the last child of comment $CommentID.

      // Get the current right value of the insert point comment.
      // It and everything above it will move up two.
      // CHECKME: this seems a cumbersome way to get at a single comment.
      $CommentModel = new CommentModel();
      $CommentModel->CommentQuery();
      $CommentModel->SQL->Where('c.CommentID', $CommentID);
      $InsertComment = $CommentModel->SQL->Get()->FirstRow();

      // If this comment is for a different discussion, then stop now.
      if (empty($InsertComment) || $DiscussionID != $InsertComment->DiscussionID) return;

      $TreeRight = (int)$InsertComment->TreeRight;

      $SQL = Gdn::SQL();

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
      return array('ParentCommentID' => $CommentID, 'TreeLeft' => $TreeRight, 'TreeRight' => $TreeRight + 1);
     
   }

   // When fetching comments in a discussion, order by the tree first and then
   // the posted date. Note this has now been moved to the comment model constructor.

   public function CommentModel_BeforeGet_Handler(&$Sender) {
      //$Sender->SQL->OrderBy('TreeLeft', 'asc');
      //$Sender->SQL->OrderBy('DateInserted', 'asc'); // CHECKME: is this needed?
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
            $Sender->EventArguments['Comment']->ParentCommentID
         );

         // Write the tree position details to the new comment.
         // The parent comment ID is already dealt with by the form handler.
         if (!empty($Details)) {
            $SQL = Gdn::SQL();

            $Update = $SQL->Update('Comment')
               ->Where('CommentID', $Sender->EventArguments['Comment']->CommentID)
               ->Set('TreeLeft', $Details['TreeLeft'])
               ->Set('TreeRight', $Details['TreeRight'])
               ->Put();
         }
      }
   }

   public function DiscussionController_BeforeDiscussionRender_Handler(&$Sender) {
      // Add a hidden "reply to comment id" field in the comment submit form.
      // Name is 'Comment/ParentCommentID'
      // ID is 'Form_ParentCommentID'
      // Note: no lomger required, now that we are using AJAX to pull up comment forms
      // right under the comment they are replying to.
      //$Sender->Form->AddHidden('ParentCommentID', '');

      // Loop for each comment and build a depth and give them some categories.
      $depthstack = array();

      foreach($Sender->Data['Comments'] as $Comment) {
         // If we hit a comment without a parent, then immediately treat it as level 0.
         if (empty($Comment->ParentCommentID)) $depthstack = array();

         // Calculate the depth of the comment (within the context of the selected comments, i.e. not
         // in absolute terms.
         while (!empty($depthstack) && end($depthstack) < $Comment->TreeRight) {
            array_pop($depthstack);
         }

         $depth = count($depthstack);
         $depthstack[] = $Comment->TreeRight;

         $Comment->ReplyToDepth = $depth;

         // TODO: if a tree is cut short or starts half-way through, then links to the rest of the replies
         // would be useful, e.g. "replies continue..." and "this is a reply to...".

         // Set the class of the comment according to depth.
         $CommentClass = 'ReplyToDepth-' . $depth;

         for($i = 5; $i <= 100; $i += 5) {
            if ($depth >= $i) $CommentClass .= ' ReplyToDepth-' . $i . 'plus';
            else break;
         }

         $Comment->ReplyToClass = trim($CommentClass);
      }
   }

   public function CommentModel_BeforeSaveComment_Handler(&$Sender) {
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
            $Sender->Permission('Vanilla.Comments.Add', TRUE, 'Category', $Discussion->CategoryID);

            // Make sure the form has the parent comment ID.
            $Sender->Form->AddHidden('ParentCommentID', $CommentID);

            // Set up the form for rendering or processing.
            $Sender->View = 'Comment';

            // Run Comment() in the PostController to add a comment to this discussion.
            // We also need to ensure the parent comment ID gets into the process.
            $Sender->Comment($DiscussionID);
         }
      }

      // CHECKME: does the draft model potenially play a part in this?
/*
      if (is_numeric($CommentID) && $CommentID > 0) {
         $this->Form->SetModel($this->CommentModel);
         $this->Comment = $this->CommentModel->GetID($CommentID);
      } else {
         $this->Form->SetModel($this->DraftModel);
         $this->Comment = $this->DraftModel->GetID($DraftID);
      }
      $this->View = 'Comment';
      $this->Comment($this->Comment->DiscussionID);
*/
   }

   // Add the option to "reply to" the comment.

   public function DiscussionController_CommentOptions_Handler(&$Sender) {
      $this->AddReplyToButton($Sender);
   }

   protected function AddReplyToButton(&$Sender) {
      if (!Gdn::Session()->UserID) return;

      $CategoryID = $Sender->CategoryID;
      $Session = Gdn::Session();
      $CommentID = $Sender->CurrentComment->CommentID;

      if ($Sender->EventArguments['Type'] == 'Comment') {
          // Can the user comment on this category, and is the discussion open for comments?
          if (empty($Sender->Data['Discussion']->Closed)
             && $Session->CheckPermission('Vanilla.Comments.Add', TRUE, 'Category', $CategoryID)
          ) {
             // Add the "Reply To" link on the options for the comment.
             $Sender->Options .= '<span>'.Anchor(T('Reply'), '/vanilla/post/replycomment/'.$CommentID, 'ReplyComment').'</span>';
          }
      }
   }

   // TODO: pre comments rendering, calculate the depths of the comments and set
   // approprate classes and other information for each one.

   // TODO: add JS and styles to the comment pages where needed.

}
