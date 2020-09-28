

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
                (attrs || (attrs = {}))[attr] =    data[attr];
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

            this.notify('Saving...');
            model.save(attrs, {
                success: function () {
                    self.trigger('after:save');
                    model.trigger('after:save');
                    self.notify('Saved', 'success');
                },
                error: function () {
                    self.notify('Error occured', 'error');
                    model.set(prev, {silent: true});
                    self.render()
                },
                patch: true
            });
            this.inlineEditClose(true);
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