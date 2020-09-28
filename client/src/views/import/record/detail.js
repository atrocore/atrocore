

Espo.define('views/import/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        readOnly: true,

        returnUrl: '#Import/list',

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.model.get('status') === 'In Process') {
                setTimeout(this.runChecking.bind(this), 3000);
                this.on('remove', function () {
                    this.stopChecking = true;
                }, this);
            }

            this.hideActionItem('delete');
        },

        runChecking: function () {
            if (this.stopChecking) return;

            this.model.fetch().done(function () {

                var bottomView = this.getView('bottom');
                if (bottomView) {
                    var importedView = bottomView.getView('imported');
                    if (importedView && importedView.collection) {
                        importedView.collection.fetch();
                    }

                    var duplicatesView = bottomView.getView('duplicates');
                    if (duplicatesView && duplicatesView.collection) {
                        duplicatesView.collection.fetch();
                    }

                    var updatedView = bottomView.getView('updated');
                    if (updatedView && updatedView.collection) {
                        updatedView.collection.fetch();
                    }
                }

                if (this.model.get('status') !== 'In Process') {
                    return;
                }
                setTimeout(this.runChecking.bind(this), 5000);
            }.bind(this));
        }

    });

});
