/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
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

Espo.define('treo-core:views/fields/base', 'class-replace!treo-core:views/fields/base', function (Dep) {

    return Dep.extend({

        inlineEditSave: function () {
            var data = this.fetch();

            var self = this;
            var model = this.model;
            var prev = Espo.Utils.cloneDeep(this.initialAttributes);

            model.set(data, {silent: true});
            data = model.attributes;

            var attrs = false;
            for (var attr in data) {
                if (_.isEqual(prev[attr], data[attr])) {
                    continue;
                }
                (attrs || (attrs = {}))[attr] = data[attr];
            }

            if (!attrs) {
                this.inlineEditClose();
                return;
            }

            if (this.validate()) {
                this.notify('Not valid', 'error');
                model.set(prev, {silent: true});
                return;
            }

            let _prev = {};
            $.each(attrs, function (field, value) {
                _prev[field] = prev[field];
            });

            attrs['_prev'] = _prev;
            attrs['_silentMode'] = true;

            model.trigger('before:save', attrs);

            let confirmMessage = null;
            let confirmations = this.getMetadata().get(`clientDefs.${model.urlRoot}.confirm`) || {};
            $.each(confirmations, (field, key) => {
                if (typeof _prev[field] !== 'undefined') {
                    let parts = key.split('.');
                    confirmMessage = this.translate(parts[2], parts[1], parts[0]);
                }
            });

            if (confirmMessage) {
                Espo.Ui.confirm(confirmMessage, {
                    confirmText: self.translate('Apply'),
                    cancelText: self.translate('Cancel')
                }, () => {
                    this.inlineEditSaveModel(model, attrs);
                });
            } else {
                this.inlineEditSaveModel(model, attrs);
            }
        },

        inlineEditSaveModel: function (model, attrs) {
            let self = this;
            this.notify('Saving...');
            model.save(attrs, {
                success: function () {
                    self.trigger('after:save');
                    model.trigger('after:save');
                    self.notify('Saved', 'success');
                    self.inlineEditClose(true);
                },
                error: function (e, xhr) {
                    let statusReason = xhr.responseText || '';
                    if (xhr.status === 409) {
                        self.notify(false);
                        Espo.Ui.confirm(statusReason, {
                            confirmText: self.translate('Apply'),
                            cancelText: self.translate('Cancel')
                        }, function () {
                            attrs['_prev'] = null;
                            attrs['_silentMode'] = false;

                            model.save(attrs, {
                                success: function () {
                                    self.trigger('after:save');
                                    model.trigger('after:save');
                                    self.notify('Saved', 'success');
                                    self.inlineEditClose(true);
                                },
                                patch: true
                            });
                        })
                    } else {
                        if (xhr.status === 304) {
                            Espo.Ui.notify(self.translate('notModified', 'messages'), 'warning', 1000 * 60 * 60 * 2, true);
                        } else {
                            Espo.Ui.notify(`${self.translate("Error")} ${xhr.status}: ${statusReason}`, "error", 1000 * 60 * 60 * 2, true);
                        }
                    }
                },
                patch: true
            });
        },

        showValidationMessage: function (message, target) {
            var $el;

            target = target || '.main-element';

            if (typeof target === 'string' || target instanceof String) {
                $el = this.$el.find(target);
            } else {
                $el = $(target);
            }

            if (!$el.size() && this.$element) {
                $el = this.$element;
            }

            $el.popover({
                placement: 'bottom',
                container: 'body',
                content: message,
                trigger: 'manual'
            }).popover('show');

            this.isDestroyed = false;

            $el.closest('.field').one('mousedown click', () => {
                if (this.isDestroyed) return;
                $el.popover('destroy');
                this.isDestroyed = true;
            });

            this.once('render remove', () => {
                if (this.isDestroyed) return;
                if ($el) {
                    $el.popover('destroy');
                    this.isDestroyed = true;
                }
            });

            if (this._timeout) {
                clearTimeout(this._timeout);
            }

            this._timeout = setTimeout(() => {
                if (this.isDestroyed) return;
                $el.popover('destroy');
                this.isDestroyed = true;
            }, this.VALIDATION_POPOVER_TIMEOUT);
        },


    })
});