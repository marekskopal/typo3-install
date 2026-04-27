'use strict';

const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const rename = require('gulp-rename');
const cleanCss  = require('gulp-clean-css');

gulp.task('sass', () => {
    return gulp.src('./packages/ms_web/Resources/Private/Sass/**/*.scss')
        .pipe(sass({
            style: 'expanded',
            precision: 10,
            includePaths: ['.']
        }).on('error', sass.logError))
        .pipe(cleanCss())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest('./packages/ms_web/Resources/Public/Css'));
});

gulp.task('build', gulp.parallel('sass'));

gulp.task('default', () => {
    return gulp.watch('./packages/ms_web/Resources/Private/Sass/**/*.scss', gulp.series('sass'));
});
