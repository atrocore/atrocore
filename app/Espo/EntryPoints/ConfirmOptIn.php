<?php

namespace Espo\EntryPoints;

use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\Error;
use Treo\Core\EntryPoints\AbstractEntryPoint;

class ConfirmOptIn extends AbstractEntryPoint
{
    public static $authRequired = false;

    public function run()
    {
        if (empty($_GET['id'])) throw new BadRequest();

        $id = $_GET['id'];

        $data = $this->getServiceFactory()->create('LeadCapture')->confirmOptIn($id);

        if ($data->status === 'success') {
            $action = 'optInConfirmationSuccess';
        } else if ($data->status === 'expired') {
            $action = 'optInConfirmationExpired';
        } else {
            throw new Error();
        }

        $runScript = "
            Espo.require('controllers/lead-capture-opt-in-confirmation', function (Controller) {
                var controller = new Controller(app.baseController.params, app.getControllerInjection());
                controller.masterView = app.masterView;
                controller.doAction('".$action."', ".json_encode($data).");
            });
        ";

        $this->getClientManager()->display($runScript);
    }
}
