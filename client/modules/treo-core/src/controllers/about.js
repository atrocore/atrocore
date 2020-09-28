

Espo.define('treo-core:controllers/about', 'controllers/base', function (Dep) {
    return Dep.extend({
        defaultAction: 'about',
        about: function () {
            this.error404();
        }
    });

});
