<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\Error;

class EntityManager extends \Espo\Core\Controllers\Base
{
    protected function checkControllerAccess()
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }
    }

    public function actionCreateEntity($params, $data, $request)
    {
        $data = get_object_vars($data);

        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (empty($data['name']) || empty($data['type'])) {
            throw new BadRequest();
        }

        $name = $data['name'];
        $type = $data['type'];

        $name = filter_var($name, \FILTER_SANITIZE_STRING);
        $type = filter_var($type, \FILTER_SANITIZE_STRING);

        $params = array();

        if (!empty($data['labelSingular'])) {
            $params['labelSingular'] = $data['labelSingular'];
        }
        if (!empty($data['labelPlural'])) {
            $params['labelPlural'] = $data['labelPlural'];
        }
        if (!empty($data['stream'])) {
            $params['stream'] = $data['stream'];
        }
        if (!empty($data['disabled'])) {
            $params['disabled'] = $data['disabled'];
        }
        if (!empty($data['sortBy'])) {
            $params['sortBy'] = $data['sortBy'];
        }
        if (!empty($data['sortDirection'])) {
            $params['asc'] = $data['sortDirection'] === 'asc';
        }
        if (isset($data['textFilterFields']) && is_array($data['textFilterFields'])) {
            $params['textFilterFields'] = $data['textFilterFields'];
        }
        if (!empty($data['color'])) {
            $params['color'] = $data['color'];
        }
        if (!empty($data['iconClass'])) {
            $params['iconClass'] = $data['iconClass'];
        }
        if (isset($data['fullTextSearch'])) {
            $params['fullTestSearch'] = $data['fullTextSearch'];
        }

        $params['kanbanViewMode'] = !empty($data['kanbanViewMode']);
        if (!empty($data['kanbanStatusIgnoreList'])) {
            $params['kanbanStatusIgnoreList'] = $data['kanbanStatusIgnoreList'];
        }

        $result = $this->getContainer()->get('entityManagerUtil')->create($name, $type, $params);

        if ($result) {
            $tabList = $this->getConfig()->get('tabList', []);

            if (!in_array($name, $tabList)) {
                $tabList[] = $name;
                $this->getConfig()->set('tabList', $tabList);
                $this->getConfig()->save();
            }

            $this->getContainer()->get('dataManager')->rebuild();
        } else {
            throw new Error();
        }

        return true;
    }

    public function actionUpdateEntity($params, $data, $request)
    {
        $data = get_object_vars($data);

        if (!$request->isPost()) {
            throw new BadRequest();
        }
        if (empty($data['name'])) {
            throw new BadRequest();
        }
        $name = $data['name'];
        $name = filter_var($name, \FILTER_SANITIZE_STRING);

        if (!empty($data['sortDirection'])) {
            $data['asc'] = $data['sortDirection'] === 'asc';
        }

        $result = $this->getContainer()->get('entityManagerUtil')->update($name, $data);

        if ($result) {
            $this->getContainer()->get('dataManager')->clearCache();
        } else {
            throw new Error();
        }

        return true;
    }

    public function actionRemoveEntity($params, $data, $request)
    {
        $data = get_object_vars($data);

        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (empty($data['name'])) {
            throw new BadRequest();
        }
        $name = $data['name'];
        $name = filter_var($name, \FILTER_SANITIZE_STRING);

        $result = $this->getContainer()->get('entityManagerUtil')->delete($name);

        if ($result) {
            $tabList = $this->getConfig()->get('tabList', []);
            if (($key = array_search($name, $tabList)) !== false) {
                unset($tabList[$key]);
                $tabList = array_values($tabList);
            }
            $this->getConfig()->set('tabList', $tabList);
            $this->getConfig()->save();

            $this->getContainer()->get('dataManager')->clearCache();
        } else {
            throw new Error();
        }

        return true;
    }

    public function actionCreateLink($params, $data, $request)
    {
        $data = get_object_vars($data);

        if (!$request->isPost()) {
            throw new BadRequest();
        }

        $paramList = [
        	'entity',
        	'entityForeign',
        	'link',
        	'linkForeign',
        	'label',
        	'labelForeign',
        	'linkType'
        ];

        $additionalParamList = [
            'relationName',
        ];

        $params = array();

        foreach ($paramList as $item) {
        	if (empty($data[$item])) {
        		throw new BadRequest();
        	}
        	$params[$item] = filter_var($data[$item], \FILTER_SANITIZE_STRING);
        }

        foreach ($additionalParamList as $item) {
            $params[$item] = filter_var($data[$item], \FILTER_SANITIZE_STRING);
        }

        if (array_key_exists('linkMultipleField', $data)) {
            $params['linkMultipleField'] = $data['linkMultipleField'];
        }
        if (array_key_exists('linkMultipleFieldForeign', $data)) {
            $params['linkMultipleFieldForeign'] = $data['linkMultipleFieldForeign'];
        }

        if (array_key_exists('audited', $data)) {
            $params['audited'] = $data['audited'];
        }
        if (array_key_exists('auditedForeign', $data)) {
            $params['auditedForeign'] = $data['auditedForeign'];
        }

        $result = $this->getContainer()->get('entityManagerUtil')->createLink($params);

        if ($result) {
            $this->getContainer()->get('dataManager')->rebuild();
        } else {
            throw new Error();
        }

        return true;
    }

    public function actionUpdateLink($params, $data, $request)
    {
        $data = get_object_vars($data);

        if (!$request->isPost()) {
            throw new BadRequest();
        }

        $paramList = [
        	'entity',
        	'entityForeign',
        	'link',
        	'linkForeign',
        	'label',
        	'labelForeign'
        ];

        $additionalParamList = [];

        $params = array();
        foreach ($paramList as $item) {
            if (array_key_exists($item, $data)) {
                $params[$item] = filter_var($data[$item], \FILTER_SANITIZE_STRING);
            }
        }

        foreach ($additionalParamList as $item) {
            $params[$item] = filter_var($data[$item], \FILTER_SANITIZE_STRING);
        }

        if (array_key_exists('linkMultipleField', $data)) {
            $params['linkMultipleField'] = $data['linkMultipleField'];
        }
        if (array_key_exists('linkMultipleFieldForeign', $data)) {
            $params['linkMultipleFieldForeign'] = $data['linkMultipleFieldForeign'];
        }

        if (array_key_exists('audited', $data)) {
            $params['audited'] = $data['audited'];
        }
        if (array_key_exists('auditedForeign', $data)) {
            $params['auditedForeign'] = $data['auditedForeign'];
        }

        $result = $this->getContainer()->get('entityManagerUtil')->updateLink($params);

        if ($result) {
            $this->getContainer()->get('dataManager')->clearCache();
        } else {
            throw new Error();
        }

        return true;
    }

    public function actionRemoveLink($params, $data, $request)
    {
        $data = get_object_vars($data);

        if (!$request->isPost()) {
            throw new BadRequest();
        }

        $paramList = [
        	'entity',
        	'link',
        ];
        $d = array();
        foreach ($paramList as $item) {
        	$d[$item] = filter_var($data[$item], \FILTER_SANITIZE_STRING);
        }

        $result = $this->getContainer()->get('entityManagerUtil')->deleteLink($d);

        if ($result) {
            $this->getContainer()->get('dataManager')->clearCache();
        } else {
            throw new Error();
        }

        return true;
    }

    public function postActionFormula($params, $data, $request)
    {
        if (empty($data->scope)) {
            throw new BadRequest();
        }
        if (!property_exists($data, 'data')) {
            throw new BadRequest();
        }

        $formulaData = get_object_vars($data->data);

        $this->getContainer()->get('entityManagerUtil')->setFormulaData($data->scope, $formulaData);

        $this->getContainer()->get('dataManager')->clearCache();

        return true;
    }

    public function postActionResetToDefault($params, $data, $request)
    {
        if (empty($data->scope)) {
            throw new BadRequest();
        }

        $this->getContainer()->get('entityManagerUtil')->resetToDefaults($data->scope);
        $this->getContainer()->get('dataManager')->clearCache();

        return true;
    }
}
