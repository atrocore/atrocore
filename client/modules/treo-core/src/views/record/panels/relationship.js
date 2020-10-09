

Espo.define('treo-core:views/record/panels/relationship', ['class-replace!treo-core:views/record/panels/relationship', 'search-manager'], function (Dep, SearchManager) {

    return Dep.extend({

        filtersLayoutLoaded: false,

        setup() {
            Dep.prototype.setup.call(this);

            this.addReadyCondition(() => {
                return this.filtersLayoutLoaded;
            });

            this.getHelper().layoutManager.get(this.scope, 'filters', layout => {
                this.filtersLayoutLoaded = true;
                let foreign = this.model.getLinkParam(this.link, 'foreign');

                if (foreign && layout.includes(foreign)) {
                    this.actionList.push({
                        label: 'showFullList',
                        action: this.defs.showFullListAction || 'showFullList',
                        data: {
                            modelId: this.model.get('id'),
                            modelName: this.model.get('name')
                        }
                    });
                }

                this.tryReady();
            });
        },

        actionShowFullList(data) {
            let entity = this.model.getLinkParam(this.link, 'entity');
            let foreign = this.model.getLinkParam(this.link, 'foreign');
            let defs = this.getMetadata().get(['entityDefs', entity, 'fields', foreign]) || {};
            let type = defs.type;

            let advanced = {};
            if (type === 'link') {
                advanced = {
                    [foreign]: {
                        type: 'equals',
                        field: foreign + 'Id',
                        value: data.modelId,
                        data: {
                            type: 'is',
                            idValue: data.modelId,
                            nameValue: data.modelName
                        }
                    }
                }
            } else if (type === 'linkMultiple') {
                advanced = {
                    [foreign]: {
                        type: 'linkedWith',
                        value: [data.modelId],
                        nameHash: {[data.modelId]: data.modelName},
                        data: {
                            type: 'anyOf'
                        }
                    }
                }
            }

            let params = {
                showFullListFilter: true,
                advanced: advanced
            };

            this.getRouter().navigate(`#${this.scope}`, {trigger: true});
            this.getRouter().dispatch(this.scope, 'list', params);
        },

        actionUnlinkRelated(data) {
            let id = data.id;

            this.confirm({
                message: this.translate('unlinkRecordConfirmation', 'messages'),
                confirmText: this.translate('Unlink')
            }, () => {
                let model = this.collection.get(id);
                this.notify('Unlinking...');
                $.ajax({
                    url: this.collection.url,
                    type: 'DELETE',
                    data: JSON.stringify({
                        id: id
                    }),
                    contentType: 'application/json',
                    success: () => {
                        this.notify('Unlinked', 'success');
                        this.collection.fetch();
                        this.model.trigger('after:unrelate', this.link);
                    },
                    error: () => {
                        this.notify('Error occurred', 'error');
                    },
                });
            });
        },

        actionRemoveRelated(data) {
            let id = data.id;

            this.confirm({
                message: this.translate('removeRecordConfirmation', 'messages'),
                confirmText: this.translate('Remove')
            }, () => {
                let model = this.collection.get(id);
                this.notify('Removing...');
                model.destroy({
                    success: () => {
                        this.notify('Removed', 'success');
                        this.collection.fetch();
                        this.model.trigger('after:unrelate', this.link);
                    },

                    error: () => {
                        this.collection.push(model);
                    }
                });
            });
        },

    });
});