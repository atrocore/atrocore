

Espo.define('treo-core:views/admin/layouts/base', 'class-replace!treo-core:views/admin/layouts/base',
    Dep => Dep.extend({

        events: _.extend({}, Dep.prototype.events, {
            'click button[data-action="save"]': function () {
                this.disableButtons();
                this.notify('Saving...');
                if (!this.save(this.enableButtons.bind(this))) {
                    this.notify(false)
                }
            }
        }),

        save: function (callback) {
            const layout = this.fetch();
            const secondsBeforeReload = 2;

            if (!this.validate(layout)) {
                this.enableButtons();
                return false;
            }

            this.getHelper().layoutManager.set(this.scope, this.type, layout, function () {
                Espo.Ui.success(this.translate('successAndReload', 'messages', 'Global').replace('{value}', secondsBeforeReload));

                if (typeof callback === 'function') {
                    callback();
                }

                setTimeout(function () {
                    window.location.reload(true);
                }.bind(this), secondsBeforeReload * 1000);
            }.bind(this));
        },
    })
);
