

Espo.define('controllers/email', 'controllers/record', function (Dep) {

    return Dep.extend({

        prepareModelView: function (model, options) {
            Dep.prototype.prepareModelView(model, options);
            this.listenToOnce(model, 'after:send', function () {
                var key = this.name + 'List';
                var stored = this.getStoredMainView(key);
                if (stored) {
                    this.clearStoredMainView(key);
                }
            }, this);
        }

    });
});
