<?php

namespace Espo\Controllers;

class I18n extends \Espo\Core\Controllers\Base
{
    public function actionRead($params, $data, $request)
    {
        if ($request->get('default')) {
            return $this->getContainer()->get('defaultLanguage')->getAll();
        }
        return $this->getContainer()->get('language')->getAll();
    }
}
