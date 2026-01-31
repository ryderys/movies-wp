
function get(route, Dataparams) {
    return jQuery.ajax({
        url: stAjax.stAdminAjax,
        type: 'GET',
        data: {
            ...Dataparams,
            action: 'st_admin_ajax_get',
            _ajax_nonce: stAdminAjax._ajax_nonce,
            route_name: route
        }
    })
}

function post(route, Dataparams) {

    let isFormData = {
        processData: false,
        contentType: false
    };

    // Check if Dataparams is a FormData object
    if (Dataparams instanceof FormData) {
        Dataparams.append('action', 'st_admin_ajax_post');
        Dataparams.append('route_name', route);
    } else {
        // Regular data object
        Dataparams = {
            ...Dataparams,
            action: 'st_admin_ajax_post',
            _ajax_nonce: window.stAdminAjax._ajax_nonce,
            route_name: route
        };
    }
    
    return jQuery.ajax({
        url: window.stAdminAjax.ajaxurl,
        type: 'POST',
        data: Dataparams,
        ...(Dataparams instanceof FormData) ? isFormData : {}
    });
}

export { get, post } 