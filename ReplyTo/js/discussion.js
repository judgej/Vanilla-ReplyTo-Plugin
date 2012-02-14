jQuery(document).ready(function($) {
   
   // When the edit link is clicked, save the nesting level categories for
   // restoring later, after the edited comment is submitted.
   // TODO: now we need a trigger once the edited comment is sent back on
   // form submission, so we can put the saved classes back onto it.
   $('a.EditComment').livequery('click', function() {
      var btn = this;
      var parent = $(btn).parents('div.Comment');
      var saveclass = $(parent).attr('class');
      // Store it against the parent of 'parent', which does not get 
      // removed (i.e. the list item).
      if (saveclass != 'Comment') $(parent).parent().data('saveclass', saveclass);
   });


   // Reply to comment
   // We want to show the comment form, but unlike editing, there is no need
   // to hide the comment we are replying to.
   $('a.ReplyComment').livequery('click', function() {
      var btn = this;

      // Widest wrapper around the comment.
      var container = $(btn).parents('li.Comment');
      $(container).addClass('Editing');

      // Second wrapper around the comment.
      var parent = $(btn).parents('div.Comment');

      // Message body, after the title and options.
      var msg = $(parent).find('div.Message');
      
      // Put spinner on end of options list.
      $(parent).find('div.Meta span:last').after('<span class="TinyProgress">&nbsp;</span>');

      // Check if the child comment form is already open within this comment.
      var CommentForm = $(parent).find('div.CommentForm').length;

      // If the comment form is not there, then open it, otherwise close it. Simples.
      if (!CommentForm) {

         $.ajax({
            type: "POST",
            url: $(btn).attr('href'),
            data: 'DeliveryType=VIEW&DeliveryMethod=JSON',
            dataType: 'json',
            error: function(XMLHttpRequest, textStatus, errorThrown) {
               // Remove any old popups
               $('div.Popup,.Overlay').remove();
               $.popup({}, XMLHttpRequest.responseText);
            },
            success: function(json) {
               json = $.postParseJson(json);
               
               // Place the form after the original comment and hide the spinner.
               $(msg).after(json.Data);
               $(parent).find('span.TinyProgress').hide();               

               // Replace the "Back to Discussions" with a "Cancel" button.
               // There is no easy way to do it in the back end without consequences.
               // TODO: some translation would probably be needed here in the long term.
               $(parent).find('a.Back').removeClass('Back').addClass('Cancel').text('Cancel');
            }
         });
      } else {
         // Take the comment form off.
         $(parent).find('div.CommentForm').remove();

         // Take the spinner off, now the form has loaded.
         $(parent).find('span.TinyProgress').remove();
      }
      
      $(document).trigger('CommentReplyingComplete', [msg]);
      return false;
   });

});