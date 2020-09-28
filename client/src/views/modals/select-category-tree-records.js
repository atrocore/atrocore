

Espo.define('views/modals/select-category-tree-records', 'views/modals/select-records', function (Dep) {

    return Dep.extend({

        setup: function () {
            this.filters = this.options.filters || {};
            this.boolFilterList = this.options.boolFilterList || {};
            this.primaryFilterName = this.options.primaryFilterName || null;

            if ('multiple' in this.options) {
                this.multiple = this.options.multiple;
            }

            this.createButton = false;
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

            this.scope = this.options.scope;

            this.header = this.getLanguage().translate(this.scope, 'scopeNamesPlural');

            this.waitForView('list');

            Espo.require('search-manager', function (SearchManager) {
                this.getCollectionFactory().create(this.scope, function (collection) {

                    collection.maxSize = this.getConfig().get('recordsPerPageSmall') || 5;

                    this.collection = collection;

                    var searchManager = new SearchManager(collection, 'listSelect', null, this.getDateTime());
                    searchManager.emptyOnReset = true;
                    if (this.filters) {
                        searchManager.setAdvanced(this.filters);
                    }
                    if (this.boolFilterList) {
                        searchManager.setBool(this.boolFilterList);
                    }
                    if (this.primaryFilterName) {
                        searchManager.setPrimary(this.primaryFilterName);
                    }

                    collection.where = searchManager.getWhere();
                    collection.url = collection.name + '/action/listTree';

                    var viewName = this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.listSelectCategoryTree') ||
                                   'views/record/list-tree';

                    this.listenToOnce(collection, 'sync', function () {
                        this.createView('list', viewName, {
                            collection: collection,
                            el: this.containerSelector + ' .list-container',
                            createDisabled: true,
                            selectable: true,
                            checkboxes: this.multiple,
                            massActionsDisabled: true,
                            searchManager: searchManager,
                            checkAllResultDisabled: true,
                            buttonsDisabled: true
                        }, function (list) {
                            list.once('select', function (model) {
                                this.trigger('select', model);
                                this.close();
                            }.bind(this));
                        }, this);
                    }, this);

                    collection.fetch();

                }, this);
            }.bind(this));
        },

    });
});

