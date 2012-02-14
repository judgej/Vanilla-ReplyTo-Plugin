jQuery(document).ready(function($) {
   
/* Options */

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

      if ($(msg).is(':visible')) {

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
               
               $(msg).after(json.Data);
               //$(msg).hide(); // Unlike editing, do not hide the original message.
               $(parent).find('span.TinyProgress').hide();               

               // Replace the "Back to Discussions" with a "Cancel" button.
               // There is no easy way to do it in the back end without consequences.
               // TODO: some translation would probably be needed here in the long term.
               $(parent).find('a.Back').removeClass('Back').addClass('Cancel').text('Cancel');

               // TODO: remove or disable the "Reply" button so the user can not get
               // multiple reply forms.
            }
         });
      } else {
         $(parent).find('div.CommentForm').remove();
         $(parent).find('span.TinyProgress').remove();
         $(msg).show();
      }
      
      $(document).trigger('CommentEditingComplete', [msg]);
      return false;
   });

});