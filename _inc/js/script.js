(function(){
function showAndHideSyncDuration() {
    if (jQuery("[name='woo_square_auto_sync']:checked").val() == "1") {
        jQuery('#auto_sync_duration_div').show();
    } else {
        jQuery('#auto_sync_duration_div').hide();
    }
}

function manualSync(event) {
    jQuery('#woo_square_error').remove();
    var way = event.data.name;
    jQuery("#manual_sync_"+way+"_btn").off("click");
    var button = jQuery("#manual_sync_"+way+"_btn");
    var old_text = button.text();
    button.text('Processing ...');
    button.attr('disabled', true);
    jQuery.ajax({
        type: "GET",
        url: myAjax.ajaxurl,
        data: 'action=manual_sync&way=' + way,
        success: function (html)
        {
            if(html){
                jQuery('#manual_sync_wootosqu_btn').parents('.welcome-panel').before('<div id="woo_square_error" class="error"><p>'+html+'</p></div>')
            }
            button.text(old_text);
            button.attr('disabled', false);
            jQuery("#manual_sync_"+way+"_btn").on("click", {name: way}, manualSync);
        }
    });
}

function initPopup(){
    jQuery("#sync-error,#sync-content-woo,#sync-content-square").html('');
    jQuery("#sync-content,#sync-error,.cd-buttons.end,.cd-buttons.start").hide();
    jQuery("#sync-loader").show();
    jQuery('.cd-popup').addClass('is-visible');
}

function processPopup(){
    jQuery('.cd-buttons.start').hide();
    jQuery('#sync-processing').text('Processing ...').prop('disabled', 'true');
    jQuery('.cd-buttons.end').show();
    
    //disable all checkboxes
    jQuery("#sync-content input:checkbox").prop('disabled', 'true');
    
}

function endPopup(){
    jQuery('#sync-processing').text('Close');
    jQuery('#sync-processing').prop('disabled', false);
}

function  show_woo_popup() {
    sync.caller = "woo";
    jQuery('#start-process').data("caller",sync.caller);
    initPopup();
    jQuery.ajax({
        type: "GET",
        url: myAjax.ajaxurl,
        data: 'action=get_non_sync_woo_data',
        success: function (response)
        {
            //ensure last user ckick was on woo->square
            if (sync.caller === 'woo'){
                response = JSON.parse(response);
                if(response.error){
                    jQuery("#sync-content, #sync-loader").hide();
                    jQuery("#sync-error").show().html(response.error);
                    endPopup();
                    return;
                }else if(!response.data){
                    return;
                }
                response = response.data;

                jQuery("#sync-loader").hide();
                jQuery("#sync-content-"+sync.caller).html(response);
                jQuery("#sync-content,.cd-buttons.start").show();
            }
            
        }
    });
}

function  show_square_popup() {
    sync.caller = "square";
    jQuery('#start-process').data("caller",sync.caller);
    initPopup();
    jQuery.ajax({
        type: "GET",
        url: myAjax.ajaxurl,
        data: 'action=get_non_sync_square_data',
        success: function (response)
        {           
            //ensure last user ckick was on square->woo
            if (sync.caller === 'square'){
                response = JSON.parse(response);           
                if(response.error){
                    jQuery("#sync-content, #sync-loader").hide();
                    jQuery("#sync-error").show().html(response.error);
                    endPopup();
                    return;
                }else if(!response.data){
                    return;
                }

                response = response.data;

                jQuery("#sync-loader").hide();
                jQuery("#sync-content-"+sync.caller).html(response);
                jQuery("#sync-content,.cd-buttons.start").show();
            }   
        }
    });
}


var sync = [];
sync.product = [];
sync.category = [];
sync.caller = '';

function startManualSync(caller) {
      
    processPopup();

    jQuery("#sync-product input:checkbox[name=woo_square_product]:checked").each(function(){
           sync.product.push(jQuery(this).val());
    });
    jQuery("#sync-category input:checkbox[name=woo_square_category]:checked").each(function(){
           sync.category.push(jQuery(this).val());
    });
    var action = caller=='woo'?'woo_to_square':'square_to_woo';
    jQuery.ajax({
        type: "GET",
        url: myAjax.ajaxurl,
        data: 'action='+"start_manual_"+action+"_sync",
        success: function (response)
        {
            if(response == '1'){
                syncCategoryOrProduct(caller, 'category');
            }else{
                jQuery("#sync-content").hide();
                jQuery("#sync-error").show().html(response);
            }
        },
        error: function (response)
        {
            jQuery("#sync-content").hide();
            jQuery("#sync-error").show().html("Error occurred!");
        }
    });
}

function syncCategoryOrProduct(caller, target){
    
    var currentProdId = sync[target].shift();
    if( !currentProdId){
        if (target == 'category'){
            syncCategoryOrProduct(caller, 'product');
        }else{
            terminateManualSync(jQuery('#start-process').data("caller"));
        }
        return;
    }
    var action = caller=='woo'?'sync_woo_'+target+'_to_square':
            'sync_square_'+target+'_to_woo';
    
    jQuery.ajax({
        type: "POST",
        url: myAjax.ajaxurl,
        data: 'action='+action+'&id=' + currentProdId,
        success: function (response)
        {
            if (response == 1){
                jQuery("#sync-"+target+" input:checkbox[name=woo_square_"+target+"][value="+currentProdId+"]").parent("div").append("<span class='dashicons dashicons-yes right'></span>").addClass('sync-success');
               
            }else{
                jQuery("#sync-"+target+" input:checkbox[name=woo_square_"+target+"][value="+currentProdId+"]").parent("div").append("<span class='dashicons dashicons-no-alt right'></span>").addClass('sync-failure');
            }
            
            
        },
        error: function (error){
            jQuery("#sync-"+target+" input:checkbox[name=woo_square_"+target+"][value="+currentProdId+"]").parent("div").append("<span class='dashicons dashicons-no-alt right'></span>").addClass('sync-failure');
        },
        complete: function (){
            syncCategoryOrProduct(caller, target);

        }
    });
}

function terminateManualSync(caller){
    jQuery.ajax({
        type: "POST",
        url: myAjax.ajaxurl,
        data: 'action=terminate_manual_'+caller+'_sync',
        success: function (html)
        {
            endPopup();            
        }
    });
}

//Bind events to the page
jQuery(document).ready(function (jQuery) {
    jQuery("#manual_sync_squtowoo_btn").on("click", {name: 'squtowoo'}, show_square_popup);
    jQuery("#manual_sync_wootosqu_btn").on("click", {name: 'wootosqu'}, show_woo_popup);


    //pop-up
    //close popup
    jQuery('.cd-popup').on('click', function (event) {
        if (jQuery(event.target).is('.cd-popup-close') || jQuery(event.target).is('.cd-popup')) {
            event.preventDefault();
            jQuery(this).removeClass('is-visible');           
            terminateManualSync(jQuery('#start-process').data("caller"));           

        }
    });
    //close popup when clicking the esc keyboard button
    jQuery(document).keyup(function (event) {
        if (event.which == '27') {
            jQuery('.cd-popup').removeClass('is-visible');
            terminateManualSync(jQuery('#start-process').data("caller"));
        }
    });
    
    //cron settings on change event
    jQuery("[name='woo_square_auto_sync']").on('change', function(){
        showAndHideSyncDuration();
    });
    
    jQuery('.cancel-process').on('click', function (event) {
        event.preventDefault();
       jQuery('.cd-popup').removeClass('is-visible');
       terminateManualSync(jQuery('#start-process').data("caller"));
    });
    
    jQuery('#start-process').on('click', function (event) {
        event.preventDefault();
        startManualSync(jQuery('#start-process').data("caller"));
    });
    
    jQuery('#sync-processing').on('click', function (event) {
        event.preventDefault();
        jQuery('.cd-popup').removeClass('is-visible');
    });
    
    
    jQuery('.collapse').on('click', function () {
        jQuery(this).siblings('.grid-div').toggleClass( "hidden collapse-content-show" );
        jQuery(this).children(".dashicons").toggleClass('collapse-open')
    });
});
})();