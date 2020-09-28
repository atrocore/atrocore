

Espo.define('views/inbound-email/fields/folders', 'views/inbound-email/fields/folder', function (Dep) {

    return Dep.extend({

        addFolder: function (folder) {
            var value = this.$element.val();

            var folders = [];
            if (value != '') {
                folders = value.split(',');
            }

            if (!~folders.indexOf(folder)) {
                folders.push(folder);
            }
            this.$element.val(folders.join(','));
        },
    });
});
