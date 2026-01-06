Espo.define('views/dashlets/entities', 'views/dashlets/abstract/base', function (Dep) {

    return Dep.extend({

        name: 'Entities',

        template: 'dashlets/record-list/entities',

        data: function () {
            return {
                entities: this.getEntities()
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.getPreferences(), 'favorites:update', () => {
                if (this.getOption('entityListType') === 'favorites') {
                    this.reRender();
                }
            });
        },

        getEntities: function () {
            const type = this.getOption('entityListType');

            let list = [];

            if (type === 'favorites') {
                list = (this.getPreferences().get('favoritesList') || []).filter(i => this.getAcl().check(i, 'read'));
            } else if (type === 'navigation') {
                (this.getPreferences().get('lpNavigation') || []).forEach(i => {
                    if (typeof i === 'string') {
                        list.push(i);
                    } else if (i.items && Array.isArray(i.items)) {
                        i.items.forEach(j => list.push(j));
                    }
                });

                list = list.filter(i => this.getAcl().check(i, 'read'));
            } else {
                const entries = Object.entries(this.getMetadata().get('scopes'))
                list = entries.filter(([key, defs]) => this.getAcl().check(key, 'read') && defs.tab === true && defs.type !== 'Relation').map(([key, _]) => key);
            }

            return list.map(key => ({
                name: key,
                icon: this.getTabIcon(key) || this.getDefaultTabIcon(key)
            }))
        }

    });
});


