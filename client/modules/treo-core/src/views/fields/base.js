/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/fields/base', 'class-replace!treo-core:views/fields/base', function (Dep) {

    return Dep.extend({

        getConfirmMessage: function (_prev, attrs, model) {
            if (model._confirmMessage) {
                return model._confirmMessage;
            }

            let confirmMessage = null;

            let confirmations = this.getMetadata().get(`clientDefs.${model.urlRoot}.confirm`) || {};
            $.each(confirmations, (field, key) => {
                if (typeof _prev[field] !== 'undefined') {
                    let parts = key.split('.');
                    confirmMessage = this.translate(parts[2], parts[1], parts[0]);
                }
            });

            return confirmMessage;
        },

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
                this.notify(this.translate('Record cannot be saved'), 'error');
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

            let confirmMessage = this.getConfirmMessage(_prev, attrs, model);
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

            $el.data('isDestroyed', false)

            $el.closest('.field').one('mousedown click', () => {
                if ($el.data('isDestroyed')) return;
                $el.popover('destroy');
                $el.data('isDestroyed', true)
            });

            this.once('render remove', () => {
                if ($el) {
                    if ($el.data('isDestroyed')) return;
                    $el.popover('destroy');
                    $el.data('isDestroyed', true)
                }
            });

            if ($el.data('timeout')) {
                clearTimeout($el.data('timeout'));
            }

            $el.data('timeout', setTimeout(() => {
                if ($el.data('isDestroyed')) return;
                $el.popover('destroy');
                $el.data('isDestroyed', true)
            }, this.VALIDATION_POPOVER_TIMEOUT));
        },


    })
});