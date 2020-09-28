

Espo.define('views/fields/formula', 'views/fields/text', function (Dep) {

    return Dep.extend({

        detailTemplate: 'fields/formula/detail',

        editTemplate: 'fields/formula/edit',

        height: 300,

        events: {
            'click [data-action="addAttribute"]': function () {
                this.addAttribute();
            },
            'click [data-action="addFunction"]': function () {
                this.addFunction();
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.height = this.options.height || this.params.height || this.height;
            this.targetEntityType = this.options.targetEntityType || this.params.targetEntityType || this.targetEntityType;

            this.containerId = 'editor-' + Math.floor((Math.random() * 10000) + 1).toString();

            if (this.mode == 'edit' || this.mode == 'detail') {
                this.wait(true);
                Promise.all([
                    new Promise(function (resolve) {
                        Espo.loader.load('lib!client/lib/ace/ace.js', function () {
                            Espo.loader.load('lib!client/lib/ace/mode-javascript.js', function () {
                                resolve();
                            }.bind(this));
                        }.bind(this));
                    }.bind(this))
                ]).then(function () {
                    ace.config.set("basePath", this.getBasePath() + 'client/lib/ace');
                    this.wait(false);
                }.bind(this));
            }

            this.on('remove', function () {
                if (this.editor) {
                    this.editor.destroy();
                }
            }, this);
        },

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.containerId = this.containerId;
            data.targetEntityType = this.targetEntityType;

            return data;
        },

        afterRender: function () {
            Dep.prototype.setup.call(this);

            this.$editor = this.$el.find('#' + this.containerId);

            if (this.$editor.size() && (this.mode === 'edit' || this.mode == 'detail')) {
                this.$editor
                    .css('height', this.height + 'px')
                    .css('fontSize', '14px');
                var editor = this.editor = ace.edit(this.containerId);

                if (this.mode == 'detail') {
                    editor.setReadOnly(true);
                    editor.renderer.$cursorLayer.element.style.display = "none";
                    editor.renderer.setShowGutter(false);
                }

                editor.setShowPrintMargin(false);
                editor.getSession().setUseWorker(false);
                editor.commands.removeCommand('find');
                editor.setHighlightActiveLine(false);

                var JavaScriptMode = ace.require("ace/mode/javascript").Mode;
                editor.session.setMode(new JavaScriptMode());
            }
        },

        fetch: function () {
            var data = {};
            data[this.name] = this.editor.getValue()

            return data;
        },

        addAttribute: function () {
            this.createView('dialog', 'views/admin/formula/modals/add-attribute', {
                scope: this.targetEntityType
            }, function (view) {
                view.render();
                this.listenToOnce(view, 'add', function (attribute) {
                    this.editor.insert(attribute);
                    this.clearView('dialog');
                }, this);
            }, this);
        },

        addFunction: function () {
            this.createView('dialog', 'views/admin/formula/modals/add-function', {
                scope: this.targetEntityType
            }, function (view) {
                view.render();
                this.listenToOnce(view, 'add', function (string) {
                    this.editor.insert(string);
                    this.clearView('dialog');
                }, this);
            }, this);
        }

    });
});

