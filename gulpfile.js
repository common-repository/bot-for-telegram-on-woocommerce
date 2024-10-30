var gulp = require('gulp');
var browserSync = require('browser-sync').create();
var sass = require('gulp-sass');
var autoprefixer = require('gulp-autoprefixer');
var livereload = require('gulp-livereload');
var cssbeautify = require('gulp-cssbeautify');
var cache = require('gulp-cached');

function styles() {
    return gulp.src('assets/scss/*.scss')
        .pipe(sass().on('error', sass.logError))
        .pipe(autoprefixer())
        .pipe(cssbeautify())
        .pipe(cache('styling'))
        .pipe(gulp.dest('assets/css/'))
        .pipe(livereload())
        .pipe(browserSync.stream());
}

function watch() {
    livereload.listen();
    gulp.watch('assets/scss/*.scss', styles);
}

function browsersync() {
    return browserSync.init({
        proxy: "localhost/"
    });
}

exports.styles = styles;
exports.watch = watch;
exports.browsersync = browsersync;

let tasks = [styles];

gulp.task('build', gulp.series(gulp.parallel(...tasks)));
gulp.task('dev', gulp.series(gulp.parallel(...tasks, watch, browsersync)));

gulp.task('move-to-svn', function (done) {
    gulp.src([
        './assets/**/*',
        './includes/**/*',
        './nuxy/**/*',
        './nuxy_settings/**/*',
        './templates/**/*',
        './languages/**/*',
        './gulpfile.js',
        './package.json',
        './readme.txt',
        './bot-for-telegram-on-woocommerce.php',
    ], {base :  '.'})
        .pipe(gulp.dest('../../../wp_svn/bot/trunk'));
    done();
});