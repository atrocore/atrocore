<?php

declare(strict_types=1);

namespace Treo\Services;

use Espo\Core\Services\Base;
use Espo\Core\Utils\Json;
use Treo\Documentator\Extractor;
use Treo\Core\Utils\Metadata;
use Treo\Core\Utils\Util;

/**
 * RestApiDocs service
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class RestApiDocs extends Base
{
    /**
     * @var string
     */
    protected $file = 'apidocs/index.html';

    /**
     * @var array
     */
    protected $dependencies
        = [
            'config',
            'entityManager',
            'user',
            'metadata'
        ];

    /**
     * @var array
     */
    protected $documentatorConfig = null;

    /**
     * @var array
     */
    protected $httpCode
        = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            406 => 'Not Acceptable',
            415 => 'Unsupported Media Type',
            500 => 'Internal Server Error'
        ];

    /**
     * Generate documentation
     *
     * @return bool
     */
    public function generateDocumentation(): bool
    {
        // prepare result
        $result = false;

        // prepare dir
        $dir = 'apidocs';

        // create dir
        if (!file_exists($dir)) {
            mkdir($dir, 0777);
        }

        if (!empty($html = $this->getHtml())) {
            $result = $this->setToFile($html);
        }

        return $result;
    }

    /**
     * Set html to file
     *
     * @param string $html
     *
     * @return bool
     */
    protected function setToFile(string $html): bool
    {
        return (!empty(file_put_contents($this->file, $html)));
    }

    /**
     * Render HTML
     *
     * @return string
     */
    protected function getHtml(): string
    {
        // prepare content
        $content = [
            '{{ title }}'   => 'AtroCore REST API documentation',
            '{{ date }}'    => date('d.m.Y'),
            '{{ content }}' => $this->getContent()
        ];

        return strtr($this->getTemplateContent('index'), $content);
    }

    /**
     * Get controllers
     *
     * @return array
     */
    protected function getControllers(): array
    {
        // prepare result
        $result = [];

        // get scopes
        $scopes = array_keys($this->getMetadata()->get('scopes'));
        sort($scopes);

        foreach ($scopes as $scope) {
            $className = $this->getControllerClassName($scope);
            if (class_exists($className)) {
                $result[] = $className;
            }
        }

        return $result;
    }

    /**
     * Get controller class name
     *
     * @param string $controller
     *
     * @return string
     */
    protected function getControllerClassName(string $controller): string
    {
        $controllerClassName = '\\Espo\\Custom\\Controllers\\' . Util::normilizeClassName($controller);
        if (!class_exists($controllerClassName)) {
            $moduleName = $this->getMetadata()->getScopeModuleName($controller);
            if ($moduleName) {
                $controllerClassName = '\\' . $moduleName . '\\Controllers\\' .
                    Util::normilizeClassName($controller);
            }
        }

        if (!class_exists($controllerClassName)) {
            $controllerClassName = '\\Treo\\Controllers\\' . Util::normilizeClassName($controller);
        }

        if (!class_exists($controllerClassName)) {
            $controllerClassName = '\\Espo\\Controllers\\' . Util::normilizeClassName($controller);
        }

        return $controllerClassName;
    }

    /**
     * Get content
     *
     * @return string
     */
    protected function getContent(): string
    {
        $result = '';

        foreach ($this->getContentSections() as $key => $value) {
            array_unshift($value, '<h2>' . $key . '</h2>');
            $result .= implode(PHP_EOL, $value);
        }

        return $result;
    }

    /**
     * Get content sections
     *
     * @return array
     */
    protected function getContentSections(): array
    {
        // prepare data
        $result = [];
        $counter = 0;
        $section = null;
        $config = $this->getDocumentatorConfig();

        foreach ($this->extractAnnotations() as $class => $methods) {
            // get section name
            $section = $this->prepareSectionName($class);

            foreach ($methods as $name => $docs) {
                // prepare docs
                if (empty($docs) && isset($config['method'][$name])) {
                    $docs = $this->prepareDynamicDocBlockData($config['method'][$name], $section);
                }
                if (!empty($docs)) {
                    // prepare docs
                    $docs = $docs + $config['common'];

                    // prepare content data
                    $data = [
                        '{{ elt_id }}'                => $counter,
                        '{{ method }}'                => $this->generateBadgeForMethod($docs),
                        '{{ route }}'                 => $docs['ApiRoute'][0]['name'],
                        '{{ description }}'           => $docs['ApiDescription'][0]['description'],
                        '{{ headers }}'               => $this->generateHeadersTemplate($docs),
                        '{{ parameters }}'            => $this->generateParamsTemplate($docs, $section),
                        '{{ body }}'                  => $this->generateBodyTemplate($counter, $docs, $section),
                        '{{ sample_response_codes }}' => $this->getResponseCodes($docs, $counter),
                        '{{ sample_response_body }}'  => $this->getResponseBody($docs, $counter, $section)
                    ];

                    // push section
                    $result[$section][] = strtr($this->getTemplateContent('Parts/content'), $data);

                    // prepare counter
                    $counter++;
                }
            }
        }

        return $result;
    }

    /**
     * Extract annotations
     *
     * @return array
     */
    protected function extractAnnotations(): array
    {
        $result = [];

        foreach ($this->getControllers() as $class) {
            $result = Extractor::getAllClassAnnotations($class);
        }

        return (!empty($result)) ? $result : [];
    }

    /**
     * Get response codes
     *
     * @param array $docs
     * @param int   $counter
     *
     * @return string
     */
    protected function getResponseCodes(array $docs, int $counter): string
    {
        // prepare result
        $result = '';
        $config = $this->getDocumentatorConfig();
        $codes = [];

        if (!empty($docs['ApiResponseCode'][0]['sample'])) {
            $codes = Json::decode($docs['ApiResponseCode'][0]['sample'], true);
        } elseif (isset($docs['ApiMethod'][0]['type'])) {
            $codes = $config['responseCode'][strtolower($docs['ApiMethod'][0]['type'])];
        }

        if (!empty($codes)) {
            $data = [];
            foreach ($codes as $code) {
                $tr = array(
                    '{{ elt_id }}'      => $counter,
                    '{{ response }}'    => $this->prepareResponseCode($code),
                    '{{ description }}' => ''
                );

                $data[] = strtr($this->getTemplateContent('Parts/sample-reponse-code'), $tr);
            }

            $result = implode(PHP_EOL, $data);
        }

        return $result;
    }

    /**
     * Prepare response code
     *
     * @param int $code
     *
     * @return string
     */
    protected function prepareResponseCode(int $code): string
    {
        $result = '';

        if (isset($this->httpCode[$code])) {
            $result = $code . ' ' . $this->httpCode[$code];
        }

        return $result;
    }

    /**
     * Get response body
     *
     * @param array  $docs
     * @param int    $counter
     * @param string $entity
     *
     * @return string
     */
    protected function getResponseBody(array $docs, int $counter, string $entity): string
    {
        // prepare result
        $result = '';

        if (!empty($docs['ApiReturn'])) {
            $data = [];
            foreach ($docs['ApiReturn'] as $row) {
                if (isset($row['sample'])) {
                    // prepare route
                    $route = $docs['ApiRoute'][0]['name'];
                    // prepare method
                    $method = $docs['ApiMethod'][0]['type'];

                    // prepare response
                    $response = $this->getEntityFields($row['sample'], $entity, $route, $method);
                    try {
                        $decoded = json_decode(str_replace("'", '"', $response));
                        $response = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    } catch (\Throwable $e) {
                    }

                    if (!empty($preparedResponse)) {
                        $response = $preparedResponse;
                    }

                    $tr = [
                        '{{ elt_id }}'   => $counter,
                        '{{ response }}' => $response
                    ];

                    // push data
                    $data[] = strtr($this->getTemplateContent('Parts/sample-reponse'), $tr);
                }
            }

            $result = implode(PHP_EOL, $data);
        }

        return $result;
    }

    /**
     * Generates the template for headers
     *
     * @param array $st_params
     *
     * @return string
     */
    protected function generateHeadersTemplate($st_params): string
    {
        // prepare result
        $result = '';

        if (!empty($st_params['ApiHeaders'])) {
            // prepare content
            $body = [];
            foreach ($st_params['ApiHeaders'] as $params) {
                $tr = [
                    '{{ key }}'   => $params['key'],
                    '{{ value }}' => $params['value']
                ];
                $body[] = strtr($this->getTemplateContent('Parts/headers-row'), $tr);
            }
            $content = ['{{ tbody }}' => implode(PHP_EOL, $body)];

            $result = strtr($this->getTemplateContent('Parts/headers-table'), $content);
        }

        return $result;
    }

    /**
     * Generates the template for parameters
     *
     * @param array  $docs
     * @param string $entity
     *
     * @return string
     */
    protected function generateParamsTemplate(array $docs, string $entity): string
    {
        // prepare result
        $result = '';

        // prepare docs
        $docs = $this->prepareApiParams($docs, $entity);

        if (!empty($docs['ApiParams'])) {
            $body = [];
            foreach ($docs['ApiParams'] as $params) {
                $tr = [
                    '{{ name }}'        => $params['name'],
                    '{{ type }}'        => $params['type'],
                    '{{ description }}' => @$params['description'],
                    '{{ is_required }}' => @$params['is_required'] == '1' ? 'Yes' : 'No',
                ];
                if (in_array($params['type'], ['object', 'array(object) ', 'array']) && isset($params['sample'])) {
                    // get template
                    $template = $this->getTemplateContent('Parts/param-sample-btn');

                    $tr['{{ type }}'] .= ' ' . strtr($template, ['{{ sample }}' => $params['sample']]);
                }
                $body[] = strtr($this->getTemplateContent('Parts/param-content'), $tr);
            }

            $result = strtr($this->getTemplateContent('Parts/param-table'), ['{{ tbody }}' => implode(PHP_EOL, $body)]);
        }

        return $result;
    }

    /**
     * Generate POST body template
     *
     * @param int    $id
     * @param array  $docs
     * @param string $entity
     *
     * @return string
     */
    protected function generateBodyTemplate($id, $docs, $entity): string
    {
        // prepare result
        $result = '';

        if (!empty($docs['ApiBody'])) {
            // prepare route
            $route = $docs['ApiRoute'][0]['name'];
            // prepares sample
            $sample = $docs['ApiBody'][0]['sample'];
            // prepare method
            $method = $docs["ApiMethod"][0]["type"];

            // prepare body
            $body = $this->getEntityFields($sample, $entity, $route, $method, true);
            try {
                $decoded = json_decode(str_replace("'", '"', $body));
                $body = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            } catch (\Throwable $e) {
            }

            $content = [
                '{{ elt_id }}' => $id,
                '{{ body }}'   => $body
            ];
            $result = strtr($this->getTemplateContent('Parts/sample-post-body'), $content);
        }

        return $result;
    }

    /**
     * Generates a badge for method
     *
     * @param array $data
     *
     * @return string
     */
    protected function generateBadgeForMethod($data)
    {
        $method = strtoupper($data['ApiMethod'][0]['type']);
        $st_labels = array(
            'POST'    => 'label-primary',
            'GET'     => 'label-success',
            'PUT'     => 'label-warning',
            'PATCH'   => 'label-warning',
            'DELETE'  => 'label-danger',
            'OPTIONS' => 'label-info'
        );

        return '<span class="label ' . $st_labels[$method] . '">' . $method . '</span>';
    }

    /**
     * Get template content
     *
     * @return string
     */
    protected function getTemplateContent(string $template): string
    {
        // prepare file
        $file = CORE_PATH . '/Treo/Documentator/Views/Templates/' . $template . '.html';

        return (file_exists($file)) ? file_get_contents($file) : '';
    }

    /**
     * Get documentator config
     *
     * @return array
     */
    protected function getDocumentatorConfig(): array
    {
        if (is_null($this->documentatorConfig)) {
            $this->documentatorConfig = include CORE_PATH . '/Treo/Configs/RestApiDocumentator.php';
        }

        return (array)$this->documentatorConfig;
    }

    /**
     * Prepare section name
     *
     * @param string $class
     *
     * @return string
     */
    protected function prepareSectionName(string $class): string
    {
        // get parts
        $parts = explode('\\', $class);

        return end($parts);
    }

    /**
     * Prepare dynamic DocBlock data
     *
     * @param array  $data
     * @param string $class
     *
     * @return array
     */
    protected function prepareDynamicDocBlockData(array $data, string $class): array
    {
        // prepare description
        if (isset($data['ApiDescription'])) {
            foreach ($data['ApiDescription'] as $k => $row) {
                if (isset($row['description'])) {
                    $data['ApiDescription'][$k]['description'] = sprintf($row['description'], $class);
                }
            }
        }

        // prepare route
        if (isset($data['ApiRoute'])) {
            foreach ($data['ApiRoute'] as $k => $row) {
                if (isset($row['name'])) {
                    $data['ApiRoute'][$k]['name'] = sprintf($row['name'], $class);
                }
            }
        }

        return $data;
    }

    /**
     * Get response entity data
     *
     * @param string $entity
     *
     * @return array
     */
    protected function getResponseEntityData(string $entity): array
    {
        // prepare result
        $result = [];

        $data = $this->getEntityData($entity);
        if (!empty($data)) {
            $result = [
                'id'         => 'string',
                'deleted'    => 'bool',
                'teamsIds'   => ['string', 'string', '...'],
                'teamsNames' => ['teamId - string' => 'teamName - string']
            ];
            foreach ($data as $name => $row) {
                $result[$name] = $row['type'];
            }
        }

        return $result;
    }

    /**
     * Get entity data
     *
     * @param string $entity
     *
     * @return array
     */
    protected function getEntityData(string $entity): array
    {
        // prepare result
        $result = [];

        // get entity defs
        $defs = $this->getMetadata()->get('entityDefs.' . $entity);

        if (isset($defs['fields'])) {
            // get config
            $inputLanguageList = $this->getConfig()->get('inputLanguageList');

            foreach ($defs['fields'] as $name => $row) {
                if (isset($row['type'])) {
                    switch ($row['type']) {
                        case 'link':
                            $result[$name . 'Id'] = [
                                'type'     => 'string',
                                'required' => !empty($row['required'])
                            ];
                            $result[$name . 'Name'] = [
                                'type'     => 'string',
                                'required' => !empty($row['required'])
                            ];
                            break;
                        case 'linkMultiple':
                            break;

                        case 'varcharMultiLang':
                            $result[$name] = [
                                'type'     => 'string',
                                'required' => !empty($row['required'])
                            ];
                            if (!empty($inputLanguageList)) {
                                foreach ($inputLanguageList as $locale) {
                                    // prepare locale
                                    $locale = ucfirst(Util::toCamelCase(strtolower($locale)));

                                    $result[$name . $locale] = [
                                        'type'     => 'string',
                                        'required' => !empty($row['required'])
                                    ];
                                }
                            }
                            break;
                        case 'textMultiLang':
                            $result[$name] = [
                                'type'     => 'string',
                                'required' => !empty($row['required'])
                            ];
                            if (!empty($inputLanguageList)) {
                                foreach ($inputLanguageList as $locale) {
                                    // prepare locale
                                    $locale = ucfirst(Util::toCamelCase(strtolower($locale)));

                                    $result[$name . $locale] = [
                                        'type'     => 'string',
                                        'required' => !empty($row['required'])
                                    ];
                                }
                            }
                            break;
                        case 'bool':
                            $result[$name] = [
                                'type'     => 'bool',
                                'required' => !empty($row['required'])
                            ];
                            break;
                        case 'int':
                            $result[$name] = [
                                'type'     => 'int',
                                'required' => !empty($row['required'])
                            ];
                            break;
                        case 'float':
                            $result[$name] = [
                                'type'     => 'float',
                                'required' => !empty($row['required'])
                            ];
                            break;
                        default:
                            $result[$name] = [
                                'type'     => 'string',
                                'required' => !empty($row['required'])
                            ];
                            break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Prepare API params
     *
     * @param array  $docs
     * @param string $entity
     *
     * @return array
     */
    protected function prepareApiParams(array $docs, string $entity): array
    {
        if (!empty($docs['ApiEntityParams'])) {
            foreach ($this->getEntityData($entity) as $name => $row) {
                $docs['ApiParams'][] = [
                    'name'        => $name,
                    'type'        => $row['type'],
                    'description' => '',
                    'is_required' => $row['required'],
                ];
            }
        }

        return $docs;
    }

    /**
     * Get entity fields
     *
     * @param string $sample
     * @param string $entity
     * @param string $route
     * @param string $method
     * @param bool   $isBody
     *
     * @return string
     */
    protected function getEntityFields($sample, string $entity, string $route, string $method, bool $isBody = false)
    {
        // prepare sample
        if (is_string($sample) && strpos($sample, '{entityDeff}') !== false) {
            // get config
            $inputLanguageList = $this->getConfig()->get('inputLanguageList');

            // get entity defs
            $entityDeffs = [];
            foreach ($this->getMetadata()->get("entityDefs.$entity.fields", []) as $field => $row) {
                // set id
                $entityDeffs['id'] = 'string';

                // set deleted
                $entityDeffs['deleted'] = 'bool';

                if (empty($row['notStorable']) && isset($row['type'])) {
                    switch ($row['type']) {
                        case 'link':
                            $entityDeffs[$field . 'Id'] = 'string';
                            break;
                        case 'linkMultiple':
                            break;
                        case 'varcharMultiLang':
                        case 'textMultiLang':
                        case 'enumMultiLang':
                            $entityDeffs[$field] = 'string';
                            if (!empty($inputLanguageList)) {
                                foreach ($inputLanguageList as $locale) {
                                    // prepare locale
                                    $locale = ucfirst(Util::toCamelCase(strtolower($locale)));

                                    $entityDeffs[$field . $locale] = 'string';
                                }
                            }
                            break;
                        case 'bool':
                            $entityDeffs[$field] = 'bool';
                            break;
                        case 'int':
                            $entityDeffs[$field] = 'int';
                            break;
                        case 'float':
                            $entityDeffs[$field] = 'float';
                            break;
                        case 'varchar':
                        case 'enum':
                        case 'text':
                        case 'wysiwyg':
                            $entityDeffs[$field] = 'string';
                            break;
                        case 'array':
                            $entityDeffs[$field] = 'array';
                            break;
                        case 'jsonObject':
                            $entityDeffs[$field] = 'json';
                            break;
                    }
                }
            }

            if ($method !== 'GET') {
                unset($entityDeffs['id']);
                unset($entityDeffs['deleted']);
            }

            // if action getDuplicateAttributes replace parameter key
            if (preg_match('/.+\/action\/getDuplicateAttributes$/', $route)) {
                if (!empty($entityDeffs['id'])) {
                    $entityDeffs['_duplicatingEntityId'] = $entityDeffs['id'];
                    unset($entityDeffs['id']);
                }
            }

            // for request body
            if ($isBody) {
                unset($entityDeffs['id']);
                unset($entityDeffs['deleted']);
            }

            $sample = str_replace(['{entityDeff}', "'"], [Json::encode($entityDeffs), '"'], $sample);
        }

        return $sample;
    }

    /**
     * Get metadata
     *
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }
}
