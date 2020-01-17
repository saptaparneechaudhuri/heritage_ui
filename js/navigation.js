(function($, Drupal, drupalSettings) {
    Drupal.behaviors.navigation_levels = {
      attach: function (context, settings) {
         $('#navigationButtons').once('MyBehavior').each(function() {
            var navLabels = drupalSettings.nav;
            var navLevels = drupalSettings.navLevel;
            for(var k in navLabels) {
                var navBtn = document.getElementById(navLabels[k]);
                navBtn.addEventListener('click', function() {
                    clickFunction($, event);
                });
            }
         });
      }
    };
}
)(jQuery, Drupal, drupalSettings);

function clickFunction($, event) {
    var triggeredBy = event.target.id;
    var triggeredArray = triggeredBy.split("_");
    var level = triggeredArray[0];
    document.getElementById('button-clicked').value = triggeredBy;
    // clicked[0].value = triggeredBy;
    console.log("level is: " + level);
    var navLevelElement = document.getElementsByName(level);
    if(navLevelElement.length != 0) {
        $(navLevelElement[0]).trigger('change');
    } 
    console.log('Nav button clicked!');
}