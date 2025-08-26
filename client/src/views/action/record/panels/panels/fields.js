/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore GmbH.
 *
 * This Software is the property of AtroCore GmbH and is
 * protected by copyright law - it is NOT Freeware and can be used only in one
 * project under a proprietary license, which is delivered along with this program.
 * If not, see <https://atropim.com/eula> or <https://atrodam.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
 */

Espo.define('workflows:views/action/record/panels/fields', 'view',
    Dep => Dep.extend({

        template: 'workflows:action/record/panels/fields',

        mode: 'detail',

        fieldsModel: null,

        enableType: 'update',

        entityField: 'targetEntity',

        events: {
            'click a[data-action="add-field"]': function (e) {
                let field = $(e.currentTarget).data('name');
                if (!(this.fieldList || []).includes(field)) {
                    this.addField(field);
                }
            },
            'click button[data-action="reset"]': function (e) {
                this.reset();
            }
        },

        data() {
            return {
                scope: this.scope,
                fields: this.fields
            };
        },

        setup() {
            if (this.model.get('type') === 'create') {
                this.enableType = 'create';
            }

            this.setupFieldsPanel();
            this.listenTo(this.model, `change:${this.entityField} change:updateType`, () => {
                this.setupFieldsPanel();
                this.reset();
                this.reRender();
            });

            this.listenTo(this.model, 'after:change-mode', mode => {
                this.mode = mode;
                if (mode === 'detail') {
                    this.$el.find('[data-action="reset"]').addClass('hidden');
                }
                this.reRender();
            });

            this.listenTo(this.model, 'before:save', () => {
                let fieldsData = this.fetchData() || {};
                this.model.set('data', _.extend({}, this.model.get('data'), {
                    fieldList: this.fieldList,
                    fieldData: fieldsData
                }));
            });
        },

        setupFieldsPanel() {
            this.scope = this.model.get(this.entityField);

            if (!this.model.get('data')) {
                this.model.set('data', {fieldData: {}, fieldList: []});
            }

            this.getModelFactory().create(this.scope, model => {
                model.set(this.model.get('data').fieldData || {});
                this.fieldsModel = model;
                let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.scope) || [];
                this.fields = [];
                $.each((this.getMetadata().get(`entityDefs.${this.scope}.fields`) || {}), (field, row) => {
                    if (~forbiddenFieldList.indexOf(field)) return;
                    if (row.layoutMassUpdateDisabled) return;
                    if (row.massUpdateDisabled) return;
                    this.fields.push(field);
                });
            });
            this.fieldList = [];
        },

        addField(name) {
            this.notify('Loading...');

            if (this.mode === 'edit') {
                this.$el.find('[data-action="reset"]').removeClass('hidden');
            }

            let label = this.translate(name, 'fields', this.scope);
            let html = '<div class="cell form-group col-sm-6" data-name="' + name + '"><label class="control-label">' + label + '</label><div class="field" data-name="' + name + '" /></div>';
            this.$el.find('.fields-container').append(html);

            let type = this.fieldsModel.getFieldType(name);

            let viewName = this.model.getFieldParam(name, 'view') || this.getFieldManager().getViewName(type);

            this.createView(name, viewName, {
                model: this.fieldsModel,
                el: this.getSelector() + ' .field[data-name="' + name + '"]',
                defs: {
                    name: name,
                    isMassUpdate: true
                },
                mode: this.mode,
                inlineEditDisabled: true
            }, view => {
                this.fieldList.push(name);
                view.render();
                view.notify(false);
            });
        },

        reset() {
            this.fieldList.forEach(field => {
                this.clearView(field);
                this.$el.find('.cell[data-name="' + field + '"]').remove();
            });

            this.fieldList = [];
            if (!this.model.get('data')) {
                this.model.set('data', {});
            }
            this.model.get('data').fieldList = [];

            this.fieldsModel.clear();

            this.$el.find('[data-action="reset"]').addClass('hidden');
        },

        fetchData() {
            let attributes = {};
            this.fieldList.forEach(field => {
                _.extend(attributes, this.getView(field).fetch());
            });

            return attributes;
        },

        getFieldsData() {
            return this.model.get('data').fields || {};
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get('type') !== this.enableType || this.model.get('updateType') !== 'basic') {
                this.$el.parent().hide();
            } else {
                if (this.mode === 'detail') {
                    this.$el.find('button.select-field').addClass('disabled').attr('disabled', 'disabled');
                } else {
                    this.$el.find('button.select-field').removeClass('disabled').removeAttr('disabled');
                }

                if (this.model.get('data') && this.model.get('data').fieldList) {
                    this.model.get('data').fieldList.forEach(name => {
                        this.addField(name);
                    });
                }

                this.$el.parent().show();
            }
        },

    })
);
