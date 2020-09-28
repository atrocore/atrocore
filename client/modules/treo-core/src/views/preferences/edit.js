

Espo.define('treo-core:views/preferences/edit', 'class-replace!treo-core:views/preferences/edit', function (Dep) {

    return Dep.extend({

        getHeader: function () {
            return `<span class="subsection">${this.translate('Preferences')}</span>${this.userName}`
        },

    });
});

