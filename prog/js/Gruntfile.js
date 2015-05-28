module.exports = function (grunt)
{

    // Load grunt tasks automatically
    require('load-grunt-tasks')(grunt);
    // Time how long tasks take. Can help when optimizing build times
    require('time-grunt')(grunt);
    // Project configuration.
    grunt.initConfig({
        clean: {
            dist: {
                files: [{
                            dot: true,
                            src: [
                                '.tmp',
                                'public/dist/{,*/}*',
                                'application/views/build/{,*/}*',
                                'application/modules/editor/views/build/{,*/}*',
                                'application/modules/frontoffice/views/build/{,*/}*',
                                'application/modules/network/views/build/{,*/}*',
                            ]
                        }]
            },
            tpls: {
                files: [{
                            dot: true,
                            src: [
                                '.tmp',
                                'public/dist/{,*/}*.tpls.js',
                            ]
                        }]
            },
            migration: {
                files: [{
                            dot: true,
                            src: [
                                'application/views/scripts/{,*/}*',
                                'application/modules/editor/views/scripts/{,*/}*',
                                'application/modules/frontoffice/views/scripts/{,*/}*',
                                'application/modules/network/views/scripts/{,*/}*',
                            ]
                        }]
            },
            server: '.tmp'
        },
        copy: {
            prepareMain: {
                files: [
                    // includes files within path
                    {
                        expand: true,
                        cwd: 'application/views/scripts/',
                        src: [
                            '**/*',
                        ],
                        dest: 'application/views/dev/',
                        filter: 'isFile'
                    },
                ]
            },
            prepareEditor: {
                files: [
                    // includes files within path
                    {
                        expand: true,
                        cwd: 'application/modules/editor/views/scripts/',
                        src: [
                            '**/*.phtml',
                        ],
                        dest: 'application/modules/editor/views/dev/',
                        filter: 'isFile'
                    },
                ]
            },
            prepareFrontoffice: {
                files: [
                    // includes files within path
                    {
                        expand: true,
                        cwd: 'application/modules/frontoffice/views/scripts/',
                        src: [
                            '**/*.phtml',
                        ],
                        dest: 'application/modules/frontoffice/views/dev/',
                        filter: 'isFile'
                    },
                ]
            },
            prepareNetwork: {
                files: [
                    // includes files within path
                    {
                        expand: true,
                        cwd: 'application/modules/network/views/scripts/',
                        src: [
                            '**/*.phtml',
                        ],
                        dest: 'application/modules/network/views/dev/',
                        filter: 'isFile'
                    },
                ]
            },
            main: {
                files: [
                    // includes files within path
                    {
                        expand: true,
                        cwd: 'application/views/dev/',
                        src: [
                            '**/*',
                        ],
                        dest: 'application/views/build/',
                        filter: 'isFile'
                    },
                ]
            },
            editor: {
                files: [
                    // includes files within path
                    {
                        expand: true,
                        cwd: 'application/modules/editor/views/dev/',
                        src: [
                            '**/*.phtml',
                        ],
                        dest: 'application/modules/editor/views/build/',
                        filter: 'isFile'
                    },
                ]
            },
            frontoffice: {
                files: [
                    // includes files within path
                    {
                        expand: true,
                        cwd: 'application/modules/frontoffice/views/dev/',
                        src: [
                            '**/*.phtml',
                        ],
                        dest: 'application/modules/frontoffice/views/build/',
                        filter: 'isFile'
                    },
                ]
            },
            network: {
                files: [
                    // includes files within path
                    {
                        expand: true,
                        cwd: 'application/modules/network/views/dev/',
                        src: [
                            '**/*.phtml',
                        ],
                        dest: 'application/modules/network/views/build/',
                        filter: 'isFile'
                    },
                ]
            }
        },
        filerev: {
            options: {
                algorithm: 'md5',
                length: 8
            },
            dist: {
                src: [
                    'public/dist/*.js',
                ]
            }
        }, // Reads HTML for usemin blocks to enable smart builds that automatically
        // concat, minify and revision files. Creates configurations in memory so
        // additional tasks can operate on them
        useminPrepare: {
            html: [
                'application/views/build/**/*.phtml',
                'application/modules/editor/views/build/**/*.phtml',
                'application/modules/frontoffice/views/build/**/*.phtml',
                'application/modules/network/views/build/**/*.phtml'
            ],
            options: {
                dest: 'public',
                flow: {
                    html: {
                        steps: {
                            js: ['concat', 'uglifyjs'],
                            css: ['cssmin']
                        },
                        post: {}
                    }
                }
            }
        },

        // Performs rewrites based on filerev and the useminPrepare configuration
        usemin: {
            html: [
                'application/views/build/**/*.phtml',
                'application/modules/editor/views/build/**/*.phtml',
                'application/modules/frontoffice/views/build/**/*.phtml',
                'application/modules/network/views/build/**/*.phtml'
            ],
            options: {
                assetsDirs: ['public']
            }
        },
        untranslate: {
            src: [
                'public/scripts/angularjs/campaign/new-editor/**/*.html',
                'public/scripts/angularjs/messages/**/*.html'
            ]
        },
        ngtemplates: {
            'Campaign.Templates': {
                src: [
                    'public/scripts/angularjs/campaign/new-editor/**/*.html',
                    'public/scripts/angularjs/messages/**/*.html'
                ],
                dest: 'public/dist/campaign-editor.tpls.js',
                options: {
                    prefix: '/'
                }
            }
        },
        translate: {
            translations: 'data/locales/*.csv',
            src: ['public/dist/*.tpls.js']
        }
    });

    grunt.registerTask('prepareConfiguration', function ()
    {
        var file = grunt.file.read('application/configs/application.ini');
        grunt.file.write('application/configs/application.backup-' + grunt.template.today('yyyymmddHHMs') + '.ini',
                file);
        var replacement = '\nresources.view.scriptPath.network = APPLICATION_PATH "/modules/network/views/build"\n' +
                          'resources.view.scriptPath.frontoffice = APPLICATION_PATH "/modules/frontoffice/views/build"\n' +
                          'resources.view.scriptPath.editor = APPLICATION_PATH "/modules/editor/views/build"\n' +
                          'resources.view.scriptPath.main = APPLICATION_PATH "/views/build"\n';

        file = file.replace(/(resources.view.basePath\s*= APPLICATION_PATH "\/views")/, replacement + '\n$1');
        file = file.replace(/(editor.resources.layout.layoutpath\s*= APPLICATION_PATH "\/views\/scripts\/")/,
                ';$1');

//        file = file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/marketeo.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/marketeo.js"');
//        file = file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/libraries\/counterSms.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/libraries\/counterSms.js")');
//        file =
//        file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/marketeo-editor-component.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/marketeo-editor-component.js")');
//        file =
//        file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/libraries\/messageLength.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/libraries\/messageLength.js")');
//        file =
//        file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/marketeo-campaign-editor.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/marketeo-campaign-editor.js")');
//        file =
//        file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/libraries\/content\/content.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/libraries\/content\/content.js")');
//        file = file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/marketeo-paginator.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/marketeo-paginator.js")');
//        file = file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/marketeo-editor.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/marketeo-editor.js")');
//        file = file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/site.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/site.js)"');
//        file = file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/email.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/email.js")');
//        file = file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/sms.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/sms.js")');
//        file = file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/facebook.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/facebook.js")');
//        file = file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/twitter.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/twitter.js")');
//        file = file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/voice.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/voice.js")');
//        file = file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/voicemail.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/editors\/voicemail.js")');
//        file = file.replace(/(resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/marketeo-table.js")/,
//                ';resources\.Dmjquery\.javascriptfiles\..*\s*= "\/scripts\/marketeo-table.js"');

        grunt.file.write('application/configs/application.ini', file);

    });
    grunt.registerTask('translate', function ()
            {
                this.requiresConfig('translate.src');
                var translation = {};
                var options = grunt.config.get('translate');
                grunt.file.expand(options.translations).forEach(function (e)
                {
                    var local = e.replace(/(.*\/)(\w*)\.\w+$/, '$2');
                    grunt.verbose.or.writeln('New lang found :' + local.yellow);
                    var file = grunt.file.read(e);
                    var lines = file.match(/.*/g);
                    var trs = {};
                    lines.forEach(function (l)
                    {
                        if (l.trim().length > 0) {
                            trs[l.replace(/"(.*)";".*"/, '$1')] = l.replace(/".*";"(.*)"/, '$1');
                            grunt.verbose.writeln('Translation [' + local.red + ']: ' +
                                                  l.replace(/"(.*)";".*"/, '$1').cyan + ' => ' +
                                                  l.replace(/".*";"(.*)"/, '$1').yellow);
                        }
                    });
                    translation[local] = trs;
                });
                var regtranslate = /\[#(.*)#\]/g;
                var generatedFiles = [];
                for (var lang in translation) {
                    grunt.file.expand(options.src).forEach(function (e)
                            {
                                var generatedFile = false;
                                generatedFiles.forEach(function (path)
                                {
                                    if (path === e) {
                                        generatedFile = true;
                                    }
                                });
                                if (!generatedFile) {
                                    var file = grunt.file.read(e);
                                    grunt.log.debug(e);
                                    var target = e.replace('.tpls', '.' + lang + '.tpls');
                                    content = file.replace(regtranslate, function (match, p1)
                                    {

                                        grunt.verbose.writeln('Replace:' + match.cyan + ' with ' +
                                                              (translation[lang][p1] || p1).yellow);
                                        return translation[lang][p1] || p1;
                                    });
                                    generatedFiles.push(target);
                                    grunt.file.write(target, content);
                                    grunt.verbose.or.writeln('File ' + target.cyan + ' created.');
                                }
                            }
                    );
//                    contents += '}]);';
                }
            }
    );
    grunt.registerTask('tradzendtojs', function ()
            {
                this.requiresConfig('untranslate.src');
                var options = grunt.config.get('untranslate');
                var regtranslate = /<\?php echo .*\$this->\s*translate\('(.*)'\).*;\s*\?>/g;
                grunt.log.debug(options.src);
                grunt.file.expand(options.src).forEach(function (e)
                        {
                            var file = grunt.file.read(e).replace(regtranslate, '[#$1#]');
                            grunt.file.write(e, file);
                        }
                );
            }
    );
    grunt.registerTask('notranslate', function ()
            {
                this.requiresConfig('untranslate.src');
                var options = grunt.config.get('untranslate');
                var regtranslate = /\[#(.*)#\]/g;
                grunt.log.debug(options.src);
                grunt.file.expand(options.src).forEach(function (e)
                        {
                            var file = grunt.file.read(e).replace(regtranslate, '$1');
                            grunt.file.write(e, file);
                        }
                );
            }
    );
// Default task(s).
    grunt.registerTask('default', [
        'clean:dist',
        'copy:main',
        'copy:editor',
        'copy:frontoffice',
        'copy:network',
        'useminPrepare',
        'concat',
        'uglify',
        'filerev',
        'usemin'
    ]);
    grunt.registerTask('htmltojs', [
        'clean:tpls',
        'ngtemplates',
        'translate',
    ]);
    grunt.registerTask('migration', [
        'copy:prepareMain',
        'copy:prepareEditor',
        'copy:prepareFrontoffice',
        'copy:prepareNetwork',
        'clean:migration',
        'prepareConfiguration'
    ]);

}
;