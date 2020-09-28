

'use strict';

const gulp = require('gulp'),
	sass = require('gulp-sass'),
	sourcemaps = require('gulp-sourcemaps'),
	connect = require('gulp-connect'),
	rigger = require('gulp-rigger'),
	livereload = require('gulp-livereload'),
	globbing = require('gulp-css-globbing'),
	notify = require("gulp-notify"),
	prefix = require('gulp-autoprefixer'),
    concat = require('gulp-concat');

const paths = {
	css: ['app/css/main.css'],
	scss :['app/scss/**/*.scss']
};

gulp.task('scss', function () {
	gulp.src(paths.scss)
		.pipe(sourcemaps.init())
		.pipe(globbing({
			extensions: ['.scss']
		}))
		.pipe(sass({
			outputStyle: 'expanded',
			includePaths : ['app/scss'],
			errLogToConsole: true
		}))
		.pipe(prefix({
			browsers: ["last 10 version"]
		}))
		.pipe(concat('treo-light-theme.css'))
		.pipe(sourcemaps.write('.'))
		.pipe(gulp.dest('css/treo/'));
});

gulp.task('connect', function() {
	connect.server({
		port: 4201,
		root: 'app',
		livereload: true
	});
});
gulp.task('copy', function () {
	gulp.src('app/css/main.html')
		.pipe(rigger())
		.pipe(gulp.dest('../treo/'))
		.pipe(connect.reload());
});



gulp.task('css', function(){
	gulp.src(paths.css)
		.pipe(connect.reload());
});



gulp.task('watch', function () {
	gulp.watch(paths.css, ['css']);
	gulp.watch(paths.scss, ['scss']);
});

gulp.task('default', ['watch', 'connect', 'scss','copy']);
