/* jshint node:true */

const wpjmPluginSlug = 'wp-job-manager-indeed-integration';
const wpjmPluginBuildPath = 'tmp/build';

module.exports = function( grunt ){
    'use strict';

    grunt.initConfig({
        pluginSlug: wpjmPluginSlug,

        // setting folder templates
        dirs: {
            css: 'assets/css',
            images: 'assets/images',
            js: 'assets/js',
            build: wpjmPluginBuildPath
        },

        // Compile all .less files.
        less: {
            compile: {
                options: {
                    // These paths are searched for @imports
                    paths: ['<%= dirs.css %>/']
                },
                files: [{
                    expand: true,
                    cwd: '<%= dirs.css %>/',
                    src: [
                        '*.less',
                        '!icons.less',
                        '!mixins.less'
                    ],
                    dest: '<%= dirs.css %>/',
                    ext: '.css'
                }]
            }
        },

        // Minify all .css files.
        cssmin: {
            minify: {
                expand: true,
                cwd: '<%= dirs.css %>/',
                src: ['*.css'],
                dest: '<%= dirs.css %>/',
                ext: '.css'
            }
        },

        // Minify .js files.
        uglify: {
            options: {
                preserveComments: 'some'
            },
            frontend: {
                files: [{
                    expand: true,
                    cwd: '<%= dirs.js %>',
                    src: [
                        '*.js',
                        '!*.min.js'
                    ],
                    dest: '<%= dirs.js %>',
                    ext: '.min.js'
                }]
            },
        },

        copy: {
            main: {
                src: [
                    '**',
                    '!*.log', // Log Files
                    '!node_modules/**', '!Gruntfile.js', '!package.json','!package-lock.json', '!yarn.lock', // NPM/Grunt
                    '!.git/**', '!.github/**', // Git / Github
                    '!tests/**', '!bin/**', '!phpunit.xml', '!phpunit.xml.dist', // Unit Tests
                    '!vendor/**', '!composer.lock', '!composer.phar', '!composer.json', // Composer
                    '!.*', '!**/*~', '!tmp/**', //hidden/tmp files
                    '!CONTRIBUTING.md',
                    '!readme.md',
                    '!phpcs.ruleset.xml',
                    '!tools/**'
                ],
                dest: '<%= dirs.build %>/'
            }
        },

        // Watch changes for assets
        watch: {
            less: {
                files: ['<%= dirs.css %>/*.less'],
                tasks: ['less', 'cssmin'],
            },
            js: {
                files: [
                    '<%= dirs.js %>/*js',
                    '!<%= dirs.js %>/*.min.js',
                ],
                tasks: ['uglify']
            }
        },

        // Generate POT files.
        makepot: {
            options: {
                type: 'wp-plugin',
                domainPath: '/languages',
                potHeaders: {
                    'language-team': 'LANGUAGE <EMAIL@ADDRESS>'
                }
            },
            dist: {
                options: {
                    potFilename: '<%= pluginSlug %>.pot',
                    exclude: [
                        'apigen/.*',
                        'tests/.*',
                        'tmp/.*',
                        'vendor/.*',
                        'node_modules/.*'
                    ]
                }
            }
        },

        // Check textdomain errors.
        checktextdomain: {
            options:{
                text_domain: '<%= pluginSlug %>',
                keywords: [
                    '__:1,2d',
                    '_e:1,2d',
                    '_x:1,2c,3d',
                    'esc_html__:1,2d',
                    'esc_html_e:1,2d',
                    'esc_html_x:1,2c,3d',
                    'esc_attr__:1,2d',
                    'esc_attr_e:1,2d',
                    'esc_attr_x:1,2c,3d',
                    '_ex:1,2c,3d',
                    '_n:1,2,4d',
                    '_nx:1,2,4c,5d',
                    '_n_noop:1,2,3d',
                    '_nx_noop:1,2,3c,4d'
                ]
            },
            files: {
                src:  [
                    '**/*.php',         // Include all files
                    '!apigen/**',       // Exclude apigen/
                    '!node_modules/**', // Exclude node_modules/
                    '!tests/**',        // Exclude tests/
                    '!vendor/**',       // Exclude vendor/
                    '!tmp/**'           // Exclude tmp/
                ],
                expand: true
            }
        },

        addtextdomain: {
            wpjobmanager: {
                options: {
                    textdomain: '<%= pluginSlug %>'
                },
                files: {
                    src: [
                        '*.php',
                        '**/*.php',
                        '!node_modules/**',
                        '!tmp/**'
                    ]
                }
            }
        },

        zip: {
            'main': {
                src: [ '<%= dirs.build %>/**' ],
                dest: 'tmp/<%= pluginSlug %>.zip',
                router: function (filepath) {
                    return filepath.replace( wpjmPluginBuildPath, wpjmPluginSlug );
                }
            }
        },

        phpunit: {
            main: {
                dir: ''
            },
            options: {
                bin: 'vendor/bin/phpunit',
                colors: true
            }
        },

        clean: {
            main: [ 'tmp/' ], //Clean up build folder
        },

        jshint: {
            options: grunt.file.readJSON('.jshintrc'),
            src: [
                'assets/js/**/*.js',
                '!assets/js/**/*.min.js',
            ]
        },

        wp_readme_to_markdown: {
            readme: {
                files: {
                    'readme.md': 'readme.txt'
                }
            }
        }
    });

    // Load NPM tasks to be used here
    grunt.loadNpmTasks( 'grunt-contrib-less' );
    grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
    grunt.loadNpmTasks( 'grunt-contrib-uglify' );
    grunt.loadNpmTasks( 'grunt-contrib-watch' );
    grunt.loadNpmTasks( 'grunt-contrib-jshint' );
    grunt.loadNpmTasks( 'grunt-checktextdomain' );
    grunt.loadNpmTasks( 'grunt-contrib-copy' );
    grunt.loadNpmTasks( 'grunt-contrib-clean' );
    grunt.loadNpmTasks( 'grunt-gitinfo' );
    grunt.loadNpmTasks( 'grunt-phpunit' );
    grunt.loadNpmTasks( 'grunt-checkbranch' );
    grunt.loadNpmTasks( 'grunt-shell' );
    grunt.loadNpmTasks( 'grunt-wp-i18n' );
    grunt.loadNpmTasks( 'grunt-wp-readme-to-markdown');
    grunt.loadNpmTasks( 'grunt-zip' );

    grunt.registerTask( 'build', [ 'gitinfo', 'test', 'clean', 'copy' ] );

    grunt.registerTask( 'package', [ 'build', 'zip' ] );

    // Register tasks
    grunt.registerTask( 'default', [
        'less',
        'cssmin',
        'uglify',
        'wp_readme_to_markdown'
    ] );

    // Just an alias for pot file generation
    grunt.registerTask( 'pot', [
        'makepot'
    ] );

    grunt.registerTask( 'test', [
        'phpunit'
    ] );

    grunt.registerTask( 'dev', [
        'test',
        'default'
    ] );
};
