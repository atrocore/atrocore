

Espo.define('views/stream/notes/update', 'views/stream/note', function (Dep) {

    return Dep.extend({

        template: 'stream/notes/update',

        messageName: 'update',

        data: function () {
            return _.extend({
                fieldsArr: this.fieldsArr,
                parentType: this.model.get('parentType')
            }, Dep.prototype.data.call(this));
        },

        events: {
            'click a[data-action="expandDetails"]': function (e) {
                if (this.$el.find('.details').hasClass('hidden')) {
                    this.$el.find('.details').removeClass('hidden');
                    $(e.currentTarget).find('span').removeClass('fa-chevron-down').addClass('fa-chevron-up');
                } else {
                    this.$el.find('.details').addClass('hidden');
                    $(e.currentTarget).find('span').addClass('fa-chevron-down').removeClass('fa-chevron-up');
                }
            }
        },

        init: function () {
            if (this.getUser().isAdmin()) {
                this.isRemovable = true;
            }
            Dep.prototype.init.call(this);
        },

        setup: function () {
            var data = this.model.get('data');

            var fields = data.fields;

            this.createMessage();


            this.wait(true);
            this.getModelFactory().create(this.model.get('parentType'), function (model) {
                var modelWas = model;
                var modelBecame = model.clone();

                data.attributes = data.attributes || {};

                modelWas.set(data.attributes.was);
                modelBecame.set(data.attributes.became);

                this.fieldsArr = [];

                fields.forEach(function (field) {
                    var type = model.getFieldType(field) || 'base';
                    var viewName = this.getMetadata().get('entityDefs.' + model.name + '.fields.' + field + '.view') || this.getFieldManager().getViewName(type);
                    this.createView(field + 'Was', viewName, {
                        model: modelWas,
                        readOnly: true,
                        defs: {
                            name: field
                        },
                        mode: 'detail',
                        inlineEditDisabled: true
                    });
                    this.createView(field + 'Became', viewName, {
                        model: modelBecame,
                        readOnly: true,
                        defs: {
                            name: field
                        },
                        mode: 'detail',
                        inlineEditDisabled: true
                    });

                    this.fieldsArr.push({
                        field: field,
                        was: field + 'Was',
                        became: field + 'Became'
                    });

                }, this);

                this.wait(false);

            }, this);
        },

    });
});

