

Espo.define('views/email/record/list-expanded', ['views/record/list-expanded', 'views/email/record/list'], function (Dep, List) {

    return Dep.extend({

        actionMarkAsImportant: function (data) {
            List.prototype.actionMarkAsImportant.call(this, data);
        },

        actionMarkAsNotImportant: function (data) {
            List.prototype.actionMarkAsNotImportant.call(this, data);
        },

        actionMoveToTrash: function (data) {
            List.prototype.actionMoveToTrash.call(this, data);
        }

    });

});
