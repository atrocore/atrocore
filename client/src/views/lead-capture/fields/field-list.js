
Espo.define('views/lead-capture/fields/field-list', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        setupOptions: function () {
            this.params.options = [];
            this.translatedOptions = {};
            var fields = this.getMetadata().get(['entityDefs', 'Lead', 'fields']) || {};
            var ignoreFieldList = this.getMetadata().get(['entityDefs', 'LeadCapture', 'fields', 'fieldList', 'ignoreFieldList']) || [];
            for (var field in fields) {
                var defs = fields[field];
                if (defs.disabled) continue;
                if (defs.readOnly) continue;
                if (~ignoreFieldList.indexOf(field)) continue;
                this.params.options.push(field);
                this.translatedOptions[field] = this.translate(field, 'fields', 'Lead');
            }
        }
    });
});
