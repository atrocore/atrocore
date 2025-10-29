<?php

namespace Atro\EntryPoints;

class FrontendManifest extends AbstractEntryPoint
{
    public static bool $authRequired = false;

    public function run(): void
    {
        header('Content-Type: application/manifest+json');

        $manifest = [
            'id' => $this->getConfig()->get('appId'),
            'name' => $this->getConfig()->get('applicationName'),
            'short_name' => $this->getConfig()->get('applicationName'),
            'start_url' => '/',
            'display_override' => ['window-controls-overlay'],
            'display' => 'standalone',
        ];

        if ($siteUrl = $this->getConfig()->get('siteUrl')) {
            $manifest['scope'] = $siteUrl;
        }

        $icon = [
            'src' => '/client/modules/treo-core/img/favicon.svg',
            'sizes' => 'any',
            'type' => 'image/svg+xml',
        ];

        if ($faviconId = $this->getConfig()->get('faviconId')) {
            $file = $this->getEntityManager()->getEntity('File', $faviconId);

            if ($file) {
                $icon['src'] = $file->getLargeThumbnailUrl();
                $icon['type'] = $file->get('mimeType');
            }
        }

        $manifest['icons'] = [$icon];

        echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        exit;
    }
}