document.addEventListener('DOMContentLoaded', () => {
    ptaVolunteer.init({
        ajaxUrl: ptaSUS.ajaxurl,
        extraData: {
            action: 'pta_sus_live_search',
            security: ptaSUS.ptanonce,
        }
    });
});