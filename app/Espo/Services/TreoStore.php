<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

declare(strict_types=1);

namespace Espo\Services;

use Espo\Core\Templates\Services\Base;
use Espo\Core\Utils\Json;

class TreoStore extends Base
{
    /**
     * @inheritDoc
     */
    public function findEntities($params)
    {
        // update store data
        $this->updateStoreData();

        return parent::findEntities($params);
    }

    /**
     * Update store data
     */
    protected function updateStoreData(): void
    {
        if (!empty($packages = $this->getRemotePackages())) {
            $this->savePackages($packages);
        }
    }

    /**
     * @param array $data
     */
    protected function savePackages(array $data): void
    {
        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->delete('treo_store')
            ->executeQuery();

        foreach ($data as $package) {
            if (empty($package['name']) || empty($package['description'])) {
                continue 1;
            }

            $name = !empty($package['name']['default']) ? $package['name']['default'] : $package['name'];
            $description = $package['description']['default'];
            $versions = Json::encode($package['versions']);
            $tags = Json::encode($package['tags']);

            $connection->createQueryBuilder()
                ->insert('treo_store')
                ->setValue('id', ':id')
                ->setValue('package_id', ':packageId')
                ->setValue('url', ':url')
                ->setValue('status', ':status')
                ->setValue('versions', ':versions')
                ->setValue('name', ':name')
                ->setValue('description', ':description')
                ->setValue('tags', ':tags')
                ->setParameters([
                    'id'          => $package['treoId'],
                    'packageId'   => $package['packageId'],
                    'url'         => $package['url'],
                    'status'      => $package['status'],
                    'versions'    => $versions,
                    'name'        => $name,
                    'description' => $description,
                    'tags'        => $tags,
                ])
                ->executeQuery();
        }
    }

    public function getRemotePackages(): array
    {
        session_start();

        $now = time();
        if (empty($_SESSION['packages_cache_time'])) {
            $_SESSION['packages_cache_time'] = $now;
        }

        $diff = $now - $_SESSION['packages_cache_time'];

        $cacheFile = 'data/cache/packages.json';
        if (file_exists($cacheFile) && $diff < 120) {
            $fileData = @json_decode(file_get_contents($cacheFile), true);
            if (!empty($fileData)) {
                return $fileData;
            }
        }

        // get packagist url
        $url = "https://packagist.atrocore.com/packages.json?id=" . $this->getConfig()->get('appId');

        // parse all
        $packages = $this->parsePackages(self::getPathContent(explode('?', $url)[0]));

        // parse available
        if (!empty($available = self::getPathContent($url))) {
            foreach ($this->parsePackages($available, 'available') as $id => $row) {
                $packages[$id] = $row;
            }
        }

        $packages = array_values($packages);

        file_put_contents($cacheFile, json_encode($packages));
        $_SESSION['packages_cache_time'] = $now;

        return $packages;
    }

    /**
     * @param string $path
     *
     * @return array
     */
    private static function getPathContent(string $path): array
    {
        $content = @file_get_contents($path);

        return (empty($content)) ? [] : json_decode($content, true);
    }

    /**
     * @param array  $packages
     * @param string $status
     *
     * @return array
     */
    private function parsePackages(array $packages, string $status = 'buyable'): array
    {
        /** @var array $result */
        $result = [];

        /** @var array $data */
        $data = [];

        foreach ($packages['packages'] as $repository => $versions) {
            if (is_array($versions)) {
                foreach ($versions as $version => $row) {
                    if (!empty($row['extra']['treoId'])) {
                        $treoId = $row['extra']['treoId'];
                        $version = strtolower($version);
                        if (preg_match_all('/^v\d+.\d+.\d+$/', $version, $matches)
                            || preg_match_all('/^v\d+.\d+.\d+-rc\d+$/', $version, $matches)
                            || preg_match_all('/^\d+.\d+.\d+$/', $version, $matches)
                            || preg_match_all('/^\d+.\d+.\d+-rc\d+$/', $version, $matches)
                        ) {
                            // prepare version
                            $version = str_replace('v', '', $matches[0][0]);

                            // skip if unstable version
                            if (strpos($version, 'rc') !== false) {
                                continue;
                            }

                            // push
                            $data[$treoId][$version] = $row;
                        }
                    }
                }
            }
        }

        foreach ($data as $treoId => $rows) {
            // find max version
            $versions = array_keys($rows);
            natsort($versions);
            $versions = array_reverse($versions);
            $max = $versions[0];

            // prepare tags
            $tags = [];
            if (!empty($rows[$max]['extra']['tags'])) {
                $tags = $rows[$max]['extra']['tags'];
            }

            // prepare item
            $item = [
                'treoId'         => $treoId,
                'packageId'      => $rows[$max]['name'],
                'url'            => $rows[$max]['source']['url'],
                'name'           => !empty($rows[$max]['extra']['name']) ? $rows[$max]['extra']['name'] : $treoId,
                'description'    => !empty($rows[$max]['extra']['description']) ? $rows[$max]['extra']['description'] : '',
                'tags'           => $tags,
                'status'         => $status,
                'usage'          => $rows[$max]['extra']['usage'] ?? null,
                'expirationDate' => $rows[$max]['extra']['expirationDate'] ?? null,
            ];

            foreach ($versions as $version) {
                $item['versions'][] = [
                    'version' => $version,
                    'require' => $rows[$version]['require'],
                ];
            }

            // push
            $result[$treoId] = $item;
        }

        return $result;
    }
}
