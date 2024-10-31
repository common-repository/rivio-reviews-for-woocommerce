jQuery(document).ready(function () {

    jQuery(".rivio-selector").click(function(){
        jQuery(".rivio-selector").removeClass("active");
        jQuery(this).addClass("active");
    });

    jQuery(".rivio-show-secret").click(function(){
        jQuery(".rivio-show-secret").css("display", "none");
        jQuery(".rivio-secret").css("display", "inline-block");
    });
});