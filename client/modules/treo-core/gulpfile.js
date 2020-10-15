

/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://treolabs.com
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

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
