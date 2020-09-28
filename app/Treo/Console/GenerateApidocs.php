<?php

declare(strict_types=1);

namespace Treo\Console;

/**
 * Class GenerateApidocs
 *
 * @author r.ratsun r.ratsun@zinitsolutions.com
 */
class GenerateApidocs extends AbstractConsole
{
    /**
     * Get console command description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Generate REST API documentation.';
    }

    /**
     * Run action
     *
     * @param array $data
     */
    public function run(array $data): void
    {
        // generate
        $result = $this
            ->getContainer()
            ->get('serviceFactory')
            ->create('RestApiDocs')
            ->generateDocumentation();
        if (!empty($result)) {
            self::show('REST API documentation generated successfully', self::SUCCESS);
        } else {
            self::show('Something wrong. REST API documentation generated failed. Check log for details', self::ERROR);
        }
    }
}
