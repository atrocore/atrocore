

Espo.define('views/export/record/record', 'views/record/base', function (Dep) {

    return Dep.extend({

        template: 'export/record/record',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.scope = this.options.scope;

            var fieldList = this.getFieldManager().getScopeFieldList(this.scope);

            var forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.scope);

            fieldList = fieldList.filter(function (item) {
                return !~forbiddenFieldList.indexOf(item);
            }, this);


            fieldList = fieldList.filter(function (item) {
                var defs = this.getMetadata().get(['entityDefs', this.scope, 'fields', item]) || {};
                if (defs.disabled) return;
                if (defs.exportDisabled) return;
                if (defs.type === 'map') return;
                if (defs.type === 'attachmentMultiple') return;

                return true;
            }, this);

            this.getLanguage().sortFieldList(this.scope, fieldList);

            fieldList.unshift('id');

            var translatedOptions = {};
            fieldList.forEach(function (item) {
                translatedOptions[item] = this.getLanguage().translate(item, 'fields', this.scope);
            }, this);

            this.createField('exportAllFields', 'views/fields/bool', {
            });

            var setFieldList = this.model.get('fieldList') || [];
            setFieldList.forEach(function (item) {
                if (~fieldList.indexOf(item)) return;
                if (!~item.indexOf('_')) return;

                var arr = item.split('_');

                fieldList.push(item);

                var foreignScope = this.getMetadata().get(['entityDefs', this.scope, 'links', arr[0], 'entity']);
                if (!foreignScope) return;
                translatedOptions[item] = this.getLanguage().translate(arr[0], 'links', this.scope) + '.' + this.getLanguage().translate(arr[1], 'fields', foreignScope);
            }, this);


            this.createField('fieldList', 'views/fields/multi-enum', {
                required: true,
                translatedOptions: translatedOptions,
                options: fieldList
            });

            this.createField('format', 'views/fields/enum', {
                options: this.getMetadata().get('app.export.formatList')
            });

            this.controlAllFields();
            this.listenTo(this.model, 'change:exportAllFields', function () {
                this.controlAllFields();
            }, this);
        },

        controlAllFields: function () {
            if (!this.model.get('exportAllFields')) {
                this.showField('fieldList');
            } else {
                this.hideField('fieldList');
            }
        }

    });

});

