/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

Espo.define('views/stream/panel', ['views/record/panels/relationship', 'lib!TextComplete'], function (Dep, Lib) {

    return Dep.extend({

        template: 'stream/panel',

        postingMode: false,

        postDisabled: false,

        events: _.extend({
            'click button.post': function () {
                this.post();
            }
        }, Dep.prototype.events),

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.postDisabled = this.postDisabled;
            data.placeholderText = this.placeholderText;
            return data;
        },

        enablePostingMode: function () {
            this.$el.find('.buttons-panel').removeClass('hide');

            if (!this.postingMode) {
                $('body').on('click.stream-panel', function (e) {
                    var $target = $(e.target);
                    if ($target.parent().hasClass('remove-attachment')) return;
                    if ($.contains(this.$postContainer.get(0), e.target)) return;
                    if ((this.seed.get('post') ?? '') !== '') return;
                }.bind(this));
            }

            this.postingMode = true;
        },

        disablePostingMode: function () {
            this.postingMode = false;

            this.seed.set('post', '');
            if (this.hasView('attachments')) {
                this.getView('attachments').empty();
            }
            this.$el.find('.buttons-panel').addClass('hide');

            $('body').off('click.stream-panel');
        },

        setup: function () {
            this.title = this.translate('Stream');

            this.scope = this.model.name;

            this.filter = this.getStoredFilter();

            this.placeholderText = this.translate('writeYourCommentHere', 'messages');

            this.storageTextKey = 'stream-post-' + this.model.name + '-' + this.model.id;
            this.storageAttachmentsKey = 'stream-post-attachments-' + this.model.name + '-' + this.model.id;

            this.on('remove', function () {
                this.storeControl();
                $(window).off('beforeunload.stream-' + this.cid);
            }, this);
            $(window).off('beforeunload.stream-' + this.cid);
            $(window).on('beforeunload.stream-' + this.cid, function () {
                this.storeControl();
            }.bind(this));

            var storedAttachments = this.getSessionStorage().get(this.storageAttachmentsKey);

            this.wait(true);
            this.getModelFactory().create('Note', function (model) {
                this.seed = model;
                if (storedAttachments) {
                    this.hasStoredAttachments = true;
                    this.seed.set({
                        attachmentsIds: storedAttachments.idList,
                        attachmentsNames: storedAttachments.names
                    });
                }
                this.createCollection(function () {
                    this.wait(false);
                }, this);
            }, this);

            this.listenTo(this.model, 'sync', () => {
                this.reRender();
            });
        },

        storeControl: function () {
            if (this.seed) {
                const text = this.seed.get('post') ?? '';
                if (text.length) {
                    this.getSessionStorage().set(this.storageTextKey, text);
                } else {
                    if (this.hasStoredText) {
                        this.getSessionStorage().clear(this.storageTextKey);
                    }
                }

                const attachmentIdList = this.seed.get('attachmentsIds') || [];

                if (attachmentIdList.length) {
                    this.getSessionStorage().set(this.storageAttachmentsKey, {
                        idList: attachmentIdList,
                        names: this.seed.get('attachmentsNames') || {}
                    });
                } else {
                    if (this.hasStoredAttachments) {
                        this.getSessionStorage().clear(this.storageAttachmentsKey);
                    }
                }
            }
        },

        createCollection: function (callback, context) {
            this.getCollectionFactory().create('Note', function (collection) {
                this.collection = collection;
                collection.url = this.model.name + '/' + this.model.id + '/stream';
                collection.sortBy = 'createdAt';
                collection.asc = false;
                collection.maxSize = this.getConfig().get('recordsPerPageSmall') || 5;
                this.setFilter(this.filter);

                callback.call(context);
            }, this);
        },

        afterRender: function () {
            const streamAllowed = this.getAcl().checkModel(this.model, 'stream', true);
            if (!streamAllowed) {
                this.$el.parent().hide();
                return;
            } else {
                this.$el.parent().show();
            }

            this.$postContainer = this.$el.find('.post-container');

            var storedText = this.getSessionStorage().get(this.storageTextKey);

            if (storedText && storedText.length) {
                this.hasStoredText = true;
                this.seed.set('post', storedText);
            }

            const assignmentPermission = this.getAcl().get('assignmentPermission');
            const buildUserListUrl = function (term) {
                let url = 'User?orderBy=name&limit=7&q=' + term + '&' + $.param({'primaryFilter': 'active'});
                if (assignmentPermission === 'team') {
                    url += '&' + $.param({'boolFilterList': ['onlyMyTeam']})
                }
                return url;
            }.bind(this);

            this.createView('post', 'views/fields/markdown', {
                defs: {
                    name: 'post'
                },
                el: this.options.el + ' .text-container',
                mode: 'edit',
                model: this.seed,
                params: {
                    maxHeight: 350,
                    minHeight: 40
                },
            }, function (view) {
                view.on('before:editor:rendered', textarea => {
                    textarea.attr('placeholder', this.placeholderText);
                });

                view.on('editor:rendered', editor => {
                    if (assignmentPermission !== 'no') {
                        const cmWrapper = new Lib.CodeMirrorEditor(editor.codemirror);
                        const textcomplete = new Lib.Textcomplete(cmWrapper, [{
                            match: /(^|\s)@((\w|\.)*)$/,
                            search: function (term, callback, match) {
                                if (!match[2]) {
                                    callback([]);
                                    return;
                                }
                                $.ajax({
                                    url: buildUserListUrl(match[2])
                                }).done(function (data) {
                                    callback(data.list)
                                });
                            },
                            template: function (mention) {
                                return mention.name + ' <span class="text-muted">@' + mention.userName + '</span>';
                            },
                            replace: function (o) {
                                return '$1@' + o.userName + ' ';
                            }
                        }], {
                            dropdown: {
                                className: "dropdown-menu textcomplete-dropdown",
                                maxCount: 7,
                                placement: "auto",
                                style: {zIndex: 1100},
                                item: {
                                    className: "textcomplete-item",
                                    activeClassName: "textcomplete-item active",
                                }
                            },
                        });

                        this.once('remove', function () {
                            textcomplete?.destroy();
                        }, this);
                    }
                });

                view.on('focus', (editor, e) => {
                    this.enablePostingMode();
                });

                view.on('editor:keypress', (editor, e) => {
                    if ((e.keyCode === 10 || e.keyCode === 13) && e.ctrlKey) {
                        this.post();
                    } else if (e.keyCode === 9 && !this.seed.get('post')) {
                        this.disablePostingMode();
                    }
                });

                view.render();
            });

            var collection = this.collection;

            this.listenToOnce(collection, 'sync', function () {
                this.createView('list', 'views/stream/record/list', {
                    el: this.options.el + ' .list-container',
                    collection: collection,
                    model: this.model
                }, function (view) {
                    view.render();
                });

                this.stopListening(this.model, 'all');
                this.stopListening(this.model, 'destroy');
                setTimeout(function () {
                    this.listenTo(this.model, 'all', function (event) {
                        if (!~['sync', 'after:relate'].indexOf(event)) return;
                        collection.fetchNew();
                    }, this);

                    this.listenTo(this.model, 'destroy', function () {
                        this.stopListening(this.model, 'all');
                    }, this);
                }.bind(this), 500);

            }, this);
            if (!this.defs.hidden) {
                collection.fetch();
            }

            $a = this.$el.find('.buttons-panel a.stream-post-info');

            $a.popover({
                placement: 'bottom',
                container: 'body',
                content: this.translate('streamPostInfo', 'messages').replace(/(\r\n|\n|\r)/gm, '<br>'),
                trigger: 'click',
                html: true
            }).on('shown.bs.popover', function () {
                $('body').one('click', function () {
                    $a.popover('hide');
                });
            });

            this.createView('attachments', 'views/fields/link-multiple', {
                model: this.seed,
                mode: 'edit',
                el: this.options.el + ' div.attachments-container',
                foreignScope: "File",
                placeholder: this.translate("attachFiles"),
                defs: {
                    name: 'attachments',
                    entityModel: this.model
                },
            }, function (view) {
                view.render();
            });
        },

        post: function () {
            const message = this.seed.get('post') ?? '';

            this.getModelFactory().create('Note', function (model) {
                if (message === '' && (this.seed.get('attachmentsIds') || []).length === 0) {
                    this.notify('Post cannot be empty', 'error');
                    return;
                }

                this.listenToOnce(model, 'sync', function () {
                    this.notify('Posted', 'success');
                    this.collection.fetchNew();

                    this.disablePostingMode();

                    if (this.getPreferences().get('followEntityOnStreamPost')) {
                        this.model.set('isFollowed', true);
                    }

                    this.getSessionStorage().clear(this.storageTextKey);
                    this.getSessionStorage().clear(this.storageAttachmentsKey);
                }, this);

                model.set('post', message);
                model.set('attachmentsIds', Espo.Utils.clone(this.seed.get('attachmentsIds') || []));
                model.set('type', 'Post');

                this.prepareNoteForPost(model);

                this.notify('Posting...');
                model.save();
            }.bind(this));
        },

        prepareNoteForPost: function (model) {
            model.set('parentId', this.model.id);
            model.set('parentType', this.model.name);
        },

        getButtonList: function () {
            return [];
        },

        filterList: ['all', 'posts', 'updates'],

        getActionList: function () {
            var list = [];
            this.filterList.forEach(function (item) {
                var selected = false;
                if (item == 'all') {
                    selected = !this.filter;
                } else {
                    selected = item === this.filter;
                }
                list.push({
                    action: 'selectFilter',
                    html: '<span class="check-icon fas fa-check pull-right' + (!selected ? ' hidden' : '') + '"></span><div>' + this.translate(item, 'filters', 'Note') + '</div>',
                    data: {
                        name: item
                    }
                });
            }, this);
            return list;
        },

        getStoredFilter: function () {
            return this.getStorage().get('state', 'streamPanelFilter' + this.scope) || null;
        },

        storeFilter: function (filter) {
            if (filter) {
                this.getStorage().set('state', 'streamPanelFilter' + this.scope, filter);
            } else {
                this.getStorage().clear('state', 'streamPanelFilter' + this.scope);
            }
        },

        setFilter: function (filter) {
            this.collection.data.filter = null;
            if (filter) {
                this.collection.data.filter = filter;
            }
        },

        actionSelectFilter: function (data) {
            var filter = data.name;
            var filterInternal = filter;
            if (filter == 'all') {
                filterInternal = false;
            }
            this.storeFilter(filterInternal);
            this.setFilter(filterInternal);

            this.filterList.forEach(function (item) {
                var $el = this.$el.closest('.panel').find('[data-name="' + item + '"] span');
                if (item === filter) {
                    $el.removeClass('hidden');
                } else {
                    $el.addClass('hidden');
                }
            }, this);
            this.collection.reset();
            this.collection.fetch();
        },

        actionRefresh: function () {
            if (this.hasView('list')) {
                this.getView('list').showNewRecords();
            }
        },

    });
});

