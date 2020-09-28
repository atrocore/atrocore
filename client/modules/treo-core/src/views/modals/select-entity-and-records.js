

Espo.define('treo-core:views/modals/select-entity-and-records', 'views/modals/select-records',
    Dep => Dep.extend({

        template: 'treo-core:modals/select-entity-and-records',

        selectBoolFilterList: [],

        selectBoolFilterData: {},

        getSelectFilters() {
            //leave empty
        },

        getSelectBoolFilterList() {
            const mainEntity = this.model.get('mainEntity');
            const selectedLink = this.model.get('selectedLink');
            const keyPath = ['clientDefs', mainEntity, 'relationshipPanels', selectedLink, 'selectBoolFilterList'];

            return this.selectBoolFilterList = this.getMetadata().get(keyPath) || [];
        },

        getSelectBoolFilterData() {
            let data = {};
            this.selectBoolFilterList.forEach(item => {
                if (typeof this.selectBoolFilterData[item] === 'function') {
                    data[item] = this.selectBoolFilterData[item].call(this);
                }
            });

            return data;
        },

        getSelectPrimaryFilterName() {
            //leave empty
        },

        setup() {
            // this.updateBoolParams();

            Dep.prototype.setup.call(this);

            this.buttonList.find(button => button.name === 'select').label = 'applyRelation';
            this.header = this.getLanguage().translate(this.options.type, 'massActions', 'Global');

            this.waitForView('selectedLink');
            this.createSelectedLinkView();

            this.listenTo(this.model, 'change:selectedLink', model => {
                this.reloadList(model.get('selectedLink'));
            });

            if (this.multiple) {
                let selectButton = this.buttonList.find(button => button.name === 'select');
                selectButton.onClick = dialog => {
                    if (this.validate()) {
                        this.notify('Not valid', 'error');
                        return;
                    }

                    let listView = this.getView('list');
                    if (listView.allResultIsChecked) {
                        let where = this.collection.where;
                        this.trigger('select', {
                            massRelate: true,
                            where: where
                        });
                    } else {
                        let list = listView.getSelected();
                        if (list.length) {
                            this.trigger('select', list);
                        }
                    }
                    dialog.close();
                };
            }

            this.listenTo(this, 'select', models => {
                if (this.validate()) {
                    this.notify('Not valid', 'error');
                    return;
                }

                const foreignIds = (models || []).map(model => model.id);
                const data = this.getDataForUpdateRelation(foreignIds, this.model);
                const url = `${this.model.get('mainEntity')}/${this.model.get('selectedLink')}/relation`;
                this.sendDataForUpdateRelation(url, data);
            });
        },

        setupPipelines() {
            const mainEntity = this.model.get('mainEntity');
            const selectedLink = this.model.get('selectedLink');
            const keyPath = ['clientDefs', mainEntity, 'addRelationPipelines', selectedLink];

            const pipelines = this.getMetadata().get(keyPath);

            if (pipelines) {
                this.pipelines = _.extend({}, this.pipelines, {
                    actionUpdateFilters: keyPath || []
                });
            }
        },

        updateBoolParams(callback) {
            this.filters = this.options.filters = this.getSelectFilters();
            this.boolFilterList = this.options.boolFilterList = this.getSelectBoolFilterList();
            this.boolFilterData = this.options.boolFilterData = this.getSelectBoolFilterData();
            this.primaryFilterName = this.options.primaryFilterName = this.getSelectPrimaryFilterName();

            this.setupPipelines();

            if (Object.keys(this.pipelines || {}).length) {
                this.runPipeline('actionUpdateFilters', {
                    filters: this.filters,
                    boolFilterList: this.boolFilterList,
                    boolFilterData: this.boolFilterData,
                    primaryFilterName: this.primaryFilterName,
                    callback: callback  //required
                });
            } else {
                callback();
            }
        },

        loadSearch() {
            this.updateBoolParams(() => Dep.prototype.loadSearch.call(this));
        },

        loadList() {
            Dep.prototype.loadList.call(this);

            this.listenToOnce(this.collection, 'sync', () => this.notify(false));
        },

        getDataForUpdateRelation(foreignIds, viewModel) {
            return {
                ids: this.options.checkedList,
                foreignIds: foreignIds
            }
        },

        sendDataForUpdateRelation(url, data) {
            if (this.options.type === 'addRelation') {
                this.ajaxPostRequest(url, data).then(response => {
                    this.notify('Linked', 'success');
                });
            } else if (this.options.type === 'removeRelation') {
                data = JSON.stringify(data);
                this.ajaxRequest(url, 'DELETE', data).then(response => {
                    this.notify('Unlinked', 'success');
                });
            }
        },

        createSelectedLinkView() {
            let options = [];
            let translatedOptions = {};
            this.model.get('foreignEntities').forEach(entityDefs => {
                let link = entityDefs.link;
                options.push(link);
                let translation = this.translate(link, 'links', this.model.get('mainEntity'));
                if (entityDefs.addRelationCustomDefs) {
                    translation = this.translate(entityDefs.addRelationCustomDefs.link, 'links', this.model.get('mainEntity'));
                }
                translatedOptions[link] = translation;
            });

            this.createView('selectedLink', 'views/fields/enum', {
                model: this.model,
                el: `${this.options.el} .entity-container .field[data-name="selectedLink"]`,
                defs: {
                    name: 'selectedLink',
                    params: {
                        options: options,
                        translatedOptions: translatedOptions
                    }
                },
                mode: 'edit'
            }, view => {
            });
        },

        getEntityFromSelectedLink() {
            let selectedLink = this.model.get('selectedLink');
            let entityDefs = (this.model.get('foreignEntities') || []).find(item => item.link === selectedLink) || {};
            return entityDefs.addRelationCustomDefs ? entityDefs.addRelationCustomDefs.entity : entityDefs.entity;
        },

        reloadList(selectedLink) {
            if (!selectedLink) {
                return;
            }

            this.notify('Loading...');
            let entity = this.getEntityFromSelectedLink();
            this.scope = entity;
            this.collection.name = this.collection.urlRoot = this.collection.url = entity;
            let collectionDefs = (this.getMetadata().get(['entityDefs', entity, 'collection']) || {});
            this.collection.sortBy = collectionDefs.sortBy;
            this.collection.asc = collectionDefs.asc;
            this.getModelFactory().getSeed(entity, seed => this.collection.model = seed);

            this.loadSearch();
            this.loadList();
        },

        validate: function () {
            let notValid = false;
            let fields = this.getFieldViews();
            for (let i in fields) {
                if (fields[i].mode === 'edit') {
                    if (!fields[i].disabled && !fields[i].readOnly) {
                        notValid = fields[i].validate() || notValid;
                    }
                }
            }
            return notValid
        },

        getFieldViews() {
            return {};
        },

        close() {
            if (this.validate()) {
                return;
            }

            Dep.prototype.close.call(this);
        }
    })
);

