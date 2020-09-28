

Espo.define('views/lead-capture/record/panels/request', 'views/record/panels/side', function (Dep) {

    return Dep.extend({

        fieldList: [
            'exampleRequestUrl',
            'exampleRequestMethod',
            'exampleRequestPayload'
        ]

    });

});