

Espo.define('treo-core:views/settings/fields/tab-list', 'class-replace!treo-core:views/settings/fields/tab-list',
    Dep => Dep.extend({

        setup() {
            this.prepareDefaultTabList();

            Dep.prototype.setup.call(this);
        },

        prepareDefaultTabList() {
            let tabList = (Espo.Utils.cloneDeep(this.model.get(this.name)) || []).filter(tab => typeof tab !== 'object');
            this.model.set({[this.name]: tabList});
        },

    })
);
