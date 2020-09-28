

Espo.define('treo-core:app', 'class-replace!treo-core:app', function (App) {

     _.extend(App.prototype, {

         initAuth: function () {
             this.auth = this.storage.get('user', 'auth') || null;

             this.baseController.on('login', function (data) {
                 this.auth = Base64.encode(data.auth.userName  + ':' + data.auth.token);
                 this.storage.set('user', 'auth', this.auth);

                 this.setCookieAuth(data.auth.userName, data.auth.token);

                 window.location.reload(true);
             }.bind(this));

             this.baseController.on('logout', function () {
                 this.logout();
             }.bind(this));
         },

    });

    return App;
});