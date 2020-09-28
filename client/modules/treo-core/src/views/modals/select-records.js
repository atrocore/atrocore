

Espo.define('treo-core:views/modals/select-records', ['class-replace!treo-core:views/modals/select-records', 'search-manager'], function (Dep, SearchManager) {

    return Dep.extend({

        boolFilterData: {},

        disableSavePreset: false,

        layoutName: "listSmall",

        listLayout: null,

        searchView: 'views/record/search',

        setup() {
            this.boolFilterData = this.options.boolFilterData || this.boolFilterData;
            this.disableSavePreset = this.options.disableSavePreset || this.disableSavePreset;
            this.layoutName = this.options.layoutName || this.layoutName;
            this.listLayout = this.options.listLayout || this.listLayout;
            this.rowActionsDisabled = this.options.rowActionsDisabled || this.rowActionsDisabled;

            Dep.prototype.setup.call(this);
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

            let where = searchManager.getWhere();
            where.forEach(item => {
                if (item.type === 'bool') {
                    let data = {};
                    item.value.forEach(elem => {
                        if (elem in this.boolFilterData) {
                            data[elem] = this.boolFilterData[elem];
                        }
                    });
                    item.data = data;
                }
            });
            this.collection.where = where;

            this.collection.whereAdditional = this.options.whereAdditional || [];

            if (this.searchPanel) {
                let hiddenBoolFilterList = this.getMetadata().get(`clientDefs.${this.scope}.hiddenBoolFilterList`) || [];
                let searchView = this.getMetadata().get(`clientDefs.${this.scope}.recordViews.search`) || this.searchView;

                this.createView('search', searchView, {
                    collection: this.collection,
                    el: this.containerSelector + ' .search-container',
                    searchManager: searchManager,
                    disableSavePreset: this.disableSavePreset,
                    hiddenBoolFilterList: hiddenBoolFilterList,
                    boolFilterData: this.boolFilterData
                }, function (view) {
                    view.render();
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
                layoutName: this.layoutName,
                searchManager: this.searchManager,
                checkAllResultDisabled: !this.massRelateEnabled,
                buttonsDisabled: true,
                skipBuildRows: true
            }, function (view) {
                this.listenTo(view, 'select', function (model) {
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

    });
});

