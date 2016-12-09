function htmlDecode(value){
   return jQuery('<div/>').html(value).text();
}

function pta_volunteer_info () {
          jQuery("input[name=signup_firstname],input[name=signup_lastname]").autocomplete({
            source: function(request, response) {
                         jQuery.ajax({ url: self.location.href,
                                  data: { q: request.term,
                                          pta_pub_action: 'autocomplete_volunteer'
                                        },
                                  dataType: "json",
                                  type: "GET",
                                  success: function(data){
                                                response(jQuery.map(data, function(item) {
                                                      return {
                                                         label: htmlDecode(item.firstname)+' '+htmlDecode(item.lastname),
                                                         firstname: htmlDecode(item.firstname),
                                                         lastname: htmlDecode(item.lastname),
                                                         email: item.email,
                                                         phone: item.phone,
                                                         user_id: item.user_id
                                                      };
                                                }));
                                           }
                                 });
                    },
            select:function(evt, ui) {
                // when a location is selected, populate related fields in this form
                jQuery('input[name=signup_firstname]').val(ui.item.firstname);
                jQuery('input[name=signup_lastname]').val(ui.item.lastname);
                jQuery('input[name=signup_email]').val(ui.item.email);
                jQuery('input[name=signup_validate_email]').val(ui.item.email);
                jQuery('input[name=signup_phone]').val(ui.item.phone);
                jQuery('input[name=signup_user_id]').val(ui.item.user_id);
                return false;
            },
            minLength: 1
          }).data( "ui-autocomplete" )._renderItem = function( ul, item ) {
            return jQuery( "<li></li>" )
            .append("<a><strong>"+htmlDecode(item.firstname)+' '+htmlDecode(item.lastname)+'</strong><br /><small>'+htmlDecode(item.email)+ '</small></a>')
            .appendTo( ul );

          };

}

jQuery(document).ready( function() {
    if(jQuery("input[name=signup_firstname],input[name=signup_lastname]").length > 0) {
        pta_volunteer_info ();
    }

});
