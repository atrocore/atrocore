<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Services;

use Atro\Core\Application;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\ModuleManager\Manager;
use Atro\Core\Templates\Services\ReferenceData;
use Atro\Core\Utils\Language;
use Espo\ORM\Entity;

class SoftwarePackage extends ReferenceData
{
    const string CHECK_UP_FILE = 'data/composer-check-up.log';

    public static function getCoreVersion(): string
    {
        if (file_exists('composer.lock')) {
            $data = json_decode(file_get_contents('composer.lock'), true);
            if (!empty($data['packages'])) {
                foreach ($data['packages'] as $package) {
                    if ($package['name'] == 'atrocore/core') {
                        return $package['version'];
                    }
                }
            }
        }

        return '-';
    }

    public function putAclMeta(Entity $entity): void
    {
        parent::putAclMeta($entity);

        if (empty($entity->get('currentVersion'))) {
            $entity->setMetaPermission('edit', false);
        }
        $entity->setMetaPermission('delete', false);

        $entity->setMetaPermission('showReleaseNotes', true);
        $entity->setMetaPermission('readDocs', $entity->get('installed') && ($entity->get('id') === 'Atro' || $this->getModuleManager()->getModule($entity->get('id'))->hasDocs()));

        $entity->setMetaPermission('install', !$entity->get('installed'));
        $entity->setMetaPermission(
            'uninstall', $entity->get('installed') && $entity->get('id') !== 'Atro' && !empty($entity->get('currentVersion')) && !empty($entity->get('targetVersion'))
        );
    }

    public function updateSystem(): bool
    {
        if (!$this->isDaemonAlive()){
            throw new BadRequest($this->getLanguage()->translate('daemonNotAlive', 'exceptions', 'SoftwarePackage'));
        }

        if ($this->jobManagerRunning()) {
            throw new BadRequest($this->getLanguage()->translate('jobManagerRunning', 'exceptions', 'SoftwarePackage'));
        }

        file_put_contents(Application::COMPOSER_LOG_FILE, 'update || ' . $this->getUser()->get('id'));

        return true;
    }

    public function install(array $ids): bool
    {
        if (!$this->isDaemonAlive()){
            throw new BadRequest($this->getLanguage()->translate('daemonNotAlive', 'exceptions', 'SoftwarePackage'));
        }

        return true;
    }

    public function uninstall(array $ids): bool
    {
        if (!$this->isDaemonAlive()){
            throw new BadRequest($this->getLanguage()->translate('daemonNotAlive', 'exceptions', 'SoftwarePackage'));
        }

        return true;
    }

    public function isDaemonAlive(): bool
    {
        file_put_contents(self::CHECK_UP_FILE, '1');
        sleep(2);
        if (file_exists(self::CHECK_UP_FILE)) {
            return false;
        }

        return true;
    }

    public function getReleaseNotes(string $id): string
    {
        $softwarePackage = $this->getRepository()->get($id);
        if (empty($softwarePackage)) {
            throw new NotFound();
        }
        $parts = explode('/', $softwarePackage->get('code') ?? '');
        if (empty($parts[1])) {
            throw new BadRequest();
        }

        $url = "https://help.atrocore.com/release-notes/" . $parts[1];

        // fetch html
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $output = curl_exec($ch);
        if ($output === false) {
            throw new BadRequest('Curl error: ' . curl_error($ch));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200) {
            throw new BadRequest("Invalid server response code: " . $httpCode);
        }

        $result = '';
        $parts = explode('<div class="page-content">', $output);
        if (isset($parts[1])) {
            $parts = explode('</div>', $parts[1]);
            $result = $parts[0] ?? '';
        }

        if (empty($result)) {
            $result = "<p>You can find the release notes here: <br /><a href=\"$url\" target=\"_blank\">$url</a></p>";
        }

        return $result;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('moduleManager');
        $this->addDependency('language');
    }

    protected function getModuleManager(): Manager
    {
        return $this->getInjection('moduleManager');
    }

    protected function getLanguage(): Language
    {
        return $this->getInjection('language');
    }

    private function jobManagerRunning(): bool
    {
        $job = $this
            ->getEntityManager()
            ->getRepository('Job')
            ->select(['id'])
            ->where(['status' => 'Running', 'type!=' => 'ComposerAutoUpdate'])
            ->findOne();

        return !empty($job);
    }
}
