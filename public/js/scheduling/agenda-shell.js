/* The shared theme enhances every select on window.load. Agenda updates native
 * options dynamically, so remove that enhancement locally after the theme runs. */
(function (root) {
    root.addEventListener('load', function () {
        if (root.jQuery && root.jQuery.fn.selectpicker) {
            root.jQuery('#agenda-board select, #admission-board select, #schedule-workspace select, #schedule-editor select').selectpicker('destroy');
        }
    });
}(window));
