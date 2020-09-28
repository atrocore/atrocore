<?php

namespace Espo\EntryPoints;

use \Espo\Core\Exceptions\NotFound;
use \Espo\Core\Exceptions\BadRequest;
use Treo\Core\EntryPoints\AbstractEntryPoint;

class ChangePassword extends AbstractEntryPoint
{
    public static $authRequired = false;

    public function run()
    {
        $requestId = $_GET['id'];
        if (empty($requestId)) {
            throw new BadRequest();
        }

        $config = $this->getConfig();
        $themeManager = $this->getThemeManager();

        $p = $this->getEntityManager()->getRepository('PasswordChangeRequest')->where(array(
            'requestId' => $requestId
        ))->findOne();

        if (!$p) {
            throw new NotFound();
        }

        $runScript = "
            app.getController('PasswordChangeRequest', function (controller) {
                controller.doAction('passwordChange', '{$requestId}');
            });
        ";
        $vars['classReplaceMap'] = json_encode($this->getMetadata()->get(['app', 'clientClassReplaceMap']));

        $this->getClientManager()->display($runScript, null, $vars);
    }

    protected function getThemeManager()
    {
        return $this->getContainer()->get('themeManager');
    }
}
