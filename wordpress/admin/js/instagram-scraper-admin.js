(function($) {
    'use strict';

    $(document).ready(function() {
        // Confirm before refreshing feed
        $('form[action*="instagram_scraper_refresh"]').on('submit', function() {
            if (typeof instagram_scraper_admin !== 'undefined') {
                return confirm(instagram_scraper_admin.refresh_confirm);
            }
            return true;
        });
    });

})(jQuery);
