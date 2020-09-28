

Espo.define('views/fields/image', 'views/fields/file', function (Dep) {

    return Dep.extend({

        type: 'image',

        showPreview: true,

        accept: ['image/*'],

        defaultType: 'image/jpeg',

        previewSize: 'small'

    });
});
