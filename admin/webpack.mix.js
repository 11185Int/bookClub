const {mix} = require('laravel-mix');

themeScripts = [
    'resources/assets/js/theme/jquery.min.js',
    'resources/assets/js/theme/jquery-ui.min.js',
    'resources/assets/js/theme/bootstrap.min.js',
    'resources/assets/js/theme/jquery.validate.min.js',
    'resources/assets/js/theme/moment.min.js',
    'resources/assets/js/theme/bootstrap-datetimepicker.js',
    'resources/assets/js/theme/bootstrap-selectpicker.js',
    'resources/assets/js/theme/bootstrap-checkbox-radio-switch-tags.js',
    'resources/assets/js/theme/chartist.min.js',
    'resources/assets/js/theme/bootstrap-notify.js',
    'resources/assets/js/theme/sweetalert2.js',
    'resources/assets/js/theme/jquery-jvectormap.js',
    'resources/assets/js/theme/jquery.bootstrap.wizard.min.js',
    'resources/assets/js/theme/bootstrap-table.js',
    'resources/assets/js/theme/fullcalendar.min.js',
    'resources/assets/js/theme/light-bootstrap-dashboard.js',
    'resources/assets/js/theme/jquery.sharrre.js',
    'resources/assets/js/theme/demo.js'
];

mix.js('resources/assets/js/app.js', 'public/js')
    .scripts(themeScripts, 'public/js/theme.js')
    .sass('resources/assets/sass/app.scss', 'public/css')
    .version();