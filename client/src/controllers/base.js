
Espo.define('controllers/base', 'controller', function (Dep) {

    return Dep.extend({

        login: function () {
            var viewName = this.getMetadata().get(['clientDefs', 'App', 'loginView']) || 'views/login';
            this.entire(viewName, {}, function (login) {
                login.render();
                login.on('login', function (data) {
                    this.trigger('login', data);
                }.bind(this));
            }.bind(this));
        },

        logout: function () {
            var title = this.getConfig().get('applicationName') || 'EspoCRM';
            $('head title').text(title);
            this.trigger('logout');
        },

        clearCache: function (options) {
            this.entire('views/clear-cache', {
                cache: this.getCache()
            }, function (view) {
                view.render();
            });
        },

        error404: function () {
            this.entire('views/base', {template: 'errors/404'}, function (view) {
                view.render();
            });
        },

        error403: function () {
            this.entire('views/base', {template: 'errors/403'}, function (view) {
                view.render();
            });
        },

    });
});

