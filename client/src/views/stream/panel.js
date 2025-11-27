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

        header: 'views/stream/header',

        events: _.extend({
            'click button.post': function () {
                this.post();
            }
        }, Dep.prototype.events),

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.postDisabled = this.postDisabled || this.mode !== 'detail';
            data.placeholderText = this.placeholderText;
            return data;
        },

        getFilterList: function () {
            return this.getMetadata().get(['scopes', this.scope, 'filterInNote']) || ['posts', 'updates']
        },


        disablePostingMode: function () {
            this.seed.set('post', '');
            if (this.hasView('attachments')) {
                this.getView('attachments').empty();
            }
        },

        setup: function () {
            this.title = this.translate('Activities');

            this.scope = this.options.defs.scope ?? this.model.name;

            this.filter = this.getStoredFilter();

            this.placeholderText = this.translate('writeYourCommentHere', 'messages');

            this.storageTextKey = 'stream-post-' + this.scope + '-' + this.model?.id;
            this.storageAttachmentsKey = 'stream-post-attachments-' + this.scope + '-' + this.model?.id;

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
                    this.listenToOnce(this.collection, 'sync', function () {
                        this.createView('list', 'views/stream/record/list', {
                            el: this.options.el + ' .list-container',
                            collection: this.collection,
                            model: this.model,
                            isUserStream: !this.model
                        }, function (view) {
                            if (this.isRendered()) {
                                view.render();
                            } else {
                                this.once('after:render', () => {
                                    view.render();
                                });
                            }
                        });

                    }, this);

                    if (!this.defs.hidden) {
                        this.fetchCollection();
                    }
                    this.wait(false);
                }, this);
            }, this);

            this.listenTo(this.model, 'sync', () => {
                this.reRender();
            });

            this.setupFilter();
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
                if (this.mode === 'detail') {
                    collection.url = this.model.name + '/' + this.model.id + '/stream';
                } else {
                    collection.url = 'Stream' + '/' + this.scope
                }

                collection.asc = false;
                collection.maxSize = this.getConfig().get('recordsPerPageSmall') || 5;
                this.setFilter(this.filter);

                callback.call(context);
            }, this);
        },

        afterRender: function () {

            this.$postContainer = this.$el.find('.post-container');

            var storedText = this.getSessionStorage().get(this.storageTextKey);

            if (storedText && storedText.length) {
                this.hasStoredText = true;
                this.seed.set('post', storedText);
            }

            const assignmentPermission = this.getAcl().get('assignmentPermission');
            const buildUserListUrl = function (term) {
                let url = 'User?orderBy=name&limit=7&q=' + term + '&' + $.param({ 'primaryFilter': 'active' });
                if (assignmentPermission === 'team') {
                    url += '&' + $.param({ 'boolFilterList': ['onlyMyTeam'] })
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
                                style: { zIndex: 1100 },
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

                view.on('editor:keypress', (editor, e) => {
                    if ((e.keyCode === 10 || e.keyCode === 13) && e.ctrlKey) {
                        this.post();
                    } else if (e.keyCode === 9 && !this.seed.get('post')) {
                        this.disablePostingMode();
                    }
                });

                view.render();
            });

            this.stopListening(this.model, 'all');
            this.stopListening(this.model, 'destroy');
            setTimeout(function () {
                this.listenTo(this.model, 'all', function (event) {
                    if (!~['sync', 'after:relate'].indexOf(event)) return;
                    this.collection.fetchNew();
                }, this);

                this.listenTo(this.model, 'destroy', function () {
                    this.stopListening(this.model, 'all');
                }, this);
            }.bind(this), 500);

            if (!this.getStoredFilter().length) {
                this.$el.find('.list-container').html('<span >No Data</span>')
            }

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
            model.set('parentId', this.model?.id);
            model.set('parentType', this.scope ?? this.model.name);
        },

        getButtonList: function () {
            return [];
        },


        getStoredFilter: function () {
            let lists = this.getStorage().get('state', 'streamPanelFilter') || this.getFilterList();
            return lists.filter(v => this.getFilterList().includes(v));
        },

        storeFilter: function (filter) {
            if (filter) {
                this.getStorage().set('state', 'streamPanelFilter', filter);
            } else {
                this.getStorage().clear('state', 'streamPanelFilter');
            }
        },

        setFilter: function (filter) {
            this.collection.data.filter = null;
            if (filter) {
                this.collection.data.filter = filter;
            }
        },

        setupFilter: function () {
            this.createView('streamHeader', this.header, {
                el: this.options.el + ' .header',
                scope: this.scope,
                model: this.model,
                mode: this.options.mode,
                filterList: this.getFilterList(),
                activeFilters: this.getStoredFilter(),
                collection: this.collection,
            }, view => {
                this.listenTo(view, 'filter-update', (activeFilter) => {
                    this.storeFilter(activeFilter);
                    this.setFilter(activeFilter);
                    this.collection.abortLastFetch();
                    this.fetchCollection();
                })
            })
        },

        actionRefresh: function () {
            if (this.hasView('list')) {
                this.getView('list').showNewRecords();
            }
        },

        fetchCollection() {
            if (this.getStoredFilter().length) {
                this.collection.reset();
                this.collection.fetch();
            }
        }
    });
});

