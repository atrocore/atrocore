

Espo.define('views/preferences/edit', 'views/edit', function (Dep) {

    return Dep.extend({

        userName: '',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.userName = this.model.get('name');
        },

        getHeader: function () {
            var html = '';
            html += this.translate('Preferences');
            html += ' &raquo ';
            html += this.userName;
            return html;
        },

    });
});

