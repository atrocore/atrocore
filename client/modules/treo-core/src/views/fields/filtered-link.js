

Espo.define('treo-core:views/fields/filtered-link', 'views/fields/link',
    Dep => Dep.extend({

        selectBoolFilterList:  [],

        boolFilterData: {},

        getBoolFilterData() {
            let data = {};
            this.selectBoolFilterList.forEach(item => {
                if (typeof this.boolFilterData[item] === 'function') {
                    data[item] = this.boolFilterData[item].call(this);
                }
            });
            return data;
        },

        setup() {
            Dep.prototype.setup.call(this);

            if (this.mode != 'list') {
                this.addActionHandler('selectLink', function () {
                    this.notify('Loading...');

                    let viewName = this.getMetadata().get('clientDefs.' + this.foreignScope + '.modalViews.select') || this.selectRecordsView;

                    this.createView('dialog', viewName, {
                        scope: this.foreignScope,
                        createButton: !this.createDisabled && this.mode != 'search',
                        filters: this.getSelectFilters(),
                        boolFilterList: this.getSelectBoolFilterList(),
                        boolFilterData: this.getBoolFilterData(),
                        primaryFilterName: this.getSelectPrimaryFilterName(),
                        createAttributes: (this.mode === 'edit') ? this.getCreateAttributes() : null,
                        mandatorySelectAttributeList: this.mandatorySelectAttributeList,
                        forceSelectAllAttributes: this.forceSelectAllAttributes
                    }, function (view) {
                        view.render();
                        this.notify(false);
                        this.listenToOnce(view, 'select', function (model) {
                            this.clearView('dialog');
                            this.select(model);
                        }, this);
                    }, this);
                });
            }

            if (this.mode == 'search') {
                this.addActionHandler('selectLinkOneOf', function () {
                    this.notify('Loading...');

                    let viewName = this.getMetadata().get('clientDefs.' + this.foreignScope + '.modalViews.select') || this.selectRecordsView;

                    this.createView('dialog', viewName, {
                        scope: this.foreignScope,
                        createButton: !this.createDisabled && this.mode != 'search',
                        filters: this.getSelectFilters(),
                        boolFilterList: this.getSelectBoolFilterList(),
                        boolFilterData: this.getBoolFilterData(),
                        primaryFilterName: this.getSelectPrimaryFilterName(),
                        multiple: true
                    }, function (view) {
                        view.render();
                        this.notify(false);
                        this.listenToOnce(view, 'select', function (models) {
                            this.clearView('dialog');
                            if (Object.prototype.toString.call(models) !== '[object Array]') {
                                models = [models];
                            }
                            models.forEach(function (model) {
                                this.addLinkOneOf(model.id, model.get('name'));
                            }, this);
                        });
                    }, this);
                });
            }
        },


        getAutocompleteUrl() {
            var url = Dep.prototype.getAutocompleteUrl.call(this);
            var boolData = this.getBoolFilterData();
            // add boolFilter data
            if (boolData) {
                url += '&' + $.param({'where':[{
                        'type': 'bool',
                        'data': boolData
                    }]});
            }

            return url;
        }

    })
);

