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

namespace Atro\Core;

use Atro\Core\ModuleManager\Manager as ModuleManager;
use Atro\Services\Composer;

class DocsManager
{
    private ?array $moduleMap = null;

    private string $corePath;

    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly DataManager   $dataManager,
    ) {
        $this->corePath = dirname(CORE_PATH);
    }

    public function getMarkdown(string $module, string $page, string $assetBaseUrl): ?string
    {
        if ($module === 'navigation') {
            return $this->buildSidebar();
        }

        if ($module === 'README') {
            return $this->buildReadme($assetBaseUrl);
        }

        $map = $this->getModuleMap();
        if (!isset($map[$module])) {
            return null;
        }

        $docsDir = $map[$module];

        if ($page === 'navigation') {
            return $this->buildSidebar();
        }

        $resolved = $this->resolvePage($docsDir, $page);
        if ($resolved === null) {
            return null;
        }

        [$filePath, $pageDir] = $resolved;
        $content    = file_get_contents($filePath);
        $content    = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $content);
        $content    = preg_replace('~\{\.[\w-]+\}~', '', $content);
        $content    = $this->convertGravNotices($content);
        $content    = $this->rewriteAssetUrls($content, $module, $pageDir, $docsDir, $assetBaseUrl);
        $relPath    = ltrim(substr($pageDir, strlen($docsDir)), '/');
        $urlPath    = preg_replace('~(^|/)(\d+\.)~', '$1', $relPath);
        $currentDir = rtrim($module . '/' . $urlPath, '/') . '/';

        return $this->rewriteRelativeLinks($content, $currentDir);
    }

    public function getAsset(string $module, string $asset): ?array
    {
        $asset = ltrim($asset, '/');

        if ($module === 'README') {
            $filePath = $this->corePath . '/' . $asset;
        } else {
            $map = $this->getModuleMap();
            if (!isset($map[$module])) {
                return null;
            }
            $filePath = $map[$module] . '/' . $asset;
        }

        if (!file_exists($filePath) || !is_file($filePath)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
            'png'         => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'         => 'image/gif',
            'svg'         => 'image/svg+xml',
            'webp'        => 'image/webp',
            default       => 'application/octet-stream',
        };

        return ['path' => $filePath, 'mime' => $mime];
    }

    public function getModuleMap(): array
    {
        if ($this->moduleMap === null) {
            $this->moduleMap = [];

            $coreDocs = $this->corePath . '/docs';
            if (is_dir($coreDocs)) {
                $this->moduleMap['Atro'] = $coreDocs;
            }

            foreach ($this->moduleManager->getModules() as $id => $module) {
                $docsDir = rtrim($module->getPath(), '/') . '/docs';
                if (is_dir($docsDir)) {
                    $this->moduleMap[$id] = $docsDir;
                }
            }
        }

        return $this->moduleMap;
    }

    private function buildSidebar(): string
    {
        $cached = $this->dataManager->getCacheData('docs_sidebar', false);
        if ($cached !== null) {
            return $cached;
        }

        $map     = $this->getModuleMap();
        $version = Composer::getCoreVersion();
        $lines   = ['- [**Help Center**](https://help.atrocore.com/v' . $version . ')', '---'];

        foreach ($this->buildModuleItems() as [$label, $id]) {
            $children = $this->buildTree($map[$id], $id);
            $lines[]  = '- [**' . $label . '**](' . $id . '/)';
            foreach ($children as $line) {
                $lines[] = '  ' . $line;
            }
        }

        $content = implode("\n", $lines) . "\n";
        $this->dataManager->setCacheData('docs_sidebar', $content);

        return $content;
    }

    private function buildReadme(string $assetBaseUrl): string
    {
        $path = $this->corePath . '/README.md';
        if (file_exists($path)) {
            $content = file_get_contents($path);
            return $this->rewriteAssetUrls($content, 'README', $this->corePath, $this->corePath, $assetBaseUrl);
        }

        $lines = ['# Module Documentation', ''];
        foreach ($this->buildModuleItems() as [$label, $id]) {
            $lines[] = '- [' . $label . '](' . $id . '/)';
        }

        return implode("\n", $lines) . "\n";
    }

    private function buildModuleItems(): array
    {
        $items = [];
        foreach ($this->getModuleMap() as $id => $docsDir) {
            if ($id === 'Atro') {
                $label = 'AtroCore';
            } else {
                $module = $this->moduleManager->getModule($id);
                $label  = ($module && $module->getName() !== '')
                    ? $module->getName()
                    : trim(preg_replace('/([A-Z])/', ' $1', $id));
            }
            $items[] = [$label, $id];
        }

        usort($items, function ($a, $b) {
            if ($a[1] === 'Atro') return -1;
            if ($b[1] === 'Atro') return 1;
            return strcmp($a[0], $b[0]);
        });

        return $items;
    }

    private function buildTree(string $dir, string $moduleId, string $urlBase = ''): array
    {
        $dirs = glob($dir . '/*', GLOB_ONLYDIR);
        if (empty($dirs)) return [];
        sort($dirs);

        $lines = [];
        foreach ($dirs as $subDir) {
            $dirname = basename($subDir);
            if (str_starts_with($dirname, '_')) continue;

            $slug    = preg_replace('/^\d+\./', '', $dirname);
            $urlPath = $urlBase ? $urlBase . '/' . $slug : $slug;
            $mdFile  = $this->findFirstMd($subDir);
            $title   = $mdFile ? ($this->parseFrontmatterTitle($mdFile) ?? $slug) : $slug;

            $lines[] = $mdFile
                ? '- [' . $title . '](' . $moduleId . '/' . $urlPath . '/)'
                : '- <span class="sidebar-label">' . htmlspecialchars($title) . '</span>';
            foreach ($this->buildTree($subDir, $moduleId, $urlPath) as $child) {
                $lines[] = '  ' . $child;
            }
        }

        return $lines;
    }

    private function resolvePage(string $docsDir, string $page): ?array
    {
        if ($page === '' || $page === 'README') {
            $md = $this->findFirstMd($docsDir);
            return $md ? [$md, $docsDir] : null;
        }

        $parts      = array_values(array_filter(explode('/', $page), fn($p) => $p !== ''));
        $currentDir = $docsDir;

        foreach ($parts as $part) {
            if ($part === 'README') {
                $md = $this->findFirstMd($currentDir);
                return $md ? [$md, $currentDir] : null;
            }
            $matched = $this->findMatchingDir($currentDir, $part);
            if ($matched === null) return null;
            $currentDir = $matched;
        }

        $md = $this->findFirstMd($currentDir);
        return $md ? [$md, $currentDir] : null;
    }

    private function findFirstMd(string $dir): ?string
    {
        $files = glob($dir . '/*.md');
        return !empty($files) ? $files[0] : null;
    }

    private function findMatchingDir(string $parentDir, string $slug): ?string
    {
        $dirs = glob($parentDir . '/*', GLOB_ONLYDIR);
        if (empty($dirs)) return null;
        sort($dirs);
        foreach ($dirs as $dir) {
            if (preg_replace('/^\d+\./', '', basename($dir)) === $slug) {
                return $dir;
            }
        }
        return null;
    }

    private function convertGravNotices(string $content): string
    {
        $map = [
            '!!!' => '[!CAUTION]',
            '!!'  => '[!WARNING]',
            '!'   => '[!TIP]',
        ];

        foreach ($map as $prefix => $label) {
            $esc     = preg_quote($prefix, '/');
            $content = preg_replace_callback(
                '/^(' . $esc . '\s+.+(?:\n' . $esc . '\s+.+)*)/m',
                function ($m) use ($prefix, $label) {
                    $lines = explode("\n", $m[1]);
                    $out   = '> ' . $label;
                    foreach ($lines as $line) {
                        $out .= "\n> " . ltrim(substr($line, strlen($prefix)));
                    }
                    return $out;
                },
                $content
            );
        }

        return $content;
    }

    private function rewriteAssetUrls(string $content, string $module, string $pageDir, string $docsDir, string $assetBaseUrl): string
    {
        $baseUrl = rtrim($assetBaseUrl, '&') . '&module=' . rawurlencode($module) . '&asset=';

        $resolve = function (string $path) use ($pageDir, $docsDir, $baseUrl): ?string {
            $abs    = $pageDir . '/' . ltrim($path, '/');
            $isAbs  = str_starts_with($abs, '/');
            $parts  = [];
            foreach (explode('/', $abs) as $p) {
                if ($p === '..') {
                    array_pop($parts);
                } elseif ($p !== '' && $p !== '.') {
                    $parts[] = $p;
                }
            }
            $resolved = ($isAbs ? '/' : '') . implode('/', $parts);
            $prefix   = rtrim($docsDir, '/') . '/';
            if (!str_starts_with($resolved, $prefix)) return null;
            return $baseUrl . substr($resolved, strlen($prefix));
        };

        $content = preg_replace_callback(
            '#\(([^)]*_assets/[^)]+)\)#',
            fn($m) => ($u = $resolve($m[1])) ? '(' . $u . ')' : $m[0],
            $content
        );
        $content = preg_replace_callback(
            '#\bsrc="([^"]*_assets/[^"]+)"#',
            fn($m) => ($u = $resolve($m[1])) ? 'src="' . $u . '"' : $m[0],
            $content
        );

        return $content;
    }

    private function rewriteRelativeLinks(string $content, string $currentDir): string
    {
        $lcMap = [];
        foreach ($this->getModuleMap() as $id => $dir) {
            $lcMap[strtolower($id)] = $id;
        }

        return preg_replace_callback(
            '~(!?)\[([^\]]*)\]\(([^)\s]+)\)~',
            function ($m) use ($currentDir, $lcMap) {
                [$full, $imgPrefix, $text, $url] = $m;

                if ($imgPrefix === '!') return $full;

                $fragment = '';
                if (($pos = strpos($url, '#')) !== false) {
                    $fragment = substr($url, $pos);
                    $url      = substr($url, 0, $pos);
                }

                if ($url === '') return $full;
                if (preg_match('~^(https?://|//)~', $url)) return $full;

                $parts    = $url[0] === '/' ? [] : array_values(array_filter(explode('/', rtrim($currentDir, '/'))));
                $segments = array_filter(explode('/', $url), fn($s) => $s !== '');

                foreach ($segments as $seg) {
                    if ($seg === '.') continue;
                    if ($seg === '..') { array_pop($parts); continue; }
                    $seg = preg_replace(['/^\d+\./', '/\.md$/i'], ['', ''], $seg);
                    if (isset($lcMap[strtolower($seg)])) {
                        $canonical = $lcMap[strtolower($seg)];
                        if (empty($parts)) {
                            $parts[] = $canonical;
                        } elseif (count($parts) === 1) {
                            $parts[0] = $canonical;
                        } else {
                            $parts[] = $canonical;
                        }
                    } else {
                        $parts[] = $seg;
                    }
                }

                if (empty($parts)) return $full;

                return '[' . $text . '](/' . implode('/', $parts) . '/' . $fragment . ')';
            },
            $content
        );
    }

    private function parseFrontmatterTitle(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if (preg_match('/^---\s*\n.*?^title:\s*(.+?)$/ms', $content, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
