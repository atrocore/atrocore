

Espo.define('multilang:views/admin/input-language', 'views/settings/record/edit',
    Dep => Dep.extend({

        layoutName: 'inputLanguage',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.buttonList.push({name: 'updateLayouts', label: 'Update Layouts', style: 'warning'});

            this.listenTo(this.model, 'after:save', function () {
                Espo.Ui.success(this.translate('successAndReload', 'messages', 'Global').replace('{value}', 2));
                setTimeout(function () {
                    window.location.reload(true);
                }, 2000);
            }.bind(this));
        },

        actionSave() {
            const oldList = this.attributes.inputLanguageList || [];
            const newList = this.model.get('inputLanguageList') || [];
            let hasDeletedElements = false;

            if (oldList.length > newList.length) {
                hasDeletedElements = true;
            } else {
                hasDeletedElements = oldList.some(lang => !newList.includes(lang))
            }

            if (hasDeletedElements) {
                Espo.Ui.confirm(this.translate('сonfirmLocaleChanges', 'labels', 'Settings'), {
                    confirmText: this.translate('Apply', 'labels', 'Global'),
                    cancelText: this.translate('Cancel', 'labels', 'Global')
                }, () => {
                    this.save();
                })
            } else {
                this.save();
            }
        },

        actionUpdateLayouts() {
            Espo.Ui.confirm(this.translate('updateLayouts', 'messages', 'Settings'), {
                confirmText: this.translate('Apply', 'labels', 'Global'),
                cancelText: this.translate('Cancel', 'labels', 'Global')
            }, () => {
                this.ajaxPostRequest('Multilang/action/updateLayouts').then(response => {
                    this.notify(this.translate('successAndReload', 'messages', 'Global').replace('{value}', 2), 'success', 3000);
                    setTimeout(function () {
                        window.location.reload(true);
                    }, 2000);
                });
            })
        }
    })
);
