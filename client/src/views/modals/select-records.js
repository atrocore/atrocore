

Espo.define('views/modals/select-records', ['views/modal', 'search-manager'], function (Dep, SearchManager) {

    return Dep.extend({

        cssName: 'select-modal',

        multiple: false,

        header: false,

        template: 'modals/select-records',

        createButton: true,

        searchPanel: true,

        scope: null,

        noCreateScopeList: ['User', 'Team', 'Role', 'Portal'],

        className: 'dialog dialog-record',

        data: function () {
            return {
                createButton: this.createButton,
                createText: this.translate('Create ' + this.scope, 'labels', this.scope)
            };
        },

        events: {
            'click button[data-action="create"]': function () {
        this.create();
            },
            'click .list a': function (e) {
                e.preventDefault();
            }
        },

        setup: function () {
            this.filters = this.options.filters || {};
            this.boolFilterList = this.options.boolFilterList || [];
            this.primaryFilterName = this.options.primaryFilterName || null;

            if ('multiple' in this.options) {
                this.multiple = this.options.multiple;
            }

            if ('createButton' in this.options) {
                this.createButton = this.options.createButton;
            }

            this.massRelateEnabled = this.options.massRelateEnabled;

            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            if (this.multiple) {
                this.buttonList.unshift({
                    name: 'select',
                    style: 'primary',
                    label: 'Select',
                    disabled: true,
                    onClick: function (dialog) {
                        var listView = this.getView('list');

                        if (listView.allResultIsChecked) {
                            var where = this.collection.where;
                            this.trigger('select', {
                                massRelate: true,
                                where: where
                            });
                        } else {
                            var list = listView.getSelected();
                            if (list.length) {
                                this.trigger('select', list);
                            }
                        }
                        dialog.close();
                    }.bind(this),
                });
            }

            this.scope = this.entityType = this.options.scope || this.scope;

            if (this.noCreateScopeList.indexOf(this.scope) !== -1) {
                this.createButton = false;
            }

            if (this.createButton) {
                if (
                    !this.getAcl().check(this.scope, 'create')
                    ||
                    this.getMetadata().get(['clientDefs', this.scope, 'createDisabled'])
                ) {
                    this.createButton = false;
                }
            }

            this.header = '';
            var iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);
            this.header += this.getLanguage().translate(this.scope, 'scopeNamesPlural');
            this.header = iconHtml + this.header;

            this.waitForView('list');
            if (this.searchPanel) {
                this.waitForView('search');
            }

            this.getCollectionFactory().create(this.scope, function (collection) {
                collection.maxSize = this.getConfig().get('recordsPerPageSmall') || 5;
                this.collection = collection;

                this.defaultSortBy = collection.sortBy;
                this.defaultAsc = collection.asc;

                this.loadSearch();
                this.wait(true);
                this.loadList();
            }, this);

        },

        loadSearch: function () {
            var searchManager = this.searchManager = new SearchManager(this.collection, 'listSelect', null, this.getDateTime());
            searchManager.emptyOnReset = true;
            if (this.filters) {
                searchManager.setAdvanced(this.filters);
            }

            var boolFilterList = this.boolFilterList || this.getMetadata().get('clientDefs.' + this.scope + '.selectDefaultFilters.boolFilterList');
            if (boolFilterList) {
                var d = {};
                boolFilterList.forEach(function (item) {
                    d[item] = true;
                });
                searchManager.setBool(d);
            }
            var primaryFilterName = this.primaryFilterName || this.getMetadata().get('clientDefs.' + this.scope + '.selectDefaultFilters.filter');
            if (primaryFilterName) {
                searchManager.setPrimary(primaryFilterName);
            }

            this.collection.where = searchManager.getWhere();

            if (this.searchPanel) {
                this.createView('search', 'views/record/search', {
                    collection: this.collection,
                    el: this.containerSelector + ' .search-container',
                    searchManager: searchManager,
                    disableSavePreset: true,
                }, function (view) {
                    this.listenTo(view, 'reset', function () {
                        this.collection.sortBy = this.defaultSortBy;
                        this.collection.asc = this.defaultAsc;
                    }, this);
                });
            }
        },

        loadList: function () {
            var viewName = this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.listSelect') ||
                           this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') ||
                           'views/record/list';

            this.createView('list', viewName, {
                collection: this.collection,
                el: this.containerSelector + ' .list-container',
                selectable: true,
                checkboxes: this.multiple,
                massActionsDisabled: true,
                rowActionsView: false,
                layoutName: 'listSmall',
                searchManager: this.searchManager,
                checkAllResultDisabled: !this.massRelateEnabled,
                buttonsDisabled: true,
                skipBuildRows: true
            }, function (view) {
                this.listenToOnce(view, 'select', function (model) {
                    this.trigger('select', model);
                    this.close();
                }.bind(this));

                if (this.multiple) {
                    this.listenTo(view, 'check', function () {
                        if (view.checkedList.length) {
                            this.enableButton('select');
                        } else {
                            this.disableButton('select');
                        }
                    }, this);
                    this.listenTo(view, 'select-all-results', function () {
                        this.enableButton('select');
                    }, this);
                }

                if (this.options.forceSelectAllAttributes || this.forceSelectAllAttributes) {
                    this.listenToOnce(view, 'after:build-rows', function () {
                        this.wait(false);
                    }, this);
                    this.collection.fetch();
                } else {
                    view.getSelectAttributeList(function (selectAttributeList) {
                        if (!~selectAttributeList.indexOf('name')) {
                            selectAttributeList.push('name');
                        }

                        var mandatorySelectAttributeList = this.options.mandatorySelectAttributeList || this.mandatorySelectAttributeList || [];
                        mandatorySelectAttributeList.forEach(function (attribute) {
                            if (!~selectAttributeList.indexOf(attribute)) {
                                selectAttributeList.push(attribute);
                            }
                        }, this);

                        if (selectAttributeList) {
                            this.collection.data.select = selectAttributeList.join(',');
                        }
                        this.listenToOnce(view, 'after:build-rows', function () {
                            this.wait(false);
                        }, this);
                        this.collection.fetch();
                    }.bind(this));
                }
            });
        },

        create: function () {
            var self = this;

            this.notify('Loading...');
            this.createView('quickCreate', 'views/modals/edit', {
                scope: this.scope,
                fullFormDisabled: true,
                attributes: this.options.createAttributes,
            }, function (view) {
                view.once('after:render', function () {
                    self.notify(false);
                });
                view.render();

                self.listenToOnce(view, 'leave', function () {
                    view.close();
                    self.close();
                });
                self.listenToOnce(view, 'after:save', function (model) {
                    view.close();
                    self.trigger('select', model);
                    setTimeout(function () {
                        self.close();
                    }, 10);

                }.bind(this));
            });
        },
    });
});

