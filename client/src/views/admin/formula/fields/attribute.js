

Espo.define('views/admin/formula/fields/attribute', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        setupOptions: function () {
            Dep.prototype.setupOptions.call(this);

            var attributeList = this.getFieldManager().getEntityAttributeList(this.options.scope).sort();

            var links = this.getMetadata().get(['entityDefs', this.options.scope, 'links']);
            var linkList = [];
            Object.keys(links).forEach(function (link) {
                var type = links[link].type;
                if (!type) return;

                if (~['belongsToParent', 'hasOne', 'belongsTo'].indexOf(type)) {
                    linkList.push(link);
                }
            }, this);
            linkList.sort();
            linkList.forEach(function (link) {
                var scope = links[link].entity;
                if (!scope) return;
                var linkAttributeList = this.getFieldManager().getEntityAttributeList(scope).sort();
                linkAttributeList.forEach(function (item) {
                    attributeList.push(link + '.' + item);
                }, this);
            }, this);

            this.params.options = attributeList;
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (this.$element && this.$element[0] && this.$element[0].selectize) {
                this.$element[0].selectize.focus();
            }
        }

    });

});

