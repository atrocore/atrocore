/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/record/panels/fields', 'view',
    Dep => Dep.extend({

        template: 'action/record/panels/fields',

        mode: 'detail',

        fieldsModel: null,

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
            if (this.model.isNew()) {
                this.mode = 'edit';
            }

            this.setupFieldsPanel();
            this.listenTo(this.model, `change:sourceEntity change:targetEntity change:updateType`, () => {
                this.setupFieldsPanel();
                if (this.model.previous(this.getEntityNameField()) && this.model.previous('updateType')) {
                    this.reset();
                }
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

        getEntityNameField() {
            return this.model.get('type') === 'suggestValue' ? 'sourceEntity' : 'targetEntity';
        },

        setupFieldsPanel() {
            this.scope = this.model.get(this.getEntityNameField());

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

            if (this.$el.find(`.fields-container .cell[data-name="${name}"]`).length === 0) {
                let label = this.translate(name, 'fields', this.scope);
                let html = '<div class="cell form-group col-sm-12" data-name="' + name + '"><label class="control-label">' + label + '</label><div class="field" data-name="' + name + '" /></div>';
                this.$el.find('.fields-container').append(html);
            }

            let type = this.fieldsModel.getFieldType(name);

            let viewName = this.model.getFieldParam(name, 'view') || this.getFieldManager().getViewName(type);

            this.createView(name, viewName, {
                model: this.fieldsModel,
                el: this.getSelector() + ' .field[data-name="' + name + '"]',
                defs: {
                    name: name,
                    isMassUpdate: true,
                    required: false,
                },
                mode: this.mode,
                inlineEditDisabled: true
            }, view => {
                this.fieldList.push(name);
                view.render();
                view.notify(false);
            });
        },

        setMode(mode) {
            this.mode = mode;
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

            if (!['create', 'update', 'createOrUpdate', 'suggestValue'].includes(this.model.get('type')) || this.model.get('updateType') !== 'basic') {
                this.$el.parent().hide();
            } else {
                if (this.mode === 'detail' || !this.model.get(this.getEntityNameField())) {
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
