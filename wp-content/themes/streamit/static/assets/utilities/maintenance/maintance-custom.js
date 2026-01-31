import './jquery.countTo.js';
import './jquery.countdown.min.js';
import '../../scss/theme-design-system/custom/_maintenance.scss';

/*----------------------------------------------
Index Of Script
------------------------------------------------
1.Coming soon 

*/
(function (jQuery) {

    "use strict";
    jQuery(document).ready(function () {
        /*----------------
        Coming soon
        ---------------------*/
        var $i;
        var $date = jQuery('.expire_date').attr('id');
        var $expire_dates;

        jQuery('.example').countdown({
            date: $date,
            offset: -8,
            day: 'Day',
            days: 'Days'

        }, function () {

        });
    });

})(jQuery);