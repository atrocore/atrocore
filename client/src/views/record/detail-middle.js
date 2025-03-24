/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

Espo.define('views/record/detail-middle', 'view', function (Dep) {

    return Dep.extend({

        init: function () {
            this.recordHelper = this.options.recordHelper;
            this.scope = this.model.name;
        },

        data: function () {
            return {
                hiddenPanels: this.recordHelper.getHiddenPanels(),
                hiddenFields: this.recordHelper.getHiddenFields()
            };
        },

        showPanel: function (name) {
            if (this.isRendered()) {
                this.$el.find('.panel[data-name="' + name + '"]').removeClass('hidden');
            }
            this.recordHelper.setPanelStateParam(name, 'hidden', false);
        },

        hidePanel: function (name) {
            if (this.isRendered()) {
                this.$el.find('.panel[data-name="' + name + '"]').addClass('hidden');
            }
            this.recordHelper.setPanelStateParam(name, 'hidden', true);
        },

        hideField: function (name) {
            this.recordHelper.setFieldStateParam(name, 'hidden', true);

            var processHtml = function () {
                var fieldView = this.getFieldView(name);
                let $field
                if (fieldView) {
                    $field = fieldView.$el;
                    var $cell = $field.closest('.cell[data-name="' + name + '"]');
                    var $label = $cell.find('label.control-label[data-name="' + name + '"]');

                    $field.addClass('hidden');
                    $label.addClass('hidden');
                    $cell.addClass('hidden-cell');
                } else {
                    $field = this.$el.find('.field[data-name="' + name + '"]')
                    $field.addClass('hidden');
                    this.$el.find('.cell[data-name="' + name + '"]').addClass('hidden-cell');
                    this.$el.find('label.control-label[data-name="' + name + '"]').addClass('hidden');
                }

                const $panel = $field.closest('.panel')
                if ($panel.find('> .panel-body > .row > .cell').length === $panel.find('> .panel-body > .row > .cell.hidden-cell').length) {
                    const name = $panel.attr('data-name')
                    if (name) {
                        this.hidePanel(name)
                    }
                }
            }.bind(this);
            if (this.isRendered()) {
                processHtml();
            } else {
                this.once('after:render', function () {
                    processHtml();
                }, this);
            }

            var view = this.getFieldView(name);
            if (view) {
                view.setDisabled();
            }
        },

        showField: function (name) {
            if (this.recordHelper.getFieldStateParam(name, 'hiddenLocked')) {
                return;
            }
            this.recordHelper.setFieldStateParam(name, 'hidden', false);

            var processHtml = function () {
                var fieldView = this.getFieldView(name);
                let $field
                if (fieldView) {
                    $field = fieldView.$el;
                    var $cell = $field.closest('.cell[data-name="' + name + '"]');
                    var $label = $cell.find('label.control-label[data-name="' + name + '"]');

                    $field.removeClass('hidden');
                    $label.removeClass('hidden');
                    $cell.removeClass('hidden-cell');
                } else {
                    $field = this.$el.find('.field[data-name="' + name + '"]')
                    $field.removeClass('hidden');
                    this.$el.find('.cell[data-name="' + name + '"]').removeClass('hidden-cell');
                    this.$el.find('label.control-label[data-name="' + name + '"]').removeClass('hidden');
                }
                $field.closest('.panel').removeClass('hidden')
            }.bind(this);

            if (this.isRendered()) {
                processHtml();
            } else {
                this.once('after:render', function () {
                    processHtml();
                }, this);
            }

            var view = this.getFieldView(name);
            if (view) {
                if (!view.disabledLocked) {
                    view.setNotDisabled();
                }
            }
        },

        getFields: function () {
            return this.getFieldViews();
        },

        getFieldViews: function () {
            return this.nestedViews
        },

        getFieldView: function (name) {
            const fieldsViews = this.getFieldViews()

            if (fieldsViews[name + 'Field']) {
                return fieldsViews[name + 'Field']
            }

            for (let key of Object.keys(fieldsViews)) {
                if (fieldsViews[key]?.name === name) {
                    return fieldsViews[key]
                }
            }

            return null
        },

        // TODO remove in 5.4.0
        getView: function (name) {
            var view = Dep.prototype.getView.call(this, name);
            if (!view) {
                view = this.getFieldView(name);
            }
            return view;
        }

    });
});
