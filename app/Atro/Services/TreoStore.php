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

use Atro\Core\Templates\Services\Base;
use Espo\Core\Utils\Json;

class TreoStore extends Base
{
    public function findEntities($params)
    {
        // update store data
        $this->updateStoreData();

        $params['where'][] = [
            "type" => "notEquals",
            "attribute" => 'id',
            "value" => 'Connector'
        ];

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
                'deprecated'     => !empty($rows[$max]['extra']['deprecated']),
            ];

            foreach ($versions as $version) {
                $item['versions'][] = [
                    'version' => $version,
                    'require' => $rows[$version]['require'],
                ];
            }

            // push
            if (empty($item['deprecated'])) {
                $result[$treoId] = $item;
            }
        }

        return $result;
    }
}
