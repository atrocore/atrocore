

Espo.define('views/inbound-email/fields/folder', 'views/fields/base', function (Dep) {

    return Dep.extend({

        editTemplate: 'inbound-email/fields/folder/edit',

        events: {
            'click [data-action="selectFolder"]': function () {
                var self = this;

                this.notify('Please wait...');

                var data = {
                    host: this.model.get('host'),
                    port: this.model.get('port'),
                    ssl: this.model.get('ssl'),
                    username: this.model.get('username'),
                };

                if (this.model.has('password')) {
                    data.password = this.model.get('password');
                } else {
                    if (!this.model.isNew()) {
                        data.id = this.model.id;
                    }
                }


                $.ajax({
                    type: 'GET',
                    url: 'InboundEmail/action/getFolders',
                    data: data,
                    error: function (xhr) {
                        Espo.Ui.error(self.translate('couldNotConnectToImap', 'messages', 'InboundEmail'));
                        xhr.errorIsHandled = true;
                    },
                }).done(function (folders) {
                    this.createView('modal', 'views/inbound-email/modals/select-folder', {
                        folders: folders
                    }, function (view) {
                        self.notify(false);
                        view.render();

                        self.listenToOnce(view, 'select', function (folder) {
                            view.close();
                            self.addFolder(folder);
                        });
                    });
                }.bind(this));
            }
        },

        addFolder: function (folder) {
            this.$element.val(folder);
        },
    });
});
